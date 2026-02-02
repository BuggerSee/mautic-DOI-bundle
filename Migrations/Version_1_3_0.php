<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_3_0 extends AbstractMigration
{
    private string $formDoiConfigTable = 'form_doi_config';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $tableName = $this->concatPrefix($this->formDoiConfigTable);

            if (!$schema->hasTable($tableName)) {
                return false;
            }

            $table = $schema->getTable($tableName);

            return !$table->hasColumn('delete_after_timeout_days');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->formDoiConfigTable)}` ADD delete_after_timeout_days INT DEFAULT NULL");
    }
}
