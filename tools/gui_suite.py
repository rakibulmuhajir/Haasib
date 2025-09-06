#!/usr/bin/env python3
"""
GUI Test Suite — Browser checks for command palette flows (Playwright, Python).

What it does
- Logs in as a user (Breeze/Jetstream-style form)
- Opens the command palette from the dock "Open" button
- Runs a few freeform commands and validates visible UI state
- Measures timings and emits a concise console report

Usage
  BASE_URL=http://127.0.0.1:8000 \
  LOGIN_EMAIL=admin@example.com \
  LOGIN_PASSWORD=secret \
  HEADLESS=1 \
  python tools/gui_suite.py

Dependencies
- pip install playwright
- python -m playwright install chromium

Notes
- Selectors are conservative and rely on visible labels/text added in the palette.
- This suite does not clean up created entities; pair with tools/cli_suite.py for cleanup.
"""
from __future__ import annotations
import os, sys, time, uuid, json
from contextlib import contextmanager
from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

BASE_URL = os.environ.get('BASE_URL', 'http://127.0.0.1:8000')
LOGIN_EMAIL = os.environ.get('LOGIN_EMAIL')
LOGIN_PASSWORD = os.environ.get('LOGIN_PASSWORD')
HEADLESS = os.environ.get('HEADLESS', '1') not in ('0','false','False')

if not LOGIN_EMAIL or not LOGIN_PASSWORD:
    print('Set LOGIN_EMAIL and LOGIN_PASSWORD env vars', file=sys.stderr)
    sys.exit(2)

def U(p: str) -> str:
    return BASE_URL.rstrip('/') + p

@contextmanager
def timer():
    t0 = time.perf_counter()
    yield lambda: (time.perf_counter() - t0) * 1000.0

def main() -> None:
    report = []
    uid = uuid.uuid4().hex[:6]
    test_company = f"GuiCo-{uid}"

    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=HEADLESS)
        ctx = browser.new_context()
        page = ctx.new_page()

        # Login
        with timer() as t:
            page.goto(U('/login'))
            page.get_by_label('Email').fill(LOGIN_EMAIL)
            page.get_by_label('Password').fill(LOGIN_PASSWORD)
            page.get_by_role('button', name='Log in', exact=False).click()
            # Dashboard should render
            page.wait_for_url(U('/dashboard'))
        report.append({'step': 'login', 'ms': round(t(),1), 'ok': True})

        # Open palette via dock Open button (more robust than keyboard globally)
        with timer() as t:
            page.wait_for_selector('button[title^="Open command palette"]', timeout=5000)
            page.click('button[title^="Open command palette"]')
            page.wait_for_selector('text=Available entities', timeout=5000)
        report.append({'step': 'open_palette', 'ms': round(t(),1), 'ok': True})

        # Run help
        with timer() as t:
            input_sel = 'div[role="dialog"] input[type="text"], div[role="dialog"] input[type="password"]'
            page.fill(input_sel, 'help')
            page.keyboard.press('Enter')
            # Execution log should appear
            page.wait_for_selector('text=EXECUTION LOG', timeout=5000)
        report.append({'step': 'help', 'ms': round(t(),1), 'ok': True})

        # Create a company via freeform and Enter
        with timer() as t:
            # Ensure palette input is focused
            page.fill('div[role="dialog"] input[type="text"]', f'company create {test_company}')
            page.keyboard.press('Enter')
            # Expect a success entry with action company.create
            page.wait_for_selector('text=company.create', timeout=7000)
        report.append({'step': 'company_create', 'ms': round(t(),1), 'ok': True})

        # Surface concise report
        total = len(report)
        passed = sum(1 for r in report if r['ok'])
        print(f"\nGUI Suite: {passed}/{total} passed")
        for r in report:
            mark = '✔' if r['ok'] else '✖'
            print(f" {mark} {r['step']:<18} {str(r['ms']).rjust(6)} ms")

        browser.close()

    if any(not r['ok'] for r in report):
        sys.exit(1)

if __name__ == '__main__':
    main()

