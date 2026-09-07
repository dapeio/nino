# Recipe: Write templates and installable page units

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Templates Panel](../templates.md) · [Features](../features.md)

One of the seven extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


Nino templates are HTML+ files. They contain HTML, textfills, and shortcodes,
but no PHP. A route renders a template; the template itself does not create the
route.

## Template kinds and filenames

| Kind | Filename | Purpose |
| --- | --- | --- |
| Page template | `page-services.tpl` | Complete route body, listed by Template Builder |
| Reusable shell | `html-header.tpl` | Header/footer/layout include |
| Reusable section | `section-newsletter.tpl` | Shared content included in pages |
| Mail template | `mail-owner.tpl` | HTML email structure |
| Locale variant | `page-legal.de_DE.tpl` | Structure that truly differs per locale |

Only `templates/page-*.tpl` files appear as editable documents in
the Templates panel. Do not prefix reusable includes with `page-`.

Include a template without its `.tpl` extension:

```html
[template /templates/html-header]
```

Use project-root-relative include paths. Do not use `../`, an absolute
filesystem path, a URL, or a request-derived filename.

## Modern page-template frame

New page templates SHOULD use explicit metadata and shell slots:

```html
<!-- nino:template-name Services -->
<!-- nino:template-vpa on -->
<!-- nino:template-slot header -->
[template /templates/html-header]
<section id="services-intro" class="nino-section">
	<div class="nino-grid-row nino-grid-middle">
		<div class="nino-grid-100 nino-grid-m-50">
			<h1 class="nino-section-title">
				[[/page-services/services-intro/title]]
			</h1>
			<p class="nino-section-subtitle">
				[[/page-services/services-intro/subtitle]]
			</p>
			<div>
				[[/page-services/services-intro/description]]
			</div>
			<a
				class="nino-btn nino-btn--primary"
				href="[[/page-services/services-intro/cta-uri]]"
			>
				[[/page-services/services-intro/cta-label]]
			</a>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-img-cover">
			[image /page-services/services-intro/image alt=""]
		</div>
	</div>
</section>
<!-- nino:template-slot footer -->
[template /templates/html-footer]
```

A page template may deliberately contain no `<section>` at all. Metadata plus
header/footer slots is the correct blank starting state. Do not insert a dummy
section merely to make the canvas non-empty.

Metadata MUST be the first source in the file and has exact syntax:

```html
<!-- nino:template-name Human readable name -->
<!-- nino:template-vpa on -->
```

`nino:template-vpa` accepts only `on` or `off`.

A display name:

- contains `1..160` bytes;
- contains no control character, `<`, or `>`;
- and does not contain `--` because it lives in an HTML comment.

The filename accepted by the Template Builder matches:

```regex
^page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl$
```

and MUST NOT contain `..`. Prefer lowercase hyphenated filenames despite the
broader compatibility pattern. The builder derives the page ID from the part
after `page-`, normalizes unsupported characters to hyphens, and prefixes
numeric IDs with `p-`. A simple filename avoids surprising fill paths.

## Header and footer slots

The marker and the following include form one fixed slot:

```html
<!-- nino:template-slot header -->
[template /templates/html-header]
```

`None` is represented by the marker with no following include:

```html
<!-- nino:template-slot header -->
```

The same applies to `footer`. There is one marker for each slot, no closing
marker, and no duplicate slot.

Allowed selected include paths match:

```regex
^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$
```

or an empty string for `None`.

Exact top/bottom `[template /templates/html-header]` / `html-footer` includes
are a recognized shell convention. They receive explicit markers on the next
deliberate Builder save.

Header/footer slots are not regular movable canvas sections. Other standalone
`[template /templates/name]` shortcodes remain normal movable components.

## What the Template Builder can edit

The parser treats:

- each complete top-level `<section>...</section>` as one component;
- each standalone `[template /templates/name]` line as one component;
- marked header/footer includes as fixed slots;
- and everything else as locked raw source.

A template shortcode nested inside a section remains part of that section. A
template shortcode inside another raw DOM parent remains locked raw source.
Section-like strings inside comments, scripts, styles, textareas, or PHP-like
text are not promoted.

The Builder preserves locked raw segments byte-for-byte and rejects a save that
changes them. Do not loosen this invariant by parsing and reserializing the
whole document with a browser DOM.

Each editable top-level section SHOULD have a unique semantic ID:

```html
<section id="services-overview">
```

The Builder rejects duplicate non-empty section IDs. Composer-created IDs match
`^[a-z][a-z0-9-]*$`. Use that form for hand-authored sections too.

