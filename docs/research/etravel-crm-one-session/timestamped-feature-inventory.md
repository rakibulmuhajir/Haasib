# Timestamped feature inventory

## 00:00-09:30 — customers, agents, employees, roles, and branches

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 00:00 | Separate setup/admin and working/customer sides | Setup records are maintained in an older administration UI; operational users sign into a simpler CRM/customer portal. | High |
| 00:28 | Customer/party creation | A customer can be a direct/walk-in customer, sales agent, business partner, or other configured type; it receives a branch, category, contact details, receivable account, and login. | High |
| 02:44 | Category and customer-specific pricing | A general price can apply to a customer type/category; a particular customer can receive a different rate. The developer describes a precedence rule in which a specific agent rate overrides the category rate. | Medium |
| 03:55 | Branch assignment | Customers and staff are tied to a branch. Cross-branch visibility is separately controlled. | High |
| 04:14 | Customer financial/login controls | The record includes receivable account, credit limit, a staff-account visibility restriction, commission/SPO fields, active/blocked login, and change-password-on-first-login. | High |
| 05:03 | Customer portal credentials | A customer/agent login is created and can reset its password from the customer record. A default password is discussed; this is not a pattern to copy. | Medium |
| 07:17 | Employee security controls | Employee records carry department, branch, position, currency, and specific rights such as final booking, final hotel voucher/modification, final ticket/refund/service voucher, payment/receipt/JV approval, backdated voucher update, and branch/financial visibility. | High |
| 08:16 | Role/module access | A booking officer can be restricted to booking while accounts are hidden. A permissions tree controls which menus/processes the login can use. | High |

## 09:30-23:30 — accounting masters, suppliers, and commercial rates

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 09:39 | Bank setup | Banks are contact/master records linked to accounts and branches. | High |
| 11:12 | Chart of accounts | A hierarchical chart uses account codes, master/control/detail levels, account type, and account nature. Receivable accounts created for parties appear in this chart. | High |
| 14:15 | Transporter accounting | A transporter has separate sale, cost, and payable accounts. Transport types/routes and rates are configured below the master record. | High |
| 16:09 | Service providers | Saudi/other service providers are parties with sale, cost, payable accounts and branch association. | High |
| 17:03 | Visa-company setup | A visa company/IATA-like supplier record has login/contact details, financial accounts, and sale/cost rate tabs. | High |
| 18:26 | Visa sale-rate matrix | Sale rates are scoped by customer type and optionally a specific customer, with effective and cease dates. Values include adult, child, infant, transport by age, ground services, portal charges, and VAT. | High |
| 19:34 | Rate precedence | The system first checks a customer-specific rate, then the wider agent/category rate. | Medium |
| 20:46 | Cost and exchange-rate setup | Visa cost uses an effective/cease date and category. SAR/PKR conversion is maintained separately; the booking can later carry a custom exchange rate with permission. | High |
| 23:10 | Rate locking expectation | The developer says exchange-rate edits should be restricted after setup. This is described, not shown as a full audit/lock mechanism. | Medium |

## 23:30-31:00 — hotels, rooms, and packages

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 23:35 | Hotel master and supplier accounting | Hotel records store city, distance, category/type/star, English and Arabic details, contact data, supplier/service provider, and separate sale/payable/purchase/expense/KSA accounts. | High |
| 24:22 | Direct hotel versus intermediary | A hotel can be paid directly or supplied through a Saudi service provider, which is linked to the hotel for payable reporting. | Medium |
| 25:39 | Hotel sale rates | Effective-dated rates are scoped by customer type/customer and include sharing, double, triple, quad, quint, and six-bed prices. The hotel can use full-room charges, voucher-only, customer restrictions, or zero-rate behavior. | High |
| 26:48 | Hotel cost and rooms | Separate cost and room tabs exist. Room inventory records bed/room/floor/beds, active dates, customer permission, supplier, and sharing data. | High |
| 27:06 | Room import | The developer explains that rooms can be added manually or imported from an Excel template supplied by the vendor. | Medium |
| 28:08 | Local hotel sale | A locally sold hotel room can be recorded without a full package, while using hotel room/inventory data when applicable. | Medium |
| 29:00 | Package setup | Hotels are linked to a named package so selecting the package later preselects itinerary/hotel options. The package list is shown, but the complete package editor is not exhaustively demonstrated. | Medium |

