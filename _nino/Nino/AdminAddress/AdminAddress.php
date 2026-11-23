<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	AdminAddress				The address of a screen of the workbench: the one place on the
 *											server that writes one, as Nino.admin.router is in the browser
 *											(_admin/assets/script.js) - and the two write the same
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	The part of the url after the # names where the workbench is: the panel,
	 *	then the name of one screen of it - the tab - and then what that screen
	 *	shows, the parts.
	 *
	 *	  #<panel>                              the panel, on its first screen, as it starts
	 *	  #<panel>/<tab>                        a screen of the panel, as it starts
	 *	  #<panel>/<tab>/<part>[/<part>...]     a state of the screen
	 *
	 *	Every panel has at least one tab: its own screen, named like the panel.
	 *	A tab of the panel is a screen of its own, named by its uri without the name of
	 *	the panel in front of it. A part is a word - what the workbench says, written
	 *	with an at sign and a lowercase word, '@new', and made by word() - or a key,
	 *	which is any other string and always ONE segment: percent-encoded, so that a
	 *	slash in it is '%2F' and an at sign in it '%40'. A word is written as it is and a
	 *	key never is, so that a key that reads '@new' is a key, '%40new', and never the
	 *	word
	 */
	class AdminAddress {

		// What a word looks like - what the router in the browser reads as one
		public const string WORD = '/^@[a-z][a-z0-9-]*$/D';

		/**
		 *	The name a tab has in an address: its uri without the name of the
		 *	panel in front of it ('builder-frames' of the panel 'builder' is
		 *	'frames', so #builder/frames), its uri where that does not begin
		 *	with it (the kernel's 'types' of 'elements'). A panel's own screen is
		 *	named like the panel
		 *
		 *	@param		string		$panel				The uri of the panel
		 *	@param		string		$tab					The uri of the tab
		 *
		 *	@return 	string
		 */
		public static function tab( string $panel, string $tab ): string {

			if( str_starts_with( $tab, $panel. '-' ) === true && strlen( $tab ) > strlen( $panel ) + 1 )
				return substr( $tab, strlen( $panel ) + 1 );

			return $tab;
		}

		/**
		 *	A word of an address, as a part of of()
		 *
		 *	@param		string		$name					Without the at sign: 'new'
		 *
		 *	@return 	array											[ 'word' => '@new' ]
		 */
		public static function word( string $name ): array {

			if( preg_match( self::WORD, '@'. $name ) !== 1 )
				throw new \InvalidArgumentException( 'Not a word of an address: '. $name );

			return [ 'word' => '@'. $name ];
		}

		/**
		 *	One part of an address: a word (see word()) as it is, a key percent-encoded - without
		 *	the characters encodeURIComponent() of the browser leaves as they are
		 *
		 *	@param		array|string		$part
		 *
		 *	@return 	string
		 */
		public static function segment( array|string $part ): string {

			// An array that says it is a word is one where it is a word (see word()); where it is not it is a key, the text it says
			if( is_array( $part ) === true ) {

				$said = (string) ( $part['word'] ?? '' );

				if( preg_match( self::WORD, $said ) === 1 )
					return $said;

				$part = $said;
			}

			return strtr( rawurlencode( $part ), [ '%21' => '!', '%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*' ] );
		}

		/**
		 *	The address of a screen: '#panel' for the panel's own screen as it starts,
		 *	'#panel/tab' for a tab as it starts, and with parts '#panel/tab/part'
		 *	- the own screen's tab is the panel's name again, '#panel/panel/part'
		 *
		 *	@param		string		$panel				The uri of the panel
		 *	@param		array 		$parts				Words (see word()) and keys, in order
		 *	@param		string		$tab					The uri of the tab, '' for the panel's own screen
		 *
		 *	@return 	string
		 */
		public static function of( string $panel, array $parts = [], string $tab = '' ): string {

			$segments = [ $panel ];

			if( $parts !== [] || $tab !== '' )
				$segments[] = $tab === '' ? $panel : self::tab( $panel, $tab );

			foreach( $parts as $part )
				$segments[] = self::segment( is_array( $part ) === true ? $part : (string) $part );

			return '#'. implode( '/', $segments );
		}
	}
}
