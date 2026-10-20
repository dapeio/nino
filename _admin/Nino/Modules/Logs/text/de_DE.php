<?php
// Die eigenen Workbench-Texte des Logs-Moduls, in seine Fills gemischt,
// solange das Modul aktiv ist (siehe text() des Panels) - dieselben
// Schlüssel und dieselbe Form wie text/<locale>.php der Workbench
return [
	'[[/_admin/nav/logs]]'					=> 'Log',
	'[[/_admin/logs/error/load]]'					=> 'Laden fehlgeschlagen.',
	'[[/_admin/logs/empty]]'							=> 'Noch keine Einträge.',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/logs/manual/intro]]'	=> 'Wer wann was geändert hat: jede Anmeldung und jede Änderung in der Workbench, mit Zeit und Konto.',
	'[[/_admin/logs/manual/list]]'	=> 'Die neueste Zeile steht oben. Hier wird nichts von Hand geschrieben und nichts verändert.',
	'[[/_admin/logs/manual/day]]'	=> 'Die Zeilen werden vierzehn Tage aufgehoben. Ob sie überhaupt geschrieben werden, stellst Du unter Konfiguration ein.',
];
