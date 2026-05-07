<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class LikeController extends Controller
{
    public function togglePost(Post $post): RedirectResponse
    {
        $this->toggle($post);
        return back();
    }

    public function toggleCommunityPost(CommunityPost $post): RedirectResponse
    {
        $this->toggle($post);
        return back();
    }

    private function toggle($model): void
    {
        $like = $model->likes()->where('user_id', auth()->id())->first();
        $like ? $like->delete() : $model->likes()->create(['user_id' => auth()->id()]);
    }
}
