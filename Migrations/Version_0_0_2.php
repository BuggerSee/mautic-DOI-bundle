<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_0_0_2 extends AbstractMigration
{
    private string $table = 'form_doi_config';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->hasTable($this->concatPrefix($this->table));
        } catch (SchemaException $e) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("CREATE TABLE `{$this->concatPrefix($this->table)}`
(
    id                    INT AUTO_INCREMENT NOT NULL,
    form_id               INT UNSIGNED       NOT NULL,
    verification_email_id INT UNSIGNED DEFAULT NULL,
    follow_up_email_id    INT UNSIGNED DEFAULT NULL,
    successRedirectUrl    VARCHAR(255) DEFAULT NULL,
    errorRedirectUrl      VARCHAR(255) DEFAULT NULL,
    createdAt             DATETIME           NOT NULL,
    updatedAt             DATETIME           NOT NULL,
    enabled               TINYINT(1)         NOT NULL,
    INDEX IDX_CEEA628C59FD49DB (verification_email_id),
    INDEX IDX_CEEA628CE885025B (follow_up_email_id),
    UNIQUE INDEX form_id_unique (form_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_CEEA628C5FF69B7D FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_CEEA628C59FD49DB FOREIGN KEY (verification_email_id) REFERENCES emails (id) ON DELETE RESTRICT;");
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_CEEA628CE885025B FOREIGN KEY (follow_up_email_id) REFERENCES emails (id) ON DELETE SET NULL;");
    }
}
