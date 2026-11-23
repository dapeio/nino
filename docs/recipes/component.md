# Recipe: Add a component

One of the five extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the working method, the core runtime model, the conventions and the
security review - apply to every step below.


Use a component when a shortcode should say what it takes: where its value
comes from and which attributes it has, each with a type and a default, so
that a tool such as the Builder feature can offer it and a template can still
be written by hand. A shortcode without a schema stays a shortcode - for
behaviour that needs no tool, the [runtime module recipe](runtime-module.md)
is enough - and a loop over the elements of a type is the [element types
recipe](element-types.md)'s business: `[elements]` draws what stands between its
tags once for each element, and a component in it reads the fields of the
element as its source. The contract this recipe writes against is the register of
the kernel, `\Nino\Components`, described in
[The Register of Components](../development.md#the-register-of-components): its
schema says where a component may stand, what it offers to what it holds, where
its value comes from, which attributes it takes, which variants it has and the
markup it is drawn as. A component with nothing to compute is a schema and a
template and needs no code; one that computes has a renderer.

The example is a countdown: `[countdown /template/page-home/launch/date]` draws the
days that are left until a date a text key holds.

## 1. A component in a feature

A feature brings its components in folders, and the kernel registers them while
the feature is on ([A feature's components](../development.md#a-features-components)).
Create `features/Countdown/` with these files:

```text
features/Countdown/
├── feature.php                          the manifest
├── Countdown.php                        the class \Nino\Modules\Countdown: the renderer
├── components/countdown/component.php   the schema
├── components/countdown/template.tpl    the markup
├── groups/countdown/group.php           the group the component is offered under
└── groups/countdown/part/part.css       the standard part of the group: its look
```

The manifest says what the feature is. It names no component:

```php features/Countdown/feature.php
<?php
// What Nino reads about the feature. The class is not named here:
// features/Countdown/ can only serve \Nino\Modules\Countdown
return [
	'key'					=> 'countdown',
	'name'				=> [ 'en_US' => 'Countdown', 'de_DE' => 'Countdown' ],
	'description'	=> [ 'en_US' => 'The days that are left until a date.', 'de_DE' => 'Die Tage, die bis zu einem Datum bleiben.' ],
	'category'		=> 'content',
	'version'			=> '1.0.0',
];
```

The schema is the file `components/countdown/component.php`. The name of the
folder is the name of the component, and a component of a feature **names its
group**:

```php features/Countdown/components/countdown/component.php
<?php
declare(strict_types=1);

return [
	'group'      => 'countdown',
	'label'      => [ 'en_US' => 'Countdown', 'de_DE' => 'Countdown' ],
	'in'         => [ 'page', 'flow' ],
	'source'     => 'text',
	'preview'    => 'text',
	'attributes' => [
		'size' => [ 'type' => 'choice', 'options' => [ 'regular', 'big' ], 'default' => 'regular', 'label' => [ 'en_US' => 'Size', 'de_DE' => 'Größe' ] ],
	],
];
```

`in` says where a tool offers the component - `page` is the page itself, `flow`
the flow of content - and changes nothing about the drawing. A container may add
`'deep' => [ 'place' ]`: the places it offers to every container of the flow below it,
however deep - a column of a row, a card in a column -, so that a component that names the
place in its `in` stands in each of them and in no container of a page - `header-wrap` does so for
`header-logo`. The parts of the container itself, which name it in their `in`, and what stands in
them are left out. `source` says that
the first word of a call is a text. An attribute has a `type` and a `default`,
and every attribute needs one. The markup is the file beside the schema; a
schema that names a template itself does not belong in a folder:

```html features/Countdown/components/countdown/template.tpl
<p class="[[class]]">[[days]]</p>
```

`[[class]]` is the name of the component and one class for each attribute that is
a class: `size="big"` is `countdown--size-big`, and `regular`, the default,
writes none. `[[days]]` is no placeholder of the register: the renderer makes it.

A group is a folder of its own with the words a tool shows and the standard
part, the look of the components of the group. `group.php` names the group and
its **knobs** - a knob is a token of a component and the five steps a theme may
turn it through, `'<component>.<name>'` with the token `--<component>-<name>`:

```php features/Countdown/groups/countdown/group.php
<?php
declare(strict_types=1);

return [
	'label' => [ 'en_US' => 'Countdown', 'de_DE' => 'Countdown' ],
	'knobs' => [
		'countdown.size' => [ 'token' => '--countdown-size', 'label' => [ 'en_US' => 'Font size', 'de_DE' => 'Schriftgröße' ],
			'ladder' => [ 'var(--page-text-1)', 'var(--page-text-2)', 'var(--page-text-3)', 'var(--page-text-4)', 'var(--page-text-5)' ] ],
	],
];
```

The standard part is `part/part.css`. It keeps the **rule of mixing**: it reads
the tokens of the brand and of the page (`--brand-...`, `--page-...`, which
`Nino.css` sets) and its own, it sets only its own, every selector has a class of
its own components, and it has no `!important`, no `@import`, no `@layer` and no
`url()`:

```css features/Countdown/groups/countdown/part/part.css
/*	countdown - the standard part: the days that are left, in the ink of the page	*/
:root {
	--countdown-size: var(--page-text-3);
}
.countdown { margin: 0; color: var(--brand-ink); font-size: var(--countdown-size); font-weight: 700; }
.countdown--size-big { --countdown-size: var(--page-text-6); }
```

The kernel puts the part into the style bundle in the layer `nino.components`, so
that a rule of the project overrules it with a plain selector. The renderer is
the method `component<Name>()` of the feature's class, the name in studly caps -
`componentCountdown()` for `countdown`, `componentNewsletterSignup()` for
`newsletter-signup`:

```php features/Countdown/Countdown.php
<?php
declare(strict_types=1);

namespace Nino\Modules {

	class Countdown {

		// [countdown /template/page-home/launch/date size="big"]
		public static function componentCountdown( array &$appData, array $args, string $content, array $context ): string {

			// $args['value'] is the text of the key, escaped: parse it for what it means, and write only what you made yourself
			$text = html_entity_decode( $args['value'] );
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $text );

			// A day the calendar does not have, 2026-02-30, is rolled over by php: it is none
			if( $date === false || $date->format( 'Y-m-d' ) !== $text )
				return '';

			$days = max( 0, (int) ( new \DateTimeImmutable( 'today' ) )->diff( $date )->format( '%r%a' ) );

			// The markup is the template of the component; the renderer gives it the token it made
			return \Nino\Components::fill( $appData, 'countdown', $args, $content, [ '[[days]]' => (string) $days ] );
		}
	}
}
```

Switch the feature on in the Features panel - the kernel reads the folders and
registers the component at the next request - and write the call. For a key
`/template/page-home/launch/date` that holds a date twelve days from today,
`[countdown /template/page-home/launch/date]` and
`[countdown /template/page-home/launch/date size="big"]` are drawn as

```html
<p class="countdown">12</p>
<p class="countdown countdown--size-big">12</p>
```

and `[countdown text="soon"]`, a date that is none, draws nothing. A component of
a feature that is off is a name nobody registered: it draws nothing, nor what
stands between its tags, and the log says so once.

What the register does for the renderer, and what it leaves to it:

- The attributes are made ready before the renderer runs - a `choice` outside its
  options is the default, a `bool` is `'1'` or `'0'`, an `int` is kept within its
  bounds, an attribute nobody declared falls away - and arrive as strings under
  their names, beside `value`, `source`, `class`, `variant` and `lang`.
- The first word of a call is the source: `~name` (the key
  `/template/<template>/<scope>/<name>`, the template being the page that is drawn
  and the scope the `id` of the call or of the nearest call above it that has one),
  a key with a slash in front, or `.field` of the element in a loop; `text="..."`
  stands for it. A source that resolves to nothing - a key nobody wrote, an empty
  text, a field there is no element for - draws nothing and the renderer is not
  called.
- A renderer has its template filled by `\Nino\Components::fill( $appData, $name,
  $args, $content, $tokens )`: the placeholders of the register and the tokens of
  the renderer's own, in one pass, where a token wins over a placeholder of the
  same name. Markup that stands only sometimes - a link round a title that has an
  address - is made by the renderer, escaped, and given as a token, so that the
  markup stays in the template and not in the class: the markup of a component
  is in its `template.tpl`, and no PHP builds it.
- What the renderer returns is finished: nobody reads it again. A `[` in
  `$args['value']` is a `[` and is no call, wherever it is written.
  `\Nino\Components::escape()` escapes for html and leaves the `[` as it is; a
  renderer that draws html+ of its own - the children of a container - draws it
  with `\Nino\Html::renderSource()` and `\Nino\Html::context()`, and writes every
  `[` of a value it puts into that text as `&#91;`. A renderer that throws a
  `\Exception` leaves its place empty, and the log says it once.
- `$context` says where the call stands: `template`, `scope`, `place` (the
  component it stands in directly: `section-col` for a menu in a column of the
  foot), `root` (the outermost component it stands in: `footer-wrap` for the same
  menu, `''` for a call on the page itself), the `element` and `fills` of a
  loop and `depth`. A renderer that draws itself differently in the head and in
  the foot reads `root`, not `place`, and a loop that draws its children hands
  its rounds the context it was given, `root` among the rest.
- A second text of a call - a caption, an alternative text - is an attribute of
  the type `text`: a reference (`~caption`, a key, `.field`) or the text itself,
  escaped. A component that has an attribute of the type `id` says where its own
  `~name` is read: `[section-wrap id="hero" image="~background"]` reads the
  background of the hero. A component of a loop - `'children' => 'nodes'` with a
  renderer - is handed what it holds and draws it as often as it needs;
  `\Nino\Components::sources()` answers which keys and pictures a source reads,
  for whoever scans a template.
- `'labels' => [ 'big' => 'Large' ]` in the attribute names what a tool shows for
  an option of a list (a text or a `locale => string` map); it is read from the
  schema `components()` hands out, and an option without one shows as its word.
- The `options` of a `choice` are a list, or the name of a static method of the
  feature's own class (`\Nino\Modules\<Directory>` or below it) that answers them
  where the project alone knows them - its menus, its forms:
  `'options' => '\Nino\Modules\Countdown::zones'`, a method
  `public static function zones( array &$appData, string $attribute ): array`
  that answers `value => label`, a label a string or a `locale => string` map. The
  feature's own class is not loaded to check the schema, and a method that is none
  is found out when it is asked: an answer that cannot be read is an
  `E_USER_WARNING` and the attribute a `string`, so **a renderer checks the value
  itself** where it names a record or a file.
- `variants` is the list of words of the one open axis of a component:
  `'variants' => [ 'card' ]` is `variant="card"`, the class `<name>--variant-card`
  (`regular` is always there and writes none). A theme may add words through the
  hook `/nino/components/variants`. `'labels' => [ 'card' => 'Card' ]` beside it
  says what a tool shows for a variant (a text or a locale => text map), a
  callback's answer may carry the same, and `\Nino\Components::variants( $appData,
  $name )` answers `[ 'standard' => ..., 'variants' => word => label or null ]`.
- A `ground` is the one attribute the kernel names itself: a choice among `none`,
  `base`, `alt`, `tint`, `primary` and `dark`, written as `<name>--ground-<word>`,
  which `Nino.css` answers for every component alike.
- `icon` and `preview` are for a tool and change nothing about the drawing. An
  icon is the name of one of the icons of the workbench - a Lucide name of
  `\Nino\Components::ICONS`, the table of `Nino.adminUi.icon()` -, which a tool draws
  before the component in a tree and an outline: `'icon' => 'columns-3'` for a
  column. A component without one has the neutral square, and so has one that names an icon the
  table has not: the schema stands, the icon is the neutral one and the log says so once - the
  components of the kernel's own feature name only icons of the table.
- A schema that cannot be worked with is refused: the component is not
  registered, the feature goes on without it and the log says why once - and the
  Features panel shows the reason. A name that is no slug, a key that is none of
  the schema's, an attribute without a default, a `choice` whose default is no
  option, a `group` that is missing or has no `groups/<group>/group.php`, a
  template that names a file.
- Three keys say what a component is to the kernel, which names no component of its own. `'block' => 'page'`, `'header'` or
  `'footer'` makes a container that stands in the page - it needs `offers` and an `in` that names `page` - the root of a block,
  of that side of a page: `\Nino\Blocks::wraps()` lists the roots, a block of such a root is read, checked and drawn like one of
  the feature Basis, its category has to be the side (`header`, `footer`, any other for the page) and a block of a header or a
  footer may read the texts of the project. `'markup' => true` says that the content of a container is the markup of the page
  itself: `offer()` and `check()` name its call with `\Nino\Components::CODE_HTML` and leave to the caller whether the account may
  write it. `'logo' => true`, for a component whose `source` is `image`, says that it draws the logo of the project, so that a header or a footer block may
  name the logo of the site as the first word of its call. A root is neither `markup` nor `through`. `'menu'` says that the
  component draws a menu of the site, so that the legal check counts a call of it as an output of that menu: `[ 'attribute' => 'menu' ]`
  names the attribute - a `choice` or a `string`, whose default is the menu where a call names none - that gives the menu of a call,
  `[ 'name' => 'legal' ]` the one menu a component draws whatever its call says; `\Nino\Components::menus()` reads it.
- A group and a component name belong to **one feature**. What a second feature
  claims is refused, with a sentence that names both, and that feature goes on
  without it.

## 2. A component with no renderer

A component with nothing to compute is a schema and a template. The template is
filled in one pass with `[[class]]`, `[[value]]`, `[[content]]` and
`[[<attribute>]]` for each attribute that is no class, and a fill of the site with
a slash in front, `[[/project/company/general/name]]`, is its text:

```php features/Countdown/components/note/component.php
<?php
declare(strict_types=1);

return [
	'group'    => 'countdown',
	'label'    => 'Note',
	'in'       => [ 'page', 'flow' ],
	'source'   => 'text',
];
```

```html features/Countdown/components/note/template.tpl
<p class="[[class]]"[[lang]]>[[value]] - [[/project/company/general/name]]</p>
```

`[note /template/page-home/launch/note]` is drawn as
`<p class="note">Soon - ACME</p>` for a key that holds `Soon`, with the name of
the company `ACME`. `[[lang]]` is ` lang="..."` where the text of the key is
another language's than the page's, and nothing where it is the page's. The class
`note` is the name of the component, a class of the group `countdown` like the other.

## 3. A component from a module

A project that has no feature registers a component from the `init()` of a
module of its own - `app/Project/DaysLeft/DaysLeft/DaysLeft.php`, listed in
`/nino/modules` - with `\Nino\Components::addComponent( $appData, $name, $schema,
$renderer )`. The schema is the same array, with the template in it (`'template'`)
since no folder holds one, and `group` is optional. It is checked by
`\Nino\Components::validate()`, and a schema that is none ends the request with `\Nino\Runtime::fail()` at
registration, nothing registered:

```php
// in init()
\Nino\Components::addComponent( $appData, 'days-left', [
	'label'      => [ 'en_US' => 'Days left', 'de_DE' => 'Tage bis' ],
	'in'         => [ 'page', 'flow' ],
	'source'     => 'text',
	'attributes' => [
		'size' => [ 'type' => 'choice', 'options' => [ 'regular', 'big' ], 'default' => 'regular' ],
	],
	'template'   => '<p class="[[class]]">[[days]]</p>',
], [ self::class, 'drawDaysLeft' ] );

public static function drawDaysLeft( array &$appData, array $args, string $content, array $context ): string {

	// $args['value'] is the text of the key, escaped; $args['class'] is the name and the classes the attributes make
	$text = html_entity_decode( $args['value'] );
	$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $text );

	if( $date === false || $date->format( 'Y-m-d' ) !== $text )
		return '';

	$days = max( 0, (int) ( new \DateTimeImmutable( 'today' ) )->diff( $date )->format( '%r%a' ) );

	return \Nino\Components::fill( $appData, 'days-left', $args, $content, [ '[[days]]' => (string) $days ] );
}
```

`[days-left /template/page-home/launch/date size="big"]`, for a key that holds a
date twelve days away, is `<p class="days-left days-left--size-big">12</p>`.
`addComponent()` **replaces** a component of the same name, which is how a project
changes the markup of one; a component that has no renderer needs a `template`,
and one whose `children` are `nodes` needs a renderer. A look for it is the
project's own `assets/style.css`.

## 4. Test it

Add the cases to the feature's own test,
`features/Countdown/tests/countdown-smoke.php`, which loads `tests/harness.php`:

```php features/Countdown/tests/countdown-smoke.php
<?php
declare(strict_types=1);

// The test of the Countdown feature. It runs against the Nino checkout three
// levels up, or the one NINO_ROOT names, in a project of its own that it
// creates and removes: php features/Countdown/tests/countdown-smoke.php
$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', dirname( __DIR__, 2 ) );
require $root. '/tests/harness.php';

$appData = ninoSandbox( 'countdown' );
$appData['/nino/modules'] = [ '\\Nino\\Modules\\Countdown' ];

// As \Nino\init() does it: the register reads the folders of the features that are on, then the modules run
\Nino\Html::init( $appData );
\Nino\Features::registerComponents( $appData );
\Nino\Modules::callModules( $appData, 'init' );
\Nino\Features::registerParts( $appData );

$ahead = date( 'Y-m-d', time() + 3 * 86400 );
\Nino\Html::addFills( $appData, [ '/template/page-home/launch/date' => $ahead, '/template/page-home/launch/never' => '2026-02-30' ], '*' );
// A template file ends with a line break, which the markup of the component keeps: the checks look at the markup
$draw = static fn( string $source ): string => trim( \Nino\Html::renderSource( $appData, $source, [ 'template' => 'page-home' ] + \Nino\Html::context( $appData ) ) );

check( 'the folders are read without a warning', ninoWarnings() === [] && \Nino\Features::componentProblems( $appData, 'countdown' ) === [] );
check( 'the component is registered in its group, which has a standard part', ( \Nino\Components::components( $appData )['countdown']['group'] ?? '' ) === 'countdown'
	&& ( \Nino\Components::groups( $appData )['countdown']['part'] ?? false ) === true );
check( 'the standard part keeps the rule of mixing', \Nino\Components::partProblems( (string) file_get_contents( dirname( __DIR__ ). '/groups/countdown/part/part.css' ), [ 'countdown', 'note' ] ) === [] );

check( 'it draws the days that are left, and the size as a class', $draw( '[countdown /template/page-home/launch/date]' ) === '<p class="countdown">3</p>'
	&& $draw( '[countdown /template/page-home/launch/date size="big"]' ) === '<p class="countdown countdown--size-big">3</p>' );
check( 'a size outside the options is the default, an attribute nobody declared falls away', $draw( '[countdown /template/page-home/launch/date size="huge" colour="red"]' ) === '<p class="countdown">3</p>' );
check( 'a date that is none, a key nobody wrote and a text with a call in it draw nothing',
	$draw( '[countdown /template/page-home/launch/never]' ) === '' && $draw( '[countdown /template/page-home/launch/nobody]' ) === '' && $draw( '[countdown text="[csrf]"]' ) === '' );
check( 'a fixed text is the source', $draw( '[countdown text="'. $ahead. '"]' ) === '<p class="countdown">3</p>' );

// The feature is off: its names are names nobody registered
$off = ninoSandbox( 'countdown-off' );
\Nino\Html::init( $off );
\Nino\Features::registerComponents( $off );
check( 'where the feature is off the call draws nothing, and the log says so once', \Nino\Html::renderSource( $off, '[countdown text="'. $ahead. '"]x[/countdown]', \Nino\Html::context( $off ) ) === ''
	&& count( ninoWarnings() ) === 1 );

ninoDone( $appData );
```

The cases are the ones every component has: the folders are read and the schema
is accepted, with no warning and no reason in `\Nino\Features::componentProblems()`;
the standard part keeps the rule of mixing, which `\Nino\Components::partProblems()`
answers with `[]` - the kernel asks it too, and a part that breaks it is left out of
the bundle; the component draws what it should, a value outside the options is the
default and an attribute nobody declared falls away; a source that is none, a key
nobody wrote and a text with a call in it (`[csrf]`, an editor's `[[/other/key]]`)
draw nothing and open nothing, wherever the renderer writes them; and with the
feature off the call draws nothing. A component in a loop is tested with
`[elements /meetups]` around a call that reads a field, `.date`, as its source.

Run the test, then the syntax checks and the static analysis of the
[agent guide](../../AGENTS.md#10-tests-and-validation):

```bash
php features/Countdown/tests/countdown-smoke.php
php tests/register-features-smoke.php
```

## Definition of done

- [ ] The schema validates: every attribute has a default, every choice an option list that holds it, a component of a feature its `group` and the feature the folder `groups/<group>/` with a `group.php`.
- [ ] The renderer is `component<Name>()` of the feature's class with the signature `( array &$appData, array $args, string $content, array $context ): string`, returns nothing it did not escape or make itself, and has its template filled by `\Nino\Components::fill()`.
- [ ] Nothing in `$args['value']` is escaped a second time, and nothing outside it is written unescaped. For an `image` the value is an escaped reference, to look the picture up by `$args['source']`, never a filename to write.
- [ ] A source that resolves to nothing renders nothing; a call with a wrong attribute still renders.
- [ ] The markup of the component is in its `template.tpl`, and the standard part keeps the rule of mixing (`\Nino\Components::partProblems()`).
- [ ] Registered one way: in folders by a feature, or by `addComponent()` in the `init()` of a module.
- [ ] The cases above are in a smoke test and the suite named in the guide passes.
