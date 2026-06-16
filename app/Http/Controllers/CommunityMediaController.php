<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Services\AuditLogger;
use App\Support\StoresImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommunityMediaController extends Controller
{
    use StoresImages;

    public function uploadImage(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);

        $data = $request->validate([
            'url'       => ['required', 'string'],
            'public_id' => ['required', 'string'],
        ]);

        $this->validateCloudinaryUrl($data['url']);

        if ($bubble->community_image_public_id && $bubble->community_image_public_id !== $data['public_id']) {
            $this->deleteCloudinaryImage($bubble->community_image_public_id);
        }

        $bubble->update(['community_image' => $data['url'], 'community_image_public_id' => $data['public_id']]);

        AuditLogger::log('community.image_uploaded', 'community', $bubble, [], $bubble->id);

        return back();
    }

    public function uploadBanner(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);

        $data = $request->validate([
            'url'       => ['required', 'string'],
            'public_id' => ['required', 'string'],
        ]);

        $this->validateCloudinaryUrl($data['url']);

        if ($bubble->community_banner_public_id && $bubble->community_banner_public_id !== $data['public_id']) {
            $this->deleteCloudinaryImage($bubble->community_banner_public_id);
        }

        $bubble->update(['community_banner' => $data['url'], 'community_banner_public_id' => $data['public_id']]);

        AuditLogger::log('community.banner_uploaded', 'community', $bubble, [], $bubble->id);

        return back();
    }

    public function removeImage(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);
        $this->deleteCloudinaryImage($bubble->community_image_public_id);
        $bubble->update(['community_image' => null, 'community_image_public_id' => null]);
        AuditLogger::log('community.image_removed', 'community', $bubble, [], $bubble->id);
        return back();
    }

    public function removeBanner(int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);
        $this->deleteCloudinaryImage($bubble->community_banner_public_id);
        $bubble->update(['community_banner' => null, 'community_banner_public_id' => null]);
        AuditLogger::log('community.banner_removed', 'community', $bubble, [], $bubble->id);
        return back();
    }
}
