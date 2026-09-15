<?php
/**
 * Bootstrap — as regras são puras; só o mínimo do WordPress é dublado.
 */

declare( strict_types=1 );

error_reporting( E_ALL );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-regras.php';
