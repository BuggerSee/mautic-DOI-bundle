<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerDoiBundle\Doi\LastDoiDateField;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LastDoiDateFieldInstaller
{
    public function __construct(
        private FieldModel $fieldModel,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public static function install(MauticFactory $factory): void
    {
        $fieldModel = $factory->getModel('lead.field');
        \assert($fieldModel instanceof FieldModel);

        $installer = new self(
            $fieldModel,
            $factory->getTranslator(),
            $factory->getLogger(true)
        );

        $installer->installIfMissing();
    }

    public function installIfMissing(): void
    {
        try {
            if ($this->fieldModel->getEntityByAlias(LastDoiDateField::ALIAS) instanceof LeadField) {
                return;
            }

            $field = new LeadField();
            $field->setLabel($this->translator->trans(LastDoiDateField::LABEL_TRANSLATION_KEY));
            $field->setAlias(LastDoiDateField::ALIAS);
            $field->setType('datetime');
            $field->setObject('lead');
            $field->setGroup('professional');
            $field->setIsListable(true);
            $field->setIsVisible(true);

            $this->fieldModel->saveEntity($field);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to install last_doi_date contact field', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
