<?php
// The Dashboard module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/dashboard]]'			=> 'Dashboard',
	'[[/_admin/dashboard/error/load]]'			=> 'Failed to load.',
	'[[/_admin/dashboard/label/lastbackup]]' => 'Latest backup',
	'[[/_admin/dashboard/label/elements]]'	=> 'Elements by type',
	'[[/_admin/dashboard/label/activity]]'	=> 'Recent activity',
	'[[/_admin/dashboard/label/all]]'				=> 'Show all',
	'[[/_admin/dashboard/empty/elements]]'	=> 'No element types yet.',
	'[[/_admin/dashboard/empty/activity]]'	=> 'No entries yet.',
	'[[/_admin/dashboard/notice/mail]]'	=> 'Mail delivery has been failing since %s (failed attempts: %s). Check mail() on the server, the Mailer settings or the recipient address.',
	'[[/_admin/dashboard/notice/untranslated]]'	=> 'Texts not translated yet (%s) in %s.',
	'[[/_admin/dashboard/notice/legal-unknown]]'	=> 'There is no value for the detail %s in the section “%s” (%s). Add it under Texts or take the detail out of the section.',
	'[[/_admin/dashboard/notice/legal-empty]]'	=> 'The detail %s in the section “%s” (%s) is empty. Enter a value under Texts.',
	'[[/_admin/dashboard/notice/legal-ignored]]'	=> 'The detail %s in the section “%s” (%s) is never replaced: only keys of four parts below /project/company/ and /project/website/general/ are allowed.',
	'[[/_admin/dashboard/notice/legal-translation]]'	=> 'The section “%s” has neither a heading nor a text in %s. Until it is translated, the page shows the version of the native language.',
	'[[/_admin/dashboard/notice/legal-nothing]]'	=> 'The element type “%s” is missing or has no section that can be seen. If it was deleted, copy the file %s back to private/elements/.',
	'[[/_admin/dashboard/notice/legal-route]]'	=> 'Legal page “%s” (%s): the path %s in /nino/legal/paths is invalid or already belongs to another route. Enter a path of its own that is free in config.php.',
	'[[/_admin/dashboard/notice/legal-route-derived]]'	=> 'Legal page “%s” (%s): /nino/legal/paths has no path for this language, so the page is reached under %s. Enter another one in config.php if you want it.',
	'[[/_admin/dashboard/notice/legal-route-none]]'	=> 'Legal page “%s” (%s): this language has no route - /nino/legal/paths has no path for it, and the derived one (%s) is missing or taken. Enter a path of its own that is free in config.php.',
	'[[/_admin/dashboard/notice/legal-route-stored]]'	=> 'The stored route %s carries the Element URI of the legal page “%s”, which belongs to the module. Give it an Element URI of its own or remove it under Routes.',
	'[[/_admin/dashboard/notice/legal-nav]]'	=> '“%s” is missing from every menu of the website in %s. Add the page to the “legal” navigation under Navigations and give it a name in every language under Routes.',
	'[[/_admin/dashboard/notice/legal-feature]]'	=> 'The feature “%s” is switched off, but the privacy policy still describes it: %s. Hide these sections under Elements › Privacy policy if they no longer apply.',
	'[[/_admin/dashboard/notice/legal-undescribed]]'	=> 'The feature “%s” is active, but the privacy policy has no section about it that can be seen. Show it again under Elements › Privacy policy or describe the feature there yourself.',
	'[[/_admin/dashboard/notice/legal-more]]'	=> '… and %s more notices about the legal texts.',
	'[[/_admin/dashboard/notice/open]]'	=> 'Open',
];
