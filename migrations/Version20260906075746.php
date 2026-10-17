<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906075746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature ADD entlieu VARCHAR(255) DEFAULT NULL, ADD jury INT DEFAULT NULL, ADD note1 DOUBLE PRECISION DEFAULT NULL, ADD note2 DOUBLE PRECISION DEFAULT NULL, ADD note3 DOUBLE PRECISION DEFAULT NULL, ADD note4 DOUBLE PRECISION DEFAULT NULL, ADD note5 DOUBLE PRECISION DEFAULT NULL, ADD note6 DOUBLE PRECISION DEFAULT NULL, ADD note7 DOUBLE PRECISION DEFAULT NULL, ADD note8 DOUBLE PRECISION DEFAULT NULL, ADD note9 DOUBLE PRECISION DEFAULT NULL, ADD visdate DATETIME DEFAULT NULL, ADD vislieu VARCHAR(255) DEFAULT NULL, ADD visstatut VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP entlieu, DROP jury, DROP note1, DROP note2, DROP note3, DROP note4, DROP note5, DROP note6, DROP note7, DROP note8, DROP note9, DROP visdate, DROP vislieu, DROP visstatut');
    }
}
