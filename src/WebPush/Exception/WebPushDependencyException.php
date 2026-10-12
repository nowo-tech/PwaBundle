<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\WebPush\Exception;

use LogicException;

/**
 * A runtime dependency of Web Push sending is missing (minishlink/web-push, PSR-17 factories or a PSR-18 client).
 */
final class WebPushDependencyException extends LogicException
{
}
