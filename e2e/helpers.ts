import { expect, type Page } from '@playwright/test';

export const ADMIN = { username: 'admin', password: 'oma2026!' };

export const MAILPIT_URL = process.env.OMA_MAILPIT_URL ?? 'http://localhost:8025';

export const uniqueEmail = (prefix: string): string =>
  `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1000)}@oma.local`;

export async function loginToAdmin(page: Page): Promise<void> {
  await page.goto('/admin/login');
  await page.locator('input[name="_username"]').fill(ADMIN.username);
  await page.locator('input[name="_password"]').fill(ADMIN.password);
  await page.locator('form').getByRole('button').first().click();
  await expect(page).toHaveURL(/\/admin\/?$/);
}

export async function registerCustomer(page: Page, email: string, password = 'Haslo123!'): Promise<void> {
  await page.goto('/sklep/register');
  await page.fill('[name="sylius_shop_customer_registration[firstName]"]', 'Test');
  await page.fill('[name="sylius_shop_customer_registration[lastName]"]', 'Playwright');
  await page.fill('[name="sylius_shop_customer_registration[email]"]', email);
  await page.fill('[name="sylius_shop_customer_registration[user][plainPassword][first]"]', password);
  await page.fill('[name="sylius_shop_customer_registration[user][plainPassword][second]"]', password);
  await page.locator('form[name="sylius_shop_customer_registration"] button[type="submit"]').last().click();
  await expect(page).toHaveURL(/thank-you/);
}

export async function loginCustomer(page: Page, email: string, password = 'Haslo123!'): Promise<void> {
  await page.goto('/sklep/login');
  await page.locator('input[name="_username"]').fill(email);
  await page.locator('input[name="_password"]').fill(password);
  await page.locator('form[action*="login"] button[type="submit"]').last().click();
}

export async function findEmailFor(recipient: string): Promise<{ subject: string; html: string }> {
  const response = await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:${recipient}`)}`);
  const found = await response.json();
  const message = found.messages?.[0];

  if (!message) {
    throw new Error(`Brak maila do ${recipient} w Mailpicie`);
  }

  const detail = await (await fetch(`${MAILPIT_URL}/api/v1/message/${message.ID}`)).json();

  return { subject: message.Subject, html: detail.HTML ?? detail.Text ?? '' };
}

export async function addFirstProductToCart(page: Page): Promise<string> {
  await page.goto('/sklep/kategorie/aromaterapia');
  await page.locator('a[href*="/sklep/produkty/"]').first().click();
  await expect(page.locator('#add-to-cart-button')).toBeVisible();

  const productName = (await page.locator('h1.text-break').first().textContent())?.trim() ?? '';

  await page.locator('#add-to-cart-button').click();
  await page.waitForTimeout(2000);

  return productName;
}

export async function completeAddressStep(page: Page, email: string): Promise<void> {
  await page.goto('/sklep/checkout/address');

  const fill = async (field: string, value: string) => {
    const locator = page.locator(`[name="sylius_shop_checkout_address[${field}]"]`).first();

    if (!(await locator.count())) {
      return;
    }

    const tag = await locator.evaluate((element) => element.tagName);
    await (tag === 'SELECT' ? locator.selectOption(value) : locator.fill(value));
  };

  await fill('customer][email', email);
  await fill('billingAddress][firstName', 'Test');
  await fill('billingAddress][lastName', 'Playwright');
  await fill('billingAddress][street', 'Karmelicka 1');
  await fill('billingAddress][city', 'Kraków');
  await fill('billingAddress][postcode', '31-000');
  await fill('billingAddress][countryCode', 'PL');

  await page.getByRole('button', { name: 'Dalej' }).first().click();
  await expect(page).toHaveURL(/select-shipping|select-payment/);
}
