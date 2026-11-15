<?php

namespace App\Enums;

enum Role: string
{
    case Uploader = 'uploader';
    case Reviewer = 'reviewer';
    case Admin = 'admin';

    /**
     * Whether this role grants at least the permissions of $other
     * (Uploader < Reviewer < Admin).
     */
    public function atLeast(Role $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Uploader => 'Uploader',
            self::Reviewer => 'Reviewer',
            self::Admin => 'Admin',
        };
    }

    private function rank(): int
    {
        return match ($this) {
            self::Uploader => 1,
            self::Reviewer => 2,
            self::Admin => 3,
        };
    }
}
