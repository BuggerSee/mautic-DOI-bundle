<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Service;

final class DoiHashContext
{
    private ?string $doiHash = null;

    public function getDoiHash(): ?string
    {
        return $this->doiHash;
    }

    public function setDoiHash(?string $doiHash): void
    {
        $this->doiHash = $doiHash;
    }
}
