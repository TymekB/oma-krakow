import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const IMG = join(HERE, 'img');
const PDF = join(HERE, '..', 'OMA-instrukcja-panelu.pdf');
const BASE = process.env.OMA_BASE_URL ?? 'http://localhost:8080';
const ADMIN = { username: 'admin', password: process.env.OMA_ADMIN_PASSWORD ?? 'oma2026!' };

const RAW = mkdtempSync(join(tmpdir(), 'oma-instrukcja-'));
mkdirSync(IMG, { recursive: true });

const LISTY = [
  ['10-produkty-lista', '/admin/products/'],
  ['12-kategorie', '/admin/taxons/new'],
  ['13-magazyn', '/admin/inventory/'],
  ['14-cechy', '/admin/product-attributes/'],
  ['15-warianty', '/admin/product-options/'],
  ['16-powiazania', '/admin/product-association-types/'],
  ['20-zamowienia', '/admin/orders/'],
  ['22-platnosci', '/admin/payments/'],
  ['23-przesylki', '/admin/shipments/'],
  ['24-metody-platnosci', '/admin/payment-methods/'],
  ['25-sposoby-wysylki', '/admin/shipping-methods/'],
  ['26-kategorie-wysylki', '/admin/shipping-categories/'],
  ['30-klienci', '/admin/customers/'],
  ['31-grupy-klientow', '/admin/customer-groups/'],
  ['40-promocje-koszyka', '/admin/promotions/'],
  ['41-promocje-katalogu', '/admin/catalog-promotions/'],
  ['42-recenzje', '/admin/product-reviews/'],
  ['50-kursy-walut', '/admin/exchange-rates/'],
  ['51-ustawienia-regionalne', '/admin/locales/'],
  ['52-kategorie-podatkowe', '/admin/tax-categories/'],
  ['53-stawki-podatkowe', '/admin/tax-rates/'],
  ['54-administratorzy', '/admin/users/'],
  ['55-edycja-strony', '/admin/site/edit'],
];

const KANAL = process.env.OMA_CHANNEL_CODE ?? 'OMA_WEB';

const PRODUKT_DEMO = { kod: 'KREM_NAWILZAJACY', nazwa: 'Krem nawilżający', slug: 'krem-nawilzajacy' };

const WARTOSCI_OPCJI = [
  ['POJEMNOSC_30', '30 ml', '89.00'],
  ['POJEMNOSC_50', '50 ml', '129.00'],
];

const ZAKLADKI_PRODUKTU = [
  ['Tłumaczenia', '11a-produkt-tlumaczenia'],
  ['Ceny', '11b-produkt-ceny'],
  ['Kategorie', '11c-produkt-kategorie'],
  ['Magazyn', '11d-produkt-magazyn'],
  ['Media', '11e-produkt-media'],
  ['Podatki', '11f-produkt-podatki'],
];

