# Nino — Developer Manual

**Language:** English · [Deutsch](development.de.md)

**Last updated:** September 7, 2026 · **Nino version:** 1.0.0-beta

This manual describes the technical work with Nino — from the entry point through routing and rendering to custom modules, persistent data, and tests. If you instead want to first learn about the architecture or set up a fresh project, read the [Concepts](concepts.md) or [Getting Started](getting-started.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Templates Panel](templates.md) · [Design Panel](appearance.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Developer Profile:** For simple websites, solid knowledge of HTML, CSS, and JavaScript as well as PHP basics is sufficient. Templates consist of HTML+, i.e., HTML with textfills and shortcodes. Only custom application logic, external interfaces, or new modules require deeper PHP knowledge. A finished project can then be largely maintained in the workbench, `/_admin`.

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
define( 'NINO_PRIVATE_DIR', '/var/www/private/nino-example' );
// Or, to move config.php alone:
// define( 'NINO_CONFIG_DIR', '/var/www/private/nino-example' );
require_once __DIR__. '/_nino/Nino.php';
```

Use `NINO_PRIVATE_DIR` for the complete private tree and `NINO_CONFIG_DIR` only for a separate `config.php`. Each explicitly configured target must exist and be writable; an invalid path is not silently replaced. Every entry point boots the kernel on its own - `index.php`, `_admin/index.php` and `_admin/recovery.php` - so define the constants you use in all three, with the same values: one defined in the site's `index.php` alone leaves the workbench on the default path, where it finds no `config.php` and offers the setup wizard.

Project-owned PHP classes use a separate source root. It is `app/` in the
project directory by default. Define `NINO_APP_DIR` as an absolute directory
path before loading the kernel when those classes live elsewhere. The root is
replaced as a whole. Installed features have a root of their own, `features/`,
which `NINO_FEATURES_DIR` relocates the same way - and replaces as a whole
too: a project that points it elsewhere moves its features along, or the
kernel skips a module it can no longer load without a word.

```php
define( 'NINO_APP_DIR', '/var/www/nino-example-app' );
define( 'NINO_FEATURES_DIR', '/var/www/nino-example-features' );
require_once __DIR__. '/_nino/Nino.php';
```

This changes only the two source roots. It does not move project data, and it
never changes where classes in the kernel-owned `Nino\` namespace - Nino's
own modules among them - are loaded from.

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

Processing a request consists of these steps:

```php
Http::request( $appData, $request );
Html::addFills( $appData, [ /* values that depend on $appData alone */ ], '*' );
Http::response( $appData, $request );
Locales::response( $appData, $request );
Html::addFills( $appData, [ /* values that depend on the request */ ], '*' );
Html::response( $appData, $request );
```

`Http::request()` does not read directly into arbitrary project variables but normalizes method, URI, query, header, body, basic auth data, and client IP under `/nino/http/request`. Simultaneously, a response is created with an empty body, status `200`, and the preset security headers.

The runtime textfills are added in two passes, and the split matters. `/nino/dir`, `/nino/public`, and `/date/year` answer to `$appData` alone, so they are registered **before** `Http::response()`: a response callback that renders a template is a real caller — `Modules\Form` and `Modules\Newsletter` build their HTML mails in exactly that window, and a fill registered after it would reach them as the literal `[[/nino/public]]`.

`Http::response()` searches for a matching route under `/nino/http/routes`, takes over its values into the prepared response, and then executes the global and route-specific response callbacks. `Locales::response()` takes over the language resolved by the route. Only after that does Nino add the textfills that need the resolved request: request URI, response URI, locale, and current user.

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
$written = \Nino\AppData::writeContentData( $appData, [
    '/nino/http/routes',
    '/nino/locales',
] );
```

The method reads the current file state again, only takes over the specified keys, and then writes atomically. It returns `false` when `config.php` could not be locked or written; under Nino's own error handler that case already ends the request with a 500, so the return value matters where a handler continues, in the tests for example. Auth sessions are additionally merged via a three-way comparison so that parallel logins or logouts do not unnoticedly overwrite each other's state.

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
[elements /services sort="-date" limit="6" query="featured=1"]
    <article id="service-[[.id]]">
        <h2>[[title]]</h2>
        <p>[[description]]</p>
    </article>
[/elements]
```

`query` filters - `key=value`, several joined with `&`, `%` as a wildcard at either end. `sort` orders by a field: `sort="title"` ascending, `sort="-date"` descending, `sort="category,-date"` by the first and, where that is equal, the second; two numbers compare as numbers, everything else naturally and without regard to case ("Item 9" before "Item 10"), and an element without the field comes last in either direction. `offset` and `limit` cut a window out of the sorted list. A `callback` runs between: it sees the sorted list and may drop or reorder, and `offset` and `limit` apply to what it let through - a page is a page of that. In PHP the same is `\Nino\Elements::queryElements( $appData, $typeUri, $query, $locale, $return, $options )` with `sort`, `offset` and `limit` under `$options`, and `\Nino\Elements::sortElements( $elements, $sort )` orders a list you already hold.

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
| `Catalogue` | Fetch and verify the signed feature catalogue, say what it offers this kernel, and install an archive below `features/` |
| `Csrf` | Read/rotate tokens and check requests |
| `Filesystem` | Read/write files, resolve paths, lock, and atomically mutate |
| `Backup` | Process encrypted backup manifests |
| `RotatingLog` | Clean dated log files after retention period |
| `Elements` | Load individual elements, query, create, modify, and delete types and elements |
| `Features` | Discover the features below `features/`, read and validate their manifests, answer and save their settings, activate and deactivate them, and apply an install unit - the wizard's too |
| `Fetch` | The kernel's one http client: a GET over https with a timeout and a byte cap, used by the catalogue and by nothing else |
| `Html` | Register fills and shortcodes, render HTML+, and sanitize allowed inline HTML |
| `Http` | Normalize requests, resolve routes, create and output responses |
| `Images` | Process uploads, manage variants, and generate URLs |
| `Locales` | Manage current, native, and available languages |
| `Text` | Read text definitions, lock, and save in batch |
| `Mail` | Send emails via project configuration, through `mail()` or a transport registered under `/nino/mail/send` |
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
| `Elements` | `[element ...]`, `[elements ...]` | loads typed content; lists support query, `sort`, `offset`, `limit`, and optional callback |
| `Form` | `POST://.form` | owns the one form endpoint and hands every submission to `\Nino\Form` - see [Forms](#forms) below |
| `Images` | `[image ...]` | creates an escaped `<img>` from an image slot or URI |
| `Jstext` | `[jstext]` | provides text values as securely encoded JSON with CSP nonce |
| `Localepicker` | `[localepicker ...]` | switches locale via query and redirect |
| `Maintenance` | `/nino/http/response`, priority 1 | while `/nino/maintenance/status` is on, answers every site page and module endpoint with a 503 and a Retry-After header, for every visitor not signed in to the workbench |
| `Navigation` | `[navigation ...]` | renders navigations from a compact line syntax |
| `Template` | `[template /path/name]` | loads the raw content of a `.tpl` file; the common render pipeline processes it further |

Every module in the table ships in `_nino/Nino/Modules/`, beside the always-on kernel modules. `Form` and `Navigation` bring their workbench panels along (Submissions, Navigations), `Design`, `Templates` and `Maintenance` are nothing but a panel each: every one is present exactly while its module is active. `Form`, `Navigation` and `Localepicker` are no longer a setup wizard choice - the wizard applies each one's `install/` unit and lists its class in `/nino/modules` on every run (`\Nino\Install\Setup::ALWAYS_MODULES`), the same way `Design`, `Templates` and `Maintenance` are listed whenever their class exists. A project may still switch any of the six off by hand in `/nino/modules`, and `_nino/` stays replaceable wholesale. Everything beyond the table is a **feature** - an installable package under `features/<Name>/` with a `feature.php` manifest, switched on in the workbench's Features panel, bringing its panel the same way. A checkout ships none: `Newsletter` (double opt-in, confirmation and unsubscribe under `/.newsletter`) and `Search` (a locale-aware fuzzy index over Element fields) come from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features), copied into `features/`. See [Features](features.md), [Panels of the Workbench](#panels-of-the-workbench) and [Directory and Autoloading](#directory-and-autoloading) below.

Some details are deliberately defensive:

- The form limits inputs, protects write operations, and discards old log months.
- The public signup of the catalogue's Newsletter feature responds independently of whether an address is new or already known. This makes it harder to query foreign addresses.
- `Jstext` uses JSON hex escaping and adds a random nonce to the Content Security Policy.

### Forms

`Modules\Form` owns the route `POST /.form` and nothing else: what a submission is, what it has to look like, the mail pair it sends and the record it leaves is `\Nino\Form`, and the split is what lets a project have more than one form without a second endpoint answering the same uri.

A project defines its forms under `/nino/form/forms` in `config.php` - beside its routes and its image slots, so they are hand-editable, they travel in every backup, and a form needs no file format of its own. Defining none gives `\Nino\Form::DEFAULT_FORM`, the contact form Nino has always shipped, field for field:

```php
'/nino/form/forms' => [
	[
		'key'						=> 'quote',
		'name'					=> 'Quote request',
		'to'						=> 'sales@example.com',	// '' sends to '[[/form/email/owner]]'
		'subject'				=> '',									// '' uses '[[/form/subject/owner]]'
		'confirm'				=> true,								// a confirmation to the first address the visitor gave
		'ownerTemplate'	=> '/templates/mail-owner',
		'userTemplate'	=> '/templates/mail-user',
		'fields'				=> [
			[ 'name' => 'email',	'label' => '[[/form/label/email]]', 'type' => 'email',		'required' => true ],
			[ 'name' => 'budget',	'label' => 'Budget',								'type' => 'number' ],
			[ 'name' => 'wishes',	'label' => 'What for?',							'type' => 'textarea' ],
		],
	],
],
```

A field's `type` is one of `\Nino\Form::TYPES` (`text`, `email`, `tel`, `url`, `number`, `textarea`, `select`; a `select` lists its `options`), its `label` may be a textfill, and its `name` may not be one of `\Nino\Form::RESERVED` - the four keys the endpoint reads off the post itself and the four a record carries beside the values. A definition with no usable field left is dropped rather than half-read: a form nobody can submit is better than one that mails to an address a hand edit mistyped.

Two more keys sit beside them. `/nino/form/retention` is how many months of submissions stay on disk (1 to 60, `\Nino\Form::RETENTION_MONTHS` without one), and `/nino/form/store` set to `false` means the mail goes out and nothing is written at all - a site that answers its inquiries and keeps no copy has less to protect, and the Submissions panel then stays empty because there is nothing to show.

The markup is the project's own: `page-contact.tpl` carries a hand-written `<form class="nino-form">` that the shared script in `Nino.ui.js` drives. A project with several forms writes each one's markup the same way, or installs the catalogue's [Forms feature](https://github.com/dapeio/nino-features/blob/main/features/Forms/README.md), which adds a `[form]` shortcode that renders one from its definition, a builder for the definitions and a set of spam guards.

**Refusing a submission** needs no callback name of its own. A module or feature that wants to turn one away registers on the same route callback ahead of the module - `\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', ..., 1 )` - and leaves a status behind; `\Nino\Form::handle()` sees a status that is not 200 and returns without sending or writing anything. `\Nino\Csrf::init()` does exactly that, which is why the endpoint has no csrf check of its own:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ): void {

	if( yourGuardRefuses( $appData ) === true )
		$request['/nino/http/response']['statusCode'] = 418;

}, 1 );
```

418 rather than a status of its own for every refusal: the shared `.nino-form` script shows one generic message for anything that is not 200 or 400, so a bot never learns which check it tripped.

### Elements Search Index

`Modules\Search` is a feature, not part of the checkout: it comes from the
catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features),
copied into `features/Search/` and switched on in the workbench's Features
panel. It keeps a small locale-aware fuzzy index over configured fields of
flat Element types - defined under `/nino/elements/index` in `config.php`,
stored as one derived file per type under `data/`, rebuilt by the **Create
searchindex** action of its Search panel and after every committed Element
write - and answers `\Nino\Modules\Search::getElements( $appData, $type,
$query )` with the matching Elements of the current locale in score order.
The index configuration, the ranking rules, the lifecycle of the derived
files and the API are documented in the feature's own README in the
catalogue: [features/Search/README.md](https://github.com/dapeio/nino-features/blob/main/features/Search/README.md).

---

## Developing a Custom Module

### Directory and Autoloading

Nino's autoloader maps namespaces directly to directories. For the
project-owned class `Project\Catalog\Catalog`, the default file is:

```text
/app/Project/Catalog/Catalog/Catalog.php
```

The resolution rules are intentionally asymmetric:

| Class namespace | Search roots |
| --- | --- |
| `Nino\Modules\...` | `_nino/` first, then `_admin/`, then `features/` (`NINO_FEATURES_DIR` when defined), then `app/` (`NINO_APP_DIR` when defined) |
| every other `Nino\...` | `_nino/` only |
| every other namespace | `NINO_APP_DIR` when defined, otherwise `app/` |

The `Nino\` namespace is kernel-owned and cannot be shadowed from the project
application root - with one deliberate opening: `Nino\Modules\*` is a merged
view over four roots rather than one directory. The runtime modules Nino ships
live in `_nino/` - the always-on ones and the optional ones a project switches
on or off in `/nino/modules` (`Form`, `Navigation`, `Localepicker`, `Design`,
`Templates`, `Maintenance`); the workbench's own screens in `_admin/Nino/Modules/`
(`Dashboard`, `Elements`, `Text`, `Images`, `Logs`, `Routes`, `Users`,
`Language`, `Backups`, `Config`, `Features`); the features a project installs
below `features/` (or `NINO_FEATURES_DIR`), one directory each with a
`feature.php` manifest - the catalogue's `Newsletter` and `Search` arrive this
way, a checkout ships none; and the application root, where a project's own
code lives. Below the features root
the `Nino/Modules/` prefix is the directory itself: `features/Newsletter/Newsletter.php`
is `\Nino\Modules\Newsletter`, not `features/Nino/Modules/Newsletter/`. The
order is what each root may do to the others: `_nino/` first, so a shipped
module can never be shadowed; `_admin/` before `features/`, so a feature
cannot replace a workbench screen; `features/` before `app/`, so a project
cannot replace an installed feature by dropping a file next to its own
modules; `app/` last, which can only add.

It is the whole relative path that is resolved, not the first segment, so one
module name may hold classes in more than one root: `\Nino\Modules\Elements`
is the kernel's runtime module in `_nino/`, `\Nino\Modules\Elements\Admin`
the workbench screen for it in `_admin/` - two halves of one module, each where
it belongs. A project's own modules keep their own namespace under `app/`.

Within the selected root, the full class name becomes a directory path and the
class basename is appended once more as the filename. Therefore, the basename
of the class and the PHP file must match. Class paths are limited to allowed
characters; dynamically composed or user-controlled class names still do not
belong in the module list.

The kernel follows the same layout. `_nino/Nino.php` holds only the boot
functions `\Nino\init()`, `request()` and `output()` plus the autoloader; every
kernel class lives in its own file below `_nino/Nino/` - `\Nino\Filesystem` in
`_nino/Nino/Filesystem/Filesystem.php`, `\Nino\Auth` in
`_nino/Nino/Auth/Auth.php`, and so on - and is loaded on first use.

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

### Panels of the Workbench

A module can bring its own screen to the workbench. `/_admin` builds its navigation, content panes, bundles and text fills from a panel registry (`\Nino\Admin\Panels`), and a module joins it by answering one question:

```php
public static function adminPanels( array &$appData ): array {
    return [ \Project\Catalog\Admin::class ];
}
```

The kernel asks every active module in `/nino/modules` order (`\Nino\Modules::collect()`), so the screen exists exactly while the module does.

The workbench's own screens are the same thing in a different root: `_admin` holds the shell - the login, the rail, the panes, the registry, the bundles - and nothing else, and every screen in it is a module under `_admin/Nino/Modules/<Name>/`, laid out exactly like the ones above: `Admin/Admin.php` is the panel, `<Tab>/<Tab>.php` a tab of it, `assets/` its scripts and stylesheet, `text/` its words. `\Nino\Admin\Admin::modules()` reads the directory rather than a list, so a screen is added by adding a directory and removed by deleting one; without the directory `/_admin` is a login and an empty rail. A panel is a class with two required and a handful of optional static methods, no interface, no base class:

| Method | Returns |
| --- | --- |
| `actions()` | `[ 'catalog/list' => [ Class::class, 'apiList' ], ... ]` - dispatched by the workbench's `POST` handler |
| `nav()` | `[ uri, label, weight = 50, group = 'content' ]` - the uri is a slug and names the link, the pane and the JS namespace (`Nino.admin.<uri>`); a label starting with `/` is a fill key, anything else literal text - every shipped panel uses a fill, a module brings its `text/<locale>.php` for it; the group is `content`, `structure`, `features` or `system` - except a panel a feature brought (its class file lies below `\Nino\Features::dir()`) always lands in `features` regardless of what it names, and naming `features` from anywhere else is refused like an unknown group |
| `perm()` | the permission that shows the link and gates the actions - `/_admin/<uri>/manage` by convention; offered as a checkbox on the Users panel's roles tab automatically, and part of the **Editor** role the wizard writes when the group is `content` |
| `panes()` | mount ids rendered inside the pane, default `[ '<uri>-list' ]` |
| `template()` | instead of mount points: a `.tpl` rendered whole into the pane, project-relative and without the extension - for a panel that lays out its own regions |
| `layout()` | `'page'` (default: a column of content at reading width) or `'workspace'` (the whole width, the rail folded to its icons) |
| `icon()` | an inline `<svg>` for the rail; a panel without one shows its label's initial when the rail is folded |
| `tabs()` | further panel classes shown as tabs of this panel's pane - each a complete panel with its own `perm()`, script and hash prefix, ordered in the strip by its `nav()` weight; `tab()` names this panel's own tab when the nav label will not do. The workbench's own modules do this: Element Types under Elements, Text Keys under Text, Image Slots under Images, User roles and Login protection under Users, Translations under Language |
| `assets()` | project-relative `.js`/`.css` files, bundled into `/_admin/.cache/` after the workbench's own |
| `text()` | a directory of `<locale>.php` fill files, merged into the workbench's own |
| `summary( &$appData )` | a Dashboard tile `[ 'value' => ..., 'label' => ... ]` |
| `log( $action, $data )` | the activity-log line for a completed action, `''` for none |

A module names its files from where its class is, so they move with it:

```php
public static function assets(): array {
    return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
}
```

Every action method guards itself with `\Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM )`, which answers `401` without an account and `403` without the permission. The workbench's own modules are merged first, and a uri or action name one of them already owns is never handed to a runtime module. The shipped modules are the reference: `features/Search/Admin/Admin.php` is the smallest complete panel, `_nino/Nino/Modules/Form/Admin/Admin.php` one with fills and a Dashboard tile, `_nino/Nino/Modules/Design/Admin/Admin.php` one with its own template, `_nino/Nino/Modules/Templates/Admin/Admin.php` a workspace. The [panel recipe](recipes/admin-panel.md) of the AI guide walks through a complete panel including its frontend.

A module that keeps its own files under `data/` registers `'/nino/admin/restore'` in `init()`; the Backups panel calls it with the staged backup and the live data directory, and the module merges what is its own (`Newsletter::callbackRestore()` in the catalogue's Newsletter feature). Finally, an `install/` directory beside the class file - `manifest.php`, `templates/`, `text/` - makes a kernel or project module selectable in the setup wizard; see the [Library Format](setup.md#library-format). A feature carries the same unit, and `\Nino\Features::activate()` applies it - without overwriting anything the project has - when the feature is switched on in the Features panel; the manifest, the settings and the lifecycle are in [Features](features.md).

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

The workbench uses the same kernel as the frontend and has its own `index.php`. After `\Nino\init()`, the entry point initializes the workbench and then hands over to the common request/response lifecycle again:

```php
$appData = \Nino\init( true );
\Nino\Admin\Admin::init( $appData );
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

