<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_0_0_3 extends AbstractMigration
{
    private string $formDoiSubmissionsTable = 'form_doi_submissions';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->hasTable($this->concatPrefix($this->formDoiSubmissionsTable));
        } catch (SchemaException $e) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("CREATE TABLE `{$this->concatPrefix($this->formDoiSubmissionsTable)}`
(
    id                 INT AUTO_INCREMENT NOT NULL,
    form_submission_id BIGINT UNSIGNED    NOT NULL,
    form_id            INT UNSIGNED       NOT NULL,
    lead_id            BIGINT UNSIGNED DEFAULT NULL,
    email              VARCHAR(255)       NOT NULL,
    hash               VARCHAR(255)       NOT NULL,
    date_created       DATETIME           NOT NULL,
    date_confirmed     DATETIME        DEFAULT NULL,
    date_expired       DATETIME        DEFAULT NULL,
    status             VARCHAR(20)        NOT NULL,
    UNIQUE INDEX UNIQ_E6D188B1D1B862B8 (hash),
    INDEX IDX_E6D188B1422B0E0C (form_submission_id),
    INDEX IDX_E6D188B15FF69B7D (form_id),
    INDEX IDX_E6D188B155458D (lead_id),
    INDEX form_doi_submission_hash_search (hash),
    INDEX form_doi_submission_status_search (status),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->formDoiSubmissionsTable)}` ADD CONSTRAINT FK_E6D188B1422B0E0C FOREIGN KEY (form_submission_id) REFERENCES form_submissions (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->formDoiSubmissionsTable)}` ADD CONSTRAINT FK_E6D188B15FF69B7D FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->formDoiSubmissionsTable)}` ADD CONSTRAINT FK_E6D188B155458D FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL");
    }
}
