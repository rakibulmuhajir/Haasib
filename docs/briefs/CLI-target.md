If you want a CLI companion people actually use instead of just demoing to investors, it needs to feel faster, smarter, and safer than forms. Here’s the short hit list that separates “toy” from “I live in this thing.”

* **Blazing summon & focus**: conflict-free hotkeys (Ctrl+/, Cmd+/, double-Shift), ignores text inputs, mouse button fallback.
* **Tiny grammar, big brains**: `<verb> [subject] [amount] [date] [flags]` plus fuzzy synonyms and natural-ish parsing; don’t make users memorize flags.
* **Context awareness**: auto-scope to current company/record; suggest likely customers/invoices; prefill from page context.
* **Inline prompts**: when info is missing, show compact, keyboardable fields in the dock instead of dumping validation errors.
* **Preview before commit**: totals, taxes, GL impact, side-by-side; Enter to post, Esc to bail.
* **Instant feedback**: optimistic UI, human messages (“INV-103 created”), links to open the record, no raw stack traces ever.
* **Idempotency + undo**: every command has a key; repeats don’t double-post; quick rollback for fat-fingered entries.
* **History, favorites, templates**: ↑/↓ to cycle, pin frequent commands, save macros like “month-end accrual.”
* **Personalization**: user-configurable hotkey, recent entities first, locale-aware dates/currency, remember last choices.
* **Safety rails**: strict RBAC, open-period locks, anomaly warnings on weird amounts, dry-run `--draft` everywhere.
* **Discoverability**: `help` shows real examples; inline hints while typing; completion for verbs, entities, and flags.
* **A11y done right**: focus trap, screen-reader announcements, visible focus states, respect reduced motion.
* **Seamless GUI bridge**: every CLI result links to the full page; complex flows can “eject” to a form mid-command without losing state.
* **Observability**: audit log (raw + parsed), latency metrics from keypress to commit, command usage analytics to prune and polish.
* **Zero surprise performance**: keystroke→suggestions < 60 ms, Enter→posted < 300 ms p50; preloaded catalogs, workerized fuzzy search.

Ship that set and users will stick, because it’s genuinely faster than clicking through twelve fields and praying the session didn’t expire. Everything else is cosplay.
