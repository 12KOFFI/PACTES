<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910220751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE convocation DROP FOREIGN KEY FK_C03B3F5F613FECDF');
        $this->addSql('ALTER TABLE convocation DROP FOREIGN KEY FK_C03B3F5FB03A8386');
        $this->addSql('ALTER TABLE convocation DROP FOREIGN KEY FK_C03B3F5FB6121583');
        $this->addSql('ALTER TABLE document_candidature DROP FOREIGN KEY FK_6192E4D2B6121583');
        $this->addSql('ALTER TABLE document_candidature DROP FOREIGN KEY FK_6192E4D2C69DE5E5');
        $this->addSql('ALTER TABLE epreuve DROP FOREIGN KEY FK_D6ADE47F613FECDF');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575AB990336');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575B6121583');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575E560103C');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB6121583');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3F896DBBDE');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FB03A8386');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FB6121583');
        $this->addSql('ALTER TABLE visite_medicale DROP FOREIGN KEY FK_B6D49D3FF8E958EC');
        $this->addSql('DROP TABLE convocation');
        $this->addSql('DROP TABLE document_candidature');
        $this->addSql('DROP TABLE epreuve');
        $this->addSql('DROP TABLE evaluation');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE visite_medicale');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE convocation (id INT AUTO_INCREMENT NOT NULL, candidature_id INT NOT NULL, session_id INT NOT NULL, created_by_id INT DEFAULT NULL, type VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, date DATE NOT NULL, lieu VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, statut VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, presence VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, envoyee_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', commentaire LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, heure TIME DEFAULT NULL, salle_id VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C03B3F5FB6121583 (candidature_id), INDEX IDX_C03B3F5F613FECDF (session_id), INDEX IDX_C03B3F5FB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE document_candidature (id INT AUTO_INCREMENT NOT NULL, candidature_id INT NOT NULL, validated_by_id INT DEFAULT NULL, type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, nom_fichier VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, chemin_fichier VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, statut VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, motif_rejet LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', validated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', taille_fichier INT DEFAULT NULL, mime_type VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6192E4D2B6121583 (candidature_id), INDEX IDX_6192E4D2C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE epreuve (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, coefficient DOUBLE PRECISION NOT NULL, note_max DOUBLE PRECISION NOT NULL, date DATETIME DEFAULT NULL, active TINYINT(1) NOT NULL, duree INT NOT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, is_eliminatoire TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D6ADE47F613FECDF (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE evaluation (id INT AUTO_INCREMENT NOT NULL, candidature_id INT NOT NULL, epreuve_id INT NOT NULL, jury_id INT DEFAULT NULL, note DOUBLE PRECISION NOT NULL, commentaire LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, statut VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', validated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1323A575B6121583 (candidature_id), INDEX IDX_1323A575AB990336 (epreuve_id), INDEX IDX_1323A575E560103C (jury_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, candidature_id INT DEFAULT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, canal VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, message LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, statut VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BF5476CAA76ED395 (user_id), INDEX IDX_BF5476CAB6121583 (candidature_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE visite_medicale (id INT AUTO_INCREMENT NOT NULL, candidature_id INT NOT NULL, updated_by_id INT DEFAULT NULL, document_medical_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, date DATE NOT NULL, lieu VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, statut VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, avis VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, commentaire LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_B6D49D3FB6121583 (candidature_id), INDEX IDX_B6D49D3F896DBBDE (updated_by_id), INDEX IDX_B6D49D3FF8E958EC (document_medical_id), INDEX IDX_B6D49D3FB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE convocation ADD CONSTRAINT FK_C03B3F5F613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE convocation ADD CONSTRAINT FK_C03B3F5FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE convocation ADD CONSTRAINT FK_C03B3F5FB6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_candidature ADD CONSTRAINT FK_6192E4D2B6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_candidature ADD CONSTRAINT FK_6192E4D2C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE epreuve ADD CONSTRAINT FK_D6ADE47F613FECDF FOREIGN KEY (session_id) REFERENCES session_recrutement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575AB990336 FOREIGN KEY (epreuve_id) REFERENCES epreuve (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575B6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575E560103C FOREIGN KEY (jury_id) REFERENCES user (id) ON UPDATE NO ACTION');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FB6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE visite_medicale ADD CONSTRAINT FK_B6D49D3FF8E958EC FOREIGN KEY (document_medical_id) REFERENCES document_candidature (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
