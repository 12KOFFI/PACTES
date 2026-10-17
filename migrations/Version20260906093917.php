<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906093917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_E33BD3B8F55AE19E ON candidature');
        $this->addSql('ALTER TABLE candidature ADD piece VARCHAR(255) DEFAULT NULL, ADD numpiece VARCHAR(255) DEFAULT NULL, ADD note10 DOUBLE PRECISION DEFAULT NULL, ADD note11 DOUBLE PRECISION DEFAULT NULL, ADD note12 DOUBLE PRECISION DEFAULT NULL, ADD note13 DOUBLE PRECISION DEFAULT NULL, DROP statut, DROP submitted_at, CHANGE numero numero VARCHAR(255) DEFAULT NULL, CHANGE niveau niveau VARCHAR(255) DEFAULT NULL, CHANGE visstatut visstatut INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE candidature ADD statut VARCHAR(50) NOT NULL, ADD submitted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP piece, DROP numpiece, DROP note10, DROP note11, DROP note12, DROP note13, CHANGE numero numero VARCHAR(50) NOT NULL, CHANGE niveau niveau VARCHAR(50) DEFAULT NULL, CHANGE visstatut visstatut VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E33BD3B8F55AE19E ON candidature (numero)');
    }
}
