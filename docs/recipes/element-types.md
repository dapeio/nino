# Recipe: Define Element types for repeated content

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Templates Panel](../templates.md) · [Features](../features.md)

One of the seven extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


Use Elements when a section has two or more records of the same schema, when
records must be reordered/filtered/reused, or when each record has an image or
several fields. Use native textfills for one section-specific record.

## Admin-manageable model fields

The current Admin model editor supports these types:

```text
string, integer, double, boolean, array, date, datetime, image, element
```

Common model properties:

| Property | Applies to | Meaning |
| --- | --- | --- |
| `type` | all | Required storage type |
| `locale` | all | Store value per locale instead of globally |
| `required` | non-image | Reject missing value on insert |
| `html` | string | Allow sanitized rich inline HTML |
| `maxlength` | string | Editing limit/hint |
| `suffix` | non-boolean, non-image, non-element | Fixed UI unit such as `€` or `%` |
| `options` | supported controls, never element | Fixed choices presented by the editing UI |
| `width`, `height` | image | Required generated image dimensions |
| `elementType` | element | Required uri of the element type this field may reference |
| `multiple` | element | Int: the field holds an ordered list of references, capped at this number (`0` = uncapped). Absent = a single reference |

An image MUST NOT be required: the element must exist before its deterministic
upload path can be created.

An `element` field references another element. Its value is that element's full
uri (`/<type>/<slug>`) — exactly what `\Nino\Elements::getElement()` takes, so a
template never re-joins it with the model. Both element forms render it as a
select of the referenced type's elements, and the kernel rejects a value that
points outside `elementType`. `elementType` is mandatory: `insertElementType()`
drops a field without one, and the Admin editor refuses to save a reference to a
type that does not exist. A target deleted later is tolerated — the stored value
survives and both forms mark it as missing. Element references never enter a
Translations export: a uri is a choice, not translatable text.

Adding `multiple` makes that reference a list. Presence of an **int** is the
switch, the same shape `autoincrement` uses on a type: `0` is uncapped, a
positive number is the ceiling, and an absent key is the single reference every
model written before this still means. `Elements::isMultiElement()` is the one
place that rule lives; `Nino.adminUi.isMultiElement()` is its client-side twin.

The value is then a php list of those same uris, so `_expectedGettype()` answers
`array` for the field and the kernel checks every entry against `elementType`,
rejects a duplicate, enforces the cap, and stores the result through
`array_values()` — a template iterating the value sees the order, never the keys
a partial removal left behind. The cap is enforced in the kernel rather than in
the form that drew the list: an api caller never went near that control.

Both element forms swap the select for `Nino.adminUi.elementList()`, the shared
multi-reference control (chosen entries with move/remove, plus a search field
over the options the form already loaded). It owns no strings — both element
forms pass `Nino.content.getText()` lookups. Pass `ordered: false` for a value
that is a set rather than a list: the move buttons go away, because offering
them says the order carries meaning. The Roles tab's permission picker is that
mode. Do not restate it in a tool; see §6a's trap about tool copies of shared
components.

## Named or numbered element uris

An element is addressed as `/<type>/<slug>`. By default the slug is supplied by
whoever adds the element, which is right when the entry has a name worth putting
in a url (`/team/ada`). A type whose entries have no such name - a gallery image,
a price row - carries one extra top-level key next to its `title`:

```php
'autoincrement' => 1,   // the next number to hand out
```

Presence of an **int** is the switch; anything else means the type names its own
elements. `/_admin`'s type editor sets it, and the element form then stops asking
for a uri: inserting with an empty slug (`insertElement( $appData, '/gallery/',
… )`) allocates the next number, zero-padded to `Elements::AUTOINCREMENT_PAD`.

- Allocation happens inside `_writeElementData()`'s existing `mutate()`, so the
  counter is read and written under the same lock as the element - two
  simultaneous inserts cannot be handed the same number.
- The counter is **stored, not derived**. Deleting the newest entry does not free
  its number: a uri is a public address, and repointing an old one at a different
  element is worse than a gap.
- `autoincrementSeed()` is re-checked on every allocation as a floor, so a
  hand-written or imported `/gallery/00042` is never overwritten by the counter
  catching up.
- An explicit slug still works on a numbered type. Numbering is what the tools
  offer, not a restriction on the kernel - an import or migration can still name
  an entry.
- `Elements::getAutoincrement()` / `readAutoincrement()` answer whether a type
  numbers its elements and what is next; `autoincrementUri()` formats one number.

