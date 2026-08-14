import { expect, test } from '@playwright/test';

test('coach staffing multi-select persists across save, redirect, and reopen', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('test@email.com');
    await page.locator('#password').fill('password123');
    await page.getByRole('button', { name: /sign in/i }).click();

    await expect(page).toHaveURL(/dashboard|live-games/);

    await page.goto('/teams');
    await page.getByRole('link', { name: /Los Angeles Lakers/i }).click();

    await expect(page).toHaveURL(/\/teams\/\d+$/);

    const manageStaffingButton = page.getByRole('button', { name: /manage staffing/i });
    const assistantTwo = page.getByRole('checkbox', { name: /Lakers Coach 2/i });
    const assistantThree = page.getByRole('checkbox', { name: /Lakers Coach 3/i });
    const assistantSummary = page.getByTestId('assistant-coaches-summary');

    await manageStaffingButton.click();
    await expect(page.getByRole('heading', { name: /manage coach staffing/i })).toBeVisible();

    await assistantTwo.check();
    await assistantThree.check();
    await page.getByRole('button', { name: /save staffing/i }).click();

    await expect(page).toHaveURL(/\/teams\/\d+$/);
    await expect(page.getByRole('heading', { name: /manage coach staffing/i })).not.toBeVisible();
    await expect(assistantSummary).toHaveText('Lakers Coach 2, Lakers Coach 3');

    await manageStaffingButton.click();
    await expect(assistantTwo).toBeChecked();
    await expect(assistantThree).toBeChecked();

    await assistantThree.uncheck();
    await page.getByRole('button', { name: /save staffing/i }).click();

    await expect(assistantSummary).toHaveText('Lakers Coach 2');

    await manageStaffingButton.click();
    await expect(assistantTwo).toBeChecked();
    await expect(assistantThree).not.toBeChecked();
});
