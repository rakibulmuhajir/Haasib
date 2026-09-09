# eTravel CRM tutorial research

Source: [Travel CRM One Session](https://www.youtube.com/watch?v=oQzJRXHPWek), uploaded by Arooba Fatima on 2023-01-30. Runtime: 1:08:10.

## Executive conclusion

The tutorial confirms that a useful Umrah system is not just an accounting package with passport rows. Its working spine is:

1. Configure agents, suppliers, hotels, routes, rates, and packages.
2. Create or import a booking with passengers.
3. Turn the booking into a complete service voucher.
4. Run daily arrival, hotel, transport, and departure work from that voucher.
5. Let those business actions feed receivables, payables, receipts, ledgers, and day-book reporting.

Haasib is already stronger in posting integrity, multi-currency controls, payment allocation, amendments, tenant isolation, and safe passenger separation. The largest remaining product gaps are earlier in the workflow: reusable Umrah packages, effective-dated agent/customer rate rules, a truly fast booking screen, full booking import, and structured fulfillment details such as hotel confirmation/BRN and visa/MOFA references.

The competitor should be studied for domain coverage and workflow questions, not copied screen-for-screen. Its interface is setup-heavy, exposes accounting concepts to operational users, duplicates many reports, and permits risky patterns such as direct custom cost overrides and blind import options.

## Recommended next move

Operations is now the right daily control surface in Haasib. The next implementation slice should be **Packages + Rate Books + Quick Booking**, because that reduces repeated entry and supplies reliable itinerary/rate data to vouchers and Operations.

Do not start that implementation until the proposed contracts and simplified user flow in [implementation-roadmap.md](implementation-roadmap.md) are accepted.

## Research pack

- [Canonical prioritized Umrah feature backlog](../../umrah-prioritized-feature-backlog.md)
- [Source and method](source-and-method.md)
- [Timestamped feature inventory](timestamped-feature-inventory.md)
- [Screen inventory](screen-inventory.md)
- [Haasib gap analysis](haasib-gap-analysis.md)
- [Implementation roadmap](implementation-roadmap.md)