Only Composer-created sections have a valid
`<!-- nino:section {...} -->` comment and can reopen their exact wizard
settings. Do not invent the JSON manually. A hand-authored top-level section is
still movable and editable through the HTML+ escape hatch.

## Textfill path design

For section-owned native content, use:

```text
/page-<pageId>/<sectionId>/<semantic-suffix>
```

Examples:

```text
/page-home/main-hero/title
/page-home/main-hero/subtitle
/page-home/main-hero/cta-label
/page-services/services-overview/description
```

Rules:

- page and section segments are stable lowercase slugs;
- the suffix describes meaning, not position or HTML tag;
- use `title` rather than `h2` and `description` rather than `left-p-1`;
- use `cta-label` and `cta-uri` as a pair;
- do not put visible text directly in markup when editors must translate it;
- keep structural class names and technical configuration out of locale text.

General page metadata is separate:

```html
[[/webpage[[/nino/http/response/uri]]/name]]
[[/webpage[[/nino/http/response/uri]]/title]]
[[/webpage[[/nino/http/response/uri]]/description]]
```

The route's internal URI determines these values. Do not hardcode metadata to a
library folder when a page can be mounted at another internal URI.

A page's reachable path is available the same way, so one page can link to
another without repeating a path the Webpages step can change:

```html
<a href="[[/webpage/site-contact/uri]]">[[/webpage/site-contact/name]]</a>
```

The wizard's Routes step and the Routes panel both write that key whenever they save a page: keyed
by the internal URI like the other three, valued with the page's Http-URI, in
`text/global.php` because an entry has one Http-URI for every locale, and
blacklisted as a technical value. Menus still come from `[navigation]`, which
reads the routes directly - this key is for a deliberate single link.

## Images

A native section image is an image slot:

```html
[image /page-services/services-intro/image alt=""]
```

Register and populate that slot through the existing Images APIs/tools. Do not
construct its generated filename.

Inside an Elements loop, an image field contains a filename relative to
project `images/`:

```html
<img
	src="[[/nino/dir]]/images/[[image]]"
	alt="[[title]]"
	loading="lazy"
>
```

Do not use the removed/incorrect `uploads` path. Let the image tooling create
dimensions and format-specific filenames.

Alt text must fit the image's purpose. Decorative images use empty alt text;
meaningful Element images SHOULD have a dedicated localized alt field when the
title is not a truthful substitute.

## Forms and interactive templates

A state-changing form needs:

```html
<form class="nino-form" action="[[/nino/dir]]/.feature" method="post">
	[csrf]
	<label for="feature-email">[[/feature/label/email]]</label>
	<input
		id="feature-email"
		name="email"
		type="email"
		required
	>
	<input
		name="location"
		type="text"
		class="nino-sr-only"
		tabindex="-1"
		autocomplete="off"
		aria-hidden="true"
	>
	<p class="nino-form-message" aria-live="polite"></p>
	<button type="submit" class="nino-btn nino-btn--primary">
		[[/feature/label/submit]]
	</button>
</form>
```

The class names alone do not submit or secure a form. A matching runtime module
must own the endpoint, validate it, and return a documented response.

For JS-enhanced controls:

- start with meaningful HTML;
- reuse the existing `nino-*` hooks `Nino.ui.js` looks for, and existing ARIA patterns;
- make touch scrolling and keyboard operation possible;
- avoid trapping focus or pointer gestures;
- and keep preview inert.

## Installable page unit

Create:

```text
_admin/install/library/pages/services/
├── manifest.php
├── images/
│   └── services-hero.jpg
├── templates/
│   └── page-services.tpl
├── text/
│   ├── en_US.php
│   └── de_DE.php
└── services.php              # optional Element type source
```

Example `manifest.php`:

```php
<?php
declare(strict_types=1);

return [
	'label' => 'Services',
	'requiresModules' => [ 'navigation' ],
	'routes' => [
		'GET://services' => [
			'uri' => '/services',
			'body' => '[template /templates/page-services]',
			'navs' => [
				'main' => 5,
				'footer' => 5,
			],
		],
	],
	'templates' => [
		'page-services.tpl',
	],
	'elementTypes' => [
		'services.php',
	],
	'files' => [
		'images/services-hero.jpg',
	],
];
```

Current page-unit consumers recognize:

- `label`;
- `requiresModules` using installer module slugs;
- `routes`;
- `templates`, including locale-keyed entries;
- `elementTypes`;
- `files`, copied from the unit to the same project-relative virtual path;
- `blacklist`;
- and `text/global.php` plus selected `text/<locale>.php`.

