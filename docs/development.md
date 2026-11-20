# Nino — Developer Manual

This manual describes the technical work with Nino — from the entry point through routing and rendering to custom modules, persistent data, and security. Where the code states a list itself — the callbacks the kernel fires, the classes, the modules, the methods of a panel — the manual names the place instead of copying the list. Setting up a project is the [Setup Wizard](setup.md) manual; one kind of change at a time is walked through in the [recipes](recipes/README.md). The way in before all of them is the [developer guide](guide/README.md), written in German: an overview in plain language and tutorials that build one feature from its first file to its test.

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
| Change the look | the tokens `--brand-...` and `--page-...` in `assets/style.css`, or a theme |

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
- `Locales`, `Csrf`, and `Auth` determine language, CSRF state, and current user; `Html::init()` between them registers the two shortcodes the kernel owns itself, `[json]`, which the base templates use in their schema.org block, and `[imagevalues]`, which the head of a page and the mail use for the address of the logo, whatever modules a project has.
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

The runtime textfills are added in two passes, and the split matters. `/nino/dir`, `/nino/public`, `/nino/date/year`, `/nino/favicon/stamp` and `/nino/favicon/svg` answer to `$appData` alone, so they are registered **before** `Http::response()`: a response callback that renders a template is a real caller — `Modules\Form` and `Modules\Newsletter` build their HTML mails in exactly that window, and a fill registered after it would reach them as the literal `[[/nino/public]]`.

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

The method reads the current file state again, only takes over the specified keys, and then writes atomically. It returns `false` when `config.php` could not be locked or written; under Nino's own error handler that case already ends the request with a 500, so the return value matters where a handler continues, in the tests for example. Auth sessions are additionally merged via a three-way comparison so that parallel logins or logouts do not unnoticedly overwrite each other's state: a session that another request ended stays ended, and a login that began before the password was changed or the account switched off opens no session.

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

