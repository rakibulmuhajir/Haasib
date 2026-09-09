# Competitor screen inventory

## 1. Admin dashboard

Evidence: [00:00 dashboard](screenshots/00-00-dashboard.png), [17:30 populated KSA status](screenshots/17-30-ksa-daily-status-populated.png)

Visible information:

- Ticket total and refunds
- Visa approvals and hotel-only passengers
- Voucher count and voucher passenger count
- Receivable, payable, cash, and bank totals
- Booking counts/amounts
- Client balance summary
- Visa/voucher progress by agent
- Daily KSA status for a selected date

Assessment: the screen mixes finance and operations, but the KSA status panel is highly valuable. Haasib should reverse the emphasis: daily operations first, money second.

## 2. Visa and voucher progress

The dashboard shows, per client/agent:

- Visa passengers
- Hotel-only passengers
- Voucher count
- Voucher passenger count
- Pending passengers without a voucher

Rows or counts drill into detailed passenger/voucher lists. This turns “missing voucher” into a visible work queue.

## 3. Pending voucher approval

Evidence: [06:00 pending vouchers](screenshots/06-00-pending-vouchers.png)

Visible columns/actions:

- Voucher number and external reference
- Client/agent
- Booking date and departure date
- Head passenger
- Package
- Internal owner/office
- Edit, inspect, approve, and invoice actions

The UI distinguishes a partially completed voucher from one ready for approval. The presentation is crude, but the gate is correct.

## 4. Printed/customer voucher

Evidence: [09:00 voucher operations](screenshots/09-00-voucher-operations.png)

Sections observed:

- Company/agent branding and QR code
- Passenger identity and visa information
- Outbound/return flights
- Repeating hotel stays
- Repeating transport sectors
- Repeating ziyarat rows
- Terms and conditions
- Makkah and Madinah contacts

Useful detail: contacts can depend on the providers included in the itinerary rather than being one static footer.

## 5. Package invoice

Evidence: [12:00 package invoice breakdown](screenshots/12-00-package-invoice-breakdown.png)

The same itinerary is represented financially as ticket, visa, hotel, transport, and ziyarat components. It supports a compact invoice or a detailed breakdown and can display foreign and base currencies.

Assessment: Haasib already has stronger accounting controls. The lesson is to preserve the link from itinerary to money, not to copy the report layout.

## 6. Hotel payment/reservation report

Evidence: [15:00 hotel payment report](screenshots/15-00-hotel-payment-report.png)

Fields include client, guest, city, check-in/out, nights, hotel, room type, total rooms/beds, supplier, reference, and payable. Filters include report type, period, city, party, hotel, and supplier.

Operational value:

- See what is booked with each hotel.
- Reconcile confirmation/reference details.
- Calculate room nights and supplier exposure.
- Find unconfirmed or tentative inventory.

## 7. Daily KSA status

Evidence: [17:00 structure](screenshots/17-00-ksa-daily-status.png), [17:30 populated](screenshots/17-30-ksa-daily-status-populated.png)

For a selected date it shows:

- Airport arrivals and departures
- Makkah check-ins and check-outs
- Madinah check-ins and check-outs
- Planned population currently in Makkah
- Planned population currently in Madinah
- Pilgrims whose itinerary has exited Saudi Arabia

This is the strongest concept in the demo. Each count should be clickable and should preserve the chosen date.

## 8. Airport arrival report

Evidence: [18:00 arrival report](screenshots/18-00-arrival-report.png)

Visible columns:

- Voucher/reference
- Client
- Passport
- Head passenger
- Pax
- Sector/origin
- Arrival date
- Flight
- Arrival airport
- Arrival time
- First hotel
- Room type
- Hotel checkout
- Transport type
- Transport provider
- Saudi company (`Shirka`)

The report is formatted for print/PDF and totals passengers.

## 9. Makkah check-in report

Evidence: [19:00 Makkah check-in](screenshots/19-00-makkah-checkin-report.png)

Visible columns:

- Voucher and booking status
- Reference and client
- Guest/head passenger
- Passport and pax
- Coming from
- Destination city
- Check-in and checkout
- Nights
- Hotel and room type/count
- Hotel confirmation number
- Saudi company and status

The report totals passengers, nights, and room quantities. “Coming from” makes it a movement report rather than merely a hotel list.

## 10. Operational report selector

Evidence: [21:00 reports menu](screenshots/21-00-operational-reports-menu.png)

Observed report family:

- Arrival Report
- Arrival Report From Flight Departure
- Departure Report
- Check In Report
- Check Out Report
- Inter City Trip Detail
- Visa Only Report
- Hotel Pax and Night Summary

Assessment: this taxonomy should inspire Haasib's operator-facing language. It should be implemented as a clean report switcher and date presets, not as another large navigation dropdown.
