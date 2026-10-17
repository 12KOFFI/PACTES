<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260904113146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jury_epreuve DROP FOREIGN KEY FK_5FBE46F7613FECDF');
        $this->addSql('ALTER TABLE jury_epreuve DROP FOREIGN KEY FK_5FBE46F7AB990336');
        $this->addSql('ALTER TABLE jury_epreuve DROP FOREIGN KEY FK_5FBE46F7E560103C');
        $this->addSql('DROP TABLE jury_epreuve');
        $this->addSql('ALTER TABLE candidature DROP piece, DROP numpiece, DROP niveau, DROP fphoto, DROP fpiece, DROP fextrait, DROP fniveau, DROP fautre, DROP etustatut, DROP jury, DROP entdate, DROP entlieu, DROP note1, DROP note2, DROP note3, DROP note4, DROP note5, DROP note6, DROP note7, DROP note8, DROP note9, DROP note10, DROP note11, DROP note12, DROP note13, DROP entstatut, DROP visdate, DROP vislieu, DROP visstatut, CHANGE numero numero VARCHAR(50) NOT NULL, CHANGE statut statut VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E33BD3B8F55AE19E ON candidature (numero)');
        $this->addSql('ALTER TABLE convocation ADD heure TIME DEFAULT NULL, ADD salle_id VARCHAR(50) NOT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP salle, CHANGE date date DATE NOT NULL, CHANGE lieu lieu VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE document_candidature DROP FOREIGN KEY FK_6192E4D2C69DE5E5');
        $this->addSql('ALTER TABLE document_candidature ADD taille_fichier INT DEFAULT NULL, ADD mime_type VARCHAR(100) DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE chemin_fichier chemin_fichier VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE document_candidature ADD CONSTRAINT FK_6192E4D2C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE epreuve DROP FOREIGN KEY FK_D6ADE47F613FECDF');
        $this->addSql('ALTER TABLE epreuve ADD duree INT NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD is_eliminatoire TINYINT(1) NOT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP duree_minutes, CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE epreuve ADD CONSTRAINT FK_D6ADE47F613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id)');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575AB990336');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575B6121583');
        $this->addSql('ALTER TABLE evaluation ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE jury_id jury_id INT DEFAULT NULL, CHANGE note note DOUBLE PRECISION NOT NULL, CHANGE statut statut VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575AB990336 FOREIGN KEY (epreuve_id) REFERENCES epreuve (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575B6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id)');
        $this->addSql('ALTER TABLE notification CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3F896DBBDE');
        $this->addSql('ALTER TABLE visite_medicale ADD document_medical_id INT DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP document_medical, CHANGE date date DATE NOT NULL, CHANGE lieu lieu VARCHAR(150) NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FF8E958EC FOREIGN KEY (document_medical_id) REFERENCES document_candidature (id)');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_B6D49D3FF8E958EC ON visite_medicale (document_medical_id)');
        $this->addSql('CREATE INDEX IDX_B6D49D3FB03A8386 ON visite_medicale (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE jury_epreuve (jury_id INT NOT NULL, epreuve_id INT NOT NULL, session_id INT NOT NULL, UNIQUE INDEX uniq_jury_epreuve (jury_id, epreuve_id, session_id), INDEX IDX_5FBE46F7E560103C (jury_id), INDEX IDX_5FBE46F7AB990336 (epreuve_id), INDEX IDX_5FBE46F7613FECDF (session_id), PRIMARY KEY(jury_id, epreuve_id, session_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE jury_epreuve ADD CONSTRAINT FK_5FBE46F7613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE jury_epreuve ADD CONSTRAINT FK_5FBE46F7AB990336 FOREIGN KEY (epreuve_id) REFERENCES epreuve (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE jury_epreuve ADD CONSTRAINT FK_5FBE46F7E560103C FOREIGN KEY (jury_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('DROP INDEX UNIQ_E33BD3B8F55AE19E ON candidature');
        $this->addSql('ALTER TABLE candidature ADD piece VARCHAR(255) DEFAULT NULL, ADD numpiece VARCHAR(255) DEFAULT NULL, ADD niveau VARCHAR(255) DEFAULT NULL, ADD fphoto VARCHAR(255) DEFAULT NULL, ADD fpiece VARCHAR(255) DEFAULT NULL, ADD fextrait VARCHAR(255) DEFAULT NULL, ADD fniveau VARCHAR(255) DEFAULT NULL, ADD fautre VARCHAR(255) DEFAULT NULL, ADD etustatut INT DEFAULT NULL, ADD jury INT DEFAULT NULL, ADD entdate DATETIME DEFAULT NULL, ADD entlieu VARCHAR(255) DEFAULT NULL, ADD note1 DOUBLE PRECISION DEFAULT NULL, ADD note2 DOUBLE PRECISION DEFAULT NULL, ADD note3 DOUBLE PRECISION DEFAULT NULL, ADD note4 DOUBLE PRECISION DEFAULT NULL, ADD note5 DOUBLE PRECISION DEFAULT NULL, ADD note6 DOUBLE PRECISION DEFAULT NULL, ADD note7 DOUBLE PRECISION DEFAULT NULL, ADD note8 DOUBLE PRECISION DEFAULT NULL, ADD note9 DOUBLE PRECISION DEFAULT NULL, ADD note10 DOUBLE PRECISION DEFAULT NULL, ADD note11 DOUBLE PRECISION DEFAULT NULL, ADD note12 DOUBLE PRECISION DEFAULT NULL, ADD note13 DOUBLE PRECISION DEFAULT NULL, ADD entstatut INT DEFAULT NULL, ADD visdate DATETIME DEFAULT NULL, ADD vislieu VARCHAR(255) DEFAULT NULL, ADD visstatut INT DEFAULT NULL, CHANGE numero numero VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE convocation ADD salle VARCHAR(100) DEFAULT NULL, DROP heure, DROP salle_id, DROP created_at, DROP updated_at, CHANGE date date DATETIME NOT NULL, CHANGE lieu lieu VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE document_candidature DROP FOREIGN KEY FK_6192E4D2C69DE5E5');
        $this->addSql('ALTER TABLE document_candidature DROP taille_fichier, DROP mime_type, DROP created_at, DROP updated_at, CHANGE chemin_fichier chemin_fichier VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE document_candidature ADD CONSTRAINT FK_6192E4D2C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE epreuve DROP FOREIGN KEY FK_D6ADE47F613FECDF');
        $this->addSql('ALTER TABLE epreuve ADD duree_minutes INT DEFAULT NULL, DROP duree, DROP description, DROP is_eliminatoire, DROP created_at, DROP updated_at, CHANGE nom nom VARCHAR(150) NOT NULL, CHANGE type type VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE epreuve ADD CONSTRAINT FK_D6ADE47F613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575B6121583');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575AB990336');
        $this->addSql('ALTER TABLE evaluation DROP updated_at, CHANGE jury_id jury_id INT NOT NULL, CHANGE note note DOUBLE PRECISION DEFAULT NULL, CHANGE statut statut VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575B6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575AB990336 FOREIGN KEY (epreuve_id) REFERENCES epreuve (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FF8E958EC');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FB03A8386');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3F896DBBDE');
        $this->addSql('DROP INDEX IDX_B6D49D3FF8E958EC ON visite_medicale');
        $this->addSql('DROP INDEX IDX_B6D49D3FB03A8386 ON visite_medicale');
        $this->addSql('ALTER TABLE visite_medicale ADD document_medical VARCHAR(255) DEFAULT NULL, DROP document_medical_id, DROP created_by_id, DROP created_at, CHANGE date date DATETIME DEFAULT NULL, CHANGE lieu lieu VARCHAR(150) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
    }
}
