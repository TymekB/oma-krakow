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

const produkt = await page.evaluate(async () => {
  const odpowiedz = await fetch('/admin/products/', { headers: { Accept: 'text/html' } });
  const html = await odpowiedz.text();
  return html.match(/\/admin\/products\/(\d+)\/edit/)?.[1] ?? null;
});

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
