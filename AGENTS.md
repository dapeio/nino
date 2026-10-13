# Nino repository guide for AI coding agents

This file is the operational specification for AI agents that modify Nino.
Follow it before copying patterns from memory or from another framework. The
six extension recipes of section 7 are part of it.

Nino is a filesystem-based PHP website framework. It has no database, Composer
dependency, JavaScript build step, component framework, or plugin manager.
Prefer readable project files and the public Nino APIs over new abstractions.

## 1. Rule strength and source of truth

The words **MUST**, **MUST NOT**, **SHOULD**, and **MAY** are normative.

1. The current user request and repository instructions have priority.
2. This file defines repository-wide defaults.
3. Existing source code and tests define the current runtime contract.
4. The manuals in `docs/` explain intent and operation.
5. If documentation and source disagree, follow the current behavior and update
   the stale documentation in the same change when that is within scope.
6. A pattern in existing code that contradicts this guide is not authority for
   new code: check the current runtime and the tests before copying it.

Do not invent an API because its name seems plausible. Search for the actual
method, its signature, and at least one current call site.

## 2. The working method

A change is made by four roles, one patch at a time. A role is a role: whoever
fills it - a person or an agent - keeps to it.

1. **The plan.** A written plan per patch, the "Bauplan": numbered parts; every
   decision of the owner quoted and marked as binding; the tests that must stay
   green and the ones the patch adds; the constraints - what stays untouched, no
   version bump unless the owner said so, no fallback for projects that do not
   exist, no guard test against an old name.
2. **The build.** One agent implements the whole plan on a branch of its own.
   - It reads the plan and this file in full first, works only in its clone and
     in throwaway copies, and runs every suite the plan names before it commits.
   - It commits once. The title is `Added|Changed|Fixed|Removed(/scope): one sentence`,
     the scope being `/nino` for the kernel, `/feature` for the catalogue, a
     module or file name where the change is one thing. The body says what and
     why in prose; the trailers are the ones the harness hands it.
   - It reports the commit, a file-by-file summary, every test with its counts,
     and what is *open*: anything left out, deviating or uncertain, each with
     its reason.
   - It never pushes and never rewrites history.
3. **The review.** A second agent reads the whole diff against the plan.
   - It runs every suite in copies of its own, never in the work clone, with
     PHPStan, ESLint, `php -l`, `node --check` and `git diff --check`; it greps
     for what the plan says must be gone, and drives the real thing in a browser
     where the patch touches a screen.
   - It reports findings with `file:line` and the exact search that proved each,
     sorted into *blocking* (the build is wrong against the plan or breaks
     something) and *non-blocking* (a nit, a wording, a judgement call), a fix
     for each, and a verdict: ok or fix.
   - At most two fix rounds, each a commit of its own on the branch.
4. **The acceptance.** The planner reads build and review and takes or declines
   each non-blocking finding, in one commit. The patch is exported with
   `git diff --binary <base>..<tip>`, the commit title as its first line `# ...`,
   applied on a fresh clone of the owner's branch, and the tree hash compared.
   The suites run once more and the numbers are recorded. The owner applies the
   patch and pushes: GitHub is version control, and nothing in the chain
   depends on it.

Coupled patches - a kernel change and the catalogue's half of it - are named as
a pair, with the push order that keeps CI green.

Whoever builds also keeps to these: read the complete function or class around
every line that changes, and find its nearest implementation, test and
documentation with `rg`; make the smallest coherent change in the local
formatting and naming; use public APIs - methods beginning with `_` are internal
unless the class itself is being maintained; keep PHP, JavaScript, CSS,
templates, tests and documentation synchronized; add no runtime dependency or
build step without explicit approval; and delete, rename or migrate no existing
project data silently.

## 3. Choose the correct extension type

Do not use “page”, “template”, “module”, and “admin module” interchangeably.

