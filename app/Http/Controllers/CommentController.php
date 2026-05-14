<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Notifications\PostCommented;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use FormatsPostResponse;

    public function storePost(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $request->validate(['content' => 'required|string|max:500']);
        $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        if ($post->user_id !== auth()->id()) {
            $post->user->notify(new PostCommented(
                auth()->user(),
                $post->id,
                $request->input('content'),
                'post'
            ));
        }

        return $this->postResponse();
    }

    public function storeCommunityPost(Request $request, CommunityPost $post): RedirectResponse|JsonResponse
    {
        $request->validate(['content' => 'required|string|max:500']);
        $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        if ($post->user_id !== auth()->id()) {
            $post->user->notify(new PostCommented(
                auth()->user(),
                $post->id,
                $request->input('content'),
                'community_post'
            ));
        }

        return $this->postResponse();
    }

    public function destroy(Comment $comment): RedirectResponse|JsonResponse
    {
        abort_unless($comment->user_id === auth()->id(), 403);
        $comment->delete();
        return $this->postResponse();
    }
}
