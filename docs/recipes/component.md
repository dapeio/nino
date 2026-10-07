# Recipe: Add a component

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Features](../features.md)

One of the eight extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


Use a component when a shortcode should say what it takes: where its value
comes from and which attributes it has, each with a type and a default, so
that a tool such as the Builder feature can offer it and a template can still
be written by hand. A shortcode without a schema stays a shortcode - for
behaviour that needs no tool, the [runtime module recipe](runtime-module.md)
is enough. A **stack** is a component that loops the elements of one type
around its content (`[slider]`, `[filter]`, `[list]` are three the kernel
brings); an element type is the [element types recipe](element-types.md)'s
business, not this one's. The contract this recipe writes against is
`\Nino\Modules\Components`, described in the
[Developer Manual](../development.md#components).

The example is a countdown: `[countdown /template/page-home/launch/date]`
draws the days that are left until a date a text key holds.

## 1. A component from the project

A project's component is registered where its module boots. Create
`app/Project/Countdown/Countdown/Countdown.php` and list
`\Project\Countdown\Countdown` in `/nino/modules` (after the kernel's own
modules, so that a replacement of one of theirs comes last):

```php
<?php
declare(strict_types=1);

namespace Project\Countdown;

class Countdown {

	// The markup is a fragment declared once, not built in the method - AGENTS.md,
	// "Markup belongs in a template" - so that a project changes it in one place
	public static
		$html = [
			'countdown' => '<p class="project-countdown[[style]][[class]]">[[days]]</p>',
		];

	public static function init( array &$appData ): void {

		\Nino\Modules\Components::addComponent( $appData, 'countdown', [ self::class, 'componentCountdown' ], [
			'label'      => [ 'en_US' => 'Countdown', 'de_DE' => 'Countdown' ],
			'source'     => 'text',
			'loop'       => true,
			'attributes' => [
				'style' => [ 'type' => 'select', 'options' => [ '', 'big' ], 'default' => '',
				             'label' => [ 'en_US' => 'Size', 'de_DE' => 'Größe' ] ],
			],
			'preview'    => 'block',
		] );
	}

	public static function componentCountdown( array &$appData, array $args ): string {

		// $args['value'] is the source made safe already: a text key's value, or
		// in a stack the field of the element, escaped, every [ written as &#91;.
		// Parse it for what it means, and write only what you made yourself
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', html_entity_decode( $args['value'] ) );

		if( $date === false )
			return '';

		$days = max( 0, (int) ( new \DateTimeImmutable( 'today' ) )->diff( $date )->format( '%r%a' ) );

		return str_replace(
			[ '[[style]]', '[[class]]', '[[days]]' ],
			[ $args['style'] === 'big' ? ' project-countdown--big' : '', $args['class'] !== '' ? ' '. \Nino\Modules\Components::escape( $args['class'] ) : '', (string) $days ],
			self::$html['countdown']
		);
	}
}
```

The rules the module keeps for you, and the ones it leaves to the renderer:

- The first argument is resolved over `Components::value()` before the
  renderer runs: a `/key` is its text, a name without a slash is a field of the
  element in a stack, `.id` and `.uri` are the place in the loop and the
  element's uri, and `text="..."` is a fixed value. A source that resolves to
  nothing renders nothing - the renderer is not called.
- Missing attributes arrive as their defaults, a `select` outside its options
  is the default, an attribute nobody declared is not there. All strings.
- Anything the renderer writes that did not come from `$args['value']` is its
  own to escape. `Components::escape()` escapes and writes `[` as `&#91;`;
  everything you return is rendered once more as a shortcode's output is.
- The markup is a fragment with `[[tokens]]`, declared as a property of the
  class (`public static $html`, as `Components::$html` is) and filled with
  `str_replace()`; what is filled in comes last in the list of tokens.
- `addComponent()` **replaces** a component or a shortcode of the same name.
  Register `title` again with a schema of your own and the kernel's is gone; the
  module's `init()` takes it back where it is called again.
- A schema that cannot be worked with is an `E_USER_ERROR` at registration - a
  name that is no slug, an unknown `source` or type, a `select` without options
  or with a default outside them, an attribute without a default.

## 2. A stack from the project

A stack is registered with `addStack()`, takes the type uri as its first
argument and calls `renderStack()` for the loop. A project's list of the
people of a type, drawn as a definition list - with `'people' => '<dl class="project-people">[[items]]</dl>'`
and `'person' => '<dd>[[inner]]</dd>'` beside the other fragment:

```php
\Nino\Modules\Components::addStack( $appData, 'people', [ self::class, 'stackPeople' ], [
	'label'      => [ 'en_US' => 'People', 'de_DE' => 'Personen' ],
	'grid'       => false,
	'attributes' => [],
	'preview'    => 'block',
] );

public static function stackPeople( array &$appData, array $args ): string {

	$items = \Nino\Modules\Components::renderStack( $appData, $args,
		static fn( string $inner, array $element, int $index ): string => str_replace( '[[inner]]', $inner, self::$html['person'] ) );

	return $items === '' ? '' : str_replace( '[[items]]', $items, self::$html['people'] );
}
```

`renderStack()` runs the loop of `[elements]` with the same `sort`, `offset`,
`limit`, `query` and `callback`, renders the content once per element with the
element as the context, and gives each cell to the function. `grid => true`
adds `cols`, `gap` and `autoheight` and passes the cell's classes as the
fourth argument; `\Nino\Modules\Components::cell()` puts them on the element
you draw. Inside the content a component's source is a field of the element
(`[title name]`). A stack never stands directly inside another.

## 3. A component from a feature

A feature declares it in its manifest - no `init()` call to write - and
brings the renderer under the name the kernel derives:

```php
// features/Countdown/feature.php
return [
	'key'        => 'countdown',
	'name'       => 'Countdown',
	'version'    => '1.0.0',
	'nino'       => '^1.6',
	'components' => [
		'countdown' => [
			'label'      => [ 'en_US' => 'Countdown', 'de_DE' => 'Countdown' ],
			'source'     => 'text',
			'attributes' => [
				'style' => [ 'type' => 'select', 'options' => [ '', 'big' ], 'default' => '' ],
			],
			'preview'    => 'block',
		],
	],
	'stacks'     => [],
];
```

```php
// features/Countdown/Countdown.php
namespace Nino\Modules {
	class Countdown {
		public static function componentCountdown( array &$appData, array $args ): string { /* as above */ }
	}
}
```

`[ '\Nino\Modules\Countdown', 'componentCountdown' ]` is the convention - the
name in studly caps behind `component`, or `stack` for a stack
(`stackGallery()`, `componentNewsletterSignup()` for `newsletter-signup`). A
manifest whose schema does not validate is refused as a whole, naming the
component, and a renderer the class lacks is a warning and skipped. The kernel
registers the feature's components at boot while it is listed in
`/nino/modules`; the shortcode is the feature's, and the Features panel lists
it so. Register a component by manifest or in `init()`, not both - a second
callback beside the first is not a replacement.

## 4. Test it

Add the cases to the module's own test, or to the feature's
(`features/Countdown/tests/countdown-smoke.php`, loading `tests/harness.php`):

- the registry knows it: `Components::components( $appData )['countdown']`
  holds the schema, `Components::defaults( $appData, 'countdown' )` its
  defaults as strings;
- it renders: a text key with a date, a date that is none, a key nobody wrote
  (nothing), a `select` outside its options (the default), an attribute that
  is not declared (ignored);
- a value with a `[` in it - an editor's `[[/other/key]]` - arrives as `&#91;`
  and opens nothing after the second rendering pass;
- in a stack, a field of the element is the source, and after the stack
  `Components::element( $appData )` is `null` again;
- a schema with a `select` whose default is no option is refused with an
  `E_USER_ERROR` (capture it with a handler of the test's own; php 8.4 raises
  a deprecation beside it).

Run `tests/kernel-smoke.php`, `tests/features-smoke.php` and the feature's own
test, then the syntax checks and the static analysis of the
[agent guide](../../AGENTS.md#10-test-and-validation-matrix).

## Definition of done

- [ ] The schema validates: every attribute has a default, every select an option list that holds it.
- [ ] The renderer has the signature `( array &$appData, array $args ): string` and returns nothing it did not escape or make itself.
- [ ] Nothing in `$args['value']` is escaped a second time, and nothing outside it is written unescaped. For `image` the value is an escaped reference, to look the picture up by `$args['source']`, never a filename to write; for `content` it is the template's own markup.
- [ ] A source that resolves to nothing renders nothing; a hand-written shortcode with a wrong attribute still renders.
- [ ] A stack renders through `renderStack()`, never by filling `[[fields]]` of its content itself.
- [ ] Registered one way: manifest or `init()`.
- [ ] The cases above are in a smoke test and the suite named in the guide passes.
