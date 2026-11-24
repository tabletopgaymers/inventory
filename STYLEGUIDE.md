# Style Guide

Open the [in-app Style Guide](https://tg-inventory-app.test/style-guide) for
current visual standards and live examples. The guide is available to signed-in
users on local and development installations.

- [Presentation](https://tg-inventory-app.test/style-guide/presentation) covers
  spacing, density, panels, tables, and status badges.
- [Forms](https://tg-inventory-app.test/style-guide/forms) covers fields and actions.
- [Notifications](https://tg-inventory-app.test/style-guide/notifications) covers
  messages, confirmation, and unsaved changes.
- [Workflow](https://tg-inventory-app.test/style-guide/workflow) and
  [Print](https://tg-inventory-app.test/style-guide/print) show workflow controls
  and printed layout.

## Where to make changes

| Area | Source |
| --- | --- |
| Page frame and navigation | [Page layout](resources/views/layout.blade.php), [application shell](resources/views/components/app/shell.blade.php), [navigation definitions](app/Support/AppNavigation.php) |
| Reusable markup | [Application components](resources/views/components/app), [workflow components](resources/views/components/workflow), [print components](resources/views/components/print) |
| Shared styles and settings | [CSS entry](resources/css/app.css), [active theme](resources/design/phase-a/app-input.css), [settings and breakpoints](resources/css/settings.css) |
| Shared behavior | [JavaScript entry](resources/js/app.js), [application shell behavior](resources/js/app-shell.js), [workflow modules](resources/js/workflow) |
| Print styling | [Shared print tables](resources/css/print-tables.css), [standalone print stylesheet](resources/css/print.css) |
| Style Guide itself | [Guide routes](routes/design-phase-a.php), [examples](resources/views/design/phase-a), [Guide styles](resources/css/style-guide.css) |

Start with the shared components and their examples. Update the relevant example
when changing a public component option or visual standard. Keep form names,
validation, authorization, and workflow behavior with the caller.

Edit Vite source files. Follow the [development instructions](README.md#frontend-assets)
to run Vite or rebuild the generated assets in `public/build`.
