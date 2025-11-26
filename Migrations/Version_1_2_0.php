<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_2_0 extends AbstractMigration
{
    private string $table = 'form_doi_actions_conditions';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->hasTable($this->concatPrefix($this->table));
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("CREATE TABLE `{$this->concatPrefix($this->table)}`
(
    action_id INT NOT NULL,
    conditions LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    PRIMARY KEY (action_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_409C900F9D32F035 FOREIGN KEY (action_id) REFERENCES {$this->concatPrefix('form_doi_actions')} (id) ON DELETE CASCADE;");
    }
}
