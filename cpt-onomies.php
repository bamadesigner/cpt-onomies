<?php

/*
Plugin Name: CPT-onomies: Using Custom Post Types as Taxonomies
Plugin URI: http://wordpress.org/plugins/cpt-onomies/
Description: A CPT-onomy is a taxonomy built from a custom post type, using the post titles as the taxonomy terms. Create custom post types using the CPT-onomies custom post type manager or use post types created by themes or other plugins.
Version: 1.3
Author: Rachel Carden
Author URI: http://www.rachelcarden.com
*/

/*
This program is free software; you can redistribute it and/or modify 
it under the terms of the GNU General Public License as published by 
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
GNU General Public License for more details. 

You should have received a copy of the GNU General Public License 
along with this program; if not, write to the Free Software 
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/

// let's define some stuff. maybe we'll use it later
define( 'CPT_ONOMIES_VERSION', '1.3' );
define( 'CPT_ONOMIES_WORDPRESS_MIN', '3.1' );
define( 'CPT_ONOMIES_DIR', dirname( __FILE__ ) );
define( 'CPT_ONOMIES_URL', WP_PLUGIN_URL . '/' . basename( dirname( __FILE__ ) ) . '/' );
define( 'CPT_ONOMIES_PLUGIN_NAME', 'CPT-onomies: Using Custom Post Types as Taxonomies' );
define( 'CPT_ONOMIES_PLUGIN_SHORT_NAME', 'CPT-onomies' );
define( 'CPT_ONOMIES_PLUGIN_DIRECTORY_URL', 'http://wordpress.org/extend/plugins/cpt-onomies/' );
define( 'CPT_ONOMIES_PLUGIN_FILE', 'cpt-onomies/cpt-onomies.php' );
define( 'CPT_ONOMIES_DASH', 'custom-post-type-onomies' );
define( 'CPT_ONOMIES_UNDERSCORE', 'custom_post_type_onomies' );
define( 'CPT_ONOMIES_OPTIONS_PAGE', 'custom-post-type-onomies' );
define( 'CPT_ONOMIES_POSTMETA_KEY', '_custom_post_type_onomies_relationship' );
define( 'CPT_ONOMIES_TEXTDOMAIN', 'cpt-onomies' );

// if we build them, they will load
require_once( CPT_ONOMIES_DIR . '/cpt-onomy.php' );
require_once( CPT_ONOMIES_DIR . '/manager.php' );
if ( is_admin() ) {
	require_once( CPT_ONOMIES_DIR . '/admin.php' );
	require_once( CPT_ONOMIES_DIR . '/admin-settings.php' );
}
require_once( CPT_ONOMIES_DIR . '/widgets.php' );

// extend all the things
require_once( CPT_ONOMIES_DIR . '/extend/gravity-forms-custom-post-types.php' );

// for translations
add_action( 'plugins_loaded', 'custom_post_type_onomies_load_textdomain' );
function custom_post_type_onomies_load_textdomain() {
	load_plugin_textdomain( CPT_ONOMIES_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}

// for the newbies
register_activation_hook( __FILE__, 'custom_post_type_onomies_activation_hook' );
function custom_post_type_onomies_activation_hook( $network_wide ) {
	flush_rewrite_rules( false );
}

?>