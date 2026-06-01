<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Services\AuditLogger;
use App\Support\ImageUploadPresets;
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
        $request->validate(['image' => 'required|image|max:2048']);

        $this->deleteCloudinaryImage($bubble->community_image_public_id);

        ['url' => $url, 'public_id' => $pid] = $this->storeImageWithMeta(
            $request->file('image'),
            'bubbles/communities',
            ImageUploadPresets::communityImage($id)
        );

        $bubble->update(['community_image' => $url, 'community_image_public_id' => $pid]);

        return back();
    }

    public function uploadBanner(Request $request, int $id): RedirectResponse
    {
        $bubble = Bubble::findOrFail($id);
        Gate::authorize('manage', $bubble);
        $request->validate(['banner' => 'required|image|max:4096']);

        $this->deleteCloudinaryImage($bubble->community_banner_public_id);

        ['url' => $url, 'public_id' => $pid] = $this->storeImageWithMeta(
            $request->file('banner'),
            'bubbles/communities',
            ImageUploadPresets::communityBanner($id)
        );

        $bubble->update(['community_banner' => $url, 'community_banner_public_id' => $pid]);

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
