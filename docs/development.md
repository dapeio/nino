# Nino — Developer Manual

This manual describes the technical work with Nino — from the entry point through routing and rendering to custom modules, persistent data, and security. Where the code states a list itself — the callbacks the kernel fires, the classes, the modules, the methods of a panel — the manual names the place instead of copying the list. Setting up a project is the [Setup Wizard](setup.md) manual; one kind of change at a time is walked through in the [recipes](recipes/README.md).

**Developer Profile:** For simple websites, solid knowledge of HTML, CSS, and JavaScript as well as PHP basics is sufficient. Templates consist of HTML+, i.e., HTML with textfills and shortcodes. Only custom application logic, external interfaces, or new modules require deeper PHP knowledge. A finished project can then be largely maintained in the workbench, `/_admin`.

---

## The Pillars

A website is a few clearly separated parts. `config.php` holds the technical structure - routes, modules, languages, accounts; `text/` and `elements/` the multilingual editorial data; `templates/` the HTML, as `.tpl` files; `data/` what the running system writes; PHP modules extend the process through callbacks. Everything a web server must never deliver lives in `private/`, everything a browser loads directly in `public/`; the short forms used throughout - `/text`, `/data`, `/images` - are virtual paths that Nino resolves into one of the two.

| Pillar | Consequence |
| --- | --- |
| **No dependencies** | No external runtime packages, no Composer bootstrap, no database: kernel and included modules are part of the project, and only PHP and the documented extensions are needed |
| **File-based project** | Configuration, texts, elements and templates are readable files, so the project can be transferred together, versioned with Git and checked without database tools. Large relational data or transactions belong in a database behind a module of the project |
| **Central state** | `$appData` is one array: `init()` creates it once, the kernel and the callbacks receive it by reference and change their own part. The request and its response stay apart in `$request` |
| **Logic and presentation apart** | `.tpl` files hold HTML, textfills and shortcodes, never PHP; modules and callbacks do the logic, and shortcodes are the controlled link between the two |

Every request runs the same three calls:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

