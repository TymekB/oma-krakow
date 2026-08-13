import { expect, test } from '@playwright/test';
import {
  addFirstProductToCart,
  completeAddressStep,
  countEmailsFor,
  findEmailFor,
  readReservedStock,
  registerCustomer,
  registerVerifiedCustomer,
  setNotificationEnabled,
  setProductStock,
  uniqueEmail,
} from './helpers';

const ADMIN_EMAIL = process.env.OMA_ADMIN_EMAIL ?? 'kontakt@oma-fizjo.pl';

const OIL_PRODUCT_ID = 4;

const OIL_VARIANT_ID = 4;

const OIL_SLUG = 'olejek-do-ciala-bergamotka-lawenda-pomarancza';

const OUT_OF_STOCK_SUBJECT = 'Produkt wyprzedany';

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

  test('gdy schodzi ostatnia sztuka admin dostaje mail o pustym stanie', async ({ page }) => {
    const reserved = await readReservedStock(page, OIL_VARIANT_ID);
    await setProductStock(page, OIL_PRODUCT_ID, reserved + 1, true);
    const before = await countEmailsFor(ADMIN_EMAIL, OUT_OF_STOCK_SUBJECT);

    await page.goto(`/sklep/produkty/${OIL_SLUG}`);
    await expect(page.locator('#add-to-cart-button')).toBeVisible();
    await page.locator('#add-to-cart-button').click();
    await page.waitForTimeout(2000);

    await completeAddressStep(page, uniqueEmail('ostatnia-sztuka'));

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await page.locator('input[type="radio"]:visible').last().check();
    await page.getByRole('button', { name: 'Dalej' }).first().click();
    await expect(page).toHaveURL(/complete/);

    await page.getByRole('button', { name: /Złóż zamówienie/i }).click();
    await expect(page).toHaveURL(/thank-you|after-pay|order/, { timeout: 30_000 });

    await expect
      .poll(() => countEmailsFor(ADMIN_EMAIL, OUT_OF_STOCK_SUBJECT), { timeout: 10_000 })
      .toBe(before + 1);

    const notification = await findEmailFor(ADMIN_EMAIL, OUT_OF_STOCK_SUBJECT);

    expect(notification.html, 'mail wskazuje produkt bez stanu').toContain('Olejek do ciała');
    expect(notification.html, 'mail prowadzi do produktu w panelu').toContain(
      `/admin/products/${OIL_PRODUCT_ID}/edit`,
    );

    const soldOutCard = await page.request.get(`/sklep/produkty/${OIL_SLUG}`);
    expect(soldOutCard.status(), 'wyprzedany produkt znika ze sklepu, nie wisi z komunikatem').toBe(404);

    await setProductStock(page, OIL_PRODUCT_ID, 50, true);
  });

  test('wyłączone zdarzenie nie wysyła maila o pustym stanie', async ({ page }) => {
    await setNotificationEnabled(page, 'admin_product_out_of_stock', false);
    await setProductStock(page, OIL_PRODUCT_ID, 50, true);
    const reserved = await readReservedStock(page, OIL_VARIANT_ID);
    const before = await countEmailsFor(ADMIN_EMAIL, OUT_OF_STOCK_SUBJECT);

    await setProductStock(page, OIL_PRODUCT_ID, reserved, true);
    await page.waitForTimeout(2000);

    expect(await countEmailsFor(ADMIN_EMAIL, OUT_OF_STOCK_SUBJECT), 'wyłączone zdarzenie milczy').toBe(before);

    await setNotificationEnabled(page, 'admin_product_out_of_stock', true);
    await setProductStock(page, OIL_PRODUCT_ID, 10, true);
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
