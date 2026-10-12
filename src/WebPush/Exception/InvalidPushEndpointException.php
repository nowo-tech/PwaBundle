<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush\Exception;

use InvalidArgumentException;

/**
 * The subscription endpoint is not an HTTPS URL on an allowed push service host.
 */
final class InvalidPushEndpointException extends InvalidArgumentException
{
}
