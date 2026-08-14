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

  test('logo w sidebarze ma teksturowane tło, a plik tekstury istnieje', async ({ page }) => {
    const brand = page.locator('.navbar-vertical .navbar-brand').first();
    const background = await brand.evaluate((element) => getComputedStyle(element).backgroundImage);

    const url = background.match(/url\("([^"]+)"\)/)?.[1];
    expect(url, 'brand ma ustawione tło').toBeTruthy();

    const response = await page.request.get(url!);
    expect(response.status(), 'plik tekstury jest serwowany, a nie 404').toBe(200);
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

  test('metody płatności: lista zawiera PayU i płatność przy odbiorze', async ({ page }) => {
    await page.goto('/admin/payment-methods/');

    const body = page.locator('body');
    await expect(body).toContainText('PayU');
    await expect(body).toContainText('Płatność przy odbiorze');
  });

  test('najnowsze zamówienie ma 4-cyfrowy numer', async ({ page }) => {
    await page.goto('/admin/orders/');

    const numbers = await page.locator('td a[href*="/admin/orders/"]').allTextContents();
    const orderNumbers = numbers.map((text) => text.trim().replace('#', '')).filter((text) => /^\d+$/.test(text));

    expect(orderNumbers.length, 'lista zamówień nie jest pusta').toBeGreaterThan(0);
    expect(orderNumbers[0], 'zamówienia sprzed zmiany zachowują stare, 9-cyfrowe numery').toHaveLength(4);
  });

  test('długa nazwa produktu nie wychodzi poza kartę w widoku opinii', async ({ page }) => {
    await page.goto('/admin/product-reviews/');

    const firstReview = page.locator('a[href*="/admin/product-reviews/"][href$="/edit"]').first();
    await expect(firstReview).toBeVisible();
    await firstReview.click();

    const overflow = await page.evaluate(() => {
      const box = document.querySelector('.thumbnail-box') as HTMLElement | null;
      const card = box?.closest('.card') as HTMLElement | null;

      if (null === box || null === card) {
        return null;
      }

      return Math.round(box.getBoundingClientRect().right - card.getBoundingClientRect().right);
    });

    expect(overflow, 'karta produktu istnieje w widoku opinii').not.toBeNull();
    expect(overflow, 'nazwa zawija się w karcie, nie wystaje poza nią').toBeLessThanOrEqual(0);
  });

  test('nowy produkt ma domyślnie kategorię podatkową VAT 23%', async ({ page }) => {
    await page.goto('/admin/products/new/simple');

    const taxCategory = page.locator('select[id$="_taxCategory"]').first();

    await expect(taxCategory.locator('option:checked')).toHaveText('VAT 23%');
  });
});

test.describe('Jeden kanał', () => {
  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page);
  });

  const forms = [
    { url: '/admin/payment-methods/new/offline', nazwa: 'metoda płatności' },
    { url: '/admin/shipping-methods/new', nazwa: 'sposób wysyłki' },
    { url: '/admin/promotions/new', nazwa: 'promocja' },
    { url: '/admin/products/new/simple', nazwa: 'produkt' },
  ];

  for (const { url, nazwa } of forms) {
    test(`formularz "${nazwa}" nie pyta o kanał`, async ({ page }) => {
      const response = await page.goto(url);

      expect(response?.status(), 'formularz się otwiera').toBe(200);
      await expect(page.locator('[name*="[channels]"]')).toHaveCount(0);
      await expect(page.getByText('Kanały', { exact: true })).toHaveCount(0);
    });
  }
});