| Goal | Correct extension |
| --- | --- |
| Add a screen to the workbench `/_admin` | Panel - answered by a runtime module's `adminPanels()`, or a module directory under `_admin/Nino/Modules/`, see the [panel recipe](docs/recipes/admin-panel.md) |
| Add behavior to public requests or a new shortcode | Runtime module, see the [runtime module recipe](docs/recipes/runtime-module.md) |
| Add a shortcode that a tool can offer - a source for its value and typed attributes with defaults - or one that loops the elements of a type around its content | Component or stack - `\Nino\Modules\Components::addComponent()` and `addStack()` from a module's `init()`, or the `components` and `stacks` keys of a feature manifest, see the [component recipe](docs/recipes/component.md) |
| Make a kernel or project module selectable and copyable during the setup wizard | Installer module package, see the [installer package recipe](docs/recipes/installer-package.md) |
| Package an installable feature - a module with a manifest, settings and a version, switched on in the workbench after setup | Feature - `features/<Name>/` with `feature.php`, see the [feature recipe](docs/recipes/feature.md) |
| Compose a page template out of sections, columns and components in the workbench | The Builder - the feature `features/Builder` of the catalogue, panel Builder in the Features group, not here |
| Add a complete starting page to the installation library | Page library unit |
| Add reusable HTML+ included by other templates | Reusable `.tpl` template |
| Map a public HTTP URL to output | Route |
| Store repeated structured content | Element type and Elements, see the [Element types recipe](docs/recipes/element-types.md) |
| Store a short editable value | Textfill |

These pieces cooperate but stay separate. A route does not create the page
template (`templates/page-*.tpl`) it renders; an installer module package is not
the runtime module it selects and copies; a feature - a runtime module with its
panel and install unit in one directory below `features/` - is never a wizard
unit. A panel is answered by a runtime module's `adminPanels()` or is a directory
below `_admin/Nino/Modules/`; a feature's panel sits in the rail's `features`
group, always, since the group a `nav()` names is read for the kernel's own and
`app/` panels only. A feature that spans types implements each layer explicitly.

## 4. Repository map

| Path | What it is |
| --- | --- |
| `_nino/` | The kernel: `Nino.php` (boot and autoloader), one class per file in `_nino/Nino/<Class>/`, the modules Nino ships in `_nino/Nino/Modules/<Name>/` - each with its own `Admin/`, `assets/`, `text/`, `templates/` and `install/` - and the public `Nino.css`, `Nino.js` and `Nino.ui.js` |
| `_admin/` | The workbench: the shell (`Admin.php`, `recovery.php`, `assets/style.css`, `assets/Nino.admin.js`, which only the workbench loads), its screens as modules in `_admin/Nino/Modules/<Name>/`, and the setup wizard with its library in `_admin/install/`, deletable after setup |
| `app/` | The project's own PHP classes and runtime modules, under namespaces of their own |
| `features/` | Installed features, one directory each with a `feature.php` manifest; the `nino` constraint it names is the kernel whose vocabulary the feature uses. A checkout ships none: they come from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) |
| `docs/` | The manuals, and the six extension recipes in `docs/recipes/` |
| `tests/` | Standalone contract tests, one suite per module or panel (`*-smoke.php`, `*-js-smoke.js`), `harness.php` (`check()`, `ninoSandbox()`, `ninoSandboxDir()`, `ninoWarnings()`, `ninoErrors()`, `ninoCall()`, `ninoUser()`, `ninoVisit()`, `ninoDone()`), its JS counterpart `harness.js` for the forty JS suites, and `fixtures/` |
| `history/` | The frozen `CHANGELOG.md` up to 1.7.0; from there the history is the git log and the tags |
| `private/`, `public/` | One installation's own state, written by the wizard and tracked by nobody. `private/` is never served: `config.php`, `templates/`, `text/`, `elements/`, `assets/`, `data/`, `.auth/`, `.logs/`, `.backups/`. `public/` is what a browser loads directly: `images/`, `fonts/`, `favicon/` and the generated `.cache/` |

Reach project state through `Filesystem::path()` and `Filesystem::url()` (or the
`[[/nino/public]]` fill), never a url built by hand from `[[/nino/dir]]`. Write
no project state into a tool folder (that breaks updates) or into `config.php`
(a Restore rolls it back); `\Nino\AppData::DEFAULTS` sit under that file, so it
holds only what the project decided. A fresh checkout has no `private/` or
`public/` - the wizard creates them, so add no placeholders - and a file under
`_admin/install/library/` is a source: editing it changes future installations.

## 5. Core runtime model that every extension must preserve

Every request follows:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

