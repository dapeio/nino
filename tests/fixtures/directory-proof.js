/**
 *	Nino									A compact filesystembased php framework
 *	directory-proof.js		A directory of 60 image slots with its record, drawn only with the
 *												blocks of the workbench: Nino.adminUi.directory() with a column of
 *												pictures, the state of each language, a category and two filters, and
 *												Nino.adminUi.record() with a large picture, a file to choose, the alt
 *												text of every language, "Save", "Save and continue" and the
 *												destructive button.
 *
 *												It is a page of the tests and no part of the workbench: the proof that
 *												the blocks carry what a panel of images needs, without that panel
 *												being rebuilt. tests/nino-ui-directory-proof-js-smoke.js loads it into
 *												a page of stand-ins and holds what it draws; a browser loads it into a
 *												page of the workbench (the pictures it draws itself, on a canvas,
 *												where there is one).
 *
 *												Nothing is kept: a change of a record stands in the rows of the
 *												directory from "Save" on and no earlier - what is typed or chosen is
 *												only in the form until then.
 *
 *	Usage: NinoProof.draw( mount, { screen, languages } )
 */

( function( wn ) {

	'use strict';

	const GROUPS = [
		[ 'general', 'Allgemein', [ [ 'logo', 'Logo', '500 × 100 px' ], [ 'logo/dark', 'Logo, dunkel', '500 × 100 px' ], [ 'share', 'Vorschaubild für soziale Netzwerke', '1200 × 630 px' ], [ 'signet', 'Signet', '256 × 256 px' ] ] ],
		[ 'home', 'Startseite', [ [ 'hero', 'Titelbild', '1920 × 1080 px' ], [ 'service/heating', 'Leistung Heizung', '640 × 420 px' ], [ 'service/sanitary', 'Leistung Sanitär', '640 × 420 px' ], [ 'service/solar', 'Leistung Solar', '640 × 420 px' ], [ 'about', 'Über uns', '800 × 600 px' ], [ 'banner', 'Aktionsbanner', '1600 × 500 px' ] ] ],
		[ 'about', 'Über uns', [ [ 'team', 'Teamfoto', '1200 × 800 px' ], [ 'office', 'Büro', '1200 × 800 px' ], [ 'history', 'Firmengeschichte', '900 × 600 px' ] ] ],
		[ 'services', 'Leistungen', [ [ 'heating', 'Heizung', '1200 × 800 px' ], [ 'sanitary', 'Sanitär', '1200 × 800 px' ], [ 'solar', 'Solar', '1200 × 800 px' ], [ 'air', 'Klima', '1200 × 800 px' ], [ 'maintenance', 'Wartung', '1200 × 800 px' ] ] ],
		[ 'contact', 'Kontakt', [ [ 'map', 'Anfahrt', '1000 × 600 px' ], [ 'shop', 'Ladengeschäft', '1000 × 600 px' ] ] ],
	];

	const COLORS = [ [ '#a34650', '#f6d9dd' ], [ '#3f7a4f', '#cfe5d3' ], [ '#3f6fa3', '#d4e3f3' ], [ '#a3703f', '#f3e1cf' ], [ '#6a4fa3', '#e2d9f3' ] ];

	/**
	 *	A picture of a house on a coloured ground, drawn on a canvas where the page has one: a data address of a PNG, which the blocks take as
	 *	a picture. Without a canvas - a page of stand-ins - an address of the site
	 */
	const picture = function( index, width, height ) {

		const canvas = wn.document.createElement('canvas');

		if( typeof canvas.getContext !== 'function' )
			return '/images/proof-'+ index+ '.png';

		const draw = canvas.getContext('2d');
		const colors = COLORS[index % COLORS.length];

		if( draw === null || draw === undefined )
			return '/images/proof-'+ index+ '.png';

		canvas.width = width;
		canvas.height = height;
		draw.fillStyle = colors[1];
		draw.fillRect( 0, 0, width, height );
		draw.fillStyle = colors[0];
		draw.globalAlpha = .35;
		draw.fillRect( 0, height * .72, width, height * .28 );
		draw.globalAlpha = 1;
		draw.fillStyle = '#ffffff';
		draw.fillRect( width * .36, height * .46, width * .28, height * .3 );
		draw.fillStyle = colors[0];
		draw.beginPath();
		draw.moveTo( width * .31, height * .46 );
		draw.lineTo( width * .5, height * .22 );
		draw.lineTo( width * .69, height * .46 );
		draw.closePath();
		draw.fill();
		draw.fillRect( width * .47, height * .6, width * .06, height * .16 );

		return canvas.toDataURL('image/png');
	};

	const draw = function( mount, options ) {

		options = options ?? {};

		const ui = wn.Nino.adminUi;
		const screen = options.screen ?? 'proof';
		const locales = ( options.languages ?? 2 ) === 2 ? [ 'de_DE', 'en_US' ] : [ 'de_DE', 'en_US', 'fr_FR', 'it_IT', 'es_ES' ].slice( 0, options.languages );
		const rows = [];

		// 60 slots: the table of groups, repeated with its own numbers where it is short
		let index = 0;

		while( rows.length < 60 ) {

			GROUPS.forEach( function( group ) {

				group[2].forEach( function( slot ) {

					if( rows.length >= 60 )
						return;

					const round = Math.floor( index / 20 );
					const key = '/'+ ( group[0] === 'general' ? '' : 'page/'+ group[0]+ '/' )+ slot[0]+ ( round > 0 ? '-'+ ( round + 1 ) : '' );
					const filled = index % 7 !== 1 && index % 11 !== 5;
					const alts = {};

					locales.forEach( function( code, at ) { alts[code] = filled === true && ( at === 0 || index % 3 !== 0 ) ? slot[1]+ ' bei Kessler Haustechnik' : '' } );

					rows.push( {
						key : key,
						name : slot[1]+ ( round > 0 ? ' ('+ ( round + 1 ) + ')' : '' ),
						group : group[0],
						groupName : group[1],
						size : slot[2],
						used : index % 9 === 1 ? '' : ( group[0] === 'general' ? 'Alle Seiten' : group[1] ),
						usedText : index % 9 === 1 ? 'Nirgends eingebunden' : ( group[0] === 'general' ? 'Alle Seiten' : group[1] ),
						file : filled === true ? 'slot-'+ index+ '.jpg' : '',
						picture : filled === true ? picture( index, 160, 100 ) : '',
						large : filled === true ? picture( index, 960, 540 ) : '',
						weight : 40 + ( index * 13 ) % 200,
						alts : alts,
						states : [],
					} );

					index++;
				} );
			} );
		}

		const states = function( row ) {
			return locales.map( function( code ) { return { locale : code, label : code.slice( 0, 2 ).toUpperCase(), name : ui.localeName( code, locales ), ok : row.alts[code] !== '' } } );
		};

		const refresh = function( row ) { row.states = states( row ) };

		rows.forEach( refresh );

		// What the cell of a name and its key needs, in tokens: a page of the tests has no stylesheet of a panel
		if( wn.document.getElementById('nino-admin-proof-style') === null && wn.document.head ) {

			const style = wn.document.createElement('style');

			style.id = 'nino-admin-proof-style';
			style.textContent = '.nino-admin-proof-key { color: var(--editor-text-secondary); font-family: var(--nino-admin-mono); font-size: var(--nino-admin-text-xs); }';
			wn.document.head.appendChild( style );
		}

		const nameCell = function( value, row ) {

			const cell = ui.el( 'div', 'nino-admin-proof-name' );

			cell.appendChild( ui.el( 'strong', '', row.name ) );
			cell.appendChild( ui.el( 'div', 'nino-admin-proof-key', row.key ) );

			return cell;
		};

		const usedCell = function( value, row ) {

			if( row.used === '' ) {

				const badge = ui.el( 'span', 'nino-admin-badge', 'Nirgends eingebunden' );
				badge.dataset.state = 'warn';

				return badge;
			}

			return ui.el( 'span', '', row.used );
		};

		let record = null;
		let handle = null;

		const options_ = function( list ) { return [ { value : '', label : list[0] } ].concat( list.slice( 1 ).map( function( one ) { return { value : one[0], label : one[1] } } ) ) };

		handle = ui.directory( {
			mount : mount,
			rowKey : 'key',
			columns : [
				{ key : 'picture', label : 'Vorschau', cell : 'thumb' },
				{ key : 'name', label : 'Name', type : 'string', render : nameCell },
				{ key : 'groupName', label : 'Gruppe', type : 'string' },
				{ key : 'size', label : 'Soll-Maße', type : 'string' },
				{ key : 'usedText', label : 'Verwendet auf', type : 'string', render : usedCell },
				{ key : 'states', label : 'Alt-Text', cell : 'locales', words : { ok : 'vorhanden', missing : 'fehlt' } },
			],
			labels : { search : 'Bilder suchen', empty : 'Noch keine Bilder.', noMatch : 'Keine Bilder gefunden.', filter : 'Filter' },
			category : { id : 'proof-group', label : 'Gruppe', value : '', options : options_( [ 'Alle Gruppen' ].concat( GROUPS.map( function( group ) { return [ group[0], group[1] ] } ) ) ), match : function( row, value ) { return value === '' || row.group === value } },
			filters : [
				{ id : 'proof-picture', label : 'Bilder', value : '', options : options_( [ 'Alle Bilder', [ 'none', 'Ohne Bild' ], [ 'unused', 'Nirgends eingebunden' ] ] ), match : function( row, value ) { return value === '' || ( value === 'none' ? row.file === '' : row.used === '' ) } },
				{ id : 'proof-alt', label : 'Alt-Text', value : '', options : options_( [ 'Alle Alt-Texte', [ 'missing', 'Fehlt in einer Sprache' ] ] ), match : function( row, value ) { return value === '' || row.states.some( function( state ) { return state.ok === false } ) } },
			],
			searchText : function( row ) { return row.key+ ' '+ row.file },
			select : { label : 'Bilder dieser Seite wählen', rowLabel : function( row ) { return row.name+ ' wählen' }, actions : [ { key : 'remove', label : 'Bilder entfernen', danger : true, run : function() {} } ] },
			actions : function( row ) { return [ ui.iconButton( 'pencil', row.name+ ' bearbeiten', function( event ) { record.open( row.key, { opener : event.currentTarget } ) }, { small : true } ) ] },
			onOpen : function( row ) { record.open( row.key, {} ) },
		} );

		record = ui.record( {
			screen : screen,
			dirty : screen,
			directory : handle,
			keyOf : function( row ) { return row.key },
			find : function( key ) { return rows.find( function( row ) { return row.key === key } ) ?? null },
			title : function( row ) { return row.name },
			subtitle : function( row ) { return row.key },
			saveNext : true,
			labels : { close : 'Schließen', prev : 'Vorheriges Bild', next : 'Nächstes Bild', save : 'Speichern', saveNext : 'Speichern und weiter', position : '%s von %s' },
			body : function( row, ctx ) {

				const wrap = ui.el( 'div', 'nino-admin-dialog-fields' );

				wrap.appendChild( ui.imageFrame( { src : row.large, alt : row.name, none : 'Noch kein Bild.', caption : row.file === '' ? '' : 'Aktuell: '+ row.file+ ' · '+ row.size.replace( ' px', '' )+ ' px · '+ row.weight+ ' KB' } ) );
				wrap.appendChild( ui.readLine( { label : 'Soll-Maße', value : row.size } ) );
				wrap.appendChild( ui.readLine( { label : 'Verwendet auf', value : row.used === '' ? 'Nirgends eingebunden' : row.used } ) );

				const file = ui.fileField( { label : 'Bild', note : 'Bis zu 2 MB und 20 Megapixel. JPG, PNG, WebP oder GIF.', button : 'Datei wählen', accept : 'image/*', onChange : function() { ctx.touch() } } );

				wrap.appendChild( file.el );
				ctx.file = file;

				const alts = ui.localeRows( { label : 'Alt-Text', required : true, locales : locales, work : locales[0], values : row.alts, maxLength : 120 } );

				wrap.appendChild( alts.el );
				ctx.alts = alts;

				return wrap;
			},
			// Only "Save" writes: the picture that was chosen and the alt texts stand in the rows from here on
			save : function( row, ctx, done ) {

				const next = Object.assign( {}, row, { alts : ctx.alts.value() } );

				if( ctx.file.value() !== null ) {
					next.file = ctx.file.value().name;
					next.picture = next.large = picture( rows.indexOf( row ) + 1, 160, 100 );
				}

				refresh( next );
				rows[rows.indexOf( row )] = next;
				handle.update( rows, row.key );
				done( true, { row : next } );
			},
			danger : { label : 'Bild entfernen', run : function( row, opener, finished ) {

				ui.confirm( 'destructive', {
					title : 'Bild entfernen',
					message : 'Das Bild von „'+ row.name+ '“ entfernen? Der Bildplatz bleibt.',
					yes : 'Bild entfernen',
					no : 'Abbrechen',
					nested : true,
					opener : opener,
					onChoose : function( ok ) {

						if( ok === false )
							return finished( null );

						const next = Object.assign( {}, row, { file : '', picture : '', large : '' } );

						rows[rows.indexOf( row )] = next;
						handle.update( rows, row.key );
						finished( true );
					},
				} );
			} },
			missing : function() { handle.notice( 'Dieses Bild gibt es nicht (mehr).' ) },
		} );

		handle.setRows( rows );

		return { directory : handle, record : record, rows : rows };
	};

	wn.NinoProof = { draw : draw };

} )( window );
