<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCommunitySettingsRequest;
use App\Models\Bubble;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommunitySettingsController extends Controller
{
    public function update(UpdateCommunitySettingsRequest $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);

        $data = $request->validated();

        if (isset($data['color'])) {
            $data['community_cover_color'] = $data['color'];
        }

        $bubble->update($data);

        AuditLogger::log('community.settings_updated', 'community', $bubble, [
            'fields_changed' => array_keys($data),
        ], $bubble->id);

        return back()->with('status', 'community-updated');
    }

    public function destroy(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);

        AuditLogger::log('community.deleted', 'community', null, [
            'community_id' => $bubble->id,
            'label' => $bubble->label,
        ], $bubble->id);

        $bubble->delete();

        return redirect()->route('bubbles');
    }
}
