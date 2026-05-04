<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use App\Events\BubbleCreated;
use App\Events\BubbleMoved;
use App\Models\Bubble;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BubbleController extends Controller
{
    public function index(): JsonResponse
    {
        $bubbles = Bubble::query()->latest('id')->get();

        return response()->json($bubbles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'label' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:40'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'size' => ['nullable', 'integer', 'min:40', 'max:220'],
            'members' => ['nullable', 'array'],
        ]);

        $bubble = Bubble::create($data);

        event(new BubbleCreated($bubble));

        return response()->json($bubble, 201);
    }

    public function update(Request $request, Bubble $bubble): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'x' => ['sometimes', 'required', 'numeric'],
            'y' => ['sometimes', 'required', 'numeric'],
            'size' => ['sometimes', 'nullable', 'integer', 'min:40', 'max:220'],
            'members' => ['sometimes', 'nullable', 'array'],
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
=======
use Illuminate\Http\Request;
use App\Models\Bubble;

class BubbleController extends Controller
{
    public function index()
    {
        return Bubble::all();
    }

    public function store(Request $request)
    {
        $bubble = Bubble::create([
            'user_id' => 1,
            'label' => $request->label,
            'color' => $request->color,
            'x' => $request->x,
            'y' => $request->y,
            'size' => $request->size ?? 80,
            'members' => 0,
        ]);

        return response()->json($bubble);
    }
}
>>>>>>> da5b16d (merda)
