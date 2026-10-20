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
	'[[/_admin/maintenance/hint/signedin]]'	=> 'Ein angemeldetes Konto sieht die Website weiterhin wie gewohnt – öffne sie in einem anderen Browser oder melde Dich ab, um zu prüfen, was Besucher sehen.',
	'[[/_admin/dashboard/label/maintenance]]'	=> 'Wartungsmodus aktiv',
	'[[/_admin/maintenance/banner/text]]'	=> 'Die Wartung ist aktiv – Besucher sehen die Wartungsseite.',
	'[[/_admin/maintenance/banner/link]]'	=> 'Zur Wartung',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/maintenance/manual/intro]]'	=> 'Schaltet die ganze Website auf eine Wartungsseite um, solange Du daran arbeitest.',
	'[[/_admin/maintenance/manual/maintenance]]'	=> 'Solange „Wartungsseite anzeigen“ eingeschaltet ist, bekommt jeder Besucher statt der Website eine 503-Antwort – Impressum, Datenschutzerklärung und Anmeldung bleiben erreichbar – und ein Hinweis in der Workbench erinnert Dich daran.',
	'[[/_admin/maintenance/manual/retry]]'	=> '„Besucher um erneuten Versuch bitten nach“ wird als Retry-After-Header gesendet: Ein wohlerzogener Browser oder eine Suchmaschine wartet so lange, bevor sie es wieder versucht.',
	'[[/_admin/maintenance/manual/account]]'	=> 'Ein angemeldetes Konto sieht die Website weiterhin, wie sie ist. Um zu prüfen, was ein Besucher sieht, öffne sie in einem anderen Browser oder melde Dich ab.',
];
