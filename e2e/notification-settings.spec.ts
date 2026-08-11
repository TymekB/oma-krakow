import { expect, test } from '@playwright/test';
import { addFirstProductToCart, completeAddressStep, loginToAdmin, uniqueEmail, MAILPIT_URL } from './helpers';

const countAdminNotifications = async (): Promise<number> => {
  const response = await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent('subject:"Nowe zamówienie"')}`);
  const found = await response.json();

  return found.messages_count ?? found.total ?? 0;
};

const placeOrder = async (page: import('@playwright/test').Page): Promise<void> => {
  const email = uniqueEmail('zdarzenia');

  await addFirstProductToCart(page);
  await completeAddressStep(page, email);

  if (page.url().includes('select-shipping')) {
    await page.getByRole('button', { name: 'Dalej' }).first().click();
  }

  await page.locator('input[type="radio"]:visible').last().check();
  await page.getByRole('button', { name: 'Dalej' }).first().click();
  await expect(page).toHaveURL(/complete/);

  await page.getByRole('button', { name: /Złóż zamówienie/i }).click();
  await expect(page).toHaveURL(/thank-you|after-pay|order/, { timeout: 30_000 });
};

const setAdminNotification = async (page: import('@playwright/test').Page, enabled: boolean): Promise<void> => {
  await page.goto('/admin/zdarzenia');

  const checkbox = page.locator('#event-admin_order_placed');
  await expect(checkbox).toBeVisible();
  await checkbox.setChecked(enabled);

  await page.getByRole('button', { name: 'Zapisz zmiany' }).click();
  await expect(page.locator('#event-admin_order_placed')).toBeChecked({ checked: enabled });
};

test.describe('Zdarzenia w panelu', () => {
  test('sekcja jest w konfiguracji i pokazuje zdarzenia', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/zdarzenia');

    await expect(page.getByRole('heading', { name: 'Zdarzenia' })).toBeVisible();
    await expect(page.locator('#event-admin_order_placed')).toBeVisible();
    await expect(page.locator('#event-order_confirmation')).toBeVisible();
  });

  test('tabela pokazuje odbiorcę powiadomienia', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/zdarzenia');

    await expect(page.getByRole('columnheader', { name: 'Powiadomienie dla' })).toBeVisible();

    const adminRow = page.locator('tr', { has: page.locator('#event-admin_order_placed') });
    await expect(adminRow, 'zdarzenie administratora ma odbiorcę Administrator').toContainText('Administrator');

    const userRow = page.locator('tr', { has: page.locator('#event-order_confirmation') });
    await expect(userRow, 'potwierdzenie zamówienia ma odbiorcę Użytkownik').toContainText('Użytkownik');
  });

  test('wyłączone zdarzenie wstrzymuje maila, włączone znów go wysyła', async ({ page }) => {
    await loginToAdmin(page);

    await setAdminNotification(page, false);
    const before = await countAdminNotifications();

    await placeOrder(page);
    expect(await countAdminNotifications(), 'przy wyłączonym zdarzeniu mail nie wychodzi').toBe(before);

    await setAdminNotification(page, true);

    await placeOrder(page);
    expect(await countAdminNotifications(), 'po włączeniu mail znów wychodzi').toBe(before + 1);
  });
});
