<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WhiteGlove | Event Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wg-ink: #142233;
            --wg-muted: #617388;
            --wg-bg: #f4f8fb;
            --wg-surface: #ffffff;
            --wg-primary: #0c6e84;
            --wg-accent: #f29f05;
            --wg-border: #dde7ef;
            --wg-shadow: 0 18px 40px rgba(10, 34, 52, 0.08);
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: "Plus Jakarta Sans", sans-serif;
            color: var(--wg-ink);
            background:
                radial-gradient(circle at 12% -10%, #d8eef8 0, transparent 30%),
                radial-gradient(circle at 85% 0%, #fdf1d3 0, transparent 28%),
                var(--wg-bg);
        }

        .nav-wrap {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(10px);
            background: rgba(244, 248, 251, 0.86);
            border-bottom: 1px solid var(--wg-border);
        }

        .nav {
            max-width: 1150px;
            margin: 0 auto;
            padding: .85rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            font-family: "Space Grotesk", sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: .02em;
            color: var(--wg-ink);
            text-decoration: none;
        }

        .actions { display: flex; gap: .6rem; flex-wrap: wrap; }

        .btn {
            border: 0;
            border-radius: 999px;
            padding: .6rem .95rem;
            text-decoration: none;
            font-size: .84rem;
            font-weight: 700;
            transition: transform .2s ease, box-shadow .2s ease;
            display: inline-block;
        }

        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: var(--wg-primary); color: #fff; box-shadow: 0 10px 24px rgba(12,110,132,.28); }
        .btn-soft { background: #d9edf2; color: #08414e; }
        .btn-line { border: 1px solid #bdd0dd; color: #1f3f5a; background: #fff; }

        main { max-width: 1150px; margin: 0 auto; padding: 1.1rem 1rem 3rem; }

        .hero {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 1rem;
            align-items: stretch;
        }

        .hero-copy {
            background: linear-gradient(140deg, #0f6f84 0%, #13938d 60%, #49a7a2 100%);
            color: #fff;
            border-radius: 26px;
            padding: 2rem;
            box-shadow: var(--wg-shadow);
        }

        .hero-copy h1 {
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(1.9rem, 3vw, 2.8rem);
            line-height: 1.08;
            margin: 0 0 1rem;
        }

        .hero-copy p { margin: 0 0 1.25rem; font-size: 1rem; color: rgba(255,255,255,.9); max-width: 60ch; }
        .hero-cta { display: flex; gap: .6rem; flex-wrap: wrap; }

        .hero-media {
            border-radius: 26px;
            overflow: hidden;
            box-shadow: var(--wg-shadow);
            position: relative;
            min-height: 320px;
        }

        .hero-media img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .floating-note {
            position: absolute;
            left: 1rem;
            bottom: 1rem;
            padding: .7rem .85rem;
            border-radius: 14px;
            color: #0f2e3f;
            background: rgba(255,255,255,.87);
            font-size: .82rem;
            font-weight: 700;
            backdrop-filter: blur(7px);
        }

        .section { margin-top: 1.1rem; }

        .panel {
            background: var(--wg-surface);
            border: 1px solid var(--wg-border);
            border-radius: 22px;
            box-shadow: var(--wg-shadow);
            padding: 1.2rem;
        }

        .heading {
            margin: 0 0 .25rem;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.25rem;
        }

        .sub { margin: 0 0 1rem; color: var(--wg-muted); font-size: .92rem; }

        .timeline {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .65rem;
        }

        .step {
            background: #f8fbfd;
            border: 1px solid var(--wg-border);
            border-radius: 14px;
            padding: .72rem;
            text-align: center;
            font-size: .8rem;
            color: #3d5a70;
            position: relative;
        }

        .step.active { font-weight: 700; }
        .step.s1 { background: #e6f3ff; border-color: #bcdaf4; color: #1d4f75; }
        .step.s2 { background: #e8f8f2; border-color: #bfe4d4; color: #1d6a4f; }
        .step.s3 { background: #fff6e7; border-color: #f0d9b0; color: #83520e; }
        .step.s4 { background: #f0efff; border-color: #d3cffc; color: #4840a3; }
        .step.s5 { background: #ffeef1; border-color: #f3c6d1; color: #9a2f4d; }

        .step:not(:last-child)::after {
            content: "→";
            position: absolute;
            right: -0.48rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6d8aa2;
            font-weight: 800;
            font-size: .95rem;
            z-index: 2;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .65rem;
        }

        .stat {
            background: #fff;
            border: 1px solid var(--wg-border);
            border-radius: 16px;
            padding: .8rem;
        }

        .stat strong { display: block; font-size: 1.4rem; font-family: "Space Grotesk", sans-serif; }
        .stat span { color: var(--wg-muted); font-size: .8rem; }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .8rem;
        }

        .feature {
            border: 1px solid var(--wg-border);
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .feature:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(20,34,51,.12); }

        .feature img { width: 100%; height: 170px; object-fit: cover; display: block; }
        .feature .content { padding: .9rem; }
        .feature h3 { margin: 0 0 .4rem; font-size: 1rem; font-family: "Space Grotesk", sans-serif; }
        .feature p { margin: 0; color: var(--wg-muted); font-size: .86rem; }

        .modules {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .8rem;
        }

        .module {
            background: #fff;
            border: 1px solid var(--wg-border);
            border-radius: 16px;
            padding: .95rem;
        }

        .module h4 { margin: 0 0 .45rem; font-size: .98rem; }
        .module p { margin: 0 0 .75rem; color: var(--wg-muted); font-size: .85rem; min-height: 38px; }
        .module a { color: #14557e; text-decoration: none; font-weight: 700; font-size: .82rem; }

        .reveal {
            opacity: 0;
            transform: translateY(22px) scale(.985);
            transition: opacity .6s ease, transform .6s ease;
        }

        .reveal.in {
            opacity: 1;
            transform: none;
        }

        @media (max-width: 960px) {
            .hero { grid-template-columns: 1fr; }
            .timeline { grid-template-columns: 1fr 1fr; }
            .feature-grid, .modules { grid-template-columns: 1fr 1fr; }
            .stats { grid-template-columns: 1fr 1fr; }
            .step:not(:last-child)::after { content: ""; }
        }

        @media (max-width: 640px) {
            .feature-grid, .modules, .timeline, .stats { grid-template-columns: 1fr; }
            .hero-copy { padding: 1.25rem; }
        }

        footer {
            border-top: 1px solid var(--wg-border);
            padding: .95rem 1rem;
            color: var(--wg-muted);
            font-size: .82rem;
            margin-top: 1.3rem;
            background: rgba(255,255,255,.6);
        }

        .foot {
            max-width: 1150px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: .9rem;
            align-items: start;
        }

        .foot-left strong { color: var(--wg-ink); display: block; margin-bottom: .2rem; }
        .foot-links, .foot-social { display: flex; gap: .55rem; flex-wrap: wrap; align-items: center; }
        .foot-links a, .foot-social a { color: #1b557d; text-decoration: none; font-weight: 700; font-size: .8rem; }
        .ico {
            width: 28px; height: 28px; border-radius: 999px; border: 1px solid var(--wg-border);
            display: inline-flex; align-items: center; justify-content: center; background: #fff;
        }
        .ico svg { width: 14px; height: 14px; fill: #1b557d; }
        .handles { margin-top: .35rem; font-size: .78rem; color: var(--wg-muted); }
        @media (max-width: 760px) {
            .foot { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="nav-wrap">
    <nav class="nav">
        <a class="brand" href="/WhiteGlove/public/index.php">WhiteGlove</a>
        <div class="actions">
            <a class="btn btn-line" href="/WhiteGlove/public/login.php">Login</a>
            <a class="btn btn-soft" href="/WhiteGlove/public/register.php">Register</a>
            <a class="btn btn-primary" href="/WhiteGlove/public/dashboard.php">Dashboard</a>
        </div>
    </nav>
</header>

<main>
    <section class="hero reveal">
        <div class="hero-copy">
            <h1>Plan, Manage, and Execute Events with Confidence</h1>
            <p>WhiteGlove helps clients, providers, and admin collaborate on bookings, bidding, milestone payments, and analytics from one powerful workflow.</p>
            <div class="hero-cta">
                <a class="btn btn-primary" href="/WhiteGlove/public/client_experience.php">Explore Client Hub</a>
                <a class="btn btn-soft" href="/WhiteGlove/public/provider_workbench.php">Access Provider Workspace</a>
                <a class="btn btn-line" href="/WhiteGlove/public/admin_analytics.php">View Admin Analytics</a>
            </div>
        </div>
        <div class="hero-media">
            <img src="https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80" alt="Luxury event reception setup">
            <div class="floating-note">Live Lifecycle: Request -> Quote -> Payment -> Event -> Review</div>
        </div>
    </section>

    <section class="section panel reveal">
        <h2 class="heading">Booking Lifecycle</h2>
        <p class="sub">A structured workflow designed to reflect real-world event management processes.</p>
        <div class="timeline">
            <div class="step active s1">Requirement Submission</div>
            <div class="step active s2">Vendor Bidding</div>
            <div class="step s3">Quotation & Invoicing</div>
            <div class="step s4">Milestone-Based Payments</div>
            <div class="step s5">Execution & Feedback</div>
        </div>
    </section>

    <section class="section panel reveal">
        <h2 class="heading">Platform Pulse</h2>
        <p class="sub">Key platform highlights demonstrating operational readiness and system capabilities.</p>
        <div class="stats">
            <div class="stat"><strong>3 User Roles</strong><span>Admin / Client / Provider</span></div>
            <div class="stat"><strong>12+ Integrated Workflows</strong><span>Booking to refund closure</span></div>
            <div class="stat"><strong>Intuitive User Experience</strong><span>Role dashboards and hub pages</span></div>
            <div class="stat"><strong>Audit-Ready Infrastructure</strong><span>Transactions, logs, approvals</span></div>
        </div>
    </section>

    <section class="section panel reveal">
        <h2 class="heading">Flagship Features</h2>
        <p class="sub">Designed to reflect real-world event management workflows beyond basic CRUD operations.</p>
        <div class="feature-grid">
            <article class="feature">
                <img src="https://images.unsplash.com/photo-1478147427282-58a87a120781?auto=format&fit=crop&w=900&q=80" alt="Event stage and lighting">
                <div class="content">
                    <h3>Vendor Bidding Marketplace</h3>
                    <p>Post requirements and receive competitive vendor bids for easy comparison.</p>
                </div>
            </article>
            <article class="feature">
                <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=80" alt="Team planning meeting">
                <div class="content">
                    <h3>Milestone Payment Engine</h3>
                    <p>Manage staged payments with secure and auditable transactions.</p>
                </div>
            </article>
            <article class="feature">
                <img src="https://images.unsplash.com/photo-1505236858219-8359eb29e329?auto=format&fit=crop&w=900&q=80" alt="Event analytics on laptop">
                <div class="content">
                    <h3>Admin Risk Dashboard</h3>
                    <p>Track key metrics including cancellations, revenue, and provider performance.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="section panel reveal">
        <h2 class="heading">Role Hubs</h2>
        <p class="sub">Jump directly into your role-specific operations.</p>
        <div class="modules">
            <div class="module">
                <h4>Client Experience Hub</h4>
                <p>Bidding, payments, checklists, reviews, and notifications.</p>
                <a href="/WhiteGlove/public/client_experience.php">Open Client Hub</a>
            </div>
            <div class="module">
                <h4>Provider Workbench</h4>
                <p>Submit bids, create quotes, generate invoices, and coordinate with clients.</p>
                <a href="/WhiteGlove/public/provider_workbench.php">Open Provider Workbench</a>
            </div>
            <div class="module">
                <h4>Admin Control Center</h4>
                <p>Approve providers, supervise refunds, and track operational health metrics.</p>
                <a href="/WhiteGlove/public/admin_analytics.php">Open Admin Center</a>
            </div>
        </div>
    </section>
</main>

<script>
    const items = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('in');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.16 });
    items.forEach((el, index) => {
        el.style.transitionDelay = `${index * 80}ms`;
        io.observe(el);
    });
</script>
<footer>
    <div class="foot">
        <div class="foot-left">
            <strong>&copy; <?php echo date('Y'); ?> WhiteGlove. All rights reserved.</strong>
            <div>Event Management System</div>
            <div class="handles">@whitegloveevents | @whitegloveofficial</div>
        </div>
        <div>
            <div class="foot-links">
                <a href="/WhiteGlove/public/about.php">About Us</a>
                <a href="/WhiteGlove/public/privacy.php">Privacy Policy</a>
                <a href="/WhiteGlove/public/terms.php">Terms of Service</a>
                <a href="/WhiteGlove/public/contact.php">Contact Us</a>
                <a href="/WhiteGlove/public/blog.php">Blog</a>
            </div>
            <div class="foot-social" style="margin-top:.45rem;">
                <a class="ico" href="#" aria-label="Instagram">
                    <svg viewBox="0 0 24 24"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8zm9.65 1.5a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="Facebook">
                    <svg viewBox="0 0 24 24"><path d="M13.5 21v-8h2.7l.4-3h-3.1V8.1c0-.9.3-1.6 1.7-1.6h1.5V3.8c-.3 0-1.1-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.9V10H8v3h2.8v8h2.7z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24"><path d="M6.5 8.2A1.7 1.7 0 1 1 6.5 4.8a1.7 1.7 0 0 1 0 3.4zM5 9.6h3V20H5V9.6zm5 0h2.8V11h.1c.4-.8 1.4-1.6 2.9-1.6 3.1 0 3.7 2 3.7 4.7V20h-3v-5.2c0-1.2 0-2.8-1.8-2.8s-2 1.3-2 2.7V20h-3V9.6z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="YouTube">
                    <svg viewBox="0 0 24 24"><path d="M23 12s0-3.5-.4-5.2a2.9 2.9 0 0 0-2-2C18.8 4.3 12 4.3 12 4.3s-6.8 0-8.6.5a2.9 2.9 0 0 0-2 2C1 8.5 1 12 1 12s0 3.5.4 5.2a2.9 2.9 0 0 0 2 2c1.8.5 8.6.5 8.6.5s6.8 0 8.6-.5a2.9 2.9 0 0 0 2-2C23 15.5 23 12 23 12zM10 15.5v-7l6 3.5-6 3.5z"/></svg>
                </a>
                <a class="ico" href="#" aria-label="X">
                    <svg viewBox="0 0 24 24"><path d="M18.9 2H22l-6.8 7.7L23 22h-6.1l-4.8-6.3L6.5 22H3.4l7.3-8.3L1 2h6.2l4.3 5.7L18.9 2zm-1.1 18h1.7L6.2 3.9H4.3L17.8 20z"/></svg>
                </a>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
