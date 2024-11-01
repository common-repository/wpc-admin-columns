<?php
/*
Plugin Name: WPC Admin Columns
Plugin URI: https://wpclever.net/
Description: Manage and organize columns in the products, orders, and any post types lists in the admin panel.
Version: 2.0.7
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-admin-columns
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 6.6
Playground: true
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCAC_VERSION' ) && define( 'WPCAC_VERSION', '2.0.7' );
! defined( 'WPCAC_LITE' ) && define( 'WPCAC_LITE', __FILE__ );
! defined( 'WPCAC_FILE' ) && define( 'WPCAC_FILE', __FILE__ );
! defined( 'WPCAC_URI' ) && define( 'WPCAC_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCAC_DIR' ) && define( 'WPCAC_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCAC_REVIEWS' ) && define( 'WPCAC_REVIEWS', 'https://wordpress.org/support/plugin/wpc-admin-columns/reviews/?filter=5' );
! defined( 'WPCAC_SUPPORT' ) && define( 'WPCAC_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcac&utm_campaign=wporg' );
! defined( 'WPCAC_CHANGELOG' ) && define( 'WPCAC_CHANGELOG', 'https://wordpress.org/plugins/wpc-admin-columns/#developers' );
! defined( 'WPCAC_DISCUSSION' ) && define( 'WPCAC_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-admin-columns' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCAC_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';

if ( ! function_exists( 'wpcac_init' ) ) {
	add_action( 'plugins_loaded', 'wpcac_init', 11 );

	function wpcac_init() {
		load_plugin_textdomain( 'wpc-admin-columns', false, basename( __DIR__ ) . '/languages/' );

		if ( ! class_exists( 'WPCleverWpcac' ) ) {
			class WPCleverWpcac {
				public function __construct() {
					include_once 'includes/kses.php';
					include_once 'includes/class-duplicate.php';
					include_once 'includes/class-backend.php';
				}
			}

			new WPCleverWpcac();
		}
	}
}
