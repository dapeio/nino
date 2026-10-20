<?php
// The Navigation module's own workbench strings, merged into its fills
// while the module is active (see the panel's text()) - same keys and
// shape the workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/navs]]'							=> 'Navigations',
	'[[/_admin/navs/empty]]'						=> 'No navigations yet – add one below.',
	'[[/_admin/navs/empty-entries]]'		=> 'No entries yet – add a route below.',
	'[[/_admin/navs/label/new]]'				=> 'New navigation',
	'[[/_admin/navs/label/navigation]]'	=> 'Navigation',
	'[[/_admin/navs/label/key]]'				=> 'Navigation id – the name a template asks for with the navigation shortcode, e.g. “main”',
	'[[/_admin/navs/placeholder/key]]'	=> 'Navigation id, e.g. “footer”',
	'[[/_admin/navs/hint/rename]]'			=> 'Renaming updates the routes in this navigation, not the templates rendering it – update the nav argument of their navigation shortcode yourself.',
	'[[/_admin/navs/label/delete]]'			=> 'Delete navigation',
	'[[/_admin/navs/label/entries]]'		=> 'Entries',
	'[[/_admin/navs/label/unnamed]]'		=> '– not named yet, so it stays out of the menu',
	'[[/_admin/navs/label/unnamed-short]]'	=> '– not named yet',
	'[[/_admin/navs/label/moveup]]'			=> 'Move up in this navigation',
	'[[/_admin/navs/label/movedown]]'		=> 'Move down in this navigation',
	'[[/_admin/navs/label/remove]]'			=> 'Remove from this navigation – the route itself stays',
	'[[/_admin/navs/label/add]]'				=> 'Add a route – it joins at the end',
	'[[/_admin/navs/label/addbtn]]'			=> 'Add to navigation',
	'[[/_admin/navs/label/choose]]'	=> 'Choose a route…',
	'[[/_admin/navs/msg/unsaved]]'	=> 'Unsaved changes – Save writes the running order.',
	'[[/_admin/navs/confirm/discard]]'	=> 'The changes to this navigation are not saved. Leave without saving?',
	'[[/_admin/navs/confirm/delete]]'		=> 'Really delete the navigation “%s”? Its routes stay, only their membership goes.',
	'[[/_admin/error/navs_invalid_id]]'	=> 'The navigation id “%s” is not valid. Start with a lowercase letter, then use lowercase letters, digits, hyphens and underscores.',
	'[[/_admin/error/navs_id_taken]]'	=> 'The navigation id “%s” is already taken.',
	'[[/_admin/error/navs_invalid_entries]]'	=> 'The navigation can only be saved with a list of routes.',
	'[[/_admin/error/navs_duplicate_entry]]'	=> '“%s” is in the list twice.',
	'[[/_admin/error/navs_unknown_route]]'	=> 'The route “%s” does not exist (any more).',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/navs/manual/intro]]'	=> 'A navigation is a menu, such as main or footer, made of routes. A template shows it with the navigation shortcode.',
	'[[/_admin/navs/manual/navigation]]'	=> 'New navigation asks for an id: the name a template asks for. Renaming an id updates the routes in it, not the templates that show it.',
	'[[/_admin/navs/manual/item]]'	=> 'An entry is a route. Add to navigation puts it at the end, the arrows move it, and Remove takes it out of this menu – the route itself stays.',
	'[[/_admin/navs/manual/name]]'	=> 'A route stands in the menu under the name it has there. A route with no name yet stays out of the menu until it gets one.',
	'[[/_admin/navs/manual/save]]'	=> 'Save writes the running order. Leaving with unsaved changes asks first.',
	'[[/_admin/navs/manual/delete]]'	=> 'Delete navigation removes the menu and nothing else: its routes stay, only their membership goes.',
];
