=== HA PowerFlow ===
Contributors: chris2172
Tags: home assistant, energy, dashboard, solar, power
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A real-time animated power-flow dashboard for WordPress, pulling live data from Home Assistant via a secure server-side proxy.

== Description ==

HA PowerFlow displays a real-time animated power-flow diagram on any WordPress page or post. It connects to your Home Assistant installation and shows live energy data — solar generation, grid import/export, home consumption, battery charging/discharging, and EV charging — as animated dots travelling along SVG paths overlaid on a custom background image.

**Key features:**

* Live data refreshed every 5 seconds from Home Assistant
* Animated flow dots whose speed reflects power levels
* Fully configurable background image, colours, label positions and flow paths
* Your Home Assistant token is encrypted server-side using AES-256 and never sent to the browser
* Optional Solar, Battery and EV sections toggled on or off
* Configuration automatically backed up to YAML on every save (last 50 kept)
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

== Screenshots ==

1. The live power-flow dashboard displayed on a WordPress page.
2. The HA PowerFlow settings page showing the connection and entity configuration.

== Changelog ==

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

= 2.1.0 =
Adds automatic config backups and the click-to-coordinate positioning tool. Recommended upgrade for all users.

= 2.0.0 =
Major security rewrite. Upgrade strongly recommended.

= 2.1.0 =
Add Battery Gauge.
Add location selector to Settings page.
Add Visibility checkbox to settings page.