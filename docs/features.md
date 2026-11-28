# Developing a feature

This manual is for someone who writes a feature: a directory below `features/` that
brings a module - and a panel and an install unit, when it needs them - to a Nino
project. The contract is `\Nino\Features` (`_nino/Nino/Features/Features.php`),
checked by `tests/features-smoke.php` against `tests/fixtures/features/`; the
docblock of each member is its reference. The catalogue's `Hello`
([dapeio/nino-features](https://github.com/dapeio/nino-features)) is the complete
example, written to be copied. The operator's side, the Features panel and the
catalogue, is in [the workbench manual](_admin.md#features).

## What a feature is

A feature is an installable package: one directory below `features/` with the
module's class, a workbench panel when it has one, an install unit with templates
and texts, and a manifest `feature.php`. A checkout ships none: one is dropped in or
installed from the signed catalogue and switched on in the workbench, and the
project holds it with a version, settings in `config.php` and files under `data/`.

| Kind | Where | Switched on by |
|---|---|---|
| **Kernel module** | `_nino/Nino/Modules/<Name>/`, Nino's own | the setup wizard, or `/nino/modules` by hand |
| **Feature** | `features/<Name>/` with `feature.php`: from outside, held by the project | the Features panel, or an application of the setup wizard |
| **Project module** | `app/<Vendor>/...` under a namespace of its own, the project's | `/nino/modules` by hand |

"Module" is a class listed in `/nino/modules` whose `init()` the kernel calls;
"feature" is the package that delivers one. The directory is the class name:
`features/Hello/Hello.php` is `\Nino\Modules\Hello`,
`features/Hello/Admin/Admin.php` its panel `\Nino\Modules\Hello\Admin`. It matches
`/^[A-Z][A-Za-z0-9]*$/`, and only `feature.php` and the class file are required. The
autoloader serves `Nino\Modules\*` from `_nino/`, `_admin/`, `features/` and `app/`
in this order, so a feature never shadows a kernel module or a workbench screen and
`app/` never replaces a feature ([autoload
layout](recipes/runtime-module.md#autoload-layout)); for the same reason a manifest
whose directory is named like a module of the kernel or of the workbench - a
directory `Nino/Modules/<Name>` in `_nino/` or `_admin/`: `Navigation`, `Elements`,
`Users`, `Logs` - is refused by a **1.9** kernel, the name being taken: the class below it, a panel's
`\Nino\Modules\<Name>\Admin` among them, would be the kernel's. `NINO_FEATURES_DIR`, defined
before `_nino/Nino.php` loads (the entry points carry the line commented out),
replaces the directory as a whole.

A set of features that only works together is offered as an **application**: an
entry of the catalogue's index (`applications`), not a package of its own - a
key, a name and a description per language, the keys of its features, a `next`
path to continue in and a `start` that says who provides the start page
([the catalogue](_admin.md#the-catalogue)). The setup wizard offers the
applications of the official catalogue and places and switches on their features
([Applications](setup.md#5-application)), and so does the Features panel's
**Applications** tab later, for the catalogue the project reads
([the panel](_admin.md#the-panel)); a feature does not know of the applications
it is part of, and setting one up changes no page or route of the project.

`features/` is server-side source: `.htaccess` and `router.php` deny it
([deployment](deployment.md#nginx-and-other-web-servers)), and a feature's browser
files are read off disk and bundled, never requested. A catalogue publishes one
directory per feature as a `.tar.gz` that its signed catalogue lists by digest, its
`tests/` left out ([a catalogue of your own](_admin.md#a-catalogue-of-your-own)).

## The manifest

`feature.php` lies beside the class file and returns an array;
`\Nino\Features::manifest()` reads and validates it. A manifest that breaks a rule
is skipped whole, with a warning that names the file and the reason, and so is a
second directory that claims a key an earlier one holds; `\Nino\Features::rejected()` answers which directories, with the reason, and the Features panel lists them under **Inactive**, as text, for an account that may manage features. The one of `Hello` is
`features/Hello/feature.php`:

```php features/Hello/feature.php
<?php
// The class is not declared: features/Hello/ can only serve \Nino\Modules\Hello
return [
	'key'					=> 'hello',
	'name'				=> [ 'en_US' => 'Hello World', 'de_DE' => 'Hallo Welt' ],
	'description'	=> [
		'en_US' => 'Hello World lets you greet a name that its panel stores. It is the example a new feature takes its shape from.',
		'de_DE' => 'Hallo Welt ermöglicht es, einen Namen zu grüßen, den sein Panel speichert. Es ist das Beispiel, an dem sich ein neues Feature in der Form orientiert.',
	],
	// What the Description tab shows: all six sections, the empty ones too
	'manual'			=> [
		'shortcodes' => [
			'[hello name="Ada" shout]' => [
				'en_US' => 'Greets Ada, in capitals.',
				'de_DE' => 'Grüßt Ada, in Großbuchstaben.',
			],
		],
		'markup' => [],
		'routes' => [
			'/hello' => [
				'en_US' => 'The page of the greeting.',
				'de_DE' => 'Die Seite des Grußes.',
			],
		],
		'panel' => [
			'Hello World' => [
				'en_US' => 'Sets who the shortcode greets.',
				'de_DE' => 'Legt fest, wen der Shortcode grüßt.',
			],
		],
		'callbacks' => [],
		'install' => [
			'templates/page-hello.tpl' => [
				'en_US' => 'The template the page /hello renders.',
				'de_DE' => 'Das Template der Seite /hello.',
			],
			'elements/privacy.php' => [
				'en_US' => 'Brings its section of the privacy policy.',
				'de_DE' => 'Bringt den Abschnitt der Datenschutzerklärung mit.',
			],
		],
	],
	'category'		=> 'system',				// one of \Nino\Features::CATEGORIES
	'maturity'		=> 'Example',				// the badge beside the name, 24 characters at most
	'version'			=> '2.0.0',
	'nino'				=> '^2.0',					// the kernel whose vocabulary it uses
	'data'				=> [ '/data/hello.php' ],					// what a backup carries
	'settings'		=> [
		'greeting'	=> [ 'type' => 'string', 'label' => 'Greeting', 'default' => 'Hello', 'maxlength' => 24 ],
		'longest'		=> [ 'type' => 'int', 'label' => 'Longest name', 'min' => 1, 'max' => 60, 'unit' => 'characters', 'default' => 40 ],
	],
];
```

| Key | Rule |
|---|---|
| `key` | a slug (`/^[a-z][a-z0-9-]*$/D`), the lower-cased directory name where left out; `requires` and `/nino/features` name features by it |
| `name`, `description`, `label`, `hint`, the label of an `options` entry | a string or a `locale => string` map, `name` required: the panel shows features that are not active, whose fills are not loaded, so the words travel in the manifest. `\Nino\Features::localized( $value, $locale )` reads one: the locale asked for, else `en_US`, else the first entry, else `''` |
| `version`, `maturity` | `major.minor.patch`, a pre-release suffix allowed; a string or a `locale => string` map of at most 24 characters each |
| `nino` | the constraint on the kernel, `*` where left out: the floor, the kernel whose vocabulary the feature uses. A feature written for this kernel says `^2.0`: the register of components, the folders of a feature and everything this manual describes are that version's, and a kernel of the 1.x line refuses it, as this one refuses a feature that says `^1.8`. The marks **1.8**, **1.9** and **1.12** in the tables below say in which version a member came and are no floor a feature of this kernel needs. The vocabulary is that of `\Nino\Features::satisfies( $constraint, $version )`: `*`, a version (`1.2.3`, `1.2`, `1`), a comparison (`>=1.2`, `!=1.2.3`), a caret (`^1.0`: the same major version from there on; `^0.13` the same minor version) or tilde (`~1.2`: the same major version from 1.2; `~1.2.3` the same minor version from 1.2.3), parts joined by a comma or a space (all must hold) and alternatives joined by `\|\|`; a pre-release kernel counts as the release it precedes |
| `php`, `requires`, `data` | `php` => `ext`, the extensions it needs; the feature keys it cannot run without (its own is dropped; an activation activates them first); the files under `/data/` it owns, no `..` - what a backup carries. `requires` is also what a feature stands on: the demo of its groups may call the components of the features it requires and of those they require, and nothing of a feature it does not name (the [demo](development.md#a-features-components)); its components, groups and blocks are read after those of the features it requires, so that what they name first stays theirs; and a circle of requirements - two features that require each other - throws nothing, is switched off one feature after the other, and is said after the description of each feature in it in the Features panel |
| `assets`, `settings` | the subsections below and the chapter [Settings](#settings); the components of a feature are no key of the manifest, they are [folders](#components-in-folders) |
| `module` | may name `\Nino\Modules\<Directory>` and nothing else, and the class file must exist |

Keys the kernel does not read are ignored, so a decorative key is not documentation.
`\Nino\Features::all()` answers every manifest by key, with `dir`, `module` and what
this installation knows: `active`, `installed`, `update` and `problems`.

### Assets

Style sheets and scripts for the website are listed under `assets` as virtual paths
below the feature's own `assets/` directory - a `.css` or a `.js` of a plain
relative name, no `..`; an entry that breaks one refuses the manifest. While the
feature is listed in `/nino/modules`, `\Nino\Features::registerAssets()` adds every
`.css` to the style bundle and every `.js` to the script bundle at every boot, ahead
of any module's `init()`, once each and in the order listed. A file for another
bundle goes through `\Nino\Html::addAsset()`.
A bundle that is loaded by the pages that need it and no other - the form module's
stylesheet and script are one - follows the style bundle or the script bundle:
`\Nino\Modules\Assets::follow()` in the `init()` and `\Nino\Modules\Assets::need()` on
the page that uses it, see [Assets Are Not Templates](development.md#assets-are-not-templates).
The form of a feature needs neither: the `[csrf]` of its markup says that the page has
a form, and the module Form links its stylesheet and its script, and carries the words
of the script, for that page - see [Forms](development.md#forms). A feature that draws
a form without `[csrf]` calls `\Nino\Modules\Form::draws( $appData )`.

A bundle has one order, whichever file it holds and whoever adds it: the kernel's
files first (`Nino.css`, `Nino.js`), then what features and modules add, in the order
they add it, then the project's own files - the ones below `/assets/`
(`private/assets/`), `style.css` among them, and a `theme.css` where the project has one - in the order
`/nino/html/assets` lists them. For the stylesheet that is `Nino.css`, the features,
`theme.css` where there is one, `style.css`.

What decides between two rules of the stylesheet is not that order but four layers.
The bundle begins with `@layer nino.reset, nino.components, nino.theme, project;` and
holds every file in a block of its layer:

| Layer | What stands in it |
|---|---|
| `nino.reset` | `Nino.css`: the tokens on `:root`, the contract of the grounds, the reset and the bare elements |
| `nino.components` | the stylesheets of features and modules, the standard parts of the groups of a feature among them |
| `nino.theme` | the project's `assets/theme.css`, a file of its own: none is delivered |
| `project` | every other file of the project's `assets/`, `style.css` among them |

A rule in a later layer overrules a rule in an earlier one whatever its selector says
and whichever file comes first, so a rule of the theme or of the project's own
`style.css` overrules a rule of a feature or of `Nino.css` with a plain selector,
and a feature does not need a heavier one to hold its own against the reset. Inside
one layer the order of the list decides, as it always did. `Nino.css` is the one
file that holds its layer itself; for every other file the bundle writes
the block, so a project writes no layer of its own. What the bundle does with a
source:

- An `@charset` is dropped (the bundle is served with its encoding), and an `@import`
  is moved in front of the first block with `layer(<the file's layer>)`, a layer it
  named itself replaced. Fonts, `@keyframes` and `@property` stand in the block as they are.
- A source whose braces do not balance is repaired the way a browser reads it and stays
  in its layer: a `}` that closes nothing is left out, a `{` never closed is closed at
  the end, and a comment, string or `url()` that does not end ends where the source
  does. Written as it is, such a source could close its own block and put what follows
  in another layer. One warning per source names the file and the faults, when the
  bundle is built. A brace in a string, a comment, an unquoted `url()` or behind a
  backslash is no brace.
- An `!important` turns the order of the layers round: of two important declarations
  the one in the earlier layer wins. A project that needs an `!important` against one
  of `Nino.css` loses it; take the selector, not the flag.
- A stylesheet that is linked by itself - `[assets /assets/x.css]` for a file with no
  list, or a `<link>` of your own - is no part of the bundle and stands outside the
  layers: unlayered rules overrule every layer. `Nino.css` linked alone keeps its layer.
- The bundles of the workbench (`/_admin/.cache/...`) hold no file of the project and
  have no layers.

`\Nino\Modules\Assets::stylesheet( $appData, $target, $swap = [] )` answers the stylesheet bundle
of a target - `'/.cache/style.css'`, as the shortcode names it - as a string, built by the code that
builds the file: the same layers, order and repair, the stamp line first. With no `$swap` it is the
bytes of the bundle file. `$swap` maps the path of a file of the bundle, as the list in
`/nino/html/assets` writes it, to css that stands in its place and in its layer, for a preview of a
file that is not saved. Nothing is written and the public bundle is left as it is. A path that is
no file of the bundle (one outside the project is none) or a replacement that is no string refuses
the call: it answers `''` and raises one warning, as a source that is repaired does.

`\Nino\Html::addAsset()` keeps the order of the list. A file of the project goes to the end; any other
file goes in front of the first file of the project that stands after the last file
of the kernel in the list, and to the end of a bundle that has none. A file that is
listed already stays where it is, whatever way its path is written
(`/assets/x.css`, `/private/assets/x.css`, `assets/x.css` are one file), and the
list in `config.php` is read as it stands and never reordered. A script of the
project runs after the scripts of the features. The bundles of the workbench
(`/_admin/.cache/...`) hold no file of the project and are not touched by the rule.

That is what the kernel does, not a promise to a feature: a feature SHOULD NOT build
on where its stylesheet stands in relation to the theme - the layers already say that
the theme and the project come after it.

### Components in folders

A shortcode a feature registers with `\Nino\Feature::shortcode()` is a plain one: it says nothing of what it takes, so a tool cannot offer it. Give it a schema and it is a **component** of [the register](development.md#the-register-of-components): it says where it may stand, where its value comes from, which attributes it takes - each with a type and a default - and the markup it is drawn as, and the kernel draws it. A component with nothing to compute needs no code at all. A feature brings its components in folders, and its manifest says nothing of them:

```text
features/Countdown/
	feature.php                          the manifest
	Countdown.php                        the class: the renderers component<Name>(), the methods that answer the options of an attribute
	components/countdown/component.php   the schema: it returns an array; the name of the folder is the name of the component
	components/countdown/template.tpl    the markup of the component
	groups/countdown/group.php           the group of the component: its words and its knobs
	groups/countdown/part/part.css       the standard part of the group: the look of its components
	groups/countdown/demo.tpl            optional: a piece of a page that shows what the knobs of the group do
```

The kernel reads the folders of a feature that is on, keeps what it checked in `private/data/cache/register/<key>.php` and registers the components before the `init()` of any module runs; [A feature's components](development.md#a-features-components) has the rules of the folders, the cache and the standard part, and the [component recipe](recipes/component.md) walks through one component from the first file to the drawn page. The renderer of a component that computes is `component<Name>()` of the feature's class, the name in studly caps: `countdown` is `componentCountdown()` and `header-nav` is `componentHeaderNav()`. It is handed `( array &$appData, array $args, string $content, array $context )` - every attribute a string, a bool `'1'` or `'0'`, the default where the call says nothing, and `value`, `class` and the rest the register makes ready - and answers finished markup, which it has the template of its component fill with `\Nino\Components::fill()`. A component with neither that method nor a template is said in the log and left out.

A component of a feature names its `group`, and the feature has the folder `groups/<group>/` with a `group.php`. A group and a component name belong to one feature: what a second feature claims is refused and said, and the feature goes on without it. Of several features that are on and bring one name or one group, each that requires another of them - directly or through features that are on, and not the other way round as well - gives way to it, and of those that are left the one that stands first in `/nino/modules` has it, which also decides two that require each other (`\Nino\Features::winner()`): who is required goes before who requires, and a feature that is tied to none of the others keeps the place its module has. A requirement that runs through a feature that is off or missing does not count. The groups have their owners first, and a component in a group its feature brings and does not get claims no name; a `requires` that the claimants do not have between them changes nothing, and the order the features are read in (`\Nino\Features::ordered()`: a feature that waits moves back, none moves forward because of a `requires`) decides nothing. A block of a key that several features bring is held by the same rule, asked of all of them at once. The standard part reads the tokens of the brand and of the page and those of its own components and meets only classes of its own components; `\Nino\Components::partProblems()` is how the kernel checks it, and a feature's own test asks it of every part it ships. A theme may replace a part through the hook `/nino/components/parts`. A group may bring a `demo.tpl`, a piece of a page of components with texts of its own that a screen draws to show what the knobs of the group do (`\Nino\Components::groups()` answers it as `demo`; [A feature's components](development.md#a-features-components) says what it may hold).

The manifest keys `components` and `stacks` are not read. A manifest that has one is a valid manifest, and the components it declares are registered nowhere: the Features panel lists the reason a component of a feature that is on is not registered - this one among them - in `components` of an entry of `features/list` (`\Nino\Features::componentProblems()`), which is no problem that keeps the feature from being switched on.

The `options` of a `choice` may be the name of a static method of the feature's own class, `'\Nino\Modules\Forms::formOptions'` (`\Nino\Modules\<Directory>` or a class below it - a feature's schema cannot name any other static method), for a choice only the project can list: the method is `( array &$appData, string $attribute ): array`, answers `value => label` (a string or a `locale => string` map, read by `localized()`), and is asked when the schema is handed out and when a value is made ready, once for each attribute in a request. The class is not loaded to check the schema: it is refused where the name is no `Class::method`, the class is not the feature's, its file is not below the feature's directory, the default is no string or `input` is `icons`. Whether the class loads and the method is a public static one is found out when it is asked, and an answer that cannot be read is a warning and the attribute a `string` for the rest of the request - a renderer validates a value that names a record or a file itself. A tool is handed the answer as `options` (the values, in their order) and `optionLabels`: `\Nino\Components::components()` hands out the schemas with the answers in them.

### The feature Basis

Basis - the components a page is written in: a header, a footer, sections with rows and columns, buttons, images and texts, each group with a standard part and its blocks - is a feature of the catalogue like every other one: a folder `features/Basis/` that a project gets in the Features panel or with an application that names it. The kernel registers none of its components and knows no feature by its name; a folder of that name below `features/` is read by the rules that hold for every folder. The table of its components, its knobs, its blocks and how its menu behaves are in the handbook of the feature, with the feature; the pages of this manual that write a page in components use those of Basis as their example, and say so.

A project that switched Basis on while the kernel still carried it has the class `\Nino\Modules\Basis` in its modules and no folder. The kernel boots it all the same, and loads nothing of it and writes nothing over it: the Features panel lists it under Active as a feature that is switched on and not installed, with the sentence that it is in the catalogue and is to be installed there (`\Nino\Features::missing()` names such features, and `\Nino\Features::rejected()` lists them with the other directories that are refused), the log says it once an hour, the Dashboard says it as its first notice, a backup and a restore go through and say in their answer that the folder is not in the archive, and a page that calls its components draws nothing for those calls, nor for what stands between their tags, and says so in the log, as for any component that nobody registered; the rest of the page is drawn. Once the folder is below `features/` - from the catalogue, or by hand - the feature is on at once, since its class is listed, and its pages are drawn as they were. What the project had written for it stays where it is, and which of the keys of its words the feature reads - `/feature/basis/...`, or those below `/module/basis/` that the project holds - is the feature's to say; the kernel reads none of them. **Apply content again** in the view of the feature adds what its install unit brings and the project lacks, and changes nothing else.

**What a component says of itself in its schema.** The kernel names no component: it reads what the schemas say. A component says `'block' => 'page'`, `'header'` or `'footer'` where it is the root of a block of that side of a page (`\Nino\Blocks::wraps()` lists them), `'markup' => true` where its content is the markup of the page - so `\Nino\Components::offer()` and `check()` name it with `CODE_HTML` for the caller to decide -, `'logo' => true` where it draws the logo of the project (its source is an image), and `'menu' => [ 'attribute' => 'menu' ]` or `'menu' => [ 'name' => 'legal' ]` where it draws a menu - the attribute that names it, or the one it draws -, so that the legal check counts a call of it as an output of that menu (see [the schema](development.md#the-register-of-components)).

### The manual

The `manual` key is what the **Description** tab of the feature's screen shows:
reference, not prose, every feature answering the same questions in the same order.
An entry is a **handle**, what somebody types and never translated, and a **line**,
a string or a `locale => string` map. The sections are
`\Nino\Features::MANUAL_SECTIONS`, drawn in this order and when empty ("no
callbacks" is an answer):

| Section | What belongs there |
|---|---|
| `shortcodes` | what a page writes between brackets, one line per form worth naming |
| `markup` | what a template writes that is no shortcode: an attribute, a class a script looks for, a `<script type="text/plain">` |
| `routes` | the addresses the feature answers |
| `panel` | the workbench screens it brings, by the name in the rail |
| `callbacks` | what it hooks into, by the callback key |
| `install` | what activation puts in the project - a template, an element type, the words it merges |

The same manual is what the `?` at the end of the head of the feature's own panel opens, in a dialog, for the people who work with the panel - nothing to write for it, and a feature whose manual says nothing has no `?`. It is drawn by `Nino.adminUi.manual()`, the one the Features panel draws it with.

A plain list item has no handle; a handle is at most 120 characters, a line 1000, a
section 40 entries. The description, the settings and the PHP a feature exposes have
no section: the manifest says the first two, the docblocks the third. The older
form, one string or a `locale => string` map, is still read; do not write it.

### Categories

One category per feature, the coarse "what is this for" the Features panel groups
and filters by (`\Nino\Features::CATEGORIES`). Where a feature could sit in two,
take the first that fits: `business` (a tool for running the business itself -
customers, cases, billing - whatever else it touches), `security` (it protects
access or touches personal data), `marketing` (being found or measured),
`communication` (messages with people), `content` (it brings content or makes it
reachable), `ui` (it only changes how existing things look); what is left is
`system`. Any slug passes
(`/^[a-z][a-z0-9-]{0,23}$/`), so a feature written for a newer catalogue stays
installable; a manifest without `category` is valid.

## The class

The class is an ordinary runtime module in `Nino\Modules`: `init()` registers
behaviour and outputs nothing, a response goes through `\Nino\Http`, and the
technical route belongs to the module (the [runtime module
recipe](recipes/runtime-module.md) applies unchanged). The kernel calls these
methods on a feature's classes:

| Method | Called | The kernel does with the answer |
|---|---|---|
| `init( array &$appData ): void` | by `\Nino\Modules::callModules( $appData, 'init' )` at every boot, for each class in `/nino/modules` in its order, after the manifests' assets are registered | nothing |
| `adminPanels( array &$appData ): array` | by `\Nino\Modules::collect()` whenever the workbench builds its registry | merges the panel classes into it ([The panel](#the-panel)) |
| `upgrade( array &$appData, string $fromVersion ): bool` | by `\Nino\Features::activate()` ([Updates](#updates-and-the-upgrade-hook)) | `false` refuses the update, anything else goes on |
| `component<Name>()` | by the register of components when the component is drawn | draws it ([Components in folders](#components-in-folders)) |
| a callback of `'/nino/admin/dashboard/head'` | by the Dashboard, once for each answer of its summary, with the areas so far, `name => data` | the areas it answers, which the script of a panel draws above the notices ([The head of the Dashboard](_admin.md#the-head-of-the-dashboard)) |
| a callback of `'/nino/admin/backup'` | before every backup packs its files - the daily one, Back up now and the safety snapshot of a restore - with `{ dataDir, staging }` | packs the copy of its data that the feature wrote into `staging` where the live file stands ([Data, backup and restore](#data-backup-and-restore)) |
| a callback of `'/nino/admin/restore'` | by the Backups panel and the recovery page, with `{ dataDir, staging }`; an exception is caught and named in the answer | none; a file it takes out of the staging is not copied ([Data, backup and restore](#data-backup-and-restore)) |
| on a panel class: `log( $action, $data )`, `summary( &$appData )`, `scopes( &$appData )` | after an action that completed; by the Dashboard; by the roles tab | the line of the activity log (`''` for none); a tile `{ value, label }`; the scoped permissions |

What a feature may read where it draws the head of the Dashboard: `\Nino\Runtime::getSessionValue( $appData, './nino/auth/previous' )`, the login before the one that opened the session (`0` for the first login of an account, `null` where the session holds none), `\Nino\Modules\Logs\Admin::countSince( $appData, $since, $actor, $visit )`, the number of lines the activity log holds since a visit (the minute of `$since` and the sign-in of `$actor` at `$visit` are not counted), and `\Nino\Modules\Backups::lastDate()`, `missed()` and `stopped()` (their notes read without a class of the workbench, so that a feature can report from a page request). The kernel itself reads none of them; they are there for a feature, behind `class_exists()` where its module may be off. A feature that tells the notices of the Dashboard somewhere else - in a mail to the owner of the site - works none out by itself: `\Nino\Modules\Dashboard\Admin::notices( $appData, true )` answers them as the Dashboard draws them, for every notice whatever the signed-in account may open (`false`, the default, is the account's own view), each as `key`, `text`, `values` and `link`, where `key` names the finding for as long as it is the same one and a count that moves is no part of it, and `sentence( $appData, $notice, $locale )` says one in a language of the site. The callback of `'/nino/admin/dashboard/head'` is asked on its own for every account that opens the Dashboard: one that throws or answers no list is named in a warning and its answer dropped, an entry whose name is no slug or whose data is no array is named and left out, and a name that is taken stays with the first. The script is as safe: a `render()` that throws, or answers anything but an element or `null`, is named in the console and its area left out, and the rest of the Dashboard is drawn. A date an area shows is written by `\Nino\Format::date()`, `dateLong()` or `datetime()`: the callback runs in the interface language of the session, and they write in it.

There is no install, activate, deactivate or uninstall hook: a feature lies in the
directory, an activation applies its unit and lists its class, and nothing runs on
the way out. The class of `Hello` is `features/Hello/Hello.php`; its markup is the
template of its component, `components/hello/template.tpl`, `<p class="[[class]]">[[text]]</p>`,
and the class draws it through `componentHello()`:

```php features/Hello/Hello.php
<?php
declare(strict_types=1);

namespace Nino\Modules {

	/**
	 *	Hello World - the example a feature takes its shape from: a component
	 *	(components/hello/, drawn by componentHello()), a route and a page made of
	 *	components (install/templates/page-hello.tpl), a file of data a backup carries
	 *	(STORAGE), and a panel of the workbench (Admin/Admin.php, assets/admin.js)
	 */
	class Hello {

		/**	The name the panel stores, with a format key of its own	*/
		public const string STORAGE = '/data/hello.php';

		/**
		 *	Registers the route /hello where the project has not written one of its own
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {

			// A route of the feature's own, only where the address is free
			if( isset( $appData['/nino/http/routes']['GET://hello'] ) === false )
				$appData['/nino/http/routes']['GET://hello'] = [ 'uri' => '/hello', 'body' => '[template /templates/page-hello]' ];
		}

		/**
		 *	The panels this module answers; the workbench reads them while the feature is on
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	array						The classes of the panels
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Hello\Admin::class ];
		}

		/**
		 *	The renderer of the component hello, [hello name="Ada" shout]: the register
		 *	has made the attributes ready, so the name arrives escaped - it is decoded
		 *	to be put into a sentence and escaped again where it is written
		 *
		 *	@param		array 		&$appData
		 *	@param		array 		$args					The attributes of the call
		 *	@param		string		$content			What stands between its tags (nothing: it is a leaf)
		 *	@param		array 		$context			Where the call stands
		 *
		 *	@return 	string									Finished markup
		 */
		public static function componentHello( array &$appData, array $args, string $content, array $context ): string {

			$name = trim( html_entity_decode( $args['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$text = (string) \Nino\Features::setting( $appData, 'hello', 'greeting', 'Hello' ). ', '. ( $name === '' ? self::name( $appData ) : $name ). '!';

			return \Nino\Components::fill( $appData, 'hello', $args, $content, [ '[[text]]' => \Nino\Components::escape( $args['shout'] === '1' ? mb_strtoupper( $text ) : $text ) ] );
		}

		/**
		 *	The name the panel stored, or World
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	string
		 */
		public static function name( array &$appData ): string {

			$stored = \Nino\Filesystem::getFileContent( $appData, self::STORAGE, [] );

			return is_array( $stored ) === true && is_string( $stored['name'] ?? null ) === true && $stored['name'] !== '' ? $stored['name'] : 'World';
		}
	}
}
```

`[hello]` is a component of the feature, which the folders `components/hello/` and `groups/hello/` declare
([Components in folders](#components-in-folders)); the register calls `componentHello()` for it with the
attributes made ready, which is why `name` arrives escaped and is decoded before it is put into a sentence.
`init()` registers no shortcode of its own. The route is registered only where the address is free: a route in `config.php` is a
page somebody made. It goes with the feature; a menu shows it once the Navigations
panel has put it into one and it has a name (`/_nino/webpage<uri>/name`, written in
the Routes panel under *Routes of features and modules*).

## The toolset

What a feature once wrote for itself, the kernel has once, and the docblock of a
member is its reference. A method whose name starts with `_` is internal, every
other is public; **1.8** and **1.9** mark the version a member came in. `\Nino\Feature` **1.8** (not `\Nino\Features`, the contract) holds
the helpers of a feature's own code, static methods that render no markup:

| Call | What it does |
|---|---|
| `template( $appData, $directory, $name )` | the `<name>.tpl` of a templates directory (`'/features/Hello/templates'`), ready for `str_replace()` over its `[[tokens]]`; one that is missing or empty is a warning and `''` |
| `classes( $args )` | the `class` attribute of a component's markup, from the argument every shortcode may carry: `''`, or a space and the classes, escaped |
| `flag( $args, $name, $default )` | a flag of a shortcode without a schema: a bare word is on, `name="no"` is read by the words a component's bool is |
| `shortcode( $appData, $name, $callback, $container )` | a plain shortcode, unless the register of components has the name (a component of a feature that is on): then `false`. `$container` is `Html::addShortcode()`'s: `true` a container with an end, `false` a leaf, `null` (the default) a container where its end stands in the document |
| `origin( $url )`, `allowOrigin( $request, $directive, $url )` | an address as a Content-Security-Policy source - https, no credentials, no ip literal - and the call that adds it to one directive of this response |
| `count( $appData, $name, $key, $limit, $window )`, `spent()`, `forget()` | tries per client in the window, kept in the folder `/data/.counters/<name>/` under a hashed key, an IPv6 address as its /64: count one and learn whether it is within budget; ask without counting; let a client go. A try reads and writes one small file of the folder, and the folder holds a bounded number of clients; [how it is kept, and what happens when a file is full](#counters) |
| `slug( $text, $max )` | a name made into a slug, the letters ascii lacks written as `TRANSLITERATIONS` says; `Nino.slug()` is its twin |

<a id="counters"></a>
**Counters.** `\Nino\Feature::count()` is the one counter of the kernel for what a visitor does without signing in: the sends of a mail an address may make, the submissions of a form, the failed codes of a recovery key, and every counter a feature keeps for its own sign-in or form. It keeps a counter in a folder, `/data/.counters/<name>/`, of 256 files `00.php` to `ff.php`. A client is in the file that the first two characters of an HMAC of its hashed key name, under a key of the installation (`/data/.counters/seed.php`, made by the first `count()`), so that a try reads and writes one file and holds the lock of that file alone: with 100,000 clients in a counter a `count()` took 1.2 ms and a `spent()` 0.4 ms, where one file with all of them took 224 ms and 117 ms. The folder starts with a dot: a backup does not carry it and a restore leaves it as it is, like `/data/.locks`. A counter that an earlier form kept in the file `/data/<name>.php` loses it with its first `count()` - the file is removed and the tries in it run out with their windows, which are short; nothing reads the old file.

A file holds 512 entries that are still open at most, so a counter holds 131,072 clients at once and takes about 17 MB of disk at the most, whatever the keys are. What a full file does to a client that has no entry in it is the question of this class that is about an attacker, and the answer is that it turns the client away: `count()` answers `false` without counting and without touching a file, and `spent()` answers `true`, so that a guard in front of an action says what the counter would say. Not the other two answers:

- *Forgetting the oldest entry to make room* lets an attacker take away the count of a client - his own, which is the brake that the counter is - with 512 keys that fall into the file of it: the limit is no limit for whoever sends a few hundred requests more.
- *Letting the client in without a count* lets him fill the file and use everything that falls into it without a limit.

Turned away is a price paid by an innocent, and it is bounded. A client that has an entry in the file goes on counting, so nobody who is in is locked out by those who came after; every entry that runs out frees a place, so the refusal ends with the windows that caused it - at most one window of the counter after the last new key of the attacker; and the clients of the other files are not touched. The file of a client is chosen by a key that is not written anywhere a visitor can read, so an attacker cannot aim at the file of a client he wants to turn away - he has to fill all 256. Measured with keys of different /64 networks and a window of an hour: 98,304 open keys fill no file, 131,072 fill 132 of the 256 files (half of the new clients are turned away), and from about 150,000 all 256 are full - about 42 requests a second for an hour, each with a new key. Keys that are turned away are lost to the attacker, and the files fill unevenly, which is why the number is above 131,072. Where a counter counts what a visitor types, he needs fewer: it depends on whether the feature counts the visitor first. The sign-in of Members counts the visitor (his network) first and the typed address only after it, so a network costs him at most a handful of entries and about 22,000 networks - one /48 - fill that counter; a feature that counts the typed key before anything else would cost him nothing. Count the visitor first, and the typed key only after.

**The way out for the operator.** The first refusal of an hour is written to the log (`Feature::count(): a file of the counter "<name>" holds 512 open entries ...`), once for each counter, and the message names the folder. What is in a full counter runs out by itself, within a window of the last new key of the attacker. To let everybody in at once, delete the files in `data/.counters/<name>/` (the folder itself may stay): the clients of that counter lose the tries they hold, which is the price, and nothing else. Making the key of the installation anew does not help - the files stay full and the clients land in other full files - and a restore changes nothing, the folder is not in a backup. The workbench shares the counter `ratelimit` (the cap of mails an hour for an address) with the visitors: where its files are full, a test mail or a request of Sign made from the workbench is turned away with the message of the cap.

A file that cannot be locked or written is a try that is not within budget (`false`); a file that holds anything but an array holds no tries; a key of the installation that cannot be made is a counter that cannot count (`false`). The folder `data/.counters` and the files in it have to belong to the user of the web server: where another user owns the folder or the key, every counter is closed - nobody is counted, everybody is turned away - and the log says so once an hour with the owner and mode of both and the user the request runs as. A name that is no slug (lower case letters, digits and hyphens, a letter first) is a warning and `false`; the names are chosen by the code that asks - none comes from a request. A file `/data/<name>.php` of the name of a counter is no business of the counter and is left as it is, with a warning once an hour that names it - except a file that is a counter of the earlier form (entries under 64 hex characters, none or many), which is removed with the first try. The other counter of the kernel that grows with the visitors, the failed tries of a sign-in (`/data/auth-tries.php`, kept by `\Nino\Auth`), is a file of its own shape with its own bound of 5,000 entries and is not kept this way.

The kernel classes a feature calls, by home:

| Class | Members |
|---|---|
| `\Nino\Features` | `setting( $appData, $key, $name, $default )`, `settings()`, `saveSettings()` ([Settings](#settings)); `get()` one feature as `all()` answers it; `localized()`, `satisfies()` |
| `\Nino\Filesystem` | `getFileContent()`, `putFileContent()` a whole file by its virtual path; `mutate( $appData, $path, $fn, $default )` every read-modify-write: it locks, reads, calls `$fn( $state )` and writes what that answers, `null` aborts and `mutate()` answers `false` - a file whose shape may change carries a `format` key; `fileExists()`; `path()`, the real path, to `glob()` a directory; `forceDir()`, `removeDir()`; `writeAtomic( $path, $content )` a file at a real path replaced as a whole - a temporary file beside it, then `rename()` - from a string or from pieces, the mode of the file that is there kept, `false` where it could not be written, the file as it was and no temporary file left, and a piece that throws reaches the caller as its exception, with the same nothing left behind; `getDir()`, `getPublicDir()`, `getPublicPath()` |
| `\Nino\AppData` | `writeContentData( $appData, $keys )` persists the given top-level keys of `config.php`, none that begins with `./`; `stored()` **1.8** reads one as the file holds it |
| `\Nino\Http` | `fail( $request, $status, $error, $code, $params, $field )` and `ok( $request, $body )` end a handler; `download( $request, $bytes, $filename, $contentType )` answers bytes as an attachment that nothing draws; `string( $data, $key, $default )` one value of a decoded body as a string, never a cast (see the paragraph below); `redirect()`, `allowSource()` a policy directive widened by one source, `pages()`, `matchesPath()`, `answersHtml()` for a sitemap or a protected area, all **1.8**; `isReservedUri( $uri )` **1.9** whether an address is one a page cannot have, `/_nino` or `/features` and what lies below; `requestRoute()`, `getRequest()`, `getClientIp()`; `afterResponse( $appData, $work )` (from the kernel that has it) work that runs once the response is out and the client let go - by `fastcgi_finish_request()` where php has it, else by the `Content-Length` the kernel tells for a body that goes out as it is, counted after every callback of `/nino/http/output` and after the body was encoded; for a file, a stream, a length or an encoding somebody else set, or a page php compresses itself, the work does not run in that request, and a response without asked-for work is sent byte for byte as it was; `canAfterResponse( $appData )` whether this request is likely to run such work - a no is certain, a yes is no promise (a feature that must not depend on it queues its mail, see [A mail from a feature](#a-mail-from-a-feature)); where the kernel tells the length it also says `Connection: close`, unless the response has a `Connection` of its own; `releasesResponse( $appData )` whether the server lets the client go before the work begins (`fastcgi_finish_request()` or `litespeed_finish_request()` exist) - the question a feature asks whose answer must not tell what its work did, such as a mail to nobody (see [A sign-in that tells nothing](development.md#a-sign-in-that-tells-nothing)); under `php -S` and, by the code, Apache with mod_php it is false although `canAfterResponse()` may be true |
| `\Nino\Activity` | `record( $appData, $feature, $message )` one line in the activity log for the feature `$feature`, which has to be on; `true` when the line is in the day's file ([A line in the activity log](#a-line-in-the-activity-log)) |
| `\Nino\Html` | `renderHtml()` a string as a page is rendered - read once into a tree and drawn from it - and `renderSource()` html+ drawn with the context of a call, `context()` that context, `tree()` the nodes of a source and `putFills()` a text with its fills put in, for a shortcode that has html+ of its own to draw (see [Drawing a page](development.md#drawing-a-page)), `renderTextfill()`, `resolveTextfill()` one fill; `addShortcode()` - a fourth argument says whether the name has an end -, `addAsset()`; `addFills()`; `getFills( $appData, $locale )` every fill of a language, the argument **1.8**; `removeFill()`, `absoluteUrl()` **1.8**; `sanitizeHtml()`; `shortcodeCalls()` the calls a template makes, `shortcodeParts()` a piece of html cut into its calls and the text between, `fillOrigin()` the language a fill's text was taken from, all **1.9** |
| `\Nino\Components` | `addComponent( $appData, $name, $schema, $renderer )` a component by schema, `validate()`, `components()`, `allowed()`, `fill()` the template of a component filled by its renderer, `escape()` a value into html or an attribute, `sources()` the keys and the pictures a source reads, `groups()` the groups of the features that bring components in folders, `validateGroup()`, `partProblems()` what a standard part does against the rule of mixing (see [The Register of Components](development.md#the-register-of-components) and [A feature's components](development.md#a-features-components)) |
| `\Nino\Text` | `entries()`, `entry()`, `isGrammarKey()`, `sanitizeValue()`, `put()`, `values()`: the project's texts and their keys; `length( $text, $format )`: how long a text is as the field of the workbench counts it, in UTF-16 code units - an entry names per language, in `limits`, how many a text of it may have; `isEmpty( $text )`: whether a text says nothing once its markup is taken away - an entry names the languages that have none in `empty`; `fills( $appData, $form, $locale )`: the textfills a field may take - `textfill` or `placeholder` - each with its notation and its value in a language |
| `\Nino\Preview` | `issue()` a draft with a token for the session, `held()` the draft a request may have, `active()` whether this request holds one, `tidy()`; with `\Nino\Modules\Template::swap()` a template that is drawn in place of its file for that request (see [A draft for a preview](development.md#a-draft-for-a-preview)) |
| `\Nino\Form` | `forms()`, `form()`, `posted()`, `validate()`, `entries()`; `blocked()` **1.8** whether the CSRF guard refused the request (`\Nino\Csrf::blocked()` is the same question; the engine itself is loaded where a form is built or posted, not for every page); `\Nino\Modules\Form::draws()` says that the page has a form where it has no `[csrf]` |
| `\Nino\Images` | `getUrl()`, `getSlot()`, `getSlots()`, `setSlotFilename()`, `setSlotAlt()`, `putSlot()`, `dropSlot()`, `cleanAlt()`, `process()`, `fit()`, `limits()`, `reject()`, `read()`, `restore()`, `delete()` |
| `\Nino\Mail` | `send()`; `rateLimited()` **1.8** whether the last send was refused by the cap per address; `sendSystem()` (from the kernel that has it) `sendAll()` for a mail the system sends on its own account - a report to the owner that a page request set off - which neither counts against the cap of the client that made the request nor is refused by it, and has a cap of its own for the whole site, `SYSTEM_CAP` units an hour, all the addresses of one call being one unit and the first that does not take the mail ending the call; `queue( $appData, $mail, $kind, $key )` (from the kernel that has it) keeps a mail in the outbox and answers its id, or `''` and `queueRefused()` says why - the mail is delivered after the response of this request or of a later one, tried again after a failure and given up on, said and counted, only after its last attempt; the optional field `'expires'` of the mail (seconds from now, 60 to 172800) makes a mail that is delivered by nobody after that time and leaves no text, for a code that is good for 15 minutes (see [A mail that expires](development.md#mail)); `queued()` counts what waits and what was given up on, the kind `Mail::VOID` queues a mail to nobody that takes the way of a mail and is let go without a transport, for an answer that must not tell whether there is a mail (see [A mail from a feature](#a-mail-from-a-feature) and [A sign-in that tells nothing](development.md#a-sign-in-that-tells-nothing)); the kind `Mail::OWNER` queues a round mail that a signed-in account sets off - 200 to 500 mails, taken only with a session, charged to no cap, delivered after the others at 20 mails a minute - and `state( $appData, $key )` and `states( $appData, $keys )` say whether the mail of a key waits, was delivered or was given up on, with no address kept for it (see [A round mail of the owner](development.md#a-round-mail-of-the-owner)) |
| `\Nino\Format` | **1.12**: `add()` a formatter by its name, `registered()`, `options()`, `apply()` a formatter on a raw value, `words()`, `amount()`, and the built-in `number()`, `currency()`, `percent()`, `date()`, `dateLong()`, `datetime()` and `time()` |
| `\Nino\Locales` | `getCurrentLocale()`, `getNativeLocale()`, `getAvailableLocales()`, `verifyLocale()`, `useLocale()` for the rest of the request; `getVisitorLocale()` the language the site shows the visitor of this browser, `getTextLocales()` the languages that have a text file, `languageTag()` a locale id as the tag of a `lang` attribute, all **1.9** |
| `\Nino\Auth` | `guard( $appData, $request, $perm )` whether a request outside the workbench may proceed, and `deny( $request, $refusal )` writing the refusal ([A surface outside the workbench](#a-surface-outside-the-workbench)); `scoped()`, `isScoped()`, `isDescribed()` the fine rights inside a panel's door, the rule `\Nino\Admin\Admin` asks too; `getCurrentUser()` the signed-in account or `false`; `checkPermission()` whether it holds a right |
| `\Nino\Admin\Admin` | `guardPerm()`; `postData()` the json of the posted `data`; `postString()` **1.8** one posted value as a string, never a cast; `cleanInt()`, `failType()`, `uploadError()`; `sessionLocale()` the language the workbench speaks, `contentLocale()` **1.9** the language a panel edits in - its own value of the session, which the select above a panel's screen sets and nothing else does; `textFills()`; `\Nino\Admin\Panels::relative()` |
| `\Nino\Elements` | `read( $appData, $type, $uri )`, `save( $appData, $type, $uri, $locale, $fields, $options )`, `remove( $appData, $type, $uri )` and `rights( $appData, $type )`: what the workbench's Elements panel does, every check of it and a code at every refusal, each answering an array ([Elements from a feature](#elements-from-a-feature)); `queryElements()`, `getElement()`, `getElementModel()`; `getElementWithFallback()` **1.9** an element in the language asked, else from another one, with its `.lang`; `insertElement()`, `updateElement()`, `deleteElement()` the writers that check no right, uri or language; `types()` the names of the types, `typeData()` a type file |
| beside them | `\Nino\Callbacks::registerCallback()`; `\Nino\Modules\Elements::fills()` and `slug()` **1.9** the fills of one element in a loop and its name alone; `\Nino\Fetch::get()`, the one way to the network; `\Nino\Modules\Jstext::publish()` fills for a public script; `\Nino\Modules::callModules()`; `\Nino\Modules\Backups::maybeRun()` the daily backup, called by a surface outside the workbench after its guard passed, behind `class_exists()` ([The daily backup](#a-surface-outside-the-workbench)) |

<a id="elements-from-a-feature"></a>
**Elements from a feature.** A feature that saves or deletes an element from a surface outside the workbench calls `\Nino\Elements::save()`, `remove()` and `read()`, and asks `rights()` for what the account may do with a type - not the actions of the panel with a made-up `$_POST`. They make every check of the workbench's panel, for the account that is signed in, and each answers an array that never has a notice of the writers in it:

```php
$result = \Nino\Elements::save( $appData, 'news', 'first', 'de_DE', [ 'title' => 'First' ], [ 'new' => true ] );
if( $result['ok'] === false ) { \Nino\Http::fail( $request, $result['status'], $result['error'], $result['code'], $result['params'], $result['field'] ); return; }
```

`save()` answers `[ 'ok' => true, 'element' => ..., 'nextUri' => ... ]` (`nextUri` is the uri a numbered type assigns next, `''` for another), `read()` `[ 'ok' => true, 'global' => ..., 'locales' => ..., 'raw' => ... ]`, `remove()` `[ 'ok' => true ]`, and `rights()` `[ 'insert' => bool, 'delete' => bool, 'update' => [ field => bool ] ]`. A refusal is always `[ 'ok' => false, 'status' => int, 'error' => string, 'code' => string, 'params' => array, 'field' => string, 'fields' => array ]`: the status and the English sentence the workbench answers with, the code the workbench words in both interface languages, the params that belong in that wording and the key the refusal is about. `fields` lists what a callback refused (`\Nino\Elements::refuse()`) for fields of the type. A callback's refusal carries the callback's own code; where the callback gave none - `\Nino\Elements::refuse()` without a code - the code is `''`, and so it is where the refusal holds several sentences.

| Code | Status | When |
|---|---|---|
| `elements_type_unknown` | 404 `read()`, 400 | the type is none of `types()` |
| `elements_uri_invalid` | 404 `read()`, 400 | the uri is empty (an insert into a numbered type has none), holds a `/`, or - of a new element - is no slug of letters, digits, hyphens and underscores |
| `elements_locale_invalid` | 400 | `save()`: the language is none the site serves |
| `elements_insert_not_allowed` | 403 | `save()` of a new element: the account may not add elements of the type |
| `elements_fields_not_allowed` | 403 | `save()`: the account may not change a field it posted; all of them are named, the first is `field`; a key the model does not have is asked like the others |
| `elements_image_upload` | 400 | `save()`: an image field set to a name it does not hold - a picture comes from an upload |
| `elements_unknown` | 404 `read()`, 400 | `read()`: no language holds the element; `save()` of an update: it does not exist |
| `elements_exists` | 400 | `save()` of a new element: the uri is taken |
| `elements_wrong_type`, `elements_missing_key`, `elements_value_refused` | 400 | the value of a field has the wrong type, a required key is missing or empty, a value is not on the field's whitelist (or is on its blacklist); the key is the param and the field |
| `elements_reference` | 400 | a reference field points outside its type, lists an element twice or holds more entries than it takes; the key of the field is the param and the field |
| `elements_delete_not_allowed` | 403 | `remove()`: the account may not delete elements of the type |
| `elements_save_failed` | 400 `save()`, 409 `remove()` | the type file could not be locked for writing or the element could not be written: the sentence of the writer's notice |
| `elements_vetoed` | 400 `save()`, 409 `remove()` | a callback said no without naming a field: its sentence, or `save failed` and `delete vetoed`; the sentence of any other notice of the writers that the codes above do not know answers the same |

The checks up to the image rule are made in the order of the table's rows, the first that fails answers: a request with a wrong type and a wrong uri is `elements_type_unknown`, with a wrong uri and a wrong language `elements_uri_invalid`, with a wrong language and no right to insert `elements_locale_invalid`. In the writer the order is: whether the element exists (an update of one that does not is `elements_unknown`, an insert of one that exists `elements_exists`, whatever the values say), the lock of the type file (`elements_save_failed`), the callbacks of the model's fields (a `false` of one is `elements_vetoed` with "Callback '...' returns an error.", before any value is judged, a required key that is emptied included), then the values field by field in the model's order, the first that fails answering - a required key that is missing (`elements_missing_key`), a value of the wrong type (`elements_wrong_type`), a value off the whitelist (`elements_value_refused`), a reference rule (`elements_reference`) - then what a callback of the write refuses, and last the write of the type file (`elements_save_failed` where it cannot be written). Only a boolean `true` in the option `new` is an insert: `'true'` or `1` is an update.

**The caller guards the request; these functions do not ask the panel's door `/_admin/elements/manage`.** An account without that door gets from `rights()`, `save()` and `remove()` everything its scoped rights allow: a feature calls `\Nino\Auth::guard()` ([A surface outside the workbench](#a-surface-outside-the-workbench)) or `\Nino\Admin\Admin::guardPerm()` first, and a caller that acts for a visitor decides for itself what it lets through. The scoped rights are those of the panel for the account that is signed in (`/_admin/elements/<type>/insert`, `/update/<field>`, `/delete`, read through `\Nino\Auth::scoped()`): an account that holds none of them keeps every action, and with no account signed in nothing narrows anything. The return value of the write decides, not the notices that fall while it runs: a warning of a callback or of a `/nino/elements/committed` hook is no refusal of a write that went through, and `remove()` of an element that is gone takes the pictures of the element's own uploads with it. `remove()` of an element that does not exist is ok, as the delete of the kernel is. A save that changes nothing writes the type file all the same and fires `/nino/elements/committed`, and two windows that save the same field do not know of each other: the last wins. `insertElement()`, `updateElement()` and `deleteElement()` check none of this - no right, no uri syntax, no language, no html, no image rule - and answer `false` with a notice for what they refuse (`deleteElement()` answers `null` for a veto of a callback, and so do the others), among it a uri that does not begin with `/` and one whose type is spelled otherwise than its file (`/NEWS/x` for `news.php`), which they refuse before anything is locked or written: they are for code that needs no such checks. What a feature builds on is `read()`, `save()`, `remove()` and `rights()`; the other public functions the class gained for them (`refusal()`, `rawBuckets()`, `imageFilenames()`, `removable()`, `mayInsert()`, `mayUpdate()`, `mayDelete()`) are internal to the kernel and the workbench's panel.


### A surface outside the workbench

A feature that serves a page or an endpoint of its own - a route of the site, answered by `index.php`, where the shell of the workbench is not loaded - asks `\Nino\Auth::guard()` whether the request is that of a signed-in account of the workbench, with a valid token, that holds a right. The route keeps the token guard on (`'csrf' => true`, the default) and the page hands the token of `\Nino\Csrf::getToken()` to its script, which sends it as `X-CSRF-Token`:

```php
$refusal = \Nino\Auth::guard( $appData, $request, self::PERM );
if( $refusal !== null ) { \Nino\Auth::deny( $request, $refusal ); return; }
```

`guard()` answers `null` where the request may proceed, else `[ 'status', 'error', 'code', 'params' ]`, and `deny()` writes that into the response with `\Nino\Http::fail()`. It asks in this order, which is the workbench's own:

| Code | Status | When |
|---|---|---|
| `post_too_large` | 413 | the token guard refused a body above `post_max_size` - php dropped it with its token; the limit in megabytes is the one param |
| `csrf` | 403 | the token guard refused the request, or the method is not GET, HEAD or OPTIONS and the request carries no valid token of the session - also on a route that switched the token guard off with `'csrf' => false`. A refused token wins over the answer of a callback that ran after the token guard: a request without a token that a maintenance callback answered 503 gets `403 csrf`, and `deny()` writes it over the 503 |
| `refused` | the status the response holds | another callback refused the request before the guard was asked (maintenance, say) and the token was not what refused; `deny()` leaves that answer as it is |
| `session` | 401 | nobody is signed in: a visitor with the token of the site, a session that ended or was revoked, an account that was deactivated or deleted |
| `not_allowed` | 403 | `$perm` is not `''` and the account does not hold it |

The rows are in the order of the checks, with one exception: on a route with `'csrf' => false` nothing asked the token before the guard, so a request that another callback refused is answered `refused` before the guard asks for the token. No token is asked of GET, HEAD and OPTIONS (HEAD runs the GET callback), and the session cookie travels with a cross-site GET: a guarded GET must not write or act - what changes anything is a POST.

`guard()` writes no file, no session value and no log line, and changes neither `$appData` nor `$request`. It asks `\Nino\Csrf::blocked()` for a request the token guard refused, which counts as the route having asked (as `\Nino\Form::blocked()` does). What the guard of the workbench does besides - the line "Login" of the activity log, the base texts of a new version, the daily backup - it does not do. The workbench checks a right that a panel of its registry names with the rule of that panel, which may let an account in through the door of a tab with a `scope()`; the public guard asks `checkPermission()` alone and is therefore never looser than the workbench, at times stricter. A fine right inside a door is asked with `scoped()`. A panel action of a feature keeps calling `\Nino\Admin\Admin::guardPerm()`.

**The daily backup.** The public guard starts none. A site whose owner may never open the workbench gets its daily backup from a surface that asks for it on purpose, as a call of its own after the guard passed: `if( class_exists( '\Nino\Modules\Backups' ) === true ) \Nino\Modules\Backups::maybeRun( $appData );` - the check is there because a delivery may drop the module. The call needs the kernel and the Backups module, not the workbench's shell. It makes today's archive (and the copy of the key) once a day, one attempt a day, prunes what is past the retention, does nothing where the project switched backups off (`/nino/admin/backups`), never waits for a restore that holds the lock - it returns at once and the next request makes the archive - and never throws: a failure is a notice, not the end of the request. Where today's archive is there a call costs the two lock files, a read of `config.php` and of the key's copy, and no write. The line "Backup created" of the activity log is the signed-in account's; a request with no account makes the archive and writes no line.

<a id="a-line-in-the-activity-log"></a>
**A line in the activity log.** `\Nino\Activity::record( $appData, $feature, $message )` writes one line into the log that the workbench's Log panel shows, for a feature whose surface is outside the workbench as well as for one inside it. The actor is never a parameter: it is the mail of the account that is signed in, and where nobody is, the fixed `feature:<key>`; `<key>` is `$feature`, which has to be the key of a feature that is on, spelt as its manifest spells it (the Users panel takes a mail that passes `FILTER_VALIDATE_EMAIL` and no such address begins with `feature:`). The kernel checks that the key is of a feature that is on, not which feature's code is calling: a feature gives its own key. The message is a sentence of the feature with the ids and the names of what it acted on: text a visitor typed does not belong into it - the kernel bounds what it can, one line of 300 characters at the most with no control character in it, and cannot tell whose words they are. Every writer of the log, the workbench's `Admin::record()` among them, has its two fields cleaned the same way: each control character (C0, DEL, C1), each control or format character (Unicode categories Cc and Cf: direction controls, zero-width characters, the soft hyphen, the byte order mark, the tag characters), line and paragraph separator, blank-looking filler (U+034F, U+115F, U+1160, U+17B4, U+17B5, U+3164, U+FFA0, U+2800, the Mongolian free variation selectors U+180B-180D and U+180F) and space of Unicode is one blank - the other variation selectors (U+FE00-FE0F, U+E0100-E01EF) stay, an emoji keeps its own -, a run of blanks is one blank (which is what keeps a line's three fields apart), the ends are trimmed, and the message is cut at 300 characters, the actor at 254 (the longest a mail is), never inside a character, after the cleaning. The answer is `false`, and no line is written, where the log is switched off (`/nino/admin/logs`), the workbench is delivered without the Logs module, the feature is not on (a warning for the programmer), a field is not valid UTF-8 or is empty once cleaned, or the day's file could not be written (a notice). A failure is never an end of the request. The log is not carried by a backup. `\Nino\Http::string()` is `Admin::postString()` for a request outside the workbench: `Admin::postData()` is the workbench's, a surface outside it decodes its own body and reads its values through `string()`.

The markup a feature's own template writes against: `[imagevalues /slot]...[/imagevalues]` gives the values of a picture as tokens - `[[url]]`, `[[src]]`, `[[width]]`, `[[height]]` and `[[alt]]`, and `[else]` divides it into what is drawn for a picture and what is drawn for none - `[link /path]...[/link]` writes an anchor to a page of the site that marks the page that is shown ([Drawing a page](development.md#drawing-a-page)) - and the components of [the feature Basis](#the-feature-basis) stand there where it is on.

In the browser:

| Where | Members |
|---|---|
| `Nino` | `slug( text, max )`, `clipboard( text )` (a promise of `true` or `false`), **1.8**; `content.getText( key )`; `dir`; `events.bindCallback( evt, cb )`: a callback bound after the event fired runs at once, every callback runs on its own and an error is thrown again from a timer, so one cannot stop the next, and the listeners are passive (**1.8**) |
| `Nino.adminUi` | `panel( spec )`, `levels( prefix, names )`, `hint()`, `textField()`, `el()`, `button()`, `localeSelect()` **1.8**; `manual( content, options )` and `manualDialog( title, content, opener )`, `showLevel( level, options )` a level of a drill-down shown and the focus moved in, `backTo( level, opener )` back to the level it came from and the focus to the control that opened the one left, `fieldError()` the sentence at an invalid field and `clearFieldErrors()` taking them off, `tabBadge( tab, text, state )` the chip with a word on a tab button, `isWord( slug )` whether the interface's vocabulary covers a slug, all **1.9**; `api.call()`, `api.errorText()`, `showError()`, `status()`, `format()`, `text()`, `emptyState()`, `notice()`, `panelHead()`, `panelName( el )` the name the head of a pane gives, `contextBar()` (a link and its controls, or options: `back`, `title`, `code`, `icons`, `tools`, `panel`, `panelLabel`), `previewTools()` the page, the width and Reload of a preview as one group, `cards()` the cards of things whose look is the content, `actionBar()` (an element, or options: `danger`, `status`, `secondary`, `primary`, `list`), `barState( bar, state )` the badge of such a bar, `localeName( code, among )` the name of a language - the one form the workbench writes it in, the code behind it only where two of `among` are named alike -, `localeBadge( locale, bar )` the quiet pill of the context bar that names the content language where it is not the native one, `say( text )` the toast that says what went well - one at a time, never an error, `listActions()`, `selectField()`, `switchField()`, `numberField()`, `textareaField()`, `fieldHelp()` the (?) at a field's name, `sectionHelp()` the one at the title of a section, and the option `help` of the field blocks (`hint` is the same), `note` what stays in view at a field, `fills`, `fillsButton()` and `fillsDialog()` the "[ ]" at a field and the dialog of the textfills it takes, `elementList()`, `stringList()`, `table()`, `buttonRow()`, `accordion()`, `steps()`, `split()`, `color()`, `choiceDialog()`, `checkImage()`, `limits()`; `confirm( level, options )` a question of the weight `'routine'`, `'big'` or `'destructive'` that the shell's mode decides whether to ask (see the [panel recipe](recipes/admin-panel.md#saying-what-happened)) |
| `Nino.admin`, the shell | `dirty` (`register`, `entry`, `watchForm`, `snapshot`, `isDirty`, `guard`, `refresh`; an entry may carry a `shortcut`, the functions Ctrl+S calls for each mode), `mode` (`current`, `policy`), `router` (`set`, `current`, `go`, `leave`), `workLocale` (the language content is edited in, chosen once in the rail: `current`, `native`, `set( locale )`, and `follow( name, handler )` for a view that draws what depends on it - `name` is its entry of `dirty`, asked about before the rail changes the language - and `bind( name )` for a view that cannot draw another language and is loaded anew instead; an entry of `dirty` may carry `saving()`), `assetUrl()`, `publicUrl()`; a panel script reads none of it at load, `panel()` and `levels()` look it up when asked |
| CSS | the site: the custom properties at `:root` of `_nino/Nino.css` - the `--brand-...` and `--page-...` tokens of [`Nino.css`](development.md#a-features-components) - are what a feature's stylesheet uses, written without a fallback (`var( --page-space-2 )`: the token is there, or the kernel is older than the feature's floor); `.nino-sr-only` and `.nino-richtext`, and, where the module `Form` is on, `nino-form` with its `nino-form-submit`, `nino-form-message`, `nino-form-trap` and `nino-form-note`, and `nino-forms-check` and `nino-forms-group`, the checkbox beside its words and the group of radio buttons under one question in a form a feature writes. The workbench: the classes of the [panel recipe](recipes/admin-panel.md#designing-an-admin-frontend), among them the four of 1.8 - `.nino-admin-btn--small`, `.nino-admin-actions`, `.nino-admin-badge[data-state]`, `.nino-admin-move`; a panel stylesheet opens with `@layer nino.system, nino.tool, nino.local;` and writes into `nino.tool`, scoped to its own root |

### A mail from a feature

A feature that sends a mail chooses by who is waiting for the answer. A form that tells the visitor whether its mail went out calls `\Nino\Mail::send()` or `sendAll()`, which deliver in the request and answer `true` or `false`. A feature that wants the mail *out*, whatever happens to the request, calls `\Nino\Mail::queue()`:

```php
$id = \Nino\Mail::queue( $appData, [
	'to'			=> $email,
	'subject'	=> $subject,
	'body'		=> $html,
	'replyTo'	=> '',
], \Nino\Mail::CLIENT, 'welcome/'. $accountId );

if( $id === '' )
	trigger_error( 'Welcome mail refused: '. \Nino\Mail::queueRefused( $appData ), E_USER_WARNING );
```

An id is a promise: the mail is delivered after the response, by this request or a later one, or - after six attempts spread over fourteen hours - given up on and said in the log and on the dashboard, with a count and a time and never with an address. It survives a visitor who leaves, a server that drops the work after the response and a transport that does not answer. It is delivered *at least* once: a process that is killed between the transport taking the mail and the outbox letting it go sends it a second time, so a mail must do no harm twice. The key (`'welcome/'. $accountId`: a name of the feature, a slash, what it makes of it) makes a second call, with the same mail, while the mail waits or went out in the last ten minutes the same mail; another mail under that key is refused as a conflict. The body is rendered before the call - the outbox keeps a finished mail, and the fills of the request are not there when it is sent - and the feature registers nothing for the delivery: the transport of the site, a feature like Mailer included, takes it as it takes any mail. A feature that has mails of its own to send for the site's sake, with no visitor to charge, passes `\Nino\Mail::SYSTEM`; the kind decides whose hourly cap the mail counts against. A round mail that an account of the workshop sets off for many people passes `\Nino\Mail::OWNER`, and the feature that lists the people reads `\Nino\Mail::states()` for the mark by each name ([A round mail of the owner](development.md#a-round-mail-of-the-owner)). A mail that is worth nothing after a time - a code that is good for 15 minutes - carries `'expires' => 900`: from that second nobody delivers it, its text is in no file after the next run of the outbox, and `state()` says `failed`; a mail that lapses after a transport refused it shows the failure of the dashboard as a mail given up on, one that lapses before any transport was asked for it is no failure, and where no request and no cron job runs it lies until one does ([A mail that expires](development.md#mail)). The feature reads `\Nino\Mail::queued()` where it tells the operator, and it declares nothing in `data` for the outbox: the files are the kernel's and no backup carries them. A feature whose answer must not tell, by its time, whether it sent a mail - a sign-in that answers alike for an address with an account and one without - queues in the branch that has a mail and sends nothing in the other, and never sends in the request: the mail goes out after the response ([the time of the answer](development.md#mail)).

The whole contract is [Mail](development.md#mail).

## The panel

A panel is one screen of the workbench, the class `adminPanels()` answers while the
feature is active: it appears with the next load of the workbench after the
activation, goes with the next one after the deactivation, and always stands in the
rail's **Features** group whatever its `nav()` names. Its permission is offered on
the roles tab; the **Editor** role does not receive it by itself. The class of
`Hello` is `features/Hello/Admin/Admin.php`:

```php features/Hello/Admin/Admin.php
<?php
declare(strict_types=1);

namespace Nino\Modules\Hello {

	class Admin {

		public static function perm(): string {
			return '/_admin/hello/manage';
		}

		public static function actions(): array {
			return [ 'hello/list' => [ self::class, 'apiList' ], 'hello/save' => [ self::class, 'apiSave' ] ];
		}

		public static function nav(): array {
			return [ 'hello', '/_admin/hello/nav', 90 ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The sign of the rail entry: Lucide hand, geometry and no view
		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2"/><path d="M14 10V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>';
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			\Nino\Http::ok( $request, [ 'name' => \Nino\Modules\Hello::name( $appData ), 'longest' => self::_longest( $appData ) ] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			$name = trim( \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'name' ) );

			if( mb_strlen( $name ) > self::_longest( $appData ) )
				\Nino\Http::fail( $request, 400, 'the name is too long', 'hello_long', [ self::_longest( $appData ) ], 'name' );
			else if( \Nino\Filesystem::mutate( $appData, \Nino\Modules\Hello::STORAGE, static fn( mixed $state ): array => array_merge( [ 'format' => 1 ], is_array( $state ) === true ? $state : [], [ 'name' => $name ] ) ) === false )
				\Nino\Http::fail( $request, 500, 'could not write the name' );
			else
				\Nino\Http::ok( $request, [ 'name' => \Nino\Modules\Hello::name( $appData ) ] );
		}

		private static function _longest( array &$appData ): int {
			return (int) \Nino\Features::setting( $appData, 'hello', 'longest', 40 );
		}
	}
}
```

- Every action guards itself with `\Nino\Admin\Admin::guardPerm()` and the panel's
  permission, in its first lines, whatever the routing did. The uri (`hello`) names
  the link, the pane, the action prefix and the script's namespace
  `Nino.admin.hello`; a taken uri is dropped with a warning.
- A failure a person can cause carries a **code**: the panel's slug with its hyphens
  written as underscores, then a word (`hello_long`; `my_feature_empty` for
  `my-feature`), in lower-case letters, digits, underscores and hyphens, beginning with a letter (nothing else is looked up). Its sentence is the
  panel's text under `[[/_admin/error/<code>]]` in both languages, `%s` where a
  param goes; `Http::fail()` carries the English message for a log, the code, the
  params and the `field` refused, and `Nino.adminUi.api.errorText()` makes the
  sentence of them. A failure nobody can cause has no code and shows the server's
  message with the status in front. The kernel's scan for codes without a sentence
  does not reach `features/`, so a feature's test asks, as `Hello`'s does.
- `text()` names a directory of `<locale>.php` files, one for each interface
  language, merged into the workbench's fills while the feature is active: the nav
  label, the labels and errors its script reads and, for the code `hello_long`,
  `[[/_admin/error/hello_long]]` => `The name may have up to %s characters.`

The script runs on `Nino.adminUi.panel()`: it asks for `hello/list` when the pane is
first shown, calls `render( data, panel )` with the answer and says a failed load in
the mount (`/_admin/hello/error/load` unless `error` names another key). `dirty` is
the entry for `Nino.admin.dirty`: the shell asks Save, Discard or Cancel before it
throws away what the field holds, and `save( done )` ends in `done( true )` or
`done( false )` on every way it can. The script is `features/Hello/assets/admin.js`:

```js features/Hello/assets/admin.js
( function(wn) {

	wn.Nino.admin = wn.Nino.admin || {};

	const t = function( key ) { return Nino.content.getText( key ) };

	let form = null;
	let input = null;
	let bar = null;
	let saved = '';

	// Saving model A of the guide: a form, Save in the bar, the answer as the badge and a toast
	const save = function( done ) {

		Nino.adminUi.clearFieldErrors( form );
		bar.line.saving();

		Nino.admin.hello.call( 'save', { name : input.value }, function( status, response ) {

			if( status !== 200 ) {
				bar.line.error( status, response, '/_admin/hello/error/save' );
				input.focus();
				done( false );
				return;
			}

			saved = input.value = response.name;
			bar.line.saved();
			Nino.adminUi.say( t('/_admin/common/msg/saved') );
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
			done( true );
		} );
	};

	Nino.admin.hello = Nino.adminUi.panel( {
		uri    : 'hello',
		mount  : 'hello-list',
		dirty  : { isDirty : function() { return input !== null && input.value !== saved }, save : save, discard : function() { input.value = saved } },
		render : function( data, panel ) {

			const mount = panel.mount();
			const field = Nino.adminUi.textField( { label : t('/_admin/hello/label/name'), help : t('/_admin/hello/hint/name'), value : data.name, maxLength : data.longest, id : 'hello-name' } );

			// The one shape of a form: a fieldset with its legend, the grid in it that puts the label at the left of the field
			const set = Nino.adminUi.el( 'fieldset' );
			const grid = Nino.adminUi.el( 'div', 'nino-admin-fieldgrid' );

			set.appendChild( Nino.adminUi.el( 'legend', '', t('/_admin/hello/label/greeting') ) );
			set.appendChild( grid );

			form = Nino.adminUi.el( 'form' );
			form.noValidate = true;
			form.addEventListener( 'submit', function( event ) { event.preventDefault(); save( function() {} ) } );

			input = field.querySelector('input');
			input.name = 'name';
			saved = input.value;

			bar = Nino.adminUi.actionBar( { status : true, primary : Nino.adminUi.button( t('/_admin/common/label/save'), null, { type : 'submit' } ) } );

			grid.appendChild( field );
			form.appendChild( set );
			form.appendChild( bar.el );
			bar.line.bind( form );

			mount.innerHTML = '';
			mount.appendChild( form );
		},
	} );

} )(window);
```

The script never renders a literal sentence (every word is a fill read through
`Nino.content.getText()`), never posts by hand (`panel.call()` wraps
`Nino.adminUi.api.call()`) and reads no member of the shell at load. The router,
drill-down levels, tables, scoped permissions, the design rules and a panel's own
template and icon are the [panel recipe](recipes/admin-panel.md).

## The install unit

`install/manifest.php` has the shape of the unit of a module in the setup wizard
(the [installer package recipe](recipes/installer-package.md) lists its keys,
[Library Format](setup.md#library-format) the files). An activation reads `routes`,
`templates`, `files`, `elementTypes`, `elements`, `blacklist` and `config` from it,
and `text/global.php` and `text/<locale>.php` for every available locale; `key`,
`imageSlots`, `whenNoPages` and `menus` besides; `label`, `moduleClass`, `requiresModules`, `preset` and
`navs` are the wizard's alone. The unit of `Hello`, `features/Hello/install/manifest.php`, copies
the template of the page `/hello` and adds a section of the privacy policy:

```php features/Hello/install/manifest.php
<?php return [
	'templates'	=> [ 'page-hello.tpl' ],
	'elements'	=> [ 'privacy' => 'elements/privacy.php' ],
];
```

The unit is applied **add-only**, on every activation of the feature - the first,
each update, a re-activation: a route key `config.php` has, a template `templates/`
has, a file that is there and a text key that exists stay as they are, and a
`config` default is written only where the project has no value. A copy the project
changed stays, one it deleted comes back; the wizard applies a unit with overwrite
on through the same `\Nino\Features::applyUnit()`. So a unit never relies on being
applied again to fix a file, and never promises an uninstall; a route the feature
owns while it is active belongs in `init()`, a visitor page in the unit's `routes`.
The words of a unit live in `/feature/<key>/<part>/<name>` (the feature's own
function) and `/template/<file name without .tpl>/<part>/<name>` (a word only a
template of the unit reads: `page-hello.tpl` reads
`/template/page-hello/intro/title`); a unit never delivers a `/project` key ([the
key grammar](development.md#the-key-grammar)); where a feature reads its own words, or those of the feature it requires, it asks `\Nino\Features::textRoot( $appData, $key )` for the root, `/feature/<key>/`
(`/module/<directory>/` for a feature the kernel delivers), and writes `<root>menu/open`. A language the unit has no
`text/<locale>.php` of is given the keys of one it has - the native language's,
else English's, else the first by its code - with an empty text each, add-only, so
that every key of the unit exists in every active language: the page shows the
native text where a language has none of its own (the [fills
chain](development.md#textfills)), and the Dashboard counts what is left to
translate. A language the unit has words of keeps them.

**The words of a page.** A route of the unit is a page, and a template's `<title>`, a menu and
the description read its name, title and description - the system's keys
`/_nino/webpage<uri>/name|title|description`, `<uri>` the `uri` of the route. The text files of the
unit carry them like any other word, as the Legal module's do:
`'[[/_nino/webpage/hello/title]]' => 'Hello World'` in `install/text/en_US.php` and in every
language the unit ships. They are the one exception to the four parts of a key
([the key grammar](development.md#the-key-grammar)), and they are written for the page the unit
routes: a project that holds the route key for another address has another page, and the words
name a page it does not have. Where the unit brings none, somebody names the page in the Routes
panel, and its `<title>` shows the key until then.

**Picture places.** `imageSlots` declares the places a template shows with `[image <uri>]`, as it does
for the wizard's own units: `uri => [ 'label' => string or locale map, 'width', 'height' ]`, and
`'filename'` for a picture the unit ships in a directory it lists under `files`. A place is added to
`/nino/html/images` where the project has none under that uri, in every activation; one the
project has - with the picture an editor uploaded - is not touched, and switching the feature off
or removing it leaves the places where they are, as it leaves the templates and the words.

**Labels of the fields.** The element form names a field by its key. An element type of the unit
(`elementTypes`) says what to call each field where it describes it, in the model:
`'time' => [ 'type' => 'string', 'label' => [ 'en_US' => 'Time (HH:MM)', 'de_DE' => 'Uhrzeit (HH:MM)' ] ]`
([element types](recipes/element-types.md)). The form takes the language of the workbench, else
English, else the first. A type file that is in the project is the project's - the copy is
add-only - and a project names a field in a language of its own with the text
`/_admin/elements/field/<type>/<key>`, which comes first.

**The start of a site.** A unit that says `'whenNoPages' => true` brings the start of a site - its pages, its header
and footer, its menus - and is applied as a whole or not at all: where the project has a page already, an activation
(the first, each update, a re-activation, **Apply content again**) places nothing of it - no route, file, template,
picture place, word, type, element, blacklist key or default - and the activation itself goes through. A page is what
the Routes panel lists as one: a stored GET route whose body draws a template `/templates/page-*` (`\Nino\Pages::isPageRoute()`,
read from the routes `config.php` holds, so the legal pages, which the module registers at run time, and `robots.txt`, `sitemap.xml`
and `llms.txt` are none, and neither is a route that answers with an error status, a 404 page: a site that has only that has not begun). The decision is made once, before anything is written, against what the project had before the unit: the
unit's own routes do not count against it. A project without a page gets the unit as any other, and the setup wizard, which applies
a unit with overwrite, ignores the key. The report of the application (see `\Nino\Features::applyUnit()`) says it with `'skipped' => 'pages'`
and every count 0, and the panel says it after **Apply content again**, after an activation, after an installation and after an update, and the log of the actions holds it: "The project has pages already; <feature> placed nothing."

**A page in a menu.** A feature that routes a page at run time - in its `init()`, the list of the posts at `/blog` - has no route in
`config.php` to carry a menu, and says in its unit which menu of the project the page belongs in: `'menus' => [ 'main' => [ '/blog' ] ]`, a menu
key and the Element-URIs of its pages, the `uri` of the route the page has. Three ways put a page into a menu, and a unit takes the one that fits the page:

| The page is | Where the menu is said |
| --- | --- |
| a route the unit puts into `config.php` with its `routes` | on the route, `'navs' => [ 'main' => 5 ]`, as the pages of the start of a site do |
| a page a feature routes at run time | `menus` of the unit, as here |
| a menu the project does not have, to be made | `navs` of the unit, which only the setup wizard reads (and it makes the menu) |

*When.* The first time the unit is applied - the first activation or installation, in the wizard's step of an application too - each page is
put at the end of its menu, as a membership under its Element-URI in `/nino/html/navroutes`: the place after the greatest one a route or a
membership holds in that menu. It is offered **once**: a switch off and on, **Apply content again** and an update offer nothing, and neither does
any later activation. *Where.* Only where the project has that menu - a menu is never made, a project without the Navigation module has none - and
the page is not in it already. A page that is the Element-URI of a route `config.php` stores - the start page, a page of the project, one the project
took out of the menu - is the project's and is left out with a warning, as are the pages beyond the eighth of a unit (eight at most, all
menus together), and a page whose place would be no whole number. A menu key has to be a slug and a page a string that starts with a single `/` and has
no control character; what is not is left out with a warning. A page that the unit names and nothing names in the texts, `/_nino/webpage<uri>/name`, is put and
said in a warning: a menu draws a page by that text and skips one that has none, so the unit brings it in `install/text/`. *Who.* A signed-in account
that may switch the feature on and may not change menus (`/_admin/navs/manage`) offers none; the answer, the toast and the log say that the page is in
no menu and that it is entered in the Navigations panel, and that is the end of it: the offer was made, and nothing puts the page into a menu later. The setup wizard's
account is signed in and holds the right, so a wizard puts its pages in. The features that come on because the one asked for requires them are told the same way.

*Off and removed.* What leaves the menus and what comes back follows the pages the unit **names** under `menus` - not whether the kernel put them there. A feature that is
switched off has no route, and the kernel takes the memberships of those pages out of `navroutes` - out of every menu they stand in, whatever the menu is called by now,
and whoever put them there, the kernel or the editors by hand, on a site that had the feature before this key existed too - and parks them in the record of the feature under
`/nino/features`, `'menus' => [ 'offered' => [ '/blog' ], 'parked' => [ '/blog' => [ 'main' => 3 ] ] ]`, in the same write of `config.php` that takes the class out of the list
(`deactivateRefused()` does the same, reading the key of the feature from its refused `feature.php` where it can). So no membership of a page that nothing routes is left for the
Navigations panel to drop or for a page of the project under the same address to inherit; the address answers 404 and the panel lists nothing. Switched on again, they are put back:
into the menus that exist under the same key, at the old place where it is free, else at the end; what cannot be put back is left out. With the Navigation module off nothing can be put back and
it stays parked until the module is on and the feature is activated once more. A menu made anew under the old key while the feature was off is a menu that has that key: the page goes into it.
A page the project took out of the menu before is not there to park and does not come back. A feature that is removed takes the memberships of its pages, present and parked, away;
nothing is left in `config.php`, the address is free for a page of the project, and the feature put in again is a first time. A page another feature that is on names too, and a page that
is the Element-URI of a route `config.php` stores, are not parked or taken. While the feature is on, its run-time route covers a page of the project under the same address: the menu
draws the feature's page there, and the project's own page is back when the feature is off. All of it is written by `\Nino\Modules\Navigation` (`append()`, `take()`, `restore()`), which
is where a Navigation that becomes a feature of its own keeps it. The report of the application says
`'menus' => [ 'added' => 1, 'present' => 0, 'in' => ..., 'denied' => false, 'refused' => [] ]`, and the panel says after an activation, an installation and an update which page is
in which menu - a page that has no name yet is said apart, as waiting for its name - and the log of the actions holds it. The kernel asks a feature no question about its routes at the time it is
switched on - a module registers them in `init()` for each request, with callbacks that add up if it is called twice - so what a unit may offer is kept to what is no page of the project. A feature
is written as in [Developing a feature](#developing-a-feature) and the catalogue's "Write a feature".

A feature that processes personal data brings its own section of the privacy policy
with the `elements` key, a map of an element type's uri to a file of the unit. The
type `privacy` is the Legal module's; the file has no `model`, so it adds to the
type and never creates it. `\Nino\Elements::seed()` only adds: an element that
exists, a section the editor changed and one deleted for good stay as they are, and
a feature that is switched off leaves its section in place - the deactivation dialog
and the Dashboard say so, and the editor may hide it. The file of `Hello` is
`features/Hello/install/elements/privacy.php`:

```php features/Hello/install/elements/privacy.php
<?php return [
	'*' => [ 'hello' => [ 'order' => 860 ] ],
	'en_US' => [ 'hello' => [
		'title' => 'Hello World',
		'text'  => '<p>The Hello World feature stores one name, the one its panel saves. Nothing about a visitor is stored. Questions: #/project/company/contact/email#</p>',
	] ],
	'de_DE' => [ 'hello' => [
		'title' => 'Hallo Welt',
		'text'  => '<p>Das Feature Hallo Welt speichert genau einen Namen: den, der in seinem Panel eingetragen wird. Über Besucher wird nichts gespeichert. Fragen: #/project/company/contact/email#</p>',
	] ],
];
```

Write the section in your own words, one entry for each language the feature ships,
stating only what the code does; a placeholder names a fact of the website. It is a
starting point and no legal advice, and the feature's manual says so. `order` is the
position, a hundred apart so a section fits between two: the module's own sections
stand in 100-399, cookies and consent in 400-499, contact, forms and mail in
500-599, statistics in 600-699, embedded content in 700-799, other features in
800-899, the closing section at 900.

## Settings

A setting is a form control the Features panel renders and the kernel validates. The
manifest declares each by name (`/^[a-z][a-zA-Z0-9]*$/`) with a schema:

| Key | For | Rule |
|---|---|---|
| `type` | all | one of `\Nino\Features::SETTING_TYPES`: `bool`, `int`, `string`, `text`, `email`, `url`, `select`, `secret`, `lines` |
| `label`, `hint`, `required` | all | a string or a `locale => string` map (the label is the name where left out); `true` refuses an empty value |
| `default` | all but `secret` | has to pass its own schema; without one the type's zero value answers (`false`, `min` or `0`, `[]`, `''`) |
| `min`, `max`, `unit` | `int` | ints, `min` not above `max`; the word the number is counted in, written in brackets behind the label: `seconds`, `minutes`, `hours`, `days`, `weeks`, `months`, `items`, `uris`, `per-hour`, `percent`, `characters` and `mm` have a word in both languages (`[[/_admin/common/unit/<unit>]]`), any other is written as it stands |
| `maxlength`, `pattern` | `string`, `text`, `secret`, `email`, `url` | `maxlength` 1 to 1000 (`text`: 10000), the ceiling where left out; `pattern`, for `string` only, a valid regular expression |
| `options` | `select` | a non-empty map `value => label` |

A feature reads its settings through the kernel, never out of the array:
`\Nino\Features::setting( $appData, 'hello', 'longest', 40 )` answers one,
`settings( $appData, 'hello' )` all - the stored value, else the default, else the
type's zero value, and only for names the schema declares: a stray key in
`config.php` is no setting, a stored value that no longer validates is not handed
over, and `setting()` answers your `$default` for a name the schema lacks. The
values live under `/nino/features` in `config.php`, per feature as
`key => { version, settings }`:

```php
'/nino/features' => [ 'hello' => [ 'version' => '2.0.0', 'settings' => [ 'greeting' => 'Hi', 'longest' => 24 ] ] ],
```

The form is on the feature's screen in the Features panel; a feature that also has a
panel gets a **Settings** tab in it, on the same actions and the permission
`/_admin/features/manage`, without a line of code. A form posts strings and gets the
real types back. `saveSettings( $appData, $key, $posted )` validates every setting
before any is written and answers `name => message` for each one it rejected, `[]`
when it saved; a setting the form did not send keeps its value, a `secret` posted as
`''` keeps the stored one and as `null` clears it.

A `string` is one line: a line break or a control character in it - a tab, a null byte,
U+0085, U+2028, U+2029 - is refused when it is saved, and a stored value that has one is
read as a value that does not validate, so `settings()` answers the default. A `text`
keeps its line breaks.

## Updates and the upgrade hook

A feature is updated by replacing its directory with the new release and activating
it again; the Features panel offers **Update** for an active feature whose manifest
version differs from the recorded one. The unit adds what is new and leaves what the
project has; then, if the class has it,
`upgrade( array &$appData, string $fromVersion ): bool` runs - when a version is
recorded that differs from the manifest's, whether the feature is on or was switched
off and its directory replaced in the meantime - with the recorded version, after
the unit and before the new version is recorded. `false` refuses the update and the
record stays; any other answer lets it go on, and a class without the method is
updated without a migration. A request that already loaded the previous version's
class refuses the activation; the panel activates again in a request of its own.

`\Nino\Features::activate( $appData, $key )` answers `true`, or why not: a feature
with `problems` (all named), a requirement that failed, a unit file it cannot copy;
a cycle of requirements ends where it began. The unit is applied to the routes
`config.php` holds, not this request's own, so a route `init()` registers is not
written; activating again at the same version changes only what the unit adds back.
`deactivate( $appData, $key )` is refused while an active feature requires the one - unless that feature is one the other requires itself, which is a circle that locks nothing -
and changes nothing for a feature that is off. `activate()`, `deactivate()`, `remove()`, `saveSettings()` and the catalogue's
`install()` run under one lock of the features (`\Nino\Features::lock()`, not waited
for): a change that finds it taken answers `\Nino\Features::BUSY` (or `UNLOCKABLE` where the lock file cannot be opened) and has done
nothing; what they write to `config.php` they read from it first.

- Migrate only what the feature owns: its `data/` files and its own config keys,
  through `\Nino\Filesystem::mutate()`, returning its result:
  a callback that aborts answers `false`, which refuses. The activation writes its
  own keys as differences against `config.php` as it stands, so a hook that migrates
  `config.php` with `mutate()` is not undone.
- Be idempotent for the versions it handles: a refused update is asked again at the
  next activation. Bump `version` with every release a project should tell apart:
  the hook runs on any difference, a lower manifest version too.

### Data, backup and restore

What a feature writes under `data/` belongs to the project: the daily backup carries the files `data` in the manifest names, as they lie when it packs them. Whatever size the files below `data/` have is carried: an archive is
written and opened in chunks, so its memory does not depend on them, only the time of a run and the room on the disk do. The templates a feature keeps in a folder of the project's `templates/`
(`templates/<folder>/<name>.tpl`) are carried by the backup like the ones directly in it, and a restore makes the folder where it is missing.

**A file that is written to while it is copied** is no state it ever had, and a database in write-ahead mode is not a database at all without the `-wal` that holds what was committed last. A feature that keeps one registers a
callback of `'/nino/admin/backup'` in `init()` (`\Nino\Callbacks::registerCallback()`). Before a run packs anything - the daily backup, **Back up now** and the safety snapshot a restore takes first, so on the recovery page as well - the
kernel calls it for every feature that is switched on, under the lock of the run, with `{ dataDir, staging }`: the live `data/` directory and an empty folder of the run, closed to other accounts. The feature writes there a copy of its
file that is whole, under the name the file has below `data/`, and the run packs the copy where the live file stands:

```php
public static function callbackBackup( array &$appData, array &$args ): void {

	$file = $args['dataDir']. '/crm.sqlite';

	// The first days of a feature have no database yet: what does not exist is not handed over, and not made here either
	if( is_file( $file ) === false )
		return;

	$live = new \PDO( 'sqlite:'. $file );
	$live->exec( 'VACUUM INTO '. $live->quote( $args['staging']. '/crm.sqlite' ) );

	// The copy is checked, not the source: what throws here ends the run, and no archive holds a database that is broken
	$copy = new \PDO( 'sqlite:'. $args['staging']. '/crm.sqlite' );
	if( $copy->query( 'PRAGMA integrity_check' )->fetchColumn() !== 'ok' )
		throw new \RuntimeException( 'the copy of crm.sqlite is damaged' );
}
```

- **A copy that is checked.** `VACUUM INTO` carries an index that is damaged into the copy as it is, and `PRAGMA quick_check` of the source does not look at the indexes, so it does not find it. A feature that wants a copy it can rely on runs `PRAGMA integrity_check` on the copy, as the example does, and throws where the answer is not `ok`; the check reads the whole file (a database of about 120 MB took under half a second where it was measured). The copy is opened by a connection of its own and by nothing else - see the next point.
- **A database is opened by its connection and by nothing else.** A feature that keeps a SQLite file never opens that file with `file_get_contents()`, `fopen()`, `copy()`, `finfo` or anything else that takes a descriptor of its own, as long as the same process holds a connection to it: when such a descriptor is closed, the operating system lets go of every lock the process holds on the file, the ones of the connection included, and another process can write into a transaction that believes itself alone. What is needed of the file - its size, a copy, its first bytes - is asked through the connection (`VACUUM INTO`, `PRAGMA page_count`) or of the directory (`filesize()`, `stat()`, which open nothing). A second connection (`PDO`) to the same file does no harm.

- **Only its own names, and only files that exist.** A copy replaces a file the manifest of the feature names under `data` (the file, or a file below a folder it names) and whose live file the backup would carry; nothing is added. What else lies in the staging when the callback is done - a name the manifest does not give, a file of another feature, a case twin of an own name, a name the backup never carries (`auth-tries.php`, anything hidden), a link, a folder with no file of its own, a pipe or other entry that is no file, a file with a second name (a hard link), something put above the staging or into the folder of another feature, a file that two features hand over - ends the run, with the feature named.
- **Whose callback it is** is read from its class: a callback of a class below the namespace of a feature that is switched on is that feature's. One that no such feature owns - a function, a class of `app/` - has no manifest that could name a file for it and is not asked; the log says so at every run. The recovery page asks the callbacks of the features that `config.php` lists: where that file cannot be read no feature is on, and the safety snapshot packs the files as they lie.
- **A callback that throws ends the run, and nothing falls back to the live file.** The answer is a `500` with the code `backups_hook` and the key of the feature, the daily backup notes it as the reason it did not run (and the request of the workbench that made it is answered as it would be anyway), the log holds what the feature said, no archive is written and the folder of the copies is gone. A write that the disk refuses in the callback ends the run as a full disk does (`backups_write`, the record says `disk`). A request that ends in the callback - `exit`, the time or the memory limit of the request, which is the limit of the callback - leaves the record of the run with the key of the feature (`feature`) and the cause (`hook`, or the limit that php names), the folder of the copies is removed, the lock goes with the process, and a backup that was asked for by hand and has nothing sent yet is answered `500` with the code `backups_hook`. A callback is part of the request that makes the backup: a slow one makes slower the one request of the workbench that makes the daily backup. A callback does not ask for a backup of its own.
- **A restore is never kept from happening by a callback.** The safety snapshot before a restore is the net under the state as it is: where the callback of a feature throws, hands over what is not its own or finds the disk full, the snapshot holds that feature's files as they lie, with the files an open database keeps beside them - also where the main file is gone; one that is a link is left out and named in the stamp - says so - in the stamp of the snapshot, in the log and in the answer of the restore, which is no failure - and the restore goes on. That is what a database that is broken needs: the callback of the manual throws on it, and the good backup is restored all the same. The backup that is asked for and the daily one end as above.
- **Room.** A run counts the live size of every file a feature with a callback owns, and what an open database keeps beside it, as the room the copies take, before it asks; and counts again with the sizes of what the features wrote, before it packs.
- **The files beside a database.** Of a file that was handed over, its `<name>-wal`, `<name>-shm` and `<name>-journal` are not in the archive: they belong to the live file, and next to the copy they would be applied to it - unless a manifest names such a file itself, which is then its own file. The archive lists in its stamp the names that were handed over, and a restore puts such a file back as the one file: it removes those three from the live `data/`. The list is the archive's word, so it is believed as far as the project can check it: only for a name the archive carries, that a feature which is switched on and has a callback of `'/nino/admin/backup'` owns by its manifest, whose companion no manifest names and the archive does not carry itself, and only if the list has no more names than the archive has files below `data/`. An archive of another project - one that is restored with a recovery key that was typed - takes nothing away: the answer says beside how many files the companions were left standing (`backups_companions_left`, also on the recovery page), and the operator removes those three files beside each database by hand before the database is used, or the old log of writes is applied to the restored file. No file is removed for one that is packed as it lies, for one that only ends so, or for an archive without the list.
- **A file that goes while the archive is packed.** A project is written to while a backup reads it, so a file the run listed can be gone when it comes to pack it. What a backup leaves out where it is gone is the companion of a database - `<name>-wal`, `<name>-shm`, `<name>-journal` where `<name>` is a file the run packs, or a companion the safety snapshot added beside a database it packs as it lies - and the temporary file of a writer of the kernel, `<name>.<12 lowercase hex digits>.tmp` (`\Nino\Filesystem::putFileContent()` and `mutate()`, the workbench's writers, the copy a restore makes beside a file). A temporary file that is there is packed like any file, as it was. A file that a manifest names whole - `/data/foo-wal`, `/data/x.tmp` - is the feature's own file, whatever it is called, and is never taken for one of these; a file of a folder a manifest names is told by its name alone, so a feature that keeps its own files named `<name>.<12 hex digits>.tmp` or `<name>-wal` beside a file `<name>` in such a folder names them whole, or has them backed up with a `backups_source` where one of them is gone. What is left out is in the stamp of the archive (`left`, the archive names, only where there are any: a restore reads `nino` and `copies` of the stamp and nothing else, and an older kernel takes the note as the one it does not know), and one notice in the log names the files. A log of writes that is gone was written into the main file, so the files beside a database are read before it: the main file holds what the log held. A raw copy of a database that is written to is no consistent snapshot all the same, which is why a feature that writes one hands over a copy (see above). Every other file that is gone, is no file any more, cannot be read or - where php names it as the file it could not open - is another file by then ends the run with the code `backups_source` and the name of the file in the archive (a `500`, and the sentence of the panel names the file; the safety snapshot of a restore ends the same way, and the restore has not begun). A backup that failed to write is answered `backups_write`, unless a listed real file is gone or is the file that php names as the one it could not open or write and that is another file by then: a file that changed elsewhere while a write failed proves nothing. The daily backup that ends on `backups_source` is tried once more the same day, by the next request that makes it; a run that ends in any other way, or that the server ends, is one attempt a day as it was. A restore of an older archive that holds such temporary files puts them back as the files they are.
- **Without the callback** a file is packed as it lies, as it always was. That is whole for a file that is written whole - `\Nino\Filesystem::putFileContent()` and `mutate()` replace it by a rename - and not for a database that is written in place: the archive holds what was on the disk at that moment, with the files beside it only where the manifest names the folder, and a restore does not take the companions of the live file away.
- **A database that is in use is not written over.** A restore puts a file that a callback handed over back by copying onto the live file, as the one kind of file that is written in place. A SQLite database that another process has open is written to by that process with pages it computed from the content the copy has replaced, and the file is damaged then: with four processes writing, more than half of the restores that were measured ended with a database that failed `PRAGMA integrity_check`. So before it copies, the restore looks at each file that the archive lists as handed over by a callback of `'/nino/admin/backup'` and that is still in the staging after the callbacks of `'/nino/admin/restore'` (the feature did not restore it itself), and asks whether a POSIX lock lies on the live file. SQLite holds one for as long as a connection is in a transaction, and in write-ahead mode from the first read of a connection for as long as it is open - a connection that is open and has read nothing holds none, and in rollback mode a connection between two transactions holds none either. Such a file is **not put back**, and the files beside it (`-wal`, `-shm`, `-journal`) stay where they are, since the process that holds the lock needs them. Everything else of the archive is restored. The answer is a `500` that says what was left (see the codes below), and the log and the line of the activity log name the files. A file that no process holds is put back by the copy and loses its companions as above - the `-journal` of a writer that died is the usual one, and it must go: the next process that opens the database would roll it into the restored file. A file of a feature without a callback of `'/nino/admin/backup'`, a file that the archive does not list, and every file of an archive of another project are put back by the copy onto the live file, as is every file below `data/` that is no php: what the kernel's writers write there is php and is replaced by rename (see below), and a database is opened by its feature, which keeps the file it opened - a rename would leave a process with a connection committing into a file nobody reads, which is why a feature that keeps a database hands it over and restores it itself.
- **What to do where files were left as they are.** Repeat the restore: a lock is gone as soon as the process that held it has ended its transaction. Where it keeps being refused, stop what writes to the database for the moment of the restore - the workers of the web server, a cron job; the maintenance mode of the workbench does not do it, since it lets every signed-in account through - or the developer of the feature makes the feature restore its database itself, as below. The safety snapshot that the answer names holds the state before.
- **How a lock is seen, and what that cannot do.** The restore asks the system and not the file: `stat()` of the live file for device and inode, and `/proc/locks`, which lists the locks of the processes of the namespace the restore runs in. It never opens the database to ask - closing any descriptor of a file ends the locks the closing process holds on it. Where `/proc/locks` cannot be read (a system that is not Linux, a restricted one) the restore knows nothing of the locks and puts the files back by the copy, as it does any other file. A lock is not seen either where a filesystem keeps it elsewhere (NFS, some network and union filesystems), where it is held by a process in another PID namespace (a container beside the one the restore runs in), or on a file of an overlay whose layers lie on different filesystems. The look at the locks and the copy are not one step. The restore looks three times: before the files beside a database are removed, right before the files of `data/` are copied, and right after. A process that takes its lock between the first look and the removal of the files beside the database (a step of microseconds) has them removed under it; its database is left as it is, if the second look sees it. In rollback-journal mode the `COMMIT` of its open transaction then answers a disk I/O error although its rows landed in the file, and a writer that is killed after its journal went leaves its uncommitted work in the file; in write-ahead mode a commit that another process made through the log that was removed is acknowledged and lost. A process that takes its lock after the removal makes its own files beside the database, and the second look leaves its database alone. One that takes its lock while the file is copied is seen by the third look, which cannot undo the copy: the answer says that the file was written while it was in use and may be damaged (`backups_restore_written`). That makes the window narrow, a few milliseconds where it was measured, and not shut. Only the callback below makes a restore of a database safe.
- **A callback of the restore that throws does not end the restore.** `'/nino/admin/restore'` runs after `config.php` has been replaced, so a restore that ended there would leave the project half in the state of the archive. The exception - an error as well - is caught for the callback that threw it, the callbacks of the other features run, the log holds what the feature said, and what is left of that feature's files in the staging is put back as if it had no callback, with the rule of the databases in use above. A file that the callback took out of the staging before it failed is not put back by the restore, whatever the callback made of the live file: the answer names it. What ends the request - `\Nino\Runtime::fail()`, `exit()`, the limits of the request - is not caught: the restore ends where it is, with `config.php` replaced and everything else of the project - the files of `data/`, the texts, the templates, the assets - as it was, and the callback must not use it for an error it can say by throwing.
- **What a restore answers where something was left out.** The restore went through, and ends as one that succeeded does: the session of the account that ran it ends and the token is a new one, the notices (another version of Nino, a snapshot that is the state as it was, files beside a database that were left) are in the answer, and the tries of a recovery key that was typed are let go. The answer is a `500` all the same, and names what it did not do, in fields of its own whichever code it has: `features` (the keys of the features whose callback threw), `files` (the files left as they are because they were in use), `taken` (the files a failed callback took out of the staging), `written` (the files that were in use while they were put back), `cut` (the entries of the archive whose copy is not whole), `waiting` (the files whose lock another request held for as long as the restore waits: they are as they were). The codes: `backups_restore_callback` (a callback threw), `backups_restore_inuse` (files left as they are), `backups_restore_both` (both), `backups_restore_written` (a file that was written while it was in use may be damaged), `backups_restore_waiting` (files that another request was writing for the whole wait were left as they were; everything else is put back, and the restore ends as one that went through does); where more than one of these happened the answer is the worst of them, in this order: `backups_restore_partial` (a copy that fell short: the restore stopped halfway, ends where it is as a restore always did and does not end as one that went through does, and its sentence sends the reader to the activity log for what else happened), waiting, written, both, a callback, files left. The line of the activity log and the log of errors have a line for each of them: the copy that is not whole, the callbacks that threw, the files left as they are, the files a failed callback took out, the files written while in use. The sentence carries one word or number for each place - the first key, a number of files - and the names are in the fields. On the recovery page, where no account may be able to read the activity log, the sentences also name the log of errors of the project, `private/data/logs.<year-month>.php`.
- **A restore is done in part where a file is shorter than its source.** A copy in place that answers `true` has written every byte; a database that was handed over and is longer than the archive's was written to by another process after the copy - a database that is in use grows - and is no failure. Every other file is put back by rename (see the next point) and is compared exactly (a database below `data/` is put back in place, with the size rule above): a file that could not be written, or that is not the archive's file in size and hash, is named in the answer (`cut`, the first ten) with the code `backups_restore_partial`.
- **A restore puts a file back beside the file and by rename, and under the lock of its writers where they take one.** Every file of the archive but the files below `data/` that are no php is copied beside its place and renamed over it, so that a reader sees the file as it was or as it is and never half of it. Where a writer reads a file, changes it and writes it back - `\Nino\Filesystem::mutate()` and its like lock the virtual path - the restore takes that lock first: `config.php`, `text/`, `elements/`, `templates/`, the php files of `data/`, and the lock of the features for `blocks/` and `themes/`, which are written as packages. `assets/`, `images/` and `fonts/` are only ever written whole and are put back without a lock. All these locks are taken before the first byte is put back and before the safety snapshot, so that the snapshot is the state the restore replaces, each without waiting and for 10 seconds in all: a restore that has not got them all by then answers `409` with the code `backups_restore_busy` and the virtual path of the file another request was writing, and has changed nothing. A lock is an open file; the restore holds as many as a quarter of the limit of open files of the process allows (256 at the usual 1024), and locks the files beyond that one at a time as each is put back, each with the 10 seconds of its own (so the whole wait can be several of them): a file whose lock is not had then is left as it was, and the answer is `500` with the code `backups_restore_waiting` and the files named (`waiting`), apart from `backups_restore_partial`, which is for files that could not be written. Each file is compared with the archive's by size and hash while its lock is held, and the lock goes as the file is in place: a save that waited for it reads the file that was put back and writes on top of it. The restore never waits for a lock for longer than a fifth of that time while it holds others - then it lets go of all of them, leaves a moment to whoever waits and starts over - and that, more than the order it takes them in (the lock of the features, `config.php`, the texts, the elements, the templates, the data), is why it cannot be one side of a deadlock. Writers wait while the safety snapshot is packed (a text save waited 5.6 s for a project of 200 MB). A write of the kernel that a callback of the restore makes lets go of the lock of that path early, so that a save of another process may be acknowledged and afterwards replaced by the archive's file; the restore asks for the lock once more before it puts that file back, and a save that waited until then is applied on top of it.
- **A disk needs the room of the copy.** A file that is put back by rename exists twice for a moment: the room a restore asks of a disk is what its files grow by, the size of the largest file that is put back beside the one it replaces, and the reserve - a restore that cannot have it is refused before anything is copied, with the numbers of the room (`backups_disk`), also where the same restore would have gone through by the copy in place. What an ended restore left beside a file (`<name>.<12 hex digits>.tmp` older than a minute) is taken away when the next one begins.
- **What no lock protects.** A file that a feature writes itself - with `file_put_contents()`, a rename of its own - and not through `\Nino\Filesystem::mutate()` or `putFileContent()` has no lock that a restore could take. A php file of `data/` is put back by rename all the same, so nobody sees half of it, but a write of the feature that read the file before may land on top of what was put back.

**What the check of the folder is and what it is not.** The callback is code of the feature and runs with the rights of the kernel, like the feature itself. The check finds a callback that is wrong - one that writes where it was not asked to, leaves a link or a file of another kind, or hands over a name that is not its own - and names the feature. It is no defence against a feature that means to deceive the run: such a feature can read and write every file of the project itself, put back a time it changed, replace its copy or the copy of another between the check and the packing. What it is not asked to do it cannot be stopped from doing. What the archive holds of a `.php` name is judged by a restore as any other: one that was opened with a typed key is refused when it holds code.

**Modules under `app/`** have no manifest that the kernel reads - `\Nino\Features::all()` reads `features/` - so no `data` that a backup carries and no callback of `'/nino/admin/backup'` that is asked: a callback that no feature owns is not called, and the log says so at every run. What the backup carries of a module of a project is what it carries of any project: the files it names itself (`newsletter.php`, `forms.*.php`, `logs.*.php`) and what lies in the directories of the workbench. A project that keeps a database in a module under `app/` makes it a feature (a directory below `features/` with a `feature.php`) to have it backed up whole.

A feature whose restore has to merge rather than overwrite - a removal that must survive an older backup, a counter that must
not go backwards - registers a callback of `'/nino/admin/restore'` in `init()`. The Backups panel and the recovery page
call it, while the feature is active, with `{ dataDir, staging }`, the live `data/`
directory and the extracted backup; it rewrites its own files in the staged copy
before that is copied over the live one.
[`Newsletter::callbackRestore()`](https://github.com/dapeio/nino-features/blob/main/features/Newsletter/Newsletter.php)
of the catalogue is the reference.

**A feature with a SQLite database restores it itself.** The copy over a live database is what damages it where another process writes to it, and a database that is in use is left as it is (see above). A feature that keeps one registers a callback of `'/nino/admin/restore'` that writes the copy of the archive into the live database with the online backup API of SQLite - `\SQLite3::backup()` of `ext/sqlite3`; `pdo_sqlite` has no such call - and takes the staged file out of the staging. SQLite does the writing page by page under its own locks, so every process that has the database open sees the restored content through its own locking, the journal mode of the live file stays, and the restore itself writes no file of the database. The staged file is `<staging>/data/<name>` (the staging has the layout of the archive, and a file of `data/` lies in its folder `data`), the live one `<dataDir>/<name>`. A writer waits for the length of the copy; in 50 restores of a small database under four writers it was over half a second in three. What is gone from the staging the restore does not touch, neither the live file nor the files beside it:

```php
public static function callbackRestore( array &$appData, array &$args ): void {

	$staged = $args['staging']. '/data/crm.sqlite';

	// An archive without the database restores none, and without ext/sqlite3 there is no online backup: the file stays in the staging, and the
	// restore puts it back by the copy - or leaves it as it is where a process uses it, and says so in its answer
	if( is_file( $staged ) === false || class_exists( '\SQLite3' ) === false )
		return;

	$from = new \SQLite3( $staged, SQLITE3_OPEN_READONLY );
	$to		= new \SQLite3( $args['dataDir']. '/crm.sqlite' );
	$from->enableExceptions( true );
	$to->enableExceptions( true );
	$to->busyTimeout( 5000 );

	// Page by page under the locks of SQLite: it throws where it cannot get them in the time, and the restore is not kept from happening for that
	$from->backup( $to );
	$from->close();
	$to->close();

	// Restored: without the staged file the restore touches neither the live file nor the files beside it
	unlink( $staged );
}
```

The callback never opens the live file with `copy()`, `fopen()` or `file_get_contents()` (see above). It can keep its connection open as long as it likes: the backup API is a connection like any other. Where the callback throws - a writer holds its transaction for longer than the busy timeout, for one - the restore goes on as described above: the staged file is put back by the copy, or left as it is where a process holds a lock on the live one, and the answer says both.

## The feature's own test

A feature's test travels with it, `features/<Name>/tests/<key>-smoke.php`, and runs
against a real checkout with a throwaway project in it, not against mocks: only Nino
can say whether the feature kept its contract. It finds the checkout three levels
up, or the one `NINO_ROOT` names, which is how the same test runs against another
Nino from outside the checkout. The bootstrap is three lines - `NINO_FEATURES_DIR`
has to exist before the harness loads the kernel, and the guard keeps one defined
before the test was included - and `tests/harness.php` gives nine helpers:

| Helper | What it does |
|---|---|
| `check( $label, $condition )` | one assertion, one line of output |
| `ninoSandbox( $name )`, `ninoSandboxDir( $appData )` | a fresh, isolated project directory - `$appData` with every path in it, the directory it is served from (`/nino/dir`, empty: the domain root), the locales `de_DE` and `en_US`, no modules, the test switches on what it tests - and its path |
| `ninoWarnings()`, `ninoErrors( $run, &$failure )` | the warnings raised since the last call, cleared as they are read - assert on the one you expect; every error one call raises, the level with it - and, where `$failure` is passed, the `\Nino\Failure` that ended the call (not in the list); without it a Failure is thrown on |
| `ninoCall( $appData, $target, $data, $action )` | one workbench request as a panel method sees it: `$_POST['data']` is the json of `$data`; answers `[ status, body, request ]` |
| `ninoUser( $appData, $mail, $perms )` | an account that exists and is signed in, full access unless `$perms` says otherwise |
| `ninoVisit( $appData, $uri, $method, $server )` | one request of the site through `Http::request()`: the request built, the response seeded with the default headers, the callbacks of `/nino/http/request` run - no route resolved, nothing rendered |
| `ninoDone( $appData )` | the summary with the seconds, the sandbox removed, the exit status |

`Hello` requires the feature Basis of the catalogue, whose components its page and its test call as the example.

The test of `Hello` is `features/Hello/tests/hello-smoke.php`:

```php features/Hello/tests/hello-smoke.php
<?php
declare(strict_types=1);

// The test of the feature Hello: the component, the page, the panel's actions, the data. php features/Hello/tests/hello-smoke.php
$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', dirname( __DIR__, 2 ) );
require $root. '/tests/harness.php';

$dir = dirname( __DIR__ );
$appData = ninoSandbox( 'hello' );
$appData['./nino/filesystem/path'] = $root;
$manifest = \Nino\Features::manifest( $dir );
check( 'the manifest validates and is written for this kernel', is_array( $manifest ) === true && \Nino\Features::satisfies( (string) $manifest['nino'] ) === true && isset( $manifest['components'] ) === false );
check( 'Basis and the feature are switched on', \Nino\Features::activate( $appData, 'basis' ) === true && \Nino\Features::activate( $appData, 'hello' ) === true );
check( 'the unit brought the page, its words and the section of the privacy policy', \Nino\Filesystem::fileExists( $appData, '/templates/page-hello.tpl' ) === true );

$appData['/nino/modules'] = (array) ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] ?? [] );
\Nino\Html::init( $appData );
\Nino\Features::registerComponents( $appData );
\Nino\Modules::callModules( $appData, 'init' );
\Nino\Features::registerParts( $appData );
$draw = static fn( string $source ): string => trim( \Nino\Html::renderSource( $appData, $source, [ 'template' => 'page-hello' ] + \Nino\Html::context( $appData ) ) );
$ignored = ninoWarnings();

check( 'the folders are read without a reason', \Nino\Features::componentProblems( $appData, 'hello' ) === [] );
$schema = \Nino\Components::components( $appData )['hello'] ?? [];
check( 'the component is in its group, labelled in both languages, with an icon of the table', ( $schema['group'] ?? '' ) === 'hello' && isset( $schema['label']['de_DE'], $schema['label']['en_US'] ) === true && in_array( $schema['icon'] ?? '', \Nino\Components::ICONS, true ) === true );
check( 'the standard part keeps the rule of mixing', \Nino\Components::partProblems( (string) file_get_contents( $dir. '/groups/hello/part/part.css' ), [ 'hello' ] ) === [] );
check( 'a tool offers it in a column', in_array( 'hello', \Nino\Components::allowed( $appData, 'section-col' ), true ) === true );
check( 'it greets the stored name, a name of the call, and in capitals', $draw( '[hello]' ) === '<p class="hello">Hello, World!</p>' && $draw( '[hello name="Ada" shout]' ) === '<p class="hello">HELLO, ADA!</p>' );
check( 'a name that spells a tag or a call is text, escaped once', $draw( '[hello name="<b> & [csrf]"]' ) === '<p class="hello">Hello, &lt;b&gt; &amp; [csrf]!</p>' );

// The unit brings its section of the privacy policy, the same one in every language and listed in the manifest of the unit
$unit = (array) include $dir. '/install/manifest.php';
$privacy = (array) include $dir. '/install/elements/privacy.php';
check( 'the section of the privacy policy is in the unit, in both languages, under the id of the feature', ( $unit['elements']['privacy'] ?? '' ) === 'elements/privacy.php'
	&& isset( $privacy['*']['hello'], $privacy['de_DE']['hello']['text'], $privacy['en_US']['hello']['text'] ) === true );

// The page the route answers is named in every language the unit speaks: what the Builder lists it by and what the page's title reads
$named = [];
foreach( [ 'de_DE', 'en_US' ] as $locale )
	$named[] = array_keys( array_filter( (array) include $dir. '/install/text/'. $locale. '.php', static fn( string $key ): bool => str_starts_with( $key, '[[/_nino/webpage/hello/' ), ARRAY_FILTER_USE_KEY ) );
check( 'the page is named, titled and described in both languages', $named[0] === $named[1] && count( $named[0] ) === 3 );

// The page of the unit is made of components: a tool reads it as blocks, with nothing it has to keep as free html
$page = (string) file_get_contents( ninoSandboxDir( $appData ). '/private/templates/page-hello.tpl' );
$tree = \Nino\Markup::parse( $page, \Nino\Components::containers( $appData ) );
// What a tool finds against the page: the name comment and the two includes of the frame, and nothing between them
$found = [];
foreach( \Nino\Components::check( $appData, $tree ) as $at => $problems )
	foreach( $problems as $problem )
		if( ( $problem['code'] === 'unknown' && $problem['detail'] === 'template' ) === false && ( $problem['code'] === 'free' && $at === 0 ) === false )
			$found[] = $problem['code'];
check( 'the page of the unit is made of components: no class of Nino 1, no element of its own, nothing a tool keeps as free html', preg_match( '/nino-|<section|<div|<h1/', $page ) === 0 && $found === [] );
check( 'the route of the page is registered and its template draws the greeting', ( $appData['/nino/http/routes']['GET://hello']['uri'] ?? '' ) === '/hello'
	&& str_contains( $draw( '[section-wrap id="intro"][section-row][section-col][hello][/section-col][/section-row][/section-wrap]' ), '<p class="hello">Hello, World!</p>' ) === true );

// The panel: both actions ask for the right, the save keeps the name and refuses one that is too long
ninoUser( $appData, 'editor@example.org', [] );
$denied = ninoCall( $appData, [ \Nino\Modules\Hello\Admin::class, 'apiSave' ], [ 'name' => 'Eve' ] );
check( 'an account without the right saves nothing', $denied[0] === 403 && \Nino\Modules\Hello::name( $appData ) === 'World' );
ninoUser( $appData, 'admin@example.org', [ '/_admin/hello/manage' ] );
$saved = ninoCall( $appData, [ \Nino\Modules\Hello\Admin::class, 'apiSave' ], [ 'name' => 'Ada' ] );
check( 'the save keeps the name in the file a backup carries', $saved[0] === 200 && \Nino\Modules\Hello::name( $appData ) === 'Ada' && in_array( \Nino\Modules\Hello::STORAGE, $manifest['data'], true ) === true );
check( 'a name that is too long is refused at its field', ( ninoCall( $appData, [ \Nino\Modules\Hello\Admin::class, 'apiSave' ], [ 'name' => str_repeat( 'a', 61 ) ] )[0] ) === 400 );

// The script of the panel builds nothing itself (the guide, "What a panel does not build itself")
$script = (string) file_get_contents( $dir. '/assets/admin.js' );
check( 'the rail entry has a sign of its own: an svg that draws, no script in it', preg_match( '/^<svg [^>]*>(<path d="[^"]+"\/>)+<\/svg>$/', \Nino\Modules\Hello\Admin::icon() ) === 1 );
check( 'the panel script takes its bar, button and field from the workbench', preg_match( '/createElement\(\s*.button|window\.confirm|\balert\(|nino-admin-btn-|type\s*[:=]\s*.file/', $script ) === 0 && str_contains( $script, 'Nino.adminUi.actionBar( {' ) === true );
check( 'the form is the shape of the guide: novalidate, a fieldset with its fieldgrid, one main button, the answer as a toast, unsaved input reported', str_contains( $script, 'noValidate = true' ) === true
	&& str_contains( $script, "'fieldset'" ) === true && str_contains( $script, 'nino-admin-fieldgrid' ) === true && substr_count( $script, 'primary :' ) === 1
	&& str_contains( $script, 'Nino.adminUi.say(' ) === true && str_contains( $script, 'dirty' ) === true );
$words = [ 'de_DE' => include $dir. '/text/de_DE.php', 'en_US' => include $dir. '/text/en_US.php' ];
check( 'both languages of the panel have the same keys', array_keys( $words['de_DE'] ) === array_keys( $words['en_US'] ) );
// A word the script reads is a fill of the panel or of the workbench's common list: never one the panel forgot
preg_match_all( "~'(/_admin/(?!common/)[a-z/_-]+)'~", $script, $read );
$own = array_map( static fn( string $key ): string => trim( $key, '[]' ), array_keys( $words['en_US'] ) );
check( 'every word the script reads is one the panel brings in both languages', array_diff( array_unique( $read[1] ), $own ) === [] );

$block = \Nino\Blocks::get( $appData, 'hello-greeting' );
check( 'the block is valid and names the feature', is_array( $block ) === true && in_array( 'hello', $block['features'] ?? [], true ) === true && ( $block['missing'] ?? [] ) === [] );
check( 'the demo of the group draws', substr_count( $draw( (string) ( \Nino\Components::groups( $appData )['hello']['demo'] ?? '' ) ), 'class="hello"' ) === 2 );

check( 'switched off, the call draws nothing and the name stays', \Nino\Features::deactivate( $appData, 'hello' ) === true && \Nino\Filesystem::fileExists( $appData, \Nino\Modules\Hello::STORAGE ) === true );
$ignored = ninoWarnings();

// Nothing of the old components API or the old stylesheet vocabulary is left in the folder
$oldWorld = [];
foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
	if( str_contains( (string) $file, '/tests/' ) === false && preg_match( '/Feature::shortcode|Modules\\\\Components|\bnino-(?!admin-fieldgrid)[a-z]|--(?:space|color|radius|fontsize)-|Nino\.ui\./', (string) file_get_contents( (string) $file ) ) === 1 )
		$oldWorld[] = (string) $file;
check( 'no file of the feature names the vocabulary of Nino 1', $oldWorld === [] );

ninoDone( $appData );
```

Test what a caller can see, as `Hello`'s test does: the manifest, the activation and
its unit, the component drawn through the register, the endpoints, a refusal with its
code and its sentences, the panel there while the feature is active and gone after,
the data that deactivation keeps. `php features/Hello/tests/hello-smoke.php`
runs it (`NINO_ROOT=...` before it for another Nino), `php tests/features-smoke.php`
is the kernel's contract test beside it. `ninoErrors()`, `ninoCall()`, `ninoUser()`
and `ninoVisit()` are 1.8.

CI runs the contract test and then every feature's own test (a loop over
`features/*/tests/*-smoke.php`, guarded because a checkout ships no feature). The
catalogue's `bin/check.sh` does the same against a checkout of Nino: its
`bin/applicable.php` names the features whose `nino` constraint the checkout
satisfies, so the floor decides on which kernels a test runs.

A feature's script is tested over the same stand-ins: `tests/<key>-js-smoke.js`
takes `check()`, `done()`, `load()`, `element()` and `documentOf()` from the
checkout's `tests/harness.js` (1.8; `NINO_ROOT` or three levels up) and `load()`s
`_admin/assets/Nino.admin.js` and the feature's own script over a stand-in for
`Nino` that keeps the requests; the stand-in `element()` has `isConnected` and
`after()` **1.9**. `load()` reads from the root of the checkout, so a
feature outside it names its script as `path.relative( root, ... )`. The catalogue's
JavaScript tests carry a stand-in of their own and run with any Nino.

## Packages

A feature is one of three kinds of package that come from outside as a folder of files; the other two are data and have no code. `\Nino\Packages` is the one
mechanism for them (`_nino/Nino/Packages/Packages.php`, checked by `tests/packages-smoke.php`): it looks at the files of a package in memory - as
`\Nino\Archive::read()` answers them, path => bytes - and puts them in place; where the files come from is not its business. A tool that exports a package writes it with `\Nino\Archive::write()`
([Reading and writing an archive](development.md#reading-and-writing-an-archive)), which makes the `.tar.gz` that `read()` reads back as the same files.

| Kind | Lives in | May hold |
| --- | --- | --- |
| `feature` | `features/<Directory>/` | code: the rules of the archive are all |
| `theme` | `private/packages/themes/<key>/` | `theme.json`, `theme.css`, `parts/<group>/part.json`, `parts/<group>/part.css`, `blocks/<block>/` (below), `preview.webp`, `LICENSE.txt`, `README.md` |
| `blocks` | `private/packages/blocks/<key>/` | `pack.json`, `<block>/` (below), `preview.webp`, `LICENSE.txt` |

**The steps of the knobs.** A `part.json` - of a group and of the part of the page alike - may carry `values`: a map of knob to step, `{ "button.radius": 1, "button.space": -2 }`, the stand the theme is delivered at. A knob is one the part has: of its group, one of its own `<component>.x-<name>`, or, for the page, one of its `knobs`; a step is a whole number from -2 to 2, 0 the middle one (any other value, a name the part has not, a list, a string, nesting or more than 64 entries is refused as `part-values`). The kernel checks and hands the map on; it works nothing out - what the steps make of the ladders is the business of the one who reads the theme. A part without `values` is at the middle.

**The free css.** `theme.css` at the root of a theme (never in a package of blocks, never in a folder, at most 64 KB like a `part.css`) is the stylesheet of the theme as a whole. Whether it is valid does not depend on the site it is checked on - not on the features that are on or installed - so `\Nino\Components::themeProblems()` holds the rule of a part to the FORM of a name and not to the components of the register: a selector is made of class selectors only - a class written as the classes of the components are, lower case letters, digits, hyphens and underscores, starting with a letter; it need not belong to a component of this site, a class that meets nothing here does no harm (`.gallery-grid { gap: 0 }` is as good with the Gallery off as on) - with pseudo classes, pseudo elements and the combinators between them, or it is `:root`; element, `*`, id and attribute selectors are refused. EVERY compound of a selector - the last one, the one behind `~`, `+`, `>` or a blank, one under `&` in a nesting, one in the argument of `:is()`, `:where()`, `:not()` or `:has()` - carries a class outside of any function, so `.section-wrap ~ *`, `.header-wrap + *`, `.section-wrap *`, `.a:has(body)` and `:where(*) .a` are refused. A `--token` is set only where it is written as the token of a component (`--button-radius`; not `--page-...`, not `--brand-...`) and read where it is one or a `--brand-...` or `--page-...`; no `@import`, `@layer`, `@namespace`, `@charset`, `@property`, no `!important`, no `url()`, `image()`, `image-set()`, `src()`, `element()` or `cross-fade()`, no backslash outside a string, and an `@media` or `@container` keeps the rule inside. Beyond a part: no `@font-face` (fonts are the Typefaces'), no `</` or `<!--` anywhere - comments and strings included, since the file is put into a style element - and no `expression()`, `-moz-binding` or `behavior` (in any case of letters; the words in a comment are no use of them). A `part.css` keeps the rule of its group (`\Nino\Components::partProblems()`: the classes of the group's own components, elements allowed below them) and, like the free css, has no `@property` and none of `image()`, `element()` and `cross-fade()`. A refusal is `theme-css` with the file and the sentences, with the line where it is known. The kernel keeps the file with the others; how a stylesheet is hung on the site is the business of the one who reads the theme. A package without `values` and without `theme.css` is as good as it was.

A block is `block.json`, `block.tpl`, `text/<xx_XX>.json` and, for a slot of `block.json` that has a picture of its own, `images/<slot>.svg`, `.webp`, `.png` or
`.jpg`. The list is a list of paths, not of endings: a file that no path of the list names - a php file, an `.htaccess`, a script, a hidden file, a part
file that nobody reads - is refused with its path, and a theme or a package of blocks with one such file is refused as a whole; nothing of it is written.
Names are lower case words joined by hyphens (a locale is `xx_XX`), no name stands twice in another spelling (a Mac folds the case), every text is UTF-8
without a null byte, and every JSON is a map of at most eight levels and at most 32 KB (a block's own files by the limits of `\Nino\Blocks`).

The `name` and the `description` of `theme.json` and `pack.json` are text: one that holds `<`, `>` or `[[` - a tag or a call a list of the workbench or the catalogue would draw as one - is refused (`file-markup`).

A block that is a frame - its root says the side `header` or `footer` in its schema (`header-wrap`, `footer-wrap`), its category is `header` or `footer` - may name a text key of the project as the first word of a call (`[text /project/company/general/description]`) and the logo of the site (`[footer-logo /logo]`, for a component whose schema says `logo`), which a block of the pages may not: a theme can bring a footer that shows the company data of whichever site it is on. A frame can read every text of the site - the pages' own texts, one behind a password or not yet published among them, the mailbox the forms go to, the words of the features: `/template/`, `/project/`, `/feature/` and `/module/` - and so can a frame from a package of another site, once it is put in, and already in its tile and its preview in the workbench. The responsibility for what a frame shows is the developer's, who puts a block on a site; `\Nino\Blocks::reads()` lists what a block reads, for a tool to show first. The block carries the key, never the text, and a key that is not there is drawn as a page draws a missing text ([Blocks](development.md#blocks)).

**The frame of a page.** A page chooses the header and the footer of the site where it calls the scaffold: `[frame /templates/frame-header-landing]` and `[frame /templates/frame-footer-none]` in place of `[template /templates/html-header]` and `[template /templates/html-footer]`. The name is `/templates/frame-header` or `/templates/frame-footer`, the standard frames, with `-<word>` of lower-case letters, digits and hyphens (at most 40); `none` is no frame. `html-header.tpl` and `html-footer.tpl` of a new site carry `[[header]]` and `[[footer]]` where the frame goes - in their own text, not in a template they include - and the call fills them; a page that calls the scaffold as before has the standard frame. A name that is not of that form draws nothing and is said once an hour; a word whose file is not there draws the standard. The texts of a named frame are the keys `/template/frame-header-<word>/...`, as for any template. `\Nino\Modules\Template::frames()` lists the frames a site has and `framed()` says whether its scaffold carries `[[header]]` and `[[footer]]` yet; the details are in [development.md](development.md), The frame of a page.

What the files hold is read by the rules of what reads them later, and none of them is new: a `part.css` by `\Nino\Components::partProblems()` (no `@import`, no
`url()`, no `!important`, no class or token of another component, no token of another group), a `part.json` by `\Nino\Components::validateGroup()` where the
project has the group (where it has not, the components the part names stand for the group, and the one who reads the theme when the feature comes
says the rest), a block by `\Nino\Blocks::validate()`, a raster picture by `\Nino\Images::size()` - of the type its ending says, at most 512 KB and 4 096 pixels
on a side - and a svg only where `\Nino\Images::cleanSvg()` takes it: a svg is written as the picture `cleanSvg()` rebuilds, never as it came. What `cleanSvg()` takes is rebuilt, not refused - a svg that has a shape and a `<script>` or an `onload` is installed as the drawing without them - and what it does not take (a document type, a link by `xlink:href`, no shape at all) refuses the package. That a rebuild lost something is for the build of the catalogue to say. A theme has at most 40 parts, as the reader of themes takes; the `brand` of `theme.json` is a map here and the feature that reads themes says what it may suggest. A block whose
components belong to a feature the project does not have is refused by `validate()` like any block that calls what nobody registered.

```php
$problem = \Nino\Packages::check( $appData, 'theme', $files );    // null, or [ 'code' => a key of Packages::WHY, 'path' => the file, 'params' => [ ... ] ]
$done    = \Nino\Packages::place( $appData, 'theme', 'craft', $files ); // true, or a problem as above - nothing is written but a package that is whole
$done    = \Nino\Packages::remove( $appData, 'theme', 'craft' );
```

`check()` is what the catalogue's build and the installation both ask; `Packages::why()` words a problem as the English sentence of the log, and a tool says it in its own
words by the code. `place()` checks again, writes the files to a staging folder below `data/.packages/`, exchanges it for the folder of the package under the lock
of the features - the old folder beside it, and put back where the exchange fails - and removes the staging folder however it ends. A change that finds the
lock taken is not made (`busy`). What a run that was killed left - a staging folder, a folder that was put aside - the next change clears under the lock
(`Packages::sweep()`). `rename()` is tried first; where the two folders lie on two file systems the files are copied beside the place (`.incoming-<key>-<hex>`) and moved in by one `rename()`. What an exchange puts aside is renamed to `.removing-<key>-<hex>` before it is deleted, so that `.previous-<key>-<hex>` stands between the two moves only, which is what `sweep()` relies on to put it back. `remove()` takes a theme or a package
of blocks away whatever uses it: a site never hangs on a package, it draws from the copies it keeps and the blocks that are put in a page are the page's;
a feature is taken away by `Features::remove()`, which asks that it is switched off first.

A package of blocks is read by `\Nino\Blocks` as a root of its own: the origin of its blocks is `pack:<key>` - a name no feature, no project and no root of the hook has, so that no origin is another's -, the label the name in its `pack.json`. The roots stand
in this order, and the first block of a key wins: the blocks of the project (`private/blocks/`), of the installed packages by their keys, of the
features by their keys, and what the hook `/nino/blocks` adds. A package is a choice of the project, so no feature that is switched on later puts out one of its
blocks, and what the project wrote itself stands before it; `Blocks::forget()` makes the request read the folders again, which `place()` and `remove()` do. `private/` is never
served and no reader includes a file of a package.

### Where a package comes from

`\Nino\Catalogue::install( $appData, $kind, $key, $version )` is the one way a package of any kind comes in from the signed catalogue (format 2, the fields of an entry are in
[A catalogue of your own](_admin.md#a-catalogue-of-your-own)). It takes the lock of the features, fetches the catalogue again and works out the plan before it asks for a byte: the features the
package asks for that the project does not have come first, deepest first, then the package - a theme and a package of blocks ask for the features of their parts and components, a
feature for the ones it `requires`. For each entry it downloads the archive (the size and sha256 of the signed entry, over https, from a host of the public internet), reads it in
memory with `\Nino\Archive` by the limits of the kind (`\Nino\Packages::LIMITS`), has `\Nino\Packages::check()` look at a theme and a package of blocks and compares the key and the
version they say of themselves with the entry, and then `\Nino\Packages::place()` puts it in place - a feature too: `place( $appData, 'feature', $directory, $files, $verify )` takes a
closure that is asked of the staging folder before the exchange, which is where the manifest of a feature is read (it is php that has to be included), compared with the entry
and with this kernel. Nothing is switched on by the kernel; the panel that asked switches on the features that were placed. What was placed is under `./nino/catalogue/placed`
for the caller, and what a package was refused for - as `check()` answers it, with the kind, key and version - under `./nino/catalogue/problem`. A feature placed in the same
call as the theme that asks for it is not in the register of the components yet when the theme is checked: the parts of its groups are read against what the parts name, as of a group the project has not, and the one who reads the theme says the rest when the feature is on.

`\Nino\Catalogue::offers( $appData, $catalogue, $kind )` answers per key of a kind the best version this kernel can run beside what is on disk (`\Nino\Packages::installed()` for a theme and a
package of blocks), with a state, what it needs that is not there (`needs`: features, which come with it, and fonts, which do not) and what cannot be had (`blocked`: a feature that is neither
on disk nor in the catalogue in a version that fits, which makes a theme or a package of blocks `incompatible`). `\Nino\Catalogue::remove()` takes a theme or a package of blocks
away. `\Nino\Catalogue::preview()` fetches the picture of an entry when it is asked for (https, a host of the public internet, the size and sha256 of the entry, a webp by its head)
and keeps it below `data/.catalogue/previews/` by its digest; it answers the bytes and the path, and the panel that asked answers the browser with the file.

The packages are **not** in a backup. They come from the catalogue again and nothing of a site hangs on them: a theme that is applied is kept as a copy of the
parts it uses, and a block that is put in a page is the page's. The blocks the project wrote itself (`private/blocks/`, with the pictures of their slots) and the
fonts are.

## What a feature does not do

- It does not install or uninstall itself: deactivation only removes the class from
  `/nino/modules`; copied files, texts, routes, settings and data stay.
- It does not overwrite: neither activation nor an update replaces a file, a route
  key or a text key the project has.
- It does not reach the network on a page request, and never but through
  `\Nino\Fetch`; Nino itself makes no outbound request unless someone presses
  **Refresh catalogue** or **Install** ([the workbench manual](_admin.md#features)),
  or reaches the Application step of the setup wizard. A feature that asks a host
  a person did not type - the address of a list a catalogue names - asks
  `\Nino\Fetch::isPublic()` first: https, a name and no address, no ending of a local
  network, and every address the name has one of the public internet. The
  catalogue asks it of its index, its archives and its pictures.
- It does not write output (no `echo`, `header()`, `http_response_code()` or `exit`;
  the response is `Http::ok()`, `fail()` or `redirect()`) and does not build markup
  in PHP: a template, read by `\Nino\Feature::template()`, holds it (an icon and a
  one-line fragment declared once as a property are the exceptions).
- It does not depend on anything outside the public API of `\Nino\*` and its own
  directory: no `_`-method, no class of `app/`, no copy of kernel code; a feature
  that needs what the kernel lacks is blocked, not patched.
- It does not read-modify-write a file with two calls (`Filesystem::mutate()`),
  widen the Content-Security-Policy to `*` or an unsafe keyword
  (`Feature::allowOrigin()` adds one origin) or register what its manifest declares twice.
