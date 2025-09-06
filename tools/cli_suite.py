#!/usr/bin/env python3
"""
CLI Test Suite — API-level functional checks for the command palette backend.

What it does
- Authenticates as a superadmin via session (CSRF + /login)
- Exercises /commands with realistic scenarios (create, assign, unassign, delete)
- Verifies side-effects via web lookups (/web/companies, /web/users, ...)
- Measures latency per step and emits a JSON + Markdown report

Usage
  BASE_URL=http://127.0.0.1:8000 \
  LOGIN_EMAIL=admin@example.com \
  LOGIN_PASSWORD=secret \
  python tools/cli_suite.py

Dependencies
- requests (pip install requests)
- (optional, for future GUI checks) beautifulsoup4

Outputs
- tools/reports/cli_suite_<timestamp>.json
- tools/reports/cli_suite_<timestamp>.md
"""
from __future__ import annotations
import os, sys, time, json, uuid, pathlib, typing as t
import requests

BASE_URL = os.environ.get('BASE_URL', 'http://127.0.0.1:8000')
LOGIN_EMAIL = os.environ.get('LOGIN_EMAIL')
LOGIN_PASSWORD = os.environ.get('LOGIN_PASSWORD')

if not LOGIN_EMAIL or not LOGIN_PASSWORD:
    print('Set LOGIN_EMAIL and LOGIN_PASSWORD env vars', file=sys.stderr)
    sys.exit(2)

S = requests.Session()

def U(p: str) -> str:
    return BASE_URL.rstrip('/') + p

def csrf_bootstrap() -> None:
    S.get(U('/sanctum/csrf-cookie'))

def xsrf() -> dict[str, str]:
    token = S.cookies.get('XSRF-TOKEN')
    return {'X-XSRF-TOKEN': token} if token else {}

def login(email: str, password: str) -> None:
    csrf_bootstrap()
    res = S.post(U('/login'), data={'email': email, 'password': password}, headers=xsrf(), allow_redirects=False)
    if res.status_code not in (204, 302):
        raise SystemExit(f'Login failed: {res.status_code} {res.text[:200]}')

def post_command(action: str, params: dict, idem_key: str | None = None) -> tuple[dict, int, float]:
    headers = {'X-Action': action, 'X-Idempotency-Key': idem_key or str(uuid.uuid4()), **xsrf()}
    t0 = time.perf_counter()
    r = S.post(U('/commands'), json=params, headers=headers)
    dt = (time.perf_counter() - t0) * 1000.0
    try:
        body = r.json()
    except Exception:
        body = {'raw': r.text}
    return body, r.status_code, dt

def get_json(path: str, params: dict | None = None) -> dict:
    r = S.get(U(path), params=params)
    r.raise_for_status()
    return r.json()

def ensure_reports_dir() -> pathlib.Path:
    d = pathlib.Path('tools/reports')
    d.mkdir(parents=True, exist_ok=True)
    return d

def main() -> None:
    login(LOGIN_EMAIL, LOGIN_PASSWORD)

    uid = uuid.uuid4().hex[:6]
    user_email = f"suite+{uid}@example.com"
    company_name = f"SuiteCo-{uid}"

    results: list[dict] = []

    def record(action: str, params: dict, status: int, ms: float, body: dict):
        ok = 200 <= status < 300 and (body.get('ok', True) is not False)
        entry = {
            'action': action,
            'ok': ok,
            'status': status,
            'ms': round(ms, 1),
            'message': body.get('message') or body.get('error') or ('ok' if ok else 'failed'),
            'errors': body.get('errors') or {},
        }
        results.append(entry)

    # 1) user.create with password
    body, status, ms = post_command('user.create', {'name': 'Suite User', 'email': user_email, 'password': 'secret123'})
    record('user.create', {'email': user_email}, status, ms, body)

    # 2) company.create
    body, status, ms = post_command('company.create', {'name': company_name})
    record('company.create', {'name': company_name}, status, ms, body)

    # Resolve company id via suggest
    co = get_json('/web/companies', params={'q': company_name, 'limit': 1}).get('data', [])
    company_id = co[0]['id'] if co else None

    # 3) company.assign (should succeed)
    body, status, ms = post_command('company.assign', {'email': user_email, 'company': company_id or company_name, 'role': 'admin'})
    record('company.assign', {'email': user_email, 'company': company_id or company_name}, status, ms, body)

    # Verify membership via lookups
    if company_id:
        users = get_json(f'/web/companies/{company_id}/users', params={'q': user_email, 'limit': 1}).get('data', [])
        results.append({'action': 'verify.membership', 'ok': any(u.get('email') == user_email for u in users), 'status': 200, 'ms': 0.0, 'message': 'membership verified'})

    # 4) idempotency replay should 409
    idem = str(uuid.uuid4())
    _b1, s1, ms1 = post_command('company.create', {'name': company_name + '-dup'}, idem)
    _b2, s2, ms2 = post_command('company.create', {'name': company_name + '-dup'}, idem)
    results.append({'action': 'idempotency.1st', 'ok': 200 <= s1 < 300, 'status': s1, 'ms': round(ms1, 1), 'message': 'first ok'})
    results.append({'action': 'idempotency.replay', 'ok': s2 == 409, 'status': s2, 'ms': round(ms2, 1), 'message': 'replay 409'})

    # 5) negative assign non-existent user -> 422 with explicit error
    body, status, ms = post_command('company.assign', {'email': f'missing+{uid}@example.com', 'company': company_id or company_name, 'role': 'admin'})
    record('company.assign.missing_user', {}, status, ms, body)

    # Cleanup
    post_command('company.unassign', {'email': user_email, 'company': company_id or company_name})
    post_command('company.delete', {'company': company_id or company_name})
    post_command('user.delete', {'email': user_email})

    # Summaries
    ok_count = sum(1 for r in results if r['ok'])
    total = len(results)
    p50 = None
    try:
        lat = sorted([r['ms'] for r in results if r['ms']])
        p50 = lat[len(lat)//2] if lat else 0.0
    except Exception:
        p50 = 0.0

    summary = {
        'base_url': BASE_URL,
        'total': total,
        'passed': ok_count,
        'failed': total - ok_count,
        'p50_ms': p50,
        'timestamp': int(time.time()),
        'results': results,
    }

    reports_dir = ensure_reports_dir()
    stamp = time.strftime('%Y%m%d_%H%M%S')
    (reports_dir / f'cli_suite_{stamp}.json').write_text(json.dumps(summary, indent=2))

    md = [f"# CLI Suite Report ({stamp})\n",
          f"Base: {BASE_URL}\n\n",
          f"Summary: {ok_count}/{total} passed, p50: {p50} ms\n\n",
          "| Action | OK | Status | ms | Message |\n",
          "|---|:--:|:--:|--:|---|\n",
    ]
    for r in results:
        md.append(f"| {r['action']} | {'✅' if r['ok'] else '❌'} | {r['status']} | {r['ms']} | {r['message']} |")
    (reports_dir / f'cli_suite_{stamp}.md').write_text("\n".join(md) + "\n")

    # Print concise console report
    print(f"\nCLI Suite: {ok_count}/{total} passed; p50 {p50} ms")
    for r in results:
        mark = '✔' if r['ok'] else '✖'
        print(f" {mark} {r['action']:<26} [{r['status']}] {str(r['ms']).rjust(6)} ms  {r['message']}")

    if any(not r['ok'] for r in results):
        sys.exit(1)

if __name__ == '__main__':
    main()

