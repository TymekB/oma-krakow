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
  test('sekcje ida w kolejnosci Katalog, Sprzedaz, Marketing, Konfiguracja, Klient', async ({ page }) => {
    await loginToAdmin(page);

    const order = await sectionOrder(page);
    const wanted = ['Katalog', 'Sprzedaż', 'Marketing', 'Konfiguracja', 'Klient'];

    for (const label of wanted) {
      expect(order, `sekcja ${label} jest w menu`).toContain(label);
    }

    const positions = wanted.map((label) => order.indexOf(label));

    expect(positions, 'kolejnosc sekcji rosnie').toEqual([...positions].sort((a, b) => a - b));
  });

  test('sekcje usuniete przez AdminMenuListener nie wracaja', async ({ page }) => {
    await loginToAdmin(page);

    const order = await sectionOrder(page);

    expect(order).not.toContain('Administracja');
  });
});
