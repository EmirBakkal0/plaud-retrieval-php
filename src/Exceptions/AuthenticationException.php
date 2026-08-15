<?php

declare(strict_types=1);

namespace Plaud\Exceptions;

/**
 * Thrown when authentication fails (invalid credentials, missing tokens, invalid JWT, etc.).
 */
class AuthenticationException extends PlaudException
{
}
