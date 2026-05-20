<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\Post;
use App\Models\Report;
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
}
