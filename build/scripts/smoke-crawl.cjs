#!/usr/bin/env node
/*
 * Smoke crawl: log in, then open every company-scoped GET page (no route parameters
 * other than {company}) for each company slug given, and report pages that return
 * an HTTP error, a server exception page, or a JavaScript error in the browser.
 *
 * It catches what vue-tsc cannot: module pages are outside tsconfig, so a missing
 * import only shows up when the page runs.
 *
 *   node scripts/smoke-crawl.cjs <slug> [<slug> ...]
 *
 * Env: BASE (default http://localhost:9001), LOGIN_USER / LOGIN_PASS (default: the
 * demo seed user from database/seeders/Demo/DemoSupport.php), ONLY (regex on the path
 * after the slug), CHROME (path to a Chromium executable when Playwright's own build
 * is not installed), OUT (screenshot folder, default storage/app/smoke-crawl).
 *
 * Read-only by design: paths that change state on GET (onboarding, switch, lock, ...)
 * are skipped. Run against the dev server, never production.
 */
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://localhost:9001';
const OUT = process.env.OUT || path.join(__dirname, '..', 'storage', 'app', 'smoke-crawl');
const only = process.env.ONLY ? new RegExp(process.env.ONLY) : null;
const slugs = process.argv.slice(2);
const SKIP = /onboarding|switch|logout|impersonat|seed|reset|purge|sync|recalc|repair|install|backfill|lock|unlock|export|download|pdf|print|csv/i;

if (!slugs.length) {
  console.error('usage: node scripts/smoke-crawl.cjs <company-slug> [...]');
  process.exit(2);
}

function companyPaths() {
  const routes = JSON.parse(execSync('php artisan route:list --json --method=GET', { cwd: path.join(__dirname, '..'), maxBuffer: 64 * 1024 * 1024 }).toString());
  const paths = new Set();
  for (const r of routes) {
    if (!r.uri.startsWith('{company}')) continue;
    const rest = r.uri.slice('{company}'.length);
    if (rest.includes('{') || SKIP.test(rest)) continue;
    if (!only || only.test(rest)) paths.add(rest);
  }
  return [...paths];
}

(async () => {
  const paths = companyPaths();
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROME ? { executablePath: process.env.CHROME } : {}) });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  let jsErrors = [];
  page.on('pageerror', (e) => jsErrors.push('pageerror: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error') jsErrors.push('console: ' + m.text()); });

  await page.goto(BASE + '/login');
  await page.fill('#email', process.env.LOGIN_USER || 'demo@haasib.app');
  await page.fill('#password', process.env.LOGIN_PASS || 'demo-password');
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);

  let bad = 0;
  let total = 0;
  for (const slug of slugs) {
    for (const p of paths) {
      total++;
      jsErrors = [];
      let status = 0;
      let serverError = '';
      try {
        const resp = await page.goto(`${BASE}/${slug}${p}`, { waitUntil: 'networkidle', timeout: 45000 });
        status = resp ? resp.status() : 0;
        if (status >= 500 || /Exception|Error\b/.test(await page.title())) {
          serverError = (await page.evaluate(() => document.body.innerText.slice(0, 300))).replace(/\s+/g, ' ');
        }
      } catch (e) {
        serverError = 'navigation: ' + e.message.split('\n')[0];
      }
      if (status >= 400 || serverError || jsErrors.length) {
        bad++;
        try { await page.screenshot({ path: path.join(OUT, `${slug}${p.replace(/[\/?=&]/g, '_')}.png`) }); } catch {}
        console.log(`BAD ${slug}${p} -> ${status} ${serverError.slice(0, 160)} ${[...new Set(jsErrors)].slice(0, 3).join(' | ').slice(0, 300)}`);
      }
    }
  }
  console.log(`${total} pages, ${bad} with problems (screenshots in ${OUT})`);
  await browser.close();
  process.exit(bad ? 1 : 0);
})();
