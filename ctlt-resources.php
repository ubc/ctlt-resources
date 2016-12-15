<?php
/**
 * @wordpress-plugin
 * Plugin Name:       CTLT Resources
 * Plugin URI:        http://ctlt.ubc.ca
 * Description:       Allows any post type to be tagged as a "Resource"
 * Version:           0.1.0
 * Author:            CTLT, Devindra Payment
 * Text Domain:       ctltres
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: 
 */

class CTLT_Resources {
	public static $directory_path = '';
	public static $directory_url = '';
	public static $is_being_activated = false;
	
	public static function init() {
		self::$directory_path = plugin_dir_path( __FILE__ );
		self::$directory_url = plugin_dir_url( __FILE__ );
		
		//add_action( 'admin_notices', array( __CLASS__, 'check_requirements' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 11 );
	}

	public static function activate() {
		self::$is_being_activated = true;
	}

	/**
	 * Load the plugin, if we meet requirements.
	 * @filter plugins_loaded
	 */
	public static function load() {
		if ( self::meets_requirements() ) {
			require_once( self::$directory_path . '/includes/class-ctltres-resources.php' );
			require_once( self::$directory_path . '/includes/class-ctltres-configuration.php' );
			require_once( self::$directory_path . '/includes/class-ctltres-widget.php' );

			if ( is_admin() ) {
				require_once( self::$directory_path . '/admin/class-ctltres-metabox.php' );
			} else {
				require_once( self::$directory_path . '/public/class-ctltres-shortcodes.php' );
				require_once( self::$directory_path . '/public/class-ctltres-archive.php' );
			}
		}
	}

	/**
	 * Generate a custom error message and deactivates the plugin if we don't meet requirements
	 * @filter admin_notices
	 */
	/*
	public static function check_requirements() {
		if ( ! self::meets_requirements() ) {
			?>
			<div id="message" class="error">
				<p>
					<?php printf( __( 'CTLT Resources requires _____ to run, and has thus been <a href="%s">deactivated</a>. Please install and activate _____ and then reactivate this plugin.', 'ubcreg' ), admin_url( 'plugins.php' ) ); ?>
				</p>
			</div>
			<?php

			// Deactivate our plugin
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}

	/**
	 * Checks if the required plugin is installed.
	 */
	public static function meets_requirements() {
		return true; //defined( 'CMB2_LOADED' );
	}
}

CTLT_Resources::init();
