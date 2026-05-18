<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    // Keys that must never appear in metadata (security)
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'remember_token',
        'secret', 'api_key', 'api_secret', 'access_token', 'refresh_token',
        'credit_card', 'cvv', 'ssn',
    ];

    public static function log(
        string $action,
        string $category,
        mixed $target = null,
        array $metadata = [],
        ?int $communityId = null
    ): AuditLog {
        $request = app(Request::class);

        $targetType   = null;
        $targetId     = null;
        $targetUserId = null;

        if ($target !== null) {
            $targetType = get_class($target);
            $targetId   = $target->getKey();

            if ($target instanceof \App\Models\User) {
                $targetUserId = $target->id;
            } elseif (isset($target->user_id)) {
                $targetUserId = $target->user_id;
            }
        }

        return AuditLog::create([
            'actor_id'      => Auth::id(),
            'action'        => $action,
            'category'      => $category,
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'target_user_id' => $targetUserId,
            'community_id'  => $communityId,
            'ip_address'    => $request->ip(),
            'user_agent'    => mb_substr($request->userAgent() ?? '', 0, 500),
            'method'        => $request->method(),
            'route_name'    => $request->route()?->getName(),
            'url'           => mb_substr($request->fullUrl(), 0, 500),
            'metadata'      => self::sanitize($metadata),
            'created_at'    => now(),
        ]);
    }

    private static function sanitize(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                continue;
            }
            if (is_string($value)) {
                $value = mb_substr($value, 0, 500);
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
