import { expect, test } from '@playwright/test';
import {
  addFirstProductToCart,
  completeAddressStep,
  findEmailFor,
  registerCustomer,
  uniqueEmail,
} from './helpers';

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

  test('gość widzi podpowiedź o logowaniu i wraca na krok adresu', async ({ page }) => {
    const email = uniqueEmail('podpowiedz');
    await registerCustomer(page, email);

    const message = await findEmailFor(email);
    const verificationLink = message.html.match(/https?:\/\/[^"'\s<>]*\/sklep\/verify\/[^"'\s<>]+/)?.[0];
    expect(verificationLink, 'mail zawiera link weryfikacyjny').toBeTruthy();
    await page.goto(verificationLink!);
    await page.goto('/sklep/logout');

    await addFirstProductToCart(page);
    await page.goto('/sklep/checkout/address');

    const hint = page.locator('.oma-login-hint');
    await expect(hint).toContainText('Masz konto?');

    await hint.getByRole('link', { name: 'Zaloguj się' }).click();
    await page.locator('input[name="_username"]').fill(email);
    await page.locator('input[name="_password"]').fill('Haslo123!');
    await page.locator('form[action*="login"] button[type="submit"]').last().click();

    await expect(page, 'po zalogowaniu wracamy do checkoutu, nie na stronę główną').toHaveURL(
      /\/sklep\/checkout\/address/,
    );
    await expect(page.locator('.oma-login-hint'), 'zalogowany nie widzi podpowiedzi').toHaveCount(0);
  });

  test('_target_path nie pozwala przekierować logowania na obcy host', async ({ page }) => {
    await page.goto('/sklep/login?_target_path=https://example.com/');
    await expect(page.locator('input[name="_target_path"]')).toHaveCount(0);

    await page.goto('/sklep/login?_target_path=//example.com/');
    await expect(page.locator('input[name="_target_path"]')).toHaveCount(0);
  });

  test('niezalogowany odzyskuje wpisany adres po powrocie na checkout', async ({ page }) => {
    const field = (name: string) => `[name="sylius_shop_checkout_address[${name}]"]`;
    const address = {
      'customer][email': 'gosc@oma.local',
      'billingAddress][firstName': 'Tymoteusz',
      'billingAddress][lastName': 'Baran',
      'billingAddress][street': 'Romana Kiełkowskiego 10a/58',
      'billingAddress][city': 'Kraków',
      'billingAddress][postcode': '30-704',
      'billingAddress][phoneNumber': '728761171',
    };

    await addFirstProductToCart(page);
    await page.goto('/sklep/checkout/address');

    for (const [name, value] of Object.entries(address)) {
      await page.fill(field(name), value);
    }
    await page.selectOption(field('billingAddress][countryCode'), 'PL');
    await page.locator('body').click();

    await page.goto('/sklep');
    await page.goto('/sklep/checkout/address');

    for (const [name, value] of Object.entries(address)) {
      await expect(page.locator(field(name)), `${name} odtworzone`).toHaveValue(value);
    }
    await expect(page.locator(field('billingAddress][countryCode'))).toHaveValue('PL');
  });
});
