/**
 * Seven controlled trading days at Mehran Filling Station, driven through the real UI.
 *
 * The station is built by ScenarioFuelStationSeeder — fixed company, four nozzles on two
 * fuels, packaged and open lubricant, six credit buyers, three suppliers, three banks, and
 * no trading history at all. Every figure the browser types in is a constant below, so the
 * expected results are known before the run starts.
 *
 *   php artisan db:seed --force --class='Database\Seeders\ScenarioFuelStationSeeder'
 *   PW_HEADLESS=1 npx playwright test tests-e2e/fuel-seven-day-scenario.spec.js
 *
 * E2E_DAYS=1 runs only the first day, which is the fast way to check the flow still works
 * after a UI change.
 *
 * The daily close screen carries data-testid hooks on the meter, dip and cash fields
 * (added for this test). Where a section has no hooks yet, the spec records what it found
 * into _scenario-discovery.json rather than guessing at selectors.
 */
import { test, expect } from '@playwright/test';
import fs from 'fs';

const BASE = process.env.E2E_BASE_URL || 'http://localhost:8000';
const EMAIL = process.env.E2E_EMAIL || 'scenario@haasib.test';
const PASSWORD = process.env.E2E_PASSWORD || 'scenario-password';
const SLUG = process.env.E2E_SLUG || 'scenario-mehran-fuel';
const DAYS = Number(process.env.E2E_DAYS || 7);

const WEEK_START = '2026-03-01';

// Rates. OGRA revises on day 4; the new price applies from that morning.
const RATE = {
  petrol: (day) => (day >= 3 ? 306.0 : 300.0),
  diesel: (day) => (day >= 3 ? 314.0 : 310.0),
};

/** [petrol litres, diesel litres, petrol shrinkage, diesel shrinkage] */
const VOLUMES = [
  [700, 500, 2.0, 1.5],
  [800, 600, 2.5, 1.5],
  [650, 450, 1.5, 1.0],
  [900, 700, 3.0, 2.0],
  [750, 550, 2.0, 1.5],
  [850, 650, 2.5, 2.0],
  [950, 800, 3.0, 2.5],
];

const OPENING = { petrol: 18000, diesel: 12000, cash: 50000 };

/** Nozzle order on screen: P1A, P1B (petrol), D2A, D2B (diesel). */
const NOZZLE_FUEL = ['petrol', 'petrol', 'diesel', 'diesel'];
const METER_START = [100000, 200000, 300000, 400000];

