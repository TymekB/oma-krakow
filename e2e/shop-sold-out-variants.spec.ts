import { expect, test } from '@playwright/test';
import { loginToAdmin } from './helpers';

const OPTION_NAME = 'Voucher';

const SOLD_OUT = '(wyprzedany)';

type Page = import('@playwright/test').Page;

type Product = { id: string; code: string; slug: string };

let created: Product | null = null;

function newCode(): string {
  return `E2E_WYPRZEDANE_${Date.now()}`;
}

async function createProductWithVariants(page: Page): Promise<Product> {
  const code = newCode();
  const slug = code.toLowerCase().replace(/_/g, '-');

  await loginToAdmin(page);
  await page.goto('/admin/products/new');

  await page.fill('#sylius_admin_product_code', code);

  await page.locator('#sylius_admin_product_options-ts-control').click();
  await page.keyboard.type(OPTION_NAME);
  await page.locator(`.ts-dropdown .option:has-text("${OPTION_NAME}")`).first().click();

  await page.locator('[data-bs-target="#product-translations"]').first().click();
  await page.fill('#sylius_admin_product_translations_pl_PL_name', code);
  await page.fill('#sylius_admin_product_translations_pl_PL_slug', slug);

  await page.getByRole('button', { name: 'Utwórz' }).click();
  await expect(page).toHaveURL(/\/admin\/products\/\d+\/edit/);

  const id = page.url().match(/\/products\/(\d+)\/edit/)![1];
  created = { id, code, slug };

  await page.goto(`/admin/products/${id}/variants/generate`);

  const codes = page.locator('input[id$="_code"]');
  const prices = page.locator('input[id$="_price"]');

  for (let i = 0; i < (await codes.count()); i++) {
    await codes.nth(i).fill(`${code}-${i}`);
    await prices.nth(i).fill('50');
  }

  await page.getByRole('button', { name: 'Generuj' }).click();
  await expect(page.locator('body')).toContainText(code);

  return created;
}

async function variantEditPaths(page: Page, productId: string): Promise<string[]> {
  await page.goto(`/admin/products/${productId}/variants/`);

  return page.evaluate(() =>
    Array.from(document.querySelectorAll('a[href*="/variants/"][href$="/edit"]')).map((el) => el.getAttribute('href')!),
  );
}

async function sellOut(page: Page, variantPath: string): Promise<void> {
  await page.goto(variantPath);
  await page.locator('[data-bs-target="#product-variant-inventory"]').first().click();

  await page.locator('[name="sylius_admin_product_variant[tracked]"]').setChecked(true, { force: true });
  await page.fill('[name="sylius_admin_product_variant[onHand]"]', '0');

  await page.getByRole('button', { name: /Aktualizuj|Zapisz/i }).first().click();
  await expect(page.locator('body')).not.toContainText('zawiera błędy');
}

async function useOptionMatching(page: Page, productId: string): Promise<void> {
  await page.goto(`/admin/products/${productId}/edit`);
  await page.selectOption('#sylius_admin_product_variantSelectionMethod', 'match');

  await page.getByRole('button', { name: /Aktualizuj|Zapisz/i }).first().click();
  await expect(page.locator('body')).not.toContainText('zawiera błędy');
}

async function deleteProduct(page: Page, code: string): Promise<void> {
  await page.goto('/admin/products/');

  const row = page.locator('tr', { hasText: code }).first();

  if (0 === (await row.count())) {
    return;
  }

  await row.locator('button[data-bs-target^="#delete-modal-"]').click();
  await page.locator('.modal.show form button[type="submit"]').click();

  await expect(page.locator('tbody')).not.toContainText(code);
}

test.describe('Wyprzedane warianty w sklepie', () => {
  test.afterEach(async ({ page }) => {
    if (null !== created) {
      await deleteProduct(page, created.code);
      created = null;
    }
  });

  test('wyprzedany wariant jest opisany, nieklikalny i pominięty przy wyborze', async ({ page }) => {
    const { id, slug } = await createProductWithVariants(page);
    const variants = await variantEditPaths(page, id);
    await sellOut(page, variants[0]);

    await page.goto(`/sklep/produkty/${slug}`);

    const soldOutRow = page.locator('tr', { hasText: SOLD_OUT });
    await expect(soldOutRow, 'dokładnie jeden wariant jest wyprzedany').toHaveCount(1);
    await expect(soldOutRow.locator('input[type="radio"]'), 'nie da się go kliknąć').toBeDisabled();

    const checked = page.locator('input[type="radio"]:checked');
    await expect(checked, 'preselekcja przeskakuje na dostępny wariant').toHaveCount(1);
    await expect(page.locator('tr', { has: checked })).not.toContainText(SOLD_OUT);

    await useOptionMatching(page, id);
    await page.goto(`/sklep/produkty/${slug}`);

    const select = page.locator('select[name*="[variant]"]').first();
    const soldOutOption = select.locator('option', { hasText: SOLD_OUT });

    await expect(soldOutOption).toHaveCount(1);
    await expect(soldOutOption).toHaveAttribute('disabled', 'disabled');

    const selectedLabel = await select.locator('option:checked').first().textContent();
    expect(selectedLabel, 'wybrana wartość opcji jest dostępna').not.toContain(SOLD_OUT);
  });

  test('produkt bez ani jednego dostępnego wariantu znika ze sklepu', async ({ page }) => {
    const { id, slug } = await createProductWithVariants(page);

    for (const variant of await variantEditPaths(page, id)) {
      await sellOut(page, variant);
    }

    const home = await page.request.get('/sklep/');
    expect(home.status(), 'strona główna nadal działa').toBe(200);

    const listing = await page.request.get('/sklep/kategorie/pielegnacja');
    expect(listing.status()).toBe(200);
    expect(await listing.text(), 'wyprzedany produkt nie wisi na listingu').not.toContain(slug);

    const card = await page.request.get(`/sklep/produkty/${slug}`);
    expect(card.status(), 'karta wyprzedanego produktu to 404').toBe(404);
  });
});
