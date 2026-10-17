<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260904180507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature ADD fphoto VARCHAR(255) DEFAULT NULL, ADD fpiece VARCHAR(255) DEFAULT NULL, ADD fextrait VARCHAR(255) DEFAULT NULL, ADD fniveau VARCHAR(255) DEFAULT NULL, ADD fautre VARCHAR(255) DEFAULT NULL, ADD entstatut INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature DROP fphoto, DROP fpiece, DROP fextrait, DROP fniveau, DROP fautre, DROP entstatut');
    }
}
