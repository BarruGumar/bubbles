<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

trait FormatsPostResponse
{
    protected function postResponse(string $message = 'OK'): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back();
    }
}
