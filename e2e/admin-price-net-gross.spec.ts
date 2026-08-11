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

    // included=0 znaczy, ze w bazie siedzi netto, wiec lustro liczy brutto
    await expect(container).toHaveAttribute('data-included', '0');
    await expect(container).toHaveAttribute('data-mirror-label', 'brutto (VAT 23%)');

    // Cena, Cena wyjsciowa, Cena minimalna
    await expect(page.locator('.oma-price-mirror')).toHaveCount(3);
  });

  test('netto wpisane w polu formularza przelicza sie na brutto', async ({ page }) => {
    await openPricingTab(page);

    const net = page.locator('input[id$="_price"]').first();
    const gross = page.locator('.oma-price-mirror input').first();

    await net.fill('');
    await net.type('100');

    await expect(gross).toHaveValue('123,00');
  });

  test('brutto wpisane w lustrze przelicza sie na netto w zapisywanym polu', async ({ page }) => {
    await openPricingTab(page);

    const net = page.locator('input[id$="_price"]').first();
    const gross = page.locator('.oma-price-mirror input').first();

    await gross.fill('');
    await gross.type('123');

    await expect(net).toHaveValue('100,00');
  });

  test('brutto z groszami zaokragla netto do dwoch miejsc', async ({ page }) => {
    await openPricingTab(page);

    const net = page.locator('input[id$="_price"]').first();
    const gross = page.locator('.oma-price-mirror input').first();

    await gross.fill('');
    await gross.type('119,00');

    await expect(net).toHaveValue('96,75');
  });

  test('puste pole nie pokazuje zera w lustrze', async ({ page }) => {
    await openPricingTab(page);

    const net = page.locator('input[id$="_price"]').first();
    const gross = page.locator('.oma-price-mirror input').first();

    await net.fill('');

    await expect(gross).toHaveValue('');
  });
});
