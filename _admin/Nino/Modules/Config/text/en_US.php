<?php
// The Config module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/config]]'						=> 'Config',
	'[[/_admin/config/group/diagnostics]]'	=> 'Errors and diagnostics',
	'[[/_admin/config/intro/diagnostics]]'	=> 'What happens when php raises an error, and how the session cookie is issued.',
	'[[/_admin/config/group/editor]]'		=> 'Editor features',
	'[[/_admin/config/intro/editor]]'		=> 'Background work /_admin does on its own. Both were silently on in every project before 0.12.0-beta – see the changelog.',
	'[[/_admin/config/group/cache]]'		=> 'Page cache',
	'[[/_admin/config/intro/cache]]'		=> 'Serving a stored copy skips the render. Off by default – switch it on once the site is finished, not while building it.',
	'[[/_admin/config/label/errorlog]]'	=> 'Write errors to a log',
	'[[/_admin/config/hint/errorlog]]'	=> 'Appends php errors to /data/logs.<month>.php, pruned after three months.',
	'[[/_admin/config/label/errordisplay]]'	=> 'Show errors in the frontend',
	'[[/_admin/config/hint/errordisplay]]'	=> 'Development only. A live site must leave this off – the dump includes file paths and a stack trace.',
	'[[/_admin/config/label/securecookie]]'	=> 'Always set the session cookie as secure',
	'[[/_admin/config/hint/securecookie]]'	=> 'Turn on behind a tls-terminating proxy, where php sees no HTTPS of its own and would otherwise leave the flag off.',
	'[[/_admin/config/label/backups]]'	=> 'Daily encrypted backup',
	'[[/_admin/config/hint/backups]]'		=> 'Runs once a day on the first request after midnight and keeps fourteen days. Restore them under Backups.',
	'[[/_admin/config/label/logs]]'			=> 'Record an audit trail',
	'[[/_admin/config/hint/logs]]'			=> 'One line per login and per change made in /_admin, kept for fourteen days. Who may read it stays a permission.',
	'[[/_admin/config/label/cachestatus]]'	=> 'Cache rendered pages',
	'[[/_admin/config/hint/cachestatus]]'	=> 'Anonymous GET requests only, and never a page with query vars, a tool uri or a signed-in visitor. Any save in the workbench drops the whole cache.',
	'[[/_admin/config/label/cachettl]]'	=> 'Lifetime of a cached page',
	'[[/_admin/config/hint/cachettl]]'	=> 'How long a stored page is served before it is rendered again. Edits do not wait for this – they drop the cache immediately.',
	'[[/_admin/config/label/cacheblacklist]]'	=> 'Never cache these',
	'[[/_admin/config/hint/cacheblacklist]]'	=> 'One uri per line. A trailing /* covers a whole subtree: /blog/* matches /blog and everything below it. Use it for anything that has to be rendered per visit.',
];
