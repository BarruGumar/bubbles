<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\JsonResponse;

class ConnectionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Connection::query()->latest('id')->get());
    }
}
