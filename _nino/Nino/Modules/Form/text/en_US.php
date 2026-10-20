<?php
// The Form module's own workbench strings, merged into its fills
// while the module is active (see the panel's text()) - same keys and
// shape the workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/submissions]]'		=> 'Submissions',
	'[[/_admin/dashboard/label/submissions]]' => 'Submissions',
	'[[/_admin/submissions/error/load]]'		=> 'Failed to load.',
	'[[/_admin/submissions/empty]]'				=> 'No submissions yet.',
	'[[/_admin/submissions/nomatch]]'			=> 'No submission matches the filter.',
	'[[/_admin/submissions/label/form]]'		=> 'Form',
	'[[/_admin/submissions/label/form-all]]'	=> 'All forms',
	'[[/_admin/submissions/label/search]]'	=> 'Search submissions',
	'[[/_admin/submissions/confirm/delete]]'	=> 'Delete the submission of %s? It is gone from this server; the mail it sent is not affected.',
	'[[/_admin/submissions/label/filename]]'	=> 'submissions.csv',
	'[[/_admin/submissions/label/export]]'	=> 'Export as CSV',
	'[[/_admin/submissions/label/more]]'		=> 'Show more',
	'[[/_admin/submissions/label/less]]'		=> 'Show less',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/submissions/manual/intro]]'	=> 'The messages that visitors sent through the forms of your website, the newest first.',
	'[[/_admin/submissions/manual/form]]'	=> 'The Form select narrows the list to one form.',
	'[[/_admin/submissions/manual/search]]'	=> 'The search looks through every value of every submission, and through the names of the fields too.',
	'[[/_admin/submissions/manual/export]]'	=> 'Export as CSV downloads what is on screen: with a form selected, the submissions of that form. A spreadsheet opens the file.',
	'[[/_admin/submissions/manual/delete]]'	=> 'Delete removes a submission from this server. The mail it sent is not affected.',
];
