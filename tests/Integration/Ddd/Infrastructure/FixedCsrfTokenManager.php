<?php

declare(strict_types=1);

namespace Nowo\PwaBundle\Tests\Integration\Ddd\Infrastructure;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Test double: accepts the token value "valid" for every id. */
final class FixedCsrfTokenManager implements CsrfTokenManagerInterface
{
    public const VALUE = 'valid';

    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, self::VALUE);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return $token->getValue() === self::VALUE;
    }
}
