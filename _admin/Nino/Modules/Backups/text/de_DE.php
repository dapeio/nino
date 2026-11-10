<?php
// Die eigenen Workbench-Texte des Backups-Moduls, in seine Fills gemischt,
// solange das Modul aktiv ist (siehe text() des Panels) - dieselben
// Schlüssel und dieselbe Form wie text/<locale>.php der Workbench
return [
	'[[/_admin/nav/backups]]'						=> 'Backups',
	'[[/_admin/backups/empty]]'					=> 'Noch kein Backup vorhanden.',
	'[[/_admin/backups/confirm/restore]]'	=> 'Das Backup vom %s wiederherstellen? Der aktuelle Stand wird zuvor automatisch gesichert. Danach wirst Du abgemeldet und landest auf der Anmeldeseite.',
	'[[/_admin/backups/label/login]]'	=> 'Zur Anmeldung',
	'[[/_admin/backups/msg/restored]]'	=> 'Das Backup vom %s wurde wiederhergestellt.',
	'[[/_admin/backups/label/now]]'			=> 'Jetzt sichern',
	'[[/_admin/backups/msg/created]]'		=> 'Backup erstellt: %s',
	'[[/_admin/backups/msg/missed]]'		=> 'Das automatische Backup von heute wurde nicht angelegt: %s. Nino versucht es heute nicht von selbst noch einmal; „Jetzt sichern“ legt eines an, sobald die Ursache behoben ist.',
	'[[/_admin/backups/msg/interrupted]]'	=> 'Das automatische Backup von heute wurde nicht fertig: Die Anfrage, die es angelegt hat, wurde beendet, meist weil dem Server der Speicher oder die Zeit ausging. Nino versucht es heute nicht von selbst noch einmal; „Jetzt sichern“ legt eines an.',
	'[[/_admin/error/backups_restored_other_version]]'	=> 'Das Backup stammt aus Nino %s, hier läuft %s.',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/backups/manual/intro]]'	=> 'Ein Backup ist eine Kopie der Inhalte Deiner Website, wie sie an einem Tag waren. Nino legt jeden Tag von selbst eines an und hebt vierzehn Tage auf; unter Konfiguration lässt sich das abschalten.',
	'[[/_admin/backups/manual/backup]]'	=> '„Jetzt sichern“ legt sofort eine Kopie an. Die Liste zeigt die Tage, für die es ein Backup gibt.',
	'[[/_admin/backups/manual/restore]]'	=> 'Wähle einen Tag, um ihn wiederherzustellen; Nino fragt vorher nach. Der aktuelle Stand wird zuvor gesichert, so lässt sich eine Wiederherstellung zurücknehmen. Sie beendet Deine Sitzung: Danach bist Du abgemeldet und meldest Dich neu an.',
];
