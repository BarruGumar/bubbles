<?php

namespace App\Http\Controllers;

use App\Models\Post;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
            'image'   => ['nullable', 'image', 'max:4096'],
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->storeImage($request->file('image'), 'bubbles/profile-posts', [
                'transformation' => ['width' => 1200, 'height' => 800, 'crop' => 'limit', 'fetch_format' => 'auto', 'quality' => 'auto'],
            ]);
        }

        $request->user()->posts()->create([
            'content' => $request->content,
            'image'   => $imageUrl,
        ]);

        return back();
    }

    public function destroy(Post $post): RedirectResponse
    {
        if ($post->user_id !== auth()->id()) {
            abort(403);
        }

        $post->delete();

        return back();
    }

    private function storeImage($file, string $folder, array $cloudinaryOptions = []): string
    {
        $key = env('CLOUDINARY_API_KEY', '');
        if (!empty($key) && $key !== 'API_KEY') {
            return Cloudinary::upload($file->getRealPath(), array_merge(
                ['folder' => $folder, 'fetch_format' => 'auto', 'quality' => 'auto'],
                $cloudinaryOptions
            ))->getSecurePath();
        }

        $path = $file->store($folder, 'public');

        return '/storage/' . $path;
    }
}
