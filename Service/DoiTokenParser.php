<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Service;

final class DoiTokenParser
{
    public function encode(int $formId, string $hash): string
    {
        $tokenData    = "{$formId}:{$hash}";

        return base64_encode($tokenData);
    }

    /**
     * @return array<int,string>|null
     */
    public function decode(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if (!$decoded) {
            return null;
        }

        $parts = explode(':', $decoded);
        if (2 !== count($parts)) {
            return null;
        }

        return [(int) $parts[0], $parts[1]];
    }
}
