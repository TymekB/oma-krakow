import { expect, test } from '@playwright/test';
import { loginToAdmin } from './helpers';

const OPTION_NAME = 'Voucher';

type Page = import('@playwright/test').Page;

type Product = { id: string; code: string };

let created: Product | null = null;

async function createProductWithOption(page: Page): Promise<Product> {
  const code = `E2E_BRUTTO_${Date.now()}`;
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

  created = { id: page.url().match(/\/products\/(\d+)\/edit/)![1], code };

  return created;
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

test.describe('Ceny brutto przy generowaniu wariantów', () => {
  test.afterEach(async ({ page }) => {
    if (null !== created) {
      await deleteProduct(page, created.code);
      created = null;
    }
  });

  test('każde pole ceny w każdym wariancie dostaje lustro brutto', async ({ page }) => {
    const { id } = await createProductWithOption(page);
    await page.goto(`/admin/products/${id}/variants/generate`);

    const containers = page.locator('[data-oma-price-tax]');
    const priceFields = page.locator('input[id$="_price"], input[id$="_originalPrice"], input[id$="_minimumPrice"]');
    const mirrors = page.locator('.oma-price-mirror');

    await expect(containers.first()).toHaveAttribute('data-rate', '0.23');
    await expect(containers.first()).toHaveAttribute('data-included', '0');
    await expect(containers.first()).toHaveAttribute('data-mirror-label', 'brutto (VAT 23%)');

    expect(await containers.count(), 'jeden kontener na wariant').toBe(3);
    expect(await mirrors.count(), 'lustro pod każdym polem ceny').toBe(await priceFields.count());
  });

  test('netto i brutto przeliczają się w obie strony', async ({ page }) => {
    const { id } = await createProductWithOption(page);
    await page.goto(`/admin/products/${id}/variants/generate`);

    const net = page.locator('input[id$="_price"]').first();
    const gross = page.locator('.oma-price-mirror input').first();

    await net.fill('');
    await net.type('50');
    await expect(gross).toHaveValue('61,50');

    await gross.fill('');
    await gross.type('123');
    await expect(net).toHaveValue('100,00');

    await net.fill('');
    await expect(gross, 'puste pole nie pokazuje zera').toHaveValue('');
  });

  test('lustro nie psuje zapisu — Generuj tworzy warianty z wpisanymi cenami', async ({ page }) => {
    const { id, code } = await createProductWithOption(page);
    await page.goto(`/admin/products/${id}/variants/generate`);

    const codes = page.locator('input[id$="_code"]');
    const prices = page.locator('input[id$="_price"]');
    const rows = await codes.count();

    for (let i = 0; i < rows; i++) {
      await codes.nth(i).fill(`${code}-${i}`);
      await prices.nth(i).fill('50');
    }

    await page.getByRole('button', { name: 'Generuj' }).click();
    await expect(page, 'formularz przechodzi, nie wraca z błędem walidacji').toHaveURL(/\/variants\/$/);

    const variantRows = page.locator('tbody tr', { hasText: code });
    await expect(variantRows).toHaveCount(rows);
  });
});
