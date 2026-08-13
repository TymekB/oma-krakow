import { expect, test } from '@playwright/test';
import { loginToAdmin } from './helpers';

const CODE = `E2E_BEZ_WARIANTOW_${Date.now()}`;

const SLUG = CODE.toLowerCase().replace(/_/g, '-');

type Page = import('@playwright/test').Page;

async function createConfigurableProduct(page: Page): Promise<void> {
  await loginToAdmin(page);
  await page.goto('/admin/products/new');

  await page.fill('#sylius_admin_product_code', CODE);

  await page.locator('[data-bs-target="#product-translations"]').first().click();
  await page.fill('#sylius_admin_product_translations_pl_PL_name', CODE);
  await page.fill('#sylius_admin_product_translations_pl_PL_slug', SLUG);

  await page.getByRole('button', { name: 'Utwórz' }).click();
  await expect(page).toHaveURL(/\/admin\/products\/\d+\/edit/);
}

async function deleteProduct(page: Page): Promise<void> {
  await page.goto('/admin/products/');

  const row = page.locator('tr', { hasText: CODE }).first();

  if (0 === (await row.count())) {
    return;
  }

  await row.locator('button[data-bs-target^="#delete-modal-"]').click();
  await page.locator('.modal.show form button[type="submit"]').click();

  await expect(page.locator('tbody')).not.toContainText(CODE);
}

test.describe('Produkt bez wariantów', () => {
  test.afterEach(async ({ page }) => {
    await deleteProduct(page);
  });

  test('nie wywraca sklepu i nie pojawia się na liście', async ({ page }) => {
    await createConfigurableProduct(page);

    const home = await page.request.get('/sklep/');
    expect(home.status(), 'strona główna sklepu odpowiada 200').toBe(200);

    const listing = await page.request.get('/sklep/kategorie/pielegnacja');
    expect(listing.status(), 'listing kategorii odpowiada 200').toBe(200);
    expect(await listing.text(), 'produkt bez wariantów nie trafia na listing').not.toContain(SLUG);

    const card = await page.request.get(`/sklep/produkty/${SLUG}`);
    expect(card.status(), 'karta produktu bez wariantów to 404, nie 500').toBe(404);
  });
});
