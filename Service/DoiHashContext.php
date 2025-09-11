<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Service;

final class DoiHashContext
{
    private ?string $doiHash = null;
    private ?int $formId     = null;

    public function getDoiHash(): ?string
    {
        return $this->doiHash;
    }

    public function setDoiHash(?string $doiHash): DoiHashContext
    {
        $this->doiHash = $doiHash;

        return $this;
    }

    public function getFormId(): ?int
    {
        return $this->formId;
    }

    public function setFormId(?int $formId): DoiHashContext
    {
        $this->formId = $formId;

        return $this;
    }
}
