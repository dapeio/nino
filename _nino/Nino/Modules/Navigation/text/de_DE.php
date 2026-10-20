<?php
// Die eigenen Workbench-Texte des Navigation-Moduls, in seine Fills
// gemischt, solange das Modul aktiv ist (siehe text() des Panels) -
// dieselben Schlüssel und dieselbe Form wie text/<locale>.php der Workbench
return [
	'[[/_admin/nav/navs]]'							=> 'Navigationen',
	'[[/_admin/navs/empty]]'						=> 'Noch keine Navigationen – lege unten eine an.',
	'[[/_admin/navs/empty-entries]]'		=> 'Noch keine Einträge – füge unten eine Route hinzu.',
	'[[/_admin/navs/label/new]]'				=> 'Neue Navigation',
	'[[/_admin/navs/label/navigation]]'	=> 'Navigation',
	'[[/_admin/navs/label/key]]'				=> 'Navigations-Kennung – der Name, den ein Template mit dem navigation-Shortcode abfragt, z. B. „main“',
	'[[/_admin/navs/placeholder/key]]'	=> 'Navigations-Kennung, z. B. „footer“',
	'[[/_admin/navs/hint/rename]]'			=> 'Umbenennen aktualisiert die Routen in dieser Navigation, nicht die Templates, die sie rendern – passe das nav-Argument ihres navigation-Shortcodes selbst an.',
	'[[/_admin/navs/label/delete]]'			=> 'Navigation löschen',
	'[[/_admin/navs/label/entries]]'		=> 'Einträge',
	'[[/_admin/navs/label/unnamed]]'		=> '– noch ohne Namen, bleibt deshalb aus dem Menü',
	'[[/_admin/navs/label/unnamed-short]]'	=> '– noch ohne Namen',
	'[[/_admin/navs/label/moveup]]'			=> 'In dieser Navigation nach oben',
	'[[/_admin/navs/label/movedown]]'		=> 'In dieser Navigation nach unten',
	'[[/_admin/navs/label/remove]]'			=> 'Aus dieser Navigation entfernen – die Route selbst bleibt',
	'[[/_admin/navs/label/add]]'				=> 'Eine Route hinzufügen – sie kommt ans Ende',
	'[[/_admin/navs/label/addbtn]]'			=> 'Zur Navigation hinzufügen',
	'[[/_admin/navs/label/choose]]'	=> 'Route wählen …',
	'[[/_admin/navs/msg/unsaved]]'	=> 'Ungespeicherte Änderungen – Speichern schreibt die Reihenfolge.',
	'[[/_admin/navs/confirm/discard]]'	=> 'Die Änderungen an dieser Navigation sind nicht gespeichert. Ohne Speichern verlassen?',
	'[[/_admin/navs/confirm/delete]]'		=> 'Die Navigation „%s“ wirklich löschen? Ihre Routen bleiben, nur die Zugehörigkeit geht.',
	'[[/_admin/error/navs_invalid_id]]'	=> 'Die Navigations-Kennung „%s“ ist ungültig. Beginne mit einem Kleinbuchstaben; danach sind Kleinbuchstaben, Ziffern, Bindestriche und Unterstriche erlaubt.',
	'[[/_admin/error/navs_id_taken]]'	=> 'Die Navigations-Kennung „%s“ ist schon vergeben.',
	'[[/_admin/error/navs_invalid_entries]]'	=> 'Die Navigation lässt sich nur mit einer Liste von Routen speichern.',
	'[[/_admin/error/navs_duplicate_entry]]'	=> '„%s“ steht zweimal in der Liste.',
	'[[/_admin/error/navs_unknown_route]]'	=> 'Die Route „%s“ gibt es (nicht mehr).',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/navs/manual/intro]]'	=> 'Eine Navigation ist ein Menü, etwa main oder footer, das aus Routen besteht. Ein Template zeigt sie mit dem Navigations-Shortcode.',
	'[[/_admin/navs/manual/navigation]]'	=> '„Neue Navigation“ fragt nach einer ID: dem Namen, nach dem ein Template fragt. Beim Umbenennen ziehen die Routen darin mit, die Templates, die sie zeigen, nicht.',
	'[[/_admin/navs/manual/item]]'	=> 'Ein Eintrag ist eine Route. „Zur Navigation hinzufügen“ setzt sie ans Ende, die Pfeile verschieben sie, und „Entfernen“ nimmt sie aus diesem Menü – die Route selbst bleibt.',
	'[[/_admin/navs/manual/name]]'	=> 'Eine Route steht unter dem Namen im Menü, den sie dort hat. Eine Route ohne Namen bleibt draußen, bis sie einen bekommt.',
	'[[/_admin/navs/manual/save]]'	=> '„Speichern“ schreibt die Reihenfolge. Wer mit ungespeicherten Änderungen geht, wird vorher gefragt.',
	'[[/_admin/navs/manual/delete]]'	=> '„Navigation löschen“ entfernt nur das Menü: Die Routen bleiben, nur ihre Zugehörigkeit geht.',
];
