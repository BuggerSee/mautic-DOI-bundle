<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use MauticPlugin\LeuchtfeuerDoiBundle\DTO\DoiTokenData;

final class DoiTokenParser
{
    public function encode(DoiTokenData $tokenData): string
    {
        $tokenString = "{$tokenData->formId}:{$tokenData->hash}";

        return base64_encode($tokenString);
    }

    public function decode(string $token): ?DoiTokenData
    {
        $decoded = base64_decode($token, true);
        if (false === $decoded) {
            return null;
        }

        $parts = explode(':', $decoded);
        if (2 !== count($parts)) {
            return null;
        }

        $formId = filter_var($parts[0], FILTER_VALIDATE_INT);
        if (false === $formId || $formId <= 0) {
            return null;
        }

        $hash = trim($parts[1]);
        if ('' === $hash) {
            return null;
        }

        return new DoiTokenData($formId, $hash);
    }
}
