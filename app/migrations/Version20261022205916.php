<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022205916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paramètres société, paramètres SMTP et suivi des emails de rappel.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE company_settings (legal_name VARCHAR(180) NOT NULL, trade_name VARCHAR(180) DEFAULT NULL, tax_id VARCHAR(60) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, phone_secondary VARCHAR(40) DEFAULT NULL, address TEXT DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, country VARCHAR(120) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, logo_path VARCHAR(255) DEFAULT NULL, logo_mime_type VARCHAR(100) DEFAULT NULL, logo_original_name VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE mail_settings (is_enabled BOOLEAN DEFAULT false NOT NULL, host VARCHAR(255) NOT NULL, port INT NOT NULL, encryption VARCHAR(20) NOT NULL, username VARCHAR(180) DEFAULT NULL, password_encrypted TEXT DEFAULT NULL, sender_email VARCHAR(180) NOT NULL, sender_name VARCHAR(180) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE contract_notifications ADD email_sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE contract_notifications ADD email_attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE contract_notifications ADD email_last_attempt_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE contract_notifications ADD email_error TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_contract_notifications_email ON contract_notifications (email_sent_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE company_settings');
        $this->addSql('DROP TABLE mail_settings');
        $this->addSql('DROP INDEX idx_contract_notifications_email');
        $this->addSql('ALTER TABLE contract_notifications DROP email_sent_at');
        $this->addSql('ALTER TABLE contract_notifications DROP email_attempts');
        $this->addSql('ALTER TABLE contract_notifications DROP email_last_attempt_at');
        $this->addSql('ALTER TABLE contract_notifications DROP email_error');
    }
}
