<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Trainer = 'trainer';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Trainer => 'Trainer',
            self::Client => 'Client',
        };
    }
}
