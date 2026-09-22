# Changelog

All notable changes to Nino are documented in this file.

## Unreleased

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
  exceptions name that, the tool-layer rules on `.admin-panel-tabs` are as
  they were, and the two suites that read the fragment as a string -
  `admin-smoke.php` and `admin-system-smoke.php` - name the modifier.

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
