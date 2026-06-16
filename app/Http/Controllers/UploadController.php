<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\Conversation;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UploadController extends Controller
{
    use StoresImages;

    public function signature(Request $request): JsonResponse
    {
        abort_unless($this->cloudinaryIsConfigured(), 503, 'Cloudinary não configurado.');

        $data = $request->validate([
            'context'    => ['required', 'string', 'in:avatar,banner,community_image,community_banner,post,community_post,message,conversation_bg'],
            'context_id' => ['nullable', 'integer'],
            'is_gif'     => ['nullable', 'boolean'],
        ]);

        $context   = $data['context'];
        $contextId = isset($data['context_id']) ? (int) $data['context_id'] : null;
        $isGif     = (bool) ($data['is_gif'] ?? false);
        $user      = $request->user();
        $timestamp = now()->timestamp;

        $params              = $this->buildSignatureParams($context, $contextId, $user, $isGif);
        $params['timestamp'] = $timestamp;

        ksort($params);

        $paramString = implode('&', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($params),
            $params
        ));

        $creds     = $this->cloudinaryCredentials();
        $signature = sha1($paramString . $creds['secret']);

        return response()->json([
            'signature'  => $signature,
            'timestamp'  => $timestamp,
            'api_key'    => $creds['key'],
            'cloud_name' => $creds['cloud'],
            ...$params,
        ]);
    }

    /**
     * Resolves Cloudinary credentials whether configured via discrete
     * CLOUDINARY_KEY/SECRET/CLOUD_NAME vars or a single CLOUDINARY_URL.
     */
    private function cloudinaryCredentials(): array
    {
        $disk = config('filesystems.disks.cloudinary', []);

        $key    = $disk['key'] ?? null;
        $secret = $disk['secret'] ?? null;
        $cloud  = $disk['cloud'] ?? null;

        if ((!$key || !$secret || !$cloud) && !empty($disk['url'])) {
            $parsed = parse_url($disk['url']);
            $key    = $key ?: ($parsed['user'] ?? null);
            $secret = $secret ?: ($parsed['pass'] ?? null);
            $cloud  = $cloud ?: ($parsed['host'] ?? null);
        }

        return ['key' => $key, 'secret' => $secret, 'cloud' => $cloud];
    }

    private function buildSignatureParams(string $context, ?int $contextId, $user, bool $isGif): array
    {
        switch ($context) {
            case 'avatar':
                $p = ['folder' => 'bubbles/avatars', 'public_id' => "user_{$user->id}", 'overwrite' => '1'];
                if (!$isGif) {
                    $p['transformation'] = 'c_fill,g_face,h_300,q_auto:good,w_300';
                }
                return $p;

            case 'banner':
                $p = ['folder' => 'bubbles/banners', 'public_id' => "banner_{$user->id}", 'overwrite' => '1'];
                if (!$isGif) {
                    $p['transformation'] = 'c_fill,h_400,q_auto:good,w_1200';
                }
                return $p;

            case 'community_image':
                $bubble = Bubble::findOrFail($contextId);
                Gate::authorize('manage', $bubble);
                return [
                    'folder'         => 'bubbles/communities',
                    'public_id'      => "community_img_{$contextId}",
                    'overwrite'      => '1',
                    'transformation' => 'c_fill,h_300,q_auto:good,w_300',
                ];

            case 'community_banner':
                $bubble = Bubble::findOrFail($contextId);
                Gate::authorize('manage', $bubble);
                return [
                    'folder'         => 'bubbles/communities',
                    'public_id'      => "community_banner_{$contextId}",
                    'overwrite'      => '1',
                    'transformation' => 'c_fill,h_500,q_auto:good,w_1400',
                ];

            case 'post':
                $p = ['folder' => 'bubbles/profile-posts'];
                if (!$isGif) {
                    $p['transformation'] = 'c_limit,h_960,q_auto:good,w_1280';
                }
                return $p;

            case 'community_post':
                $p = ['folder' => 'bubbles/posts'];
                if (!$isGif) {
                    $p['transformation'] = 'c_limit,h_960,q_auto:good,w_1280';
                }
                return $p;

            case 'message':
                $p = ['folder' => 'messages'];
                if (!$isGif) {
                    $p['transformation'] = 'c_limit,h_960,q_auto:good,w_1280';
                }
                return $p;

            case 'conversation_bg':
                $p = ['folder' => 'chat_backgrounds'];
                if (!$isGif) {
                    $p['transformation'] = 'c_limit,h_1080,q_auto:good,w_1920';
                }
                return $p;

            default:
                abort(422, 'Contexto inválido.');
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_id'  => ['required', 'string'],
            'url'        => ['required', 'string'],
            'context'    => ['required', 'string', 'in:avatar,banner,community_image,community_banner,conversation_bg'],
            'context_id' => ['nullable', 'integer'],
        ]);

        $this->validateCloudinaryUrl($data['url']);

        $url      = $data['url'];
        $publicId = $data['public_id'];
        $context  = $data['context'];
        $ctxId    = isset($data['context_id']) ? (int) $data['context_id'] : null;
        $user     = $request->user();

        switch ($context) {
            case 'avatar':
                if ($user->avatar_public_id && $user->avatar_public_id !== $publicId) {
                    $this->deleteCloudinaryImage($user->avatar_public_id);
                }
                $user->update(['avatar' => $url, 'avatar_public_id' => $publicId]);
                break;

            case 'banner':
                if ($user->banner_public_id && $user->banner_public_id !== $publicId) {
                    $this->deleteCloudinaryImage($user->banner_public_id);
                }
                $user->update(['banner' => $url, 'banner_public_id' => $publicId]);
                break;

            case 'community_image':
                $bubble = Bubble::findOrFail($ctxId);
                Gate::authorize('manage', $bubble);
                if ($bubble->community_image_public_id && $bubble->community_image_public_id !== $publicId) {
                    $this->deleteCloudinaryImage($bubble->community_image_public_id);
                }
                $bubble->update(['community_image' => $url, 'community_image_public_id' => $publicId]);
                break;

            case 'community_banner':
                $bubble = Bubble::findOrFail($ctxId);
                Gate::authorize('manage', $bubble);
                if ($bubble->community_banner_public_id && $bubble->community_banner_public_id !== $publicId) {
                    $this->deleteCloudinaryImage($bubble->community_banner_public_id);
                }
                $bubble->update(['community_banner' => $url, 'community_banner_public_id' => $publicId]);
                break;

            case 'conversation_bg':
                $conversation = Conversation::findOrFail($ctxId);
                abort_unless(
                    $conversation->participants()->where('user_id', $user->id)->exists(),
                    403
                );
                $conversation->participants()->updateExistingPivot($user->id, [
                    'bg_preset'    => null,
                    'bg_image_url' => $url,
                ]);
                break;
        }

        return response()->json(compact('url', 'public_id'));
    }
}
