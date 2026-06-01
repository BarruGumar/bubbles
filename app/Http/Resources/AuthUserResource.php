<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'username'          => $this->username,
            'bio'               => $this->bio,
            'avatar'            => $this->avatar,
            'banner'            => $this->banner,
            'avatar_color'      => $this->avatar_color,
            'role'              => $this->role,
            'email_verified_at' => $this->email_verified_at,
        ];
    }
}