The text is read once into a tree (`\Nino\Markup::parse()`, the notation of [HTML+ - the notation](#html---the-notation): text, textfills and calls, with their source and attributes) and drawn from it (`\Nino\Components::render()`), and then the callbacks under `/nino/html/render` are called, once for the page:

1. Text goes into the page as it is.
2. A textfill is looked up in the fills of the language and goes into the page as it is; a fill inside it is resolved, at most ten passes.
3. A call is drawn by the component or shortcode that has its name.
4. The callbacks under `/nino/html/render` are given the finished page.

Nothing is read twice: what a shortcode answers is finished, and a value is never read as a call. [Drawing a page](#drawing-a-page) has the two rules and the functions a shortcode draws html+ of its own with.

### HTML+ - the notation

HTML+ is HTML with three marks in it: a textfill `[[key]]`, a call `[name ...]` and the end of a call `[/name]`. `\Nino\Markup::parse()` reads a document into a tree in one pass; it knows no `$appData` and no file, keeps no state between two calls, and never throws - what the notation cannot say is text, or a note. `\Nino\Markup::write()` writes a tree back as a document. The rules below are what the reader does; `tests/fixtures/markup/cases/` holds one case for each, and most of the examples here are taken from there.

A node of the tree is one of

```php
[ 't' => 'text' ]
[ 'f' => '/project/company/general/name' ]
[ 'n' => 'button', 's' => '/template/page-home/hero/button', 'a' => [ 'size' => 's', 'wide' => true ], 'c' => null, 'o' => 0 ]
```

`f` is a textfill without its brackets, `s` the source of a call (`''` for none), `a` its attributes, `c` its children - `null` for a leaf, a list (`[]` if there is nothing in it) for a container - and `o` the byte offset of its opening mark. Text that follows text is one node, and a node of empty text does not exist.

**Text.** Whatever is no mark is text and goes into the page as it is. That holds for a comment, a `<script>` and a `<style>` too: the marks inside them are read like any other, so `.c[href]{}` in a stylesheet and `list[i]` in a script hold the calls `[href]` and `[i]`: write `\[` where a bracket is meant as a bracket. A document with no `[` is one text node.

**Textfill.** `[[`, a key and `]]`: `[[/project/company/general/name]]`, `[[.id]]`, `[[title]]`, `[[imageAlt]]`, `[[content:header-logo]]`. The characters of a key are the letters, the digits and `_ . / : -`; anything else between double brackets (`[[a|b]]`, `[[ x ]]`, `[[]]`) is text. One textfill may stand inside the key, as it is written: `[[/_nino/webpage[[/nino/http/response/uri]]/title]]` is one node. Its value is looked up when the node is drawn (see [Textfills](#textfills)); the parser only says where it stands.

**Call.** `[name word word ...]`. The name is lower case letters, digits and hyphens, starting with a letter: `[button]`, `[section-col]`; `[1a]`, `[A]`, `[a_b]` and `[a.b]` are text. Words are separated by blanks - a space or a tab - and a word is one of three things:

- **The source**, the first word that is kept, if it begins with `~`, `/` or `.`: `~title`, `/template/page-home/hero/title`, `/templates/html-header`, `.field`. Behind the first character stand letters, digits and `_ . / : ~ -` and textfills, which a mark takes whole - `[template /templates/page-x.[[/nino/http/response/locale]]]` is one call, the `]]` of the fill is not the end of the mark. A source has at least two characters, so the `/` of ` /]` is none, and the parser counts no segments: what a source means is the business of whoever draws the call.
- **An attribute**, `name="value"`.
- **A switch**, an attribute name by itself: `[button wide]` has the attribute `wide`, and its value is `true`. What `wide="no"` or `wide="0"` mean is for the component that reads them.

A word that looks like a source and stands behind a word that is kept is a switch of that name, in its place: `[a x="1" ~t .f /g y]` has the attribute `x` and the switches `~t`, `.f`, `/g` and `y`. So the source stands first: in `[title text="x" /template/page-home/hero/title]` there is none.

**A word of a call** - the name of an attribute, or a switch - is `[A-Za-z][A-Za-z0-9_-]*`, the form of the name of a field: `bC="x"`, `d9`, `e-f="y"` and `g_h` are words, `9x` and `2026-12-24` are none, and a mark that holds a word that is none is text - `[example 2026-12-24 time="19:30"]` is text on the page, whatever `example` is. A bare word - the field of an element as the first word of a call in a cell, `[text summary]`, `[text long_name]` - is a switch, and a shortcode is handed it as the entry `0` of its arguments, as it is handed a source. The register takes fewer names for the attributes of a schema, see [The Register of Components](#the-register-of-components).

**Value.** Between double quotes, in one line. It ends at the next `"` that no `\` masks: `\"` is `"` and `\\` is `\`, any other `\` stays what it is, and so do `[`, `]`, `'`, a tab and a textfill - `[a v="x\"y\\z\qw [k] ]] '1' [[/x/y]]"` is one call and one value. An empty value is a value (`alt=""`). A value that is never closed makes the mark text, and the mark behind it is read: `[a v="x] [b]` is text and then the call `[b]`.

A value with no quotes - `size=50`, `size='50'`, `w=` - is dropped: the call stays and the attribute is not there, with the note `attribute`. An attribute named twice counts as the last one, in the place of the first, with the same note, and a value with no quotes never replaces one with quotes.

**Leaf and container.** `[name /]` is a leaf: a `/` behind a blank and nothing but blanks after it. The `/` has to follow a blank, so `[a/]` and `[a x/]` are text, and a `/` that ends a word is part of it (`[a /x/]x[/a]` has the source `/x/`). A container has an end, `[/name]` - the name and nothing else, a blank before the `]` allowed; `[/a x]` is text. Which names are containers, which leaves is told by whoever registered them (`true` or `false`, see [Drawing a page](#drawing-a-page)); where nobody said, a name is a container exactly if its end stands somewhere in the same document - an end that a `\` masks does not count - and a leaf otherwise. `[name ... /]` is a leaf whatever the name is, which is how a document that writes both forms of a name writes the leaf: `[image ... /]` beside `[image]...[/image]`.

An end closes the next open call of its name, and the open calls between them are closed with it, each with the note `unclosed`; calls of one name nest, so `[box]a[box]b[/box]c[/box]` is two boxes, one in the other. An end that has no open call of its name - or whose name is a leaf - is skipped with the note `stray-end`, and what is still open when the document ends is closed, `unclosed`. At most 16 containers are open at once (`\Nino\Markup::MAX_DEPTH`): the 17th stays a leaf, what it would have held stands behind it, its end is skipped, and the note is `depth`.

**`\[`.** A `\` in front of a mark makes the mark text, whole, and the `\` is gone: `\[name]`, `\[[/a/b]]`, `\[/end]` and `\[button x="[y]"]` are the text `[name]`, `[[/a/b]]`, `[/end]` and `[button x="[y]"]`. In front of anything else a `\` stays, and there is no `\` for a `\`: `a\\[b]` is `a\` and the text `[b]`.

**A mark stands in one line.** A line break is no blank: a mark that has one, between its words or in a value, is text whatever stands in it, and so is what it would have held. A `\` in front of a line break masks nothing.

**What stays text.** A mark with a name that is none, a word that is none, a value that is never closed, a line break inside it, an end with a word in it, and brackets that make no mark: `1 [ 2 ] [] [x [[ ]] [/ ] [/1] [a=b]`. A call with more than 64 words (`\Nino\Markup::MAX_WORDS`; the source, the attributes and the switches together) is text too, and has no note.

**Notes.** `parse()` answers the tree and, beside it, what it noticed, in the order of the offsets: `[ 'code' => 'unclosed', 'name' => 'button', 'offset' => 123 ]`. The codes are `unclosed`, `stray-end`, `depth` and `attribute` for what is said above, and `pattern` and `limit` for a document that was not read. A document is drawn whole whatever the notes say; they are for a tool that tells an author what is wrong. The log hears only of the last two, once in a request.

**The four limits.** A read costs time in the size of the document and memory in the number of its marks, so a document that would cost too much is not read at all:

| Limit | Constant | A document that goes over it |
| --- | --- | --- |
| 50 000 `[` | `MAX_MARKS` | is one text node, with the note `limit` at the offset `0` |
| 200 000 words in its calls together | `MAX_WORDS_READ` | the same |
| 64 words in one call | `MAX_WORDS` | that mark is text, no note |
| 1000 notes | `MAX_NOTES` | the first 1000 are kept; the last note is `limit`, at the offset of the first that was not kept |

A document that PCRE gives up on is one text node with the note `pattern`. Where a document is not read, `\Nino\Html` says so once in a request - a source of so many bytes "is not read for its calls" - as an `E_USER_WARNING`, and the page shows the document as the text it is.

`\Nino\Markup::write( $nodes, $containers )` is the other way: a tree as a document in the one form the notation has - `[name source attr="value" switch]content[/name]`, the source first, a `\` and a `"` in a value masked, no blank that no text holds, `o` not written - and a `\` in front of each place in a text that would be read as a mark. It leaves out what the notation cannot say (a name that is none, an attribute that is none, a value with a line break) and throws nothing. For a tree that `parse()` gave, reading what is written gives the tree again, save `o`, except for two forms that the class docblock names. A leaf is written `[name ... /]` where the same name is a container anywhere in the tree or in `$containers`.

`\Nino\Markup::layout( $nodes, $depth )` sets out a tree that is new - a block a tool made - as a person writes a template: each call on a line of its own, a tab for each level, the end of a container at the level of its start; `$depth` is how many tabs the first level stands behind. The blank texts between the calls of a level are replaced by a line break and the tabs, and the first call of `$nodes` has no line break before it, as what stands before it in the document is the document's. A level that holds anything but calls and blank text - words, a textfill, html - stays as it is, with what it holds. `write()` of the result is the template as written by hand, and `parse()` of that is the tree again but for the blank texts. A tree that was read from a file is not given to it: what is not given stays byte for byte as it was.

```php
$block = \Nino\Markup::layout( \Nino\Markup::parse( '[section-wrap][section-row][section-col][/section-col][/section-row][/section-wrap]', $containers, $notes ), 0 );
// write( $block ) is "[section-wrap]\n\t[section-row]\n\t\t[section-col][/section-col]\n\t[/section-row]\n[/section-wrap]"
```

### Textfills

Textfills are placeholders with double square brackets:

```html
<title>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</title>
<p>[[/template/page-contact/hero/subtitle]]</p>
```

A key that nobody has a text for - an empty text is none - stays written as `[[key]]`, and what a key holds goes into the page as it is and is never read for calls. A key with no slash in front is a token of the loop the textfill stands in or a field of its element (see [Elements in Templates](#elements-in-templates)), and stays written where there is none.

Nino combines:

1. global values from `/text/global.php`,
2. values of the current locale from `/text/<locale>.php` - and, for every key the language has no text for, the text of another language (below),
3. runtime values added with `Html::addFills()`.

**A language that has no text.** A key whose value in the current language is missing or empty - empty is `trim( $value ) === ''`, so a text of whitespace is empty too - takes the value of the first language of a chain that has a non-empty one: the native locale (where the site serves it), then the other available locales in the order `config.php` lists them, then the other languages that have a text file (`\Nino\Locales::getTextLocales()`: a file on disk of a language that is switched off, so that a site whose only language has no texts of a feature yet still shows the ones another file has). The language's own non-empty text always wins, `global.php` is as it was, the runtime values win over everything - a fill added with `Html::addFills()` for the language or for `'*'` is never replaced by another language's text - and `'*'` has no chain. One key never falls back, `/project/website/html/lang`: a French page with the German tag is worse than none.

`\Nino\Html::fillOrigin( $appData, $key )` answers which language a key's text was taken from - `'de_DE'` - and `null` where it is the current language's own, where a runtime value stands for it and where no language has one. It is what a block that shows a text reads to say in which language it is: the components write a `lang` attribute (below). The file layer - global, the chain, the language's own file - is made once per request and language and kept in `./nino/html/filecache`; a write under `/text/` through `\Nino\Filesystem` drops it, and with it the list `getTextLocales()` keeps in `./nino/locales/text`, so a text saved in a request is the next fill of that request. A write that goes to disk behind the filesystem - the restore of a backup - drops both itself.

`\Nino\Html::getFills( $appData, $locale )` answers the whole table of a language - the file layer, the fills added at runtime for the language, and last those added for `*` - and the table is made once for a request and a language and kept in `./nino/html/filecache`, beside the file layer: a loop that reads a key of the site in every round asks for the table in every round, and building it takes as long as it is long (about 20 µs for 300 fills, 75 µs for 5 000 and 360 µs for 20 000, in every round). `addFills()` drops the table of the language it adds to, or of every language where it adds to `*`; `removeFill()` does the same; a write under `/text/` through `\Nino\Filesystem` and the restore of a backup drop the whole cache. The current language is only the key of the table that is asked for, so a switch of it needs nothing dropped. A fill that is put into `$appData['./nino/html/fills']` by hand, behind `addFills()`, is not seen by a table that was kept: use `addFills()` and `removeFill()`.

`\Nino\Locales` has three helpers for this: `getTextLocales( $appData )`, every language that has a file `/text/<locale>.php` - the available ones first, in `config.php`'s order, then the others by their code; `getVisitorLocale( $appData )`, the language the site shows the visitor of this browser (the one they picked where it is available, else the project's default), which in a workbench request is not `getCurrentLocale()` - that one is the interface language there; and `languageTag( $locale )`, the BCP 47 tag of a locale id, `de_DE` is `de-DE`, the value of a `lang` attribute.

**The favicon set.** Two runtime fills read the set the Favicon tab of the Images panel writes (see [The Favicon Set](_admin.md#the-favicon-set-a-tab-of-images)). `[[/nino/favicon/stamp]]` is the newest modification time, in seconds, of `favicon.ico`, `apple-touch-icon.png`, `site.webmanifest` and `favicon.svg` below `public/favicon/` - `0` for a site without them - and is meant to be appended to an icon's address as a query (`[[/nino/public]]/favicon/favicon.ico?v=[[/nino/favicon/stamp]]`), so that a browser fetches the icon again once the set was written. `[[/nino/favicon/svg]]` is the `<link rel="icon" type="image/svg+xml">` of `favicon.svg`, its address stamped, while that file exists and the empty string while it does not, so that a template writes it right behind another link (`...sizes="any">[[/nino/favicon/svg]]`) and a site without a vector icon draws no link and no empty line. The fragment, which starts with its own line break, is the property `\Nino\Images::$faviconSvgHtml`, a project replaces it as it replaces `\Nino\Modules\Navigation::$html`. The base unit's `html-header.tpl` uses both; a template of a project that was installed earlier is its own and adds them by hand. The two are answered by `\Nino\Images::faviconStamp()` and `faviconSvgLink()`; `\Nino\Images::square()` (with `transparent()`, the empty canvas it and the tab draw on) is the decoder the tab makes its pictures with - the picture upright and set whole on a transparent square - and the kernel's only public way to a picture that is neither cut nor stored.

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

The first segment is the namespace: `template` for the words of a template (`/template/page-team/item-1/title`, or `/template/common/form/submit` for words several templates read), `project` for the project's own facts, settings and content (`/project/company/contact/email`), `feature` for the function of a catalogue feature (`/feature/lightbox/controls/close`) and `module` for that of a kernel module (`/module/form/info/required`). What each holds and which category it takes is the table in [AGENTS.md](../AGENTS.md#text-keys); `\Nino\Text::isGrammarKey()` is the rule.

Who a word belongs to is decided in this order, and the first yes wins:

1. A fact, a setting or content of the project that belongs to no template - the company, the website, the mailbox, the look of the mails, the texts of the project's own modules: `/project/<category>/...`.
2. A word several templates read and no function ties down - field labels, button words, the label in front of a phone number: `/template/common/<part>/<name>`. That includes a word that code puts into any template that has fitting markup, such as the contact form's field labels, which `\Nino\Form::DEFAULT_FORM` brings into every template showing the form.
3. A word that belongs to a function of a module or a feature - its code outputs it, sets it at runtime, selects it from a fixed set, or the function ships it for markup any project template writes: `/module/<directory>/...` or `/feature/<key>/...`. A project template reading it as well, or a project replacing the template, changes nothing about that.
4. Otherwise the word belongs to the one template that reads it, in the category of its file name: `/template/<file name without .tpl>/...`. That holds for the templates a unit ships as well.

The rule applies when a key is created. Nothing moves a key later: if a second template reads a word of another category, the word stays where it is. The scan in the Text Keys tab names such a case; whoever wants the word shared creates it under `common` and changes the places that read it.

The part is the block a word stands in: `intro`, `hero`, `item-<n>`, `form`, `navigation`, `action`, `summary`, `outro`, `label` - or a word naming the block (`welcome`, `pager`, `banner`). The name is the thing itself, preferably `title`, `text`, `button` or `label`. A list with one word per entry puts the identifier in the name (`/feature/modeswitch/mode/dark`), one with several words per entry puts it in the part (`item-1`, `category-necessary`).

A label in front of a fact and the fact are two things. `/template/common/label/phone` is the word "Phone"; the number is `/project/company/contact/phone`, which every template reads from there and from nowhere else.

The category of a template is its file name without `.tpl`: `page-home.tpl` carries `/template/page-home/...`, `html-footer.tpl` carries `/template/html-footer/...`, nothing is cut off or derived. A name that is not a word of a key - a dot, an upper-case letter, an underscore, a slash - gives the template no category: it carries no keys of its own, and may read every other one (`page-home.de_DE.tpl`, `.specimen.tpl`). Only files directly below `templates/` have one. `\Nino\Modules\Template::category()` answers it for a file name or for a template as a shortcode or a route body names it (`/templates/page-home`), and is the one place the rule is written.

A key may be put together at runtime in exactly these shapes, and no other: `[[/_nino/webpage[[/nino/http/response/uri]]/<name>]]` and `[[/_nino/locale/[[locale]]/name]]` in a template, and a four-segment key in which one placeholder is a whole name or the identifier of a list's part (`/feature/posts/navigation/` + `prev`, `/feature/consent/category-` + `necessary` + `/name`). Nothing scans the words that can come out of it, so whoever composes a key checks the rendered words in their own smoke test.

Three things are no text key. The kernel's runtime fills live under `/nino/` and are never in a text file (`[[/nino/dir]]`, `[[/nino/public]]`, `[[/nino/date/year]]`, `[[/nino/favicon/stamp]]`, `[[/nino/http/response/uri]]`, ...; `\Nino\Html::runtimeFillKeys()` names them). A local placeholder has no leading slash (`[[name]]` in an element loop or a mail template, `[[.rel]]`). Configuration such as `/nino/jstext/keys` or `/project/catalog` in `config.php` is another store, even where it looks the same.

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

What the handler answers is finished: nobody reads it again, so a `[` in it is a `[`. A shortcode that has html+ of its own to draw - a template it includes, the content between its tags - draws it with `\Nino\Html::renderSource( $appData, $content, \Nino\Html::context( $appData ) )`; what the handler is handed, what the context says and the rest of the drawing are in [Drawing a page](#drawing-a-page). A name nobody registered draws nothing, and nor does what stands between its tags; the log says so once.

The fourth argument of `addShortcode()` says whether the name has an end: `true` for a container, which needs its `[/name]`, `false` for a leaf, which never has one, and `null` - the default - for a name that is both, a container exactly in the documents where its end stands (`[image]` is a leaf in a page and a container in the head of it). A document that writes both forms of a name writes the leaf `[name ... /]`; `Feature::shortcode()` takes the argument too. A value of an attribute stands between double quotes, and a `"` or a `\` in it is written `\"` and `\\`; a `[` that is no call is written `\[`.

A name is a component or a shortcode. Where the register has a component of the name, the register draws it and the shortcode is never asked: a shortcode that is registered under such a name is a warning in the log, which names the name, the owner of the component and the class of the shortcode. The other order says the same - a component that is registered under the name of a shortcode (`\Nino\Components::addComponent()`, or the folders of a feature) is one warning with both registrants, once for the name in a request - and the component is what is drawn from then on. The kernel itself has no name that is both.

What a template calls can be read without running it: `\Nino\Html::shortcodeCalls( $appData, $html )` answers the calls of the registered shortcodes in a piece of html+, in the order they stand - `[ 'name' => 'example', 'args' => [ 'first', 'limit' => '3', 'content' => 'Content' ] ]` for `[example first limit="3"]Content[/example]` - found by reading it the way a page is read, so that a scan sees what the page will run. The calls between the tags of a call follow the one that holds them, and a name nobody registered is no call. `shortcodeParts()` cuts a piece of html+ into its top-level calls and the text between them; the text of a part is `\Nino\Markup::write()` of its nodes, which is the source where that is in the form the notation writes. A third argument, a list of names (`[]` by default), names calls whose content is not looked into: the call itself is found and what stands between its tags is not, which is how `Components::sources()` passes the stacks of the cell `none`.

A shortcode whose output depends on the clock tells the page cache how long the page it is on stays true, with `\Nino\Modules\Cache::limit()` (see [the page cache and the clock](#the-page-cache-and-the-clock)).

A shortcode that says what it takes - a source for its value, attributes with a type and a default, the markup it is drawn as - is a **component**, so that a tool can offer it and a template can still be written by hand. It is registered in the register of the kernel and not with `addShortcode()`: see [The Register of Components](#the-register-of-components). A name that is a component is drawn by the register and a shortcode of the same name is never asked; `addShortcode()` says so in the log where the component is there first.

### Drawing a page

A page is read once and drawn from the tree, and two rules hold wherever something is drawn:

1. **What a shortcode answers is finished.** Nobody reads it for calls or fills: a `[` in it is a `[`, and a `[csrf]` in it is the text `[csrf]`.
2. **A value is never read as a call.** The text of a fill, the text of a key, the value of a field of an element and the value of an attribute go into the page as they are - or, where a component writes them, escaped - and none is looked at for calls afterwards. A fill's text may hold markup, because the fills are the site's own words and the project's to write html into, and a fill inside a fill is resolved, at most ten passes; that is all it is.

So a `[` in a value is no danger to the page, and the components keep writing every `[` of a value they resolve as `&#91;` all the same. A shortcode that fills values into a template and then draws that template reads what it put in, so it writes every `[` of such a value as `&#91;`, which is what keeps it text (`\Nino\Feature::classes()` does; `\Nino\Components::escape()` escapes for html and leaves a `[` as it is, since what a renderer of the register returns is finished and nobody reads it).

**`renderSource()`.** A shortcode that has html+ of its own to draw - the file a template includes, the content of a loop, the lines of a menu - draws it itself and answers the result:

```php
public static function doShortcode( array &$appData, array $args ): string {

	// The template of the shortcode is html+, read once and drawn where the call stands
	$source = \Nino\Feature::template( $appData, self::TEMPLATES, 'banner' );

	return \Nino\Html::renderSource( $appData, $source, \Nino\Html::context( $appData ) );
}
```

With `features/Meetups/templates/banner.tpl` holding `<div class="banner">[[/project/company/general/name]] [csrf]</div>`, `[banner]` answers `<div class="banner">ACME <input type="hidden" name="_csrf" value="..."></div>`: the fill and the `[csrf]` of the template are drawn, because the shortcode drew the template, and a `[csrf]` that the answer would carry from a value is not.

`\Nino\Html::renderSource( $appData, $source, $context = [], $keep = true )` reads `$source` into a tree - once for a request and a text, kept under its sha1; at most 256 trees (`\Nino\Html::MAX_TREES`) - and draws it with `$context`. `$keep = false` is for a text that is made for one drawing and read by nobody twice, the cell of one element of a loop: its tree is not kept, so that a loop of a thousand elements does not keep a thousand trees. An empty source is `''`. Sources that stand inside one another - a template that includes the next - may be 20 deep, the page itself being the first; the next draws nothing and says so once in a request (`E_USER_WARNING`), which is what stops a template that includes itself. `renderHtml()` is `renderSource()` and then the callbacks under `/nino/html/render`, once for the page, whatever it holds.

**`context()`.** `\Nino\Html::context( $appData )` is the place the call that is being drawn stands in, and what a shortcode hands to `renderSource()` so that what it draws is drawn in the same place. Outside a call it is the default. It is an array of the keys `\Nino\Components::render()` takes - the six below, and `trace` and `path` for [a preview](#a-draft-for-a-preview):

| Key | Holds |
| --- | --- |
| `template` | the name of the template whose words `~name` reads - `/template/<template>/<scope>/<name>`. `[template]` sets it to the category of the file it draws (`page-home` for `/templates/page-home`, `''` for a file that has none, see `\Nino\Modules\Template::category()`); a page that stands in no template has `''`, and `~name` then draws nothing and says so once |
| `scope` | the value of the nearest attribute of the type `id` above the call (`id="hero"`), `''` where there is none |
| `place` | `page` at the top of a document, else the name of the component the call stands in |
| `element` | the element of a loop, `[ 'uri' => ..., 'id' => ..., 'element' => ..., 'model' => ... ]` - what a `.field` source reads - or `null`. `[element]` and each round of `[elements]` set it - the type and the number of the round, or the uri of the element and `0` - beside the fields as `fills`, so that `[section-title .title]` reads the title of the element; `[elementvalues]` sets none |
| `fills` | `[ '[[token]]' => text ]` for the tokens of the loops the call stands in, the innermost winning: a textfill with no slash reads them first, and the call of a shortcode has them put into its arguments |
| `depth` | how deep the nodes stand; a tree deeper than `\Nino\Markup::MAX_DEPTH` draws nothing |

**`tree()` and `putFills()`.** `\Nino\Html::tree( $appData, $source, $keep = true )` answers the nodes of a source as `renderSource()` reads them - with the same names, kept in the same place - for a shortcode that has to look at them before it draws, and draws the nodes with `\Nino\Components::render( $appData, $nodes, \Nino\Html::context( $appData ) )`. The content a call was just handed is answered from the nodes it was made of, so a callback that asks for the tree of its `content` does not read it again. `\Nino\Html::putFills( $appData, $text, $context )` puts the textfills of a text in the way the arguments of a call have them put in: `[[/key]]` is the text of the key in the language of the page, resolved, `[[token]]` is a token of `$context['fills']`, one fill inside the key is put in first, and a key nobody has a text for stays written. What is put in is read by nobody.

**A picture, and a menu, as data.** A shortcode or a component that draws a picture or a menu in a markup of its own asks for the values and writes them, and needs no module for it:

- `\Nino\Images::picture( array &$appData, string $reference, ?array $element ): ?array` finds a picture. `$reference` is the uri of an image slot or, in a loop, the name of an image field of the element (`$element` is the `element` of the context, `null` outside a loop; a name that is a field of the element is the field, anything else is a slot). It answers `[ 'url', 'width', 'height', 'alt' ]` - the file as `getUrl()` writes it, the size of the slot or of the box of the field (the size of the file for a field that fits), the alt text stored with the picture for the language of the page, `''` where there is none - or `null` where the slot has no file, the field holds none or the name is nothing. A reference that is one of `\Nino\Images::PLACEHOLDERS` (`/nino/placeholder/landscape`, `/nino/placeholder/mark`) is a picture of the kernel and no slot: the `url` is a `data:` address of a vector picture, with its size and no alt text, whatever slots the project has. The values are raw and the caller escapes what it writes. A stored file name that is empty, absolute or climbs out of the directory with `..` names no picture, and a slot is looked up by its name, never as a path.
- `\Nino\Images::cleanSvg( string $svg ): ?string` rebuilds a vector picture from its shapes and answers the new svg, or `null` for bytes that are no plain vector picture. The svg is parsed and only what the lists of the class name is copied: shapes (`path`, `circle`, `ellipse`, `rect`, `line`, `polyline`, `polygon`), groups, definitions, gradients with their stops and clip paths, each with the plain attributes of a drawing, a value only where it holds nothing but the characters of a number, a colour, a path or a transform, and `url()` only to an id of the same picture. A script, a style, a foreign object, an embedded picture, a link, an event attribute and every prefixed attribute are never part of the copy. A document type, an entity declaration, a root that is no svg of the svg namespace, bytes that are no UTF-8, more than `\Nino\Images::MAX_SVG_BYTES` (256 KiB), more than 2000 elements, nesting deeper than 24 and a picture without a shape are refused. The text of a `<text>` is no shape and falls away. What is stored is always the rebuilt picture, never the upload: the Favicon tab of the Images panel is its first caller.
- `[imagevalues /logo]...[/imagevalues]` draws what stands between its tags with the values of that picture as the tokens of the context - `[[url]]` (the absolute address of the file, the one a meta tag or a mail asks for, with the directory the project is served from once), `[[src]]` (the path, with the directory in front of it), `[[width]]`, `[[height]]` and `[[alt]]` - and nothing where there is no picture, so that no empty tag is left behind. Each value is escaped for text and for an attribute, and none is read as a call, a fill or the end of an attribute; `[imagevalues]` inside `[imagevalues]` is drawn with the tokens of the inner picture winning. It is a container, and it is what `html-header.tpl` and `mail-header.tpl` write for the address of the logo.
- `\Nino\Modules\Navigation::entries( array &$appData, string $nav ): array` is the menu `[navigation nav="..."]` draws, as a list of `[ 'href', 'title', 'current' ]` in menu order: the address as a link takes it, with the directory the project is served from in front of a path, the title as the editor typed it, and whether it is the page that is shown. A line a callback of `/nino/navigation/lines` left with no address is an entry with `href` `''`. The values are raw, not escaped - the caller escapes them - and `[navigation]` draws its generated entries from this list. The lines written between the tags are the template's own and are no part of it.
- `\Nino\Modules\Localepicker::entries( array &$appData ): array` is the same for the languages `[localepicker]` offers, in the order the site lists them: `[ 'locale' => 'de_DE', 'lang' => 'de-DE', 'name' => 'Deutsch', 'href' => '?/_nino/localepicker/current=de_DE', 'current' => false ]`. The name is the text of `/_nino/locale/<locale>/name`, decoded and not escaped (the code where the site has none); the switch itself is the module's, which answers the query of `href`.

**What a callback is handed.** The callbacks under `/nino/html/shortcode/<name>` are `( array &$appData, array $args ): string`; a second registration under a name is handed the answer of the first as its arguments. They are called with the arguments of the call made ready:

- the source is `$args[0]` and each switch is the next entry, in the order they stand, so `[example first limit="3"]` has `$args[0]` as `first`; a call with no source starts its switches at `0`;
- `name="value"` is `$args['name']`; the last of a name counts;
- the fills in the source and in a value are put in first, as the page had them before the calls: `[[/key]]` as the text of the key, `[[token]]` as the token of the loop the call stands in, a key nobody has a text for as it was written. What is put in is a part of the argument and nobody reads it;
- `$args['content']` is, for a container with something in it, `\Nino\Markup::write()` of its children: the text they were written in where that was in the one form the notation has (double quotes, single spaces, the source first), and that form where it was not. Its textfills are still there as `[[key]]`, for whoever draws the content; a container with nothing in it has no `content`. An **attribute** named `content` is a value like any other and is not the content - it is not passed at all, and `[html content="..."]` draws nothing;
- what comes back is the answer: a string, and `''` for anything else. It is not read.

A name nobody registered - no component, no shortcode - draws nothing, nor what stands between its tags, and the log says so once an hour for the name (`E_USER_NOTICE`, kept by a counter of its own in `data/component-notices.php`, and at most twenty things a request), not at every request of every page. That is what a shortcode of a feature that is switched off does.

**`addShortcode( $appData, $name, $callback, ?bool $container = null )`** tells the reader whether the name has an end: `true` a container, `false` a leaf, and `null` - the default - a container exactly where its end stands in the document, which is what a name that is written both ways needs (see [Shortcodes](#shortcodes)). Registering a name drops the trees the request has kept, since which names are leaves and which containers is part of how a text is read.

**Trees kept between requests.** The tree of a template file is kept in a php file of its own, `private/data/cache/trees/<sha1 of the path>.php`. `\Nino\Html::fileTree( $appData, '/templates/page-home.tpl' )` answers it and `renderFile()` draws it, with the depth and the context `renderSource()` has; `[template]` draws the file it includes that way. A file that the request finds as it was - its size and modification time are the ones the tree was read with, and so is the stand of the registered names (a component or a shortcode that came or went, an update of the kernel) - is not opened. Where only the time or the size differ the file is read and its sha256 compared, so that a touched file is not made into a tree again. A text of the same size written within the second of the one before it, by a tool that does not set the time, is not seen until the file changes again or the folder is emptied: that is the limit of the comparison, and every cache of the kernel has it.

What is kept is nothing the request needs. A folder that cannot be written, a disk that is full, a file in it that is not the shape of a tree: none is more than a tree that is made again, and none is said on the page. The folder is emptied with the page cache (`\Nino\Html::dropTrees()`, which empties the [cache of a feature's schemas](#a-features-components) as well) and where a feature is switched on, updated or off, it is no part of a backup, and a request looks at a file at most once. `'/nino/markup/cache' => false` in `config.php` turns it off: the file is read as often as it is asked for and nothing is written; the default is `true`. The file is php so that opcache can keep it as it keeps code. **Where opcache is off**, php parses the file as source at every request, which costs more than reading the template again - an installation without opcache sets the switch to `false`. Text that comes from no file - the body of a route, the content of a call - is read once in a request, kept in its memory and in no file.

### A draft for a preview

A panel that shows a change before it is saved - the Builder, the Themes - hands the draft to the kernel and shows the page with a token. `\Nino\Preview` is the token and the folder; what the page does with the draft is the caller's.

```php
// In the action of the panel: the draft is whatever the panel needs to draw it, json-encodable and at most 2 MB
$token = \Nino\Preview::issue( $appData, 'builder', '/_admin/builder/manage', [ 'source' => $source ], $asked );
\Nino\Http::ok( $request, [ 'token' => $token, 'query' => \Nino\Preview::QUERY. '='. $token, 'ttl' => \Nino\Preview::TTL ] );

// In a callback of '/nino/http/request' - the request of the page that carries the query
$held = \Nino\Preview::held( $appData, $request, 'builder', '/_admin/builder/manage' );

if( $held !== null )
	\Nino\Modules\Template::swap( $appData, '/templates/page-home', $held['data']['source'] );
```

`issue( $appData, $kind, $perm, $data, $renew = '', $most = Preview::MAX )` writes the draft to `private/data/.preview/<kind>/<token>/` (the folder of a dot, which a backup does not carry) and holds the token for the session; it answers `''` where there is none to give - no account, an account without the right, a kind that is no word (`[a-z][a-z0-9-]*`), a draft that is too large or not written. The token is 128 bits from `random_bytes()`, valid for `Preview::TTL` (900) seconds, and `$renew` is the token the caller holds: it is kept, with the new draft, while it holds. A session holds at most `$most` tokens of a kind - 24 by default, never more than `Preview::LIMIT` (200); a panel that draws a tile for each of 120 themes asks for 120 - the newest, and the folders of the others go, as do all folders whose time is out whenever one is issued.

`held( $appData, $request, $kind, $perm )` answers `[ 'token', 'expires', 'data' ]` only for the session that was given the token, the account that asked for it, while that account holds `$perm` and the time is not out; else `null`, and the page is the visitors' - nothing says why. A request that holds a draft is a *preview request*: `Preview::active( $appData )` is true for the rest of it, and its response carries `Cache-Control: no-store`, `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex` (it is never in the page cache: a query and an account keep it out of it as well). The token is never logged.

`\Nino\Modules\Template::swap( $appData, $file, $source )` makes `[template <file>]` draw `$source` - as `renderSource()` draws it, with the context of the call and the words of the file's own category - for this request, in place of the file. The file and its kept tree are not touched. It does nothing unless the request holds a draft, takes only a file directly in `templates/` (`/templates/page-home`, with or without `.tpl`), and refuses a source of more than `Template::MAX_BYTES`.

**The trace.** A preview request that asks for it - `\Nino\Preview::trace( $appData )`, called after `held()`, does nothing for a request that holds no draft - draws the page with a comment around what each node drew, so that a screen can tell which node made which element: `<!--nino:n 0.1.0-->...<!--/nino:n-->` around the output of every call - the position is the index of the node in each level, joined by dots, with the template before it where the call stands in a template that `[template]` drew (`page-home:0.1.0`) - and `<!--nino:s ~title-->...<!--/nino:s-->` around the value of a source of text (`text` for a fixed `text="..."`). A comment holds a position, a word and the source as a key and nothing that could end a comment; a source that is none of these is not traced. Only a call of a component with a schema is traced, and only where the comment is a comment: none stands inside `<script>`, `<style>`, `<title>` or `<textarea>`, none in a tag (a quoted value of an attribute may hold a `>`), and the value of a source is marked only where the template of its component writes `[[value]]` as the content of an element - for a component with a renderer by `fill()`, which puts the mark in, so that the value a renderer is handed never carries one. A shortcode (`[json]`, `[template]`, a loop, a menu) is not traced and what it draws of its own text is drawn without positions, so the JSON-LD of a page stays JSON. The context key `trace` is true for a request that asked for the trace and false for any other, whatever a caller puts in it: `'trace' => false` turns it off for a render. A page that is not a preview request is the page it was, byte for byte.

**Writing the words and the picture slots of a page in one step.** `\Nino\Text::put( $appData, $set, $drop = [], $formats = [] )` writes `$set` - `key => language => text` - and removes `$drop`, one `mutate()` of each language file however many keys it takes. A key is written to every language of the site if it is new (an empty text where none is named), only to the languages named if it is there; each text goes through `sanitizeValue()` by the format `meta.php` sets for the key - which a caller never overrules -, else the one `$formats` names for it (`plain`, `inline`, `lines`, `blocks`), else the one the key's values or, for a new key, its texts hold. A key without a format in `meta.php` is cleaned by the format of the caller, so a key that a template writes into an attribute (`alt="[[key]]"`) needs `plain` in `meta.php`: that is the lock, and the one the Text panel keeps too. The answer is `key => language => what stood there` (`null` for nothing; `'*'` for a key of `global.php`), and `null` where nothing was done: a key that is none of the form `/<namespace>/<category>/<part>/<name>` ([the key grammar](#the-key-grammar)), a language with no text file, a text that is none, a key that is both set and removed, a global key to set. The whole call is checked before the first file is touched. `\Nino\Text::values( $appData, '/template/page-home' )` answers every key below a namespace with its texts in every language. `\Nino\Images::putSlot( $appData, $uri, [ 'label', 'width', 'height', 'filename'? ] )` creates a slot or sets its label and size - a slot that is there keeps its file and its alt texts - in one mutation of `config.php`, and `dropSlot( $appData, $uri )` removes it; both answer the slot as it was (`[]` for none, `false` where nothing was written) and touch no file below `/images/`.

### The Register of Components

`\Nino\Components` is the register of the components a template is written in, and it draws the tree `\Nino\Markup` read. A component is **described**, not programmed: its schema says where it may stand, what it offers to the content it holds, where its value comes from, which attributes it takes, which variants it has and the markup it is drawn as. Code is for the component that computes, and that is a renderer. `\Nino\Components::validate()` says what a schema may hold, `addComponent()` registers one and `render()` draws nodes.

**What is registered.** The kernel registers no component of its own. A name is a component where the `init()` of a module registers it with `addComponent()`, or where a feature that is on brings it in folders ([A feature's components](#a-features-components)); [the feature Basis](features.md#the-feature-basis), which comes with the kernel and is off until somebody switches it on, brings twenty. A name that is registered nowhere - the component of a feature that is off, a word that is none - draws nothing, nor what stands between its tags, and the log says so once.

```php
\Nino\Components::addComponent( $appData, 'callout', [
	'label'      => [ 'en_US' => 'Callout', 'de_DE' => 'Hinweis' ],
	'in'         => [ 'page', 'flow' ],
	'offers'     => [ 'flow' ],
	'source'     => 'text',
	'format'     => 'inline',
	'variants'   => [ 'quiet' ],
	'attributes' => [
		'tone'   => [ 'type' => 'choice', 'options' => [ 'info', 'warn' ], 'default' => 'info' ],
		'size'   => [ 'type' => 'choice', 'options' => [ '100', '50', '33' ], 'default' => '100', 'responsive' => true ],
		'sticky' => [ 'type' => 'bool', 'default' => false ],
		'more'   => [ 'type' => 'link', 'default' => '', 'class' => false ],
		'anchor' => [ 'type' => 'id', 'default' => '' ],
	],
	'template'   => '<aside[[id]] class="[[class]]"[[lang]]><b>[[value]]</b> <a href="[[more]]">[[/project/company/general/name]]</a>[[content]]</aside>',
] );

\Nino\Components::addComponent( $appData, 'tag', [
	'label'    => 'Tag',
	'in'       => [ 'callout' ],
	'source'   => 'text',
	'template' => '<i>[[value]]</i>',
] );
```

`[callout /template/page-home/hero/title tone="warn" size="100 m:50 l:33" sticky anchor="top" more="page:/contact#form"][tag text="one"][/callout]` is drawn, for a title `Hi` and a contact page at `/kontakt`, as

```html
<aside id="top" class="callout callout--tone-warn callout--m-size-50 callout--l-size-33 callout--sticky"><b>Hi</b> <a href="/kontakt#form">ACME</a><i>one</i></aside>
```

A component is registered in the `init()` of a module or a feature, where the shortcodes are: the register is in the memory of the request, so it is registered in every request. A feature can also bring its components in folders, which the kernel reads and registers before any `init()` runs ([A feature's components](#a-features-components)). A component that is registered under a name again takes the place of the first and keeps its position in the list, and nothing is said - nothing, that is, but a name that is a shortcode as well, which is said once (see [Shortcodes](#shortcodes)).

**The schema.** Every key is optional but `label`; a key that is none of these is refused, not left out.

| Key | Holds |
| --- | --- |
| `label` | A text, or a `locale => text` map. No control character but a line break and a tab |
| `group` | A slug a tool offers the component under. A component a feature brings in its folders names one, and the feature has the folder of that group (see [A feature's components](#a-features-components)) |
| `in` | The places it may stand in, a list of distinct slugs. `page` and `flow` are the kernel's. Without it the component is offered nowhere |
| `offers` | The places it offers to what it holds. Its presence, even as `[]`, makes it a container; its own name is always one of them |
| `once` | `true`: once in a container. The register stores it, and whoever lets a person place components enforces it |
| `through` | `true`: a container that draws no element round what it holds - a loop - so that what may stand in it is what the container it stands in allows. It needs `offers` and `'children' => 'nodes'` |
| `children` | `'nodes'`: the register does not draw what the component holds. Its renderer is handed the nodes and draws them itself, as often as it likes - the component of a loop. It is the only value there is, and a component that has it needs a renderer (see **The renderer** below) |
| `source` | What its first word is: `text`, `image`, `link` or `none` (the default) |
| `format` | For a `text`: `plain` (the default), `inline`, `lines` or `blocks`. A component that has an attribute named `format` says it for each call, and the default of that attribute is the format of the schema (see below) |
| `attributes` | `name => schema` of the attributes, below |
| `variants` | The words of its one open axis, a list. `regular` is always there, first |
| `template` | The markup, a string of at most 65 536 bytes. A string that is no more than the name of a `.tpl` file is a file name, which `validate()` reads in the directory of a feature where it is given one and `addComponent()` is not: there it is refused |
| `icon`, `preview` | For a tool, and nothing the drawing reads: an icon, the name of one of `\Nino\Components::ICONS` (the table of `Nino.adminUi.icon()`, which the tree of the workbench draws before the component; the neutral square, `\Nino\Components::NEUTRAL_ICON`, where there is none and for a name the table has not, which the log says once), and one of the pictures `title`, `text`, `image`, `button`, `block` (the default), `cells`, `table` |

A name is a slug of at most 40 characters - lower case letters, digits and hyphens, the first a letter, a hyphen only between two of the others - and is neither `page` nor `flow`; those two are the places of the kernel.

**An attribute** is `name => schema`. The name is lowerCamel, `[a-z][a-zA-Z0-9]*`, at most 40 characters, and none of `value`, `source`, `content`, `class`, `text`, `variant` and `lang`, which the drawing writes itself.

| Key | Holds |
| --- | --- |
| `type` | `choice`, `bool`, `int`, `string`, `text`, `image`, `link` or `id` |
| `default` | Required: a value of the type. A choice's is one of its options, an int's lies within its bounds, a bool's is `true` or `false`, and an id's is empty or a word |
| `options` | For a choice: a list of strings or integers without a repeat - or `'\Namespace\Class::method'`, a public static method that answers `value => label` and is asked once for each component and attribute in a request |
| `labels` | For a choice whose `options` are a list: a map of option => label, a text or a locale => text map, for a tool that shows the options and wants more than the word: `'labels' => [ 'start' => [ 'en_US' => 'Left', 'de_DE' => 'Links' ] ]`. A label is taken for an option the list holds, when it is at most 200 bytes and holds nothing empty and no control character but the line break and the tab; one that is not is left out and its option stays, and a choice without a valid label has no `labels` key. A tool reads `\Nino\Features::localized( $attribute['labels'][$option] ?? '', $locale )` from the schema `components()` answers - it is the same for a responsive choice, whose steps are its options - and an option with no label is shown as its word. A choice whose options are a method answers its labels with the list |
| `min`, `max` | For an int, integers, `min` not above `max` |
| `responsive` | For a choice that is a class: the value may be a width for every viewport |
| `class` | Whether the value is written as a class. `true` for a choice and a bool, `false` for the others, and only those two may say `true`. A `false` value is the token `[[attribute]]` of the template instead |
| `label`, `hint`, `input` | For a tool: text or a locale map, and how it draws the attribute - a choice `select`, `segment` or `icons` (for an alignment), a bool `switch` or `checkbox` |

How a call's value becomes what the renderer is handed - always a string:

- **choice**: the value if it is among the options, else the default. As a class where `class` is true and the value is not the default and not empty: `callout--tone-warn`. The options of a class are words (lower case letters, digits and hyphens) or empty.
- **bool**: a bare word, or one of `1 true yes on` - in any case - is on, `0 false no off` is off, any other word is the default. The renderer gets `'1'` or `'0'`; on, it is the class `callout--sticky`.
- **int**: `-?` and up to nine digits, kept within `min` and `max`, else the default.
- **string**: the value, escaped, with the fills of the site in it put in (the tokens of a loop too, below); the default where the call says none.
- **text**: a second text of a call, a caption or an alternative text. What is written is a reference - `~name`, a key with a slash in front, `.field`, read as the first word of a call is, in the section of the call and with the format `plain` - or the text itself, with the fills of the site in it put in, escaped once. It is a reference where it is one word: a `~` or a `/` with a space in it, a `.` that is no field name and a bracket in it make a text, so that `~ about 5` and `.NET rocks` are what they say. A key nobody has a text for is nothing. The default is written the same way, so `'default' => '~alt'` is the key `alt` of the section. What it holds is never read: a call, a fill or a placeholder in the text of a key or in the value is the text it is.
- **image**: the reference as it is written, escaped. The renderer looks the picture up and never writes it as a file name.
- **link**: an address, below.
- **id**: a slug of at most 64 characters (`[a-z][a-z0-9-]*`), else nothing. It is the `[[id]]` of the template, written ` id="top"`, and the **scope** of its children: the `scope` of their context, which `~name` reads. It is the scope of the call's own words as well: `[section-wrap id="hero" image="~background"]` reads `/template/<template>/hero/background`, and `[section-title ~title id="hero"]` the title of the hero, whatever the section round the call is - an id that is none leaves the one round it. The id of the default counts where the call says none. A component has one attribute of this type at most, and an attribute named `id` has to be of it.

**The tokens of a loop in a value.** A component that stands in a loop - the children of a `[collection]`, the content of an `[elements]` - has the fields of the round in its context (`context.fills`: `[[title]]`, `[[.slug]]`, `[[.id]]` and the other tokens `\Nino\Modules\Elements::fills()` makes). The value of an attribute of the type `string`, `text` or `link`, and a fixed `text="..."`, takes them in, in the same pass as the fills of the site: `[button href="/leistungen/[[.slug]]" text="More on [[title]]"]`. What a token holds is the text of the field, and that is all it is:

- It is read as the text it is. A tag of a rich-text field is no part of an attribute and is dropped, an entity is the character it stands for, a line break stays; the text is then escaped for its place once. A field that spells a call (`[template /templates/x]`), a key of the site (`[[/project/company/general/name]]`), a token (`[[.slug]]`) or a quote stays that text: nothing in it is looked up and no second round puts a token into a token.
- Only the tokens of the loop are put in, those the loop has a field for. A token it has no field for stays written, and so does `[[.lang]]`, which is markup (` lang="de-DE"`) and no field. The keys of the site, `[[/key]]`, are what they are everywhere: the text of the key in the language of the page, nothing where nobody has one - in a loop and outside of one.
- A link is looked at after the tokens are in, as a link that is written is: an address that is a script (`javascript:`, in any case, with a blank or a control character in it), a data address, another host by a second slash or a backslash, or a scheme that is none of `http`, `https`, `mailto`, `tel` is no link. An address that holds a token of the loop is an address even where it starts with a slash - a field never makes a key of it. A field in the path of the address is a part of the path: it is written percent-encoded (`/s/[[title]]` for `A/B?c` is `/s/A%2FB%3Fc`), so that it cannot make a path, a query or an address of its own; after `mailto:` and `tel:` the `@` and the `+` stay, and after `page:` the field is the path of a page, which is checked as a path is. A field at the start of the value - `href="[[url]]"` - is the address, and is checked as one.
- An attribute of the type `choice`, `bool`, `int`, `id` or `image` takes no token: the value is read as it was. A picture of an element of a loop is the name of its image field: `[image .photo]`, `[footer-logo .photo]` and `[section-wrap image=".photo"]` draw the file the field holds, and the alt text of the field the model of the field names (`'alt' => 'photoAlt'`) where the call says none. A file name that is empty, absolute, climbs out with `..` or holds a control character is no picture.

Where no token is in the context, nothing changes: a token is the text it is written as. The context holds tokens in more places than a loop of elements: the content of `[imagevalues]` (`[[src]]`, `[[alt]]`, `[[width]]`, `[[height]]`), of `[element]` and of `[elementvalues]` (`[[.value]]`, `[[.count]]`) is drawn with them, and a call in it is filled like one in a loop - `[imagevalues /logo][button text="[[alt]]" href="[[src]]"][/imagevalues]` draws the alt text in the button and the address of the picture as its link.

A field that is the whole value or its start - `href="[[url]]"`, or the host in `href="https://[[host]]/x"` - is an address like a written one: what the check lets through, a field lets through, so that every `https` host is allowed, and so is every path. What the author does not want to be an address is written with a part in front of the token, which is then a part of a path.

**Places.** A place is a word. `page` is the page itself, the top of a document; `flow` is the flow of content; a component's own name is the place of what it holds, and `offers` adds the others. `\Nino\Components::allowed( $appData, $parent )` answers the components that may stand directly in a parent, in the order they were registered: those whose `in` names a place the parent offers. `''` and `'page'` are the page; a container offers its own name and the places it names; a leaf offers none; a name that is no component is a place that offers itself. For the two components above `allowed( $appData, '' )` is `[ 'callout' ]`, `allowed( $appData, 'callout' )` is `[ 'callout', 'tag' ]` and `allowed( $appData, 'tag' )` is `[]`. A tool asks it and the drawing does not: a component at a place that is not its own is drawn all the same. `\Nino\Components::allowed( $appData, $parent, $container = '' )` takes the container the parent stands in as a third argument, which only a component with `'through' => true` reads. A loop - a container whose renderer draws what it holds once for each element - stands between a row and its columns and draws no element of its own, so what is in it is what the row holds, and not every place the loop offers: with `offers` of `page`, `flow` and `section-row`, `allowed( $appData, 'loop' )` is the wrap of a page, the header and the column of a row alike, which a tool must not offer in a row. With `'through' => true` in its schema, `allowed( $appData, 'loop', 'section-row' )` is what `allowed( $appData, 'section-row' )` is. (The feature Collections brings a loop of its own; whether its schema says `through` is the feature's.) A tool that knows where the loop stands says it - the nearest container that is no `through` itself, for a loop in a loop the one the outer loop stands in - and without a container the answer is what the places it offers say, as it is for every container. A component that is not `through` takes no notice of the third argument, and drawing asks nobody where a component stands. A schema that says `through` without `offers` or without `'children' => 'nodes'` is refused.

**Rules of a place.** `allowed()` says which components fit a place; a tool that edits a page needs the rest of what a place says, and the server that saves it needs the same answer. `\Nino\Components::offer( $appData, $path, $siblings )` is that answer for the container at the end of `$path` - the names of the components from the page to it, `[]` for the page - that holds `$siblings`, the nodes `\Nino\Markup::parse()` gave: one entry for each name `allowed()` gives, `true` where it may be put and the reason where it may not. The reasons are constants: `Components::CODE_ONCE` (its schema says `once` and the container holds one), `CODE_DEPTH` (it would be a level beyond `Components::LEVELS`, 6, counted from the first name of the path, a `through` container no level), `CODE_SELF` (a container for `flow` does not stand in itself, however far up the other one is) and `CODE_HTML` (it is the component `html`: who may write it is no rule of the register, a caller that may takes it for `true`). A `through` container - one that draws no element of its own, a loop or a filter - is no level and has no places of its own: a container on the path before it that is none stands in its stead, however many follow one another (a filter, a loop and then the columns of a row offer the columns of the row), and where there is none the place is the page. What may stand in a `through` container is the cut of what that container before it allows and of what the container itself lets in: a component that names it in its `in`, or one whose `in` has a place the container offers in `offers`. A filter with `'offers' => []` therefore holds only what names it (a loop that lists the filter in its `in`), and a `through` container does not stand in itself. `through` means nothing else: the register reads it in `allowed()`, `offer()` and `check()` and validates that the schema has `offers` and `'children' => 'nodes'`; drawing does not read it.

```php
// What a tool shows in the menu of "add" inside the third column of the first section
$offered = \Nino\Components::offer( $appData, [ 'section-wrap', 'section-row', 'section-col' ], $column['c'] );
// [ 'button' => true, 'button-group' => true, 'html' => 'html', ... ]

// What the server finds wrong in the tree it is asked to save: position => findings
$found = \Nino\Components::check( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ), $notes ) );
// [ '0.1.0' => [ [ 'code' => \Nino\Components::CODE_PLACE, 'detail' => 'section-col' ] ] ]
```

`\Nino\Components::check( $appData, $nodes )` reads a whole tree by the same rules and answers, for each node that has something against it, its position - the path of the trace, the index of the node in each level, text nodes counted (`0.1.0`) - and a list of `[ 'code', 'detail' ]`. The codes: `CODE_UNKNOWN` (F1, a name that is no component and no shortcode) and `CODE_BARE` (a shortcode, a call with no schema, whose content is its own); neither is looked into. `CODE_VARIANT` (F2, a variant the component does not have, asked of the callbacks of `/nino/components/variants` as `render()` asks them; the detail is the word), `CODE_OUTSIDE` (F3, a value that is none of what its attribute takes) and `CODE_FOREIGN` (F3, an attribute the schema does not name); the detail is the attribute. `CODE_PLACE` (F7, a component at a place that offers none for it), `CODE_ONCE` (K4, for the second and each after), `CODE_SELF` and `CODE_DEPTH` (K5; said once, at the first, and what stands below it is looked at all the same); the detail is the name. `CODE_DUPLICATE` (an id of an attribute of the type `id` that stands twice in the tree, for the second and each after) and `CODE_FREE` (a text between calls that holds a `<`: html that is no component). What stands in a call of `html` is its content and no finding; the call itself is `CODE_HTML`, and whether that is wrong is for the caller, who knows what the account may write. `check()` does not look at the sources of the calls, at textfills in values or between calls, at the length or the control characters of a value, at where a link goes or at the ids of what a call with no schema holds: the drawing makes these safe, or the caller looks. A tree with nothing against it is `[]`. The two functions share one rule: what `offer()` does not offer, `check()` finds (and `html`, which `offer()` names and `check()` reports).

**Classes.** The name first, then in the order of the schema one class for each attribute that is a class, then the variant: `callout callout--tone-warn callout--sticky callout--variant-quiet`. A responsive choice says the width for every viewport in its first part and, behind it, one for each breakpoint of `\Nino\Markup::BREAKPOINTS` - `m` from 768 px, `l` from 1024 px: `size="100 m:50 l:33"` is `callout--m-size-50 callout--l-size-33` (the first part is the default and no class), and `size="50"` is `callout--size-50`. A second part without a breakpoint, a breakpoint nobody knows, a value outside the options and a breakpoint named twice fall away; where the first part has a breakpoint, the value for every viewport is the default.

**Variants.** `variant="quiet"` is the class `callout--variant-quiet`; `regular` is no class. The variants of a component are the list of its schema and what the callbacks of **`/nino/components/variants`** make of it - the way a part that brings variants adds them. A callback is handed `[ 'name' => 'callout', 'variants' => [ 'regular', 'quiet' ], 'standard' => 'regular' ]` and answers the same shape:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/components/variants', static function( array &$appData, array $answer ): array {

	if( $answer['name'] === 'callout' ) {
		$answer['variants'][] = 'dark';
		$answer['standard'] = 'dark';
	}

	return $answer;
} );
```

A variant that the call names and the list does not hold is the standard, and so is none; the standard is `regular` unless a callback says another. An answer that is no list of words with `regular` in it and a standard among them is a notice in the log (`E_USER_NOTICE`, once for the name in a request), and the schema's variants stand. The callbacks are asked once for a name in a drawing.

An answer may also carry `labels`, a map of variant => label - a text, or a locale => text map, as the `label` of a component is - for a tool that shows a variant and wants more than the word: `'labels' => [ 'dark' => 'Dark surface' ]`. A label is taken for a word the answer names, when it is at most 200 bytes and holds nothing empty and no control character but the line break and the tab; one that is not is left out and its variant stays. A schema says the same for its own variants with a `labels` key beside `variants`. `\Nino\Components::variants( $appData, 'callout' )` answers what `render()` sees, the callbacks included: `[ 'standard' => 'dark', 'variants' => [ 'regular' => null, 'quiet' => null, 'dark' => 'Dark surface' ] ]` - the label of a word that has none is `null`, and a name that is no component has no variants.

**Sources.** What the first word of a call - or the value of an attribute of the type `link` or `image` - names, and what comes of it:

| Written | Is |
| --- | --- |
| `text="..."` | for a component whose `source` is `text`: a fixed text instead of the first word. The fills of the site in it are put in, `[[/key]]` as the text of the key |
| `~name` | the key `/template/<template>/<scope>/<name>` of the context, the scope being the id of the call itself where it has one, else the nearest above. Without a template or a scope there is no key: nothing is drawn and the log says so once |
| `/a/b/c/d` | a key, as it is written; a fill inside it is put in first |
| `.field` | a field of the element of the context - `.id` the number in the loop, `.uri` its uri, `.slug` its name alone - and nothing where there is no element |
| `page:/contact#form` | for a **link** - the text of a key, or the value of an attribute: the address of that page of the site in the language of the page, with the directory the project is served from in front and the anchor or query behind. Only a route that is a page of the website is one; otherwise the link is nothing |

The first word of a call is a key, `~name` or `.field`, because a source begins with `~`, `/` or `.`: an address or `page:` stands in the text of a key or in an attribute. A **text** is the text of the key in the language of the page - where that has none, the text of another language, and the component writes it with `lang` (the token `[[lang]]`, ` lang="de-DE"`, which `\Nino\Html::fillOrigin()` supplies); where no language has one, or the text is empty, the component draws nothing, and nor do its children. It is never read as markup: `plain` is read as the text it is - entities are the characters they stand for - and escaped once, `inline`, `lines` and `blocks` are sanitized by `\Nino\Html::sanitizeHtml()`, and every `[` stays what it is. A **link** is an address that a browser may be sent to: a path, an anchor, or `https:`, `http:`, `mailto:` and `tel:` - any other scheme, a control character and a second slash are no address and the link is nothing. A key that is no text of the site is the address it is written as (`/contact`), and a key under `/_nino/webpage/` is the address of a page and is written with the directory the project is served from. An **image** is the reference to the picture - the key as it is, escaped, or the file an image field holds - and the renderer looks the picture up.

**The context of a drawing.** A call reads from the place it stands in, the context of [Drawing a page](#drawing-a-page). `~name` is the key `/template/<template>/<scope>/<name>`: the template is the page template that is drawn (`[template /templates/page-home]` makes it `page-home`) and the scope the `id` of the call itself, or of the nearest call above that has an attribute of the type `id`. In `page-home.tpl`

```html
[section-wrap id="news"]
	[section-row]
		[section-col]
			[section-title ~head tag="h2"]
		[/section-col]
	[/section-row]
[/section-wrap]
```

draws the text of `/template/page-home/news/head` as `<h2 class="section-title">...</h2>`, and a call with no template or no `id` above it has no key and draws nothing (the log says so once). A call in a loop reads the fields of the element of its round: `[elements /meetups sort="date"]` draws what stands between its tags once for each element, with the element as the context, so that `[section-title .title tag="h3"]` writes the field `title` as the type of the field writes it, `.id` is the number in the loop, `.uri` the uri of the element and `.slug` its name alone. The textfills of the fields, `[[place]]`, are the loop's own and stand in the text between the calls:

```html
[elements /meetups sort="date"][section-title .title tag="h3"] ([[place]])|[/elements]
```

draws `<h3 class="section-title">Party</h3> (Hall)|<h3 class="section-title">Walk</h3> (Park)|` for two elements, `party` in the hall and `walk` in the park.

**The template.** The markup is filled in one pass, so that what a value brings is never looked at again: a value never becomes a call or a fill, and a value that spells `[[content]]` stays what it is. The placeholders:

| Placeholder | Is |
| --- | --- |
| `[[class]]` | the classes, the name first, with no space in front |
| `[[value]]` | the value of the source, escaped; empty where the source is `none` |
| `[[content]]` | what the children drew |
| `[[content:name]]` | what the children called `name` drew: a template that names a slot gets the children of that name there, and `[[content]]` the others |
| `[[id]]` | ` id="top"` for the attribute of the type `id`, or nothing |
| `[[lang]]` | ` lang="de-DE"` for a text of another language than the page's, or nothing |
| `[[attribute]]` | the value of each attribute that is no class |
| `[[/a/b/c/d]]` | a fill of the site, with a slash in front: the text of the key as plain text, escaped, and nothing where the key has none |

**The renderer.** A component that computes is handed `( array &$appData, array $args, string $content, array $context ): string`; with a renderer, the template is not drawn - the renderer has it filled, `\Nino\Components::fill()`, below. `$args` holds `value` (as `[[value]]`), `source` (the first word as it is written, not escaped), `class`, `variant` and `lang` (as `[[lang]]`), then each attribute under its name as a string, the default where the call says none, an `id` as its slug, and `content:<name>` for each `[[content:<name>]]` the template names: what the children of that name drew. `$content` is what the other children drew and `$context` the context of the call (see [Drawing a page](#drawing-a-page)). What it answers is finished and read by nobody. A scalar is written as a string, anything else is nothing. A renderer that throws a `\Exception` draws nothing, the page is finished, and the log has one line for the name in a request (`E_USER_WARNING`); an `\Error` is a bug of the renderer and goes on up.

**`fill( $appData, $name, $args, $content, $tokens )`** answers the template of a component filled the way the register fills it - `[[class]]`, `[[value]]`, `[[content]]`, `[[content:name]]`, `[[id]]`, `[[lang]]`, `[[attribute]]` and the fills of the site the template names - with the tokens of the renderer's own added, in one pass. A renderer that has markup to leave out or to add - a link around a title only where there is an address, a `<figcaption>` only where there is a caption - makes that markup, escaped, and gives it as a token, so that the markup of the component stays in its template:

```php
public static function componentTitle( array &$appData, array $args, string $content, array $context ): string {

	$open = $args['href'] === '' ? '' : '<a href="'. $args['href']. '">';

	return \Nino\Components::fill( $appData, 'title', $args, $content, [ '[[open]]' => $open, '[[close]]' => $open === '' ? '' : '</a>' ] );
}
```

With the template `<h2 class="[[class]]">[[open]][[value]][[close]]</h2>`, the call `[title text="Hi" href="/x"]` is `<h2 class="title"><a href="/x">Hi</a></h2>` and `[title text="Hi"]` is `<h2 class="title">Hi</h2>`. `$tokens` is `[ '[[token]]' => markup ]`; a token wins over a placeholder of the same name, and one whose value is no scalar is no token. What a token holds and what an argument holds is read by nobody - a token that spells `[[content]]` stays so - and the `$args` of the renderer may be changed before it asks; what is not there is nothing. A name nobody registered, and a component without a template, is `''`. The fills of the site are read when the template names one, so a fill the renderer added before is in it.

**A component that draws what it holds.** With `'children' => 'nodes'` the register draws nothing of what the component holds: the renderer is handed `$content` as `''` and `$context` as the context its children are drawn in - the template, the scope its own id makes, its name as the place, one deeper, the element and the tokens of the call - with the nodes under `'children'`, an empty list for a call that holds nothing. It draws them with `\Nino\Components::render( $appData, $nodes, $context )`, once for each round of its own, and gives each round the context it needs, `'element'` and `'fills'` among them:

```php
foreach( $items as $index => $item ) {

	$round = [ 'element' => $item, 'fills' => [ '[[n]]' => (string) $index ] ] + $context;
	$html .= '<li>'. \Nino\Components::render( $appData, $nodes, $round ). '</li>';
}
```

What the children are is not looked at before: a name nobody registered in them is not said, since nothing is drawn unless the renderer draws it. `addComponent()` refuses `'children' => 'nodes'` without a renderer.

**When a call is not what the schema says.** The register never refuses a document; it draws what it can.

| The call | What is drawn |
| --- | --- |
| a name nobody registered | nothing, and nothing it holds; the log says so once an hour for the name |
| a variant the component does not have | the standard variant |
| an attribute value outside its options, a number that is none, a word that is no bool | the default |
| an attribute the schema does not have | it falls away |
| a component at a place that is not its own | it is drawn |
| a text that is missing in the language of the page | the text of another language, with its `lang`; where there is none the component draws nothing |
| a call with no end, an end with no call | the open call is closed where the container round it or the document ends, a stray end is skipped; the page stays whole (the notes of [the notation](#html---the-notation)) |

**What a source reads.** `\Nino\Components::sources( $appData, $source, $template )` answers what the calls of a source - html+, as a template file is written - read through the register, for a tool that looks for the keys a template uses or for the slots of its pictures, and draws nothing: no renderer runs, no key is looked up, and the log is not told. `$template` is the name `~name` reads under, as the context calls it (`page-home`). Of each call of a registered component, in the order they stand, it lists the first word where the source of the component is a text, an image or a link, and then each attribute of the type `text`, `image` or `link` - the default where the call says none - that names a key: `~name`, which the template and the id of the call itself, or else the nearest above it, make a key of, and a key with a slash in front. A link names a key only as `~name`: written with a slash it may be an address as well, which the drawing decides. Not listed are a fixed `text="..."`, a `.field`, a `~name` where there is no template or no id above, a name nobody registered, a fill that stands in the source (`[[/key]]`; whoever scans for fills finds it in the text), the template a `[template]` includes, and everything that holds a space, a bracket or a control character: a value is no call, and what a value holds reads nothing. An entry is `[ 'name' => 'figure', 'kind' => 'text', 'source' => '/template/page-home/hero/caption', 'written' => '~caption', 'attribute' => 'caption' ]`, the attribute for one that is an attribute. For `[hold anchor="hero" image="~background"][heading ~title][/hold]` it lists `/template/page-home/hero/background` as an image and `/template/page-home/hero/title` as a text, the same keys the drawing reads.

**The rest of the API.** `components( $appData )` answers the clean schemas by name, in the order they were registered - an attribute whose options are a method carries the answer of the method as `options` and `optionLabels` - and `containers()` which names are containers (those that have `offers`), as `\Nino\Markup::parse()` is told them. `version( $appData )` is the sha1 of the schemas: what a tree kept in a file depends on. `escape()` escapes a value for html. `validate( $name, $schema )` answers the clean schema or what is wrong with it as a string; `addComponent()` ends the request with `\Nino\Runtime::fail()` on it - and on a component with neither a renderer nor a template - and registers nothing.

**What is refused.** A schema is, with the reason in the message, when
- the name is no slug, is longer than 40 characters, or is `page` or `flow`, or the schema is no array;
- a key of the schema or of an attribute is none of those above - nothing unknown is left out;
- `label` is empty or no text or map; `group`, `in`, `offers`, `variants` or the words in them are none, or a place is named twice; `once` is no bool;
- `source` or `preview` is none of its words, `format` stands with a source that is no text or is none of its words, `children` is anything but `'nodes'`;
- an attribute named `format` stands on a component whose source is no text, is no choice, has options that are a method or are none of `plain`, `inline`, `lines` and `blocks`, or has a default that is not the `format` of the schema where the schema names one - without it, the default is the format of the clean schema;
- an attribute has no `default`, an unknown `type`, a name that is no lowerCamel identifier or that is reserved, a `choice` without options, with a repeated option, with a default outside them or with options that are no words where they are classes, an `int` with a bound that is no integer, `min` above `max` or a default outside them, a `bool` whose default is no bool, a `string`, `link` or `image` whose default is no string, an `id` whose default is no word or longer than 64 characters, a `class` that is no bool or is `true` on a type that makes no class, a `responsive` that is not on a choice that is a class, `options`, `min` or `max` on another type, or an `input` its type has not;
- an attribute is named `id` and is not of the type `id`, or a second attribute is;
- a `template` is no string, is longer than 65 536 bytes or has a null byte;
- an `icon` is no string (a name the table has not is no reason: the neutral icon stands for it).

### A feature's components

A feature brings components of this register in folders, so that the kernel reads and checks them, writes down what it found, and registers them in every request before the `init()` of any module runs:

```
<Feature>/
	feature.php                      the manifest; its keys components and stacks are not read
	<Feature>.php                    the class: init(), the renderers component<Name>(), the methods that answer the options of an attribute
	components/<name>/component.php  returns the schema; the name of the folder is the name of the component
	components/<name>/template.tpl   the template, where the schema names none
	groups/<group>/group.php         returns the words of the group and its knobs
	groups/<group>/part/part.css     the standard part of the group
	groups/<group>/demo.tpl          a piece of a page that shows what the knobs of the group do
```

The kernel does this for each feature that is listed in `/nino/modules` and has a folder `components/`, a feature of `features/` and a [feature that comes with the kernel](features.md#a-feature-that-comes-with-the-kernel) alike (`\Nino\Features::registerComponents()`, asked by `\Nino\init()`). The folder of a component is a slug; its `component.php` returns what `addComponent()` takes, with the `template.tpl` beside it as its template where the schema names none: the markup is the schema's own or the `template.tpl` beside the file, and a schema that names another file as its template is refused, since a file the stamp does not list would change unseen. The schema is checked by `validate()` with the key and the directory of the feature, and a component of a feature **names its `group`**, which has a folder `groups/<group>/` with a `group.php` that is accepted. The renderer of a component is `[ \Nino\Modules\<Feature>, 'component'. <Name> ]`, the name in studly caps, where the class has that method; a component with neither that method nor a template - or one that draws what it holds and has no renderer - is said once in a request and left out. A manifest that has the keys `components` or `stacks` is a valid manifest: the keys are not read, the names they declare are registered nowhere, and the Features panel says so (`\Nino\Features::componentProblems()`).

**A group** is `groups/<group>/group.php`, which returns `[ 'label' => text or map, 'knobs' => ... ]`. A knob is `'<component>.<name>' => [ 'token' => '--<component>-<name>', 'label' => text or map, 'ladder' => five steps ]`, of a component of the group, at most six for a component. A step is a value of css as a theme may write it as it is: at most 120 characters of letters, digits, spaces and `. , % ( ) + * / # -`, with no quote, semicolon, brace, comment or `url(`. `\Nino\Components::validateGroup()` checks it. A group and a component name belong to **one feature**: what a feature registered first is not another's to take - the second is said once in a request with both names, and goes on without it (a component of a group that is refused goes with it). The features that come with the kernel are registered first, the others in the order of `/nino/modules`, so that a project's order of switching on takes nothing from a delivered feature. `\Nino\Components::groups( $appData )` answers `group => [ 'feature', 'label', 'knobs', 'part', 'demo', 'components' ]` in the order they were registered.

**The demo** of a group is its `demo.tpl`: a short piece of a page in HTML+ that shows what the knobs of the group do, for a screen that lets a person turn them and wants something to look at while they do. The group answers it as `demo`, the text of the file, `''` where it has none. The kernel brings no route and no page for it: whoever shows it draws it with `\Nino\Html::renderSource( $appData, $demo )` into a page that has the style bundle, and the knobs that a theme set are the ones the page has. A demo is made of components - those of its feature and of the feature Basis, which draws its own where it is switched on - and of texts of its own written in the file: `[section-title text="A title"]`, and for a text with paragraphs `[text text="<p>One</p><p>Two</p>"]`. It reads nothing of the project: no `[[` in it (a textfill, or a fixed text that would fill one in), no source that is a key, `~name` or `.field`, and no value of an attribute that begins with `~`, `/` or `.`, which a text, a link or a picture would read as a reference. A picture is one that the kernel brings: `\Nino\Images::PLACEHOLDERS` names two, `/nino/placeholder/landscape` (800 x 600) and `/nino/placeholder/mark` (160 x 160), which `\Nino\Images::picture()` answers as a vector picture in a `data:` address whatever slots the project has, so that `[image /nino/placeholder/landscape decorative]` and `[header-logo light="/nino/placeholder/mark" dark="/nino/placeholder/mark" href="#"]` draw one. Elements are the kernel's as well: `\Nino\Elements::PLACEHOLDERS` names one type of elements, `/_placeholder`, a type file in the constant - the fields `title`, `text`, `date` (an ISO date), `price` (a number) and `link` (a string), none of them per language, and six elements that differ in the length of their texts and in the order of every field that sorts. Where a call names it - `[collection-list /_placeholder sort="title" limit="3"]` - the demo may loop it, and a child of that call may read the fields of the round with a source or a value that is a dot and the name of a field - `[collection-col .price]`, `[text text=".title"]` - and no other field and nothing outside the loop. `\Nino\Elements::getElementModel()`, `queryElements()`, `getElement()` and so `\Nino\Modules\Elements::queryLoop()` answer it from the constant, with sort, offset, limit and query as they answer a type of the project, whatever types the project has; no write knows it (they refuse it and say so in the log), a file of the project of that name is never read, the workbench lists it as no type, and its name - it begins with an underscore, where the name of a type of the project begins with a letter - is none the workbench or a feature's seed takes. A demo should show every knob of its group at work and every value of the choice a knob hangs on - the gaps of a row, the sizes of a button, the variant card of a column - and call every component of the group once: two sections and a row of cards for a group of sections, every tone and size for a group of buttons. What a component of Basis reads from the project by itself is the project's all the same: the menus in the demos of the header and of the foot, and the name of the company in the line of the copyright.

The demo is read like a template of the feature (`\Nino\Components::demoProblem()` asks what is wrong with it): a file of the folder, not a link, at most 65 536 bytes, utf-8 with no null byte, and read as HTML+ with no note - every call closed, no stray end, nothing deeper than sixteen levels. One that breaks this is said once in a request with the group and the reason, and the group has no demo (`''`): its knobs, its part and its components are the feature's all the same.

**What a part may overrule.** A part - the standard part of a group, or one a hook puts in its place - works with what the register holds and is checked by `partProblems()` for nothing more than the rule of mixing. It may set the steps of the knobs of its group, each a value that `\Nino\Components::validStep()` accepts; it may bring knobs of its own, which are named `--<component>-x-<name>` (the key `<component>.x-<name>` in `group.php`, `--tidy-x-glow` for `tidy.x-glow`) and are read and set as a core knob is, by its own components only; and through `/nino/components/variants` it may add variants to its components and choose their standard - the variants the schema names stay in the list, in front, whatever a callback answers.

**A ground** is the one attribute whose name is the kernel's, like the variant: a component of a feature that has an attribute `ground` makes it a choice among `none`, `base`, `alt`, `tint`, `primary` and `dark` (or part of them), or `\Nino\Features::readComponents()` refuses it. The register writes it as the class `<name>--ground-<word>`, and `Nino.css` answers that class, the same for every component, so that a part never has to know what it stands on: it names the roles `--brand-ground`, `--brand-ink` and the rest for what stands on it. `base` is the roles of the page, `alt` and `tint` take `--brand-ground-alt` and `--brand-ground-tint` as the ground, `dark` sets `color-scheme: dark`, which turns every `light-dark()` pair of the palette at once, and `primary` takes the ground, the ink and the accent from `--brand-color-1` and `--brand-on-color-1`. The selectors meet the word whole - at the end of the value of `class` or before the next class - so that `--ground-alt` is not `--ground-alternative`. A button on a dark section is right because it reads `--brand-ground`, `--brand-ink` and `--brand-accent` and nothing else.

**`Nino.css`** is the reset and the common denominator of every page, and the whole of it is the layer `nino.reset`: the tokens below, the contract of the grounds, the elements at rest, `.nino-sr-only` for what only a screen reader hears, `.nino-richtext` for a text that keeps its paragraphs and lists, and an article in `main` for the pages of the kernel's modules. It draws no component and sets no type of a page - outside such an article, headings are the size of the text and lists have no marks - and that is meant: the look of a page is a feature's or the project's. `body` is a column, so that the foot of a short page stands at the bottom; a project that needs the block flow of its own markup says `body { display: block }`. The tokens are the contract a standard part reads, and a part reads these and no others of the page:

| Tokens | Hold |
| --- | --- |
| `--brand-scheme` | the mode of the page, `light dark` for the visitor's own, `light` or `dark`; `color-scheme` is set from it |
| `--brand-color-1`, `--brand-color-2`, `--brand-on-color-1`, `--brand-on-color-2` | the two colours of the brand and the colour written on each |
| `--brand-font-title`, `--brand-font-text`, `--brand-font-button` | the fonts of the titles, of the text and of the buttons |
| `--brand-ground-base`, `--brand-ground-alt`, `--brand-ground-tint`, `--brand-ink-base`, `--brand-ink-quiet-base`, `--brand-line-base`, `--brand-accent-base`, `--brand-on-accent-base` | the palette worked out of the colours, a `light-dark()` pair each |
| `--brand-second-base`, `--brand-on-second-base` | the second colour as a thing that stands on a ground, a `light-dark()` pair: `--brand-color-2` and its writing on a light page, on a dark page the colour lightened by a half with white and dark writing; a brand with one colour sets `--brand-color-2` and `--brand-on-color-2` to `initial` and has 35 % of the first colour mixed with the ink (`--brand-second-from-1`) under white writing, which holds 4.5 : 1 for every first colour; a brand that writes its colours by hand chooses a first colour that reads on white, since the accent is that colour in a light page |
| `--brand-ground`, `--brand-ink`, `--brand-ink-quiet`, `--brand-line`, `--brand-accent`, `--brand-on-accent`, `--brand-second`, `--brand-on-second` | the roles: what the ground is and what is written on it, the quiet ink, the line, the accent and what is written on it. Each points at the palette, a ground names them again, and a part reads these |
| `--page-size`, `--page-width`, `--page-leading`, `--page-radius`, `--page-line`, `--page-motion`, `--page-scroll-pad` | the size of the text, the width of the page, the leading, the radius of a corner, the width of a line, the duration of a movement (`0s` for a visitor who asked for less motion) and how far a focused element is kept from the top of the window, for a bar that stands there |
| `--page-space-1` to `--page-space-6`, `--page-text-1` to `--page-text-6` | the scale of the room, `.25rem` to `4rem`, and of the type, `.875rem` to a fluid `3.5rem` at the top |
| `--page-weight-title`, `--page-weight-text` | the weight of the headings and of the name of a logo (`700`) and of the text (`400`) |
| `--page-bleed` | `1` on the first child of a component that has the attribute `bleed` and `0` on everything inside that child, set by the reset and nowhere else: what draws a corner of its own leaves it out, as the figure of the picture does with `--image--corner: calc( var(--image-radius) * ( 1 - var(--page-bleed, 0) ) )`, so that the edge of the component is the corner |

**The list is frozen for 2.x:** a token, a knob, an attribute and a variant of a component of the kernel's own features keeps its name and its meaning, and within 2.x a name is only added. A part reads for what stands *on* something - writing, line, accent, a button - the roles, and the palette (`--brand-ground-alt` and the rest of the `-base` names) only for the surface it paints itself where it has no attribute `ground`, as the footer does; the roles turn with a ground, the palette does not. The knobs of `basis` are the ones below, each a token of its component with a ladder of five steps (a step is a length with its unit - `0px`, not `0` - and no keyword such as `larger`; the middle one is what the part sets in `:root`):

| Knob | Token | Ladder |
| --- | --- | --- |
| `button.space` | `--button-space` | `var(--page-space-1)`, `calc( var(--page-space-1) * 1.5 )`, `var(--page-space-2)`, `calc( var(--page-space-2) * 1.5 )`, `var(--page-space-3)` |
| `button.radius` | `--button-radius` | `0px`, `calc( var(--page-radius) / 2 )`, `var(--page-radius)`, `calc( var(--page-radius) * 2 )`, `999px` |
| `button.size` | `--button-size` | `var(--page-text-1)`, `calc( ( var(--page-text-1) + var(--page-text-2) ) / 2 )`, `var(--page-text-2)`, `calc( ( var(--page-text-2) + var(--page-text-3) ) / 2 )`, `var(--page-text-3)` |
| `footer-wrap.space` | `--footer-wrap-space` | `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)`, `var(--page-space-6)`, `calc( var(--page-space-6) * 1.5 )` |
| `footer-col.gap` | `--footer-col-gap` | `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)`, `var(--page-space-6)` |
| `header-wrap.space` | `--header-wrap-space` | `var(--page-space-1)`, `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)` |
| `header-logo.height` | `--header-logo-height` | `1.5rem`, `1.875rem`, `2.25rem`, `2.75rem`, `3.5rem` |
| `header-nav.gap` | `--header-nav-gap` | `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)`, `var(--page-space-6)` |
| `image.radius` | `--image-radius` | `0px`, `calc( var(--page-radius) / 2 )`, `var(--page-radius)`, `calc( var(--page-radius) * 2 )`, `calc( var(--page-radius) * 4 )` |
| `image.height` | `--image-height` | `var(--page-space-5)`, `calc( var(--page-space-6) * .75 )`, `var(--page-space-6)`, `calc( var(--page-space-6) * 1.5 )`, `calc( var(--page-space-6) * 2 )` |
| `section-wrap.space` | `--section-wrap-space` | `var(--page-space-4)`, `var(--page-space-5)`, `var(--page-space-6)`, `calc( var(--page-space-6) * 1.5 )`, `calc( var(--page-space-6) * 2 )` |
| `section-row.gap` | `--section-row-gap` | `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)`, `var(--page-space-6)` |
| `section-col.pad` | `--section-col-pad` | `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)`, `var(--page-space-6)` |
| `section-col.radius` | `--section-col-radius` | `0px`, `calc( var(--page-radius) / 2 )`, `var(--page-radius)`, `calc( var(--page-radius) * 2 )`, `calc( var(--page-radius) * 4 )` |
| `section-title.space` | `--section-title-space` | `var(--page-space-1)`, `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)` |
| `text.size` | `--text-size` | `var(--page-text-1)`, `calc( ( var(--page-text-1) + var(--page-text-2) ) / 2 )`, `var(--page-text-2)`, `calc( ( var(--page-text-2) + var(--page-text-3) ) / 2 )`, `var(--page-text-3)` |
| `text.gap` | `--text-gap` | `var(--page-space-1)`, `var(--page-space-2)`, `var(--page-space-3)`, `var(--page-space-4)`, `var(--page-space-5)` |

**A fixed step is a factor on the knob of the same size.** `gap="0"` to `"4"` of a `[section-row]` and `pad="0"` to `"4"` of a `[section-col]` are 0, a half, one, one and a half and two and a half times the knob (`--section-row-gap`, `--section-col-pad`), and `height="1"` to `"4"` of an `[image]` are a half, one, one and a half and two and a half times `--image-height`; `size="s"` and `"l"` of a `[text]` and of a `[button]` are an eighth under and over the knob (`--text-size`, `--button-size`). `gap="2"` is the default, and `pad="2"` is what `auto` is on a card or a ground - a plain column has no padding at `auto` and one knob of it at `pad="2"`. So a theme that turns a knob turns every fixed step with it, and the steps never change their order, for a knob larger than 0; the large steps of a ladder get large - `pad="4"` at the top of the ladder is 160 px - and whoever writes a ladder measures it at 390 px. The factors are the contract; the tokens that carry them in a part (`--section-row--f`, `--section-row--space`, `--section-col--f`, `--section-col--inset`, `--text--f`, `--button--f`, `--image--corner`, `--image--f`) are not: the name of such a helper has two hyphens after the component, which no knob can have, and a part of a theme does not calculate with them. The other fixed steps follow the page and no knob: `spacer size`, `section-title size`, `section-row width`, `section-wrap height` and `footer-col size`.

**For the author of a theme:** a part should calculate with the tokens of the page and of the knobs - `calc( var(--page-space-4) * 1.5 )` - and not write a fixed value such as `1.5rem` where the page has a token for it; a fixed value beats the fixed steps of the components, which only multiply the knob. This is a recommendation and no check: what a theme writes in its own part is the author's to answer for. `bleed` is a bool, and a component of a feature that gives the attribute another type is refused by `\Nino\Features::readComponents()`; its token is `--page-bleed`.

A project turns them in its `assets/style.css`, `:root { --brand-color-1: #0a7d5a; }`, which is a later layer and so overrules `Nino.css` whatever the selector says. A part writes a token without a fallback, `var( --page-space-2 )`: the token is there, or the kernel is older than the feature's floor. The one exception is `--page-bleed`, which is set on the first child of a component with `bleed` only and is read as `var( --page-bleed, 0 )`.

**The layers of the style bundle.** The first rule of every stylesheet bundle of the site is `@layer nino.reset, nino.components, nino.theme, project;` (`\Nino\Modules\Assets::LAYERS`), and the bundle puts each file in one of the four: `Nino.css` is `nino.reset` and holds that layer itself; the standard part of every group and the stylesheet of every feature and module are `nino.components`; the project's `assets/theme.css` - a file of its own, none is delivered - is `nino.theme`; every other file of its `assets/`, `style.css` among them, is `project`. A rule in a later layer overrules a rule in an earlier one whatever the selectors say: a rule of the project overrules a part with a plain selector (`.button { border-radius: 0 }`), and a part needs no heavier selector to stand against the reset. A part never writes a layer, the bundle does ([Assets](features.md#assets)), and `partProblems()` refuses `@layer` in one.

**The standard part** of a group is its `part/part.css`: the default look of its components, which `\Nino\Features::registerParts()` puts into the style bundle of the site in the layer `nino.components` ([Assets](features.md#assets)). Before it does, it asks the hook `/nino/components/parts` with `[ 'group' => 'section', 'feature' => 'basis', 'css' => '/_nino/Nino/Modules/Basis/groups/section/part/part.css' ]` (the path of a feature below `features/` is `/features/<Name>/groups/...`): a callback that answers another `css` replaces the part, and `''` leaves the group without one. The other part is a stylesheet that is there below a feature, written as a path of the bundle: `/features/<Name>/...css` or `/_nino/Nino/Modules/<Name>/...css`; a file of the project, of the private tree or of no feature is none, and an answer that is none of these is said once and the standard part stands. The hook is asked behind the `init()` of the modules, which is where a callback is registered. A standard part keeps the **rule of mixing**. It is read as css - comments, strings with their escapes, blocks - and not searched for patterns: it has no backslash outside a string (no name, selector or function is spelt with an escape); each of its selectors has a class of the components of its group - the name, or the name, a hyphen and more - or is `:root`, so that a selector of a type, `*`, an id, a class of another component or an attribute selector on `class` stands only behind such a class; it reads only `--brand-...`, `--page-...` and the tokens of its own components (`--<component>-...`), and sets only the latter, in `:root` as well; and it has no `!important` (whatever stands between the `!` and the word), no `@import`, `@layer`, `@namespace`, `@charset` or `@property`, and nothing that loads from outside or takes a picture from another element: `url()`, `image()`, `image-set()`, `-webkit-image-set()`, `src()`, `element()` or `cross-fade()`, in any case. A part that cannot be read - a string or a comment that is never closed, braces that do not balance, nesting deeper than 16 - is refused as well, and so is one longer than 262 144 bytes. `\Nino\Components::partProblems( $css, $components )` answers what a part does against it, one sentence each, and the kernel asks it when it reads the folders: a part that breaks the rule is said and left out of the bundle, while the group and its components are the feature's all the same. A feature's own suite asks it of every part it ships.

**What a request reads.** The checked schemas of a feature are kept in `private/data/cache/register/<key>.php`, written by `\Nino\Filesystem::putFileContent()` from the result of the reading and nothing else: php that holds the data of the schemas and groups, the kernel version it was written for and a stamp - the size and the modification time of each `component.php`, `template.tpl`, `group.php`, `part.css` and `demo.tpl`, with the lists of what the folders hold. A request that finds the stamp as it was registers from the file and **includes no `component.php` and no `group.php`**; where a file changed, came or went, or the kernel is another, it reads the folders again and writes the file. The comparison has the limit every cache of the kernel has: a file of the same size written within the second of the one before it, by a tool that does not set the time, is not seen until it changes again or the folder is emptied. Nothing is stored while anything of the folders is refused - a schema, a group or a standard part that breaks the rule of mixing - so that a feature that is wrong is read, and said, at every request until it is mended; a part the check refuses switches the cache off for that feature. The file is never the truth and never a reason for a message: a folder that cannot be written, a file that is none of the shape or has no right to be there, is a feature that is read again. The folder is emptied by `\Nino\Html::dropTrees()`, is no part of a backup, and `'/nino/markup/cache' => false` turns it off like the trees of the templates: the folders are read at every request and nothing is written. The kernel version is part of what the file says, and in a kernel taken from git it does not change: where the form that `validate()` answers changes, a file written before keeps being taken until the folder is emptied - the limit the trees have. Measured on one machine (medians of 400): 20 schemas in 6 groups register from the file in 0.09 ms with opcache and in 0.4 ms without, since php then parses the file at every request; reading, checking and registering them from the folders takes 0.5 to 0.9 ms with opcache and 1.0 ms without.

**What is included.** `component.php` and `group.php` are php of the feature, like its `feature.php`, and are included for a feature that is on: by the request that reads the folders and by the Features panel for the feature it lists as on - never for a feature that is off. They are read inside a closure, what they print is dropped, what they throw is the reason the component is refused. Nothing in the folders is a link: a folder, a `component.php`, a `template.tpl`, a `group.php`, a `part.css` or a `demo.tpl` whose real path is not the one it is written under is refused, since what is read goes into the register, the bundle that is served and the demo that a panel draws. A folder of `components/` whose name is no slug is refused with nothing of it included; a template named by the schema is a file of the folder of the feature (`validate()` reads no path that leaves it). `\Nino\Features::readComponents( $dir, $key )` answers `[ 'components', 'groups', 'rejected' ]` (a group is `[ 'label', 'knobs', 'part', 'demo' ]`) with a sentence for everything it refused (or the sentence alone where the folders cannot be read), and `componentProblems( $appData, $key )` those sentences, and the ones for what another feature owns, for the Features panel.

**The feature Basis** is the feature that comes with the kernel: `_nino/Nino/Modules/Basis/` holds twenty components in six groups, each group with its standard part, and its folders are the pattern to copy. It is **off** until it is switched on, in the Features panel, which lists `\Nino\Modules\Basis` in `/nino/modules` (`\Nino\Features::activate( $appData, 'basis' )`) and puts the three words of its menus into the texts of the project, or by the setup wizard, whose base unit says `'features' => [ 'basis' ]` and writes the starter pages in its components ([The Look](setup.md#the-look)). Where it is off its names - `section-wrap`, `text`, `button` and the rest - are names nobody registered: they draw nothing, nor what stands between their tags, and the log says so once. The table of its components is in [the feature Basis](features.md#the-feature-basis); a card is a `section-col` with `variant="card"`.

### Blocks

A block is a piece of a page that a tool puts in: a hero, a picture beside a text, the header of every page. It is data and has no code - a folder `blocks/<key>/` with `block.json` (the name and the description in every language, the `category` - `intro`, `content`, `trust`, `offer`, `action`, `contact` or `frame` -, the `rank`, the `images` and, optional, the `variants`), `block.tpl` (one call of a root with an `id`, made of components) and `text/<locale>.json`, one file for each language, `en_US` among them; a slot of `block.json` may have a picture of its own, `images/<slot>.svg`, `.webp`, `.png` or `.jpg` (at most 512 KB and 4 096 pixels on a side, a svg only where `\Nino\Images::cleanSvg()` takes it). `\Nino\Blocks` reads the folders `blocks/` of every feature that is on - the feature Basis brings eight ([the feature Basis](features.md#the-feature-basis)) -, of the project (`private/blocks/`), of the installed packages of blocks (`private/packages/blocks/<key>/<block>/`, see [Packages](features.md#packages); the origin is the key of the package) and of the roots a callback of the hook `/nino/blocks` adds, in the order Basis, project, packages, other features, hook: the first block of a key wins. The hook is handed a list and answers it with `[ 'dir' => '/absolute/path/to/a/folder/of/blocks', 'origin' => 'theme-name', 'label' => 'Theme name' ]` added - the `label`, a text or a locale => text map of at most 80 characters, is optional and is what a tool shows for the origin: where a theme or a package of blocks puts its own. A block of the project is checked as a block of the kernel is.

```php
$blocks = \Nino\Blocks::all( $appData );          // key => key, origin, label (of the origin), name and description (locale => text), category, rank, wrap, locales, features, variants, alternatives, images, pictures (slot => 'images/<file>')
$block  = \Nino\Blocks::get( $appData, 'hero-full' ); // the same, with tree, source, id, texts (locale => name => text) and slots
$why    = \Nino\Blocks::rejected( $appData );     // '<origin>/<folder>' => the reason, for what is not listed
$name   = \Nino\Blocks::variantOf( $block, $tree ); // the name of the variant a tree of the block has, null where it is its own
$tree   = \Nino\Blocks::setVariant( $block, $tree, 'image-right' ); // the tree with that variant set
$check  = \Nino\Blocks::validate( $appData, 'my-block', [ 'json' => $json, 'tpl' => $tpl, 'texts' => [ 'de_DE' => $de, 'en_US' => $en ], 'pictures' => [ 'background.png' => $bytes ] ] ); // the block, or the reason as a string
```

**What a block may be.** `validate()` is the rule for every block, the files of a folder and what a tool is about to write alike. A block that fails is left out of `all()`, said once in the log and named by `rejected()`.

- The folder is a slug (lower case letters, digits, hyphens), no other block has the key - the first root wins: Basis, the project, the other features by their keys, then the hook, so that a block of the project is not put out by a feature that is switched on later and a block that is put out says by whom in `rejected()` - and it is a folder of its own, no link. It holds `block.json`, `block.tpl` and `text/` and nothing else, and `text/` holds `<xx_XX>.json` and nothing else. A name that ends in `.php` (or `.phtml`, `.phar`, `.inc`) is code and refuses the block wherever it stands; a name that begins with a dot is the file system's and is not read.
- An address a link takes - written into the template or the text of a source a link reads - is one a link is drawn for: a page of the site (`page:/…`), a path, an anchor, `https:`, `http:`, `mailto:` or `tel:`. `\Nino\Components::address()` answers it for any tool that stores a link target.
- The json is an object of at most five levels (`block.json` six) and 16 KB, the keys of `block.json` are the six above, the name and the description have the languages of the texts and no other, and each language has the same names of texts. A text is words: no control character but a line break, no `<` or `>` and no `[[`, at most 600 characters.
- The template is at most 16 KB, valid utf-8, read by `\Nino\Markup` with no note and written back byte for byte, and it is one call - `section-wrap`, or for the category `frame` a `header-wrap` or a `footer-wrap` - whose `id` is at most 32 characters. It has no `[[`: a block reads no key of the project's. `\Nino\Components::check()` finds nothing against it - no name nobody registered - but a name that a feature which is off brings as `components/<name>/` is a block that needs that feature: `all()` lists it with the keys in `missing` (`[]` for a block that can be put in), `get()` answers what `all()` does and none of the tree, source, texts and slots, and nothing of it is drawn or put in -, no call that has no schema, no `[html]`, no html between the calls, no component at a place that is not its own, no value outside what its attribute takes - but a variant that the active mixture of the components does not have: the block names it (`variants`) and a tool says what it appears as.
- **Rule B6:** the id of a call inside the block begins with the id of the root and a hyphen (`cards-a` in `cards`). A tool that puts the block in makes the id of the root free and gives the ids inside the same new beginning.
- A source is `~name`, a picture of `\Nino\Images::PLACEHOLDERS` or the type of elements of `\Nino\Elements::PLACEHOLDERS` - below which `.field` is a field of its model - and nothing else: no text of the project, no slot of a page. The value of an attribute that begins like a source is held to the same. A **frame** (the root a `header-wrap` or a `footer-wrap`, the category `frame`) may read more, in the first word of a call and nowhere else: a text key of the text store in the form a person gives one (`\Nino\Text::isGrammarKey()`, at most 200 bytes) under any of its four roots - `/template/`, `/project/`, `/feature/` and `/module/` - where the call's first word is a text, and the picture `/logo` or `/logo/dark` of a `header-logo` or a `footer-logo`. So the standard footer of a site - `[footer-logo /logo]` and `[text /project/company/general/description]` - is a block. Only the form and the root are checked: a text that is not there is a text that is not there, drawn as a page draws it, since a package comes from another site. The block carries the key and never the text: no file of it, nor an export, holds what the site says, and what is drawn is what a page draws for the call, cleaned alike. **The responsibility is the developer's**: a frame can show ANY text of the site - the texts of the pages, one behind a password or not yet published among them, the mailbox the forms go to, the words of the features - on every page, and a frame of a package of another site can too, from the moment it is put in, and already in its tile and its preview in the workbench. A textfill is not where a secret is kept, and whoever puts a block on a site answers for what it shows. `\Nino\Blocks::reads( $block )` answers the keys a block reads - texts and logo, each once, sorted; always `[]` for a block of the pages - for a tool to show before a block is put in (give it what `get()` answers). The prefixes are two constants, `Blocks::FRAME_TEXT_ALLOWED` and `FRAME_TEXT_EXCEPT` (empty); a prefix in the second closes it again, one line. An attribute, a loop, an element, `[html]`, a key of the configuration, the accounts, the session, `/nino`, `/_nino` and `/_admin`, and every source of a block of the pages stay refused. A tool that makes such a block the frame of a site keeps the source a source: the template is put in as it is written.
- Each `~name` has a text in every language, and each text is read. The name a text has in its file is the name of the source for a call whose nearest id above is the id of the root, and for a call below another id the part of that id behind the id of the root and a hyphen, a hyphen and the name: `~title` below `cards-a` in a block whose root is `cards` is `a-title`; `get()` lists each in `slots` with its `scope` (`a`). Two sources that come to one name refuse the block.
- A `~name` that is a picture - an `image` attribute, the source of `[image]` - has a slot in `images`: `{ "placeholder": "/nino/placeholder/landscape", "width": 1920, "height": 1080, "label": { "de_DE": "...", "en_US": "..." } }`. The block brings no picture of its own, only a placeholder of the kernel; a tool gives the slot of the page the placeholder as its file.

`features` of a block is worked out from the register - the feature each component of its template belongs to - and not stated by it. A name that no feature brings, off or on, is refused. A block that is listed with `missing` is checked for what can be checked without the register of the component that is not there: its ids, its sources and its words; what its texts and slots are read by is not looked at.

**Variants.** `block.json` may name `variants`, at most eight (`\Nino\Blocks::MAX_VARIANTS`): a name (a word), a `label` in the languages of the block, and a `set`. A variant is a named set of attributes on the components the template already has, with no markup of its own, so that switching between them keeps the texts, the pictures and the children. The `set` maps a place to attributes and their values. A place is `root` for the root of the template, or the indices from the root down with the blanks between the calls counted (`1.1.1`), no leading zero; it names a call, and it is counted in the form a block is put into a page in - the root as `\Nino\Markup::layout()` sets it out, each call on a line of its own - whatever blanks `block.tpl` has between its calls, which is the position the Builder writes. An attribute is one of the schema of the component there that the register writes as a class (a `choice` or a `bool` that is not `'class' => false`): a look, and never a text, a picture, a link, an id, the `class` or the `variant`, nor an attribute that goes into the markup or changes what a component does (`href`, `alt`, `target`, `tag`, `menu`). Its value is a word the schema takes - the register decides, `\Nino\Components::check()` reads the tree with the set in it as it reads a stored tree, so a responsive value such as `off m:on` is as valid as it is for the attribute - and no source. A variant changes how components stand and never what they say: no markup, no source and no link target comes into a page by it, and a block with a set that would is refused with a sentence that begins `block.json, variants, "<name>":`. An empty set is the template as it is. `all()` and `get()` list them as `alternatives` (`[]` for none), in the order of the file, each `[ 'label' => locale => text, 'set' => place => attribute => value ]`, and with them `defaults`, place => attribute => `[ type, default ]` of what the variants set. `variants` of the same lists is the other thing, the variants of the components the template calls.

```json
"variants": {
	"image-left":  { "label": { "de_DE": "Bild links",  "en_US": "Picture left" },  "set": {} },
	"image-right": { "label": { "de_DE": "Bild rechts", "en_US": "Picture right" }, "set": { "1": { "reverse": "on" } } }
}
```

`variantOf()`, `variantsFit()` and `setVariant()` are pure functions of the block as `get()` answers it - never one a request brings - and of a tree, a list of nodes as `\Nino\Markup::parse()` has them whose first call is the root of the block (a block that stands in a page as one node is handed as `[ $node ]`). A tree is built like the block where every place any variant names is a call of the name the template has there and each level on the way has the same run of calls and blanks: `variantsFit()` says so, and a tool offers the choice of a variant only for such a tree. `variantOf()` names the first variant whose set holds at all its places and with which each attribute that another variant sets has the value of the template, and `null` for a tree that is none of them, one that is not built like the block and one with no call. Values are compared as the register reads them: an attribute that is not written has the default of its schema, so `reverse="off"` written out is `reverse` not written. `setVariant()` puts every attribute that any variant sets back to the value of the template and then sets the variant, and a value that is the default of its attribute is not written, the attribute is taken out. The texts, the sources, the children and the attributes no variant names stay as they are, an attribute that stands in the call is set where it stands and a new one is put at its end, a place that is not the template's is passed over, and an unknown name gives the tree back. None of them throws for a block or a tree of another form.

**What is kept.** The blocks that were read are kept in `private/data/cache/blocks.php` - the blocks, the reasons of those that were refused, the kernel's version, the version of the register of components (`\Nino\Components::version()`), the components the features that are off bring, and the size and time of every file below the roots - and read again where any of it differs. A request that finds the file as it was reads and checks no file of a block and says nothing; it still lists the folders and looks at the size and time of each file, which is what the stamp is made of (about 2 ms for fifty blocks), and a file that changes to the same size within the same second is not seen until it changes again. The log hears of a block that is refused once, when it is read. `'/nino/markup/cache' => false` turns the file off; `\Nino\Html::dropTrees()`, which the page cache calls, removes it; a backup does not take it.

### Pages

A page is a route that draws a template of `templates/page-*.tpl`, with a name and a title in every language of the site, an address for each language and a place in the menus. `\Nino\Pages` makes one in one call and lists them; the Routes panel of the workbench calls the same function, so a tool that makes pages has the rules of the panel and no copy of them. Neither function knows an account: the caller checks the right of its own (`\Nino\Admin\Admin::guardPerm()` in a panel) before it calls.

```php
$made = \Nino\Pages::put( $appData, [
	'uri'      => '/site-news',                                  // the Element-URI the texts of the page are filed under
	'httpUris' => [ 'de_DE' => '/neuigkeiten', '' => '/news' ],   // a language => its address, '' => the address of the others
	'template' => 'page-news',
	'text'     => [ 'de_DE' => [ 'name' => 'Neuigkeiten', 'title' => 'Neuigkeiten' ], 'en_US' => [ 'name' => 'News', 'title' => 'News', 'description' => '' ] ],
	'navs'     => [ 'main' ],
	'source'   => '[template /templates/html-header]',            // optional: the new template, which is written with the page
] );

if( isset( $made['error'] ) === true )
	\Nino\Http::fail( $request, $made['error']['status'], $made['error']['message'], $made['error']['code'], $made['error']['params'], $made['error']['field'] );

$pages = \Nino\Pages::all( $appData ); // [ [ uri, template, names, addresses, locales, navs, runtime ], ... ]
```

`put()` makes the template (where there is a `source`), the name, the title and the address of each language in its text file, the blacklist entry of the address and, as the last of the writes, the routes of the languages and the entry in the menus - all under the one lock of `config.php`, which is held from the first check to the write of the routes. A write that fails gives back the ones before it: each text file has the bytes it had, or is no file where it was none, and the template file is removed, so nothing is left of a page that is refused - not even when the write of `config.php` itself fails, which is an answer like any other. It makes a new page, or saves the page named by `originalUri`, which keeps what its first route carries and the form does not edit. An address must be a plain path that is no reserved one (`/_admin`, `/_nino` and the like, `\Nino\Http::isReservedUri()`) and that no other route answers on; the Element-URI is the page's own; there is a name and a title in every language of the site; the template is one of `templates/page-*.tpl` that is there.

With `source` the template is made, in the same step and as the one file of that name: the name is `page-` and words of lower case letters and digits joined by hyphens, at most `\Nino\Pages::MAX_NAME` long, and none the kernel draws by itself (`\Nino\Pages::reserved()`: the maintenance page and the pages of the legal module); the file is not there yet - a template that is there is never overwritten, nor one that differs from it in case only -, and the source is at most `\Nino\Modules\Template::MAX_BYTES`. **The source is not checked.** It is written as the bytes it is and never run or read by the call; it is drawn when the page is, with the rules of every template, so free html and a call without a schema in it are drawn for every visitor. Whoever hands over a source that an account wrote checks it first with the rules of the right that account has (a feature: `\Nino\Components::check()` and its own rule) and checks the right itself. Where a later write fails, the file is removed.

The answer of a page that was written is `[ 'uri', 'routes', 'template', 'created' ]`, that of a refusal `[ 'error' => [ 'status', 'message', 'code', 'params', 'field' ] ]` - the arguments of `\Nino\Http::fail()`, so that a panel makes a sentence of the code in the language of the account; the refusals of a `source` have codes with words as well (`routes_invalid_template`, `routes_reserved_template`, `routes_template_exists`, `routes_template_too_large`). `all()` is one entry for each page (Element-URI), however many routes it has, so several entries may name one template: its template (`''` where the body picks the file at runtime), its name - the title where it has none - and its address for each language, the languages it has an address in and the menus it is in; the pages that a module routes at runtime and no `config.php` has, such as the imprint and the privacy policy, stand behind the stored ones with `runtime` set.

#### Removing and copying a page

`\Nino\Pages::remove( $appData, $template )` takes a page away whole, and `\Nino\Pages::duplicate( $appData, $template, $page )` makes a new page whose template is a copy of another. Both answer like `put()` - the result, or `[ 'error' => [ status, message, code, params, field ] ]` with nothing written - and know no account.

```php
$gone = \Nino\Pages::remove( $appData, 'page-news' );
// [ 'template' => 'page-news', 'uri' => '/site-news', 'routes' => [ 'GET://news' ], 'texts' => 6, 'slots' => [ '/template/page-news/hero/image' ],
//   'files' => [ 'template/page-news/hero/image.800x600.webp' ], 'kept' => [], 'linked' => [], 'remnant' => false ]

$asked = \Nino\Pages::uses( $appData, 'page-news' );  // the same refusals, or [ template, uri, routes, remnant, kept, linked, removable ] - nothing is written

$copy = \Nino\Pages::duplicate( $appData, 'page-news', [
	'uri'      => '/site-news-2',
	'httpUris' => [ '' => '/news-2' ],
	'template' => 'page-news-2',                                  // the name of the new template
	'text'     => [ 'de_DE' => [ 'name' => 'Neuigkeiten 2', 'title' => 'Neuigkeiten 2' ], 'en_US' => [ 'name' => 'News 2', 'title' => 'News 2' ] ],
	'navs'     => [],
] );
// the answer of put(), and 'copied' => [ 'texts' => 6, 'slots' => 1, 'files' => 1 ]
```

**What `remove()` takes.** The routes of the page in every language, which are its entries in every menu - a membership is the route's own `navs`, and the page's entry in `/nino/html/navroutes` goes with them -; the template `templates/<template>.tpl`; the keys `/template/<template>/...` of every language and of `global.php`, with their format and limit in `meta.php` and their entry in the blacklist; the four details of the page, `/_nino/webpage<uri>/{name,title,description,uri}`, unless a page that is routed at runtime has the same Element-URI; the slots `/template/<template>/...` of `/nino/html/images`; and the files of those slots that are named for the slot - the uri of the slot without its first slash, a dot and the rest, the name `\Nino\Images::process()` gives - and that no remaining slot names (`\Nino\Images::deleteUnlessShared()`). Every file below `images/template/<template>/`, the folder of the page, that no remaining slot names goes with them. A file under another name outside that folder, or one with `..` or a control character in it, is nobody's to delete here and stays. Everything of the page's that **something else reads** stays - a text or a slot of the namespace and the details under `/_nino/webpage<uri>/`, with their format, slot and file: a fill, a source or the value of an attribute of a call in another template or in the body of a route (read as `\Nino\Markup` reads them: a comment and the text between tags read nothing), or the value of a text of any language or of `global.php` that holds the key, found by a pattern. The answer lists them under `kept` as `[ 'key' => ..., 'by' => [ who reads it ] ]`, and the page goes all the same: nothing refuses it for a reader, and a text that stays reads what it read. What **links** to the page - with `page:<uri>` in a call of a template or route or as the value of a text, or by reading the address of the page, the detail `/_nino/webpage<uri>/uri`, which stays as a text and leads to a page that is gone - holds nothing: a link with `page:<uri>` to a page that is gone is drawn without a target, and the address that was read answers 404. The answer lists it under `linked` as `[ 'by' => ..., 'key' => the text that holds the link, '' for a template or route ]`, so that a caller can say so before it asks. `\Nino\Pages::uses()` is that question without the act: it answers what `remove()` would keep and who links, and the refusals below, and writes nothing. What is kept stays under the name of the page: `\Nino\Pages::taken( $appData, $name )` answers whether a template, a text, a meta entry, a slot or a picture file stands under a name, and a tool that writes the words of a new page before it calls `put()` asks it first and takes another name, so that it never writes over what another page still reads.

**Which page may go.** The page is named by its template, which is `page-` and lower case words (`\Nino\Pages::TEMPLATE`: `mail-*`, `html-*` and every other template are never taken, not even as a leftover). A template that no stored page draws and that has nothing of a page is `routes_unknown_page` (404); one that has something is a leftover (below). It is refused with 409, and nothing written, when it is

- the page at the address `/` (`routes_remove_home`) or the page of the status 404 (`routes_remove_not_found`, the route `GET://404` or the Element-URI `/404`);
- a page that a module or a feature routes at runtime and no `config.php` has, or a template the kernel draws itself (`\Nino\Pages::reserved()`): the imprint and the privacy policy, the maintenance page (`routes_remove_runtime`);
- a template that more than one page draws (`routes_remove_shared`, the pages are named): the call takes one page, and the template would be gone from the others - a caller removes the other pages' routes first;
- a template that another template, another route or a link to its file names in a `[template /templates/<name>]` call (`routes_remove_included`): that page would draw nothing where this one stood;
- a template that is a link to another file, or has a twin that differs in case only (`routes_template_alias`).

A name that is no page template is `routes_invalid_template` (400). **A leftover** is what a cut-off run leaves: a template with no page that draws it, or words, slots or picture files below `images/template/<template>/` under the name of a template with no route. It is taken like a page, with `remnant => true` and no routes, together with every file below its picture folder that no remaining slot names (the folders go with them), and with the details under `/_nino/webpage/<name>/` and `/_nino/webpage/site-<name>/` (`<name>` is the template without `page-`) that no route answers for - it cannot know the Element-URI of a page that has no route. After it the project is as it was before the page, and the name is free. `duplicate()` refuses a name that has any of it - a text, a meta entry, a slot or a picture file (`routes_namespace_taken`, 409) -, and `put()` with a source refuses a picture file below the folder of the name that no slot names (the same code): a tool that makes a page writes its words and slots before it calls `put()`, so these are no leftover for it.

**All of it or none.** The lock of `config.php` is held from the first check to the last write, with no gap: the write of `config.php` is made under the held lock and does not let it go, so no other `put()`, `remove()` or `duplicate()` runs in between. Every `\Nino\Filesystem::lockFile()` drops what the request had read of the file, so that a writer that locks and then reads - the Routes and Navigation panels do - reads the disk and not an older copy. The order is the one that keeps the site whole at every step: the routes, the slots and the page's memberships go in the one write of `config.php`, so no visitor is sent to a page that is half gone; then the texts, `meta.php` and the blacklist, file by file; the template is the last of the writes that can be given back, so no route ever points at a template that is not there; the files of the pictures go last, when nothing can fail any more that would need them. A step that fails gives every file back byte for byte, the last first: the bytes it had are written again where the file still holds what this call wrote, and where another writer changed it meanwhile only what this call took out is put in again, where it stood, and what they took out stays out. The answer of a failed write is a 500 that names the file. Where a file cannot be given back - its lock cannot be had, the write fails - the answer is a 500 with the code `routes_unrestored` and `error.unrestored`, the list of the files that were not put back, and the log says the same; `put()` and `duplicate()` answer so as well.

**The hook.** After the page is gone and the lock is let go, `/nino/pages/removed` is fired once, with the answer of the call as its argument (`template`, `uri`, `routes`, `texts`, `slots`, `files`, `kept`, `linked`, `remnant`). A feature that keeps state of its own for a page removes it there - the Builder, its last state below `data/builder`. It is a message and no veto: the page is gone when a callback runs, and what a callback returns changes nothing of the answer. A callback that throws is caught and logged and the callbacks after it run (a `\Nino\Failure` is thrown on, as everywhere). A removal that is refused fires nothing.

**What `duplicate()` copies.** It is `put()` with the source of the copy, in the same step and under the same lock and giving-back, so every refusal of `put()` holds for it - the address, the Element-URI, the name of the new template, which is held to the rules of the name of any template `put()` makes. `$page['template']` is the new name; `source` and `originalUri` in `$page` are not read. The copy has

- the source of the template, read with `\Nino\Markup`: every textfill, source and attribute value of a call that begins with a key of the original's namespace, `/template/<template>/...`, is written for the new one, and so are the four details of the original page, exactly - `/_nino/webpage<uri>/{name,title,description,uri}`, not those of a page below it - and a link `page:<uri>` to it, where one page draws the original: the copy shows and links to itself. The same holds for a fill or a `page:` link in a text that is copied. Free text, comments and the attributes of html elements are not read as keys and stay as they are, and a source the parser does not write back as it read it is refused (`routes_source_unreadable`). The display name in `<!-- nino:template-name ... -->` is the new page's name in the language of the site. The ids stay: a `~name` is the key of the template it stands in, so the copy reads its own words with no change, and an id is the scope of one template and nobody's else;
- every text of the namespace in every language that has a file, and in `global.php`, as the text of the copy, with the format and the limit it has in `meta.php`;
- every slot of the namespace as a slot of the copy, with its label, its size and its alt texts, and its file as a file of its own under the name of the new slot (the old name with the path of the slot replaced, the same bytes) - the two pages share no file, so removing either never touches the other. A slot whose file cannot be read has none in the copy; a slot that names a picture of the kernel (a reference that begins with `/`) names it again.

It copies nothing that is not in the namespace: a source that points at `/template/common`, at `/project`, at the slot of another page or at elements (`[elements /services]`) stays a reference, and the copy shows the same. The name, title, description, addresses and menus are the caller's in `$page`. A link in free html that names the original by its address stays as it was written, and the entries of the blacklist and a feature's last state of the original (the Builder's) are not copied. A namespace that has a text, a meta entry, a slot or any picture file below `images/template/<new name>/` already is `routes_namespace_taken` (409): what is there is nobody's to overwrite. A feature's own state of a removed page (the Builder's) is not counted by the kernel - the feature removes it at `/nino/pages/removed`. A template that is a link or has a case twin is refused as the source and as the name (`routes_template_alias`).

The codes are `routes_unknown_page`, `routes_remove_home`, `routes_remove_not_found`, `routes_remove_runtime`, `routes_remove_shared`, `routes_remove_included`, `routes_template_alias`, `routes_namespace_taken`, `routes_source_unreadable` and `routes_unrestored`; their words are in the text files of the Routes module, `/_admin/error/<code>`. `kept` and `linked` have none: the panel makes the sentence.

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

A list with no element is nothing. `empty="<name>"` on `[elements]` makes it the template `private/templates/<name>.tpl`, rendered as a `[template]` is: `[elements /meetups empty="meetups-none"]...[/elements]`. The name is slugs with a slash between them, below `/templates`; any other value renders nothing and is a warning. A kernel that does not know the attribute leaves the list empty. It is the attribute of the catalogue's `[search-results]`, and a stack that loops elements does not take it.

Where the language of the page has no text of an element - the element has no version in that language, or a field of it is empty - the block shows the text of another language instead: `\Nino\Elements::getElementWithFallback( $appData, $uri, $locale )` answers the element with each field of the model that is per language (`'locale' => true`) and missing or empty in `$locale` taken from the native language, then the other available languages in their order, then the others the type file holds a bucket of, and `'.lang'` beside it - the tag of the language that gave the first field taken, `''` where the language had it all; `false` where no language has the element. `'.langs'` stands beside `'.lang'` and says it for each field: `field => tag` of the language that gave it - `[ 'summary' => 'de-DE' ]` for an English page whose element has its summary in German only - and `[]` where the language had it all. `'.lang'` is what it was, the tag of the first field taken, and `[[.lang]]` is what it was; `.langs` is an array and no fill, so it is in no `[[token]]`. A block that shows the title in the language of the page and the summary in another one - the card of a collection - puts the `lang` attribute on the summary alone, from `$element['.langs']['summary']`. `\Nino\Modules\Elements::queryLoop( ..., true )` hands each element on with it. `[elements]` draws every element through it, and `[[.lang]]` is that tag as an attribute with its space in front - ` lang="de-DE"` - or nothing: put it on the element that shows the text, so that a screen reader reads a German text with a German voice on an English page. The query lists the elements it lists - an element that exists in German only is listed on the English page, and shown - and a field of the model that no language has a value for is replaced by nothing, so that the block never shows `[[summary]]`. A sort is the order of what is drawn. `[element]` and the stacks of the Components module read the language they are asked for, as before.

`query` filters - `key=value`, several joined with `&`, `%` as a wildcard at either end. `sort` orders by a field: `sort="title"` ascending, `sort="-date"` descending, `sort="category,-date"` by the first and, where that is equal, the second; two numbers compare as numbers, everything else as a text of the language the list is asked for - the language of the page, or the `locale` of the loop - where the php extension intl is there (`Collator`: a letter with an umlaut stands with its letter, `Ärzte` before `Zebra`, the case of a letter is no difference, "Item 9" comes before "Item 10") and naturally, without regard to case, by the bytes where it is not ("Item 9" before "Item 10", `Ärzte` after `Zebra`). The sort of `\Nino\Elements::queryElements()` and of `\Nino\Elements::sortElements( $elements, $sort, $locale )` takes the language the same way (`''` sorts by the bytes; `\Nino\Elements::sortLocale()` is the language a call for `$locale` is sorted in, the current one for `*`); `[elements]` sorts what it draws, the fallback included, in the language of the loop. The values of `[elementvalues]` (`sort="value"`, the default) are sorted the same way, in the language of the call (`locale`, else the page), so that a bar of filter buttons and the cards beside it have one order, and two values that are numbers compare as numbers there as well. The sort keys are made once for each element and compared as bytes, which is the order of `Collator::compare()`; a text that is no UTF-8 is sorted by its text with what is wrong replaced. An element without the field comes last, and what compares equal keeps its order. `sort="random"` shuffles the list - the whole value, never combined with fields (among several it is a field named `random`) - and the Cache module keeps a rendered page, so the order stays fixed for the life of a cache entry. `offset` and `limit` cut a window out of the sorted list. A `callback` runs between: it sees the sorted list and may drop or reorder, and `offset` and `limit` apply to what it let through - a page is a page of that. In PHP the same is `\Nino\Elements::queryElements( $appData, $typeUri, $query, $locale, $return, $options )` with `sort`, `offset` and `limit` under `$options`, and `\Nino\Elements::sortElements( $elements, $sort )` orders a list you already hold.

Normal field values are HTML-encoded. A field released in the model with `html => true` may only contain a limited, sanitized amount of inline HTML: `strong`, `em`, `span`, `code` and `a`. `blocks => true` beside it adds paragraphs and lists (`p`, `ul`, `ol`, `li`) and the `<br>` inside them; `breaks => true` on a field *without* `html` keeps the line breaks of its plain text, escaped, as `<br>`. All of it is `\Nino\Html::fieldValue( $value, $field )`, which `[element]` and `[elements]` call and which a feature that draws a field itself should call instead of keeping a copy; `\Nino\Html::sanitizeHtml( $html, $format )` takes `inline`, `lines` or `blocks` - and, with a third argument `true`, keeps the `[` and `]` the source wrote and writes every one that an entity decoded to as `&#91;` or `&#93;`, which is how `[html]` treats the template's markup that the fills have put an editor's text into - and `\Nino\Html::detectFormat()` reads the widest format a value holds. Give the element that holds a `blocks` value the class `nino-richtext`: the reset at the top of `Nino.css` takes every paragraph gap and list marker away, and the class restores them. The protection deliberately takes place in the Elements module: Element placeholders are local data of the respective block and not part of the global textfill space.

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

A stylesheet bundle of the site puts each file in the block of its layer (`@layer nino.theme { ... }`), moves an `@import` of a file to the front and repairs a file whose braces do not balance inside its layer; a bundle written below `/_admin/` is no such bundle. Only if the target name ends with `.min` is it additionally minified. The cache considers path, size, and modification time of the source files.

A feature does not write that array. It names its files under `assets` in its manifest, and `\Nino\Features::registerAssets()` adds every `.css` to `/.cache/style.css` and every `.js` to `/.cache/script.js` while the feature is active - see [Assets](features.md#assets). What a feature or a module adds with `\Nino\Html::addAsset()` stands behind the kernel's files and in front of the project's own files below `/assets/`, whatever order the files come in, so that `assets/theme.css` and `assets/style.css` are the last of the stylesheet bundle. Which rule wins is a matter of layers: the bundle begins with `@layer nino.reset, nino.components, nino.theme, project;`, `Nino.css` stands in the first two, a feature or a module in `nino.components`, `assets/theme.css` in `nino.theme` and the other files of `assets/` in `project`, so that a rule of the theme or of the project overrules a rule of a feature whatever its selector (see [Assets](features.md#assets)).

Assets deliberately **do not** go through the full HTML+ engine. Only the two secure directory fills are replaced: `[[/nino/dir]]` and `[[/nino/public]]`. This prevents editorial textfills or shortcodes from inadvertently generating executable CSS or JavaScript code. Both are needed - the script of the form module falls back to `[[/nino/dir]]/.form` for a form that carries no action of its own, and a stylesheet addresses a file under `public/`, a webfont for one, through `[[/nino/public]]`.

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

The kernel classes live one per file, in `_nino/Nino/<Class>/<Class>.php`, and are loaded on first use; the docblocks of each state its public tasks. `\Nino\Markup` reads HTML+ into a tree and writes a tree back ([HTML+ - the notation](#html---the-notation)), and `\Nino\Components` is the register of the components and draws that tree ([The Register of Components](#the-register-of-components)). `\Nino\Format` writes a number or a date for the language of the page and is the registry of the formatters a component's renderer may call (see [Formatting a value](#formatting-a-value)). Use the public methods instead of the internal implementations starting with `_`. This keeps project code decoupled from details like cache invalidation, file format, and session rotation. `\Nino\Feature` is the class a feature calls for what every feature needs - the template its markup lives in, the class attribute, a flag, a counter per client, a slug, the widening of the Content-Security-Policy - and its docblocks are the reference; [the toolset](features.md#the-toolset) of the feature manual lists it with the rest of what a feature calls. The browser has the same for a feature's scripts, in `Nino.js` and `Nino.admin.js` (the docblocks are the reference there too): `Nino.adminUi.panel()` is the lifecycle of a workbench panel, with `levels()`, `hint()`, `textField()`, `button()`, `el()` and `localeSelect()` beside it; and `Nino.slug()` and `Nino.clipboard()` serve a site script and a panel alike.

### Reading and writing an archive

`\Nino\Archive` reads a `.tar.gz` that somebody else made - a package from the catalogue, a family of fonts - in memory: nothing is written to a disk, no `PharData` and no extension but zlib is asked for, and nothing is used that only one C library has (`Archive::supported()` says whether the functions of zlib that inflate are there). The answer is the files, or the code of what is wrong.

```php
$files = \Nino\Archive::read( $bytes, 'inter', 40, 4194304, 16777216, '~\.(?:woff2|json|txt)$~' );

if( is_string( $files ) === true )
	// one of the constants of \Nino\Archive: UNSUPPORTED, GZIP, BOMB, TRAILING, TRUNCATED, HEADER, TYPE, PAX, PATH, DEPTH, FOLDER, DUPLICATE, NAME, COUNT, FILE_SIZE, TOTAL_SIZE
	return;

// $files: [ 'font.json' => '...', '400.woff2' => '...', 'sub/x.txt' => '...' ] - the path below the folder, the bytes, in the order of the archive
```

The arguments are the archive; the one folder it holds at its top, as it is named in it (`null` for the one it has, whatever it is called); the most entries it may hold, folders counted; the most bytes of one file; the most bytes of all files together; and which files it may hold, a pattern for `preg_match()` or a `Closure` that is asked `( string $path ): bool` of each path below the folder - without it every file whose segments are plain names (`[A-Za-z0-9][A-Za-z0-9._-]*`, at most 81 characters) is allowed. The limits are the caller's: what a feature, a theme and a font may hold is not the same. An archive holds exactly one folder at its top, and below it plain files and plain folders at most `Archive::DEPTH_MAX` (6) segments deep; the folders themselves are no result, an empty one is not heard of.

What is refused is what a reader of another make could read as something else: a link of either kind, a device and any other entry that is no file and no folder (the long names of the GNU tar among them), a pax header with a key that is not on the list of what says nothing of a name or a size - the times, the owner, a comment, `LIBARCHIVE.*` and `SCHILY.xattr.*` and the attributes of the file system are skipped and never read: the name of an entry is the one of its header; `path`, `linkpath`, `size`, `GNU.sparse.*` and any key nobody knows are refused, a header whose checksum does not hold, a path that is absolute, climbs, has a backslash, a control character or a NUL byte with bytes behind it, a name twice - also in the spelling that differs in the case alone, which the file system of a Mac folds together - and a file below a file, a folder that has a size, bytes behind the end of the tar and bytes behind the end of the gzip stream (a second stream among them). A stream that unpacks to more than the limits allow is stopped while it is inflated, in steps of 4 KiB of the stream, and not read to its end.

Three things for the caller. A `$allow` pattern has to be anchored: `'/\.css/'` lets `x.css.php` through, `'/\.css$/D'` does not. The keys of the answer are strings: a name that is nothing but digits is refused (`path`), since PHP would make it an integer. And the answer holds the archive twice while it is read, the stream unpacked and the files cut from it, and the inflating stops at the limits plus a reserve of about 6 KiB for each entry the caller allows: choose `$entries` and `$total` for what the memory of the host can bear, not for what an honest archive needs.

An archive that is accepted has one folder at its top (`tar czf x.tgz folder/`, not `-C folder .`), a path of at most 100 bytes in the name field of its header or one that the ustar prefix carries (the long-name records of the GNU tar and of pax are refused), and none of the files a Mac adds - `._*` and `.DS_Store` are names the reader refuses, so a package is built with `COPYFILE_DISABLE=1 tar --format=ustar` or by the build script of the catalogue.

`\Nino\Archive::write()` makes such an archive, for a tool that exports a package - a block, a package of blocks - and wants it read again by `read()`: `write( string $folder, array $files, int $entries, int $each, int $total, int $time = 0, string|\Closure|null $allow = null ): string|array`. `$files` is path below the folder => bytes, the limits and `$allow` are those of `read()`, and `$time` is the time of every entry (0 where it is left out). The answer is the bytes of the `.tar.gz`, or an array `[ 'code' => one of the constants of the class, 'path' => the path that is refused ]` - the codes of `read()` for the same faults (where two rules catch one fault the other code may answer: a path that is too deep and not plain is `depth` here and `path` there), and `Archive::LONG` for a path that does not fit the name and the prefix fields of a ustar header (a long-name record is never written). Everything `read()` refuses is refused here, for the folders it writes as for the files, and nothing is corrected: a path that is not of plain segments, one deeper than `DEPTH_MAX`, a name twice - in the case alone as well, or a file that is a folder of another -, content that is no string, a name of digits alone (a folder below the top as well), a file the field of a header cannot measure (`header`), and a number or a size over a limit - `$entries` counts the folder itself, so that `0` refuses even the empty archive (`count`); a `Closure` of `$allow` that throws is not caught. `$folder` is held to more than `read()` holds it to: a plain name as the paths below it are, or the answer is `FOLDER`. The answer is the archive where `is_string()` says so, else the array. The tar is ustar, written in memory with no `PharData` and no shell: the folder first, the files in the order of their paths with each folder written before its first file, the rights 0644 and 0755, owner 0 and no names, the type flags `0` and `5` and nothing else. The gzip stream is made by `gzencode()` at level 9, only where `supported()` says so (`UNSUPPORTED` otherwise), and its header says no time, no name and no level, the system Unix: the same arguments give the same bytes where the zlib is the same one, which is what a catalogue that compares the checksum of an archive relies on.

`read()` takes a last argument by reference, `$refused`, in which a refusal says the entry it is about - the path below the folder, the whole path of an entry in another folder, a name with each character that is not printable ASCII as a question mark - and which is `''` for a good archive and for a refusal that is about no entry; the answer is the code, as it was.

`\Nino\Http::download( $request, $bytes, $filename, $contentType )` answers bytes as a file to save: status 200, `Content-Disposition: attachment` under a name made harmless (no path, no quote, no control character, no character that turns text round - U+202E, the line and paragraph separators; cut at 150 bytes with its ending kept; `filename*` in utf-8 where it is not ASCII), `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store` and `Content-Length`, and a body that is not drawn - a csv or a page of html that is a document is sent as it is, whatever `[[...]]` or tag it holds. A type that is no `type/subtype` is `application/octet-stream`.

```php
$bytes = \Nino\Archive::write( 'my-blocks', $files, 400, 262144, 4194304 );

if( is_array( $bytes ) === true )
	return $bytes['code']. ': '. $bytes['path'];
```

### Formatting a value

`\Nino\Format` writes a number or a date the way the language of the page writes it, and is the registry of the **formatters** a component's renderer may call by name. The kernel calls none itself: it is the API of a feature whose components format a field - a price, a date - and let the editor choose how. A formatter is `( array &$appData, mixed $value ): ?string`: it gets the **raw** value - the float of a `double` field, the integer of an `integer` field, the string of a `date`, a text key's text with its entities read and its tags dropped - and answers plain text, or `null` for a value it cannot read, which the caller then shows as it is stored. `\Nino\Format::apply( $appData, $name, $value )` runs the formatter of that name in the language of the page and answers `null` for a name nobody registered, as it does for a value the formatter cannot read; the caller escapes the answer with `\Nino\Components::escape()` and never writes it as html. A template never names a callable: only what `add()` registered is ever called. A formatter that answers anything else than a string or `null` is an `E_USER_WARNING`, once for each formatter in a request, and the value shows as it is; one that throws is not caught. A built-in formatter throws nothing and raises nothing. Add one from the `init()` of a module or a feature, before the first render:

```php
\Nino\Format::add( $appData, 'nights', static function( array &$appData, mixed $value ): ?string {
	$number = \Nino\Format::number( $appData, $value );
	return $number === null ? null : $number. ( (string) $value === '1' ? ' Nacht' : ' Nächte' );
}, [ 'en_US' => 'Nights', 'de_DE' => 'Nächte' ] );
```

The name is a slug, the label a string or a `locale => string` map; `add()` with the name of a built-in replaces it at its place. `registered()` lists them. `options()` is the options method of an attribute named `as` - the empty word, which shows the value as it is stored, and every formatter by its name - for a component that registers with `addComponent()`; the schema of a component in a feature's folders names a method of the feature's own class, which may answer `\Nino\Format::options()`. `words()` answers the words of the language and `amount()` writes a number with the separators of the language.

The seven built in write what the table says, in German for a language whose id begins with `de` and in American English for every other, with the no-break space (U+00A0) before `€`, `%` and `AM`/`PM`. A value is read only if it is exactly what the editor of the type stores: an `int`, a finite `float` below 1e15 (an amount of money below 1e13, so that its cents are its own), a string of digits with a point (`1234.50` - not `12,50`, ` 12.5` or `1e5`), a date as `Y-m-d`, `Y-m-d\TH:i`, `Y-m-d\TH:i:s` (or with a space for the `T`) or a time as `H:i` and `H:i:s`; a date php would roll over (`2026-02-30`), a year below 1 or above 9999, an offset and a timestamp are not read. A date is a wall-clock time written as it stands: no zone is converted and the clock is not read.

| Value | Formatter | `de_DE` | `en_US` |
| --- | --- | --- | --- |
| `1234.5` | `number` | `1.234,5` | `1,234.5` |
| `1234.5` | `currency` | `1.234,50 €` | `€1,234.50` |
| `-1234.567` | `currency` | `-1.234,57 €` | `-€1,234.57` |
| `19`, `7.5` | `percent` (the value is the percent number) | `19 %`, `7,5 %` | `19%`, `7.5%` |
| `'2026-11-11'` | `date`, `date-long` | `11.11.2026`, `11. November 2026` | `11/11/2026`, `November 11, 2026` |
| `'2026-03-05T09:05'` | `datetime`, `time` | `05.03.2026, 09:05`, `09:05` | `3/5/2026, 9:05 AM`, `9:05 AM` |
| `'2026-11-11'` | `time` | not read | not read |
| `'12,50'`, `true`, `NAN`, `'2026-02-30'` | any | shown as stored | shown as stored |

`number` writes as many decimals as the value has, up to six and as far as the fifteen digits of a double reach, and no zero at their end; `currency` always two, with the sign in front of the whole and none for an amount that rounds to nothing, in the currency of the configuration key `/nino/format/currency` - a code of three capital letters, `EUR` by default and where the value is none - with the symbol of `EUR`, `USD`, `GBP`, `CHF` and `JPY` and the code itself for any other. Set it in `config.php`; a page that writes a second currency adds a formatter of its own that calls `\Nino\Format::amount()`. There is no `intl` and no `setlocale()`: the same page reads the same on every host.

A language overrides each word by a text of its own, read only where the language has one - not the text of another language that the fills would fall back to, and not an empty one: the keys `/template/common/format/decimal`, `thousands`, `currency`, `percent`, `date`, `date-long`, `datetime`, `time`, `am` and `pm`, and `/template/common/month/january` to `december`. The patterns of `currency`, `percent`, `date`, `date-long`, `datetime` and `time` are text with the placeholders `{amount}`, `{symbol}`, `{day}`, `{day2}`, `{month}`, `{month2}`, `{monthname}`, `{year}`, `{hour}`, `{hour2}`, `{hour12}`, `{minute2}` and `{ampm}`. The kernel delivers none of these keys; the Text Keys tab creates them. A language other than German with no words of its own gets the English ones.

---

## Integrated Modules

Modules are activated in `/nino/modules`. The order of the array is relevant if several of them register callbacks of the same priority. The modules Nino ships are the directories of `_nino/Nino/Modules/` (`\Nino\Modules\*`): the always-on ones, which `\Nino\AppData::DEFAULTS` lists, and `Form`, `Legal`, `Localepicker`, `Maintenance` and `Navigation`, which the setup wizard applies on every run and a project may still switch off by hand. `_nino/` stays replaceable wholesale. Everything beyond is a **feature**: an installable package under `features/<Name>/` with a `feature.php` manifest, switched on in the workbench's Features panel and bringing its panel the same way. A checkout ships none; they come from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features), and each documents itself in the `manual` of its manifest. See [Developing a feature](features.md), [Panels of the Workbench](#panels-of-the-workbench) and the [autoload layout](recipes/runtime-module.md#autoload-layout).

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

The field types are `\Nino\Form::TYPES` and the names a field may not take `\Nino\Form::RESERVED`; `\Nino\Form` documents what each type posts and how `normalize()` and `problems()` treat a definition. The `label` of a field and the `subject` of a form are texts: the fills of the site in them are put in and nothing else is read, so a shortcode in one is the text it is.

Only `[[fields]]` carries every field into a mail - the whole submission as a table, whatever the form asks for. The `mail-owner` and `mail-user` templates a new installation starts from carry it; a project that installed before copies it into its own templates by hand. A template that fills only `[[name]]`, `[[email]]`, `[[subject]]` (the field `cat`) and `[[message]]`, as the earlier ones did, mails nothing of a field of another name.

Two more keys sit beside them. `/nino/form/retention` is how many months of submissions stay on disk (1 to 60, `\Nino\Form::RETENTION_MONTHS` without one), and `/nino/form/store` set to `false` means the mail goes out and nothing is written at all - a site that answers its inquiries and keeps no copy has less to protect, and the Submissions panel then stays empty because there is nothing to show. Two caps bound what a stream of submissions from many addresses leaves on disk. `/nino/form/month-cap` is how many entries one month's file takes (1 to 10000, `\Nino\Form::MONTH_CAP_DEFAULT` without one): a submission over it is still taken and mailed, and not recorded, so a full month never closes the form - only where the owner's mail does not go out either, so that nothing would have the inquiry, it is answered 429. `/nino/form/hour-cap` is how many submissions the whole site takes in an hour (1 to 10000, `\Nino\Form::HOUR_CAP_DEFAULT` without one), which protects the owner's mailbox: a submission over it is answered 429 like one the mail cap refuses, sends no mail and is not recorded. The Submissions panel says which cap is reached. The retention times the month cap is the most the panel loads at once and has to fit the memory limit - at 128M keep it at 9000 or below.

A project that changes the retention or the confirmation mail changes what its privacy policy has to say: the section "Contact form" of the privacy policy (type `privacy`, element `contact-form`) names the period the submissions are kept and the confirmation the visitor gets. Whoever sets `/nino/form/retention` or `/nino/form/store`, or sends no confirmation, adapts that section to it - see [Legal](#legal).

The markup is the project's own: `page-contact.tpl` carries a hand-written `<form class="nino-form">` that the script of the module (`Modules/Form/assets/form.js`) drives, and a feature's form is driven the same way ([A form of a feature](#a-form-of-a-feature)). A project with several forms writes each one's markup the same way; the script sets `novalidate` on every form it drives and so takes the check of the required fields and of the address - it says what is wrong in the language of the page, in the message of the form and with `aria-invalid` on the field, where the browser's own bubble speaks the language of the browser - while a visitor without the script keeps the check of the browser, which is why `page-contact.tpl` does not write it; the form of `page-contact.tpl` carries `autocomplete` on the two fields a browser can fill. The script checks an address only where the field holds one, so an address field the form does not require may stay empty, and it sends a form once: while the request is out the button is disabled and a further submit does nothing, and the button is given back when the request ends without a delivered message. When the answer comes the keyboard goes to the message of the form - the first `<p>` of it, which gets `tabindex="-1"` and, where the markup says none, `role="status"` - since the button that was focused is disabled while the request is out and a delivered message locks every field; a form the script refuses itself puts it on the first field it refused. The catalogue's Forms feature draws one from its definition. Nino.css draws the fields, their labels - the size of the field's text, the smaller for a label that floats - the status line, the note under the fields (`nino-form-note`, .85 of the field's text), the checkbox and the radio group of such a form from the custom properties `--forms-*`, whose defaults stand in `:where(:root)` and `:where(*)` in the form block of the file - the size, the space, the corners and the line of a field, where a label stands, the colour of the lines and of the labels, the case of the labels - and a theme moves them on `:root`; the catalogue's Design feature has the form as an object of the page, with four sets for it. A feature that writes a form uses two classes of its own beside the field classes, `.nino-forms-check` for a checkbox beside its words and `.nino-forms-group` for the group of radio buttons under one question, and the same block draws both.

**Refusing a submission** needs no callback name of its own. A module or feature that wants to turn one away registers on the same route callback ahead of the module - `\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', ..., 1 )` - and leaves a status behind; `\Nino\Form::handle()` sees a status that is not 200 and returns without sending or writing anything. `\Nino\Csrf` refuses the same way one step earlier - on the global `/nino/http/response`, at priority 1, which runs before any route's callback - which is why the endpoint has no csrf check of its own:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ): void {

	if( yourGuardRefuses( $appData ) === true )
		$request['/nino/http/response']['statusCode'] = 418;

}, 1 );
```

418 rather than a status of its own for every refusal: the shared `.nino-form` script shows one generic message for anything that is not 200 or 400, so a bot never learns which check it tripped.

The endpoint's own answers follow the same rule. A submission the per-address mail cap refuses is a `429`, neither mailed nor recorded - the visitor sees the generic message and tries again later, and a throttled flood does not become unthrottled disk growth. A submission whose owner mail no transport took is answered `500` - the generic message on the page, so the visitor knows it did not arrive - and, where the project keeps a copy, still recorded, since the inquiry is in the Submissions panel; the Dashboard says why. Only the owner's mail counts: a visitor confirmation that could not be delivered, with the owner's mail out, answers `200`.

#### A form of a feature

A feature that writes a form of its own gets the behaviour of the contact form - the check of the entries in the language of the page, the mark and the caret on a refused field, one request at a time, the answer in the form's message and the lock after a delivered one - by meeting an agreement, and writes no JavaScript. The contact form and the newsletter signup meet the same one.

The markup:

```html
<form class="nino-form" action="[[/nino/dir]]/meetups" method="post" data-form-texts="/feature/meetups/signup/">
	[csrf]
	<input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
	<label for="meetups-name">...</label>
	<input type="text" id="meetups-name" name="name" class="nino-form-input" required>
	<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">...</button>
	<p class="nino-form-message"></p>
</form>
```

- `class="nino-form"` hands the form to the script. `action` is the endpoint it posts to, below the project's directory, and what a visitor without JavaScript posts to as well. A form with no `action` posts to `/.form`.
- `data-form-texts` is the prefix, ending in `/`, of the five lines the script says: `required` (a required field is empty), `email` (an address is not one, and a 400 on a form that asks for an address and holds none of the typed fields below), `invalid` (a url, number, date, select, radio or checkbox the browser or the server refuses, and a 400 on a form that asks for no address), `success` and `error`. A key the feature did not write says nothing, and the words of the contact form are never a fallback. Without the attribute the prefix is the contact form's `/module/form/info/`. The words are fills of the feature, so a feature publishes the prefix to its page in `init()`: `\Nino\Modules\Jstext::publish( $appData, [ '/feature/meetups/signup/' ] )`. A prefix starts and ends with a slash with a name between; `publish()` refuses any other with a warning and publishes nothing for it.
- The script sets `novalidate` on the form itself, so that it says what is wrong in the language of the page; a visitor without the script gets the check of the browser for `required`, `type="email"` and the other attributes. A form that still carries `novalidate` in its markup keeps working - without the script it posts what the browser lets through, and the route refuses the rest.
- A hidden field named `id` that carries the id of the form on its page (`<input type="hidden" name="id" value="meetups-signup">`) is what a visitor without the script is sent back to.
- The first `<p>` of the form is its message, a live region the script writes into, and the first `<button>` is the one it holds back while a request is out. Every `input`, `textarea` and `select` is posted by its `name`, hidden fields and the honeypot included; the script adds the token of the page when the form has no `[csrf]`.

The answer of the route is its status and nothing else, the body is not read: **200** is `success`, **400** the entries were refused (`invalid` where the form holds a url, number, date, select, radio or checkbox or asks for no address, else `email`), and every other status is `error` - one generic line, so that a bot never learns which check it tripped. A redirect is followed, so a route that answers a sign-up with a redirect to a page that answers 200 is a 200 to the script; a route that answers a refusal by drawing its page again with a status, as the guide's Meetups does, is that status. Without JavaScript the browser posts the form natively. The script marks every request it makes with the header `X-Nino-Form: 1` (`\Nino\Form::SCRIPT_HEADER`; `Nino.http.sendRequest()` takes request headers as its sixth argument), and a post without it comes from a browser that runs no script, which `\Nino\Form::answer( $appData, $request, $outcome )` answers with a `303` back to the page the form came from instead of a status nobody reads: `/.form` calls it itself, and a feature that serves a form of its own calls it from its route callback once it has set the status and the body for the script - with the marker it leaves the response as the callback made it. `$outcome` is one of `success`, `invalid`, `email` and `error` (`\Nino\Form::OUTCOMES`), the words of the lines of `data-form-texts` that say how a post ended, and another word is `error`. `Location` is always a path of this site: the path and query of the `Referer` where its scheme, host and port are the ones the request was made to, else the start page; the query of the page stays but for a parameter `form`, which carries the outcome (`?form=success`), and the fragment is the value of the field `id` where it is the shape of an id (a letter, then letters, digits, `-` and `_`, at most 64). A `Referer` with a control character, a space, a backslash, credentials or a path outside the project's directory is not trusted and sends the visitor to the start page. The refusal of the csrf guard stays a `403` and `blocked()` is respected as before; `/.form` answers the honeypot, like every other status that is neither 200 nor 400, with `error`, the word the script says for it, so that a bot learns no more from the redirect than from the status; a 400 is `invalid` or `email` by the same rule as the script. A page reads the word back with `\Nino\Form::outcome( $request )`: one of the four, or `''` where the address names none, and draws the message from the same lines of `data-form-texts` - the kernel itself draws nothing of it.

What the feature does itself is everything that protects its endpoint, and the script and the kernel give it no more than the field and the token: the route checks its entries itself, because a request need not come from the script. **The callback of the route runs after the kernel has refused a post without a valid token** - `\Nino\Csrf` sets the status 403 and a flag and nothing more - so the callback returns at once when `\Nino\Form::blocked( $request )` is true, and writes nothing; without that line a forged post is stored. A route none of whose callbacks has asked `\Nino\Form::blocked()` and whose callbacks answered such a post with a success or a redirect is overruled: the kernel answers the guard's 403 and writes a warning to the log with the method, the route and the registrants of its callbacks. One callback of the route asking is enough. Only the answer is replaced; what a callback did directly - a header or cookie sent at once, the session, a file, output - stays, so this does not undo a write, it makes the missing line visible. The honeypot is the route's to read - a filled field is a bot, answered 418 - and so is a time trap; the kernel has no helper for either, `\Nino\Form::handle()` reads the honeypot `location` of `/.form` alone and nothing in the kernel keeps a time trap. Counting tries is `\Nino\Feature::count()`, `spent()` and `forget()`; the month and hour caps of `/.form` are the contact form's and do not reach a route of a feature. `\Nino\Form::posted()` reads the entries as plain, shortened text.

A template a project copied keeps working: the class `nino-newsletter-form` of the Newsletter feature's signup names the prefix `/feature/newsletter/info/` and, with no `action`, the endpoint `/.newsletter` - the one class the script knows - and where a form carries it and `data-form-texts` both, the attribute wins; `page-contact.tpl` and a contact form copied from it need no attribute.

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

**Placeholders.** A text names a fact of the website by a placeholder instead of writing it: `#/project/company/contact/email#`. Only keys below `\Nino\Modules\Legal::PREFIXES` (`/project/company/` and `/project/website/general/`) in the four-segment form of the key grammar are replaced, only in text and only after the text was made safe, so a value can be nothing but text: tags and entities are removed, the rest is escaped, and every bracket becomes an entity. A key without a value stays as it is written, so that it shows. The list of prefixes is a constant of the kernel and deliberately no setting. The Elements panel offers them at the text field of the two types: a "[ ]" at its name, for an account that may change the field, opens the keys `\Nino\Text::fills()` lists for the form `placeholder` - the same two prefixes - and a click copies the placeholder (`\Nino\Modules\Legal::PLACEHOLDER_FIELDS` names the fields).

**The legal navigation.** The wizard creates a third navigation, `legal`, with the two pages as its first entries, and the footer of the base frames outputs it with `[navigation nav="legal" id="legal__nav"][/navigation]`. The wizard creates it only while the project does not have one; a second run leaves the entries as the editors set them. Its entries are Element-URIs: a page that has one route per language is one entry.

**The name of a navigation.** A navigation may have a name for the workbench - "Main menu" for `main` - in the key `/nino/html/navnames` of `config.php`, `[ 'main' => 'Main menu' ]`, a key of its own beside the list of keys in `/nino/html/navs`, which stays a plain list. An entry of it that is no text is read as no name and kept when another navigation is saved. A navigation without a name has no entry and the key is absent while none has one; a template never reads it, `[navigation nav="main"]` asks for the key. `\Nino\Pages::navNames()` answers the names of the registered navigations for a form that lists them, and the Navigations panel keeps the name with the key it renames and removes it with the navigation it deletes.

**Features add their own sections.** A feature that processes personal data names a file in the `elements` key of its install unit (see [Developing a feature](features.md#the-install-unit)). Activating the feature adds the sections of that file with `\Nino\Elements::seed()` - add-only, never replacing an element or a language version that exists. Deactivating the feature does not delete them: the Dashboard and the dialog say that the text stays. A section deleted for good - in every language - is remembered in `/nino/elements/removed` in `config.php` (by the listener on `/nino/elements/committed`), so that the next update of the feature does not bring it back; creating the element again by hand removes the entry. Deleting a whole type in the Elements panel leaves `removed[<type>] = []` in the same place; `\Nino\Elements::seed()` does not read that entry, so a deleted section type of the legal texts or of a feature is put in place again by the next seed, unless the caller reads that entry first and leaves a deleted type alone.

**A section can be changed.** `\Nino\Modules\Legal::SECTION`, `/nino/legal/section`, is the callback a section's text goes through before it is drawn: it receives `[ 'type', 'id', 'html' ]` and may add to `html`. The Consent feature puts its settings button into its own section with it. What a listener leaves in `html` is finished html: nobody reads it again, so a call or a fill in it is the text it is, and a listener that has html+ of its own to draw draws it first with `\Nino\Html::renderSource()`.

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

`Runtime` registers a common handler for PHP errors and exceptions. Deliberately triggered notices, warnings, and deprecation messages can be logged without ending the request, and so can a deprecation the engine raises - it says a future PHP will do something differently, not that this request went wrong. Exceptions, every other engine error, and `\Nino\Runtime::fail( $message )` lead to a `500` response. A month's log keeps its newest 1000 entries: the file is one array, rewritten whole on every entry, so a template raising a notice per view would otherwise grow it with the traffic.

`\Nino\Runtime::fail( string $message ): never` is the one way for code that cannot go on to end the request: it throws `\Nino\Failure`, a final `\Error`, which the handler logs with the file and line of the call of `fail()` and answers with a `500`. Being an `\Error`, it passes a `catch ( \Exception )`. Code that catches `\Throwable` around something that may call a callback, a module's method or a write of `config.php` catches `\Nino\Failure` first and throws it again. Where the handler is not registered yet - `NINO_PRIVATE_DIR` and `NINO_CONFIG_DIR` are checked first - the uncaught `Failure` is php's own fatal error with the message.

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

Nino starts sessions in strict mode. Session cookies are `HttpOnly`, use `SameSite=Lax`, and are set via HTTPS as `Secure`. Behind a TLS-terminating proxy, the Secure flag can be enforced with `/nino/session/force-secure-cookie`. A successful login renews the session ID and the CSRF token. A module that has proven who is asking by other means than a password - a remembered device whose token only the account's owner was given - opens the session with `\Nino\Auth::resumeUser()`, which is the end of a login after the password check: the same rotation, the end of a previous sign-in, a fresh session token, the login callback and the time of the login, for an active account only; it asks for no password, counts no tries and is not held back by the cooldown of failed logins, so the caller has to have checked what lets the account in.

A session is started when something writes to it, not on every request: minting a CSRF token (so: rendering a form, which includes serving one from the page cache), signing in, a visitor picking a language, and opening a page of a route that names a language other than the visitor's own. A page that does none of those answers with no session, no `PHPSESSID` cookie and no session file - a refused post, a language the site does not have and a cookie that names no session included - which matters both for the disk a crawler fills and for what a cookie banner has to declare. `\Nino\Runtime::startSession()` is the call that starts one; every write goes through it, and it answers `false` on the CLI, where there is no session to start. It answers `false` as well where PHP refuses to start one at all - an unusable `session.save_path`, say: the reason PHP gave goes into the error log and the request is answered without a session, rather than becoming a 500 nothing explains.

Authentication is additionally protected by:

- a dummy password hash against measurable differences with unknown users,
- failure limits per account and IP - the IP being the one `\Nino\Http::getClientIp()` resolves, so a site behind a reverse proxy needs `/nino/http/proxies` set or the whole internet shares one bucket,
- automatic rehashing of outdated password hashes,
- random session tokens with limited runtime,
- and the ability to revoke all sessions of a user.

### The Visitor's Address

`\Nino\Http::getClientIp( $appData )` answers who the request is from. That is the TCP peer (`REMOTE_ADDR`) - unless the peer is a reverse proxy listed under `/nino/http/proxies`, in which case it is the rightmost `X-Forwarded-For` hop that is not itself one of those proxies.

The key matters because every per-IP rule in the site reads this one value: the login cooldown's IP bucket, `\Nino\Mail`'s send cap (`/nino/mail/cap`: calls an hour, 5 by default, 0 for none), a form's rate limit, and the address a session is listed under in the workbench. (A session is not pinned to an address - its token is the key it lives under.) Behind a proxy without the key set, `REMOTE_ADDR` is the proxy for every single visitor, so all of them share one bucket - five contact-form submissions from anyone then hold up every visitor's mail for the rest of the window. The login cooldown's IP bucket, the mail cap and a form's rate limit count an IPv6 client by its /64 and an IPv4-mapped address as the IPv4 address it carries, through `\Nino\Http::clientBucket( $ip )`, so a visitor who writes any address of their network is one entry; the address a session is listed under stays whole. `\Nino\Feature::count()`, `spent()` and `forget()` apply it to a key that is the bare address, as `getClientIp()` answers it; a key built around an address (`clientBucket( 'visitor|2001:db8::1' )` stays whole) takes `clientBucket()` for its address part first.

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
- `Jstext` transfers the text of a key - its fills filled in, its entities decoded once - JSON-encoded, its brackets escaped so that no shortcode can be typed into it, and CSP-bound into JavaScript, which writes it with `textContent`, never as markup.
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

`\Nino\Modules\Navigation::routeLines()` fires it once per call, after the lines of a menu are collected and ordered and before they are returned - for a menu without lines too, then with `[]`. Every `[navigation nav="..."]` asks for its lines that way, as does `entries()`, so the hook is where a module decides which pages a menu offers to the visitor at hand. `Legal` asks `routeLines()` too, for every language, menu and page it checks in the workbench, so that its check sees the menu a visitor sees.

The argument is `[ 'nav' => 'main', 'lines' => [ '/:Start', '/members:Members' ] ]`: the key of the menu and its lines, `<uri>:<title>`, in menu order. A callback changes `lines` in place, or returns an array that replaces the argument. `routeLines()` answers the strings of `lines`, in their order and numbered from zero; a callback that sets `lines` to anything but an array, removes it or returns a value that is not an array leaves a menu with no lines. The lines an author wrote between the tags of `[navigation]` are not in the argument and are drawn as written; what a callback leaves in `lines` is drawn as text, with a colon or without one. The title in a line is plain text, its quotes and brackets as typed, and is escaped once where the menu draws it.

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/navigation/lines', static function( array &$appData, mixed &$args ): void {
    if( ( $args['nav'] ?? '' ) !== 'main' || \Project\Members::isSignedIn( $appData ) )
        return;

    $args['lines'] = array_values( array_filter( $args['lines'], static fn( mixed $line ): bool => is_string( $line ) === true && str_starts_with( $line, '/members:' ) === false ) );
} );
```

### `/nino/blocks`

`\Nino\Blocks::roots()` fires it each time the blocks are read, with a list that holds the folders of the features and of the project: a callback adds `[ 'dir' => '/absolute/path/to/a/folder/of/blocks', 'origin' => 'theme-name', 'label' => 'Theme name' ]` - an absolute path, an origin of lower case letters, digits and hyphens, and optionally a `label` for people, a text or a locale => text map - and answers the list. A label that is none is said once and the origin is read in its place; the label of Basis and of the other features is the name of the feature, the one of the project is `\Nino\Blocks::PROJECT`. An entry of another shape is said once in the log and left out. What lies below a root is read and checked as the blocks of the project are ([Blocks](#blocks)).

### `/nino/http/request`

`Http::request()` fires it once per request, after the request is composed and the response is seeded with the default headers and before the request is put on the stack that `Http::getRequest()` reads. The argument is the whole `$request`, with `/nino/http/request` and `/nino/http/response` in it. A callback that changes `uri` in both - a module that maps the path of a request by its host, say - changes it for routing and for everything that reads the request later, such as the current page of a navigation and the `return` field of a form, because `getRequest()` answers the copy taken after the last callback ran. A callback therefore reads the request it was given and not `getRequest()`, which has not seen it yet.

### `/nino/html/absolute-url`

`\Nino\Html::absoluteUrl()` fires it once per call, after the address is composed and before it is returned, for a website without an address too. The argument is `[ 'path' => '/page', 'url' => 'https://example.com/page' ]`: the path as it was given and the answer it would otherwise be, `''` where the website has no address. A callback sets `url` to give the path an address of its own, eg. another host for the pages of a module, and leaves `path` as it is; the string in `url` after the last callback is the answer, and anything else is `''`. Without a callback the answer is the one the website has.

### `/nino/elements/<type>/insert` and `/nino/elements/<type>/update`

`\Nino\Elements::insertElement()` and `updateElement()` fire them under the lock of the type file, after the values are placed in the type file and before it is written. The argument is the type file, which a callback may change; a callback returns `false` to refuse the write, and nothing is written. While it runs, `\Nino\Elements::writing( $appData )` answers the write it is called for - operation, type, uri, previous uri, language, the keys written, the element before and as it will stand - and `\Nino\Elements::refuse( $appData, $field, $sentence )` refuses it and names the field, which the Elements panel shows at the field. A write a callback makes has its own `writing()` and its own refusals while it runs, and the callback is told its own once it is over. The two calls, the keys of `writing()` and what the panel answers are in the [element types recipe](recipes/element-types.md#refusing-a-write-and-saying-which-field).

### The page cache and the clock

`\Nino\Modules\Cache::limit( array &$appData, int $until ): void` says, while a page renders, that the page is good until a moment - a unix timestamp. A shortcode whose output depends on the clock calls it with the moment its output stops being true, and the page the cache stores gets the earlier of that moment and the configured lifetime (`/nino/cache/ttl`). It never lengthens a lifetime, several calls keep the earliest, and with the cache off or on a page the cache does not store it does nothing. A moment that has passed - or is this very second - means the page is not stored, and its response carries no `X-Nino-Cache` header, as every page the cache does not store. The moment is a runtime value of the request being rendered (`./nino/cache/until`): nothing a visitor sends and nothing the workbench writes reaches it, and it is written to no configuration.

The moment is measured against `time()`, the clock the cache reads to store the page and to judge it fresh. Code that keeps a clock of its own - a zone, a held clock in a test - works out the seconds left on it and passes `time()` plus that, never a moment it took from its own clock:

```php
public static function shortcode( array &$appData, array $args ): string {
    // "Open today" is true until midnight of the project's zone
    $zone     = new \DateTimeZone( 'Europe/Berlin' );
    $now      = new \DateTimeImmutable( 'now', $zone );
    $midnight = $now->modify( 'tomorrow' );
    \Nino\Modules\Cache::limit( $appData, time() + ( $midnight->getTimestamp() - $now->getTimestamp() ) );

    return self::openToday( $appData );
}
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
`/nino/admin/dashboard/head` is the third: the one where a module adds an area to
the head of the Dashboard, answered with data the script of a panel of the same
module draws ([The head of the Dashboard](_admin.md#the-head-of-the-dashboard)).