`init( true )` boots without a `config.php`, because until the setup wizard has run there is none. `Admin::init()` then decides what the route serves: the wizard (`_admin/install/Install.php`) while `Admin::isInstalled()` says no, the login and the panels afterwards. The wizard is not a module from `/nino/modules`; the panels that ship as modules - Design, Templates and Maintenance - are, and come through `adminPanels()` like any other.

`_admin/recovery.php` is the third entry point, booting the same way: it verifies the recovery secret (`\Nino\Admin\Recovery`) and offers a restore and a password reset, nothing else.

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
- The catalogue's Newsletter feature does not reveal whether an email address already exists.
- Image processing limits uploads to 8 MiB and source files to 20 million pixels before memory-intensive processing begins.
- PHP data files in publicly accessible directories receive protection stubs or only return values.

These precautions do not absolve project code of responsibility. Data from requests, files, or external APIs remains untrusted until it is validated for its specific target context and safely output.

---

## Tests and Change Workflow

Nino uses standalone smoke tests without PHPUnit. Each test creates an isolated temporary project and checks a different area:

| Test | Focus |
| --- | --- |
| `tests/kernel-smoke.php` | Kernel, routing, rendering, auth, filesystem, and modules |
| `tests/features-smoke.php` | the feature contract against `tests/fixtures/features/`: discovery, manifest validation, version constraints, every settings type, activation with the unit applied add-only, updates through the upgrade hook, deactivation, and the delivered manifests |
| `tests/catalogue-smoke.php` | the catalogue: the https client behind a stub, the detached signature, what a catalogue document must say, what it offers this kernel, an installation and an update from archive bytes built in the test, and every refusal on the way - a hostile archive among them |
| `features/<Name>/tests/<key>-smoke.php` | a feature's own test, travelling with it - the catalogue's `features/Search/tests/search-smoke.php`, for one, covers activation, index lifecycle, fuzzy ranking, locales, and the Admin rebuild action. Empty in a checkout, which ships no feature |
| `tests/admin-smoke.php` | the workbench shell and its content panels: the text blacklist and html sanitizer, element and image operations |
| `tests/admin-system-smoke.php` | the structure and system panels: the session gate, accounts, roles and permissions, element types, backups and recovery, the activity log, and a render of every panel in every interface language |
| `tests/install-smoke.php` | Installation steps, generated structure, and self-lock |
| `tests/design-smoke.php` | generated Design values and authenticated Theme/Header/Footer operations |
| `tests/templates-smoke.php` | section composition, template includes, lossless page frames, content quick fill, and save conflicts |
| `tests/demo-catalogue-smoke.php` | the demo catalogue page shows every section preset in every layout and every `nino-*` class |
| `tests/*-js-smoke.js` | browser-like logic of management interfaces and template builder |
| `tests/concurrency-smoke.php` | parallel and atomic write operations |

