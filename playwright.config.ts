import { defineConfig, devices } from '@playwright/test';

const appUrl = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const databasePath = 'database/playwright.sqlite';
const laravelEnv = [
    'APP_ENV=testing',
    `APP_URL=${appUrl}`,
    'BCRYPT_ROUNDS=4',
    'BROADCAST_CONNECTION=log',
    'CACHE_STORE=array',
    'DB_CONNECTION=sqlite',
    `DB_DATABASE=${databasePath}`,
    'MAIL_MAILER=array',
    'QUEUE_CONNECTION=sync',
    'SESSION_DRIVER=database',
].join(' ');

export default defineConfig({
    testDir: './tests/Browser',
    timeout: 30_000,
    expect: {
        timeout: 10_000,
    },
    fullyParallel: false,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: appUrl,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: [
        {
            command: `touch ${databasePath} && ${laravelEnv} php artisan migrate:fresh --seed --force && ${laravelEnv} php artisan serve --host=127.0.0.1 --port=8000`,
            url: appUrl,
            reuseExistingServer: !process.env.CI,
            timeout: 120_000,
        },
        {
            command: 'NODE_OPTIONS= npm run dev -- --host 127.0.0.1 --port 5173',
            url: 'http://127.0.0.1:5173/@vite/client',
            reuseExistingServer: !process.env.CI,
            timeout: 120_000,
        },
    ],
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
