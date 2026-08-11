import { expect, test } from '@playwright/test';
import { addFirstProductToCart } from './helpers';

test.describe('Sklep', () => {
  test('strona główna sklepu się ładuje', async ({ page }) => {
    await page.goto('/sklep/');

    await expect(page.getByRole('heading', { name: 'Najnowsze produkty' })).toBeVisible();
    await expect(page.locator('a[href*="/sklep/produkty/"]').first()).toBeVisible();
  });

  test('tło sklepu jest spójne z landingiem', async ({ page }) => {
    await page.goto('/sklep/');

    const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    expect(background).toBe('rgb(244, 243, 240)');

    const grain = await page.evaluate(() => getComputedStyle(document.body, '::before').backgroundImage);
    expect(grain).toContain('feTurbulence');
  });

  test('baner strony głównej ma istniejącą teksturę w tle', async ({ page }) => {
    await page.goto('/sklep/');

    const banner = page.locator('.oma-banner').first();
    const background = await banner.evaluate((element) => getComputedStyle(element).backgroundImage);

    const url = background.match(/url\("([^"]+)"\)/)?.[1];
    expect(url, 'baner ma ustawioną teksturę').toBeTruthy();

    const response = await page.request.get(url!);
    expect(response.status(), 'plik tekstury jest serwowany, a nie 404').toBe(200);
  });

  test('nawigacja po kategorii idzie przez Turbo, bez przeładowania strony', async ({ page }) => {
    await page.goto('/sklep/');

    await page.evaluate(() => {
      (window as unknown as { __alive: boolean }).__alive = true;
    });

    await page.getByRole('link', { name: 'Aromaterapia' }).first().click();
    await expect(page).toHaveURL(/kategorie\/aromaterapia/);

    const survived = await page.evaluate(() => (window as unknown as { __alive?: boolean }).__alive === true);
    expect(survived, 'kontekst JS przetrwał nawigację, czyli Turbo obsłużyło klik').toBe(true);
  });

  test('karta produktu pokazuje cenę i przycisk dodania do koszyka', async ({ page }) => {
    await page.goto('/sklep/kategorie/aromaterapia');
    await page.locator('a[href*="/sklep/produkty/"]').first().click();

    await expect(page.locator('#add-to-cart-button')).toBeVisible();
    await expect(page.locator('body')).toContainText('zł');
  });

  test('dodanie produktu do koszyka aktualizuje podsumowanie', async ({ page }) => {
    const serverErrors: string[] = [];
    page.on('response', (response) => {
      if (response.status() >= 500) {
        serverErrors.push(`${response.status()} ${response.request().method()} ${new URL(response.url()).pathname}`);
      }
    });

    const productName = await addFirstProductToCart(page);
    expect(productName, 'nazwa produktu ze strony produktu, nie pusty tytuł offcanvasu').not.toBe('');

    await page.goto('/sklep/cart/');

    await expect(page.locator('body')).toContainText(productName);
    await expect(page.locator('body')).toContainText('Suma');
    expect(serverErrors, 'dodanie do koszyka bez odpowiedzi 5xx').toEqual([]);
  });

  test('po kilku nawigacjach Turbo jedno kliknięcie dodaje jedną sztukę', async ({ page }) => {
    await page.goto('/sklep/');

    for (let i = 0; i < 3; i++) {
      await page.goto('/sklep/kategorie/aromaterapia');
      await page.locator('a[href*="/sklep/produkty/"]').first().click();
      await expect(page.locator('#add-to-cart-button')).toBeVisible();
      await page.goBack();
    }

    await page.locator('a[href*="/sklep/produkty/"]').first().click();
    await expect(page.locator('#add-to-cart-button')).toBeVisible();
    await page.locator('#add-to-cart-button').click();
    await page.waitForTimeout(2500);

    await page.goto('/sklep/cart/');
    const quantity = page.locator('input[name*="quantity"]').first();

    await expect(quantity, 'bundle sklepu nie może wykonać się drugi raz po nawigacji Turbo').toHaveValue('1');
  });

  test('sortowanie działa i zachowuje kategorię', async ({ page }) => {
    await page.goto('/sklep/kategorie/aromaterapia?sorting%5Bprice%5D=asc');

    await expect(page).toHaveURL(/sorting/);
    await expect(page.getByRole('heading', { name: 'Aromaterapia' })).toBeVisible();
  });
});

test('akordeon szczegółów otwiera się i zamyka', async ({ page }) => {
  await page.goto('/sklep/produkty/olejek-eteryczny-eukaliptus');

  const toggle = page.locator('[data-bs-target="#details"]');
  await expect(toggle).toBeVisible();

  const initial = await toggle.getAttribute('aria-expanded');

  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', initial === 'true' ? 'false' : 'true');

  await page.waitForTimeout(200);

  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', initial ?? 'true');
});
