# Background Upload System

## Purpose
Allow WordPress admins to upload custom dashboard backgrounds stored in:


## Current Behaviour
- WordPress uploads the original file to `/uploads/YYYY/MM/`
- The plugin copies the file into `/uploads/ha-powerflow/`
- The settings page stores the custom background URL
- The preview updates instantly via AJAX

## Planned Enhancements
- Multiple background presets
- Remove/reset background buttons
- Automatic renaming (e.g., `dashboard-bg.png`)
- File validation (type, size, dimensions)
- Background selection UI
