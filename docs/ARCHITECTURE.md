# Plugin Architecture

## File Structure
- `ha-powerflow.php`  
  Main plugin loader, activation hooks, includes.

- `admin/`  
  Settings page, admin UI, scripts.

- `includes/`  
  Shortcodes, AJAX handlers, settings registration.

- `assets/`  
  CSS, JS, SVGs, icons.

## Data Flow
1. Settings page loads plugin options
2. Admin selects background → AJAX copies file → updates option
3. Shortcode renders dashboard using:
   - Background image
   - Home Assistant API data
   - SVG powerflow animations

## Home Assistant Integration
- Uses REST API
- Token stored in WordPress options
- Data fetched client‑side or server‑side depending on mode
