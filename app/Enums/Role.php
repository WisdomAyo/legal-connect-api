<?php

namespace App\Enums;

enum Role: string
{
    case Lawyer = 'lawyer';
    case Client = 'client';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Lawyer => 'Legal Practitioner',
            self::Admin => 'Administrator',
        };
    }

    public function permissions(): array
    {
        return match ($this) {
            self::Client => ['view-lawyers', 'book-consultation', 'message-lawyer'],
            self::Lawyer => ['manage-profile', 'view-clients', 'manage-cases', 'set-availability'],
            self::Admin => ['manage-users', 'verify-lawyers', 'view-reports', 'manage-platform'],
        };
    }
}
