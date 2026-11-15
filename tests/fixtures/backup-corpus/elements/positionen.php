<?php
declare(strict_types=1);

// unit/positionen.php - the element type the items are kept in, copied to /elements/positionen.php where the project has none (the
// manifest's elementTypes, add-only: a type of this name that the project has stays as it is, whatever fields it changed). An item
// is an element of this type, entered in the screen Positionen of the surface or in the Elements panel; \Crm\Positionen reads them for the editor of the documents and the Builder
// draws them on a page: [elements /positionen sort="sort" query="hidden=0"].
//
// The shape is the one \Nino\Elements::insertElementType() writes: the model, then one bucket per locale plus '*' for what is not
// translated. Empty - an item that was seeded is an item somebody has to find and delete.
//
// The words are per language, so that a page can offer what the owner sells in the language of the visitor, and a language that has
// none shows the native one's. The numbers are global: the net price in euros with at most two decimals and the rate in percent
// (19, 7, 0) are the same in every language. A line of a document copies them in cents and in hundredths when the editor adds it
return [
	'title' => 'Positionen',
	'model' => [
		'title' => [
			'type'			=> 'string',
			'locale'		=> true,
			'required'	=> true,
			'maxlength'	=> 200,
		],
		'description' => [
			'type'			=> 'string',
			'locale'		=> true,
			'breaks'		=> true,
			'inputsize'	=> 4,
			'maxlength'	=> 2000,
		],
		// "Std.", "h", "Monat"
		'unit' => [
			'type'			=> 'string',
			'locale'		=> true,
			'maxlength'	=> 20,
		],
		// The unit of the electronic invoice, by its code of UN/ECE Recommendation 20 (the words of the codes are in the surface): a line takes it from the item and may change it.
		// Empty is none: a line without a code is missing in the checklist of the document. A project that had this type before the field came keeps the type as it was
		'unit_code' => [
			'type'			=> 'string',
			'options'		=> [ '', 'H87', 'HUR', 'MIN', 'DAY', 'WEE', 'MON', 'LS', 'C62', 'KMT' ],
		],
		// The net price in euros
		'price' => [
			'type'			=> 'double',
			'required'	=> true,
			'suffix'		=> '€',
		],
		// The rate in percent; a project that charges no VAT does not ask for it and the line gets 0
		'vat' => [
			'type'			=> 'double',
			'suffix'		=> '%',
		],
		// Off is the usual case: a new item is offered in the editor and shown on a page without anybody touching the switch (the
		// element form starts a boolean at No, which is why the field says "hidden" and not "active"). On takes the item out of the
		// editor of the documents and off a page whose loop says query="hidden=0"
		'hidden' => [
			'type'			=> 'boolean',
		],
		// The order on a page and in the editor
		'sort' => [
			'type'			=> 'integer',
		],
	],
	'*' => [
		'*' => [ 'hidden' => false ],
	],
];