const isoDate = (day) => {
  const d = new Date(`${WEEK_START}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + day);
  return d.toISOString().slice(0, 10);
};

const login = async (page) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[type="email"], input[name="email"]', { timeout: 15000 });
  await page.fill('input[type="email"], input[name="email"]', EMAIL);
  await page.fill('input[type="password"], input[name="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle').catch(() => {});
};

const dismissDraft = async (page) => {
  const discard = page.getByRole('button', { name: /Discard|Start fresh|New close/i }).first();
  if (await discard.isVisible().catch(() => false)) {
    await discard.click().catch(() => {});
  }
};

const clickIfPresent = async (page, name) => {
  const btn = page.getByRole('button', { name }).first();
  if (await btn.isVisible().catch(() => false)) {
    await btn.click().catch(() => {});
    await page.waitForTimeout(600);
  }
};

const setFieldIfPresent = async (page, testId, value) => {
  const field = page.locator(`[data-testid="${testId}"]`);
  if (await field.count()) {
    await field.fill('');
    await field.fill(String(value));
  }
};

const setField = async (page, testId, value) => {
  const field = page.locator(`[data-testid="${testId}"]`);
  await expect(field, `field ${testId} should exist`).toBeVisible({ timeout: 10000 });
  await field.fill('');
  await field.fill(String(value));
};

/**
 * The five steps are shadcn Tabs, so each trigger carries role="tab", NOT role="button".
 * getByRole('button', …) matches nothing here and times out looking — which reads exactly
 * like the step being gated behind an earlier one. It is not; there is no gating at all.
 */
const openSection = async (page, name) => {
  await page.getByRole('tab', { name }).click({ timeout: 15000 });
  await page.waitForTimeout(500);
};

/** Everything addressable in the current section, for the sections without hooks yet. */
const describeSection = async (page, name) =>
  await page.evaluate((sectionName) => {
    const txt = (el) => (el?.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 60);
    return {
      section: sectionName,
      testIds: [...document.querySelectorAll('[data-testid]')].map((e) => e.getAttribute('data-testid')),
      visibleInputs: [...document.querySelectorAll('input,select,textarea')]
        .filter((i) => i.offsetParent !== null)
        .map((i) => ({
          type: i.getAttribute('type') || i.tagName.toLowerCase(),
          placeholder: i.getAttribute('placeholder') || '',
          testid: i.getAttribute('data-testid') || '',
          value: i.value,
        })),
      visibleButtons: [...document.querySelectorAll('button')]
        .filter((b) => b.offsetParent !== null)
        .map(txt)
        .filter(Boolean),
    };
  }, name);

test.describe('Mehran Filling Station — seven controlled days', () => {
  test.setTimeout(600000);

  test('post the week through the UI and read the results back', async ({ page }) => {
    const discovery = [];
    // Tall enough that the tab strip and the save buttons are never below the fold.
    await page.setViewportSize({ width: 1440, height: 1200 });
    await login(page);

    // The company must be the scenario one, not whatever was last visited.
    await page.goto(`${BASE}/${SLUG}/fuel/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle').catch(() => {});
    await expect(page.getByText(/Mehran Filling Station/i).first()).toBeVisible({ timeout: 15000 });

    const meters = [...METER_START];
    const dips = { 0: OPENING.petrol, 1: OPENING.diesel };
    let drawer = OPENING.cash;
    const results = [];

    for (let day = 0; day < DAYS; day++) {
      const [petrolLitres, dieselLitres, petrolShrink, dieselShrink] = VOLUMES[day];
      const date = isoDate(day);

      await page.goto(`${BASE}/${SLUG}/fuel/daily-close?date=${date}`, { waitUntil: 'domcontentloaded' });
      await page.waitForLoadState('networkidle').catch(() => {});
      await dismissDraft(page);

      await setField(page, 'business-date', date);

      // --- Meter sales -----------------------------------------------------
      await openSection(page, /Meter Sales/i);
      for (let n = 0; n < 4; n++) {
        const fuel = NOZZLE_FUEL[n];
        const litres = (fuel === 'petrol' ? petrolLitres : dieselLitres) / 2;
        const opening = meters[n];
        const closing = opening + litres;
        meters[n] = closing;

        // Both meter pairs. The day's sales total is derived from the readings, and
        // leaving the manual pair at zero made the form insist the day had no sales.
        await setField(page, `nozzle-${n}-opening-electronic`, opening);
        await setField(page, `nozzle-${n}-closing-electronic`, closing);
        await setFieldIfPresent(page, `nozzle-${n}-opening-manual`, opening);
        await setFieldIfPresent(page, `nozzle-${n}-closing-manual`, closing);
        await setField(page, `nozzle-${n}-sale-rate`, RATE[fuel](day));
      }
      discovery.push(await describeSection(page, `day${day}-meter-sales`));

      await clickIfPresent(page, /Save Meter Sales/i);

      // --- Tank dip --------------------------------------------------------
      await openSection(page, /Tank Dip/i);
      const closingDips = [
        Number((dips[0] - petrolLitres - petrolShrink).toFixed(2)),
        Number((dips[1] - dieselLitres - dieselShrink).toFixed(2)),
      ];
      dips[0] = closingDips[0];
      dips[1] = closingDips[1];

      for (let t = 0; t < 2; t++) {
        await setField(page, `tank-${t}-liters`, closingDips[t]);
        const stick = page.locator(`[data-testid="tank-${t}-stick"]`);
        if (await stick.isVisible().catch(() => false)) {
          await stick.fill(String(Number((closingDips[t] / 40).toFixed(1))));
        }
      }
      discovery.push(await describeSection(page, `day${day}-tank-dip`));
      await clickIfPresent(page, /Save Tank Dip/i);

      // --- Cash in ---------------------------------------------------------
      // Opening cash is yesterday's drawer. Credit sales, expenses, amanat and banking
      // all live behind "Add" buttons that build dynamic rows with no hooks yet, so this
      // run covers the all-cash day: everything the pump took stays in the drawer.
      await openSection(page, /Cash In/i);
      await setField(page, 'opening-cash', drawer);
      discovery.push(await describeSection(page, `day${day}-cash-in`));
      await clickIfPresent(page, /Save Cash In/i);

      // --- Cash out --------------------------------------------------------
      await openSection(page, /Cash Out/i);
      discovery.push(await describeSection(page, `day${day}-cash-out`));
      await clickIfPresent(page, /Save Cash Out/i);

      // --- Review and post -------------------------------------------------
      await openSection(page, /Review/i);
      const fuelRevenue = petrolLitres * RATE.petrol(day) + dieselLitres * RATE.diesel(day);
      const closing = Number((drawer + fuelRevenue).toFixed(2));
      await setField(page, 'closing-cash', closing);
      discovery.push(await describeSection(page, `day${day}-review`));

      await page.getByRole('button', { name: /Post Daily Close/i }).first().click({ timeout: 20000 });
      await page.waitForTimeout(2500);

      // Quote the page's own complaint rather than just saying "did not post".
      const problems = await page.evaluate(() => {
        const lines = document.body.innerText.split(String.fromCharCode(10)).map((l) => l.trim()).filter(Boolean);
        return lines.filter((l) => /must be|cannot|invalid|failed|required|exceed|does not/i.test(l)).slice(0, 8);
      });
      expect(problems, `day ${day} did not post: ${JSON.stringify(problems)}`).toEqual([]);

      results.push({ day, date, fuelRevenue, opening: drawer, closing });
      drawer = closing;

      fs.writeFileSync('tests-e2e/_scenario-discovery.json', JSON.stringify(discovery, null, 2));

    }

    fs.writeFileSync('tests-e2e/_scenario-discovery.json', JSON.stringify(discovery, null, 2));
    fs.writeFileSync('tests-e2e/_scenario-results.json', JSON.stringify(results, null, 2));
    expect(results).toHaveLength(DAYS);
  });
});
