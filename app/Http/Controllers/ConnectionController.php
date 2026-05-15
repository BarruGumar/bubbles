<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConnectionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Connection::query()->latest('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_bubble_id' => ['required', 'integer', 'exists:bubbles,id', 'different:to_bubble_id'],
            'to_bubble_id' => [
                'required',
                'integer',
                'exists:bubbles,id',
                Rule::unique('connections')->where(fn ($query) => $query
                    ->where('from_bubble_id', $request->integer('from_bubble_id'))
                    ->where('to_bubble_id', $request->integer('to_bubble_id'))),
            ],
        ]);

        $connection = Connection::create($data);

        return response()->json($connection, 201);
    }

    public function destroy(Connection $connection): JsonResponse
    {
        $userId = auth()->id();
        $ownsFrom = $connection->fromBubble?->user_id === $userId;
        $ownsTo = $connection->toBubble?->user_id === $userId;

        abort_unless($ownsFrom || $ownsTo, 403);

        $connection->delete();

        return response()->json(status: 204);
    }
}
