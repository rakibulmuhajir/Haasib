#!/usr/bin/env python3
"""
CLI Probe: Exercise /commands endpoint and report timings and outcomes.

Usage:
  BASE_URL=http://127.0.0.1:8000 LOGIN_EMAIL=admin@example.com LOGIN_PASSWORD=secret \
  python tools/cli_probe.py

Notes:
  - Requires a superadmin user to authenticate.
  - Creates temporary users/companies and cleans them up.
  - Prints a concise report (action, ok, status, ms, message/errors).
"""
from __future__ import annotations
import os, sys, time, uuid, json, typing as t
import requests

BASE_URL = os.environ.get("BASE_URL", "http://127.0.0.1:8000")
EMAIL = os.environ.get("LOGIN_EMAIL")
PASSWORD = os.environ.get("LOGIN_PASSWORD")

if not EMAIL or not PASSWORD:
    print("Set LOGIN_EMAIL and LOGIN_PASSWORD env vars", file=sys.stderr)
    sys.exit(2)

S = requests.Session()

def url(p: str) -> str:
    return BASE_URL.rstrip('/') + p

def csrf_bootstrap() -> None:
    # Sanctum CSRF cookie
    S.get(url('/sanctum/csrf-cookie'))

def xsrf_header() -> dict[str,str]:
    token = S.cookies.get('XSRF-TOKEN')
    return {'X-XSRF-TOKEN': token} if token else {}

def login(email: str, password: str) -> None:
    csrf_bootstrap()
    headers = xsrf_header()
    # Breeze/Jetstream style login
    res = S.post(url('/login'), data={'email': email, 'password': password}, headers=headers, allow_redirects=False)
    if res.status_code not in (204, 302):
        raise SystemExit(f"Login failed: {res.status_code} {res.text[:200]}")

def post_command(action: str, params: dict) -> tuple[dict, int, float]:
    headers = {'X-Action': action, 'X-Idempotency-Key': str(uuid.uuid4()), **xsrf_header()}
    t0 = time.perf_counter()
    r = S.post(url('/commands'), json=params, headers=headers)
    dt = (time.perf_counter() - t0) * 1000.0
    try:
        body = r.json()
    except Exception:
        body = {'raw': r.text}
    return body, r.status_code, dt

def main() -> None:
    login(EMAIL, PASSWORD)

    report: list[dict] = []
    uid = uuid.uuid4().hex[:6]
    user_email = f"probe+{uid}@example.com"
    company_name = f"ProbeCo-{uid}"

    scenarios = [
        ("user.create", {"name": "Probe User", "email": user_email, "password": "secret123"}),
        ("company.create", {"name": company_name}),
        # Negative: assign missing user -> expect 422
        ("company.assign", {"email": f"missing+{uid}@example.com", "company": company_name, "role": "admin"}),
        # Cleanup
        ("company.delete", {"company": company_name}),
        ("user.delete", {"email": user_email}),
    ]

    for action, params in scenarios:
        body, status, ms = post_command(action, params)
        ok = 200 <= status < 300 and (body.get('ok', True) is not False)
        msg = body.get('message') or body.get('error') or 'ok' if ok else 'failed'
        details = []
        errs = body.get('errors') or {}
        if isinstance(errs, dict):
            for k, v in errs.items():
                first = v[0] if isinstance(v, list) and v else v
                details.append(f"{k}: {first}")

        report.append({
            'action': action,
            'ok': ok,
            'status': status,
            'ms': round(ms, 1),
            'message': msg,
            'details': details,
        })

    print("\nCLI Probe Report:")
    for row in report:
        status = f"[{row['status']}]".ljust(6)
        mark = '✔' if row['ok'] else '✖'
        print(f" {mark} {row['action']:<16} {status} {str(row['ms']).rjust(6)} ms  {row['message']}")
        for d in row['details']:
            print(f"    - {d}")

    # Exit non-zero if any scenario failed
    if any(not r['ok'] for r in report):
        sys.exit(1)

if __name__ == '__main__':
    main()

