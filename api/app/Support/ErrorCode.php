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
    case BadRequest = 'bad_request';
    case Conflict = 'conflict';
    case CsrfMismatch = 'csrf_mismatch';
    case TooManyRequests = 'too_many_requests';
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
            self::BadRequest => 'The request could not be processed.',
            self::Conflict => 'The request conflicts with the current state.',
            self::CsrfMismatch => 'Session expired or CSRF token mismatch.',
            self::TooManyRequests => 'Too many requests. Please slow down.',
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
            $status === 409 => self::Conflict,
            $status === 419 => self::CsrfMismatch,
            $status === 422 => self::ValidationFailed,
            $status === 429 => self::TooManyRequests,
            $status === 503 => self::ServiceUnavailable,
            $status >= 500 => self::ServerError,
            default => self::BadRequest,
        };
    }
}
