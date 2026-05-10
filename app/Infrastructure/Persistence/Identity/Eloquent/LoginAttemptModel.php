<?php

namespace App\Infrastructure\Persistence\Identity\Eloquent;

use Database\Factories\LoginAttemptModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttemptModel extends Model
{
    /** @use HasFactory<\Database\Factories\LoginAttemptModelFactory> */
    use HasFactory;

    protected $table = 'user_login_attempts';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'ip_address',
        'user_agent',
        'success',
        'failure_reason',
        'created_at',
    ];

    protected $casts = [
        'success' => 'bool',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): LoginAttemptModelFactory
    {
        return LoginAttemptModelFactory::new();
    }
}