const browser = await chromium.launch();
const context = await browser.newContext({ locale: 'pl-PL', viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
const page = await context.newPage();

async function zrzut(nazwa, maxWysokosc = 1500) {
  await page.waitForTimeout(700);
  const pelna = await page.evaluate(() => document.documentElement.scrollHeight);
  await page.screenshot({
    path: join(RAW, `${nazwa}.png`),
    clip: { x: 0, y: 0, width: 1440, height: Math.min(pelna, maxWysokosc) },
  });
}

async function idz(sciezka) {
  await page.goto(`${BASE}${sciezka}`, { waitUntil: 'networkidle' }).catch(() => {});
}

await idz('/admin/login');
await zrzut('01-login', 900);
await page.locator('input[name="_username"]').fill(ADMIN.username);
await page.locator('input[name="_password"]').fill(ADMIN.password);
await page.locator('form').getByRole('button').first().click();
await page.waitForURL(/\/admin\/?$/);
await zrzut('02-pulpit', 1400);

for (const [nazwa, sciezka] of LISTY) {
  await idz(sciezka);
  await zrzut(nazwa, 1400);
}

await idz('/admin/products/');
const utworz = page.getByRole('button', { name: /Utwórz/i }).first();
if (await utworz.count()) {
  await utworz.click();
  await zrzut('10a-produkt-utworz', 900);
}

await idz('/admin/products/new/simple');
await zrzut('10b-produkt-nowy');

await idz('/admin/products/');
const produkt = await page.evaluate(
  () => [...document.querySelectorAll('a')].map((a) => a.getAttribute('href') ?? '').find((h) => /\/admin\/products\/\d+\/edit/.test(h))?.match(/(\d+)/)?.[1] ?? null,
);

if (produkt) {
  await idz(`/admin/products/${produkt}/edit`);
  await zrzut('11-produkt-edycja');

  for (const [etykieta, nazwa] of ZAKLADKI_PRODUKTU) {
    const zakladka = page.locator('#side-nav button', { hasText: new RegExp(`^\\s*${etykieta}\\s*$`) }).first();

    if (await zakladka.count()) {
      await zakladka.click();
      await zrzut(nazwa);
    }
  }
}

await idz('/admin/orders/');
const zamowienie = await page.evaluate(
  () => [...document.querySelectorAll('table a')].map((a) => a.getAttribute('href')).find((h) => /\/admin\/orders\/\d+/.test(h ?? '')) ?? null,
);
if (zamowienie) {
  await idz(zamowienie);
  await zrzut('21-zamowienie-szczegoly', 1700);
}

await idz('/admin/taxons/new');
const kategoria = await page.evaluate(
  () => [...document.querySelectorAll('a')].map((a) => a.getAttribute('href')).find((h) => /\/admin\/taxons\/\d+\/edit/.test(h ?? '')) ?? null,
);
if (kategoria) {
  await idz(kategoria);
  await zrzut('12a-kategoria-edycja');
}

await idz('/admin/site/edit');
await page.waitForTimeout(2500);
const historia = page.getByRole('button', { name: /Historia zmian/i }).first();
if (await historia.count()) {
  await historia.click();
  await zrzut('55a-historia-zmian', 1000);
}

await produktKonfigurowalny();

/**
 * Rozdzial 5 pokazuje produkt konfigurowalny, ktorego sklep na co dzien nie ma.
 * Dane demo powstaja tylko na czas zrzutow i sa kasowane na koncu.
 */
async function produktKonfigurowalny() {
  page.on('dialog', (okno) => okno.accept());

  await usunDemo();

  await idz('/admin/product-options/new');
  await page.fill('[name="sylius_admin_product_option[code]"]', 'POJEMNOSC');
  await page.fill('[name="sylius_admin_product_option[translations][pl_PL][name]"]', 'Pojemność');

  const dodajWartosc = page.getByRole('button', { name: /Dodaj wartość/i }).first();
  for (const _ of WARTOSCI_OPCJI) {
    await dodajWartosc.click();
    await page.waitForTimeout(500);
  }
  for (const [i, [kod, etykieta]] of WARTOSCI_OPCJI.entries()) {
    await page.fill(`[name="sylius_admin_product_option[values][${i}][code]"]`, kod);
    await page.fill(`[name="sylius_admin_product_option[values][${i}][translations][pl_PL][value]"]`, etykieta);
  }
  await page.getByRole('button', { name: /^Utwórz$/ }).first().click();
  await page.waitForLoadState('networkidle');
  const opcja = page.url().match(/product-options\/(\d+)/)?.[1];

  await idz(`/admin/product-options/${opcja}/edit`);
  await zrzut('10d-opcja-wartosci', 1600);

  await idz('/admin/products/new');
  await page.fill('[name="sylius_admin_product[code]"]', PRODUKT_DEMO.kod);
  await page.locator('.ts-wrapper').first().click();
  await page.waitForTimeout(500);
  await page.locator('.ts-dropdown .option', { hasText: 'Pojemność' }).first().click();
  await page.waitForTimeout(400);
  await page.evaluate(() => document.activeElement?.blur());
  await zrzut('10c-produkt-konfigurowalny', 1000);

  await page.locator('#side-nav button', { hasText: /^\s*Tłumaczenia\s*$/ }).first().click();
  await page.waitForTimeout(500);
  await page.fill('[name="sylius_admin_product[translations][pl_PL][name]"]', PRODUKT_DEMO.nazwa);
  await page.fill('[name="sylius_admin_product[translations][pl_PL][slug]"]', PRODUKT_DEMO.slug);
  await page.getByRole('button', { name: /^Utwórz$/ }).first().click();
  await page.waitForLoadState('networkidle');
  const produkt = page.url().match(/products\/(\d+)\/edit/)?.[1];

  if (!produkt) {
    console.warn('Nie udalo sie utworzyc produktu demo — pomijam zrzuty wariantow');
    await usunDemo();
    return;
  }

  await page.getByRole('button', { name: /Zarządzaj wariantami/i }).first().click();
  await zrzut('10e-zarzadzaj-wariantami', 1000);

  await idz(`/admin/products/${produkt}/variants/generate`);
  await zrzut('10f-generowanie-wariantow', 1400);

  for (const [i, [, etykieta, cena]] of WARTOSCI_OPCJI.entries()) {
    await page.fill(`[name="sylius_admin_product_generate_variants[variants][${i}][code]"]`, `${PRODUKT_DEMO.kod}-${etykieta.replace(' ', '').toUpperCase()}`);
    await page.fill(`[name="sylius_admin_product_generate_variants[variants][${i}][channelPricings][${KANAL}][price]"]`, cena);
  }
  await page.getByRole('button', { name: /^Generuj$/ }).first().click();
  await page.waitForLoadState('networkidle');

  await idz(`/admin/products/${produkt}/variants/`);
  await zrzut('10g-warianty-lista', 1000);

  await usunDemo();
}

async function usunDemo() {
  for (const [lista, tekst] of [['/admin/products/', PRODUKT_DEMO.nazwa], ['/admin/product-options/', 'Pojemność']]) {
    await idz(lista);
    const wiersz = page.locator('tr', { hasText: tekst }).first();

    if (!(await wiersz.count())) {
      continue;
    }

    // ikona kosza otwiera modal; przycisk potwierdzenia siedzi w tym modalu, nie w wierszu
    const kosz = wiersz.locator('button.btn-icon:not(.dropdown-static)').first();

    if (!(await kosz.isVisible().catch(() => false))) {
      console.warn(`Nie znalazlem kosza dla "${tekst}" — dane demo zostaja do recznego usuniecia`);
      continue;
    }

    await kosz.click();
    await page.waitForTimeout(900);
    await page.locator('.modal.show button.btn-danger').first().click();
    await page.waitForLoadState('networkidle');
  }
}

execFileSync('sh', ['-c', `for f in "${RAW}"/*.png; do sips -Z 1440 -s format jpeg -s formatOptions 78 "$f" --out "${IMG}/$(basename "$f" .png).jpg" >/dev/null; done`]);

const strona = await context.newPage();
await strona.goto(`file://${join(HERE, 'instrukcja.html')}`, { waitUntil: 'networkidle' });
await strona.evaluate(() => document.fonts.ready);
await strona.waitForTimeout(1500);
await strona.pdf({
  path: PDF,
  format: 'A4',
  printBackground: true,
  margin: { top: '18mm', right: '16mm', bottom: '20mm', left: '16mm' },
  displayHeaderFooter: true,
  headerTemplate: '<div></div>',
  footerTemplate:
    '<div style="width:100%; font-family: Helvetica, Arial, sans-serif; font-size:7.5pt; color:#6f6259;' +
    ' padding:0 16mm; display:flex; justify-content:space-between;">' +
    '<span>OMA — instrukcja obsługi panelu</span><span class="pageNumber"></span></div>',
});

console.log(`Gotowe: ${PDF}`);
await browser.close();