A key of `$appData` under `/...` belongs to the stable configuration and data space (`/nino/http/routes`), a key under `./...` to the current request alone (`./nino/locales/current`). The notation decides nothing about persistence: a `/...` value is saved only when a writer is asked to, see [Persistent Data](#persistent-data-and-concurrent-write-access).

### Where does a change belong?

| Task | Location |
| --- | --- |
| Change a page title | a textfill in `text/`, or the Text panel |
| Add a team member | an element, in the Elements panel |
| Compose a page from sections, columns and components | the Builder, a feature from the catalogue |
| Change lower-level HTML | the HTML+ editor, or the `.tpl` file in `templates/` |
| Create a public URL | a route in `config.php`, or the Routes panel |
| Output a dynamic list | an element query, or a shortcode with a callback |
| Add technical function | a module of the project |
| Add a packaged function - a newsletter, a search | a feature from the catalogue, copied into `features/` and switched on in the Features panel |
| Change the look | `assets/theme.css` and `assets/style.css` by hand, or the catalogue's Design feature |

## Entry Point and Runtime Model

The three calls are the entire runtime, and the next section takes them one by one. There is no teardown phase: whatever has to outlive the request must be written to the file system explicitly.

### `$appData` and `$request`

Nino separates the state of the application from the state of a single HTTP operation:

- `$appData` contains configuration, registered callbacks, caches, the current user, and other runtime data.
- `$request` contains the normalized input and the resulting response.

Both arrays are passed by reference. This keeps it visible which method reads or modifies data; a hidden container or global service locator is not necessary.

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

`NINO_PRIVATE_DIR` moves the complete private tree out of the webroot, `NINO_CONFIG_DIR` only `config.php`, `NINO_APP_DIR` the root of the project's own PHP classes (`app/`) and `NINO_FEATURES_DIR` the root of the installed features (`features/`). Each is defined before the kernel is loaded - in every entry point, with the same value - and replaces its directory as a whole; none of them moves the kernel's own `Nino\` classes. The examples and the pitfalls are in [Deployment](deployment.md#configuration-and-application-source-outside-the-webroot).

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
Html::init( $appData );
Auth::init( $appData );
Modules::callModules( $appData, 'init' );
```

The order is part of the runtime contract:

- `AppData::prepare()` sets up the internal runtime areas of `$appData`.
- `AppData::prepareSession()` provides the session configuration - and the two `/nino/error/` switches, so a failure in this window can still be logged - before PHP starts the session.
- `Runtime::init()` sets up PHP error handling and starts or takes over the session.
- `Filesystem::init()` determines the project and configuration path and initializes the file cache.
- `AppData::init()` loads `config.php` into `$appData`.
- `Locales`, `Csrf`, and `Auth` determine language, CSRF state, and current user; `Html::init()` between them registers the one shortcode the kernel owns itself, `[json]`, which the base templates use in their schema.org block whatever modules a project has.
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

The runtime textfills are added in two passes, and the split matters. `/nino/dir`, `/nino/public`, and `/nino/date/year` answer to `$appData` alone, so they are registered **before** `Http::response()`: a response callback that renders a template is a real caller — `Modules\Form` and `Modules\Newsletter` build their HTML mails in exactly that window, and a fill registered after it would reach them as the literal `[[/nino/public]]`.

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

A route carries `uri`, `body`, `statusCode`, `header`, `locale`, `csrf` and `maintenance`, among others. `\Nino\Http` reads the first five where it resolves the route and builds the response; `csrf` is read by `\Nino\Csrf` and `maintenance` by `Modules\Maintenance`, and each is documented where it is read.

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

`Http::ok()` sets the body of a successful response. `Http::fail()` sets the status code and a uniform `error` field - the English message for a developer or a log - and, optionally, a stable `code`, its `params` and the `field` the value was refused for, which the workbench turns into a sentence in the interface language. Both modify the passed `$request` directly and are more readable than manually setting all fields, especially for JSON routes.

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

Which content belongs in `config.php`, `text/`, `elements/`, `templates/`, and `data/` is named in [The Pillars](#the-pillars). For development, it is especially important that loaded values are not automatically written back.

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
- `.json` files are JSON-encoded and decoded; one that does not decode answers the default, like a file that is not there.
- Read accesses are cached based on modification time and file size.
- Write operations first create a temporary file in the target directory and then replace the target via `rename()`.
- Locks are sidecar files under `/data/.locks`; their name is derived from the target path.
- Paths with `..` or a nul byte are rejected as an additional protective layer - by every door of `Filesystem`, not only by the two that read and write content: `path()` and `url()` answer `''`, `fileExists()`, `lockFile()`, `putFileContent()` and `mutate()` answer `false`, `getFileContent()` answers its default, and `forceDir()` creates nothing. `path()`, `url()` and `forceDir()` say so in the log, so a call site that forgot to validate its own input is findable; the reads, the writes, the existence check and the lock refuse as quietly as they would a missing file. The nul byte is the half that is not merely a second layer: PHP's own `mkdir()`, `fopen()`, `rename()` and `glob()` throw a `ValueError` for one, which the `@` in front of them does not suppress, so without this rule a nul byte where a `..` would have answered `false` was an uncaught 500.

For a simple, complete replacement, `\Nino\Filesystem::putFileContent( $appData, '/data/example.php', $content )` is sufficient.

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
<title>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</title>
<p>[[/template/page-contact/hero/subtitle]]</p>
```

Nino combines:

1. global values from `/text/global.php`,
2. values of the current locale from `/text/<locale>.php`,
3. runtime values added with `Html::addFills()`.

Fills can contain other fills. Nino therefore repeats the replacement until the entire string no longer changes, but at most ten passes. This allows controlled nesting without a cyclic fill blocking the runtime indefinitely.

Runtime values can be specifically added:

```php
\Nino\Html::addFills( $appData, [
    '/project/catalog/list/count' => 42,
], '*' );
```

The third parameter denotes the language scope. `'*'` stands for language-independent values.

#### The key grammar

Every text key a project or a unit carries has four segments, `/<namespace>/<category>/<part>/<name>`, each of them lower-case words joined by hyphens - `[a-z0-9]+(-[a-z0-9]+)*`. The words are English and spelled out. A key never says which language it is in, or whether it is global or per language, and a name is never a number: a list puts its index in the part (`item-1`).

The first segment is the namespace: `template` for the words of a template (`/template/page-services/item-1/title`, or `/template/common/form/submit` for words several templates read), `project` for the project's own facts, settings and content (`/project/company/contact/email`), `feature` for the function of a catalogue feature (`/feature/lightbox/controls/close`) and `module` for that of a kernel module (`/module/form/info/required`). What each holds and which category it takes is the table in [AGENTS.md](../AGENTS.md#text-keys); `\Nino\Text::isGrammarKey()` is the rule.

Who a word belongs to is decided in this order, and the first yes wins:

1. A fact, a setting or content of the project that belongs to no template - the company, the website, the mailbox, the look of the mails, the texts of the project's own modules: `/project/<category>/...`.
2. A word several templates read and no function ties down - field labels, button words, the label in front of a phone number: `/template/common/<part>/<name>`. That includes a word that code puts into any template that has fitting markup, such as the contact form's field labels, which `\Nino\Form::DEFAULT_FORM` brings into every template showing the form.
3. A word that belongs to a function of a module or a feature - its code outputs it, sets it at runtime, selects it from a fixed set, or the function ships it for markup any project template writes: `/module/<directory>/...` or `/feature/<key>/...`. A project template reading it as well, or a project replacing the template, changes nothing about that.
4. Otherwise the word belongs to the one template that reads it, in the category of its file name: `/template/<file name without .tpl>/...`. That holds for the templates a unit ships as well.

The rule applies when a key is created. Nothing moves a key later: if a second template reads a word of another category, the word stays where it is. The scan in the Text Keys tab names such a case; whoever wants the word shared creates it under `common` and changes the places that read it.

The part is the block a word stands in: `intro`, `hero`, `item-<n>`, `form`, `navigation`, `action`, `summary`, `outro`, `label` - or a word naming the block (`welcome`, `pager`, `banner`). The name is the thing itself, preferably `title`, `text`, `button` or `label`. A list with one word per entry puts the identifier in the name (`/feature/modeswitch/mode/dark`), one with several words per entry puts it in the part (`item-1`, `category-necessary`).

A label in front of a fact and the fact are two things. `/template/common/label/phone` is the word "Phone"; the number is `/project/company/contact/phone`, which every template reads from there and from nowhere else.

The category of a template is its file name without `.tpl`: `page-home.tpl` carries `/template/page-home/...`, `html-footer.tpl` carries `/template/html-footer/...`, nothing is cut off or derived. A name that is not a word of a key - a dot, an upper-case letter, an underscore, a slash - gives the template no category: it carries no keys of its own, and may read every other one (`page-home.de_DE.tpl`, `.demo-catalogue.tpl`). Only files directly below `templates/` have one. `\Nino\Modules\Template::category()` answers it for a file name or for a template as a shortcode or a route body names it (`/templates/page-home`), and is the one place the rule is written.

A key may be put together at runtime in exactly these shapes, and no other: `[[/_nino/webpage[[/nino/http/response/uri]]/<name>]]` and `[[/_nino/locale/[[locale]]/name]]` in a template, and a four-segment key in which one placeholder is a whole name or the identifier of a list's part (`/feature/posts/navigation/` + `prev`, `/feature/consent/category-` + `necessary` + `/name`). Nothing scans the words that can come out of it, so whoever composes a key checks the rendered words in their own smoke test.

Three things are no text key. The kernel's runtime fills live under `/nino/` and are never in a text file (`[[/nino/dir]]`, `[[/nino/public]]`, `[[/nino/date/year]]`, `[[/nino/http/response/uri]]`, ...; `\Nino\Html::runtimeFillKeys()` names them). A local placeholder has no leading slash (`[[name]]` in an element loop or a mail template, `[[.rel]]`). Configuration such as `/nino/jstext/keys` or `/project/catalog` in `config.php` is another store, even where it looks the same.

Two forms are the system's, not the project's: `/_nino/webpage<uri>/<name|title|description|uri>`, the details of a page after its Element-URI (which may hold slashes and dots), and `/_nino/locale/<code>/name`, the name of a language. The setup wizard and the Routes panel write the first - for the pages that features route at runtime, in the Routes panel's section "Routes of features and modules" - and the Localepicker unit and the Language panel write the second; the Language panel gives a language it adds its code as the name. `/_admin/...` are the workbench's own words and stay as they are.

The server holds people to the grammar where they make keys: the Text Keys tab creates and renames only keys that follow it (`\Nino\Text::isGrammarKey()`), and refuses to rename a key under `/_nino/` or `/_admin/`. Keys a project already has are left alone and stay editable. `tests/keys-smoke.php` holds everything Nino ships to it, finds a leftover of an old form, and checks that every key a shipped template reads exists.

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

A shortcode that says what it takes - a source for its value and attributes with a type and a default - is a **component**, and one that loops the elements of a type around its content a **stack**: see [Components](#components).

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

Normal field values are HTML-encoded. A field released in the model with `html => true` may only contain a limited, sanitized amount of inline HTML: `strong`, `em`, `span`, `code` and `a`. `blocks => true` beside it adds paragraphs and lists (`p`, `ul`, `ol`, `li`) and the `<br>` inside them; `breaks => true` on a field *without* `html` keeps the line breaks of its plain text, escaped, as `<br>`. All of it is `\Nino\Html::fieldValue( $value, $field )`, which `[element]` and `[elements]` call and which a feature that draws a field itself should call instead of keeping a copy; `\Nino\Html::sanitizeHtml( $html, $format )` takes `inline`, `lines` or `blocks`, and `\Nino\Html::detectFormat()` reads the widest format a value holds. Give the element that holds a `blocks` value the class `nino-richtext`: the reset at the top of `Nino.css` takes every paragraph gap and list marker away, and the class restores them. The protection deliberately takes place in the Elements module: Element placeholders are local data of the respective block and not part of the global textfill space.

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

Assets deliberately **do not** go through the full HTML+ engine. Only the two secure directory fills are replaced: `[[/nino/dir]]` and `[[/nino/public]]`. This prevents editorial textfills or shortcodes from inadvertently generating executable CSS or JavaScript code. Both are needed - `Nino.ui.js` falls back to `[[/nino/dir]]/.form` for a form that carries no action of its own, and the base unit's `theme.css` addresses its three webfonts through `[[/nino/public]]`.

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

The kernel classes live one per file, in `_nino/Nino/<Class>/<Class>.php`, and are loaded on first use; the docblocks of each state its public tasks. Use the public methods instead of the internal implementations starting with `_`. This keeps project code decoupled from details like cache invalidation, file format, and session rotation.

---

## Integrated Modules

Modules are activated in `/nino/modules`. The order of the array is relevant if several of them register callbacks of the same priority. The modules Nino ships are the directories of `_nino/Nino/Modules/` (`\Nino\Modules\*`): the always-on ones, which `\Nino\AppData::DEFAULTS` lists, and `Form`, `Legal`, `Localepicker`, `Maintenance` and `Navigation`, which the setup wizard applies on every run and a project may still switch off by hand. `_nino/` stays replaceable wholesale. Everything beyond is a **feature**: an installable package under `features/<Name>/` with a `feature.php` manifest, switched on in the workbench's Features panel and bringing its panel the same way. A checkout ships none; they come from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features), and each documents itself in the `manual` of its manifest. See [Features](features.md), [Panels of the Workbench](#panels-of-the-workbench) and the [autoload layout](recipes/runtime-module.md#autoload-layout).

### Components

`Modules\Components` is where a shortcode gets a schema. A **component** is a shortcode that says what it takes - one source for its value and a few attributes, each with a type and a default - so that a tool can offer it and a template can still be written by hand. A **stack** is a component that loops the elements of one type around its content. Both are registered in one registry, which the module's `init()` fills with the kernel's own - `\Nino\Modules\Components::init()` lists them, each with its schema - and which a feature's manifest or a project's `MyApp` adds to. The module is always on, and `[image]` lives here since it left the module `Images`, which no longer exists.

```php
\Nino\Modules\Components::addComponent( $appData, 'countdown', [ Countdown::class, 'componentCountdown' ], [
	'label'      => [ 'en_US' => 'Countdown', 'de_DE' => 'Countdown' ],
	'source'     => 'text',          // text | image | href | content | none
	'loop'       => true,            // may stand in a stack, where its source may be a field
	'attributes' => [
		'style' => [ 'type' => 'select', 'options' => [ '', 'big' ], 'default' => '', 'label' => [ 'en_US' => 'Style', 'de_DE' => 'Stil' ] ],
	],
	'preview'    => 'block',         // title | text | image | button | block | cells
	'draws'      => 'p .project-countdown', // optional, for a tool: the tag and the class it draws
	'in'         => [ 'section' ],   // optional, where a tool offers it: section | card | hero
] );
```

`addComponent()` and `addStack()` register one: they call `\Nino\Html::addShortcode()` with a wrapper around the callback, and **replace** a component or a shortcode of the same name, which is how a project changes the markup of one. The rest of the API - `components()` and `stacks()`, `defaults()`, `value()`, `element()`, `renderStack()`, `cell()` and `escape()` - is documented where it stands, in `Components.php`.

**The schema.** `label` is a string or a locale map, and every attribute needs a `default`. An attribute is a `string`, an `int` (`min`, `max`), a `bool`, a `select` (`options`, a list, with the default among them) or `lines`, and three types that only exist here: `key` (a text key), `image` (an image slot) and `href` (a key or an address). `label` and `hint` of an attribute are optional. `class` is allowed on every component without being declared: the renderer puts it at the end of the classes of its outer element. `draws`, `in` and `holds` are optional and tell a tool something about a component without changing how it renders. `draws` is what the renderer draws on the outside, written the way a builder shows it: the tag, a space, the one class a theme addresses the element by - `{tag} .nino-section-title`, `a .nino-btn`. It is a non-empty string of at most 64 characters from `a-z`, `0-9`, space, `.`, `{`, `}` and `-`, and a name in braces is an attribute of the schema, put in from the shortcode or else from the default. `in` says where a tool offers the component, and a place says where a component stands: a non-empty list of distinct words from `section` (directly in a column - also in the cell of a stack that holds no card), `card` (in a card) and `hero` (directly in a column of the hero section, the first section of a page when it has a cover); without it the component is offered everywhere. `holds` is one of the same three words, and only a component whose source is its `content` may have it: what stands between its tags is then the calls of the components of that place, which are rendered with it and which a builder edits as its children. The kernel has one, `[card]`, which holds `card`. A component that draws another element in another place is another component - the kernel has six titles - because no renderer reads a context. A stack declares none of the three. A stack says `grid => true` if it takes `cols`, `gap` and `autoheight` and draws cells, and may name `assets` (`css` and `js`, project paths) for a tool to tell the developer which files it needs - nothing adds them to a page. `preview` names one of the fixed pictures a builder draws it as. The names `value`, `source`, `content`, `text`, `uri` and `grid` are taken, and so are the arguments of a stack's loop. **A schema the module cannot work with is refused at registration** with an `E_USER_ERROR` that says why: a name that is no slug, an unknown `source` or type, a `select` with no options or a default outside them, an attribute without a default, an `int` outside its bounds, a place in `in` that is none of the three or is named twice, a `holds` that names no place or stands with another source than `content`, a `draws` that is empty, of other characters or more than 64 or with a placeholder that no attribute answers. A manifest is read the same way, before it is applied (see [Features](features.md)). Rendering does not validate: an attribute that is not declared is ignored, a `select` outside its options is the default, a number that is none too, so a file written by hand may be incomplete and still renders.

**What a renderer receives.** `public static function componentTitle( array &$appData, array $args ): string`, with `$args` made ready by `dispatch()`: the first argument resolved by `value()` and made safe (`value`, and `source` as it was written), what stands between the tags (`content`), every attribute under its name with the defaults filled in and a `select` reduced to its options, and `text` where the shortcode had a fixed one. A component whose value is `null` - or empty, where it has a source - renders nothing: an empty slot is no broken picture, and a missing field no empty heading. What a renderer returns is rendered again like the output of any shortcode, which is why `value` is escaped and has every `[` written as `&#91;` (the rule is in [AGENTS.md](../AGENTS.md#rendering-and-escaping-rules)); `value()` documents what the first argument can be - a text key, a field or the place in the loop in a stack, a fixed `text="..."`, a picture reference, an address.

The kernel brings the titles and subtitles of a section, a card and a hero, `[text]`, `[image]`, `[button]`, `[html]`, `[spacer]` and `[card]` as components, and `[stack]`, `[slider]`, `[filter]` and `[list]` as stacks.

The six titles share one renderer and differ in the class they draw, which is their place: `title` and `subtitle` are for a section (`in` is `[ 'section' ]`), `card-title` and `card-subtitle` for a card (`[ 'card' ]`), `hero-title` and `hero-subtitle` for a hero (`[ 'hero' ]`), and `draws` names the tag and the class (`{tag} .nino-card-title`, `{tag} .nino-hero-subtitle`). `[button]` declares `draws` too, `a .nino-btn`, and is offered everywhere; `[text]`, `[image]`, `[html]` and `[spacer]` declare neither, and neither does a stack. `[card]` declares all three: `in` is `[ 'section', 'hero' ]`, `draws` is `article .nino-card` and `holds` is `card`. The registry carries the keys as the schema has them - `components()` and a feature's manifest are both read by `validate()`.

`[card]` is a component that holds components: what stands between its tags is the calls of the components of a card - a title, a text, an image, a button - and it draws the `<article>` around them with its ground, its padding, whether it takes the whole height of its column or cell (`fill`), where its content stands in a card taller than it (`vertical`), how the text and the buttons are aligned, a picture behind the content, its border and a class of its own; `componentCard()` documents the attributes and the order of the classes. The same component stands once in a column - a card beside a picture - and once per element in a stack: the cards of a loop. It reads no context itself; the components in it do. The content is not rendered by the renderer, which calls no `renderHtml()` of its own: the engine renders the output of a shortcode again (`\Nino\Html::_doShortcode()`, to a depth it caps - that is what makes `[template]` work), so the components in the card are rendered when the card is, and in a stack with the element still their context, because `renderStack()` holds it around the rendering of the stack's content. A card with nothing between its tags renders nothing; a card whose components all render nothing is an empty `<article>`, as a section with nothing in it is a section. The markup of the card and of its picture is `$html['card']` and `$html['card-bg']`.

`[image]` is the shortcode `Modules\Images` had, and it behaves as it did in every form; `componentImage()` documents the alt text, the content form that a meta tag or a mail asks for the address with, and the frame that crops. One thing the code does not say: the content of a shortcode runs up to the first closing tag, so a bare `[image]` ahead of the content form `[image /logo]...[/image]` in one template reads the text between them as its own content. Write the bare one as `[image /x][/image]`. The markup of every component is a property, `\Nino\Modules\Components::$html`, `$html['img']` among them - replace an entry, or register a component of the same name.

**Stacks.** A stack is written with the type as its first argument and the cell between its tags:

```html
[stack /services sort="title" limit="6" cols="100 50 50" gap="2" autoheight="1" id="services"]
    [image image focus="5"]
    [title title tag="h4"]
    [text summary]
    [button .uri text="More"]
[/stack]
```

Every stack reads the loop of `[elements]` - `sort`, `offset`, `limit`, `query`, `callback`, `locale` - and an `id`; with a grid, `cols` (the widths in the three viewports as classes of the grid - `100 50 33` - or one value for all), `gap` (0-6, the class `nino-stack-gap-<n>` on the row) and `autoheight` (`nino-autoheight` on each cell, in the group of the `id`). That is the grid: the type and its data, the cells per viewport, the gap, the equal height and the id, and nothing about what a cell looks like. A cell is a `<div>`; a card is a `[card]` in the content, drawn once per element:

```html
[stack /services sort="title" cols="100 50 33" gap="2" autoheight="1"]
    [card ground="counter" fill="1"]
        [card-title title]
        [text summary]
        [button .uri text="More" size="small"]
    [/card]
[/stack]
```

The cells are the stack's and the card is the content, so the two are told apart in `Nino.css` too: the cell holds the half gap as padding, and the card in it has its padding and its border as in a column but no margin of its own, the half gap being the room between neighbours. Below 640px, where the classes of the grid begin, the cells are one below the other and a card in one is as wide as the column. The kernel brings four stacks - `[stack]`, `[slider]`, `[filter]` and `[list]` - and their schemas, with the attributes of each, are in `Components::init()`.

A stack **renders each cell itself**. `[elements]` fills its content with `str_replace()` and returns it, to be rendered afterwards - which would run every component in it once the loop is over, with no element. `renderStack()` sets the element as the context (`./nino/components/element`, what `element()` returns), renders the content with `\Nino\Html::renderHtml()`, puts the previous context back and gives the cell to the function it was handed: `fn( string $inner, array $element, int $index, array $cell ): string`, where `$cell` is `[ 'class' => ..., 'attributes' => ... ]` - the classes of the cell in the grid and `data-autoheight-group`, both empty without a grid - for the cell to put on the element it draws; `\Nino\Modules\Components::cell( $cell, $inner, $class, $attributes )` does that and draws the `<div>` of the cell. A stack that draws a cell of its own, as the slider does its `<li>`, puts them on that. What a stack returns has no shortcode left in it. The loop is the one of `[elements]` - `\Nino\Modules\Elements::queryLoop()`, which both call - so `sort`, `offset`, `limit`, `query` and `callback` mean the same in both, and `[elements]` stays as it is, for templates written by hand.

Two rules follow. **A stack never stands directly inside another, and a `[card]` never stands directly inside another**: the shortcode reads its content up to the first closing tag, and the context is the element of the one cell. A stack in a template that a stack includes is fine - the context is put back. And a fill in the argument of a component (`[title "[[/some/key]]"]`) is not a source: the source is the key itself, not its value.

**From a feature.** A manifest that names `components` and `stacks` (see [Features](features.md#components-and-stacks)) has them registered at every boot, with the renderers `[ '\Nino\Modules\<Name>', 'component<Name>' ]` and `stack<Name>`; `MyApp` calls `addComponent()` in its `init()`. A component is registered one way or the other, not both: `Html::addShortcode()` adds a second callback beside the first, where `addComponent()` replaces it. A shortcode without a schema stays a shortcode, and is no component. [The recipe](recipes/component.md) writes one of each.

The classes the markup uses are in `Nino.css`, and the custom properties a design layer sets to turn it - the font roles, the type scale, the grounds and the rest - are declared and commented at its `:root`. The catalogue's Design feature documents what it compiles from them in the manual of its `feature.php`.

### Forms

`Modules\Form` owns the route `POST /.form` and nothing else: what a submission is, what it has to look like, the mail pair it sends and the record it leaves is `\Nino\Form`, and the split is what lets a project have more than one form without a second endpoint answering the same uri.

A project defines its forms under `/nino/form/forms` in `config.php` - beside its routes and its image slots, so they are hand-editable, they travel in every backup, and a form needs no file format of its own. Defining none gives `\Nino\Form::DEFAULT_FORM`, the contact form Nino has always shipped, field for field:

```php
'/nino/form/forms' => [
	[
		'key'						=> 'quote',
		'name'					=> 'Quote request',
		'to'						=> 'sales@example.com',	// '' sends to '[[/project/mail/address/owner]]'
		'subject'				=> '',									// '' uses '[[/module/form/subject/owner]]'
		'confirm'				=> true,								// a confirmation to the first address the visitor gave
		'ownerTemplate'	=> '/templates/mail-owner',
		'userTemplate'	=> '/templates/mail-user',
		'fields'				=> [
			[ 'name' => 'email',	'label' => '[[/template/common/form/email]]', 'type' => 'email',		'required' => true ],
			[ 'name' => 'budget',	'label' => 'Budget',								'type' => 'number' ],
			[ 'name' => 'wishes',	'label' => 'What for?',							'type' => 'textarea' ],
		],
	],
],
```

The field types are `\Nino\Form::TYPES` and the names a field may not take `\Nino\Form::RESERVED`; `\Nino\Form` documents what each type posts and how `normalize()` and `problems()` treat a definition.

Only `[[fields]]` carries every field into a mail - the whole submission as a table, whatever the form asks for. The `mail-owner` and `mail-user` templates a new installation starts from carry it; a project that installed before copies it into its own templates by hand. A template that fills only `[[name]]`, `[[email]]`, `[[subject]]` (the field `cat`) and `[[message]]`, as the earlier ones did, mails nothing of a field of another name.

Two more keys sit beside them. `/nino/form/retention` is how many months of submissions stay on disk (1 to 60, `\Nino\Form::RETENTION_MONTHS` without one), and `/nino/form/store` set to `false` means the mail goes out and nothing is written at all - a site that answers its inquiries and keeps no copy has less to protect, and the Submissions panel then stays empty because there is nothing to show.

A project that changes the retention or the confirmation mail changes what its privacy policy has to say: the section "Contact form" of the privacy policy (type `privacy`, element `contact-form`) names the period the submissions are kept and the confirmation the visitor gets. Whoever sets `/nino/form/retention` or `/nino/form/store`, or sends no confirmation, adapts that section to it - see [Legal](#legal).

The markup is the project's own: `page-contact.tpl` carries a hand-written `<form class="nino-form">` that the shared script in `Nino.ui.js` drives. A project with several forms writes each one's markup the same way; the catalogue's Forms feature draws one from its definition.

**Refusing a submission** needs no callback name of its own. A module or feature that wants to turn one away registers on the same route callback ahead of the module - `\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', ..., 1 )` - and leaves a status behind; `\Nino\Form::handle()` sees a status that is not 200 and returns without sending or writing anything. `\Nino\Csrf` refuses the same way one step earlier - on the global `/nino/http/response`, at priority 1, which runs before any route's callback - which is why the endpoint has no csrf check of its own:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ): void {

	if( yourGuardRefuses( $appData ) === true )
		$request['/nino/http/response']['statusCode'] = 418;

}, 1 );
```

418 rather than a status of its own for every refusal: the shared `.nino-form` script shows one generic message for anything that is not 200 or 400, so a bot never learns which check it tripped.

The endpoint's own answers follow the same rule. A submission the per-ip mail cap refuses is a `429`, neither mailed nor recorded - the visitor sees the generic message and tries again later, and a throttled flood does not become unthrottled disk growth. A submission whose owner mail no transport took is answered `500` - the generic message on the page, so the visitor knows it did not arrive - and, where the project keeps a copy, still recorded, since the inquiry is in the Submissions panel; the Dashboard says why. Only the owner's mail counts: a visitor confirmation that could not be delivered, with the owner's mail out, answers `200`.

### Legal

`Modules\Legal` brings the imprint and the privacy policy as **elements**: two types, `legal` (the imprint) and `privacy` (the privacy policy), one element per section, each with a title and a text per language and the fields `order` and `hidden`. The setup wizard applies its `install/` unit on every run (`\Nino\Install\Setup::ALWAYS_MODULES`) and fills the two types with a newly written starting text in German and English. Nothing of it is replaced afterwards: elements are the editors' content, so a second wizard run adds what is missing and leaves every section as it is. The notice that these texts are a starting point and no legal advice stands in full in the [setup manual](setup.md#modules).

| Part | What it does |
| --- | --- |
| `[legal]`, `[privacy]` | draw the sections of one type in the visitor's language, ordered by `order`, without the ones set to `hidden`; a section that has neither a title nor a text in this language is drawn in the native language (or the first that has one) and carries `lang=""` |
| `\Nino\Modules\Legal::render( $appData, $type )` | what the two shortcodes call; title and text are made safe by the field's own model (`\Nino\Html::fieldValue()`) first |
| `\Nino\Modules\Legal::placeholders( $appData, $html )` | replaces `#/project/company/contact/email#` with what that text key says in the current language |
| `\Nino\Modules\Legal::url( $appData, $page, $locale = '' )` | the address of `imprint` or `privacy`, with the directory prefix, per language, falling back to the native one |
| `\Nino\Modules\Legal::addLocale( $appData, $locale )` | what a language added later gets: the page details, field labels and type hints, the page names, and the section versions of this language the module and the active features ship; called by the Language panel |
| `\Nino\Modules\Legal::contributions( $appData, $featureKey, $locale = '' )` | the sections a feature's unit brings, for the dialog that deactivates or removes it |
| `\Nino\Modules\Legal::check( $appData )`, `notices( $appData )` | what is wrong with the setup, for the Dashboard; never called by a page request |

**Pages and routes.** The module routes the two pages itself, in `init()`: one route per language and page, with the language's path from `/nino/legal/paths` in `config.php` (the unit's default is `/impressum`, `/datenschutz` for `de_DE` and `/imprint`, `/privacy` for `en_US`), `locale` set and `'maintenance' => false`, so that the pages stay reachable while the site is in maintenance. A language without a path of its own gets a derived one, `/fr-fr/impressum`; a path that is invalid or taken by another route is left out and reported by `check()` instead of overwriting anything. Both routes of a page share one Element-URI, `/legal/imprint` and `/legal/privacy`, which the setup wizard and the Routes panel keep for these pages. The page text of each is `/_nino/webpage<uri>/{name,title,description}`.

**Placeholders.** A text names a fact of the website by a placeholder instead of writing it: `#/project/company/contact/email#`. Only keys below `\Nino\Modules\Legal::PREFIXES` (`/project/company/` and `/project/website/general/`) in the four-segment form of the key grammar are replaced, only in text and only after the text was made safe, so a value can be nothing but text: tags and entities are removed, the rest is escaped, and every bracket becomes an entity. A key without a value stays as it is written, so that it shows. The list of prefixes is a constant of the kernel and deliberately no setting.

**The legal navigation.** The wizard creates a third navigation, `legal`, with the two pages as its first entries, and the footer of the base frames outputs it with `[navigation nav="legal" id="legal__nav"][/navigation]`. The wizard creates it only while the project does not have one; a second run leaves the entries as the editors set them. Its entries are Element-URIs: a page that has one route per language is one entry.

**Features add their own sections.** A feature that processes personal data names a file in the `elements` key of its install unit (see [Features](features.md#the-install-unit)). Activating the feature adds the sections of that file with `\Nino\Elements::seed()` - add-only, never replacing an element or a language version that exists. Deactivating the feature does not delete them: the Dashboard and the dialog say that the text stays. A section deleted for good - in every language - is remembered in `/nino/elements/removed` in `config.php` (by the listener on `/nino/elements/committed`), so that the next update of the feature does not bring it back; creating the element again by hand removes the entry.

**A section can be changed.** `\Nino\Modules\Legal::SECTION`, `/nino/legal/section`, is the callback a section's text goes through before it is drawn: it receives `[ 'type', 'id', 'html' ]` and may add to `html`. The Consent feature puts its settings button into its own section with it.

**Copying a deleted type back.** A type that was deleted is no section, and the Dashboard names the file of the unit to copy back to `private/elements/<type>.php`: `_nino/Nino/Modules/Legal/install/elements/<type>.php`.

**Switching it off.** A project may remove `\Nino\Modules\Legal` from `/nino/modules` by hand. The two shortcodes, the routes and the Dashboard notices are gone then, and the elements stay where they are. Nothing in the kernel needs the module: the Language panel, the Routes panel and the Dashboard ask for the class before they use it.

**What the module stores about visitors.** Nothing: no cookie, no request to a third party. A reader of a section sees its text and nothing else.

---

## Developing a Custom Module

A module is a class with a static `init( array &$appData )` that the kernel calls for every class listed in `/nino/modules`: a feature's in the `Nino\Modules` namespace, a project's in one of its own below `app/`. The [runtime module recipe](recipes/runtime-module.md) writes a complete one - shortcode, endpoint, configuration, tests. A good module adheres to four rules:

1. `init()` registers behavior but does not produce output.
2. HTTP handlers modify the central response or use `Http::ok()`/`Http::fail()`.
3. Templating remains in shortcodes and `.tpl` files; PHP does not output page fragments unplanned via `echo`.
4. Variable files are protected during read-modify-write with `Filesystem::mutate()`.

### Directory and Autoloading

Nino's autoloader maps a class name to a file: the project-owned class `Project\Catalog\Catalog` is `app/Project/Catalog/Catalog/Catalog.php`, and `Nino\Modules\*` is a merged view over `_nino/`, `_admin/`, `features/` and `app/`, in that order. The roots, the rule and what it keeps a project from doing are the [autoload layout](recipes/runtime-module.md#autoload-layout) of the recipe. The kernel follows the same layout: `_nino/Nino.php` holds only the boot functions `\Nino\init()`, `request()` and `output()` plus the autoloader; every kernel class lives in its own file below `_nino/Nino/` - `\Nino\Filesystem` in `_nino/Nino/Filesystem/Filesystem.php`, `\Nino\Auth` in `_nino/Nino/Auth/Auth.php`, and so on - and is loaded on first use.

### Panels of the Workbench

A module can bring its own screen to the workbench. `/_admin` builds its navigation, content panes, bundles and text fills from a panel registry (`\Nino\Admin\Panels`), and a module joins it by answering one question:

```php
public static function adminPanels( array &$appData ): array {
    return [ \Project\Catalog\Admin::class ];
}
```

The kernel asks every active module in `/nino/modules` order (`\Nino\Modules::collect()`), so the screen exists exactly while the module does.

The workbench's own screens are the same thing in a different root: `_admin` holds the shell - the login, the rail, the panes, the registry, the bundles - and nothing else, and every screen in it is a module under `_admin/Nino/Modules/<Name>/`, laid out exactly like the ones above: `Admin/Admin.php` is the panel, `<Tab>/<Tab>.php` a tab of it, `assets/` its scripts and stylesheet, `text/` its words. `\Nino\Admin\Admin::modules()` reads the directory rather than a list, so a screen is added by adding a directory and removed by deleting one; without the directory `/_admin` is a login and an empty rail. A panel is a class with two required and a handful of optional static methods, no interface, no base class. `\Nino\Admin\Panels` lists them in its docblock, and the [panel recipe](recipes/admin-panel.md#the-panel-contract) has the table: what each method returns, and how `nav()`, `tabs()` and `scopes()` are read.

Every action method guards itself with `\Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM )`, which answers `401` without an account and `403` without the permission. The workbench's own modules are merged first, and a uri or action name one of them already owns is never handed to a runtime module. The shipped modules are the reference: `_nino/Nino/Modules/Form/Admin/Admin.php` is one with fills and a Dashboard tile, and the smallest complete panel in the checkout is the fixture `tests/fixtures/features/Sample/Admin/Admin.php` - in the catalogue, `features/Hello/Admin/Admin.php` is the panel of the feature written to be copied, and `features/Builder/Admin/Admin.php` one with its own template and a workspace layout. The [panel recipe](recipes/admin-panel.md) walks through a complete panel including its frontend.

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

`init( true )` boots without a `config.php`, because until the setup wizard has run there is none. `Admin::init()` then decides what the route serves: the wizard (`_admin/install/Install.php`) while `Admin::isInstalled()` says no, the login and the panels afterwards. The wizard is not a module from `/nino/modules`; a panel that ships as a module is, and comes through `adminPanels()` like any other.

`_admin/recovery.php` is the third entry point, booting the same way: it verifies the recovery secret (`\Nino\Admin\Recovery`) and offers a restore, setting the password of an account that exists and creating one with full access, nothing else. The workbench itself asks for the secret in one place, the Recovery password tab of Users (`Recovery::change()`).

---

## Error Handling and Logs

`Runtime` registers a common handler for PHP errors and exceptions. Deliberately triggered notices, warnings, and deprecation messages can be logged without ending the request, and so can a deprecation the engine raises - it says a future PHP will do something differently, not that this request went wrong. Exceptions, every other engine error, and `E_USER_ERROR` lead to a `500` response. A month's log keeps its newest 1000 entries: the file is one array, rewritten whole on every entry, so a template raising a notice per view would otherwise grow it with the traffic.

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

A session is started when something writes to it, not on every request: minting a CSRF token (so: rendering a form, or checking a post), signing in, and a visitor picking a language. A page that does none of those answers with no session, no `PHPSESSID` cookie and no session file - which matters both for the disk a crawler fills and for what a cookie banner has to declare. `\Nino\Runtime::startSession()` is the call that starts one; every write goes through it, and it answers `false` on the CLI, where there is no session to start. It answers `false` as well where PHP refuses to start one at all - an unusable `session.save_path`, say: the reason PHP gave goes into the error log and the request is answered without a session, rather than becoming a 500 nothing explains.

Authentication is additionally protected by:

- a dummy password hash against measurable differences with unknown users,
- failure limits per account and IP - the IP being the one `\Nino\Http::getClientIp()` resolves, so a site behind a reverse proxy needs `/nino/http/proxies` set or the whole internet shares one bucket,
- automatic rehashing of outdated password hashes,
- random session tokens with limited runtime,
- and the ability to revoke all sessions of a user.

### The Visitor's Address

`\Nino\Http::getClientIp( $appData )` answers who the request is from. That is the TCP peer (`REMOTE_ADDR`) - unless the peer is a reverse proxy listed under `/nino/http/proxies`, in which case it is the rightmost `X-Forwarded-For` hop that is not itself one of those proxies.

The key matters because every per-IP rule in the site reads this one value: the login cooldown's IP bucket, `\Nino\Mail`'s send cap, a form's rate limit, and the address a session is listed under in the workbench. (A session is not pinned to an address - its token is the key it lives under.) Behind a proxy without the key set, `REMOTE_ADDR` is the proxy for every single visitor, so all of them share one bucket - five contact-form submissions from anyone then hold up every visitor's mail for the rest of the window.

```php
'/nino/http/proxies' => [
    '198.51.100.7',       // one proxy
    '10.0.0.0/8',         // or a whole range
    '2001:db8::/32',      // v4 and v6, exact or cidr
],
```

Empty, which is the default, means "no proxy": `X-Forwarded-For` is ignored entirely. Fill it in only with proxies you actually operate or pay for - the header is one any client can write, and an address in this list that is not a proxy hands out exactly that spoof. Everything left of the rightmost untrusted hop is never read, because a forwarding proxy appends to the header rather than verifying what is already in it. The Config panel edits the same list and refuses a line that is neither an address nor a CIDR range.

### Response Headers

Each response starts with central security headers, including:

- `Strict-Transport-Security`,
- `Content-Security-Policy`,
- `X-Frame-Options: SAMEORIGIN`,
- `X-Content-Type-Options: nosniff`,
- `Cache-Control: no-store`.

The last one is Nino's own answer to whether a response may be stored. PHP used to give it, as a side effect of the session that was started on every request; now that a request without session state starts none, the header is declared in `\Nino\Http` instead - a project that wants its public pages cached by browsers and proxies changes that one value, and accepts that a visitor sees a page one render behind an edit for as long as it says.

Project code may specifically extend these headers. It should not generally replace or weaken them just to get a messy inline integration working.

### Outputs and Uploads

- Element fields are HTML-encoded or sanitized if explicitly allowed HTML.
- `Jstext` transfers data JSON-encoded and CSP-bound into JavaScript.
- The catalogue's Newsletter feature does not reveal whether an email address already exists.
- Image processing limits uploads to 8 MiB and source files to 20 million pixels before memory-intensive processing begins. `\Nino\Images::limits()` answers all of it for a form to name: the kernel's cap, what php lets a request carry (the smaller of `upload_max_filesize` and `post_max_size`, 0 where there is none) and the pixels. `\Nino\Images::reject( $bytes )` says why the kernel would refuse bytes - `image_too_large`, `image_type` or `image_too_many_pixels`, each with its params - and is what the upload endpoints of the workbench answer with.
- Every derived image is written as webp where gd can write one: lossless where the alternative was png, so nothing is lost and an alpha channel is carried along, and lossy where it was jpeg. Measured on a 1600x1000 source, that is 1 KB against png's 24 KB for line art and 151 KB against jpeg's 199 KB for a photograph. A gd without webp, or `'/nino/images/webp' => false` in `config.php`, writes png and jpeg as before; the shipped `.htaccess` declares the type for a host whose own `mime.types` predates it.
- PHP data files in publicly accessible directories receive protection stubs or only return values.

These precautions do not absolve project code of responsibility. Data from requests, files, or external APIs remains untrusted until it is validated for its specific target context and safely output.

---

## Tests and Change Workflow

Nino uses standalone smoke tests without PHPUnit: each file under `tests/` creates an isolated temporary project and checks one area, and every PHP suite loads `tests/harness.php` for the sandbox, `check()` and the summary. The commands CI runs - the nine PHP suites, the JavaScript loop, PHPStan and ESLint - are the one block in [Deployment](deployment.md#tests-before-go-live), and the [agent guide](../AGENTS.md#10-tests-and-validation) says when to run the whole block. A feature's own test, `features/<Name>/tests/<key>-smoke.php`, loads `tests/harness.php` from the checkout three levels up, or from the one `NINO_ROOT` names - which is how the same test runs against another Nino version; the [feature recipe](recipes/feature.md#9-the-features-own-test) writes one.

For changes to the kernel or a module, the following workflow is recommended:

1. First reproduce or specify the behavior in the appropriate smoke test.
2. Implement the smallest possible change.
3. Run all PHP and JavaScript smoke tests, the syntax checks and the static analysis.
4. For changes to routes, files, or callbacks, also adjust the corresponding documentation.
5. Explicitly justify security-relevant exceptions such as `csrf => false`, unlocked HTML, or additional CSP sources.

Avoid assertions that only fix internal intermediate steps. A good smoke test checks the visible contract: response, saved data, permissions, lock behavior, or generated project structure.

---

## Callback Reference

The hooks the kernel and the integrated modules fire are the call sites of `\Nino\Callbacks::doCallbacks()`, each next to the code that fires it and the argument it passes. Callback names are simple strings. Still, treat the established names and argument forms like an API: a rename or changed argument type can affect every registered module. The names under `/nino/*` are the kernel's.

Most hooks say that something happened. `/nino/mail/send` and `/nino/images/render` are the two that replace a kernel action instead: `\Nino\Mail::TRANSPORT` and `\Nino\Images::RENDER` document what a handler receives and what it sets to take the job over. Both are fired after the checks that keep an endpoint safe - the per-ip mail cap, the byte cap, the image type and the pixel cap - so that a feature cannot switch them off by registering.

### Two extension surfaces, one idea

A module reaches the framework in two ways, and the difference is not events
versus methods - it is *reaction* versus *declaration*.

**Reaction** is what callbacks are for. Something happened; whoever cares runs.
The firing side knows no listener, several may run in priority order, and some
of them may refuse (`/nino/elements<type>/insert` and its siblings return
`false` to veto). Every callback name is of this kind.

**Declaration** is what the `/_admin` panel contract is for. A module answers
`adminPanels()` with a class, and that class *states what it is* in the
workbench: uri, label, weight, group, permission, panes, assets, text, tabs
(see `\Nino\Admin\Panels` and [Panels of the Workbench](#panels-of-the-workbench)). The tool reads that into a registry
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
