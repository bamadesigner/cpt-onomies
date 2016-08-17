<?php

/**
 * Plugin Name:     CPT-onomies: Using Custom Post Types as Taxonomies
 * Plugin URI:      http://wordpress.org/plugins/cpt-onomies/
 * Description:     A CPT-onomy is a taxonomy built from a custom post type, using the post titles as the taxonomy terms. Create custom post types using the CPT-onomies custom post type manager or use post types created by themes or other plugins.
 * Version:         1.3.4
 * Author:          Rachel Carden
 * Author URI:      https://bamadesigner.com
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     cpt-onomies
 * Domain Path:     /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
 
// If you define them, will they be used?
define( 'CPT_ONOMIES_VERSION', '1.3.4' );
define( 'CPT_ONOMIES_WORDPRESS_MIN', '3.1' );
define( 'CPT_ONOMIES_DIR', dirname( __FILE__ ) );
define( 'CPT_ONOMIES_PLUGIN_NAME', 'CPT-onomies: Using Custom Post Types as Taxonomies' );
define( 'CPT_ONOMIES_PLUGIN_DIRECTORY_URL', 'http://wordpress.org/extend/plugins/cpt-onomies/' );
define( 'CPT_ONOMIES_PLUGIN_FILE', 'cpt-onomies/cpt-onomies.php' );
define( 'CPT_ONOMIES_DASH', 'custom-post-type-onomies' );
define( 'CPT_ONOMIES_UNDERSCORE', 'custom_post_type_onomies' );
define( 'CPT_ONOMIES_OPTIONS_PAGE', 'custom-post-type-onomies' );
define( 'CPT_ONOMIES_POSTMETA_KEY', '_custom_post_type_onomies_relationship' );

// If we build them, they will load
require_once( CPT_ONOMIES_DIR . '/cpt-onomy.php' );
require_once( CPT_ONOMIES_DIR . '/manager.php' );
require_once( CPT_ONOMIES_DIR . '/widgets.php' );

// We only need these in the admin
if ( is_admin() ) {
	require_once( CPT_ONOMIES_DIR . '/admin.php' );
	require_once( CPT_ONOMIES_DIR . '/admin-settings.php' );
}

// Extend all the things
require_once( CPT_ONOMIES_DIR . '/extend/gravity-forms-custom-post-types.php' );

// For translations
add_action( 'plugins_loaded', 'custom_post_type_onomies_load_textdomain' );
function custom_post_type_onomies_load_textdomain() {
	load_plugin_textdomain( 'cpt-onomies', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

// runs when you activate the plugin
register_activation_hook( __FILE__, 'custom_post_type_onomies_activation_hook' );
function custom_post_type_onomies_activation_hook( $network_wide ) {
	
	// rewrite rules can be a pain in the ass
	// so let's flush them out and start fresh
	flush_rewrite_rules( false );
	
}

// runs when you upgrade anything
add_action( 'upgrader_process_complete', 'custom_post_type_onomies_upgrader_process_complete', 1, 2 );
function custom_post_type_onomies_upgrader_process_complete( $upgrader, $upgrade_info ) {
		
	// for some reason I find myself having to flush my
	// rewrite rules whenever I upgrade WordPress so just
	// helping everyone out by taking care of this automatically
	flush_rewrite_rules( false );
	
}