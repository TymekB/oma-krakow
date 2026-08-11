<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811020156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ustawienia zdarzeń: włączanie i wyłączanie powiadomień mailowych.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE oma_notification_setting (code VARCHAR(64) NOT NULL, enabled TINYINT(1) NOT NULL, PRIMARY KEY(code)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE oma_notification_setting');
    }
}
