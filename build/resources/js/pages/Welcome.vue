<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

withDefaults(
    defineProps<{
        canRegister?: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const FULL = 'invoice create Al Noor Traders';

const typed = ref('');
const done = ref(false);
// 0 = hidden, 1 = level 1, 2 = level 2 open, 3 = level 3 open
const stage = ref(0);
const hole = ref<HTMLElement | null>(null);

const level = (n: number) => ({
    opacity: stage.value >= n ? 1 : 0,
    transform: stage.value >= n ? 'none' : 'translateY(-14px)',
    hl: stage.value > n,
});
const l1 = computed(() => level(1));
const l2 = computed(() => level(2));
const l3 = computed(() => level(3));

let startTimer: ReturnType<typeof setTimeout> | undefined;
let typeTimer: ReturnType<typeof setInterval> | undefined;
let doneTimer: ReturnType<typeof setTimeout> | undefined;
let t2: ReturnType<typeof setTimeout> | undefined;
let t3: ReturnType<typeof setTimeout> | undefined;
let observer: IntersectionObserver | undefined;

onMounted(() => {
    const reduce =
        window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) {
        typed.value = FULL;
        done.value = true;
        stage.value = 3;
        return;
    }

    let i = 0;
    startTimer = setTimeout(() => {
        typeTimer = setInterval(() => {
            i += 1;
            typed.value = FULL.slice(0, i);
            if (i >= FULL.length) {
                clearInterval(typeTimer);
                doneTimer = setTimeout(() => {
                    done.value = true;
                }, 380);
            }
        }, 55);
    }, 700);

    if (!hole.value) {
        return;
    }
    if (!('IntersectionObserver' in window)) {
        stage.value = 3;
        return;
    }
    observer = new IntersectionObserver(
        (entries) => {
            if (!entries.some((e) => e.isIntersecting)) {
                return;
            }
            observer?.disconnect();
            stage.value = 1;
            t2 = setTimeout(() => {
                stage.value = 2;
            }, 900);
            t3 = setTimeout(() => {
                stage.value = 3;
            }, 1800);
        },
        { threshold: 0.35 },
    );
    observer.observe(hole.value);
});

onBeforeUnmount(() => {
    clearTimeout(startTimer);
    clearInterval(typeTimer);
    clearTimeout(doneTimer);
    clearTimeout(t2);
    clearTimeout(t3);
    observer?.disconnect();
});
</script>

<template>
    <Head title="Haasib">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="lp">
        <header class="wrap head">
            <a href="#top" class="brand">Haasib</a>
            <nav aria-label="Main" class="nav">
                <a href="#rabbit" class="nav-link">Rabbit hole</a>
                <a href="#commands" class="nav-link">Commands</a>
                <a href="#books" class="nav-link">Features</a>
                <a href="#trades" class="nav-link">Trades</a>
                <template v-if="$page.props.auth.user">
                    <Link :href="dashboard()" class="btn btn-sm">
                        Open Haasib
                    </Link>
                </template>
                <template v-else>
                    <Link :href="login()" class="signin">Sign in</Link>
                    <Link v-if="canRegister" :href="register()" class="btn btn-sm">
                        Create account
                    </Link>
                </template>
            </nav>
        </header>

        <main>
            <section id="top" class="wrap hero">
                <h1 class="h1">Keep the books at the speed you type.</h1>
                <p class="lede">
                    Accounting for petrol pumps, travel agencies and everyday
                    trade in Pakistan. Write what you want done, and follow any
                    number back to where it came from.
                </p>
                <div class="cta-row">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="btn btn-lg"
                    >
                        Open Haasib
                    </Link>
                    <Link
                        v-else-if="canRegister"
                        :href="register()"
                        class="btn btn-lg"
                    >
                        Create account
                    </Link>
                    <a href="#rabbit" class="btn-ghost">See the rabbit hole</a>
                </div>

                <div
                    role="img"
                    aria-label="The command palette: invoice create Al Noor Traders, with matching suggestions"
                    class="palette"
                >
                    <div class="pal-bar">
                        <span class="brass">›</span>
                        <span
                            >{{ typed
                            }}<span class="caret" aria-hidden="true"></span
                        ></span>
                        <span class="kbd">Ctrl K</span>
                    </div>
                    <div class="pal-body">
                        <template v-if="done">
                            <div class="sug drop hot">
                                <span
                                    ><b>Invoice for Al Noor Traders</b><br /><span
                                        class="sub"
                                        >Opens the invoice form with the
                                        customer filled in</span
                                    ></span
                                >
                                <span class="act">Enter</span>
                            </div>
                            <div class="sug drop2">
                                <span
                                    >Al Noor Traders<br /><span class="sub"
                                        >Customer, owes Rs 85,000</span
                                    ></span
                                >
                                <span class="act">Statement</span>
                            </div>
                            <div class="sug drop3">
                                <span
                                    >INV-01002<br /><span class="sub"
                                        >Al Noor Traders, Rs 55,000, sent</span
                                    ></span
                                >
                                <span class="act">Open</span>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <section id="rabbit" class="band">
                <div ref="hole" class="wrap split">
                    <div>
                        <h2 class="h2 mb18">The rabbit hole</h2>
                        <p class="body-lg">
                            Click any number on any report and Haasib opens what
                            it is made of. Click again, and again, until you
                            reach the invoice, payment or journal that put it
                            there.
                        </p>
                        <p class="body-lg last">
                            Every level adds up exactly to the one above it, so
                            a figure you can't explain is a figure you can find.
                        </p>
                    </div>
                    <ol
                        aria-label="This month's profit followed down to one invoice"
                        class="levels"
                    >
                        <li
                            class="lvl card"
                            :style="{
                                opacity: l1.opacity,
                                transform: l1.transform,
                            }"
                        >
                            <div class="card-h card-h-lg">
                                <span>Profit this month</span
                                ><span class="num">Rs 122,000</span>
                            </div>
                            <div class="hl row-hl" :class="{ on: l1.hl }">
                                <span>Service income</span
                                ><span class="num">155,000</span>
                            </div>
                            <div class="row-mute mt4">
                                <span>Office rent</span
                                ><span class="num">−25,000</span>
                            </div>
                            <div class="row-mute mt8">
                                <span>Office supplies</span
                                ><span class="num">−8,000</span>
                            </div>
                        </li>
                        <li
                            class="lvl card indent1"
                            :style="{
                                opacity: l2.opacity,
                                transform: l2.transform,
                            }"
                        >
                            <div class="card-h">
                                <span>Service income</span
                                ><span class="num">155,000</span>
                            </div>
                            <div class="hl row-hl" :class="{ on: l2.hl }">
                                <span>INV-01002, Al Noor Traders</span
                                ><span class="num">55,000</span>
                            </div>
                            <div class="row-mute mt4">
                                <span>INV-01003, Bright Mart</span
                                ><span class="num">100,000</span>
                            </div>
                        </li>
                        <li
                            class="lvl card card-dark indent2"
                            :style="{
                                opacity: l3.opacity,
                                transform: l3.transform,
                            }"
                        >
                            <div class="card-h">
                                <span>INV-01002</span
                                ><span class="num">55,000</span>
                            </div>
                            <div class="sub mt6">
                                Al Noor Traders, issued 16 Oct, two lines
                            </div>
                            <span class="open-inv">Open the invoice</span>
                        </li>
                    </ol>
                </div>
            </section>

            <section id="commands" class="wrap sec">
                <h2 class="h2 mb14">What people type</h2>
                <p class="body-md mb44">
                    Press Ctrl+K anywhere. You only see the commands your role
                    may run.
                </p>
                <div class="ref">
                <dl class="ref-list">
                    <dt>customer create</dt><dd>Add a customer</dd>
                    <dt>invoice create</dt><dd>Open a new invoice</dd>
                    <dt>invoice send</dt><dd>Send an invoice</dd>
                    <dt>payment create</dt><dd>Record money received</dd>
                    <dt>invoice void</dt><dd>Cancel an invoice</dd>
                    <dt>invoice duplicate</dt><dd>Copy an invoice</dd>
                </dl>
                <dl class="ref-list">
                    <dt>customer list</dt><dd>See every customer</dd>
                    <dt>invoice list</dt><dd>See every invoice</dd>
                    <dt>payment list</dt><dd>See money received</dd>
                    <dt>user create</dt><dd>Invite someone to the company</dd>
                    <dt>company switch</dt><dd>Move to another company</dd>
                    <dt>help</dt><dd>Every command you can run</dd>
                </dl>
                </div>
            </section>

            <section id="books" class="band">
                <div class="wrap sec">
                    <h2 class="h2 mb14">Everything the books need</h2>
                    <p class="body-md mb52">
                        A full double-entry ledger underneath, with the screens
                        a business uses every week.
                    </p>
                    <div class="books">
                <div>
                    <h3>Selling</h3>
                    <p>Customers and categories, invoices, consolidated invoices, credit notes, payments with on-account credit, period discounts.</p>
                </div>
                <div>
                    <h3>Buying</h3>
                    <p>Vendors, bills with goods received and vendor claims, bill payments, vendor credits, expenses.</p>
                </div>
                <div>
                    <h3>Banking</h3>
                    <p>Bank and cash accounts with balances from the ledger, bank feeds and rules, reconciliation.</p>
                </div>
                <div>
                    <h3>Reports</h3>
                    <p>Profit and loss, balance sheet, trial balance, receivables and payables aging, statements for customers, vendors, banks and partners.</p>
                </div>
                <div>
                    <h3>Setting up</h3>
                    <p>A chart of accounts for your trade, opening balances, fiscal years and periods, tax rates.</p>
                </div>
                <div>
                    <h3>People and stock</h3>
                    <p>Payroll and salary advances, items, warehouses and stock, partners' capital and profit share.</p>
                </div>
                    </div>
                </div>
            </section>

            <section id="trades" class="wrap sec">
                <h2 class="h2 mb48">Shaped to your trade</h2>
                <div class="trades">
                    <article class="trade-main">
                        <h3>Petrol pumps</h3>
                        <p class="trade-lead">
                            Close each day from the morning dip. Pump readings,
                            tank stock and the cash drawer reconcile in one
                            place, and the day posts itself to the books.
                        </p>
                        <p class="brass trade-tail">
                            Credit slips per vehicle.
                            Amanat held for customers. Fuel rates, investors,
                            shift handovers, stock and profit statements.
                        </p>
                    </article>
                    <div class="trade-side">
                        <article class="trade-card">
                            <h3>Travel and Umrah</h3>
                            <p>
                                Groups, vouchers and visas, hotels, transport
                                and tickets, agent pricing and refunds, with
                                each group's profit known to the rupee.
                            </p>
                        </article>
                        <article class="trade-card">
                            <h3>Every other business</h3>
                            <p>
                                Invoices, bills, bank, payroll and the reports
                                your accountant asks for, with guided set-up.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="trust">
                <div class="wrap split trust-in">
                    <h2 class="h2 h2-40">
                        Books you can trust, and only your people can open
                    </h2>
                    <ul class="trust-list">
                    <li>Every entry has two sides, so the trial balance always agrees with itself.</li>
                    <li>Mistakes are corrected with a visible reversal, never by quietly editing the past.</li>
                    <li>Closed periods stay closed until someone with the right role reopens them.</li>
                    <li>Roles decide who can see and do what, down to single actions.</li>
                    <li>Each company's records are kept apart in the database.</li>
                    <li>Run several companies from one login, and switch between them in a click.</li>
                    </ul>
                </div>
            </section>

            <section id="create" class="wrap sec closing">
                <h2 class="h2 h2-44">
                    Bring your opening balances. Haasib takes it from there.
                </h2>
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="btn btn-xl"
                >
                    Open Haasib
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="register()"
                    class="btn btn-xl"
                >
                    Create account
                </Link>
                <Link v-else :href="login()" class="btn btn-xl">Sign in</Link>
            </section>
        </main>

        <footer class="foot">
            <div class="wrap foot-in">
                <span>Haasib</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.lp {
    font-family: 'Familjen Grotesk', sans-serif;
    color: #10241f;
    background: #f3f6f4;
    min-height: 100vh;
    font-size: 16px;
    line-height: normal;
}
.lp *,
.lp *::before,
.lp *::after {
    box-sizing: border-box;
}
.lp :where(h1, h2, h3, p, ul, ol, dl, dd) {
    margin: 0;
}
.lp a {
    color: #7a5a12;
}
.lp a:hover {
    color: #5c430c;
}
.lp a:focus-visible {
    outline: 2px solid #0f2e28;
    outline-offset: 3px;
    border-radius: 6px;
}
.num {
    font-variant-numeric: tabular-nums;
}
.brass {
    color: #d9b45a;
}
.wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding-left: 32px;
    padding-right: 32px;
}

