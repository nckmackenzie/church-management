# Incremental Modernization Plan

**Project:** Church Management Application  
**Assessment date:** 14 October 2026  
**Goal:** Modernize a production PHP application gradually, without a Laravel rewrite and without requiring a big-bang frontend replacement.

## Executive recommendation

Keep PHP, Apache, MySQL, the current URLs, and the existing session-based login while the application is modernized feature by feature. Introduce:

- Composer and PSR-4 autoloading for new PHP code;
- a small set of framework-independent HTTP, validation, security, and error-handling utilities;
- Vite as the asset builder;
- Tailwind CSS for migrated screens only at first;
- React as isolated components ("islands") on screens whose interaction justifies it;
- automated tests around each feature before it is changed;
- explicit new routes while legacy routes continue to work during the transition.

Do **not** begin with a Laravel rewrite or a site-wide React single-page application. Basic CRUD pages can remain server-rendered PHP and use Tailwind. React is most valuable for complex forms, tables, filters, and screens already making several AJAX requests.

The target is a modular PHP application with a progressively enhanced frontend. Laravel can remain a future option rather than a prerequisite.

## What exists today

The application is a small custom MVC framework:

```text
Apache rewrite
    -> public/index.php
        -> Core (controller/method/params URL dispatch)
            -> feature controller
                -> model using a shared PDO wrapper
                -> PHP view + shared header/footer
                    -> Bootstrap/AdminLTE + jQuery plugins + page scripts
```

Repository inventory, excluding bundled third-party vendor/plugin code:

| Area | Size observed |
|---|---:|
| Controllers | 47 files / about 11,983 lines |
| Models | 47 files / about 9,523 lines |
| Views | 195 files / about 25,187 lines |
| Custom page JavaScript | 73 files / about 5,961 lines |
| Views containing inline scripts | 150 |
| Views using the shared header/footer | 175 / 172 |
| Controllers returning JSON somewhere | 25 |
| Controllers reading JSON request bodies | 12 |
| Automated test files | 0 |

Positive foundations already present:

- Controllers, models, and views are mostly grouped by feature, so one feature can be migrated at a time.
- PDO prepared statements and password hashing/verification are already used in important areas.
- Some database transactions already exist.
- Modern JavaScript modules, `fetch`, and JSON endpoints have already been introduced on some screens.
- The development machine has PHP 8.2, Node 24, and Composer 2 available.
- The production PHP/runtime version and its compatibility still need to be verified separately.

Current constraints:

- There is no root `composer.json`, `package.json`, frontend build, or manifest integration.
- Routing dynamically exposes public controller methods rather than using an explicit route table.
- Authentication and authorization are repeated in controller constructors and methods.
- JSON response shapes, status codes, headers, and errors are inconsistent.
- Shared layout files load a large Bootstrap/AdminLTE/jQuery plugin bundle on nearly every page.
- PHP, HTML, SQL coordination, validation, permissions, and redirects often live in the same controller method.
- Most views echo values without a central HTML-escaping helper.
- A frontend utility contains a hard-coded localhost application URL.
- Several copied/version-suffixed JavaScript files suggest there is no dependable build/versioning workflow.

## Immediate production risks

These should be handled before, or in parallel with, the first UI migration. They are more important than switching CSS libraries.

### Critical: administrative user actions lack a controller-wide access boundary

`Users::__construct()` currently loads the user model but does not require an authenticated administrator. Because `Core` dispatches any matching public method, methods such as `createupdate`, `reset`, `assignrights`, `delete`, and administrative page actions can be addressed directly by URL. Several methods check only the HTTP verb and do not check the logged-in user's privilege.

Recommended action:

1. Add a deny-by-default authentication/authorization guard for every administrative user action.
2. Explicitly allow only login, forgot-password, reset-request, and denied-access routes to be anonymous.
3. Add integration tests proving anonymous and ordinary users receive `401`/`403` and cannot mutate users or rights.
4. Review all controllers that do not perform an obvious session check (`Auths`, `Budgets`, `Pages`, and `Users`) and document intentional public routes.

### Critical: no CSRF protection was found

State-changing forms and JSON requests rely on the session cookie but do not send or validate CSRF tokens. Add a token to all POST/PUT/PATCH/DELETE requests, including `fetch` calls. Use `SameSite=Lax` (or stricter where feasible), `Secure` in production, and `HttpOnly` session cookies as additional defenses, not substitutes.

