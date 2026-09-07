<?php
// The Navigation module's own workbench strings, merged into its fills
// while the module is active (see the panel's text()) - same keys and
// shape the workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/navs]]'							=> 'Navigations',
	'[[/_admin/navs/inactive]]'					=> 'The Navigation module is not active – these menus are stored, but nothing renders them. Activate \\Nino\\Modules\\Navigation under Config.',
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
	'[[/_admin/navs/confirm/delete]]'		=> 'Really delete the navigation “%s”? Its routes stay, only their membership goes.',
];
