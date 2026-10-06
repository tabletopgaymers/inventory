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

## Catalog and inventory search

Use separate Create/Edit forms with retained validation input, optional notes and
explicit lifecycle actions. Archive has a named confirmation checkbox; Restore
returns to Inactive. Shared green Active/neutral Archived badges show lifecycle.
Browse controls query only on Search/Show All; pending changes retain displayed
results and CSV. Alphabetical collection checklists flow down three/two/one columns.
Storage and extra-source column choices are separate fieldsets; quantities stay
right aligned. The scrollable full result table fixes headings and item names,
with collection heading rows and a sticky bottom Download action. Narrow screens
retain the table inside horizontal scrolling. Optional item money displays two
decimals while stored exact precision and blank/zero remain unchanged.
