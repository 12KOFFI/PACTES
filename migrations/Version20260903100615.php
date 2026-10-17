<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260903100615 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE etablissement DROP FOREIGN KEY FK_20FD592C4C4A5FBB');
        $this->addSql('ALTER TABLE localite DROP FOREIGN KEY FK_F5D7E4A94C4A5FBB');
        $this->addSql('ALTER TABLE admis DROP FOREIGN KEY FK_EB0861AFC1B77974');
        $this->addSql('ALTER TABLE metier DROP FOREIGN KEY FK_51A00D8C9F7E4405');
        $this->addSql('CREATE TABLE jury_epreuve (jury_id INT NOT NULL, epreuve_id INT NOT NULL, session_id INT NOT NULL, INDEX IDX_5FBE46F7E560103C (jury_id), INDEX IDX_5FBE46F7AB990336 (epreuve_id), INDEX IDX_5FBE46F7613FECDF (session_id), UNIQUE INDEX uniq_jury_epreuve (jury_id, epreuve_id, session_id), PRIMARY KEY(jury_id, epreuve_id, session_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, candidature_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, canal VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, statut VARCHAR(20) NOT NULL, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BF5476CAA76ED395 (user_id), INDEX IDX_BF5476CAB6121583 (candidature_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE jury_epreuve ADD CONSTRAINT FK_5FBE46F7E560103C FOREIGN KEY (jury_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE jury_epreuve ADD CONSTRAINT FK_5FBE46F7AB990336 FOREIGN KEY (epreuve_id) REFERENCES epreuve (id)');
        $this->addSql('ALTER TABLE jury_epreuve ADD CONSTRAINT FK_5FBE46F7613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id)');
        $this->addSql('ALTER TABLE direction_regionale DROP FOREIGN KEY FK_D783C96BA76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A769F7E4405');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76ED16FA20');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76FF631228');
        $this->addSql('ALTER TABLE epi_declaration DROP FOREIGN KEY FK_9AA8352F53E73F');
        $this->addSql('ALTER TABLE epi_declaration DROP FOREIGN KEY FK_9AA8352F80C97749');
        $this->addSql('ALTER TABLE epi_declaration DROP FOREIGN KEY FK_9AA8352F8B43E2A');
        $this->addSql('ALTER TABLE equipement DROP FOREIGN KEY FK_B8B4C6F3A76ED395');
        $this->addSql('ALTER TABLE metier_equipement DROP FOREIGN KEY FK_2ED26BD7806F0F5C');
        $this->addSql('ALTER TABLE metier_equipement DROP FOREIGN KEY FK_2ED26BD7ED16FA20');
        $this->addSql('ALTER TABLE prospection DROP FOREIGN KEY FK_47EBD1B0A76ED395');
        $this->addSql('ALTER TABLE prospection DROP FOREIGN KEY FK_47EBD1B0FF631228');
        $this->addSql('ALTER TABLE prospection_metier DROP FOREIGN KEY FK_843FC6FCE4F4C9');
        $this->addSql('ALTER TABLE prospection_metier DROP FOREIGN KEY FK_843FC6FED16FA20');
        $this->addSql('ALTER TABLE secteur DROP FOREIGN KEY FK_8045251FA76ED395');
        $this->addSql('DROP TABLE direction_regionale');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE epi_declaration');
        $this->addSql('DROP TABLE equipement');
        $this->addSql('DROP TABLE metier_equipement');
        $this->addSql('DROP TABLE prospection');
        $this->addSql('DROP TABLE prospection_metier');
        $this->addSql('DROP TABLE secteur');
        $this->addSql('DROP INDEX IDX_EB0861AFC1B77974 ON admis');
        $this->addSql('ALTER TABLE admis DROP entreprise_partenaire_id');
        $this->addSql('ALTER TABLE candidature ADD submitted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX IDX_20FD592C4C4A5FBB ON etablissement');
        $this->addSql('ALTER TABLE etablissement DROP direction_regionale_id');
        $this->addSql('DROP INDEX IDX_F5D7E4A94C4A5FBB ON localite');
        $this->addSql('ALTER TABLE localite DROP direction_regionale_id');
        $this->addSql('DROP INDEX IDX_51A00D8C9F7E4405 ON metier');
        $this->addSql('ALTER TABLE metier DROP secteur_id');
        $this->addSql('ALTER TABLE user ADD is_active TINYINT(1) NOT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE direction_regionale (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, chef_lieu VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, creation DATE DEFAULT NULL, modification DATETIME DEFAULT NULL, INDEX IDX_D783C96BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, etablissement_id INT DEFAULT NULL, metier_id INT DEFAULT NULL, secteur_id INT DEFAULT NULL, user_id INT NOT NULL, type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, fichier VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, actif TINYINT(1) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, INDEX IDX_D8698A76FF631228 (etablissement_id), INDEX IDX_D8698A76ED16FA20 (metier_id), INDEX IDX_D8698A769F7E4405 (secteur_id), INDEX IDX_D8698A76A76ED395 (user_id), INDEX IDX_type_actif (type, actif), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE epi_declaration (id INT AUTO_INCREMENT NOT NULL, etablissement_metier_id INT NOT NULL, metier_equipement_id INT NOT NULL, declare_par_id INT DEFAULT NULL, recu TINYINT(1) DEFAULT 0 NOT NULL, date_declaration DATETIME DEFAULT NULL, commentaire LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, creation DATETIME DEFAULT NULL, modification DATETIME DEFAULT NULL, INDEX IDX_9AA8352F80C97749 (etablissement_metier_id), INDEX IDX_9AA8352F53E73F (metier_equipement_id), INDEX IDX_9AA8352F8B43E2A (declare_par_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE equipement (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, designation VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, caracteristiques LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, creation DATE DEFAULT NULL, modification DATETIME DEFAULT NULL, INDEX IDX_B8B4C6F3A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE metier_equipement (id INT AUTO_INCREMENT NOT NULL, metier_id INT NOT NULL, equipement_id INT NOT NULL, ordre INT DEFAULT NULL, obligatoire TINYINT(1) DEFAULT 1 NOT NULL, creation DATE DEFAULT NULL, INDEX IDX_2ED26BD7ED16FA20 (metier_id), INDEX IDX_2ED26BD7806F0F5C (equipement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE prospection (id INT AUTO_INCREMENT NOT NULL, etablissement_id INT NOT NULL, user_id INT DEFAULT NULL, nom_structure VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, localite VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, telephone_structure VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, secteur_activite VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, nom_responsable VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, telephone_responsable VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, commentaire LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, date_prospection DATE DEFAULT NULL, creation DATE DEFAULT NULL, modification DATETIME DEFAULT NULL, fichier_convention VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, date_convention DATE DEFAULT NULL, est_partenaire TINYINT(1) NOT NULL, INDEX IDX_47EBD1B0FF631228 (etablissement_id), INDEX IDX_47EBD1B0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE prospection_metier (id INT AUTO_INCREMENT NOT NULL, prospection_id INT NOT NULL, metier_id INT NOT NULL, nombre_postes INT NOT NULL, INDEX IDX_843FC6FCE4F4C9 (prospection_id), INDEX IDX_843FC6FED16FA20 (metier_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE secteur (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, creation DATE DEFAULT NULL, modification DATETIME DEFAULT NULL, INDEX IDX_8045251FA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE direction_regionale ADD CONSTRAINT FK_D783C96BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A769F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76ED16FA20 FOREIGN KEY (metier_id) REFERENCES metier (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76FF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE epi_declaration ADD CONSTRAINT FK_9AA8352F53E73F FOREIGN KEY (metier_equipement_id) REFERENCES metier_equipement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE epi_declaration ADD CONSTRAINT FK_9AA8352F80C97749 FOREIGN KEY (etablissement_metier_id) REFERENCES etablissement_metier (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE epi_declaration ADD CONSTRAINT FK_9AA8352F8B43E2A FOREIGN KEY (declare_par_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE equipement ADD CONSTRAINT FK_B8B4C6F3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE metier_equipement ADD CONSTRAINT FK_2ED26BD7806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE metier_equipement ADD CONSTRAINT FK_2ED26BD7ED16FA20 FOREIGN KEY (metier_id) REFERENCES metier (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE prospection ADD CONSTRAINT FK_47EBD1B0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE prospection ADD CONSTRAINT FK_47EBD1B0FF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE prospection_metier ADD CONSTRAINT FK_843FC6FCE4F4C9 FOREIGN KEY (prospection_id) REFERENCES prospection (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE prospection_metier ADD CONSTRAINT FK_843FC6FED16FA20 FOREIGN KEY (metier_id) REFERENCES metier (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE secteur ADD CONSTRAINT FK_8045251FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE jury_epreuve DROP FOREIGN KEY FK_5FBE46F7E560103C');
        $this->addSql('ALTER TABLE jury_epreuve DROP FOREIGN KEY FK_5FBE46F7AB990336');
        $this->addSql('ALTER TABLE jury_epreuve DROP FOREIGN KEY FK_5FBE46F7613FECDF');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB6121583');
        $this->addSql('DROP TABLE jury_epreuve');
        $this->addSql('DROP TABLE notification');
        $this->addSql('ALTER TABLE admis ADD entreprise_partenaire_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE admis ADD CONSTRAINT FK_EB0861AFC1B77974 FOREIGN KEY (entreprise_partenaire_id) REFERENCES prospection (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_EB0861AFC1B77974 ON admis (entreprise_partenaire_id)');
        $this->addSql('ALTER TABLE candidature DROP submitted_at');
        $this->addSql('ALTER TABLE etablissement ADD direction_regionale_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE etablissement ADD CONSTRAINT FK_20FD592C4C4A5FBB FOREIGN KEY (direction_regionale_id) REFERENCES direction_regionale (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_20FD592C4C4A5FBB ON etablissement (direction_regionale_id)');
        $this->addSql('ALTER TABLE localite ADD direction_regionale_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE localite ADD CONSTRAINT FK_F5D7E4A94C4A5FBB FOREIGN KEY (direction_regionale_id) REFERENCES direction_regionale (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_F5D7E4A94C4A5FBB ON localite (direction_regionale_id)');
        $this->addSql('ALTER TABLE metier ADD secteur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE metier ADD CONSTRAINT FK_51A00D8C9F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_51A00D8C9F7E4405 ON metier (secteur_id)');
        $this->addSql('ALTER TABLE user DROP is_active, DROP created_at, DROP updated_at, DROP last_login_at');
    }
}
