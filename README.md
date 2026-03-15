<div align="center">
  <img src="assets/images/icons/pwa-icon-192.png" alt="HA Powerflow Logo" width="120" height="120" />
  
  # ⚡ HA Powerflow for WordPress

  An elegant, live-animated Home Assistant Power Flow card embedded directly into your WordPress site. Show off your real-time solar generation, grid usage, battery storage, EV charging, and heat pump efficiency to the world.

  [![Version](https://img.shields.io/badge/version-2.2.0-blue.svg)](https://github.com/chris2172/ha-powerflow/releases)
  [![License](https://img.shields.io/badge/license-GPLv2-green.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
  [![Home Assistant](https://img.shields.io/badge/Home%20Assistant-Integration-41BDF5?logo=home-assistant)](https://www.home-assistant.io/)
</div>

---

## 📸 The Widget
Bring the iconic Home Assistant energy dashboard to your public blog or personal portfolio.

*(Place an animated GIF or screenshot of the widget here: `![Widget Demo](path/to/demo.gif)`)*

---

## 🚀 Features

- **Live Real-time Data**: Polls Home Assistant via the REST API to display current Watt/kW metrics and daily Energy (kWh) totals.
- **Dynamic Physics Animation**: Power lines glow, pulse, and speed up relative to the amount of power flowing through them.
- **Smart Entity Discovery**: The built-in Smart Discovery tool scans your Home Assistant instance and finds power/energy entities automatically.
- **Drag & Drop Customisation**: Use the Live Preview in the WordPress admin panel to click and drag entity labels and module icons to perfectly fit any background image.
- **Snapshot Backups**: The plugin automatically backs up your configuration settings, encrypted at rest.
- **Extensive Module Support**:
  - 🏠 Home (Load)
  - 🏙️ Grid (Import/Export)
  - ☀️ Solar (PV)
  - 🔋 Home Battery (Charge/Discharge & SOC)
  - 🚗 Electric Vehicle (Charging Power, SOC, and extended telemetry — see below)
  - ♨️ Heat Pump (Power, Energy, and COP Efficiency)
  - ☁️ Weather Integration

---

## 🚗 EV Module — Extended Telemetry

In addition to Power and SOC, the EV module supports four extra sensor fields, each with an individual **Visible** toggle to show or hide them independently on the front end:

| Field | Type | Description |
|---|---|---|
| **Charge Added** | Energy (kWh) | Running total of energy added in the current session |
| **Plug Status** | Text | Live status from your charger (e.g. Charging, Eco+, Disconnected) |
| **Charge Mode** | Text | Active charging mode (e.g. Smart, Boost) |
| **Co Charger Cost** | Monetary | Cost rate from your charger sensor |

Configure these under **Settings → HA Powerflow → Modules → EV**.

### Currency Symbol

A global **Currency Symbol** (e.g. `£`, `€`, `$`) is set under **Settings → HA Powerflow → Sensors → Defaults**. This symbol is used across all cost displays including Co Charger Cost and the EV Charge Summary shortcode.

---

## 📊 EV Charge Summary Shortcode

The plugin automatically records every EV charging session in the background whilst the `[ha_powerflow]` widget is active on any page. Sessions are stored in the WordPress database and can be reviewed at any time.

### Shortcode Usage

```text
[ev_charge_summary]
```

To auto-refresh the page during an active session:

```text
[ev_charge_summary refresh=60]
```

The `refresh` attribute accepts any number of seconds. The page only auto-refreshes whilst a **live session is in progress** — once the EV disconnects the refresh stops automatically.

### What is Displayed

The shortcode renders a full session summary including:

- **Live Session Banner** — pulsing indicator with a running elapsed timer whilst charging
- **Active Session Card** — live stats updated on every page refresh:
  - Duration, Energy Added, Total Cost
  - Average & Peak charge rate (kW)
  - Rate per kWh, data point count, start time
  - Dual-axis chart: kWh added (area line) + charge power in kW (bars), with running cost shown in the tooltip
- **Previous Session** — the most recent completed session is always shown below the active card (or on its own when no session is in progress), so you always have a reference point
- **Session History** — all older completed sessions in a collapsible list, each expandable to reveal full stats and chart

### Session Logic

| Plug Status received | Action |
|---|---|
| Any value except Disconnected | Session continues (Charging, Eco+, Boost, Waiting, etc. all count as the same session) |
| Contains "Disconnected" | Session ends and is marked as completed |
| `unavailable`, `unknown`, `N/A`, empty | Ignored — never starts or ends a session |

**Total Cost** is calculated as `Charge Added (kWh) × Co Charger Cost (rate)`.

> **Note:** Session logging only occurs while a page containing `[ha_powerflow]` is open in a browser. The `[ev_charge_summary]` shortcode displays stored data only — it does not poll Home Assistant itself.

---

## 🗂️ EV History — Admin Tab

When the EV module is enabled, an **⚡ EV History** tab appears in the plugin settings. It provides:

- **Summary stats** — total sessions, total energy, total cost across all recorded sessions, and a live indicator if a session is currently active
- **Session table** — date, time range, duration, kWh, cost, avg/peak rates, data point count, and status badge per session
- **Per-session delete** — remove any individual completed session with a confirmation prompt
- **Clear All History** — wipe all completed sessions while preserving any currently active session
- **Export CSV** — download the full session table as a dated `.csv` file

> The EV History tab is hidden automatically when the EV module is disabled in Quick Modules, and appears immediately when it is re-enabled — no page reload required.

---

## 🛠️ Installation

1. Download the latest release `.zip` from the [Releases](https://github.com/chris2172/ha-powerflow/releases) page.
2. In your WordPress Admin dashboard, navigate to **Plugins → Add New → Upload Plugin**.
3. Upload the `.zip` file and click **Install Now**.
4. Click **Activate Plugin**.

---

## 🔌 Connecting to Home Assistant

Your HA instance must be publicly accessible (e.g. via Nabu Casa or a reverse proxy).

### 1. Generate a Long-Lived Access Token
1. Open your Home Assistant dashboard.
2. Click your **User Profile** (bottom left).
3. Select the **Security** tab.
4. Scroll to **Long-Lived Access Tokens** and click **Create Token**.
5. Name it `WordPress Powerflow` and copy it immediately (it is not shown again).

### 2. Configure the Plugin
1. Go to **WordPress Admin → ⚡ HA Powerflow**.
2. Paste your HA Server URL (include `http://` or `https://` and port if needed).
3. Paste the Long-Lived Access Token.
4. Click **Test Connection**.

---

## 🎨 Adding to your Webpage

### Main Power Flow Widget

```text
[ha_powerflow]
```

### EV Charge Summary

```text
[ev_charge_summary]
[ev_charge_summary refresh=60]
```

Add either shortcode to any WordPress post, page, or widget area.

### Drag & Drop Interface

In the HA Powerflow settings, use the Live Preview window to drag modules to any position. Coordinates update in real time, and a 🎯 coordinate picker is available for precise placement.

---

## 🔐 Security

- Snapshot configuration backups are encrypted using `openssl_aes-256-cbc`. Even if a file is exposed, your Home Assistant Access Token is secured by your WordPress Salts.
- All admin actions (delete session, clear history, snapshot management) are protected by WordPress nonces and `manage_options` capability checks.
- Public REST endpoints use IP-based rate limiting (max 100–120 requests per minute) so the widget works correctly for non-logged-in visitors.

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome. Check the [issues page](https://github.com/chris2172/ha-powerflow/issues).

## 📝 License

This project is licensed under the GPL-2.0 License.
