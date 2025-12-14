# Invoice & Bill Creation UX Plan

**Status**: APPROVED - Ready for Implementation
**Date**: 2025-12-12
**Approved**: 2025-12-12
**Goal**: Design frictionless invoice/bill creation that captures minimum viable data upfront, allowing completion later

---

## 1. The Problem

Current accounting software makes invoice creation feel like filling out a tax form:
- 15+ fields on one screen
- Required fields that block you ("Enter tax code!")
- Accountant jargon everywhere
- No clear path from "quick draft" to "ready to send"

**Result**: SMB owners avoid the software, use spreadsheets, chase payments manually.

---

## 2. The Philosophy

### "3-Click Invoice" Principle
> A business owner should be able to create a valid invoice in **3 interactions**:
> 1. Who is it for?
> 2. What did you sell?
> 3. Send (or Save)

Everything else is **optional enhancement**, not required blocker.

### Progressive Completion Model
```
┌─────────────────────────────────────────────────────────────┐
│  QUICK DRAFT          COMPLETE              READY TO SEND   │
│  ───────────────────────────────────────────────────────────│
│  Customer ✓           + Line details        + Review        │
│  Amount ✓             + Tax codes           + PDF preview   │
│  Description ✓        + Payment terms       + Email/share   │
│                       + Reference #                         │
│                       + Notes                               │
│                                                             │
│  [Just enough to      [Fill when you        [Send when      │
│   remember the sale]   have time]            ready]         │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Invoice Creation Flow

### Stage 1: Quick Capture (Minimum Viable Invoice)

**Required to save draft:**
| Field | Why Required | Smart Default |
|-------|--------------|---------------|
| Customer | Who pays | Last used / search |
| Amount | How much | None (must enter) |
| Description | What for | None (must enter) |

**Auto-filled:**
| Field | Default | Can Override |
|-------|---------|--------------|
| Invoice Date | Today | Yes |
| Due Date | Customer terms or Net 30 | Yes |
| Currency | Customer's currency | Yes |
| Invoice # | Next in sequence | Yes (before approve) |

**Hidden until needed:**
- Line item breakdown (single line assumed)
- Tax codes (use customer's default)
- Revenue account (use default)
- Notes, reference, attachments

### Owner Mode: Quick Invoice UI

```
┌─────────────────────────────────────────────────────────────┐
│  New Invoice                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Who is this for?                                           │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🔍 Search customers...                              │   │
│  └─────────────────────────────────────────────────────┘   │
│  Recent: [Acme Corp] [Tech Solutions] [+ New Customer]     │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  What did you sell?                                         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Web design services                                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  How much?                                                  │
│  ┌──────────────────┐                                      │
│  │ PKR  50,000      │  ☐ Add tax                           │
│  └──────────────────┘                                      │
│                                                             │
│  Due: [In 30 days ▼]                    Dec 11, 2025       │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  [+ Add more details]                                       │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │  Save Draft    │  │  Send Invoice  │                    │
│  └────────────────┘  └────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

**Key UX decisions:**
1. **No visible line item grid** - Single amount field by default
2. **"Add tax" checkbox** - Not a dropdown with 50 tax codes
3. **Due date as relative** - "In 30 days" not "2025-01-10"
4. **Recent customers** - One click for repeat business
5. **"Add more details"** - Progressive disclosure trigger

---

### Stage 2: Complete Details (When Time Permits)

Clicking "+ Add more details" or editing a draft reveals:

