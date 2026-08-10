import { expect, test, type FrameLocator, type Page } from '@playwright/test';
import { loginToAdmin } from './helpers';

const EDITED_KEY = 'hero.eyebrow';
const EDITED_TEXT = 'Kraków · test edycji';

const frame = (page: Page): FrameLocator => page.frameLocator('#site-frame');

async function resetContent(page: Page, original: string): Promise<void> {
  await page.request.put(`/admin/landing-content/${EDITED_KEY}`, { data: { value: original } });
}

test.describe('Edytor strony w panelu', () => {
  test.beforeEach(async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/site/edit');
  });

  test('otwiera podgląd strony z wyłączonym zapisem', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Edycja strony' })).toBeVisible();
    await expect(page.locator('#save')).toBeDisabled();
    await expect(page.locator('#status')).toHaveText('Brak zmian do zapisania.');
    await expect(frame(page).locator('.editable').first()).toBeVisible();
    await expect(frame(page).locator('.editable').first()).toHaveAttribute('contenteditable', 'true');
  });

  test('teksty są edytowalne, a linki nie nawigują', async ({ page }) => {
    const link = frame(page).locator('.nav__link').first();

    await expect(link).toHaveClass(/editable/);
    await expect(link).toHaveAttribute('contenteditable', 'true');

    await link.evaluate((el) => (el as HTMLElement).click());

    await expect(page, 'klik w link nie nawiguje').toHaveURL(/\/admin\/site\/edit$/);
  });

  test('zapis przez API tworzy wersję widoczną w historii', async ({ page }) => {
    const before = await (await page.request.get('/landing-content.json')).json();
    const original = before.values[EDITED_KEY] ?? null;

    const save = await page.request.put('/admin/landing-content', {
      data: { values: { [EDITED_KEY]: EDITED_TEXT } },
    });
    expect(save.ok()).toBe(true);

    const after = await (await page.request.get('/landing-content.json')).json();
    expect(after.values[EDITED_KEY]).toBe(EDITED_TEXT);

    await page.reload();
    await page.locator('#history-toggle').click();
    await expect(page.locator('[data-restore], .site-edit__revision').first()).toBeVisible();
    await expect(page.locator('.site-edit__revision-info').first()).toContainText('admin');

    if (original !== null) {
      await resetContent(page, original);
    }
  });

  test('przywrócenie wersji cofa treść strony', async ({ page }) => {
    const before = await (await page.request.get('/landing-content.json')).json();
    const original = before.values[EDITED_KEY] ?? null;

    await page.request.put('/admin/landing-content', { data: { values: { [EDITED_KEY]: 'WERSJA PIERWSZA' } } });

    const { revisions } = await (await page.request.get('/admin/site/revisions')).json();
    const first = revisions[0];

    await page.request.put('/admin/landing-content', { data: { values: { [EDITED_KEY]: 'WERSJA DRUGA' } } });

    const restore = await page.request.post(`/admin/site/revisions/${first.id}/restore`);
    expect(restore.ok()).toBe(true);

    const restored = await (await page.request.get('/landing-content.json')).json();
    expect(restored.values[EDITED_KEY]).toBe('WERSJA PIERWSZA');

    if (original !== null) {
      await resetContent(page, original);
    }
  });

  test('przywrócenie wersji identycznej z obecną nie tworzy nowego wpisu', async ({ page }) => {
    const before = await (await page.request.get('/landing-content.json')).json();
    const original = before.values[EDITED_KEY] ?? null;

    await page.request.put('/admin/landing-content', { data: { values: { [EDITED_KEY]: 'STAN OBECNY' } } });

    const { revisions } = await (await page.request.get('/admin/site/revisions')).json();
    const newest = revisions[0];

    const restore = await page.request.post(`/admin/site/revisions/${newest.id}/restore`);
    const payload = await restore.json();

    expect(payload.restored).toBe(0);

    const after = await (await page.request.get('/admin/site/revisions')).json();
    expect(after.revisions.length, 'brak nowego wpisu w historii').toBe(revisions.length);

    if (original !== null) {
      await resetContent(page, original);
    }
  });

  test('treść zapisana w panelu pojawia się na publicznej stronie', async ({ page }) => {
    const before = await (await page.request.get('/landing-content.json')).json();
    const original = before.values[EDITED_KEY] ?? null;

    await page.request.put('/admin/landing-content', { data: { values: { [EDITED_KEY]: EDITED_TEXT } } });

    await page.goto('/');
    await expect(page.locator('.eyebrow').first()).toHaveText(EDITED_TEXT);
    await expect(page.locator('.editable')).toHaveCount(0);

    if (original !== null) {
      await resetContent(page, original);
    }
  });
});

test.describe('Sesja admina', () => {
  test('zapamiętaj mnie trzyma sesję 14 dni', async ({ page, context }) => {
    await page.goto('/admin/login');
    await page.locator('input[name="_username"]').fill('admin');
    await page.locator('input[name="_password"]').fill('oma2026!');
    await page.locator('input[name="_remember_me"]').check();
    await page.locator('form').getByRole('button').first().click();
    await expect(page).toHaveURL(/\/admin\/?$/);

    const cookie = (await context.cookies()).find((item) => item.name === 'APP_ADMIN_REMEMBER_ME');

    expect(cookie, 'ciasteczko remember me zostało ustawione').toBeTruthy();

    const days = (cookie!.expires - Date.now() / 1000) / 86_400;
    expect(days).toBeGreaterThan(13);
    expect(days).toBeLessThan(15);
  });
});