## 31:00-40:50 — quick booking, full booking, and vouchers

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 31:08 | Role-specific Umrah menu | The agent portal exposes Booking List, New Booking, New Quick Booking, Import Booking, Update IATA, Update Visa, Voucher List/New Voucher, accommodation/transport vouchers, voucher export, intimation, complaints, passport delivery, and passenger transfer. | High |
| 31:26 | Quick versus full booking | Quick Booking is for rapid manual entry. A fuller New Booking screen exposes more operational and commercial controls. | High |
| 32:38 | Quick-booking fields | The user selects customer, package, room type, up to three hotels and nights, expected departure, and passenger rows. Package/hotel choices prefill from setup. | High |
| 34:47 | Passport documents | Passport-copy attachment to a booking is explained. The upload storage/capacity issue is discussed, but a completed attachment workflow is not shown. | Medium |
| 35:44 | Group/manual booking number | A booking can carry an external/manual booking or group reference. It starts as draft and is later finalized. | Medium |
| 35:57 | Finalization | Booking status moves from draft to final. Finalization permission is separately controlled on the employee. | High |
| 36:54 | Booking-to-voucher flow | The voucher is opened from the booking and inherits its passengers. PNR and visa number can be entered per passenger. | High |
| 37:15 | Accommodation voucher | Repeating hotel rows capture hotel, note, BRN, room type, quantity, check-in, nights, checkout, confirmation number, view type, meal plan, and an excluded-night flag. | High |
| 38:26 | Flight itinerary | Departure and arrival flight, sector, dates, ETD and ETA are captured for the journey. | High |
| 39:10 | Transport and ziyarat | Repeating transport rows select provider, route, transport type, and BRN. Makkah/Madinah ziyarat dates and times are additional voucher information. | High |
| 40:29 | Voucher price/cost derivation | Hotel, visa, transport, ticket, and service values roll into sale and cost sections; custom sale/cost approvals and custom exchange rates are available with controls. | High |

## 40:50-49:30 — operational and accounting reports

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 40:58 | Searchable report catalogue | Reports are selected from a searchable list rather than only fixed navigation links. Each report has a task-specific filter panel. | High |
| 41:48 | Arrival/check-in/checkout reporting | Separate reports answer Saudi return/departure, Makkah or Madinah check-in, checkout, booking/flight/PAX views, and arrival summaries. | High |
| 41:59 | Arrival Intimation | Filters include branch, package, hotel, visa company, customer/agent, airport, status, and date range. | High |
| 42:42 | Flight and passenger grouping | Reports can group by flight or booking and show passenger totals. Excel output is explicitly available alongside on-screen reporting. | High |
| 43:09 | Movement reports | Departure and package movement reports are listed. The recording proves report availability more clearly than it proves a dispatch execution workflow. | Medium |
| 44:13 | Booking commercial view | A booking has group/PAX information plus sale details, custom-sale approval, exchange-rate override, package invoice option, voucher totals, payment, balance, cost detail, notes/locks, and flight rows. | High |
| 46:21 | Account ledger | Ledger filters include branch, account, module, report currency (SAR/PKR), department, category, opening-balance inclusion, sort order, and date range. It can render or export to Excel. | High |
| 47:20 | Hotel ledger/rate trace | Hotel charges can be inspected in the ledger and traced back to hotel rates. | Medium |

## 49:30-56:50 — bulk import and passenger transfer

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 49:38 | Booking import | A standard CSV/Excel-style template is imported to create a booking. The form selects package, customer, IATA, approval date, Makkah/Madinah hotels, and expected departure. | High |
| 50:28 | Standard template | Users download/use the vendor's standard column and date format. The call spends significant time correcting Windows/Excel date formatting, showing how fragile the import is. | High |
| 53:56 | Import actions | Import supports custom adult/child/infant sale and cost, send-SMS, allow-duplicate, `Create Booking + Update Mofa`, or `Update Mofa only`. | High |
| 54:14 | Passenger transfer | Selected passengers can move to another existing booking, split into a new booking, or create a new booking under another customer. | High |

## 56:50-68:10 — financial opening, vouchers, services, and dashboard

| Time | Demonstrated behavior | How it works | Confidence |
|---|---|---|---|
| 57:09 | Financial start date | The company record defines financial-period start/end and whether duplicate passport numbers are allowed. Opening balances are dated immediately before operational entry begins. | High |
| 58:55 | Opening balances | An opening entry selects account, currency, debit/credit, exchange rate, and branch. | High |
| 60:34 | Quick accounting vouchers | The Accounts menu provides bank payment/receipt, cash payment/receipt, journal voucher, service list, day book, and opening list. Quick voucher uses voucher type, branch, bank/cash account, date, description, booking/party, and amount. | High |
| 62:29 | Print and day book | Saved receipts can be printed. Day Book separates receipts and payments and shows cash in bank and cash in hand for branch/date, with Excel and PDF output. | High |
| 63:52 | Supplier payment | The developer explains paying a service provider/vendor and having the payable/ledger update automatically. | Medium |
| 65:14 | Service invoices | A generic service list can record extra services such as a non-Umrah visa, with supplier, cost, price, currency, and commission. | Medium |
| 66:34 | Dashboard | The full dashboard presents opening/invoices/payments/adjustments/balance, accommodation/transport-only totals, voucher status, booking/passenger counts, ticket/service invoice summary, pending items, and planned KSA/Makkah/Madinah population. | High |
| 66:53 | Operational drill-down | The developer describes using date-driven dashboard counts to see who is in KSA, Makkah, or Madinah and inspect booking/ticket information. | Medium |

## Menu-only capabilities requiring separate validation

These were visible but not fully exercised: scanner booking, booking log, passport delivery workflow, complaints, advance actions, reservation list, hotel/transport BRN lists, promotional offers, booking rule engine, agent packages, bus seats, hotel reservation/purchase/cancellation, checkout/check-in mismatch checks, and ticket-group inventory. They are useful prompts for future interviews, not confirmed implementation requirements for Haasib.
