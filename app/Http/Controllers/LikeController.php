<?php

namespace App\Http\Controllers;

use App\Events\BadgeCountUpdated;
use App\Events\NotificationCreated;
use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Notifications\PostLiked;
use App\Support\FormatsPostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class LikeController extends Controller
{
    use FormatsPostResponse;

    public function togglePost(Post $post): RedirectResponse|JsonResponse
    {
        $liked = $this->toggle($post);

        if ($liked && $post->user_id !== auth()->id()) {
            $notif = new PostLiked(auth()->user(), $post->id, 'post');
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

    public function toggleCommunityPost(CommunityPost $post): RedirectResponse|JsonResponse
    {
        $liked = $this->toggle($post);

        if ($liked && $post->user_id !== auth()->id()) {
            $notif = new PostLiked(auth()->user(), $post->id, 'community_post', $post->bubble_id);
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

    public function toggleComment(Comment $comment): RedirectResponse|JsonResponse
    {
        $user = auth()->user();
        abort_if($user->isBanned(), 403);
        abort_if($user->isSuspended(), 403);
        abort_if($user->isGloballyMuted(), 403);

        $this->toggle($comment);

        return $this->postResponse();
    }

    public function reactorsPost(Post $post): JsonResponse
    {
        return $this->reactorsList($post);
    }

    public function reactorsCommunityPost(CommunityPost $post): JsonResponse
    {
        return $this->reactorsList($post);
    }

    private function reactorsList($model): JsonResponse
    {
        $likes = $model->likes()
            ->with('user:id,name,username,avatar,avatar_color')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'reactors' => $likes->map(fn ($like) => [
                'id' => $like->user->id,
                'name' => $like->user->name,
                'username' => $like->user->username,
                'avatar' => $like->user->avatar,
                'avatar_color' => $like->user->avatar_color ?? '#009ac7',
                'reaction_type' => $like->type,
            ]),
            'by_type' => $likes->groupBy('type')->map->count(),
        ]);
    }

    private function toggle($model): bool
    {
        $allowed = ['like', 'love', 'laugh', 'wow', 'sad'];
        $type = in_array(request()->input('type', 'like'), $allowed)
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
