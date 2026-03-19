<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all custom tables and options created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-installer.php';

// Need WPSP_VERSION for the installer.
if ( ! defined( 'WPSP_VERSION' ) ) {
	define( 'WPSP_VERSION', '0.1.0' );
}

WPSP_Installer::uninstall();
