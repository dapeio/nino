<?php
// The Config module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/config]]'						=> 'Config',
	'[[/_admin/config/group/diagnostics]]'	=> 'Errors and diagnostics',
	'[[/_admin/config/intro/diagnostics]]'	=> 'What happens when php raises an error, how the session cookie is issued, and which address a visitor is counted as.',
	'[[/_admin/config/group/editor]]'		=> 'Workbench',
	'[[/_admin/config/intro/editor]]'		=> 'Background work /_admin does on its own.',
	'[[/_admin/config/group/cache]]'		=> 'Page cache',
	'[[/_admin/config/intro/cache]]'		=> 'Serving a stored copy skips the render. Off by default – switch it on once the site is finished, not while building it.',
	'[[/_admin/config/label/errorlog]]'	=> 'Write errors to a log',
	'[[/_admin/config/hint/errorlog]]'	=> 'Appends php errors to /data/logs.<month>.php, pruned after three months.',
	'[[/_admin/config/label/errordisplay]]'	=> 'Show errors in the frontend',
	'[[/_admin/config/hint/errordisplay]]'	=> 'Development only. A live site must leave this off – the dump includes file paths and a stack trace.',
	'[[/_admin/config/label/securecookie]]'	=> 'Always set the session cookie as secure',
	'[[/_admin/config/hint/securecookie]]'	=> 'Turn on behind a tls-terminating proxy, where php sees no HTTPS of its own and would otherwise leave the flag off.',
	'[[/_admin/config/label/proxies]]'	=> 'Reverse proxies in front of this site',
	'[[/_admin/config/hint/proxies]]'	=> 'One address or cidr range per line, and only proxies you operate or pay for. Left empty, a visitor is whoever php is talking to – which behind a proxy is the proxy, so every per-ip limit on the site counts all visitors as one. Filled in, the visitor is read from X-Forwarded-For, which anyone can set: an address in this list that is not actually a proxy hands out that spoof.',
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

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/config/manual/intro]]'	=> 'Settings that apply to the whole project and rarely change – mostly for developers.',
	'[[/_admin/config/manual/error]]'	=> '“Errors and diagnostics” decides whether PHP errors are written to a log or shown on the site, how the session cookie is issued and which proxies stand in front of the site. A live site never shows errors.',
	'[[/_admin/config/manual/backup]]'	=> 'Daily encrypted backup switches the automatic backup on or off. The backups are restored under Backups.',
	'[[/_admin/config/manual/log]]'	=> 'Record an audit trail switches the Log on or off: one line per login and per change, kept for fourteen days.',
	'[[/_admin/config/manual/cache]]'	=> 'Cache rendered pages serves stored copies of pages instead of rendering them again. Switch it on when the site is finished, not while you build it. Every save in the workbench empties the whole cache.',
];
