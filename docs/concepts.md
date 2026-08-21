# Concepts

**Language:** English · [Deutsch](concepts.de.md)

**Last updated:** August 21, 2026 · **Nino version:** 0.11.0-beta.1

This manual explains the architecture of Nino and the interaction of configuration, data, templates, and modules. If you instead want to set up a website directly, start with [Getting Started](getting-started.md); concrete APIs and implementation details are in the [Developer Manual](development.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [`/_theme` Operation](_theme.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Core Pillars

Nino organizes a website with only a few but clearly separated components:

- `config.php` defines the technical structure of the website;
- `.php` files under `text/` and `elements/` contain multilingual editorial data;
- `.tpl` files under `templates/` provide the HTML;
- `data/` contains the operational data of the running system;
- PHP modules extend the process via callbacks.

The following paths describe the configured project state; before completing `/_install`, they do not yet exist.

The architecture follows four fundamental decisions:

| Pillar | Technical Consequence |
|---|---|
| **No Dependencies** | Nino does not require external runtime packages, no Composer bootstrap, and no database. Kernel and included modules are part of the project; only PHP and the documented extensions are needed. |
| **File-Based Project** | Configuration, texts, elements, and templates are stored as readable files; assets also remain part of the file system. The project can be transferred together, versioned with Git, and checked without database tools. |
| **Central Application State (`$appData`)** | The central application state is in an array. `init()` creates `$appData` once; kernel and callbacks then receive it by reference and specifically modify their respective area. The HTTP request and the resulting response remain separate in `$request`. |
| **Separation of Logic and Presentation** | `.tpl` files contain HTML, textfills, and shortcodes, but no PHP. Modules and callbacks handle the logic; shortcodes form the controlled connection to the template. |

These decisions interlock: Files provide the permanent project state, `$appData` handles the state of a request, and a fixed process generates the HTTP response from it.

The file-based approach is deliberately designed for classic websites and manageable structured content. Large relational data sets, complex queries, or transactional processes belong in a database or external service that a project-specific module can connect to.

## Data Flow of a Request

An HTTP request starts in `index.php` and always goes through the same three steps:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

| Step | Task |
|---|---|
| `init()` | prepares runtime values, loads `config.php`, initializes language, session, and protection mechanisms, and calls activated modules |
| `request()` | normalizes the HTTP request, resolves the route, passes `$appData` and `$request` through callbacks, and renders the response body |
| `output()` | sets status and headers, serializes JSON if needed, and sends the response |

`$appData` contains configuration, modules, callback registry, and runtime values. `$request`, on the other hand, contains the normalized HTTP request under `/nino/http/request` and the resulting response under `/nino/http/response`.

There is no additional dependency injection container. Instead of an object graph, kernel functions and callbacks receive `$appData` and—where required—`$request` and only modify their respective part of the process.

## `$appData`: Stable and Temporary Keys

Keys in `$appData` follow a path convention:

```php
$routes = $appData['/nino/http/routes'] ?? [];
$locale = $appData['./nino/locales/current'] ?? '';
```

| Notation | Meaning | Example |
|---|---|---|
| `/...` | stably named project or kernel value | `/nino/locales/native` |
| `./...` | temporary value of the current request | `./nino/locales/current` |

The convention distinguishes stably named application values from explicitly temporary runtime values.

**Important:** Even a stably named value is not automatically saved. Changes to values from `config.php` initially only apply to the current request; only `\Nino\AppData::writeContentData()` writes them back. Textfills and elements have their own file APIs.

## Routing

Routes are in `config.php` under `/nino/http/routes`. The array key connects HTTP method and browser path:

```php
'/nino/http/routes' => [
    'GET://' => [
        'uri'  => '/home',
        'body' => '[template /templates/page-home]',
    ],
],
```

`GET://` denotes the homepage: `GET` is the method, the second slash is the HTTP URI `/`. For `/contact`, the key is `GET://contact` accordingly.

The route separates the public address from the internal page identity. The homepage is accessible in the browser under `/`, but can internally be called `/home`. Text keys like `/webpage/home/title` remain stable even if only the public path changes.

In addition to `uri` and `body`, a route can contain, for example, status code, headers, or a fixed language. If there is no match for a request, Nino uses the configured route for `/404`.

## Persistent Project Data

Nino separates persistent data by its task:

| Location in the configured project | Content |
|---|---|
| `config.php` | routes, modules, languages, users, and technical options |
| `text/global.php` | language-independent textfills |
| `text/<locale>.php` | textfills of one language |
| `elements/*.php` | type models and entries of recurring content |
| `templates/*.tpl` | HTML structure for pages, components, and emails |
| `images/` and `assets/` | editorial images and static frontend files |
| `data/` | form submissions, newsletter data, and other operational data written during operation |

A page title thus belongs in `text/`, a route in `config.php`, news posts, a portfolio, or team members in `elements/`, and the visible HTML structure in `templates/`.

## Templates and Rendering

The route body from `config.php` is initially a simple string. Before output, it is rendered through the Nino template engine. With the corresponding shortcode, it can refer to a template file:

```text
[template /templates/page-home]
```

The loaded `.tpl` file can contain shortcodes and textfills in addition to HTML:

```html
[template /templates/html-header]
<main>
  <h1>[[/webpage/home/title]]</h1>

  [elements /services]
    <article>
      <h2>[[title]]</h2>
      <p>[[description]]</p>
    </article>
  [/elements]
</main>
[template /templates/html-footer]
```

This way, multiple template files can be combined into a complex HTML structure.

Page templates can be composed from complete HTML and `[template]` sections via the optional [Template Builder `/_templates`](_templates.md). It saves normal, readable `.tpl` markup and does not replace testing the finished website in the browser; lower-level structure work remains possible through HTML+ or code.

Nino processes an HTML string in a fixed order during each rendering pass:

1. Textfills are replaced (`[[/webpage/home/title]]`).
2. Registered shortcodes are executed (`[template /templates/html-header]`).
3. Registered callbacks under `/nino/html/render` receive the result.

**Textfills** are individual global or language-dependent values. They are suitable for page titles, descriptions, and other text content at a fixed location.

**Elements** represent recurring content according to a predefined type model, such as services, team members, or references. Developers define the fields and can edit all entries via `/_admin`; editors maintain the entries released to them via `/_editor`.

**Shortcodes** connect templates with dynamic logic as needed. For example, they load a template, output elements, or call a project-specific function. Developers can register their own shortcodes; their technical callback signature is described in the Developer Manual. The output can in turn contain textfills and further shortcodes.

## Callbacks and Modules

Callbacks are the common extension mechanism of Nino. A named callback path clarifies at which point a registered function is executed. `$appData` and the passed callback parameter are passed through all registered functions when called.

A module bundles the callbacks, shortcodes, and modifications of a single feature. In development, extensions should therefore be sensibly distributed across individual modules. Activated module classes are in `config.php` under `/nino/modules` and are called during `init()`. Navigation, language selection, forms, and template integration follow the same principle.

Project-specific functions use their own callback paths and modules instead of
modifying kernel files. Their PHP classes belong in `app/` under a project
namespace; the kernel-owned `Nino\` classes remain in `_nino/`. This way, the
extension remains part of the specific project, `_nino/` stays replaceable, and
no second hook or plugin system is introduced.

## `/_admin`, `/_templates`, and `/_editor`

The optional interfaces have their own entry points and are not frontend modules from `/nino/modules`:

| Interface | Responsibility |
|---|---|
| [`/_admin`](_admin.md) | full technical access to structure, configuration, texts, and elements |
| [`/_templates`](_templates.md) | section-first composition, reusable template includes, and native quick fill of `page-*.tpl` |
| [`/_editor`](_editor.md) | daily maintenance of released content, images, user, and operational data |

`/_templates` shares password and session with `/_admin`; `/_editor`, on the other hand, has individual accounts and granular rights. Thus, the separation is no longer solely between structure and content but primarily between full development access and restricted editorial work.

`/_theme` complements these tools with the design layer: it generates the color and size tokens every stylesheet reads from, and remains usable after an installation.

## Where Does a Change Belong?

| Task | Suitable Location |
|---|---|
| Change page title | textfill in `text/` or via `/_editor` |
| Add new team member | element via `/_editor` |
| Compose a page from complete sections | `/_templates` (Alpha) |
| Change lower-level HTML structure | HTML+ escape hatch or `.tpl` file in `templates/` |
| Create new public URL | route in `config.php` or via `/_admin` |
| Output dynamic list | element query or shortcode with callback |
| Add technical function | project-specific module |
| Change colors or typography | `/_theme` for the design tokens; stylesheets for everything beyond them |

## Next Steps

- [Getting Started](getting-started.md) guides through the necessary initial setup.
- [`/_admin` Operation](_admin.md) explains full technical and content access.
- [`/_templates` Operation](_templates.md) explains the structural template builder in Alpha status.
- [`/_editor` Operation](_editor.md) accompanies permission-controlled content maintenance.
- [Deployment](deployment.md) describes the path from the local website to secure operation.
