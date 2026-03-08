<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
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

        .ha-pf-manual-wrapper * {
            box-sizing: border-box;
        }

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

        .ha-pf-manual-wrapper .card:hover {
            box-shadow: var(--ha-pf-shadow-lg);
        }

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

        .ha-pf-manual-wrapper p {
            margin-bottom: 16px;
        }

        .ha-pf-manual-wrapper code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.9em;
            color: #ef4444;
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

        .ha-pf-manual-wrapper .badge-new { background: #dcfce7; color: #166534; }
        .ha-pf-manual-wrapper .badge-pro { background: #e0f2fe; color: #0369a1; }
        .ha-pf-manual-wrapper .badge-important { background: #fee2e2; color: #991b1b; }

        .ha-pf-manual-wrapper ul, .ha-pf-manual-wrapper ol {
            padding-left: 20px;
            margin-bottom: 20px;
        }

        .ha-pf-manual-wrapper li {
            margin-bottom: 10px;
        }

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

        .ha-pf-manual-wrapper .feature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        
        .ha-pf-manual-wrapper .feature-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        @media (max-width: 640px) {
            .ha-pf-manual-wrapper .feature-grid { grid-template-columns: 1fr; }
            .ha-pf-manual-wrapper h1 { font-size: 2rem; }
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
            padding: 12px;
            border-radius: 40px;
            display: flex;
            justify-content: center;
            gap: 24px;
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
        
        .ha-pf-manual-wrapper .glossary dt {
            font-weight: 700;
            color: var(--ha-pf-primary);
        }
        
        .ha-pf-manual-wrapper .glossary dd {
            margin: 0;
            margin-bottom: 15px;
        }

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
        <a href="#modules">Modules</a>
        <a href="#visuals">Design</a>
        <a href="#pro">Advance</a>
    </nav>

    <section id="welcome">
        <div class="card">
            <h2>👋 Welcome to HA Powerflow</h2>
            <p>The <strong>HA Powerflow HUD</strong> is a high-performance, real-time visualization tool for your WordPress site. It connects directly to your <strong>Home Assistant</strong> instance to display live energy flows (Solar, Battery, Grid, and House) in a beautiful, high-tech interface.</p>
            <div class="tip">
                <strong>New to Home Assistant?</strong> Don't worry. This guide will walk you through exactly what you need to copy and paste to get your data flowing.
            </div>
            <h3>Core Concepts</h3>
            <dl class="glossary">
                <dt>HUD</dt>
                <dd>Heads-Up Display. The visual interface that shows your data.</dd>
                <dt>Entity</dt>
                <dd>A specific sensor in Home Assistant (e.g., your solar production or battery percentage).</dd>
                <dt>Refresh Rate</dt>
                <dd>How often (in seconds) the widget asks Home Assistant for new data. A setting of 5 or 10 seconds is usually perfect.</dd>
            </dl>
        </div>
    </section>

    <section id="setup">
        <div class="card">
            <h2>🔌 1. Connecting to Home Assistant</h2>
            <p>The plugin needs two things to see your data: the <strong>URL</strong> of your HA server and a <strong>Security Token</strong>.</p>
            
            <h3>Step A: The URL</h3>
            <p>This is the web address you use to log into Home Assistant. 
                <ul>
                    <li>If you are at home, it might look like <code>http://192.168.1.50:8123</code>.</li>
                    <li>If you use Nabu Casa, it might look like <code>https://your-unique-id.ui.nabu.casa</code>.</li>
                </ul>
            </p>

            <h3>Step B: The Access Token <span class="badge badge-important">Vital</span></h3>
            <p>To let WordPress access your data securely, you must generate a token:</p>
            <ol>
                <li>Open your Home Assistant dashboard.</li>
                <li>Click on your <strong>Profile Name</strong> (usually at the very bottom of the sidebar).</li>
                <li>Scroll all the way down to the <strong>Long-Lived Access Tokens</strong> section.</li>
                <li>Click <strong>CREATE TOKEN</strong>. Give it a name like "WordPress Site".</li>
                <li><strong>Copy the token immediately!</strong> You will only see it once. It is a very long string of letters and numbers.</li>
            </ol>

            <div class="warning">
                <strong>Security Note:</strong> Your token is like a key to your house. Never share it, and never post it in public screenshots.
            </div>

            <h3>Step C: The Test</h3>
            <p>Paste the URL and Token into the plugin settings and click <strong>Test Connection</strong>. If it turns green and says "Success", you are ready to configure your sensors!</p>
        </div>
    </section>

    <section id="modules">
        <div class="card">
            <h2>🛰️ 2. Setting Up Your Sensors</h2>
            <p>Now that you're connected, you need to tell the plugin which "Entities" (sensors) represent your power.</p>
            
            <h3>The Core Four</h3>
            <p>Most energy systems rely on these primary sensors:</p>
            <ol>
                <li><strong>Grid Power:</strong> This sensor should show a positive number when you are taking power from the grid, and a negative number when you are exporting (selling back) solar power.</li>
                <li><strong>House Power:</strong> The real-time "Load" or demand of your home.</li>
                <li><strong>Solar Power:</strong> The current output of your solar panels.</li>
                <li><strong>Battery SOC:</strong> The state of charge percentage (0-100%).</li>
            </ol>

            <div class="tip">
                <strong>Smart Discovery:</strong> In the settings page, click the <strong>Scan for Entities</strong> button. The plugin will search your HA instance and try to find these sensors for you automatically!
            </div>

            <h3>Quick Modules</h3>
            <p>Enable or disable specific components using the toggles at the top of the settings page. If you don't have an Electric Vehicle (EV) or a Heat Pump, simply turn them off to keep your HUD clean.</p>
        </div>
    </section>

    <section id="visuals">
        <div class="card">
            <h2>🎨 3. Design & Appearance</h2>
            <p>This is where you make the HUD yours. The HUD is split into <strong>Labels</strong> (text) and <strong>Lines</strong> (the animated flow).</p>
            
            <h3>Smart Palette <span class="badge badge-new">New</span></h3>
            <p>If you aren't sure which colors to use, leave the "Line Color" fields blank. The plugin will use its <strong>Smart Logic</strong>:
                <div class="feature-grid">
                    <div class="feature-item">
                        <strong style="color:#2ecc71">Green Flows</strong><br>
                        Exporting power to the grid or charging from solar.
                    </div>
                    <div class="feature-item">
                        <strong style="color:#1a73e8">Blue Flows</strong><br>
                        Importing power from the grid or normal consumption.
                    </div>
                    <div class="feature-item">
                        <strong style="color:#f1c40f">Gold Flows</strong><br>
                        Solar generation is active.
                    </div>
                    <div class="feature-item">
                        <strong style="color:#e67e22">Orange Flows</strong><br>
                        Battery is discharging to sustain the home.
                    </div>
                </div>
            </p>

            <h3>The Live Preview</h3>
            <p>In the admin settings, toggle the <strong>Live Preview</strong> to see exactly how your choices look. You can change colors and see the glow update instantly without refreshing the page.</p>

            <h3>Custom Labels</h3>
            <p>You can change the color of the <strong>Title</strong> (e.g., "SOLAR"), the <strong>Power</strong> (e.g., "4.2 kW"), and the <strong>Energy</strong> (e.g., "12.5 kWh") independently to ensure they are legible over your background image.</p>
        </div>
    </section>

    <section id="pro">
        <div class="card">
            <h2>🚀 4. Advanced HUD Mastery</h2>
            <p>Once you are comfortable, you can use these tools to create a truly professional layout.</p>
            
            <h3>🎯 Drag & Drop Positioning <span class="badge badge-new">New</span></h3>
            <p>Don't guess the X and Y numbers! Positioning entities is now completely interactive:
                <ol>
                    <li>Turn on <strong>🐛 Debug Mode</strong> at the bottom of the settings page.</li>
                    <li>Look at your Live Preview canvas.</li>
                    <li>Simply <strong>click and drag</strong> any entity label, module icon, or custom sensor exactly where you want it.</li>
                    <li>The coordinate numbers in the settings panel will update automatically when you drop it!</li>
                </ol>
            </p>

            <h3>Additional Entities</h3>
            <p>Want to show your swimming pool temperature or the humidity in your greenhouse? Use the <strong>"Additional HUD Entities"</strong> section. You can add unlimited extra sensors and place them anywhere on the screen.</p>

            <h3>Snapshots & Encrypted Backups</h3>
            <p>Every time you click "Save", the plugin creates an automatic <strong>Snapshot</strong>. If you make a mistake or don't like a new layout, go to the "Maintenance" tab, pick a previous time, and click <strong>Restore</strong>.</p>
            <div class="tip">
                <strong>🔒 Security First:</strong> Your Home Assistant Access Token and URL are automatically strongly encrypted using <code>AES-256</code> before being saved to any snapshot backup file. 
            </div>
        </div>
    </section>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> HA Powerflow HUD — v<?php echo HA_POWERFLOW_VERSION; ?></p>
        <p><a href="https://github.com/chris2172/ha-powerflow" target="_blank" style="color: var(--ha-pf-text-muted); text-decoration: underline;">View on GitHub</a></p>
        <p>Created for energy enthusiasts. Enjoy your new dashboard!</p>
    </footer>
</div>
