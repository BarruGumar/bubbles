<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function storePost(Request $request, Post $post): RedirectResponse
    {
        $request->validate(['content' => 'required|string|max:500']);
        $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);
        return back();
    }

    public function storeCommunityPost(Request $request, CommunityPost $post): RedirectResponse
    {
        $request->validate(['content' => 'required|string|max:500']);
        $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);
        return back();
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === auth()->id(), 403);
        $comment->delete();
        return back();
    }
}