### High: output encoding is not systematic

Database and submitted values are echoed directly into HTML, HTML attributes, and JavaScript. Add one `e()` helper based on `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` and require it at every HTML boundary. For JavaScript data, serialize with `json_encode` using safe flags rather than interpolating strings.

### High: sessions and credentials need hardening

- Regenerate the session ID after successful login.
- Centralize session start and cookie settings.
- Rate-limit login and password-reset attempts.
- Avoid weak random temporary passwords based on `md5(mt_rand())`; use `random_bytes`/`random_int` and require a password change.
- Do not put temporary passwords into logs.
- Review SMS password delivery as an account-recovery risk.

### High: errors can disclose internals

The database wrapper echoes connection exception messages. Production should log detailed errors server-side, return a correlation ID, and show a generic response to the browser. Configure PHP not to display errors in production.

### High: request/response contracts are inconsistent

JSON endpoints do not set an explicit JSON content type and return different shapes. Introduce a response helper with a stable envelope, for example:

```json
{
  "data": {},
  "error": null,
  "meta": {}
}
```

Validation failures should consistently return `422`, unauthenticated requests `401`, forbidden requests `403`, missing resources `404`, conflicts `409`, and unexpected errors `500`.

### Medium: environment coupling

The custom JavaScript utility uses `http://localhost/cms`. Browser code should use relative URLs or receive the base URL from server-rendered configuration. Production secrets should come from environment variables and never be emitted to the frontend or committed.

## Target architecture

```text
                       Existing URL remains stable
                                  |
                         explicit route registry
                         /                  \
                legacy controller       migrated controller
                    action                    action
                      |                         |
               existing model        request -> service -> repository
                      |                         |
                    MySQL <---------------------+
                      |
             PHP view/layout shell
                /             \
       server HTML          React island (only when useful)
       + Tailwind           + Tailwind
                \             /
                 Vite manifest assets
```

### Backend layers for new or migrated code

Keep the layers small and practical:

- **Action/controller:** HTTP method, authentication, authorization, input mapping, and response only.
- **Validator/request object:** field rules and normalized input.
- **Service:** business rules and transaction boundary.
- **Repository:** PDO/SQL and persistence mapping.
- **View/JSON response:** escaped HTML or a stable JSON contract.

Do not mechanically rewrite every existing model. Apply these boundaries only as a controller is migrated or when shared code is needed.

### Frontend strategy

Use three levels and choose the simplest level per screen:

1. **PHP + Tailwind:** default for ordinary forms, detail pages, and simple CRUD.
2. **PHP + small vanilla module:** for a date picker, confirmation dialog, or a few dependent fields.
3. **PHP shell + React island:** for complex tables, multi-row forms, live filters, substantial client state, or several API calls.

React should mount into a dedicated element in a PHP view. The rest of the page, navigation, login session, and URL remain owned by PHP. This is the incremental integration approach documented by React itself.

Do not add React Router initially. Server URLs should continue to be authoritative, and normal links should navigate between controllers.

## Tailwind coexistence with Bootstrap/AdminLTE

Loading Tailwind globally alongside the existing theme can change headings, lists, borders, images, and plugin layouts. For the transition:

- compile a separate stylesheet for migrated screens;
- use a Tailwind prefix such as `tw:` so utility names cannot collide;
- initially omit Tailwind Preflight from the legacy bundle;
- load the new stylesheet only from a migrated view or migrated layout;
- do not combine Tailwind classes and Bootstrap component classes on the same element;
- preserve AdminLTE navigation until a later shared-shell phase.

Tailwind's current documentation explicitly supports prefixing and disabling Preflight for integration into an existing project. Before adopting Tailwind v4, confirm that production users meet its modern browser baseline.

## Vite integration

Use one root Node project and emit built assets into a new directory such as `public/build/`.

Suggested source layout:

```text
assets/
  css/
    app.css
  js/
    app.jsx
    islands/
      AgeGroups.jsx
      CustomerForm.jsx
app/
  Http/
  Security/
  Services/
  Repositories/
  Support/
public/
  build/                 # generated, deployed assets
tests/
  Unit/
  Integration/
```

Configure Vite to generate a manifest. Add a PHP asset helper that:

- points to the Vite development server only in local development;
- reads `public/build/.vite/manifest.json` in production;
- emits the hashed CSS and JavaScript files for an entry;
- fails clearly during deployment if the manifest is missing.

