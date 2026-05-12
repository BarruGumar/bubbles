<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\Post;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LikeController extends Controller
{
    use FormatsPostResponse;

    public function togglePost(Post $post): RedirectResponse|JsonResponse
    {
        $this->toggle($post);
        return $this->postResponse();
    }

    public function toggleCommunityPost(CommunityPost $post): RedirectResponse|JsonResponse
    {
        $this->toggle($post);
        return $this->postResponse();
    }

    private function toggle($model): void
    {
        $like = $model->likes()->where('user_id', auth()->id())->first();
        $like ? $like->delete() : $model->likes()->create(['user_id' => auth()->id()]);
    }
}
