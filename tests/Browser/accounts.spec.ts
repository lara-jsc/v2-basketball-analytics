import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page, email: string, password = 'password123'): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/dashboard|live-games/);
}

test('admin creates an assistant coach who can sign in without admin access', async ({ page }) => {
    const email = `assistant-${Date.now()}@example.com`;

    await signIn(page, 'test@email.com');
    await page.getByRole('link', { name: 'Accounts' }).click();
    await expect(page).toHaveURL(/\/accounts$/);

    await page.getByRole('link', { name: /new account/i }).click();
    await page.getByLabel('Full name').fill('Browser Assistant');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Team', { exact: true }).selectOption({ label: 'Los Angeles Lakers' });
    await page.getByRole('radio', { name: 'Assistant coach', exact: true }).check();
    await page.getByLabel('Password', { exact: true }).fill('Browser-pass-123');
    await page.getByLabel('Confirm password').fill('Browser-pass-123');
    await page.getByRole('button', { name: /create account/i }).click();

    await expect(page).toHaveURL(/\/accounts$/);
    await expect(page.getByRole('status')).toContainText('Browser Assistant');

    await page.getByRole('button', { name: /log ?out/i }).first().click();
    await expect(page).toHaveURL(/login|\/$/);
    await signIn(page, email, 'Browser-pass-123');

    await expect(page.getByRole('link', { name: 'Accounts' })).toHaveCount(0);
    const response = await page.goto('/accounts');
    expect(response?.status()).toBe(403);
});
