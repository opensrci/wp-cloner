<?php
/**
 * Plugin Name: pos.host cloner
 * Plugin URI: 
 * Description: pos.host site copier.
 * Author: pos.host
 * Author URI: https://pos.host/about
 * Version: 0.0.1
 * Text Domain: woocommerce-pos-host
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2021 pos.host
 * Forked from
 * NS Cloner - Site Copier  by Never Settle  (https://neversettle.it) 
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package ph_cloner
 *
 * WC requires at least: 3.5.0
 * WC tested up to: 4.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PH_CLONER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PH_CLONER_PLUGIN_URL', plugin_dir_url(__FILE__));
define( 'PH_CLONER_PLUGIN_BASENAME', plugin_basename(__FILE__));
define( 'PH_CLONER_LOG_TABLE', 'wp_ph_cloner_log' );
define( 'PH_CLONER_PLUGIN_VERSION', '0.0.3');

// Load external libraries.
require_once PH_CLONER_PLUGIN_DIR . 'ph-cloner-starter.php';

/**
 * Main core of PH_Cloner plugin.
 *
 * This class is an umbrella for all cloner components - managing instances of each of the other utility classes,
 * addons, sections, background processes, etc. and letting them refer to each other. It also handles all the basic
 * admin hooks for menus, assets, notices, templates, etc.
 */
final class PH_Cloner {

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version = '1.0';

	/**
	 * Menu Slug
	 *
	 * @var string
	 */
	public $menu_slug = 'wp_ph_cloner';

	/**
	 * Shortcut reference to access $wpdb without declaring a global in every method
	 *
	 * @var wpdb object
	 */
	public $db;

         /**
	 * Instance of PH_Cloner_Log
	 *
	 * @var PH_Cloner_Log object
	 */
	public $log;

	/**
	 * Prefix to add to temporary tables by modes that require them
	 *
	 * @var string
	 */
	public $temp_prefix = '_mig_';

	/**
	 * Singleton instance of
	 *
	 */
	private static $instance = null;
        
        	/**
	 * Get singleton instance
	 *
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
        
	/**
	 * PH_Cloner constructor.
	 */
	private function __construct() {
                  
		// Set instance to prevent infinite loop.
		self::$instance = $this;
		// Create $wpdb access shortcut to save declaring global every place it's used.
		global $wpdb;
		$this->db = $wpdb;
                 $this->init();
               
	}

	/**
	 * Initialize Cloner modes, sections, UI, etc.
	 *
	 * The difference between this and the constructor is that anything that needs to use localization has to go here.
	 */
	public function init() {
		// Install custom tables after cloner init.
                 $this->install_tables();
	}

	/**
	 * Retrieve list of database tables for a specific site.
	 *
	 * @param int  $site_id Database prefix of the site.
	 * @param bool $exclude_global Exclude global tables from the list (only relevant for main site).
	 * @return array
	 */
	public function get_site_tables( $site_id, $exclude_global = true ) {
		if ( empty( $site_id ) || ! is_multisite() ) {
			// All tables - don't filter by any id.
			$prefix = $this->db->esc_like( $this->db->base_prefix );
			$tables = $this->db->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
		} elseif ( ! is_main_site( $site_id ) ) {
			// Sub site tables - a prefix like wp_2_ so we can get all matches without having to filter out global tables.
			$prefix = $this->db->esc_like( $this->db->get_blog_prefix( $site_id ) );
			$tables = $this->db->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
		} else {
			// Root site tables - a main prefix like wp_ requires that we filter out both global and other subsites' tables.
			// Define patterns for both subsites (eg wp_2_...) and global tables (eg wp_blogs) which should not be copied.
			$wp_global_tables  = $this->db->tables( 'global', false, $site_id );
			$all_global_tables = apply_filters( 'ph_cloner_global_tables', $wp_global_tables );
			$global_pattern    = "/^{$this->db->base_prefix}(" . implode( '|', $all_global_tables ) . ')$/';
			$subsite_pattern   = "/^{$this->db->base_prefix}\d+_/";
			$temp_pattern      = '/^' . ph_cloner()->temp_prefix . '/';
			$prefix            = $this->db->esc_like( $this->db->base_prefix );
			$all_tables        = $this->db->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
			$tables            = [];
			foreach ( $all_tables as $table ) {
				$is_global_table  = preg_match( $global_pattern, $table );
				$is_subsite_table = preg_match( $subsite_pattern, $table );
				$is_temp_table    = preg_match( $temp_pattern, $table );
				if ( ( ! $is_global_table || ! $exclude_global ) && ! $is_subsite_table && ! $is_temp_table ) {
					array_push( $tables, $table );
				}
			}
		}
		// Apply optional filter and return.
		return apply_filters( 'ph_cloner_site_tables', $tables, $site_id );
	}

	/**
	 * Check whether the current user can run a clone operation and whether nonce is valid, then optionally die or return false.
	 *
	 * @param bool $die Whether to die on failure.
	 * @return bool
	 */
	public function check_permissions( $die = true ) {

                return true;
	}
       	
        /**
	 * Install custom Cloner tables
	 */
	public function install_tables() {
                //log table
                $query = "
                            CREATE TABLE `".PH_CLONER_LOG_TABLE."` (
                            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT, 
                            logtime DATETIME DEFAULT NULL,
                            log longtext DEFAULT NULL,
                            PRIMARY KEY (id)
                            ) {$this->db->get_charset_collate()};
                          ";
                $this->db->query($query);
                //update_site_option( 'ph_cloner_installed_version', $this->version );
        }

}

/**
 * Return singleton instance of PH_Cloner
 * 
 * @return PH_Cloner
 */
function ph_cloner() {
	return PH_Cloner::instance();
}



