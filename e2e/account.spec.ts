import { expect, test } from '@playwright/test';
import { findEmailFor, registerCustomer, uniqueEmail } from './helpers';

test.describe('Konto klienta', () => {
  test('rejestracja kończy się stroną podziękowania', async ({ page }) => {
    await registerCustomer(page, uniqueEmail('rejestracja'));

    await expect(page).toHaveURL(/thank-you/);
  });

  test('mail weryfikacyjny ma branding oma i działający link', async ({ page }) => {
    const email = uniqueEmail('mail');
    await registerCustomer(page, email);

    const message = await findEmailFor(email);

    expect(message.html).toContain('#5a1f2c');
    expect(message.html).not.toContain('sylius.com');
    expect(message.html).not.toContain('#1abb9c');

    const link = message.html.match(/https?:\/\/[^"'\s<>]*\/sklep\/verify\/[^"'\s<>]+/)?.[0];
    expect(link, 'mail zawiera link weryfikacyjny').toBeTruthy();

    const base = new URL(page.url()).origin;
    expect(link, 'link prowadzi na ten sam host i port co sklep').toContain(base);

    await page.goto(link!);
    await expect(page.locator('body')).not.toContainText('NotFoundHttpException');
  });

  test('logowanie odrzuca błędne hasło', async ({ page }) => {
    await page.goto('/sklep/login');
    await page.locator('input[name="_username"]').fill('nie-istnieje@oma.local');
    await page.locator('input[name="_password"]').fill('zle-haslo');
    await page.locator('form[action*="login"] button[type="submit"]').last().click();

    await expect(page.locator('body')).toContainText(/nieprawidłow|invalid|błęd/i);
  });
});

test.describe('Logowanie Google', () => {
  test('przycisk jest ukryty, dopóki brakuje credentiali', async ({ page }) => {
    await page.goto('/sklep/login');

    const configured = await page.getByRole('link', { name: /Kontynuuj z Google/i }).count();

    if (configured === 0) {
      const response = await page.request.get('/sklep/connect/google', { maxRedirects: 0 });
      expect(response.status(), 'bez kluczy trasa ma zwracać 404, nie błąd serwera').toBe(404);

      return;
    }

    const response = await page.request.get('/sklep/connect/google', { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toContain('accounts.google.com');
  });
});
