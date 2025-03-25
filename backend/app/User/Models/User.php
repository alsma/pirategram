<?php

declare(strict_types=1);

namespace App\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeOfEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function scopeOfUsername(Builder $query, string $username): Builder
    {
        return $query->where('username', $username);
    }
}
