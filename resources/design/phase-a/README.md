# Design Phase A guide build

This isolated package builds only the static Tailwind+daisyUI guide. It does not style working application pages or alter the deployment pipeline.

From this directory, using Node.js 20 or newer and npm:

```powershell
npm ci
npm run build
```

Input: input.css. Source scanning is disabled globally and enabled only for ../../views/design/phase-a. Output: ../../../public/design/phase-a/evaluation.css. The compiled CSS is included for publication; the existing server deployment needs no npm build step or installed frontend dependencies.

Exact versions and transitive integrity hashes live in package.json/package-lock.json. Only free Tailwind CSS/CLI 4.3.3 and daisyUI 5.7.47 are used; corresponding MIT notices are in licenses/. No paid templates or MCP service. Generated dependencies are ignored and must not be published.

Guide routes permit only tg-inventory-app.test and dev-inventory.tabletopgaymers.org in local/development, behind existing application authentication and development site's existing Basic protection. Production/other hosts are excluded. Views contain static illustrative data; no workflow posts, calculations or persistence.

Design choices remain proposals for user review. Td has not been adopted by working application screens. Independent exact-source/access review precedes guide publication under D-190; Management coordinates publication.
