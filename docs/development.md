# Nino — Developer Handbook
*[Deutsch](development.de.md)*

**Links:**
[README](../README.md) · [Design Handbook](design.md) · [_editor Handbook](_editor.md) · [_admin Handbook](_admin.md) · [_install Handbook](_install.md) · [_templates-Handbook](_templates.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

## Overview
Nino deliberately does without an installer tool or a purely UI-driven workflow during development. Developers therefore need basic PHP knowledge and advanced HTML/CSS/JavaScript skills to fully build a website. Administering and running a finished project, however, requires no technical know-how at all.

Building a simple website with static and dynamic content needs very little PHP work. Templates are written in HTML+ *(HTML + [[/text/fills]] + [shortcodes])*. The kernel configuration, global and local texts *(textfills)*, and typed data *(elements)* are stored in PHP arrays. These can be created through the graphical `_admin` interface and then maintained via `_editor`. Further assets (CSS/JS/images) are added the classic way.
Advanced module development for extended functionality — integrating an API or a database, for example — requires advanced PHP knowledge.

## Fundamentals
### Philosophy
Nino is built on a handful of principles that show up again and again in its design decisions:
- **No dependencies.** No libraries, no package management, no database drivers. The entire website lives purely in the filesystem.
- **As simple as possible.** No elaborate syntax, no objects, no third-party libraries with foreign concepts. Every decision favors performance and simplicity.
- **All state lives in one array.** Every value of a PHP lifecycle lives in one array (`$appData`), passed by reference into every method/function. No objects — only classes and methods. Similar to the Unix-pipe concept, each class/method does its job and changes/extends `$appData`.
- **Logic and templates are strictly separate. Always.**
  `.php` files never output HTML;
  `.tpl` files never contain PHP logic.
  This separation makes some paths a little longer — but it creates structure and keeps even large projects transparent.

### Basic flow
A PHP lifecycle can be summarized, greatly simplified, in 3 steps:
1. `index.php` has Nino initiate a new `$appData` array based on `config.php`. The init process loads all kernel classes and every module enabled in `config.php`, and lets them register callbacks.
2. The current request is matched in `index.php` against the routes stored in `config.php` (*`METHOD://uri` => ..*). A response with body/header/statusCode is built inside `appData` - the route's template is rendered as the body. Depending on the language, the global and local textfills from `/text` are loaded and substituted. Every `[shortcode]` present in the template *(Template, Elements, etc.)* runs its callback. Modules can modify the response while the kernel callbacks run.
3. `index.php` then has Nino output the response.

### The `$appData` concept
A defining part of Nino's philosophy is the single point of data.
Almost every function in the kernel takes `array &$appData` as its first parameter. There is no DI container, no service objects/singletons, no globals beyond `$appData` itself. This associative array carries every value of the running PHP lifecycle.
The array is first built via `$appData = \Nino\init();` in `index.php`. Based on `config.php`, persistent variables are loaded *(e.g. `[/nino/http/routes]`)*. Temporary variables for the lifecycle start with a dot *(e.g. `[./nino/locales/current]`)*. These can simply be set and expire once the script ends.
As a result:
  There is exactly one `$appData` per request. It is freshly built in `init()` and discarded at the end of the process. Persistent variables must go into `config.php`, text content into global or local text files under `/text`, templates into `/templates`, and data following a recurring pattern into elements under `/elements`.
Access to the running `appData` array happens only through callbacks. Modules register these in their `init()` method.

### Callbacks: the one hook mechanism
Communication and dynamics between Nino and its modules happen only through callback event hooks. Any module can register and run global callbacks.

```php
\Nino\Callbacks::registerCallback( array &$appData, string $name, mixed $callback, int $prio = 5 ): void;
\Nino\Callbacks::doCallbacks( array &$appData, string $name, mixed &$args = null ): mixed;
```

`$name` is just a plain string key — following the convention (`/nino/http/response`,
`/nino/http/response/GET://_editor`, `/nino/html/shortcode/template`). The kernel's own fixed callbacks are registered and run following the same pattern. Any module can use kernel callbacks or register its own.
`$prio` is `0`–`9`, lower runs first.

`doCallbacks()` calls every callback registered under `$name` in priority order, passing `$args` through each one.
This mechanism is used in the kernel for:
route dispatch (`/nino/http/response/...`),
shortcode dispatch (`/nino/html/shortcode/...`),
and can be used as a general project extension point (`/project/my/callback`).

*(All kernel callbacks are listed at the end of these docs.)*

## The request/response lifecycle in detail
Every request runs through the same three top-level calls in the kernel.

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

`$appData` is passed by reference through all three — `init()` builds it,
`request()` mutates it while resolving the response, `output()` emits the response.

### 1. `\Nino\init()` — build `$appData`, initiate modules

```php
$appData = [ './nino/uid' => dirname(__DIR__) ];
AppData::prepare( $appData );      // seed the empty runtime keys
Runtime::init( $appData );         // start/resume the PHP session
Filesystem::init( $appData );      // set up the file read cache
AppData::init( $appData );         // read /config.php, merge into $appData
Locales::init( $appData );         // resolve the request's current locale
Auth::init( $appData );            // resolve the current session user, if any
Modules::callModules( $appData, 'init' );
```

The last line, `Modules::callModules()`, takes `config.php`'s `/nino/modules` array,
loads every class name it contains, and calls `$className::init( &$appData )`.

One exception:
`_editor/Editor.php` and `_admin/Admin.php` are **not** modules in this
list — they are self-contained entry points that call their own
`::init()` explicitly (see "The `_editor`/`_admin` add-ons" below), since a
request to `/_editor` never goes through the main site's
`config.php`/`index.php` at all.

### 2. `\Nino\request()` — resolve the route, run the request/response callbacks

```php
function request( array &$appData, array $request ): array {
    Http::request( $appData, $request );      // parse method/uri/query/header/body,
                                               // pre-seed the response header with the
                                               // security-header defaults (see below)
    Locales::request( $appData, $request );   // fix the locale for this request
    Http::response( $appData, $request );      // resolve the route, THEN run the callbacks
    Html::addFills( $appData, [ /* uri, locale, current user, ... */ ], '*' );
    Html::response( $appData, $request );      // render the body, if it's a string
    return $request;
}
```

`Http::request()` collects and sanitizes every request value and stores it as `$request['/nino/http/request']`. It creates a practically empty `$request['/nino/http/response']` with the framework's default security headers
*(Content-Security-Policy, Strict-Transport-Security, X-Frame-Options, etc.)*, an empty body, and status code 200.
A module can now extend the existing header, change the status code, and fill the body. `Http::output()` sends all of this later.

`Http::response()` is then responsible for the actual routing:

```php
$routeData = self::requestRoute( $appData, $request['/nino/http/request']['uri'], $request['/nino/http/request']['method'] )
    ?? self::requestRoute( $appData, '/404', 'GET' )
    ?? [ '.uri' => '/404', 'statusCode' => 404 ];

// A route's own header fields *extend* the pre-seeded response header
// instead of replacing the whole array
if( isset( $routeData['header'] ) === true )
    $routeData['header'] = array_merge( $request['/nino/http/response']['header'], $routeData['header'] );

$request['/nino/http/response'] = array_merge( $request['/nino/http/response'], $routeData );

Callbacks::doCallbacks( $appData, '/nino/http/response', $request );
Callbacks::doCallbacks( $appData, '/nino/http/response/'. $request['/nino/http/request']['method']. ':/'. $request['/nino/http/response']['uri'], $request );
```

Two callback dispatches happen here - the difference matters in
practice:

- `/nino/http/response` fires for **every single request**, no matter
  which route. *(e.g. `Csrf`)*
- `/nino/http/response/<METHOD>://<uri>` fires only for the
  **exact** route - and note it's keyed on the resolved *response* uri
  (`$request['/nino/http/response']['uri']`), not the raw request uri:
  after routing/locale rewrites the two can differ. This is how a
  special behavior for specific pages or POST/API endpoints is built.
  *(e.g. `Auth` login/logout)*

After these callbacks, `$request['/nino/http/response']['body']` is either a plain HTML string for `\Nino\Html::renderHtml()`, or - for a JSON API response, for example - gets encoded to JSON later in `Http::output()`.

### 3. `\Nino\output()` — send

```php
function output( array &$appData, array $request ): void {
    Http::output( $appData, $request );
}
```

`Http::output()` now encodes a non-string body as JSON and merges the
default response keys into the header
(`array_merge( self::$_defaultResponse['header'],
self::filterHeaderFields( $request[...]['header'] ) )`).
The kernel sends the status code, echoes the body, and calls `exit`.
After that the script is done - there is no "teardown" phase.

**Important:** Any output during the runtime *(`echo '...';`, `header('Location: ...')`, etc.)* breaks the whole architecture and is a bug. `Http::output()`'s own `http_response_code()` call deliberately runs last and sets the status code. Changes to status code, header Location, etc. must happen directly on `$request['/nino/http/response']`.

## Persistent data
### Overview
For handling persistent data, Nino provides three different concepts. All of them are stored in the filesystem and can be read and written through the kernel, `_admin` and `_editor`. They differ in structure and, correspondingly, in how they're used.

#### 1. `config.php` — the place for global key/value values
This is where the `$appData` starting values live: routes, modules, locales, users, asset bundles, error/session flags. Every persistent change to the kernel and module configuration is stored here - including for additional modules. `$appData` is, however, never written automatically. That only happens deliberately, via:
`AppData::writeContentData( $appData, array $keys )`
The file is re-read here and merged together with the *passed* keys. Integrating external values into `_admin` is not implemented yet.

#### 2. `elements/*.php` — the place for typed values
This is where data following fixed models can be stored and read *(e.g. posts, staff, reviews, news, etc.)*.
For every element, a type model with fields is defined *(e.g. title/string/200 chars/multilocale, price/float/€ suffix, product/image/800x600, etc.)*, which can then be filled with elements via `_editor`. Fields/variables can have different types *(strings, integer, DateTime, boolean - also images and float)*, can be single- or multi-language, or carry extra rules *(max length, suffix, etc.)*. The type model is stored together with the elements in one file *(e.g. `elements/services.php`)*.
Every type has a unique type uri *(e.g. `/services`)*, a global title, and its model with the variable definitions.
Every element has a unique uri *(e.g. `/webdesign` - so `/services/webdesign`)* and, depending on the model, global and local values.
A graphical interface for conveniently creating/editing types is available in `_admin`. Precise documentation for manually editing the file will follow.
**Access** happens through the built-in template shortcode, e.g. `[elements /services]<h5>[title]</h5>[/elements]`, or the kernel class `\Nino\Elements::getElement( $appData, ..)`, etc.
Creating/editing/deleting elements (not types) - including image upload - also happens graphically, via `_editor`.

#### 3. `text/*.php` — the place for global/local text content
This is where all language-independent *(text/global.php)* and language-dependent texts live *(`text/de_DE.php`, `text/en_US.php`, ...)*. The structure is likewise key/value, but is aimed specifically at text building blocks in templates. The global texts, and the local texts matching the current language, are loaded automatically at startup and substituted in `\Nino\Html::renderHtml()`. Textfills are normally set up following the convention `[[/category/type/name]]` and are substituted in exactly that form inside the template. Nesting *(e.g. `[[/webpage[[/nino/http/response/uri]]/title]]`)* is possible too and is fully resolved. Which text keys can exist is defined in `_admin`. Filling in the text values happens in `_editor`.

## Basic Templating (Shortcodes, Textfills)

The heart of the template engine sits in `Html::renderHtml( $appData, $html )`. This method runs automatically whenever templates load, and it always does 3 things:

```php
$html = self::_renderFills( $appData, $html );       // [[key]] -> value
$html = self::_renderShortcodes( $appData, $html );  // [name args]content[/name] -> the callback's return value
$html = Callbacks::doCallbacks( $appData, '/nino/html/render', $html );
```

### 1. Textfills
Here every global and local textfill array is combined and substituted with `str_replace( [ [[key1]], [[key2]], .. ], [ val1, val2, .. ] )`.
***Important:** Beyond the content of the `.php` files, textfills can also be created at runtime via `Html::addFills()`.*
The replacement loop runs until the number of
`[[` no longer changes - so a fill's *value* can itself contain
another `[[key]]`.
**!** JavaScript and CSS are also rendered with textfills when the kernel renders assets.

### 2. Shortcodes
Every registered shortcode matching the pattern (`[shortcodename arg1 arg2="value"]content[/shortcodename]`, or
self-closing `[shortcodename arg1]`) is captured here by a regex. Shortcodes are registered beforehand via `Html::addShortcode()` and, on every match, run the given callback through `_doShortcode()`. That private static function...

1. ...parses `arg1`, `arg2="value"` into a mixed positional/keyed
   `$args` array (a bare word becomes a positional entry,
   `key="value"` becomes `$args['key']`).
2. ...puts any enclosed content into `$args['content']`.
3. ...dispatches to the shortcode's registered handler via
   `Callbacks::doCallbacks( $appData, '/nino/html/shortcode/'. $name, $args )`,
   and
4. **...calls `renderHtml()` again, recursively -regardless of the return value.** This mechanism is what allows shortcodes to nest.
*(e.g. `[template x]` first returns the raw, unprocessed content of `x.tpl`.
Only the recursive call to `renderHtml()` resolves further textfills/shortcodes inside that template.)*
**Potential for infinite loops:** without care, `[template x]` inside `x.tpl` itself would cause an infinite loop. For that reason, the `Template` shortcode is written differently:

```php
public static function doShortcode( array &$appData, array $args ): string {
    $html = Filesystem::getFileContent( $appData, ( $args[0] ?? '' ). '.tpl', '' );
    return Callbacks::doCallbacks( $appData, '/nino/html/render', $html );
}
```

It calls the lower-level `doCallbacks( '/nino/html/render', ... )` directly instead of the full `renderHtml()` - the one extra recursive render pass that `_doShortcode()` always adds afterwards is then the only re-processing the included template's content gets, so the include itself never triggers a second nested full render pass.

The kernel shortcode `[element]` is a good example of using `content` and `args` inside a callback.
`[element /services/consulting]<h2>[[title]]</h2>[/element]` loads the element's data and then replaces `[[title]]` *inside the enclosed content* with the element's matching field value. Note that the uri is positional (`$args[0]`), not a named `uri="..."` argument.

### 3. `'/nino/html/render'` callbacks
Every additionally registered callback on the `'/nino/html/render'` event receives the HTML string for modification.
The result is then returned.

## Nino Classes
This list is not a complete reference.
Every class and module naturally has additional `public static` methods beyond what's listed here — some genuinely play no leading role in day-to-day project development and are left out for that reason. Note, though, that not every `\Nino` core class has an `init(...)` method to begin with - only `AppData`, `Auth`, `Filesystem`, `Locales` and `Runtime` do; `Callbacks`, `Elements`, `Html`, `Http`, `Images`, `Mail` and `Modules` never had one. Every `\Nino\Modules` class, by contrast, always has one (that's how it gets initiated from `config.php`'s `/nino/modules` list) - it's simply omitted below since it never needs to be called directly. The methods listed here are the ones that matter directly when developing with Nino.

### `\Nino` Kernel
These classes are initialized automatically and are a mandatory part of every project. They mostly serve to deliberately modify the `$appData` array and mostly work as standalone helper classes/methods.

#### `AppData`
Manages the central appData array: loading `config.php` at boot, and selectively writing individual top-level keys back.
`writeContentData( array &$appData, array $keys )`
Writes only the given top-level keys back to `config.php` - re-reads the file fresh first, so it doesn't clobber concurrent writes.

#### `Auth`
User authentication: session login/logout, user management, and the granular permission system for admin accounts.
`loginUser( array &$appData, string $username, string $pw )`
Checks credentials, rotates the session id/CSRF token, rehashes the password if needed, and sets the logged-in user.
`logoutUser( array &$appData )`
Ends the current session, rotates the CSRF token, and removes the client from the user's known sessions.
`getCurrentUser( array &$appData )`
Returns the currently logged-in user's data, or `false`.
`getUser( array &$appData, string $username )`
Returns a user's data by email address, or `false`.
`insertUser( array &$appData, string $username, string $pw, array $perms = [] )`
Creates a new user with a hashed password.
`deleteUser( array &$appData, string $username )`
Deletes a user.
`updateUser( array &$appData, string $username, string $newUsername, string $pw = '' )`
Changes a user's email and/or password, keeping the current session valid across a self-rename.
`logoutAllSessions( array &$appData, string $username )`
Ends every active session of a user ("log out everywhere"), including the current one if it's affected.
`checkPermission( array &$appData, string $perm, string $username = '' )`
Recursively checks (including wildcard parent perms) whether a user holds a given permission.

#### `Callbacks`
The generic callback mechanism: any piece of code registers itself under a string key, any other piece of code fires that key deliberately.
`registerCallback( array &$appData, string $name, mixed $callback, int $prio = 5 )`
Registers a callable under a name (with a priority) in appData.
`doCallbacks( array &$appData, string $name, mixed &$args = null )`
Runs every callback registered under a name, in order, passing its return value on as the new `$args`.

#### `Csrf`
CSRF protection for every non-safe (`POST`/`PUT`/`DELETE`/`PATCH`) request, via a session token - required and always active, unlike the optional `\Nino\Modules\*` classes below. `\Nino\Modules\Csrf` only adds the `[csrf]` shortcode on top of it.
`getToken( array &$appData )`
Returns the current session's CSRF token, creating one if needed.
`rotateToken( array &$appData )`
Replaces the token with a new one (e.g. after login/logout).

#### `Filesystem`
Central file I/O with an in-request cache, locking, and automatic `.php`/`.json` (de)serialization.
`getFileContent( array &$appData, string $filename, mixed $default = false )`
Reads a file, cached (mtime-invalidated); `.php` files are included, `.json` is decoded.
`putFileContent( array &$appData, string $filename, mixed $content, bool $nolock = false, bool $append = false )`
Writes content to a file (incl. locking, opcache invalidation); serializes arrays to match the extension.
`lockFile( array &$appData, string $filename )`
Opens a file with an exclusive lock (`flock LOCK_EX`).
`unlockFile( array &$appData, string $filename )`
Releases a previously locked file.
`fileExists( array &$appData, string $filename )`
Checks whether a file exists.
`forceDir( array &$appData, string $dirpath )`
Creates a directory if it doesn't exist yet.
`getPath( array &$appData )`
Returns the project's absolute filesystem path.
`getDir( array &$appData )`
Returns the site's configured URL directory.
`copyDir( string $source, string $dest )`
Copies files/folders recursively.
`removeDir( string $target )`
Deletes files/folders recursively.

#### `Elements`
File-based, multilingual content (comparable to posts/nodes): CRUD on elements and element types.
`getElement( array &$appData, string $uri, string $locale = '', mixed $return = false )`
Returns a single element in one locale.
`queryElements( array &$appData, string $typeUri, array $query, string $locale = '', mixed $return = false )`
Searches every element of a type by a key/value query (incl. `%wildcard%`), over one locale or all of them.
`updateElement( array &$appData, string $uri, array $data, string $locale = '' )`
Updates an existing element.
`insertElement( array &$appData, string $uri, array $data, string $locale = '' )`
Creates a new element, provided the uri is still free.
`deleteElement( array &$appData, string $uri, string $locale = '' )`
Deletes an element (optionally across every locale via `*`).
`insertElementType( array &$appData, string $typeUri, array $model )`
Creates a new element type, including its field model and default values.

#### `Html`
The HTML rendering pipeline: textfills, shortcodes, asset lists, and HTML sanitizing.
`renderHtml( array &$appData, string $html )`
Substitutes textfills and shortcodes in an HTML string.
`renderTextfill( array &$appData, string $fill )`
Resolves a single textfill key in the current language.
`addAsset( array &$appData, string $library, string $assetfile )`
Adds an asset file to a named asset library.
`addShortcode( array &$appData, string $shortcode, mixed $callback )`
Registers a new shortcode name with its callback.
`addFills( array &$appData, array $fills, string $locale = '' )`
Adds textfills for a locale (or `*`).
`getAssets( array &$appData, string $library )`
Returns every asset file of a library.
`getFills( array &$appData )`
Returns every currently valid textfill (global + locale + request fills), merged.
`containsHtml( string $value )`
Checks whether a value contains one of the allowed inline tags (`strong`/`em`/`span`/`a`).
`sanitizeHtml( string $html )`
Cleans up raw HTML down to a whitelist of allowed inline tags and safe href schemes.

#### `Http`
The HTTP request/response cycle: routing, security headers, output.
`output( array &$appData, array &$request )`
Sends header, status code, and body, and ends execution.
`requestRoute( array &$appData, string $uri, string $method )`
Looks up a registered route for method+uri, including the wildcard fallback onto parent paths (`/*`).
`findRouteUri( array &$appData, string $responseUri, string $locale )`
Finds the route key whose uri (in a given locale) matches a given response uri - for locale redirects.
`getRequest( array &$appData, int $offset = 0 )`
Returns an earlier request from the request history.
`getClientIp()`
Returns the client's actual TCP peer address (`REMOTE_ADDR`, no spoofable headers).
`filterHeaderFields( array $headerArray )`
Filters a raw header array down to a whitelist of known HTTP headers.

#### `Images`
Image uploads: validation, centered crop/resize, and management of the developer-defined image slots.
`process( array &$appData, string $bytes, int $targetWidth, int $targetHeight, string $basePath )`
Validates raw upload bytes, center-crops/resizes them to the target dimensions, and stores them deterministically (jpeg or png).
`delete( array &$appData, string $filename )`
Deletes an image file previously stored via `process()`.
`getUrl( array &$appData, string $filename )`
Builds the public URL to a stored image file.
`getSlots( array &$appData )`
Returns every developer-defined image slot.
`getSlot( array &$appData, string $uri )`
Returns one slot's definition.
`setSlotFilename( array &$appData, string $uri, string $filename )`
Records a new filename for an existing slot and persists it to `config.php`.

#### `Locales`
System-wide locale handling, including switching locale via a query parameter and redirecting to the matching language variant.
`getCurrentLocale( array &$appData )`
Returns the currently active locale.
`getNativeLocale( array &$appData )`
Returns the site's default/native locale.
`getAvailableLocales( array &$appData )`
Returns every available locale.
`verifyLocale( array &$appData, string $locale )`
Checks whether a locale is available.
`setCurrentLocale( array &$appData, string $locale )`
Sets (after verification) the current locale and persists it in the session.

#### `Mail`
A thin wrapper around `mail()` with a Content-Type/Reply-To header and an IP-based rate limit shared by every sender (Form, Newsletter).
`send( array &$appData, string $to, string $subject, string $body, string $replyTo )`
Sends an HTML mail with a Reply-To header, unless the sending client IP has already hit its hourly cap.

#### `Modules`
Loads, initiates, and addresses the optional modules enabled in `config.php`.
`callModules( array &$appData, string $method )`
Calls a given method (e.g. `init`) on every enabled module.

#### `Runtime`
The PHP runtime environment: session handling and global error/exception handling, including logging.
`getSessionValue( array &$appData, string $key, mixed $return = null )`
Reads a value from the project-specific session.
`setSessionValue( array &$appData, string $key, mixed $value )`
Writes a value into the project-specific session.
`unsetSessionValue( array &$appData, string $key )`
Removes a value from the session.
`handleError()`
The global error/exception handler: logs (if configured) to `/data/logs.<year-month>.php` and either displays the error or aborts with a 500.

### `\Nino\Modules`
These kernel modules are optional and extend Nino projects' functionality - mostly on the frontend. They're built exactly the way an external module would be (see "Building your own modules" below) and live under `_nino/Nino/Modules/`, one directory per module, autoloaded on first use exactly like a project's own custom modules.
They're enabled via the `/nino/modules` array in `config.php`.

#### `Assets`
CSS/JS asset management: bundles, caches and (for `.min` filenames) minifies several source files into one output file.
**Shortcode:**
`[assets /path/file.css]`
Includes the files collected for this library via `addAsset()`, bundled (and minified for a `.min` filename), as a `<link>` or `<script>` tag.

#### `Csrf`
Adds the `[csrf]` shortcode on top of the required `\Nino\Csrf` kernel class (see above) - the protection itself is active whether or not this module is.
**Shortcode:**
`[csrf]`
Renders a hidden `_csrf` input field carrying the current session token - belongs in every form that's checked server-side via the Csrf callback.

#### `Elements`
Shortcodes for including one or several elements in templates.
**Shortcode:**
`[element /type/uri locale="xx" callback="..."]...[[key]]...[/element]`
Renders the content once, for a single element; `[[key]]` placeholders inside the content are replaced with the element's field values. `locale`/`callback` are optional.
`[elements /type limit="N" query="key=value" locale="xx" callback="..."]...[[key]]...[/elements]`
Repeats the content for every element of a type (optionally filtered via `query`, capped via `limit`) - `[[key]]` placeholders are re-substituted for each hit.

#### `Form`
The contact form: validation, sending the owner/confirmation mail, and storing every submission for `_editor`.
**Javascript:**
`Nino.http.sendRequest( '/.form', 'POST', function( xhr ) { ... }, { name: '...', email: '...', message: '...', cat: '...', location: '' } )`
Sends the contact form to `/.form`. `location` must stay empty (honeypot). Success/failure is only visible via `xhr.status` - the response body stays empty: 200 sent, 400 missing/invalid field, 418 honeypot filled, 403 CSRF invalid/missing.

#### `Images`
A shortcode for including a developer-defined image slot.
**Shortcode:**
`[image slotUri alt="..."]`
Renders an `<img>` tag for a developer-defined image slot, provided an image has already been uploaded for it - otherwise it stays empty.

#### `Jstext`
Also exposes the current textfills as JSON to the frontend JavaScript.
**Shortcode:**
`[jstext]`
Renders a `<script>` tag that provides every current textfill as the `NinoJstext` JS object (secured with a CSP nonce).
**Javascript:**
`Nino.content.getText( key )`
Reads back a text provided by the `[jstext]` shortcode (`window.NinoJstext`), e.g. `Nino.content.getText('/form/info/success')`. Returns `''` if the key doesn't exist.

#### `Localepicker`
A ready-made language switcher as a shortcode, including redirect handling.
**Shortcode:**
`[localepicker callback="..."]`
Renders the locale-selection UI with every available locale, current locale marked as the active entry. `callback` optional.

#### `Navigation`
A simple renderer for navigation menus (burger or regular variant) from a line-based mini syntax.
**Shortcode:**
`[navigation id="..." class="..." burger]uri:Title:Attributes[/navigation]`
Builds a `<ul>` navigation out of `uri:title:attribute` lines in the content; the `burger` flag (no `=`) renders the burger-menu variant instead of the regular one.

#### `Newsletter`
Double opt-in newsletter signup under `/.newsletter`: signup, confirmation, and self-service unsubscribe.
`getUnsubscribeLink( array &$appData, string $email )`
Builds the absolute unsubscribe url for a (subscribed or still-pending) email address.
**Javascript:**
`Nino.http.sendRequest( '/.newsletter', 'POST', function( xhr ) { ... }, { email: '...', location: '' } )`
Signs an email address up for the newsletter. `location` must stay empty (honeypot). On 200, `xhr.responseJSON.status` reports `'new'` (confirmation mail just sent) or `'existing'` (already subscribed/pending, no new mail); 400 missing/invalid email, 418 honeypot filled, 403 CSRF invalid/missing. Confirmation and unsubscribe do **not** go through JS - they run via the link sent by mail (`GET /.newsletter?confirm=<token>` resp. `?unsubscribe=<token>`, see `getUnsubscribeLink()`).

#### `Template`
A shortcode for including another `.tpl` template.
**Shortcode:**
`[template /path/file]`
Loads the given `.tpl` file (without extension) and renders it.

## Building your own modules
A regular PHP class becomes a Nino module as soon as it has the static method `init( array &$appData ):void`. Once the class is then entered under its fully-qualified name in `config.php`'s `/nino/modules`,
`\Nino\init(..)` calls that static `init(..)` with the current `$appData`, letting it modify `$appData` and register callbacks and shortcodes.
Registering it in `config.php` follows this pattern:

```php
'/nino/modules' => [
    '\\Nino\\Modules\\Assets',
    // ...
    '\\MyProject\\Modules\\Foo',
],
```

Any class following this convention is autoloaded on first reference,
whether that's `callModules()` calling it, `\Nino\init(..)`, or a direct
call from anywhere else: `_nino/Nino.php` registers a
`spl_autoload_register()` callback that derives a file path from the
requested class' own namespace:

```php
$relativePath = str_replace( '\\', '/', $className );
$filename = __DIR__ /* _nino/ */ . '/' . $relativePath . '/' . basename( $relativePath ) . '.php';
```

For `\MyProject\Modules\Foo` (or the leading-backslash-free
`MyProject\Modules\Foo` - both name the same class, and PHP strips the
leading backslash before an autoloader ever sees the name), that
resolves to `_nino/MyProject/Modules/Foo/Foo.php` — the directory is
named after the full namespace path, and the file inside it repeats the
short class name. Every built-in `\Nino\Modules\*` module already lives
at exactly this path under `_nino/Nino/Modules/`, so it autoloads the
same way - `config.php`'s `/nino/modules` list only ever controls which
modules get a method called on them via `callModules()`, never which
classes exist to be referenced at all.

Your own module, built in Nino's coding philosophy, could look like this:
### Example

```php
<?php
declare(strict_types=1);

namespace MyProject\Modules {

    class LoginLog {

        public static function init( array &$appData ): void {
            \Nino\Callbacks::registerCallback( $appData, '/nino/auth/login', [ self::class, 'onLogin' ] );
            \Nino\Html::addShortcode( $appData, 'lastlogin', [ self::class, 'doShortcode' ] );
        }

        public static function onLogin( array &$appData, mixed $args ): mixed {
            \Nino\Filesystem::putFileContent( $appData, '/data/last-login.php', [
                'mail' => $args['mail'] ?? '', 'time' => time(),
            ] );
            return $args;
        }

        public static function doShortcode( array &$appData, array $args ): string {
            $last = \Nino\Filesystem::getFileContent( $appData, '/data/last-login.php', [] );
            return $last['mail'] ?? '';
        }
    }
}
```

***The long-term dream is to build a small Nino community. Thanks to the modular concept, we can take work off each other's hands and share encapsulated modules with general-purpose tasks.
I'd love any feedback, on GitHub or at mail@dape.io.***

## The `_editor`/`_admin` add-ons

For developing and maintaining a website, `_admin/` and `_editor/` ship graphical frontend tools. Both have their own bootstrap scripts that work independently of the `/index.php` route. The applications are therefore decoupled and can be removed at any time without affecting the actual website's display.
The webserver must be configured to forward requests to `/_editor` and `/_admin` to the matching index. When testing locally with PHP's built-in server, the router script must route this case explicitly.

The two applications are built similarly, with clear differences:

**`_admin`** serves a single (!) developer. Login happens via a hardcoded `PASSWORD_HASH` constant, defined directly in the file. Its functions simplify site development and reduce the need to hand-edit `.php` array files. Its potential for structural damage, however, is significant.

_editor serves the site administrators/operators. Multiple admin accounts can be created (via _admin) with different permissions. The backup system provides basic safeguarding.

**`_admin` is deliberately decoupled from `config.php`**
Logging in and using `_admin` works independently of `config.php`. That way a backup can be restored even if `config.php` itself is broken.

### The Backup
Once development is done, the backup can be enabled via `config.php`'s `/nino/editor/backups`. That creates a backup once a day on `_editor` login. `Admin\Backup::maybeRun()` tars+gzips every file the admin panel can write to at runtime,
  encrypts it (AES-256-GCM), and writes it under a one-time random
  directory name inside `_editor/`. Backups rotate every 14 days. The file itself has a `.php` extension with a self-terminating stub `<?php http_response_code(403); exit; return '<base64-payload>';` — so a direct HTTP request can't read it.
`Dev\Restore` reads `Backup`'s own output independently — it discovers the
  backup directory via a filesystem glob pattern and keeps its own copy of the decryption key in `_admin/.restore-key.php`. Every restore first takes an independent safety snapshot of the *current* state.

A user guide for both applications lives here:
[_editor Handbook](_editor.md) · [_admin Handbook](_admin.md)

## Testing

`tests/*.php` are dependency-free PHP scripts (no PHPUnit) — each one
directly `require`s the kernel/backend file(s) it needs, and
runs against an isolated sandbox directory under
`sys_get_temp_dir()`, never against real project data:

```
tests/kernel-smoke.php      Kernel: AppData, Filesystem, Auth, Http, Locales, Images, Csrf, Mail, Newsletter, ...
tests/editor-smoke.php      _editor/Editor.php: Elements, Text, Users, Images, Backup, Logs, Submissions
tests/admin-smoke.php       _admin/Admin.php: ElementTypes, Text, Pages, Images, Users, Restore, Config, rate limiting
tests/install-smoke.php     _install/Install.php: Checks, Setup, Themes, Webpages, PersonalInfos, Admins, Finish
tests/templates-smoke.php   _templates/Templates.php: Library, Parser/Serializer round-trip, Stylesheets, Documents
tests/concurrency-smoke.php Filesystem locking under parallel writers
```

Run with `php tests/kernel-smoke.php` etc. — currently 840
assertions total across the six suites. CI runs all six plus
`php -l` and `node --check` over every file, see
`.github/workflows/ci.yml`.

Two things worth keeping in mind when adding your own tests:

- Where possible, dispatch through the real entry point
  (`Admin::handlePost()`, not the domain class's `apiXxx()` directly),
  as soon as what you're testing hooks in at that level (e.g. the
  activity log) — calling the domain method directly silently skips
  that.
- A private method (e.g. `Mail::_hit()`) is still testable via
  `ReflectionMethod` — see the Mail rate-limit section in
  `tests/kernel-smoke.php` for the pattern, including why
  `invokeArgs()` (not `invoke()`) is needed to pass `$appData` by
  reference.

## Encryption / stub conventions

Anyone writing another admin-managed file that must live in the
webroot but must not be readable via a direct request should follow
the established pattern (`Admin\Backup`, `Admin\Logs`, `Dev\Restore`):

1. A one-time random directory name
   (`bin2hex(random_bytes(16))`), generated once and persisted, linked
   from nowhere.
2. A `.php` extension, content wrapped as `<?php
   http_response_code(403); exit; return '<payload>';` with **no
   closing `?>` tag** — keep the payload as base64 inside the
   single-quoted string literal, in the same never-closed PHP block as
   the `exit()`.
3. Only encrypt when the content is genuinely as sensitive as a
   credential (AES-256-GCM, `random_bytes(12)` IV, the standard
   16-byte tag, `$iv . $tag . $cipher` concatenated for storage) —
   otherwise the stub alone is enough, and the key-management overhead
   isn't worth it.

## An example: Setup → Go-Live

The shortest real path from an empty checkout to a deployed, working
site:

1. **Routing works locally.** `php -S 127.0.0.1:8000 router.php` —
   the bundled `router.php` dispatches `/_editor/*`/`/_admin/*` to
   their own entry points and lets `.cache/`/`.demo/` through as static
   files; without it, `/_editor` and `/_admin` 404 on the built-in
   server.
2. **Set a real `_admin` password:** `php _admin/Admin.php <your
   password>` → paste the printed hash into `_admin/Admin.php`'s
   `PASSWORD_HASH` constant. The shipped placeholder matches no real
   password, so nothing past it is reachable before this is set.
3. **Create the first admin account** under `/_admin` → "Users",
   with the manager checkbox ticked — the first real write to `config.php`'s
   `/nino/auth/user`.
4. **Define the route** in `config.php`'s `/nino/http/routes` (by
   hand, or via `/_admin` → "Config"):
   ```php
   'GET://about' => [ 'uri' => '/about', 'body' => '[template /templates/page-about]' ],
   ```
