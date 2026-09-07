<?php
// Die eigenen Workbench-Texte des Features-Moduls, in seine Fills gemischt,
// solange das Modul aktiv ist (siehe text() des Panels) - dieselben
// Schlüssel und dieselbe Form wie text/<locale>.php der Workbench. Ein %s
// füllt das Skript: das Features-Verzeichnis, eine Version, eine Liste
// von Schlüsseln
return [
	'[[/_admin/nav/features]]'								=> 'Features',
	'[[/_admin/features/label/active]]'				=> 'Aktive Features',
	'[[/_admin/features/hint/intro]]'					=> 'Alle Features, die unter %s installiert sind. Lege ein Feature-Verzeichnis dort ab und schalte es hier ein; was es wissen muss, wird als seine Einstellungen in der config.php gespeichert, und beim Ausschalten bleibt all das für das nächste Mal erhalten.',
	'[[/_admin/features/hint/empty]]'					=> 'Es sind keine Features installiert. Ein Feature ist ein Verzeichnis unter %s, mit einem feature.php-Manifest neben seiner Klasse.',
	'[[/_admin/features/label/version]]'			=> 'Version %s',
	'[[/_admin/features/label/installed]]'		=> 'installiert als %s',
	'[[/_admin/features/label/requires]]'			=> 'Benötigt: %s',
	'[[/_admin/features/label/settings]]'			=> 'Einstellungen',
	'[[/_admin/features/label/none]]'					=> '– keine –',
	'[[/_admin/features/status/active]]'			=> 'Aktiv',
	'[[/_admin/features/status/inactive]]'		=> 'Inaktiv',
	'[[/_admin/features/status/update]]'			=> 'Update verfügbar',
	'[[/_admin/features/status/incompatible]]'	=> 'Nicht kompatibel',
	'[[/_admin/features/label/activate]]'			=> 'Aktivieren',
	'[[/_admin/features/label/deactivate]]'		=> 'Deaktivieren',
	'[[/_admin/features/label/update]]'				=> 'Auf %s aktualisieren',
	'[[/_admin/features/hint/secret-set]]'		=> 'Ein Wert ist gespeichert. Lass das Feld leer, um ihn zu behalten, oder gib einen neuen ein, um ihn zu ersetzen.',
	'[[/_admin/features/hint/secret-unset]]'	=> 'Noch nichts gespeichert.',
	'[[/_admin/features/hint/lines]]'					=> 'Ein Eintrag pro Zeile.',
	'[[/_admin/features/msg/activating]]'			=> 'Wird aktiviert …',
	'[[/_admin/features/msg/activated]]'			=> 'Aktiviert.',
	'[[/_admin/features/msg/deactivating]]'		=> 'Wird deaktiviert …',
	'[[/_admin/features/msg/deactivated]]'		=> 'Deaktiviert.',
	'[[/_admin/features/msg/updating]]'				=> 'Wird aktualisiert …',
	'[[/_admin/features/msg/updated]]'				=> 'Aktualisiert.',
	'[[/_admin/features/msg/reload]]'					=> 'Die Workbench lädt neu, damit die Navigation die Änderung zeigt …',
	'[[/_admin/features/error/activate]]'			=> 'Das Feature konnte nicht aktiviert werden.',
	'[[/_admin/features/error/deactivate]]'		=> 'Das Feature konnte nicht deaktiviert werden.',
	'[[/_admin/features/error/update]]'				=> 'Das Feature konnte nicht aktualisiert werden.',
];
