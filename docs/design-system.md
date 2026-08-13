# Design System: KORA OPS Web

## 1. Visual Theme & Atmosphere

KORA OPS Web is a premium operations cockpit for the Bus MVP. The interface is dense enough for repeated professional use, but calm and readable: navy command surfaces, mist backgrounds, crisp white cards, gold as the single action accent, and restrained motion. The product should feel like an operations room, not a marketing landing page.

Density: Daily App Balanced 6/10. Variance: Offset Operational 6/10. Motion: Fluid CSS 5/10.

## 2. Color Palette & Roles

- **KORA Navy** (#001D4E) - Primary command color, navigation, main headings, critical surfaces.
- **KORA Gold** (#F6B000) - Single accent for CTAs, active states, focus rings, selected records.
- **Digital Blue** (#0057B8) - Informational states, route progress, charts, subtle data emphasis.
- **Mist Surface** (#F4F6F8) - App background and secondary surfaces.
- **Graphite Ink** (#111827) - Primary text in light mode.
- **White Surface** (#FFFFFF) - Cards, tables, dialogs, inputs.
- **Night Canvas** (#0B1224) - Dark mode background.
- **Night Surface** (#121B30) - Dark mode cards and popovers.

No purple or neon palette is allowed. Gold remains the only accent.

## 3. Typography Rules

- **Display and Body:** Geist Sans. Use weight, color, and spacing for hierarchy. Avoid oversized headings inside dashboards.
- **Mono:** Geist Mono for operational numbers, codes, ticket references, timestamps, and compact metadata.
- **Line length:** Body copy should stay under 65 characters when possible.
- **Banned:** Inter for new KORA OPS UI, generic serif fonts, pure black (#000000), and negative letter spacing.

## 4. Component Stylings

- **Buttons:** shadcn/ui-compatible variants using `class-variance-authority`; primary maps to semantic `primary`, gold action intent maps through tokens, never hardcoded component-level colors.
- **Cards:** 18px radius by default through `--radius`; use borders and blue-tinted shadows only when hierarchy needs a framed surface.
- **Inputs:** Label above input, helper/error text below, gold focus ring via `--ring`, light and dark variables required.
- **Badges:** Compact, readable status indicators; avoid inventing status colors outside semantic variants.
- **Tables and Lists:** Dense rows, clear borders, primary text plus muted metadata; no raw Eloquent IDs or numeric backend IDs in UI examples.
- **Loaders:** Skeletons matching the expected layout; no generic circular loading patterns for page sections.

## 5. Layout Principles

- Use CSS Grid for dashboards, KPI boards, tables, and multi-column operational layouts.
- Mobile collapses to one column below 768px; no horizontal scroll.
- Compose business stories from `resources/js/components/ui/*`; do not create a second component library in Storybook.
- Keep components compatible with both light and dark mode, even when the client maquette only shows light mode.
- The MVP is Bus only. Do not add KORA Ride, Wallet, Colis, Driver, payroll, or full accounting screens without a ticket.

## 6. Motion & Interaction

- Animate with transform and opacity only.
- Use restrained hover elevation and active press feedback.
- Keep Storybook stories deterministic and performant; avoid CPU-heavy cinematic animation in foundational component stories.
- When an animated showcase is explicitly requested, include a pre-flight design plan and ensure no text overlap or horizontal overflow.

## 7. Anti-Patterns

- No emojis.
- No Inter in new UI.
- No pure black.
- No neon or outer glow effects.
- No purple/blue AI gradient aesthetic.
- No generic three-equal-card feature rows.
- No generic placeholder names such as John Doe or Acme.
- No fake precision claims such as 99.99%.
- No AI copywriting cliches such as "Elevate", "Seamless", "Unleash", or "Next-Gen".
- No raw numeric backend IDs in web/mobile/API-visible examples.
