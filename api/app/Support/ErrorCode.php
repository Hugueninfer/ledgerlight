<?php

namespace App\Support;

/**
 * Stable machine-readable codes for the API error contract.
 */
enum ErrorCode: string
{
    case NotFound = 'not_found';
    case Forbidden = 'forbidden';
    case Unauthenticated = 'unauthenticated';
    case ValidationFailed = 'validation_failed';
    case ServerError = 'server_error';
    case ServiceUnavailable = 'service_unavailable';

    /**
     * Fixed, safe human-readable message. Never derived from exception text.
     */
    public function message(): string
    {
        return match ($this) {
            self::NotFound => 'Resource not found.',
            self::Forbidden => 'You are not allowed to perform this action.',
            self::Unauthenticated => 'Authentication is required.',
            self::ValidationFailed => 'The given data was invalid.',
            self::ServerError => 'Something went wrong.',
            self::ServiceUnavailable => 'Service temporarily unavailable.',
        };
    }

    public static function fromStatus(int $status): self
    {
        return match (true) {
            $status === 401 => self::Unauthenticated,
            $status === 403 => self::Forbidden,
            $status === 404, $status === 405 => self::NotFound,
            $status === 422 => self::ValidationFailed,
            $status === 503 => self::ServiceUnavailable,
            default => self::ServerError,
        };
    }
}
