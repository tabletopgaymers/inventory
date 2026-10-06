# Living presentation guide

The application uses `resources/views/layout.blade.php` and `public/app.css`.
This provisional guide accompanies the shared styles; do not copy demonstration assets.

- Main navigation: Users, Catalog, Inventory, Purchases, Relocations, Events. Account navigation remains separate. Current destinations use `aria-current="page"` with a thicker underline.
- Page headings pair the H1 with an optional status. Unfinished pages show a yellow **Planned** badge and a readable completion phase, purpose, intended actions and related destinations. They contain no simulated transaction controls.
- Purple identifies navigation and primary actions; Cancel/Discard remains red. Forms retain existing validation and notices.
- Desktop layout wraps navigation rather than hiding destinations. Narrow layouts retain usable links, wrap long account text and keep form inputs inside the available width. Tables use their own horizontal scroll container.
- Optional workflow previews, when available, open in a separate tab with an explicit sample-data/no-application-effect explanation. Working pages do not carry preview links.
- Print clears page backgrounds and hides header/actions. Keyboard focus retains the shared visible outline.
# Correction workflow components

The item page and immutable detail use right-aligned, tabular quantity/cost cells.
Edit Inventory keeps Set, Adjust and optional rationale on each storage row with
separate Current/New columns; Total Available uses non-focusable spans. On narrow
screens the table scrolls within its container, while page actions remain usable.
Use the shared primary button for Continue/Save/OK and discard link for Cancel.
Review/history retains Date, Description, signed Quantity and Unit Cost, with an
ordinary accessible description link as well as whole-row pointer navigation.
Native departure confirmation preserves the form when Cancel is selected.
