<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Connection;

class ConnectionController extends Controller
{
    public function index()
    {
        return Connection::all();
    }

    public function store(Request $request)
    {
        return Connection::create([
            'from_bubble_id' => $request->from,
            'to_bubble_id' => $request->to,
        ]);
    }
}