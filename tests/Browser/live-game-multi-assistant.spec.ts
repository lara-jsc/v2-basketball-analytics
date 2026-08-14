import { expect, test } from '@playwright/test';

test('main coach can split players across multiple assistants on create', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel(/user id/i).fill('lakers@email.com');
    await page.locator('#password').fill('password123');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/dashboard|live-games/);

    await page.goto('/live-games/create');
    await page.locator('#opponent-team').selectOption({ label: 'Golden State Warriors' });

    await page.getByRole('button', { name: /assign assistants/i }).click();

    await page.getByLabel(/owner for D'Angelo Russell/i).selectOption('Lakers Coach 2');
    await page.getByLabel(/owner for Jaxson Hayes/i).selectOption('Lakers Coach 3');

    await expect(page.getByText(/Lakers Coach 2 · 1 player/i)).toBeVisible();
    await expect(page.getByText(/Lakers Coach 3 · 1 player/i)).toBeVisible();
});