```
┌─────────────────────────────────────────────────────────────┐
│  Invoice #INV-1025                              [Draft ▼]   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Customer: Acme Corp                    [Change]            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Contact: john@acme.com                             │   │
│  │  Address: 123 Business St...            [Edit ▸]    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  LINE ITEMS                                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Description          │ Qty │  Rate   │    Amount   │   │
│  ├──────────────────────┼─────┼─────────┼─────────────┤   │
│  │ Web design services  │  1  │ 50,000  │    50,000   │   │
│  │ [+ Add line]                                        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  DATES & TERMS                                              │
│  Invoice Date: [Dec 11, 2025 📅]                           │
│  Due Date:     [Jan 10, 2025 📅]   Terms: [Net 30 ▼]       │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  ADDITIONAL INFO                        ○ Collapsed         │
│  Reference #, Notes, Attachments...     [Expand ▸]          │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│                              Subtotal:      PKR 50,000      │
│                              Tax (17%):     PKR  8,500      │
│                              ─────────────────────────      │
│                              Total:         PKR 58,500      │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────┐  │
│  │  Save Draft    │  │  Preview PDF   │  │ Send Invoice │  │
│  └────────────────┘  └────────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

### Stage 3: Ready to Send

Before sending, show preview and final checks:

```
┌─────────────────────────────────────────────────────────────┐
│  Ready to Send?                                    [ × ]    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │              [ PDF PREVIEW ]                        │   │
│  │                                                     │   │
│  │  Your Company Name                                  │   │
│  │  ────────────────                                   │   │
│  │  INVOICE #INV-1025                                  │   │
│  │                                                     │   │
│  │  Bill To:            Amount Due:                    │   │
│  │  Acme Corp           PKR 58,500                     │   │
│  │  ...                 Due: Jan 10, 2025              │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Send to: john@acme.com                      [Change]       │
│                                                             │
│  ☐ Send me a copy                                          │
│  ☐ Include payment link (online payment)                   │
│                                                             │
│  ┌────────────────┐  ┌────────────────────────────────┐    │
│  │  ← Edit        │  │  Send Invoice →                │    │
│  └────────────────┘  └────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

---

## 4. Accountant Mode: Full Form

When `isAccountantMode`, show the complete form immediately with all fields visible:

```
┌─────────────────────────────────────────────────────────────┐
│  New Invoice                               [Save] [Post]    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  HEADER                                                     │
│  ──────────────────────────────────────────────────────────│
│  Customer:      [Acme Corp                         ▼]       │
│  Invoice #:     [INV-1025        ]  (auto)                 │
│  Invoice Date:  [2025-12-11      📅]                       │
│  Due Date:      [2025-01-10      📅]                       │
│  Terms:         [Net 30                           ▼]       │
│  Currency:      [PKR                              ▼]       │
│  Reference:     [PO-12345                          ]       │
│                                                             │
│  LINE ITEMS                                                 │
│  ──────────────────────────────────────────────────────────│
│  │ Item   │ Description      │ Qty │ Rate   │ Acct  │ Tax │ Amount  │
│  │ ▼      │ Web design       │  1  │ 50000  │ 4-100 │ 17% │ 50,000  │
│  │ +      │                  │     │        │       │     │         │
│                                                             │
│  TOTALS                                                     │
│  ──────────────────────────────────────────────────────────│
│                              Subtotal:      PKR 50,000      │
│                              Discount:      PKR      0      │
│                              Tax (17%):     PKR  8,500      │
│                              ─────────────────────────      │
│                              Total:         PKR 58,500      │
│                                                             │
│  JOURNAL PREVIEW                                            │
│  ──────────────────────────────────────────────────────────│
│  DR  1-1200 Accounts Receivable      PKR 58,500            │
│      CR 4-1000 Revenue               PKR 50,000            │
│      CR 2-1100 Sales Tax Payable     PKR  8,500            │
│                                                             │
│  NOTES                                                      │
│  ──────────────────────────────────────────────────────────│
│  Internal: [                                           ]    │
│  Customer: [                                           ]    │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Bill Creation (Mirror of Invoice)

Same philosophy but for vendor bills:

### Owner Mode: Quick Bill Entry

```
┌─────────────────────────────────────────────────────────────┐
│  Enter a Bill                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Who is it from?                                            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🔍 Search vendors...                                │   │
│  └─────────────────────────────────────────────────────┘   │
│  Recent: [Office Depot] [AWS] [+ New Vendor]               │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  What did you buy?                                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Office supplies                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  How much?                                                  │
│  ┌──────────────────┐                                      │
│  │ PKR  15,000      │  ☐ Includes tax                      │
│  └──────────────────┘                                      │
│                                                             │
│  Category: [Office Expenses ▼]                             │
│                                                             │
│  Bill Date: [Today ▼]       Due: [In 30 days ▼]            │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  [+ Add more details]                                       │
│                                                             │
│  ┌────────────────┐  ┌────────────────┐                    │
│  │  Save Draft    │  │ Save & Pay Now │                    │
│  └────────────────┘  └────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

**Key differences from Invoice:**
1. **Category required** - Where does this expense go?
2. **"Includes tax" checkbox** - Common for receipts
3. **"Save & Pay Now"** - Shortcut to immediate payment

---

## 6. Field Requirements by Stage

### Invoice Fields

