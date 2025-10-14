# CLI Commands: Reporting Dashboard

All commands support natural language input and provide same functionality as GUI.

## Dashboard Commands

```bash
# View dashboard
report dashboard
report dashboard show
report dashboard --company=ACME --date="2025-01"

# View KPIs
report kpi
report kpi show --type=revenue,profit,cash
report kpi trend --metric=revenue --period=3m
```

## Report Generation Commands

```bash
# Income statement
report income-statement
report income-statement --from="2025-01-01" --to="2025-01-31"
report income-statement --compare=previous-month
report income-statement --detailed --currency=USD

# Balance sheet
report balance-sheet
report balance-sheet --as-of="2025-01-31"
report balance-sheet --include-zero-balances

# Cash flow
report cash-flow
report cash-flow --method=indirect
report cash-flow --from="2025-01-01" --to="2025-01-31"

# Trial balance
report trial-balance
report trial-balance --currency=EUR
report trial-balance --date="2025-01-31"

# Aging reports
report aging --type=customer
report aging --type=vendor
report aging --as-of="2025-01-31"
```

## Report Management Commands

```bash
# List reports
report list
report list --type=income-statement
report list --status=completed

# Generate and export
report generate income-statement --export=pdf
report generate balance-sheet --export=excel --output=/tmp/
report generate custom --template="Monthly Summary" --email=true

# Download report
report download <report-id>
report download <report-id> --format=csv
report download <report-id> --output=/path/to/save

# Delete report
report delete <report-id>
report delete --older-than=30d
```

## Template Commands

```bash
# Templates
report template list
report template create "Monthly P&L" --type=income-statement
report template save --name="My Dashboard" --current-layout=true
report template apply "Monthly P&L" --to="2025-01"
report template delete "Monthly P&L"
```

## Schedule Commands

```bash
# Scheduling
report schedule list
report schedule create "Monthly P&L" --frequency=monthly --recipients=finance@company.com
report schedule create "Weekly Cash Flow" --frequency=weekly --every=monday
report schedule enable <schedule-id>
report schedule disable <schedule-id>
report schedule delete <schedule-id>
```

## Natural Language Examples

```bash
# Natural language queries
report "show me profit for last month"
report "generate balance sheet as of December 31"
report "compare revenue this quarter vs last quarter"
report "email me cash flow report every Monday"
report "who owes me money?"
report "what are my expenses this year?"
report "show trial balance in euros"
```

## Output Formats

- **Table**: Default format for lists and tabular data
- **JSON**: For API integration and scripting
- **CSV**: For data export
- **ASCII Charts**: Simple visualizations in terminal
- **Progress bars**: For report generation status

## Examples

```bash
# Quick dashboard view
$ report dashboard
┌──────────────┬──────────────┬──────────┬──────────┐
│ KPI          │ This Month   │ Change   │ Trend    │
├──────────────┼──────────────┼──────────┼──────────┤
│ Revenue      │ $125,000     │ +12%     │ ↗︎       │
│ Expenses     │ $85,000      │ +5%      │ ↗︎       │
│ Profit       │ $40,000      │ +25%     │ ↗︎       │
│ Cash Balance │ $210,000     │ +8%      │ ↗︎       │
└──────────────┴──────────────┴──────────┴──────────┘

# Generate report with options
$ report generate income-statement \
    --from="2025-01-01" \
    --to="2025-01-31" \
    --compare=previous-month \
    --export=pdf \
    --email=team@company.com
Generating income statement... [██████████] 100%
Report saved to: /var/reports/income-statement-2025-01.pdf
Email sent to: team@company.com

# Natural language
$ report "show me customer aging"
┌─────────────┬─────────┬─────────┬─────────┬─────────┐
│ Customer    │ 0-30    │ 31-60   │ 61-90   │ >90     │
├─────────────┼─────────┼─────────┼─────────┼─────────┤
│ Client A    │ $10,000 │ $0      │ $0      │ $0      │
│ Client B    │ $0      │ $5,000  │ $2,000  │ $0      │
│ Client C    │ $0      │ $0      │ $0      │ $15,000 │
└─────────────┴─────────┴─────────┴─────────┴─────────┘
```