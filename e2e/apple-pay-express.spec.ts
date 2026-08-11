import { expect, test } from '@playwright/test';
import { addFirstProductToCart } from './helpers';

type JsonCall = { status: number; body: Record<string, unknown> };

const SHEET_CONTACT = {
  emailAddress: 'apple.pay@oma.local',
  givenName: 'Natalia',
  familyName: 'Testowa',
  phoneNumber: '+48123456789',
  addressLines: ['Karmelicka 7', 'm. 3'],
  locality: 'Kraków',
  postalCode: '31-000',
  countryCode: 'PL',
};

async function postJson(
  page: import('@playwright/test').Page,
  url: string,
  payload: Record<string, unknown>,
): Promise<JsonCall> {
  return page.evaluate(
    async ({ url, payload }) => {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      return { status: response.status, body: await response.json() };
    },
    { url, payload },
  );
}

test.describe('Apple Pay express checkout', () => {
  test('zachęta i przycisk nie renderują się bez APPLE_PAY_MERCHANT_ID', async ({ page }) => {
    await addFirstProductToCart(page);
    await page.goto('/sklep/cart/');

    await expect(page.locator('[data-oma-apple-pay-express]')).toHaveCount(0);
    await expect(page.locator('[data-oma-apple-pay]')).toHaveCount(0);
  });

  test('arkusz dostaje metody wysyłki policzone dla adresu', async ({ page }) => {
    await addFirstProductToCart(page);

    const response = await postJson(page, '/sklep/apple-pay/shipping-methods', {
      shippingContact: { countryCode: 'PL', postalCode: '31-000', locality: 'Kraków' },
    });

    expect(response.status, JSON.stringify(response.body)).toBe(200);

    const methods = response.body.shippingMethods as Array<Record<string, string>>;
    expect(methods.length, 'sklep zwraca metody wysyłki dla PL').toBeGreaterThan(0);
    expect(methods[0].identifier).toBeTruthy();
    expect(methods[0].amount).toMatch(/^\d+\.\d{2}$/);
  });

  test('zamówienie powstaje z adresu z arkusza i wchodzi w płatność PayU', async ({ page }) => {
    await addFirstProductToCart(page);

    const shipping = await postJson(page, '/sklep/apple-pay/shipping-methods', {
      shippingContact: { countryCode: 'PL', postalCode: '31-000', locality: 'Kraków' },
    });
    const methods = shipping.body.shippingMethods as Array<Record<string, string>>;

    const order = await postJson(page, '/sklep/apple-pay/order', {
      shippingContact: SHEET_CONTACT,
      billingContact: SHEET_CONTACT,
      shippingMethod: methods[0].identifier,
      paymentToken: { data: 'sandbox-token', version: 'EC_v1' },
    });

    expect(order.status, JSON.stringify(order.body)).toBe(200);
    expect(order.body.tokenValue, 'zamówienie dostało token').toBeTruthy();
    expect(order.body.redirectUrl as string).toContain('/pay');

    await page.goto(order.body.redirectUrl as string);
    await page.waitForURL(/snd\.payu\.com\/pay/, { timeout: 30_000 });
  });

  test('bez kompletu danych z arkusza zamówienie nie powstaje', async ({ page }) => {
    await addFirstProductToCart(page);

    const order = await postJson(page, '/sklep/apple-pay/order', {
      shippingContact: { countryCode: 'PL', postalCode: '31-000', locality: 'Kraków' },
    });

    expect(order.status).toBe(422);
    expect(order.body.error).toBeTruthy();
  });
});
