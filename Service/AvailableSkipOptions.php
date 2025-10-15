<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use Mautic\LeadBundle\Model\ListModel;

class AvailableSkipOptions
{
    public function __construct(private ListModel $listModel)
    {
    }

    /**
     * @return mixed[]
     */
    public function getAvailableSkipOptions(): array
    {
        return $this->listModel->getChoiceFields();
    }
}
