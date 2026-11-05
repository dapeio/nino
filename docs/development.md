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

`Http::request()` does not read directly into arbitrary project variables but normalizes method, URI, query, header, body, basic auth data, and client IP under `/nino/http/request`. The query is read as PHP reads one, within PHP's own limits: the first `max_input_vars` pairs, and no key nested deeper than `max_input_nesting_level`. A query with more is cut to that, not refused - PHP's warning for it is fatal here, and one request with a long enough query would otherwise be a `500` on every address. Simultaneously, a response is created with an empty body, status `200`, and the preset security headers.

The runtime textfills are added in two passes, and the split matters. `/nino/dir`, `/nino/public`, and `/nino/date/year` answer to `$appData` alone, so they are registered **before** `Http::response()`: a response callback that renders a template is a real caller — `Modules\Form` and `Modules\Newsletter` build their HTML mails in exactly that window, and a fill registered after it would reach them as the literal `[[/nino/public]]`.

`Http::response()` searches for a matching route under `/nino/http/routes`, takes over its values into the prepared response, and then executes the global and route-specific response callbacks. `Locales::response()` takes over the language resolved by the route. Only after that does Nino add the textfills that need the resolved request: request URI, response URI, locale, and current user.

`Html::response()` renders the body only if it is a string. Arrays and other structured values remain unchanged and are later output as JSON.

### 3. `\Nino\output()`

`Http::output()` finalizes the response:

- Non-string bodies are JSON-encoded and receive a suitable `Content-Type`.
- Project and standard headers are merged.
- Status code and headers are sent.
- A response that names a `file` has the file sent in place of its body, after the headers and in pieces: whole, or as the one byte range the request asked for. Every output buffer is ended first and PHP's own output compression is switched off for the request, so that the bytes leave as they are, and the visitor's session is closed, so that a long download does not keep their other requests waiting (see [Answering a File](#answering-a-file)).
- For `HEAD`, the body is not output - and no byte of a file.
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

A page in several languages has one route per language: the routes share the page's `uri` and each carries the `locale` of its language, so `GET://contact` (`en_US`) and `GET://kontakt` (`de_DE`) are one page, each reached at the address of its language. A route without `locale` serves every language. The address that is requested sets the visitor's language when its route has a `locale` (`Locales::response()`) and leaves it as it is when it has none; the language switch sends the visitor to the page's `GET` route for the language chosen (`\Nino\Http::findRouteUri()`, which asks for the method as well, since the endpoint of a form can share the `uri` of the page it stands on). The Routes panel writes this the way `\Nino\Modules\Legal` does: an address of one language is a route with that `locale`, the address the other languages share is one route without - a page has at most one -, and a page whose languages share one address keeps the one route. The fill `/_nino/webpage<uri>/uri` is the address of the current language: every language's `text/<locale>.php` holds its own, `global.php` the one that serves every language, else the native language's.

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

### Answering a File

A response that is a file - a download, a document, an audio or video file - names it instead of holding it. The module sets `file` on `$request['/nino/http/response']` to the absolute path of a regular, readable file, sets the headers it knows (`Content-Type`, `Content-Disposition`, `Cache-Control`) and writes no body:

```php
// in init(): the endpoint belongs to the module
$appData['/nino/http/routes']['GET://download/report'] = [ 'uri' => '/download/report' ];

\Nino\Callbacks::registerCallback(
    $appData,
    '/nino/http/response/GET://download/report',
    static function( array &$appData, array &$request ): void {
        $request['/nino/http/response']['file'] = \Nino\Filesystem::path( $appData, '/data/reports/report.pdf' );
        $request['/nino/http/response']['header']['Content-Type'] = 'application/pdf';
        $request['/nino/http/response']['header']['Content-Disposition'] = 'attachment; filename="report.pdf"';
        $request['/nino/http/response']['header']['Cache-Control'] = 'private, max-age=3600';
    }
);
```

The path is the module's own: resolved through `\Nino\Filesystem::path()` or from a directory the module owns, never built from what the request says. An identifier in the address is checked against a list of the module's before it picks a file, a file behind a permission is named only after `\Nino\Auth::checkPermission()` has said yes, and the name in `Content-Disposition` is a header value like any other: one that comes from data is built from an allowlist of characters, never from what a request or an editor typed. The module does not read the file, does not call `readfile()` or `fpassthru()` and does not echo it: the kernel sends it, after the headers and in pieces of at most 1 MiB with a flush after each, so that a file of any size costs no more memory than one piece, and stops when the client has gone. (`\Nino\Http::sendFile()` is the one place besides the body where the response path writes to the output.)

What the kernel makes of a response with the status `200` that names a file:

- `body` is ignored and emptied, whatever type it has. `Content-Length` is the size of the file, `Accept-Ranges: bytes` is added, `Content-Type` stays what the module set and is `application/octet-stream` where it set none, and `Last-Modified` is the time of the file as an HTTP date unless the module set one. The headers every response starts with are merged as for every response.
- For `GET` and `HEAD`, a request for one range - `Range: bytes=0-499`, `bytes=500-` or `bytes=-500` - is answered `206 Partial Content` with `Content-Range: bytes 0-499/1234` and the length of the part as `Content-Length`; an end beyond the file is its last byte. A range that no byte satisfies - a start at or past the size, a suffix of `0`, any range of an empty file - is a `416` with `Content-Range: bytes */1234` and no body. A `Range` that is not of this shape, or asks for more than one range, is ignored and the whole file is answered, as is any `Range` sent with another method. `\Nino\Http::byteRange()` is that arithmetic, public and pure.
- A `HEAD` request gets the headers of the `GET` and not a byte.
- A `file` that is no regular, readable file when the response is sent - gone, renamed, a directory, no string - is a `404` with the body `Not Found` as plain text, without the `Content-Disposition` and the `Cache-Control` the module set for the file, and the kernel records a warning that names no path: a path is never written into a response.

What is to be sent is left on the request under `./nino/http/stream` - `[ 'path' => ..., 'start' => ..., 'length' => ... ]` - by `_finalizeResponse()` alone - `output()` sends it for every method but `HEAD`, which gets the headers alone - and the key is absent where there is nothing to stream (no `file`, a status other than `200`, a `404`, a `416`). A response whose status is not `200` does not answer a file: its `file` is not read and the response is sent as it is, a `Range` on it or not. `If-Range` and ranges in several parts are not implemented - `If-Range` is not read, so a range is answered whether or not the file changed since the client saw it, and a request for several ranges is answered with the whole file. The Cache module never stores a response that names a file.

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
2. values of the current locale from `/text/<locale>.php` - and, for every key the language has no text for, the text of another language (below),
3. runtime values added with `Html::addFills()`.

**A language that has no text.** A key whose value in the current language is missing or empty - empty is `trim( $value ) === ''`, so a text of whitespace is empty too - takes the value of the first language of a chain that has a non-empty one: the native locale (where the site serves it), then the other available locales in the order `config.php` lists them, then the other languages that have a text file (`\Nino\Locales::getTextLocales()`: a file on disk of a language that is switched off, so that a site whose only language has no texts of a feature yet still shows the ones another file has). The language's own non-empty text always wins, `global.php` is as it was, the runtime values win over everything - a fill added with `Html::addFills()` for the language or for `'*'` is never replaced by another language's text - and `'*'` has no chain. One key never falls back, `/project/website/html/lang`: a French page with the German tag is worse than none.

`\Nino\Html::fillOrigin( $appData, $key )` answers which language a key's text was taken from - `'de_DE'` - and `null` where it is the current language's own, where a runtime value stands for it and where no language has one. It is what a block that shows a text reads to say in which language it is: the components write a `lang` attribute (below). The file layer - global, the chain, the language's own file - is made once per request and language and kept in `./nino/html/filecache`; a write under `/text/` through `\Nino\Filesystem` drops it, and with it the list `getTextLocales()` keeps in `./nino/locales/text`, so a text saved in a request is the next fill of that request. A write that goes to disk behind the filesystem - the restore of a backup - drops both itself.

`\Nino\Locales` has three helpers for this: `getTextLocales( $appData )`, every language that has a file `/text/<locale>.php` - the available ones first, in `config.php`'s order, then the others by their code; `getVisitorLocale( $appData )`, the language the site shows the visitor of this browser (the one they picked where it is available, else the project's default), which in a workbench request is not `getCurrentLocale()` - that one is the interface language there; and `languageTag( $locale )`, the BCP 47 tag of a locale id, `de_DE` is `de-DE`, the value of a `lang` attribute.

**The tag of a page.** `[[/nino/html/lang]]` is a runtime fill worked out for the language a page is rendered in: the value of `/project/website/html/lang` where the language has one of its own, else the language's tag. The templates the kernel ships - the base unit's `html-header.tpl`, the Form module's `mail-header.tpl` - write it into `<html lang>`; a project's own templates keep the key they have, which a language added in the Language panel presets with its language subtag (`fr` for `fr_FR`). The workbench's shell has a tag of its own, `[[/_admin/lang]]`: the language its words are in, which is English where the workbench has no words of the interface language.

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

Two forms are the system's, not the project's: `/_nino/webpage<uri>/<name|title|description|uri>`, the details of a page after its Element-URI (which may hold slashes and dots), and `/_nino/locale/<code>/name`, the name of a language. The setup wizard and the Routes panel write the first - for the pages that features route at runtime, in the Routes panel's section "Routes of features and modules" - and the setup wizard and the Language panel write the second: each gives a language it adds to the project its name in its own tongue (`\Nino\Locales::ENDONYMS`: `Deutsch`, `English (US)`, `Français`), or the code where that table has none, and leaves a name that is there. No unit delivers it, since the text of a unit reaches every project whether it has the language or not. `/_admin/...` are the workbench's own words and stay as they are.

The server holds people to the grammar where they make keys: the Text Keys tab creates and renames only keys that follow it (`\Nino\Text::isGrammarKey()`), and refuses to rename a key under `/_nino/` or `/_admin/`. Keys a project already has are left alone and stay editable. `tests/keys-smoke.php` holds everything Nino ships to it and checks that every key a shipped template reads exists.

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

What a template calls can be read without running it: `\Nino\Html::shortcodeCalls( $appData, $html )` answers the calls of the registered shortcodes in a piece of html+, in the order they stand - `[ 'name' => 'example', 'args' => [ 'first', 'limit' => '3', 'content' => 'Content' ] ]` for `[example first limit="3"]Content[/example]` - found with the pattern and the argument splitting of the rendering, so that a scan sees what the page will run. The calls between the tags of a call follow the one that holds them, and a name nobody registered is no call. A third argument, a list of names (`[]` by default), names calls whose content is not looked into: the call itself is found and what stands between its tags is not, which is how `Components::sources()` passes the stacks of the cell `none`.

A shortcode that says what it takes - a source for its value and attributes with a type and a default - is a **component**, and one that loops the elements of a type around its content a **stack**: see [Components](#components).

### Elements in Templates

The shortcodes `[element]` and `[elements]` load structured content. Within their block, fields are addressed with `[[field]]`; `[[.id]]` contains the internal element ID, `[[.uri]]` the uri of the element, `/<type>/<name>`, and `[[.slug]]` the name alone - the last segment of the uri, which is what a link to a page of the element needs: `<a href="/blog/[[.slug]]">`. The type is in `[[.uri]]` and not in `[[.slug]]`; an element without a uri has a slug of nothing.

```html
[elements /services sort="-date" limit="6" query="featured=1"]
    <article id="service-[[.id]]"[[.lang]]>
        <h2>[[title]]</h2>
        <p>[[description]]</p>
    </article>
[/elements]
```

Where the language of the page has no text of an element - the element has no version in that language, or a field of it is empty - the block shows the text of another language instead: `\Nino\Elements::getElementWithFallback( $appData, $uri, $locale )` answers the element with each field of the model that is per language (`'locale' => true`) and missing or empty in `$locale` taken from the native language, then the other available languages in their order, then the others the type file holds a bucket of, and `'.lang'` beside it - the tag of the language that gave the first field taken, `''` where the language had it all; `false` where no language has the element. `[elements]` draws every element through it, and `[[.lang]]` is that tag as an attribute with its space in front - ` lang="de-DE"` - or nothing: put it on the element that shows the text, so that a screen reader reads a German text with a German voice on an English page. The query lists the elements it lists - an element that exists in German only is listed on the English page, and shown - and a field of the model that no language has a value for is replaced by nothing, so that the block never shows `[[summary]]` or the `[]` the shortcode pass made of it. A sort is the order of what is drawn. `[element]` and the stacks of the Components module read the language they are asked for, as before.

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

A feature does not write that array. It names its files under `assets` in its manifest, and `\Nino\Features::registerAssets()` adds every `.css` to `/.cache/style.css` and every `.js` to `/.cache/script.js` while the feature is active - see [Assets](features.md#assets).

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

The kernel classes live one per file, in `_nino/Nino/<Class>/<Class>.php`, and are loaded on first use; the docblocks of each state its public tasks. Use the public methods instead of the internal implementations starting with `_`. This keeps project code decoupled from details like cache invalidation, file format, and session rotation. `\Nino\Feature` is the class a feature calls for what every feature needs - the template its markup lives in, the class attribute, a flag, a counter per client, a slug, the widening of the Content-Security-Policy - and its docblocks are the reference; [the toolset](features.md#the-toolset) of the feature manual lists it with the rest of what a feature calls. The browser has the same for a feature's scripts, in `Nino.js`, `Nino.ui.js` and `Nino.admin.js` (the docblocks are the reference there too): `Nino.adminUi.panel()` is the lifecycle of a workbench panel, with `levels()`, `hint()`, `textField()`, `button()`, `el()` and `localeSelect()` beside it; `Nino.ui.options()`, `whenVisible()` and `reducedMotion()` serve a site script; and `Nino.slug()` and `Nino.clipboard()` serve a site script and a panel alike.

---

## Integrated Modules

Modules are activated in `/nino/modules`. The order of the array is relevant if several of them register callbacks of the same priority. The modules Nino ships are the directories of `_nino/Nino/Modules/` (`\Nino\Modules\*`): the always-on ones, which `\Nino\AppData::DEFAULTS` lists, and `Form`, `Legal`, `Localepicker`, `Maintenance` and `Navigation`, which the setup wizard applies on every run and a project may still switch off by hand. `_nino/` stays replaceable wholesale. Everything beyond is a **feature**: an installable package under `features/<Name>/` with a `feature.php` manifest, switched on in the workbench's Features panel and bringing its panel the same way. A checkout ships none; they come from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features), and each documents itself in the `manual` of its manifest. See [Developing a feature](features.md), [Panels of the Workbench](#panels-of-the-workbench) and the [autoload layout](recipes/runtime-module.md#autoload-layout).

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
	'group'      => 'section',       // required here, the group a tool offers it under: section | hero | card | global
] );
```

A component whose source is a text key - the title, the subtitle and the text of a section, a card and a hero, the button - writes ` lang="<tag>"` on its outer element where the language of the page has no text for the key and the text it shows is another language's (`\Nino\Html::fillOrigin()`), and nothing where the text is the page's own, where the call fixes it with `text="..."` and where the value is a field of an element. The templates of the registry (`Components::$html`) have a `[[lang]]` slot for it where the attributes stand, filled the way `[[class]]` is; a renderer of a feature that draws a text of a key puts `\Nino\Locales::languageTag( $origin )` on its element the same way.

`addComponent()` and `addStack()` register one: they call `\Nino\Html::addShortcode()` with a wrapper around the callback, and **replace** a component or a shortcode of the same name, which is how a project changes the markup of one. The rest of the API - `components()` and `stacks()`, `defaults()`, `sources()`, `value()`, `element()`, `renderStack()`, `panels()`, `cell()` and `escape()` - is documented where it stands, in `Components.php`. `sources()` is what a tool reads a template with: the keys and the slots it names as the source of a component, found as the rendering finds the calls - and the slots it names in an attribute of the type `image`, a card's `background` among them, as an entry that says which attribute it was. The Text Keys scan reads them besides the fills, the Image Slots scan besides the `<img>` tags, and both lists of where a key or a slot is used read them, since a page the Builder writes names its texts and pictures in no other way.

**The schema.** `label` is a string or a locale map, and every attribute needs a `default`. An attribute is a `string`, an `int` (`min`, `max`), a `bool`, a `select` (`options`, a list, with the default among them - or the name of a static method, see below) or `lines`, and five types that only exist here: `key` (a text key), `image` (an image slot), `href` (a key or an address), `field` and `variant`. A **field** is the name of a field of the element a stack loops, letters, digits, hyphens and underscores starting with a letter (`imageAlt` is one): it tells a tool that a field is chosen here, and the kernel reads it as it reads the `field` of `[col]` - a default that is empty or such a name is accepted, and a value that is none is the default. A call that writes the attribute empty (`title=""`) says no field, whatever the default is, and reads as the empty string; a call that leaves it out reads as the default, as does a value that is no field name. A field says `required`, `false` where it says nothing: `true`, `false` or the name of a `bool` attribute of the same schema, in any order, which makes the field required while that attribute is on (a logo's `link` while `links` is on). Only a field has it, `validate()` refuses it on another type and a name that is no `bool` attribute of the schema, and `components()` and `stacks()` hand it out, `false` included. The kernel enforces nothing with it - it is a statement for a tool, as `panels` is - and none of its own fields is required: the title of `[tabs]` and `[accordion]` numbers the panels where it is empty, and the field of `[col]` has a line of text or a content to show instead. A **variant** is a `select` whose words are the modifiers of one class: the renderer writes a word that is not empty as that class, the `prefix` and the word - the prefix is the start of a class that ends in two hyphens (`nino-ink--`) where the schema says it, and the class the component is named for with two hyphens where it does not, so that `style="loud"` of a title is `nino-section-title--loud` - and so a variant's options are words, lower case letters and digits with hyphens between them, never a class name: that is how a tool tells a variant from a choice of something else, and draws it as a row of buttons by default. **Options from a method.** The `options` of a `select` or a `variant` are either that list or a string `'\Namespace\Class::method'`, a public static method for a choice that only the running project can list - the forms or the albums it has. The method is `public static function method( array &$appData, string $attribute ): array` and answers `value => label`, a label being a string or a `locale => string` map as `label` and `hint` are (`\Nino\Features::localized()` reads it); the empty word is allowed, and an empty answer is an empty list. **A feature's manifest** names a static method of the feature's own class, `\Nino\Modules\<Directory>` or a class below it, and nothing else - a schema is not to ask any other static method of the kernel or of the project. It is read on paper alone, because a manifest is read for features that are off, from a staging directory before an install and while the previous version of the class is the one that is loaded: the string has to be of that form, the class has to be the feature's and its file has to stand where the autoloader looks for it below the feature's directory, the `default` has to be a string (a `variant`'s a word or the empty word), and `input` cannot be `icons`. Nothing is loaded and nothing is asked then. **A module's** `addComponent()` is checked as a whole: the class has to load and the method has to be a public static one of it. Whether the default is among the options only the answer can say, and a value outside the list is the default as with a list of its own. The method is asked when `components()` or `stacks()` hand out the schema and when a value of the attribute is made ready for a render, whichever comes first, and **once for each component or stack and attribute in a request**; asking is also where a class that does not load or a method that is none is found out for a manifest. What `components()` and `stacks()` hand out carries the answer in the place of the name: `options` the values and `optionLabels` the `value => label` map, the labels as the method gave them. They are the schemas a tool is given and not schemas to register: `Components::registered( array &$appData, bool $stack = false ): array` answers the registry as it was registered, with the name of the method and nothing asked, which is what a feature reads that registers a component again in its `init()`. A tool loads the list once per session, not each time it draws the attribute, and takes the order from `options`, using `optionLabels` only to look up a label: a JSON object puts integer-like keys first, and an answer keyed from 0 on encodes as a list. A list that comes from project data goes stale, so a tool must tolerate a stored value that is not in the answer any more. An answer that cannot be read - no array, a label that is no string or map, the word of a `variant` that is none, a class that does not load, a method that is none, or a method that throws - is an `E_USER_WARNING`, once, and the attribute is a `string` for the rest of the request: the schema handed out says `string` without `options`, `input` and `prefix`, the value of a shortcode goes through as it is and a tool draws a text field, so that a page does not stay empty because a list could not be made. Only a `variant` keeps what it is: its value passes as the empty word or a word, and is the default otherwise. **A renderer therefore validates the value itself** wherever it names a record or a file, since after a failure any value passes. A method that asks the registry itself finds its own attribute a `string`. A listed feature whose method is not there at registration is a warning and the component is left out, not the `E_USER_ERROR` of `addComponent()` on every request. `label`, `hint`, `input` and `tab` of an attribute are optional. `input` is a hint for a tool on how to draw the attribute, and nothing a renderer reads: for a `select` and a `variant` `select` (the default), `segment` (the words as a row of buttons) or `icons` (the icons of an alignment - the options are the empty word and words of `left`, `center` and `right`, of `top`, `middle`, `bottom` and `last-bottom` (a card's own) or of `start`, `center` and `end`), for a `bool` `switch` (the default) or `checkbox`; no other type has one. `tab` is the other hint of that kind, on an attribute of any type: one word of the short list the registry knows (`\Nino\Modules\Components::TABS`, today `background`), which says that a tool draws the attribute on the tab of that name in its dialog for the component - the Builder draws the attributes of a tab together on a tab of their own, after the properties - and so knows no component by name; an attribute that says none stands among the properties, and has no such key in the registry. The kernel's card says `background` for its `ground`, `background`, `focus` and `dim`. `class` is allowed on every component without being declared: the renderer puts it at the end of the classes of its outer element. `draws`, `in`, `holds`, `stretched`, `icon`, `link` and `by` are optional and tell a tool something about a component without changing how it renders - but for `link`, which does, and `stretched` where `link` stands with it, which gives the `<a>` a class. `draws` is what the renderer draws on the outside, written the way a builder shows it: the tag, a space, the one class a theme addresses the element by - `{tag} .nino-section-title`, `a .nino-btn`. It is a non-empty string of at most 64 characters from `a-z`, `0-9`, space, `.`, `{`, `}` and `-`, and a name in braces is an attribute of the schema, put in from the shortcode or else from the default. `in` says where a tool offers the component, and a place says where a component stands: a non-empty list of distinct words from `section` (directly in a column - also in the cell of a stack that holds no card), `card` (in a card), `hero` (directly in a column of the hero section, the first section of a page when it has a cover), `table` (between the tags of a `[table]`) and `panels` (between the tags of a stack that says `panels`); without it the component is offered everywhere. `holds` is one of the same words, and only a component whose source is its `content` may have it: what stands between its tags is then the calls of the components of that place, which are rendered with it and which a builder edits as its children. The kernel has one, `[card]`, which holds `card`. `stretched` is a bool, false where the schema says nothing: a component that says `true` draws one link, and that link may be the link of a linked holder it stands in - a card with `linked`. A holder cannot say it, a holder is no link, and the schema is refused. Where `link` is true the `<a>` the kernel makes is that link and carries the class `nino-stretch` (`<a class="nino-stretch" href="...">`, the class first); a component that draws its own `<a>` gives it the class in its template, to the one link it draws and not to the links of a list. The renderer reads no context, so the class is written in a card and out of one, and `Nino.css` reads it only under `nino-card--linked` and `nino-grid--linked`, where it stretches that link over the whole card or the whole column; the kernel's `card-title` and `title` are the two components of the kernel that say it. Two such links in one card or column both cover it and the later one lies on top: one linked title per linked surface is meant. A tool that edits the link of a linked holder edits the link of a component that says `stretched`. `icon` is the picture a tool shows the component by - a tool shows one of its own where there is none - and it is geometry and not markup: a list of 1 to 12 parts of a 24 by 24 icon that the tool draws with a stroke, each part a tag (`path`, `circle`, `ellipse`, `rect`, `line`, `polyline` or `polygon`) and the map of the attributes that tag takes, every value a string, as in `[ 'path', [ 'd' => 'M6 12h12' ] ]`; the kernel's nineteen carry the icons of Lucide. `link` is a bool, false where the schema says nothing: a component that says `true` has two more attributes, `href` (a key, an address or in a stack a field of the element, as a button's) and `target` (`_blank` or none), after the ones it declares and before `class`, and where `href` comes out as an address what its renderer drew is wrapped in `<a href="...">`, with `target="_blank" rel="noopener"` for `_blank` - the address is made safe as a button's is, a scheme that is none a link may take is a `#`. It stands for a component that draws one thing and no link of its own: the schema may not declare `href` or `target` itself, and a text that holds a link of its own would be a link inside a link. The `<a>` is the outer element of a linked component, so a rule that names the component as the direct child of something (`.nino-card-cols > .nino-card-img`, `.nino-text-center > .nino-section-text--loud`) does not match it while it is linked: write the rule for the anchor as well, or without the `>`. `by` names who registered a component - a slug, which a tool groups what it offers by: `nino` where the schema says nothing, which is the kernel's own components and what a module of a project registers, and the key of the feature for the components of a feature's manifest, whatever the manifest says of it. `group` is the word a tool offers a component under - `section`, `hero`, `card` or `global` - and what `by` says decides whether it has one: a component of the kernel or of a module of a project (`by` is `nino`) has to declare it, as one of the four, and one of a feature's manifest declares none, since a tool offers it under the feature: a manifest that names one is refused. It is not `in`: a place says where a component may stand and a group where it is offered, so that the title of a hero, which stands in a section as well (`in` is `[ 'hero', 'section' ]`), is offered with the hero (`hero`) - and `global` is the group of what stands anywhere, a text, a picture, a button, the html and a spacer. A component that draws another element in another place is another component - the kernel has six titles - because no renderer reads a context. A stack declares none of the six, nor a `group`. A stack says `grid => true` if it takes `cols`, `gap` and `autoheight` and draws cells, and says what its **cell** is with `cell`, one of `components` (the default: what stands between its tags, drawn once for each element), `section` (the cell is a whole section), `columns` (what stands between the tags is the `[col]`s and nothing else), `row` (it is one field or one line of text, the row of a list) or `none` (it has no content: the renderer draws each cell from the fields its attributes of the type `field` name, the call is a single tag such as `[logos /logo image="image" link="url"]`, and what stands between a pair of tags is not read: a single tag that is followed later by a `[/name]` is paired with it, and everything between the two, a second row of the call included, is content that is not drawn, so it is written as a single tag and closed nowhere) - a tool reads it to know what it may put between the tags without knowing a stack by name - for `none` it offers no content and shows the `field` attributes as the choice - and a schema with another word is refused. A stack says `panels => true` if, where its call names no type, it takes `[panel]`s between its tags for its panels, one cell each, instead of looping a type: the kernel's `[tabs]` and `[accordion]` do, the schema of every other stack says `false` (the default), and a value that is no bool is refused. A tool reads it to offer such a stack as a group of panels without knowing one by name, and a feature's stack may say it too and read its panels with `Components::panels()` (see the fixed panels below). It may name `assets` (`css` and `js`, project paths) for a tool to tell the developer which files it needs - nothing adds them to a page. `preview` names one of the fixed pictures a builder draws it as. The names `value`, `source`, `content`, `text`, `uri` and `grid` are taken, and so are the arguments of a stack's loop - but for `text` in a stack, which is its own: a line of text its cells fill, and no fixed text that its value is made from, so that the fields in it are not put in before the cells are drawn. **A schema the module cannot work with is refused at registration** with an `E_USER_ERROR` that says why: a name that is no slug, an unknown `source` or type, a `select` with no options or a default outside them (for options that are a method: a name that is no `Class::method`, in a manifest a class that is not the feature's or has no file in its directory and in a module's registration a class that does not load or a method that is no public static one, a default that is no string (a variant's: no word or the empty word), `input` icons), an attribute without a default, an `int` outside its bounds, a place in `in` that is none of the five or is named twice, a `holds` that names no place or stands with another source than `content`, a `stretched` that is no bool or stands with `holds`, a `draws` that is empty, of other characters or more than 64 or with a placeholder that no attribute answers, an `icon` that is no such list of parts or holds more than 2048 characters of values, a variant with an option that is no word or a prefix that is no start of a class, an `input` that its type does not have or icons for words that are no alignment, a `tab` that is none of the words of the list, a `link` that is no bool or that declares an `href` or a `target` of its own, a `by` that is no slug, a `group` that a component of the kernel or of a project lacks or that is none of the four, a `group` in a component of a feature, a `required` on an attribute that is no field, one that is neither a boolean nor a name, or a name that no bool attribute of the schema answers to. A manifest is read the same way, before it is applied (see [Developing a feature](features.md#components-and-stacks)). Rendering does not validate: an attribute that is not declared is ignored, a `select` outside its options (the list a method answers too) is the default, a number that is none too, so a file written by hand may be incomplete and still renders.

**What a renderer receives.** `public static function componentTitle( array &$appData, array $args ): string`, with `$args` made ready by `dispatch()`: the first argument resolved by `value()` and made safe (`value`, and `source` as it was written), what stands between the tags (`content`, empty for a stack of the cell `none`), every attribute under its name with the defaults filled in and a `select` reduced to its options, and `text` where the shortcode had a fixed one, read with the fills in it put in (see the two rules after the stacks) - for a stack it is the line its cells fill and arrives as the call wrote it. A component whose value is `null` - or empty, where it has a source - renders nothing: an empty slot is no broken picture, and a missing field no empty heading. What a renderer returns is rendered again like the output of any shortcode, which is why `value` is escaped and has every `[` written as `&#91;` (the rule is in [AGENTS.md](../AGENTS.md#rendering-and-escaping-rules)); `value()` documents what the first argument can be - a text key, a field or the place in the loop in a stack, a fixed `text="..."`, a picture reference, an address. An address that is a key under `/_nino/webpage/` - `/_nino/webpage/contact/uri`, the address of a page within the project, which the Routes step writes without the directory the project is served from - is written with that directory in front of it, `/shop/contact` for a site at `/shop`, as the navigation writes it.

The kernel brings the titles and subtitles of a section, a card and a hero, `[text]` and `[card-text]`, `[image]` and `[card-image]`, `[logo]`, `[button]`, `[html]`, `[spacer]`, `[card]`, `[scroll-hint]`, `[line]`, `[col]` and `[panel]` as components, and `[stack]`, `[repeat]`, `[slider]`, `[filter]`, `[tabs]`, `[accordion]`, `[list]`, `[table]` and `[sections]` as stacks. The registry lists them in the order a column offers them: the title, the subtitle and the text of a section with the line of fields after it, then those of a card with its picture, then the title and the subtitle of a hero, then the card, the button, the image, the logo, the html, the spacer and the scroll hint, then the column of a table and last the panel of a fixed group. Ten of them can be a link - the titles, the subtitles, the texts and the two pictures; not the card, which holds buttons, the button, which has an address of its own, the logo, which stands in the link of a frame, the html, the spacer and the scroll hint - and the eight texts have a `color`, a variant after the `style`: `muted` is the ink the text inherits, at four fifths of its strength (`color-mix(in srgb, currentColor 80%, transparent)`), and `accent` the ink of a link on the ground the text stands on (`nino-ink--muted`, `nino-ink--accent`). The muted ink fades on any ground, a dark or a primary one included, where the quiet ink of the ground is the ink itself; it fades the colour and not the box, as an `opacity` would, so that what stands in the text with a ground of its own keeps it. A title or a subtitle that says `muted` takes that ink in place of the colour of its own role. A text has no light, no dark and no status colour, because the ground it stands on says what is light on it.

The six titles share one renderer and differ in the class they draw, which is their place: `title` and `subtitle` are for a section, and offered in the hero as well (`in` is `[ 'hero', 'section' ]`, `group` is `section`), `hero-title` and `hero-subtitle` for a hero, and offered in a section as well (`[ 'hero', 'section' ]`, `hero`), `card-title` and `card-subtitle` for a card (`[ 'card' ]`, `card`), and `draws` names the tag and the class (`{tag} .nino-card-title`, `{tag} .nino-hero-subtitle`). `[card-text]` and `[card-image]` are the text and the picture of a card, offered in a card alone (`card`), and draw `div .nino-card-descr` and `img .nino-card-img`. `[button]` declares `draws` too, `a .nino-btn`, and is offered everywhere; `[text]`, `[image]`, `[html]` and `[spacer]` declare neither, and neither does a stack. `[logo]` declares `in`, `[ 'hero', 'section' ]`, and `draws`, `span .nino-logo-set`. These six, the ones that stand anywhere or in a section and a hero, are the group `global`. `[card]` declares all three: `in` is `[ 'section', 'hero' ]`, `draws` is `article .nino-card` and `holds` is `card`, and its group is `card`, as it is that of the title, the subtitle, the text and the picture of a card. `[scroll-hint]`, the arrow of a hero, is offered under `hero`. `[spacer]` draws an empty `<div class="nino-spacer nino-spacer-<size>">`, `size` a step from 1 to 6 (2 where it says none) and the class a height of that step of the space scale - a height, not a margin, so that a margin class a column writes on it (`nino-mt-2`) is room beside its size and does not compete with it. The registry carries the keys as the schema has them - `components()` and a feature's manifest are both read by `validate()`.

`[card]` is a component that holds components: what stands between its tags is the calls of the components of a card - a title, a text, an image, a button - and it draws the `<article>` around them with its ground, its padding, whether it takes the whole height of its column or cell (`fill`), where its content stands in a card taller than it (`vertical`), how the text and the buttons are aligned, a picture behind the content, its border, whether its link - the title's, or that of another component that says `stretched` - is the link of the whole card (`linked`) and a class of its own; `componentCard()` documents the attributes and the order of the classes. `linked` is the stretched link: the card is no `<a>` - it holds buttons, and a link in a link is no markup - but writes the class `nino-card--linked`, and `Nino.css` stretches the link of the component that says `stretched`, the `<a class="nino-stretch">`, over the whole card by its `::after` - for `[card-title]` the `<a>` around its title when the title has an `href`, for a component of a feature the one it draws itself - so that the keyboard has one stop, named by that link and shown as a ring on the card, the pointer has the whole area, and the buttons, links, fields, frames and players in the card are lifted over the overlay and keep targets of their own. A card with no such link shows nothing of it, and text in a linked card cannot be selected. The same component stands once in a column - a card beside a picture - and once per element in a stack: the cards of a loop. It reads no context itself; the components in it do. The content is not rendered by the renderer, which calls no `renderHtml()` of its own: the engine renders the output of a shortcode again (`\Nino\Html::_doShortcode()`, to a depth it caps - that is what makes `[template]` work), so the components in the card are rendered when the card is, and in a stack with the element still their context, because `renderStack()` holds it around the rendering of the stack's content. The children of a card are sorted before they are written. The first of them that is a picture - a component whose source is an image, `[card-image]` or `[image]`, wherever among the others it stands - stands first in the `<article>`, after the picture behind the card; every other child stands behind it in a `<div class="nino-card-content">`, in the order it was written in (`\Nino\Html::shortcodeParts()` cuts the content by the pattern of the rendering, so a picture inside the content of another call is that call's). A card with no padding of its own, `padding="0"` (`nino-p-0`), has its picture at its edges, cut at the corners of the card, and its words in a room of their own; a card without a picture has the div alone, and one with nothing but a picture has no div. A card with nothing between its tags renders nothing; a card whose components all render nothing is an `<article>` with an empty div. The markup of the card, of its content and of its picture is `$html['card']`, `$html['card-content']` and `$html['card-bg']`. The block pads the content only where the card does not pad it: a card with no padding of its own (`nino-p-0`), a fullwidth card and a counter card; in every other card the card's padding is the inset of its content, as it was before the block.

`[image]` is the shortcode `Modules\Images` had, and it behaves as it did in every form; `componentImage()` documents the alt text, the content form that a meta tag or a mail asks for the address with - `[[src]]` is the path of the file with the directory the project is served from in front of it, for an `<img>`, and `[[url]]` is the absolute address of it, `https://` and the address of the website and the path with the directory once, which is what a meta tag or a mail writes (`<meta property="og:image" content="[[url]]">`, not `https://[[/project/website/general/url]][[src]]`, which names the directory twice under a subdirectory); where the website has no address `[[url]]` is the path, as `[[src]]` has it - and the frame that crops, which a focus, a ratio or a class of its own call for and which is cut at the radius of the image kind (see the radius of a surface at the end of this chapter). `[card-image]` is the picture of a card: the `<img>` of `.nino-card-img`, which is as wide as the card (`style="inline"`, the class `nino-card-img--inline`, is the other way round, the picture at its own size, and is no call for a frame), or - for a focus, a ratio or a class of its own - the frame of `[image]`, which then carries `nino-card-img` so that the card's rules work on it. `[card-text]` is `[text]` in the class of the text of a card, `nino-card-descr`. A link in either - in the classes `nino-section-text` and `nino-card-descr`, whatever the format, the style and the colour of the text, so that a text of a template written by hand has it too - is the accent ink of the ground the text stands on and is underlined on hover and on keyboard focus; a button in a text keeps its own rules, and the titles have no such rule: `a` itself is still the colour of what it stands in, with no underline. `[text]` has a `tag` after its `format`, `p` by default, `div` or `span`, and writes its element as that; the format `blocks`, the default, holds paragraphs and lists of its own and is a `div` whatever the tag says - a `p` or a `span` cannot hold a `p` - and so is `[card-text]`, which has no tag. `[button]` has a `width` besides its `size`, `full` for a button as wide as the box it stands in (`nino-btn--fullwidth`); where it has no address at all it is a `<span class="nino-btn">`, the same classes and no link, since an `<a>` without an `href` looks like a button and is none. `[scroll-hint]` is the arrow at the foot of a hero as a link: an `<a class="nino-hero-arrowdown">` to the section whose id `to` names, and with none a `#` marked `data-arrow-next`, which `Nino.ui.js` answers by scrolling to what follows the section the arrow stands in; the chevron is drawn by `Nino.css` in the ink the arrow stands on, and its label, for a screen reader, is a text of its own, `/template/common/navigation/next-section`. One thing the code does not say: the content of a shortcode runs up to the first closing tag, so a bare `[image]` ahead of the content form `[image /logo]...[/image]` in one template reads the text between them as its own content. Write the bare one as `[image /x][/image]`. The markup of every component is a property, `\Nino\Modules\Components::$html`, `$html['img']` among them - replace an entry, or register a component of the same name.

**`[logo]`** is the picture that is read on every ground: two image slots in one set, `<span class="nino-logo-set">`, and the ground it stands on says which of them is seen. `light` is the slot of the picture for a light ground (`/logo`) and `dark` the one for a dark ground (`/logo/dark`), both attributes of the type `image` - the base unit declares the two slots - and `alt` is the text for a picture whose slot has none of its own. `[logo]` draws both, `[logo dark=""]` only the light one, and `[logo light="/brand/mark" dark="/brand/mark-white"]` the slots it names. Each picture is the `<img>` of `$html['img']` with the class `nino-logo-light` or `nino-logo-dark`, the size of its slot and its alt text found as `[image]` finds it; a slot without a file draws no `<img>`, so that a set of one picture shows it on every ground and a set of none is empty - `''`, which keeps the wrapper that hides itself when it is `:empty` (the logo of the navigation) empty and leaves the name of the site in the link of a frame. A `class` goes on the set, the content form is not needed, and the component has no link: the frames wrap it in the `<a class="nino-logo">` of their own. Which picture is seen is `Nino.css`'s, through two properties, `--logo-light` and `--logo-dark`, a display value each (`block` or `none`): `.nino-logo-set .nino-logo-light` is `display: var(--logo-light, block)` and `.nino-logo-dark` is `display: var(--logo-dark, none)`. Every rule that paints a ground sets the pair beside `--color-ground` and `--color-ink` - the sections, rows, columns and cards, the counter families (`--counter-grid-logo-light` ... `--counter-card-logo-dark`), the dimmed ones, the statuses, and the overlays and the modal that put the page's ground back, with the page's pair - from the roles of the ground (`--color-section-dark-logo-light`, `--color-primary-logo-dark`, `--color-logo-light` for the page ...). A design layer solves the roles from the ink of each surface, so the picture of the page flips with the colour scheme and a brand ground with the colour it was given; a frame that paints a ground of its own sets the pair as it sets its roles.

**Stacks.** A stack is written with the type as its first argument and the cell between its tags:

```html
[stack /services sort="title" limit="6" cols="100 50 50" gap="2" autoheight="1" id="services"]
    [image image focus="5"]
    [title title tag="h4"]
    [text summary]
    [button .uri text="More"]
[/stack]
```

The first argument of a component in a cell is its source: a field of the element, `.id` the number of the element in the loop, `.uri` its uri (`/<type>/<name>`) and `.slug` the name alone, the last segment of the uri - `[text .slug]` writes the name. Outside a stack, the three are nothing. A link to a page of the element is written with the token, `[button text="More" href="/blog/[[.slug]]"]`.

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

The cells are the stack's and the card is the content, so the two are told apart in `Nino.css` too: the cell holds the half gap as padding, and the card in it has its padding and its border as in a column but no margin of its own, the half gap being the room between neighbours - a margin class on the card (`nino-mt-2`) is the one thing that gives it one, since the rule that takes the margin away is no heavier than the margin it takes and stands before the margin classes at the end of `Nino.css`. Below 640px, where the classes of the grid begin, the cells are one below the other and a card in one is as wide as the column. The kernel brings nine stacks, and their schemas, with the attributes of each, are in `Components::init()`. A stack that does not say what its cell is has the content between its tags for it. The shortcode `[stack]` is called Grid in the interface, Raster in German:

| Stack | Word (en / de) | `cell` | Attributes of its own |
| --- | --- | --- | --- |
| `[stack]` | Grid / Raster | `components` | none; the grid's `cols`, `gap`, `autoheight` |
| `[repeat]` | Plain repeat / Einfache Wiederholung | `components` | none |
| `[slider]` | Slider / Slider | `components` | `width`, `min` |
| `[filter]` | Filter / Filter | `components` | `by`, `all`; the grid's |
| `[tabs]` | Tabs / Tabs | `components` (`panels`) | with a type, or with `[panel]`s; `title` (a `field`), `text` |
| `[accordion]` | Accordion / Accordion | `components` (`panels`) | with a type, or with `[panel]`s; `title` (a `field`), `text`, `exclusive`, `open` |
| `[list]` | List / Liste | `row` | `style` |
| `[table]` | Table / Tabelle | `columns` (it holds the place `table`) | `style` |
| `[sections]` | Sections / Sektionen | `section` | `alternate` |

`[repeat]` is the loop that repeats the content and nothing else: the cells stand one after the other where the call stands, with no row, no cell and no grid, so that a `[card]` or a section is the element itself. A call with a valid `id` or a `class` is the one exception: it is wrapped in a `<div>` of that `id` and `class`, the single place a page can link to or a rule can address the whole repetition by - either would otherwise be an attribute that does nothing. `[tabs]` and `[accordion]` take a title for each element from the field `title` names, or from a line of free text `text` that has the fields of the element in it as `[col]`'s does, escaped once - a field that holds html (`html`, with or without `blocks`) is its text there, in `[col text]` and `[line text]` too where a token is filled by the line itself: the tags are dropped, the end of a block or a line break is a space and the entities are the characters they stand for -, and the line wins where both are given; an element whose field is empty or none, or whose line comes out empty, is titled by its number in the loop, counted from 1, and no other field is read for it. A line is written in the tag of the call, which the shortcode reads up to its first closing bracket, so the brackets of a field in it are entities: `text="&#91;&#91;title&#93;&#93; (&#91;&#91;year&#93;&#93;)"`. The same entities mean the opposite inside a loop's content: in the `text` of a `[col]` or a `[line]` there, `&#91;&#91;x&#93;&#93;` is a literal pair of brackets, because `renderStack()` has filled the fields into that tag before the shortcode reads it, while the call of a stack stands on the page, where its tag ends at its first `]` and no field has been filled. A stack's `text` is declared with the type `string` and nothing else, since it is handed on as written. A `title` whose name does not follow the pattern of the type `field` (letters, digits, hyphens and underscores, starting with a letter), names no field of the type, or names a field that holds an image or a list is titled by the number; a field that holds html shows its text, the tags dropped. `[tabs]` draws

```html
<div class="nino-tabs" id="faq">
  <nav class="nino-tabs-nav">
    <button type="button" class="nino-tabs-tab nino-is-active" data-tabs-target="faq-1">First</button>
    <button type="button" class="nino-tabs-tab" data-tabs-target="faq-2">Second</button>
  </nav>
  <div class="nino-tabs-panel nino-is-active" id="faq-1">...</div>
  <div class="nino-tabs-panel" id="faq-2" hidden>...</div>
</div>
```

a button and a panel for each element, the cell being the panel, the first of each active and the other panels `hidden`; the panels are `<id>-<number>` of the call's `id`, or `tabs-<n>-<number>` with a running number of the request where the call has none. That is what a page without script shows right; `Nino.ui.js` takes every `.nino-tabs` and sets what a script has to - `role="tablist"` on the nav, `role="tab"` with `aria-selected`, `aria-controls` and a roving `tabindex` on the buttons, `role="tabpanel"` with `aria-labelledby`, `hidden` and `aria-hidden` on the panels, the switch on a click and the arrow keys, `Home` and `End` - so the markup carries none of it, and the script finds the buttons and the panels of a group below the `.nino-tabs` it stands in, which is why a set of tabs does not stand inside the panel of another. `[accordion]` draws a `<details class="nino-accordion">` with a `<summary class="nino-accordion-trigger">` and a `<div class="nino-accordion-panel">` for each element, all in a `<div class="nino-accordion-group">` with the call's `id`, and needs no script: with `exclusive` (on by default) the `<details>` share one `name` - the call's `id`, or `accordion-<n>` with a running number of the request - and the browser closes the others when one opens, and `open="first"` has the first one open at the start. The `class` of the call is the class of the `<details>` of `[accordion]`, and that of the wrapper of `[tabs]`. `[sections]` loops whole sections: what stands between its tags is the row of one section, drawn once per element with the element as the context, and each is a `<section class="nino-section">` of the call's `class` after that, with the `id` of the call and the number of the element in the loop (`news-1`, `news-2`) where the call has one - the sections stand one after the other where the call stood, with no element round them, so that what a page says of its first section and of the sections that follow still holds. It has the loop of every stack and no grid. Its one attribute, `alternate`, mirrors every second section: with `alternate="1"` the second, the fourth and so on carry the class `nino-section--mirrored` after the classes of the call, and from 768px, where its columns stand side by side, `Nino.css` turns the columns of their row around, so that one template changes the side of its picture from one section to the next - the row itself keeps its place, a `nino-grid-row--left` stays at the left and a `nino-pl-0` or `nino-pr-0` keeps its side; the renderer writes the class, so that two loops on a page count each for itself, and a section of a template written by hand may carry it as well.

**Fixed panels.** `[tabs]` and `[accordion]` also take their panels from the template. A call that names no type reads the `[panel]` calls between its tags - `[panel title="..." class="..."]...[/panel]` - and draws a tab and a panel, or a `<details>`, for each in the markup above, fragment for fragment: the same classes, the same ids (`<id>-<number>`, `tabs-<n>-<number>`, `accordion-<n>`), the first tab active and the other panels `hidden`, `exclusive` and `open` as they are in a loop, so that `Nino.ui.js` and `Nino.css` need nothing of their own.

```html
[tabs id="faq" class="wide"]
  [panel title="Prices"][text /template/page-home/faq/prices][/panel]
  [panel title="Terms &amp; conditions" class="narrow"]...[/panel]
[/tabs]
```

draws

```html
<div class="nino-tabs wide" id="faq">
  <nav class="nino-tabs-nav">
    <button type="button" class="nino-tabs-tab nino-is-active" data-tabs-target="faq-1">Prices</button>
    <button type="button" class="nino-tabs-tab" data-tabs-target="faq-2">Terms &amp; conditions</button>
  </nav>
  <div class="nino-tabs-panel nino-is-active" id="faq-1">...</div>
  <div class="nino-tabs-panel narrow" id="faq-2" hidden>...</div>
</div>
```

The `class` of a panel is on its tab panel, or on its `<details>` after the class of the call. A title is read as the title of a loop is: its entities are the characters they stand for, its tags are dropped, and it is written escaped once with its `[` as `&#91;`; with none, or with nothing left of it, the panel is titled by its number counted from 1. The content of a panel is html+ like the content of a card: the stack does not render it but hands it back as it stands, and the components in it run in the rendering that follows, in the context the group stands in - in the cell of an outer stack that is the element of that cell, so `[card-title title]` in a panel reads the field of the element. The content of the group is the cell's, as a `[card]`'s is: the stack of the cell fills the fields of its element into it - `[[title]]`, `[[.slug]]`, in the text, in the title of a panel and in the value of a call such as `[button href="/blog/[[.slug]]"]` - which only a group that names no type gets; a call with a type is a loop and its content is its own. Only the `[panel]`s that stand directly between the tags are read, as a table reads its `[col]`s and nothing else; text beside them, other calls and a `[panel]` inside another call are not drawn, and the loop's attributes, `title` and `text` play no part without a type. A schema that says `panels` with the cell `none` is refused: that cell has no content to read. A group with no panel is nothing and takes no number of the request. `[panel]` draws nothing itself: outside a group, in a card and in the content of a loop it is nothing, as a `[col]` is outside a table, and the registry says so with the place `panels` - a component's `in` and a stack's `holds` may name it. The shortcode reads its content up to the first closing tag of its name, so a group does not stand inside the panel of another, whatever the two are: both end at the first `[/panel]`. A call with a type is the loop, unchanged, and a `[panel]` between its tags is nothing.

A stack of a feature that says `panels => true` reads its panels with `\Nino\Modules\Components::panels( $appData, $args['content'] )`, which answers `[ [ 'title' => ..., 'class' => ..., 'content' => ... ], ... ]` for the `[panel]`s directly in a content: the title as text written safe, the number where it comes out empty, the class written safe, the content as it stands, html+ that the rendering after the stack runs. It tells the fixed form from the loop by an empty `$args['source']`.

`[table]` loops the elements as the rows of a table: `[table /prices sort="title" style="striped"]` and, between its tags, one `[col]` for each column. It has the loop of every stack and no grid, and the attributes `style` (empty, `striped`, `bordered` or `striped-bordered`), `class` and `id`: it draws `<div class="nino-table-wrap"><table class="nino-table nino-table--striped">` - the wrap scrolls where the viewport is too narrow - with one `<tr>` for each element, in the order, the limit and the query of the loop. What stands between its tags besides the `[col]`s is not drawn, a table with no column is nothing, and so is a table with no element. The header row, `<thead><tr><th>`, is written when at least one column has a title, and a column without one has an empty `<th>`. A column is `[col /text/key/of/the/title field="price" align="right"]`: its first argument is the text key of the title, none for no title, and what its cells show is the field `field` names, shown as `[text field]` shows it, or the picture of an image field as `[image field]` draws it, in the class `nino-table__img` - or the line of free text `text="[[price]],- EUR"`, which wins where a column says both. A column that says neither is the cell of what stands between its tags: `[col]<b>[[title]]</b> [button .uri text="More"][/col]` is html+ like the content of a card, the fields of the element filled in escaped as everywhere in a cell and the components in it drawn with the element as their context. The table reads a bare `[col]` and a column with a content side by side, in either order, which the shortcode reading alone does not (it takes the next `[col]...[/col]` for the content of a bare `[col]` before it), and the content of a column is not trimmed of anything but white space at its ends. `align` is `right` or `center` (`nino-table__cell--right`, `nino-table__cell--center`). A `[col]` draws a cell in a `[table]` and nothing anywhere else; the registry says so with the place `table` - a component's `in` and a stack's `holds` may name it, as the card's `holds` names `card`. `[line]` is a line of text, a `p`, `span` or `div` of the class `nino-line` (`tag`), that is filled the same way: `[line text="[[price]],- EUR"]`, the row of a `[list]` or one line among others in a card. In `text`, `[[name]]` is a field of the element, `[[/path]]` a site text, and both are filled escaped; the characters of the text itself are escaped too, so that a `<` of it is text; a `[[name]]` of no field stays as it is, escaped. A call is read up to its first closing bracket and the text is the named argument `text`: a quote, an apostrophe, an ampersand or a bracket of the text's own is written as the entity `&quot;`, `&#39;`, `&amp;`, `&#91;` or `&#93;`, which the call decodes, and a field holding a `]` is written as `&#93;` by the stack. In a loop a `[[name]]` that fills nothing is made safe by the stack, and an image field's token prints nothing; outside a loop nothing does, so a `[line]` there ends at the `]` of a token that no site text fills. A `[[name]]` that is no field of the element stays as written. The kernel writes a field as `[text]` does - a date as its ISO date, a number as it is, a boolean as `1` and nothing - and has no format of its own for them.

A stack **renders each cell itself**. `[elements]` fills its content with `str_replace()` and returns it, to be rendered afterwards - which would run every component in it once the loop is over, with no element. `renderStack()` sets the element as the context (`./nino/components/element`, what `element()` returns), renders the content with `\Nino\Html::renderHtml()`, gives the cell to the function it was handed, `fn( string $inner, array $element, int $index, array $cell ): string`, while the element is still the context - so that a stack that writes something of the element besides its content, the title of a tab, reads it as a component does - and puts the previous context back. `$cell` is `[ 'class' => ..., 'attributes' => ... ]` - the classes of the cell in the grid and `data-autoheight-group`, both empty without a grid - for the cell to put on the element it draws; `\Nino\Modules\Components::cell( $cell, $inner, $class, $attributes )` does that and draws the `<div>` of the cell. A stack that draws a cell of its own, as the slider does its `<li>`, puts them on that. What a stack returns has no shortcode left in it - but for the content of the `[panel]`s of a fixed group, which is handed back as html+ and run by the rendering that follows (see the fixed panels above). The loop is the one of `[elements]` - `\Nino\Modules\Elements::queryLoop()`, which both call - so `sort`, `offset`, `limit`, `query` and `callback` mean the same in both, and `[elements]` stays as it is, for templates written by hand.

**What a cell may hold besides components.** The content of a stack is filled with the element before it is rendered, by the fills `[elements]` makes (`\Nino\Modules\Elements::fills()`): `[[.id]]`, `[[.uri]]` - the uri of the element, `/<type>/<name>` - `[[.slug]]` - the name alone, the last segment of the uri - and `[[<field>]]`, in running text and inside the value of a call - `[button text="More" href="/blog/[[.slug]]"]`. `[[.lang]]` is nothing in a cell: a stack takes no field from another language. A value is escaped as a field is written into `[elements]`, with `[` and `]` as `&#91;` and `&#93;` so that it opens no shortcode and ends no call; in `text=` and `href=` it is escaped once, the first being read as a text is and the second as an address, an entity in either being the character it stands for, while any other attribute is taken as it stands and escaped again by the component. A field of the type without a value in the language is nothing. The fills of the page come first - `[[/_nino/webpage/contact/uri]]` is put in before the shortcodes are read. The fill is a plain replacement over what is written in the cell, but the content of a call in it that is not a component - `[elements]`, another stack, a feature's loop - is that call's own and is not filled, its opening tag is: `[elements /posts query="category=[[slug]]"]` takes the field of the cell's element, and the block between its tags is drawn with the posts. What stands between the tags of a component, a `[card]`, is the cell's and is filled. The cell of a template that a stack includes is not part of the content and is not filled.

Two rules follow. **A stack never stands directly inside another, and a `[card]` never stands directly inside another**: the shortcode reads its content up to the first closing tag, and the context is the element of the one cell. A stack in a template that a stack includes is fine - the context is put back. And a fill in the argument of a component (`[title "[[/some/key]]"]`) is not a source: the source is the key itself, not its value. **The fixed text of a call may carry a fill**: `text="[[/template/page-home/hero/title]]"`. The fills of a page are put in before its shortcodes run, so a call on a page gets the text of the key in `text="..."` as the fills hold it - a plain text with its quotes and brackets as entities, as the panels store it - and `value()` reads a fixed plain text as it reads the text of a key: an entity is the character it stands for, escaped once, and a bracket is written inert after it is decoded. A text that an author types is read the same way, as in an attribute of html: `text="Fish &amp; Chips"` is Fish & Chips. The other formats are sanitized as they stand. A call that comes from elsewhere, a renderer or a tool that calls the callback of the shortcode itself, has no fills put in, so `dispatch()` puts them in the way the pass of a page does: each `[[key]]` of the fixed text is the text of the key in the language of the page as the fills hold it, and nothing where the key has no text, where a page leaves a fill without a key as it is written and the call, which ends at its first `]`, prints the rest. The fills inside that text are left as they are on this path: a fill inside a text fill is not resolved there, where a page resolves them in its passes. The attributes and the content are not read like that: an attribute is taken as it stands, and the content is left to the rendering that follows.

**From a feature.** A manifest that names `components` and `stacks` (see [Developing a feature](features.md#components-and-stacks)) has them registered at every boot, with the renderers `[ '\Nino\Modules\<Name>', 'component<Name>' ]` and `stack<Name>`; `MyApp` calls `addComponent()` in its `init()`. A component is registered one way or the other, not both: `Html::addShortcode()` adds a second callback beside the first, where `addComponent()` replaces it. A shortcode without a schema stays a shortcode, and is no component. [The recipe](recipes/component.md) writes one of each.

The classes the markup uses are in `Nino.css`, and the custom properties a design layer sets to turn it - the font roles, the type scale, the grounds and the rest - are declared and commented at its `:root`. The catalogue's Design feature documents what it compiles from them in the manual of its `feature.php`.

**The row of a section.** What a section holds stands in a `nino-grid-row`: at most `--grid-max-width` wide, in the middle of the section, with the gutter of the grid at its sides. Its width and its place are classes of their own:

- `nino-grid-row--narrow` and `nino-grid-row--wide` are two maximum widths below the grid's.
- `nino-grid-row--full` has no maximum width: the row is as wide as the box it stands in, its gutter kept.
- `nino-grid-row--left` puts a row that is narrower than the page at the left edge of the section instead of in the middle.
- `nino-grid-row--right` puts it at the right edge.
- `nino-grid-row--reverse` puts the columns in the opposite order on every width, the last one first: the stack of a phone reads from the last column to the first, and a row that wraps has its lines changed places as well as its columns mirrored. `nino-grid-row--s-reverse`, `--m-reverse`, `--l-reverse` and `--xl-reverse` do it from 640px, 768px, 1024px and 1280px up.
- `nino-grid-row--s-forward`, `--m-forward`, `--l-forward` and `--xl-forward` turn a row back to the order of the markup from that width up; of two classes that reach a width, the one of the wider tier wins, as the width classes do. `nino-grid-row--reverse nino-grid-row--m-forward` is a row turned round on a phone only.
- A section that says `nino-section--mirrored` already turns its row from 768px: a reversed tier does not mirror it twice (the rule of the mirror has the last word on the direction there), and a `--forward` tier leaves the mirror in place.
- A turned row keeps what its alignment classes say: the columns of a line that is not full stand at the left, `nino-grid-center` centres them, `nino-grid-top` is the top and `nino-grid-bottom` the bottom. A section of tiles is a row like any other and turns the same way.
- `nino-grid-gap-<n>`, `<n>` from `0` to `6`, keeps the columns apart by a step of the space scale times the whitespace between pieces - side by side, and one above the other where they are wrapped or stacked on a phone. The width classes of the columns give the gap back, so that the row never wraps for it, the cards that are columns by `nino-card--grid` included.

Its padding is a utility for each side, `nino-pt-<n>`, `nino-pb-<n>`, `nino-pl-<n>` and `nino-pr-<n>`, a step of the space scale from `0` to `6` as the margins `nino-mt-<n>`, `nino-mb-<n>`, `nino-ml-<n>` and `nino-mr-<n>` have it, and it wins over the padding the row has of its own: the gutter, and the room of a row that paints a ground or holds a picture. The picture behind an element - a row, a column or a card that holds one in a block of its own, `nino-grid-row-bg`, `nino-grid-bg` or `nino-card-bg` - lies over the whole of it. `nino-bg-pl-<n>` and `nino-bg-pr-<n>`, `<n>` from `0` to `3`, on the row, the column or the card take the block in from the left or from the right by a step of the space scale, `0` being the edge it lies at without a class: the element is as wide as it was and its content stands where it stood, only the picture is narrower. The rule asks for the block as a child of the element with the class, so that a picture behind an element inside it keeps its own sides.

A row at the edge, `nino-pl-0` or `nino-pr-0`, takes the side padding of its section with it - from 768px the section has one of its own, and the row would still stand that far from the edge. A padded cover (`nino-cover--padded`) holds its picture inside that padding, and the picture follows the content to the edge: it has no inset at the sides where the row says both `nino-pl-0` and `nino-pr-0`, and none above and below, from 768px, where the section says both `nino-pt-0` and `nino-pb-0`. The Builder keeps all of these as settings of a section's row.

A section at the edge is the row's: `nino-grid-row--full nino-pl-0 nino-pr-0`, no maximum width and no padding at either side, the side padding of the section going with it from 768px. A section with no room above and below says `nino-pt-0 nino-pb-0`. A hand-written cover keeps its row inside `nino-cover-content`, which takes the side padding of the section as its own: where the content is to stand no further in than the gutter of the row, the section itself says `nino-pl-0 nino-pr-0`. The row of a cover in the Builder's form, which stands in the section itself, has what `nino-cover-content` has in a hand-written one: the air above its content, a step of the space scale (a `nino-pt-<n>` on the row says another, and a row that paints a ground - a colour or a picture behind its columns - keeps the room above its content that the ground gives it, the step times the whitespace), and from 768px the side padding of the section, which a box placed in the section does not stand in. A side keeps the edge where the section or the row says `nino-pl-0` or `nino-pr-0`, in a row of `nino-grid-row--full`, and in a padded cover, whose offsets are its padding.

**A section of tiles.** A `<section class="nino-section nino-tiles">` is the second kind of section: graphic tiles across the full width, where a section without the class is editorial content with a reading width. Both are a section with an id, one `nino-grid-row` and columns with the width classes of the grid; the class `nino-tiles` stands after `nino-section`, as `nino-cover` does, and says what the section is. `nino-tiles--<ratio>` names the height of the tiles with the seven words of the `[image]` ratios (`1-1`, `4-3`, `3-2`, `16-9`, `21-9`, `4-1`, `5-1`); without one a tile is as high as its words. The height comes from the width of the row and the tile's share of it - the row of a tiles section is a size container, a width class says its share, a ratio class the height over the width, and the tile's minimum height is the width its width class gives it times the ratio - so a tile holds its ratio where its words fit and grows where they do not, the same in every browser. A tiles section is written like this:

```html
<section id="work" class="nino-section nino-tiles nino-tiles--16-9 nino-section--alt">
	<div class="nino-grid-row nino-grid-gap-1">
		<div class="nino-grid-100 nino-grid-m-50 nino-grid--dim nino-grid--linked nino-stack-end nino-text-left">
			<div class="nino-grid-bg">[image /template/page-home/work/picture alt=""]</div>
			[title /template/page-home/work/title tag="h3" href="/_nino/webpage/work/uri"]
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-grid--primary nino-text-center">
			[title /template/page-home/work/claim tag="h3"]
			[button /_nino/webpage/contact/name href="/_nino/webpage/contact/uri" style="primary"]
		</div>
	</div>
</section>
```

The section has no padding of its own on any side - the tiles carry the room - and `nino-pt-<n>` and the other spacing utilities on it still say what they say; its ground and its borders work as on any section, and the ground shows between the tiles as grout. Its row has no maximum width, no gutter and no place: `nino-grid-row--narrow`, `--wide`, `--full`, `--left` and `--right` say nothing on it, `nino-grid-gap-<n>` is the gap between the tiles (`--tiles-gap` where the row names none, `0px` in the kernel), the padding utilities on the row still win and `nino-section--mirrored` reverses it as it reverses any row. A tile is a direct child of that row, and its width tiers, `nino-hide-<s|m|l>`, grounds, `nino-grid--dim`, picture block, `nino-stack-*`, `nino-text-*`, spacing utilities, `nino-radius` and animation classes mean what they mean on a column. By rule it has a padding of `--tiles-padding` times `--whitespace-scale` (a step wider from 768px, and `nino-p-<n>` says another), is a stack - its words one below the other, placed at the start, the middle or the end by `--tiles-place`, or by `nino-stack-start`, `nino-stack-center` and `nino-stack-end` - and takes the sizes of its words from `--tiles-title-scale`, `--tiles-subtitle-scale` and `--tiles-text-scale`, times the roles. The tile itself says `overflow: visible` and has no `aspect-ratio`: a tile with a ratio is that high at the least and grows past it for words that need more room, and the tiles of one line are as high as the highest of them - a 33 beside a 66 takes the height of the 66, the highest in the line, whichever ratio the section names. The picture block of a tile is cut at `--radius-tile`, so that a zoomed picture stays inside the corners. A section of tiles has no cover, parallax, picture of its own or arrow, and the script reads none of it.

`nino-grid--linked` on a column is what `nino-card--linked` is on a card: the one `a.nino-stretch` in it is stretched over the whole column, every other link, button and field in it stays above, and the focus ring is drawn on the column. `[title]` draws that class on its link, as `[card-title]` does, so a tile whose title has an `href` is a link as a whole; one linked title per linked surface, since a second stretched link would cover the first. The Builder writes `nino-grid--linked` on a tile only; the rules address it wherever it stands. The tokens `--tiles-padding`, `--tiles-gap`, `--tiles-place`, `--tiles-title-scale`, `--tiles-subtitle-scale` and `--tiles-text-scale` are declared at `:root` and are what a design layer turns.

**The radius of a surface.** The corners of a surface are the page's and not a template's. `Nino.css` declares two strengths for the whole page at its `:root`, `--radius-surface`, the standard, and `--radius-surface-alt`, the alternative, and six kinds of surface, each with a token that points at one of the two - `--radius-cover`, `--radius-row`, `--radius-column`, `--radius-tile`, `--radius-card` and `--radius-image` - and a marker, `--radius-<kind>-default`, that says which as a word, `standard` or `alternative`. The marker draws nothing: a tool that wants the default of a kind without resolving a length, the Builder among them, reads it as a word. The kernel's own are the small radius for the standard, the large one for the alternative and every kind standard; the catalogue's Design feature writes the two strengths and, for each kind, its token and its marker. The cover is the picture of a padded cover with the box of a parallax and the scrim of a dimmed cover. The row and the column are the ones that paint a ground or hold a picture, and the radius of a column is its own token: the ground of a column and the picture behind it are cut at it, and a column no longer takes a card's radius. The tile is a column of a section of tiles (below): its ground and its picture are cut at `--radius-tile`, which `nino-radius` and `nino-radius-alt` on the tile set to the standard or the alternative strength. The card is the card, and the picture in it stands at .7 of its corner. The image is the frame of `[image]`, which a focus, a ratio or a class of its own calls for. A button, a field, a badge, an alert, a gallery and the rest keep `--radius`, `--radius-small` and `--radius-large`.

A surface cuts what is in it at its radius. The column and the row that paint a ground, the row and the column that hold a picture, and the card say `overflow: clip` - `clip` and not `hidden`, which would make a box of its own for the margins and the floats inside - so that a picture or a ground of a child that reaches the edge (`nino-p-0`) is cut at the corner and does not stand out of it, square. The column that holds a picture has the radius of its kind to cut at, as the one that paints a ground has. The section is none of them: the arrow of a hero and the elements lifted over its edge stand out of it. A padded cover is a section, and its picture block clips its picture and its scrim at the radius of the cover, but what stands on the picture is not cut by that: the section clips at its own edge, which is square, and is the picture's only where the padding is 0. So the row of a padded cover cuts what it holds at the corners of the picture, and only where its edge is the picture's edge. Its sides are the picture's - it has the offsets of the picture, and a maximum width centres it between auto margins that span the picture - and so is its top with `nino-cover-top` and its bottom with `nino-cover-bottom`. On that side it rounds the two corners at the radius of the cover with `clip-path` (`:where(.nino-cover--padded.nino-cover-top > .nino-grid-row)` and the one for the bottom, rules with no weight of their own), so that a column with a ground that stands at the corner (`nino-pl-0`, `nino-pr-0`, `nino-p-0`) is cut with the picture. The rest it leaves alone: the two corners of the other side stand in the middle of the picture, where a column keeps the radius of its own, and that side is open, so that the arrow of a hero and what is lifted out of the row stand out of it. A row in the middle of the cover has no corner of the picture and is not cut at all. What a child draws outside its own box - the ring of a focused control, a shadow - is cut with it where it reaches the edge of such a surface, the sides and the closed edge of the row of a padded cover included, which is what a clip does: give the child a padding there, or leave the surface square.

One element takes the other strength with a class of its own, `nino-radius` for the standard and `nino-radius-alt` for the alternative, written where it deviates from the default of its kind - on the padded cover, the row, the column, the card and the frame of the image. There is one rule for each kind and each class, and it sets the token of its kind alone: a class on a column does not reach a card in it, and the parts inside a surface, the picture of a card and the scrim of a cover, follow the class as they follow the default - and so does a surface of the same kind inside it, a column in a column, as a custom property is inherited. `[image]` has no `style` any more, and `nino-image--rounded` is gone: a picture is a surface like the others. A template that still carries the class, or the style `rounded` on `[image]`, draws its picture at the radius of the image kind where a focus, a ratio or a class of its own frames it - with the style alone it is the bare `<img>` -, as the class has no rule any more and the component ignores the style. A picture the component draws without a frame - no focus, no ratio and no class - is the bare `<img>`, which has no corners of its own and nothing to carry a class. The picture of a card keeps the corners of its card: `[card-image]` frames it for the same three, and the frame, like the bare `<img>`, is cut at .7 of the card's radius (`.nino-card-img`; at the top of a fullwidth card, its whole radius); `nino-radius` or `nino-radius-alt` written on it set the token of the image kind, which that picture does not read, so they change no corner of it.

**The hover of a picture.** A picture grows under the pointer where its frame says so with a class of its own, `nino-hover` - `[image /pic class="nino-hover"]`, `[card-image /pic ratio="3-2" class="nino-hover"]` - and the frame is where the class stands, never the bare `<img>`, which has no frame to carry it, as it has none for the radius classes: `[image]` and `[card-image]` frame a picture for any class of its own - a ratio or a focus is not needed, and the `style` of a card's picture alone frames nothing - so that the class always has a frame to stand on. Under the pointer, and under the keyboard where the picture is a link - the `<a>` that `href` puts round the frame -, the frame rises and casts its shadow and the picture in it grows and brightens, the `transform` and the `box-shadow` of the frame and the `transform` and the `filter` of the picture each over one duration; the frame crops, so the grown picture stays inside it and inside its corners. Five custom properties of `Nino.css`'s `:root` are the handles - `--image-hover-scale` (`1.04`, a factor), `--image-hover-lift` (`0px`, a length, negative to rise), `--image-hover-brightness` (`1`, a factor), `--image-hover-shadow` (`none`, a `box-shadow`, cast while the effect shows) and `--image-hover-duration` (`.3s`) - so that the kernel's default is a gentle zoom and nothing else: a design layer sets them on `main`, as it sets those of the viewport animation, and a template on the frame or on an element round it, and the kernel reads them and decides nothing more. In a linked card (`linked`) the pointer stands on the overlay of the title's link and never on the frame, so the picture follows the card: its hover, and the focus of the link of its title. For a visitor who asked for less motion the frame keeps its place and the picture its size - `transform: none` on both, for the fine pointer and for none - and the brightness and the shadow, which are no motion, still change; under a coarse pointer the effect is not declared, so that a tap changes nothing, not even a shadow that a design layer gave the frame at rest, since a hover that sticks after a tap is no effect anybody asked for.

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

The markup is the project's own: `page-contact.tpl` carries a hand-written `<form class="nino-form">` that the shared script in `Nino.ui.js` drives. A project with several forms writes each one's markup the same way; the form of `page-contact.tpl` carries `novalidate`, which leaves the check of the required fields and of the address to the script - it says what is wrong in the language of the page, in the message of the form and with `aria-invalid` on the field, where the browser's own bubble speaks the language of the browser - and `autocomplete` on the two fields a browser can fill. The catalogue's Forms feature draws one from its definition. Nino.css draws the fields, their labels, the status line, the checkbox and the radio group of such a form from the custom properties `--forms-*`, whose defaults stand in `:where(:root)` and `:where(*)` in the form block of the file - the size, the space, the corners and the line of a field, where a label stands, the colour of the lines and of the labels, the case of the labels - and a theme moves them on `:root`; the catalogue's Design feature has the form as an object of the page, with four sets for it. A feature that writes a form uses two classes of its own beside the field classes, `.nino-forms-check` for a checkbox beside its words and `.nino-forms-group` for the group of radio buttons under one question, and the same block draws both.

**Refusing a submission** needs no callback name of its own. A module or feature that wants to turn one away registers on the same route callback ahead of the module - `\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', ..., 1 )` - and leaves a status behind; `\Nino\Form::handle()` sees a status that is not 200 and returns without sending or writing anything. `\Nino\Csrf` refuses the same way one step earlier - on the global `/nino/http/response`, at priority 1, which runs before any route's callback - which is why the endpoint has no csrf check of its own:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ): void {

	if( yourGuardRefuses( $appData ) === true )
		$request['/nino/http/response']['statusCode'] = 418;

}, 1 );
```

418 rather than a status of its own for every refusal: the shared `.nino-form` script shows one generic message for anything that is not 200 or 400, so a bot never learns which check it tripped.

The endpoint's own answers follow the same rule. A submission the per-address mail cap refuses is a `429`, neither mailed nor recorded - the visitor sees the generic message and tries again later, and a throttled flood does not become unthrottled disk growth. A submission whose owner mail no transport took is answered `500` - the generic message on the page, so the visitor knows it did not arrive - and, where the project keeps a copy, still recorded, since the inquiry is in the Submissions panel; the Dashboard says why. Only the owner's mail counts: a visitor confirmation that could not be delivered, with the owner's mail out, answers `200`.

### Legal

`Modules\Legal` brings the imprint and the privacy policy as **elements**: two types, `legal` (the imprint) and `privacy` (the privacy policy), one element per section, each with a title and a text per language and the fields `order` and `hidden`. The setup wizard applies its `install/` unit on every run (`\Nino\Install\Setup::ALWAYS_MODULES`) and fills the two types with a newly written starting text in German and English. Nothing of it is replaced afterwards: elements are the editors' content, so a second wizard run adds what is missing and leaves every section as it is. The notice that these texts are a starting point and no legal advice stands in full in the [setup manual](setup.md#modules).

| Part | What it does |
| --- | --- |
| `[legal]`, `[privacy]` | draw the sections of one type in the visitor's language, ordered by `order`, without the ones set to `hidden`; a section that has neither a title nor a text in this language is drawn in the native language (or the first that has one - the other languages of the site in their order, then a language that only has a text file) and carries `lang=""` |
| `\Nino\Modules\Legal::render( $appData, $type )` | what the two shortcodes call; title and text are made safe by the field's own model (`\Nino\Html::fieldValue()`) first |
| `\Nino\Modules\Legal::placeholders( $appData, $html )` | replaces `#/project/company/contact/email#` with what that text key says in the current language |
| `\Nino\Modules\Legal::url( $appData, $page, $locale = '' )` | the address of `imprint` or `privacy`, with the directory prefix, per language, falling back to the native one |
| `\Nino\Modules\Legal::addLocale( $appData, $locale )` | what a language added later gets: the page details, field labels and type hints, the page names, and the section versions of this language the module and the active features ship; called by the Language panel |
| `\Nino\Modules\Legal::contributions( $appData, $featureKey, $locale = '' )` | the sections a feature's unit brings, for the dialog that deactivates or removes it |
| `\Nino\Modules\Legal::check( $appData )`, `notices( $appData )` | what is wrong with the setup, for the Dashboard; never called by a page request |

**Pages and routes.** The module routes the two pages itself, in `init()`: one route per language and page, with the language's path from `/nino/legal/paths` in `config.php` (the unit's default is `/impressum`, `/datenschutz` for `de_DE` and `/imprint`, `/privacy` for `en_US`), `locale` set and `'maintenance' => false`, so that the pages stay reachable while the site is in maintenance. A language without a path of its own gets a derived one, `/fr-fr/impressum`; a path that is invalid or taken by another route is left out and reported by `check()` instead of overwriting anything. Both routes of a page share one Element-URI, `/legal/imprint` and `/legal/privacy`, which the setup wizard and the Routes panel keep for these pages. The page text of each is `/_nino/webpage<uri>/{name,title,description}`.

**Placeholders.** A text names a fact of the website by a placeholder instead of writing it: `#/project/company/contact/email#`. Only keys below `\Nino\Modules\Legal::PREFIXES` (`/project/company/` and `/project/website/general/`) in the four-segment form of the key grammar are replaced, only in text and only after the text was made safe, so a value can be nothing but text: tags and entities are removed, the rest is escaped, and every bracket becomes an entity. A key without a value stays as it is written, so that it shows. The list of prefixes is a constant of the kernel and deliberately no setting.

**The legal navigation.** The wizard creates a third navigation, `legal`, with the two pages as its first entries, and the footer of the base frames outputs it with `[navigation nav="legal" id="legal__nav"][/navigation]`. The wizard creates it only while the project does not have one; a second run leaves the entries as the editors set them. Its entries are Element-URIs: a page that has one route per language is one entry.

**Features add their own sections.** A feature that processes personal data names a file in the `elements` key of its install unit (see [Developing a feature](features.md#the-install-unit)). Activating the feature adds the sections of that file with `\Nino\Elements::seed()` - add-only, never replacing an element or a language version that exists. Deactivating the feature does not delete them: the Dashboard and the dialog say that the text stays. A section deleted for good - in every language - is remembered in `/nino/elements/removed` in `config.php` (by the listener on `/nino/elements/committed`), so that the next update of the feature does not bring it back; creating the element again by hand removes the entry.

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

A module that keeps its own files under `data/` registers `'/nino/admin/restore'` in `init()`; the Backups panel calls it with the staged backup and the live data directory, and the module merges what is its own (`Newsletter::callbackRestore()` in the catalogue's Newsletter feature). Finally, an `install/` directory beside the class file - `manifest.php`, `templates/`, `text/` - makes a kernel or project module selectable in the setup wizard; see the [Library Format](setup.md#library-format). A feature carries the same unit, and `\Nino\Features::activate()` applies it - without overwriting anything the project has - when the feature is switched on in the [Features panel](_admin.md#features); the manifest, the settings and the updates are in [Developing a feature](features.md).

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
'/nino/mode'          => 'production',
'/nino/error/log'     => true,
'/nino/error/display' => false,
```

`/nino/mode` is `'production'` or `'development'` and `'production'` where `config.php` does not name it. It decides nothing here: the two keys below it are what the runtime reads. The workbench's Config panel writes them, with the cache's two, when the mode is switched, and shows the mode in the rail; a project that edits the file by hand sets what it wants of the four.

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

The key matters because every per-IP rule in the site reads this one value: the login cooldown's IP bucket, `\Nino\Mail`'s send cap (`/nino/mail/cap`: calls an hour, 5 by default, 0 for none), a form's rate limit, and the address a session is listed under in the workbench. (A session is not pinned to an address - its token is the key it lives under.) Behind a proxy without the key set, `REMOTE_ADDR` is the proxy for every single visitor, so all of them share one bucket - five contact-form submissions from anyone then hold up every visitor's mail for the rest of the window.

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
- `Referrer-Policy: strict-origin-when-cross-origin`,
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`,
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

Nino uses standalone smoke tests without PHPUnit: each file under `tests/` creates an isolated temporary project and checks one area, and every PHP suite loads `tests/harness.php` for the sandbox, `check()` and the summary. The commands CI runs - every `tests/*-smoke.php`, the JavaScript loop, PHPStan and ESLint - are the one block in [Deployment](deployment.md#tests-before-go-live), and the [agent guide](../AGENTS.md#10-tests-and-validation) says when to run the whole block. A feature's own test, `features/<Name>/tests/<key>-smoke.php`, loads `tests/harness.php` from the checkout three levels up, or from the one `NINO_ROOT` names - which is how the same test runs against another Nino version; the [feature manual](features.md#the-features-own-test) writes one.

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

Most hooks say that something happened. `/nino/mail/send` and `/nino/images/render` are the two that replace a kernel action instead: `\Nino\Mail::TRANSPORT` and `\Nino\Images::RENDER` document what a handler receives and what it sets to take the job over. Both are fired after the checks that keep an endpoint safe - the per-address mail cap, the byte cap, the image type and the pixel cap - so that a feature cannot switch them off by registering.

### `/nino/navigation/lines`

`\Nino\Modules\Navigation::routeLines()` fires it once per call, after the lines of a menu are collected and ordered and before they are returned - for a menu without lines too, then with `[]`. Every `[navigation nav="..."]` asks for its lines that way, so the hook is where a module decides which pages a menu offers to the visitor at hand. `Legal` asks `routeLines()` too, for every language, menu and page it checks in the workbench, so that its check sees the menu a visitor sees.

The argument is `[ 'nav' => 'main', 'lines' => [ '/:Start', '/members:Members' ] ]`: the key of the menu and its lines, `<uri>:<title>`, in menu order. A callback changes `lines` in place, or returns an array that replaces the argument. `routeLines()` answers the strings of `lines`, in their order and numbered from zero; a callback that sets `lines` to anything but an array, removes it or returns a value that is not an array leaves a menu with no lines. The lines an author wrote between the tags of `[navigation]` are not in the argument and are drawn as written; what a callback leaves in `lines` is drawn as text, with a colon or without one.

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/navigation/lines', static function( array &$appData, mixed &$args ): void {
    if( ( $args['nav'] ?? '' ) !== 'main' || \Project\Members::isSignedIn( $appData ) )
        return;

    $args['lines'] = array_values( array_filter( $args['lines'], static fn( mixed $line ): bool => is_string( $line ) === true && str_starts_with( $line, '/members:' ) === false ) );
} );
```

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
