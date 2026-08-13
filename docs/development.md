# Nino — Developer Manual

**Language:** English · [Deutsch](development.de.md)

**Last updated:** August 8, 2026 · **Nino version:** 0.11.0-beta.1

This manual describes the technical work with Nino — from the entry point through routing and rendering to custom modules, persistent data, and tests. If you instead want to first learn about the architecture or set up a fresh project, read the [Concepts](concepts.md) or [Getting Started](getting-started.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Developer Profile:** For simple websites, solid knowledge of HTML, CSS, and JavaScript as well as PHP basics is sufficient. Templates consist of HTML+, i.e., HTML with textfills and shortcodes. Only custom application logic, external interfaces, or new modules require deeper PHP knowledge. A finished project can then be largely maintained via `/_admin`, `/_templates`, and `/_editor`.

---

## Entry Point and Runtime Model

Every public request goes through three calls:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

These few lines represent the entire runtime:

1. `init()` builds `$appData`, starts the session, and initializes the kernel and modules.
2. `request()` normalizes the request, resolves the route, and renders the response via the kernel and registered callbacks.
3. `output()` sends status, headers, and body, and ends the script.

There is no subsequent teardown phase. Everything that needs to be permanently preserved before the end must be explicitly written to the file system.

### `$appData` and `$request`

Nino separates the state of the application from the state of a single HTTP operation:

- `$appData` contains configuration, registered callbacks, caches, the current user, and other runtime data.
- `$request` contains the normalized input and the resulting response.

Both arrays are passed by reference. This keeps it visible which method reads or modifies data; a hidden container or global service locator is not necessary.

In `$appData`, the following convention applies:

- Keys under `/...` belong to the stable configuration and data space, e.g., `/nino/http/routes`.
- Keys under `./...` apply only to the current PHP lifecycle, e.g., `./nino/locales/current`.

This notation does not automatically decide persistence. Even a `/...` value is only persistent if a suitable write method explicitly saves it.

The relevant part of `$request` looks simplified like this:

```php
[
    '/nino/http/request' => [
        'method' => 'GET',
        'uri'    => '/contact',
        'query'  => [],
        'header' => [],
        'body'   => '',
        'ip'     => '127.0.0.1',
    ],
    '/nino/http/response' => [
        'uri'        => '/contact',
        'locale'     => 'de_DE',
        'header'     => [],
        'body'       => '',
        'statusCode' => 200,
    ],
]
```

### Configuration Outside the Document Root

Without an override, Nino reads `config.php` from `private/`. To move the complete private tree or only this file outside the publicly accessible directory, define the path before loading the kernel:

```php
define( 'NINO_CONTENT_DIR', '/var/www/private/nino-example' );
// Or, to move config.php alone:
// define( 'NINO_CONFIG_DIR', '/var/www/private/nino-example' );
require_once __DIR__. '/_nino/Nino.php';
```

Use `NINO_CONTENT_DIR` for the complete private tree and `NINO_CONFIG_DIR` only for a separate `config.php`. Each target must exist and be writable for necessary write operations.

---

## Request/Response Lifecycle in Detail

### 1. `\Nino\init()`

`init()` executes the core components in a fixed order:

```php
AppData::prepare( $appData );
AppData::prepareSession( $appData );
Runtime::init( $appData );
Filesystem::init( $appData );
AppData::init( $appData );
Locales::init( $appData );
Csrf::init( $appData );
Auth::init( $appData );
Modules::callModules( $appData, 'init' );
```

The order is part of the runtime contract:

- `AppData::prepare()` sets up the internal runtime areas of `$appData`.
- `AppData::prepareSession()` provides the session configuration before PHP starts the session.
- `Runtime::init()` sets up PHP error handling and starts or takes over the session.
- `Filesystem::init()` determines the project and configuration path and initializes the file cache.
- `AppData::init()` loads `config.php` into `$appData`.
- `Locales`, `Csrf`, and `Auth` determine language, CSRF state, and current user.
- Only then does `Modules::callModules()` initialize the modules registered under `/nino/modules`.

Thus, a module can rely on the core functions and the loaded configuration. Conversely, the basic initialization must not depend on an optional module.

### 2. `\Nino\request()`

Processing a request consists of four steps:

```php
Http::request( $appData, $request );
Http::response( $appData, $request );
Locales::response( $appData, $request );
Html::addFills( $appData, [ /* runtime values */ ], '*' );
Html::response( $appData, $request );
```

`Http::request()` does not read directly into arbitrary project variables but normalizes method, URI, query, header, body, basic auth data, and client IP under `/nino/http/request`. Simultaneously, a response is created with an empty body, status `200`, and the preset security headers.

`Http::response()` searches for a matching route under `/nino/http/routes`, takes over its values into the prepared response, and then executes the global and route-specific response callbacks. `Locales::response()` takes over the language resolved by the route. After that, Nino adds runtime textfills such as request URI, response URI, locale, current user, `/nino/dir`, and the current year.

`Html::response()` renders the body only if it is a string. Arrays and other structured values remain unchanged and are later output as JSON.

### 3. `\Nino\output()`

`Http::output()` finalizes the response:

- Non-string bodies are JSON-encoded and receive a suitable `Content-Type`.
- Project and standard headers are merged.
- Status code and headers are sent.
- For `HEAD`, the body is not output.
- Then the script ends with `exit`.

> **No direct output during runtime:** `echo`, `header()`, and `http_response_code()` break Nino's fundamental concept and must not be used in the regular request/response lifecycle. They bypass the common response path and can corrupt headers, JSON responses, and tests. Instead, modify `/nino/http/response`.

---

## Routing and Responses

Routes are located under `/nino/http/routes` in `config.php`. The public key consists of method, colon, and URI:

```php
'/nino/http/routes' => [
    'GET://' => [
        'uri'  => '/home',
        'body' => '[template /templates/page-home]',
    ],
    'GET://contact' => [
        'uri'    => '/contact',
        'locale' => 'de_DE',
        'body'   => '[template /templates/contact]',
    ],
    'POST://api/example' => [
        'uri'  => '/api/example',
        'csrf' => true,
    ],
]
```

The array key describes the route requested from outside. The `uri` field is its internal identity. This separation is useful when multiple public URLs should show the same behavior or a localized route should remain internally stable.

A route can provide the following fields, among others:

| Field | Meaning |
| --- | --- |
| `uri` | internal identity of the response |
| `body` | string for HTML or structured value for JSON |
| `statusCode` | HTTP status code |
| `header` | additional response headers |
| `locale` | language resolved for this route |
| `csrf` | explicitly control CSRF check for this route |

If Nino cannot resolve a route, `GET://404` is used. If this route is also missing, a minimal `404` response is created.

Wildcard routes end with `/*`. For a request to `/blog/entry`, Nino also looks for a missing exact route step by step in parent paths, e.g., `GET://blog/*`. The internal `uri` of the found route remains the fixed anchor for callbacks and rendering.

### Example: Modify a Response with a Callback

```php
\Nino\Callbacks::registerCallback(
    $appData,
    '/nino/http/response/GET://api/example',
    static function( array &$appData, array &$request ): void {
        \Nino\Http::ok( $request, [
            'version' => \Nino\VERSION,
            'status'  => 'ready',
        ] );
    }
);
```

`Http::ok()` sets the body of a successful response. `Http::fail()` sets the status code and a uniform `error` field. Both modify the passed `$request` directly and are more readable than manually setting all fields, especially for JSON routes.

Route-specific callbacks use the **internal response URI**:

```text
/nino/http/response/<METHOD>:/<response-uri>
```

For `GET` and the internal URI `/contact`, the name is therefore `/nino/http/response/GET://contact`. It is only executed after the general callback `/nino/http/response`.

---

## Callbacks: The Common Extension Mechanism

Kernel, modules, and project code communicate via named callbacks:

```php
\Nino\Callbacks::registerCallback(
    array &$appData,
    string $name,
    mixed $callback,
    int $prio = 5
): void;

\Nino\Callbacks::doCallbacks(
    array &$appData,
    string $name,
    mixed &$args = null
): mixed;
```

Priorities range from `0` to `9`; lower values run first. Invalid values are set to `5`. Each handler receives `$appData` and `$args` by reference:

```php
static function( array &$appData, mixed &$args ): mixed {
    // read, modify, or return a new value
    return $args;
}
```

A return value not equal to `null` replaces `$args` for the next handler. `null` leaves the value already modified by reference unchanged.

The exact semantics are important: The callback chain does not have a general abort value. Even after `false`, further handlers run. `false` only acts as a veto where the calling code explicitly checks this result, e.g., before certain element write operations. Security logic such as CSRF protection therefore sets a clear state in `$request` instead of relying on an apparent abort of the chain.

Callback paths under `/nino/*` are reserved for the kernel and the included modules. For project-specific events, use your own namespace:

```php
\Nino\Callbacks::doCallbacks( $appData, '/project/catalog/import', $rows );
```

This keeps it clear which events belong to the kernel and which are part of the project.

---

## Persistent Data and Concurrent Write Access

Nino stores all content in the file system. This is easy to secure and transfer but makes a clear structure and controlled write operations particularly important.

### Save Configuration Targetedly

Which content belongs in `config.php`, `text/`, `elements/`, `templates/`, and `data/` is shown in the section [Persistent Project Data](concepts.md#persistent-project-data). For development, it is especially important that loaded values are not automatically written back.

`config.php` is loaded into `$appData` at startup. Selected top-level keys can be saved targetedly:

```php
\Nino\AppData::writeContentData( $appData, [
    '/nino/http/routes',
    '/nino/locales',
] );
```

The method reads the current file state again, only takes over the specified keys, and then writes atomically. Auth sessions are additionally merged via a three-way comparison so that parallel logins or logouts do not unnoticedly overwrite each other's state.

### `Filesystem`

`Filesystem` encapsulates path resolution, serialization, cache, locks, and atomic write operations:

- `.php` files are saved as `<?php return ...;` and read via `include`.
- `.json` files are JSON-encoded and decoded.
- Read accesses are cached based on modification time and file size.
- Write operations first create a temporary file in the target directory and then replace the target via `rename()`.
- Locks are sidecar files under `/data/.locks`; their name is derived from the target path.
- Paths with `..` are rejected as an additional protective layer.

For a simple, complete replacement, `putFileContent()` is sufficient:

```php
\Nino\Filesystem::putFileContent(
    $appData,
    '/data/example.php',
    [ 'updated' => time() ]
);
```

For a read-modify-write operation, `mutate()` should always be used:

```php
\Nino\Filesystem::mutate(
    $appData,
    '/data/counter.php',
    static function( array $current ): array {
        $current['value'] = (int) ( $current['value'] ?? 0 ) + 1;
        return $current;
    },
    [ 'value' => 0 ]
);
```

`mutate()` locks the file, discards a possibly outdated cache entry, reads the current state, executes the callback, and writes back atomically. If the callback returns `null`, the write operation is discarded.

The manual pattern "read, modify, write" is unsafe with parallel requests: Two processes can read the same initial state, and the last writer loses the other's change.

---

## Rendering: From HTML+ to HTML

The central method is:

```php
$html = \Nino\Html::renderHtml( $appData, $html );
```

It performs three processing steps:

1. Replace textfills.
2. Resolve shortcodes.
3. Execute callbacks under `/nino/html/render`.

### Textfills

Textfills are placeholders with double square brackets:

```html
<title>[[/webpage/meta/title]]</title>
<p>[[/contact/intro]]</p>
```

Nino combines:

1. global values from `/text/global.php`,
2. values of the current locale from `/text/<locale>.php`,
3. runtime values added with `Html::addFills()`.

Fills can contain other fills. Nino therefore repeats the replacement until the entire string no longer changes, but at most ten passes. This allows controlled nesting without a cyclic fill blocking the runtime indefinitely.

Runtime values can be specifically added:

```php
\Nino\Html::addFills( $appData, [
    '/project/catalog/count' => 42,
], '*' );
```

The third parameter denotes the language scope. `'*'` stands for language-independent values.

### Shortcodes

Shortcodes integrate behavior and structured content into templates:

```html
[template /templates/header]

[element /team/ada]
    <article>
        <h2>[[name]]</h2>
        <p>[[description]]</p>
    </article>
[/element]
```

A shortcode can have positional and named arguments as well as enclosed content:

```text
[example first limit="3"]Content[/example]
```

The handler receives `first` as `$args[0]`, `3` as `$args['limit']`, and the content as `$args['content']`. It is registered with:

```php
\Nino\Html::addShortcode( $appData, 'example', 'Project\\Example::shortcode' );
```

The output of a shortcode is sent through `renderHtml()` again. Therefore, templates, textfills, and shortcodes can be nested within each other. The maximum render depth is 20 levels; after that, Nino stops further recursion.

### Elements in Templates

The shortcodes `[element]` and `[elements]` load structured content. Within their block, fields are addressed with `[[field]]`; `[[.id]]` contains the internal element ID.

```html
[elements /services limit="6" query="featured=1"]
    <article id="service-[[.id]]">
        <h2>[[title]]</h2>
        <p>[[description]]</p>
    </article>
[/elements]
```

Normal field values are HTML-encoded. A field released in the model with `html => true` may only contain a limited, sanitized amount of inline HTML. The protection deliberately takes place in the Elements module: Element placeholders are local data of the respective block and not part of the global textfill space.

### Assets Are Not Templates

The assets shortcode bundles and caches CSS or JavaScript:

```php
'/nino/html/assets' => [
    '/.cache/site.min.css' => [
        '/assets/reset.css',
        '/assets/site.css',
    ],
    '/.cache/site.min.js' => [
        '/assets/site.js',
    ],
],
```

The respective target names are then included as shortcodes:

```html
[assets /.cache/site.min.css]
[assets /.cache/site.min.js]
```

Only if the target name ends with `.min` is it additionally minified. The cache considers path, size, and modification time of the source files.

Assets deliberately **do not** go through the full HTML+ engine. Only the secure directory path `[[/nino/dir]]` is replaced. This prevents editorial textfills or shortcodes from inadvertently generating executable CSS or JavaScript code.

### Final HTML Callbacks

After fills and shortcodes, the kernel callback `/nino/html/render` runs. The registered methods and functions receive the finished string and can finally modify it:

```php
\Nino\Callbacks::registerCallback(
    $appData,
    '/nino/html/render',
    static function( array &$appData, string &$html ): string {
        return str_replace( '<html>', '<html data-project="example">', $html );
    },
    8
);
```

This hook is suitable for clearly limited, global post-processing. Project logic and content queries belong in modules or shortcodes.

---

## Important Kernel APIs

The following overview is a working reference, not a complete listing of every internal method.

| Class | Important Public Tasks |
| --- | --- |
| `AppData` | Prepare basic state, load `config.php`, save selected keys with `writeContentData()` |
| `Auth` | Login, logout, user management, session revocation, and permission checking |
| `Callbacks` | Register and execute callbacks |
| `Csrf` | Read/rotate tokens and check requests |
| `Filesystem` | Read/write files, resolve paths, lock, and atomically mutate |
| `Backup` | Process encrypted backup manifests |
| `RotatingLog` | Clean dated log files after retention period |
| `Elements` | Load individual elements, query, create, modify, and delete types and elements |
| `Html` | Register fills and shortcodes, render HTML+, and sanitize allowed inline HTML |
| `Http` | Normalize requests, resolve routes, create and output responses |
| `Images` | Process uploads, manage variants, and generate URLs |
| `Locales` | Manage current, native, and available languages |
| `Text` | Read text definitions, lock, and save in batch |
| `Mail` | Send emails via project configuration |
| `Modules` | Load and initialize released modules |
| `Runtime` | Provide session and error handling |

Use the public methods instead of internal implementations starting with `_`. This keeps project code decoupled from details like cache invalidation, file format, and session rotation.

---

## Integrated Modules

Modules are activated in `/nino/modules`. The order of the array is relevant if multiple modules register callbacks of the same priority.

| Module | Integration | Key Features |
| --- | --- | --- |
| `Assets` | `[assets ...]` | bundles, caches, and optionally minifies CSS/JS |
| `Csrf` | `[csrf]` | renders a hidden token field; core protection itself is always active |
| `Elements` | `[element ...]`, `[elements ...]` | loads typed content; lists support `limit`, query, and optional callback |
| `Form` | `POST://.form` | validates contact forms, uses honeypot and rate limit, sends emails, and logs successful submissions |
| `Images` | `[image ...]` | creates an escaped `<img>` from an image slot or URI |
| `Jstext` | `[jstext]` | provides text values as securely encoded JSON with CSP nonce |
| `Localepicker` | `[localepicker ...]` | switches locale via query and redirect |
| `Navigation` | `[navigation ...]` | renders navigations from a compact line syntax |
| `Newsletter` | POST/GET under `/.newsletter` | double opt-in, confirmation, and unsubscribe without public address disclosure |
| `Template` | `[template /path/name]` | loads the raw content of a `.tpl` file; the common render pipeline processes it further |

Some details are deliberately defensive:

- The form limits inputs, protects write operations, and discards old log months.
- The public newsletter signup responds independently of whether an address is new or already known. This makes it harder to query foreign addresses.
- `Jstext` uses JSON hex escaping and adds a random nonce to the Content Security Policy.

---

## Developing a Custom Module

### Directory and Autoloading

Nino's autoloader maps namespaces directly to directories. For the class `Project\Catalog\Catalog`, the following file is expected:

```text
/_nino/Project/Catalog/Catalog/Catalog.php
```

The basename of the class and the PHP file must match. Class paths are limited to allowed characters; dynamically composed or user-controlled class names still do not belong in the module list.

### Example: Minimal Module

```php
<?php

namespace Project\Catalog;

class Catalog {

    public static function init( array &$appData ): void {
        \Nino\Html::addShortcode(
            $appData,
            'catalog-count',
            self::class. '::shortcodeCount'
        );

        \Nino\Callbacks::registerCallback(
            $appData,
            '/nino/http/response/GET://api/catalog',
            self::class. '::responseCatalog'
        );
    }

    public static function shortcodeCount( array &$appData, array &$args ): string {
        $rows = \Nino\Filesystem::getFileContent(
            $appData,
            '/data/catalog.php',
            []
        );

        return (string) count( $rows );
    }

    public static function responseCatalog( array &$appData, array &$request ): void {
        \Nino\Http::ok( $request, [
            'items' => \Nino\Filesystem::getFileContent(
                $appData,
                '/data/catalog.php',
                []
            ),
        ] );
    }
}
```

Subsequently, the class is released in `config.php`:

```php
return [
    '/nino/modules' => [
        // integrated modules ...
        '\\Project\\Catalog\\Catalog',
    ],

    '/nino/http/routes' => [
        // existing routes ...
        'GET://api/catalog' => [
            'uri' => '/api/catalog',
        ],
    ],
];
```

A good module adheres to four rules:

1. `init()` registers behavior but does not produce output.
2. HTTP handlers modify the central response or use `Http::ok()`/`Http::fail()`.
3. Templating remains in shortcodes and `.tpl` files; PHP does not output page fragments unplanned via `echo`.
4. Variable files are protected during read-modify-write with `Filesystem::mutate()`.

### Secure Custom Write Operations

For writing routes, CSRF is active by default. A deliberate `csrf => false` is only useful for endpoints that have another verifiable authentication mechanism, such as signed webhooks. The exception belongs to the route and should be justified in the code.

Additionally, the handler should:

- strictly limit method and expected input format,
- limit input lengths before expensive processing,
- check permissions with `Auth::checkPermission()`,
- not return internal error messages or file paths to clients,
- and provide a project-related rate limit for sensitive actions.

---

## Separate Entry Points

`/_admin`, `/_templates`, `/_editor`, and `/_install` use the same kernel as the frontend but each have their own `index.php`. After `\Nino\init()`, the entry point initializes its area and then hands over to the common request/response lifecycle again.

```php
$appData = \Nino\init();
\Nino\Admin\Admin::init( $appData );
\Nino\Templates\Templates::init( $appData );
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

The example shows the entry point of `/_templates`; the other areas initialize their own classes accordingly. The areas are not regular modules from `/nino/modules`:

- `/_install` creates the first project state and then locks itself;
- `/_admin` provides full access to technical structure as well as texts and elements;
- `/_templates` creates and composes `page-*.tpl` files from complete HTML and template sections and quick-fills native content;
- `/_editor` maintains content and operational data within account permissions.

`/_templates` integrates admin authentication and shares password, lock status, and session with `/_admin`. If `_admin/` is removed from a delivery, the builder is therefore also unavailable. The planned `/_themes` is not yet an entry point in the current source state.

---

## Error Handling and Logs

`Runtime` registers a common handler for PHP errors and exceptions. Deliberately triggered notices, warnings, and deprecation messages can be logged without ending the request. Exceptions, engine errors, and `E_USER_ERROR` lead to a `500` response.

The behavior is controlled in `config.php`:

```php
'/nino/error/log'     => true,
'/nino/error/display' => false,
```

With active logging, Nino writes monthly files under `/data/logs.<Y-m>.php` and removes entries outside the three-month retention period. Display should only be activated in a protected development environment. Even there, the backtrace hides function arguments so that passwords, session tokens, and request headers do not accidentally appear on the error page.

Error logging is not a substitute for controlled return values: An expected technical error should be treated as an appropriate `4xx` response. The global handler is intended for unexpected technical conditions.

---

## Security Model for Developers

Security in Nino arises from a few central rules that continue to apply to project code.

### CSRF

Non-reading methods are checked by default. `GET`, `HEAD`, and `OPTIONS` are considered safe. The token can be read from a form field `_csrf`, the header `X-CSRF-Token`, or a JSON body.

The shortcode

```html
[csrf]
```

creates the hidden form field. However, the actual protection belongs to the kernel and remains active even if the optional rendering module is not used.

### Sessions and Login

Nino starts sessions in strict mode. Session cookies are `HttpOnly`, use `SameSite=Lax`, and are set via HTTPS as `Secure`. Behind a TLS-terminating proxy, the Secure flag can be enforced with `/nino/session/force-secure-cookie`. A successful login renews the session ID and the CSRF token.

Authentication is additionally protected by:

- a dummy password hash against measurable differences with unknown users,
- failure limits per account and IP,
- automatic rehashing of outdated password hashes,
- random session tokens with limited runtime,
- and the ability to revoke all sessions of a user.

### Response Headers

Each response starts with central security headers, including:

- `Strict-Transport-Security`,
- `Content-Security-Policy`,
- `X-Frame-Options: SAMEORIGIN`,
- `X-Content-Type-Options: nosniff`.

Project code may specifically extend these headers. It should not generally replace or weaken them just to get a messy inline integration working.

### Outputs and Uploads

- Element fields are HTML-encoded or sanitized if explicitly allowed HTML.
- `Jstext` transfers data JSON-encoded and CSP-bound into JavaScript.
- The newsletter does not reveal whether an email address already exists.
- Image processing limits uploads to 8 MiB and source files to 20 million pixels before memory-intensive processing begins.
- PHP data files in publicly accessible directories receive protection stubs or only return values.

These precautions do not absolve project code of responsibility. Data from requests, files, or external APIs remains untrusted until it is validated for its specific target context and safely output.

---

## Tests and Change Workflow

Nino uses standalone smoke tests without PHPUnit. Each test creates an isolated temporary project and checks a different area:

| Test | Focus |
| --- | --- |
| `tests/kernel-smoke.php` | Kernel, routing, rendering, auth, filesystem, and modules |
| `tests/editor-smoke.php` | Editor routes, permissions, backups, logs, and content operations |
| `tests/admin-smoke.php` | Admin authentication and technical management functions |
| `tests/install-smoke.php` | Installation steps, generated structure, and self-lock |
| `tests/templates-smoke.php` | section composition, template includes, lossless page frames, content quick fill, and save conflicts |
| `tests/*-js-smoke.js` | browser-like logic of management interfaces and template builder |
| `tests/concurrency-smoke.php` | parallel and atomic write operations |

Locally, they are executed individually:

```bash
php tests/kernel-smoke.php
php tests/editor-smoke.php
php tests/admin-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

The GitHub Actions pipeline uses PHP 8.4, runs these PHP and JavaScript smoke tests, and supplements them with syntax checks across all PHP and JavaScript files.

For changes to the kernel or a module, the following workflow is recommended:

1. First reproduce or specify the behavior in the appropriate smoke test.
2. Implement the smallest possible change.
3. Run all PHP and JavaScript smoke tests as well as the syntax checks.
4. For changes to routes, files, or callbacks, also adjust the corresponding documentation.
5. Explicitly justify security-relevant exceptions such as `csrf => false`, unlocked HTML, or additional CSP sources.

Avoid assertions that only fix internal intermediate steps. A good smoke test checks the visible contract: response, saved data, permissions, lock behavior, or generated project structure.

---

## Callback Reference

The following table lists the most important hooks used by the kernel and integrated modules. Additional, area-specific hooks can be found directly in the respective source code.

| Callback | Argument | Purpose |
| --- | --- | --- |
| `/nino/http/request` | complete `$request` | supplement normalized request before routing |
| `/nino/http/response` | complete `$request` | global processing of every response; CSRF, among others, applies here |
| `/nino/http/response/<METHOD>:/<uri>` | complete `$request` | behavior of a resolved route |
| `/nino/html/shortcode/<name>` | shortcode arguments | handler of a registered shortcode |
| `/nino/html/render` | HTML string | final global post-processing of rendered HTML |
| `/nino/shortcodes/assets/output/<extension>` | link or script template | adapt HTML of the assets shortcode for a file type |
| `/nino/auth/login` | user data | react to a successful login |
| `/nino/auth/logout` | user data | react to a logout |
| `/nino/auth/user/{insert\|update\|delete}` | user data | supplement changes to user accounts |
| `/nino/elements<type-uri>/insert` | element type data | check insertion into a type or reject with `false` |
| `/nino/elements<type-uri>/update` | element type data | check modification in a type or reject with `false` |
| `/nino/elements/delete<type-uri>` | element type data | check deletion from a type or reject with `false` |
| `/nino/elements<type-uri>/update/uri` | element data | react to a change in element URI |

Callback names are simple strings. Still, treat the established names and argument forms like an API: A rename or changed argument type can affect every registered module.

---

## Further Manuals

- [Concepts](concepts.md) explains the core pillars and the overall technical context.
- [Getting Started](getting-started.md) guides from checkout to configured project.
- [`/_install` Reference](_install.md) documents all installation steps and writing rules.
- [`/_admin` Operation](_admin.md) describes full technical and content access.
- [`/_templates` Operation](_templates.md) explains the structural template builder in Alpha status.
- [`/_editor` Operation](_editor.md) explains editorial work and the permission model.
- [Deployment](deployment.md) describes web servers, security, and go-live.
- [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) explains how to handle security reports.
