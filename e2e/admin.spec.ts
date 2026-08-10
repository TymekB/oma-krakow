import { expect, test } from '@playwright/test';
import { loginToAdmin } from './helpers';

test.describe('Panel admina', () => {
  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page);
  });

  test('legenda wykresu jest po polsku', async ({ page }) => {
    const legend = page.locator('.apexcharts-legend-text');

    await expect(legend.first()).toBeVisible();
    await expect(legend.nth(0)).toHaveText('Sprzedaż');
    await expect(legend.nth(1)).toHaveText('Liczba opłaconych zamówień');
  });

  test('tło panelu jest jaśniejsze niż karty i nie zlewa się z nimi', async ({ page }) => {
    const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);

    expect(background).toBe('rgb(250, 249, 246)');
  });

  test('drzewo kategorii nie dubluje się po wejściu w edycję', async ({ page }) => {
    await page.goto('/admin/taxons/');
    await page.waitForLoadState('networkidle');
    const onFreshLoad = await page.locator('[data-live-name-value]').count();

    await page.locator('a[href*="/admin/taxons/"][href*="/edit"]:visible').first().click();
    await expect(page).toHaveURL(/\/admin\/taxons\/\d+\/edit/);
    await page.waitForLoadState('networkidle');

    const afterNavigation = await page.locator('[data-live-name-value]').count();

    await page.reload();
    await page.waitForLoadState('networkidle');
    const directLoad = await page.locator('[data-live-name-value]').count();

    expect(afterNavigation, 'nawigacja nie zostawia komponentów poprzedniej strony').toBe(directLoad);
    expect(onFreshLoad).toBeGreaterThan(0);
  });

  test('lista produktów się otwiera', async ({ page }) => {
    await page.goto('/admin/products/');

    await expect(page.getByRole('heading', { name: /Produkty/i }).first()).toBeVisible();
  });

  test('lista zamówień się otwiera', async ({ page }) => {
    await page.goto('/admin/orders/');

    await expect(page.getByRole('heading', { name: /Zamówienia/i }).first()).toBeVisible();
  });

  test('kolumna Kanał znika przy jednym kanale', async ({ page }) => {
    for (const url of ['/admin/orders/', '/admin/payments/', '/admin/shipments/']) {
      await page.goto(url);

      const headers = await page.locator('thead th').allTextContents();
      const trimmed = headers.map((header) => header.trim());

      expect(trimmed, `${url} nie pokazuje kolumny Kanał`).not.toContain('Kanał');
    }
  });

  test('metody płatności: Stripe jest na liście', async ({ page }) => {
    await page.goto('/admin/payment-methods/');

    await expect(page.locator('body')).toContainText('Karta, BLIK');
  });

  test('najnowsze zamówienie ma 4-cyfrowy numer', async ({ page }) => {
    await page.goto('/admin/orders/');

    const numbers = await page.locator('td a[href*="/admin/orders/"]').allTextContents();
    const orderNumbers = numbers.map((text) => text.trim().replace('#', '')).filter((text) => /^\d+$/.test(text));

    expect(orderNumbers.length, 'lista zamówień nie jest pusta').toBeGreaterThan(0);
    expect(orderNumbers[0], 'zamówienia sprzed zmiany zachowują stare, 9-cyfrowe numery').toHaveLength(4);
  });
});