`$appData` is application state, configuration, callbacks, caches, and runtime
state; `$request` is one normalized request and its response. Both are passed by
reference. Keys under `/...` are stable configuration/data space, keys under
`./...` request-lifecycle-only state. A `/...` value is not persistent merely
because it is in `$appData`: a writer must explicitly save it.

### Text keys

A textfill key follows one grammar, `/<namespace>/<category>/<part>/<name>`:
exactly four segments, each `[a-z0-9]+(-[a-z0-9]+)*`, English, never a number as
a name, never a language or a form (global or per language) in it.

| Namespace | Holds | Category |
| --- | --- | --- |
| `template` | the words one template reads | the template's file name without `.tpl`, prefix and all: `page-home.tpl` is `/template/page-home/...`; words several templates read live in `/template/common` |
| `project` | the project's own facts and settings | `company`, `website`, `mail`, or a name of the project's own |
| `feature` | the words of a catalogue feature's function | the feature's manifest `key` |
| `module` | the words of a kernel module | the module's directory, lower case |

The system writes `/_nino/webpage<uri>/<name|title|description|uri>` (a page's
details, after its Element-URI) and `/_nino/locale/<code>/name` (a language's
name); the workbench's own words are `/_admin/...`; `/nino/...` names the
kernel's runtime fills and is no text key. A label in front of a fact is a word
of its own (`/template/common/label/phone`), the fact comes from
`/project/company/...` alone. `tests/keys-smoke.php` holds everything shipped to
this; the Text Keys tab creates and renames only keys that follow it. The rules
and the reasons are in [docs/development.md](docs/development.md#the-key-grammar).

### Response rules

Runtime handlers MUST NOT call `echo`, `header()`, `http_response_code()`, or
`exit`. They MUST modify `$request['/nino/http/response']` or use:

```php
\Nino\Http::ok( $request, [ 'items' => $items ] );
\Nino\Http::fail( $request, 400, 'invalid request' );
```

Do not expose stack traces, local paths, tokens, email-existence checks, or raw
exception messages in a public response.

### Route rules

Routes live under `/nino/http/routes` and are keyed
`METHOD://public-uri`:

```php
'GET://services' => [
	'uri'  => '/services',
	'body' => '[template /templates/page-services]',
],
```

The key is the public request; `uri` is the stable internal response identity.
Route-specific callbacks use the internal identity:

```text
/nino/http/response/GET://services
```

Technical endpoints owned by a module MAY be registered in its `init()`.
Normal visitor pages SHOULD be persisted as page routes instead. A page a
module serves in every language by itself (`\Nino\Modules\Legal`) is the
exception: it registers one route per language in `init()`, all with the same
`uri` - the Element-URI - and the language's `locale`.

A route MAY carry `'maintenance' => false`: `Modules\Maintenance` then leaves it
reachable while the site is in maintenance. Only code or `config.php` sets it -
the legal pages and `Auth`'s login route do - never a request, and the Routes
panel keeps it when it saves a route.

CSRF protection is active by default. A state-changing browser route MUST keep
it active and its form MUST render `[csrf]`. Use `'csrf' => false` only when a
documented alternative verifier, such as a cryptographic webhook signature, is
implemented and tested.

### Persistence and concurrency rules

Never implement a read-modify-write cycle as separate read and write calls.
Two requests can read the same state and one will overwrite the other. Use:

```php
$written = \Nino\Filesystem::mutate(
	$appData,
	'/data/catalog.php',
	function( mixed $state, array &$appData ) use ( $entry ): array {
		$rows = is_array( $state ) ? $state : [];
		$rows[] = $entry;
		return $rows;
	},
	[]
);
```

Returning `null` from the callback aborts the mutation and releases its lock.
Check the boolean result where failure affects the response.

To persist selected top-level configuration keys already changed in
`$appData`, use:

```php
\Nino\AppData::writeContentData( $appData, [
	'/project/catalog',
	'/nino/http/routes',
] );
```

Do not serialize all of `$appData`. It contains runtime values and may overwrite
concurrent changes.

### Rendering and escaping rules

HTML+ is processed as textfills, then shortcodes, then final render callbacks.

- Absolute textfill: `[[/template/page-home/main-hero/title]]`
- Reusable template: `[template /templates/html-header]`
- Image slot: `[image /template/page-home/main-hero/image alt=""]`
- Component: `[hero-title /template/page-home/main-hero/title tag="h3"]` - a shortcode with a schema, its first argument the source of its value
- Stack: `[stack /services sort="title"]...[/stack]` - a component for a loop, its components reading the fields of the element
- Element-local field: `[[title]]` only inside `[element]` or `[elements]`
- CSRF field: `[csrf]`

Escape at the output context:

- HTML text/attribute: `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE,
  'UTF-8')`. `ENT_SUBSTITUTE` is not optional: spelling the flags out drops
  php's own default, and without it one byte that is not valid UTF-8 answers
  `''` and takes the whole value with it - silently, with no warning and no log
  line. The same holds for `ENT_NOQUOTES` on text content.
- Rich element text: declare `'html' => true` in its model (`'blocks' => true`
  as well for paragraphs and lists, `'breaks' => true` instead for the line
  breaks of plain text) and rely on `\Nino\Html::sanitizeHtml()` when accepting
  it and `\Nino\Html::fieldValue()` when drawing it.
- Browser DOM: insert untrusted text with `textContent`, not `innerHTML`.
- URL/path/class/ID: validate against a strict allowlist before escaping.
- JSON: return arrays through Nino; do not concatenate JSON by hand.

Shortcode output is rendered again. If untrusted content can contain `[`, it can
otherwise become a new fill or shortcode on the next rendering pass. Escape or
neutralize it for the intended context.

A component is a shortcode with a schema (`\Nino\Modules\Components`), and
the rules above apply to it unchanged. The kernel resolves its first argument
through `Components::value()` before the renderer runs, and what that returns is
escaped for its context and has every `[` written as `&#91;`: **`[` in a
resolved value is always `&#91;`**, for a text key, a fixed `text="..."`, a field
of the element in a stack and an address alike. A renderer writes what it made
itself and what `$args['value']` holds, and escapes the rest with
`Components::escape()`. Two kinds of source differ in what the value is, and a
renderer treats them as such: for `image` it is the *reference* to the picture,
escaped like text - to look up through `\Nino\Images` by `$args['source']`
(the uri of a slot) or by the image field of the element, and never to write
as it is; for `content` it is the template's own markup between the tags,
which is not an editor's value and keeps its `[`. A stack renders each cell itself through
`Components::renderStack()` and returns html with no shortcode in it; it does not
fill fields into its content and leave the rendering to the next pass, as
`[elements]` does.

### Callback rules

Callbacks receive `$appData` and the argument by reference. A non-`null` return
value replaces the argument for following callbacks. Keep callback names and
argument shapes backward compatible. Use a project namespace for project
events; `/nino/*` is reserved for Nino.

## 6. Code and interface conventions

### PHP

- PHP is 8.4+ and new files MUST contain `declare(strict_types=1);`.
- Keep the namespace and one public class per autoloaded module file.
- Match nearby tabs, spaces, braces, docblocks, and explicit types.
- Validate array shape before reading nested user data.
- Catch failures only when the caller can recover safely; do not hide bugs.

### Markup belongs in a template

PHP decides **what** is shown; a template decides **what it looks like**. A page,
a panel screen, a preview, a mail body, a fragment a shortcode returns - the
markup lives in a `.tpl`, is read through `\Nino\Filesystem`, and is filled with
`str_replace()` over `[[token]]` placeholders (a template rendered as HTML+ by
the kernel uses the fill and shortcode engine instead). A project's templates are
`templates/*.tpl`; a module's or a feature's own are `templates/` beside its
class - `_nino/Nino/Modules/<Name>/templates/`, `features/<Name>/templates/`,
read as `/features/<Name>/templates/<name>.tpl`.

This is not a style preference. It is what lets somebody change how a thing looks
without reading the class that decides when it appears, and what keeps a diff
about behaviour separate from a diff about design.

Three shapes MAY carry markup in PHP, and nothing else:

1. **A one-line fragment with `[[tokens]]`, declared once as a named property**
   at the top of the class - never built inside a method. `\Nino\Modules\Navigation::$html`
   (`'<li><a href="[[uri]]"[[attributes]]>[[title]]</a></li>'`) and
   `Modules\Assets`'s `$_template` are the shape to copy; being a property is
   the point, because it is then one place to read and a project can replace it.
2. **An icon**, which is geometry rather than a view - and what the panel
   contract asks a panel class for as a string (`Admin::icon()`).
3. **A last-resort fallback** for the case where no template can be read at all -
   `Modules\Maintenance`'s built-in page is the one instance, and it says so
   where it stands.

Two things that look like exceptions and are not: a **document format** built
from data (`sitemap.xml`, `robots.txt`, an RSS feed) is a serialisation rather
than a view, and a **builder whose product is markup** (the Builder
feature composes `.tpl` source) is writing its output, not rendering itself.
Both MUST say so where the markup is.

A template is output: what is in it is sent to whoever asked for the page. Do not
explain the file inside it - put that in the class that fills it. A panel's own
template (`templates/panel.tpl`) is the exception, since only a logged-in
workbench user is ever sent it.

### JavaScript

- There is no module bundler or transpiler. Admin files use an IIFE and attach
  behavior below `window.Nino`.
- Use `const`/`let`, explicit event listeners, and existing HTTP helpers: a
  workbench panel never posts by hand but calls `Nino.adminUi.api.call()`.
- Do not add inline handlers or depend on globally leaked DOM IDs.
- Keep pure model functions separately testable where practical.
- Use `textContent` for all server- or user-derived strings.

### CSS and management UI

See "Designing an admin frontend" in the
[admin panel recipe](docs/recipes/admin-panel.md#designing-an-admin-frontend)
before writing markup or CSS for the workbench: the shell, a panel, the wizard
or the recovery page.

- Maintain keyboard focus, labels, error text, small viewports, coarse pointers,
  and `prefers-reduced-motion`; do not encode meaning only by color or hover.
- A design layer turns `_nino/Nino.css` through the custom properties its
  `:root` declares, and the comments there are the reference. A new default is
  declared in `Nino.css`; the delivered `theme.css` is the body the catalogue's
  Design feature compiles at its defaults, and the catalogue's tests compare the
  two byte for byte, so a change there is made in both repositories.

### Templates

- `.tpl` files contain HTML+, never PHP, and no inline event handlers.
- Use valid semantic HTML and accessible names: real `button` controls for
  actions, real `a` elements for navigation, a label on every form control and
  suitable alt text on every content image.
- Do not assume JavaScript is available for essential content.

## 7. Recipes

The six extension recipes linked from the table in section 3 are part of this
specification: every rule above applies inside them, and a change to a contract
they describe updates the recipe in the same commit.

## 8. Security review required for every extension

Security is not a separate cleanup pass. Apply the following questions while
designing the behavior and assert important boundaries in tests.

### 8.1 Trust, authentication and CSRF

Treat all of these as untrusted: `$_GET`, `$_POST`, headers, cookies, the request
body, uploaded bytes, and IP forwarding headers; Admin and Editor payloads, even
after login; values loaded from editable `config.php`, text, Elements, and
templates; filenames and IDs supplied by a browser; imported translation JSON;
external API, webhook, feed, and mail data; and HTML returned from a project
callback. Authentication proves who sent data, not that it is valid or safe for
HTML, a path, a header, or a class name. Validation in the browser is a
convenience: repeat it on the server.

- Every panel API method calls `Admin::guardPerm()` with the panel's own
  permission; `Admin::guard()` alone is for the one screen every account may use.
- Runtime management endpoints check a specific `Auth::checkPermission()`
  value; read and write permissions are distinct when their risk differs.
- Do not accept a username in the request and check that user's permission as
  authorization for the current caller, expose workbench actions through a new
  public route, or implement parallel ad-hoc auth cookies: session fixation
  protection and the cookie policy stay in the kernel.
- Respect `./nino/csrf/blocked` before runtime write behavior; admin guards also
  respect an already failed response. Do not exempt JSON requests merely because
  their content type is JSON. A signed webhook exemption validates raw bytes,
  timestamp/replay window, algorithm, and secret using constant-time comparison.

### 8.2 Identifiers, paths, classes, URLs and output

- Define a full-match allowlist regex for IDs and slugs. Reject `..`, slashes
  where a flat segment is expected, null/control characters, and empty
  normalized values. Validate structure before resolving or escaping.
- Resolve project paths through `Filesystem` or a fixed owned base directory.
  Never concatenate onto `Filesystem::getPath()` for a private path - that is
  the public, webserver-facing root; `Filesystem::path( $appData, '/text' )`
  resolves `Filesystem::PRIVATE_DIRS` against the private root instead.
- Never pass a request-derived class string to autoloading or `method_exists()`,
  and never let a request choose an arbitrary callback method.
- Validate schemes for links: reject `javascript:` and unexpected data schemes
  where the value can enter an `href` or `src`.
- For outbound HTTP, allowlist destinations where possible and set time and size
  limits; a feature that needs the network goes through `\Nino\Fetch::get()`
  (https only, no redirects), and never on a page request.
- Escape at the output context (section 5); a CSS, JS, mail header or shell
  context needs handling of its own. Do not trust a rich-text editor's client
  filtering or concatenate request data into inline scripts or styles; keep CSP
  nonces and the security headers, and add no `unsafe-inline` or remote CSP
  source merely to make one widget work.

### 8.3 Files, uploads and privacy

- Use Nino's image processing API for uploads: enforce size, decoded format,
  width/height, and an owned destination, and store generated relative
  filenames, not a browser filename or absolute path.
- Do not delete a previous image until the new value was persisted (restore or
  clean up on veto, as current image flows do). Delete only files proven to be
  owned by the exact record or slot, and never recursively delete a path
  assembled from a request. Keep secrets and runtime records out of versioned
  templates and text.
- Public login, reset, newsletter, and account-like flows return generic
  responses that do not reveal whether a record exists. Rate-limit
  authentication, public mail, signup, and expensive endpoints, and bound log
  and submission retention.
- Do not log passwords, CSRF tokens, session IDs, authorization/cookie headers,
  backup keys, full request arrays, or unnecessary personal data. Export only the
  requested content scope.
- A translation import is merge-only and validated against schema and path;
  unknown keys and the technical, global and image fields stay untouched.
- A feature that processes personal data MUST bring its section of the privacy
  policy, as the [feature recipe](docs/recipes/feature.md#4-the-install-unit) writes it.

### 8.4 Failure behavior

- Use `400` for malformed input, `401` for missing authentication, `403` for
  insufficient permission, `404` for an intentionally public missing resource,
  `409` for conflicts, `429` for rate limits, and `500` for an internal write
  failure where the operation did not complete.
- Do not report success before persistence succeeds, do not turn a recoverable
  logging failure into loss of a successfully handled public request, and do not
  swallow programming errors under a broad `catch(Throwable)`.
- A failure a person can cause carries a code with text in both interface
  languages, and only a `401` for an ended session carries the code `session`;
  see `\Nino\Http::fail()` and the [panel recipe](docs/recipes/admin-panel.md#saying-what-happened).

## 9. Common incorrect approaches and their correction

| Incorrect | Correct |
| --- | --- |
| Bind an Elements image to a textfill or a fixed value | An Elements image binds to a `field` of the collection's model |
| Prefix reusable includes with `page-`, or add metadata below markup | Reserve `page-*` for full page templates; put both metadata comments at byte zero |

## 10. Tests and validation

Every test is a standalone script that creates its own isolated temporary
project and does not rely on a previously installed working tree. The commands CI
runs - every `tests/*-smoke.php`, the JavaScript loop, PHPStan and ESLint - are one
block in [docs/deployment.md](docs/deployment.md#tests-before-go-live): run the
suite of the area you changed, and the whole block for a change to the shared
kernel, security, the filesystem, the installer or an interface two parts share.
PHPStan and ESLint MUST report nothing new: a finding listed in
`phpstan-baseline.neon` may be fixed and its entry removed, a new finding is
never added to the baseline. Report a check whose interpreter, extension,
browser capability or fixture was unavailable as not run, with the reason.

A change to what a catalogue feature reads from the kernel - the demo page and
its `data-demo-preset` marks, `Nino.css`, the base unit's templates and keys, the
feature contract - is also checked by the catalogue itself, before the push:
`NINO_ROOT=$PWD ../nino-features/bin/check.sh`, with
[dapeio/nino-features](https://github.com/dapeio/nino-features) cloned beside
it. A rename that spans both repositories is a coupled pair (section 2).

A useful smoke assertion tests what a caller can see: the exact status and a safe
response shape, the data persisted after a fresh read, two independent changes
that both survive, the denial of a missing permission or CSRF token, sanitized
rendered output, and a stable UI state after a request - on the failure branch as
well as the happy path. It does not assert only that a method exists, snapshot an
entire unstable page, check a CSS class with no behavioral reason, call a private
method instead of the public flow, or pass because errors were globally suppressed.

## 11. Definition of done

For every artifact: the syntax checks, PHPStan and ESLint pass with nothing new,
and the tests named in section 10 pass. In addition:

- [ ] The class is where the autoloader looks for it, and it answers what its kind asks for: a panel `actions()`, `nav()` and `perm()` (a runtime module's panel is returned by `adminPanels()`, a workbench one is `_admin/Nino/Modules/<Name>/Admin/Admin.php`); a runtime module `init()`, which registers behavior without output or incidental writes; a feature a `feature.php` that `\Nino\Features::manifest()` reads without a warning, with the class file beside it.
- [ ] Every panel action carries the panel's slug and guards itself with `Admin::guardPerm()` and the panel's permission; payload, IDs, enums, lengths and paths are validated; persistence is atomic and failures are returned.
- [ ] A technical route has one owner and the right callback identity; shortcode and response output is context-safe; a write enforces CSRF, auth, validation and a rate limit as applicable; shared files use atomic mutation and bounded retention.
- [ ] A panel's `assets()` names project-relative `.js`/`.css` only (through `Panels::relative()` for a module), its JS namespace equals the nav uri, and its `template()` is a fragment with no head, link or script of its own. It uses the existing UI primitives and safe DOM construction, sends requests through `Nino.adminUi.api.call()`, gives a failure a person can cause a code with text in both languages, and reports unsaved input to `Nino.admin.dirty` (feature-detected), with a save that ends in `done( ok )` on every way it can end.
- [ ] A component's schema validates - every attribute has a `default`, every `select` its `options` with the default among them - and its renderer has the signature `( array &$appData, array $args ): string` and keeps the rendering rules of section 5: it returns what it made itself and `$args['value']`, and escapes everything else through `Components::escape()`. A source that resolves to nothing renders nothing, and a hand-written shortcode with an unknown attribute still renders. A stack renders through `renderStack()` and leaves no shortcode in what it returns; a component that holds components declares `holds` and returns its content as it is inside its own markup. A component or stack is registered one way, by `init()` or by manifest, not both.
- [ ] An installer unit is the module's own `install/` directory with a unique slug as its key; the manifest uses recognized keys and installer slugs for requirements; templates, files, types and text land in exact paths; config defaults preserve existing custom values; a reapply is idempotent and a deselect deletes no copied file. `elements` only adds (a deleted section stays deleted where the module keeps a tombstone) and `navs` creates a menu only in the wizard's apply step and only while the project has none.
- [ ] A feature's `key` is a unique slug, `version` is `major.minor.patch`, `nino` names the constraint it was written against - its floor is the kernel whose vocabulary the feature uses - `requires` lists feature keys and `data` the `/data/` files it owns. Every setting has a known type and a validating default (no default on a `secret`) and is read through `\Nino\Features::setting()` with a default. The unit is add-only safe: nothing relies on overwriting, no uninstall is promised, runtime routes live in `init()`; `upgrade()` migrates only the feature's own data and is idempotent, and an owner of `data/` registers `/nino/admin/restore`.
- [ ] A page unit keeps route and template separate, with one owner each; its `page-*.tpl` filename, display metadata and VPA are valid; sections have unique semantic IDs and stable fill paths; includes omit `.tpl` and resolve to shipped or copied files; required text, modules, image slots and Element types are supplied. Every key it delivers follows the grammar: `/template/<file name without .tpl>/<part>/<name>`, `/template/common/...` only in the base unit, no `/project` or `/_nino` key, the same keys in every language, and a template whose name has a dot or an upper-case letter carries no key of its own. A page's name, title and description are proposed by the manifest's `suggest`, never by a `/_nino` key in a text file. A page template the Builder reads is written back byte for byte.
- [ ] Activation, API, shortcode, failure, denial and - for a module panel - its absence while the module is off are covered by a test; a feature's own test, `features/<Name>/tests/<key>-smoke.php`, loads `tests/harness.php` (three levels up or `NINO_ROOT`).
- [ ] The documentation says what the code now does: the manual or recipe that states a changed contract changes in the same commit, a link into it still lands on a heading, and `README.de.md` follows `README.md` - English is canonical, the German mirrors it with the same commands and paths, so a change to one is made in both. Every other document is English only.