Every declared `files` entry MUST be a unit-relative file or directory that
exists. Tests should assert its observable copied output, not merely the
manifest entry.

Normally one page unit has one route. The wizard uses the route body to
identify its library unit and lets the developer change:

- its public HTTP URI;
- its internal Element/page URI;
- template choice;
- navigation memberships/order;
- status code;
- and per-locale name, title, and description.

The manifest values are starting suggestions, not immutable route identity.

For a 404 page include:

```php
'statusCode' => 404,
```

Do not add the same route in `config.php`, a module `init()`, and a page
manifest. Choose one owner.

## Page text suggestions and real content

`text/en_US.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/webpage/services/uri]]' => '/services',
	'[[/webpage/services/name]]' => 'Services',
	'[[/webpage/services/title]]' => 'Services built around your goals',
	'[[/webpage/services/description]]'
		=> 'Strategy, design and implementation from one team.',

	'[[/page-services/services-intro/title]]'
		=> 'Useful work, clearly delivered',
	'[[/page-services/services-intro/subtitle]]'
		=> 'From the first idea to a maintainable website.',
	'[[/page-services/services-intro/description]]'
		=> 'Choose the support that fits the current stage of your project.',
	'[[/page-services/services-intro/cta-label]]'
		=> 'Start a conversation',
	'[[/page-services/services-intro/cta-uri]]'
		=> '/contact',
];
```

`text/de_DE.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/webpage/services/uri]]' => '/services',
	'[[/webpage/services/name]]' => 'Leistungen',
	'[[/webpage/services/title]]' => 'Leistungen für klare Ziele',
	'[[/webpage/services/description]]'
		=> 'Strategie, Design und Umsetzung aus einer Hand.',

	'[[/page-services/services-intro/title]]'
		=> 'Sinnvolle Arbeit, klar umgesetzt',
	'[[/page-services/services-intro/subtitle]]'
		=> 'Von der ersten Idee bis zur wartbaren Website.',
	'[[/page-services/services-intro/description]]'
		=> 'Wähle die Unterstützung, die zur aktuellen Projektphase passt.',
	'[[/page-services/services-intro/cta-label]]'
		=> 'Gespräch beginnen',
	'[[/page-services/services-intro/cta-uri]]'
		=> '/contact',
];
```

The four `/webpage/<library-slug>/*` keys are suggestions read into the
installation form. They are stripped from the page fragment during merge.
The wizard writes the actual metadata under the internal URI chosen for that
page instance - `name`, `title` and `description` per locale, and `uri` once in
`text/global.php`.

All other keys are ordinary project content and are merged into the target text
files.

A Webpages entry has one public HTTP URI. Locale fragments may technically
suggest different URI strings, but only one suggestion can win for that entry;
this does not create localized routes. Prefer the same URI in all locale files
unless the route architecture deliberately handles localized URLs.

## Locale-specific structural templates

Prefer one template plus translated textfills. If structure itself must differ,
use locale-keyed files:

```php
'routes' => [
	'GET://legal' => [
		'uri' => '/legal',
		'body'
			=> '[template /templates/page-legal.[[/nino/http/response/locale]]]',
		'navs' => [ 'footer' => 5 ],
	],
],
'templates' => [
	'de_DE' => 'page-legal.de_DE.tpl',
	'en_US' => 'page-legal.en_US.tpl',
	'html-footer-legal.tpl',
],
```

Use this only when markup—not merely wording—differs. Every supported locale
must resolve to an existing filename, and an omitted locale-gated file must not
leave a broken include.

## Page/template tests

Extend `tests/install-smoke.php` for a new page unit and
`tests/templates-smoke.php` for Builder contracts. Test:

- unit label, suggested URI, metadata in every shipped locale;
- exact template and Element-type copies;
- required module activation;
- generated route body, internal URI, public URI, navs, order, status;
- reapply behavior and survival of unrelated routes;
- page filename/display name/page ID/VPA listing;
- header/footer slot parsing, including `None`;
- one or more valid top-level components;
- unique section IDs;
- locked raw source byte preservation;
- include paths and absence of PHP;
- every referenced textfill/image/Element type is provided or intentionally
  created later;
- and a rendered request reaches the expected template.

Run:

```bash
php -l _admin/install/library/pages/services/manifest.php
php -l _admin/install/library/pages/services/text/en_US.php
php -l _admin/install/library/pages/services/text/de_DE.php
php tests/install-smoke.php
php tests/templates-smoke.php
node tests/templates-js-smoke.js
```
