import { expect, test } from '@playwright/test';
import { addFirstProductToCart, completeAddressStep, registerCustomer, uniqueEmail } from './helpers';

test.describe('PayU', () => {
  test('płatność PayU przekierowuje do sandboxa PayU', async ({ page }) => {
    const email = uniqueEmail('payu');
    await registerCustomer(page, email);
    await addFirstProductToCart(page);
    await completeAddressStep(page, email);

    if (page.url().includes('select-shipping')) {
      await page.getByRole('button', { name: 'Dalej' }).first().click();
    }

    await expect(page).toHaveURL(/select-payment/);

    const payu = page.locator('input[type="radio"][value="payu"]');
    await expect(payu, 'PayU ma być dostępne w checkoucie').toHaveCount(1);
    await payu.check();
    await page.getByRole('button', { name: 'Dalej' }).first().click();

    await expect(page).toHaveURL(/complete/);
    await page.getByRole('button', { name: /Złóż zamówienie/i }).click();

    await page.waitForURL(/snd\.payu\.com\/pay/, { timeout: 30_000 });
    expect(page.url(), 'PayU dostaje utworzone zamówienie i oddaje link do płatności').toMatch(/orderId=/);
  });
});
