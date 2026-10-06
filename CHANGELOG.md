# Changelog

All notable changes to Nino are documented in this file.

## Unreleased

### Added

- **Features:** `\Nino\Features::DIRECTORY_PATTERN` and
  `\Nino\Features::EXTENSION_PATTERN`, beside `KEY_PATTERN`,
  `CATEGORY_PATTERN` and `VERSION_PATTERN`: what a feature's directory and a
  PHP extension name in `"php" => "ext"` look like.

### Changed

- **Docs:** comments in the core named callers and a wizard that are gone.
  `AppData::DEFAULTS` no longer calls Form, Navigation and Localepicker wizard
  checkboxes, Filesystem no longer says callers drop a cache slot to re-read,
  `Catalogue::install()` says it places the missing requirements too,
  `Images::delete()` gives the names `process()` and `fit()` really hand out,
  the Backup's two Newsletter literals give the reason they still exist, and
  accounts' extra permissions are a hand edit of config.php, not a
  "direct-json task". The Form engine's docblocks and the manual's "Refusing a
  submission" (both languages) no longer credit `\Nino\Csrf::init()` with the
  route callback: Csrf refuses on the global `/nino/http/response`. No code
  changed.

- **Workbench:** the Dashboard's element-type tile and the check that a
  reference field points at an existing type read the type names
  (`\Nino\Modules\Elements\Admin::types()`) instead of every type file;
  `Types::summaries()` is built on `Admin::types()` and `typeData()` and keeps
  its strcmp order. Nothing the panel shows changes.

- **Text:** the limit a text key derives from its longest value is worked out
  in one place, `\Nino\Text::maxlength()` (internal). The Text Keys tab gave a
  hidden key with no value a hand-written `150`; it asks the same function
  `\Nino\Text::entries()` uses now. Every limit stays what it was.

- **Maintenance:** the `maintenance/status` and `maintenance/set` answers carry
  `min` and `max`, the bounds `apiSet()` holds the Retry-After seconds to, and
  the panel's seconds field takes them from there instead of repeating 60 and
  604800 in its script.

- **Accounts:** the setup wizard's check for a usable admin account compares
  with `\Nino\Auth::STATUS_ACTIVE` instead of a literal `2`, and `\Nino\Auth`
  reads the `status` that `getUser()` always fills without a fallback of its
  own. Nothing changes for any account.

- **Workbench:** the backups and the activity log each answer their one
  directory - `\Nino\Modules\Backups::dirs()` (a list of one) is
  `Backups::dir()` - and read the 403 stub their files are wrapped in from
  `\Nino\Admin\Recovery::STUB_PREFIX`/`STUB_SUFFIX` instead of three private
  copies. Files on disk are unchanged.

- **Workbench:** the roles tab lists the permission groups in the order
  roles/list now names them (`groups`: `\Nino\Admin\Panels::GROUPS`, then
  `other`) instead of a copy of that list in `roles.js`, so a group the
  registry adds can no longer drop its permissions from the picker.

- **Setup wizard:** the Setup step keeps no lists of its own. The always-on
  modules are `\Nino\AppData::DEFAULTS['/nino/modules']`, the languages it
  offers are the base unit's `text/<locale>.php` files, and the Personal
  Infos step reads every text file of the base unit; `Maintenance` is listed
  on every run instead of whenever its class exists. The written
  `/nino/modules` is the same as before.

- **Workbench:** the Config panel and the Users panel's login protection tab
  show `\Nino\AppData::DEFAULTS` for a key config.php does not hold, instead of
  their own copies of the same eleven values; `Lockout`'s private `DEFAULTS`
  and `Config\Admin::_currentValue()` are gone. Every value shown is the same
  as before.

- **Features, catalogue:** a feature's directory, key, version and extension
  names are spelled once. `\Nino\Catalogue` drops its private
  `DIRECTORY_PATTERN` and its own extension pattern, the Features panel its
  private `KEY_PATTERN` and `VERSION_PATTERN`, and the workbench's module scan
  reads `Features::DIRECTORY_PATTERN`. Every value accepted or refused before
  is still accepted or refused. `tests/catalogue-smoke.php` checks that no
  reader writes the patterns out again.

- **Text:** a site's words live in `/text`, and nothing follows
  `/nino/locales/textfiles` any more. The renderer (`\Nino\Html::getFills()`),
  the Language and Navigations panels, the Legal module and the Image Slots
  tab read `/text`, as the Text panel, the Text Keys tab, the setup wizard,
  backups and `Filesystem::PRIVATE_DIRS` always did. A project that set the
  key by hand in config.php rendered from one directory and edited another;
  it renders from `/text` now. A config.php that holds the key is not refused,
  and `\Nino\AppData::DEFAULTS` keeps it (as `/text`) for catalogue features
  that still read it, until 2.0.

- **Docs:** section 2 of the base unit's `theme.css` no longer explains its
  colours and sizes through `/_design`, `assets/style.design.css` and a
  manifest's default knobs, all gone since the look left the core: the tokens
  it reads are section 1's, compiled once and frozen, and a stylesheet that
  declares them again - the Design feature's palette - recolours the site.
  The line offering Basis as "the look to pick" goes with the picker. Only new
  projects get the new comments; the file is copied at install. The Design
  feature's `library/base.css` carries these sections byte for byte and
  follows in its own patch.

### Removed

- **`\Nino\Install\Setup::TOOL_MODULES`**, a public constant whose one entry was
  `Maintenance`, and the private `CORE_MODULES` and `AVAILABLE_LOCALES`.

## v1.4.0 - 2026-10-06

### Added

- **Workbench:** one request helper for every panel, `Nino.adminUi.api`
  (`_admin/assets/Nino.admin.js`). `call( action, payload, callback, extra )`
  posts one action to `<project directory>/_admin/` - the trailing slash and
  the directory are the helper's, not each panel's - and calls back
  synchronously with `( status, body )`, the shape every panel already read.
  It names no `Nino.admin` member and asks `Nino.http`, `Nino.dir` and
  `Nino.content` only when a request is made, so the setup wizard can load
  the file without the shell. The twenty copies of `_apiCall` in the kernel's
  panels are one-line names for it; the wizard's and the recovery page's own
  transports stay, both are English by design.

- **Workbench:** a session that ends under an open form no longer loses it.
  Only a `401` with the code `session` and a `403` with the code `csrf` are
  the end of a session - the Users panel's `401` for a wrong password and a
  missing permission's `403` reach their panel as they did. The helper then
  asks `GET /_admin/?session=1` (new: `{ user, csrf }`, answered before the
  login page, for a logged-out session too) and decides: the same account and
  a token that was replaced by a login elsewhere - the second-tab case -
  sends the waiting requests again, once, with the fresh token written into
  the page's csrf fields; a session that is gone opens a login dialog over the
  page (`<dialog id="admin-session-dialog">` in `page-index.tpl`, texts
  `/_admin/common/session/*`) that posts to the login endpoint without leaving,
  with the anonymous session's token, and sends the requests on after a login
  of the same account (a `403` from that login asks again whose session this
  is, since the token changed under it); another account's session offers
  only the reload and sends nothing, and leaves the page's own, dead token in
  its csrf fields, so that a form filled in for one account is never saved by
  another, not even by a panel that does not use the helper yet. The dialog
  has a *Close* button, and closing it (Escape included, except while a login
  of the same account can still bring the requests back) releases what waited
  - `api.dismiss()`: each request gets the answer it already had, so the
  panels show their error, give their forms back and let the input be copied
  out - for the person who cannot log in: an account that was deleted or
  disabled, a password changed under the page, a login that answers 503 in
  maintenance. `api.waiting()` tells whether requests wait. The notice for
  another account has a title of its own, a login whose check finds the session
  still gone says so instead of doing nothing, and `?session=1` sets
  `Cache-Control: no-store` itself, whatever a project relaxed globally. A
  login that is answered after the dialog was dismissed still asks whose
  session this is, and so teaches the page the new token. Three requests
  failing together share one check; a second failure
  of a request already sent again goes to its callback. No keep-alive: a page
  left open long enough still ends its session, it just no longer costs the
  input. `tests/admin-api-js-smoke.js` (new, 100 checks) holds the endpoint,
  the payload and the extras, the synchronous callback, every path above
  including the 401/403 that are not a session and a check that fails, and
  offline against a server's own 500 (told apart by the missing response
  headers, a failed connection arrives as a 500 as well);
  `tests/admin-script-js-smoke.js` the dialog's wiring, its login, its answers
  and every way out of it (22 → 52 checks); `tests/admin-smoke.php` the codes,
  `?session=1` logged in and out and the guard's two bodies.

- **Workbench:** failures say what failed. `\Nino\Http::fail( $request,
  $status, $error, $code = '', $params = [], $field = '' )` adds the stable
  `code`, the `params` that fill its sentence and the `field` the value was
  refused for to the body, and only when given: a call with three arguments
  answers what it always did. About fifty failures a person can cause carry
  a code - the type of a value (`int_range`, `bool`, `lines`, `lines_ip`),
  a wrong password, a role or navigation or route or text key or type that
  does not fit, a last account with full access, a field the account may not
  change - and the shell's two languages word them as `/_admin/error/<code>`;
  a panel's own codes carry its slug and live in its own `text()`.
  `Admin::failType()` words a value of the wrong type for Config, Login
  protection and Maintenance (`typeError()` stays). `Nino.adminUi.api.errorText(
  status, response, fallbackKey )` says a failure in the interface language -
  the code's text with its params, else the server's own message, else the
  panel's sentence, those two with the status number in front, and a request
  that never arrived as that - `showError()` writes it in place of a list,
  and `format( text, ...params )` fills `%s`, `%d` and `%n` with a function,
  because a param is what somebody typed and `String.replace()` reads `'$&'`
  in it, and writes a number with a fraction the way the interface language
  does (`0,5 MB` in German, from the new fill `/_admin/common/unit/decimal`).
  `tests/admin-system-smoke.php` finds every code in the source and fails on
  one without text in both languages (692 → 708 checks, 14 red before), and
  holds the codes of Config, Login protection, Maintenance, Roles, Text Keys,
  Element Types, Routes and Navigation.

- **Workbench:** the status line, `Nino.adminUi.status( el, labels )`:
  *Saving …*, *Saved at 09:41.*, *Unsaved changes* and the error, as one
  element with its state in `data-state`, a glyph in front of each state so
  the meaning does not rest on a colour, a status role and, for an error, an
  alert. `error()` marks the field the server named (`[data-field]` or
  `[name]` in the bound form) `aria-invalid`, focuses it and lets it go with
  the next thing typed; `bind( form, isDirty )` turns *saved* into *unsaved*
  on input without keeping a second copy of the panel's own dirty state (a
  picked file or a search box is not a change: an upload saves itself, while
  adding, removing and moving a reference in `Nino.adminUi.elementList()` is,
  and the control now fires a bubbling `change` for it) and leaves a refusal
  on screen until the next save; what is typed while a save is on its way
  ends in *Unsaved changes*, not in *Saved*. The Elements form and the three
  Users forms use it (the other forms still print a plain *Saved.*), the
  Users form marks the wrong current password, and a Users account that
  renames itself updates the rail, which the session check compares against.
  Known limitation: below 38 rem a bottom action bar still hides its status
  line, as it hid the old messages - which now includes an error, so on a
  phone a failed save of the Elements form shows nothing in the bar until the
  layout of the narrow bar is reworked (not part of this change).

- **Images:** `\Nino\Images::limits()` answers what an upload runs into in one
  place - the kernel's 8 MiB (`MAX_UPLOAD_BYTES`, now public), what php lets a
  request carry (the smaller of `upload_max_filesize` and `post_max_size`,
  where `0` is none) and the 20 megapixels - and a value php cannot read is no
  limit rather than a warning. `\Nino\Images::reject( $bytes )` says why the
  kernel would refuse bytes (`image_too_large`, `image_type`,
  `image_too_many_pixels`, with the limit as param); `process()` and `fit()`
  use it, so the limits are enforced in one place. `tests/kernel-smoke.php`
  holds both, `Http::fail()` and that none of it warns (793 → 809 checks).

- **Workbench:** upload limits are named at the control and checked before
  the file is sent. The shell carries `data-upload-bytes` and
  `data-upload-pixels` (new fills `/_admin/upload/*`), the Elements image field
  and the Images slots print *Up to 2 MB and 20 megapixels.* under the file
  input (`Nino.adminUi.uploadHint()`), and `Nino.adminUi.checkImage( file,
  done )` refuses a file above the byte limit - and, where the browser can
  decode it (`createImageBitmap`), above the pixel limit - in the server's own
  words without sending it. `\Nino\Admin\Admin::uploadError()` maps what php
  says about an upload that did not arrive: `UPLOAD_ERR_INI_SIZE` and
  `FORM_SIZE` are a `413` `upload_too_large` naming php's limit in MB,
  `PARTIAL` a `400`, a missing file a `400`, and a missing temporary
  directory, a failed write and a blocking extension a `500` - server
  faults - and a request above `post_max_size`, which php answers by dropping
  its token with the rest of its body, is a `413` `post_too_large` instead of
  the `403` for a missing token. `tests/admin-smoke.php` holds every one of
  them for both upload endpoints (247 → 286 checks),
  `tests/admin-elements-js-smoke.js` the pre-check and the status line
  (87 → 101).

- **Workbench:** unsaved input survives. `Nino.admin.dirty`
  (`_admin/assets/script.js`) is the registry the form panels report to:
  `register( name, { isDirty, save( done ), discard, bar } )`, or
  `watchForm( name, formGetter, save )` with `snapshot( name )` for a form of
  plain fields (compared on demand, not on every key; a file input, a search
  box, a password a browser may fill in and a field marked `data-dirty="ignore"`
  are not input, and a form that holds no fields - a load error took its
  place - is clean), `isDirty( names )`, `dirtyNames()`, `refresh()` and
  `guard( names | null, proceed, onCancel )`. A guarded exit that finds
  something unsaved asks **Save**, **Discard** or **Cancel**: Save runs the
  `save( done )` of every dirty entry in order and goes on only when each
  reports `done( true )`, the first that reports `done( false )` is brought on
  screen with its errors; Discard lets every entry forget; Cancel runs
  `onCancel()`, so a select or a checkbox that started the exit can put itself
  back. While a question stands a second call is ignored - it calls
  `onCancel()` as well - except from inside a Save the question itself started,
  which may ask one of its own (an Element Types save drops the Elements form
  next door); a `save()` that throws ends the question and the Save and the
  error goes on up, so no exit stays shut.
  A refused Save puts the focus on the first invalid field once its pane is on
  screen. The browser's own question (`beforeunload`) is installed only while
  something is unsaved, so a clean page keeps its fast back and forward, and a
  form with unsaved input shows
  *Unsaved changes* in its action bar (`.nino-admin-actionbar-dirty`, a `span`,
  not a status role, so the phone rule that hides the status line leaves it;
  a bar that has a status line of its own says it there and hides the marker,
  except below 38 rem, where that line is hidden). The page's own protection
  against leaving is taken back after ten seconds, and when the page is shown
  again, if an exit that was decided on does not happen. Every listener is
  installed from `onReady()`, never at load. The registration is opt-in and
  feature-detected: a panel script that finds no registry registers nothing,
  and a feature panel on an older kernel keeps working. Registered: Elements,
  Element Types, Text, Text Keys (the group form, the new-key form and the scan
  form), Routes, Config, Language, Users (a new account, an account with its
  role), Roles, Login protection, Image Slots (form and scan), Features
  settings, Navigations and Maintenance. Translations is not: its import
  textarea is a paste area, not stored input.
  `Nino.adminUi.choiceDialog( { title, message, choices, onChoose } )`
  (`_admin/assets/Nino.admin.js`) is the question: a native `<dialog>` in the
  workbench root, opened modally, built with `createElement` and `textContent`
  only, one button per choice (`primary`, `danger`, `secondary`), Escape the
  secondary one, focus back to the opener, one at a time; it owns no words and
  names no `Nino.admin` member, so the wizard keeps loading the file, and a
  browser without `showModal()` asks with `confirm()`. The rich-text editor's
  handle gains `focus()` and `mark( { required, invalid, describedBy } )`, which
  act on the `role="textbox"` element. New fills
  `/_admin/common/confirm/unsaved`, `/_admin/common/label/discard` and
  `/_admin/common/label/cancel`; the admin panel recipe documents the contract.
  `tests/admin-script-js-smoke.js` holds the registry, the guard in all its
  answers, `beforeunload`, the back-link capture, the marker, the shell's exits
  and the dialog (52 → 125 checks, the file stops at the first new check
  without the change), `tests/admin-html-editor-js-smoke.js` the handle
  (10 → 17), `tests/admin-lists-js-smoke.js` the registrations, their names,
  the new classes and fills (141 → 165, 16 red before), `tests/admin-smoke.php`
  the shell's words (286 → 296, 8 red before) and `tests/admin-system-smoke.php`
  the bundle order (708 → 711, 3 red before).

- **Images:** an image slot can have its image taken away, tells when a picture
  was scaled up, says where it is used and keeps an alt text per language. All
  of it is in the Images panel (`_admin/Nino/Modules/Images/`).
  **Remove image** (`images/remove`, after a question that names the slot and
  what follows) writes the slot's record first and deletes the file second, and
  only a file the slot owns - its name starts with the slot's own
  `<uri>.`, the deterministic path an upload writes, and no other slot names it:
  a name written into `config.php` by hand, such as a picture a template includes
  literally, is cleared from the slot and stays on disk. A slot without an image
  answers `200` with no filename, so the call can be repeated. An upload answers
  `source` (the size the picture is shown at) and `belowTarget`; a picture
  smaller than the slot's target is saved all the same and the line under the
  control says so in words, in the new `is-warning` style
  (`.nino-admin-field-image-msg.is-warning`, `.nino-admin-field-hint.is-warning`).
  The size is the one the picture is shown at, so a photograph with an EXIF
  orientation of 6 is measured upright. Each slot lists the pages that show it
  (*Used on: Home (/)*) or warns *Not included anywhere*: `Slots::usage()` reads
  `templates/*.tpl` and every served `GET` route, follows `[template
  /templates/<name>]` includes (visited set, depth 20) and reads a body named
  by `[[/nino/http/response/locale]]` once per language; the Image Slots tab
  appends *not included anywhere* to such a row, with the templates that
  mention it. The **alt text** is stored per slot and language
  (`'/nino/html/images'[<uri>]['alt'] = [ 'de_DE' => '...' ]`), written with
  `Images::setSlotAlt()` - cleaned (`Images::cleanAlt()`: control characters
  become spaces, the ends are trimmed), merged per posted language, an empty
  text removes its entry - by a targeted mutation of `config.php`, as is the
  filename an upload or a removal writes (`Images::setSlotFilename()`), so an alt
  text saved while an upload finishes costs neither, in whichever order the two
  end. An alt text typed and not saved is reported to `Nino.admin.dirty`: the
  panel asks before a category is opened over it, on a reload and on a log out. `images/alt` answers `400` for a
  language the site does not have, a value that is no string and more than 250
  characters. New fills `/_admin/images/*` and `/_admin/slots/label/unused`,
  `/_admin/slots/label/templates` in both interface languages.
  `tests/admin-images-js-smoke.js` (new, 40 checks) draws one slot;
  `tests/kernel-smoke.php` (809 → 850, with the EXIF checks below),
  `tests/admin-smoke.php` (296 → 336) and `tests/admin-system-smoke.php`
  (711 → 725) hold the rest.

- **Elements:** an image field has the same two things. **Remove image**
  (`elements/removeimage`) writes the field empty and deletes the file this
  element's own upload wrote - `elements/<type>/<uri>[-<key>][-<locale>].<w>x<h>.<ext>`
  with `-<key>` only where the type has more than one image field and `-<locale>`
  only for a per-language one - not named by another field or language of the element - and an upload says when
  the picture was scaled up. A field may name the string field that holds its
  alt text per language, model property `alt` (`'alt' => 'imageAlt'`): the
  Element Types tab offers the plain, per-language string fields and
  `Types::cleanModel()` drops a link to a global field, a rich-text field, the
  image itself or a field that is not there; both element forms say which field
  is whose. `[element]` and `[elements]` render `[[imageAlt]]` as an empty string
  for an element or language that has no value there - not as the literal fill -
  so `alt="[[imageAlt]]"` is `alt=""`, decorative. The element-types recipe
  documents the property. `tests/admin-smoke.php`, `tests/admin-system-smoke.php`,
  `tests/admin-elements-js-smoke.js` (180 → 191) and
  `tests/admin-elementtypes-js-smoke.js` (48 → 50) hold it.

- **Installer:** a page unit and the base unit may declare image slots,
  `imageSlots` in their `manifest.php`: `uri => [ 'label' => string or locale
  map, 'width', 'height', 'filename' ]`, the `filename` a file the unit ships
  under `files` - or none, for a slot that starts empty. The Routes step (and
  the Setup step, for the base unit) checks each one (a slot uri, a label for the native locale, a size within
  `Images::MAX_SOURCE_PIXELS`, a seed file that is there once the files are
  copied) and fails by name - *could not seed image slot <uri>* - before any
  route is written; it only adds slots, so a slot the project already has keeps
  its label, size and image when the step is applied again. Documented in
  `docs/setup.md`, `docs/setup.de.md` and the installer-package recipe.
  `tests/install-smoke.php` (280 → 299) seeds the home unit's slot, applies
  again, fails on a seed that cannot be copied, and checks every page unit: each
  `[image <uri>]` in its templates is declared, each declared slot has a valid
  uri, a label, a size and a seed the unit ships, and no template carries a
  literal `<img>` of the images directory.

- **Images:** `[image <uri>]...[/image]` renders its content instead of the
  `<img>`, and only for a slot that has an image: `[[src]]` (the path of the
  file from the site's root), `[[width]]`, `[[height]]` and `[[alt]]` are filled
  in, escaped and with `[` as `&#91;` like the `<img>`'s own. A place that needs
  the address and not a picture - a meta tag, a mail - is written
  `[image /logo]<meta property="og:image" content="https://[[/website/url]][[src]]">[/image]`
  and is not left as an empty or a broken tag where nothing is uploaded yet. A
  bare `[image <uri>]` is unchanged - but a shortcode's content runs up to the
  first closing tag, across a second opening of the same one, so a bare
  `[image]` ahead of the content form in one template has to be written
  `[image <uri>][/image]`, or it takes the text up to the closing tag as its own
  content. The templates the base unit ships do not mix the two. Documented in
  `docs/development.md` and `docs/development.de.md`; `tests/kernel-smoke.php` (850 → 855) holds it.

- **Text editor, Elements, Text Keys:** paragraphs, lists and line breaks. A
  value has one of four **formats**, `\Nino\Html::FORMATS`: `plain`, `inline`
  (`strong`, `em`, `span`, `code`, `a` - what a field with `html` always had),
  `lines` (those plus `<br>`) and `blocks` (those plus paragraphs and lists:
  `p`, `ul`, `ol`, `li`). `\Nino\Html::sanitizeHtml( $html, $format = 'inline' )`
  has a profile for each: `lines` turns a newline of the text into `<br>`, drops
  one straight after a `<br>` and trims the ends; `blocks` keeps `p`, `ul` and
  `ol` at the top, `li` only in a list (whatever else is in a list becomes an
  item of its own, so no word is lost), turns `div`, headings and quotes into
  paragraphs, gathers loose text into paragraphs (a blank line starts one, a
  newline is a `<br>`, as `Posts` has it) and drops what is empty; every profile
  is idempotent, and a name that is none of them is read as `inline`. New:
  `Html::detectFormat()` (the widest a value holds), `Html::fieldFormat()`
  (an element field's model: `html` + `blocks` is `blocks`, `html` is `inline`,
  `breaks` without `html` is `breaks`, anything else `plain` - a flag on a field
  it does not fit is ignored), `Html::fieldValue( $value, $field )` - the one
  rule that makes a field's value safe to put into a template: sanitized to the
  format, `<br>` for the newlines of a `breaks` field, escaped for any other,
  then `[` as `&#91;` - which `[element]` and `[elements]` call now (it is the
  public API a feature that draws a field itself takes in place of a copy;
  `[elementvalues]` keeps escaping plain) and `Html::breaksToNewlines()`.
  `containsHtml()` is unchanged.
  An **element type** field takes the model flags `blocks` (with `html`) and
  `breaks` (without); the Element Types tab offers each only where it applies
  and `Types::cleanModel()` drops one from a field it does not fit; the Elements
  form hands `blocks` to the editor, and the save - and the Translations import -
  hold the value to `Html::fieldFormat()`.
  A **text key** has a format and a limit of its own: `text/meta.php`,
  `'/the/key' => [ 'format' => ..., 'maxlength' => ... ]`, read by `Text::meta()`
  (an entry that is no format or limit is left out) and written by
  `Text::setMeta( $appData, $key, ?$format, ?$maxlength )`, a mutation of the
  file that drops an entry left with neither - `Text::updateMeta()` changes only
  the settings it is given, decided under the same lock, so two saves of one key
  that change different settings both land, and `Text::moveMeta()` is a rename's
  one mutation. `Text::entries()` answers `format`
  (the one set, else the widest the values hold), `html` (not `plain`),
  `formatSet`, `maxlength` (the one set - never below the longest text the key holds, so a longer one written later by an import is not cut at the first keystroke - else derived as before) and
  `maxlengthSet`; `Text::sanitizeValue( $value, bool|string $format )` takes a
  format (`true` is `inline`, `false` `plain`, as before; an unknown name is
  `plain`) and so does `saveBatch()` for every key - the wizard, the Translations
  import and the Templates feature included. The Text Keys tab has a **Format**
  select (*Automatic*, *Plain text*, *Formatted*, *Line breaks*, *Paragraphs and
  lists*) and a **Limit** per key; **Apply** posts them with the two checkboxes
  as one `keys/save`, which leaves a setting it is not sent as it was, answers
  `400` for a format that does not exist (`keys_format`), a limit outside
  `1..Text::MAX_LIMIT` (5000, `keys_limit`) and a limit below the longest text
  the key holds (`keys_limit_short` - the editor would cut it at the first
  keystroke), converts every stored value of the key in every locale file when
  the format changes (one mutation per file, like a change of shape; *Automatic*
  only forgets the choice) and asks first when the new format holds less, naming
  what becomes of the text. `keys/rename` moves the entry and `keys/delete`
  removes it, for a retired key too; `keys/create` takes a `format`; the log
  line of `keys/save` names format and limit when they are posted. The editors
  are handed the key's format: **Enter** is nothing in `inline`, a `<br>` in
  `lines` and a new paragraph or item in `blocks` (Shift+Enter is the break,
  Enter in an empty item leaves the list, Backspace and Delete at the edge of a
  block join it by script); two toolbar buttons make bulleted and numbered lists
  and `blocks` shows paragraphs and lists in the editor's own style;
  formatting a selection that crosses a line or a block wraps each run, no
  longer flattening them into one tag; paste and drop are plain text in the
  profile's way (a newline is a `<br>`, a blank line a paragraph); **Ctrl/Cmd+B**
  and **+I** are `strong` and `em`, **+U** is swallowed, and the browser's own
  `format*` input is cancelled, so no `<b>`, `<i>`, `<u>` or style gets in; the
  browser's `<span style>` after a block merge, and `<div>`, are cleaned on every
  input; Enter is also handled on `beforeinput`, where a mobile or IME keyboard
  reports it. The steps the editor makes itself (splitting, joining, lists) are
  not on the browser's undo stack: Ctrl+Z undoes typing only. `Nino.css` gains
  `.nino-richtext`, the class for the element that holds a `blocks` value - the
  reset at its top takes the paragraph gaps and the list markers away.
  Documented in `docs/_admin.md` and `docs/_admin.de.md`, `docs/development.md`
  and `docs/development.de.md`, the element-types recipe and `AGENTS.md`.
  `tests/kernel-smoke.php` (855 → 901), `tests/admin-smoke.php` (336 → 355),
  `tests/admin-system-smoke.php` (725 → 809) hold the sanitizer, the helpers, the
  shortcodes, the Text and Keys actions and the import;
  `tests/admin-html-editor-js-smoke.js` (17 → 47), `tests/admin-text-js-smoke.js`
  (58 → 71), `tests/admin-elements-js-smoke.js` (191 → 202) and
  `tests/admin-elementtypes-js-smoke.js` (50 → 71) the editor, the tab and the
  forms. Checked in Chromium (Enter, lists, joining, paste, drop, shortcuts,
  limit); not in Firefox or Safari.

- **Elements:** a list of texts is edited as rows. An `array` field whose value
  is a list of strings - or is not there yet - gets `Nino.adminUi.stringList()`
  (`_admin/assets/Nino.admin.js`, `.nino-admin-stringlist*` in the design
  system): a text input per entry, move up and down, remove, and an add button.
  Like the list of references it keeps its value in a hidden input carrying the
  JSON, exactly as stored until a row changes, so the form's reading, comparing
  and saving are untouched; the rows carry no `data-field`, an empty row is not
  an entry, and the control owns no words. A list of anything else, and text
  that was typed and is not JSON, keep the JSON field. New fills
  `/_admin/elements/label/list-*`. `tests/nino-ui-stringlist-js-smoke.js` (new,
  24 checks) holds the control, `tests/admin-elements-js-smoke.js` which field
  gets which.

- **Element Types:** a field can be renamed, and a type duplicated. The name of
  a field is edited in its row, which says that the stored values move; the save
  asks and posts `renames` (`{ old: new }`) beside the model. `Types::apiSave()`
  moves every value of every entry under the lock - `*`, every locale bucket
  (a locale the project no longer offers included), the defaults entry `*` of
  each - rebuilding each entry from a snapshot, so a swap and a chain work, and
  reads the shape check against the old model under its new names, so a rename
  and a switch between global and per translation in one save migrate the values
  under the new key. It answers `400` for a field the type does not have
  (`types_rename_unknown`), a name the model does not have
  (`types_rename_missing`) and renames that are not an object of strings
  (`types_renames`), and `409` for two renames to one name or a name an
  unrenamed field keeps (`types_rename_collision`), a name that still holds the
  values of a field removed earlier (`types_rename_values`: the editor never
  deletes data) and an image field renamed onto the key of another
  (`types_rename_image`: an upload's file is named after its field and would
  overwrite the other's) - nothing is written. An image's link to the field that
  holds its alt text follows the rename: the form names a field the way it was
  saved while it is in the form, and `apiSave()` reads each link through the
  renames before the model is cleaned, so renaming that field is not a removal of
  the link. The answer carries `renamed` and `references`: the templates that
  still fill `[[old]]`, the roles granted `/_admin/elements/<type>/update/<old>`
  and the label text for the field, which are reported and not changed; the type
  list shows them once after the reload. A new image field must not take the name
  an image field had before it was renamed - its uploads would be named the same
  and overwrite the old file; the manual and the recipe say so. **Duplicate
  type** is made in the form: it posts the form's model, title and numbering
  under the new uri through the existing `types/create`, so the copy is what
  `cleanModel()` keeps of any model - no hand-written default or callback, no `*`
  defaults, no elements, a numbered type restarting at 1 - and the form asks
  about unsaved input first and opens the copy. The log names the renames. New
  fills `/_admin/types/*` and `/_admin/error/types_rename_*`.
  `tests/admin-system-smoke.php` holds the buckets, the swap and the chain, every
  refusal, the permission and the alt link; `tests/admin-elementtypes-js-smoke.js`
  the rows, the renames, the question, the request and the copy.

- **Workbench:** the rail's groups fold, and the phone gets a menu. A group
  heading is a button (`aria-expanded`, a caret, the rail's hover tint, a focus
  ring) that folds the links under it; the choice is kept per browser in
  `localStorage` (`nino-admin-nav-groups`), everything is open by default, and
  the group of the panel on screen is always open - selecting a panel opens its
  group. On the folded rail nothing is hidden, whatever was folded, and the
  headings are dividers that are not in the tab order. Below 64rem the strip of
  links gives way to one `<select class="nino-admin-nav-select">` built from the
  rail (`Nino.admin.navGroups`, `script.js`), one `<optgroup>` per heading and
  none for a rail with a single group, named like the nav itself; it shows the
  panel on screen - a tab selects its owner - and a change opens the panel as a
  move of the person's own (a history entry, see below). The strip is hidden
  only where the select exists. `Panels::$html['nav-group']` is a `<button>`
  now, a project that replaced it must follow; which links a heading folds is
  the order the server rendered them in, so no markup was added to
  `nav-link`. The meaning of a group, `Panels::_entry()` and the `features`
  group of feature panels are unchanged. The shared stylesheet's four
  `span.nino-admin-nav-group` selectors name the class alone, the button
  baseline's hover tint leaves the heading out, and the select's and the fold's
  rules are in the design-system half (classes only, `nino-admin-nav--select`,
  `nino-admin-nav-collapsed`). `tests/admin-script-js-smoke.js` holds the fold,
  its persistence, the open group of the selected panel, the folded rail, the
  select's groups and its change, and a rail with one group;
  `tests/admin-system-smoke.php` the heading as a button and every link under
  the heading of its own group.

- **Workbench, Navigations:** a route that exists only at runtime can be in a
  menu. A feature's route - Posts' `/blog` - is registered in `init()` and has no
  entry in `config.php` to carry `'navs'`, so it could not be put in one. The
  picker offers every live `GET` route that is not in `config.php` as well,
  marked `runtime` in the answer; its membership is stored under the new key
  `/nino/html/navroutes`, `[ 'GET://blog' => [ 'main' => 3 ] ]`, and
  `\Nino\Modules\Navigation::routeLines()` merges it into the routes that are
  live, the route's own `'navs'` winning for the same menu and a value that is
  not a whole number ignored. A feature that is switched off takes its entry
  with it - the route is not live, so nothing renders - and the next save of that
  menu drops what was stored. A route without `/webpage<uri>/name` is offered
  with the existing "not named yet" marker, and renders once it has one.
  Of the runtime routes, wildcard routes (a key ending in `/*`) and the
  workbench (`GET://_admin` and everything below it, `recovery.php` included)
  are not offered; technical
  routes - `robots.txt`, `/.search` - stay. The key is absent from `config.php`
  while no route has a membership, nothing is written into `/nino/http/routes`
  for a runtime route, and the live route array keeps its runtime routes after a
  save. `tests/kernel-smoke.php` holds the merge, the skipped routes and
  the malformed entries (907 → 915 checks); `tests/admin-system-smoke.php` the offer,
  the exclusions, the three keys and the live array.

- **Auth:** `Auth::lockedAccounts( &$appData )` answers `[ mail => timestamp the
  lock ends ]` for every account whose bucket in `data/auth-tries.php` holds a
  lock still running - the ip buckets and a bucket whose account is gone are not
  listed - and `Auth::unlock( &$appData, $username )` removes that one account's
  bucket and nothing else, a locked client address included. It answers `false`
  for an unknown account and when the file could not be locked or written (a
  `Filesystem::mutate()` that finds nothing to remove is a lift that is already
  done, and true). `tests/kernel-smoke.php` holds the list (an elapsed lock, an ip
  bucket, a bucket without an account, a plain count), the lift, the ip left as it
  was, the unknown account and a lock that cannot be taken (915 → 951 checks
  together with the two entries below).

- **Auth:** `Auth::setStatus( &$appData, $username, $active )` switches an
  account on or off, `Auth::STATUS_ACTIVE` (2) and `Auth::STATUS_DISABLED` (0)
  name the two values the code used as literals. Disabling ends every session of
  the account, with the revocation `updateUser()` and `logoutAllSessions()` make,
  and the request's own if it is that account's; both ways fire
  `/nino/auth/user/update` like `updateUser()`. A login stores its time in the
  account's own record as `lastLogin`, and `Auth::lastLogin( $user )` reads it -
  or the newest session of a record without the field - as a timestamp, `0` when
  the account never logged in. The time is not a file of its own: the
  catalogue's Hello test holds `data/` to the one file a feature writes. The
  merge in `AppData::writeContentData()` leaves it out of what makes a record
  "changed by this request" (`_sessionless()`) and keeps the later of the two
  times, so a login finishing after an administrator's change no longer writes
  the status it booted with over it - a deactivation came back as active, and in
  the other order the login time was lost - and an administrator's password
  change is not undone by a login in parallel. Both orders are in
  `tests/kernel-smoke.php` and red without the merge change.

- **Workbench, Users:** the list says more of an account. Beside its role it
  names, as words and not by colour, *Deactivated*, *locked until …* and *Last
  login: …* - or *Never logged in*. A manager **deactivates** an account from its
  form, after a question, and activates it again (`users/status`, `Auth::setStatus()`):
  never their own, and never the last active account with full access - a
  deactivated account does not count as one (`Roles::fullAccessExists()` looks at
  active accounts only), so the guards of delete, role change and the Roles
  tab's own save protect the last account that can log in. The Login protection
  tab lists **Locked accounts**, with the time each lock ends and a **Lift lock**
  button (`lockout/unlock`, `Auth::unlock()`); the lift redraws only that
  fieldset and leaves a number typed into the form above it alone. A locked
  address is not an account and is not listed. `tests/admin-users-js-smoke.js`
  (new, 24 checks) holds the list texts, the one-save request and the button,
  `tests/admin-lockout-js-smoke.js` (new, 19) the list, the lift that keeps a
  typed number, a refusal that keeps the row and a list without any `data-key`;
  `tests/admin-smoke.php` the list's new fields, `users/status` with its refusals
  and the one save (359 → 403 checks), `tests/admin-system-smoke.php` the
  Lockout tab's list and lift (842 → 869 together with the entry below).

- **Workbench, Roles:** a finer permission is picked, not typed. Under the
  picker, three lists that depend on each other - **Area**, **Action** and, for an
  action with fields, **Field** - and **Add permission**; the only strings the
  button can add are ones the panels list. A panel lists them with the new
  optional `scopes( &$appData )` of the panel contract - a tree of `{ scope,
  door, label, areas: [ { id, label, perm?, actions: [ { id, label, perm,
  fields?: [ { id, label, perm } ] } ] } ] }` - and
  `Users\Admin::scopeOptions()` asks the registry's classes for it and keeps only
  what is shaped like a permission (`Roles::isPermShape()`) and lies below its
  scope. `Elements\Admin::scopes()` offers each type with *add*, *change* (and
  each field) and *delete*; a field whose name cannot be part of a permission - a
  space, an umlaut - is left out and stays under the type's blanket.
  `Text\Admin::scopes()` offers each group and each key by its path, never a value.
  `roles/list` carries the tree as `scopes`. A permission a role holds that the
  tree lists is named by its place in it; one it does not stays under *Not
  offered*. Adding the first finer permission of a panel asks first - from then on
  the panel allows only what the role names - and so does taking the last one
  away with its ✕, which puts the panel back to everything (No keeps it); a line
  under the picker names the panels a role has that for. A summary, *This role may …*, says in words
  what the role does: full access, the areas it opens, what it may do in a panel
  in detail, a panel with single permissions that is not opened (the door is
  missing) and what nothing explains, by its string. The pure helpers
  `Nino.admin.roles.covers()`, `scopeState()` and `summarize()` port
  `Auth::checkPermission()` and `Admin::isScoped()`. The four `custom-*` fills and
  the `.admin-perm-add` rules of the typed field are gone, as is the
  `.admin-form-actions` row of the old role form.
  `tests/admin-roles-js-smoke.js` (new, 51 checks) holds the helpers, the
  transition warning in and out, that the lists only reach permissions of the tree and the
  summary's cases; `tests/admin-smoke.php` the tree's shape rules with a panel
  that offers a malformed one, `tests/admin-system-smoke.php` that granting each
  permission of the Elements and Text trees allows exactly that action, field or
  key.

- **Workbench, Users:** the recovery password can be changed from the
  workbench. A fourth tab of Users, **Recovery password** (`recoverypw/save`,
  its own permission `/_admin/recoverypw/manage`, offered on the Roles tab and
  held by the Developer role through full access), takes the current password,
  the new one - at least eight characters - and the new one again; a repeat that
  differs sends nothing, a success empties the fields and a failure keeps them.
  `\Nino\Admin\Recovery::change( &$appData, $current, $new )` checks the new
  password first, so a malformed request uses no attempt, then verifies the old
  one on the counter `recovery.php` shares - five wrong ones, from either door,
  lock it for an hour - and writes the hash through the one atomic writer; a
  project without a hash is refused with `409` and told to write the first one
  by hand. An open recovery session stays open. The activity log says *Change
  Recovery Password* and nothing of the data. `Recovery::MIN_PW_LENGTH` is
  public for it. `tests/admin-recoverypw-js-smoke.js` (new, 20 checks) holds
  the repeat, the request and what each answer does to the fields,
  `tests/admin-smoke.php` the permission offered once in the system group (403
  → 404 checks, with the tab lists of three assertions now naming the tab) and
  `tests/admin-system-smoke.php` the tab's guards, the attempt that is not used
  up, the shared lock, the stub the file stays behind and the log line (869 →
  926 together with the entries below).

- **Workbench, Backups:** **Back up now**. A button on the Backups screen - there
  for an empty list too, so it is how the first archive of a project is made, and
  not while backups are switched off - writes one more archive, named by date
  and time (`2026-10-02-170512`), beside the day's own and never in place of
  it: today's `Y-m-d.php` is the state before the day's work. It is listed
  before the daily archive, restored like any other and kept like the daily
  ones - 14 days, and at most the newest ten of its kind
  (`\Nino\Modules\Backups::now()`, `backups/now`, `Backups\Admin::ID_PATTERN`
  takes the new name). It does not take the lock every workbench request takes
  for the daily check, so the workbench keeps working while it runs; a failure
  is a `500` with its reason and writes no file. `backups/list` carries
  `enabled`. `tests/admin-backups-js-smoke.js` holds the button in all three
  states, the disabled button while it runs, the id written into the line
  and the list asked for again, and a refusal that keeps the list (16 → 27
  checks), `tests/admin-system-smoke.php` the archive, the daily one left
  byte for byte, retention, the refusals, and that restoring the archive of
  the afternoon and the daily one give their own states.

- **Workbench, Dashboard:** notices. What needs somebody's attention stands
  above the tiles until it is dealt with, drawn by one shared component -
  `Nino.adminUi.notice( message, { href, label } )` and `.nino-admin-notice`
  in the design system, a status paragraph whose text is always set as text and
  whose link is only drawn for a `#panel` target (`AGENTS.md` section 6a lists
  it). `dashboard/summary` answers `notices`, a list of `{ text, values, link }`:
  a fill key, what fills its `%s` in order, and where it leads.
  *Mail delivery has been failing since …* is for every account that opens the
  dashboard, like the date of the latest backup: it rests on a record of the
  last failed call (`/data/mail-status.php`, below) that holds no address.
  *Texts not translated yet (N) in …* is for an account that may
  open the Text panel, once per language, linking to `#text`: a key counts when
  the native language has a text for it and the other has none or an empty one
  - `\Nino\Modules\Text\Admin::untranslatedCounts()`, which skips hidden keys,
  the language-independent ones and a native text that is empty, and counts every
  such key for a configured language without a text file. Nothing falls back to
  another language and no key is created. `tests/admin-dashboard-js-smoke.js`
  (new, 25 checks) holds the component and the panel: the blanks filled in
  order, a value with markup or `$&` staying text, a link only for `#…`, and no
  notice for an answer without any; `tests/admin-lists-js-smoke.js` that it is
  one component; `tests/admin-smoke.php` the counts, `tests/admin-system-smoke.php`
  the notices for a developer, for an account with no panel permission and
  after a text is saved.

- **Mail:** a call that did not deliver leaves a record. `\Nino\Mail::send()`
  and `sendAll()` write `/data/mail-status.php` once per call - the date of the
  first failure, of the last one and the number of failed calls, no address and
  nothing a transport said - and a call in which every mail was delivered
  clears it; a call that sent nothing (the per-ip cap, an empty batch) neither
  raises nor clears it, and a record that cannot be written changes nothing
  about the answer. Once per call rather than per mail, because a contact
  form's owner mail and the confirmation behind it are one call: a confirmation
  that went out must not wipe the failure of the mail the owner is waiting for.
  `\Nino\Mail::failure()` reads it, and what each mail of the call came to is
  left in `./nino/mail/results`, one bool per mail in the order given, reset on
  every call. The record is never part of a backup (`Backup::NEVER`). A
  notice that comes and goes with a broken owner address is the known limit: a
  later call that delivers everything - a newsletter confirmation - clears it.
  `tests/kernel-smoke.php` holds the record, the count, the date kept, the
  clearing, the cap and the empty batch leaving it alone, a refused first mail
  with a delivered second, the results reset and a malformed file, 951 → 1002
  checks together with the entries below.

- **Maintenance:** the module brings its own page, in two languages - the
  built-in page used to be one English string in the class. `templates/page-maintenance.de_DE.tpl`
  and `.en_US.tpl` are self-contained (no include,
  no fills, `[[title]]` and `[[text]]`), rendered in the site's native
  language whatever language the route asked for - `useLocale()`, so nothing is
  written into the visitor's session - with the English one for a native
  language without a page, and the texts `/maintenance/title` and
  `/maintenance/text` from the project, else the module's own defaults in that
  language (`install/text/<locale>.php`), else English. A project's own
  `templates/page-maintenance.tpl` still wins and keeps the language of the
  route. The native locale is checked for its shape and for being available
  before it becomes part of a path; the class constant stays as the last resort
  for a checkout that lost `templates/`.

- **Maintenance:** a banner while the switch is on. Every public page a signed-in
  account opens carries one line at the top, put in by `callbackOutput()` on
  `/nino/http/output` (priority 9, after the cache's own) - in the normal flow
  after the opening `<body>`, so it covers no sticky header - for an HTML string
  body with a `</body>`, never for `/_admin`, a json answer, a feed or a
  fragment. The link to the switch appears only for an account holding
  `/_admin/maintenance/manage` and is built from the project's directory (the
  hook runs on the finished page, where a fill is no longer replaced). Every
  screen of the workbench carries the same notice (`Nino.adminUi.notice()`)
  for the accounts that have the Maintenance panel, drawn from the status the
  panel already asks for - no new action. `tests/kernel-smoke.php` holds the
  page per language and its fallbacks, the banner and everything it must not
  touch, `tests/admin-maintenance-js-smoke.js` (new, 16 checks) the notice
  following the state.

- **Features:** an optional manifest key `maturity` - how far along a feature
  is, in the author's own words, a string or a `locale => string` map of at
  most 24 characters each - which the Features panel draws as a badge beside
  the name in the Active, Inactive and Available tabs and on a feature's own
  screen. `\Nino\Catalogue` reads it from a catalogue entry as tolerantly as
  `category`: an invalid one is dropped, never a reason to refuse the catalogue,
  and a catalogue cached before the key existed reads as none (the panel's
  `?? ''`). Three read-only kernel accessors carry the shortcode report:
  `\Nino\Callbacks::registered()`, `\Nino\Html::shortcodes()` and
  `\Nino\Features::shortcodes()`; nothing on the render path changed.

- **Forms:** three more field types, `checkbox`, `radio` and `date`
  (`\Nino\Form::TYPES`). A ticked checkbox posts its value and an unticked one
  nothing, so a required one has to be ticked; a radio group posts the member
  that is ticked, which has to be one of its `options` - a radio without one
  non-empty option is left out of the definition, as a select without options
  still takes any value; a date is `Y-m-d` and has to be a day that exists. And
  `\Nino\Form::problems( $entry )`: one `[ 'field' => index | null, 'code' => ... ]`
  for each case `normalize()` leaves out or replaces (`key`, `field`, `name`,
  `reserved`, `duplicate`, `type`, `options`, `fields`, `to`, `ownerTemplate`,
  `userTemplate`), `[]` for a clean definition - for a writer that takes a
  definition from a person and wants to say where it went wrong instead of
  saving something else than was typed. `normalize()` and `problems()` are one
  routine (`_inspect()`), so they cannot disagree. `tests/kernel-smoke.php`
  holds the codes and places, the equivalence over a set of definitions and the
  three types through the endpoint, `tests/nino-ui-form-js-smoke.js` the
  client's side.

- **Routes:** the pages a feature serves by itself - Posts' `/blog`, the
  Newsletter's `/.newsletter`, Hello's `/hello` - are listed under the pages as
  **Feature routes**, with no arrows and no delete, because there is no route of
  the project's to move or remove. Their form shows the path read-only and the
  name, title and description per language, and `routes/savetexts` writes
  exactly `/_nino/webpage<uri>/name`, `…/title` and `…/description` for an
  Element-URI a runtime route carries right now (a `404` for any other), with
  the rules of `routes/save` for them: no route goes into `config.php`, no `uri`
  key is stored - the feature decides the path - and nothing goes on the
  blacklist. It is the one writer of those keys left for a page nobody saved:
  the Text Keys tab no longer creates a `/_nino` key (see Changed).
  `\Nino\Modules\Routes\Admin::runtimeRoutes()` names the pages, `routes/list`
  answers them under `runtime`. A write that fails is answered with a `500`
  naming the file, here and in `routes/save`, and the three words are stored as
  plain text, as the Text panel stores the same keys: the title and the
  description land in an attribute of the page head. `tests/admin-system-smoke.php`
  holds the list, the three keys and what is not written, the refusals, the
  failed write and the log line;
  `tests/admin-routes-js-smoke.js` the rows, the form and the request.

- **Language:** a language added in the panel gets its name. `language/addlocale`
  writes `/_nino/locale/<code>/name` into `text/global.php` with the code as its
  value, unless the key is there already, before it writes the language's file
  - and for a file that exists already too, which it touches in nothing else;
  before, a language added later had no name and the public picker showed the
  raw fill. The Text panel is where the name is made a real one.

- **`\Nino\Text::isGrammarKey( $key )`** answers whether a key follows the
  grammar (see Changed), and **`\Nino\Modules\Template::category( $name )`**
  the category of a template: a file name (`page-home.tpl`) or a template as a
  shortcode or route body names it (`/templates/page-home`), without `.tpl`,
  `null` where the name is no word of a key (a dot, an upper-case letter, a
  slash).

- **Text Keys scan:** every key a template reads and nothing defines is a row,
  with what may be done about it: `create` (it follows the grammar, so a value
  makes it a key - for a `/feature` or `/module` key with the note that it
  normally belongs to that feature or module), `system` (a `/_nino` key, named
  after a page or a language: no input, no *ignore*, and the row says who
  writes it - the Routes panel, the Language panel - or that nobody does) and
  `grammar` (any other form: no input, can be ignored). Placeholders a shortcode
  fills in - `[[name]]` in the mail templates, `[[.rel]]` - are no keys and are
  not listed. The answer carries a second list, `alsoUsed`: a
  `/template/<category>/...` key that a template of another category reads as
  well, with the files - a note under the rows, never a count. `keys/scanapply`
  creates only what may be created and neither creates nor retires a `system`
  row; the Dashboard counts every row. `tests/admin-system-smoke.php` holds the
  kinds, the writers, the placeholders, the apply and the note;
  `tests/admin-text-js-smoke.js` the rows without an input and the notes in
  both languages.

- **`tests/keys-smoke.php`** (new, 70 checks) holds the grammar and everything
  Nino ships to it: the text fragments of the base unit, the page units and the
  modules' units (the grammar, who may deliver what, the same keys in both
  languages, none global and per language at once, every fill a value names
  resolves), every key a shipped template reads (a runtime fill, a key of the
  system, a composed shape that is allowed, or a key the template's unit, the
  base unit or a required module delivers - and only the template's own
  category or `common`), the key literals in the kernel's and the workbench's
  code, no old key family left in any shipped file, and `category()` and
  `isGrammarKey()` over a table of cases. CI runs it in `lint-and-test`.

- **Workbench, Text:** texts are found by search. The Text panel and the Keys
  tab open with a search field above the list: every word must occur in the
  key, the text or the name shown for it, accents and case do not matter, and
  a word that starts with `/` looks at the key only. A hit shows the name, the
  place it belongs to and the match marked in the text, and opens the form on
  that field. At most 50 hits show, then *Show more*. The language code next
  to a hit switches the language the panel works in, *Empty in <language>*
  lists what is still missing in it, and the Keys tab has the chip *Hidden
  only*. A row opens by `#text/<key>` and `#keys/<key>` as well as by its row.
  New module `Nino.admin.textKeys` (`textkeys.js`) holds the model both panels
  share (rows, blocks, sections, fields, the search) without touching the DOM
  apart from the search bar and the hit; `Nino.adminUi.describeKey()`,
  `slugLabel()` and `humanize()` name a key by the vocabulary.

- **Workbench, Text:** the list is grouped the way an editor thinks. *Pages*
  come first in the order of the saved routes, a sub-page behind its page - the
  legal page and the demo catalogue, which have no template of their own to
  name, with a row each - then the block *General*: the project (company,
  website, mail), the common words, the building blocks sorted by name, the
  modules, the features, the system's own (languages, page details without a
  page) and the other keys. A page's form carries its details - name, title
  and description, the system keys `/_nino/webpage<uri>/*`; the path is not
  shown - in a section of its own above the template's texts, and both are
  saved in one request. The log line names every
  group the request touched (`Edit Text /template/page-home, /_nino/webpage/home`).
  The form shows each field with its German or English name from the
  vocabulary, a subtitle for what it is, an *All languages* badge for a global
  key and the key itself as small text; the sections' preview shows the first
  filled text.

- **Workbench, vocabulary:** 133 words `/_admin/common/word/<slug>` in both
  languages (*Name*, *Titel*, *Beschreibung*, *Preis*, ...). A key's last
  segments are looked up here: a word is itself, `<word>-<number>` is
  "<word> <number>", `<left>-<word>` is "<left> · <word>", and what the
  vocabulary does not know is humanized (`opening-hours` is *Opening hours*).
  The Elements field labels, the Images slot groups and the wizard's personal
  information fields use it, so the editor reads the same word in every place.

- **Workbench, Text Keys:** a key is created segment by segment. The form asks
  for the namespace, the category, the part and the name in four fields, shows
  the key it makes and checks every segment while it is typed against
  `[a-z0-9]+(-[a-z0-9]+)*`; the category offers the ones the project has. The
  namespace choice starts with `/project`, *Unlock* adds `/template`,
  `/feature` and `/module`, and `/_nino` and `/_admin` are never offered.
  Renaming a key uses the same form with the segments filled in, and a key of
  the system or the workbench has no *Rename*. A global value and the first
  value are asked only when a key is created.

- **Workbench, Elements:** the key of a field is picked from a list of 17 words
  (`name`, `title`, `description`, `price`, ...), with *Own key …* to
  type another one.

- **`tests/admin-text-js-smoke.js`** (83 -> 244 checks) holds the model, the
  search, the sections, the form, the page details and the locale switch on a
  fake DOM; `tests/admin-router-js-smoke.js` (50 -> 60) the hash links of both
  panels; `tests/admin-elements-js-smoke.js` (226 -> 230) and
  `tests/admin-elementtypes-js-smoke.js` (77 -> 85) the labels and the key
  picker; `tests/admin-smoke.php` (409 -> 424) the order, pages, templates and
  features `keys` answers and the log line; `tests/admin-system-smoke.php`
  (1011 -> 1023) the categories of the Keys list and that both languages carry
  the same vocabulary; `tests/admin-images-js-smoke.js` (49 -> 50) the
  grouping of slots; `tests/install-smoke.php` (307 -> 309) the wizard's
  labels.

- **Legal module (`\Nino\Modules\Legal`):** the imprint and the privacy policy
  are elements. Two types, `legal` and `privacy`, one element per section with
  a `title` and a `text` per language, an `order` and `hidden`; `[legal]` and
  `[privacy]` draw them - in the order of `order`, without the hidden ones, in the
  visitor's language, a section that has neither title nor text in it in the
  native one with `lang=""` - on two pages the module routes itself. The
  starting texts are newly written for Nino, German and English (the earlier
  `page-legal` templates are gone with the page unit), and the Wizard fills the
  types with them. A text names a fact of the website
  by a placeholder, `#/project/company/contact/email#`, which is replaced after
  the text was made safe, only in text and never inside a tag, only for a key
  of the four-segment grammar below `/project/company/` or
  `/project/website/general/` (`Legal::PREFIXES`, a constant, no setting): the
  value is resolved like `[[key]]`, then reduced to text - tags and entities
  out, the rest escaped, every bracket an entity, a line end a `<br>` - so it
  can bring no markup, fill or shortcode. A key that is no placeholder, has no
  value or lies elsewhere stays as written, so that it shows. The module
  registers one route per page and language (`/impressum`, `/datenschutz`,
  `/imprint`, `/privacy` from `/nino/legal/paths` in `config.php`, the unit's
  default), with the Element-URI `/legal/imprint` and `/legal/privacy`, the
  language and `'maintenance' => false`; a language without a path of its own
  is routed under its code, `/fr-fr/impressum`; a path that is invalid or taken
  is left out, and `check()` says so. `Legal::url()` answers the address of a
  page, `Legal::callbackSeoPages()` tells the Seo feature about the pages, and
  the callback `/nino/legal/section` lets a feature add to a section before it
  is drawn. The language panel calls `Legal::addLocale()` for a language added
  later (page details, labels, hints, the sections' version in that language
  where the unit has one). `Legal::check()` and `notices()` read what is wrong -
  placeholder without value, empty or never replaced, a section without
  text in a language, a missing type with the file to copy back, a bad path, a
  page in no menu a template outputs, the section of a feature that is off, an
  active feature without a section - and the Dashboard shows at most eight of
  them, to an account that may manage elements. **Not legal advice:** the
  texts are a starting point, not tailored and not legally reviewed, and
  the operator is responsible for having them checked; the README, `docs/setup`,
  `docs/development` (new section "Legal") and `docs/_admin` say so in the
  same words, and the Elements panel shows a hint above the sections of both
  types. `tests/legal-smoke.php` (new, 111 checks, in CI) runs the wizard in a
  sandbox and holds the unit, the texts (English, anchors, placeholders),
  the shortcodes, every way a value tries to become markup, the routes,
  `addLocale()`, the tombstones and `check()`.

- **Install units, `elements` and `navs`:** a unit's manifest may name element
  files, `'elements' => [ 'privacy' => 'elements/privacy.php' ]`, in the shape
  of a type file. `\Nino\Elements::seed()` (new) only adds: it creates a type
  that is missing when the file brings a model, adds an element that is in none
  of the type's buckets, adds a language version an element lacks, and
  replaces and deletes nothing, whatever the caller's overwrite flag says -
  elements are the editors' content, so a second Wizard run and a feature
  update leave a changed section as it is. It reads `/nino/elements/removed`
  (new, in `config.php`): the tombstone list of an element deleted for good,
  which the Legal module writes for its two types when a section is deleted
  in every language and removes again when it is created by hand. A value that
  does not fit the type's model is left out and logged; the write is one lock,
  the cache is dropped and `/nino/elements/committed` says insert and update.
  `\Nino\Features::applyUnit()` reads the key for the Wizard and a feature's
  activation alike, with the file's path checked. `'navs' => [ 'legal' => [
  '/legal/imprint', '/legal/privacy' ] ]` creates a menu: only the Wizard's
  apply step reads it, only where the project has no menu of that key, and a
  second run leaves the editors' entries. `\Nino\Html::resolveTextfill()`
  (new) resolves one text key with its nested fills, the way `[[key]]` does,
  and `[json]` uses it. Tests: `tests/kernel-smoke.php` (1051 -> 1098),
  `tests/features-smoke.php` (251 -> 259, with
  `tests/fixtures/features/Sample/install/elements/privacy.php`),
  `tests/install-smoke.php` (309 -> 324), `tests/catalogue-smoke.php`
  (169 -> 172, the privacy sections a removed feature leaves behind).

- **Route field `maintenance`:** a route with `'maintenance' => false` stays
  reachable while the site is in maintenance. `Modules\Maintenance` reads the
  field from the response the route was merged into; the two Legal routes carry
  it, and the maintenance page that is built in links the imprint and the
  privacy policy (`[[legal]]` in `page-maintenance.<locale>.tpl`). The
  Routes panel keeps it - like every field it does not edit, `locale` and
  `header` among them - when it saves a route.

- **Menu membership of runtime routes by Element-URI:** `/nino/html/navroutes`
  is keyed by the route's `uri` instead of its route key, so a page with one
  route per language is one entry in a menu, named in the visitor's language,
  and `Navigation::routeLines()` picks the route of the current language. The
  Navigations panel shows such an entry once, with all its paths, and sends the
  first one; the Routes panel's list of the pages of features and modules does
  the same (`httpUris`).

- **Workbench, Elements:** a type may have a hint,
  `/_admin/elements/type/<type>/hint`, shown as a paragraph of the design
  system above its list and above the form of an element, its words as text.
  The Legal unit brings the two hints that say the texts are no legal advice.

- **Workbench, Features:** deactivating or removing a feature that brought
  sections of the privacy policy says so in the dialog - the policy still
  describes it - with the titles, and the Dashboard keeps saying it.
  `tests/admin-features-js-smoke.js` (188 -> 192),
  `tests/admin-elements-js-smoke.js` (230 -> 235),
  `tests/admin-navs-js-smoke.js` (43 -> 48),
  `tests/admin-routes-js-smoke.js` (58 -> 61),
  `tests/admin-dashboard-js-smoke.js` (25 -> 28) and
  `tests/admin-system-smoke.php` (1023 -> 1051: the Dashboard's legal notices,
  `language/addlocale`, routes, menus, the reserved Element-URIs).

### Changed

- **Workbench:** what the panels print for a failure. A failure with a code is
  the code's sentence without the number in front; one without keeps
  `(status) message` - the server's own message first, as before, then the
  panel's sentence - so a veto a project's callback wrote and a reason a feature
  cannot be activated, which are content, still reach the person. A request
  that never reached the server says that instead of `(500)`. The upload
  endpoints answer what went wrong in a code of its own where they used to say
  *no file uploaded*, *could not read upload* and *invalid or oversized image*:
  an unreadable temporary file is a `500`, an image the kernel refuses names
  its reason, and one gd cannot decode after all is `image_unreadable`.
  `Admin::guard()`'s `401` carries the code `session`, so a client that
  matched the whole body must match `{ "error": "not logged in", "code":
  "session" }`; `tests/features-smoke.php` and `tests/catalogue-smoke.php` do
  (6 and 2 red before). `tests/admin-lists-js-smoke.js` holds that every panel
  posts through the helper, keeps no transport of its own and writes no status
  number itself (138 → 141 checks, 5 red before); `tests/admin-features-js-smoke.js`
  reads the helper's uri from `Nino.dir` (2 red before).

  Feature panels (`features/<Name>/assets`) are untouched: they keep posting
  as they do and do not get the session recovery before they adopt the
  helper, which is for the release after this one - on the kernels before it
  `Nino.adminUi.api` does not exist, so a feature must detect it.

- **Wizard:** "New Route" is a secondary action beside Next, so the bar has
  one primary button again. The Accounts step names the rule in the password's
  label - "Password (at least 8 characters)" - sets `minlength` and asks for
  the password twice, as Finish already did; a repeat that differs posts
  nothing and says "Passwords do not match." The recovery password's label
  names the same rule. The server and its API are unchanged, and the repeat
  stays a typo guard in the browser; `docs/setup.md` and `docs/setup.de.md`
  say so under Accounts and Finish. `tests/install-script-js-smoke.js` holds that the
  list action is secondary and Next the only primary (23 → 26 checks, 1 red
  before); the new `tests/install-accounts-js-smoke.js` holds the mismatch,
  the one call with `{ mail, pw }` and the cleared fields (9 checks, 4 red
  before); `tests/install-smoke.php` ties the number the template shows to the
  one the API enforces, a password one character short being refused and one
  of the length accepted (see the count below).

- **German, Du:** every German text of the workbench and of the starter site
  says the capitalised "Du", "Dich", "Dein", with the verbs, imperatives and
  reflexives that go with it (the German manuals under `docs/` are not part of
  this). In the workbench - which reaches every project with this update -
  that is the login screen (the wrong-input hint, the expired-token hint and the
  welcome line), the Configuration proxies hint, the Elements required-fields
  message, the Users own-account marker ("(Du)") and the Maintenance hint
  ("melde Dich ab"). In the texts the wizard writes - which reach **new
  installations only**: an existing project keeps its words and changes them
  in the Text panel - it is the base unit, the home, about-me and 404 pages,
  the privacy policy (every form of address, 54 of them) and the Form and
  Localepicker units. The Maintenance unit's text has no manifest and the
  wizard never applies it; it is converted as well, for a project that copies
  it by hand. Keys and the English texts are unchanged. Three sentences speak
  about a thing rather than the reader ("Sie ist danach von diesem Server
  verschwunden") and stay. `tests/install-smoke.php` expects "Wähle Deine
  Sprache"; no test scans the texts for the other forms of address.

- **Docs:** the template editor's code dialog is called "HTML+ Editor" in
  `README.md`, `README.de.md`, `docs/concepts.md`, `docs/concepts.de.md` and
  `docs/setup.de.md`, where they said "escape hatch" and "Escape-Hatch". The
  workbench's own label follows with the Templates feature in the catalogue.

- **Images:** `[image]` no longer uses the slot's label as its alt text. The
  alt text is the one stored for the slot in the language of the page, else the
  template's own `alt="..."`, else none - `alt=""`, which is how a decorative
  picture is written and what the hint in the panel promises for an empty
  field. A slot that relied on its label now renders `alt=""` until an alt
  text is written. The text is output escaped and with `[` written as
  `&#91;`.

- **Images:** `\Nino\Images::setSlotFilename()` accepts `null` (no image) and
  answers whether `config.php` was written; it changes the slot's `filename`
  there and nothing else (a mutation of `config.php`, like `setSlotAlt()`), is
  `false` for a slot `config.php` no longer holds, and leaves `$appData` as it
  was where nothing was written. The Images panel's upload does not give up the
  slot's previous picture when that write fails: it answers `500`, a file it had
  just written under a new name is removed, and a re-upload that overwrote the
  old file under its deterministic name has the old bytes put back.

- **Installer:** the home page's hero is the image slot
  `/page-home/fullscreen-image/background` (1920 × 1080), seeded with a neutral
  placeholder drawing - a small SVG of Nino's own,
  `images/page-home/fullscreen-image/background.svg` of the home unit, which
  replaces the 2 MB photograph `demo.jpg` (6000 × 4000, above the 20 MP an
  upload may have). The file carries the slot's own name, so an upload replaces
  it and **Remove image** deletes it. `page-home.tpl` shows the slot with `[image
  /page-home/fullscreen-image/background alt=""]` instead of a literal `<img>`;
  the Template Builder metadata names the slot too, so the next section edit
  composes the same markup. An existing project migrates by hand: in
  `templates/page-home.tpl` replace the literal `<img src="[[/nino/public]]/images/demo.jpg" alt="">`
  with the `[image ...]` line above (and `"backgroundImage":"[[/nino/public]]/images/demo.jpg","backgroundImageSource":"fixed"`
  in the section comment with `"backgroundImage":"/page-home/fullscreen-image/background","backgroundImageSource":"new"`),
  create the slot (1920 × 1080) under *Image Slots* and upload the picture.

- **Installer, templates:** the logo is the image slot `/logo` (500 × 100, empty
  to begin with), declared by the base unit, and every place that showed the
  THATSNINO logo asks the slot: `theme.header.tpl` (`[image /logo alt=""]`),
  the burger navigation `html-header-nav.tpl` (in a `.nino-headernav-logo`
  wrapper, which is empty and takes no room without a logo), the `og:image` and
  `twitter:image` tags of `html-header.tpl` and the Form module's
  `mail-header.tpl` (the address through the content form of `[image]` above).
  With no logo uploaded the header and the navigation show no picture, the mail
  header none, and the page head carries no `og:image` and no `twitter:image` -
  no broken `<img>`, no empty tag. The Setup step adds the slot like the Routes
  step adds the pages': once, never replacing a slot the project has. On a fresh
  install the *Missing image slots* tile now reads 0: no template of the starter
  site carries a literal `<img>` of the images directory any more.
  **Upgrading:** a project set up earlier keeps its `templates/*.tpl` and its
  `public/images/logo.png`; to use the slot, create `/logo` under *Image Slots*,
  upload the logo and replace every `images/logo.png` reference in
  `theme.header.tpl`, `html-header-nav.tpl`, `html-header.tpl` (its `og:image` and
  `twitter:image` tags too) and `mail-header.tpl` the way the shipped files do. `Nino.css` gains `header .nino-headernav-logo img`
  and the `:empty` rule. `tests/install-smoke.php` holds the slot, the
  frames with and without a logo, and the tile.

- **Html:** `sanitizeHtml()` leaves a space where it unwraps a block that
  separated two runs of text - `<p>Grill.</p><p>Zweiter</p>` read
  `Grill.Zweiter`, and the words of a pasted text ran together - unless a space
  or the end is there already. Every other input and output is as it was (the vectors of the
  Text, Elements and kernel tests are byte for byte the same; Posts' pinned
  `<h2>Not a heading</h2>Second.` is `Not a heading Second.`, not a newline,
  which `Posts::_body()` would turn into a `<br>`).

- **Text:** the starting value of a new key - `keys/create` and the scan's
  *Apply* - goes through `Text::sanitizeValue()` like every other value saved
  from the workbench, in the format it was asked for or its value shows. It was
  stored as typed: tags the key's format does not have and shortcodes included.
  `Text::sanitizeValue()` also makes a line of every `<br>` and every end of a
  block before it strips the tags of a plain value (`Html::breaksToNewlines()`),
  where `Amtsgericht<br>Musterstadt` became `AmtsgerichtMusterstadt`.

- **Workbench, Routes:** a new route starts empty and a delete says what stays.
  The form for a new route has no Element URI and no HTTP URI, and the texts no
  "Page" / "Page Title" / "Page description." filler (the server's `DEFAULT_TEXT`
  and `_orDefault()` are gone; the wizard's own Webpages step keeps its own).
  A **name** and a **title** are required in every active language, and so are
  both URIs and the template: the form checks them before it sends anything,
  marks each field `aria-invalid` with an asterisk after its name
  (`.nino-admin-required`) and `aria-required`, puts the focus on the first and
  says it in the status line - the browser's own validation bubble, in the
  browser's language, is switched off for the form (`novalidate`). Every
  name, title and description field says its language in an `aria-label`. The
  server refuses a missing or blank name or title with a `400`, the codes
  `routes_missing_name` and `routes_missing_title` and the language, after the
  URI, collision and template refusals and before anything is written, so config.php
  and the text files are untouched; a blank description is stored empty. The
  template is proposed only as `page-blank`, when `templates/` has it - the
  answer of `routes/list` carries `defaultTemplate` - and otherwise the select
  starts on a disabled "Choose a template…", never on the template that sorts
  first and never on another finished page (`page-404`, `page-contact`). The
  delete question names what stays: the page's texts (`/webpage<uri>/name|title|description`
  with the languages that hold a value, and `/webpage<uri>/uri`) and the
  template file, with how many other routes use it, or the body for a route that
  picks its template at runtime. A row of the list is the page's name - in the
  content language last chosen in Elements or Text (the native one before that), else the first that has one, else the path - over
  its path, and a **↗** that opens the page in a new tab, only for a path
  below the site (`routes/list` answers `selectedLocale`). The German interface
  says all of it. `tests/admin-routes-js-smoke.js` holds the empty form, the
  proposal, the required fields, the question and the row (16 → 46 checks);
  `tests/admin-system-smoke.php` the refusals that write nothing, the empty
  description and the proposal; `tests/install-smoke.php` posts a name and a title.

- **Workbench, Navigations:** a menu is saved as a whole. ↑, ↓, × and Add change
  a working copy in the browser and write nothing; the status line says there
  are unsaved changes, and **Save** posts the complete running order - `navs/save`
  takes an optional `entries`, a list of HTTP URIs - which the server writes
  under the lock on `config.php` in one write of the three keys it touches, the
  priorities dense `1..n`; a route that is left out loses its membership (and a
  route in no menu carries no `'navs'`), a rename and its entries are one save,
  and a refused one - an entry listed twice (`navs_duplicate_entry`), a route
  that does not exist (`navs_unknown_route`, `404`), entries that are no list
  (`navs_invalid_entries`) - writes nothing. Without `entries` a save only creates
  or renames, as before; `navs/delete` is under the lock too. `navs/assign`,
  `navs/unassign` and `navs/move` are gone, and with them `navs_route_present`:
  nothing but the panel and the tests called them. The picker starts on an empty
  choice and Add waits for a route. An id typed above the entries survives a click
  on an arrow - only the entries are drawn again. The panel registers its
  working copy with the shell (`Nino.admin.dirty.register`), so the back link,
  logout and the interface language ask Save, Discard or Cancel; a shell that
  does not have the registry gets a `confirm()` on the back link instead.
  Showing the panel again reads the menus and routes again - a copy with
  unsaved changes is never replaced, only the routes it can pick from are.
  The German interface says all of it. The new
  `tests/admin-navs-js-smoke.js` (42 checks) holds the copy, the Save request, the
  picker, the back link, the redraw and the registry; `tests/admin-system-smoke.php`
  the order, the dense priorities, the refusals and the rename (the old assign/move/unassign checks are rewritten as saves with
  entries).

- **Workbench, Users:** an account has one Save. Address, password and role are
  one `users/save` (`role` is optional) and one button; the separate role form
  and its second status line are gone, and `users/role` stays for callers that
  use it. Everything is checked before anything is written. The role counts only
  when it differs from the stored one: it needs the manage permission, is refused
  for your own account and runs the checks of `users/role` (an existing role, not
  the last full access, not wider than the signed-in account) - so a manager
  without full access who changes only the address of a Developer account keeps
  being allowed to, as a rename grants nothing. `users/save` answers `{ mail,
  role }`, and an unchanged account is not written. The activity log's line for
  `users/save` names a posted role. The panel's comments no longer call the status
  a developer-only, direct-JSON task.

- **Workbench, Roles:** only an active account counts as the last full access.
  `Roles::fullAccessExists()` skips an account whose `status` is not
  `Auth::STATUS_ACTIVE`, so deleting, deactivating or taking the role from the
  last account that can log in is refused even where a disabled account holds
  full access on paper.

- **Recovery:** setting a password no longer creates an account from a typo.
  `recovery/reset` sets the password of an account that exists - picked from a
  list on the page, which has no free address field any more - and answers
  `404` for an address nobody holds. It also lifts the account's lock and
  activates it again if it was deactivated, so a recovered developer account
  gets in. Creating an account with full access is its own action,
  `recovery/create`: it needs `confirm: true`, which the page sends only after
  a question, refuses an address that already has an account (`409`) and
  checks the address and the password like reset does. The page has its two
  sections, *Set a password* and *Create a full-access account*, and re-reads the
  accounts after a create. `tests/admin-recovery-js-smoke.js` (new, 18 checks)
  holds the select, that a create sends nothing when the question is
  answered no or the repeat differs, and the request after yes;
  `tests/admin-system-smoke.php` the in-process actions of both, the new
  password logging in, the sessions gone, the lock lifted and the session flag
  that is unset.

- **Workbench, Security:** the `/nino/admin/action` event no longer carries the
  passwords of `users/create`, `users/save` and the new Recovery password tab.
  `data` is still the posted payload, but its top-level `pw`, `current` and
  `currentPassword` are blanked, so a listener sees that one was sent and never
  which - the old and the new secret of the Recovery password tab would
  otherwise have reached every listener in plain text. A secret posted under
  another key is not blanked, with one exception: a feature's `secret` setting
  in `features/settings` (an SMTP password, an API key) comes out blank as
  `data.fields.<name>` too - see *Admin events* in the leftovers of the review
  under Fixed. `tests/admin-system-smoke.php` posts through the dispatcher with
  a listener registered.

- **Contact form:** an inquiry whose owner mail did not go out is answered with
  a `500` and no body - the generic message on the page - where it used to be
  `200`, so that nobody learned a form had stopped reaching them. The inquiry is
  still recorded where the project keeps a copy (`/nino/form/store`), and the
  Dashboard says why. Only the owner's mail decides: a visitor confirmation that
  could not be delivered while the owner's went out answers `200` in both store
  modes, because the owner has the inquiry and a visitor sent back to the form
  would only send it twice. A submission the cap refused is still a `429`.
  The checks in `tests/kernel-smoke.php` and `tests/admin-smoke.php` that submit
  the form passed only because a failed `mail()` still answered `200` - on a
  machine without a sendmail, as the output shows; they register a stub transport
  now, and the new ones pin the `500`, the record kept, and the confirmation
  that alone is refused.

- **Features panel:** it says what a switch did. Activate and an Update that
  switched a requirement on show *Switched on: Social media links, Lightbox.*
  in a dialog before the workbench reloads - the feature asked for first, the
  rest by name; `features/activate` answers `switchedOn: [ { key, name } ]`
  for it, and so does `features/install` for the install that switched on
  (its `required` keeps saying what was placed, which is not the same: a
  requirement that was in the directory but off is switched on without being
  placed). Install's message names the switched-on requirements instead of the
  placed ones where it switched on. Deactivate answers `found`: the templates,
  texts and elements that still contain the feature's shortcodes
  (`[ { shortcode, total, places: [ { kind, where } ] } ]`, ten places a
  shortcode at most), shown in a dialog after it - a shortcode of a feature that
  is off stays as the text it is written as. Opening tags and raw brackets only:
  a shortcode the Text panel stored neutralized, `&#91;...&#93;`, never
  rendered and is not reported. Which shortcodes are a feature's is read from
  the callbacks registered, not from its manual. Contract tests in
  `tests/features-smoke.php`, `tests/catalogue-smoke.php`,
  `tests/kernel-smoke.php` and `tests/admin-features-js-smoke.js`.

- **Features panel:** a feature's settings live in a tab of its own panel. An
  active feature that has a panel and declares settings gets a **Settings** tab
  in that panel, added by the registry (`\Nino\Modules\Features\Settings`,
  `_admin/Nino/Modules/Features/Settings/Settings.php`), with no code in the
  feature: the same form, drawn by the new `Features/assets/settings.js`, over
  the same `features/list` and `features/settings` actions and the same
  permission `/_admin/features/manage` - so an account holding only the
  feature's own permission still does not see the settings, while an account
  holding only `/_admin/features/manage` gets every such panel in its rail,
  opened on the Settings tab alone, and the seven actions are still seven. The tab's label and group are the Features panel's,
  which keeps the Roles list and the Dashboard as they were (one permission
  entry, one tile). `features/list` entries gain `settingsTab`, the uri of that
  tab or `''`; on a feature's screen in the Features panel the form and its
  Save are drawn only where it is `''`, and a link leads to the tab otherwise.
  A registry without the Features module's directory simply attaches nothing.
  Behind it, `Nino.adminUi.panelHead().tabs()` no longer replaces the registry's
  own strip when a screen inside one of the pane's tabs draws a strip of its
  own: that one goes to the top of the tab's pane, replaced on a redraw, and
  the head keeps its tabs - a feature that draws a strip (Redirects) would
  otherwise have lost the Settings tab on its first draw. `tests/features-smoke.php`
  (237 → 251 checks) holds the tab, who sees it and the unchanged Roles list and
  tile, `tests/admin-script-js-smoke.js` (148 → 151) the strip, the new
  `tests/admin-feature-settings-js-smoke.js` (31 checks) the form, its Save and
  its errors, `tests/admin-features-js-smoke.js` (185 → 188) the detail screen.

- **Forms:** the mail templates a new installation starts from carry
  `[[fields]]`. `mail-owner` and `mail-user` used to fill only `[[name]]`,
  `[[email]]`, `[[subject]]` and `[[message]]`, so the answer to a field of
  another name - a checkbox, a date, anything a project added - reached neither
  the owner nor the visitor. They now show the whole submission as a table and
  keep the date row. Add-only: a project that installed before keeps its
  templates and copies the placeholder in by hand.

- **Text keys (breaking):** every text key follows one grammar,
  `/<namespace>/<category>/<part>/<name>` - four segments, each lower-case
  words joined by hyphens, English, never a number as a name. The namespaces
  are `template` (the words a template reads, category = its file name without
  `.tpl`; words several templates read live in `/template/common`), `project`
  (the project's facts and settings: `company`, `website`, `mail`),
  `feature` (the words of a catalogue feature's function, category = its key)
  and `module` (a kernel module's words, category = its directory, lower case).
  The system's own forms are `/_nino/webpage<uri>/<name|title|description|uri>`
  and `/_nino/locale/<code>/name`; `/_admin/...` is unchanged; the kernel's
  runtime fills all live under `/nino/` (`[[/date/year]]` is `[[/nino/date/year]]`).
  A label in front of a fact and the fact are two keys now: `/template/common/label/phone`
  is the word, `/project/company/contact/phone` the number, which every template
  reads from there alone. The rules and the order of the questions that decide
  where a word belongs are in the Developer Manual, "The key grammar". Every key
  Nino ships is renamed, with every reader of it - templates, `Form`, `Mail`,
  `Maintenance`, `Localepicker`, `Navigation`, `Nino.ui.js`, the Routes and
  Images panels, the wizard - and its documentation:

  | before | now |
  | --- | --- |
  | `/company/{name,description}` | `/project/company/general/{name,description}` |
  | `/company/{adress,phone,email,country}` | `/project/company/contact/{address,phone,email,country}` |
  | `/website/{url,author,host}` | `/project/website/general/{url,author,host}` |
  | `/website/{charset,lang}` | `/project/website/html/{charset,lang}` (blacklisted) |
  | `/form/email/owner`, `/mail/sender` | `/project/mail/address/{owner,envelope}` |
  | `/mail/style/...` (11) | `/project/mail/{color,font,spacing}/...`, in the base unit now, blacklisted |
  | `/website/header/title/navigation` | `/template/frame-header/navigation/label` |
  | `/website/footer/title/navigation` | `/template/common/navigation/footer` |
  | `/website/footer/title/getintouch` | `/template/frame-footer/contact/title` |
  | `/global/{adress,phone,email}` | `/template/frame-footer/label/address`, `/template/common/label/{phone,email}` |
  | `/slider/label/{prev,next,slide}` | `/template/common/slider/{prev,next,slide}` |
  | `/form/label/{name,email,message,cat,date,submit}`, `/form/required` | `/template/common/form/{name,email,message,reason,date,submit,required}`, in the base unit now |
  | `/form/info/*`, `/form/subject/*` | `/module/form/info/*`, `/module/form/subject/*` |
  | `/mail/owner/*`, `/mail/user/*` | `/template/mail-owner/...`, `/template/mail-user/...` (`intro`, `summary`, `outro`) |
  | `/maintenance/{title,text}` | `/module/maintenance/page/{title,text}` |
  | `/nino/locales/title`, `/nino/locales/locale/<code>` | `/module/localepicker/menu/title`, `/_nino/locale/<code>/name` |
  | `/page-<page>/...` | `/template/page-<page>/<part>/<name>` (`/page-services/item/1/title` is `/template/page-services/item-1/title`, `/page-404/backhome` is `/template/page-404/hero/button`) |
  | `/webpage<uri>/...` | `/_nino/webpage<uri>/...` |
  | `/newsletter/info/*` (read by `Nino.ui.js`), `Jstext`'s default prefixes | `/feature/newsletter/info/*`, `/module/form/info/`, `/feature/newsletter/info/`, `/template/common/slider/` |

  The Text Keys tab holds people to it: `keys/create`, `keys/rename` and
  `keys/scanapply` accept only a new key that follows the grammar - a `400` with
  the code `keys_invalid` and a sentence that names the form - and `keys/rename`
  refuses to rename a key under `/_nino/` or `/_admin/` (`keys_system`), whose
  name is what the code that reads it asks for. Keys a project already has are
  not touched and stay editable: saved, hidden, deleted, renamed to a key that
  follows the grammar. The tab's form still takes one line; the field per
  segment comes with the Text panel's rework. The legal page is the one
  exception until the Legal module replaces it: `page-legal.<xx_XX>.tpl` and the
  footer link keys `/website/legal/{uri,name}` keep their names, and what the
  page reads from other units follows the table.

  **No migration, no aliases:** nothing was published with the old keys. A
  project set up with an earlier version keeps its old texts and renames them
  with the table - `[[/company/name]]` in a template of its own stays what it
  is and shows raw until it is changed. `tests/keys-smoke.php` (see Added) is
  what finds a leftover.

- **Frames:** `theme.header.tpl` and `theme.footer.tpl` are `frame-header.tpl`
  and `frame-footer.tpl` - a file name is a category now, and a dot is no part
  of one. `html-header.tpl` and `html-footer.tpl` include them as
  `[template /templates/frame-header]` and `[template /templates/frame-footer]`.
  A project that edited a frame renames its file and the include in its own
  `html-header.tpl`; a feature that writes the frames (Design) writes the new
  names.

- **Setup:** a page unit's proposal for the name, title and description of its
  page is the `suggest` entry of its manifest - `uri`, `name`, `title` and
  `description`, each a string or one string per language - and no longer a
  `[[/webpage/<folder>/...]]` key in its text files, which were never written as
  text and were the one place a unit delivered a key of the system. The
  home page's hero seed is `images/template/page-home/fullscreen-image/background.svg`
  and its slot `/template/page-home/fullscreen-image/background`; the section
  JSON of `page-home.tpl` and `page-contact.tpl` names the page `page-home` and
  `page-contact` (the Template Builder takes the category for its page id). The
  contact page's, the mails' and the demo catalogue's words for a form are the
  base unit's `/template/common/form/*` now: the contact form unit keeps its 15
  words per language (`/module/form/*`, the two mails), and no longer ships a
  `text/global.php` or a blacklist - the look of the mails is the base unit's, so
  the Newsletter feature, whose copy was byte-identical, needs no second one.
  The *Personal information* step offers `/project/company/` and
  `/project/website/general/`, each field labelled by its category and name
  (`Company › Address`, `Website › Url`) in English like the rest of the
  wizard, and `/project/website/html/*` is outside it without a blacklist of its
  own. `tests/install-smoke.php` holds the proposals read from the manifest, the
  keys of the project texts, the labels and pages named `/footer`, `/common` and
  `/2026-home`.

- **Version:** `\Nino\VERSION` is `1.4.0`. Features that read these keys
  declare `nino ^1.4`; a feature that only reads its own keys is not
  concerned.

- **Workbench, Text:** `keys` answers four more members: `order` (a key to its
  place in the files, for the order the editor expects), `pages` (the saved
  routes that are pages, `null` without the Routes panel, for the page rows),
  `templates` (a category to its template file and the
  `<!-- nino:template-name -->` of it) and `features`.
  The keys of the workbench itself (`/_admin/...`) are not listed in the Text
  panel any more, they are Nino's words and not the project's. `keys/list`
  answers `selectedLocale` and `categories`. The log line of a save names its
  groups, not only the first key.

- **Workbench, Elements:** a field is labelled by its fill, then by the
  vocabulary word of its key, then by the key itself with a capital
  (`Price_default`); the sixteen demo labels the Elements unit carried
  (`/_admin/elements/field/<type>/<name>`) are gone, the vocabulary says them.
  The hints of an image and its alternative text name the field they are linked
  to by the same label: its fill, then the word, then the key with a capital.

- **Workbench, Images:** slots are grouped by namespace and category of their
  key, so the same word names them as in the Text panel.

- **Setup wizard:** the personal information fields are labelled from the
  English vocabulary, `Website › URL` instead of `Website › Url`.

- **Wizard, the imprint:** the Legal unit is applied on every run
  (`Setup::ALWAYS_MODULES`), so a new project has the two pages, the types with
  their sections and a third menu, **legal**, with both pages as its first
  entries. The footer of the base frames outputs it with `[navigation
  nav="legal" id="legal__nav"][/navigation]`. The starter site has three pages (home, contact, 404); the
  imprint is no longer one of them. The Routes step offers the third menu as a
  checkbox, refuses the two Element-URIs of the Legal pages (`409`), and the
  Webpages step scans the units' templates when it asks who owns one
  (`_unitOwningTemplate()`).

- **Workbench, Routes:** the section for runtime routes is called "Routes of
  features and modules"; routes of one Element-URI are one row. `routes/save`
  builds the stored route from the previous one and sets only `uri`, `body`,
  `statusCode` and `navs` anew, so `maintenance`, `locale`, `header` and other
  fields of a hand-extended route survive a save; `routes/save` refuses the two
  Element-URIs of the Legal pages (`routes_reserved_uri`).

- **Maintenance:** the login's exception is the route field `maintenance`
  (`Auth` carries it on its login route) instead of a constant in
  `Modules\Maintenance`, one mechanism for both.

- **Text key grammar:** the legal page was the one exception to the grammar
  (`page-legal.<xx_XX>.tpl`, `/website/legal/{uri,name}`); with the page gone
  there is no exception, and `tests/keys-smoke.php` (70 -> 74) holds none.

### Fixed

- **A rich field lost everything behind an invalid byte on libxml 2.13.** The
  sanitizer gave the value to `DOMDocument::loadHTML()` as it was; libxml 2.9
  carried a byte that is not utf-8 on, 2.13 ends the text node at it and the
  rest of the value was dropped, silently, on save. `Html::sanitizeHtml()`
  now turns such a byte into the replacement character before parsing, the
  way a string field's is handled, so the result is the same on every libxml.

- **`tests/kernel-smoke.php` failed under `display_errors=On`.** The child
  process the boot checks spawn printed the fatal to stdout, where the test
  expected only what the script wrote. It runs with `display_errors=stderr`.

- **Workbench, Elements:** a required field is marked and a refused save says
  where. A required field carries an asterisk after its name (`aria-hidden`,
  `.nino-admin-required`) and `aria-required` on its control - on the text box
  of a rich-text field, on the name of a list of references, which has no
  control that could carry it; an image, a yes/no choice and a field this
  account may not write are not asked for. A save that finds a required field
  empty sends nothing, marks each such field `aria-invalid` with a sentence
  under it (`.nino-admin-field-error`, linked with `aria-describedby`, not an
  alert), says it once in the form's status line and moves the focus to the
  first one; if that one is in another language, the form switches to it first -
  the select, the content locale and the fields. The language switch names the
  languages a save would write that still have open fields (*de_DE - 2 open*).
  After a first refusal the marks follow what is typed, and a form whose fields
  hold what they were drawn with again counts as unchanged again - its status
  line goes back to idle once nothing is marked. The uri of a new element is a
  required field of the same kind in place of the browser's own bubble, which
  came in the browser's language and which a save from the unsaved-changes
  question never met. `_missingRequiredFields()` is `_validate()`, which returns
  `{ key, locale, kind, label }` for the uri, the global fields, the language on
  screen and the other languages a save writes, in that order. The summary
  *Please fill in the marked required fields.* is a sentence without a list of
  names (new fills `/_admin/elements/error/field-required` and
  `/_admin/elements/label/locale-open`). `tests/admin-elements-js-smoke.js`
  holds the marks, the order of the problems, the focus and the language switch
  (101 → 180 together with the JSON entry below and the unsaved-input entry
  above; the file stops at the first new check without the change).

- **Workbench, Elements:** text that is not JSON in a list or object field no
  longer disappears, and no longer saves as an empty list. The field's text is
  parsed (`[]` for a blank field, an array or an object as such); anything else
  - a scalar, `null`, a syntax error - stops the save with *Not valid JSON - a
  list or an object is expected.* at the field, for a global field and for a
  translated one, whichever language is on screen. A translated field keeps
  what was typed when its language is left (the stored value stays, the text is
  remembered and the language counts as edited) and shows it again when the
  language comes back. A required list holding a non-empty object counts as
  filled, the way the kernel counts it. Nothing changes on the server, which
  already answered a scalar with a 400.

- **Workbench, Text Keys:** Save writes every language edited since the group
  was opened, as the Text panel does, and not only the one on screen: the other
  languages were thrown away without a word. One request per language, one
  after the other, the global keys with the first; a failed request or a key
  the server did not accept stops the loop, leaves the languages not yet
  written marked as unsaved and names the key and the language; *Saved.* comes
  once, after the last. The form is held while it runs, the rename, delete and
  schema buttons included. `tests/admin-text-js-smoke.js` (17 → 58).

- **Workbench:** leaving a form no longer loses what was typed into it. The
  Elements previous and next buttons, copying an element, and every write of an
  Element Type (create, save, delete: each drops the Elements form), the Text
  Keys schema change, rename and delete (each reloads the tab), switching a
  feature on or off or installing one, restoring a backup, ending one's own
  sessions, a log out, the interface language, the back link of every form and
  a Navigations entry action ask first (see *Added*). A copy of an element
  carried the stored values, not the typed ones, without saying so; it asks, and
  copies what is stored or saved. Visiting a language no longer marks it as
  edited when its rich-text field reads back as other markup than the stored
  string (`&nbsp;`, a quote in an `href`): it is compared with what the control
  held when it was drawn. The Maintenance panel is read again every time it is
  shown, which rebuilt its form over a switch just turned over or a *Retry-After*
  just typed; it now leaves a form with unsaved input alone. While the
  Elements form saves, its links are inert and a Discard that comes in meanwhile
  changes nothing, so the save finishes with the values it was given.
  `tests/admin-elementtypes-js-smoke.js` (36 → 48), `tests/admin-features-js-smoke.js`
  (162 → 169), `tests/admin-backups-js-smoke.js` (12 → 16) and
  `tests/admin-routes-js-smoke.js` (12 → 16) hold the guards; each stops at its
  first new check without the change.

- **Images:** the EXIF orientation of a JPEG is honoured on every upload path -
  the Images panel, element image fields and anything else that calls
  `\Nino\Images::process()` or `fit()`, the Gallery's thumbnails and large views
  included. It is read from the JPEG header without ext-exif
  (the segments are walked to the first Exif APP1,
  the TIFF header names the byte order, IFD0 is searched for tag `0x0112`; every
  read is length-checked and the walk is capped) and applied before the crop:
  the geometry is worked out on the picture as shown, the rectangle is read from
  the stored pixels with its sides swapped for 5-8, and only the small target
  canvas is rotated or flipped, so the 20 MP / 128M budget holds. A photograph
  stored on its side is no longer cut as a landscape. `\Nino\Images::size()` is
  new (`width`, `height` as shown, `type`, `orientation`); the `RENDER` payload's
  `source` gains `orientation` (1-8, a payload without it means 1) - a handler
  that renders itself has to apply it. Pictures uploaded earlier stay as they
  were stored and have to be uploaded again. `tests/kernel-smoke.php` builds a
  four-quadrant picture for each orientation, in both byte orders, and checks
  `process()` to two shapes and `fit()` against the upright result, the header
  reader against malformed blocks, that nothing raises a warning, and that
  `Images.php` calls no `exif_` function.

- **Images:** an alt text can no longer carry a live fill or shortcode. A
  stored text, or one in a template, with `[[/key]]` or `[template ...]` in it
  was rendered again after the `<img>` was built, because shortcode output is
  rendered once more; the brackets are neutralised now.

- **Text:** a key that holds a `<br>` keeps it. `/mail/user/closing` and
  `/mail/newsletter/closing` ship that way, and `containsHtml()` never saw a
  break: the Text panel showed a plain textarea with a literal `<br>` in it and
  the next save stripped it, answering `you.Kind regards,`. A key without a
  stored format takes it from its values, so those two are edited as line breaks
  and save as such. The wizard's, the Translations import's and the Templates
  feature's writes through `Text::saveBatch()` follow the same format.

- **Workbench:** Back and Forward walk the workbench, the rail's entries are real
  links, a deep link survives the login. The rail links are `href="#<panel>"`:
  Ctrl-click, a middle click or Shift-click open the panel in a new tab or window,
  a copied link goes to it, and only a plain click is the script's. A move the person
  makes adds a history entry (`pushState`) - a rail click, a tab, the phone's
  select, a row or a "new" button opened in Elements, Images, Text, Users and
  Roles, their back links, the previous and next element - and what only keeps
  the address true to the screen replaces the one it is on: the page load, a step
  through the history, the arrow keys of a tab strip, a panel showing its level
  again, the address a new element gets when it is saved. `router.go()` is the
  explicit push; `router.set()` pushes only while the shell flags the switch it
  is in as the person's (a one-shot set by `selectTab( panel, tab, true )`),
  which keeps a panel that writes its level while it is shown - the Dashboard,
  every drill-down panel - to one entry instead of overwriting the one before.
  The five drill-down panels follow the hash when it names them
  (`showCurrent()`): the picker, a type's list or one element's form; a category
  or an account or a role or the form for a new one; an unknown target is the
  top level, and a hash that names another panel (a click on the rail) keeps the
  level in memory as before. Leaving an open form that way asks Save / Discard /
  Cancel like its back link (`router.leave()` through `Nino.admin.dirty.guard()`),
  and a Cancel - or a Save that fails, which the shell answers by bringing the
  form on screen - writes the level on screen back into the address. Elements keeps
  the types the picker drew to open one from the hash, and loads another type's
  list the way a reload does. This also makes the Dashboard's tiles
  (`#elements/<type>`) work for an Elements panel that is already loaded, and
  so do the Template Builder's links into `#images/<group>` and
  `#elements/<type>` where they resolve in the same document. After the
  login the page loads again where it stands (`Nino.auth.login( user, pw, null )`
  reloads; a string still redirects) - `location.replace( '/_admin' + hash )`
  would be a navigation inside the same document that requests nothing and leaves
  the login form on screen - so the `#hash` and `?locale=` of the address survive,
  and there is no redirect parameter to guard. The interface-language pickers of
  the workbench and of the login screen keep the hash. `script.js` keeps
  `location.hash.replace` and uses no `location.search` (the Templates feature's
  test reads it). `tests/admin-script-js-smoke.js` (125 → 148 checks) holds the
  entries (one per rail click and tab click, none for the load, the arrow keys, a
  hashchange or a modifier or middle click), a Dashboard-like panel and the
  language picker; `tests/admin-router-js-smoke.js` (new, 50) the router and the
  follow of Text, Users and Roles and the back links; `tests/admin-elements-js-smoke.js`
  (203 → 226) and `tests/admin-images-js-smoke.js` (40 → 49) theirs;
  `tests/admin-login-js-smoke.js` (30 → 34) the reload and the picker;
  `tests/nino-auth-js-smoke.js` (26 → 29) `login()` with `null`;
  `tests/admin-system-smoke.php` the links as `href="#<panel>"`.

- **Maintenance:** one can sign in while the site shows the maintenance page.
  `POST /.nino/auth/login` was answered with the 503 like every other route, so
  an operator who was not signed in yet got the maintenance page instead of a
  session - from the workbench's login form and from its re-login dialog.
  That one POST is let through (`Modules\Maintenance::_prepare()`); a `GET` of
  the address, the logout and every other route stay as they were.

- **Forms:** the `.nino-form` script refuses what the server would. A required
  checkbox that is not ticked is a missing field (its `.value` is what it would
  send, ticked or not, so the length test let it through), and the browser's
  `badInput` of a date, a number or a url is asked whatever `.value` holds - a
  half-typed date or letters in a number field read as `''` and were sent as
  no answer. A `400`
  on a form with a checkbox, a radio or a date says *check your entries* rather
  than naming the address. `Form::normalize()` cut a label, an option, a form
  name and a subject at a byte count, which left invalid utf-8 for a long
  non-ascii text (a German label of 241 bytes); it now cuts at the same bound on
  a character boundary, as `posted()` does.

- **Cache:** a cached page keeps the policy its features widened. A hit never
  renders, so a module that adds a source to the `Content-Security-Policy`
  for what a page uses (a feature's output callback on `/nino/http/output`,
  such as the host of an embedded frame) did not run for it, and the page was
  served under the narrower default policy - and the embed or script was
  blocked - for as long as the entry lived. `Modules\Cache` now stores the
  policy the page was sent with beside its body, the `[jstext]` nonce held as
  the same marker the body uses, and puts it back on a hit with that
  request's own nonce, so the nonce still never outlives its response. This
  needs the callback to run after a feature's own:
  `Modules\Cache::callbackOutput()` is registered at priority 9 (it was 5, and
  kernel modules initialise before features, so it ran before a feature's own
  and would have stored the unwidened policy). An entry stored before has no policy and keeps the header
  the response callbacks composed, as it did. The Callback Reference lists
  `/nino/http/output`, which it did not (only the Cache row named it).
  Upgrade note for third-party modules: a project's own `/nino/http/output`
  callback at the default priority now runs before the store, so what it writes
  into the body or the policy is cached and served until the entry expires;
  before, Cache was registered first at 5 and stored ahead of it.
  `tests/kernel-smoke.php` holds the registration, a policy widened by a
  callback at the default priority, registered after Cache's own as a
  feature's is, coming out of the entry on a hit with the hit's nonce, and an
  entry without a policy (1039 → 1047 checks).

- **Leftovers of the review of the first patches:**
  - *Wizard:* the name, title and description of a page the Webpages step
    writes go through the same plain-text filter as in the Routes panel -
    markup stripped, a quote written as an entity - where `<script>` in a
    name or a quote in a title reached the page head unfiltered.
  - *Text:* `\Nino\Text::setBlacklisted()` answers whether the list is as
    asked (`bool`, it was `void`); `routes/save` answers `500` when the key of
    the page's address could not be put on the blacklist. A directory called
    `x.tpl` among the templates is no read of a directory any more in the
    key scan (`Keys`) and in the Images panel's scans.
  - *Images, slots:* `slots/save`, `slots/create` and `slots/delete` change
    their one slot in `config.php` as it is now, through `Filesystem::mutate()`,
    where each wrote the whole `/nino/html/images` key from the copy the
    request booted with and took back an alt text or a file saved since.
    A delete removes the file the slot names in `config.php` now.
  - *Admin events:* `/nino/admin/action` for `features/settings` carries the
    fields of the feature's manifest typed `secret` blank (`data.fields.<name>`);
    the Callback Reference says so, in English and German.
  - *Cache:* a stored policy with a nonce is not sent on a hit by a request
    that has none - it would have carried `'nonce-@@...@@'`; the composed
    header stands then, as for an entry without a policy.
  - *Maintenance:* the banner goes after the whole opening `<body>` tag also
    where an attribute value holds a `>`.
  - *Nino.ui.js:* a required checkbox of a `.nino-newsletter-form` counts as
    missing when it is not ticked, as in `.nino-form`.
  - *Workbench:* `discard()` of the Text panel and of the Keys tab leaves the
    model alone while a save runs (the Elements panel's rule). Refreshing the
    catalogue in the Features panel draws the list and the status line, and
    the open feature's screen only where no input is typed into it. The
    "unsaved" marker of the Users panel goes to the action bar of the form on
    screen: `Nino.admin.dirty.watchForm( name, formGetter, save, bar )` takes
    an optional fourth argument for it. The Elements language switch moves the
    selection in one breath with the fields. On a phone the marker is a row
    of its own above the buttons, so that it never runs under *Save* in a bar
    of three. On the desktop rail the first heading loses its top margin also
    where the phone's select stands in front of it.
  - *Workbench, history:* the entries the shell writes are numbered
    (`history.state.nino`), so a step through Back or Forward has a direction
    and a length. A step that is refused - *Cancel*, a *Save* that failed, a
    save that is running - is taken back with `history.go()` and the
    traversal it causes is ignored, where the form's address was written over
    the entry the browser had just stepped to. `Nino.admin.router.refuse()`
    is the call for a panel that will not follow. An address typed by hand has
    no number: refusing it keeps the old way. A late `elements/get` or
    `elements/list` answer no longer draws a form or a list after Back
    (`_follow()` retires the requests still on their way).
  - *Images, groups:* a group is named by the vocabulary
    (`Nino.adminUi.slugLabel()` of its category: "Page home" for
    `template/page-home`) and written into the address the way the Text panel
    writes a row, `#images/template/page-home`, not as `%2F`; the old form
    still opens it.
  - *Left as it is, on purpose:* the Keys tab already names the group of page
    details neutrally ("Seitenangaben") where it has no list of pages; and
    `_pages()` of the Text panel keeps listing a route to a template whose
    name has a dot - the demo catalogue's is `.demo-catalogue`, and it is a
    page.
  - *Tests:* the `Sie`/`du` scans of `tests/install-smoke.php` (two checks)
    and of `tests/legal-smoke.php` (one check) are gone, see Removed. New or
    changed checks, measured: `kernel-smoke` 1098 -> 1104, `admin-system-smoke`
    1051 -> 1061, `install-smoke` 324 -> 323 (2 gone, 1 new), `features-smoke`
    259 -> 262, `legal-smoke` 111 -> 110, `admin-elements-js-smoke` 235 -> 243,
    `admin-features-js-smoke` 192 -> 197, `admin-images-js-smoke` 50 -> 58,
    `admin-router-js-smoke` 60 -> 63, `admin-script-js-smoke` 151 -> 172,
    `admin-text-js-smoke` 244 -> 247, `admin-users-js-smoke` 24 -> 28,
    `nino-ui-form-js-smoke` 52 -> 57.

### Removed

- **The guard tests against `Sie` and lowercase `du`** in
  `tests/install-smoke.php` (the scan over the workbench's, the modules' and
  the library's German texts, and the one over the page units' proposed
  names) and `tests/legal-smoke.php` (the German texts of the legal pages).
  The texts are as they were; concrete expectations ("Wähle Deine Sprache")
  stay.

- **The typed permission field of the Roles form** and its four `custom-*`
  text fills, with the `.admin-perm-add` rules: a finer permission is picked
  from the panels' tree now (see Added).

- **The THATSNINO logo and the photograph `demo.jpg`.** The base unit no longer
  ships `images/logo.png` and `images/logo-invert.png` and, with no file left,
  no `images` directory: the logo is the slot `/logo` (see Changed). The home
  unit no longer ships `images/demo.jpg` (2 MB); its hero is
  seeded with a placeholder SVG of Nino's own. A feature whose frames or mails
  name `images/logo.png` or `images/logo-invert.png` literally has to ask the
  slot too, or show a missing image on a new project.

- **The base unit's cookie banner.** The `<div class="nino-cookie-banner">`
  block in `html-footer.tpl`, its four texts `/cookiebanner/info/text`,
  `/cookiebanner/label/legal`, `/cookiebanner/label/decline` and
  `/cookiebanner/label/accept`, the `.nino-cookie-banner` rules in
  `Nino.css`, the code in `Nino.ui.js` that revealed it and the public
  `Nino.ui.cookieConsent` are gone, with the three class names on the demo
  catalogue's page. It was a plain two-button notice that stored one
  cookie; the [Consent
  feature](https://github.com/dapeio/nino-features/blob/main/features/Consent/README.md)
  replaces it and answers the question properly - its API is
  `document.documentElement.dataset.consent` and the `nino:consent` event.
  `Nino.cookie` stays. **Upgrading:** a project set up with 1.3.2 or
  earlier keeps the block in its own `templates/html-footer.tpl`, and
  `Nino.css` and `Nino.ui.js` are replaced with the kernel - without the
  Consent feature the block would show unstyled, permanently visible, with
  buttons that do nothing. Delete the `<div class="nino-cookie-banner"
  id="cookie-banner">` block from `templates/html-footer.tpl` (or switch the
  Consent feature on, which removes it by itself and for now still reads the old
  `accepted` and `declined` values), and replace a call to
  `Nino.ui.cookieConsent` in a project script with the Consent API; the four
  texts in `text/*.php` can go as well. `tests/install-smoke.php` holds that
  the installed footer carries no banner and the picked locale no
  `/cookiebanner/` key (271 → 280 checks with the password checks and the
  German check above: 7 of the 9 new ones were red before, and so was the
  changed "Wähle Deine Sprache" expectation); `tests/nino-ui-scroll-js-smoke.js` that
  `Nino.ui.cookieConsent` is undefined and neither script nor stylesheet
  mention a banner (13 → 15, 2 red).

- **Dead keys and the proposals in text files.** `/form/title` and
  `/form/info/welcome`, which nothing read (the welcome text was published to
  every page by `Jstext` all the same), the Form unit's `text/global.php` and
  its blacklist, the eight pages' `/webpage/<folder>/{uri,name,title,description}`
  proposals and the comment on top of each text file that explained them, the
  text files of the `blank` and `legal` units, which held nothing else,
  `Webpages::_withoutWebpageMeta()`, `PersonalInfos::KEY_BLACKLIST` and
  `Keys::isValidKey()`.

- **Workbench, Text Keys:** the one-line key field of the new-key form and the
  inline rename, `Keys._renameKey`; the form with its segments does both.
  `Text._groupEntries`, `_preview` and `_groupDescr` are gone with the list
  they built.

- **Workbench, Elements:** the sixteen demo labels `/_admin/elements/field/*`
  mentioned above.

- **Page unit `legal`:** `_admin/install/library/pages/legal/` with
  `page-legal.<locale>.tpl`, `html-footer-legal.tpl`, the footer link
  `/website/legal/{uri,name}` and `Setup::_applyLegalLink()`. The Legal module
  and the menu `legal` replace them. A project that has the old page keeps its
  files and routes: nothing is migrated, and nothing was published that needs it.

## v1.3.2 - 2026-10-01

### Added

- **Docs:** `THIRD-PARTY-NOTICES.md`, the notice for the one piece of
  third-party work Nino ships. The workbench's pages, the install wizard and
  every panel's `icon()` draw Lucide's icons, which are ISC-licensed, and
  the ones Lucide took over from Feather are MIT-licensed as well; both
  licenses ask for their notice in every copy, and no file carried it. Both
  READMEs name the file under their license.

- **Elements:** `\Nino\Elements::prevElement( $appData, $elementUri, $locale,
  $return, $options )` and `nextElement()`, the element before and after
  one in the order its type lists them - the type file's own order, which
  is what the Elements panel and an `[elements]` block without `sort`
  show - or in the caller's `sort` and `query` under `$options`, resolved
  for `$locale` like `getElement()`, and `$return` past either end, for a
  uri outside that list and for one that is no element uri. A detail
  page's "previous" and "next" walk the list its visitor came from.
  `tests/kernel-smoke.php` holds the order, both ends, the locale the
  neighbour is resolved for and the list it is taken from, `sort`, `query`,
  a uri's slash spellings and the quiet answers (773 → 783 checks, 1 red
  before: the methods did not exist, so the rest of the block is skipped).

- **Elements:** the element form steps through its type's entries.
  **‹ Previous element** and **Next element ›** sit at the right of the
  form's context bar, in the order of the list and disabled at either
  end, and follow the list the server sends back after every save - so a
  just created element has its neighbours without a detour over the
  list. Like the back link, they do not save. `tests/admin-elements-js-smoke.js`
  holds the neighbours of the first, a middle, the last, an unknown and a
  new element, the buttons' targets and clicks, that the form renders them
  into its context bar and a new element's form does not, that a fresh
  list re-points them, and that a save in flight disables both and hands
  only the reachable one back: 62 → 81 checks, 4 red without the change
  (the nav block is skipped where the module has none).

- **Element Types:** `inputsize` on a string field, the number of rows its
  input opens with - the textarea's rows, or the rich-text area's minimum
  height in lines (`--nino-admin-richtext-rows`, turned into a height by
  the stylesheet, with the plain `3rem` kept for a browser without `lh`).
  The field editor offers it next to maxlength, `Types::cleanModel()` keeps
  it for string fields only, and it is a hint for the form rather than a
  limit on the value. `tests/admin-system-smoke.php` holds that a posted
  size survives on a string field and is dropped on a double (683 → 685
  checks, 1 red before); `tests/admin-elementtypes-js-smoke.js` that the
  editor forwards it (32 → 33, 1 red); `tests/admin-elements-js-smoke.js`
  that the textarea takes it as rows and the html editor as its height
  (part of the count above).

- **Tests:** the shape a panel's `icon()` is held to, which nothing
  measured. `Panels::_entry()` documents it as "an inline svg and nothing
  that runs" and drops anything else, so the rail falls back to the label's
  initial - what a panel without an icon gets. `tests/admin-system-smoke.php`
  hands one panel four icons in turn and holds that every one that reaches
  the rail is an inline svg with no `<script>` and no `on...=` handler, and
  that the harmless one is not dropped, so the check cannot pass by refusing
  everything. Proven by replacing the guard's condition with `false`: 680
  checks, 1 failed.

- **Tests:** the csrf guard's header path was measured against a header
  array the test wrote itself. `\Nino\Csrf::_extractToken()` documents
  that it reads `X-CSRF-Token` off the already normalized request header,
  "the one every other header read in the kernel already goes through" -
  and `\Nino\Http::filterHeaderFields()` keeps an allowlist that drops a
  name missing from it in silence. Taking `'X-CSRF-Token'` out of that
  list left all seven suites green while the documented path was dead for
  every real client. `tests/kernel-smoke.php` now sends the header the way
  a client does, as `HTTP_X_CSRF_TOKEN` through `\Nino\Http::request()`,
  and holds that it arrives and is compared rather than trusted.

- **Tests:** `Accounts::usableUsers()` documents two ways an entry under
  `/nino/auth/user` is an array key rather than an account - it is
  disabled, or it carries no password - and only the first was measured.
  The shipped placeholder the existing check uses has `status => 0` and
  a real hash, so an enabled entry with `pw => ''` would have satisfied
  the wizard's "at least one admin" precondition and let `Finish` lock
  the installer over a project nobody can sign in to. One check in
  `tests/install-smoke.php` holds both halves now, through the list and
  through the precondition; dropping the password half of the condition
  in `usableUsers()` turns it red.

### Changed

- **Install:** the Personal Information step asks for the website's
  address. `/website/url` - the domain without the protocol, which the base
  unit's templates put behind `https://` for the canonical link, the Open
  Graph and Twitter tags, the JSON-LD block, `sitemap.xml` and `robots.txt` -
  is off the base unit's blacklist: the step shows it first, and the Text
  panel lists it with the other `/website/*` keys. The two keys the step
  still leaves out, `/website/charset` and `/website/lang`, are the step's
  own `KEY_BLACKLIST`. The setup manual and its German twin list the address
  with the keys the step shows and say what it feeds; they still counted it
  among the technical keys left out.

- **Docs:** AGENTS.md's test matrix names the check a change needs that a
  catalogue feature reads - the demo catalogue page and its
  `data-demo-preset` marks, `Nino.css`, the base unit's templates and keys,
  the feature contract: the catalogue's own `bin/check.sh` against this
  checkout, before the push. CI's `features` job runs the same tests, but
  after it, and that is where the marks the demo catalogue gave the list, the
  table and the accordion met them: `static-*` on this side, `items-*` in the
  Templates feature still, three red checks in `demo-catalogue-smoke.php`.

- **CI:** a checkout without `app/.htaccess` or `features/.htaccess` is told
  so first. Each is the one file its directory holds, git keeps no empty
  directory, and PHPStan - `phpstan.neon` analyses both directories - ended
  the job with "Path … does not exist" before `tests/kernel-smoke.php`, which
  holds the four denied directories to their `.htaccess`, got to name the
  file - a run in September ended that way, and `features/.htaccess` went
  missing once more on 22 September. A step before PHPStan names the missing
  file now.

- **Tests:** `tests/admin-system-smoke.php` reads the tab bar with the
  `nino-admin-tabs--panel` modifier the panel's strip carries since 1.3.1, as
  `tests/admin-smoke.php` did in 1.3.1 already. The tag's own CI run failed
  on that one check.

- **Elements:** a type's entries are a table, not a list of titles. One
  column per field a cell can show - `elements/list` has answered those
  `columns` and every element's `values` for one translation since
  1.0.0-beta, and nothing drew them - the uri first, the cells in the
  translation the workbench is set to and empty where an entry has none
  yet, with the shared table's search, type-aware sort and pages. A row
  opens the form; a type none of whose fields fits a cell keeps the plain
  list. The form's locale switch moves the workbench's content locale, so
  the back link reads the list again when it did. `tests/admin-smoke.php`
  pins the answer the table reads (the columns in model order, the
  translation asked, an entry without that translation still listed with
  its cell empty: 242 → 247 checks; proven by mutation, see the patch),
  `tests/admin-elements-js-smoke.js` that the panel draws the table from
  it, the uri under `.uri`, a row into the form, and the plain list where
  there is no column (81 → 87, 5 red before).

- **Admin:** the demo catalogue marks its static-block specimens by the
  presets' new names. The catalogue's `.demo-catalogue.tpl` carries one
  `data-demo-preset`/`data-demo-layout` mark per specimen so the Template
  Builder's `demo-catalogue-smoke.php` can hold that every preset and every
  layout of the library is shown; the feature renamed `items-list`,
  `items-table` and `items-accordion` to `static-list`, `static-table` and
  `static-accordion`, and the ten marks that named them follow. Marks only:
  the sections themselves are what they were.

- **Docs:** the panel recipe's skeleton still drew a heading over its list -
  the one thing the pane's head does for every panel since the entry below,
  so a panel written from the skeleton would have said its name twice. The
  list screen draws none now and a comment says why; the form a level
  below keeps its own, under its context bar, like every form in the
  workbench. Found by the pass that took the catalogue's panels to the
  head.

- **Admin:** every panel but the Dashboard opens with the same head. The
  panes used to open however their panel began: a tab strip and nothing
  else where the registry had tabs, the first row of a list where it had
  none, a title the panel's own script drew where a feature thought of one -
  Design's, Search's, Mailer's - and the Features panel's own strip in a row
  of its own; twenty-three screens and no two of them telling where you are
  the same way. `Panels::panesHtml()` now renders one row into every pane
  from `Panels::$html`, like the rest of the shell: the panel's label as the
  `<h2>` of the screen, the same label the rail shows through the same
  escape, the registry's tab strip beside it, an actions slot at the end.
  The workbench's bar surface, edge to edge, so the screens read as one
  tool. The shell script looks for a strip in the head and nowhere else. A
  panel whose tabs are its own puts its strip beside the name through
  `Nino.adminUi.panelHead( mount ).tabs( strip )` - the Features panel does,
  its row keeps the filter and the category - and one with buttons over its
  screen appends them to the slot; `head()` in the panel contract, `false`,
  drops the row, which the Dashboard answers because the tiles have no
  single subject. A workspace pane has no padding for the head to bleed
  through, and the rule that says so has to stand in the workbench layer:
  a later layer wins whatever the selector. `tests/admin-smoke.php`,
  `tests/admin-system-smoke.php`, `tests/admin-script-js-smoke.js`,
  `tests/admin-lists-js-smoke.js` and `tests/admin-features-js-smoke.js`
  hold the head, the exception, the fragment, the helper and the layer;
  against the kernel before this change: 3, 6, 3 failed and two suites
  that die on the strip they do not find.

- **Admin:** the white surfaces stand off the page. The light scheme's
  page colour moves a shade further from white (`#edf0f5` where it was
  `#f5f7fb`), the border every card and field draws goes from 13% to 19%
  ink and the separator from 9% to 11%, and the two shadows gain a little
  depth - the cards, the rail and the bars are white, and on a page that
  close to white their edge measured 1.3:1, which is where "the white
  areas need more contrast" came from. The dark scheme is as it was.

- **Docs:** what three passes left between their areas. Three comments in
  `\Nino\Auth` and the Users panel's docblock said sessions and
  permissions are "a developer-only, direct-json task", from before the
  panel could assign a role (`Auth::setRole()`) and end every session of
  an account (`Auth::logoutAllSessions()`, `users/logoutall`); status and
  a permission held beside a role still are, and the sentences say which.
  The Navigation panel's script sent the reader to `pages.js` and
  `elementtypes.js`, the Form panel's called itself `editor.js` and its
  neighbour `logs.js` - the Routes panel's `admin.js`, the Elements panel's
  `types.js`, `admin.js` and the Log panel's `admin.js`. And
  `tests/install-smoke.php` named `setDevPassword()` three times, a method
  that is `setRecoverySecret()`, and "a Design module, because there is
  none" where the Design feature is a catalogue feature the Features panel
  installs.

- **Docs:** AGENTS.md's "Two traps" in section 6a has held three of them
  since restating a shared component in a panel was added to the list.

- **Docs:** the panel recipe listed ten of the eleven workbench panels -
  Features was missing, from the list and from the system weights - left
  Maintenance out of the module panels' weights, described a pane's tab
  strip without the `.nino-admin-tabs--panel` the registry adds to it, and
  built its skeleton's buttons with `nino-btn nino-btn--primary`, the public
  site's button class, which no workbench stylesheet declares. The skeleton
  uses `.nino-admin-btn-primary` now, which is what the table above it names
  and what every shipped panel uses.

- **Docs:** four things in `docs/_admin.md` and its German twin were not
  what the tool does. The Maintenance panel - a kernel module's panel like
  Submissions and Navigations, with its own `/_admin/maintenance/manage` -
  was missing from the group table, from the permission table and from the
  sentence that lists the optional kernel modules, although the manual
  documents the panel itself further down; the dashboard's tile list left
  out the active features and the maintenance tile; the wizard has six
  steps, not ten (`Nino.install.STEPS`, and `docs/setup.md` says six); and
  the recovery password could be set with `php _admin/Admin.php <password>`,
  which prints nothing at all - that file declares classes and has no cli
  entry of any kind. The manual writes the stub `Recovery::hash()` reads
  instead.

- **Docs:** the roles picker's docblock put a role's permissions in "three
  group boxes". It groups by the rail's four nav groups plus the "other" box
  for what no panel offers, as `_groupName()` two screens below already
  says - so it names no number now.

- **Docs:** the Image Slots tab's script described itself as the "Images"
  module and carried a half-replaced sentence from the rename ("what the
  Images panel Images panel edits", "types.js/the Elements panel Elements"),
  and it named a `Dev\Images` class for the scan its own `Slots::apiScan()`
  answers. The Images panel's grouping comment named the same `Dev`-era
  `Text` class for a function that is the Text panel's `_groupEntries()`.

- **Docs:** the Log panel's script named an `Admin.php`'s "Logs class" and
  an `_admin/Editor.php` for the half that writes the lines it lists. Both
  are `\Nino\Modules\Logs\Admin::record()`, in the `Admin/Admin.php` beside
  it.

- **Docs:** five comments of the Dashboard panel named files, classes and
  rules that are not there: an `_admin/Editor.php` holding a `Dashboard`
  class, a `logs.js` for what is the Log panel's `assets/admin.js`,
  `\Nino\Panels` for the registry, which is `\Nino\Admin\Panels`, and a
  stylesheet header promising "two dashboard tiles" in a file that holds the
  panel's two lists and the "show all" link under them.

- **Docs:** the Config panel's script sent the reader to a `language.js` and
  a `pages.js` for the settings that moved out of its form. The languages
  are the Language panel's own `admin.js`, the routes the Routes panel's;
  neither file name exists. Its `nav()` answered "[ uri, label ]" into "the
  dashboard's tab bar" as well.

- **Docs:** three comments of the Backups panel named a `Restore` class in
  `_admin/Admin.php`. That class has no name in the repository any more -
  `apiRestore()` and `_safetySnapshot()` are `\Nino\Modules\Backups\Admin`'s,
  in `Admin/Admin.php` beside the engine - and the panel's script still
  called itself the "Restore" module. Its `nav()` answered "[ uri, label ]"
  into "the dashboard's tab bar" as well.

- **Docs:** the Routes panel was still the "Pages" module of a `Routes.php`
  in seven comments. There is no `Routes.php` and no `Routes` class: the
  panel is `\Nino\Modules\Routes\Admin` in `Admin/Admin.php`, which is where
  `apiMove()`, `apiSave()` and `_templateFromBody()` are read. Its class
  docblock also explained its independence with "the same standalone-folder
  reasoning every other class in this file follows", of which there is none
  left - one panel is one directory now - its `nav()` still answered
  "[ uri, label ]" into "the dashboard's tab bar" rather than
  "[ uri, label, weight, group ]" into the rail, and its list shape follows
  the Image Slots tab's `slots.js`, not an `images.js`.

- **Docs:** five comments of the Text panel and its Text Keys tab named
  files and methods from the tool's two-file days: an `_admin/assets/text.js`
  and a `text.js` where the panel's script is `assets/admin.js` and the tab's
  `assets/keys.js`, a `Dev\Text` class for what is `\Nino\Modules\Text\Keys`,
  and a `Text::_entries()` for `\Nino\Text::entries()`, which is what the
  entries in that parameter come from.

- **Docs:** nineteen comments of the Elements panel named a class, a file or
  a method that is somewhere else. `AUTOINCREMENT_PAD`, `_writeElementData()`,
  `insertElement()` and `updateElement()` are `\Nino\Elements`', not the
  panel's own `Admin`'s; `process()` is `\Nino\Images`'; `apiSave()` and
  `_convertShape()`, the text-key migration the type editor copies, are
  `\Nino\Modules\Text\Keys`'; `cleanModel()` and `_unknownReferencedType()`
  live in `Types.php`, not in `Admin.php`; the Types tab's script is
  `assets/types.js`, not `assets/elementtypes.js`; `typeDescr()` is read from
  `Admin.php`, not from an `Editor.php`; the reorder pair the type editor
  copies is the Routes list's `admin.js`, not a `pages.js`; the panel answers
  `elements/*` actions of the one POST /_admin route rather than "the
  /_admin/elements/* routes"; and its script still called element types
  "developer-only, not exposed here" although the Types tab of its own pane
  creates, saves and deletes one - the same sentence `## 1.3.0` corrected in
  the panel class.

- **Docs:** ten comments in `_admin/assets/` described an older workbench.
  The hash router named "Elements/Text/Users" and "all three panels", where
  six panels persist their drill-down level in it; `exportCsv()` sent the
  reader to the Form and Newsletter panels' `assets/editor.js`, which is
  `assets/admin.js`; `onReady()` still wired up "the user/text/elements
  panels" rather than whatever the registry rendered; the table model's
  `FIELD_TYPES` lives in `Types.php`, not in `Admin.php`; three docblocks of
  the design system explained their "owns no strings" rule with a `/_admin`
  that is English and a localized tool beside it, from the days of two
  tools; `numberField()` named two of its three callers; and
  `setStateClass()` counted three shells where the workbench's and the
  wizard's are the two that call it. The stylesheet's vocabulary index
  gained `.nino-admin-tabs--panel`, the modifier the panel tab strip has
  carried since it stopped taking its exception from a class outside the
  design system.

- **Docs:** five docblocks of `_admin/Admin.php` described a shape the file
  does not have. The panel contract was "two required and six optional
  static methods" above a list of ten of them; `Admin::panels()` answered a
  registry entry of nine keys where `Panels::_entry()` builds seven more -
  `tab`, `template`, `layout`, `icon`, `tabs`, `parent` and `own` - and now
  points at `collect()`, which is where the shape is written out;
  `Recovery::handlePost()` dispatches five actions, not "four ... the other
  three need it open", since `recovery/reset` is the fifth; `Recovery::set()`
  named recovery.php as a second caller, which offers no way to change the
  secret at all; and the encryption key the recovery hash compares itself to
  is `\Nino\Modules\Backups\Admin::_key()`, not a `Backup::_key()`.

- **Docs:** `Modules\Elements::_escapeFieldValue()`'s docblock described
  one of its two parameters and one of its two branches. The method gained
  `$isHtml` when rich element fields did, and takes
  `\Nino\Html::sanitizeHtml()` for one - which is what the whole
  `'html' => true` model flag is - while the docblock still read as though
  every value went through `htmlspecialchars()`. Both are documented now.

- **Docs:** two more comments named the wrong writer. `\Nino\Runtime`
  compared its own log file to "Modules\Form's forms.<Y-m>.php", which
  `\Nino\Form::record()` writes - `Modules\Form` owns the route and
  nothing else since the engine moved. And `\Nino\Modules\Jstext` sent
  the reader to `Nino.ui.js`'s ".nino-newsletter" handler for one of its
  three shipped key prefixes; the class is `.nino-newsletter-form`, which
  is what the handler selects and what the shipped markup carries.

- **Docs:** `AGENTS.md`'s source references sent an agent looking for an
  element file to
  `_admin/install/library/pages/.demo-elements/demo-services.php`. There is
  no such page unit and no such file - the install library's only hidden
  unit is `.demo-catalogue`, which ships templates and images and no
  elements. The row names the complete type file in the element recipe,
  and says where the suites build theirs.

- **Docs:** the runtime-module recipe pointed at recipes by number - "(7.)",
  "(9.)", "(recipe 7)" - the way `feature.md` points at its own numbered
  sections. This recipe has no numbered sections, and recipe 7 of the guide
  is "Package a feature", not the panel recipe it meant. All three name the
  recipe they mean, the way the same file already does two paragraphs
  further down. Its autoload list also left `Maintenance` out of the
  optional modules a project switches on or off in `/nino/modules`, which
  it names itself a page later.

- **Docs:** the Concepts manual and its German twin still laid a project
  out the way it was before the `private/` and `public/` split. "Location
  in the configured project" named `config.php`, `text/`, `elements/`,
  `templates/` and `data/` at the project root, and put `images/` and
  `assets/` in one row - where `images/` is served and `assets/` holds the
  sources of a bundle and must never be. The table names both halves now,
  and the sentence above it says the short forms used in the code are the
  virtual paths `\Nino\Filesystem` resolves into one of them.

- **Docs:** five things the developer manual and its German twin said
  about a kernel that has moved on. The boot order left out
  `Html::init()`, which stands between `Csrf::init()` and `Auth::init()`
  and registers the one shortcode the kernel owns itself. The assets
  chapter said the bundle replaces "only" `[[/nino/dir]]`, where
  `Modules\Assets::_createCachefile()` replaces `[[/nino/public]]` beside
  it - without which the base unit's `theme.css` would ship the literal in
  its three `@font-face` urls. The module table had no `Cache` row at all,
  although the module is one of the seven always-on ones, and its
  `Elements` row named two of that module's three shortcodes.
  "Important Kernel APIs" had no `Form` row, although `\Nino\Form` is
  where the form engine has lived since `Modules\Form` became the route
  and nothing else. And the panel reference called
  `features/Search/Admin/Admin.php` a shipped module: a checkout ships no
  feature, so the smallest complete panel in it is the Sample fixture.

- **Docs:** `Nino.css` still spoke of "the Design module" in three places
  and offered `assets/style.theme01.css` as the example of a project's own
  stylesheet. Design is a catalogue feature - every other file here says
  so - and a project's stylesheets are `assets/theme.css` and the
  `assets/style.css` the base unit ships empty after it; `style.theme01.css`
  is a name from the wizard's whole-page themes, which are gone.

- **Docs:** `\Nino\Images::getSlots()` said image slots "can't be added or
  removed from the admin, only the file each currently points to changes".
  The Image Slots tab of the Images panel has created, edited and deleted
  one since it exists - `\Nino\Modules\Images\Slots`, with its own
  `slots/create` and `slots/delete` actions. The comment names the split
  the two panes really make, the same one Element Types and Elements have.

- **Docs:** `\Nino\Mail`'s class comment still said a rate-limited burst
  "just becomes silently-missing mail". It has not since the cap grew a
  flag of its own: `send()` and `sendAll()` set
  `'./nino/mail/ratelimited'`, and `\Nino\Form::handle()` answers such a
  submission 429 and records nothing - the line 30 lines below it in the
  same file already said so. The class comment says it too now.

- **Docs:** `\Nino\Modules\Cache`'s own docblock listed what the page
  cache never stores, and the list was both wider and shorter than the
  code. Wider: it kept "any uri under /_", where `TOOL_PREFIXES` holds
  `/_admin` alone, so a project's own `/_something` is cacheable.
  Shorter: `_cacheable()` also refuses a page whose own route has a
  response callback registered for it - answering from the cache would
  never call it - and everything a wildcard route answers, which is the
  rule that keeps an anonymous client from growing `private/data/` one
  entry per invented address. The `_admin` manual described both
  already; the class now does too.

- **Docs:** `\Nino\Modules\Maintenance` said it is listed in
  `/nino/modules` "the same way Design and Templates are". Both moved to
  the catalogue and left `\Nino\Install\Setup::TOOL_MODULES` holding this
  one class, so the comparison named two modules a checkout does not have.
  The docblock says what that list holds now, and why this module is its
  one entry.

- **Docs:** seven kernel comments sent the reader somewhere there is
  nothing. `\Nino\Backup::manifest()` named `Backup::maybeRun()`, a method
  of `\Nino\Modules\Backups`; `\Nino\RotatingLog` credited its two
  workbench callers as `Admin\Logs` and `Admin\Backup`, which are
  `\Nino\Modules\Logs\Admin` and `\Nino\Modules\Backups`;
  `\Nino\Form::prune()` named the first of those the same way;
  `\Nino\Text` put the two editors it was split out of in
  "Admin.php/Admin.php", where one of them is `Text/Keys/Keys.php`;
  `\Nino\Elements` pointed at a `cleanModel()` in `_admin/Admin.php`,
  which is the Element Types tab's, and called `deleteElement()` - the
  method above it - the one below; and `_nino/Nino.php` said the same of
  `init()`. Every one of them now names what is there, and no behaviour
  changed.

- **Docs:** the agent guide's repository map left one kernel class out
  and its test matrix excluded the wrong directory. Section 4 lists
  nineteen classes under `_nino/Nino/<Class>/<Class>.php` and
  `_nino/Nino/Form/Form.php` - the form engine behind `POST /.form` - is
  not among them, so the one class a form question leads to was the one
  the map did not name. Section 10's syntax checks skip `./data/*`, a
  path that has been `private/data/` since the split and is in no
  checkout anyway; `.github/workflows/ci.yml` skips `./.git/*`, and the
  two commands are the same command now.

- **Docs:** the installer recipe listed `active` among the unit keys the
  installer supports, and nothing reads it. `Setup::apiLibrary()` computes
  an `active` flag for the picker out of `moduleClass` and `preset`, which
  is where the name comes from; no manifest in the tree carries the key,
  and neither `apiApply()` nor `Features::applyUnit()` looks for one. The
  row is gone, two paragraphs below the recipe's own warning not to add a
  manifest key and assume the installer uses it.

- **Docs:** the style guide's rule for naming project paths spelled them
  `templates/`, `text/`, `elements/` and `images/` - the layout before
  the `private/`/`public/` split, and the spelling it asks every manual
  to use. They are `private/templates/`, `private/text/`,
  `private/elements/` and `public/images/`, and what a checkout is
  missing is the two directories above them.

- **Docs:** the screenshot briefing counted five areas in the two
  READMEs, which embed four - frontend, workbench, wizard, Template
  Builder. The fifth, Editor, names `_editor1.webp` and `_editor2.webp`,
  two files this directory does not hold; the row says so rather than
  reading as a description of what is there. The sentence below it said
  four further screenshots stay embedded in the reference manuals, and
  no manual embeds any of the eight files that are here beside the seven
  in use.

- **Docs:** the design manual's plan put the design tokens in
  `text/global.php`, where that file holds `/company/*`, `/website/*`
  and the two mail fills and nothing else. The tokens are the `--nino-*`
  custom properties of `assets/theme.css`, compiled into the first of its
  four layers; they stopped being textfills when the wizard stopped
  asking about the look. Both language versions.

- **Docs:** the wizard reference named seven Personal Infos keys, and
  four of them do not exist. `/company/address`, `/website/name`,
  `/website/description` and `/website/keywords` are in no text file the
  base unit ships; the key is `/company/adress`, the description is
  `/company/description`, and the two the step actually adds to the
  other three - `/website/author` and `/website/host` - were not listed
  at all. It also called the values global, where `/company/country` and
  `/company/description` are per locale. The list is now the eight keys
  `personalinfos.js`'s `ORDER` names, in that order, with the blacklisted
  technical ones said to be left out. Both language versions, and the
  German twin's Library-Format list, also promised a manifest a
  "Beschreibung" and a "Vorschaubild": no manifest in the tree carries
  either, only `label`. And the sentences naming `templates/`, `text/`,
  `elements/` and `images/` as what a checkout is missing name
  `private/` and `public/` now, which is where those four live.

- **Docs:** "Getting Started" and its German twin promised ten wizard
  steps over a table of six - `Install::MODULES` has six modules and the
  rail draws six. The environment chapter named `templates/`, `text/`,
  `elements/` and `images/` as the directories a checkout is missing,
  from before the `private/`/`public/` split: `Checks::DIRECTORIES`
  looks at the project root, `private` and `public`, and the four named
  are inside the first two. And "Verify the Result" sent the reader to
  the Templates panel right after a first install, three lines above the
  paragraph saying a checkout ships no feature - that panel belongs to
  the Template Builder, so both mentions say where it is there.

- **Docs:** the project structure in both READMEs listed ten of the
  workbench's eleven screens. `_admin/Nino/Modules/` carries a
  `Features/` directory beside the ten named, and the panel it holds is
  the one the same README sends a reader to for installing a feature -
  so the one screen a project needs before it has any others was the one
  the tree did not show. It stands between Backups and Config now, where
  its `nav()` weight puts it in the rail.

- **Docs:** the base unit's own files named a wizard and a set of
  stylesheets that are gone. `assets/style.css` said the look lives in
  `style.theme.<name>.css`, `style.header.css`, `style.footer.css` and a
  generated token layer - four files nothing in the tree writes, since
  the delivered look is the one `assets/theme.css` the unit copies. And
  two templates sent the reader to `/_install`, an address the wizard
  has not answered on since it became the workbench's first-run mode:
  `llms-txt.tpl` to "/_install's Setup step" for a list of pages, which
  the Routes step builds, and `html-header.tpl` to "/_install's own
  PersonalInfos step" for the address field.

- **Docs:** the wizard's own step scripts counted the same steps the php
  classes did - `webpages.js` "Step 7", `personalinfos.js` "Step 8",
  `accounts.js` "Step 9", `finish.js` "Step 10" - and `finish.js` called
  what that step sets "the real /_admin password" rather than the
  recovery password. Six comments sent the reader to `_admin`'s "Pages
  module" and its `pages.js`, which is the Routes panel and its
  `assets/admin.js`, and two in `personalinfos.js` to
  `_admin/assets/text.js`, which has been the Text panel's own
  `assets/admin.js` since the panel scripts were named alike.
  `script.js` called the Accounts step "Admin" and the wizard's side rail
  "the top nav", and `style.css` named `Nino.install.selectStep()`, a
  method that is `showStep()`.

- **Docs:** the setup wizard's own classes counted steps the wizard does
  not have. `Webpages` called itself "Step 7", `PersonalInfos` "Step 8",
  `Accounts` "Step 9" and `Finish` "Step 10", where `Install::MODULES`
  lists six modules and `page-wizard.tpl` draws six numbered entries in
  the rail. `Finish` also sent the reader to `Install::setDevPassword()`,
  a method that is `setRecoverySecret()`, and called what that step sets
  "the real _admin password" - it is the recovery password, which no
  login ever asks for. Two docblocks still gated the wizard on "the
  shipped default _admin hash", where `Admin::isInstalled()` reads
  `/nino/install/completed` and the stored recovery secret; `guard()`
  documented one of its two parameters. Two comments sent the reader to
  `/_admin`'s "Pages module", which is the Routes panel under
  `_admin/Nino/Modules/Routes/`; three named a "mail" unit no module in
  the tree ships; one named `data/` and `.cache/` as the directories the
  first step judges by their parent, where `Checks::DIRECTORIES` holds
  the project root, `private` and `public`; and the page library's
  include count said seven units where `library/pages/` holds eight.

### Fixed

- **1.3.1 called itself 1.3.0.** The release was tagged without the commit
  that names it: `\Nino\VERSION` - what a feature's `nino` constraint is
  checked against - stayed `'1.3.0'`, the manuals' headers too, and the
  changelog kept 1.3.1's entries under Unreleased. `\Nino\VERSION` is
  `'1.3.1'`, the headers say 1.3.1 of 22 September, and the entries the tag
  carried have their own section below. The tag itself still reports 1.3.0.
  The German README called Nino a beta as well, a sentence the English one
  had dropped in 1.3.1; it says released now.

- **Install:** the Routes step reported success over a template it could
  not write. It kept its own copies of the unit helpers that Setup handed
  to `\Nino\Features` in 1.1 - a manifest reader, a file copy that
  swallowed a failed write, a text merge - and one config.php rewrite per
  default of a required module. It reads, copies and merges through
  `\Nino\Features::readUnitManifest()`, `copyFile()`, `copyTree()` and
  `mergeText()` now, every copy checked: the first that fails ends the
  step with a 500 naming the file, before any route is written, the way
  Setup's step answers. A file a page unit's manifest names but does not
  carry fails the step too, as it does for a Setup unit. Setup's one-line
  wrappers around the kernel's helpers are gone as well.
  `tests/install-smoke.php` puts a directory where contact's template has
  to go and holds that the apply fails, names the file and writes no route
  (268 → 270 checks, 2 red before).

- **Translations:** an import could fail a whole element over one field.
  The import pre-checks every translated value so that a malformed one is
  skipped on its own, and that pre-check was a copy of the kernel's rules
  that had drifted: it compared a whitelist loosely where the kernel
  compares strictly, so `'1'` passed a whitelist of `'01'` and the kernel
  then refused the element's partial update, siblings included. The
  kernel states its rules once now: `\Nino\Elements::FIELD_TYPES` is the
  list of field types a model may declare (the type editor's
  `Types::FIELD_TYPES` is that list), and `\Nino\Elements::valueError(
  $field, $value )` says what a write would refuse - the type, the lists
  strictly, a reference's prefix, duplicates and cap - which the write
  itself and the import both ask. `tests/kernel-smoke.php` holds the
  check's answers and that a model keeps exactly the named types (784 →
  793 checks, 1 red before); `tests/admin-system-smoke.php` imports a
  whitelisted `'1'` beside a valid title and holds that only the one is
  skipped (690 → 692, 4 red before - two of them existing checks: with the
  loose pre-check the title beside the bad value was not written).

- **Text:** the scan for missing keys, and the Dashboard tile that counts
  them, reported `[[/nino/http/response/uri/clean]]` - a fill the kernel
  makes at request time - as a key no text file answers. The scan kept its
  own copy of the kernel's runtime fills (`Keys::KERNEL_FILLS`), one
  short. The kernel states them once now: `\Nino\Html::bootFills()` and
  `requestFills()` are what `\Nino\request()` and Maintenance register
  (Maintenance's own copy of the five request fills is gone), and
  `runtimeFillKeys()` is the list by name, which the scan reads.
  `tests/admin-system-smoke.php` scans a template carrying every one of
  them (688 → 690 checks, 2 red before), `tests/kernel-smoke.php` holds
  that a request registers every fill the kernel names (783 → 784, 1 red).

- **Element Types:** the type editor offered a unit/suffix input on an
  element-reference field, and the save dropped what was typed in silence:
  three places stated which types take a unit, and the editor's code was
  one type short. The rule lives once now, in `Types::SUFFIX_TYPES` (the
  types that render an input a unit can sit next to); `cleanModel()`
  keeps a suffix for those alone, `elements/types list` answers the list
  as `suffixTypes`, and the editor offers the input for that list and
  nothing else. `tests/admin-system-smoke.php` pins the answer and the
  dropped suffix on a reference (686 → 688 checks, 1 red before);
  `tests/admin-elementtypes-js-smoke.js` renders rows of every kind and
  holds which get the input (33 → 36, 2 red before).

- **Install:** the base unit's web manifest pointed its 192 and 512 px
  icons at `/favicon/…`, the place the set lived before the public/
  split, so both fell through to index.php - and in a subdirectory site
  every install did. The two `src` values are manifest-relative now
  (`favicon-192x192.png`), which resolves under `public/favicon/`
  wherever the site sits, and the shipped `favicon.ico`, which nothing
  linked, is the header template's first icon link. An existing project
  keeps its copy: drop the `/favicon/` prefix of both `src` values in
  `public/favicon/site.webmanifest`, and add
  `<link rel="icon" href="[[/nino/public]]/favicon/favicon.ico" sizes="any">`
  to its header template if it wants the .ico. `tests/install-smoke.php`
  holds that the manifest names its icons relative to itself and that
  every shipped icon is reachable through the header or the manifest
  (266 → 268 checks, 2 red before).

- **Recovery:** a delivery without the Backups module answered a restore
  through recovery.php with a 500 instead of the documented 501. The date
  check read `\Nino\Modules\Backups\Admin::ID_PATTERN` before the
  `class_exists()` guard, so the guard could never answer. It comes first
  now. `tests/admin-system-smoke.php` measures it in a child process whose
  autoloader refuses that one class (685 → 686 checks, 1 red before: the
  child threw "Class not found").

- **Images:** a failed slot upload showed its message as ordinary secondary
  text. The panel set the modifier `error`, which no rule styles; the design
  system's is `is-error`, the one the Elements image field uses.
  `tests/admin-lists-js-smoke.js` now holds that every modifier a workbench
  script puts beside `.nino-admin-field-image-msg` is a rule in style.css
  (136 → 138 checks, 1 red before).

- **Install:** the wizard answered a POST whose `data` field was an array
  (`data[]=x`) with a 500, and it has no authentication until it finishes.
  `Install::postData()` was a copy of `Admin::postData()` from before the
  guard 92bc3fb gave that one; it reads through `Admin::postData()` now.
  `tests/install-smoke.php` posts the array: 265 → 266 checks, 1 red
  before (a TypeError).

- **A panel opened from the rail did not write its name into the address.**
  Only the panels that keep drill-down state - Dashboard, Elements, Text,
  Images, Users and its Roles tab - wrote the url hash, through
  `router.set()` with the parts that state needs. The nineteen others never
  wrote it, so the bar kept naming whatever panel had last written: open
  Images, then Routes, and it still said `#images`, and a reload went back
  to Images. `selectTab()` settles the hash after every switch - the panel's
  or the open tab's bare name, unless the panel's own script already wrote
  a deeper one - and `tests/admin-script-js-smoke.js` reads the address
  back after a rail click, a tab click and an arrow key.

- **A `--bar` tab strip anywhere but the panel head drew as the segmented
  one.** The design system has two strips: the segmented row of equal boxes
  with the open tab filled, and `--bar`, natural widths under one rule with
  an underline. The segmented rules excluded only the head's `--panel` strip,
  and a `:not()` weighs as much as the class it names - so they outweighed
  every `--bar` rule by one class, and a `--bar` strip inside a panel
  (Redirects', Design's, the Features panel's detail) drew as equal boxes
  with the underline's colours on top: three panels, three tab designs. The
  segmented rules exclude `--bar` too, the `--bar` strip lays itself out,
  and `tests/admin-lists-js-smoke.js` holds both.

- **`tests/` was served.** `app/`, `features/` and the wizard's library each
  carry a `Require all denied` and a rule in `router.php`; the suites did
  not, so over http `tests/kernel-smoke.php` booted the kernel against a
  sandbox and ran every check for whoever asked - seconds of cpu and a temp
  directory per request, the sandbox path printed back. `tests/.htaccess`
  and a `router.php` rule deny it the same way, the deployment manual's
  nginx block, its checklist and its direct-access list name the directory,
  and `tests/kernel-smoke.php` holds all four directories to the pair.

- **The base unit's robots.txt fenced two addresses that stopped existing
  with the split, and its demo page ended a sentence in German.**
  `robots.tpl` disallowed `/.cache/` and `/data/`: the first is
  `/public/.cache/` since `public/` gathered the served half
  (`Filesystem::PUBLIC_DIRS`), the second lives under `private/` and is not
  served at all. Both lines are gone; the bundle cache is not fenced in
  their place, since the stylesheets and scripts in it are what a crawler
  renders the page with. And `demo-catalogue-include.tpl` closed its one
  English sentence with "eingesetzt", a remainder of a translation that
  stood on every delivered demo page. `tests/install-smoke.php` holds the
  first as a rule: every path the file disallows begins with a directory
  that exists under the webroot.

- **A visitor who asked their system for less motion got it from four rules
  and from nothing else.** `Nino.css` declares twenty-seven transitions and
  three endless animations; its `prefers-reduced-motion` block stopped the
  three animations and one transition, so the slider still slid its 600ms,
  the burger menu unrolled, the cookie banner rose, the toast faded in, the
  preloader faded out over a full second and every button and field
  crossfaded its colours. The block clamps every animation and transition to
  .01ms now - a state change still happens, it simply arrives instead of
  travelling, which is what keeps the ones a visitor asked for by pressing
  something - and the three that run on their own are still stopped outright
  after it. `Nino.ui.js` was worse: `scrollIntoView` and `scrollTo` take the
  behaviour as an option, and an option wins over whatever the stylesheet
  says, so the hash link, the down arrow and "back to top" all rode smoothly
  however the system was set; and `onReady()` wrote `scroll-behavior: smooth`
  onto `<html>` as an inline style, which is the one declaration a stylesheet
  cannot overrule without `!important`, putting the ride back for every
  anchor on the page. All four ask `Nino.ui._reducedMotion()` now, asked
  fresh each time because the setting can change while the page is open.
  `tests/nino-ui-scroll-js-smoke.js` answers the media query both ways and
  reads the behaviour and the inline style back, and holds the stylesheet's
  block to the clamp (seven checks; against the old sources the file aborts
  at the first of them, and with only the stylesheet reverted two are red).

- **Nine reorder and remove buttons were called nothing but a hover hint.**
  The ↑/↓ pair in the Routes list, in the Element Types field list, in the
  Navigations entry list and in the wizard's Routes list, and the × beside
  the last of them, have an arrow or a cross for a face and carried the word
  for it in `title` alone. `title` is the weakest source the accessibility
  tree accepts, it is shown on hover and on nothing else - not on a touch
  screen, not to a keyboard - and a project's stylesheet cannot make it
  visible. `Nino.adminUi.elementList()`'s own button sets both, and these
  five hand-rolled copies of the same row set one; all nine set
  `aria-label` beside the title now, from the fill the title already uses.
  `tests/admin-lists-js-smoke.js` reads every `dc.createElement('button')`
  in every shipped workbench script, per variable, and requires a name
  wherever a glyph was written on one - so a Delete whose face is a whole
  word and whose title is a hint is left alone (one check, red before,
  naming all nine).

- **A refused contact form said so three ways, and a screen reader was
  handed none of them.** `Nino.ui.js` refuses a submit on the client - a
  required field left empty, an address that is not one - and answered with
  a red outline on the field, a sentence in a paragraph nothing had been
  asked to watch, and the caret left wherever it was: on a long form refused
  for its last field, that is the submit button, with the explanation above
  the fold. It also never took the outline off again, exactly as the
  workbench login form used to not, so a visitor who corrected the address
  submitted with the corrected field still marked. The message paragraph is
  declared `role="status"` by the handler that writes into it - it is a
  project's own markup, so the component that fills it is the only place
  that can - a refused field carries `aria-invalid` beside the class and
  loses both on the next attempt, and the caret goes to the first field the
  form refused. `Nino.ui.toast()`, a sentence that appears unasked and is
  gone four seconds later, is a live region for the same reason.
  `tests/nino-ui-form-js-smoke.js` grew attributes and a focus flag in its
  field stub and drives a refusal, a correction and a toast (nine checks,
  six red before).

- **The wizard's Routes step offered three boxes per language that a screen
  reader could only call "edit text".** A route's per-locale row is a grid of
  the locale code and three controls - the menu name, the HTML title and the
  description - and none of them had a label: all three carried a
  placeholder, which is not a name, because it is gone the moment somebody
  types in the box and never reaches the accessibility tree as one. A
  project set up in four languages therefore had twelve unnamed boxes on one
  screen, in one row each, with nothing but their order to tell them apart.
  Each box now names itself, and names its locale with it - "Name (de_DE)" -
  since "Name" four times over says nothing about which language it is the
  name in; the placeholder stays as the example it always was.
  `tests/install-script-js-smoke.js` gained a recording element and builds a
  row through the step's own `_localeRow()` (three checks, one red before).

- **The workbench had no heading of any level, and the wizard's ran h3, h3,
  h3, h1.** Measured in headless Chromium on the rendered shell: `h1
  count=0`, and no `h2` or `h3` either, so a screen reader asked to list the
  page's headings - the usual way of finding out what a page is - answered
  with nothing at all. The two locked pages had none either, and the
  wizard's one `<h1>` was on its last screen, under three `<h3>`. The shell
  and the wizard now open with an `<h1>` the design system's new
  `.nino-admin-sr-only` keeps off the glass, because neither has a place a
  visible one would belong: the shell's panels open with their own screen
  and the wizard's steps with a lead paragraph. The two locked pages promote
  the sentence they already show, and the levels below are a ladder - the
  wizard's three cards and its finish screen are `h2`, the environment
  groups `checks.js` draws are `h2`, and a panel's own headings (the
  dashboard's two cards, a feature's screen, the translations screen, which
  was a second `<h1>`) are the `h2` they always were one level below. The
  shared card rule takes both levels. `tests/admin-lists-js-smoke.js` reads
  the heading levels out of each of the four screens and holds them to one
  `h1` first and no missing rung (two checks, both red before, naming all
  four files).

- **Neither the rail nor the wizard's progress said which one of them you
  were on.** The workbench marks the open panel by putting an `active` class
  on its rail link, and the setup wizard marks the step it is on the same way
  on one of six words - a colour and nothing else, so a screen reader read
  the navigation as up to twelve links with nothing to tell them apart and
  the wizard's progress display as six pieces of static text. Measured on the
  rendered shell in headless Chromium, the page carried no `aria-current` at
  all. Both set it now - `page` on the open panel, `step` on the current
  wizard step - and remove it from the others rather than writing "false" on
  them, which is a value that reads as "not this one" on every link that is
  not the answer. `tests/admin-script-js-smoke.js` gained a rail and a pane
  of its own and drives `onReady()` against it (five checks, three red
  before - two of them also the first real drive of the pane strip's arrow
  keys), and `tests/install-script-js-smoke.js` moves the wizard between
  steps and reads the mark back (four checks, two red before).

- **A keyboard could walk the shared table's rows and not see which one it
  was on, and never heard which column it was sorted by.** A clickable row
  in `Nino.adminUi.table()` is a tab stop, and the stylesheet answered focus
  with the same tint it answers hover with - and took the browser's own ring
  off on top of it. Computed from the design tokens, that tint is 1.15:1
  against the card in the light scheme and 1.26:1 in the dark one, where the
  focus rule asks for 3:1 (WCAG 2.2 SC 1.4.11). The row draws a real 2px ring
  now, inset so the table's own scroller cannot clip it. The sort state had
  the matching problem from the other side: which column the table is ordered
  by, and in which direction, was a `::after` glyph and a class - both of
  them things a screen reader is handed nothing of - so every header now
  carries `aria-sort`, "none" included, which is also what says the column
  can be sorted at all. `tests/admin-lists-js-smoke.js` sweeps the design
  system for a rule that removes an outline without putting one back (two
  checks, both red before) and `tests/nino-ui-table-js-smoke.js` presses the
  sort buttons and reads the attribute back (four checks, all red before).

- **"Log out" and "Close recovery" were links to nowhere, and too small to
  hit.** Both were `<a href="#">` with a click handler: announced to a screen
  reader as links, activated by Enter and not by Space the way every other
  action in the workbench is, and - being real links to the page one is
  already on - a middle-click or "open in new tab" on either reloaded the
  screen instead of doing anything. Measured in headless Chromium on the
  rendered shell, "Log out" was also 44x19 css px, under the 24x24 the
  pointer rule asks for (WCAG 2.2 SC 2.5.8). Both are `<button type="button">`
  now, and the design system grew `.nino-admin-linkbutton` for the shape they
  need - an action that reads as a line of text - which takes the button
  surface back off and puts a 1.5rem floor under the hit area - and the shared
  hover rule names it as its one exception, since that rule weighs more than
  the new class's own and would otherwise tint a button that has no surface;
  the two handlers dropped the `preventDefault()` they no longer have anything
  to prevent. The same measurement now reports no focusable element of the shell
  under 24px in either direction. `tests/admin-lists-js-smoke.js` sweeps the
  tool's own templates for `href="#"` - the shape the mistake always takes -
  and holds the new class to its floor (two checks, both red before).

- **Six controls of the workbench had no name a screen reader could read
  out.** The language switcher is a bare `<select>` standing on the login
  card and in the rail's settings popover with no visible word beside it, so
  it was announced as "combo box" and nothing else. The shared data table's
  four controls fared no better: its search box carried a placeholder, which
  is not a name - it is gone on the first keystroke and never reaches the
  accessibility tree - its two pager arrows were announced as "button ‹" and
  "button ›", and its rows-per-page select as a combo box of numbers. The
  multi-reference control's search box had the same placeholder-only
  problem, under a `<span>` that labels nothing because the field around it
  is a `<div>`. The switcher names itself from a new
  `/_admin/label/language` fill; the table and the element list name their
  boxes with the word the caller already gives them, and the table's three
  glyph controls fall back to three new `/_admin/common/label/` fills the
  way `switchField()`'s on/off pair already does, so no caller has to know
  them. `tests/nino-ui-table-js-smoke.js` drives the renderer through a
  recording element and holds all four names (four checks, all red before),
  `tests/nino-ui-elementlist-js-smoke.js` the fifth (one, red), and
  `tests/admin-system-smoke.php` the switcher's, rendered rather than read,
  so an unresolved fill would fail it too (one, red).

- **Three screens wrote their answer into a paragraph nobody was told to
  watch.** The login form's verdict, the recovery page's "Checking …" and
  the wizard's "Please fix the error above before continuing" are written
  into a paragraph by a script after a submit, and the three paragraphs were
  plain `<p>` elements: the sentence appeared on the screen and a screen
  reader said nothing, so an operator who cannot see it pressed Log in and
  got silence. All three are `role="status" aria-live="polite"` now, the way
  the wizard's five per-step messages and the recovery page's other two
  already were. The login form also marked the field it refused with a red
  outline and nothing else - a colour, which is the one signal that reaches
  neither a screen reader nor a colour-blind operator - and sets
  `aria-invalid` beside it, taken back with the outline on the next attempt.
  `tests/admin-lists-js-smoke.js` collects every `msg`/`message` id a
  shipped `_admin` script writes into and holds each one's paragraph to a
  live region, so the next screen to grow one is held to it too (one check,
  red before, naming all three); `tests/admin-login-js-smoke.js` grew
  attributes in its dom stub and three checks around the mark and the
  region (two red before).

- **A tab strip of the workbench could be operated with a pointer and not
  with the arrow keys.** All three of them - the strip the shell renders over
  a pane's tabs (`\Nino\Admin\Panels::$html`) and the Features panel's two -
  are `role="tablist"` rows of `role="tab"` buttons, which tells a screen
  reader user that Left, Right, Home and End move along the strip. None of
  them listened for a key: the strip was walked with Tab, one stop per tab,
  and the keys the announcement promised did nothing. The panes were not
  panels either - a tab named nothing with `aria-controls`, and the div it
  opened was an anonymous div rather than the `role="tabpanel"` the pattern
  says it is - and every strip but the one the shell happened to open
  reported a tablist with no tab selected at all, because only the opened
  panel ever went through `selectTab()`. `Nino.adminUi.tabKeys()` is the
  keyboard half, in the design system rather than three times over:
  Left/Right wrap, Home and End jump to the ends, and exactly one tab is in
  the page's tab order at a time. `Nino.adminUi.buttonRow()` uses it for
  every row it is given the `aria-selected` flag for, the shell's own strip
  calls it directly and paints every strip's first tab on wire-up, and the
  shell's fragments name the pane each tab opens - a lone tab pane, where one
  tab is no strip, stays a plain div with no tab to be the panel of.
  `tests/admin-lists-js-smoke.js` drives the helper and the row through
  ArrowRight, End and the wrap-around (nine checks, all nine red before),
  and `tests/admin-smoke.php` counts the tab/panel pairs in the rendered
  panes (two checks, both red before).

- **A site served from a subdirectory answered its own addresses with the
  404 page.** Every address the kernel writes carried `[[/nino/dir]]`, and
  the router looked the request path up as it came in, so `/shop/about`
  found no route keyed `GET://about`; the workbench's panels, its login and
  logout, the recovery page and the install wizard posted to `/_admin/` and
  `/.nino/auth/…` from the domain root, a locale switch redirected there,
  and a menu entry linked there - twenty-eight places. `Http::request()`
  reads the request path without the directory; `\Nino\request()` derives
  the directory from the entry script's own address where `config.php`
  does not name it (`Filesystem::deriveDir()`), which is what lets the
  wizard run from there at all; `Nino.js` carries it as `Nino.dir` for the
  two pages that load it unbundled and the bundled scripts write the fill;
  the redirect and the menu put it in front. `kernel-smoke.php` holds the
  router, the redirect, the menu, the derivation, and that no shipped script
  or template names an address from the domain root; `nino-auth-js-smoke.js`
  holds `Nino.dir` and the endpoints under it.

### Removed

- **The social media links left the base unit for the catalogue.** The keys
  `/company/instagram`, `/company/facebook`, `/company/youtube` and
  `/company/telegram`, which the Personal Information step asked for, the
  heading `/website/footer/title/followus`, the template `html-socialmedia.tpl`
  that drew them - four brand icons of unknown origin, every link opening a
  new tab - and the `.nino-socialmedia` rules in `Nino.css`. Four networks
  fixed as text keys were the wrong shape for a list a site keeps: the
  catalogue's feature Social links is an element type the editors keep under
  Elements, with icons from Lucide, every address checked before it becomes a
  link, and `[social]`, `[social-link]` and `[social-icon]` to draw it. The
  Design feature's frames include that feature's template instead. The demo
  catalogue's "Social and partners" is "Partners", with the logo bar alone;
  `install-library-templates-js-smoke.js` lost its check of the icons' paint
  (9 → 8 checks); `install-smoke.php` holds that the Personal Information step
  asks for no network's address (270 → 271); and the setup manual and its
  German twin say where the links went.

## 1.3.1 — 2026-09-22

### Changed

- **Docs:** the base unit's `theme.css` header no longer points at the
  catalogue's `design-library/`, a directory the catalogue dropped on
  purpose: the whole-page themes the wizard used to offer are gone, a
  project composes its look from the Design feature's part sets, and a
  presets field that combines them may come later. The Design feature's
  `library/base.css` carries this header byte for byte and follows in its
  own patch.

- **Docs:** the feature directory in `docs/features.md`, its German twin and
  `docs/recipes/feature.md` shows `templates/`, the directory the catalogue's
  AGENTS.md asks every feature that draws anything to keep its markup in;
  and the element recipe's image address is `[[/nino/public]]/images/…`,
  the fill the base unit's own templates use, where it read `[[/nino/dir]]`
  - the project directory, which is not where a public file is served from.

- **Docs:** README, SECURITY.md and the two deployment manuals no longer
  call Nino a beta. 1.3.0 is a release, and the five sentences that said
  otherwise were written before there was one; what they said beside it -
  the latest release is the supported one, fixes land on `main`, there is no
  LTS line - stands as it was.

- **Docs:** thirteen comments in the Elements, Users, Images and Text panels
  sent the reader to `elements.js`, a file that has been `admin.js` since the
  panel scripts were named alike, and one docblock in `Elements/Types/Types.php` named
  `\Nino\Modules\Elements\Admin::insertElementType()`, a method that lives in
  `\Nino\Elements`. Every one names the file and the class that exist.

- **Tests:** three checks in `tests/admin-system-smoke.php` read the source
  of the restore for `rename(`, `lockFile( $appData, '/config.php' )` and
  `'/nino/admin/restore'`, so a refactor that spelled any of them differently
  turned them red while a restore that dropped the behaviour behind a
  different spelling would not. They measure the restore now: `config.php`
  is another file afterwards, with nothing temporary beside it; it does not
  change while another process holds its lock, watched from that process at
  the write step itself; and the module callback is handed the live data
  directory and the extracted backup.

### Fixed

- **The panel tab strip's exception was spelled with a class outside the
  design system's namespace.** `Fixed(/admin): tabs styling` kept the
  generic tab rules off the pane's own strip with
  `:not(.admin-panel-tabs)` - a workbench class inside the `nino.system`
  layer, which `tests/admin-lists-js-smoke.js` holds to `nino-admin-*`
  classes alone. The strip carries `nino-admin-tabs--panel` now, the three
  exceptions name that, and the tool-layer rules on `.admin-panel-tabs` are
  as they were.

- **The demo catalogue page posted its newsletter forms from the domain
  root.** Both newsletter sections of `.demo-catalogue.tpl` wrote
  `action="/.newsletter"` - the line the Templates feature's preset wrote
  until its own fix - and an action in the markup wins over the one
  `Nino.ui.js` builds with the project directory in front, so a site at
  `/shop` posted beside itself. They write `[[/nino/dir]]/.newsletter` now,
  like every other address the install library writes, and
  `tests/install-smoke.php` holds that no template of the library writes an
  `action` or `href` from the domain root.

- **`--fontfamily-subtitle` was read by `Nino.css` and declared only by the
  base unit's `theme.css`.** `.nino-atf-subtitle` and `.nino-section-subtitle`
  read it, so on a page without that theme - or with an older copy of it -
  both fell back to whatever face the parent had. Declared beside
  `--fontfamily-text` and `--fontfamily-title` now, with the text face's
  stack, as theme.css maps it; and `tests/kernel-smoke.php` holds that every
  custom property `Nino.css` reads without a fallback is one it declares.
  `tests/install-smoke.php` counted the font stacks of both files - six -
  and would have counted the new one as a failure; it holds "at least one,
  none quoted as a whole" now, which is what it was there for.

## 1.3.0 — 2026-09-21

### Added

- **Tests:** the ip bucket of the login throttle, whose rules nothing
  asserted. Every login in `tests/kernel-smoke.php` comes from 127.0.0.1, so
  the bucket was live in every test and read by none: that an ip in cooldown
  is refused before the password is looked at and without the account's
  bucket moving, that a guess against an account that does not exist counts
  against the ip, that the ip trips at `maxtries` times its factor and not
  at `maxtries`, that a successful login clears the ip bucket as well as the
  account's, that an account already in cooldown feeds no bucket, and that
  a caller without a client ip gets no ip bucket rather than a shared empty
  one. Each check was proven by breaking its rule in `Auth.php`.

- **Tests:** the csrf guard's other four paths, none of which was measured.
  `tests/kernel-smoke.php` now drives which methods are checked (PUT, DELETE
  and PATCH as much as POST, and a method the kernel does not recognize), the
  `X-CSRF-Token` header, the token in a json body - `$_POST` is empty for one
  of those - and the per-route `'csrf' => false` opt-out, including the one
  thing it must not do: a POST to an address no route is registered for must
  not inherit the 404 page's opt-out, which would wave through every POST to
  every unregistered uri on the site.

### Changed

- **Tests:** eight checks that copied shipped content - the two locales the
  library ships, the three always-on units, the two roles, the two menus
  (three times over), what the contact page requires, the four fields of the
  contact form - read the source they mirrored now: the base unit's text files, `Setup::ALWAYS_MODULES`
  and `units()`, `Roles::defaults()`, the Navigation unit's manifest, each
  page template's manifest, `Form::DEFAULT_FORM` with `TYPES` and `RESERVED`.
  What they pin is the invariant each meant (a listed locale has a file, an
  always-on key has a unit, a default lands, a listing answers its manifest, a
  shipped field survives validation and is typed from the vocabulary), so a
  content change edits one place and a drift between two still fails. Each
  was proven by breaking the code beside it.

- **The envelope sender is a textfill, not a `config.php` key.**
  `\Nino\Mail::_getSender()` read `/nino/mail/sender` from `config.php` ahead
  of the `[[/form/email/owner]]` fill, so the two halves of one setting lived
  in two places: the address mails go out as in the Text panel, the address
  the host sends them as in a file. It is `[[/mail/sender]]` now, shipped
  empty by the base unit beside the owner address - empty means the same as
  that address, which is the normal case - and set in the Text panel like it.
  A value that is no address falls back to the owner address with a line in
  the log - the old key silently cost every mail its `From` for that.
  `/nino/mail/sender` is not read any more; `docs/deployment.md` says so.

- **Nine comments and a manual line described code that is not there any
  more.** `\Nino\Modules\Backups` still promised archives under a one-time
  random directory name and a key copy inside `_admin/`, where `dirs()` and
  `_bootstrap()` write `private/.backups` and `private/.auth/backup-key.php`.
  The Elements panel called element type creation a developer-only task "not
  exposed here", although the Types tab of its own pane creates, saves and
  deletes one, and it pointed at an `assets/elements.js` that is
  `assets/admin.js` - with the Save button named in German on the way past.
  `\Nino\Backup::manifest()` credited two classes and a method that exist
  nowhere and said it carries the activity log, which is written to
  `private/.logs/` and is in no archive. `\Nino\Form::entries()` was
  documented as "within the retention window" although it returns every month
  file on disk: pruning happens on a new submission, so a form nobody submits
  to keeps everything. The merge in `\Nino\Elements::_writeElementData()` was
  said to build the element that comes back, while it only feeds the uri-change
  notification, out of values read before the lock. The setup wizard's
  stylesheet named two hide utilities and a script that no longer exist and
  spoke of "all four tools". Both workbench manuals and `AGENTS.md` put the
  login throttle's counters under `private/.auth/`, where `\Nino\Auth` writes
  `private/data/auth-tries.php`. And the restore check in
  `tests/admin-system-smoke.php` was labelled after a class that has no name in
  the repository any more. Every one of them now says what the code does; two
  wizard rules nothing references - `.install-admin-row` and
  `.install-next-step--admin` - went with them, and no behaviour changed.

- **The mailbox every mail is sent from moved to the base install unit, as a
  fill.** `\Nino\Mail::_getSender()` reads `[[/form/email/owner]]` for the
  `From` header and the envelope sender of every mail the framework sends, and
  that fill shipped with the **Form module's** install unit - which the wizard
  offers rather than installs. A project that did not pick that module had
  neither header, and a mail without a `From` goes out as the webserver user,
  which is the most reliable way there is to land in a spam folder. It is the
  base unit's now, so every project has it, and its value is
  `[[/company/email]]` rather than a placeholder address: by default the
  mailbox the project already named, changed in one place when replies should
  reach another. `/nino/mail/sender` still wins where the envelope sender has
  to differ from it for SPF, and `docs/deployment.md` now says so - it was in
  no document at all before, only in the two lines of `Mail.php` that read it.

- **The shipped pages name the section presets the Template Builder has now.**
  The feature renamed them so a key names the group an editor looks in, and
  three files here named the old ones: the demo catalogue's template, in 48
  `data-demo-preset` attributes, and the `<!-- nino:section -->` markers in
  `page-home.tpl` and `page-contact.tpl`. A marker whose preset the library
  does not know is not a library section any more - the panel leaves it on the
  page and will not edit it - so without this a fresh install's two most
  visible pages carried a section the builder no longer recognised. Only the
  `preset` value changed; the section ids beside it are what the generated
  textfill keys are built from and stay as they are.

- **The workbench manual stops describing a feature it does not own.** The
  Templates section spelled out what the Template Builder composes - the preset
  library, the reusable sections, the quick fill - and then linked the feature's
  own manual for the same thing. Two places to keep in step, in two
  repositories, and the manual here is the one nobody would think to update.
  It now says what the Newsletter and Search sections already said: which
  feature the panel belongs to, that it is a workspace panel, and that the
  feature's own manual documents it. `AGENTS.md` likewise stated which kernels
  the Template Builder declares itself for; which those are is its manifest's
  business, in that repository. Nothing about the kernel moved - what a panel
  from a feature *is*, and how the Features panel handles it, is still here.


### Fixed

- **The wizard's Setup step reported success over a unit file it could not
  copy.** `\Nino\Features::applyUnit()` answers the first file it could not
  copy, and the wizard read no answer from it: the step went on to write
  locales, modules and routes into `config.php` and answered 200, with a
  template missing and nothing to say so. The first such file ends the step
  now, with a 500 naming it and nothing written for that run; what was
  copied before it stays, and applying again once the target is writable
  picks up whole, the way every reapply of this step does.

- **A catalogue update of a running feature never ran the new version's
  upgrade hook.** The Features panel placed the new directory and activated
  again in the same request, and `Features::activate()` asks `method_exists()`
  for the hook - which answers for the class in memory, loaded at boot from
  the previous version. The hook the new version brought was never called,
  the new version was recorded all the same, and with the record saying
  current nothing ever called it later: every data migration a feature ships
  was skipped on exactly the update path the panel offers. Measured with a
  1.2.0 whose `upgrade()` writes a marker over a running 1.1.0 - no marker,
  version recorded. `\Nino\Catalogue::install()` notes now when it replaces
  the directory of a class that is already loaded, `activate()` refuses to
  apply such an update in that request and says why, and the panel answers
  that the update is pending and activates again in a request of its own -
  the same call **Update** in the Active tab makes, words and reload included
  - so one press is still one press, and the hook runs in a request that
  loads the new class. The text that wrapped a refusal of the same-request
  activation is gone with that activation.

- **A day's log file that could not be written took the action being logged
  down with it.** `Modules\Logs\Admin::record()` rewrote the day's file in
  place with an unchecked `file_put_contents()`. A target that could not be
  written - a directory in its place, a permission, a full disk - raised php's
  own warning, which the framework's handler ends the request on: the element
  was saved, the route was moved, and the request answered a 500 from its
  log line. Listing the log died on the same file, and a reader who arrived
  during a write found it truncated. The line is written beside the file and
  renamed over it now, through the writer the recovery hash already used and
  which the shell offers as `Admin::writeFileAtomic()` - and a write that
  fails is what the log says it is, with the lock it took released whichever
  way the write went. A file that cannot be read holds no lines.

- **A restore left two files behind in the temp directory every time, and
  threw on a backup it could not unpack.** `tempnam()` creates the file it
  names, and both the restore and the safety snapshot it takes first went on
  to work on that name plus a suffix - the file `tempnam()` made was never
  removed: two per restore, for the life of the server. And nothing on the
  way was checked or caught. A backup that decrypts but is not an archive - a
  file somebody truncated or replaced - made `PharData` throw out of the
  panel: a 500 with nothing said, and the archive and the staging directory
  it had written left standing as well. The snapshot had drifted from
  `Backups::_create()` in the same way, and a file the manifest names but
  that cannot be read was a TypeError instead of a sentence. Both remove
  everything they make on every way out now; what fails answers with a reason,
  and a snapshot that cannot be made is a restore that does not start.

- **A unit file that could not be written was an activation that reported
  success.** `Features::copyFile()` wrote a unit's template with an unchecked
  `file_put_contents()`, and `applyUnit()` answered nothing to `activate()`
  either way. A target that could not be written - a directory in its place, a
  permission, a full disk - raised php's own warning, which the framework's
  handler ends the request on: a 500 with half the unit copied. Under a handler
  that carries on, a project's own or a test suite's, the activation went on to
  list the class and record the version: a success, with the template missing
  and nothing to say so. Every copy and every text merge is checked now, and
  `applyUnit()` names the first file it could not copy, so the activation is
  refused with that sentence before anything is listed or recorded, and raises
  nothing on the way. The wizard applies its units through the same method and
  still reads no answer from it - a fresh install writes into directories it
  has just created - and that is its own step to take.

- **A `.json` file that does not decode was answered as `null`, not as the
  default.** `Filesystem::getFileContent()` answers `$default` for a path it
  refuses, a file it cannot prepare and one that is not there - and cached
  what `json_decode()` gave it for one that is: `null` for an empty file, a
  truncated one, one holding anything but json. That `null` went out as the
  file's content, into `mutate()` too, whose callback is typed for the array
  it was promised as the default - a TypeError out of a read-modify-write, for
  a data file a crash or a full disk left half written. Such a file answers
  the default now, the same as one that is not there, and `mutate()` starts
  its callback from the default it was given.
- **Converting a text key to global threw away the only translation it had.**
  `Keys::apiSave()` documents the migration it performs: per-locale to global
  "keeps the native locale's value (falling back to the first non-empty one)".
  `_convertShape()` read that native value with `??`, which steps aside for a
  null and for nothing else - and a locale file that carries the key with an
  empty string is not null, as the comment two lines below it already
  explained about the other half of the same expression. So the fallback ran
  only for a native locale that had never heard of the key at all. For the
  ordinary case - a key written per-locale, translated into one language,
  still blank in the project's own - the empty native value won, the key
  became a global empty string, and the translation that existed was deleted
  along with the locale files' copies. Measured in
  `tests/admin-system-smoke.php`: a key empty in `de_DE` and filled in `en_US`
  converted to `''`, where the docblock says `en_US`. Empty is now treated as
  nothing to keep, so the documented fallback runs; a native locale that does
  have a value still wins over every other one, and a key that is empty in
  every language still becomes an empty global key rather than no key.

- **A reorder the server refused left no trace anywhere.** The ↑/↓ buttons of
  the Routes list reorder the persisted routes, and because equal menu
  priorities follow route order, that list is also what orders every
  navigation those pages stand in. `_move()` answered anything but a 200 with
  a bare `return`: the row did not move, nothing appeared, no message, no
  status - the arrow simply read as a button that does nothing, however often
  it was pressed. And whatever the refusal was - a route somebody else deleted
  since the list was drawn, a config.php that could not be written - the order
  on screen stopped being the order on disk at that moment, with nothing to
  say so. A refused move now reads the list back from the server, redraws it,
  and puts the status and the server's own reason in a polite live region
  below the list, where the rows and their arrows stay standing; if the reload
  fails too, the reason for the move is still shown. The panel had no js test -
  `tests/admin-routes-js-smoke.js` is a new one, twelve checks over the list,
  an accepted move, two refused ones and a reload that fails as well, six of
  which the old file failed.

- **A required field could be saved empty in every language but the one on
  screen.** One click on Save in the Elements panel writes every translation
  that was edited - that is what `_saveLocales()` is for, and it has been that
  way since the editor stopped losing edits made before a locale switch. The
  required-field check in front of it never moved with that: it read the dom,
  and the dom only ever holds the visible locale. So leaving a required field
  empty in one language, switching to another, filling it in there and saving
  wrote both - the form said "saved", and the element carried a required field
  with nothing in it, in a language nobody was looking at. The check now
  covers every locale the save is about to write: the visible one from its own
  controls as before, the others from the values that are actually going to be
  submitted, and a translation that is not on screen is named in the message
  (`title (de_DE)`) so the person knows where to go. A translation nobody
  edited is not checked, because it is not written either. The one case the
  stored values cannot show is a blank number field in another locale, which
  was already 0 by the time it was stored; the new helper says so where it
  stands. Five checks in `tests/admin-elements-js-smoke.js`.

- **A type that referenced another one with a leading slash could not be saved
  at all.** An element field names the type it may point at, and everything
  that reads that name normalises it: `\Nino\Elements` builds the prefix a
  reference has to start with as `'/'. trim( elementType, '/' ). '/'`, and the
  panel's own `referencedBy()` compares the same way. So a type file written
  by hand with `'elementType' => '/pages'` is a working model - the kernel
  validates against it, the site renders it - and only the panel's
  dangling-reference check compared the raw value against the bare type uris
  on disk. It answered 400 with "references the unknown element type
  \"/pages\"" for the type it had just been handed, on every save of that
  file, including one that changed nothing but the title, and the type editor
  drew the field as "reference missing" beside it. The name is normalised
  where it enters the model now, so what is stored is the spelling the kernel,
  `referencedBy()` and both element forms already read - the option value the
  forms build was `//pages/x` otherwise - and the check normalises too rather
  than trusting its caller. A slash on its own is still no reference, and an
  unknown type is still unknown however it is spelled.
  `tests/admin-system-smoke.php` covers all four.

- **A restore that failed took the list of backups with it.** The Backups
  panel is the screen somebody opens when the site is already broken, and a
  refused restore emptied it: `_confirmRestore()` reported through
  `_showError()`, which clears `#backups-list` and puts one paragraph there
  instead. But a restore the server did not carry out overwrote nothing -
  every date on that list is still exactly as valid as it was a second
  earlier, and the buttons that were just taken away are the way to try the
  next one. Only a page reload brought them back, on the one screen where a
  person is least likely to trust a reload. The list now keeps its rows and a
  refused restore writes into a polite live region below them, which is where
  every other panel of the workbench puts a failure it survived;
  `_showError()` stays for the list that could not be loaded, which has
  nothing to keep. The panel had no js test at all - `tests/admin-backups-js-smoke.js`
  is a new one, twelve checks over the list, both failure paths and the
  successful restore, six of which the old file failed.

- **An image slot could be saved that no upload was able to fill, and deleting
  one threw the file away before the record.** Two things in the Image Slots
  tab. A slot's width and height are the exact canvas `\Nino\Images::process()`
  renders onto, and they had no upper bound at all: measured on this gd with
  php's default 128M, `apiCreate` took a 20000x20000 slot with a 200 and the
  upload meant to fill it then came back as "invalid or oversized image" -
  blaming the photograph for a size nobody could satisfy, on every attempt,
  for as long as the slot stood. Both `apiCreate` and `apiSave` now refuse a
  size above `\Nino\Images::MAX_SOURCE_PIXELS` with a 400 and a reason the
  panel already shows; the constant became public for it, because it is not
  only a gate on the way in - the kernel's own comment says the target buffer
  is the same allocation - and the largest square that budget allows
  (4472x4472, 20 megapixels) still saves and still fills. Second, `apiDelete`
  deleted the slot's uploaded file and only then wrote the slot list.
  Measured with config.php's sidecar lock made impossible to open, which is
  what a read-only or full disk comes to: the old code answered 200, the file
  was gone, the slot was still in config.php pointing at it - a public page
  with a broken `<img>` and nothing left in the panel to re-upload over - and
  the retry 404'd, because the only copy that had lost the slot was the one
  in memory. The record is written first now, a write that fails puts the
  slot back and answers 500, and the file is deleted only once the removal is
  on disk. Nine checks in `tests/admin-system-smoke.php` around both.

- **Three icons of the shell carried two `class` attributes each.** The theme
  toggle's sun, moon and system `<svg>` in `page-index.tpl` opened with
  `class="admin-theme-toggle-*"` and carried a second
  `class="lucide lucide-…"` at the other end of the same tag. A tag holds one
  attribute of any given name: the html parser keeps the first and drops every
  repeat as a parse error, without saying so. Measured in headless Chromium
  against the shipped markup - the three elements reached the dom with ten
  attributes rather than eleven, and `#admin-theme-toggle .lucide` matched
  nothing at all. Nothing reads the lucide classes today, which is why this
  could sit there unnoticed; written in the other order it is the whole theme
  toggle going blank, since `admin-theme-toggle-system`, `-dark` and `-light`
  are what the stylesheet switches the three icons on. The two attributes are
  now one, in the place every other icon of the file keeps it, and the
  measurement finds all three lucide classes back. `tests/admin-lists-js-smoke.js`
  sweeps every `.tpl` in the checkout for a tag carrying the same attribute
  twice rather than only this file.

- **The login form never took back what it had marked.** `login.js` outlines
  the field it refuses - the login screen is the one place with no per-field
  error text, only "that pair was wrong" - and it added that outline without
  ever removing it. Submit with no email, fill the email in, submit again:
  the email field still carried the red outline while the password field got
  one of its own, so the form pointed at two fields and one of them was
  correct. The message under the fields had the same problem from the other
  end: it is set to `pending` for the duration of the request, and the answer
  *added* `error` to it, so a refused login ended up carrying both classes and
  both rules of the stylesheet - the blue "checking" state and the red one at
  once, for as long as the page stayed open. Each attempt now clears both
  outlines and the message class before it validates anything, and the answer
  replaces the pending class rather than joining it.
  `tests/admin-login-js-smoke.js` grew a real class list in its dom stub and
  six checks around it, three of which the old file failed.

- **The Navigations panel called a page unnamed that the menu names.** A menu
  entry is "a path with a name", so the panel reports per route whether the
  `/webpage<uri>/name` key exists - a route without one is skipped by
  `Modules\Navigation::routeLines()`, and offering it would be offering an
  entry that never appears. The menu resolves that name through the fill
  engine, which merges the locale-independent `global.php` under the file of
  the locale the visitor is on; the panel read `text/<native locale>.php` and
  nothing else. So a name written once in `global.php` for every language, and
  a name written in a language that is not the native one, were both names the
  menu puts on the page and the panel refused to offer: the entry could not be
  added at all, and where it already stood it was listed as "(unnamed)". The
  panel now resolves a route's name the way `Html::getFills()` does - global
  under each locale the project offers, the native one first so its wording
  stays the label - and reads the configured text directory rather than a
  hard-coded `/text`. `tests/admin-system-smoke.php` adds three routes named
  only in `global.php`, only in `en_US.php`, and in both `global.php` and the
  native file, and checks the reported name and flag for each.

- **The mobile opt-out of the row equalizer left a height on the element
  anyway.** `.nino-autoheight` equalizes every element sharing a
  `data-autoheight-group`, and `data-autoheight-mobile` is how a single one of
  them says not to on a phone - a card whose text is long enough that a forced
  row height turns it into a clipped block. The opt-out was read in the
  measuring loop alone: the element was kept out of its group's maximum, and
  the second loop, which carried no condition at all, then wrote that maximum
  onto it a moment later. The one element that asked to keep its own height
  was the one element given a height nothing had measured for it. Alone in a
  group it came off worse still: that group was never measured, so it was
  handed `undefinedpx` - an invalid declaration the browser drops, which is
  why the simple case looked correct and only the row the attribute was
  written for misbehaved. The opt-out is now decided once per element per
  resize and holds for the assignment as much as for the measurement. New
  suite `tests/nino-ui-autoheight-js-smoke.js` drives a row of three cards
  with the middle one opted out, and a fourth alone in its own group, on a
  mobile client and then on a desktop one.

- **The public stylesheet's two main font stacks were one family name nobody
  has.** `--fontfamily-text` and `--fontfamily-title` in `_nino/Nino.css`
  wrapped the entire stack in single quotes, and a quoted value in
  `font-family` is one family name rather than a list: the browser looked for
  a face literally called `-apple-system, BlinkMacSystemFont, "Segoe UI",
  Roboto, sans-serif`, found none, and never reached the `sans-serif` at the
  end because that keyword sat inside the string too. Measured in headless
  Chromium against the file itself - a canvas set to the computed value drew
  the same string at 349.22px, to the pixel what a family name invented for
  the test drew, where the stack unquoted drew it at 380.21px. So every page
  the framework styles without a theme, and every page before its webfont
  arrives, fell back to the browser's standard serif instead of the system
  sans the file asks for. The quotes are gone from both. `--fontfamily-code`
  beside them was never quoted, and the three stacks of the base unit's
  `theme.css` quote only the webface they ship, which is the shape both files
  now have. `tests/install-smoke.php` reads every `--fontfamily-*`
  declaration of both files and checks that none is one quoted name and that
  each ends in a bare generic family.

- **A locale a project dropped pinned its default in the visitor's session.**
  `Locales::init()` takes care never to write the default it resolves into the
  session, and says why: a locale nobody chose would outlive a later change of
  the project's native locale. It then handed the locale it found in the
  session to `setCurrentLocale()` - which writes what it is given - and for a
  visitor whose stored locale the project no longer offers, what it was given
  was that very default. `init()` broke its own rule, in the two lines below
  the comment stating it: the next boot read the default back out of the
  session and let it win, so a project that changed its native locale never
  reached the visitor who had once picked a language it has since dropped. The
  stale locale falls back through `useLocale()` now - applied for this request,
  remembered nowhere - and what the visitor actually chose is left in their
  session untouched, so it is theirs again if the project offers it again.

- **Half an answer came back as a whole one where curl is not installed.**
  `\Nino\Fetch` falls back to php's own stream wrapper when the curl extension
  is missing, and that half had no answer for a server that sends its headers,
  part of a body and then goes quiet. `fread()` reports a used-up read timeout
  by answering `false`; the read loop took that for the end of the body, and
  the truncated answer was returned as `ok` with status 200 - a catalogue that
  is half a json document, an archive that is half an archive, both described
  as complete, with the signature check left to be the only thing that noticed.
  Measured against a local socket server that stalls mid-body: `ok: true`,
  `status: 200`, `body: "PARTIAL-BODY"` after the timeout had passed. A read
  that failed is a failed transfer now - no body, the reason in `error`, and
  the timeout named where the stream recorded one - which is the shape the curl
  half has always answered a stall in.

- **A log sweep could delete nothing at all, and say nothing about it.**
  `RotatingLog::prune()` - the one sweep behind the error log, the form
  submissions, the activity log and the backup retention - built a glob pattern
  out of the directory it was given, and a directory is a path rather than a
  pattern: a project installed below a name carrying `[`, `]`, `*` or `?`
  ("site[2]") had those characters read as syntax, so the pattern described a
  path that does not exist and every sweep found nothing, for the life of that
  installation. The directory is read with `scandir()` now and the prefix and
  suffix are matched as the literal strings they are. The second half was the
  same silence from the other end: the date was always parsed as a full
  `Y-m-d` with a monthly `Y-m` padded out to the first of the month, so the two
  formats the kernel itself passes were the only two that worked - any other
  one a caller named parsed as nothing, matched nothing and deleted nothing.
  The caller's own format is the contract now, parsed behind a `!` so that a
  field the format does not set is the epoch's rather than today's - which is
  what kept a monthly bucket anchored to the first of its month, and is now
  what keeps every other format anchored too.

- **A textfill at the hard length limit could be stored with half a
  character.** `Text::sanitizeValue()` cut an over-long value with `substr()`,
  and the limit it cuts at is a byte count - what the file on disk has to stay
  under. A byte offset lands inside a multibyte character as readily as between
  two, so a value that reached 20000 bytes in the middle of one was written
  into `/text/<locale>.php` ending on half of it: a text file that is not UTF-8
  any more. Nothing refused it - every reader substitutes U+FFFD for that byte
  instead (the panel's own json, `htmlspecialchars()` with `ENT_SUBSTITUTE` on
  the page, an export), so the word came back from the editor broken and saving
  it again wrote the replacement character in for good. The cut is
  `mb_strcut()` now, which backs off to the last character boundary: the same
  byte limit, at most one character less of it, and a value that is still UTF-8
  whether it ends on an umlaut or an emoji.

- **A session php refused to start was a 500 nothing could explain.**
  `Runtime::init()` starts the session a visitor already carries, and it has
  to: a session cookie's flags are fixed at `session_start()` time and cannot
  be retrofitted afterwards. That puts the start before `AppData::init()` has
  read config.php - so when php raised its own warning about an unusable
  `session.save_path`, an engine-raised level is fatal here, and
  `handleError()` knew neither `/nino/error/log` nor `/nino/error/display` yet:
  every request carrying a session cookie ended in a bare 500 with nothing on
  the page, nothing on stderr and nothing in the log, and `startSession()`'s
  own documented `return false` was unreachable code. The failure is silenced
  and re-raised through the framework's own non-fatal channel now, with the
  reason php gave, and `AppData::prepareSession()` carries the two error
  switches into that window beside the session keys it already read - from the
  project's file where it has an opinion, from `AppData::DEFAULTS` where it has
  none. A site whose session storage is broken answers its pages and says so in
  the log instead of going dark. Nothing is loosened by carrying on: a csrf
  token that cannot be stored never matches the one a form sends back, so that
  check fails closed. A project that has no config.php yet is left exactly as
  it was - seeding an error switch there would create the private directory
  before the wizard puts its deny rule into it, and an unfinished install must
  keep saying nothing at all.

- **A key a request did not carry was persisted as null, and a stored null is
  not an absent key.** `AppData::writeContentData()` wrote `$appData[$key] ??
  null` for every key it was handed, so a caller naming one its own appData
  never carried - or one it deliberately unset - stored an explicit null in
  config.php. `AppData::init()` merges that file *over* `AppData::DEFAULTS` key
  by key, where a key the file does not carry leaves the framework default
  standing and a key carrying null overwrites it. One such write, and
  `/nino/cache/ttl` was null on every later boot for the life of the file
  instead of the 3600 the project never decided against - with nothing in the
  file, the panel or the log to say where the value had gone. A named key the
  request does not carry is taken back out of config.php now: absent in memory,
  absent on disk, which is what unsetting one before naming it already meant.
  The accounts remain the exception they were - a record missing from a
  request's own `/nino/auth/user` is a deletion its three-way merge has to
  decide about, not an absence.

- **A feature's manifest could pull the transients into every backup.**
  `\Nino\Backup`'s docblock calls `auth-tries.php` and `ratelimit.php`
  transient throttling counters rather than data, and they were kept out by
  not being listed - which held while the list was literals and stopped
  holding when a manifest became a source of paths. `'/data/'` passes
  `str_starts_with( $file, '/data/' )`, so a manifest naming the directory
  itself had its whole tree walked: both counters, the catalogue cache and
  the lock directory, in every backup, and written back by a restore.
  Restored `auth-tries.php` re-locks an account somebody already waited out;
  restored `.locks` plants lock files for requests that ended weeks ago. The
  manifest validator refuses the data root now, and `Backup` keeps the promise
  where it makes it rather than by what its list happens not to mention -
  those three names and anything hidden are never carried, whatever asks. A
  trailing slash in a claim no longer doubles in the archive name either.

- **A route with a `script-src` of its own left the jstext nonce in a
  directive nothing reads.** `Modules\Jstext` appended `script-src 'self'
  'nonce-…'` to the policy unconditionally, and a repeated directive is not a
  merge: the first occurrence is the one a browser enforces and every later one
  is ignored. A route may declare header fields of its own - that is what a
  route's `header` is for - so a project that hardened the policy on one route
  got its own `script-src` enforced, the nonce ignored, the inline jstext block
  refused as an unlisted inline script, and `Nino.content.getText()` answering
  `''` for every key on that page. Silently: the page renders and the policy is
  honoured, only the words are missing. The nonce now goes into the policy's own
  `script-src` where it has one, and the directive is appended only where it
  has none. A `script-src` of `'none'` is left alone - that value is a decision
  against inline scripts, and a nonce beside it would overturn it rather than
  merge with it.

- **A reply address nothing had checked went out as the `Reply-To` header.**
  Every other address `\Nino\Mail` puts on a header line is validated - `$to`
  with `FILTER_VALIDATE_EMAIL`, refusing the mail, and `_getSender()` the same
  for `From`, which it drops rather than "passing something unchecked to
  sendmail". The reply address had neither, and it comes from where those two
  do: an admin-editable textfill read through `renderHtml()`. A fill a project
  never installed renders as its own literal, so `[[/form/email/owner]]` was
  sent as the header verbatim - and that fill belongs to the Form module's
  install unit, which the wizard offers rather than always installs, so a
  project running the Newsletter feature without the contact form did that on
  every confirmation mail. It is dropped now, with a line in the error log
  naming the value; the mail still goes out, because the recipient and the
  body were never the problem. A real address is untouched, the display-name
  form included - valid for this header, unlike `mail()`'s own `$to`.

- **Sending a contact form pinned a locale the visitor never chose in their
  session.** The owner's notification goes out in the site's native locale
  whatever language the form was filled in, and `Form::send()` made that switch
  - and the switch back - with `Locales::setCurrentLocale()`, which writes what
  it is given into the visitor's session. What the switch back wrote was
  whatever `getCurrentLocale()` answered, and for a visitor who has chosen
  nothing that is the project's default. `Locales::init()` takes care never to
  persist that default, and says why: it would outlive a later change of the
  project's native locale. One inquiry wrote it there anyway, and from then on
  `init()` read it back and let it win - so a project that changed its native
  locale never reached the visitor who had once written in. The switch is
  `Locales::useLocale()` now, new beside `setCurrentLocale()`: the same
  verify-and-apply, for the render this request is doing, remembering nothing.

- **A response body `json_encode()` refused was answered as an empty 200.** It
  returns `false` for a body it cannot encode, `false` went into the response
  body, and `echo false` sends nothing - so the answer was a 200 carrying a json
  content-type and no body. Every `_apiCall` in the workbench reads that as a
  success with nothing in it: a blank panel, no message, and nothing in the log.
  One byte of malformed utf-8 anywhere in the body was enough, and a recorded
  log line is built from what a panel posted, so one such byte in an element
  name blanked the activity log for good. Malformed utf-8 is now substituted
  (`U+FFFD`, what a browser would show for it anyway) instead of costing the
  whole response; what cannot be substituted - `Inf`/`NaN`, a resource, a
  recursion - is answered as the failure it is, with the reason in the body and
  a line in the error log. A status a handler already set to a failure is kept.

- **A request that named no uri was not answered at all.** `REQUEST_URI` is not
  guaranteed by the cgi environment - `php-cgi` under IIS composes none - and
  reading the absent key is an undefined-key warning, which is fatal in Nino.
  An empty one got further and fared no better: `cleanUri()` cut the path with
  two `strtok()` calls, and `strtok()` answers `false` for a string of nothing
  but delimiters, so `''` and `'#'` raised a TypeError inside `Http::request()`
  before anything could answer. `strtok()` also skips leading delimiters, so
  `'#frag'` resolved to `'frag'` - the fragment standing in for the path. The
  cut is `strcspn()` now, which is total, and both keys are read with a
  default: an unnamed method matches no route, an unnamed uri is `/`.

- **`Nino.http.sendRequest()` never mapped a network failure to a status code.**
  It documented 500 for a failed request, 408 for a timeout and 499 for an
  abort, and set them by assigning to `xhr.status` - which is a getter on
  `XMLHttpRequest.prototype` with no setter, so the assignment was a silent
  no-op. The comment two lines above it says precisely that about
  `xhr.response`. What every caller got instead was the browser's 0, the same
  for all three, and the panels that print the number printed it: "the login
  endpoint answered 0 - the credentials were never checked." The three codes are
  now defined as an own property on the instance, which shadows the accessor; a
  served answer keeps the status php sent it.

- **A radio group was submitted as its last member, whichever one the visitor
  ticked.** The shared `.nino-form` script collects a form by walking every
  `input`, `textarea` and `select` and writing `data[name]` for each - and a
  radio group shares one name, so the last member overwrote the answer. Someone
  picking the first of three options had the third submitted, stored and mailed.
  A required group was worse: a radio's `.value` is never empty, so the required
  check never fired for one, and a question nobody answered went through as
  answered. Both handlers had it - the contact form and the newsletter signup.
  Only the ticked member now carries the answer, an unticked one never
  overwrites it, and a required group is asked of the group.

  Neither `Form::TYPES` nor the Forms feature declares `radio`, so a form built
  through either could not produce one. A hand-written `<form class="nino-form">`
  in a template could and can - which is what `docs/development.md` describes as
  the ordinary way to write one.

## 1.3.0-beta — 2026-09-18

### Changed

- **`\Nino\VERSION` is `1.3.0-beta`.** The published `v1.2.0-beta` tag and
  this tree both called themselves `1.2.0-beta` while the feature contract
  moved on underneath: `Features::manifest()` learned to read a sectioned
  `manual` map after the tag, so the tagged kernel refuses every one of the
  24 published manifests - measured, all 24 - while every constraint they
  carried (`^1.0`, `^1.1`, `^1.2`) is satisfied by it. Two different
  contracts under one version number cannot be told apart by a constraint,
  which is the whole mechanism a constraint exists for. The catalogue's
  manifests move to `^1.3` in the same change, so a kernel that cannot read
  a manifest is refused before the archive is fetched rather than after.

- **`Features::applyUnit()` hands back a unit's config defaults instead of
  writing them.** It takes a fourth accumulator, `&$config`, beside
  `&$routes` and `&$blacklist`, and the caller persists them. config.php
  cannot be both written inside this method and decided by the caller under
  one lock, and the caller is the one that needs it (see the activation fix
  below). For the wizard's Setup step it is also one full rewrite of
  config.php fewer per unit that brings any default: they go with the keys
  that step writes anyway.

- **Two docblocks that documented nothing.** A method renamed or moved away
  from its docblock leaves the block behind, and the next member's own block
  lands directly under it - php takes the second, and the first rots where it
  stands describing something that may no longer exist. `Features` carried
  one for a method that has since been renamed and rewritten, which is gone;
  `install/Install.php` carried one for `_perRouteTemplate()`, which had no
  docblock of its own, and it is back above that method. A rule in
  `kernel-smoke.php` holds the whole tree to it: no docblock directly
  follows another.

- **The catalogue's shipped key is described as what it is.** The comment
  above `Catalogue::PUBLIC_KEY`, `key()`'s `@return`, the class docblock and
  both manuals still said the constant was empty until Nino's first key
  existed, and that no catalogue is accepted until one is configured. The
  constant holds a key, and an empty `/nino/catalogue/key` falls back to it -
  so a stock installation does verify, against the kernel's own. The
  "no key" refusals in `Catalogue::fetch()` and the Features panel stay:
  they are the fork and key-rotation case, which is what the suite's own
  comment says they are for.

- **A model's whitelist and blacklist compare strictly.** A list is a list of
  values, and `'1'` is not `1`: a value is refused unless the list holds it in
  the field's own type. A model whose list was spelled in another type than
  its field - `[ '1', '2' ]` under an integer - accepted those values before
  and refuses them now; spell the list in the field's type.

- **The shell's markup is properties now, not strings four methods build.**
  `AGENTS.md` allows exactly one shape for markup in PHP - a fragment with
  `[[tokens]]`, declared once as a named property - and gives the reason:
  being a property is what lets somebody change how a thing looks without
  reading the class that decides when it appears. The rail, the pane wrapper,
  the tab strip, the mount points and the language switcher were concatenated
  inside `navHtml()`, `panesHtml()`, `_paneContent()` and
  `_localePickerHtml()` instead, and so were the `[csrf]` hidden input and the
  `[image]` `<img>` - the latter already copied once as "the existing
  pattern". They are `\Nino\Admin\Panels::$html`,
  `\Nino\Admin\Admin::$html`, `\Nino\Modules\Csrf::$html` and
  `\Nino\Modules\Images::$html`, the same shape
  `\Nino\Modules\Navigation::$html` has always had.

  Nothing about the rendered page changes - the suites compare the shell byte
  for byte and did not move. What is new is that the seam is tested as a seam:
  each of the four is replaced in a test and what comes back has to follow it,
  so "a project can replace this" is a check rather than a sentence in
  `AGENTS.md`.

- **Install switches the feature on.** Pressing Install on a feature the
  project does not have placed the directory and left it sitting in the
  Inactive tab, waiting for an Activate - one intention, two presses, and a
  tab to find the feature on in between. `features/install` now follows the
  install with the activation, and what a feature requires comes with it,
  since `\Nino\Features::activate()` walks its requirements itself.

  Two of the three cases were already this: an active feature is activated
  again, which is how an update is applied. The third stays as it was, and is
  the reason the cases are told apart at all - a feature that is on disk and
  switched off stays off. Somebody switched it off, and a newer version of it
  is not them changing their mind; its Activate stays in the row.

  The answer says which happened - `updated`, `activated`, or neither - and
  the panel's word follows it, because somebody told "Installed." goes looking
  for the Activate that is no longer there. Where the files are placed but the
  activation fails, which only an activation can find out (a manifest
  requiring something the directory does not have), the panel answers a 400
  saying both halves rather than leaving a feature in the list for no stated
  reason.

  `\Nino\Catalogue::install()` itself is unchanged and still activates
  nothing: placing files and switching on are two steps, and only the panel
  knows whether the second one was asked for.

  And because it switches a feature on, it now ends the way Activate and
  Deactivate already did: by building the workbench again, with the address
  kept on this panel. The rail, a feature's assets and its words are all
  rendered before the browser is given the page, so a feature switched on
  inside a page built before it existed is a feature with no panel, no styles
  and its fills showing as `[[...]]`. Updating a running feature reloads too -
  applying the update is a re-activation, and what the page holds is the
  version from before it. An install that switched nothing on, which is the
  one case that leaves the shell alone, still just reads the list again.

  What the install did is said in a dialog now, instead of being written onto
  the offer's row. That line could never be read: the offers are recomputed
  against what is on disk on every list, so an installed offer is `current`
  and the Available tab leaves it out - the row the message was written to was
  gone before it rendered. The dialog is what restoring a backup already uses,
  for the same reason, and it is also what makes the reload safe to do
  underneath it.

- **A feature's own screen in the Features panel is two tabs: Description and
  Settings.** Description is the sentence the manifest describes the feature
  with - it used to be the tail of the line under the heading, where a sentence
  appended to a line that gets scanned is a sentence nobody reads - and under it
  the manual. Settings is the form. The line under the heading is identity only
  now: category and version.

  A feature that declares no setting, or describes itself nowhere, has the one
  pane it has and no strip over it: a tab bar with one tab on it is chrome
  around a pane that was going to be shown anyway.

  Both panes are built and one of them is hidden, rather than one pane built per
  switch - a setting typed into and then left to go and read what it does comes
  back with what was typed in it. Hidden is still inside the form, so the one
  Save below both collects the whole schema whichever tab is on; pressed from
  the Description it brings the settings forward first, because a hidden control
  is not focusable and the browser cannot report a failed constraint on one. A
  save comes back to the tab it was saved from; stepping into a feature opens on
  what it is.

  The manual loses the box it sat in, its summary and its 32rem scroll cap: the
  tab is what gets it out of the way, so it is as long as it is. And a section
  with entries is one list rather than a row of two-column lines, which is what
  actually puts every handle of a section in one column - a grid per entry sizes
  its first column to its own handle, and a column that starts somewhere else on
  every line is not a column.

- **A feature's manual is a reference card now, not prose.** One section per
  kind of thing a feature can add - `shortcodes`, `markup`, `routes`, `panel`,
  `callbacks`, `install` - and one line per entry, with the handle somebody
  types beside it:

  ```php
  'manual' => [
      'shortcodes'  => [ '[catalog]' => [ 'en_US' => 'The list.', 'de_DE' => 'Die Liste.' ] ],
      'markup'      => [ 'data-catalog' => 'On a container the script should fill.' ],
      'routes'      => [ '/api/catalog' => 'The public JSON endpoint.' ],
      'panel'       => [], 'callbacks' => [], 'install' => [],
  ],
  ```

  What a developer does with a feature's manual is *look something up* in it -
  which shortcode, which route, what the panel is called - and prose makes that
  a read rather than a glance. Every feature answering the same questions in the
  same order is worth more than any one of them answering them well. The panel
  draws every section including the ones left empty: "no callbacks" is an
  answer, and a reader who does not find the question has to go and read the
  source to learn that the answer was nothing.

  Three things deliberately have no section. The **description** is the first
  line of the same tab and the **settings** are the other one, with the labels
  and hints the manifest declares - writing either again is writing it
  differently. And the **PHP a feature exposes** is a README question: this tab
  is what an operator opens to find out what arrived on their site.

  `markup` is the one section that is not something Nino registers - an
  attribute, a class a script looks for, a `<script type="text/plain">`. Several
  features add nothing else, and without it their manual would be empty while
  they are the ones with the most to say.

  The older prose form is still read, so a catalogue written before this keeps
  working; it is no longer the one to write. `docs/features.md` has the
  reference, the recipe's manifest example follows it, and
  `tests/features-smoke.php` holds every feature in a checkout to the sectioned
  shape - a schema half a catalogue follows is not a schema.

- **`_nino/Nino.css` puts its own design decisions in one cascade layer,
  `@layer nino.base`.** An unlayered rule beats a layered one whatever its
  specificity, so every stylesheet outside that file - `assets/theme.css`, a
  project's `assets/style.css`, a feature's own - now overrides Nino's
  defaults by existing rather than by out-specifying them.

  The measurement that prompted it, against the classes a Design part set may
  write (single class, no nesting, which is how the sets are authored):

  ```
  section  80 rules, 27 reachable (33%)     atf      59 rules, 23 (38%)
  article  40 rules, 24 reachable (60%)     buttons  25 rules, 19 (76%)
  ```

  Two thirds of what Nino set for a section could not be reached at all:
  `.nino-section-title {}` loses to `.nino-text-center > .nino-section-title`
  by one class, and the way out was `!important` or doubled selectors in every
  set. Doing this before the part sets are written is much cheaper than after -
  a set authored against the old cascade would have to be revisited.

- Four things stay **outside** the layer on purpose, each marked where it sits:
  the scroll-driven header (a header preset's stylesheet is unlayered, so a
  layered collapse rule would lose to every preset and no bar would ever
  collapse again), the focus ring (an accessibility floor, not a look - WCAG
  2.2 SC 2.4.7), the back-to-top button, and sections 08 *Javascript Elements*
  and 09 *Utilities* wholesale. `tests/kernel-smoke.php` walks the file and
  holds each of them to that side, because a rule that slid into the layer
  later would not break anything visibly - it would only stop winning.

- **No project has to migrate anything.** Nothing outside `Nino.css` changed;
  among themselves and against those four, the unlayered stylesheets compete on
  specificity exactly as before. Verified in a browser against the Design
  preview: a set's single-class rule now beats Nino's two-class rule, the same
  selector injected unlayered beats the set again (so the difference really is
  the layer and not specificity arithmetic), the utilities still centre a
  title, the focus ring is still drawn, and the header still collapses from
  90px to 0 on scroll.

- **The wizard's second step is called "Languages".** It asks about locales, and
  the module picker beside them stays hidden until a project module with an
  install unit is found - nothing in a fresh checkout - so the step is named for
  the half that is always there. The rail label changed with 1.2.0-beta; the
  manuals had not followed, which left `docs/getting-started.md` pointing at
  `setup.md#2-setup`, an anchor that no longer existed. Heading, anchors and
  both Getting Started tables now match, in English and German, and
  `tests/install-script-js-smoke.js` holds the manual to the rail's numbering
  and refuses a step link that resolves to nothing. The step *key* is still
  `setup`, and so are `Setup::units()`, the `setup/apply` action and
  `\Nino\Install\Setup` - only the label is the narrower name, and the docs say
  which is which.

### Fixed

- **Two spellings of one file took two locks.** `_resolvePath()` answers one
  path for several virtual ones by design - a missing or repeated separator
  is nothing, and `/private/data/x.php` is the same file as `/data/x.php`,
  which is what `CONTENT_DIR`'s indirection is for. The lock was keyed on
  the caller's spelling instead, the sidecar being `sha1()` of it, so one
  file had as many locks as it had spellings and two call sites naming it
  differently serialized against nothing: measured at 61 of 120 concurrent
  updates surviving, against 120 of 120 with one spelling. The lock key is
  canonical now, folded only where the two spellings really do resolve to
  one file - so `/private/config.php` and `/config.php` stay apart wherever
  `NINO_CONFIG_DIR` points elsewhere, and `/private/.auth/` keeps its own
  key. It stays a *virtual* path, so every already-canonical name hashes to
  the sidecar it always did and `Modules\Cache`'s own cleanup still finds
  its lock file.

- **An activation reverted what its own upgrade hook had written.**
  `activate()` read the stored routes, then applied the unit and called the
  feature's `upgrade()` hook, and then persisted the copy it had read at the
  start. `docs/features.md` invites that hook to migrate the project's own
  config, with the kernel's own `mutate()` - and the activation that called
  it silently undid the migration. It took no second request: one process,
  one activation, the hook's work gone, as long as the unit had a route of
  its own to add (without one the key was not written at all, which is why
  this went unseen). The same copy was persisted for `/nino/modules` and
  `/nino/features`, whose window was the whole request rather than a few
  lines, so a parallel change to either was lost as well. All three keys are
  now written as the differences they are, against config.php as it stands
  at lock time, in one locked read-modify-write; the module list is decided
  by what the file holds rather than by what this request believed.

- **Removing a symlinked feature blamed the file permissions.** A feature
  reached through a symlink - a checkout linked into `features/`, which is
  how one is developed - was not removed at all: `Filesystem::removeDir()`
  will not follow a link, which is right, but it did not remove the link
  either, and the `is_dir()` check after it followed the link and still said
  yes. So the operator was told the web server may not write there, which
  was neither the reason nor anything they could act on. A linked-in feature
  now goes by dropping the link, and what the link points at is left alone.

- **A number too wide for an int was stored as `PHP_INT_MAX`.** A setting's
  `int` accepted any run of digits and cast it, and PHP's cast saturates
  rather than failing. A setting without a `max` kept that value; one with a
  `max` did refuse it, but named a bound the value had never been near. The
  digits are held against what the cast made of them now, so
  `9223372036854775807` still passes and one more digit does not; `007` and
  `-0` are unaffected.

- **A refusal on the feature that was asked for was announced as a
  requirement's.** The install loop labelled a failure by the plan's size
  rather than by which entry it was on, and the plan carries every missing
  requirement ahead of the feature the project pressed Install on. So as
  soon as one requirement came along, a refusal for the feature itself read
  `required feature "<its own key>": ...` in the panel. It is labelled by
  identity now.

- **An archive whose manifest does not parse raised instead of refusing.**
  A manifest is php the archive brought, and php that does not parse throws
  where an invalid one returns null - out past the `restore_error_handler()`
  below the read, so the closure that silences a bad manifest stayed on the
  handler stack for the rest of the process, and out past the cleanup, so a
  copy of the archive was left below `data/` for good, one more on every
  retry. The panel got a 500 rather than the refusal. Both are now what they
  promised: the read is caught and answers the ordinary refusal, and the
  staging directory goes in a `finally`.

- **A link entry in an archive was installed as an empty file.** The look
  before the extraction asked `isLink()`, which `PharData` answers `false`
  to for every entry a tar can hold - it names link entries as plain files
  of no size - so that guard never fired once, and the look after the
  extraction found a plain file rather than a link to object to. The result
  was inert, but `docs/features.md` promises "plain files and directories
  only" and the archive still got as far as being unpacked. The wrapper is
  asked whether it will open the entry instead, which is the question it can
  answer. A hard link entry still passes; it opens.

- **A callback that threw kept the file locked for the rest of the request.**
  `Filesystem::mutate()` released its lock on both of its own exits - a
  callback answering `null`, and the write at the end - and a throwable had
  no exit at all: it walked past every `unlockFile()` there is, and the
  handle is held outside the cache slot on purpose, so nothing else dropped
  it either. A data file that is not the array a callback's signature asks
  for, or one that no longer parses, therefore left an exclusive lock on it
  that nothing released until the request ended, while every other process
  wanting that file waited. Two callers already swallow such a throwable and
  render the page anyway (`Form::record()`, the error log), so the request
  did survive to hold it. The lock now comes off whichever way the callback
  leaves, and the throwable carries on unchanged.

- **A damaged lockout counter shut the recovery door.** `Recovery::verify()`
  typed its `mutate()` callback `array $state`, but `$default` answers only
  for a file that is not there - a file that is there answers with what it
  holds, and an empty, truncated or unreadable `lockout.json` decodes to
  `null`. That was a TypeError, so the one entry point left to somebody
  locked out of the workbench answered 500 until the file was repaired by
  hand. The callback takes `mixed` and normalises, the way `AGENTS.md` asks
  of a `mutate()` callback.

- **A checkout carried no `features/` and no deny rule for it.** `.gitignore`
  excluded the directory rather than its contents, and an exclusion of a
  directory cannot be undone from below - git stops there and never reads
  what is inside - so `features/.htaccess` had never been shippable, however
  the rule was written. What the manuals, `AGENTS.md` and
  `tests/features-smoke.php` all say a checkout ships, no clone had. On a
  server that meant the tree was served rather than denied, since the root
  `.htaccess` forwards only what does not resolve on disk and a feature's
  files do resolve; `Catalogue::install()` creates the directory on the way
  in, and a directory made that way carries no rule. The exclusion names the
  contents now, the rule is in the repository, and the three suites a clone
  could not pass - `features-smoke.php` and the two that read the directory
  before their first check - pass.

- **The Language panel did not load.** Its `showCurrent()` builds the form
  once, gated on a `_ready` flag the first answer sets - and the flag was
  never declared, so the gate compared `undefined` and the panel built zero
  times rather than once. Declared now, and a check holds every panel that
  gates on the flag to declaring it.

- **A bracket in a page name was filled in the menu.** `[navigation]` escaped
  a generated entry's name as text but left its `[` alone, and what a
  shortcode returns is rendered again - fills and shortcodes included - so a
  name carrying `[[/some/fill]]` was expanded in every menu that lists the
  page. The brackets are entities on the way out now, as they are everywhere
  else an editor's words reach a page.

- **A hidden tab panel or filter item could show.** `.nino-tabs-panel` and
  `.nino-filter-item` carry a `display` of their own, and an author rule
  beats the browser's `[hidden] { display: none }`. The stylesheet says it
  for both.

- **A regenerated asset bundle kept the url a browser was still holding.**
  `/public/.cache/style.css` is the same address before and after a rebuild,
  so a visitor with the old copy cached kept being served it, and the way out
  was a hard reload nobody knows to do. The url carries the bundle's own hash
  now - the one `_createCachefile()` already writes into its first line - as a
  query, so the file on disk keeps its single name and nothing piles up beside
  it.

- **A callback that was not callable was dropped in silence.**
  `registerCallback()` returned without a word, so a hook registered with a
  renamed method or a typo simply never fired and nothing anywhere said why.
  It raises an `E_USER_WARNING` naming the hook now - the kernel's "record
  this and carry on" channel, so a bad registration still cannot take the page
  down.

- **The private root was one directory stored under two keys.**
  `./nino/filesystem/contentpath` resolved everything addressed through the
  `/private/...` prefix and `./nino/filesystem/privatepath` everything
  addressed as one of the `PRIVATE_DIRS` - the same directory, reached two
  ways, with two places for it to be named. Nothing ever set them apart,
  because `\Nino\init()` wrote both from one value; had anything moved one,
  the templates would have followed and the recovery secret, the backups and
  the logs would have stayed behind. One key now, and `getPrivatePath()`
  answers what `getContentPath()` answers.

- **Filtering the submissions list rebuilt it on every keystroke.** Every card
  built again, every value decoded again, and then a
  `scrollHeight`/`clientHeight` read per card to find the ones that overflow -
  which forces a layout each time. The cards are built once now and the two
  filters toggle a class, which also means the search field no longer loses
  the focus it had to be given back.

- **The shipped header template passed an argument nothing reads.**
  `[navigation nav="main" burger title="..."]` - the navigation shortcode
  reads `content`, `callback`, `id`, `class` and `nav`, never `title`.

- **A settings list of 20 000 lines cost 883 ms to refuse.** The `lines`
  validator de-duplicated with an `in_array()` over the list built so far - a
  walk of that list per posted line - and looked at `MAX_LINES` only once the
  whole post had been through it. So an oversized list was paid for in full
  before being told it was too long: 2.6 ms at 1 000 lines, 61 ms at 5 000,
  883 ms at 20 000. The seen lines are keys now and the cap is checked as each
  line is kept, which is the same threshold reached earlier: 0.04 ms at
  20 000. It is an authenticated endpoint, but a second of cpu per request is
  a second of cpu per request.

- **The wizard re-read its whole page library once per page.**
  `Webpages::pages()` asks `_unitFromBody()` for every route it lists, and
  that scans the library: a `scandir`, a manifest `include` per unit, and a
  text fragment `include` per unit per locale. Seven units and two locales is
  21 includes, and a project with twenty pages paid for that twenty times over
  to render the page list once. Read once per locale set now - the library is
  inside the tool and nothing writes to it at runtime. Per `pages()` call:
  0.72 → 0.08 ms at four pages, 6.95 → 0.91 at forty.

- **Activating a feature re-applied its requirements' units every time.**
  Every file copied or skipped, every text key walked, `config.php` written -
  once per requirement, on every activation of anything that requires it,
  even when that requirement was already on and already at the version its own
  directory carries. A requirement in that state is skipped now; one whose
  record is older than its directory is an update and still runs, and one with
  problems is still refused rather than quietly passed over. And `applyUnit()`
  wrote `config.php` once per config default a unit brought instead of once
  for all of them.

- **Three things the kernel did over again on every page.** Each measured
  before and after.

  `Html::_renderFills()` replaces fills until nothing changes, because a
  fill's value may name another fill. Proving the pass just made was the
  final one meant a whole further `str_replace()` over the document - which
  walks it once per fill key, so a project with a few hundred fills paid that
  many scans to discover nothing had been left. A document with no `[[` in it
  cannot have anything left, and that is one scan for two characters. On a
  16 KB page: 0.55 → 0.32 ms with 50 fills, 1.86 → 0.94 with 200, 4.16 → 2.10
  with 500. The comparison still decides every other case.

  An element read with the `'*'` locale could not hit the read cache at all -
  the early return excluded `'*'` outright - so every one of them went back to
  the type file, walked its locale buckets to find which one holds the element
  and rebuilt the merged array. On a page rendering a collection that is once
  per element per render. What a `'*'` read resolves to is remembered beside
  the element now: one pass over 500 elements went from 1.68 ms to 0.22.

  `Elements::deleteElement()` rewrote the whole type file and fired
  `/nino/elements/committed` even when it had removed nothing - a second
  delete of the same element, a locale that never held it. So a module
  keeping derived data was told about a deletion that had not happened, and
  every cached read of that type file elsewhere was invalidated by the new
  mtime. It is still an idempotent success, which is the contract the suite
  pins; what is gone is the work.

- **Two copies that had drifted apart.** A catalogue entry is a published
  feature manifest, so `\Nino\Catalogue`'s reader and
  `\Nino\Features::manifest()` have to agree about the fields they both read.
  They did not: the manifest side drops a `requires` entry naming the feature
  itself and de-duplicates the rest, the catalogue side kept both, so the
  Features panel could show a feature requiring itself and the same
  requirement listed twice. (The install walk survived it - `_plan()` chains
  what it has already planned - so it was the list a reader sees, not the
  install.) The catalogue side takes the same two steps now, and the three
  patterns it had copied - what a key, a version and a category look like -
  and the line-for-line copy of `localizedValid()` are gone: those are
  `\Nino\Features`' vocabulary, and two spellings of "what a feature key is"
  is one too many.

  `\Nino\Modules\Localepicker::callbackResponse()` was a verbatim copy of
  `\Nino\Locales::callbackResponse()` - every line and every comment,
  differing in the query key alone, which is two places to fix whenever one of
  them turns out to be wrong. The kernel's is `switchFromQuery()` now, taking
  the key as a parameter, and the module is the second caller of it.

- **The workbench asked the server for the same thing twice.** Three separate
  repetitions, each measured before and after.

  The panel registry is a glob over the module directories, a `ReflectionClass`
  per panel class and a `nav()`/`actions()` call each - and one `GET /_admin`
  built it **four times over**: for the asset bundles, for the text fills, for
  the rail and for the panes. Every panel action built it once more. It is
  built once per request now, keyed by what it is built from, so a feature
  switched on through the Features panel - which adds a module and its panel
  inside the request that switched it on - still gets a fresh one.
  `Admin::modules()` reads its directory once per process for the same reason.

  Opening the Elements panel fetched `elements/types` **twice**.
  `_refreshTypes()` stands down while a types request is in flight, and
  `init()`'s callback cleared both halves of that guard - `_loading` and
  `_ready` - before calling `_showTypes()`, which is what reaches it. So the
  list the callback had just rendered was fetched again. `_loading` is cleared
  last now.

  The Config pane is in the page on every workbench load, hidden, and its
  script bound `init()` to `ready`: `config/list` was fetched for a screen
  nobody had opened, and when the workbench did land on Config the shell's
  `show()` called `showCurrent()` while that first request was still in
  flight, so the form was fetched twice. `showCurrent()` is the one entry
  point now, which is the contract `script.js` documents.

- **Seven comments in the kernel and the workbench were written in German.**
  A docblock that names the screen it belongs to tends to name it in the
  language the screenshot was taken in - "Elemente nach Typ", "Letzte
  Aktivität", "überall abmelden", and three drill-down docblocks naming the
  panel bar as `System/Texte/Elemente`. Each reads fine to whoever wrote it
  and not at all to the next person. They say what the English interface says
  now, and `tests/kernel-smoke.php` reads every comment in `_nino/` and
  `_admin/` and names the ones that are German - by an umlaut *and* by a word
  list, because "Elemente nach Typ" has no umlaut in it and an umlaut alone
  would flag `Nino.ui.js`'s `de` locale table, which is the point of that
  table.

- **Two module stylesheets wrote rules over the whole workbench.** A panel's
  `admin.css` is bundled into the same page as every other panel's, so a
  selector that starts at a bare class is not that panel's rule - it is the
  workbench's. `.admin-form-actions` in the accounts panel and
  `.admin-dashboard-more` in the dashboard's were exactly that, while both
  files' own docblocks promised every selector starts at one of the panel's
  ids. Both start at one now, and the suite reads the selectors out of every
  module stylesheet and names the ones that do not, so the promise is checked
  rather than repeated.

- **The recovery form said whether a secret exists by how fast it refused.**
  `Recovery::verify()` checks a posted secret against the stored hash, and
  `$hash !== null && password_verify(...)` never reached the verify on an
  installation that has none - one whose password file went missing, or one
  the wizard never got as far as writing. The verify is where the time goes:
  bcrypt at php's default cost is around a fifth of a second here, so one
  installation refused in half a millisecond and the other in two hundred,
  and which of the two states an installation is in is then readable off a
  single unauthenticated request over any network. The docblock has claimed
  since it was written that it is not. It is not now: an installation with no
  hash verifies against a pinned decoy, and the null check that decides the
  answer comes after the verify, where it cannot be skipped. Nothing
  authenticates against the decoy, and the tests compare its algorithm and
  cost against what `password_hash()` produces on the php running them, so a
  php that moves `PASSWORD_DEFAULT` on fails a check rather than quietly
  leaving the decoy cheaper than a real hash.

- **The signed-in address went into the workbench's rail as markup.** The
  `[[/nino/auth/user]]` fill carried the account's mail address exactly as
  stored, and `filter_var()`'s `FILTER_VALIDATE_EMAIL` - the check every panel
  that writes an address runs - accepts a quoted local part, so
  `"<script>alert(1)</script>"@example.com` is an address they take. Drawn
  into `page-index.tpl` as it stood, it was a script in the shell of every
  account that loads the page, put there by anyone who may create or rename
  one. The fill is escaped where it is registered now, with its brackets
  neutralized along with it: a fill is substituted before the shortcode pass
  runs over the finished document, so a `[` left standing in one would be
  read as syntax afterwards.

- **A burst of parallel guesses was never locked out.** Every request of the
  burst passed the login's cooldown check before the first of them filled the
  bucket, and the failed attempts registered after that treated the lock they
  found as a fresh first try: the account was open again the moment the
  `maxtries`'th attempt had closed it, with the counter back at one.
  `/nino/auth/maxtries` held for guesses made one after the other and not for
  guesses made at once - the case a lockout is for. A registration that finds
  a running lock now leaves it alone; the other buckets of the same attempt,
  the client's ip among them, are still counted.

- **A manager could set the password of a wider account.** Handing out a role
  is bounded by holding it: an account that may manage users cannot create or
  promote an account into a permission its own lacks, since the account it
  creates is the one it signs in as next. The password field of an existing
  account had no such bound - the same manager could give a developer account
  a password of their choosing and sign in as that. `users/save` now refuses a
  password on an account holding a permission the manager's own does not,
  with a 403 naming it. The address stays changeable, since a rename grants
  nothing, and an account editing itself has proven its current password.

- **A feature whose directory sits outside the project had no panel at all.**
  `Panels::relative()` answers a project-relative path for everything a panel
  says about its own files, and knew one root: the project. A project that
  points `NINO_FEATURES_DIR` somewhere else - which `index.php` documents, and
  which `\Nino\Filesystem` addresses through the virtual `/features` prefix
  either way - got `''` back for the exact line the manuals tell a feature
  panel to write, and `''` was dropped in silence. The pane was a blank mount
  point, every label a raw fill key, and nothing in the log said why. The
  features directory is the second root that function answers for now, and an
  asset path that is not a project path is named in the log rather than
  dropped.

- **The safety snapshot of a restore could not be restored.** Before
  overwriting the project, a restore writes an encrypted copy of the current
  state - "so a wrong choice is itself undoable", which the panel's confirm
  text and both manuals repeat. It was named `pre-restore-<timestamp>`, and
  the list, `backups/restore` and `recovery.php` all accepted `YYYY-MM-DD` and
  nothing else: the way back out existed on disk and was reachable by ssh. It
  is offered now, after the dated backups rather than among them, and works
  through recovery.php too. It is also the one archive nothing ever pruned -
  every restore a project did stayed as a full encrypted copy, for good. The
  newest three are kept, counted in restores rather than in days, because what
  makes a snapshot worth keeping is that it is recent in restores.

- **An install that placed files but failed to activate left no log line.**
  The shell records an action only where it answered 200, and this one answers
  400 on purpose - the directory is already the new one, and saying so is the
  half a failure still has to report. So the placement, which is files written
  into the project by somebody at a time, went unrecorded whenever the
  activation behind it failed. It is recorded with the reason the activation
  gave.

- **The wizard dropped unapplied routes on Back and forward.** The Routes step
  keeps its list in the browser until Next posts it, and applying the
  Languages step marks it stale - so pressing Back to change a locale and Next
  again replaced the whole list with the server's, and every route added or
  edited since was gone without a message, on the very gesture the step goes
  out of its way to keep an open form alive for. It takes the templates,
  locales and navigations from the answer (which is what Back may have
  changed) and keeps its own list whenever it carries edits.

- **The slider's controls were drawn over the bottom of every slide.** The
  40px the stylesheet reserves for them is `padding-bottom`, and the page sets
  `box-sizing: border-box` for everything - so the height `Nino.ui.js` measures
  and writes onto the slider had that padding taken out of it again, and the
  absolutely positioned track ran into the band the controls sit in. Measured
  in Chromium: the controls overlapped the track by 25px, and by 0 after. The
  slider sizes its own box as content now, which is what the padding was
  written for.

- **A rejected form told the visitor their email address was wrong when it was
  not.** `\Nino\Form::TYPES` has `url`, `number` and `select` in it, and the
  server refuses all three - the client checked neither, so a url typed as "my
  site" came back as a 400 and the form said "please enter a valid email
  address" over an address that was fine. A url and a number are checked
  client-side now, by the browser's own verdict rather than a second regex of
  ours, and a 400 on a form that carries one of those types asks the visitor
  to check their entries instead: the new fill `/form/info/invalid`. A site
  installed before this has no such fill; until it adds one in the Text panel,
  that 400 shows the address text as before rather than nothing.

- **The workbench lost what was typed when you looked something up.** The
  shell documents that switching panels never resets anything - "jumping back
  and forth is always exactly where you left it" - but four panels answered
  its `showCurrent()` by re-running `init()`: Config, Language, the Users
  panel's Login protection tab and Features. A ticked switch, a typed number,
  a half-entered locale code, a feature's settings field: the answer came
  back, the pane was emptied and rebuilt from the server's values, and the
  edit was gone without a word. They now build once and stay; the actions that
  change state re-fetch on their own, the way the Elements panel has always
  done it. Features also fired two list requests on a page opened at
  `#features`, because the shell's ready binding runs before the panel's own.

- **The Features filter moved the caret to the end on every keystroke.** The
  panel is drawn again per keystroke and the focus put back by hand - at the
  end of the value, so an edit in the middle of a word ("newsletter", Home,
  "s") sent the next character to the end instead, and a Backspace after a
  mid-string click deleted the last character rather than the one before the
  click. The selection is carried across the redraw now.

- **A CSV export dropped every column the first row did not have.** The
  Submissions panel deliberately lists several forms in one view, and the
  header row was `Object.keys( rows[0] )`: with a quote-request entry first,
  every contact-form-only field of the rows below it was missing from the
  file, and one entry recorded before ids existed took `id` and `form` down
  with it for everything after. The columns are the union of every row's keys
  now, in the order they first appear.

- **Three smaller ways the workbench said nothing.** An element's heading kept
  the old language's title when the locale select was switched, because the
  lookup that updates it asked for an id the heading never carried. Clicking
  an element type whose file no longer parses did nothing visible at all: the
  error was written into the pane the list was covering. And a replacement
  image kept showing the old picture, because the stored name is deterministic
  per slot, so the browser answered the unchanged url from its own cache while
  the panel said "saved".

- **A locale switch mid-save was still possible in the Text panel.** The guard
  that disables the form while a save is in flight queried `#text-edit-form`,
  and the locale select and the back link are appended to the toolbar beside
  it - so neither was ever disabled. Changing the locale while the first of
  two queued requests was out built fresh fields for the other locale and sent
  the second request from the older snapshot: the screen said "saved" over a
  field whose text was not persisted. The guard queries the whole `#text-form`
  wrapper now.

- **An editor could put a private template on a public page.** `\Nino\Html`
  renders fills first and shortcodes after them, over the finished document,
  so whatever a stored textfill carries is read again as markup of that page.
  `\Nino\Text::sanitizeValue()` stripped tags and encoded quotes but left
  brackets alone - so `[template /templates/mail-owner]`, typed into a heading
  in the Text panel, rendered that template to every visitor: its copy, and
  the owner address the mail templates carry. `[elements /type]` emptied a
  collection onto the page the same way.

  The Text panel is an editor's - `/_admin/text/manage` is the permission the
  shipped Editor role holds - and an editor edits words. What a page includes
  is a developer's decision, so a stored value may name another fill, which is
  deliberate and the renderer resolves it, and every other bracket is now an
  entity. Both branches, plain and rich. Neither entity contains a bracket, so
  a value re-saved through the panel is unchanged, the way the quotes beside
  them already were.

  `AGENTS.md` has stated the rule all along - "if untrusted content can
  contain `[`, it can otherwise become a new fill or shortcode on the next
  rendering pass" - and `\Nino\Modules\Elements`, Search and ProtectedArea
  each neutralize their own. This was the one input that did not.

- **Every derived image is a webp now.** webp is not a third option beside png
  and jpeg - it is a better container for both answers the encoder was already
  giving. So the branch stays exactly as it was, and only what it writes
  changes: lossless where png would have been, lossy where jpeg would have
  been. Measured on this gd, 1600x1000:

  | | png | jpeg | webp |
  | --- | --- | --- | --- |
  | a photograph | 2930 KB | 199 KB | **151 KB** lossy |
  | line art | 24 KB | 242 KB | **1 KB** lossless |

  Not lossy for both: on line art that is 67 KB here, larger than png and soft
  into the bargain, which is the whole reason the branch exists. Lossless also
  carries the alpha channel, so nothing is given up on the png side either.

  A gd that cannot write webp keeps png and jpeg, as does a project that sets
  `'/nino/images/webp' => false` - for a client older than webp, or a pipeline
  downstream that expects those two names. The shipped `.htaccess` declares
  `image/webp` for a host whose own `mime.types` predates it; php's development
  server answers the type by itself, and nginx has carried it since 1.11.

  A side effect worth having: a re-upload that used to change the output format
  changed the filename with it, and the panel had to delete the orphan. Both
  branches write `.webp` now, so a replacement overwrites in place and there is
  no orphan to miss.

- **Every webp upload was stored as a png, twelve times its size.** The output
  format was chosen from the source type: png, gif and webp all *can* carry
  transparency, so all three were answered with png. For png and gif that guess
  does double duty - what arrives as one is usually line art, and jpeg would
  soften exactly the edges that matter. For webp it is simply wrong: webp is
  what phones and export tools write for photographs. Measured on a 1600x1000
  photograph, 3185 KB as png against 264 KB as jpeg - for every derived size,
  on disk and over the wire, for every visitor.

  A webp says outright whether it has an alpha channel, so that is read now
  instead of assumed: `VP8 ` is the simple lossy chunk and never has one, `VP8L`
  carries the flag behind its dimensions, `VP8X` in its leading flags byte. An
  opaque webp becomes jpeg, one with alpha stays png, and a container this does
  not recognize is treated as if it had alpha - that costs bytes, where the
  wrong answer the other way would flatten a transparent logo onto black. png
  and gif are untouched.

- **One byte that was not UTF-8 deleted the value it was in.** Eight escapes in
  the kernel and the workbench spelled their flags out as `ENT_QUOTES` or
  `ENT_NOQUOTES` - and spelling them out drops php's own default, which has
  carried `ENT_SUBSTITUTE` since 8.1. Without it `htmlspecialchars()` answers
  invalid UTF-8 with `''`: one Latin-1 byte anywhere in an element field, a
  maintenance notice, an image `alt` or a link target - out of an import, a
  feed, a paste - and the whole value rendered as nothing. Silently: no
  warning, no log line, and the page looked merely empty.

  `AGENTS.md` has required `ENT_QUOTES | ENT_SUBSTITUTE` all along, so this was
  drift from a written rule rather than a missing one, and it had already bitten
  twice before - `\Nino\Form` stored and mailed a submission as nothing, and
  that was fixed where it was found rather than as a class. All eight now carry
  the flag, `\Nino\Html::sanitizeHtml()`'s serializer among them, which is the
  road every rich field takes. `tests/kernel-smoke.php` greps both trees for the
  pattern, so the ninth is a failing test rather than a report.

- **A preview kept every `ResizeObserver` it ever made.** `scaleFrame()` created
  one per call and disconnected none, so a caller that re-fits the same box left
  the previous observer attached and still firing. Design rebuilds the scaler on
  every width change and every preview it loads: after n of them, one drag of the
  window edge ran the fit arithmetic n times, and nothing ever took one away. The
  port carries its observer now, and a second call replaces the first.

- **The login form called a server's `403` and its own the same thing.** Both
  said "the login endpoint answered 403 - that is a server configuration",
  which is right for one of them and sends the other looking in the wrong
  place: a token that went stale while the page sat open is fixed by
  reloading it, not by a hoster ticket. Every answer the kernel composes
  carries a `Content-Security-Policy` (it is in `\Nino\Http`'s default
  response header set) and a server's own error page carries none, and a
  same-origin xhr may read that header - so the form reads it and says which
  of the two it got. `docs/deployment.md` already told an operator to make
  that distinction with `curl`; the form makes it before anyone has to.

- **The deployment manual read a missing `NINO_HTACCESS` as proof.** The
  variable is set by the shipped `.htaccess`, and the manual read its absence
  as "the file is not applied at all - `AllowOverride` is off". That is one
  of two causes: some FastCGI setups and PHP wrappers never pass `SetEnv`
  through to the script and leave the variable empty while every rule in the
  file is in force. Read the old way, a host like that sends an operator to
  fix an `AllowOverride` that was never wrong. A `1` still proves the file
  applies; its absence decides nothing, and the manual now names the test
  that does - an address that certainly does not exist answers with Nino's
  own 404 page, policy header and all, exactly when the forwarding rule
  applies. Both manuals and the go-live checklist.

- **On Apache, every address Nino owns under a dot answered `403`.** The
  shipped `.htaccess` denies dotfiles so that `.env`, `.git/config` or an
  editor backup can never be served. It did so with a bare `<FilesMatch
  "^\.">`, and Apache stops its directory walk at the first component that
  does not exist and tests `<FilesMatch>` against that one - so
  `/.nino/auth/login` was denied as `.nino` and `/.form` as `.form`, before
  PHP saw either. That is the workbench login, the contact form, the
  newsletter and the protected area, on every Apache install this file
  applies to, while every ordinary address reached `index.php` normally -
  which is what made it look like a rule of the host rather than the
  project's own file.

  The deny is conditioned on the path resolving to something on disk now,
  which is the line `router.php` and the nginx recipe in
  `docs/deployment.md` both already drew: a dot path that is a file stays
  denied, a dot path that is a route reaches the front controller.

  Measured against Apache 2.4.58 with the shipped file, before and after:
  `/.nino/auth/login`, `/.form`, `/.newsletter`, `/.protected` and
  `/.demo-catalogue` went from `403` to reaching `index.php`; `/.gitignore`
  and `/.git/config` stayed `403`; `/.cache/style.css` and `/.demo/…` are
  served as before. `<If>` needs no override class the file did not already
  need - `CGIPassAuth` above requires `AuthConfig`, and without that the
  file 500s there long before reaching this.

- **A development install answered 200 for a crash.** With
  `/nino/error/display` on, the handler echoed its dump and `exit`ed - and the
  `header()` that sets the 500 sat after that branch, so it never ran. An
  uncaught exception and an `E_USER_ERROR` both answered `200 OK` with a stack
  trace in the body, where this method's own docblock and `docs/development.md`
  promise 500. Measured with `php -S`: display on answered 200, display off 500,
  for the same throw; both answer 500 now. A crash that reports success is
  worth more than the dump it prints: an uptime check, a devtools filter and
  every `fetch()` that keys on the status believed it.

- **Wrong credentials were answered `200` while another session was live.** The
  login endpoint decided success by asking who is signed in, not by the result
  of the attempt - and `loginUser()` leaves a resumed session untouched when it
  refuses. So a wrong password posted from a tab whose session still held
  somebody was answered `200`/`true` while the attempt itself was counted as a
  failed one. `Nino.js` takes any 200 for a login and redirects, so that tab
  walked into the workbench as the identity it already had. The endpoint reads
  the attempt's own result now; the documented 401 is what a refusal gets.

- **A nul byte in a path was a 500 where a `..` was a `false`.** Every I/O call
  in `\Nino\Filesystem` carries an `@` so a failure comes back as `false` and
  the caller's own "could not be written" message is reachable - but `@` does
  not suppress an exception, and `mkdir()`, `fopen()`, `rename()` and `glob()`
  all throw a `ValueError` for a path containing one. Any caller whose own
  allowlist let a nul through (a pattern without the `D` modifier is enough)
  got an uncaught 500 instead of its own refusal. Both rules now live in one
  place that all seven doors ask, so the sentence in `docs/development.md` is
  true of the whole class rather than of the two that read and write content.

- **A version constraint with an empty alternative was satisfied by every
  kernel.** `'^9.0 ||'` - a trailing, doubled or lone `||` - split into an
  alternative with no parts, and an alternative with no parts held: the check
  that keeps a feature off a kernel it was not written for waved every kernel
  through, in the Features panel and in the catalogue alike. Such a constraint
  is refused when a manifest declares it, and an alternative now has to earn
  its "holds" from a part that held.

- **A catalogue behind a token could never verify.** The signature's address
  was built by appending `.sig` to the whole url, so
  `https://host/catalogue.json?token=abc` was fetched as `...?token=abc.sig` -
  a 404, reported as "the catalogue signature could not be fetched" with
  nothing saying the shape of the url was the cause. `.sig` goes on the path
  now, and the query and fragment stay where they were.

- **A third of cached pages kept a dead nonce in their script data.** The
  `[jstext]` nonce reaches a page twice - raw in the script tag, and
  JSON-encoded in the block beside it - and `json_encode()` escapes a `/` as
  `\/`. A base64 nonce carries one about a third of the time, and the page
  cache re-stamped the raw form only, so a stored page kept the render-time
  nonce in its JSON for as long as the entry lived. The nonce is hex now: 16
  bytes either way, and no character JSON touches.

- **A request that posted an array where a string belonged was a 500.** Four
  of them in the workbench and the wizard, all reachable by typing `[]` into a
  field name: `?locale[]=x` on `/_admin` raised "Array to string conversion" -
  a level the runtime treats as fatal - with a log line per hit;
  `action[]=x` reached `isset( $actions[$action] )`, which is an "Illegal
  offset type" TypeError, where the answer is the 404 two lines further down;
  `data[]=x` reached `json_decode()`, which takes a string, and that path is
  open before any authentication, since `recovery.php` reads its password
  through `postData()`; and the wizard's own dispatcher had the same
  `action[]=x`. All four read their value as a string or not at all now, and
  answer the way they always meant to.

  `\Nino\Features::manifest()` had the same shape in a different place: `key`,
  `version`, `nino` and `module` were cast before they were checked, so a
  manifest with an array in one of those fields took the request down one line
  before the reader could say what was wrong with it. A manifest is a PHP file
  somebody put in `features/`; refusing it with a sentence is the whole job of
  that function.

- **Every visitor got a session file and a cookie, whether or not they had a
  session.** `Runtime::init()` started the PHP session on every request, for
  every anonymous page view and every crawler hit alike. Two things followed
  from that: a file under `session.save_path` per first request, kept until
  PHP's garbage collection gets to it, and a `Set-Cookie: PHPSESSID` on a
  session that in the great majority of those requests never held a single
  value - which is also a cookie a banner has to declare.

  The session is started when something writes to it now: a CSRF token being
  minted (rendering a form, or checking a post), a login, a visitor picking a
  language. A visitor who arrives with the cookie still gets their session
  started up front, because Auth reads its token to resume a login.
  `\Nino\Runtime::startSession()` is the one call that starts one; every write
  goes through it, and it answers `false` where there is nothing to start.

  Measured with `php -S` against a two-page project: before, a plain page
  answered `Set-Cookie: PHPSESSID`, `Expires: Thu, 19 Nov 1981`,
  `Cache-Control: no-store, no-cache, must-revalidate`, `Pragma: no-cache`
  and one session file per request; after, it answers with none of them, the
  same page with a `[csrf]` in it answers with the cookie and one file, and a
  visitor who has a session keeps the one they have.

  Those four cache headers were PHP's, sent by the session's cache limiter.
  Whether a response may be stored is not a side effect of having a session,
  so `\Nino\Http`'s default response header now says it: `Cache-Control:
  no-store`, the same answer as before for every page. A project that wants
  its public pages cached by browsers and proxies changes that one value.

- **Behind a reverse proxy, every visitor was the same visitor.** The client
  address is the TCP peer, and behind Cloudflare, a load balancer or an
  ingress that peer is the proxy - the same address for everybody. Every
  per-ip rule in the site reads that one value, so they all counted the
  internet as one client: the login cooldown's ip bucket, `\Nino\Mail`'s send
  cap, a form's rate limit and the address a session is listed under. The
  cooldown was the sharper end - fifty wrong logins against account names
  that need not exist put the one address every visitor shares into cooldown,
  and every admin's correct password was refused for the hour. The mail cap was
  the one that bit first and quietest - five contact-form submissions from
  anyone locked out every visitor's mail, newsletter confirmations included,
  for the rest of the window, and a rate-limit refusal is not surfaced as an
  error.

  `/nino/http/proxies` names the proxies in front of the site, as exact
  addresses or CIDR ranges, and the Config panel's **Reverse proxies in front
  of this site** edits the same list. Where the peer is one of them, the
  visitor is the rightmost `X-Forwarded-For` hop that is not itself a listed
  proxy; everything left of it is never read, because a forwarding proxy
  appends to that header rather than checking what is already in it.

  The list is what makes the header believable, which is why the key exists
  at all rather than the header simply being trusted: `X-Forwarded-For` is an
  ordinary request header any client can write, and read unconditionally it
  would have handed the cooldown bucket, the log's ip field and the session
  list's address to whoever asked. Empty is the default and keeps the previous behaviour
  exactly - the header ignored, the peer the answer. A line that is neither an
  address nor a CIDR range is refused on save rather than stored, because it
  would match nothing while the form reads as configured.

- **A slider could not be operated with a keyboard.** Its previous and next
  controls were `<div>`s with a click listener and its dots were bare `<li>`s
  with one - reachable with a pointer and with nothing else: no tab stop, no
  Enter, no Space, and a screen reader announcing `‹`. All of them are real
  buttons now, each with a name, and the dot showing says that it is.
  Measured in Chromium: the controls were not tab stops at all, and are now.
  The words come from `/slider/label/prev`, `/slider/label/next` and
  `/slider/label/slide` - shipped in both languages, overridable per slider
  with `data-slider-label-*`, and falling back to the language the page
  declares. The classes the stylesheet paints are unchanged.

- **The `..` rejection was a layer with two doors.** The development manual
  promises that `Filesystem` refuses a path containing `..` as an additional
  protective layer, and only `getFileContent()` and `putFileContent()` made
  the check - `fileExists()`, `path()`, `url()`, `forceDir()`, `lockFile()`
  and `mutate()` resolved a traversal and handed it on. Every door refuses it
  now; `path()`, `url()` and `forceDir()` say so in the log, the reads, the
  writes, the existence check and the lock refuse as quietly as they would a
  missing file.

- **A hand-written account could 500 the login form.** This framework's own
  account class says that status, sessions and permissions are a
  developer-only, direct-JSON task - and then read both keys as if they were
  always there, so a record written by hand as a hash and a permission list
  raised a warning the runtime treats as fatal. `\Nino\Auth::getUser()` fills
  both in, once, for every caller.

- **`NINO_CONTENT_DIR` was ignored in silence.** The constant was renamed to
  `NINO_PRIVATE_DIR` in 1.1, and AGENTS.md still named the old one - so a
  deployment written against it left the password hashes, the sessions and
  the data under the document root while the operator believed the private
  tree had been moved out of it. The retired name now fails at boot with a
  message naming its successor.

- **A pasted closing tag cut the rest of a value off.** The HTML sanitizer
  parsed a value inside a `<div>` of its own, so the first unbalanced
  `</div>` - which is what pasting from a web page looks like - closed that
  wrapper, and everything after it was read as standing outside the value and
  dropped, silently, on save. The wrapper is a tag no HTML has.

- **One value that is not a string closed three panels.** `\Nino\Text::entries()`
  measured every stored value with `strlen()`, so an `int` a developer had
  written into a text file - a year, a count - raised a `TypeError` under
  `strict_types`, and the Text, Text Keys and Language panels all answered
  `500` until somebody found the line. A scalar reads as the text it stands
  for; a value that is no text at all is left out.

- **`alt=""` was not an empty alt.** The shortcode argument parser judged
  "was there a value?" by the value itself, so `alt=""` - which is how a
  decorative picture is written, and how AGENTS.md writes one - arrived as a
  positional argument and the picture kept the image slot's label as its alt
  text. So did `name="name"`, any value that happens to equal its own name.

- **A type whose element names are long and not ASCII emptied the Elements
  panel.** The line under a type names its elements and was cut at 150 bytes,
  which can land inside a multibyte character - and `json_encode()` answers a
  string that is not valid UTF-8 with `false`, so the panel's whole reply came
  back empty. The cut lands on a character boundary, and the type prefix is
  stripped only where it is a prefix.

- **A select could not offer numbers.** PHP stores a numeric string array key
  as an `int`, so a feature declaring `'12' => 'Twelve'` as a select option
  had its whole manifest refused - the feature vanished from the Features
  panel with a message contradicting what its author had written.

- **A login finishing late wrote its stale copy of every account back.** Every
  write to the accounts persists the whole key from the copy its own request
  booted with, and only the sessions were merged with what the file had
  meanwhile become. So a login that started before an administrator's change
  and finished after it wrote its boot-time copy of every record over that
  change, and an account created in between disappeared. The records are
  merged too now: what a request changed is written as that request left it,
  what it never touched is taken from the file, an account created while it
  ran is kept, and one it deleted stays deleted.

- **A restore replaced `config.php` in place.** It is the one file every
  request reads at boot, and it was written with a plain
  `file_put_contents()` - so a request booting mid-write read a truncated
  file, which either fatals or comes back as something that is not an array,
  and every visitor was told the configuration is broken until the write
  finished. It is written beside the file and renamed over it, under the same
  lock every other writer of that file takes.

- **Two editors saving two pages at once kept one of them.** Saving, deleting
  and moving a route each read the routes, decide against what they find and
  write the whole key back - and only the write was under a lock, which is
  after the decision. So the second save wrote its own full copy over the
  first one's page. All three hold the lock across the read, the decision and
  the write.

- **A type URI with a trailing slash found nothing.** `queryElements()` looked
  the type file up with a trimmed copy of the URI but built every hit by
  gluing the URI as given to the element's name - so `/articles/` asked for
  `/articles//slug` and came back empty, and `articles` came back with that
  spelling in every hit's `.uri`. The URI is normalised once, where the query
  starts.

- **A query could not find an element whose boolean is off.** A stored `false`
  became the empty string on the way into the comparison, so `live=0` matched
  nothing at all while `live=1` worked. A boolean compares as `1` or `0`.

- **A type URI read back as an element.** The type files and the elements read
  out of them shared one cache, both keyed by URI - so once a type had been
  read, asking for the type's own URI as if it were an element handed back
  that type's whole locale bucket, every element in it, as one element. They
  are two caches now, dropped together as before.

- **Two requests inserting the same element merged into one.**
  `insertElement()` asks whether the element is there before it takes the lock
  the write holds, so both requests passed that look and the second one's
  fields landed in the first one's element. The look happens where the write
  does now; the one before it stays, since it is what makes the ordinary
  refusal cheap.

- **A PHP deprecation took the site down.** Every level the engine raises was
  fatal, deprecations among them - so a PHP minor upgrade could answer `500`
  where nothing was wrong, and intermittently at that: a compile-time
  deprecation fires only on the run that recompiles the file, once per opcache
  lifetime. A deprecation says a future PHP will do something differently, not
  that this request went wrong. It is recorded like every other non-fatal
  level and the request carries on. Every other engine level still stops.

- **A month's error log grew without bound.** The log is one array per month,
  rewritten whole on every entry under an exclusive lock, and only whole
  months were ever dropped - so a template raising a notice per view grew the
  file with the traffic until the request that had to read all of it to add a
  line was itself what took the site down. A month keeps its newest 1000
  entries.

- **Every page carried the site's whole text, the form's mailbox included.**
  The `[jstext]` block serialised every fill the site has into an inline
  script - the legal copy, the addresses, and `/form/email/owner`, which is
  the mailbox a contact form delivers to - while the scripts reading it only
  ever ask for two groups. It carries what was published to it now:
  `/form/info/` and `/newsletter/info/` as shipped, `/_admin/` where the
  workbench serves itself, whatever `/nino/jstext/keys` adds in `config.php`,
  and whatever a module or feature registers with
  `\Nino\Modules\Jstext::publish()`. A published key is public, and the
  development manual says so.

- **The maintenance page shipped a script its own policy refused.**
  `Modules\Maintenance` answers from the response callback at priority 1 and
  ends the request there, while the policy naming the inline script's nonce
  was composed at priority 5 - so a maintenance page, which renders the
  site's own footer and with it `[jstext]`, carried a script the browser then
  blocked. The policy is composed at priority 0, ahead of everything that can
  end a request.

- **An editor's language name could put a script into the workbench.** The
  language switcher took the name of each language from a text fill and
  wrote it into the shell's markup as it stood. That key is editable in the
  Text panel, so an account holding only the Text permission could put markup
  into the page every other account is served, its own session included. The
  name is escaped for the text it is, brackets included, the way a navigation
  label already was.

- **The rich-text editor ran what a stored value carried.** A field the model
  released for html was assigned to the editor's `innerHTML` when a record was
  opened. The save path sanitises, but a record written by hand, by an import,
  or by a module that writes elements without the panel does not pass through
  it - so opening such a record ran whatever it held, with the editor's own
  session. Measured in Chromium: an `onerror` handler in a stored value ran.
  The value is parsed inertly and rebuilt as the five tags the editor knows,
  with a link keeping only an href the same rule the server applies accepts.

- **A page name containing a colon rewrote the link's markup.** A menu line
  is `<uri>:<title>`, and the parser split it on every colon - so the second
  colon of an ordinary page name ("Angebot: Sommer") ended the title and
  opened the third field, which is written into the `<a>` tag as attributes.
  A name typed in the Text panel decided what the markup said. A generated
  line is split once now, and the page's name is escaped on the way into the
  page, the way an editor's words are everywhere else. A hand-written line in
  the shortcode's own body is the template author's and keeps all three
  fields, verbatim.

- **The wizard's Accounts step never showed what it had to say.** Its message
  element is shown by the pane class of its step, and the stylesheet named
  `show-admin` while the shell sets `show-accounts` - so every message that
  step writes, "mail already in use" included, was written into an element
  with `display: none`. The test that now stands over it compares the two
  lists rather than the one spelling.

- **A page of your own could take a library page's template file.** A page on
  the Blank template is written to `templates/page-<its own uri>.tpl`, so one
  called `/home` lands on `templates/page-home.tpl` - the file the library's
  home page owns, with a route body identical to that page's. The wizard then
  read it back as the home page and, on the next apply, wrote the library's
  home page over whatever had been built in it. Such a name is refused where
  it is typed, naming the library page that has it.

- **The burger navigation could not be opened with a keyboard.** The control
  behind the label is a checkbox, and the stylesheet gave it `display: none` -
  a control that is not rendered is not focusable either. So on every narrow
  viewport the site's whole navigation could be opened with a pointer and by
  nothing else, which also takes out everything that drives a keyboard.
  Measured in Chromium: the checkbox was not a tab stop at all, and is now.
  It is hidden rather than removed, and the icon carries the focus ring the
  control has nothing left to show one with.

- **Nothing said what a visitor who asked for less motion gets.** The
  parallax has honoured `prefers-reduced-motion` since it was written; the
  arrow that bounces at the foot of a hero, the bouncing helper class, the
  spinner, the viewport animations and the smooth scroll to an anchor did
  not. They stop, arrive without arriving, and jump, for a visitor whose
  system asks them to. A viewport animation becomes plainly visible rather
  than merely un-animated, so an element whose script never ran is not left
  invisible.

- **A query variable could take the page's scripts down with it.** `Nino.http
  .readQueryVars()` handed every value straight to `decodeURIComponent()`,
  which answers a stray `%` with a `URIError` - and a query variable is
  whatever somebody put in the address. A key without a value read the
  literal string `undefined`, an empty query produced a variable named `''`,
  and a value carrying its own `=` was cut at it. Text that is not valid
  percent-encoding is now the text itself, which is what the address bar
  shows anyway. A `+` in a value is a space now, as
  `application/x-www-form-urlencoded` and php's own `$_GET` read it; it used
  to stay a `+`.

- **The resize and scroll throttle throttled nothing.** Both listeners set
  their gate, asked for an animation frame and cleared the gate again in the
  same breath - so every event of a burst got a frame of its own, and a
  scroll was one callback round per event rather than one per frame. The gate
  is cleared inside the frame now. A resize callback was also handed whatever
  the last scroll event had left behind, which on a page nobody had scrolled
  was `false`.

- **One throwing scroll callback stopped every scroll behaviour on the page.**
  `Nino.ui`'s scroll gate was cleared after the callbacks had run, so a
  callback that threw left it shut for good: the scrolled-header class never
  came back, the parallax froze, and anything a project or a feature had
  registered stopped with it. The gate is cleared in a `finally`. The one
  callback in this file that could throw - the parallax offset on a box with
  no picture in it - no longer does.

- **A slider without slides threw out of the whole UI setup.** A `.nino-slider`
  with no `<ul>` yet, or one with nothing in it, made the setup read the slide
  at the start position and throw - so every tab strip, form and filter
  further down the page stayed unwired. Such a slider is left alone.

- **Any visitor could grow the page cache without bound.** A cache entry is
  keyed by the address as asked for, and a wildcard route (`GET://blog/*`)
  answers an unbounded set of them - so every made-up address under one became
  a page-sized entry plus an empty lock file beside it, as fast as an
  anonymous client could send requests. What a wildcard route covers is
  answered live now; the wildcard's own address is a route of its own and is
  still cached.

- **Pages that could never be served were stored anyway.** A page whose route
  has a handler of its own is never answered from the cache, since that would
  skip the handler - but only the serving side knew it. The storing side wrote
  an entry on every anonymous view and told the response it was a `miss`:
  entries that could only ever be written, never read. The rule holds on both
  sides now.

- **An expired entry was read and rejected on every request** until something
  dropped the whole cache. It is deleted the first time it is found stale.

- **Dropping the cache left a lock file per page behind.** Every write creates
  a lock side-car under `private/data/.locks/`, and nothing ever removed one -
  so a directory of empty files grew with every page ever cached and stayed
  after the cache itself was gone. Invalidation takes the side-cars of the
  entries it drops with it.

- **Installing from the catalogue failed on a private root reached through a
  symlink.** `PharData` names every entry of an archive by the archive's
  canonical path, symlinks resolved, while the unpacking cut that prefix by
  the length of the path it had been given. With `private/` behind a symlink
  - a hosting home directory, a bind mount, macOS's `/var` - every entry path
  came out wrong and every install was refused with a message blaming the
  archive. The prefix is the archive's own path now.

- **An archive with a file where the feature directory should be threw out
  of the install.** The look before the extraction accepted a top-level file
  of the directory's name, and the look after it opened that file as a
  directory: an uncaught exception instead of a refusal, and the staging
  directory below `private/data/` left behind. Such an archive is refused by
  name, and everything the extraction is checked for happens inside the same
  guard as the extraction itself.

- **An update activated from the off state skipped the upgrade hook.**
  Deactivating a feature keeps its recorded version on purpose, and
  installing a newer version leaves a switched-off feature off - so the path
  an update of an inactive feature takes is deactivate, install, activate.
  `\Nino\Features::activate()` ran `upgrade()` only for a feature that was
  already on: on exactly that path the record jumped to the new version with
  the migration never run, and no later activation could run it either, the
  old version being gone from the record. The record decides now, not the
  active flag; a first activation still runs no hook.

- **A replaced feature directory could be read back from opcache as the old
  one.** A catalogue install swaps a directory for another at the same paths,
  and opcache looks at a file's timestamp every couple of seconds at most -
  not at all where `validate_timestamps` is off. The request that placed the
  new version could compile the old manifest and class from the new paths.
  Every php file of the directory is dropped from opcache before the swap and
  after it, and before a feature directory is removed.

- **A submission's text could reach the owner as nothing.** A posted value
  was cut at its byte cap with `substr()`, which leaves half of a multibyte
  character behind when the cut lands inside one, and the mail body and the
  stored record were escaped without `ENT_SUBSTITUTE` - so `htmlspecialchars()`
  answered the whole value with an empty string. A message of a thousand
  bytes with an umlaut at the cut, or a client posting Latin-1, went out and
  was recorded as nothing while the visitor saw ok. The cut lands on a
  character boundary now, and a byte that is not UTF-8 becomes the
  replacement character, the way AGENTS.md has always asked for.

- **A submission the mail cap refused was answered ok.** The endpoint set
  `200` and `{status: ok}` before it looked at the flag `\Nino\Mail` raises
  when the per-ip cap refuses a send - nothing had gone out, nothing was
  recorded, and the visitor waited for a reply to a message nobody received.
  Over budget is a `429` now, which the shared `.nino-form` script shows as
  the generic message. With `/nino/form/store` off, a mail no transport took
  is a `500` for the same reason: nothing has the inquiry. Where a copy is
  kept, the submission still records and the visitor is still told ok, since
  the inquiry is in the Submissions panel.

- **A nul byte in a mail was a 500.** PHP's `mail()` refuses a nul byte in
  any of its arguments with a `ValueError`, which nothing caught - so a form
  field carrying one turned into a bare 500 instead of the `false`
  `\Nino\Mail::send()` promises. The byte is dropped with the CR/LF, from the
  body as well, before any transport sees the mail; and an argument `mail()`
  refuses outright is still answered with `false`.

- **A form key nothing has could land on the first form.** The endpoint
  checked the posted key against the slug shape and collapsed a miss to `''`,
  which is the first form - so a page posting `Quote` for a form defined as
  `quote` was validated against, mailed to and recorded under the contact
  form. The key goes to the lookup as posted, and a miss is the `404` the
  docs promised.

- **A form field name longer than 64 characters could never be submitted.**
  `\Nino\Form::normalize()` accepted names of any length while the endpoint
  reads posted keys of at most 64 characters; the field rendered, the browser
  posted it, and the value was dropped on arrival. The bound is the same on
  both sides now: `normalize()` leaves such a field out, so a definition
  cannot carry one the endpoint would drop.

- **A placeholder inside a submitted value was filled.** The mail body was
  filled pair by pair over the whole string, so `[[date]]` or `[[email]]`
  inside a visitor's message, once inside the `[[fields]]` table, was
  rewritten by the pairs after it - contrary to what the docblock promised.
  The placeholders are filled in one pass now.

- **A fatal PHP never hands the error handler was a bare 500 with an empty
  log.** `set_error_handler()` is not called for the levels the engine raises
  and stops on, and everything Nino offers for diagnosis hung off that handler.
  An exhausted memory limit, an expired `max_execution_time` or a compile-time
  fatal such as a redeclared class produced a `500` with nothing in
  `private/data/logs.<YYYY-MM>.php` and no effect from `/nino/error/display`:
  the failures that most need explaining were the ones that explained
  themselves least, and the deployment manual's promise that the reason for a
  bare `500` is in that log did not hold for them.

  `Runtime::handleShutdown()`, registered by `Runtime::init()`, reads
  `error_get_last()` and reports it through the same two config keys
  `handleError()` reads. No backtrace with it - the stack the request died on
  is gone by the time a shutdown function runs, and `error_get_last()` is
  everything PHP kept of it.

  What this does and does not reach is worth knowing before reaching for it. On
  the PHP 8.4 Nino requires, a parse error in a lazily autoloaded class and a
  call to a function that is not there are a `ParseError` and an `Error` -
  thrown objects `handleException()` has always caught and logged, and they
  were never the silent case. What was silent is the engine's own fatals. A
  failure before `Runtime::init()` has run, PHP failing on `Nino.php` itself,
  stays the webserver's to report and is the one case the log still cannot
  show.

  Reporting an exhausted memory limit takes memory, which is the one thing that
  request has none of: nothing is freed before a shutdown function runs, so
  what is left to write with is the size of the block PHP just refused, while
  what the entry costs is the size of the month's log, read back in and written
  out again. Measured, a fatal refused 132 KiB of hash table against a 600 KiB
  log wrote nothing at all, and left the log undamaged, so not even a broken
  file showed that an entry had gone missing. The handler raises the limit by
  8 MiB before it reports that one - a figure rather than no limit at all,
  because a request that has just proven it will take whatever it is given must
  not be handed the machine on its way out.

- **The front controller forwarded to a relative target, and on some hosts that
  is an internal redirect loop.** `RewriteRule . index.php [L]` reads like the
  portable choice - Apache resolves a relative substitution against the
  directory the file sits in - but the per-directory prefix `mod_rewrite`
  strips is not always the one it puts back. Where it is not, the substitution
  resolves to nothing, Apache retries, and gives up after ten internal
  redirects with a `500`. The target is now the absolute `/index.php`, measured
  on an IONOS host where that one character was the whole difference. The cost
  is the one line a subdirectory install edits (`/shop/index.php`); no form is
  both absolute and location-independent, `RewriteBase` included, so the file
  takes the one that works everywhere and names the edit.

  The shape this leaves behind is distinctive and was worth writing down: `/`
  and `/_admin/` answer normally, because `mod_dir` resolves those two through
  `DirectoryIndex` without a rewrite ever running, and every other address - an
  existing page and a nonsense one alike - is a `500`. No PHP error log and no
  effect from `/nino/error/display`, because PHP is never reached.

  `RewriteRule ^index\.php$ - [L]` now also stands ahead of the catch-all. It
  closes the second way into the same loop: where `%{REQUEST_FILENAME}` is not
  the mapped filesystem path in per-directory context, `!-f` stays true for
  `index.php` itself and the catch-all fires on its own result.

  Both manuals gained the section for it, including the error-log line that
  confirms it (*Request exceeded the limit of 10 internal redirects*) and
  `FallbackResource /index.php` for a host where `mod_rewrite` misbehaves
  beyond this - it does the same job without `mod_rewrite` and cannot loop by
  construction. And the header that says whether a `500` is even PHP's: every
  Nino response carries a `Content-Security-Policy`, an Apache error page
  carries none.

- **The workbench login blamed the password for failures that never looked at
  it.** `_admin/assets/login.js` showed *Check your input or contact the
  administrator* for any answer that was not a `200`, so a `403` from a host
  that refuses a uri with a dot segment, a `404` from a server with no
  forwarding to `index.php`, and a `500` from PHP all read as wrong
  credentials - and the one person who can fix any of them goes looking at the
  account instead of at the server. `401` is the only answer that means the
  pair was read and refused. Everything else now says the endpoint answered,
  and names the status.

  The endpoint is `POST /.nino/auth/login`, a dot uri like `/.form` and
  `/.newsletter` - and a dot path is exactly what a shared host blocks by
  default, which is worth knowing before the password is doubted.
  `docs/deployment.md` gained the table that reads the four statuses, and the
  one request that tells Nino's own CSRF `403` apart from the server's: a `403`
  carrying a `Content-Security-Policy` header reached the kernel, one without it
  never did. It also finally names where a bare `500` explains itself -
  `private/data/logs.<YYYY-MM>.php`, which is the only place it does once
  `/nino/error/display` is off.

  `tests/admin-login-js-smoke.js` is new and drives the form's own branch;
  `tests/admin-smoke.php` additionally holds the shell's two locales to the same
  key set, since a key present in one and missing from the other renders as an
  empty string - a login form with a blank line where the reason should be.

- **On Apache, a fresh install answered `403` on `/` and Nino's 404 page on
  every other address.** The project shipped no routing at all: no
  `DirectoryIndex`, so `/` found no index file on any host whose PHP
  configuration does not add `index.php` to Apache's list, fell through to the
  directory listing and was refused by the `Options -Indexes` two lines above
  it - and no front controller, so `/imprint` was the server's own 404 rather
  than the project's page. `docs/deployment.md` called the forwarding the
  server's job and gave the nginx equivalent only, which is a fair sentence
  where a configuration has to be written anyway and no help at all where the
  `.htaccess` *is* the configuration. Both rules now ship in it, and the Apache
  section documents them, including the `AllowOverride Indexes` that
  `DirectoryIndex` needs.

  The symptom is worth naming because it reads like a refusal and is not one:
  Nino answers no `GET` with a `403` - the CSRF guard leaves the safe methods
  alone and an unknown address is a `404` - so a `403` on a page request is
  always the server's. The 404 on `/index.php` is the project working
  correctly; `/index.php` is not a route.

- **`docs/deployment.md`'s nginx section documented the denials as
  configuration and the routing as prose.** Which is the half that makes the
  site answer at all: nginx returns the same `403` on `/` for the same reason
  Apache does, `autoindex` being off by default, and there `.htaccess` is never
  read - so the identical symptom has an entirely different cause and nothing in
  the project can fix it. Both manuals now carry the complete `server` block,
  with the PHP-FPM socket as the only line left to fill in: `index index.php`,
  `try_files` to `index.php`, the PHP location with `HTTP_AUTHORIZATION`, and
  the dotfile rule written the way `router.php` writes it - denying only paths
  that resolve on disk, so `/.form` and `/.newsletter` keep falling through as
  the routes they are. The fourth denied tree, `_admin/install/library/`, was in
  the bullet list and missing from the blocks; it is in them now.

- **The `/_admin` login was refused on Apache with PHP as CGI or FastCGI**, for
  credentials that were correct. The workbench sends its pair as an HTTP Basic
  `Authorization` header, and Apache hands a CGI/FastCGI script no such header
  unless `CGIPassAuth` is on - a directive that needs 2.4.13 and does not cover
  every php-cgi wrapper. The documented fallback for those hosts is a
  `RewriteRule` copying the header into an environment variable, but Apache
  prefixes a variable set during an internal redirect with `REDIRECT_` (once per
  redirect), and `\Nino\Http` only ever looked at `HTTP_AUTHORIZATION`. So the
  workaround written for exactly those hosts did not work on them:
  `PHP_AUTH_USER`, `HTTP_AUTHORIZATION` and `REDIRECT_HTTP_AUTHORIZATION` all
  empty under SAPI `cgi-fcgi` is what it looked like. The credential pair is now
  read from whichever member of that `REDIRECT_…` family arrives. A client
  cannot reach it: a request header lands as `HTTP_<NAME>`, so the one name that
  could be tried arrives as `HTTP_REDIRECT_HTTP_AUTHORIZATION` and matches
  nothing.
- The shipped `.htaccess` now carries that `RewriteRule` beside `CGIPassAuth
  On`, so a host that needs it has it without editing anything, and it is
  harmless where `CGIPassAuth` already worked - it writes the value the header
  already has.
- `.htaccess` also sets `SetEnv NINO_HTACCESS 1`. Whether the file is applied at
  all is the question behind every "the login does not work" *and* every "why is
  `private/` being served", and `AllowOverride` can switch it off silently - a
  rule that is not applied looks exactly like a rule that is. The deployment
  checklist asks for the variable, and `docs/deployment.md` has the probe that
  reads it along with the three places the credentials can fail to arrive.

### Removed

- **`putFileContent()`'s append mode, and `_appendFile()` with it.** Nobody
  passed the flag - 92 call sites in the kernel, the workbench, the features and
  the suites, and not one of them a fifth argument. It could not usefully have
  been passed either: this class serialises a `.php` file as
  `<?php return ...;` and a `.json` file through `json_encode()`, so appending
  to either produced a file that no longer parses, and the two formats are what
  it stores. Logs are not the exception that justified it - they are dated
  `.php` arrays written whole through `mutate()`, and `\Nino\RotatingLog` only
  ever sweeps them. What is left is one path with one behaviour, and three
  branches fewer around the cache bookkeeping that the append case needed.

- **Dead phone-layout rules in the accounts panel's stylesheet.** A
  `@media (max-width: 38rem)` block placed a list row's `<a>` and `<button>`
  into a two-column grid. There is no button - `_renderList()` gives each row
  exactly one child, the `<a>`, and the panel's Add button lives outside the
  list in the shared action bar - and there is no grid either: the shared
  `.nino-admin-list > li` is a plain block, so the `grid-template-columns` the
  block set on it was ignored, and with it every `grid-column` under it. Three
  rules, none of which a browser ever applied.


## 1.2.0-beta — 2026-09-11

The catalogue, and the form engine. A feature is installed from the Features
panel: the panel loads a signed `catalogue.json` from getnino.dev on request,
offers what fits the running kernel, and installs or updates an archive below
`features/`. The contact form becomes an engine a project defines its own
forms for, with the Submissions panel reading whatever they collect. Three
hooks for features: another mail transport, sorted and paged element lists,
and a seam a submission can be refused at.

And the kernel gets smaller by two. The Template Builder is a feature of that
catalogue now, and the Design panel is gone altogether: a project starts from
one fixed look the setup wizard delivers, and the wizard is six steps instead
of ten. Both are the same bet - what a project may not want should be
something it can add, or leave out, one directory at a time.

### Added

- **`\Nino\Catalogue`** (`_nino/Nino/Catalogue/Catalogue.php`): fetches the
  catalogue and its detached signature (ECDSA P-256 over SHA-256, DER,
  base64), verifies it against the key the kernel ships as `PUBLIC_KEY` or
  the one under `/nino/catalogue/key`, parses format 1 and refuses a
  catalogue that is wrong anywhere, answers `offers()` per key (`available`,
  `upgrade`, `current`, `incompatible`) and `install()`s one version: the
  catalogue fetched again, the archive downloaded with its size as the cap,
  sha256 and size checked, unpacked below `data/.features/` with every entry
  validated (one directory, plain files, nothing outside, bounded), read as a
  feature, matched against key and version and checked to fit this kernel,
  then moved into `features/`
  with the old directory put back if the move fails. Nothing is activated.
  A successful fetch is kept under `data/catalogue.php`; `cached()` reads it
  back with no request of its own, null when there is nothing to show - no
  fetch yet, a file that does not hold what fetch() writes, or a
  `/nino/catalogue/url` that no longer matches (switching the catalogue off
  included).
- **`\Nino\Fetch`** (`_nino/Nino/Fetch/Fetch.php`): the kernel's one http
  client - a GET over https with timeout and byte cap, through curl or the
  stream wrapper, no redirects, certificate verified, user agent `Nino`. A
  test stubs it under `./nino/fetch/stub`. Used by the catalogue and nothing
  else; Nino makes no request unless the Features panel is asked to.
- **Configuration** `/nino/catalogue/url` (Nino's catalogue by default, `''`
  switches it off) and `/nino/catalogue/key` (a PEM public key for a
  catalogue of your own).
- **Features panel:** three tabs - Available, Inactive, Active - each
  labelled with a count. Available is the catalogue: what it offers that is
  not already current, listing the newest version of every published
  feature this kernel can run, with **Install** and **Update**; updating an
  active feature applies the update in the same step, and its offers are
  always recomputed against the features on disk now, so an install shows up
  there without a new fetch. An action bar above the tabs holds **Refresh
  catalogue** - fetched only when pressed, never on its own - and a status
  line naming when the cache is from. `features/list` answers the cached
  catalogue alongside the installed features, so Available fills the moment
  the panel opens with no request of its own; `features/catalogue` refreshes
  it. Where `features/` is not writable, the archive is linked to unpack by
  hand. Actions `features/catalogue` and `features/install`.
- **`\Nino\Form`** (`_nino/Nino/Form/Form.php`): the form engine, lifted out
  of `\Nino\Modules\Form`, which keeps the route `POST /.form` and hands
  every submission here. A project defines its forms under
  **`/nino/form/forms`** in `config.php` - beside its routes and its image
  slots, so they are hand-editable, they travel in every backup, and a form
  needs no file format of its own; defining none gives `DEFAULT_FORM`, the
  contact form this framework has always shipped, field for field. A field
  names one of `TYPES` (`text`, `email`, `tel`, `url`, `number`, `textarea`,
  `select`) and may not take one of `RESERVED`; a definition with no usable
  field left is dropped rather than half-read. `forms()`, `form()`,
  `normalize()`, `posted()`, `validate()`, `handle()`, `send()`, `render()`,
  `record()`, `prune()`, `remove()` and `entries()` are the api a module or a
  feature builds on - the catalogue's Forms feature is a second writer of the
  definitions and a spam guard in front of them, and carries no copy of any
  of this.
- **A seam for refusing a submission:** a module or feature registers on the
  route callback `/nino/http/response/POST://.form` ahead of the module -
  priority 1, what `\Nino\Csrf::init()` already does - and leaves a status
  behind; `Form::handle()` returns without sending or writing anything. No
  callback name of its own, and none needed.
- **Configuration** `/nino/form/retention` (months a submission stays on
  disk, 1 to 60, `RETENTION_MONTHS` without one) and `/nino/form/store`
  (`false` means the mail goes out and nothing is written - a site that
  answers its inquiries and keeps no copy has less to protect).
- **`\Nino\Mail::sendAll()`**: several mails that are one action of one
  visitor - a form's owner notification and the confirmation that answers it -
  for one hit of the per-ip cap. Charging each separately made the cap count
  envelopes rather than submissions, so with two mails per submission and a
  cap of five the third was answered "sent" while nothing left the server.
- **`category`** in a feature manifest, and **`\Nino\Features::CATEGORIES`**
  (`content`, `ui`, `communication`, `marketing`, `security`, `system`): what
  a feature is for, one per feature, what the Features panel groups and
  filters by. Any slug is accepted, not only the six - a feature written for a
  catalogue newer than the kernel running it is filed under a category that
  kernel cannot know and has to install regardless - so the vocabulary is held
  together where features are published. `\Nino\Catalogue` carries the field
  through `parse()` and `offers()` and drops one it cannot read rather than
  refusing the entry: a bad entry costs the whole catalogue, and a category is
  a heading in a list. The catalogue format stayed at 1; its reader takes only
  the keys it knows.
- **`manual`** in a feature manifest, and the box the Features panel opens a
  feature's screen with: the short manual its author wrote - where the
  shortcode goes, what an attribute does - rather than the README, which is
  written for somebody reading the source. Localized like `name` and
  `description`, capped at 10000 characters per language, and rendered as
  paragraphs on blank lines with `` `backticks` `` as code and no other
  markup: the text comes out of a manifest, so every piece of it becomes a
  text node, never html. Open when the screen opens, closed with a click, and
  scrolling inside its own box rather than pushing the settings down the page.
  A feature that carries none gets no box. Deliberately not in
  `catalogue.json`: what a manual answers is asked once the feature is
  installed, and an offer already carries its description.
- **`\Nino\Features::remove()`** and the panel's **Remove**: an inactive
  feature's directory deleted from the workbench, the one step deactivating
  deliberately leaves out. What the feature kept stays - its settings, its
  files under `data/`, whatever its unit copied into the project - so putting
  the same feature back finds its settings where it left them. Refused for an
  active feature: its class is listed in `/nino/modules`, and a directory
  deleted from under the autoloader is a fatal on the next request rather
  than a message. Action `features/remove`.
- **`\Nino\Catalogue::install()` resolves requirements.** It works out the
  whole set first - what the feature `requires`, what those require, and only
  what the project does not already carry - checks every entry against this
  kernel, and places them deepest first, so the feature asked for arrives
  last and can be switched on straight away. A requirement already on disk is
  left as it is, whatever version it has. One the catalogue cannot serve
  refuses the whole install, naming it, with nothing placed. The panel's
  answer names what came along.
- **`/nino/images/render`** (`\Nino\Images::RENDER`): a rendering callback,
  the same shape the mail transport has. `\Nino\Images::process()` and the new
  `\Nino\Images::fit()` fire it after the checks and before the encoding - the
  byte cap, the path, the image type and the pixel cap are what keep an upload
  endpoint safe and are not something a feature switches off by registering -
  with `mode`, the target box, the deterministic `basePath` and the source's
  own dimensions. A handler that wrote the file sets `filename`, `false`
  refuses the upload, `null` passes it on to gd. Where a richer uploader
  belongs - webp, a srcset, an imagick pipeline - rather than a fork of the
  two methods.
- **`\Nino\Images::fit()`**: the whole picture scaled into a box with its own
  proportions kept, and never scaled up. What `process()` cannot be: it crops
  to exactly the dimensions asked for, which is right for a slot with a fixed
  frame and wrong for the large view behind a thumbnail, where cropping is
  what the viewer opened the image to undo. The box goes into the filename
  rather than the result, so the name stays deterministic per slot.
- **`/nino/mail/send`** (`\Nino\Mail::TRANSPORT`): a transport callback.
  `Mail::send()` fires it after the per-ip cap and the header cleaning with
  `{ to, subject, body, replyTo, sender, headers, sent }`; a handler that
  sets `sent` to `true` or `false` replaces `mail()`, one that leaves it at
  `null` passes the mail on.
- **`[elements]`** takes `sort` (`title`, `-date`, `category,-date`: numbers
  as numbers, everything else natural and case-insensitive, an element
  without the field last) and `offset`; `offset` and `limit` apply after the
  callback. `\Nino\Elements::queryElements()` takes the same as a sixth
  parameter `$options` (`sort`, `offset`, `limit`), and
  `\Nino\Elements::sortElements()` orders a list on its own.
- **Tests:** `tests/catalogue-smoke.php` - a keypair generated per run,
  archives written byte by byte (a hostile one too), the network stubbed;
  kernel-smoke covers the transport callback, the sorted, paged query, the
  forms of a project and the seam a guard refuses at;
  `tests/admin-submissions-js-smoke.js` - the Submissions panel's script,
  which had none.

- **`\Nino\Filesystem::path()`** resolves `/features/...` against
  `\Nino\Features::dir()`, so a feature names its own files - a stylesheet or
  script it adds to the project's bundles with `\Nino\Html::addAsset()` -
  as `/features/<Name>/...` and they are found after a relocation with
  `NINO_FEATURES_DIR` too.
- **`\Nino\Modules\Maintenance`** (`_nino/Nino/Modules/Maintenance/`): one
  switch that answers every site page and module endpoint with a 503 for a
  visitor who is not signed in to the workbench, `Retry-After` and
  `Cache-Control: no-store` among the headers. Its `/nino/http/response`
  callback fires at priority 1, before `Modules\Jstext` (5) and
  `Modules\Cache` (9) - `/_admin` keeps working throughout, and a signed-in
  account still sees the site as it is. Configuration
  `/nino/maintenance/status` (bool, default off) and `/nino/maintenance/retry`
  (seconds, default 3600); the page renders `templates/page-maintenance.tpl`
  with the site's own header and footer where a project has installed one, a
  built-in minimal page with the same `/maintenance/title` and
  `/maintenance/text` texts otherwise. The full-page cache is switched off at
  runtime, never persisted, for as long as maintenance is on. Listed in
  `/nino/modules` by the setup wizard whenever its class exists, the same way
  as `Design` and `Templates`; its System panel **Maintenance** sits next to
  Config.
- A fourth workbench navigation group, **Features**, between Structure and
  System (`\Nino\Admin\Panels::GROUPS`). Every panel a feature brings lands
  there regardless of what its own `nav()` names - the registry checks
  whether the panel's class file lies below `\Nino\Features::dir()` and sets
  the group itself - so an editor granted the Features group sees every
  active feature's panel and nothing a kernel or `app/` module placed there
  instead. The group carries no heading while nothing is in it.
- **`assets/style.css`**, the site's own stylesheet, shipped empty by the base
  unit and last in the css bundle. Four manuals already told a project to put
  its own rules there and promised the file is never touched - it was the one
  file in that list that did not exist. Everything else in the bundle is
  replaced wholesale when its choice is made again (the theme, either frame,
  the generated token layer), so there was nowhere to put a rule that survives
  picking another theme.
- **`assets/theme.css`**, the site's whole look in one stylesheet, delivered by
  the base install unit beside the two frame templates it is drawn against
  (`templates/theme.header.tpl`, `templates/theme.footer.tpl`). Four layers
  concatenated in the order the cascade needs them: the compiled design tokens,
  the theme that assigns each one a role, the header frame's rules and the
  footer frame's. It is what the old wizard produced when you pressed Next four
  times and took the defaults - `basis`, byte for byte - and it is now a file
  edited by hand rather than a file a panel rewrites. Setup seeds the bundle
  with it and with `assets/style.css`, appending only what is not there yet, so
  a project that has added entries of its own keeps them where it put them.
### Changed

- `\Nino\Features::constraintValid()` is public - the catalogue validates
  the `nino` field of an entry with it.
- The setup wizard no longer offers Navigation, the locale picker and the
  contact form as a choice: `\Nino\Install\Setup::ALWAYS_MODULES` lists
  their unit keys, and `apiApply()` applies each one's unit and lists its
  class in `/nino/modules` on every run, the same way `Design` and
  `Templates` (`TOOL_MODULES`) already were. The picker (`apiLibrary()`)
  still offers any other module unit - a project's own below `app/`, or a
  fork below `_admin/install/library/modules/` - unchanged.
- A panel naming the `features` group from outside `features/` is refused
  with the existing "unknown nav group" warning and falls back to `content`,
  the same as any other invalid group.
- **Features panel:** a list, not a wall of cards. Every feature is one row
  of the shared grouped list now, so a catalogue of any size stays
  readable. An **active** feature is the shared drill-down row - name, one
  line, chevron - and everything it offers is on the screen behind it: its
  settings, the update waiting for it, and **Deactivate**. The pane is
  `features-detail`, with the workbench's own back link, the settings in
  one fieldset, and Deactivate, Update and Save together in the bar pinned
  to the bottom. The screen survives the reload a save ends in and falls
  back to the list when the feature it is for is switched off elsewhere.
  An **inactive** feature and a **catalogue offer** stay rows with their
  one action - Activate, Install, or the archive link - since there is
  nothing to step into for either.
- **Features panel:** a filter beside the tabs, over name, key and
  description. It narrows every tab at once and the counts narrow with it,
  so a search says which tab the match is on. Tabs and filter share a head
  that stays at the top while the rows scroll - scoped to this pane, since
  the shared tab strip stays deliberately unsticky for panels that drill
  into a context bar. **Active** is the first tab and the one the panel
  opens on, and both `features/list` and `features/catalogue` answer
  sorted by the name a person reads rather than by the key.
- **`\Nino\Modules\Form`** is the route and nothing else now: it registers
  `POST /.form` and hands the request to `\Nino\Form`. A page written against
  that endpoint keeps working, and a project that defines no forms gets the
  contact form it always had.
- **Submissions panel:** it knew four field names - `name`, `email`, `cat`,
  `message` - so a project defining a form with a company and a budget got
  cards showing a date and nothing else. It knows none now: a card shows the
  date, which form the inquiry came from, the address to answer at, and every
  value the entry carries under the label it was collected under, taken from
  the form definition with its fills resolved server-side. A value whose field
  the form has since lost still shows, under its own name. A select narrows to
  one form and a search box searches values and labels, both drawn only where
  there is more than one form; the export writes what they left. A card
  expands to **Delete** for that one submission - `submissions/delete`, the
  panel's first write, guarded by its own permission and written to the
  activity log; an entry recorded before submissions carried an id offers
  none, because nothing addresses it across a deletion.
- **Features panel:** a category select beside the search box, built from the
  categories on screen so it never offers a heading nothing is filed under,
  and dropped when the category it names is gone. The search box reads the
  category too, and the category leads the line under a feature's name.
- **`\Nino\Backup`** carries an active feature's own `data` files and
  directories, named by its manifest, so a backup taken with a feature
  switched on restores what the feature kept.
- **`.nino-form`** posts a checkbox by its checked state rather than by its
  value. An unticked box with no value attribute still reads `"on"`, so every
  box was submitted, mailed and recorded as ticked.
- The Features panel bundles a stylesheet of its own now
  (`_admin/Nino/Modules/Features/assets/admin.css`): the head, the rows,
  and the gap in a row's buttons, which the script builds without
  whitespace between them and which therefore had none.
- **The section composer says each thing once.** The dialog's heading carried
  its own name on step 2, where the preset's name is the more useful of the
  two, and the preset was named again in a card below it with the description
  that sold it in the library. Both panels of that step had a numbered heading
  saying where you already were; every area tab carried "Collection" or
  "Single" under its name; the editor under the lit tab repeated that tab's
  name and its help; and the quick view labelled its one list. All of it is
  gone, and five labels lost the property name they repeated twice per row:
  "Source", "Value", "New textfill", "Data source", "Data field".

### Fixed

- **A header that scrolls away really goes.** `body.nino-scroll-down
  .nino-scroll-header` set `max-height: 0` and nothing else, and max-height is
  the weakest of the four ways a box keeps its height: min-height beats it
  outright, padding is never squeezed below what it asks for, and a border is
  drawn whatever the box does. Every one of the six shipped header presets uses
  at least one of them, so five of six stayed on screen while the page scrolled
  under them and the sixth left its border. The collapsed state takes all four
  back now, rather than every preset having to know the rule exists; a preset
  that is not a bar still opts out where it says so, as the sidebar rail does
  above its own breakpoint.
- **A button's link takes a fragment and a relative path again.** The composer's
  link field was an `<input type="url">`, and the composer is a real form: a
  value like `#prices` or `/kontakt` made the browser refuse the submit before
  the handler ran, so the section could not be saved at all. The field is a
  text input carrying the server's own rule now
  (`AreaComposer::validLiteral`), which refuses a foreign scheme, a
  protocol-relative `//host` and whitespace, and takes everything else.
- **An area bound to an existing Elements type may use that type's field
  names.** `AreaComposer`'s field pattern took lowerCamel alone, while an
  Elements type takes any non-empty key - so a collection with a field called
  `header_image` was refused, and told it was "an unknown model field". With a
  collection the project already has there is no model to be unknown to: the
  pattern is as wide as the `[[fill]]` the binding becomes can carry, and a
  name outside it is refused for what it is.
- **The root size overruled the visitor's own browser setting.** `--base-size`
  was `16px`, stepping to `18px` above 768px, and `html { font-size }` took it
  literally - so someone who had raised their browser's default to 20px because
  they need it got 16 anyway. It is `100%` / `112.5%` now: the same sizes for a
  default of 16, proportional for anyone else. Both files that set it changed,
  and the second one is the one that mattered: the base unit's `theme.css`
  assigns `--base-size` from its own `--nino-base-size`, so a correct kernel
  alone would have been overruled in every real install. `tests/install-smoke.php`
  holds both to a percentage. Measured in Chromium: at a 16px default the
  computed root size is 18px before and after; at a 20px default it was 18px and
  is now 22.5px.
- **A deactivated feature's data was in no backup.** `\Nino\Backup::manifest()`
  skipped a feature that was not switched on, its `data` files with it. But
  deactivating is documented as removing the class from `/nino/modules` *and
  nothing else* - settings and data stay, switching it back on finds everything
  as it was. A backup taken meanwhile did not carry that data, and a restore from
  it left `config.php` recording an installed version whose data was gone: the
  one state deactivation exists to make impossible. Ownership now ends when the
  directory does, not when the switch goes off - a feature that was really
  removed drops out of `\Nino\Features::all()` and stops being carried. The
  catalogue's Newsletter had the same hole.
### Removed

- **The Template Builder leaves the kernel.**
  `_nino/Nino/Modules/Templates/` - 70 files, 10,917 lines, a third of everything
  under `_nino/` - is now the `templates` feature of the catalogue
  [dapeio/nino-features](https://github.com/dapeio/nino-features), installed from
  the Features panel like any other. The code is unchanged: the autoloader
  resolves `features/Templates/AreaComposer/AreaComposer.php` as
  `\Nino\Modules\Templates\AreaComposer` without a line of renaming, because
  below `features/` the `Nino/Modules` prefix is the directory itself. The class
  came out of `\Nino\Install\Setup::TOOL_MODULES`, so a fresh install no longer
  lists it in `/nino/modules`, and `docs/templates.md`, its German half and the
  two recipes travel with it.

  Its panel now sits in the workbench's **Features** group rather than under
  **Structure** - every feature's panel does, so that granting that one group
  stays a bounded grant. Nothing changes for permissions: the panel was in
  `structure` before, which the Editor role never carried either.

  **`\Nino\VERSION` is `1.2.0-beta` for this.** A kernel that still ships the
  module serves its own copy - the autoloader resolves `_nino/` first, on
  purpose, so a shipped module can never be shadowed - and the feature would
  look installed and do nothing. `^1.2` in its manifest is what refuses that and
  says why.
- **Design leaves the core - the whole of it, panel and catalogue.** The site's
  look is no longer something Nino asks about or keeps editable: every project
  starts from one fixed theme (see `assets/theme.css` under Added), and the
  **Design** feature - not written yet - is what will replace it, with a
  catalogue per part of a page rather than one whole-page theme. Removed here:

  - `_nino/Nino/Modules/Design/` - the module, its panel, the token palette
    solver, the appearance catalogue reader and the live preview: 11 files,
    4,449 lines. `\Nino\Modules\Design` is out of
    `\Nino\Install\Setup::TOOL_MODULES`, so a fresh install no longer lists
    it in `/nino/modules`.
  - `\Nino\Install\Themes` in `_admin/install/Install.php` - 1,142 lines, the
    class behind the wizard's Theme, Header and Footer steps. Install.php
    goes from 3,507 lines to 2,391.
  - **Four of the wizard's ten steps.** Themes, Header, Footer and Design are
    gone from the rail, the template, `STEPS` and `_commitStep`, together with
    `assets/themes.js` and `assets/design.js` (1,145 lines) and their ~340
    lines of stylesheet. The wizard is six steps: Environment, Setup, Routes,
    Personal Infos, Accounts, Finish.
  - `_admin/install/library/{themes,header,footer}/` - ten themes, six headers
    and seven footers, 85 files and 3,824 lines. **Parked, not deleted:** they
    sit in
    [`design-library/`](https://github.com/dapeio/nino-features/tree/main/design-library)
    of [dapeio/nino-features](https://github.com/dapeio/nino-features), outside
    `features/`, so `bin/build.php` and `bin/check.sh` never see them - not a
    feature, never published, source material for the one that is coming. The
    two Design manuals (`docs/appearance.md`, `docs/appearance.de.md`, 378
    lines) and the panel's three screenshots went with them, archived under
    `design-library/docs/` for the token contract they document.
  - `tests/design-smoke.php`, `design-js-smoke.js`,
    `install-design-js-smoke.js` and `install-themes-js-smoke.js` - 2,404
    lines, ~350 checks. What is still Nino's is asserted where it belongs:
    `tests/install-smoke.php` holds the base unit to the stylesheet, the
    webfonts and both frame templates it has to deliver, and to a header the
    collapsed state can really take back;
    `tests/install-script-js-smoke.js` holds the wizard to its six steps and
    to leaving nothing of the other four behind.

  Nothing under `_admin/install/library/` is publicly served any more - the
  theme picker's `preview.svg` was its one deliberate exception - so
  `router.php` and both `.htaccess` rules deny the tree whole, with no carve-out
  to get wrong.

## 1.1.0-beta — 2026-09-07

Features. An installable package is one directory below `features/` with a
`feature.php` manifest, switched on in the workbench rather than picked in
the wizard; Nino's optional modules ship with the kernel again, and `app/`
is the project's alone. The features themselves are published from the
catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) -
a checkout ships none.

### Added

- **`\Nino\Features`** (`_nino/Nino/Features/Features.php`): discovers the
  directories below `features/`, reads and validates their manifests, matches
  the `nino` version constraint (`*`, exact, `>=`/`<=`/`>`/`<`/`!=`, `^1.0`,
  `~1.2`, `~1.2.3`, parts joined by comma or space, `||` alternatives; a
  pre-release kernel counts as its release), answers a feature's settings
  with the schema's defaults, validates a posted settings form, and
  activates, updates and deactivates a feature.
- **The manifest** `feature.php`: `key`, `name` and `description` (a string
  or a `locale => string` map), `version`, `nino`, `php => [ 'ext' ]`,
  `requires`, `settings` and `data`. The class is derived from the directory
  - `\Nino\Modules\<Directory>` - and a `module` entry naming anything else
  is refused; a manifest that does not validate is skipped with a warning,
  and so is a second directory claiming a key.
- **Settings** with the types `bool`, `int`, `string`, `text`, `email`,
  `url`, `select`, `secret` and `lines`, each with `label`, `hint`,
  `required` and a validated `default` (none on a `secret`); `min`, `max` and
  `unit` for an int, `maxlength` and `pattern` for a string, `options` for a
  select. Stored under `/nino/features` in `config.php` as
  `key => { version, settings }`, read through
  `\Nino\Features::setting()` / `settings()`; a form posts strings and gets
  real types back, a secret posted empty keeps the stored one and `null`
  clears it, every setting is validated before any is written.
- **Activation** applies the feature's `install/` unit without overwriting
  anything the project has - an existing route key, template, file or text
  key stays, a unit `config` default fills in only where the project has
  nothing - activates required features first, lists the class in
  `/nino/modules` and records the version. Activating an active feature
  applies an update: the unit adds what is new, and a module implementing
  `upgrade( array &$appData, string $fromVersion ): bool` migrates its own
  data first (`false` refuses). Deactivation removes the class and nothing
  else, and is refused while another active feature requires it.
- **The Features panel** in the workbench's System group (permission
  `/_admin/features/manage`, actions `features/list`, `features/activate`,
  `features/deactivate`, `features/settings`): lists every feature in the
  directory with its state and its problems, activates, deactivates, updates
  and edits settings. A reload of the workbench shows or removes a panel a
  feature brings.
- **`NINO_FEATURES_DIR`** relocates `features/` the way `NINO_APP_DIR` does
  `app/`; `index.php`, `_admin/index.php` and `_admin/recovery.php` carry the
  line. `features/.htaccess` denies the tree like `app/.htaccess`,
  `router.php` mirrors it.
- **`tests/harness.php`**, the shared bootstrap of a smoke test: `check()`,
  `ninoSandbox()`, `ninoSandboxDir()`, `ninoWarnings()`, `ninoDone()`. A
  feature's own test lives in `features/<Name>/tests/<key>-smoke.php` and
  loads it from the checkout three levels up or from `NINO_ROOT`.
  `tests/features-smoke.php` covers the contract against
  `tests/fixtures/features/` (`Sample/` with every settings type, a panel,
  an install unit and `upgrade()`; `Helper/`, `Old/`, `Broken/`). CI runs it
  and then every feature's test; PHPStan analyses `features/` and excludes
  `features/*/tests/*`.
- **A `features` job in CI** clones the catalogue
  [dapeio/nino-features](https://github.com/dapeio/nino-features), copies
  every feature of it into the checkout and runs the features' own tests and
  PHPStan against it on every push - the gate against a kernel change that
  breaks a published feature. The catalogue's CI does the reverse against
  Nino's `main` and latest tag. The checkout's own feature-test loop
  survives an empty `features/`.
- `'/nino/features' => []` in `AppData::DEFAULTS`; `Features` in the kernel
  class list.
- Docs: `docs/features.md` and `docs/features.de.md`, the feature manual and
  contract; `docs/recipes/feature.md`, the seventh recipe; Features in every
  manual's navigation line, in `AGENTS.md`'s map and tables, and in the
  roadmap.

### Changed

- **Nino's optional modules ship with the kernel.** `Form`, `Navigation`,
  `Localepicker`, `Design` and `Templates` moved from `app/Nino/Modules/` to
  `_nino/Nino/Modules/`, beside the always-on kernel modules; a project
  switches them on or off in `/nino/modules`, and `_nino/` is replaceable
  wholesale again. `app/` holds project-owned classes only (`app/.htaccess`
  stays).
- **`Newsletter` and `Search` are features**, each with a manifest, and
  live in the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features)
  rather than in the checkout (see Removed); the wizard's Setup step no
  longer offers them - it offers navigation, language selection and the
  contact form - and a feature is switched on in the Features panel after
  setup. The demo catalogue page unit no longer requires `newsletter` and
  ships its two newsletter labels itself.
- **The autoloader resolves `Nino\Modules\*` over four roots**, in this
  order: `_nino/`, `_admin/`, `features/` (or `NINO_FEATURES_DIR`), then
  `app/` (or `NINO_APP_DIR`). Below the features root the `Nino/Modules/`
  prefix is the directory itself (`features/<Name>/<Name>.php`). A shipped
  module cannot be shadowed, a feature cannot replace a workbench screen, a
  project cannot replace an installed feature, `app/` can only add.
- **The wizard applies its units through `\Nino\Features::applyUnit()`**,
  with overwrite on, so `_admin/install/` may still be deleted after setup
  and a feature's activation - add-only - shares one implementation with it.
  `Setup::units()` scans `_nino/Nino/Modules/*/install/`, the app dir and
  `_admin/install/library/modules/`, never `features/`.
- The Search feature's own test, `features/Search/tests/search-smoke.php`
  in the catalogue, runs over the harness; Nino's suites keep the kernel and
  workbench contract, with `Modules\Form` as the example of a module whose
  panel comes and goes.
- Docs: the READMEs, `AGENTS.md`, the developer, setup, workbench,
  deployment, concepts, templates, design and getting-started manuals and the
  recipes describe the layout above; the test lists name
  `tests/features-smoke.php` and the feature tests.

### Removed

- `app/Nino/Modules/` - nothing of Nino's is delivered below `app/` any more.
- **`Newsletter` and `Search` no longer ship with the checkout.** They live in
  the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features)
  (`features/Newsletter/`, `features/Search/`, each with `feature.php`,
  tests, README and changelog); a project installs one by copying its
  directory into `features/` and activating it in the Features panel.
  `features/` holds nothing but `.htaccess` in a checkout. The Newsletter's
  own tests - signup, confirm and unsubscribe, its panel, its restore merge -
  travel with it. The `newsletter-form` section preset of the Template
  Builder still ships with the kernel and needs the Newsletter feature to
  answer its form.
- `tests/search-smoke.php` - see the Search feature's own test,
  `features/Search/tests/search-smoke.php` in the catalogue.

## 1.0.0-beta — 2026-09-06

The 1.0 baseline: the shape 0.13.0-beta settled on, with static analysis in
CI, a kernel in one file per class, and the extension recipes as
documentation. Nothing a project or a module calls was removed, and no
project file format changed.

### Added

- Static analysis in CI. `phpstan.neon` runs PHPStan at level 5, with
  `phpstan-baseline.neon` holding the findings that were open when the check
  arrived, so only something new fails; `eslint.config.mjs` checks the browser
  scripts for undefined names, unused code and `==`. Both run after the syntax
  lints. An `.editorconfig` states the tabs/LF style the source already uses.
- `docs/recipes/`: the six extension recipes that lived in `AGENTS.md` - panel,
  runtime module, installer package, section preset, templates and page units,
  element types - one file each, with an index.

### Changed

- **The kernel is one file per class.** `_nino/Nino.php` keeps `\Nino\init()`,
  `request()`, `output()` and the autoloader; `AppData`, `Auth`, `Callbacks`,
  `Csrf`, `Filesystem`, `Backup`, `RotatingLog`, `Elements`, `Html`, `Http`,
  `Images`, `Locales`, `Text`, `Mail`, `Modules` and `Runtime` live under
  `_nino/Nino/<Class>/<Class>.php` and are loaded on first use. No public API
  changed.
- `AGENTS.md` holds the rules alone and links the recipes; its sections 13 to 18
  are 8 to 13 now.
- `\Nino\AppData::writeContentData()` returns `bool` instead of `void`.
- This changelog is condensed to release notes. The complete development notes
  of the beta versions remain in the git history before this change.
- `SECURITY.md` no longer names a version.
- Docs: the READMEs, the developer manual and `AGENTS.md` name the static
  analysis and the kernel layout; the recipes sit in every manual's navigation
  line and in the roadmap.

### Fixed

- `Design::saveSettings()` could never see a failed `config.php` write;
  `Elements::_cacheElement()` was handed an int array key as its locale;
  `[elementvalues]` passed an int id to `str_replace()`; the exception handler
  is registered with the signature PHP asks for; `Modules\Routes\Admin::_setText()`
  had no caller and is gone.
- `login.js` compared with `==`, `Nino.ui.js` hid two assignments in
  conditions, `area-composer.js` carried two unused helpers.

## 0.13.0-beta — 2026-09-06

One workbench. `/_admin`, `/_editor`, `/_install`, `/_design` and
`/_templates` were five tools with three logins; they are one now, `/_admin`,
with accounts and roles, and every screen in it is a panel a module can bring.

> No project built on an earlier version carries over: the tool directories,
> their routes, the `/_editor/*` permissions, the `/nino/editor/*` keys and
> the shared `_admin` password are gone, and there is no compatibility path.
> Nino is pre-1.0 and this is the shape it settles on before the first
> release. Read **Removed** before touching a checkout that predates it.

### Added

- **Accounts and roles** (`\Nino\Auth`). An account has a mail, a password and
  a role; a role is a named set of permissions under `/nino/auth/roles`, one
  string per panel or tab, `/_admin/<uri>/manage` by convention. The wizard
  writes **Editor** (every content panel's permission) and **Developer** (`/*`)
  and creates the root account. The Users panel creates and deletes accounts
  and hands out roles; its **User roles** tab edits the roles, its **Login
  protection** tab holds the throttle (`\Nino\Modules\Users\Lockout`). A change
  that would take *Manage users* from your own account or leave no full access
  behind is refused.
- **Scoped permissions** for Elements and Text, e.g.
  `/_admin/elements/services/update/title` or
  `/_admin/text/update/page-home/atf/title`, matched by the existing `/*` rule.
  An account holding none under a panel keeps that panel's full meaning; the
  panels send what the account may do, so a read-only field is locked and
  buttons it may not use disappear. Typed by hand in the roles editor.
- **`/nino/admin/action`**, a callback fired after every dispatched workbench
  action with `{ action, panel, status, user, data }`, for failed actions too.
- **Tabs.** A panel may answer `tabs()` with further panel classes, each with
  its own permission, script and hash prefix. Element Types, Text Keys, Image
  Slots, User roles, Login protection, Translations and the new **Language**
  panel (`\Nino\Modules\Language\Admin`) use it.
- **The panel contract**: `perm()`, `nav()` with a group (`content`,
  `structure`, `system`), and optionally `template()`, `layout()` (`page` or
  `workspace`), `icon()`, `tabs()` and `tab()`; `\Nino\Admin\Panels::relative()`
  turns a `__DIR__` path into the project-relative form.
- **The shell**: a foldable rail, workspace panels, deep links (`#design/header`),
  panels that load on first selection, dashboard tiles, the Elements panel's
  read-only **Raw storage** view.
- **`_admin/recovery.php`**: restore a backup or reset a password with the
  recovery password the wizard's last step sets (`\Nino\Admin\Recovery`, hash
  under `private/.auth/pw.php`, rate-limited).
- **Duplicate an element** from the element form, and **delete an element
  type** with its elements and images from the Element Types form - refused
  while another type's `element` field points at it.
- The text-key scan creates the rows with a value, passes over empty ones and
  ignores a row permanently through `/text/blacklist.php`, in one request
  (`keys/scanapply`).
- The Template Builder and the Design panel speak the interface language: every
  string is a fill (`app/Nino/Modules/Templates/text/<locale>.php`,
  `/_admin/design/knob/<knob>/…`). `tests/admin-lists-js-smoke.js` fails on a
  sentence written straight into the dom, `tests/admin-system-smoke.php` on a
  missing key or an unresolved fill in either interface language.
- **Optional modules live in `app/Nino/Modules/`**: `Form`, `Newsletter`,
  `Navigation`, `Search`, `Localepicker`, `Design` and `Templates`, owned by the
  project from then on. **Design and Templates are modules with a panel each**
  (`\Nino\Modules\Design`, `\Nino\Modules\Templates`), listed in `/nino/modules`
  whenever their directory exists.
- `Setup::units()` scans `_nino/Nino/Modules`, then the app's modules, then the
  rest of the app dir, so a delivered module claims a key before a project unit
  of the same name.

### Changed

- **`NINO_PRIVATE_DIR` replaces `NINO_CONTENT_DIR`**; `NINO_CONFIG_DIR` stays.
  `index.php`, `_admin/index.php` and `_admin/recovery.php` each boot the
  kernel, so a deployment defines its constants in all three. `NINO_APP_DIR`
  replaces `app/` as a whole, Nino's optional modules included.
- **`app/` is denied like `private/`** (`app/.htaccess`, `router.php`).
- **Every workbench screen is a module** under `_admin/Nino/Modules/<Name>/`:
  `Dashboard`, `Elements`, `Text`, `Images`, `Logs`, `Routes`, `Users`,
  `Language`, `Backups`, `Config`. `_admin/` holds the shell alone
  (`\Nino\Admin\Admin`, `\Nino\Admin\Panels`, `\Nino\Admin\Recovery`,
  `_admin/assets/`, `_admin/text/`); `Admin::modules()` reads the directory.
  The autoloader takes `_admin/` as a third root for `Nino\Modules\*`, between
  `_nino/` and `app/`. Both bundles are built from the registry.
- The design system moved into the `nino.system` half of
  `_admin/assets/style.css` and `Nino.admin.js`. `.nino-admin-tools` is gone;
  `.nino-admin-rail-toggle`, `.nino-admin-nav-group`, `.nino-admin-nav-icon`,
  `.nino-admin-nav-label`, `.nino-admin-shell--workspace` and
  `.nino-admin-shell--folded` are new.
- **One language, one empty state.** Every word the workbench renders is a fill
  in the panel's own `text/<locale>.php`; scripts read
  `Nino.content.getText()`, backend labels go through `Nino.adminUi.text()`,
  `Nino.adminUi.emptyState()` is the one empty state. The workbench falls back
  to English for a site language it has no words in.
- The panels merged: **Text** and **Text Keys**, **Images** and **Image
  Slots**, one **Users**, one **Elements**, **Backups**, **Log**. Permissions
  are `/_admin/<uri>/manage` (`/view` for Submissions and Log); the backup and
  log switches are `/nino/admin/backups` and `/nino/admin/logs`.
- The rail groups Content, Structure and System. Config keeps errors, the
  workbench switches and the page cache; the login throttle moved to Users, the
  languages to Language (`config/addlocale` is `language/addlocale`);
  `users/permissions` is `users/role`.
- The setup wizard is the workbench's first-run mode
  (`_admin/install/Install.php`, library `_admin/install/library/`), with the
  steps Environment, Setup, Themes, Header, Footer, Design, Routes, Personal
  Infos, Accounts and Finish.
- `Modules\Cache` drops the cache on any successful `POST` to `/_admin`;
  `router.php` routes `/_admin` and `/_admin/recovery.php` and denies the
  library except the theme previews.
- `Nino.editor.*`, `Nino.design` and `Nino.templates` are `Nino.admin.<uri>`.
- Tests: `editor-smoke.php` is `admin-smoke.php`, the old `admin-smoke.php` is
  `admin-system-smoke.php`. Docs: one workbench manual (`docs/_admin.md`) with
  the references `docs/setup.md`, `docs/appearance.md` and `docs/templates.md`.

### Removed

- `_editor/`, `_install/`, `_design/` and `_templates/` with their entry
  points, routes, login pages and the `[admin-tools]` shortcode
  (`\Nino\Admin\ToolBridge`); `_nino/Nino/Panels/`; `_nino/Nino.admin.css`
  and `_nino/Nino.admin.js`.
- `editorPanels()`; the `/_editor/*` permissions; the shared `_admin`
  password and its login page; the `/nino/editor/*` keys.
- `Users::presets()` and `Users::apiSetPermissions()` - roles are data now;
  the `/_admin/users/role/editor` and `/developer` fills.
- `\Nino\Design\*` and `\Nino\Templates\*` - they are
  `\Nino\Modules\Design\*` and `\Nino\Modules\Templates\*` now, one class
  per file.
- `docs/_editor.md`, `docs/_install.md`, `docs/_design.md`,
  `docs/_templates.md` (and the German versions) - consolidated, see above.

### Fixed

- **The workbench login failed on CGI and FastCGI.** `\Nino\Http::request()`
  decodes the `Authorization: Basic` pair itself where the SAPI does not; the
  shipped `.htaccess` carries `CGIPassAuth On` (Apache 2.4.13 and newer), the
  deployment manuals name the nginx/PHP-FPM equivalent.
- **A password that is not plain ascii could never sign in**; the pair is sent
  utf-8 encoded now.
- **A users manager could give itself full access** through a role it wrote
  and an account it created. Granting is bounded by holding
  (`Roles::notHeld()`).
- **Activity-log lines were forgeable** through a line break in a posted
  value; control characters collapse to a space.
- **The activity log skipped most of the workbench**; each tab and panel now
  answers for its own writes.
- `.github/workflows/ci.yml` ran a deleted suite and never
  `tests/admin-system-smoke.php`; the js loop reported the last file's status
  alone.
- `\Nino\Auth::deleteUser()` ended the current session whoever was deleted.
- The Keys panel's rich-text fields called `Nino.editor.htmlEditor`, which no
  longer existed; the language switcher rendered a nameless option for a
  locale without a Localepicker name; a failed element list stayed hidden.
- A literal `[template]` token inside a text fill was executed rather than
  shown.
- Saving, creating or deleting an element type threw and left the Elements
  pane on stale data: `Nino.admin.elements.invalidate()` is back, with a
  request counter against responses still in flight.
- Stale references: the wizard's hint and a test pointed at `docs/_install.md`,
  `.gitignore` at `_admin/setup/`, a sitemap template at `/_install`, the demo
  catalogue at `/_design`; two English strings survived the i18n pass;
  `Backups\Admin::lastDate()` had no caller left.

## 0.12.0-beta — 2026-08-30

Appearance tooling, project layout, optional search and caching, and the
removal of every pre-1.0 compatibility path.

> Nino remains in beta. The optional Template Builder under `/_templates`
> is released as an alpha feature. Its interface, block library, and
> workflows may still change significantly.
>
> This release is not backward compatible with projects built on an earlier
> version. The frontend speaks one `nino-*` class namespace, the Design
> settings key is `/nino/design/settings`, the project tree lives under
> `private/`, and every legacy path and compatibility fallback has been
> removed. Read **Changed** before updating an existing project.

### Added

- **The generated design layer** (`/_design`). It owns the colour tokens every
  stylesheet reads (`--nino-alt`, `--nino-on-alt`, …) and solves them in OKLCH
  from a primary colour, an optional secondary and ten settings: Contrast,
  Saturation, Harmony, Temperature, Depth, Size, Width, Volume (Headings),
  Spacing and Shaping (Corners). Every background is published together with
  the text colour solved for it against the WCAG formula; nine surfaces publish
  ten values each. Written to `/assets/style.design.css`, spliced into the css
  bundle directly after `_nino/Nino.css`, rewritten in full on every save.
  Settings are steps 1 to 3; at step 2 throughout the stylesheet reproduces
  `Nino.css` exactly.
- **The size raster**: `--nino-text-1` to `-6`, `--nino-space-1` to `-6`,
  `--nino-radius-1` to `-3`, `--nino-radius-full` and `--nino-line-height`,
  which a theme assigns from instead of writing numbers.
- **Four brand roles and a fifth ground**: `--nino-brand`, `--nino-brand-safe`,
  `--nino-accent`, `--nino-accent-safe` and `--nino-tint`. `Nino.css` gains
  `.nino-section--tint`, `.nino-section--brand-alt`, `.nino-btn--brand-alt` and
  the `--color-section-tint-*`, `--color-accent-tint`, `--color-focus-tint` and
  `--color-brand-alt*` roles. `--nino-scrim`, the cover scrim solved per design
  (a theme may darken it, never lighten it below the floor); `.nino-cover--dim`
  and `.nino-img-background--dim` hand the dark ground's ink down. A global
  `:focus-visible` ring (`--color-focus-*` per ground), `--color-disabled`,
  `.nino-alert--warning` and `--radius-large`.
- **A catalogue of ten themes**: Basis, Bureau, Chronicle, Console, Gallery,
  Market, Midnight, Platform, Poster and Practice. Every position of every
  setting and every header and footer frame is used by at least one of them,
  and a test says so. Theme stylesheets are mapping layers - every colour role
  points at a `--nino-*` token - and every manifest declares the `design`
  block it was drawn with; the catalogue tests reject literal colour and size
  roles and resolve every pair in both modes.
- **Frames.** A site's `<header>` and `<footer>` are interchangeable units
  under the library's `header/<key>` and `footer/<key>` (a `template.tpl` and
  its `style.css`), included through `[template /templates/theme.header]`;
  picks persist at `/nino/install/header` and `/footer`.
- **The installer's appearance steps**: Themes (applying a theme installs the
  Header, Footer and Design its manifest declares), then Header, Footer and
  Design, each with a live preview - the frame steps render the real template,
  Design a whole example page inside the project's own frames with the demo
  images and the theme's fonts, scaled into the panel by
  `Nino.adminUi.scaleFrame()`, with a light/dark switch. Design has a dirty
  state, Revert and a leave guard; a half-typed colour is refused out loud.
  Step messages sit in the action bar.
- `/_design` after the installation, with four dialogs - Theme, Design, Header,
  Footer - sharing `/_admin`'s session and CSRF; the catalogue stays under the
  installer's library, of which only the theme `preview.svg` files are public.
- Admin UI components: `Nino.adminUi.buttonRow()`, `selectField()`,
  `switchField()` (`.nino-admin-switch`), `fieldChange()`
  (`.nino-admin-changed`), `.nino-admin-tabs`, and the shared data table
  `Nino.adminUi.table()` with search, type-aware sorting and paging
  (`tests/nino-ui-table-js-smoke.js`). `Nino.adminUi` moved out of the public
  `Nino.js` into `_nino/Nino.admin.js`.
- `Demo: Catalogue`, a page unit showing every section preset in every layout
  and every `nino-*` block `Nino.css` defines, counted by
  `tests/demo-catalogue-smoke.php`.
- **`Modules\Search`**, an optional weighted fuzzy index for Elements:
  `/nino/elements/index` assigns fields to priorities, `Search::getElements()`
  returns complete Elements in score order; rebuilt on element writes and by
  **Create searchindex** in Config; one non-atomic `/data/index-<type>.php` per
  type.
- **`Modules\Cache`**, an optional full-page cache for anonymous `GET`
  requests without query vars, off by default, configured in Config with a
  lifetime and a uri blacklist. `[csrf]` and `[jstext]` values are stamped per
  request; a successful write through a tool drops the cache; responses carry
  `X-Nino-Cache: hit|miss`. A `/nino/http/output` callback carries the finished
  response.
- Element types can number their own elements (**Element URIs**,
  `'autoincrement'` in the type file, `/gallery/00001`), allocated under the
  element lock and never reused.
- An `element` model field type: a reference to another type's entry by its
  full uri; a deleted target shows as *missing*.
- `Elements::queryElementValues()` and `[elementvalues]`, the distinct values
  of one field with their counts (`sort`, `includeEmpty`, `[[.value]]`,
  `[[.count]]`, `[[.id]]`); `nino-filter` in `Nino.ui.js`; the
  `filterable-grid` preset; the `[[section:collection:<areaKey>]]` compile
  token.
- Config is a typed form validated against `Config::FIELDS` and saved as one
  write; adding a language writes its `text/<locale>.php` skeleton, switched
  off; the language list reports what exists on disk.
- A **Navigations** area in `/_admin` (`/nino/html/navs`, dense priorities); a
  versioned **Translations** JSON export and import; inline `<code>` in the
  rich-text editor and its sanitizer; a pinned form toolbar; starter wording in
  the wizard's Webpages step; a page's Http-URI as the textfill
  `/webpage<uri>/uri` in `text/global.php`.
- **The Template Builder**, rebuilt section-first (Alpha): manifest-v3 presets
  with named Areas, Design and Data views, component bindings (generated,
  collection, existing textfill or a fixed value), Layouts, Area Styles,
  `data-*` attributes declared by the manifest, `[template]` canvas sections,
  page metadata (display name, VPA default), sandboxed real-markup previews
  and an explicit HTML+ escape hatch. Presets: Articles, Fullscreen image,
  flexible content, media split, reusable template, Process, Pricing (with
  four-column Layouts), Features, Partners, Call to action, Banner, the CTA
  button pair, and five static blocks (Table, List, FAQ, Newsletter form,
  Contact form). Every **Auto** option names the value it resolves to; preview
  cards share one viewport; a background image may be a fixed value.
- Type size is a modifier of the class it changes (`--quiet`, `--loud` on every
  typography class, plus the new section body-copy class) instead of the two
  font-size utilities, which are gone.

### Changed

- **Every legacy path and compatibility fallback is gone**, roughly 750 lines:
  the pre-v3 section composer, the `--nino-origin` and `--nino-vibrant` tokens,
  the named Design vocabulary (`contrast: 'high'`, `colors: 'clean'`, …), the
  kernel's second autoload root, and the `/nino/theme/design` key - it is
  `/nino/design/settings` now.
- **A checkout ships no project.** There is no `private/` in the repository;
  the wizard creates the directory, its deny rule and the first `config.php`.
  Framework defaults live in `\Nino\AppData::DEFAULTS`, so a first install
  writes four keys. `\Nino\init()` takes one flag; only the installer's entry
  point passes it. A unit's `files` are copied before its `templates`.
- **Project layout.** The private half - `config.php`, `templates/`, `text/`,
  `elements/`, `data/`, the asset sources, `.auth/`, `.logs/`, `.backups/` -
  lives under `private/`, denied by its own `.htaccess`, refused by
  `router.php`, movable with `NINO_CONTENT_DIR`. The public half - `images/`,
  `fonts/`, `favicon/`, the generated `.cache/` - lives under `public/`; urls
  gain a `/public` segment through `[[/nino/public]]` or
  `\Nino\Filesystem::url()`. `Filesystem::getPath()` is the code root,
  `getPublicPath()` the browser-facing one; everything in
  `Filesystem::PRIVATE_DIRS` resolves through `Filesystem::path()`. **An
  existing installation moves `public/assets` to `private/assets`.**
- The `/_admin` password hash, the login throttle, the activity log and the
  backup archives moved out of the tool folders into `private/.auth/`,
  `private/.logs/` and `private/.backups/`, so `_admin/` and `_editor/` hold no
  project state and an update may replace them wholesale. The `/_install` lock
  is `/nino/install/completed` beside the stored password; `/nino/backup/dir`
  and `/nino/logs/dir` are gone.
- Project classes resolve from `app/`, or `NINO_APP_DIR`; `Nino\` stays
  kernel-only.
- **Breaking: the frontend speaks one class namespace, `nino-*`.** `ui-*`,
  `js-*` and `sc-*` are gone; the state classes `.error`, `.success`,
  `.pending`, `.existing`, `.touch` and `.active` are `nino-is-*`;
  `.nino-icon.small` is `.nino-icon--small`. Hand-written markup and custom
  CSS need a manual rename; a compiled section re-emits the current vocabulary
  on save.
- The appearance tool moved from `/_theme` to `/_design` (`Nino\Design\Design`,
  `Nino\Design\Tokens`, `Appearance`). Harmony and Temperature became named
  choices and were renumbered - Temperature is Neutral/Cool/Brand/Warm, Harmony
  is Monochrome/Analogous/Triadic/Complementary - so a Design saved earlier in
  the beta should be reopened and checked. Volume, Shaping and Measure are
  labelled Headings, Corners and Width; the stored keys are unchanged.
- Header and Footer come before Design in the installer. Bundle order is the
  contract: `Nino.css`, the generated design values, the theme, the frames,
  the project's overrides.
- Management APIs expose one schema: Webpages posts `libraryKey` and `navs`,
  Template creation `filename`, navigations come only from `/nino/html/navs`,
  the theme only from `/nino/install/theme`. `/nino/install/webpages` is gone;
  both tools derive the page list from `/nino/http/routes` and
  `/webpage<uri>/*`. Menu membership is numbered densely. Config no longer
  edits routes, navs or asset bundles. The backup and log switches are
  `/nino/editor/backups` and `/nino/editor/logs`.
- `_nino/Nino.admin.css` is a class-only design system (`nino-admin-*` under a
  `.nino-admin` root, 1482 to 775 lines) with the cascade layers
  `nino.system, nino.tool, nino.local`; the locale switch and the select
  indicator are components.
- The Template Builder library is manifest-v3 only; Add Section and Edit
  Section are split by intent; the Data view puts one binding on one line; the
  Articles preset equalizes card titles and descriptions per section;
  `/_templates` is a reserved route.
- Wizard: "New Webpage" is "New Route", the step **Webpages** is **Routes**, a
  new route starts on **Blank**, which is copied per route
  (`templatePerRoute`), and `/contact` sits in the footer menu.
- `/_admin`: **Save and back** and **Save and new**, the element row label is
  the `title` field, an anchored save row, denser forms from 768px, grouped
  drill-down lists, **Pages** is **Routes**, Translations moved from
  `/_editor`.

### Fixed

- Leaving the Design pane and coming back threw away every unsaved setting.
- Saturation moved the brand and nothing else, and turning it up could come
  back less saturated; Contrast named three positions and produced two. All
  three scale now, and every position clears WCAG AA.
- Brand colour written as ink in a dozen `Nino.css` components failed the text
  target on any ground that is not near-white; `--color-accent` per surface is
  the missing role. Sections hand their ink roles down with their background;
  `.nino-article--alt` and the status alerts are surfaces with a solved ink;
  the footer, the locale picker, pagination and the form status message take
  the ink of the band they are in.
- Spacing, alignment, opacity and visibility utilities lost to every component
  written after them; they are the last chapter of the stylesheet now.
- Three target sizes and the footer frame `v7`'s legal link sat under WCAG 2.2
  SC 2.5.8's 24px.
- `.nino-cover-content` hung over its section's right edge; `nino-cover` next
  to a side rail overflowed horizontally; a viewport animation widened the
  page.
- `Design::normalize()` raised a `TypeError` on an array-shaped knob, a 500
  anybody could ask for through `design/save` and `design/apply`.
- The authenticated `/_design` page rendered no CSRF field; header previews
  corrupted UTF-8 and attribute delimiters; two js smoke suites crashed before
  their size assertions; two unclosed `<div>`s in the installer's Design step.
- The Template Builder ignored the background image slot a section chose; a
  `]` inside a fill cut a shortcode match short in the preview; composer
  dialogs rendered outside `#pd-app`; previews requested `/.cache/style.css`
  from servers answering with HTML; Area tabs clipped the editor body; gallery
  previews load project fonts; viewport-animation classes kept preview
  sections transparent; generated image markup pointed at `/uploads`.
- `/_admin`: the element type overview kept its counts from page load; the
  Element Types editor dropped **max. characters** and **unit/suffix** on
  save; the Elements module showed an outdated schema after a type was saved;
  a required image field made a type impossible to add to; the image preview
  pointed at `/uploads`; long options ran under the select chevron; the last
  form regressions after the shared-style refactor.
- `/_install`: "Back to list" in the Routes step discarded the open form; "New
  Route" carried a stray margin; the tool shells dropped their design-system
  classes on every panel switch; the `Demo: Sections` page pointed at moved
  photography and a module the library no longer ships.
- Backups omitted nested Element image paths. Failed or unserializable writes
  were visible through the in-request cache. Element type files acquired
  double-slash cache and lock aliases, type creation hid write failures, a
  reset-to-default left an old override, partial URI renames discarded
  omitted fields. Route, query and locale handling is hardened against
  malformed or array-shaped request values. The project root no longer has to
  stay writable during normal operation. A touch slider filling the viewport
  kept native scrolling; the rich-text field restored drag selection.

### Security

- Template Builder writes only validated `page-*.tpl` paths, keeps locked raw
  source byte-identical, validates both HTML and template sections, rejects
  duplicate section IDs, uses optimistic revisions for external-edit
  conflicts, validates fixed shell-slot topology, and replaces files
  atomically. Real-markup previews run without scripts in sandboxed frames
  whose CSP permits only project-origin styles/assets and generated data images.

## 0.11.0-beta.1 — 2026-08-09

Development tooling, installation workflow, content management, and documentation update.

> Nino remains in beta. The optional Template Builder under `/_templates`
> is released as an alpha feature. Its interface, block library, and
> workflows may still change significantly.

### Added

- `/_templates`, an optional graphical structure editor for `page-*.tpl` and
  `section-*.tpl` files: a manifest-driven block library of 76 definitions
  covering the Nino.css component catalogue, block selection, insertion,
  reordering, duplication, removal and responsive settings, and editing of
  CSS classes, attributes, boolean attributes (`attrtoggle`), tags and text.
  Templates keep their attribute order, whitespace, comments, indentation and
  entity spelling; opening and saving an unchanged template is a no-op.
- The **Themes** step in `/_install`: self-contained theme library units under
  `library/themes/` with manifest, stylesheet, preview, fonts and declared
  assets. Applying one replaces its entry in the css bundle without changing
  the cascade position; the selection persists under `/nino/install/theme`.
- Page management and the complete management of element types, elements,
  multilingual texts, image slots, routes, users, configuration and restoration
  in `/_admin`.
- English and German documentation for concepts, development, installation,
  administration, editorial content management, template editing and
  deployment, with screenshots and language links.

### Changed

- The former `/_dev` developer area is `/_admin`, the former `/_admin` content
  backend is `/_editor`; directories, routes, namespaces, sessions, JavaScript
  namespaces, CSS identifiers, configuration keys, documentation and tests
  follow.
- A fresh checkout requires `/_install` before the website can run; the wizard
  has seven steps (Environment, Setup, Themes, Webpages, Personal information,
  Admins, Finish) and creates `templates/`, `text/`, `elements/`, `images/` and
  `assets/` instead of the checkout shipping a configured project. It replaces
  the selected locales, modules, pages and theme while preserving manually
  defined routes; templates and text are added, never deleted.
- The shared webpage model of `/_install` and `/_admin` distinguishes the
  library key, the template file, the internal page URI, the public HTTP URI,
  the response status and the rendered route body; both tools share one
  persisted list, and the page order in `/_admin` determines the main
  navigation order.
- The Template Builder derives block identity from ordinary HTML tags and CSS
  classes and inserts through the same server-side parser as existing
  documents; responsive settings sit below their base setting; article
  modifiers are independent toggles.
- Documentation structure, metadata, navigation, terminology and maturity
  labels are standardized; `/_templates` is documented as Alpha.

### Fixed

- `/nino/install/webpages` was interpreted differently by `/_install` and
  `/_admin`; pages created during installation appeared as unknown templates;
  saving the shipped 404 page reset its status to `200`; saving localized pages
  lost their locale-dependent template expression; custom route bodies and
  manually added routes were overwritten or removed.
- Account creation and updates failed on a by-reference callback parameter.
- Theme stylesheets referenced a missing font directory; unused font faces
  were removed.
- Template Builder: edited classes changed order, inserted children were
  misindented, removed blocks left blank lines, generic definitions overrode
  specialized modal, toast and component blocks.

### Security

- The Template Builder writes only supported `page-*.tpl` and `section-*.tpl`
  files, keeps header, footer, email and other technical templates read-only,
  rejects unsupported tags and `on*` event-handler attributes, never writes an
  empty tree, writes atomically, reuses the protected `/_admin` session and
  stores no sidecar files or builder attributes in project templates. Existing
  templates are accepted only when a byte-exact round trip is guaranteed.

### Tests

- `tests/templates-smoke.php` and the dependency-free
  `tests/templates-js-smoke.js`; tests for the block catalogue, manifest
  parsing, byte-exact round trips, structural actions, responsive settings,
  `attrtoggle`, block matching specificity and theme units; expanded
  installation and administration smoke tests.

## 0.10.0-beta

Security and reliability hardening pass, plus a module-system refactor.

### Security

- Made `\Nino\Csrf` protection permanently active.
- Limited `\Nino\Modules\Csrf` to rendering the `[csrf]` shortcode.
- Accepted CSRF tokens from request headers and JSON bodies.
- Changed CSRF method matching to use exact HTTP methods.
- Added throttled logins per IP address.
- Prevented user enumeration during login.
- Replaced client-IP session binding with session tokens.
- Removed password hashes from PHP session data.
- Added strict session cookie settings and token lifetimes.
- Fixed parallel logins invalidating each other.
- Fixed `X-Frame-Options`.
- Completed the Content-Security-Policy configuration.
- Fixed mail header injection and sender headers.
- Prevented credentials from leaking through the error handler.

### Changed

- Moved modules to `_nino/Nino/Modules/<Name>/<Name>.php`.
- Added module loading through `spl_autoload_register()`.
- Renamed `\Nino\Shortcodes` to `\Nino\Modules`.
- Added `Http::fail()` and `Http::ok()` response helpers.
- Moved `\Nino\Text` into the kernel.
- Reworked the rotating log.
- Moved `backupManifest()` to `\Nino\Backup`.
- Reduced comment density across the codebase.

### Fixed

- Changed element mutations to use `Filesystem::mutate()`.
- Fixed early-return lock leaks in element operations.
- Added atomic file writes with correct locking.
- Changed `Filesystem` reads to use the locking primitive consistently.
- Fixed configuration path and rotating-log edge cases.
- Added a shortcode recursion guard.
- Fixed element query keys being combined incorrectly.
- Fixed URI renaming.
- Fixed route locale handling.
- Prevented asset bundles from being rebuilt when unchanged.
- Replaced recursive array merging with scalar-overwrite behavior.
- Fixed a dashboard permission leak.
- Fixed response header filtering.
- Fixed `Callbacks::doCallbacks()`.
- Changed `writeContentData()` to check its write result.
- Prevented `Text::saveBatch()` from rebuilding entries for every call.

### Tests

- Added `tests/concurrency-smoke.php`.
- Added the developer-area smoke suite.
- Expanded `tests/kernel-smoke.php`.
- Expanded the administration smoke suite.

## 0.9.0-beta

First tagged release.

### Added

- Added newsletter double opt-in with confirmation by email.
- Added self-service newsletter unsubscribe links.
- Added automatic newsletter routes under `/.newsletter`.
- Added local development support for bundled demo images through
  `router.php`.

### Changed

- Stopped tracking `/data/` and uploaded images in Git.
- Simplified `docs/*.md` to reference documentation.
- Removed `docs/security.md`.

### Fixed

- Fixed the Jstext module clearing the default Content-Security-Policy.
- Fixed production error display and logging defaults.
- Fixed locale-switch redirects.
- Added `session_regenerate_id()` after login to prevent session fixation.
