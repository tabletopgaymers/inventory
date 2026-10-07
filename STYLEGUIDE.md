# Living presentation guide

The local application uses `resources/views/layout.blade.php`, anonymous Blade
components in `resources/views/components/app/` and `public/app.css`.
Design Phase B follows Chameleon's D-224 component contract and the approved
D-222/D-223 frame, purple palette and system typography. This is a local candidate;
no remote design publication is claimed. Do not copy prototype behavior or fixtures.

- Main navigation: Inventory (with Location Counts child), Catalog, Purchases, Relocations, Events, Users. Preserve existing destination access; account actions remain in the header disclosure. Current destinations use `aria-current="page"` and purple styling. Inventory also exposes Browse/Location Counts section links.
- Page headings pair the H1 with an optional status. Unfinished pages show a yellow **Planned** badge and a readable completion phase, purpose, intended actions and related destinations. They contain no simulated transaction controls.
- Purple identifies navigation and primary actions; Cancel/Discard remains red. Forms retain existing validation and notices.
- Desktop uses the approved 14rem sidebar and flexible working area. Below 64rem a native modal drawer provides navigation, with close/Escape, contained focus and focus return. The sidebar remains available without supported dialog/JavaScript. Account disclosure supports keyboard, Escape and outside dismissal. Tables use a named, focusable horizontal scroll region; forms and actions wrap on narrow screens.
- Optional workflow previews, when available, open in a separate tab with an explicit sample-data/no-application-effect explanation. Working pages do not carry preview links.
- Print clears page backgrounds and hides the working shell/actions. Dedicated count/packing worksheets load the unchanged pre-adoption `public/worksheet-base.css` plus their original print CSS, preserving paper geometry, repeated headers, Findings and blank Sent. They do not load application Td styling. Keyboard focus retains the shared visible outline.

## Stable local presentation boundary

Build application CSS with `npm run build:app` in `resources/design/phase-a/`, using
the installed pinned Tailwind/CLI 4.3.3 and daisyUI 5.7.47. `app-input.css` explicitly
scans working views and writes only `public/app.css`. Existing guide input/build,
guide outputs, dependency versions and lockfile remain separate and unchanged.

Components: `shell`, `navigation`, `page-heading`, `panel`, `action`, `field`,
`status-badge`, `table-region`. Internal Td utilities belong to components;
`app-*` semantic classes and `--app-*` tokens are the shared appearance boundary.
Page controls retain their names, validation, form routes, CSRF and business JS
identifiers. Shell-only behavior is in `public/app-shell.js`, with unique
`app-nav-open`, `app-nav-dialog`, `app-nav-close` hooks. No frontend runtime added.

Tokens map to the Td app theme: page #f5f5f7, surface white, border #d5d5df,
text #20212b, primary #5b21b6, muted #595965 and danger #991b1b. Use Segoe UI /
system-ui. Labels above controls, tabular numeric columns, restrained white panels.
Statuses carry explicit text: Draft/Request/Requested yellow;
Active/Ordered/Shipped/Receiving green; Received/Finalized/Complete purple;
Inactive/Archived/Cancelled neutral. Cancel/Discard/Archive controls remain red.
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
explicit lifecycle actions on Edit. Compact Catalog rows show tailored summaries
and Edit only. Read-only Details disclosures retain omitted descriptions/contact/
notes/status for ordinary viewers without requiring Edit access. Archive opens a
dedicated named confirmation screen; its explicit Archive submission retains
existing server confirmation/authorization checks.
Restore returns to Inactive; Activate is separate. Central retains its protected
active status. Shared green Active/neutral Inactive/Archived badges show lifecycle.
Collections have an All-categories default, visible single-select Category filter
and Apply; category links preselect this same filter. Collection links scope Items,
with both parent Category and Collection stated, plus a clear all-items return.
Browse controls query only on Search/Show All; pending changes retain displayed
results and CSV. Alphabetical collection checklists flow down three/two/one columns.
Storage and extra-source column choices are separate fieldsets; quantities stay
right aligned. The scrollable full result table fixes headings and item names,
with collection heading rows and a sticky bottom Download action. Narrow screens
retain the table inside horizontal scrolling. Optional item money displays two
decimals while stored exact precision and blank/zero remain unchanged.

## Request intake

Purchase/relocation indexes show persistent request statuses with immediate checkbox
alternatives. Draft/Request/Requested keep yellow badges; Cancelled uses existing
neutral archived styling. Request notes/details are escaped multiline plain text.
Keep Requested Items visibly separate from explicit catalog search and use current
source/destination projections. Preparation labels distinguish estimates and saved
fulfillment from actual orders/shipments. Packing worksheets keep a blank Sent
writing column and landscape print layout; no saved fulfillment becomes actual Sent.
