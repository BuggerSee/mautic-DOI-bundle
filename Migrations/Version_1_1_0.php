<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_1_0 extends AbstractMigration
{
    private string $formDoiSubmissionsTable = 'form_doi_submissions';
    private string $formDoiConfigTable      = 'form_doi_config';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $submissionsTableName = $this->concatPrefix($this->formDoiSubmissionsTable);
            $configTableName      = $this->concatPrefix($this->formDoiConfigTable);

            if (!$schema->hasTable($submissionsTableName) || !$schema->hasTable($configTableName)) {
                return false;
            }

            $submissions = $schema->getTable($submissionsTableName);
            $config      = $schema->getTable($configTableName);

            $needsSubmissionsCols = !($submissions->hasColumn('verification_skipped')
                && $submissions->hasColumn('skip_reason')
                && $submissions->hasColumn('browser_proof_token'));

            $needsSubmissionsIndex = !$submissions->hasIndex('UNIQ_E6D188B11F48B612');

            $needsConfigCols = !($config->hasColumn('skip_conditions')
                && $config->hasColumn('skip_on_cookie')
                && $config->hasColumn('skip_post_action')
                && $config->hasColumn('skip_post_action_property'));

            return $needsSubmissionsCols || $needsSubmissionsIndex || $needsConfigCols;
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $submissions = $this->concatPrefix($this->formDoiSubmissionsTable);
        $config      = $this->concatPrefix($this->formDoiConfigTable);

        // Add columns to form_doi_submissions
        $this->addSql("ALTER TABLE `{$submissions}` ADD verification_skipped TINYINT(1) DEFAULT 0 NOT NULL, ADD skip_reason VARCHAR(50) DEFAULT NULL, ADD browser_proof_token VARCHAR(255) DEFAULT NULL");

        // Create a unique index on browser_proof_token
        $this->addSql("CREATE UNIQUE INDEX UNIQ_E6D188B11F48B612 ON `{$submissions}` (browser_proof_token)");

        // Add columns to form_doi_config
        $this->addSql("ALTER TABLE `{$config}` ADD skip_conditions LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)', ADD skip_on_cookie TINYINT(1) DEFAULT 0 NOT NULL, ADD skip_post_action VARCHAR(255) DEFAULT NULL, ADD skip_post_action_property LONGTEXT DEFAULT NULL");
    }

    protected function down(): void
    {
        $submissions = $this->concatPrefix($this->formDoiSubmissionsTable);
        $config      = $this->concatPrefix($this->formDoiConfigTable);

        // Drop index from form_doi_submissions
        $this->addSql("DROP INDEX UNIQ_E6D188B11F48B612 ON `{$submissions}`");

        // Drop columns from form_doi_submissions
        $this->addSql(
            "ALTER TABLE `{$submissions}` 
            DROP COLUMN verification_skipped,
            DROP COLUMN skip_reason,
            DROP COLUMN browser_proof_token"
        );

        // Drop columns from form_doi_config
        $this->addSql(
            "ALTER TABLE `{$config}` 
            DROP COLUMN skip_conditions, 
            DROP COLUMN skip_on_cookie, 
            DROP COLUMN skip_post_action, 
            DROP COLUMN skip_post_action_property"
        );
    }
}
