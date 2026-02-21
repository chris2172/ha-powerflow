# Powerflow Line Positioning

## Purpose
Allow admins to reposition animated SVG powerflow lines to match custom backgrounds.

## Planned Features
- Drag‑and‑drop control points for SVG paths
- Live preview of animation direction
- Adjustable:
  - Start/end points
  - Curve tension
  - Line thickness
  - Animation speed
- Save custom geometry to plugin settings

## Technical Notes
- SVG paths stored as JSON in plugin options
- Editor mode overlays draggable control points
- Animation uses CSS motion paths or stroke‑dashoffset
