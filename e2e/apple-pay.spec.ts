import { expect, test } from '@playwright/test';
import { addFirstProductToCart, completeAddressStep, registerCustomer, uniqueEmail } from './helpers';

const APPLE_PAY = 'input[type="radio"][value="payu_apple_pay"]';

async function goToPaymentStep(page: import('@playwright/test').Page, prefix: string): Promise<void> {
  const email = uniqueEmail(prefix);
  await registerCustomer(page, email);
  await addFirstProductToCart(page);
  await completeAddressStep(page, email);

  if (page.url().includes('select-shipping')) {
    await page.getByRole('button', { name: 'Dalej' }).first().click();
  }

  await expect(page).toHaveURL(/select-payment/);
}

test.describe('Apple Pay', () => {
  test('bez wsparcia Apple Pay metoda jest schowana w checkoucie', async ({ page }) => {
    await goToPaymentStep(page, 'applepay-off');

    await expect(page.locator(APPLE_PAY), 'metoda istnieje w formularzu').toHaveCount(1);
    await expect(page.locator(APPLE_PAY), 'ale nie jest pokazywana bez ApplePaySession').toBeHidden();
  });

  test('z ApplePaySession płatność idzie do PayU', async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(window, 'ApplePaySession', {
        value: { canMakePayments: () => true },
        configurable: true,
      });
    });

    await goToPaymentStep(page, 'applepay-on');

    const applePay = page.locator(APPLE_PAY);
    await expect(applePay).toBeVisible();
    await applePay.check();
    await page.getByRole('button', { name: 'Dalej' }).first().click();

    await expect(page).toHaveURL(/complete/);
    await page.getByRole('button', { name: /Złóż zamówienie/i }).click();

    await page.waitForURL(/snd\.payu\.com\/pay/, { timeout: 30_000 });
    expect(page.url(), 'PayU dostaje zamówienie z preselekcją Apple Pay').toMatch(/orderId=/);
  });
});
