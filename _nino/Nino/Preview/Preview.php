<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Preview				A draft that is shown to one session before it is saved: a token, a folder, a time
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Preview - what a panel shows of a change that is not saved yet. The panel hands the draft to issue() and gets a token;
	 *	the page is asked for with the token in its query (QUERY), and the code that draws the page asks held() whether the
	 *	request may have the draft. A request that may not - another session, another account, a token that ran out, an account
	 *	that lost the right - gets the page as the visitors have it, and nothing says why.
	 *
	 *	The draft is a folder below DIR, one for each token, kept apart by what the draft is for ($kind): its data as the panel
	 *	gave it and a note of whose it is and until when. The folder is named with a dot, which a backup does not carry. The
	 *	token is 128 bits from random_bytes(), held by the session that was given it and by nobody else, and it is not written to
	 *	a log. A session holds at most MAX tokens of a kind unless the caller asks for more, up to LIMIT; the drafts whose time is out are removed whenever one is issued.
	 *
	 *	A request that holds a draft is a preview request: active() says so, for the code that draws differently then - the
	 *	trace of \Nino\Components::render(), the template that \Nino\Modules\Template::swap() puts in the place of a file - and
	 *	its answer is marked for nobody to keep and nobody to index
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Preview {

		// Where the drafts are kept: a folder of the project's data, below it one for each kind of draft and below that one for each token
		public const string DIR = '/data/.preview';

		// The query of a request that asks for its draft, and how long a token holds, in seconds
		public const string QUERY = 'nino-preview';
		public const int TTL = 900;

		// A token, and the name of a kind - a word of lower case letters, digits and hyphens
		public const string TOKEN = '/^[0-9a-f]{32}$/D';
		public const string KIND = '/^[a-z][a-z0-9-]{0,31}$/D';

		// The query word that asks for the page to carry a mark on its <html> (see mark()): a word of KIND's form, read only by a request that holds a draft
		public const string MARK = 'nino-preview-mark';

		// How many tokens of a kind a session holds - the newest - and the largest draft, in bytes of its json
		public const int MAX = 24;
		public const int LIMIT = 200;
		public const int MAX_BYTES = 2097152;

		// The key of the session that holds the tokens, with the kind after it, and the key of the request that holds one
		private const string SESSION = '/nino/preview/';
		private const string HELD = './nino/preview/held';
		private const string TRACE = './nino/preview/trace';

		/**
		 *	Write a draft and hold its token for the session: the token the session has for it - $renew - where it still holds and is
		 *	of this kind, else a new one. The draft is written before the token is held, and the session keeps the newest MAX of
		 *	its tokens: the folders of the others go
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind					What the draft is for: a word, eg. 'builder'
		 *	@param		string		$perm					The right the account must hold - to be given a token now, and to have the draft later
		 *	@param		array			$data					What the draft is: json-encodable, at most MAX_BYTES as json
		 *	@param		string		$renew				The token the one who asks holds, '' for none
		 *
		 *	@return 	string										The token; '' where there is none to give: no account, no right, a kind that is none, a draft too large or one that could not be written
		 */
		public static function issue( array &$appData, string $kind, string $perm, array $data, string $renew = '', int $most = self::MAX ): string {

			$user = \Nino\Auth::getCurrentUser( $appData );

			if( $user === false || $perm === '' || preg_match( self::KIND, $kind ) !== 1 || \Nino\Auth::checkPermission( $appData, $perm ) === false )
				return '';

			$encoded = json_encode( $data, JSON_UNESCAPED_SLASHES );

			if( $encoded === false || strlen( $encoded ) > self::MAX_BYTES )
				return '';

			$held = self::_tokens( $appData, $kind );
			$token = preg_match( self::TOKEN, $renew ) === 1 && ( $held[$renew] ?? 0 ) > time() ? $renew : bin2hex( random_bytes( 16 ) );
			$expires = time() + self::TTL;
			$dir = self::DIR. '/'. $kind. '/'. $token;

			if( \Nino\Filesystem::putFileContent( $appData, $dir. '/draft.json', $data ) === false
				|| \Nino\Filesystem::putFileContent( $appData, $dir. '/meta.json', [ 'user' => (string) $user['mail'], 'expires' => $expires ] ) === false )
				return '';

			unset( $held[$token] );
			$held[$token] = $expires;
			$kept = array_slice( $held, -max( 1, min( $most, self::LIMIT ) ), null, true );

			// The draft that falls out is not kept for nobody to ask for
			foreach( array_diff_key( $held, $kept ) as $dropped => $until )
				if( preg_match( self::TOKEN, (string) $dropped ) === 1 )
					\Nino\Filesystem::removeDir( \Nino\Filesystem::path( $appData, self::DIR. '/'. $kind. '/'. $dropped ) );

			\Nino\Runtime::setSessionValue( $appData, self::SESSION. $kind, $kept );
			self::tidy( $appData );

			return $token;
		}

		/**
		 *	The draft a request asks for, where it may have it: the token in its query is one this session was given, of this kind, by
		 *	the account that is signed in, who holds the right still, and its time is not out. A request that has it is marked as a
		 *	preview request (see active()) and answered with headers that keep it from being stored or indexed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$request			(reference) The request
		 *	@param		string		$kind
		 *	@param		string		$perm
		 *
		 *	@return 	array|null								[ 'token', 'expires', 'data' ]; null for a request that may not have a draft - and says nothing of why
		 */
		public static function held( array &$appData, array &$request, string $kind, string $perm ): ?array {

			$token = $request['/nino/http/request']['query'][self::QUERY] ?? null;
			$user = \Nino\Auth::getCurrentUser( $appData );

			if( is_string( $token ) === false || preg_match( self::TOKEN, $token ) !== 1 || preg_match( self::KIND, $kind ) !== 1 || $user === false || $perm === ''
				|| \Nino\Auth::checkPermission( $appData, $perm ) === false )
				return null;

			// A token of this session whose time is out: its folder goes, which is cheap and keeps the data from lying about
			$until = self::_tokens( $appData, $kind )[$token] ?? 0;

			if( $until <= time() ) {

				if( $until > 0 )
					\Nino\Filesystem::removeDir( \Nino\Filesystem::path( $appData, self::DIR. '/'. $kind. '/'. $token ) );

				return null;
			}

			$dir = self::DIR. '/'. $kind. '/'. $token;
			$meta = \Nino\Filesystem::getFileContent( $appData, $dir. '/meta.json', [] );
			$data = \Nino\Filesystem::getFileContent( $appData, $dir. '/draft.json', null );

			if( is_array( $meta ) === false || is_array( $data ) === false || ( $meta['user'] ?? null ) !== $user['mail'] || (int) ( $meta['expires'] ?? 0 ) <= time() )
				return null;

			$appData[self::HELD] = [ 'kind' => $kind, 'token' => $token ];
			$request['/nino/http/response']['header'] = [ 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex' ] + (array) ( $request['/nino/http/response']['header'] ?? [] );

			return [ 'token' => $token, 'expires' => (int) $meta['expires'], 'data' => $data ];
		}

		/**
		 *	Whether this request holds a draft, as held() answered it: what draws differently for a preview asks this and nothing
		 *	of the request, so that a query that only spells the token changes nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function active( array &$appData ): bool {
			return is_array( $appData[self::HELD] ?? null ) === true;
		}

		/**
		 *	The mark a request that holds a draft asks its page to carry on its <html> (data-nino-preview): the word of the query MARK, where it
		 *	is a word of the form of a kind. A request that holds no draft has none, whatever its query says - so nothing of a public answer
		 *	ever carries it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$request			The request
		 *
		 *	@return 	string										The word; '' for none
		 */
		public static function mark( array &$appData, array $request ): string {

			$word = $request['/nino/http/request']['query'][self::MARK] ?? null;

			return self::active( $appData ) === true && is_string( $word ) === true && preg_match( self::KIND, $word ) === 1 ? $word : '';
		}

		/**
		 *	Ask for the trace of what this request draws (see \Nino\Components::render()), after held(): a preview that is a picture
		 *	of a tile has no use for comments, a screen that clicks on the page has. Only a request that holds a draft has the
		 *	trace, whoever asks (see tracing())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function trace( array &$appData ): void {

			$appData[self::TRACE] = true;
		}

		/**
		 *	Whether this request holds a draft and asked for the trace
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function tracing( array &$appData ): bool {
			return ( $appData[self::TRACE] ?? false ) === true && self::active( $appData ) === true;
		}

		/**
		 *	Remove the drafts whose time is out, of every kind. A folder is looked at only where its name is a kind or a token, and a
		 *	link is left alone
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function tidy( array &$appData ): void {

			$base = \Nino\Filesystem::path( $appData, self::DIR );

			foreach( (array) glob( $base. '/*', GLOB_ONLYDIR ) as $kindDir ) {

				$kind = basename( (string) $kindDir );

				if( preg_match( self::KIND, $kind ) !== 1 || is_link( (string) $kindDir ) === true )
					continue;

				foreach( (array) glob( (string) $kindDir. '/*', GLOB_ONLYDIR ) as $folder ) {

					$token = basename( (string) $folder );

					if( preg_match( self::TOKEN, $token ) !== 1 || is_link( (string) $folder ) === true )
						continue;

					$meta = \Nino\Filesystem::getFileContent( $appData, self::DIR. '/'. $kind. '/'. $token. '/meta.json', [] );

					if( is_array( $meta ) === false || (int) ( $meta['expires'] ?? 0 ) <= time() )
						\Nino\Filesystem::removeDir( (string) $folder );
				}
			}
		}

		/**
		 *	The tokens this session holds of a kind, token => the time it runs out
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$kind
		 *
		 *	@return 	array
		 */
		private static function _tokens( array &$appData, string $kind ): array {

			$held = \Nino\Runtime::getSessionValue( $appData, self::SESSION. $kind, [] );

			return is_array( $held ) === true ? array_filter( $held, static fn( mixed $until ): bool => is_int( $until ) === true ) : [];
		}
	}
}
