import { test } from '@playwright/test';
import { loginToAdmin } from './helpers';

test('promocja katalogowa: Staly rabat bez zakladki kanalu', async ({ page }) => {
  await loginToAdmin(page);

  await page.goto('/admin/catalog-promotions/new');

  const addAction = page.getByRole('button', { name: /Dodaj akcję/i });
  await addAction.click();

  const menu = addAction.locator('xpath=..').locator('.dropdown-menu');
  const items = menu.locator('a, button');
  const count = await items.count();
  for (let i = 0; i < count; i += 1) {
    console.log(`ITEM[${i}]`, JSON.stringify((await items.nth(i).innerText()).trim()));
  }

  await items.first().click();
  await page.waitForTimeout(2500);

  const html = await page.content();
  console.log('CHANNEL_TAB:', html.includes('channel-tab'), '| NAV_TABS:', html.includes('nav-tabs'), '| OMA_SKLEP:', html.includes('OMA Sklep'), '| KWOTA:', html.includes('Kwota'));

  await page.screenshot({ path: '/tmp/catalog-action-card.png', fullPage: true });
});
