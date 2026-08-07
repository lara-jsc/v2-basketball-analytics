import { expect, test } from '@playwright/test';

test('coach can create and open a live game console', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('test@email.com');
    await page.locator('#password').fill('password123');
    await page.getByRole('button', { name: /sign in/i }).click();

    await expect(page).toHaveURL(/dashboard|live-games/);

    await page.goto('/live-games/create');
    await expect(page.getByRole('heading', { name: /set up live game/i })).toBeVisible();

    await page.locator('#home-team').selectOption({ label: 'Los Angeles Lakers' });
    await page.locator('#opponent-team').selectOption({ label: 'Golden State Warriors' });
    await expect(page.getByText('5/5 selected')).toBeVisible();

    await page.getByRole('button', { name: /create game/i }).click();

    await expect(page).toHaveURL(/\/live-games\/\d+$/);
    await expect(page.getByRole('button', { name: /start game/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /active lineup/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /event pad/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /keys to win/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /coach alerts/i })).toBeVisible();
});
