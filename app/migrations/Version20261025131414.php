<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Prospects commerciaux et liens vers les fiches issues de la conversion.
 */
final class Version20261025131414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prospects commerciaux, import Excel et conversion en client/projet/contrat.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE prospects (
              reference VARCHAR(30) NOT NULL,
              name VARCHAR(200) NOT NULL,
              contact_name VARCHAR(160) DEFAULT NULL,
              email VARCHAR(180) DEFAULT NULL,
              phone VARCHAR(40) DEFAULT NULL,
              address VARCHAR(255) DEFAULT NULL,
              city VARCHAR(120) DEFAULT NULL,
              postal_code VARCHAR(20) DEFAULT NULL,
              country VARCHAR(80) DEFAULT NULL,
              tax_identifier VARCHAR(60) DEFAULT NULL,
              source VARCHAR(80) DEFAULT NULL,
              status VARCHAR(40) NOT NULL,
              estimated_amount_ht NUMERIC(18, 3) DEFAULT '0.000' NOT NULL,
              probability INT DEFAULT NULL,
              expected_close_date DATE DEFAULT NULL,
              next_action VARCHAR(255) DEFAULT NULL,
              next_action_date DATE DEFAULT NULL,
              notes TEXT DEFAULT NULL,
              converted_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                id UUID NOT NULL,
                created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                owner_user_id UUID DEFAULT NULL,
                created_by UUID DEFAULT NULL,
                converted_by UUID DEFAULT NULL,
                converted_client_id UUID DEFAULT NULL,
                converted_project_id UUID DEFAULT NULL,
                converted_contract_id UUID DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_prospects_status ON prospects (status)');
        $this->addSql('CREATE INDEX idx_prospects_email ON prospects (email)');
        $this->addSql('CREATE INDEX idx_prospects_name_city ON prospects (name, city)');
        $this->addSql('CREATE INDEX idx_prospects_owner ON prospects (owner_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_prospects_reference ON prospects (reference)');
        $this->addSql('CREATE INDEX IDX_35730C06DE12AB56 ON prospects (created_by)');
        $this->addSql('CREATE INDEX IDX_35730C06248985A6 ON prospects (converted_by)');
        $this->addSql('CREATE INDEX IDX_35730C065AA408DD ON prospects (converted_client_id)');
        $this->addSql('CREATE INDEX IDX_35730C06A225EECA ON prospects (converted_project_id)');
        $this->addSql('CREATE INDEX IDX_35730C06A7CA5CCD ON prospects (converted_contract_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              prospects
            ADD
              CONSTRAINT FK_35730C062B18554A FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              prospects
            ADD
              CONSTRAINT FK_35730C06DE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              prospects
            ADD
              CONSTRAINT FK_35730C06248985A6 FOREIGN KEY (converted_by) REFERENCES users (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              prospects
            ADD
              CONSTRAINT FK_35730C065AA408DD FOREIGN KEY (converted_client_id) REFERENCES clients (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              prospects
            ADD
              CONSTRAINT FK_35730C06A225EECA FOREIGN KEY (converted_project_id) REFERENCES projects (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              prospects
            ADD
              CONSTRAINT FK_35730C06A7CA5CCD FOREIGN KEY (converted_contract_id) REFERENCES contracts (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE prospects DROP CONSTRAINT FK_35730C062B18554A');
        $this->addSql('ALTER TABLE prospects DROP CONSTRAINT FK_35730C06DE12AB56');
        $this->addSql('ALTER TABLE prospects DROP CONSTRAINT FK_35730C06248985A6');
        $this->addSql('ALTER TABLE prospects DROP CONSTRAINT FK_35730C065AA408DD');
        $this->addSql('ALTER TABLE prospects DROP CONSTRAINT FK_35730C06A225EECA');
        $this->addSql('ALTER TABLE prospects DROP CONSTRAINT FK_35730C06A7CA5CCD');
        $this->addSql('DROP TABLE prospects');
    }
}
