import { test as setup } from '@playwright/test';

setup('authenticate as superadmin', async ({ page }) => {
  // Navigate to login page
  await page.goto('/login');
  
  // Login with superadmin credentials
  await page.fill('[name="email"]', 'superadmin@example.com');
  await page.fill('[name="password"]', 'password');
  await page.click('[type="submit"]');
  
  // Wait for redirect to dashboard
  await page.waitForURL('/dashboard');
  
  // Save authenticated state
  await page.context().storageState({ path: 'superadmin-auth.json' });
});

setup('authenticate as regular user', async ({ page }) => {
  // Navigate to login page
  await page.goto('/login');
  
  // Login with regular user credentials
  await page.fill('[name="email"]', 'user@example.com');
  await page.fill('[name="password"]', 'password');
  await page.click('[type="submit"]');
  
  // Wait for redirect to dashboard
  await page.waitForURL('/dashboard');
  
  // Save authenticated state
  await page.context().storageState({ path: 'user-auth.json' });
});