Locally, they are executed individually:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/admin-system-smoke.php
php tests/install-smoke.php
php tests/design-smoke.php
php tests/templates-smoke.php
php tests/features-smoke.php
php tests/catalogue-smoke.php
for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done
php tests/demo-catalogue-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

`tests/harness.php` is the bootstrap they share: it loads the kernel and the workbench shell and provides `check()`, `ninoSandbox()` (an isolated project directory, two locales, no modules), `ninoSandboxDir()`, `ninoWarnings()` (the warnings recorded since the last call, for a test that expects one) and `ninoDone()`. A feature's test loads it from the checkout three levels up, or from the one `NINO_ROOT` names - which is how the same test runs against another Nino version.

Static analysis runs beside the tests. PHPStan reads `phpstan.neon` (level 5, the kernel, the workbench, `app/` and `features/`, without a feature's own tests); `phpstan-baseline.neon` next to it lists the findings that were open when the check arrived, so only a new finding fails. Fixing one of the listed findings means removing its entry; a new finding is never added to the baseline to silence it. ESLint reads `eslint.config.mjs` and checks the browser scripts for undefined names, unused code and `==`; it declares the browser globals and the `Nino` namespace, nothing else. Neither is a dependency of the product: there is no `composer.json` and no `package.json`, CI installs both tools itself.

```bash
phpstan analyse
npx eslint .
```

The GitHub Actions pipeline uses PHP 8.4 and Node 22, runs the syntax checks across all PHP and JavaScript files, then PHPStan and ESLint, then these PHP and JavaScript smoke tests. A second job, `features`, clones the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) and copies every feature of it into the checkout, then validates their manifests and runs their own tests and PHPStan against it - the gate against a kernel change that breaks a published feature; the catalogue's CI does the reverse against Nino's `main` and its latest tag. `features/*/tests/` itself is empty in a checkout, which is why the loop above skips a glob that matched nothing.

