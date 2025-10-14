import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';

const loginAsSuperAdmin = async (page: Page) => {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'superadmin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
};

const selectLocale = async (page: Page, localeLabel: string) => {
  await page.click('[data-testid="locale-switcher"]');
  await page.waitForSelector('.p-dropdown-item');
  await page.click(`.p-dropdown-item:has-text("${localeLabel}")`);
};

test.describe('Locale Switching', () => {
  test('updates Profile and Settings pages when switching locales', async ({ page }) => {
    await loginAsSuperAdmin(page);

    await page.goto('/profile');
    const profileHeader = page.locator('h1.page-header-h1');
    await expect(profileHeader).toHaveText('Profile');

    await selectLocale(page, 'Français');
    await expect(profileHeader).toHaveText('Profil');
    await expect(page.locator('.page-header-sub')).toContainText('Gérez vos informations personnelles');

    await page.goto('/settings');
    const settingsHeader = page.locator('h1.page-header-h1');
    await expect(settingsHeader).toHaveText('Paramètres');
    await expect(page.locator('.p-tabmenu-nav li:first-child .p-menuitem-text')).toHaveText('Général');

    await selectLocale(page, 'English (US)');
    await expect(settingsHeader).toHaveText('Settings');
    await expect(page.locator('.p-tabmenu-nav li:first-child .p-menuitem-text')).toHaveText('General');
  });
});
