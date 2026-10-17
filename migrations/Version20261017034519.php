<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261017034519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B8613FECDF');
        $this->addSql('ALTER TABLE candidature ADD note_epreuve_ecrite DOUBLE PRECISION DEFAULT NULL, ADD note_epreuve_orale DOUBLE PRECISION DEFAULT NULL, CHANGE session_id session_id INT NOT NULL, CHANGE vague creneau_entretien INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B8613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id) ON DELETE RESTRICT');
        $this->addSql('CREATE UNIQUE INDEX uniq_candidature_user_session ON candidature (user_id, session_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_candidature_numero ON candidature (numero)');
        $this->addSql('ALTER TABLE session_recrutement ADD date_reference_age DATE NOT NULL, ADD age_min SMALLINT NOT NULL, ADD age_max SMALLINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_session_annee_vague ON session_recrutement (annee, numero_vague)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY FK_E33BD3B8613FECDF');
        $this->addSql('DROP INDEX uniq_candidature_user_session ON candidature');
        $this->addSql('DROP INDEX uniq_candidature_numero ON candidature');
        $this->addSql('ALTER TABLE candidature DROP note_epreuve_ecrite, DROP note_epreuve_orale, CHANGE session_id session_id INT DEFAULT NULL, CHANGE creneau_entretien vague INT DEFAULT NULL');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT FK_E33BD3B8613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('DROP INDEX uniq_session_annee_vague ON session_recrutement');
        $this->addSql('ALTER TABLE session_recrutement DROP date_reference_age, DROP age_min, DROP age_max');
    }
}
