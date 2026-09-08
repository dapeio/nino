<?php
// The Maintenance module's own workbench strings, merged into its fills
// while the module is active (see the panel's text()) - same keys and
// shape the workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/maintenance]]'						=> 'Wartung',
	'[[/_admin/maintenance/state/online]]'		=> 'Die Website ist online.',
	'[[/_admin/maintenance/state/offline]]'	=> 'Die Website zeigt die Wartungsseite.',
	'[[/_admin/maintenance/label/status]]'		=> 'Wartungsseite anzeigen',
	'[[/_admin/maintenance/hint/status]]'		=> 'Jeder Besuch erhält eine 503-Antwort statt der Website, solange dies aktiv ist.',
	'[[/_admin/maintenance/label/retry]]'		=> 'Besucher um erneuten Versuch bitten nach',
	'[[/_admin/maintenance/hint/retry]]'			=> 'Wird als Retry-After-Header gesendet, damit ein braver Browser oder Bot so lange wartet, bevor er es erneut versucht.',
	'[[/_admin/maintenance/hint/signedin]]'	=> 'Ein angemeldetes Konto sieht die Website weiterhin wie gewohnt – öffne sie in einem anderen Browser oder melde dich ab, um zu prüfen, was Besucher sehen.',
	'[[/_admin/dashboard/label/maintenance]]'	=> 'Wartungsmodus aktiv',
];
