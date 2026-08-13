import { expect, test } from '@playwright/test';
import { loginToAdmin } from './helpers';

const OIL_PRODUCT_ID = 5;

async function openPricingTab(page: import('@playwright/test').Page): Promise<void> {
  await loginToAdmin(page);
  await page.goto(`/admin/products/${OIL_PRODUCT_ID}/edit`);
  await page
    .locator('[data-bs-toggle="tab"], [role="tab"], .nav-link, .list-group-item')
    .filter({ hasText: /^\s*Ceny\s*$/ })
    .first()
    .click();
}

test.describe('Konfigurator cen netto i brutto', () => {
  test('kazde pole ceny dostaje lustro z odwrotna kwota', async ({ page }) => {
    await openPricingTab(page);

    const container = page.locator('[data-oma-price-tax]').first();
    await expect(container).toHaveAttribute('data-rate', '0.23');

    // included=1 znaczy, ze w bazie siedzi brutto, wiec lustro liczy netto
    await expect(container).toHaveAttribute('data-included', '1');
    await expect(container).toHaveAttribute('data-mirror-label', 'netto (VAT 23%)');

    // Cena, Cena wyjsciowa, Cena minimalna
    await expect(page.locator('.oma-price-mirror')).toHaveCount(3);
  });

  test('brutto wpisane w polu formularza przelicza sie na netto', async ({ page }) => {
    await openPricingTab(page);

    const gross = page.locator('input[id$="_price"]').first();
    const net = page.locator('.oma-price-mirror input').first();

    await gross.fill('');
    await gross.type('123');

    await expect(net).toHaveValue('100,00');
  });

  test('netto wpisane w lustrze przelicza sie na brutto w zapisywanym polu', async ({ page }) => {
    await openPricingTab(page);

    const gross = page.locator('input[id$="_price"]').first();
    const net = page.locator('.oma-price-mirror input').first();

    await net.fill('');
    await net.type('100');

    await expect(gross).toHaveValue('123,00');
  });

  test('netto z groszami zaokragla brutto do dwoch miejsc', async ({ page }) => {
    await openPricingTab(page);

    const gross = page.locator('input[id$="_price"]').first();
    const net = page.locator('.oma-price-mirror input').first();

    await net.fill('');
    await net.type('96,75');

    await expect(gross).toHaveValue('119,00');
  });

  test('puste pole nie pokazuje zera w lustrze', async ({ page }) => {
    await openPricingTab(page);

    const gross = page.locator('input[id$="_price"]').first();
    const net = page.locator('.oma-price-mirror input').first();

    await gross.fill('');

    await expect(net).toHaveValue('');
  });
});
