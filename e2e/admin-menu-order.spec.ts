import { expect, test } from '@playwright/test';
import { loginToAdmin } from './helpers';

async function sectionOrder(page: import('@playwright/test').Page): Promise<string[]> {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('.navbar-vertical .nav-item > .nav-link, aside .nav-item > .nav-link'))
      .map((el) => (el.textContent || '').replace(/\s+/g, ' ').trim())
      .filter((text) => text.length > 0),
  );
}

test.describe('Kolejnosc sekcji w menu panelu', () => {
  test('Marketing stoi nad Klient i pod Sprzedaz', async ({ page }) => {
    await loginToAdmin(page);

    const order = await sectionOrder(page);

    const sales = order.indexOf('Sprzedaż');
    const marketing = order.indexOf('Marketing');
    const customers = order.indexOf('Klient');

    expect(sales, 'sekcja Sprzedaż jest w menu').toBeGreaterThan(-1);
    expect(marketing, 'sekcja Marketing jest w menu').toBeGreaterThan(-1);
    expect(customers, 'sekcja Klient jest w menu').toBeGreaterThan(-1);

    expect(marketing, 'Marketing pod Sprzedaz').toBeGreaterThan(sales);
    expect(marketing, 'Marketing nad Klient').toBeLessThan(customers);
  });

  test('sekcje usuniete przez AdminMenuListener nie wracaja', async ({ page }) => {
    await loginToAdmin(page);

    const order = await sectionOrder(page);

    expect(order).not.toContain('Administracja');
  });
});