The production web server needs only the compiled files; Node does not need to run on the production server. The official Vite backend-integration guide describes this manifest-based approach.

## Phased delivery plan

### Phase 0 — Safety baseline (about 3–5 developer days)

- Back up the database and rehearse restore in a non-production environment.
- Record the production PHP, Apache, MySQL, and browser support matrix.
- Add a staging environment using a sanitized recent database copy.
- Fix the `Users` access-control issue and audit controller entry points.
- Add CSRF protection, output escaping, hardened sessions, and production error logging.
- Add health-check and error correlation IDs.
- Capture smoke tests for login, navigation, permissions, and one read/write flow.

**Exit gate:** authentication/authorization regression tests pass and rollback/restore steps have been exercised.

### Phase 1 — Tooling without changing pages (about 2–4 days)

- Add `composer.json` with PSR-4 autoloading for a new application namespace; retain the old autoloader as a fallback.
- Add PHPUnit (or Pest if the team prefers its syntax) and a test bootstrap.
- Add `package.json`, Vite, React, React DOM, and Tailwind.
- Add lint/format scripts for PHP and JavaScript.
- Build `public/build` through a repeatable command and integrate the Vite manifest helper.
- Add CI checks: PHP syntax, unit/integration tests, frontend build, and dependency audit.
- Keep generated assets either reproducibly built in deployment or deliberately committed—choose and document one policy.

**Exit gate:** a no-op frontend entry builds in CI and can be loaded in staging without changing legacy styling.

### Phase 2 — Establish shared HTTP boundaries (about 3–5 days)

- Introduce explicit route definitions for all new endpoints.
- Keep the current `Core` dispatcher only as a legacy fallback during migration.
- Add `Request`, `HtmlResponse`, `JsonResponse`, redirect, validation, and exception-handling helpers.
- Add authentication and authorization middleware/guards.
- Add a common API client for CSRF headers, JSON parsing, timeouts, and normalized errors.
- Derive URLs from the current origin/`URLROOT`; remove the hard-coded localhost URL.

**Exit gate:** one test endpoint returns consistent JSON and rejects wrong verbs, missing CSRF, unauthenticated users, and unauthorized users.

### Phase 3 — Low-risk pilot: Age Groups (about 3–5 days)

`Agegroups` is a good pilot: its controller is about 170 lines, its model about 81 lines, and it has three small views. It exercises list/add/edit/delete without touching a high-volume financial transaction flow.

Recommended pilot scope:

- Put age-group business logic in a service/repository.
- Add explicit routes and permission checks.
- Add request validation and CSRF protection.
- Add integration tests for list/create/update/delete and tenant/congregation boundaries.
- Rebuild its three views with prefixed Tailwind utilities.
- Keep it server-rendered initially; use a small vanilla module for delete confirmation.
- Optionally replace only the list/table with a React island after the PHP/Tailwind version is stable. This proves React mounting without making React mandatory.

**Exit gate:** production metrics are unchanged or improved, permissions match the old behavior, and rollback is a single deployment reversal.

### Phase 4 — Repeat controller by controller

Use this order as a starting point, adjusted for business priority:

| Wave | Features | Reason |
|---|---|---|
| 1 | Age groups, VATs, years | Small CRUD; validates patterns |
| 2 | Customers, accounts, banks, congregations, districts | Moderate CRUD and relationships |
| 3 | Members, officials, services, suppliers, products | Larger workflows; good React-island candidates |
| 4 | Contributions, invoices, payments, journals, bank transactions | Financial writes require stronger tests and transaction review |
| 5 | Expenses, budgets, reconciliation, reports | Highest complexity and regression impact |
| 6 | Users, roles, shared navigation/layout | Security-sensitive and cross-cutting; secure immediately but redesign after patterns stabilize |

Security defects in a later-wave feature should still be fixed immediately; the wave controls redesign, not risk remediation.

For every controller, use the migration checklist below and deploy it independently.

### Phase 5 — Shared shell and legacy dependency removal

After enough pages use the new stack:

- replace the AdminLTE top/side navigation with a Tailwind layout;
- split the legacy footer bundle so jQuery plugins load only on pages that need them;
- replace DataTables selectively with a React table or a lightweight server-rendered alternative;
- remove Bootstrap/jQuery plugins only when no remaining page uses them;
- delete obsolete copied scripts after usage is verified;
- turn off legacy dynamic route fallback after the final controller is registered explicitly.

