<?php

namespace App\Http\Controllers;

use App\Events\BadgeCountUpdated;
use App\Events\NotificationCreated;
use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Notifications\CommentReplied;
use App\Notifications\PostCommented;
use App\Services\AuditLogger;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    use FormatsPostResponse;

    public function storePost(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $request->validate(['content' => 'required|string|max:500']);
        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->input('content'),
        ]);

        AuditLogger::log('comment.created', 'content', $comment, [
            'post_id' => $post->id,
        ]);

        if ($post->user_id !== auth()->id()) {
            $notif = new PostCommented(auth()->user(), $post->id, $request->input('content'), 'post');
            $post->user->notify($notif);
            $notifData = $notif->toArray($post->user);
            broadcast(new BadgeCountUpdated($post->user->id, 'notifications', 1));
            broadcast(new NotificationCreated($post->user->id, [
                'id'         => (string) Str::uuid(),
                'type'       => $notifData['type'],
                'message'    => $notifData['message'],
                'data'       => $notifData,
                'read'       => false,
                'created_at' => 'agora',
                'url'        => $notifData['url'] ?? null,
            ]));
        }

        return $this->postResponse();
    }

    public function storeCommunityPost(Request $request, CommunityPost $post): RedirectResponse|JsonResponse
    {
        $user = $request->user();

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
            $notif = new PostCommented(auth()->user(), $post->id, $request->input('content'), 'community_post', $post->bubble_id);
            $post->user->notify($notif);
            $notifData = $notif->toArray($post->user);
            broadcast(new BadgeCountUpdated($post->user->id, 'notifications', 1));
            broadcast(new NotificationCreated($post->user->id, [
                'id'         => (string) Str::uuid(),
                'type'       => $notifData['type'],
                'message'    => $notifData['message'],
                'data'       => $notifData,
                'read'       => false,
                'created_at' => 'agora',
                'url'        => $notifData['url'] ?? null,
            ]));
        }

        return $this->postResponse();
    }

    public function storeReply(Request $request, Comment $parent): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if($parent->parent_comment_id !== null, 422, 'Não podes responder a uma resposta.');

        $request->validate(['content' => 'required|string|max:500']);

        $reply = $parent->commentable->comments()->create([
            'user_id'           => auth()->id(),
            'content'           => $request->input('content'),
            'parent_comment_id' => $parent->id,
        ]);

        AuditLogger::log('comment.created', 'content', $reply, [
            'post_id'           => $parent->commentable_id,
            'parent_comment_id' => $parent->id,
        ]);

        $parentAuthor = $parent->user;
        if ($parentAuthor && $parentAuthor->id !== auth()->id()) {
            $isCommunityPost = $parent->commentable instanceof CommunityPost;
            $notif = new CommentReplied(
                auth()->user(),
                $parent->commentable_id,
                $request->input('content'),
                $isCommunityPost ? 'community_post' : 'post',
                $isCommunityPost ? $parent->commentable->bubble_id : null,
            );
            $parentAuthor->notify($notif);
            $notifData = $notif->toArray($parentAuthor);
            broadcast(new BadgeCountUpdated($parentAuthor->id, 'notifications', 1));
            broadcast(new NotificationCreated($parentAuthor->id, [
                'id'         => (string) Str::uuid(),
                'type'       => $notifData['type'],
                'message'    => $notifData['message'],
                'data'       => $notifData,
                'read'       => false,
                'created_at' => 'agora',
                'url'        => $notifData['url'] ?? null,
            ]));
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
