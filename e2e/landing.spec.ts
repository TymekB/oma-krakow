import { expect, test } from '@playwright/test';

test.describe('Landing', () => {
  test('renderuje hero z wordmarkiem i CTA', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('.hero')).toBeVisible();
    await expect(page.getByText('Kraków · Gabinet kameralny')).toBeVisible();
    await expect(page.getByRole('link', { name: /Umów wizytę/i }).first()).toBeVisible();
  });

  test('tło ma papierową barwę i warstwę ziarna', async ({ page }) => {
    await page.goto('/');

    const background = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    expect(background).toBe('rgb(244, 243, 240)');

    const grain = await page.evaluate(() => {
      const style = getComputedStyle(document.body, '::before');

      return { image: style.backgroundImage, blend: style.mixBlendMode, opacity: style.opacity };
    });

    expect(grain.image).toContain('feTurbulence');
    expect(grain.blend).toBe('multiply');
  });

  test('sekcje nawigują kotwicami', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'Cennik' }).first().click();

    await expect(page).toHaveURL(/#cennik/);
  });
});

test.describe('Edycja landingu', () => {
  test('gość nie ma trybu edycji', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(1500);

    await expect(page.locator('.editable')).toHaveCount(0);
  });

  test('publiczny landing nie włącza edycji nawet z parametrem edit', async ({ page }) => {
    await page.goto('/?edit=1');
    await page.waitForTimeout(1500);

    await expect(page.locator('.editable')).toHaveCount(0);
  });

  test('strona edycji wymaga zalogowanego admina', async ({ page }) => {
    const response = await page.request.get('/admin/site/edit', { maxRedirects: 0 });

    expect([302, 401, 403]).toContain(response.status());
  });

  test('endpoint treści jest publiczny i zwraca obiekt', async ({ page }) => {
    const response = await page.request.get('/landing-content.json');

    expect(response.status()).toBe(200);
    expect(await response.json()).toHaveProperty('values');
  });

  test('zapis treści bez zalogowania jest odrzucony', async ({ page }) => {
    const response = await page.request.put('/admin/landing-content/hero.lead', {
      data: { value: 'nieautoryzowana zmiana' },
      maxRedirects: 0,
    });

    expect([302, 401, 403]).toContain(response.status());
  });
});
