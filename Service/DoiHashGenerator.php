<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;

final class DoiHashGenerator
{
    public function __construct(private CoreParametersHelper $coreParametersHelper)
    {
    }

    public function generate(int $submissionId, string $email): string
    {
        $timestamp = time();
        $data      = sprintf('%d:%s:%d', $submissionId, $email, $timestamp);

        return hash_hmac('sha256', $data, $this->getSecretKey());
    }

    private function getSecretKey(): string
    {
        return $this->coreParametersHelper->get('secret_key');
    }
}
