<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'token',
        'filename',
        'expires_at',
    ];

    protected $dates = [
        'expires_at',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
