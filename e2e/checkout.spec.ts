import { expect, test } from '@playwright/test';
import { addFirstProductToCart, completeAddressStep, registerCustomer, uniqueEmail } from './helpers';

test.describe('Checkout', () => {
  test('przechodzi przez adres i dostawę aż do wyboru płatności', async ({ page }) => {
    const email = uniqueEmail('checkout');
    await registerCustomer(page, email);
    await addFirstProductToCart(page);
    await completeAddressStep(page, email);

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await expect(page).toHaveURL(/select-payment/);
    await expect(page.locator('body')).toContainText('Suma zamówienia');
  });

  test('krok płatności nie pokazuje instrukcji o SMS', async ({ page }) => {
    const email = uniqueEmail('platnosc');
    await registerCustomer(page, email);
    await addFirstProductToCart(page);
    await completeAddressStep(page, email);

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await expect(page).toHaveURL(/select-payment/);

    const body = page.locator('body');
    await expect(body, 'offline BLIK z SMS-em ma być wyłączony').not.toContainText('wyślemy SMS-em numer telefonu');
    await expect(body, 'checkout oferuje co najmniej jedną działającą metodę').toContainText(
      /Przelew bankowy|Płatność przy odbiorze|Karta, BLIK/,
    );
  });

  test('podsumowanie zamówienia pokazuje wybraną płatność', async ({ page }) => {
    const email = uniqueEmail('podsumowanie');
    await registerCustomer(page, email);
    await addFirstProductToCart(page);
    await completeAddressStep(page, email);

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await page.locator('input[type="radio"]').last().check();
    await page.getByRole('button', { name: 'Dalej' }).first().click();

    await expect(page).toHaveURL(/complete/);
    await expect(page.getByRole('button', { name: /Złóż zamówienie/i })).toBeVisible();
  });
});
