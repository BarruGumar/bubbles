<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Notifications\CommentReplied;
use App\Notifications\PostCommented;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    use FormatsPostResponse;

    public function storePost(StoreCommentRequest $request, Post $post): RedirectResponse|JsonResponse
    {
        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->validated()['content'],
        ]);

        AuditLogger::log('comment.created', 'content', $comment, [
            'post_id' => $post->id,
        ]);

        if ($post->user_id !== auth()->id()) {
            NotificationService::send(
                $post->user,
                new PostCommented(auth()->user(), $post->id, $request->validated()['content'], 'post')
            );
        }

        return $this->postResponse();
    }

    public function storeCommunityPost(StoreCommentRequest $request, CommunityPost $post): RedirectResponse|JsonResponse
    {
        $bubble = $post->bubble;
        if ($bubble) {
            abort_if($request->user()->isBannedFromCommunity($bubble), 403, 'Estás banido desta comunidade.');
            abort_if($request->user()->isMutedInCommunity($bubble), 403, 'Estás em silêncio nesta comunidade.');
        }

        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->validated()['content'],
        ]);

        AuditLogger::log('comment.created', 'content', $comment, [
            'post_id' => $post->id,
        ], $post->bubble_id);

        if ($post->user_id !== auth()->id()) {
            NotificationService::send(
                $post->user,
                new PostCommented(auth()->user(), $post->id, $request->validated()['content'], 'community_post', $post->bubble_id)
            );
        }

        return $this->postResponse();
    }

    public function storeReply(StoreCommentRequest $request, Comment $parent): RedirectResponse|JsonResponse
    {
        abort_if($parent->parent_comment_id !== null, 422, 'Não podes responder a uma resposta.');

        $reply = $parent->commentable->comments()->create([
            'user_id'           => auth()->id(),
            'content'           => $request->validated()['content'],
            'parent_comment_id' => $parent->id,
        ]);

        AuditLogger::log('comment.created', 'content', $reply, [
            'post_id'           => $parent->commentable_id,
            'parent_comment_id' => $parent->id,
        ]);

        $parentAuthor = $parent->user;
        if ($parentAuthor && $parentAuthor->id !== auth()->id()) {
            $isCommunityPost = $parent->commentable instanceof CommunityPost;
            NotificationService::send(
                $parentAuthor,
                new CommentReplied(
                    auth()->user(),
                    $parent->commentable_id,
                    $request->validated()['content'],
                    $isCommunityPost ? 'community_post' : 'post',
                    $isCommunityPost ? $parent->commentable->bubble_id : null,
                )
            );
        }

        return $this->postResponse();
    }

    public function destroy(Comment $comment): RedirectResponse|JsonResponse
    {
        abort_unless($comment->user_id === auth()->id() || auth()->user()->isModerator(), 403);

        AuditLogger::log('comment.deleted', 'content', $comment);

        $comment->delete();

        return $this->postResponse();
    }
}
