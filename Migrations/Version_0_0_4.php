<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_0_0_4 extends AbstractMigration
{
    private string $formDoiSubmissionsTable = 'form_doi_submissions';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $tableName = $this->concatPrefix($this->formDoiSubmissionsTable);

            if (!$schema->hasTable($tableName)) {
                return false;
            }

            $table = $schema->getTable($tableName);

            return !$table->hasColumn('date_followup_sent');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->formDoiSubmissionsTable)}` ADD date_followup_sent DATETIME DEFAULT NULL");
    }
}
