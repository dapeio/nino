<?php
// The workbench shell's own words - and only those. Every screen is a module
// under _admin/Nino/Modules/ and brings its own text/<locale>.php (see the
// panel contract in Admin.php); what is left here is what the shell itself
// renders - the login form, the account chrome, the navigation's group
// headings - plus the two sets any panel may reach for: the rich-text
// editor's toolbar (_admin/assets/html-editor.js) and /_admin/common/*, the
// words that would otherwise be written once per module.
return [
	'[[/_admin/login/label/user]]'	=> 'Email',
	'[[/_admin/login/label/pw]]'		=> 'Password',
	'[[/_admin/login/label/submit]]'=> 'Log in',
	'[[/_admin/login/error/user]]'	=> 'Email is required.',
	'[[/_admin/login/error/pw]]'		=> 'Password is required.',
	'[[/_admin/login/error/wrong]]'	=> 'Check your input or contact the administrator.',
	'[[/_admin/login/msg/welcome]]'	=> 'Enter your email and password:',
	'[[/_admin/login/msg/pending]]'	=> 'Checking your input.',

	'[[/_admin/user/logout]]'				=> 'Log out',
	'[[/_admin/user/theme]]'					=> 'Toggle light/dark',
	'[[/_admin/user/settings]]'			=> 'Settings',
	'[[/_admin/user/rail]]'					=> 'Fold or unfold the navigation',

	'[[/_admin/label/rail]]'			=> 'Workbench navigation',
	'[[/_admin/label/nav]]'			=> 'Workbench sections',
	'[[/_admin/nav/group/content]]'		=> 'Content',
	'[[/_admin/nav/group/structure]]'	=> 'Structure',
	'[[/_admin/nav/group/system]]'		=> 'System',

	'[[/_admin/htmleditor/label/strong]]'	=> 'Bold',
	'[[/_admin/htmleditor/label/em]]'			=> 'Italic',
	'[[/_admin/htmleditor/label/span]]'		=> 'Highlight',
	'[[/_admin/htmleditor/label/code]]'		=> 'Code',
	'[[/_admin/htmleditor/label/a]]'				=> 'Link',
	'[[/_admin/htmleditor/label/linkplaceholder]]'	=> 'https://…',
	'[[/_admin/htmleditor/label/linkok]]'					=> 'Apply',
	'[[/_admin/htmleditor/label/linkcancel]]'			=> 'Cancel',
	'[[/_admin/htmleditor/label/formatting]]'			=> 'Text formatting',
	'[[/_admin/htmleditor/label/content]]'				=> 'Formatted text',

	'[[/_admin/common/label/image-target]]'		=> 'Target size:',
	'[[/_admin/common/label/locale]]'		=> 'Translation',
	'[[/_admin/common/label/save]]'			=> 'Save',
	'[[/_admin/common/label/create]]'		=> 'Create',
	'[[/_admin/common/label/delete]]'		=> 'Delete',
	'[[/_admin/common/label/remove]]'		=> 'Remove',
	'[[/_admin/common/label/rename]]'		=> 'Rename',
	'[[/_admin/common/label/add]]'			=> 'Add',
	'[[/_admin/common/label/back]]'			=> 'Back to list',
	'[[/_admin/common/label/moveup]]'		=> 'Move up',
	'[[/_admin/common/label/movedown]]'	=> 'Move down',
	'[[/_admin/common/label/ignore]]'		=> 'Ignore',
	'[[/_admin/common/label/uri]]'			=> 'Uri',
	'[[/_admin/common/label/label]]'		=> 'Label',
	'[[/_admin/common/label/global]]'		=> 'Global',
	'[[/_admin/common/label/on]]'				=> 'on',
	'[[/_admin/common/label/off]]'			=> 'off',
	'[[/_admin/common/msg/saving]]'			=> 'Saving …',
	'[[/_admin/common/msg/saved]]'			=> 'Saved.',
	'[[/_admin/common/msg/deleting]]'		=> 'Deleting …',
	'[[/_admin/common/msg/scanning]]'		=> 'Scanning …',
	'[[/_admin/common/msg/creating]]'		=> 'Creating %s (%d / %n) …',
	'[[/_admin/common/error/load]]'			=> 'Failed to load.',
	'[[/_admin/common/error/save]]'			=> 'Failed to save.',
	'[[/_admin/common/error/delete]]'		=> 'Failed to delete.',
	'[[/_admin/common/error/rename]]'		=> 'Failed to rename.',
	'[[/_admin/common/error/scan]]'			=> 'Scan failed.',
	'[[/_admin/common/error/request]]'	=> 'Request failed.',
	'[[/_admin/common/label/width]]'		=> 'Width (px)',
	'[[/_admin/common/label/height]]'		=> 'Height (px)',
	'[[/_admin/common/label/restore]]'	=> 'Restore',
	'[[/_admin/common/unit/seconds]]'		=> 'seconds',
];
