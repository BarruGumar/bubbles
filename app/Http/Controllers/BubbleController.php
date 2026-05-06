<?php

namespace App\Http\Controllers;

use App\Events\BubbleMoved;
use App\Models\Bubble;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BubbleController extends Controller
{
        public function showPage(Bubble $bubble): Response
    {
        return Inertia::render('Community/Show', [
            'bubble' => $bubble,
        ]);
    }

    public function index(): JsonResponse
    {
        $bubbles = Bubble::query()->latest('id')->get();

        return response()->json($bubbles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'                => ['nullable', 'exists:users,id'],
            'label'                  => ['required', 'string', 'max:120'],
            'color'                  => ['nullable', 'string', 'max:40'],
            'x'                      => ['required', 'numeric'],
            'y'                      => ['required', 'numeric'],
            'size'                   => ['nullable', 'integer', 'min:40', 'max:220'],
            'community_title'        => ['nullable', 'string', 'max:120'],
            'community_description'  => ['nullable', 'string', 'max:1000'],
            'community_cover_color'  => ['nullable', 'string', 'max:40'],
            'community_tagline'      => ['nullable', 'string', 'max:160'],
            'community_guidelines'   => ['nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ]);

        $bubble = Bubble::create($data);

        return response()->json(['id' => $bubble->id], 201);
    }

    public function update(Request $request, Bubble $bubble): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'x' => ['sometimes', 'required', 'numeric'],
            'y' => ['sometimes', 'required', 'numeric'],
            'size' => ['sometimes', 'nullable', 'integer', 'min:40', 'max:220'],
            'members' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'community_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'community_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'community_cover_color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'community_tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'community_guidelines' => ['sometimes', 'nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ]);

        $bubble->update($data);

        if (array_key_exists('x', $data) || array_key_exists('y', $data)) {
            event(new BubbleMoved($bubble->fresh()));
        }

        return response()->json($bubble->fresh());
    }

    public function destroy(Bubble $bubble): JsonResponse
    {
        $bubble->delete();

        return response()->json(status: 204);
    }
}
