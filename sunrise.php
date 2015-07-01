<?php

if( defined( 'WP_UNIT_TEST' ) && WP_UNIT_TEST ){
	require( dirname( __FILE__ ) . '/sunrise/unit-testing.php');
} elseif( defined( 'WP_IS_DEV' ) && WP_IS_DEV ) {
	require( dirname( __FILE__ ) . '/sunrise/local.php' );
} else {
	require( dirname( __FILE__ ) . '/sunrise/production.php' );
}
