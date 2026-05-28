<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function storePost(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);

        $report = Report::updateOrCreate(
            [
                'reporter_id' => auth()->id(),
                'reportable_type' => Post::class,
                'reportable_id' => $post->id,
            ],
            ['reason' => $data['reason'], 'status' => 'pending']
        );

        AuditLogger::log('report.submitted', 'moderation', $report, [
            'reportable_type' => 'Post',
            'post_id' => $post->id,
        ]);

        return response()->json(['message' => 'Denúncia enviada.']);
    }

    public function storeCommunityPost(Request $request, CommunityPost $post): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);

        $report = Report::updateOrCreate(
            [
                'reporter_id' => auth()->id(),
                'reportable_type' => CommunityPost::class,
                'reportable_id' => $post->id,
            ],
            ['reason' => $data['reason'], 'status' => 'pending']
        );

        AuditLogger::log('report.submitted', 'moderation', $report, [
            'reportable_type' => 'CommunityPost',
            'post_id' => $post->id,
        ]);

        return response()->json(['message' => 'Denúncia enviada.']);
    }

    public function storeUser(Request $request, User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Não podes denunciar-te a ti próprio.'], 422);
        }

        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);

        $report = Report::updateOrCreate(
            [
                'reporter_id'     => auth()->id(),
                'reportable_type' => User::class,
                'reportable_id'   => $user->id,
            ],
            ['reason' => $data['reason'], 'status' => 'pending']
        );

        AuditLogger::log('report.submitted', 'moderation', $report, [
            'reportable_type' => 'User',
            'user_id'         => $user->id,
        ]);

        return response()->json(['message' => 'Denúncia enviada.']);
    }

    public function storeCommunity(Request $request, int $id): JsonResponse
    {
        $bubble = Bubble::findOrFail($id);

        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);

        $report = Report::updateOrCreate(
            [
                'reporter_id'     => auth()->id(),
                'reportable_type' => Bubble::class,
                'reportable_id'   => $bubble->id,
            ],
            ['reason' => $data['reason'], 'status' => 'pending']
        );

        AuditLogger::log('report.submitted', 'moderation', $report, [
            'reportable_type' => 'Community',
            'community_id'    => $bubble->id,
        ]);

        return response()->json(['message' => 'Denúncia enviada.']);
    }
}
