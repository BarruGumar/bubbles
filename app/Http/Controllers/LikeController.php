<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\Post;
use App\Notifications\PostLiked;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LikeController extends Controller
{
    use FormatsPostResponse;

    public function togglePost(Post $post): RedirectResponse|JsonResponse
    {
        $liked = $this->toggle($post);

        // Notify the post owner on new like (not on unlike, not if it's their own post)
        if ($liked && $post->user_id !== auth()->id()) {
            $post->user->notify(new PostLiked(auth()->user(), $post->id, 'post'));
        }

        return $this->postResponse();
    }

    public function toggleCommunityPost(CommunityPost $post): RedirectResponse|JsonResponse
    {
        $liked = $this->toggle($post);

        if ($liked && $post->user_id !== auth()->id()) {
            $post->user->notify(new PostLiked(auth()->user(), $post->id, 'community_post', $post->bubble_id));
        }

        return $this->postResponse();
    }

    private function toggle($model): bool
    {
        $allowed = ['like', 'love', 'laugh', 'wow', 'sad'];
        $type    = in_array(request()->input('type', 'like'), $allowed)
            ? request()->input('type', 'like')
            : 'like';

        $like = $model->likes()->where('user_id', auth()->id())->first();

        if ($like) {
            if ($like->type === $type) {
                $like->delete();
                return false;
            }
            $like->update(['type' => $type]);
            return true;
        }

        $model->likes()->create(['user_id' => auth()->id(), 'type' => $type]);
        return true;
    }
}
