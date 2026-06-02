<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommunityPostRequest;
use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Services\AuditLogger;
use App\Support\ImageUploadPresets;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommunityPostController extends Controller
{
    use StoresImages;

    public function store(StoreCommunityPostRequest $request, int $id): RedirectResponse
    {
        $user   = $request->user();
        $bubble = Bubble::findOrFail($id);

        $isOwner  = $bubble->user_id === $user->id;
        $isMember = $isOwner || $bubble->memberships()->where('user_id', $user->id)->where('status', 'active')->exists();
        abort_unless($isMember, 403, 'Não és membro desta comunidade.');

        abort_if($user->isBannedFromCommunity($bubble), 403, 'Estás banido desta comunidade.');
        abort_if($user->isMutedInCommunity($bubble), 403, 'Estás em silêncio nesta comunidade.');

        $imageUrl = null;
        $imagePid = null;
        $videoUrl = null;
        $videoPid = null;

        if ($request->hasFile('image')) {
            ['url' => $imageUrl, 'public_id' => $imagePid] = $this->storeImageWithMeta(
                $request->file('image'),
                'bubbles/posts',
                ImageUploadPresets::post()
            );
        }

        if ($request->hasFile('video')) {
            ['url' => $videoUrl, 'public_id' => $videoPid] = $this->storeVideoWithMeta(
                $request->file('video'),
                'bubbles/posts'
            );
        }

        $communityPost = $bubble->communityPosts()->create([
            'user_id'          => auth()->id(),
            'content'          => $request->content,
            'image'            => $imageUrl,
            'image_public_id'  => $imagePid,
            'video'            => $videoUrl,
            'video_public_id'  => $videoPid,
        ]);

        AuditLogger::log('community_post.created', 'content', $communityPost, [
            'has_image' => $imageUrl !== null,
            'has_video' => $videoUrl !== null,
        ], $bubble->id);

        return back();
    }

    public function update(Request $request, int $id, CommunityPost $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $data = $request->validate(['content' => 'required|string|min:1|max:1000']);
        $post->update(['content' => $data['content']]);

        AuditLogger::log('community_post.updated', 'content', $post, [], $id);

        return response()->json(['content' => $post->content]);
    }

    public function destroy(int $id, CommunityPost $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $imagePid = $post->image_public_id;
        $videoPid = $post->video_public_id;

        AuditLogger::log('community_post.deleted', 'content', $post, [], $id);

        $post->delete();

        app()->terminating(fn () => $this->deleteCloudinaryImage($imagePid));
        app()->terminating(fn () => $this->deleteCloudinaryVideo($videoPid));

        return response()->json(['ok' => true]);
    }
}
