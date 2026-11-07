---
paths:
    - 'resources/js/**'
---

# Js

## Use Guichet and Caisse as the KORA visual reference

For new or revised KORA OPS screens, match the production Guichet and Caisse UI (sale.tsx, cash.tsx and their components): Geist typography, gold primary actions, moderately rounded controls and simple bordered panels. Storybook is a component verification surface, not the authority for a separate visual identity. General design skills must not override this explicit product direction.

## Respect KORA OPS gutters and dashboard color language
Before adding or revising an operations page, follow the existing shell’s content gutters and page rhythm; do not add a second horizontal padding layer inside the page. Reuse the home dashboard’s colored KPI card tones and established KORA panel styling so pages feel part of the same product. Compare with a nearby production page before settling the layout.

## Form fields: icons, required markers, and phone input
For every form field, put a context-appropriate icon beside its visible label. Required fields must use the native required semantics and show a red asterisk beside the label; optional fields must not show an asterisk. For phone numbers, use PhoneInput from react-phone-number-input with French labels and the relevant country as default; do not use native type="tel" inputs.

## Match the production table and filter layout
For screens with data tables, follow the established KORA OPS pattern used on the promo code list: keep search and filters in a separate section above the table, not inside the table card. Keep the table card focused on column headers, rows, pagination, and empty state; do not add a redundant title/header block inside it. Before styling or changing a table, inspect the promo code list and another production table page, then reuse their spacing, borders, and row treatment.

## Upload fields need drop, preview, and progress
Whenever adding a file-upload field, provide a keyboard-accessible drag-and-drop area and file picker, preview selected files with remove controls, and display an upload progress bar during submission. Preserve existing type/size limits and show validation errors.

## Rebuild after frontend changes
After every frontend code change, run `npm run build` before finishing and confirm it completes successfully. If it fails, fix the frontend build errors before reporting completion.
