# Design Phase A guide build

This nested package retains only the historical static evaluation build. The live application uses the root Vite build; run npm commands from the application root for normal UI development.

`app-input.css` remains the single source of the existing application theme and custom rules, imported by `resources/css/app.css`. The root Vite build owns its compilation. The previous nested `build:app` command is retired; do not regenerate or edit public/app.css. That older compiled file remains temporarily for rollback/reference and is no longer loaded by the application shell.

From this directory, using Node.js 20 or newer and npm:

```powershell
npm ci
npm run build
```

Evaluation input: input.css. Source scanning is disabled globally and enabled only for ../../views/design/phase-a. Evaluation output: ../../../public/design/phase-a/evaluation.css. This command is not the application build. The application now requires the root npm build before deployment activation.

Exact versions and transitive integrity hashes live in package.json/package-lock.json. Only free Tailwind CSS/CLI 4.3.3 and daisyUI 5.7.47 are used; corresponding MIT notices are in licenses/. No paid templates or MCP service. Generated dependencies are ignored and must not be published.

Guide routes permit only tg-inventory-app.test and dev-inventory.tabletopgaymers.org in local/development, behind existing application authentication and development site's existing Basic protection. Production/other hosts are excluded. Views contain static illustrative data; no workflow posts, calculations or persistence.

Historical evaluation choices are reference material. Normal application and style-guide pages use the shared Tailwind/daisyUI foundation plus custom app styles. Current task authority and project operating instructions control changes and publication; historical evaluation gates do not assign new work.
