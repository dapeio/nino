<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-demo-smoke.php	Contract test for what the demo of a group may call besides the components of its own feature: the components of Basis, the
 *												feature the kernel delivers - a demo.tpl of a feature that has none of them is read all the same, and a name that is no
 *												component of either is refused. What a demo is, and what makes one wrong, is tests/register-features-smoke.php.
 *
 *	Usage: php tests/basis-demo-smoke.php
 */

// A project with no features of its own: the one feature of the run is the one the kernel delivers
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-basis-demo-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

// A feature of this test: one component in one group, and the demo of the group
$show = NINO_FEATURES_DIR. '/Show';
mkdir( $show. '/components/show-box', 0755, true );
mkdir( $show. '/groups/show', 0755, true );
file_put_contents( $show. '/components/show-box/component.php', '<?php return '. var_export( [ 'group' => 'show', 'label' => 'Box', 'in' => [ 'page' ], 'offers' => [], 'template' => '<div class="[[class]]">[[content]]</div>' ], true ). ';' );
file_put_contents( $show. '/groups/show/group.php', '<?php return '. var_export( [ 'label' => 'Show' ], true ). ';' );

echo "The demo of a group: the components of Basis\n";

$demo = '[show-box]'. "\n\t". '[section-title text="A title" href="#"]'. "\n\t". '[image /nino/placeholder/mark decorative]'. "\n". '[/show-box]'. "\n";
file_put_contents( $show. '/groups/show/demo.tpl', $demo );
$result = \Nino\Features::readComponents( $show, 'show' );
check( 'a demo.tpl of a group may call the components of Basis, and a picture of the kernel: it is read, and nothing is refused', is_array( $result ) === true && $result['rejected'] === [] && $result['groups']['show']['demo'] === $demo );

file_put_contents( $show. '/groups/show/demo.tpl', '[show-box][form][/show-box]' );
$result = \Nino\Features::readComponents( $show, 'show' );
check( '...a name that is no component of the feature or of Basis is refused with a sentence, and the group stays', is_array( $result ) === true && array_keys( $result['rejected'] ) === [ 'demo show' ] && str_contains( $result['rejected']['demo show'], 'calls "form"' ) === true
	&& $result['groups']['show']['demo'] === '' );

ninoDone( ninoSandbox( 'basis-demo' ) );