5. **Create an element type** under `/_admin` → "Element Types" for
   every kind of recurring content the site needs.
6. **Prepare an image slot** under `/_admin` → "Images" for anything
   the template should later let admins swap out without a code
   change.
7. **Write the template**, using the textfills, element type and
   image slot from the steps above (see
   `docs/design.md` for the full markup reference and the
   distinction between a slot and an element field).
8. **Set `config.php` for production:**
   - `/nino/error/display` → `false`, `/nino/error/log` → `true`
     (the shipped defaults since `0.9.0-beta` — leaving `display` at
     `true` puts raw PHP errors, including function arguments via
     `debug_backtrace()`, straight into the response body)
   - `/nino/session/force-secure-cookie` → `true` if the site sits
     behind a TLS-terminating reverse proxy (where
     `$_SERVER['HTTPS']` typically isn't set, even over an HTTPS
     connection)
   - decide on `/nino/editor/backups`/`/nino/editor/logs`
9. **Go live** — point the webserver at the project root,
   verify that `/_editor` actually renders a login page
   (no 404, no raw PHP source), then work through
   `docs/admin.md`'s go-live checklist, including running all three
   test suites against the deployed `config.php`.

## Appendix
### The kernel callbacks

| Name | Fires | Args | Can veto |
|---|---|---|---|
| `/nino/auth/login` | after a successful login | the logged-in user array | no |
| `/nino/auth/logout` | after logout | the logged-out user array | no |
| `/nino/auth/user/insert` | after `Auth::insertUser()` has persisted | the new user array | no |
| `/nino/auth/user/update` | after `Auth::updateUser()` has persisted | the updated user array | no |
| `/nino/auth/user/delete` | after `Auth::deleteUser()` has persisted | the deleted user array | no |
| `/nino/elements<typeUri>/insert` | before a new element of this type is written | the full file data of this type | **yes** |
| `/nino/elements<typeUri>/update` | before an existing element of this type is written | the full file data of this type | **yes** |
| `/nino/elements<typeUri>/update/uri` | when an element's own uri changes | the posted element data | no |
| `/nino/elements/delete<typeUri>` | before an element of this type is removed | the full file data of this type, after the deletion | **yes** |
| a field's own `callbacks` entries | while validating a save, once per listed callback | the full posted element data | **yes** |
| `/nino/http/request` | once per request, right after parsing method/uri/headers/body | the full `$request` array | no |
| `/nino/http/response` | once per request, after routing, for every route | the full `$request` array | no |
| `/nino/http/response/METHOD://uri` | only for this exact route | the full `$request` array | no |
| `/nino/html/render` | once per `renderHtml()` pass (fills → shortcodes → this) | the html string so far | no (the return value replaces the html) |
| `/nino/html/shortcode/<name>` | this is how every shortcode actually gets dispatched | the shortcode's parsed `$args` | no (the return value becomes the shortcode's output) |
| `/nino/shortcodes/assets/output/<css\|js>` | when `[assets ...]` renders its `<link>`/`<script>` wrapper | the template string, before the fills | no (the return value replaces the template) *(name is a leftover from the `\Nino\Shortcodes` → `\Nino\Modules` rename - the class moved, this string didn't)*
