import { expect, test } from '@playwright/test';
import {
  addFirstProductToCart,
  completeAddressStep,
  findEmailFor,
  registerCustomer,
  registerVerifiedCustomer,
  uniqueEmail,
} from './helpers';

const ADMIN_EMAIL = process.env.OMA_ADMIN_EMAIL ?? 'kontakt@oma-fizjo.pl';

test.describe('Powiadomienie dla administratora', () => {
  test('po złożeniu zamówienia admin dostaje mail z produktem i kupującym', async ({ page }) => {
    const email = uniqueEmail('zamowienie');

    const productName = await addFirstProductToCart(page);
    await completeAddressStep(page, email);

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await page.locator('input[type="radio"]:visible').last().check();
    await page.getByRole('button', { name: 'Dalej' }).first().click();
    await expect(page).toHaveURL(/complete/);

    await page.getByRole('button', { name: /Złóż zamówienie/i }).click();
    await expect(page).toHaveURL(/thank-you|after-pay|order/, { timeout: 30_000 });

    const notification = await findEmailFor(ADMIN_EMAIL);

    expect(notification.subject).toContain('Nowe zamówienie');
    expect(notification.html, 'mail zawiera kupiony produkt').toContain(productName);
    expect(notification.html, 'mail zawiera adres kupującego').toContain(email);
  });

  test('po rejestracji admin dostaje mail o nowym użytkowniku', async ({ page }) => {
    const email = uniqueEmail('nowyklient');

    await registerCustomer(page, email);

    const notification = await findEmailFor(ADMIN_EMAIL);

    expect(notification.subject).toContain('Nowe konto klienta');
    expect(notification.html, 'mail zawiera adres nowego klienta').toContain(email);
  });

  test('po dodaniu opinii admin dostaje mail z oceną i treścią', async ({ page }) => {
    const email = uniqueEmail('opinia');
    const comment = 'Olejek pieknie pachnie i dziala uspokajajaco.';

    await registerVerifiedCustomer(page, email);

    await page.goto('/sklep/produkty/olejek-eteryczny-eukaliptus/recenzje/nowa');
    await page.fill('[name="sylius_shop_product_review[title]"]', 'Cudowny zapach');
    await page.fill('[name="sylius_shop_product_review[comment]"]', comment);
    await page.locator('.sylius-rating label').nth(4).click();
    await page.locator('button[type="submit"], input[type="submit"]').last().click();
    await expect(page).toHaveURL(/produkty\/olejek-eteryczny-eukaliptus$/);

    const notification = await findEmailFor(ADMIN_EMAIL);

    expect(notification.subject).toContain('Nowa opinia');
    expect(notification.html, 'mail zawiera treść opinii').toContain(comment);
    expect(notification.html, 'mail zawiera autora opinii').toContain(email);
  });
});
