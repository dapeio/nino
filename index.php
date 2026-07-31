<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

require '_nino/Nino.php';

// Init Nino - the contact form's POST / handler is \Nino\Shortcodes\Form,
// registered like every other module via config.php's /nino/modules
$appData    = \Nino\init();

// Replace theme
$themes = [ 'agency', 'correct', 'glow', 'kontor', 'marketplace', 'nighty', 'solid', 'wellness' ];
if( isset( $_GET['theme'] ) && in_array( $_GET['theme'], $themes, true ) )
	$_SESSION['theme'] = $_GET['theme'];
$cur = $_SESSION['theme'] = in_array( $_SESSION['theme'] ?? '', $themes, true) ? $_SESSION['theme'] : $themes[0];
$appData["/nino/html/assets"]["/.cache/style.css"][1] = '/assets/style.theme.'. $cur. '.css';

// Output Nino
$request    = \Nino\request( $appData, $_SERVER );

// Themeswitcher
$themeswitcher = '<details style="position:fixed;bottom:12px;right:12px;z-index:2147483647;font:13px/1.4 arial,system-ui,sans-serif"><summary style="list-style:none;background:#111;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer;box-shadow:0 4px 16px #0005">&#9673; ' . $cur . '</summary><div style="background:#111;padding:6px;border-radius:8px;margin-top:6px;box-shadow:0 4px 16px #0005">';
foreach( $themes as $theme )
	$themeswitcher .= '<a href="?theme=' . $theme . '" style="display:block;padding:5px 12px;border-radius:5px;text-decoration:none;white-space:nowrap;background:' . ( $theme === $cur ? '#fff' : 'transparent' ) . ';color:' . ( $theme === $cur ? '#111' : '#fff' ) . '">' . ucfirst( $theme ) . '</a>';
$request['/nino/http/response']['body'] = str_replace( '</body>', $themeswitcher.'</div></body>', $request['/nino/http/response']['body'] );

\Nino\output( $appData, $request );