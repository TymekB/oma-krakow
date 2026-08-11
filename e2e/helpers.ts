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

export async function registerVerifiedCustomer(page: Page, email: string, password = 'Haslo123!'): Promise<void> {
  await registerCustomer(page, email, password);

  const message = await findEmailFor(email);
  const link = message.html.match(/https?:\/\/[^"'\s<>]*\/sklep\/verify\/[^"'\s<>]+/)?.[0];

  if (!link) {
    throw new Error(`Brak linku weryfikacyjnego w mailu do ${email}`);
  }

  await page.goto(link);
  await page.goto('/sklep/login');

  if (await page.locator('input[name="_username"]').count()) {
    await loginCustomer(page, email, password);
  }

  await page.goto('/sklep/account/dashboard');
  await expect(page, `konto ${email} jest zalogowane`).not.toHaveURL(/\/sklep\/login/);
}

export async function findEmailFor(recipient: string, subject?: string): Promise<{ subject: string; html: string }> {
  const query = subject === undefined ? `to:${recipient}` : `to:${recipient} subject:"${subject}"`;
  const response = await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`);
  const found = await response.json();
  const message = found.messages?.[0];

  if (!message) {
    throw new Error(`Brak maila do ${recipient} w Mailpicie`);
  }

  const detail = await (await fetch(`${MAILPIT_URL}/api/v1/message/${message.ID}`)).json();

  return { subject: message.Subject, html: detail.HTML ?? detail.Text ?? '' };
}

export async function loginToAdminIfNeeded(page: Page): Promise<void> {
  await page.goto('/admin/login');

  if (!(await page.locator('input[name="_username"]').count())) {
    return;
  }

  await page.locator('input[name="_username"]').fill(ADMIN.username);
  await page.locator('input[name="_password"]').fill(ADMIN.password);
  await page.locator('form').getByRole('button').first().click();
  await expect(page).toHaveURL(/\/admin\/?$/);
}

export async function setNotificationEnabled(page: Page, code: string, enabled: boolean): Promise<void> {
  await loginToAdminIfNeeded(page);
  await page.goto('/admin/zdarzenia');

  await page.locator(`input[value="${code}"]`).setChecked(enabled, { force: true });
  await page.getByRole('button', { name: /Zapisz/i }).first().click();

  await expect(page.locator(`input[value="${code}"]`), `zdarzenie ${code} zapisane`).toBeChecked({ checked: enabled });
}

export async function countEmailsFor(recipient: string, subject: string): Promise<number> {
  const query = `to:${recipient} subject:"${subject}"`;
  const response = await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`);
  const found = await response.json();

  return found.messages_count ?? 0;
}

export async function readReservedStock(page: Page, variantId: number): Promise<number> {
  await loginToAdminIfNeeded(page);
  await page.goto('/admin/inventory/');

  const reserved = page.locator(`span.onHold[data-product-variant-id="${variantId}"]`);

  return (await reserved.count()) ? Number(await reserved.first().innerText()) : 0;
}

export async function setProductStock(page: Page, productId: number, onHand: number, tracked: boolean): Promise<void> {
  await loginToAdminIfNeeded(page);
  await page.goto(`/admin/products/${productId}/edit`);
  await page.locator('[data-bs-target="#product-inventory"]').click();

  const trackedInput = page.locator('[name="sylius_admin_product[variant][tracked]"]');
  await trackedInput.setChecked(tracked, { force: true });
  await page.fill('[name="sylius_admin_product[variant][onHand]"]', String(onHand));
  await page.getByRole('button', { name: /Aktualizuj|Zapisz/i }).first().click();

  await expect(page.locator('body'), `stan produktu ${productId} zapisany`).not.toContainText('zawiera błędy');
  await expect(trackedInput).toBeChecked({ checked: tracked });
  await expect(page.locator('[name="sylius_admin_product[variant][onHand]"]')).toHaveValue(String(onHand));
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

export async function placeOfflineOrder(page: Page, email: string): Promise<string> {
  await completeAddressStep(page, email);

  if (page.url().includes('select-shipping')) {
    await page.getByRole('button', { name: 'Dalej' }).first().click();
  }

  await page.locator('input[type="radio"]:visible').last().check();
  await page.getByRole('button', { name: 'Dalej' }).first().click();
  await expect(page).toHaveURL(/complete/);

  await page.getByRole('button', { name: /Złóż zamówienie/i }).click();
  await expect(page).toHaveURL(/thank-you|after-pay|order/, { timeout: 30_000 });

  await page.goto('/sklep/account/orders');
  const href = await page.getByRole('link', { name: 'Pokaż' }).first().getAttribute('href');
  const number = href?.split('/').pop();

  if (!number) {
    throw new Error(`Brak zamówienia na liście konta ${email}`);
  }

  return number;
}

export async function completeOrderPaymentInAdmin(page: Page, orderNumber: string): Promise<void> {
  await loginToAdmin(page);
  await page.goto(`/admin/orders/?criteria[number][value]=${orderNumber}`);
  await page.getByRole('link', { name: `#${orderNumber}` }).first().click();

  const completePayment = page.locator('form[action*="/payments/"][action$="/complete"]');
  await completePayment.locator('button[type="submit"]').first().click();
  await expect(completePayment).toHaveCount(0);
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
  await fill('customer][firstName', 'Test');
  await fill('customer][lastName', 'Playwright');
  await fill('customer][phoneNumber', '728761171');
  await fill('billingAddress][firstName', 'Test');
  await fill('billingAddress][lastName', 'Playwright');
  await fill('billingAddress][street', 'Karmelicka 1');
  await fill('billingAddress][city', 'Kraków');
  await fill('billingAddress][postcode', '31-000');
  await fill('billingAddress][countryCode', 'PL');

  await page.getByRole('button', { name: 'Dalej' }).first().click();
  await expect(page).toHaveURL(/select-shipping|select-payment/);
}