/* header */
.head {
    padding-top: 24px;
    padding-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.lp a.brand {
    text-decoration: none;
    color: #0f2e28;
    font-weight: 700;
    font-size: 22px;
    letter-spacing: -0.01em;
}
.nav {
    display: flex;
    gap: 26px;
    align-items: center;
    flex-wrap: wrap;
    font-size: 15px;
}
.lp a.nav-link {
    color: #44564f;
    text-decoration: none;
}
.lp a.nav-link:hover {
    color: #0f2e28;
}
.lp a.signin {
    color: #0f2e28;
    text-decoration: none;
    font-weight: 600;
}
.lp a.btn {
    background: #0f2e28;
    color: #f3f6f4;
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
}
.lp a.btn:hover {
    color: #f3f6f4;
    background: #17433a;
}
.btn-sm {
    padding: 11px 18px;
    border-radius: 8px;
}
.btn-lg {
    padding: 15px 24px;
    border-radius: 10px;
    font-size: 17px;
}
.btn-xl {
    padding: 16px 26px;
    border-radius: 10px;
    font-size: 17px;
}
.lp a.btn-ghost {
    color: #0f2e28;
    text-decoration: none;
    padding: 15px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 17px;
    box-shadow: inset 0 0 0 1.5px #b9c8c1;
}
.lp a.btn-ghost:hover {
    color: #0f2e28;
    background: #e7eeea;
}

/* hero */
.hero {
    padding-top: 56px;
    padding-bottom: 104px;
}
.h1 {
    font-weight: 700;
    font-size: 74px;
    line-height: 1;
    letter-spacing: -0.035em;
    margin: 0 0 22px;
    max-width: 13em;
    color: #0f2e28;
}
.lede {
    font-size: 21px;
    line-height: 1.5;
    color: #44564f;
    margin: 0 0 36px;
    max-width: 34em;
}
.cta-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 56px;
}
.palette {
    background: #0f2e28;
    color: #edf2ef;
    border-radius: 16px;
    box-shadow: 0 30px 60px -32px rgba(15, 46, 40, 0.55);
    max-width: 880px;
    overflow: hidden;
}
.pal-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 22px 26px;
    border-bottom: 1px solid #234a41;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 20px;
}
.kbd {
    margin-left: auto;
    font-family: 'Familjen Grotesk', sans-serif;
    font-size: 13px;
    color: #9fb3aa;
    box-shadow: inset 0 0 0 1px #2f5a50;
    border-radius: 5px;
    padding: 3px 8px;
}
.caret {
    display: inline-block;
    width: 2px;
    height: 22px;
    background: #f4efe2;
    vertical-align: -4px;
    margin-left: 2px;
}
.pal-body {
    padding: 10px 12px;
    min-height: 236px;
}
.sug {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    padding: 14px;
}
.sug.hot {
    background: #1a4239;
    border-radius: 8px;
}
.sub {
    color: #a8bcb3;
    font-size: 15px;
}
.act {
    font-size: 14px;
    color: #a8bcb3;
    align-self: center;
}
.drop {
    animation: drop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
.drop2 {
    animation: drop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) 0.08s both;
}
.drop3 {
    animation: drop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) 0.16s both;
}
@keyframes drop {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes blink {
    50% {
        opacity: 0;
    }
}
@media (prefers-reduced-motion: no-preference) {
    .caret {
        animation: blink 1s steps(1) infinite;
    }
}

/* bands and shared type */
.band {
    background: #fff;
    border-top: 1px solid #dce4e0;
    border-bottom: 1px solid #dce4e0;
}
.sec {
    padding-top: 104px;
    padding-bottom: 104px;
}
.h2 {
    font-size: 46px;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: #0f2e28;
}
.h2-40 {
    font-size: 40px;
}
.h2-44 {
    font-size: 44px;
    max-width: 16em;
}
.mb14 { margin-bottom: 14px !important; }
.mb18 { margin-bottom: 18px !important; }
.mb44 { margin-bottom: 44px !important; }
.mb48 { margin-bottom: 48px !important; }
.mb52 { margin-bottom: 52px !important; }
.mt4 { margin-top: 4px; }
.mt6 { margin-top: 6px; }
.mt8 { margin-top: 8px; }
.body-lg {
    font-size: 19px;
    line-height: 1.6;
    color: #44564f;
    margin-bottom: 18px;
}
.body-lg.last {
    margin-bottom: 0;
}
.body-md {
    font-size: 19px;
    color: #44564f;
    max-width: 38em;
}

/* rabbit hole */
.split {
    padding-top: 104px;
    padding-bottom: 104px;
    display: grid;
    grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
    gap: 64px;
    align-items: start;
}
.levels {
    list-style: none;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.lvl {
    transition:
        opacity 0.7s cubic-bezier(0.2, 0.8, 0.2, 1),
        transform 0.7s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.card {
    border-radius: 14px;
    padding: 20px 22px;
    background: #f3f6f4;
    box-shadow: inset 0 0 0 1px #dce4e0;
}
.card-dark {
    background: #0f2e28;
    color: #edf2ef;
    box-shadow: none;
}
.indent1 {
    margin-left: 32px;
}
.indent2 {
    margin-left: 64px;
}
.card-h {
    display: flex;
    justify-content: space-between;
    font-size: 19px;
    font-weight: 700;
    color: #0f2e28;
}
.card-dark .card-h {
    color: inherit;
}
.card-h-lg {
    font-size: 22px;
}
.row-hl {
    display: flex;
    justify-content: space-between;
    margin: 12px -10px 0;
    padding: 8px 10px;
    border-radius: 8px;
    background: transparent;
    font-weight: 600;
}
.hl {
    transition: background-color 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.hl.on {
    background: rgba(217, 180, 90, 0.28);
}
.row-mute {
    display: flex;
    justify-content: space-between;
    color: #5a6b64;
}
.card-dark .sub {
    font-size: inherit;
}
.open-inv {
    display: inline-block;
    margin-top: 12px;
    color: #d9b45a;
    font-weight: 600;
}

/* commands */
.ref {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 64px;
}
.ref-list {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 16px 24px;
    align-items: baseline;
    border-top: 2px solid #0f2e28;
    padding-top: 16px;
}
.ref-list dt {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 15px;
}
.ref-list dd {
    color: #44564f;
}

/* features */
.books {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 48px 56px;
}
.books h3 {
    font-size: 20px;
    margin: 0 0 12px;
    color: #0f2e28;
}
.books p {
    line-height: 1.7;
    color: #44564f;
}

/* trades */
.trades {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 20px;
}
.trade-main {
    grid-column: span 7;
    background: #0f2e28;
    color: #edf2ef;
    border-radius: 18px;
    padding: 40px;
}
.trade-main h3 {
    font-size: 28px;
    letter-spacing: -0.02em;
    margin: 0 0 14px;
}
.trade-lead {
    margin-bottom: 24px !important;
    font-size: 18px;
    line-height: 1.6;
    color: #c2d1ca;
}
.trade-tail {
    line-height: 1.8;
}
.trade-side {
    grid-column: span 5;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.trade-card {
    background: #fff;
    box-shadow: inset 0 0 0 1px #dce4e0;
    border-radius: 18px;
    padding: 32px;
}
.trade-card h3 {
    font-size: 22px;
    margin: 0 0 10px;
    color: #0f2e28;
}
.trade-card p {
    line-height: 1.6;
    color: #44564f;
}

/* trust */
.trust {
    background: #e7eeea;
}
.trust-in {
    align-items: stretch;
}
.trust-list {
    list-style: none;
    padding: 0;
    display: grid;
    gap: 22px;
    font-size: 18px;
    line-height: 1.55;
    color: #2c3f38;
}

/* closing + footer */
.closing {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.foot {
    border-top: 1px solid #dce4e0;
}
.foot-in {
    padding-top: 28px;
    padding-bottom: 28px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 14px;
    color: #5a6b64;
}

@media (prefers-reduced-motion: reduce) {
    .lvl,
    .hl {
        transition: none;
    }
    .drop,
    .drop2,
    .drop3 {
        animation: none;
    }
}

@media (max-width: 860px) {
    .split,
    .ref,
    .books,
    .trades {
        grid-template-columns: 1fr;
    }
    .trade-main,
    .trade-side {
        grid-column: auto;
    }
    .h1 {
        font-size: 46px;
    }
    .wrap {
        padding-left: 16px;
        padding-right: 16px;
    }
    .indent1 {
        margin-left: 16px;
    }
    .indent2 {
        margin-left: 32px;
    }
}
</style>