For changes to the kernel or a module, the following workflow is recommended:

1. First reproduce or specify the behavior in the appropriate smoke test.
2. Implement the smallest possible change.
3. Run all PHP and JavaScript smoke tests, the syntax checks and the static analysis.
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
| `/nino/elements/committed` | `{ operation, type, uri, previousUri, locale }` | notification after an Element insert, update, or delete was persisted; cannot veto the completed write |
| `/nino/mail/send` | `{ to, subject, body, replyTo, sender, headers, sent }` | deliver a mail another way than `mail()`: a transport that took it sets `sent` to `true` or `false`, and `mail()` is skipped; `sent` left at `null` passes the mail on |
| `/nino/admin/restore` | `{ dataDir, staging }` | `/_admin` restores a backup: a module merges its own `data/` files from the staged copy into the live directory |
| `/nino/admin/action` | `{ action, panel, status, user, data }` | a `/_admin` panel action has run and answered - notification only, and fired for a failed action too. Says who did what in the workbench; *what changed* is the kernel's own events above |

`/nino/mail/send` is the one hook that replaces a kernel action rather than reacting to it. `\Nino\Mail::send()` fires it after the per-ip cap and after every header value was cleaned - with the subject still raw, since how a subject is encoded is the transport's business - and calls `mail()` only where no handler set `sent`. A module or feature that delivers over SMTP or an API registers here in `init()`; `\Nino\Mail::TRANSPORT` is the name.

