=== HA PowerFlow ===
Contributors: chris2172
Tags: home assistant, energy, dashboard, solar, power
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A real-time animated power-flow dashboard for WordPress, pulling live data from Home Assistant via a secure server-side proxy.

== Description ==

HA PowerFlow displays a live, animated power-flow diagram on any WordPress page or post. It connects to your Home Assistant installation every 5 seconds and shows current energy data as animated dots travelling along SVG paths overlaid on a custom background image.

**Key features:**

* Live data refreshed every 5 seconds from Home Assistant
* Animated flow dots whose direction and speed reflect live power levels
* Fully configurable background image, colours, label positions and flow paths
* Your Home Assistant token is AES-256 encrypted server-side and never sent to the browser
* Optional Solar, Battery and EV sections — enable only what you have
* Battery gauge widget: two-ring SVG gauge showing SOC and charge/discharge state
* EV gauge widget: same two-ring gauge for your electric vehicle's SOC
* Custom entity labels: add any Home Assistant entity to the dashboard with its own display name, unit, font size, position and visibility toggle
* Grid Energy Out automatically hidden when neither Solar nor Battery is enabled
* Configuration automatically backed up to YAML on every save (last 50 kept)
* Full config import/export including AES-256 encrypted token
* Built-in click-to-coordinate tool for positioning labels without guesswork

**Usage:**

1. Enter your Home Assistant URL and Long-Lived Access Token in Settings > HA PowerFlow.
2. Enable the features you need (Solar, Battery, EV) and fill in your entity IDs.
3. Add the shortcode `[ha_powerflow]` to any page or post.

**Security:**

All requests to Home Assistant are proxied through your WordPress server. Your token is stored AES-256 encrypted and is never exposed to the browser or included in page source.

== Installation ==

1. Upload the `ha-powerflow` folder to the `/wp-content/plugins/` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to **Settings > HA PowerFlow**.
4. Enter your Home Assistant URL (e.g. `https://homeassistant.local:8123`) and a Long-Lived Access Token.
5. Enable the features you need and fill in your Home Assistant entity IDs.
6. Add the shortcode `[ha_powerflow]` to any page or post.

== Frequently Asked Questions ==

= Does it work with Home Assistant Cloud (Nabu Casa)? =
Yes, as long as you provide a valid external URL and a Long-Lived Access Token.

= My Home Assistant uses a self-signed certificate. Will it work? =
Yes. Untick the "Verify SSL certificate" option in the Connection panel.

= Where do I get a Long-Lived Access Token? =
In Home Assistant, click your profile icon (bottom left), scroll to the bottom, and click "Create Token" under Long-Lived Access Tokens.

= Do I need to add my WordPress URL to Home Assistant's configuration.yaml? =
Usually not. Because the plugin fetches data server-to-server, CORS is not involved. If you see 403 errors, add your WordPress domain to `cors_allowed_origins` in Home Assistant's `configuration.yaml`.

= Where are my configuration backups? =
At `wp-content/uploads/ha-powerflow/config/`. Files are named `YYMMDD-hhmmss-config.yaml`. The 50 most recent are kept automatically.

= Is the background image included? =
Yes. A default background image is included in the plugin's `assets/` folder and is used automatically until you upload your own.

= What entities does Grid Energy Out require? =
Grid Energy Out is only shown when Solar or Battery is enabled. Without generation capability you have no export agreement, so the field is hidden to keep the UI uncluttered.

= What are Custom Entity Labels? =
Custom Entity Labels let you add any Home Assistant sensor to the dashboard — for example, battery temperature, inverter frequency, or house water temperature. Each custom entity has its own Display Name, Entity ID, Unit of Measurement, Font Size, Rotation, X/Y position and a Visible toggle. Only visible entries appear on the dashboard.

= What do the gauge widgets show? =
The Battery Gauge shows a two-ring SVG circle. The outer ring fills proportionally to the battery's state of charge (green above 20%, amber 10–20%, red below 10%). The inner circle turns green when the battery is charging and red when discharging. The EV Gauge works the same way for your electric vehicle.

== Screenshots ==

1. The live power-flow dashboard displayed on a WordPress page.
2. The HA PowerFlow settings page showing the connection and entity configuration.
3. Battery and EV gauge widgets on the dashboard.
4. The Custom Entity Labels panel in the settings page.

== Changelog ==

= 2.2.0 =
* Added Battery Gauge widget — two-ring SVG showing SOC and charge/discharge state
* Added EV SOC Gauge widget — same two-ring layout as battery gauge
* Added Custom Entity Labels — add any HA entity with display name, unit, font size, position and visibility control
* Added Font Size field to custom entities so secondary labels can be smaller than primary ones
* Grid Energy Out now hidden when neither Solar nor Battery is enabled
* Fixed config snapshot to cover 100% of registered options
* Fixed label/position/rotation defaults being ignored when a previous version saved 0 to the database
* Config YAML export and import now covers battery gauge, EV gauge and custom entities

= 2.1.0 =
* Added click-to-coordinate developer tool for positioning labels
* Added automatic YAML configuration backup on every save (AES-256 encrypted token)
* Redesigned settings page with improved visual layout
* Added Developer Tools and Uninstall preference cards

= 2.0.0 =
* Complete rewrite with full security audit
* Server-side proxy — HA token never sent to browser
* AES-256 token encryption in database
* All inputs sanitised on save; all outputs escaped on render
* Collapsible, icon-led settings panels
* Sticky save bar with unsaved-changes indicator

= 1.0.1 =
* Minor bug fixes

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.2.0 =
Adds battery and EV gauge widgets, custom entity labels, and fixes a regression affecting upgrades from v1.x where all positions defaulted to 0.

= 2.1.0 =
Adds automatic config backups and the click-to-coordinate positioning tool. Recommended upgrade for all users.

= 2.0.0 =
Major security rewrite. Upgrade strongly recommended.
