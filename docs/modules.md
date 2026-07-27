# Writing Your Own Module

How to extend Nino without touching the kernel. Read `docs/developer.md`
first if the `init()` → callback vocabulary is unfamiliar. Everything a
module can do goes through one mechanism: `Callbacks` - a string name, a
callable, and a priority.

## What a module is

A class with a static `init( array &$appData ): void`, listed by
fully-qualified name in `config.php`'s `/nino/modules`:

```php
'/nino/modules' => [
    '\\Nino\\Shortcodes\\Assets',
    // ...
    '\\MyProject\\Modules\\Foo',
],
```

`Modules::callModules( $appData, 'init' )` (called from `\Nino\init()`)
walks the list and calls each class's `init()`. That's the whole
lifecycle. A class that isn't loaded yet is required from a path derived
from its namespace (`\MyProject\Modules\Foo` →
`_nino/MyProject/Modules/Foo/Foo.php`) - or `require` it yourself before
`\Nino\init()` runs, then the path doesn't matter.

## Callbacks

```php
\Nino\Callbacks::registerCallback( array &$appData, string $name, callable $callback, int $prio = 5 ): void;
\Nino\Callbacks::doCallbacks( array &$appData, string $name, mixed &$args = null ): mixed;
```

```php
namespace MyProject\Modules;

class Foo {
    public static function init( array &$appData ): void {
        \Nino\Callbacks::registerCallback( $appData, '/nino/auth/login', [ self::class, 'onLogin' ] );
    }

    public static function onLogin( array &$appData, mixed $args ): mixed {
        error_log( 'login: '. ( $args['mail'] ?? '?' ) );
        return $args; // non-null return replaces $args for the next callback
    }
}
```

Rules worth internalizing:

- `$name` is just a string - no wildcards, no enforced namespacing. Make
  up your own name for a project-only hook and call `doCallbacks()` on it
  yourself (that's exactly how `[element callback="my/callback"]` works).
- `$prio` is `0`-`9`, lower runs first, default `5`.
- Every registered callback runs, in priority order - the chain never
  stops early. A non-`null` return value becomes `$args` for the next
  callback and the final return of `doCallbacks()`.
- A few call sites treat an explicit `false` return as a veto (see the
  table) - everything else is fire-and-forget.

## Every system-wide hook point

`<typeUri>` is an element type's own uri, eg. `/deals`.

| Name | Fires | Args | Veto |
|---|---|---|---|
| `/nino/auth/login` | after a successful login | user array | no |
| `/nino/auth/logout` | after logout | user array | no |
| `/nino/auth/user/insert` / `update` / `delete` | after the change persists | user array | no |
| `/nino/elements<typeUri>/insert` / `update` / `delete` | before the write | type's full file data | **yes** |
| `/nino/elements<typeUri>/update/uri` | when an element's uri changes | posted element data | no |
| a field's own `callbacks` entries (element type model) | while validating a save | posted element data | **yes** |
| `/nino/http/request` | once per request, after parsing | `$request` | no |
| `/nino/http/response` | once per request, after routing | `$request` | no |
| `/nino/http/response/METHOD:/uri` | only for that exact route | `$request` | no |
| `/nino/html/render` | once per `renderHtml()` pass | html string | return value replaces it |
| `/nino/html/shortcode/<name>` | shortcode dispatch (`Html::addShortcode()` registers here) | parsed `$args` | return value is the output |
| `/nino/shortcodes/assets/output/<css\|js>` | when `[assets]` renders its tag | template string | return value replaces it |

## Testing your module

Same pattern as `tests/kernel-smoke.php` (see `docs/developer.md`):
`require` the kernel plus your module file, call `init()`/your handler
against a throwaway `$appData`, assert on the result. No PHPUnit, no
fixtures beyond a temp directory.