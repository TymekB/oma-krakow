import { expect, test } from '@playwright/test';
import { addFirstProductToCart, completeAddressStep, findEmailFor, uniqueEmail } from './helpers';

test.describe('Powiadomienie dla administratora', () => {
  test('po złożeniu zamówienia admin dostaje mail z produktem i kupującym', async ({ page }) => {
    const email = uniqueEmail('zamowienie');

    const productName = await addFirstProductToCart(page);
    await completeAddressStep(page, email);

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await page.locator('input[type="radio"]').last().check();
    await page.getByRole('button', { name: 'Dalej' }).first().click();
    await expect(page).toHaveURL(/complete/);

    await page.getByRole('button', { name: /Złóż zamówienie/i }).click();
    await expect(page).toHaveURL(/thank-you|after-pay|order/, { timeout: 30_000 });

    const notification = await findEmailFor(process.env.OMA_ADMIN_EMAIL ?? 'kontakt@oma-fizjo.pl');

    expect(notification.subject).toContain('Nowe zamówienie');
    expect(notification.html, 'mail zawiera kupiony produkt').toContain(productName);
    expect(notification.html, 'mail zawiera adres kupującego').toContain(email);
  });
});