| Field | Quick Draft | Complete | Required for Send |
|-------|:-----------:|:--------:|:-----------------:|
| Customer | ✓ Required | ✓ | ✓ |
| Amount/Line Items | ✓ Required | ✓ | ✓ |
| Description | ✓ Required | ✓ | ✓ |
| Invoice Date | Auto (today) | Editable | ✓ |
| Due Date | Auto (terms) | Editable | ✓ |
| Invoice Number | Auto | Editable | ✓ |
| Currency | Auto (customer) | Editable | ✓ |
| Tax | Checkbox → Auto | Full control | — |
| Revenue Account | Auto (default) | Selectable | — |
| Reference | — | Optional | — |
| Notes | — | Optional | — |
| Attachments | — | Optional | — |
| Payment Terms | Auto | Selectable | — |

### Bill Fields

| Field | Quick Draft | Complete | Required for Approve |
|-------|:-----------:|:--------:|:--------------------:|
| Vendor | ✓ Required | ✓ | ✓ |
| Amount/Line Items | ✓ Required | ✓ | ✓ |
| Description | ✓ Required | ✓ | ✓ |
| Category/Account | ✓ Required | ✓ | ✓ |
| Bill Date | Auto (today) | Editable | ✓ |
| Due Date | Auto (terms) | Editable | ✓ |
| Bill Number | Optional | Editable | Recommended |
| Currency | Auto (vendor) | Editable | ✓ |
| Tax | Checkbox → Auto | Full control | — |
| Reference | — | Optional | — |
| Notes | — | Optional | — |
| Attachments | — | Optional | — |

---

## 7. Smart Defaults Engine

### Customer/Vendor Defaults
When selected, auto-fill:
- Currency (from customer/vendor record)
- Payment terms (from customer/vendor record)
- Tax treatment (from customer/vendor tax profile)
- Default revenue/expense account

### Item/Product Defaults
When line item selected, auto-fill:
- Description (from item)
- Rate/Price (from price list)
- Account (from item mapping)
- Tax code (from item)

### Learning Defaults
System learns from patterns:
- "Office Depot usually = Office Supplies"
- "This customer usually pays Net 15"
- "Similar amount to last invoice"

---

## 8. Inline Quick-Add

Never force user to leave the form to add master data:

### Quick Add Customer (Inline)

```
┌─────────────────────────────────────────────────────────────┐
│  Quick Add Customer                                [ × ]    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Name:    [ Acme Corporation                       ]        │
│  Email:   [ billing@acme.com                       ]        │
│                                                             │
│  [ Create & Select ]                                        │
│                                                             │
│  💡 You can add more details later in Customers            │
└─────────────────────────────────────────────────────────────┘
```

Only name required. Email optional. Everything else added later.

### Quick Add Line Item

```
┌─────────────────────────────────────────────────────────────┐
│  + Add Line                                                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  What are you selling?                                      │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🔍 Search products/services...                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Or type a description:                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Custom consulting services                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Qty: [ 1 ]   Rate: [ 25,000 ]                             │
│                                                             │
│  [ Add Line ]                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 9. Mobile: Receipt-to-Bill

### Snap Receipt Flow

```
┌─────────────────────────────────────────────────────────────┐
│  📸 Snap Receipt                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [Camera viewfinder with receipt outline guide]             │
│                                                             │
│                        [ 📷 ]                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘

        ↓ OCR Processing ↓

┌─────────────────────────────────────────────────────────────┐
│  Bill from Receipt                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📄 [Receipt thumbnail]                                     │
│                                                             │
│  Detected:                                                  │
│  Vendor:    [Office Depot              ] ✏️                 │
│  Amount:    [PKR 15,234                ] ✏️                 │
│  Date:      [Dec 10, 2025              ] ✏️                 │
│                                                             │
│  Category:  [Office Supplies           ▼]                   │
│                                                             │
│  [ Save as Draft ]  [ Save & Match to Bank ]               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 10. Validation Strategy

### Soft vs Hard Validation

| Validation Type | Behavior | Example |
|-----------------|----------|---------|
| **Hard Block** | Cannot save at all | No customer, no amount |
| **Soft Warning** | Can save draft, blocks send/approve | Missing tax code |
| **Suggestion** | Shows hint, doesn't block | "Due date is in the past" |

### Owner Mode Validation
- Fewer hard blocks
- More smart defaults
- Friendly warning messages

### Accountant Mode Validation
- Standard accounting validations
- Period checks
- Balance verifications

---

## 11. State Transitions

