<?php
/**
 * Plugin Name:     CPT-onomies: Using Custom Post Types as Taxonomies
 * Plugin URI:      http://wordpress.org/plugins/cpt-onomies/
 * Description:     A CPT-onomy is a taxonomy built from a custom post type, using the post titles as the taxonomy terms. Create custom post types using the CPT-onomies custom post type manager or use post types created by themes or other plugins.
 * Version:         1.3.6
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
define( 'CPT_ONOMIES_VERSION', '1.3.6' );
define( 'CPT_ONOMIES_PLUGIN_DIRECTORY_URL', 'http://wordpress.org/extend/plugins/cpt-onomies/' );
define( 'CPT_ONOMIES_PLUGIN_FILE', 'cpt-onomies/cpt-onomies.php' );
define( 'CPT_ONOMIES_OPTIONS_PAGE', 'custom-post-type-onomies' ); // @TODO remove when we create admin class
define( 'CPT_ONOMIES_POSTMETA_KEY', '_custom_post_type_onomies_relationship' ); // @TODO remove when we create admin class

// If we build them, they will load.
require_once plugin_dir_path( __FILE__ ) . 'cpt-onomy.php';
require_once plugin_dir_path( __FILE__ ) . 'manager.php';
require_once plugin_dir_path( __FILE__ ) . 'widgets.php';

// We only need these in the admin.
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'admin.php';
	require_once plugin_dir_path( __FILE__ ) . 'admin-settings.php';
}

// Extend all the things.
require_once plugin_dir_path( __FILE__ ) . 'extend/gravity-forms-custom-post-types.php';

/**
 * Our main plugin class.
 *
 * Class    CPT_onomies
 * @since   1.3.5
 */
class CPT_onomies {

	/**
	 * Whether or not this plugin is network active.
	 *
	 * @since	1.3.5
	 * @access	public
	 * @var		boolean
	 */
	public $is_network_active;

	/**
	 * Holds the class instance.
	 *
	 * @since	1.3.5
	 * @access	private
	 * @var		CPT_onomies
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @access  public
	 * @since   1.3.5
	 * @return	CPT_onomies
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		return self::$instance;
	}

	/**
	 * Let's warm up the engine.
	 *
	 * @access  protected
	 * @since   1.3.5
	 */
	protected function __construct() {

		// Is this plugin network active?
		$this->is_network_active = is_multisite() && ( $plugins = get_site_option( 'active_sitewide_plugins' ) ) && isset( $plugins[ CPT_ONOMIES_PLUGIN_FILE ] );

		// Load our text domain.
		add_action( 'init', array( $this, 'textdomain' ) );

		// Runs on install.
		register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Runs when the plugin is upgraded.
		add_action( 'upgrader_process_complete', array( $this, 'upgrader_process_complete' ), 1, 2 );

	}

	/**
	 * Method to keep our instance from being cloned.
	 *
	 * @since	1.3.5
	 * @access	private
	 * @return	void
	 */
	private function __clone() {}

	/**
	 * Method to keep our instance from being unserialized.
	 *
	 * @since	1.3.5
	 * @access	private
	 * @return	void
	 */
	private function __wakeup() {}

	/**
	 * Runs when the plugin is installed.
	 *
	 * @access  public
	 * @since   1.3.5
	 */
	public function install() {

		/*
		 * Rewrite rules can be a pain in the ass
		 * so let's flush them out and start fresh.
		 */
		flush_rewrite_rules( false );

	}

	/**
	 * Runs when the plugin is upgraded.
	 *
	 * @access  public
	 * @since   1.3.5
	 * @param   Plugin_Upgrader $upgrader   Plugin_Upgrader instance.
	 * @param   array $upgrade_info         Array of bulk item update data.
	 *              @type string $action   Type of action. Default 'update'.
	 *              @type string $type     Type of update process. Accepts 'plugin', 'theme', or 'core'.
	 *              @type bool   $bulk     Whether the update process is a bulk update. Default true.
	 *              @type array  $packages Array of plugin, theme, or core packages to update.
	 */
	public function upgrader_process_complete( $upgrader, $upgrade_info ) {

		/*
		 * For some reason I find myself having to flush my
		 * rewrite rules whenever I upgrade WordPress so just
		 * helping everyone out by taking care of this automatically
		 */
		flush_rewrite_rules( false );

	}

	/*
	 * Internationalization FTW.
	 * Load our textdomain.
	 *
	 * @access  public
	 * @since   1.3.5
	 */
	public function textdomain() {
		load_plugin_textdomain( 'cpt-onomies', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

}

/*
 * Returns the instance of our main CPT_onomies class.
 *
 * Will come in handy when we need to access the
 * class to retrieve data throughout the plugin.
 *
 * @since	1.3.5
 * @access	public
 * @return	CPT_onomies
 */
function cpt_onomies() {
	return CPT_onomies::instance();
}

// Let's get this show on the road.
cpt_onomies();
