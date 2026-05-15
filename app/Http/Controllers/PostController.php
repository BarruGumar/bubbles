<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
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
        $imageUrl = null;
        $imagePid = null;
        $videoUrl = null;
        $videoPid = null;

        if ($request->hasFile('image')) {
            ['url' => $imageUrl, 'public_id' => $imagePid] = $this->storeImageWithMeta(
                $request->file('image'),
                'bubbles/profile-posts',
                ['transformation' => ['width' => 1200, 'height' => 800, 'crop' => 'limit', 'fetch_format' => 'auto', 'quality' => 'auto']]
            );
        }

        if ($request->hasFile('video')) {
            ['url' => $videoUrl, 'public_id' => $videoPid] = $this->storeVideoWithMeta(
                $request->file('video'),
                'bubbles/profile-posts'
            );
        }

        $request->user()->posts()->create([
            'content' => $request->content,
            'image' => $imageUrl,
            'image_public_id' => $imagePid,
            'video' => $videoUrl,
            'video_public_id' => $videoPid,
        ]);

        return back();
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $data = $request->validate(['content' => 'required|string|min:1|max:1000']);
        $post->update(['content' => $data['content']]);

        return response()->json(['content' => $post->content]);
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $this->deleteCloudinaryImage($post->image_public_id);
        $this->deleteCloudinaryVideo($post->video_public_id);

        $post->delete();

        return back();
    }
}
