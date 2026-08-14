import { expect, test } from '@playwright/test';

test('opponent coach can split lineup players across multiple assistants before submit', async ({ browser, page }) => {
    await page.goto('/login');
    await page.getByLabel(/user id/i).fill('lakers@email.com');
    await page.locator('#password').fill('password123');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/dashboard|live-games/);

    await page.goto('/live-games/create');
    await page.locator('#opponent-team').selectOption({ label: 'Golden State Warriors' });
    await page.getByRole('button', { name: /create game/i }).click();
    await expect(page).toHaveURL(/\/live-games\/\d+$/);

    const liveGameUrl = page.url();
    const opponentPage = await browser.newPage();

    await opponentPage.goto('/login');
    await opponentPage.getByLabel(/user id/i).fill('warriors@email.com');
    await opponentPage.locator('#password').fill('password123');
    await opponentPage.getByRole('button', { name: /sign in/i }).click();
    await expect(opponentPage).toHaveURL(/dashboard|live-games/);

    await opponentPage.goto(liveGameUrl);
    await opponentPage.getByRole('button', { name: /submit lineup/i }).click();
    await opponentPage.getByRole('button', { name: /assign assistants/i }).click();

    await opponentPage.getByLabel(/owner for Jonathan Kuminga/i).selectOption('Warriors Coach 2');
    await opponentPage.getByLabel(/owner for Kevon Looney/i).selectOption('Warriors Coach 3');

    await expect(opponentPage.getByText(/Warriors Coach 2 · 1 player/i)).toBeVisible();
    await expect(opponentPage.getByText(/Warriors Coach 3 · 1 player/i)).toBeVisible();

    await opponentPage.getByRole('button', { name: /^done$/i }).click();
    await opponentPage.getByRole('button', { name: /submit lineup/i }).click();
    await opponentPage.getByRole('button', { name: /confirm with assistant assignments/i }).click();

    await expect(opponentPage.getByText(/Both starting fives are ready/i)).toBeVisible();

    await opponentPage.close();
});
