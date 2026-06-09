<?php

namespace App\Http\Controllers;

use App\Models\UserPunishment;
use Illuminate\Http\RedirectResponse;

class PunishmentController extends Controller
{
    public function acknowledge(UserPunishment $punishment): RedirectResponse
    {
        abort_if($punishment->user_id !== auth()->id(), 403);

        if ($punishment->notified_at === null) {
            $punishment->updateQuietly(['notified_at' => now()]);
        }

        return back();
    }
}
