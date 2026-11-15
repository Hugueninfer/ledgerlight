<?php

namespace App\Exceptions;

use App\Support\ErrorCode;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * The one opt-in way to give an API error a specific message and extra fields.
 *
 * Every other exception is rendered with the fixed message of its ErrorCode.
 * This one is rendered verbatim (message + extra fields merged into the error
 * contract), so its message must be written for the user by the code throwing
 * it: never pass exception text, user input or anything secret.
 */
final class ApiException extends HttpException
{
    /**
     * @param  array<string, mixed>  $extra  Additional top-level fields; the contract fields (message, code, request_id) always win.
     * @param  array<string, string>  $headers
     */
    public function __construct(
        int $status,
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly array $extra = [],
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($status, $message, $previous, $headers);
    }
}
