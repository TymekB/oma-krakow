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
