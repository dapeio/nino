# The Workbench Interface

The rules every screen of the workbench keeps: the kernel's panels, the panels of a feature, the setup wizard and the recovery page. Their aim is one sentence - **the same task looks the same, is called the same and behaves the same wherever it is done.** What a screen is built from is in the [panel recipe](recipes/admin-panel.md) ("Designing an admin frontend" is the reference of the classes and `Nino.adminUi.*` blocks); this chapter says which of them a screen uses for which task, and what it must not build itself.

The words **MUST**, **MUST NOT** and **SHOULD** are normative, as in the [agent guide](../AGENTS.md). A panel that is changed is brought to every rule of this chapter, its stylesheet included, in the same change; a panel that is new keeps them from its first line. The [checklist](#checklist) at the end is the review of a view.

## Principles

1. **One task, one block.** What the kernel has as a block (`Nino.adminUi.*`, a `.nino-admin-*` class) a panel does not build itself, not even "nearly the same". Where a block is missing it is added to the kernel, not to the panel.
2. **Every view has the same build:** head, context bar, content, bar - or, for a work surface, context bar, surface, bar. Each part has one place for each thing - the way back at the top left, the main button at the bottom right, delete at the bottom left ([Anatomy](#anatomy-of-a-view)).
3. **One way of saving per view:** a form with Save in the bar, a work surface with its save bar, or an immediate action with its answer as a toast - never mixed ([Saving](#saving)).
4. **Words from the list.** The same thing has the same name everywhere; a button says what it does; no raw key stands without a word before it ([Words](#words)).
5. **Nothing is lost silently, nothing is asked twice.** Unsaved input reports to the shell; a question is asked by the table of levels; where there is an undo, it replaces the question ([Dialogs](#dialogs)).
6. **The keyboard, 390 px, light and dark belong to the task.** Targets are at least 24 px, text at least 12 px, contrast at least 4.5 : 1, the page never scrolls sideways, the focus is visible, and nothing means something by colour alone.

## Anatomy of a view

A view has one of two forms. A list or a form has a head and its content is one column; a work surface has no head and its content fills the height.

```
A view with a head
head ─────────── title · tabs (first level) ························ ? ┐  Nino.adminUi.panelHead()
context bar ──── ‹ Back to …   [tools of the view]                      ┤  Nino.adminUi.contextBar()
content          one column (at most 62 rem) or split()                 │
bar ──────────── [Delete thing] [● Changed]   line  [Side] [Main]       ┤  actionBar() / listActions() / saveBar()

A work surface
context bar ──── Panel · ‹ Back to …  Name  code [icons]   [tools]  ?   ┐  contextBar( { panel, title, code, icons, tools } )
surface          [outline] [settings] [preview]                         ┤  split( { fill, aside } ), one scroll bar for each column
bar ──────────── [● Changed] line   [Undo] [Redo] [Save]               ┘  saveBar()
```

| Part | Rule | Block |
|---|---|---|
| Head | A list or form view has the head with the panel's title and the "?" of its manual - the Dashboard too. The tabs of the first level stand in the head and nowhere else. A count at a tab is a badge, not a part of the word. | `panelHead()`, `tabBadge()` |
| Work surface | A work surface - a draft with a preview that is saved as a whole - has no head: it uses the whole height. The panel's name stands small in the context bar above the way back (`contextBar( { panel } )`, which then gives the head of the pane way: its heading stays for a screen reader) and stays the heading of the view, which the focus goes to (`panelLabel : false` leaves the small name out of the bar where the title says it - the head still gives way and the "?" still stands in the bar; a bar in a level that is hidden does not count, so the head is back with the list); the "?" of the manual is an icon button at the right of the context bar. The name of the thing, its quiet identifier (`code`) and the tools of the view stand in the context bar; `split( { fill : true } )` follows it directly - no gap, no frame - and reaches down to the bar. The page does not scroll: each column has a scroll bar of its own, and the preview's is the one of its page. What is rarely needed (the frame of a page, an identifier, markup) is behind an icon button beside the name (`icons`). The list of the same panel keeps its head. | `contextBar()`, `split( { fill } )` |
| Address | Every view has an address in the one form of [Addresses](#addresses): `#panel/tab/part`. Reloading and the browser's back button keep it. | `Nino.admin.router`, `levels()` |
| Way back | Exactly ONE back link, at the left of the context bar: "Back to <name of the level above>" - one wording, the name from the head of the pane or given. A deeper level replaces the link; it does not put a second one under it. | `contextBar( { back } )`, `levels()` |
| Tools of the view | The page a preview shows ("Shows"), its width (Mobile, Tablet, Desktop as one strip - `icons : true` draws a phone, a tablet and a monitor with the word as name and tooltip) and "Reload" are one group, `previewTools()`. On a work surface the group stands at the right of the context bar (`contextBar( { tools } )`); where the bar is narrower than 56 rem they fold into a menu behind ONE icon button, "Tools", that closes with Esc, a click beside it, the focus leaving it and a click into the preview. On a split view that is no surface they stand in the bar over the preview. Never in the content. | `previewTools()`, `contextBar( { tools } )`, `split()` |
| Working language | The language in which content is shown and edited is chosen once for the whole workbench, in the account area of the rail. No panel has a language switch of its own. A view with values that depend on the language shows the working language as a quiet badge in the context bar as soon as it is not the native one. The interface language ("Interface language", the picker in the settings menu) and the working language ("Edit content in") have two different names and two places. The rail shows it as a select under the account's icons; folded and on a phone it stands in the settings menu beside the interface language, and only a project of more than one language has it. A change asks before it throws unsaved input of a view that follows the language away (Save, Discard, Cancel), and the open view draws again in the new language. A panel reads and sets it, and a view that depends on it follows it. | `Nino.admin.workLocale`, `Nino.adminUi.localeBadge()` |
| Introduction | None. No open paragraph stands over a form or a list: what explains a view stands behind the "?" of its head, what explains a section behind the (?) of its title, what explains a field behind the (?) of its name. The one lead that is left is the sentence a step of the setup wizard says before its fields - the wizard has no head and no manual of its own - and `hint( text, 'lead' )` is for that alone. | `panelHead()`, `sectionHelp()`, `fieldHelp()` |
| Section | A group of a form is a `fieldset > legend`. A section of a view without a form has a title of its own. Nothing is larger than the panel's title under it. A section that opens and closes is an accordion, and only that. | `fieldset`, `accordion()` |
| Surface | A card is `.nino-admin-card` (a `fieldset` is one). A panel draws no frames, radii or shadows of its own. | `.nino-admin-card` |
| Two columns | Settings at the left, a preview at the right: only `split()`. At 390 px its switch says "Settings | Preview" unless the view has a better fixed name. Settings that are wide forms (a block's fields) take `settingsWidth : 'wide'`: 480 px instead of 23 rem, half of the view at the most - it has an effect from a view of 46 rem on, where there is room, and is never narrower than without it. | `split()`, `previewFrame()` |
| Three columns | A column that lists the parts of the draft (an outline) stands at the left edge of the surface: `split( { aside : { content, label, collapsed, rail } } )`. It folds: open it is a column on the white ground of the preview, folded a strip of 48 px that holds what the caller gives for it (the icons of the parts). The button at its top - the list icon and the double arrow of the rail's button - stays in both, says `aria-expanded`, is named by what the column holds ("Outline"), and keeps the focus. Whether it is folded is held for the session for each view. At 390 px it stands over the settings. A work surface of the outline and the preview alone has no settings (`settings` is left out): two columns, the preview taking all the rest, and the switch at 390 px is named by the outline. | `split( { aside } )` |
| The page in the preview | A page that is shown in the preview of the workbench is marked (`previewFrame( { mark : 'loose' } )`): the header of Basis that stays at the top of a page scrolls away there, so it takes none of the room of the page that is worked on. The kernel writes the mark on the page from the first byte, for a request that holds a draft and asks for it; a public page is never marked. | `previewFrame( { mark } )` |

```js
// The level "thing" under "Routes": one back link that says where it leads, the tools of the level beside it
mount.appendChild( Nino.adminUi.contextBar( {
	back  : { to : mount, onClick : function() { Nino.adminUi.backTo( 'routes-list', opener ) } },
	tools : [ reload ],
} ) );
// A work surface with no head: the name of the panel stands small over the link, the tools of the preview at the right
const tools = Nino.adminUi.previewTools( { pages, page, width : 'desktop', onPage, onWidth, onReload } );
Nino.adminUi.contextBar( { panel : 'Themes', title : theme.name, code : theme.key, back : { to : 'Themes', onClick : leave },
	icons : [ { icon : 'panels-top-left', name : frameName, onClick : openFrame } ], tools : tools.el } );
mount.appendChild( Nino.adminUi.split( { id : 'themes-split', fill : true, settings, preview : { src } } ).el );
```

## Addresses

The address of a view is the part after the `#`, and it is written in ONE form for every panel of the workbench, the kernel's and a feature's:

```
#<panel>                              the panel, on its first screen, as it starts
#<panel>/<tab>                        a screen of the panel, as it starts
#<panel>/<tab>/<part>[/<part>...]     a state of that screen: the thing that is open, a tab of a list, a form that makes one
```

- **Every panel has at least one tab: its own screen, named like the panel.** There is no second form for a panel without tabs: `#routes/routes/@new` is the form of a new route, `#elements/elements/blog/post-1` an element, `#users/roles/@new` the form of a new role. `#<panel>` alone is the panel's first screen as it starts - the same as `#<panel>/<its first tab>`, and the form a link to a panel, the rail and a notice write. The reason is that what stands behind the panel is always a screen and never a thing: a thing can be called whatever it likes, a screen cannot be called `new`.
- **A tab is named by its uri without the name of the panel in front of it:** `types` of `elements` is `#elements/types`, `builder-frames` of `builder` is `#builder/frames`, the settings of a feature's panel `<panel>-settings` is `#<panel>/settings`. The server renders the name with the pane (`data-address-tab`, `Panels::panesHtml()`), the router reads it from there. The tab itself is still called by its uri everywhere in a panel's script: `Nino.admin.router.href( 'types', [ Nino.admin.router.word( 'new' ) ] )` is `#elements/types/@new`. A tab whose name another screen of its panel has is refused when the registry is built, with a warning.
- **A part is a word or a key, and the segment says which.** A *word* is a thing the workbench says - `@new`, `@scan`, `@rename`, `@inactive`, `@feature` - written with an at sign and a lowercase word, as it is. A *key* is the name of a thing from the data - a type, an element, an account, a route, a slot, a text key, or an element that is called `@new` - and is always ONE segment, whatever it holds: percent-encoded, a slash in it is `%2F` (`#text/text/template%2Fpage-home`), a space `%20`, an umlaut its bytes, every at sign `%40` (an account is `ada%40example.com`, the element `@new` is `%40new`). So a role, a menu or an element may be called `new` or `@new`: `new` is its key, `%40new` the key `@new`, and `@new` the form that makes one. The router tells them apart where it reads, at the segment as it is written, before it is decoded. A panel never splits a key at its slashes and never joins the segments of one.
- **Nothing writes an address by hand but `Nino.admin.router`.** A panel asks it: `href( screen, parts )` for the link of a row (a real `a`), `go()` for a move the person made, `set()` to keep the address true to what is on screen, `current()` for `{ panel, parts, known }` - the screen the address names, its parts, and `known`, false where the address names a tab the panel has not (the panel's start is shown and the address is written over) and true everywhere else - also where a broken part ended the parts, since what stood before it was read. A string in `parts` is a key; a word is made with `router.word( 'new' )`, and read with `router.isWord( parts, at, 'new' )` - never by comparing a part with `'@new'`, which a key can be. What the server writes - a notice, the `next` of an application, a link in a text - is written by `\Nino\AdminAddress::of( $panel, $parts, $tab )`, with `AdminAddress::word()` for a word, which writes the same.
- **An address in any other form is no address, and nothing reads it.** The panel's start is shown, never an error and never an empty screen: an unknown panel opens the first panel of the rail, an unknown tab the panel as it starts, a part a panel does not know (a thing that is gone, a word it has not) its list - and the address is written over by the one the screen has. A part that is no part - an empty segment, a control character, a stray `%` - ends the parts there: what stands before it is read, the part and what follows are dropped, the panel and its tab stay. An address of more than 4096 characters is none.
- **What the person does is the same everywhere.** Reloading shows the state of the address. Back and Forward walk the states of the workbench without loading the page: a person's own move - the rail, a tab, a row opened, a back link - adds an entry; keeping the address true to the screen replaces one. A move that leaves a form with unsaved input asks first, as its back link does (Save, Discard, Cancel), and on Cancel the address stays where the screen is. The title of the document follows the screen, and the focus goes where [Keyboard and focus](#keyboard-and-focus) says.

## Lists and tables

| Task | The one way | Block |
|---|---|---|
| The directory of a panel - the things it manages, as soon as a row shows more than a name and one detail or has actions of its own (pages, elements, redirects, fonts) | A **table** in `.nino-admin-table-scroll`: a search field over the full width above it that searches in the browser ("Search <things>"), sortable columns, small buttons at the right of each row (open, duplicate, delete in red), page size and paging below, "New <thing>" at the bottom right of the bar. The whole set is loaded and filtered in the browser. | `Nino.adminUi.table()` |
| Groups and short directories that are only opened (text groups, image groups, navigations, roles) | A **list**: a row of 53 px (the dense row of 46 px where it has no second line), the name in the strong weight, a quiet second line, a badge after the name, at the right at most three small actions and a "›" where the row opens. The whole row is the target, a real `a` (with an address) or `button`. A group has a title over its list. No chain of brackets in the first line: "Logo (/logo, 500x100, no image)" becomes a name and a second line. | `Nino.adminUi.list( rows, options )` |
| Column heads of a table | Words, never the help text of the field; the column of the actions has no head. | `table()` |
| Entries whose look is the content (a theme, a block) | **Cards** with a preview picture, all of the same height; a head line (name and badges) without a wrap - the name has half of the line at least, the badges give way first -, no description where the picture shows it, at most three small buttons with short words in ONE line at the bottom that share the width of the card. Cards are used for nothing else. | `cards( items )`, `scaleFrame()` for the picture |
| Entries that are only read | A dense list. | `.nino-admin-list-dense` |
| Ordered rows in a draft | The row with icon buttons up, down and remove at the end. | `.nino-admin-move`, `iconButton()`, `tree()` |
| Entries one under another with children - the components of a page, the outline of a template | A **tree**, the one form: a type icon before the kind (bold) and the content quietly after it, a guide line down every entry that holds others with a branch to each child, the chosen row a tint with a hairline of the accent colour and **no shadow**, the buttons of a row (insert, up, down, remove in red) only under the pointer, with the focus in the row and on the chosen row - on a touch screen on the chosen row alone -, a hint on a line of its own, a lock and the word on a locked row, the way to the chosen row as a line over the tree that chooses its rows, and a loop as its icon with its pattern "per element" on a dashed accent line, a field it reads as a tag that is never cut short. A panel draws no tree of its own. | `tree()`, `path : true` |
| The same thing in several views (preview, outline, tree) | The pointer or the focus over a thing marks the same thing in every other view that is open, in every direction; the tree, the outline and the frame of the preview are joined by one switchboard and mark without moving the focus; a marked row that lies outside the window of the tree is rolled into view, a row in view stays where it is (`highlight( ids, { scroll : false } )` leaves the tree alone). The preview can show the thing under the pointer as well: `previewFrame().marks( { elements, keyAt, onHover, hover : true } )` draws a quiet frame of one pixel - two rings, the ground of the workbench inside and its ink outside, so that it is seen on a light page and on a dark one - on exactly the border box of the innermost element of the page that stands for what the pointer is over; it goes when the pointer leaves the page, is not drawn on what is marked (the solid box stays), and takes no pointer; `onHover( key )` tells the thing the pointer is over, so that the tree and the outline mark it too. Without `hover` the marks are as they were. | `hoverSync()`, `tree().highlight()`, `previewFrame().marks()` |
| Actions of a row | Opening is the row. Further actions: at most three small buttons at the end of the row, in the order of the bar (side ... main), the dangerous one last and as `danger`; a fourth is refused. "Deactivate" is not dangerous, "Delete" and "Remove" are. No 38 px buttons in a row. With more than three, the first two stand as icon buttons and the rest are behind ONE icon button "More actions" - a menu of the kernel (`menuButton()`: arrow keys, Home and End, Esc closes and gives the focus back, a press beside it closes), the dangerous one last and red, a letter goes to the next entry that begins with it; an entry of a table's menu presses the button of the row, which is not in the page - its handler works through the row it was made for, not through where the button stands; a table does that by itself (`actionsInline`), a list still refuses a fourth. | `button( ..., { small : true } )`, `rowActions()`, `table( { actions } )`, `menuButton()` |
| Several rows at once | `table( { select : { actions } } )`: a box in front of every row, named by the row, and in the head one box for the rows of the page (none, some, all); the box of the head chooses the rows of the page that is shown; with one chosen a bar over the table says "n selected" and how many of them are not on this page (spoken as well), offers "Select all N matches" where the search matches more than the page shows, has the actions as buttons - what takes something away in red - and "Clear selection". A search, a sort and a page keep the choice; Shift and a click chooses a range. A table without the option has none of it. | `table( { select } )`, `selected()`, `clearSelection()` |
| New | "New <thing>" is the main button at the right of the bar of the list - in every panel. | `actionBar( { list : true, primary } )`; a list bar with several buttons (scan, import, export): `listActions( buttons )` |
| Search and filter | The search field of a table stands over its full width above it and searches in the browser; its placeholder is "Search <things>" (without "..."). Over a list a search field and what narrows it - a select, a chip that is on or off - stand in one bar. A select that picks a kind stands left of the search in the same line, with a name of its own for a screen reader; where the two do not fit - under 30 rem - the select stands over the search, both of the full width. The bar filters nothing: it draws, and tells what was chosen and typed, and the feature loads or filters. A table has it as `table( { filter } )`, a list that is no table - cards - as `filterBar( { filter, search } )`: the one bar, the table makes its toolbar of it. From 12 entries on a list SHOULD have a search. | `table( { search : true, searchText, filter } )`, `filterBar( { filter, search, items } )`, `chip()` |
| Empty | A box with one sentence and - where there is one - the next step as a button. Never a bare sentence, never a hint line. An empty result of a search or a filter says that it finds nothing; a table draws it itself. | `emptyState( text, action )` |
| Parts of a view | The second level of tabs, under the head whose own tabs are the first: a tablist with the arrow keys that wraps into a second row at 390 px and never scrolls sideways. | `tabs( items )` |
| A list that failed to load | The error stands in place of the list. | `showError()` |
| Order | Where the order of a list changes without a form (routes), it is saved at once and said as a toast; in a form it belongs to the draft. | - |

## Forms

| Task | The one way | Block |
|---|---|---|
| Field | The label stands at the LEFT of the field in every form, from a width of 48 rem (`.nino-admin-fieldgrid`) and in a dialog from 40 rem; in a narrow column (a phone, `split()`, the sheet of properties, a dialog under 40 rem) it stands above. A label has at most two lines; one that needs three is worded too long and is shortened - the rest belongs behind the "?". The same look on a desktop and on a phone is no aim. A placeholder is never the label. | `textField()`, `numberField()`, `selectField()`, `textareaField()`, `checkField()`, `codeField()`, `.nino-admin-fieldgrid` |
| Help | ALWAYS behind the (?) icon at the name of the field - never as text under the field, in a dialog and in a sheet of properties too. The icon is a button of 24 px named "Help for <field>" with `aria-expanded`; a click or Enter opens the sentence as a small bubble at the field, Esc and a click beside it close it. The sentence is always in the document and tied to the field by `aria-describedby`, so a screen reader hears it without a click. It explains what the value does. What is **not** help and stays visible at the field: the error, a counter, the limits of an upload, a conversion of the value ("= 1 h"), the result of a check (the contrast of a colour) and a warning with a consequence ("older ones are deleted", "a live site must leave this off"). A format ("one per line") is written twice: as the example value in the empty field (`placeholder`, an example and no substitute for the name) and in the help. The sentence is the option `help` of the field's block (`hint` draws the same); what stays visible is its option `note`. The label stands for the field: a press on the name or on the label's surface focuses the field, never the (?). The bubble opens under the (?), under the field where the field is there, else over the name - it never lies on the field it is about and never goes below the bar at the foot of the page. | `fieldHelp()`, `help`, `note` |
| Explanation of a section | The same (?) at the title of the section (the legend of a fieldset), named "Help for <title>"; the section is a group named by the words of its title and described by the sentence. Never a paragraph under the title: only a warning and an empty state stay visible. | `sectionHelp()` |
| Required | A star after the name and `aria-required`. Every required field, in every form. | `.nino-admin-required` |
| Checking | When saving, not while typing (exceptions: the colour field and a counter). A form carries `novalidate`; the browser's own bubble never appears. An error is a sentence under the field and a sentence in the line of the bar, the focus goes to the first wrong field. A button is not disabled because the input is invalid. | `fieldError()`, `clearFieldErrors()`, `status.fail()` |
| Wording of errors | empty: "<Name> is missing." - wrong: "<Name> is not valid: <what is expected>." - taken: "<Name> is already taken." Whole sentences with a full stop. | fills `/_admin/common/error/*` |
| Yes or no | A switch. Several yes-or-no properties of one thing that belong together (what a role may do, which parts a page has) may be ONE list of ticks; a column of switches is for properties that stand apart. | `switchField()`, `checklist()` |
| One tick with its name (a consent, "this is the main one") | One box with the label at the left, as every field; a required one has its star. A yes or no that is a setting is a switch. | `checkField()` |
| Several of a set | A group of ticks - a fieldset with a legend for its name, the (?) of its help behind the words - and a value that is the list of the ticked, in the order of the options; a row may have a quiet state at its end ("12 text keys"). For a short list; a long one is searched (`elementList()`). | `checklist()`, `.nino-admin-checklist` |
| One of two to five | The steps bar. | `steps()` |
| One of many | A select. | `selectField()` |
| Colour | Hex, picker and contrast. | `color()` |
| File | One button of the second kind - "Choose file", or the words the view gives it - with the name of the chosen file and its size beside it, "Remove" once there is one, the limits of the upload under it as a note and what is wrong with the file (too large, a type the field does not take) as the error of the field. The block does not upload: the view gets the `File` and sends it at once (and says so as a toast) or with the saving of the form - the view decides by [Saving](#saving), and a file it keeps in the form it reports as unsaved input. No bare `input[type=file]`. | `fileField()`, `uploadHint()`, `checkImage()` |
| Code, CSS, JSON | A field of several lines in a fixed-width face, at 12 px, with a scroll bar of its own: no editor, no highlighting, no spell checking. The Tab key is the browser's and moves the focus on - a field that took it would be a trap for the keyboard. Where the account may edit the textfills it has the "[ ]" like any text field. | `codeField()` |
| Values in several languages | The form shows the working language of the workbench. All languages under one another only for short values that are required in EVERY language (the name and address of a page or route, an alt text): one row per language, the language as a badge in front, the working language first and marked, a row one line or several, what is wrong said at the row it is wrong in. A language is named by the one block of the workbench - "Deutsch", "English (US)" -, the code quiet behind the name only where two of the languages that stand together are named alike. | `localeRows()`, `Nino.adminUi.localeName()` |
| Date and time | Two native fields, a date and a time, with ONE value: an ISO string of the clock of the site, "2026-11-17T06:02" - the zone of the site, not the browser's, so nothing is converted; the zone is said under the field. Empty is allowed, a date alone is midnight, a time without a date is no value; the earliest and the latest moment are `min` and `max`. | `dateTime()` |
| A field whose work textfills decide | An icon "[ ]" in the form, size and place of the (?) icon, directly beside it at the name of the field. It appears only for accounts that may edit the field, and only at: multi-line text fields, HTML+ fields, technical values and the sources of loops. A click opens a dialog with the available textfills (search, name, notation, today's value in the working language); **a click on a row copies the notation to the clipboard**, says so as a toast and closes the dialog - nothing is pasted at the caret. Without https or localhost the browser has no clipboard: the click then selects the notation in the row, a sentence in the dialog says to copy it with the keyboard (no toast, no mark of success), and the dialog stays. After a copy the focus is back in the field. The icon is the option `fills` of the field's block, which the caller sets only for an account that may edit the field. Anyone who may design chooses every source of a loop - choosing a source is not editing it. | `fills`, `fillsDialog()` |
| Character counter | "235 / 8000" at the right below the field, as every block that counts draws it (a text, a text of several lines, the editor of rich text). | `.nino-admin-char-counter` |
| Width of a field | The full width of the column; a number, a date, a colour or an identifier is short (12 rem). No further widths. | - |

```js
form.noValidate = true;
const name = Nino.adminUi.textField( { label : t('/_admin/roles/label/name'), name : 'name', required : true } );
// when saving
Nino.adminUi.clearFieldErrors( form );
if( name.input.value.trim() === '' ) {
	status.fail( t('/_admin/common/error/fields') );
	Nino.adminUi.fieldError( name.el, name.input, 'roles-name-error', Nino.adminUi.format( t('/_admin/common/error/missing'), t('/_admin/roles/label/name') ) );
	name.input.focus();
	return done( false );
}
```

## Saving

Three models. A view has exactly one.

| Model | When | Bar | Block |
|---|---|---|---|
| **A form** | the fields of a record or of a setting | `[Delete <thing>] [● Changed] ... line · [side actions] · [Save]` | `actionBar( { danger, status, secondary, primary } )`, `Nino.admin.dirty.watchForm()` |
| **B work surface** | a draft with a preview that is saved as a whole | `[● Changed] line ... [Undo] [Redo] [more] · [Save]` | `saveBar()`, `previewFrame()`, `conflict()` |
| **C immediate** | one single action without a form: upload, switch on or off, the order of a list, back up, duplicate, copy | no bar (or only a list's bar: `actionBar( { list : true, primary } )`, `listActions()`) | the answer as a toast, `say()` |

- **Exactly one main button per view, at the far right of the bar.** No Save button in the content, none per row. What reads "Apply" per row belongs to the form (model A) or is a view of its own.
- **The word:** "Save" for what exists; "Create <thing>" in a view or a dialog that creates; never "Apply", "OK", "Done". An effect beyond saving has a name of its own and stands to the LEFT of the main button ("Apply to website"), or it is the main button of a view of its own.
- **At the left** stands only "Delete <thing>" (`danger`) and beside it the state as a badge: "● Changed" / "✓ Saved".
- **The line:** "Saving ..." (only after 0.4 s), then empty; an error stays until something changes. A save that went well has two answers and no third: the badge of the bar changes to "✓ Saved" and the toast says "Saved." - the line carries an error or a hint and never a success, with a time or without. Success is never written as a hint line in the content.
- **After saving the view stays as it is.** A new thing gets its address; there is no jump to the list.
- **Unsaved input** is reported by every view of the models A and B to `Nino.admin.dirty`; the shell asks "Save · Discard · Cancel". `save( done )` ends on every way with `done( ok )`. **Ctrl/Cmd+S** saves through the registry.
- **Conflict (409):** model B asks with `conflict()`; model A shows the server's sentence in the line and leaves the form as it is.
- **Immediate (C)** says what happened, for 4 s, and to a screen reader (`role="status"`). The form is **one toast**, `say( text )`, at the bottom right above the bar, at most one at a time, and never for an error: an error stays at the field, in the line of the bar or in `showError()`, and `say()` refuses it. The same toast says the success of a form that was saved or deleted.

```js
const bar = Nino.adminUi.actionBar( {
	danger  : Nino.adminUi.button( t('/_admin/routes/label/delete'), remove, { kind : 'danger' } ),
	status  : true,                // the badge "● Changed" / "✓ Saved" and the line; the shell paints the badge
	primary : Nino.adminUi.button( t('/_admin/common/label/save'), null, { type : 'submit' } ),
} );
form.appendChild( bar.el );        // danger first, ONE main button last - a second one is refused
Nino.admin.dirty.watchForm( 'routes', function() { return form }, save );
Nino.adminUi.say( t('/_admin/common/msg/saved') );
```

## Dialogs

| Case | Form |
|---|---|
| A question before an action | `confirm( level, { title, message, yes, no } )` (asked of an open dialog - a form that asks before it throws something away - it says `nested : true`, and Esc closes the question alone); where the question is about things - what a deletion takes and what it keeps - `details`, a list under the message |
| Make or copy something that needs only a name and at most four fields (duplicate, "save as"): every field of the table above is a `type` of a field of the dialog - a tick, a list of ticks, the languages, a file, code, a moment - not only a text and a select | `formDialog()` |
| Pick something (a block, a source, a component) | `pickDialog()`, size `wide`; things that fall into groups say `group`, things that are known by their look say `image` (tiles in a grid, a quiet surface where there is no picture) |
| One tile chosen in each of several groups, then taken over (the header and the footer of a page, a look for the day and one for the night) | `tileChoice( { label, help, note, groups : [ { key, label, help, note, value, items : [ { value, label, hint, image, draw } ] } ], draw, onChange } )` - a field of the dialog (`type : 'tiles'` in `formDialog()`, the value an object group => value), the content of `choiceDialog()` (`el`) or free on a page; see below |
| The properties of ONE part of a work surface (a component, a logo, the menu of a frame) while the surface stays in view | `propertiesDialog()`: the title is the kind and name of the part, inside an `accordion()` with one section open (the content); the values go into the draft and the preview at once, the sheet has only "Close" - saving is done with the bar of the surface |
| Text to read (a manual, the available textfills, markup to look at) | `manualDialog()` |
| Everything else - every form of a record of its own with more than four fields, everything with an address of its own | a view of its own, no dialog |
| A section that opens inside a form | only `accordion()`; never a "Delete" or "Duplicate" form inside a form |

**Rules**

- **Block:** every dialog is `choiceDialog()` or a block on it. No `window.confirm()`, `alert()`, `prompt()`, no `<dialog>` of its own - not in the wizard and not on the recovery page either.
- **Details:** a question that is about things names them in `details`: lines of text, and groups `{ title, lines }` where the lines have a heading each ("Deleted", "Kept"). They are drawn as a list under the message, every line as text and never as markup. The caller cuts a long list short with a line of its own - "and 3 more" - so that the dialog stays a question and no report.
- **Title:** always. Verb and thing as a statement, without punctuation and without "...": "Delete element", "Duplicate theme". It is not a question. The text under it is the question in one sentence and - where something is lost - a second that says what.
- **Buttons:** one row at the right. In every dialog the order is: further answers, Cancel, and the main answer at the far right. The answer that goes on is worded like the title ("Delete element"), never "Yes" or "OK". Exactly one button is `primary` or `danger`.
- **Danger:** `danger` and the focus never on the destroying button: it opens on Cancel. Otherwise the focus opens on the first field or the first button.
- **Keyboard:** a modal dialog keeps the Tab key in itself: the rest of the page is inert while it is open, Tab at the last control goes to the first and Shift+Tab at the first to the last.
- **Closing:** Esc is the answer that does nothing. No "x", no closing by a click beside the dialog. The focus returns to what opened it.
- **Sizes:** `narrow` 26 rem (a question), `form` 40 rem, `wide` 60 rem, `sheet` 28 rem at the right over the whole height. Title and answers stand, the content scrolls; at 390 px the dialog fills the width. The sheet of `propertiesDialog()` is not modal from 38 rem: no backdrop, the surface moves in and stays in use.
- **Checking in a dialog** is the checking of a form (`fieldError()`); the dialog stays open until the server has said yes.

**When a question is asked** (`level`; the shell's policy: development asks only `destructive`, production asks everything):

| Level | Actions |
|---|---|
| `destructive` | delete · remove from disk · restore (a backup, the last state) · overwrite (an import, a conflict) · reset to the default |
| `big` | apply to the website · activate or deactivate · rename an identifier · change the mode or the native language · log everybody out |
| `routine` | whatever is done all day |
| no question, but "Undo" | on a work surface with a history (model B): remove, move, insert. The line of the bar says so: "... Undo brings it back." |
| no question | taking something out of an unsaved draft (the "x" of a row) |

Typing the identifier (`confirm( ..., { typed } )`) is only for a thing that takes many others with it (an element type with all of its elements).

```js
Nino.adminUi.confirm( 'destructive', {
	title    : t('/_admin/elements/confirm/delete-title'),        // "Delete element"
	message  : Nino.adminUi.format( t('/_admin/elements/confirm/delete'), name ),
	yes      : t('/_admin/elements/confirm/delete-title'),
	no       : t('/_admin/common/label/cancel'),
	onChoose : function( ok ) { if( ok === true ) remove() },
} );
```


**Tiles chosen by group.** `Nino.adminUi.tileChoice()` is the block for "one of these in each of these groups, then take them over": each group is a radiogroup (`role="radiogroup"`, named by its heading) of the tiles of `pickDialog()` - a picture on a quiet surface of a fixed shape over the name, with an optional line of its own (`hint`) - and the chosen tile has the accent round it. A group has one stop for the Tab key (the chosen tile, the first where none is chosen), the arrow keys walk the tiles in every direction and choose the one they reach (Left and Right along the order, Up and Down to the tile over or under, Home and End to the ends; they stay in the group and do not wrap), Tab goes on to the next group. A tile that is `disabled` is passed over. The block sends nothing: `onChange( groupKey, value, values )` tells every change, `value()` is the object group => value (`null` for a group in which nothing is chosen) that the caller reads where it takes the choice over - the main answer of the dialog -, `set( key, value )` chooses without telling. What explains a group stands behind the (?) of its heading (`help`), what has to stay in view under it (`note`) is a quiet line; the whole has a `label` with the same two (`help` is shown only with a `label`). The picture of a tile is `image` (an address; decoration, loaded when it comes near) or what the caller draws into its surface with the hook `draw( face, item, group )` - on the item or on the whole -, and a picture that cannot be had in one step (the page in a frame) is drawn later into the very same surface, which the handle gives as `faces[group][value]`. The block owns no strings.

```js
const frames = Nino.adminUi.tileChoice( { label : 'Frames of this page', help : 'The header and the footer of the site.', groups : [
	{ key : 'header', label : 'Header', value : 'default', items : [ { value : 'default', label : 'Standard' }, { value : 'landing', label : 'Landing', image : '/preview/landing.webp' } ] },
	{ key : 'footer', label : 'Footer', value : 'none', items : [ { value : 'default', label : 'Standard' }, { value : 'none', label : 'None' } ], draw : drawTheFramePicture },
], onChange : function( group, value, values ) { setSaveable( values ) } } );
Nino.adminUi.choiceDialog( { title, message, content : frames.el, choices, onChoose : function( answer ) { if( answer === 'set' ) apply( frames.value() ) } } );
```

## Feedback

| What | Form | Place | Block |
|---|---|---|---|
| Error at a field | A sentence, red, `aria-invalid` | under the field | `fieldError()` |
| Saving refused or failed | A sentence, `role="alert"` | the line of the bar | `status.fail()`, `status.error()`, `saveBar().line` |
| A list or view could not load | A sentence | in place of the content | `showError()` |
| Success of a form | The badge "✓ Saved" and a toast "Saved."; the line of the bar stays empty (`status.saved()` empties it and says the toast) | the bar, the toast above it | `say()`, `status.saved()` |
| Success of an immediate action | A toast: one sentence with "✓", 4 s, `role="status"`, it stays while the pointer rests on it or the focus is in it, at most one | bottom right above the bar | `say()` |
| Something needs attention until it is fixed | A box with a sign and a sentence, optionally a link | at the top of the content | `notice()` |
| The explanation of a whole view | The "?" of the head, which opens its manual | in the head | `panelHead()` - never a paragraph over the content; `hint()` is quiet text for what stands beside a thing (a line in a card, a note at a tree row), never success, error or emptiness, never the help of ONE field (that stands behind the (?)) |
| The explanation of a section of a form | The (?) at the title of the section | in the title | `sectionHelp()` - never a paragraph under the title |
| Nothing there | A box | in place of the list | `emptyState()` |
| The state of a thing | A badge with a word | beside the name | `.nino-admin-badge[data-state]` |
| Loading | The sentence "<Thing> is loading ...", `aria-busy` | in place of the content | - (no spinner, no skeleton) |
| Locked | The button is `disabled`; the reason stands behind a (?) icon at the button (and as `title`) | at the button | - |
| Session expired, conflict | A dialog | - | the shell, `conflict()` |

Colour never says something alone: red has a word, a badge has a sign (●, ✓). Red means "takes something away" or "has failed" - nothing else.

## Buttons and icons

| Kind | Class | For | Height |
|---|---|---|---|
| Main | `.nino-admin-btn-primary` | the one action of the view or dialog | 38 px |
| Normal | `.nino-admin-btn-secondary` | everything else | 38 px |
| Dangerous | `.nino-admin-btn-danger` | takes something away for good or switches the website off | 38 px |
| Small | the kind and `.nino-admin-btn--small` | in rows, cards, beside fields | 30 px |
| Icon | `iconButton()` | up, down, remove, add, reload, settings, markup ... in rows and toolbars | 28 x 28 px |
| Link | `.nino-admin-linkbutton` | a quiet action in a text ("Show all") | at least 24 px |

- Every button comes from `Nino.adminUi.button( text, onClick, { kind, small, icon, name, type } )` or `iconButton( icon, name, onClick, { danger, small } )`. No panel defines a button class; no button is without the class of its kind.
- **Icons are Lucide, everywhere.** A panel gets them from `Nino.adminUi.icon( name )`: an inline SVG in `currentColor`, 16 px, `aria-hidden`. Characters such as arrows, crosses and circled arrows are no icons and do not stand in buttons.
- **An icon without a word** only in rows and toolbars, and always with a name (`aria-label` and `title`). An icon before a word is set only by the block (`button( ..., { icon } )`), never by a character in the text. No "..." at a button.
- **Dangerous is red - on the website itself too:** "Delete" in the bar of a block carries the danger colour like every `danger` button.
- A navigation is a link (`a`), not a main button: "Edit" on a card is `secondary`.
- In every row of buttons the order is: side ... main (at the right). The dangerous one stands for itself (at the left of a bar, last in a row).

## Words

One word per thing. The fills are in `/_admin/common/label/*`; a panel takes them from there and writes none of its own for the same thing.

| Thing | German | English | Not |
|---|---|---|---|
| Take a thing away for good | **Löschen** / "<Ding> löschen" | Delete | Remove (for what is permanent) |
| Take a thing out of something, it stays (an image from its slot, a route from a navigation, a field from a draft) | **Entfernen** | Remove | Delete |
| Take the files of a feature from the disk | **Deinstallieren** | Uninstall | Remove |
| Button above a list | **Neue/r/s <Ding>** | New <thing> | Add, Create, "+" |
| Main button of a view that creates | **<Ding> anlegen** | Create <thing> | Save |
| Put a row into a draft | **<Ding> hinzufügen** | Add <thing> | New |
| Put a block at a place (page editor) | **<Ding> einfügen** | Insert <thing> | Add |
| Keep | **Speichern** | Save | Apply, OK, Done |
| Bring what is saved to the website | **Auf Website anwenden** | Apply to website | Activate, Publish |
| A copy | **Duplizieren** | Duplicate | Copy (that is the clipboard) |
| Leave a dialog, do nothing | **Abbrechen** | Cancel | Close, No |
| Leave a dialog that is only read | **Schließen** | Close | OK |
| Throw away what is unsaved | **Verwerfen** | Discard | Reset |
| Set to the system's default | **Zurücksetzen** | Reset | Discard |
| Get an earlier state | **Wiederherstellen** | Restore | Reset, Load |
| On or off of a feature, an account | **Aktivieren / Deaktivieren**; state "Aktiv / Inaktiv" | Activate / Deactivate | Switch on |
| Switch | **An / Aus** | On / Off | ON / OFF in capitals |
| Back link | **Zurück zu <Name>** | Back to <name> | Back to the list, All <things> |
| States of the bar | **Geändert · Gespeichert · Wird gespeichert ...** | Changed · Saved · Saving ... | Unsaved changes (only in the shell's dialog), Saved at hh:mm. |
| Search | **<Dinge> suchen** | Search <things> | filter |
| Empty | **Noch keine <Dinge>.** / filtered: **Keine <Dinge> gefunden.** | No <things> yet. / No <things> found. | - |
| Error at a field | **<Name> fehlt.** · **<Name> ist ungültig: ...** | <Name> is missing. · <Name> is not valid: ... | This required field is empty |
| Address of a page | **Adresse** (the technical identifier is the "Element URI", explained once) | Address | Uri |
| Language | **Deutsch**, **English (US)** - as the project names it for its own picker; the code behind it only where two are named alike | Deutsch, English (US) | de_DE alone, "German (de_DE)" |
| Navigation | its name ("Hauptmenü"), the identifier quiet behind it (`main`) | - | the identifier alone |
| Date | **17.11.2026, 06:02** (de) · **2026-11-17, 06:02** (en) - a date the workbench draws itself, which `Nino.adminUi.dateText()` writes from a value of the files (`2026-11-17 06:02`, `2026-11-17`) or a `Date`, with no seconds. `\Nino\Format::datetime()` is the formatter of the site's pages, not of the workbench: it writes American English (`11/17/2026, 6:02 AM`) for a language that is not German, as visitors read it, and a language that wants another form says so in its `/template/common/format/datetime` | | seconds; ISO in a German interface; the formatter of the site in the workbench |

"Zurücksetzen" / "Reset" puts what a person has set back to the default of the system and is asked at the level `destructive`, as a delete is ([Dialogs](#dialogs)).

Form: the German address is "Du/Dein/Dir" with a capital. Buttons and titles are in sentence case, without a full stop and without "..." (German: the verb in the infinitive at the end - "Route löschen"; English: the verb first - "Delete route"). Sentences end with a full stop. The ellipsis of a loading sentence is a space and "...". Quotation marks are „...“ in German, “...” in English. No raw key, type name or code stands as the only word: first the word, then the identifier in `code`. Every word is a fill, in both languages.

The setup wizard and the recovery page are bilingual: German is the default, English is offered at the top right, and the choice holds for every step and over a reload (one entry of the browser's storage, a value of an allow-list of two). They have no fills of the project to ask, so each has one table of its sentences with the same keys in both languages, and a refusal of the server is a code that table words. They use the same list of words and the same blocks as the panels - the dialog of a question, the fields, the buttons, the bar of the wizard - and the login and the session dialog of the workbench do too.

## Narrow widths

The workbench is fully usable on a phone, with one exception: a **work surface** (a page editor, a theme editor) only has to work there - every action is reachable, nothing runs over the edge, targets are at least 24 px - and is not arranged for it. It is built and checked for a desktop and a tablet.

- The page never scrolls sideways. What is wider (a table, code) scrolls in a frame of its own.
- Tabs wrap into a second row; nothing disappears behind the edge without a visible sign. The "?" stays in the row of the title.
- Labels stand above the fields (a narrow column); fields take the full width. The (?) icon stays at the name of the field.
- The bar stays at the bottom: the line above, the badge at the left and the buttons at the right below it; buttons become small (30 px), the words stay. No bar has more than four buttons.
- `split()` shows one side and the switch; a grid of cards is one column; a dialog fills the width, its title and buttons stay in sight.
- Targets are at least 24 x 24 px (icon buttons 28 px), at least 4 px between two targets.

## Keyboard and focus

- Whatever the mouse can do, the keyboard can. The order is the reading order: head, context bar, content, bar.
- The focus ring is `outline: 2px solid var(--editor-blue)` (fields: `var(--editor-focus)`) and is never removed.
- After a change of view the focus is on its heading (`tabindex="-1"`, without a visible ring for the mouse); after a dialog on what opened it; after the deletion of a row on the next one, else the previous, else the empty state; after a refused save on the first wrong field.
- A composed block has one tab stop and the arrow keys (`steps()`, `tree()`, `accordion()`, tabs). The buttons that only show under the pointer show with the focus as well: on the row that has the stop while the focus is in the tree, so Tab goes to its buttons from any row.
- Enter in a one-line field is the main button of the form or dialog. Esc closes dialogs and nothing else.
- Shortcuts: Ctrl/Cmd+S saves (the shell). Further shortcuts are assigned by the shell alone.
- Changes of state are announced: the line of the bar is `role="status"`, an error is `role="alert"`, loading is `aria-busy`.

## Tokens

A rule of the workbench, of a panel or of a feature writes no number for type, weight, space, radius, shadow, height or colour - it names a step. The steps are custom properties of `:root` in `_admin/assets/style.css` (`nino.system`), and they are the same for the kernel's panels and for a feature's.

| Scale | Tokens | Values |
|---|---|---|
| Type | `--nino-admin-text-xs`, `-sm`, `-md`, `-lg`, `-xl`, `-2xl` | 12 px (never less, whatever the root size is) · 12.6 px · 13.5 px · 15 px (the base) · 21 px (the title of a record) · 27 px (the title of a panel) |
| Weight | `--nino-admin-weight-normal`, `-medium`, `-strong`, `-heavy` | 400 · 550 · 650 · 750 |
| Space | `--nino-admin-space-1` ... `-8`, `--nino-admin-space-legend` | .25 · .375 · .5 · .625 · .75 · 1 · 1.25 · 1.5 rem; 2.5 rem above the first field of a card with a legend |
| Radius | `--editor-radius-sm`, `--editor-radius`, `--nino-admin-radius-pill` | 7 px · 10 px · 999 px |
| Shadow | `--editor-shadow-sm`, `--editor-shadow` | |
| Height | `--nino-admin-control-height`, `--nino-admin-control-small`, `--nino-admin-control-icon`, `--nino-admin-control-icon-small`, `--nino-admin-row-height`, `--nino-admin-row-dense`, `--nino-admin-row-compact` | 38 px (44 px on a coarse pointer) · 30 px · 28 px · 24 px · 53 px (a row of a list with a second line) · 46 px (a dense row, a switch; 50 px on a coarse pointer) · 34 px (an entry of the rail, a tree row) |
| Tree | `--nino-admin-tree-indent`, `--nino-admin-tree-guide`, `--nino-admin-tree-lead` | 1.4 rem (the indent of one level) · 14 px (where a guide line runs, from the left edge of the entry's row) · 2.5 rem (where a hint and the tag of a loop begin, in line with the names) |
| Colour | `--editor-*` | text only in `--editor-text`, `--editor-text-secondary`, `--editor-blue`, `--editor-red-ink`, `--editor-green`, `--editor-orange-ink`; the word of a danger button in `--nino-admin-danger-ink` |

Space is for `padding`, `margin` and `gap`. A value is the nearest step (a tie goes up); the steps are the values the sheet used most, so that what is rounded is the exception and the look of the workbench stays. What lies below half of the smallest step (under .125 rem) is the width of a hairline and not a space, and stays as it is, as do `0`, `auto`, `em` (which follow the type), a percentage, and a pixel value of a border or an overlap. What a rule needs beyond a step is written once as an exception with a comment that says why and how it was measured; the exceptions of the design system are these:

- The root size, `15px`, which the steps count from.
- The size of a tile's value (`1.6rem`): a date of ten characters fills a tile of 155 px at this size and wraps at the step above, at 390 px wide.
- Sizes in `em` (code inside a text, a fieldset) and `inherit`: they follow the text around them.
- The widths of a label column (`7rem`, `11rem`), the top margin of the login card (`clamp( 2rem, 9vh, 6rem )`) and the room of the arrow of a select (`14px`), which are layout, not space.
- The heights `54px` (a choice with a name and a line under it) and the radius `12px` (the card of the login).

Each exception stands at its place in `style.css` with a comment, and the test names it by selector, property and value.

**Where the rule holds.** The rule is kept by `_admin/assets/style.css`, and the test holds it there. The stylesheets of the kernel's panels (`_admin/Nino/Modules/*/assets`) and those of the features still write sizes of their own: each is brought to the tokens by the change that brings its panel to this chapter, and from then on the same test holds it.

What is drawn on the website itself (the bar of a block in the page editor) MUST read the same tokens: the block hands them on as custom properties into its shadow DOM and keeps no colour, size or radius of its own.

## What a panel does not build itself

A panel (script, template, stylesheet - the kernel's or a feature's) MUST NOT:

1. create a button other than with `Nino.adminUi.button()` or `iconButton()`, or define a button, chip or link-button class;
2. open a dialog other than through `choiceDialog()` and the blocks on it, or use `window.confirm`, `alert` or `prompt`;
3. write a bar (`.nino-admin-actionbar`, `-savebar`, `-contextbar`) by hand, or show more than one bar per view;
4. rebuild list rows, cards, badges, tabs, switches, error, success, empty and hint lines with classes of its own;
5. restyle a `nino-admin-*` class in its stylesheet (other than spacing and arrangement in its own root element), write a fixed colour, size, radius or shadow, or read a token that `style.css` does not declare;
6. write a rule without its own root element (`#themes-app ...`) - a dialog gets its size from the size class of the kernel, not from an unscoped panel rule;
7. give a thing of the list of words a name of its own, or write a sentence in a script;
8. use the browser's checking (`required` without `novalidate`), a placeholder as a label, a help text under a field, or a language switch of its own for content;
9. write success as a `hint` into the content, or change the view after saving;
10. show a view without an address, without the back link of [Anatomy](#anatomy-of-a-view), or with a second back link;
11. write an address from strings (`'#' + ...`, `location.hash = ...`) or split a key at its slashes: the router writes it, and a key is one part ([Addresses](#addresses)).

A panel's stylesheet then holds only the arrangement of its own content (a grid, spaces from the scale) and what exists only there (the swatches of a palette, the preview tile of a block).

## Checklist

For every review of a view. A line marked **[T]** can be held as a structural test.

**Build**
- [ ] A list or form view: head with title and "?", tabs only in the head. A work surface: no head, the panel's name small in the context bar. An address for every level, in the form of [Addresses](#addresses) and written by the router; reloading keeps it. [T]
- [ ] Exactly one back link, "Back to <name>", in the context bar.
- [ ] At most one bar; exactly one main button, at the far right; "Delete <thing>" at the left. [T]

**Lists**
- [ ] A list, table or cards from the block; the row opens; actions small, the dangerous one last.
- [ ] The empty state as a box; a load error in place of the list. [T]

**Forms**
- [ ] Every field has a `label`, at the left of the field (narrow: above), at most two lines; help only behind the (?); required with a star; the textfill icon only at the kinds of field named above.
- [ ] No language switch of its own; a view that depends on the language follows `Nino.admin.workLocale` and shows the badge of the working language (`localeBadge()`), languages named by `localeName()`. [T]
- [ ] `novalidate`; errors with `fieldError()` and the line; the focus on the first error. [T]
- [ ] Yes/no is a switch; one tick with a name is `checkField()`; several of a set are `checklist()`; one of a few is `steps()`; a colour is `color()`; a file is `fileField()` with its limits; code is `codeField()`; a moment is `dateTime()`; the languages are `localeRows()`.

**Saving**
- [ ] One model (A, B or C); no Save button in the content. [T]
- [ ] `Nino.admin.dirty` is registered; `save( done )` always ends; Ctrl+S saves. [T]
- [ ] After saving the view stays; the badge says "Saved", the toast says it, the line is empty. [T]

**Dialogs**
- [ ] A block; a title; the button is worded like the action; Cancel at the left of the main answer; the level by the table; a dangerous one opens on Cancel. [T]
- [ ] No dialog for a form of more than four fields.

**Words**
- [ ] Words from the list; every word a fill in German and English; no raw identifier alone; "Du" with a capital; no "..." at a button. [T partly]

**Buttons and values**
- [ ] Every button from the block, with the class of its kind; an icon button with a name. [T]
- [ ] The stylesheet of the panel: scoped, only tokens, no restyling of a `nino-admin-*` class. [T]

**Measures**
- [ ] 390 px: no sideways scrolling, nothing hidden (a work surface only has to be reachable); targets at least 24 px; text at least 12 px; contrast at least 4.5 : 1, light and dark.
- [ ] Keyboard: everything reachable, the focus visible, the focus in the right place after a dialog, a deletion and an error.

## What the tests pin

`tests/admin-guide-smoke.php` reads the sources of the workbench and holds what can be held by structure, never by words: every `--nino-admin-*` token that a stylesheet or script of `_admin` reads is declared, every `nino-admin-*` class that a script of `_admin` uses has a rule in `style.css`, and `style.css` names type and space only through the tokens, apart from the exceptions above; the login, the wizard and the recovery page are held to the forms, the dialogs and the bar of the blocks, and the stylesheet of the wizard to the tokens. A rule of this chapter that can be held by structure is added to that suite in the change that brings the block it needs.
