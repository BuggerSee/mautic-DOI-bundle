<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Unit\Service;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerDoiBundle\Doi\LastDoiDateField;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\LastDoiDateFieldInstaller;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LastDoiDateFieldInstallerTest extends TestCase
{
    /** @var FieldModel&MockObject */
    private FieldModel $fieldModel;

    /** @var TranslatorInterface&MockObject */
    private TranslatorInterface $translator;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private LastDoiDateFieldInstaller $installer;

    protected function setUp(): void
    {
        $this->fieldModel  = $this->createMock(FieldModel::class);
        $this->translator  = $this->createMock(TranslatorInterface::class);
        $this->logger      = $this->createMock(LoggerInterface::class);
        $this->installer   = new LastDoiDateFieldInstaller($this->fieldModel, $this->translator, $this->logger);
    }

    public function testInstallIfMissingSkipsWhenFieldAlreadyExists(): void
    {
        $this->fieldModel->expects($this->once())
            ->method('getEntityByAlias')
            ->with(LastDoiDateField::ALIAS)
            ->willReturn(new LeadField());

        $this->fieldModel->expects($this->never())->method('saveEntity');
        $this->translator->expects($this->never())->method('trans');

        $this->installer->installIfMissing();
    }

    public function testInstallIfMissingCreatesDatetimeField(): void
    {
        $this->fieldModel->expects($this->once())
            ->method('getEntityByAlias')
            ->with(LastDoiDateField::ALIAS)
            ->willReturn(null);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with(LastDoiDateField::LABEL_TRANSLATION_KEY)
            ->willReturn('Last DOI Date');

        $this->fieldModel->expects($this->once())
            ->method('saveEntity')
            ->with($this->callback(function (LeadField $field): bool {
                return LastDoiDateField::ALIAS === $field->getAlias()
                    && 'datetime' === $field->getType()
                    && 'lead' === $field->getObject()
                    && 'Last DOI Date' === $field->getLabel()
                    && $field->getIsListable();
            }));

        $this->installer->installIfMissing();
    }

    public function testInstallIfMissingLogsErrorWhenSaveFails(): void
    {
        $this->fieldModel->method('getEntityByAlias')->willReturn(null);
        $this->translator->method('trans')->willReturn('Last DOI Date');
        $this->fieldModel->method('saveEntity')->willThrowException(new \RuntimeException('save failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to install last_doi_date contact field',
                $this->callback(static fn (array $context): bool => 'save failed' === $context['error'])
            );

        $this->installer->installIfMissing();
    }
}
