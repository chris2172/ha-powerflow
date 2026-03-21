<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Bracket characters built at runtime so WordPress shortcode parser never matches them
$_b1 = chr(91); $_b2 = chr(93); ?>
<div class="ha-pf-manual-wrapper">
    <style>
        .ha-pf-manual-wrapper {
            --ha-pf-primary: #1a73e8;
            --ha-pf-primary-hover: #1557b0;
            --ha-pf-bg: #f8fafc;
            --ha-pf-card-bg: #ffffff;
            --ha-pf-text-main: #1e293b;
            --ha-pf-text-muted: #64748b;
            --ha-pf-border: #e2e8f0;
            --ha-pf-radius: 12px;
            --ha-pf-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --ha-pf-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ha-pf-text-main);
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .ha-pf-manual-wrapper * { box-sizing: border-box; }

        .ha-pf-manual-wrapper header {
            text-align: center;
            margin-bottom: 60px;
        }

        .ha-pf-manual-wrapper h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            border: none;
            padding: 0;
            line-height: 1.2;
        }

        .ha-pf-manual-wrapper .subtitle {
            font-size: 1.1rem;
            color: var(--ha-pf-text-muted);
        }

        .ha-pf-manual-wrapper .card {
            background: var(--ha-pf-card-bg);
            border-radius: var(--ha-pf-radius);
            padding: 32px;
            margin-bottom: 32px;
            border: 1px solid var(--ha-pf-border);
            box-shadow: var(--ha-pf-shadow);
            transition: transform 0.2s ease;
        }

        .ha-pf-manual-wrapper .card:hover { box-shadow: var(--ha-pf-shadow-lg); }

        .ha-pf-manual-wrapper h2 {
            font-size: 1.5rem;
            margin-top: 0;
            border-bottom: 2px solid var(--ha-pf-border);
            padding-bottom: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--ha-pf-text-main);
        }

        .ha-pf-manual-wrapper h3 {
            font-size: 1.25rem;
            margin-top: 32px;
            margin-bottom: 16px;
            color: var(--ha-pf-primary);
            font-weight: 700;
        }

        .ha-pf-manual-wrapper p { margin-bottom: 16px; }

        .ha-pf-manual-wrapper code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.9em;
            color: #ef4444;
        }

        .ha-pf-manual-wrapper .shortcode-block {
            background: #0f172a;
            color: #7dd3fc;
            padding: 14px 20px;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1rem;
            margin: 16px 0;
            letter-spacing: 0.5px;
        }

        .ha-pf-manual-wrapper .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .ha-pf-manual-wrapper .badge-new       { background: #dcfce7; color: #166534; }
        .ha-pf-manual-wrapper .badge-pro       { background: #e0f2fe; color: #0369a1; }
        .ha-pf-manual-wrapper .badge-important { background: #fee2e2; color: #991b1b; }

        .ha-pf-manual-wrapper ul,
        .ha-pf-manual-wrapper ol { padding-left: 20px; margin-bottom: 20px; }

        .ha-pf-manual-wrapper li { margin-bottom: 10px; }

        .ha-pf-manual-wrapper .tip {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 8px;
            margin: 24px 0;
            font-size: 0.95rem;
        }

        .ha-pf-manual-wrapper .warning {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 20px;
            border-radius: 8px;
            margin: 24px 0;
            font-size: 0.95rem;
        }

        .ha-pf-manual-wrapper .info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 8px;
            margin: 24px 0;
            font-size: 0.95rem;
        }

        .ha-pf-manual-wrapper .feature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 20px;
        }

        .ha-pf-manual-wrapper .feature-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .ha-pf-manual-wrapper table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 0.9rem;
        }

        .ha-pf-manual-wrapper th {
            background: #f1f5f9;
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            color: #374151;
        }

        .ha-pf-manual-wrapper td {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .ha-pf-manual-wrapper tr:nth-child(even) td { background: #f8fafc; }

        @media (max-width: 640px) {
            .ha-pf-manual-wrapper .feature-grid { grid-template-columns: 1fr; }
            .ha-pf-manual-wrapper h1 { font-size: 2rem; }
            .ha-pf-manual-wrapper table { font-size: 0.8rem; }
        }

        .ha-pf-manual-wrapper footer {
            text-align: center;
            padding: 40px;
            color: var(--ha-pf-text-muted);
            font-size: 0.9rem;
        }

        .ha-pf-manual-wrapper .manual-nav {
            position: sticky;
            top: 20px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            padding: 12px 20px;
            border-radius: 40px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 40px;
            border: 1px solid var(--ha-pf-border);
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .ha-pf-manual-wrapper .manual-nav a {
            text-decoration: none;
            color: var(--ha-pf-text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .ha-pf-manual-wrapper .manual-nav a:hover {
            color: var(--ha-pf-primary);
            transform: translateY(-1px);
        }

        .ha-pf-manual-wrapper .github-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #24292f;
            color: white !important;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            margin-top: 15px;
        }

        .ha-pf-manual-wrapper .github-link:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .ha-pf-manual-wrapper .glossary {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px 20px;
            margin-top: 20px;
        }

        .ha-pf-manual-wrapper .glossary dt { font-weight: 700; color: var(--ha-pf-primary); }
        .ha-pf-manual-wrapper .glossary dd { margin: 0 0 15px; }

        .ha-pf-manual-wrapper .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: var(--ha-pf-primary);
            color: white;
            border-radius: 50%;
            font-weight: 700;
            font-size: 14px;
            margin-right: 10px;
        }

        .ha-pf-manual-wrapper .ev-field-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .ha-pf-manual-wrapper .ev-field-icon {
            font-size: 1.4rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .ha-pf-manual-wrapper .ev-field-name {
            font-weight: 700;
            color: var(--ha-pf-text-main);
        }

        .ha-pf-manual-wrapper .ev-field-desc {
            color: var(--ha-pf-text-muted);
            font-size: 0.9rem;
            margin-top: 2px;
        }
    </style>

    <header>
        <h1>⚡ HA Powerflow HUD</h1>
        <p class="subtitle">The definitive guide to visualizing your energy ecosystem.</p>
        <a href="https://github.com/chris2172/ha-powerflow" target="_blank" class="github-link">
            <svg height="20" width="20" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
            Download on GitHub
        </a>
    </header>

    <nav class="manual-nav">
        <a href="#welcome">Welcome</a>
        <a href="#setup">Setup</a>
        <a href="#modules">Sensors</a>
        <a href="#pricing">Smart Pricing</a>
        <a href="#ev">EV Charging</a>
        <a href="#visuals">Design</a>
        <a href="#pro">Advanced</a>
    </nav>

    <!-- ── Welcome ──────────────────────────────────────────────────────── -->
    <section id="welcome">
        <div class="card">
            <h2>👋 Welcome to HA Powerflow</h2>
            <p>The <strong>HA Powerflow HUD</strong> is a high-performance, real-time visualisation tool for your WordPress site. It connects directly to your <strong>Home Assistant</strong> instance to display live energy flows (Solar, Battery, Grid, and House) in a beautiful, high-tech interface.</p>
            <div class="tip">
                <strong>New to Home Assistant?</strong> Don't worry. This guide will walk you through exactly what you need to copy and paste to get your data flowing.
            </div>
            <h3>Core Concepts</h3>
            <dl class="glossary">
                <dt>HUD</dt>
                <dd>Heads-Up Display. The visual interface that shows your data.</dd>
                <dt>Entity</dt>
                <dd>A specific sensor in Home Assistant (e.g. your solar production or battery percentage).</dd>
                <dt>Refresh Rate</dt>
                <dd>How often (in seconds) the widget asks Home Assistant for new data. 5–10 seconds is usually ideal.</dd>
                <dt>Session</dt>
                <dd>A complete EV charging event — from plug-in to disconnect — automatically recorded by the plugin.</dd>
            </dl>
        </div>
    </section>

    <!-- ── Setup ────────────────────────────────────────────────────────── -->
    <section id="setup">
        <div class="card">
            <h2>🔌 1. Connecting to Home Assistant</h2>
            <p>The plugin needs two things: the <strong>URL</strong> of your HA server and a <strong>Security Token</strong>.</p>

            <h3>Step A: The URL</h3>
            <ul>
                <li>Local access: <code>http://192.168.1.50:8123</code></li>
                <li>Nabu Casa: <code>https://your-unique-id.ui.nabu.casa</code></li>
            </ul>

            <h3>Step B: The Access Token <span class="badge badge-important">Vital</span></h3>
            <ol>
                <li>Open your Home Assistant dashboard.</li>
                <li>Click your <strong>Profile Name</strong> (bottom of the sidebar).</li>
                <li>Scroll to <strong>Long-Lived Access Tokens</strong> and click <strong>CREATE TOKEN</strong>.</li>
                <li>Name it "WordPress Site" and <strong>copy it immediately</strong> — you will only see it once.</li>
            </ol>

            <div class="warning">
                <strong>Security Note:</strong> Your token is like a key to your home. Never share it or post it in screenshots.
            </div>

            <h3>Step C: Test the Connection</h3>
            <p>Paste the URL and Token into the plugin settings and click <strong>Test Connection</strong>. A green success message means you are ready to configure your sensors.</p>
        </div>
    </section>

    <!-- ── Modules ──────────────────────────────────────────────────────── -->
    <section id="modules">
        <div class="card">
            <h2>🛰️ 2. Setting Up Your Sensors</h2>
            <p>Tell the plugin which Home Assistant entities represent your power sources and consumers.</p>

            <h3>The Core Four</h3>
            <ol>
                <li><strong>Grid Power:</strong> Positive = importing from grid, negative = exporting solar.</li>
                <li><strong>House Power:</strong> Real-time load/demand of your home.</li>
                <li><strong>Solar Power:</strong> Current output of your solar panels.</li>
                <li><strong>Battery SOC:</strong> State of charge percentage (0–100%).</li>
            </ol>

            <div class="tip">
                <strong>Smart Discovery:</strong> Click <strong>Scan for Entities</strong> in the Sensors tab. The plugin will search your HA instance and suggest matching sensors automatically.
            </div>

            <h3>🔋 Battery Intelligence <span class="badge badge-pro">Pro</span></h3>
            <p>The HUD can predict exactly how long your battery will last or how soon it will be full. To enable this, enter your battery's <strong>Capacity (kWh)</strong> and <strong>Minimum State of Charge (%)</strong> in the Sensors tab.</p>
            <ul>
                <li><strong>Time-to-Empty:</strong> Shown when your house is discharging the battery (e.g. <code>4h 20m</code> remaining).</li>
                <li><strong>Time-to-Full:</strong> Shown when solar is charging the battery.</li>
            </ul>

            <h3>☀️ Solar Forecasting</h3>
            <p>Link your <strong>Solcast</strong> or <strong>Forecast.Solar</strong> entity to see a progress ring around your PV icon. The ring fills as you reach your daily predicted generation, with the total forecast displayed clearly (e.g. <code>18.5 kWh Forecast</code>).</p>

            <h3>Quick Modules</h3>
            <p>Use the toggles at the top of the settings page to enable or disable modules. If you don't have an EV or Heat Pump, turn them off to keep your HUD clean. Note that the <strong>EV History</strong> tab only appears when the EV module is enabled.</p>
        </div>
    </section>

    <!-- ── Smart Pricing ────────────────────────────────────────────────── -->
    <section id="pricing">
        <div class="card">
            <h2>📉 3. Smart Pricing & Grid Logic <span class="badge badge-new">New</span></h2>
            <p>If you use a smart tariff like <strong>Octopus Agile</strong>, the HUD can dynamically react to real-time electricity prices.</p>

            <h3>Price-Driven Flow</h3>
            <p>By entering your <strong>Grid Price Import</strong> entity, the grid flow lines will change color automatically:</p>
            <ul>
                <li><strong style="color:#2ecc71">🟢 Green Flow:</strong> When the price is below your "Cheap Energy" threshold (e.g. 10p). Great for free or plunge pricing events!</li>
                <li><strong style="color:#ef4444">🔴 Red Flow:</strong> When the price exceeds your "Peak Energy" threshold (e.g. 35p). A visual warning to avoid heavy usage.</li>
            </ul>
            <p>Configure these thresholds under <strong>Settings → Sensors → Grid Pricing</strong>.</p>
        </div>
    </section>

    <!-- ── EV ───────────────────────────────────────────────────────────── -->
    <section id="ev">
        <div class="card">
            <h2>🚗 4. EV Charging & User Tracking</h2>
            <p>The EV module tracks every charging session automatically, calculating costs and efficiency whilst you charge.</p>

            <div class="info">
                <strong>WordPress Integration:</strong> Charging sessions are linked directly to your site's <strong>WordPress User</strong> accounts. Simply pick a user from the dropdown in the EV History table to attribute their usage.
            </div>

            <div class="ev-field-row">
                <div class="ev-field-icon">⚡</div>
                <div>
                    <div class="ev-field-name">Charge Added</div>
                    <div class="ev-field-desc">Running energy total for the session in kWh. Displayed as e.g. <code>12.4 kWh</code>.</div>
                </div>
            </div>
            <div class="ev-field-row">
                <div class="ev-field-icon">🔌</div>
                <div>
                    <div class="ev-field-name">Plug Status</div>
                    <div class="ev-field-desc">Live text state from your charger (e.g. <em>Charging</em>, <em>Eco+</em>, <em>EV Disconnected</em>). This field also drives automatic session tracking.</div>
                </div>
            </div>
            <div class="ev-field-row" style="border-bottom:none;">
                <div class="ev-field-icon">💷</div>
                <div>
                    <div class="ev-field-name">Co Charger Cost</div>
                    <div class="ev-field-desc">Cost rate per kWh from your charger sensor. Displayed using your Currency Symbol (e.g. <code>£0.25</code>). Also used to calculate total session cost.</div>
                </div>
            </div>

            <h3>EV Charge Summary Shortcode</h3>
            <p>Place this shortcode on any page to show a full history of your charging sessions:</p>
            <div class="shortcode-block">EV_CHARGE_SUMMARY <span style="font-size:0.75em;opacity:0.7;">(shortcode)</span></div>

            <p>To auto-refresh the page during an active session, add the <code>refresh</code> attribute with the number of seconds:</p>
            <div class="shortcode-block">EV_CHARGE_SUMMARY refresh=60 <span style="font-size:0.75em;opacity:0.7;">(shortcode)</span></div>

            <div class="feature-grid">
                <div class="feature-item">
                    <strong>🟢 Live Session Banner</strong><br>
                    Pulsing indicator with a running elapsed timer whilst charging is active.
                </div>
                <div class="feature-item">
                    <strong>📊 Session Stats</strong><br>
                    Duration, energy added, total cost, average/peak rates, and Solar % contribution.
                </div>
            </div>

            <h3>EV History Tab (Admin)</h3>
            <ul>
                <li><strong>Summary stats</strong> — total sessions, energy, and costs recorded.</li>
                <li><strong>User Assignment</strong> — link sessions to WordPress accounts via dropdown.</li>
                <li><strong>Export CSV</strong> — download a dated <code>.csv</code> for accounting or reimbursement.</li>
                <li><strong>Clear All History</strong> — clean up completed sessions while preserving active ones.</li>
            </ul>
        </div>
    </section>

    <!-- ── Design ───────────────────────────────────────────────────────── -->
    <section id="visuals">
        <div class="card">
            <h2>🎨 5. Design & Appearance</h2>
            <p>Make the HUD yours. The display is split into <strong>Labels</strong> (text) and <strong>Lines</strong> (the animated flow).</p>

            <h3>The Live Preview</h3>
            <p>Toggle <strong>Live Preview</strong> in the admin settings to see your changes in real time. Colour adjustments, module visibility, and label positions all update instantly without saving.</p>

            <h3>Custom Labels</h3>
            <p>Set independent colours for the <strong>Title</strong>, <strong>Power</strong>, and <strong>Energy</strong> text to keep them legible over any background image. You can also adjust the <strong>Font Size (px)</strong> for additional HUD sensors individually.</p>
        </div>
    </section>

    <!-- ── Advanced ─────────────────────────────────────────────────────── -->
    <section id="pro">
        <div class="card">
            <h2>🚀 6. Advanced HUD Mastery</h2>

            <h3>🎯 Drag & Drop Positioning</h3>
            <p>Enable <strong>🐛 Debug Mode</strong> at the bottom of the settings page, then click and drag any entity label or module icon in the Live Preview. Coordinates update automatically when you drop.</p>

            <h3>Additional HUD Entities</h3>
            <p>Want to show your pool temperature or greenhouse humidity? Use the <strong>Additional HUD Entities</strong> section under the Sensors tab. Add unlimited extra sensors with custom labels and place them anywhere on the canvas. Each sensor can have a custom font size to fit your layout perfectly.</p>

            <h3>🛠️ Maintenance & Health</h3>
            <p>Open the <strong>Maintenance</strong> tab to monitor your plugin's performance:</p>
            <ul>
                <li><strong>Latency:</strong> How fast Home Assistant is responding.</li>
                <li><strong>Success Rate:</strong> Percentage of successful data checks.</li>
                <li><strong>Last Seen:</strong> The exact time of the last successful data poll.</li>
                <li><strong>Snapshots:</strong> Automatically created before every save—restore any previous configuration with one click.</li>
            </ul>

            <h3>🗓️ EV Booking Calendar <span class="badge badge-new">New</span></h3>
            <p>Coordinate charging with other users using the simple 7-day booking tool. Any logged-in user can reserve a 30-minute slot.</p>
            <div class="shortcode-block">[ev_booking_calendar] <span style="font-size:0.75em;opacity:0.7;">(shortcode)</span></div>
            <ul>
                <li><strong>Privacy:</strong> Regular users only see if a slot is "Booked".</li>
                <li><strong>Admin View:</strong> Administrators see the name of the user who booked each slot and can cancel any session.</li>
                <li><strong>Interactive:</strong> Click any available slot to book instantly.</li>
                <li><strong>Estimates:</strong> Real-time cost forecasting based on grid price, markup ranges, and specialized support for <strong>Intelligent Octopus Go</strong> (split peak/off-peak rates).</li>
            </ul>

            <h3>⚡ User Dashboard <span class="badge badge-new">New</span></h3>
            <p>Users can manage their upcoming charges and sync them to their personal devices.</p>
            <div class="shortcode-block">[my_ev_bookings] <span style="font-size:0.75em;opacity:0.7;">(shortcode)</span></div>
            <ul>
                <li><strong>Dynamic Cards:</strong> View date, time, and session costs at a glance.</li>
                <li><strong>iCal Sync:</strong> Export any booking to Google, Apple, or Outlook calendars.</li>
                <li><strong>Self-Service:</strong> Cancel upcoming sessions directly from the dashboard.</li>
                <li><strong>Automation:</strong> Receive instant email confirmations and reminders.</li>
            </ul>

            <h3>Shortcode Reference</h3>
            <table>
                <thead>
                    <tr><th>Shortcode</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[ha_powerflow]</code></td>
                        <td>The main live power flow widget.</td>
                    </tr>
                    <tr>
                        <td><code>[ev_charge_summary]</code></td>
                        <td>EV charging session history with charts and stats.</td>
                    </tr>
                    <tr>
                        <td><code>[ev_booking_calendar]</code></td>
                        <td>7-day EV charging reservation calendar.</td>
                    </tr>
                    <tr>
                        <td><code>[my_ev_bookings]</code></td>
                        <td>A user's personal dashboard for upcoming charging sessions.</td>
                    </tr>
                    <tr>
                        <td><code>[ha_powerflow_manual]</code></td>
                        <td>This comprehensive guide you are reading now!</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> HA Powerflow HUD — v<?php echo HA_POWERFLOW_VERSION; ?></p>
        <p><a href="https://github.com/chris2172/ha-powerflow" target="_blank" style="color: var(--ha-pf-text-muted); text-decoration: underline;">View on GitHub</a></p>
        <p>Created for energy enthusiasts. Enjoy your dashboard!</p>
    </footer>
</div>