A type file's top level therefore holds plain values as well as data buckets.
Anything walking it must skip non-arrays (`is_array( $bucketData )`), the way
`rawBuckets()`, `_writeElementData()` and `_cacheElement()` do.

The lower-level Elements API also understands advanced metadata such as
defaults, callbacks, whitelist, and blacklist. The current Admin schema editor
does not preserve every advanced key when it rewrites a model. Use only the
Admin-manageable shape for types intended to be edited there, or extend the
Admin editor and tests together.

`options` and `maxlength` primarily constrain the management interface; do not
treat client controls as a security boundary. When an enum or length is a
runtime invariant, validate it in the owning server-side operation too.

## Complete type file

An installable `services.php` can contain:

```php
<?php
declare(strict_types=1);

return [
	'title' => 'Services',
	'model' => [
		'title' => [
			'type' => 'string',
			'locale' => true,
			'required' => true,
			'maxlength' => 180,
		],
		'description' => [
			'type' => 'string',
			'locale' => true,
			'html' => true,
			'maxlength' => 2000,
		],
		'linkLabel' => [
			'type' => 'string',
			'locale' => true,
			'maxlength' => 120,
		],
		'link' => [
			'type' => 'string',
			'maxlength' => 500,
		],
		'image' => [
			'type' => 'image',
			'width' => 1200,
			'height' => 800,
		],
		'featured' => [
			'type' => 'boolean',
		],
	],
	'*' => [
		'*' => [],
		'strategy' => [
			'link' => '/services/strategy',
			'featured' => true,
		],
	],
	'en_US' => [
		'strategy' => [
			'title' => 'Strategy',
			'description' => 'A clear foundation for informed decisions.',
			'linkLabel' => 'Explore strategy',
		],
	],
	'de_DE' => [
		'strategy' => [
			'title' => 'Strategie',
			'description' => 'Eine klare Basis für fundierte Entscheidungen.',
			'linkLabel' => 'Strategie entdecken',
		],
	],
];
```

Data layout:

- `title` describes the type itself.
- `model` defines all fields.
- `'*']['*']` holds global defaults.
- `'*'][<id>]` holds global fields for a record.
- `<locale>[<id>]` holds localized fields for that same record.

An Element exists if it has global or localized data. It does not need a
duplicate full record in every bucket.

New type slugs SHOULD match `^[a-z][a-z0-9_-]*$`. New record IDs accepted by
Admin match `^[A-Za-z0-9][A-Za-z0-9_-]*$` and contain no slash. Prefer
lowercase hyphenated IDs.

## Render Elements

```html
[elements /services limit="6" query="featured=1"]
<article id="service-[[.id]]" class="nino-article">
	<img
		class="nino-article-img"
		src="[[/nino/dir]]/images/[[image]]"
		alt="[[title]]"
	>
	<div class="nino-article-content">
		<h2 class="nino-article-title">[[title]]</h2>
		<div class="nino-article-descr">[[description]]</div>
		<a class="nino-btn nino-btn--primary" href="[[link]]">
			[[linkLabel]]
		</a>
	</div>
</article>
[/elements]
```

- `[[.id]]` is the internal record ID.
- `[[field]]` is local to the current Elements block.
- normal field output is HTML-escaped;
- `html: true` fields are sanitized by the Elements renderer;
- `limit` bounds output;
- `query` matches model values and supports the existing percent wildcard
  forms.

Do not use absolute `[[/page-*]]` fills for per-record data. Do not use local
`[[title]]` outside an Element block.

## Render one field's distinct values

`[elements]` loops records. Nothing loops the *values* a field takes across a
type's elements - which a client-side category filter's button row needs
(one button per distinct `category`, not per record). `[elementvalues]`
answers that, built on `\Nino\Elements::queryElementValues()`:

```html
[elementvalues /services key="category" sort="value"]
<button type="button" class="nino-filter-btn" data-filter-value="[[.value]]">
	[[.value]] <span class="nino-filter-count">([[.count]])</span>
</button>
[/elementvalues]
```

- `key` (required) is the model field to enumerate; a missing field or type
  renders nothing.
