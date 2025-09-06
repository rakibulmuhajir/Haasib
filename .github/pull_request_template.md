## PR Checklist (MVP Phase)

- [ ] MVP scope: keeps changes minimal and focused on delivery
- [ ] Reka UI: uses Reka UI primitives (Dialog/Toast/etc.) where UI is added/changed
- [ ] CLI parity: any guided palette change has a freeform equivalent (parser + synonyms)
- [ ] Validation: uses toasts for feedback (no duplicate inline validation UIs)
- [ ] Commands: mutating requests include `X-Idempotency-Key`; errors are explicit and structured
- [ ] Docs: updated if needed (see `docs/TEAM_MEMORY.md`, `docs/cli.md`)
- [ ] Tests (as appropriate): CLI/GUI probes updated (`tools/cli_suite.py`, `tools/gui_suite.py`)

Context/Notes:
- Link related issues and briefly state scope boundaries.
- Call out any intentional deferrals to keep MVP moving.