## Per-controller migration checklist

1. Inventory URLs, methods, permissions, database tables, uploads, SMS side effects, and consuming JavaScript.
2. Capture current behavior with integration tests and a short manual smoke script.
3. Define the HTML and/or JSON contract and its error cases.
4. Add explicit routes, HTTP verb rules, authentication, authorization, CSRF, and tenant checks.
5. Extract business rules into a service and SQL into a repository only where it improves the slice.
6. Ensure multi-write operations use a database transaction and are safe to retry where appropriate.
7. Escape all rendered output and validate all server inputs.
8. Build the new view at the simplest frontend level that meets the need.
9. Test accessibility, keyboard operation, responsive layout, and supported browsers.
10. Deploy behind a per-feature configuration flag or reversible route/view switch.
11. Compare errors, response times, and business totals; keep the old implementation for one release window.
12. Remove the old path only after acceptance and rollback expiry.

## Testing strategy

Start with high-value coverage, not a percentage target:

- **Unit tests:** validators, calculations, date rules, permission decisions, and services.
- **Repository integration tests:** SQL behavior against a dedicated test database.
- **Controller integration tests:** status, redirect, session, CSRF, authorization, tenant boundary, and JSON shape.
- **Browser smoke tests:** login plus the five most important end-to-end business flows. Playwright is a reasonable choice once Vite/Node exists.
- **Characterization tests:** lock down legacy behavior before refactoring complex reports and accounting code.

Use separate test data and never point automated tests at production.

## Deployment and rollback

Each release should contain immutable PHP plus prebuilt hashed frontend assets. A safe release sequence is:

1. Run tests and build assets in CI.
2. Back up before schema/data changes.
3. Deploy backward-compatible database changes first.
4. Deploy code and assets together.
5. Run health and smoke tests.
6. Enable the migrated feature for administrators/internal users first when possible.
7. Expand access while monitoring PHP errors, HTTP 4xx/5xx rates, latency, and key business totals.

Prefer expand/contract database changes: add new nullable columns/tables first, backfill, switch reads/writes, and remove old structures in a later release. A frontend rollback must not require an immediate database rollback.

## Definition of done for a migrated feature

A controller is considered migrated when:

- all routes are explicit and enforce verbs;
- authentication, role permission, CSRF, and congregation/tenant boundaries are tested;
- input is server-validated and output is correctly encoded;
- JSON responses follow the common contract;
- business logic is not coupled to HTML rendering;
- important multi-write operations are transactional;
- frontend assets come from Vite and contain no environment-specific URL;
- the page does not load unused legacy plugins where practical;
- automated tests and a rollback path exist;
- operational logs contain useful context but no credentials or sensitive payloads.

## Laravel decision

Laravel is not required for this plan. Avoiding it now reduces the learning and migration burden. The proposed boundaries—explicit routes, controllers, services, repositories, middleware-like guards, Composer autoloading, and tests—also make a future Laravel move easier because business code will no longer depend heavily on the custom router or global helpers.

Reconsider Laravel only after several slices have been modernized and if the team wants its conventions for queues, mail, validation, ORM, migrations, or authentication. At that point it can be introduced behind selected routes or in a separate service, rather than requiring a rewrite.

## Recommended first milestone

The first milestone should be **security baseline + toolchain + Age Groups pilot**, not a global redesign. It gives the project:

- an urgent reduction in account and request risk;
- a working Composer/Vite/Tailwind/React-capable foundation;
- one independently deployable migrated controller;
- real evidence about build, hosting, team learning, and rollout effort;
- a repeatable template for subsequent controllers.

## References

- [React: Add React to an Existing Project](https://react.dev/learn/add-react-to-an-existing-project)
- [Vite: Backend Integration](https://vite.dev/guide/backend-integration)
- [Tailwind CSS: Preflight and disabling it](https://tailwindcss.com/docs/preflight)
- [Tailwind CSS: Utility prefixing](https://tailwindcss.com/docs/styling-with-utility-classes#using-the-prefix-option)
- [Tailwind CSS: Browser compatibility](https://tailwindcss.com/docs/compatibility)

## Assessment limitations

This is a static repository assessment. It did not connect to the database, inspect production server settings, execute business transactions, or test real user roles. Production PHP/MySQL versions, data volume, traffic, backup quality, deployment process, and supported browsers must be confirmed in Phase 0 before estimates or package versions are finalized.
