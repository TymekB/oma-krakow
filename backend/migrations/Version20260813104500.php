<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Uzupełnia kategorię podatkową VAT 23% dla wariantów bez ustawionej kategorii.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE sylius_product_variant
            SET tax_category_id = (SELECT id FROM sylius_tax_category WHERE code = 'standard')
            WHERE tax_category_id IS NULL
              AND EXISTS (SELECT 1 FROM sylius_tax_category WHERE code = 'standard')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
