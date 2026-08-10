<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810235251 extends AbstractMigration
{
    private const ALLOWED_CODES = "'payu', 'payu_apple_pay', 'cash_on_delivery'";

    private const DISABLED_CODES = "'blik', 'bank_transfer', 'stripe'";

    public function getDescription(): string
    {
        return 'W checkoucie zostaja tylko PayU i platnosc przy odbiorze';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            sprintf(
                'UPDATE sylius_payment_method SET is_enabled = 0 WHERE code NOT IN (%s)',
                self::ALLOWED_CODES,
            )
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            sprintf(
                'UPDATE sylius_payment_method SET is_enabled = 1 WHERE code IN (%s)',
                self::DISABLED_CODES,
            )
        );
    }
}
