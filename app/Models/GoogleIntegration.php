<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleIntegration extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'oauth_client_id' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
            'scopes' => 'array',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }
}
