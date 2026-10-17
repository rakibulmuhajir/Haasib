"""Find raw DB::table / join / FROM reads of soft-deleting tables with no deleted_at nearby.

A reopened daily close soft-deletes what it created, so every re-save can leave trashed
copies. Eloquent hides them; a raw DB::table() read does not and will count them.

    python -I scripts/softdelete-audit.py [build-dir]      # default: current directory

Review each hit: a write by id, or a name looked up for an id, is fine. A read that sums,
counts, lists or guards ("used in X, cannot delete") needs whereNull('<alias>.deleted_at')
(in the join's ON for a LEFT JOIN). Hits left after the 2026-10 sweep are all of the fine kind.
"""
import os, re, sys

root = sys.argv[1] if len(sys.argv) > 1 else '.'
tables = set()
for base in ('app', 'modules'):
    for dp, _, fs in os.walk(os.path.join(root, base)):
        if 'Models' not in dp:
            continue
        for f in fs:
            if not f.endswith('.php'):
                continue
            s = open(os.path.join(dp, f), encoding='utf-8', errors='ignore').read()
            if 'SoftDeletes' not in s:
                continue
            m = re.search(r"protected \$table\s*=\s*'([a-z_.]+)'", s)
            if m:
                tables.add(m.group(1))

print(f'{len(tables)} soft-deleting tables', file=sys.stderr)
hits = []
for base in ('app', 'modules'):
    for dp, _, fs in os.walk(os.path.join(root, base)):
        if 'Migrations' in dp or os.sep + 'tests' in dp:
            continue
        for f in fs:
            if not f.endswith('.php'):
                continue
            p = os.path.join(dp, f)
            lines = open(p, encoding='utf-8', errors='ignore').read().split('\n')
            for i, line in enumerate(lines):
                for t in tables:
                    pat = re.escape(t)
                    if re.search(r"(DB::table\(\s*['\"]" + pat + r"(\s+as\s+\w+)?['\"]|join\(\s*['\"]" + pat + r"(\s+as\s+\w+)?['\"]|\b(from|join)\s+" + pat + r"\b)", line, re.I):
                        window = '\n'.join(lines[max(0, i - 3): i + 15])
                        if 'deleted_at' not in window:
                            hits.append((t, os.path.relpath(p, root).replace(os.sep, '/'), i + 1, line.strip()[:110]))
for h in sorted(hits):
    print(f'{h[0]:<32} {h[1]}:{h[2]}  {h[3]}')
print(f'{len(hits)} suspicious reads', file=sys.stderr)
