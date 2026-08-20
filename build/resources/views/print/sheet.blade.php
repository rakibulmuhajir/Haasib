{{--
    The print sheet, for documents rendered server-side by dompdf.

    dompdf resolves no CSS custom properties, so the ledger tokens arrive here
    as literal colour. They are the same values `resources/js/lib/printSheet.ts`
    emits for the browser print path, and the comment on each rule names the
    token it stands for -- so the two paths for one document can be checked
    against each other rather than drifting apart, which is how this template
    came to be printing a blue company name and grey bands while the same
    voucher printed on paper in ink and olive.

    Typeface is the one thing that does not carry over: dompdf ships DejaVu and
    the ledger faces are woff2, which it cannot embed. The type roles are held
    by weight, size and letter-spacing here instead of by family.
--}}
@page { margin: 24px 26px; }

/* --text-primary */
body { color: #19212e; font-family: DejaVu Sans, sans-serif; font-size: 8px; line-height: 1.25; margin: 0; }
table { border-collapse: collapse; width: 100%; }

.masthead { margin-bottom: 5px; table-layout: fixed; }
.masthead td { border: 0; padding: 0 5px; text-align: center; vertical-align: top; width: 33.333%; }
.masthead td:first-child { padding-left: 0; text-align: left; }
.masthead td:last-child { padding-right: 0; text-align: right; }
.party-logo { display: block; max-height: 42px; max-width: 125px; margin-bottom: 3px; }
.center-logo { margin-left: auto; margin-right: auto; }
.right-logo { margin-left: auto; }
.party-name { font-size: 10px; font-weight: bold; }

/* The ledger's one accent red, on the issuing company and nowhere else. */
.main-name { color: #b8332a; font-size: 15px; font-weight: bold; }

/* --text-secondary */
.secondary { color: #5b6572; font-size: 7px; }
.label { color: #5b6572; display: block; font-size: 6px; letter-spacing: 0.08em; text-transform: uppercase; }

/* --rule-w-strong over --rule-w-base, the double rule a title sits between. */
.document-title { border-bottom: 2.5px solid #19212e; border-top: 1.5px solid #19212e; font-size: 15px; font-weight: bold; letter-spacing: .4px; margin: 4px 0; padding: 3px; text-align: center; text-transform: uppercase; }
.identity { margin-bottom: 4px; }
.identity td { padding: 3px 5px; }
.focus { font-size: 10px; font-weight: bold; }

/* --surface-band: the olive marker, not the grey it used to be. */
.section { background: #e6efdb; border: 1px solid #19212e; font-size: 9px; font-weight: bold; margin-top: 5px; padding: 2px 4px; text-align: center; text-transform: uppercase; }

/* --rule-default hairlines; the header rule is the heavier one, and the
   header itself is set in the metadata voice rather than in bold. */
.grid th, .grid td { border: 1px solid #d5dad0; padding: 3px 4px; text-align: left; vertical-align: top; }
.grid th { background: none; border-bottom: 1.5px solid #19212e; color: #5b6572; font-size: 7px; font-weight: normal; letter-spacing: 0.08em; text-transform: uppercase; }
.primary { font-weight: bold; }
.footer-note { border-top: 1px solid #19212e; margin-top: 6px; padding-top: 4px; }

.watermark { font-size: 58px; font-weight: bold; left: 165px; opacity: .12; position: fixed; top: 330px; transform: rotate(-35deg); }

/* A cancelled voucher is adverse and stays red. A draft is not an achievement,
   so it loses the green it had and reads as what it is: not finished yet. */
.cancelled { color: #b8332a; }
.draft { color: #5b6572; }