- The value set is a hybrid: a field with declared `options` (see "Admin-manageable
  model fields" above) returns
  them in model order, each with how many current elements carry it,
  including a count of `0`; a field without `options` falls back to the
  values actually observed, still with counts. Either way `query` and
  `locale` scope the counted collection exactly like `[elements]`.
- A `count: 0` value is hidden by default - a filter button that matches
  nothing is not a useful default. Pass `includeEmpty="1"` to show it anyway.
- `sort` is `value` (alphabetical, the default), `count` (most-used first),
  or `declared` (the order `queryElementValues()` itself returns: declared
  options, then any other observed value).
- `limit` and `callback` behave like `[elements]`.
- `[[.value]]` and `[[.count]]` are the only local fills, plus `[[.id]]` as
  the 0-based iteration index. `[[.value]]` is escaped the same way a normal
  Element field is.

`[elementvalues]` and the `[elements]` loop it filters MUST read the same
collection, or the buttons match nothing. Inside a Section Library preset do
not write that slug by hand: a Layout MAY use the compile token
`[[section:collection:<areaKey>]]`, which resolves to the collection the named
Elements Area is actually bound to - the auto-generated
`<page>-<section>-<area>` of a new Area as readily as a type picked under Edit
Section → Data, so the pair stays correct on the very first insert and after
any later rebind. The token names a declared Elements Area of the same preset;
anything else is refused at manifest load. `_nino/Nino/Modules/Templates/library/
filterable-grid/` is a complete worked example: a static block (§10.3a) pairs
`[elementvalues]` with an Elements Area whose `item.data` stamps each card
with its own field value per §10.3.

Outside a preset - an ordinary hand-written page template - there is no such
token and no Area to follow, so both loops simply name the same collection.

## Search indexed Elements

`\Nino\Modules\Search` is a feature from the catalogue
[dapeio/nino-features](https://github.com/dapeio/nino-features), copied into
`features/Search/`: it is switched on in the workbench's Features panel, or by
listing its class by hand, and its fields are configured manually. Do not add it to the wizard or infer its
fields from templates:

```php
'/nino/modules' => [
	// ...
	'\\Nino\\Modules\\Search',
],
'/nino/elements/index' => [
	'articles' => [
		0 => 'title',
		1 => 'summary',
		2 => 'keywords',
		3 => 'author',
	],
],
```

The outer key is one flat Element type. Priorities are integer keys `0` through
`3`, strongest to weakest, and each names one existing model field. The public
read API is:

```php
$hits = \Nino\Modules\Search::getElements( $appData, 'articles', $query );
```

It searches only `Locales::getCurrentLocale()` and returns canonical Elements,
not index rows or scores. Every normalized query token has to match; priorities
rank the hits.

Lifecycle is deliberately small and explicit:

- module `init()` only registers `/nino/elements/committed` and performs no I/O;
- the Search panel's **Create searchindex** under `/_admin` rebuilds every
  configured type on every press;
- a committed insert, update, or delete rebuilds that configured type;
- one type owns exactly `/data/index-<type>.php`;
- reads of a missing or malformed file return `[]` and never self-heal;
- stale files for no-longer-configured types are inert because the read API
  checks the current configuration.

Search indexes are the explicit exception to the general mutable-file rule in
the agent guide. They are expendable derived data and use one direct, non-atomic
full-file write: no `Filesystem::mutate()`, temporary rename, sidecar lock,
signature, or revision. Keep that contract visible in code and docs. Test it in
the feature's own test, `features/Search/tests/search-smoke.php` in the
catalogue, including
inactive/active module behavior, all-index
Admin rebuilds, current-locale search, weighted fuzzy ranking, committed-write
refresh, missing/malformed reads, and write-failure reporting.

## Element changes and translations

When saving a record with global and localized fields:

- write global fields to locale `*`;
- write each locale's fields to its own locale bucket;
- do not copy the last edited locale over all others;
- preserve omitted fields during partial update;
- sanitize rich fields server-side;
- and use the public `Elements` methods so callbacks, type checks, locks, and
  cache invalidation run.

Changing an existing field between global and localized is a data migration.
Admin preserves values using the native locale/fallback rules. A model patch
must test the migration in both directions and must not leave stale locale
values that override the new shape.

## Element tests

Use `tests/kernel-smoke.php` for model/runtime behavior,
the catalogue's `features/Search/tests/search-smoke.php` for the derived
search-index contract,
`tests/admin-smoke.php` for type CRUD and migrations, and the relevant JS tests
for forms/uploads. Test:

- valid and invalid field types;
- required zero/false values;
- required string/array rejection;
- image dimensions and post-create upload;
- rich-text sanitization;
- global/localized merge in at least two locales;
- partial updates preserving other locale fields;
- global-to-locale and locale-to-global migration;
- query with multiple keys and wildcard forms;
- concurrent writes;
- image replacement/cleanup without deleting unrelated files;
- and rendering/escaping of local placeholders.
