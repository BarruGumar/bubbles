<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Services\AuditLogger;
use App\Support\ImageUploadPresets;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    use StoresImages;

    public function store(StorePostRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isBanned(), 403, 'A tua conta foi banida.');
        abort_if($user->isSuspended(), 403, 'A tua conta está suspensa.');
        abort_if($user->isGloballyMuted(), 403, 'Estás em silêncio global.');

        $imageUrl = null;
        $imagePid = null;
        $videoUrl = null;
        $videoPid = null;

        if ($request->hasFile('image')) {
            ['url' => $imageUrl, 'public_id' => $imagePid] = $this->storeImageWithMeta(
                $request->file('image'),
                'bubbles/profile-posts',
                ImageUploadPresets::post()
            );
        }

        if ($request->hasFile('video')) {
            ['url' => $videoUrl, 'public_id' => $videoPid] = $this->storeVideoWithMeta(
                $request->file('video'),
                'bubbles/profile-posts'
            );
        }

        $post = $request->user()->posts()->create([
            'content' => $request->content,
            'image' => $imageUrl,
            'image_public_id' => $imagePid,
            'video' => $videoUrl,
            'video_public_id' => $videoPid,
        ]);

        AuditLogger::log('post.created', 'content', $post, [
            'has_image' => $imageUrl !== null,
            'has_video' => $videoUrl !== null,
        ]);

        return back();
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $data = $request->validate(['content' => 'required|string|min:1|max:1000']);
        $post->update(['content' => $data['content']]);

        AuditLogger::log('post.updated', 'content', $post);

        return response()->json(['content' => $post->content]);
    }

    public function destroy(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $imagePid = $post->image_public_id;
        $videoPid = $post->video_public_id;

        AuditLogger::log('post.deleted', 'content', $post);

        $post->delete();

        // Defer Cloudinary cleanup until after the HTTP response is sent so the
        // client isn't blocked waiting for an external API call.
        app()->terminating(fn () => $this->deleteCloudinaryImage($imagePid));
        app()->terminating(fn () => $this->deleteCloudinaryVideo($videoPid));

        return response()->json(['ok' => true]);
    }
}
