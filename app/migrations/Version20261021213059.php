<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021213059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : sécurité, tiers, projets, contrats, jalons et journal d’audit.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (username VARCHAR(60) DEFAULT NULL, action VARCHAR(40) NOT NULL, entity VARCHAR(80) NOT NULL, entity_id VARCHAR(64) DEFAULT NULL, module VARCHAR(10) DEFAULT NULL, occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, before_data JSON DEFAULT NULL, after_data JSON DEFAULT NULL, result VARCHAR(20) DEFAULT \'success\' NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, message TEXT DEFAULT NULL, id UUID NOT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_logs (entity, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_occurred_at ON audit_logs (occurred_at)');
        $this->addSql('CREATE INDEX IDX_D62F2858A76ED395 ON audit_logs (user_id)');
        $this->addSql('CREATE TABLE clients (code VARCHAR(30) NOT NULL, name VARCHAR(200) NOT NULL, client_type VARCHAR(20) NOT NULL, tax_identifier VARCHAR(60) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, country VARCHAR(80) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, contact_name VARCHAR(160) DEFAULT NULL, payment_terms_days INT DEFAULT 30 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, notes TEXT DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_by UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_clients_code ON clients (code)');
        $this->addSql('CREATE INDEX IDX_C82E74DE12AB56 ON clients (created_by)');
        $this->addSql('CREATE TABLE fiscal_years (label VARCHAR(20) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) NOT NULL, closed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, closed_by UUID DEFAULT NULL, created_by UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_fiscal_years_label ON fiscal_years (label)');
        $this->addSql('CREATE INDEX IDX_2716073C88F6E01 ON fiscal_years (closed_by)');
        $this->addSql('CREATE INDEX IDX_2716073CDE12AB56 ON fiscal_years (created_by)');
        $this->addSql('CREATE TABLE permissions (code VARCHAR(100) NOT NULL, label VARCHAR(180) NOT NULL, module VARCHAR(20) DEFAULT NULL, description TEXT DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_permissions_code ON permissions (code)');
        $this->addSql('CREATE TABLE project_milestones (label VARCHAR(200) NOT NULL, planned_date DATE DEFAULT NULL, amount_ht NUMERIC(18, 3) DEFAULT \'0.000\' NOT NULL, status VARCHAR(20) NOT NULL, description TEXT DEFAULT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_milestones_project ON project_milestones (project_id)');
        $this->addSql('CREATE TABLE project_types (code VARCHAR(30) NOT NULL, label VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, sort_order INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_types_code ON project_types (code)');
        $this->addSql('CREATE TABLE projects (code VARCHAR(30) NOT NULL, name VARCHAR(200) NOT NULL, contract_reference VARCHAR(100) DEFAULT NULL, tender_reference VARCHAR(100) DEFAULT NULL, status VARCHAR(40) NOT NULL, signed_on DATE DEFAULT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, planned_delivery_date DATE DEFAULT NULL, currency_code VARCHAR(3) DEFAULT \'TND\' NOT NULL, contract_amount_ht NUMERIC(18, 3) DEFAULT \'0.000\' NOT NULL, vat_rate NUMERIC(9, 4) DEFAULT \'0.0000\' NOT NULL, vat_amount NUMERIC(18, 3) DEFAULT \'0.000\' NOT NULL, contract_amount_ttc NUMERIC(18, 3) DEFAULT \'0.000\' NOT NULL, budget_amount NUMERIC(18, 3) DEFAULT \'0.000\' NOT NULL, payment_terms VARCHAR(255) DEFAULT NULL, probability INT DEFAULT NULL, notes TEXT DEFAULT NULL, closed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, client_id UUID NOT NULL, project_type_id UUID NOT NULL, manager_user_id UUID DEFAULT NULL, closed_by UUID DEFAULT NULL, created_by UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_projects_status ON projects (status)');
        $this->addSql('CREATE INDEX idx_projects_client ON projects (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_projects_code ON projects (code)');
        $this->addSql('CREATE INDEX IDX_5C93B3A4535280F6 ON projects (project_type_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A419A3E683 ON projects (manager_user_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A488F6E01 ON projects (closed_by)');
        $this->addSql('CREATE INDEX IDX_5C93B3A4DE12AB56 ON projects (created_by)');
        $this->addSql('CREATE TABLE project_members (project_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (project_id, user_id))');
        $this->addSql('CREATE INDEX IDX_D3BEDE9A166D1F9C ON project_members (project_id)');
        $this->addSql('CREATE INDEX IDX_D3BEDE9AA76ED395 ON project_members (user_id)');
        $this->addSql('CREATE TABLE roles (code VARCHAR(60) NOT NULL, label VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, is_system BOOLEAN DEFAULT false NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_roles_code ON roles (code)');
        $this->addSql('CREATE TABLE role_permissions (role_id UUID NOT NULL, permission_id UUID NOT NULL, PRIMARY KEY (role_id, permission_id))');
        $this->addSql('CREATE INDEX IDX_1FBA94E6D60322AC ON role_permissions (role_id)');
        $this->addSql('CREATE INDEX IDX_1FBA94E6FED90CCA ON role_permissions (permission_id)');
        $this->addSql('CREATE TABLE suppliers (code VARCHAR(30) NOT NULL, name VARCHAR(200) NOT NULL, tax_identifier VARCHAR(60) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, country VARCHAR(80) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, service_category VARCHAR(120) DEFAULT NULL, payment_terms_days INT DEFAULT 30 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, notes TEXT DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_by UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_suppliers_code ON suppliers (code)');
        $this->addSql('CREATE INDEX IDX_AC28B95CDE12AB56 ON suppliers (created_by)');
        $this->addSql('CREATE TABLE users (username VARCHAR(60) NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(160) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, last_login_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_username ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
        $this->addSql('CREATE TABLE user_roles (user_id UUID NOT NULL, role_id UUID NOT NULL, PRIMARY KEY (user_id, role_id))');
        $this->addSql('CREATE INDEX IDX_54FCD59FA76ED395 ON user_roles (user_id)');
        $this->addSql('CREATE INDEX IDX_54FCD59FD60322AC ON user_roles (role_id)');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE clients ADD CONSTRAINT FK_C82E74DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE fiscal_years ADD CONSTRAINT FK_2716073C88F6E01 FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE fiscal_years ADD CONSTRAINT FK_2716073CDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_milestones ADD CONSTRAINT FK_51FAB6166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A419EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4535280F6 FOREIGN KEY (project_type_id) REFERENCES project_types (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A419A3E683 FOREIGN KEY (manager_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A488F6E01 FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_D3BEDE9A166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_members ADD CONSTRAINT FK_D3BEDE9AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6FED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_AC28B95CDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE');

        // Compteurs de références métier (PRJ-2026-0001, FAC-2026-0042…).
        // Mis à jour de façon atomique par App\Service\ReferenceGenerator.
        $this->addSql('CREATE TABLE reference_sequences (sequence_key VARCHAR(40) NOT NULL, last_number INT DEFAULT 0 NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (sequence_key))');

        // Unicité insensible à la casse des identifiants et courriels (§5.4).
        $this->addSql('CREATE UNIQUE INDEX uniq_users_username_lower ON users (LOWER(username))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email_lower ON users (LOWER(email))');

        // Garde-fous de cohérence financière : aucun montant négatif, TVA bornée.
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT chk_projects_amounts CHECK (contract_amount_ht >= 0 AND budget_amount >= 0 AND vat_amount >= 0 AND contract_amount_ttc >= 0 AND vat_rate >= 0 AND vat_rate <= 100)');
        $this->addSql('ALTER TABLE project_milestones ADD CONSTRAINT chk_milestones_amount CHECK (amount_ht >= 0)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reference_sequences');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858A76ED395');
        $this->addSql('ALTER TABLE clients DROP CONSTRAINT FK_C82E74DE12AB56');
        $this->addSql('ALTER TABLE fiscal_years DROP CONSTRAINT FK_2716073C88F6E01');
        $this->addSql('ALTER TABLE fiscal_years DROP CONSTRAINT FK_2716073CDE12AB56');
        $this->addSql('ALTER TABLE project_milestones DROP CONSTRAINT FK_51FAB6166D1F9C');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A419EB6921');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A4535280F6');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A419A3E683');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A488F6E01');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A4DE12AB56');
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT FK_D3BEDE9A166D1F9C');
        $this->addSql('ALTER TABLE project_members DROP CONSTRAINT FK_D3BEDE9AA76ED395');
        $this->addSql('ALTER TABLE role_permissions DROP CONSTRAINT FK_1FBA94E6D60322AC');
        $this->addSql('ALTER TABLE role_permissions DROP CONSTRAINT FK_1FBA94E6FED90CCA');
        $this->addSql('ALTER TABLE suppliers DROP CONSTRAINT FK_AC28B95CDE12AB56');
        $this->addSql('ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FA76ED395');
        $this->addSql('ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FD60322AC');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE fiscal_years');
        $this->addSql('DROP TABLE permissions');
        $this->addSql('DROP TABLE project_milestones');
        $this->addSql('DROP TABLE project_types');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE project_members');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE user_roles');
    }
}
