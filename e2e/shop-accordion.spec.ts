import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

async function openProductThroughTurbo(page: Page): Promise<void> {
  await page.goto('/sklep/kategorie/aromaterapia', { waitUntil: 'networkidle' });
  await page.locator('a[href*="/sklep/produkty/"]').first().click();
  await expect(page.locator('#add-to-cart-button')).toBeVisible();
}

function reviewsButton(page: Page): Locator {
  return page.locator('.accordion-button').filter({ hasText: /Recenzje/ }).first();
}

const flip = (state: string | null) => (state === 'true' ? 'false' : 'true');

async function toggleAndWaitForTransition(button: Locator, panel: Locator, expected: string): Promise<void> {
  await button.click();
  await expect(button).toHaveAttribute('aria-expanded', expected);
  await expect(panel).not.toHaveClass(/collapsing/);
}

test.describe('Akordeon na stronie produktu', () => {
  test('po nawigacji Turbo przelacza sie w obie strony', async ({ page }) => {
    await openProductThroughTurbo(page);

    const button = reviewsButton(page);
    const panel = page.locator('#reviews');
    await button.scrollIntoViewIfNeeded();

    const initial = await button.getAttribute('aria-expanded');

    await toggleAndWaitForTransition(button, panel, flip(initial));
    await toggleAndWaitForTransition(button, panel, initial ?? 'false');
  });

  test('jedno klikniecie daje jedno zdarzenie collapse', async ({ page }) => {
    await openProductThroughTurbo(page);

    await page.evaluate(() => {
      const counters = { shown: 0, hidden: 0 };
      (window as unknown as { omaCollapseCounters: typeof counters }).omaCollapseCounters = counters;
      document.addEventListener('shown.bs.collapse', () => (counters.shown += 1));
      document.addEventListener('hidden.bs.collapse', () => (counters.hidden += 1));
    });

    const button = reviewsButton(page);
    const panel = page.locator('#reviews');
    await button.scrollIntoViewIfNeeded();

    const initial = await button.getAttribute('aria-expanded');

    await toggleAndWaitForTransition(button, panel, flip(initial));
    await toggleAndWaitForTransition(button, panel, initial ?? 'false');

    const counters = await page.evaluate(
      () => (window as unknown as { omaCollapseCounters: { shown: number; hidden: number } }).omaCollapseCounters,
    );

    expect(counters, 'podwojna inicjalizacja bundla dawalaby wiecej zdarzen').toEqual({ shown: 1, hidden: 1 });
  });
});
