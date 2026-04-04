<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Onwynd') }} — API</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream:    #f7f4f2;
            --brown:    #4b3425;
            --brown-lt: #6d4b36;
            --sage:     #9bb068;
            --sage-dk:  #7a9150;
            --coral:    #fe814b;
            --yellow:   #ffce5c;
            --muted:    rgba(75,52,37,0.5);
            --border:   rgba(75,52,37,0.1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Urbanist', system-ui, sans-serif;
            background: var(--cream);
            color: var(--brown);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Grid background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(75,52,37,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(75,52,37,0.04) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Layout ── */
        .page {
            position: relative;
            z-index: 1;
            max-width: 860px;
            margin: 0 auto;
            padding: 0 24px 80px;
        }

        /* ── Navbar ── */
        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px 0 0;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .nav-dots {
            display: flex;
            flex-wrap: wrap;
            width: 24px;
            gap: 3px;
        }
        .nav-dots span {
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }
        .nav-dots span:nth-child(1) { background: var(--sage);   margin-left: 3px; }
        .nav-dots span:nth-child(2) { background: var(--coral); }
        .nav-dots span:nth-child(3) { background: var(--yellow); }
        .nav-wordmark {
            font-size: 18px;
            font-weight: 800;
            color: var(--brown);
            letter-spacing: -0.3px;
        }
        .nav-tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            color: var(--sage);
            background: rgba(155,176,104,0.12);
            border: 1px solid rgba(155,176,104,0.3);
            border-radius: 6px;
            padding: 3px 8px;
        }

        /* ── Hero ── */
        .hero {
            padding: 72px 0 56px;
        }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--sage);
            margin-bottom: 24px;
        }
        .hero-eyebrow::before {
            content: '';
            width: 28px;
            height: 1px;
            background: var(--sage);
            display: inline-block;
        }
        .hero-title {
            font-size: clamp(40px, 7vw, 68px);
            font-weight: 900;
            line-height: 1.0;
            letter-spacing: -2px;
            color: var(--brown);
            margin-bottom: 24px;
        }
        .hero-title .accent { color: var(--sage); }
        .hero-title .accent-2 { color: var(--coral); }
        .hero-sub {
            font-size: 18px;
            font-weight: 500;
            line-height: 1.6;
            color: var(--muted);
            max-width: 520px;
            margin-bottom: 40px;
        }
        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 100px;
            font-family: 'Urbanist', sans-serif;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(75,52,37,0.12); }
        .btn-primary   { background: var(--sage);  color: #fff; border-color: var(--sage); }
        .btn-secondary { background: transparent; color: var(--brown); border-color: var(--border); }
        .btn-secondary:hover { border-color: var(--brown); }
        .btn-ghost { background: transparent; color: var(--sage); border-color: rgba(155,176,104,0.4); font-family: 'JetBrains Mono', monospace; font-size: 13px; }

        /* ── Status strip ── */
        .status-strip {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 56px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--sage);
            box-shadow: 0 0 0 3px rgba(155,176,104,0.2);
            animation: pulse 2.4s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(155,176,104,0.2); }
            50%       { box-shadow: 0 0 0 6px rgba(155,176,104,0.08); }
        }

        /* ── Cards grid ── */
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 56px;
        }
        @media (max-width: 640px) { .cards { grid-template-columns: 1fr; } }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            transition: border-color 0.2s, transform 0.2s;
        }
        .card:hover { border-color: rgba(75,52,37,0.2); transform: translateY(-2px); }
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 18px;
        }
        .card-icon.sage   { background: rgba(155,176,104,0.15); }
        .card-icon.coral  { background: rgba(254,129,75,0.12); }
        .card-icon.yellow { background: rgba(255,206,92,0.2); }
        .card-title { font-size: 15px; font-weight: 800; margin-bottom: 6px; }
        .card-desc  { font-size: 13px; line-height: 1.6; color: var(--muted); margin-bottom: 16px; }
        .card-link  {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--sage);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .card-link:hover { text-decoration: underline; }

        /* ── Divider ── */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 0 0 48px;
        }

        /* ── Terminal block ── */
        .terminal {
            background: var(--brown);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 56px;
        }
        .terminal-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: rgba(0,0,0,0.2);
        }
        .terminal-bar span {
            width: 12px; height: 12px; border-radius: 50%;
        }
        .terminal-bar span:nth-child(1) { background: #ff5f57; }
        .terminal-bar span:nth-child(2) { background: #febc2e; }
        .terminal-bar span:nth-child(3) { background: #28c840; }
        .terminal-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: rgba(255,255,255,0.35);
            margin-left: 8px;
        }
        .terminal-body {
            padding: 24px 28px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 2;
        }
        .t-comment { color: rgba(155,176,104,0.6); }
        .t-cmd     { color: rgba(255,255,255,0.9); }
        .t-out     { color: rgba(255,206,92,0.8); }
        .t-url     { color: var(--coral); }
        .t-prompt  { color: rgba(155,176,104,0.5); user-select: none; }
        .t-cursor  {
            display: inline-block;
            width: 8px; height: 14px;
            background: var(--sage);
            vertical-align: middle;
            animation: blink 1.1s step-end infinite;
            margin-left: 2px;
        }
        @keyframes blink { 0%,100% { opacity: 1; } 50% { opacity: 0; } }

        /* ── Secret access ── */
        .secret-section {
            margin-bottom: 56px;
        }
        .secret-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .secret-label::before, .secret-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .secret-form {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }
        .secret-input {
            flex: 1;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            padding: 14px 20px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            color: var(--brown);
            outline: none;
            transition: border-color 0.2s;
            letter-spacing: 0.05em;
        }
        .secret-input::placeholder { color: rgba(75,52,37,0.3); }
        .secret-input:focus { border-color: var(--sage); }
        .secret-input.invalid { border-color: var(--coral); animation: shake 0.35s ease; }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60% { transform: translateX(-4px); }
            40%,80% { transform: translateX(4px); }
        }
        .secret-btn {
            padding: 14px 22px;
            border-radius: 12px;
            border: none;
            background: var(--brown);
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .secret-btn:hover { background: var(--coral); }

        /* ── Secret panel ── */
        .secret-panel {
            display: none;
            margin-top: 16px;
            background: var(--brown);
            border-radius: 16px;
            overflow: hidden;
        }
        .secret-panel.visible { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .secret-panel-header {
            background: rgba(0,0,0,0.25);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .secret-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            background: var(--sage);
            color: var(--brown);
            border-radius: 6px;
            padding: 3px 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .secret-panel-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .secret-panel-body {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .secret-item {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 16px 20px;
        }
        .secret-item-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--sage);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }
        .secret-item-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: rgba(255,255,255,0.85);
            word-break: break-all;
            line-height: 1.6;
        }
        .secret-item-value a {
            color: var(--coral);
            text-decoration: none;
        }
        .secret-item-value a:hover { text-decoration: underline; }

        /* ── Footer ── */
        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding-top: 32px;
            border-top: 1px solid var(--border);
        }
        .footer-copy {
            font-size: 13px;
            color: var(--muted);
        }
        .footer-links {
            display: flex;
            gap: 20px;
        }
        .footer-links a {
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
        }
        .footer-links a:hover { color: var(--sage); }
    </style>
</head>
<body>

<div class="page">

    <!-- Nav -->
    <nav class="nav">
        <a href="https://onwynd.com" class="nav-brand">
            <div class="nav-dots">
                <span></span><span></span><span></span>
            </div>
            <span class="nav-wordmark">onwynd</span>
        </a>
        <span class="nav-tag">API</span>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-eyebrow">REST API &nbsp;·&nbsp; v1 &nbsp;·&nbsp; {{ date('Y') }}</div>
        <h1 class="hero-title">
            Built for<br>
            <span class="accent">wellbeing</span>,<br>
            <span class="accent-2">at scale.</span>
        </h1>
        <p class="hero-sub">
            The Onwynd API powers seamless mental wellness experiences — sessions, subscriptions, insights, and beyond.
        </p>
        <div class="hero-actions">
            <a href="/api/v1/health" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>
                Health Check
            </a>
            <a href="https://onwynd.com" target="_blank" rel="noopener" class="btn btn-secondary">
                onwynd.com →
            </a>
            <a href="/api/v1/config" class="btn btn-ghost">
                GET /config
            </a>
        </div>
    </section>

    <!-- Status -->
    <div class="status-strip">
        <span class="status-dot"></span>
        All systems operational
    </div>

    <!-- Endpoint cards -->
    <div class="cards">
        <div class="card">
            <div class="card-icon sage">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#9bb068"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
            </div>
            <div class="card-title">Health</div>
            <div class="card-desc">Verify service uptime, latency, and system timestamp in one call.</div>
            <a href="/api/v1/health" class="card-link">GET /api/v1/health →</a>
        </div>
        <div class="card">
            <div class="card-icon coral">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#fe814b"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>
            </div>
            <div class="card-title">Status</div>
            <div class="card-desc">High-level system information and runtime details for integrators.</div>
            <a href="/api/v1/system/status" class="card-link">GET /api/v1/system/status →</a>
        </div>
        <div class="card">
            <div class="card-icon yellow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#c89b00"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            </div>
            <div class="card-title">Config</div>
            <div class="card-desc">Feature flags and public configuration available for frontend clients.</div>
            <a href="/api/v1/config" class="card-link">GET /api/v1/config →</a>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Terminal block -->
    <div class="terminal">
        <div class="terminal-bar">
            <span></span><span></span><span></span>
            <span class="terminal-title">onwynd-api — bash</span>
        </div>
        <div class="terminal-body">
            <div class="t-comment"># Verify the API is alive</div>
            <div><span class="t-prompt">$ </span><span class="t-cmd">curl -s https://api.onwynd.com/api/v1/health | jq .</span></div>
            <div class="t-out">{</div>
            <div class="t-out">&nbsp;&nbsp;"status": "ok",</div>
            <div class="t-out">&nbsp;&nbsp;"timestamp": "2026-03-24T10:42:18Z"</div>
            <div class="t-out">}</div>
            <br>
            <div class="t-comment"># Ready. Ship it.</div>
            <div><span class="t-prompt">$ </span><span class="t-cmd">_<span class="t-cursor"></span></span></div>
        </div>
    </div>

    <!-- Secret access -->
    <div class="secret-section">
        <div class="secret-label">access control</div>
        <div class="secret-form">
            <input
                type="password"
                class="secret-input"
                id="secret-input"
                placeholder="Enter access key..."
                autocomplete="off"
                spellcheck="false"
            >
            <button class="secret-btn" id="secret-btn" onclick="checkSecret()">→ Unlock</button>
        </div>

        <div class="secret-panel" id="secret-panel"></div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-copy">&copy; {{ date('Y') }} Onwynd Inc. &nbsp;·&nbsp; Made with care.</div>
        <div class="footer-links">
            <a href="https://onwynd.com" target="_blank">Website</a>
            <a href="https://onwynd.com/privacy" target="_blank">Privacy</a>
            <a href="mailto:support@onwynd.com">Support</a>
        </div>
    </footer>

</div>

<script>
    function checkSecret() {
        const input = document.getElementById('secret-input');
        const panel = document.getElementById('secret-panel');
        const btn   = document.getElementById('secret-btn');

        btn.disabled = true;
        btn.textContent = '...';

        fetch('/internal/unlock', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ key: input.value }),
        })
        .then(res => {
            if (!res.ok) throw new Error('denied');
            return res.json();
        })
        .then(data => {
            // Build panel HTML from server response — nothing was pre-rendered
            let linksHtml = data.links.map(l =>
                `<div class="secret-item">
                    <div class="secret-item-label">${l.label}</div>
                    <div class="secret-item-value"><a href="${l.href}" target="_blank">${l.href}</a></div>
                </div>`
            ).join('');
            panel.innerHTML = `
                <div class="secret-panel-header">
                    <span class="secret-badge">AUTHORIZED</span>
                    <span class="secret-panel-title">Internal Access — {{ config('app.name') }} Core</span>
                </div>
                <div class="secret-panel-body">
                    ${linksHtml}
                    <div class="secret-item">
                        <div class="secret-item-label">Environment</div>
                        <div class="secret-item-value">${data.env} · PHP ${data.php}</div>
                    </div>
                    <div class="secret-item">
                        <div class="secret-item-label">App Version</div>
                        <div class="secret-item-value">${data.version}</div>
                    </div>
                </div>`;
            panel.classList.add('visible');
            input.style.borderColor = 'var(--sage)';
            btn.textContent = '✓ Access granted';
            btn.style.background = 'var(--sage)';
            input.disabled = true;
        })
        .catch(() => {
            input.classList.add('invalid');
            input.value = '';
            btn.textContent = '✗ Try again';
            btn.style.background = 'var(--coral)';
            btn.disabled = false;
            setTimeout(() => {
                input.classList.remove('invalid');
                btn.textContent = '→ Unlock';
                btn.style.background = '';
            }, 1200);
        });
    }

    document.getElementById('secret-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') checkSecret();
    });
</script>

</body>
</html>
