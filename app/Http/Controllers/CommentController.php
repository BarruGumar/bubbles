<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Notifications\PostCommented;
use App\Services\AuditLogger;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use FormatsPostResponse;

    public function storePost(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if($user->isBanned(), 403, 'A tua conta foi banida.');
        abort_if($user->isSuspended(), 403, 'A tua conta está suspensa.');
        abort_if($user->isGloballyMuted(), 403, 'Estás em silêncio global.');

        $request->validate(['content' => 'required|string|max:500']);
        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        AuditLogger::log('comment.created', 'content', $comment, [
            'post_id' => $post->id,
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
        $user = $request->user();
        abort_if($user->isBanned(), 403, 'A tua conta foi banida.');
        abort_if($user->isSuspended(), 403, 'A tua conta está suspensa.');
        abort_if($user->isGloballyMuted(), 403, 'Estás em silêncio global.');

        $bubble = $post->bubble;
        if ($bubble) {
            abort_if($user->isBannedFromCommunity($bubble), 403, 'Estás banido desta comunidade.');
            abort_if($user->isMutedInCommunity($bubble), 403, 'Estás em silêncio nesta comunidade.');
        }

        $request->validate(['content' => 'required|string|max:500']);
        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        AuditLogger::log('comment.created', 'content', $comment, [
            'post_id' => $post->id,
        ], $post->bubble_id);

        if ($post->user_id !== auth()->id()) {
            $post->user->notify(new PostCommented(
                auth()->user(),
                $post->id,
                $request->input('content'),
                'community_post',
                $post->bubble_id
            ));
        }

        return $this->postResponse();
    }

    public function destroy(Comment $comment): RedirectResponse|JsonResponse
    {
        abort_unless($comment->user_id === auth()->id(), 403);

        AuditLogger::log('comment.deleted', 'content', $comment);

        $comment->delete();

        return $this->postResponse();
    }
}