Callback names are simple strings. Still, treat the established names and argument forms like an API: A rename or changed argument type can affect every registered module.

### Two extension surfaces, one idea

A module reaches the framework in two ways, and the difference is not events
versus methods - it is *reaction* versus *declaration*.

**Reaction** is what callbacks are for. Something happened; whoever cares runs.
The firing side knows no listener, several may run in priority order, and some
of them may refuse (`/nino/elements<type>/insert` and its siblings return
`false` to veto). Every name in the table above is of this kind.

**Declaration** is what the `/_admin` panel contract is for. A module answers
`adminPanels()` with a class, and that class *states what it is* in the
workbench: uri, label, weight, group, permission, panes, assets, text, tabs
(see `\Nino\Admin\Panels` and section 7). The tool reads that into a registry
it can validate (a bad uri, a taken uri, a missing `actions()`/`nav()`, an
asset that is not there - each reported with the class that caused it), sort by
group and weight, and derive the rail, the panes, the asset bundle, the fills
and the permission list from. A callback bus cannot do any of that without
becoming a registry itself, and it has the wrong default for it: with
`doCallbacks()` the last writer wins, while a panel registry must let the
*first* claim of a uri stand so no module can replace a core screen by picking
its name.

So the boundary is: **events across the tool's edge, contract inside it.**
`/_admin` registers its own routes as callbacks like anything else, and
`/nino/admin/action` and `/nino/admin/restore` are the two points where a
module reacts to what the workbench does without owning a panel there.

---

## Further Manuals

- [Concepts](concepts.md) explains the core pillars and the overall technical context.
- [Getting Started](getting-started.md) guides from checkout to configured project.
- [Setup Wizard](setup.md) documents all installation steps and writing rules.
- [`/_admin` Workbench](_admin.md) describes every panel, the roles and the recovery page.
- [Templates Panel](templates.md) explains the structural template builder in Alpha status.
- [Design Panel](appearance.md) explains the four appearance editors and the token contract.
- [Features](features.md) explains installable features: the manifest, the settings, activation, updates and a feature's tests.
- [Deployment](deployment.md) describes web servers, security, and go-live.
- [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) explains how to handle security reports.