```
┌─────────┐     ┌──────────┐     ┌──────────┐     ┌────────┐
│  NEW    │────▶│  DRAFT   │────▶│ APPROVED │────▶│  SENT  │
└─────────┘     └──────────┘     └──────────┘     └────────┘
                     │                │                │
                     │                │                │
                     ▼                ▼                ▼
               ┌──────────┐    ┌──────────┐    ┌──────────┐
               │ DELETED  │    │  VOIDED  │    │   PAID   │
               └──────────┘    └──────────┘    └──────────┘
```

### Draft
- No journal entry
- Fully editable
- Can be deleted

### Approved
- Journal posted
- Limited edits (non-financial only)
- Can be voided (open period) or credit noted (closed period)

### Sent (Invoice only)
- Same as Approved + sent_at timestamp
- Triggers customer notification
- Starts due date tracking

### Paid
- Derived state (from payment allocations)
- Cannot void (must void payments first)

---

## 12. Performance Optimizations

### Instant Response
- Optimistic UI updates
- Background saves (auto-save drafts every 30s)
- Skeleton loading for customer/vendor search

### Reduce API Calls
- Prefetch recent customers/vendors
- Cache tax codes and accounts
- Batch line item calculations client-side

### Offline Capability (Mobile)
- Save draft locally
- Queue for sync
- Conflict resolution on reconnect

---

## 13. Confirmed Decisions

### 1. Single Amount vs Line Items Default?
- **DECISION**: Option A for Owner Mode, Option B for Accountant
- Owner sees single amount field, can expand to lines via "+ Add more details"
- Accountant always sees line item grid

### 2. Tax Handling?
- **DECISION**: Option A + C combined
- Checkbox "Add tax" that auto-applies customer's tax profile
- If no profile, uses company default tax code

### 3. "Send" vs "Approve" Flow?
- **DECISION**: Owner Mode = Auto-approve on Send, Accountant Mode = Separate steps
- Owner clicks "Send" → system approves + posts journal + sends email
- Accountant clicks "Approve" (posts journal), then "Send" separately

### 4. Quick Add Customer Data?
- **DECISION**: Name only required
- Email field shown but optional
- Everything else added later in customer edit

### 5. Default Due Date?
- **DECISION**: Cascade fallback chain
- 1st: Customer's payment terms (if set)
- 2nd: Company default terms (if set)
- 3rd: Net 30 (hardcoded fallback)

---

## 14. Implementation Phases

### Phase 1: Quick Draft (MVP)
- [ ] Single-amount invoice creation
- [ ] Customer search + quick add
- [ ] Auto-defaults (date, terms, currency)
- [ ] Save draft functionality
- [ ] Basic validation

### Phase 2: Complete Form
- [ ] Line item grid
- [ ] Tax code selection
- [ ] Account selection (Accountant mode)
- [ ] "Add more details" expansion
- [ ] Attachments

### Phase 3: Send Flow
- [ ] PDF preview
- [ ] Email composition
- [ ] Send tracking
- [ ] Status updates

### Phase 4: Bill Mirror
- [ ] Apply same patterns to Bills
- [ ] Category/Account required
- [ ] Receipt attachment
- [ ] "Pay Now" shortcut

### Phase 5: Mobile
- [ ] Receipt capture
- [ ] OCR integration
- [ ] Quick invoice on mobile
- [ ] Bank feed matching

---

## 15. Success Metrics

| Metric | Target | Why |
|--------|--------|-----|
| Time to first invoice | < 60 seconds | Speed matters for adoption |
| Fields touched (draft) | ≤ 4 | Minimal friction |
| Draft → Sent conversion | > 80% | Drafts should become real invoices |
| Mobile receipt → Bill | < 30 seconds | Capture expenses on the go |
| Error rate on send | < 2% | Smart defaults prevent errors |

---

## Next Steps

1. **Review this plan** - Get alignment on questions in Section 13
2. **Design mockups** - Figma/visual designs for Owner Mode flow
3. **Define lexicon terms** - Add invoice/bill terms to `lexicon.ts`
4. **API contract** - Define minimal vs full payload for create endpoint
5. **Component breakdown** - Identify reusable form components

---

**Questions for Review:**

1. Do we agree on "3-click invoice" as the north star?
2. Should single-amount be the default or always show line items?
3. How aggressive should auto-defaults be?
4. Is "Send = Auto-approve" acceptable for Owner Mode?
5. What's the minimum data for Quick Add Customer?
