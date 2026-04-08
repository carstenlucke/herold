# Neon Tokyo — Retro-Futurism

## North Star: "Electric Nightscape"
Cyberpunk-inspired. Moody dark backgrounds with neon accents that glow. Futuristic, immersive, and high-energy.

## Colors
- **Primary (`#ff2d78`):** Hot pink neon — CTAs, focus states, active elements.
- **Secondary (`#00ffcc`):** Cyan neon — links, secondary actions, data highlights.
- **Tertiary (`#ffe04a`):** Warm neon yellow — badges, warnings, emphasis.
- **Background (`#0a0a12`):** Near-black with blue undertone.
- Never use neon colors for large surface areas. They are accents only.

## Glow Effects (Core Pattern)
- **Neon glow:** `text-shadow: 0 0 8px currentColor` on accent text.
- **Button glow:** `box-shadow: 0 0 16px rgba(255, 45, 120, 0.4)` on hover.
- **Border glow:** `border: 1px solid rgba(255, 45, 120, 0.5)` with `box-shadow: inset 0 0 12px rgba(255, 45, 120, 0.1)`.
- Keep glows diffused (12-20px blur). Never harsh or tight.

## Typography
- **Headlines:** Sora — geometric and futuristic. Use bold weight.
- **Body:** Inter — reliable contrast against dark backgrounds.
- **Labels:** Space Grotesk — technical, monospaced feel.

## Elevation
- No traditional shadows. Use inner/outer glows with neon tint.
- Surface hierarchy via opacity: darkest base → lighter containers.
- Subtle scan-line or grid textures as background detail (CSS gradients).

## Components
- **Buttons:** Dark background, neon border, text glow on hover. No solid neon fill.
- **Cards:** `surface_container` with thin neon border at 30% opacity.
- **Inputs:** Dark fill, bottom neon border, glow on focus.

## Rules
- Maximum 2 neon accent colors per view. Use neutral text primarily.
- Neon on dark only — never on light backgrounds.
- Glows animate on interaction, not at rest.