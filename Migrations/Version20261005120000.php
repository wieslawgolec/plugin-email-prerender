<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_prerender_cache table (payload + tokens JSON) for Email Pre-Render Plugin';
    }

    public function up(Schema $schema): void
    {
        $table = MAUTIC_TABLE_PREFIX.'email_prerender_cache';

        $this->addSql('CREATE TABLE IF NOT EXISTS '.$table.' (
            id INT UNSIGNED AUTO_INCREMENT NOT NULL,
            email_id INT NOT NULL,
            contact_id INT NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            contact_hash VARCHAR(64) NOT NULL,
            subject LONGTEXT NOT NULL,
            html LONGTEXT NOT NULL,
            plain_text LONGTEXT DEFAULT NULL,
            tokens JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            INDEX idx_email_id (email_id),
            INDEX idx_expires (expires_at),
            INDEX uniq_email_contact_hash (email_id, contact_id, content_hash, contact_hash)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS '.MAUTIC_TABLE_PREFIX.'email_prerender_cache');
    }
}
