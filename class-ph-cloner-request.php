<?php
/**
 * Cloner Request class.
 *
 * @package PH_Cloner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PH_Cloner_Request class
 *
 */
class PH_Cloner_Request {

	/**
	 * Request Data
	 *
	 * @var array
	 */
	private $request = [];

	/**
	 * Option key to save stored request to
	 *
	 * @var string
	 */
	private $option_key = 'ph_cloner_saved_request';

	/**
	 * List of default variables to be defined for source and target sites
	 *
	 * @var array
	 */
	private $vars = [
		'prefix',
		'upload_dir',
		'upload_url',
		'url',
		'url_short',
	];

	/**
	 * Copay table process Instance 
	 *
	 * @var PH_Cloner_Request
	 */
	private $table_process;

	/**
	 * PH_Cloner_Request constructor.
	 */
	public function __construct() {
		// Load request from saved request option if present, enabling background processes to stay in sync.
		//$request = (array) get_site_option( 'ph_cloner_saved_request', [] );
                 //$this->request = $request;
                 $this->table_process = new PH_Cloner_Tables_Process();

	}

	/**
	 * Reload the request from the saved version in the database
	 *
	 * @return $this
	 */
	public function task() {
		$this->table_process->task($this->request);
	}

	/**
	 * Reload the request from the saved version in the database
	 *
	 * @return $this
	 */
	public function refresh() {
		$this->request = (array) get_site_option(  $this->option_key, [] );
		return $this;
	}

	/**
	 * Get all current request variables
	 *
	 * @return array
	 */
	public function get_request() {
		return $this->request;
	}

	/**
	 * Get a request variable
	 *
	 * @param string $key Key of request array.
	 * @param mixed  $default Default value.
	 *
	 * @return null
	 */
	public function get( $key, $default = null ) {
		return isset( $this->request[ $key ] ) ? $this->request[ $key ] : $default;
	}

	/**
	 * Set a request variable
	 *
	 * @param string $key Key of request array.
	 * @param string $value Value to set.
	 */
	public function set( $key, $value ) {
		$this->request[ $key ] = $value;
		ph_cloner_log()->log( [ "SETTING REQUEST VAR '$key' to:", $value ] );
	}

	/**
	 * Save the current request into site options for later reference by background processes
	 */
	public function save() {
		update_site_option(  $option_keyy, $this->request );
		ph_cloner_log()->log( [ 'SAVING REQUEST:', $this->request ] );
	}

	/**
	 * Save the current request into site options for later reference by background processes
	 */
	public function delete() {
		delete_site_option( self::$option_key );
		ph_cloner_log()->log( 'DELETING REQUEST' );
	}

	/**
	 * Generate definitions for site variables
	 *
	 * If null, 'network', or another string is provided as the site_id, it defaults to the main site
	 * (either the only site for single installs, or the main site on the network for multisite).
	 * Teleport uses this to provide a string (remote site url) rather than an ID, and then uses
	 * the filter at the bottom to return the correct variables.
	 *
	 * IMPORTANT: This cannot be called for target values during the middle of a cloning operation,
	 * because the target options table could be empty and site_url() will return empty.
	 *
	 * @param int $site_id Blog id of site to get variables for.
	 * @return array
	 */
	private function define_vars( $site_id = null ) {
		$is_subsite = is_multisite() && ! is_null( $site_id ) && is_numeric( $site_id );
		if ( $is_subsite ) {
			switch_to_blog( $site_id );
		}
		// Get site url directly rather than with site_url(), because option/object
		// caching can result in a blank value for a newly created site.
		$option_q = 'SELECT option_value FROM ' . ph_cloner()->db->options . " WHERE option_name='siteurl'";
		$site_url = set_url_scheme( ph_cloner()->db->get_var( $option_q ) );
		// Past Cloner versions had manual checking/overrides for wp_upload_dir.
		// However, it seems that wp_upload_dir() is now more reliable, whereas the
		// overrides were beginning to cause problems. If a fix is needed on a case
		// by case basis for when wp_upload_dir() is overwritten by a filter (e.g.
		// compatibility with another plugin), we could add a small patch plugin OR
		// add a filter in ns-compatibility.php to filter ph_cloner_request_define_vars.
		$upload_dir = wp_upload_dir();
		// If the upload_url_path option is blank, _wp_upload_dir will use WP_CONTENT_URL,
		// with the domain set to the network domain, not the current blog's domain, so fix it.
		$upload_url = str_replace( WP_CONTENT_URL, $site_url, $upload_dir['baseurl'] );
		// These definitions should all work both for multisite (after using switch_blog above
		// so they have the correct sub-site values) as well as single site / whole network.
		$vars = [
			'prefix'              => ph_cloner()->db->prefix,
			'upload_dir'          => $upload_dir['basedir'],
			'upload_dir_relative' => str_replace( ABSPATH, '', $upload_dir['basedir'] ),
			'upload_url'          => $upload_url,
			'upload_url_relative' => str_replace( $site_url, '', $upload_url ),
			'url'                 => $site_url,
			'url_short'           => untrailingslashit( preg_replace( '|^(https?:)?//|', '', $site_url ) ),
		];
		if ( $is_subsite ) {
			restore_current_blog();
		}
		return apply_filters( 'ph_cloner_request_define_vars', $vars, $site_id );
	}

	/**
	 * Add source and target vars to the current cloner request
	 *
	 * Take definitions from define_vars() for source and target ids, if applicable,
	 * and add them to the current cloner request array.
	 */
	public function set_up_vars() {
		$source_id = $this->get( 'source_id' );
		if ( $source_id ) {
			foreach ( $this->define_vars( $source_id ) as $key => $value ) {
				$this->set( "source_{$key}", $value );
			}
		}
		$target_id = $this->get( 'target_id' );
		if ( $target_id ) {
			foreach ( $this->define_vars( $target_id ) as $key => $value ) {
				$this->set( "target_{$key}", $value );
			}
		}
		if ( $source_id && $target_id ) {
			$this->set_up_search_replace( $source_id, $target_id );
		}
	}

	/**
	 * Set up search / replace value arrays
	 *
	 * @param int|null $source_id ID of source site.
	 * @param int|null $target_id ID of target site.
	 */
	public function set_up_search_replace( $source_id = null, $target_id = null ) {
		$source_id  = $source_id ?: $this->get( 'source_id' );
		$target_id  = $target_id ?: $this->get( 'target_id' );
		$option_key = "ph_cloner_search_{$source_id}_replace_{$target_id}";
		// Generate arrays and save if not.
		$search  = [
			$this->request['source_upload_dir_relative'],
			$this->request['source_upload_url'],
			$this->request['source_url_short'],
			$this->request['source_prefix'] . 'user_roles',
		];
		$replace = [
			$this->request['target_upload_dir_relative'],
			$this->request['target_upload_url'],
			$this->request['target_url_short'],
			$this->request['target_prefix'] . 'user_roles',
		];

		$search  = apply_filters( 'ph_cloner_search_items_before_sequence', $search );
		$replace = apply_filters( 'ph_cloner_replace_items_before_sequence', $replace );

		// Sort and filter replacements to intelligently avoid compounding replacement issues.
		ph_set_search_replace_sequence( $search, $replace );
		// Add filters that enable custom replacements to be applied.
		$search_replace = [
			'search'  => apply_filters( 'ph_cloner_search_items', $search ),
			'replace' => apply_filters( 'ph_cloner_replace_items', $replace ),
		];
		// Save in settings for use by background processes.
		update_site_option( $option_key, $search_replace );
		ph_cloner_log()->log( [ "SETTING search/replace for source *$source_id* and target *$target_id*:", $search_replace ] );
	}

	/**
	 * Get saved search / replace value arrays
	 *
	 * @param int|null $source_id ID of source site.
	 * @param int|null $target_id ID of target site.
	 * @return array
	 */
	public function get_search_replace( $source_id = null, $target_id = null ) {
		$source_id  = $source_id ?: $this->get( 'source_id' );
		$target_id  = $target_id ?: $this->get( 'target_id' );
		$option_key = "ph_cloner_search_{$source_id}_replace_{$target_id}";
		return get_site_option( $option_key );
	}

	/**
	 * Shortcut to check if the current mode is equal to a provided one (or in a provided list).
	 *
	 * @param string|array $mode_id Mode id or array of them to compare to the current mode.
	 * @return bool
	 */
	public function is_mode( $mode_id ) {
		if ( is_array( $mode_id ) ) {
			return in_array( $this->get( 'clone_mode' ), $mode_id, true );
		} else {
			return $this->get( 'clone_mode' ) === $mode_id;
		}
	}
        
        
	/**
	 * Create a new site/blog on the network (step 1 for core mode)
	 */
	public function create_site() {
		$source_id    = $this->get( 'source_id' );
		$target_name  = $this->get( 'target_name', '' );
		$target_title = $this->get( 'target_title', '' );

		// Sanitize.
		$target_name = strtolower( trim( $target_name ) );

		// Try to create new site.
		$source    = get_site( $source_id );
		$site_data = [
			'title'   => $target_title,
			//hard coded to set SuperAdmin as site Admin 
                          //'user_id' => $this->get( 'user_id' ),
                          'user_id' => 1,
			'public'  => $source->public,
			'lang_id' => $source->lang_id,
		];
		if ( is_subdomain_install() ) {
			$site_data += [
				'domain' => $target_name . '.' . preg_replace( '|^www\.|', '', get_current_site()->domain ),
				'path'   => get_current_site()->path,
			];
		} else {
			$site_data += [
				'domain' => get_current_site()->domain,
				'path'   => get_current_site()->path . $target_name . '/',
			];
		}
		ph_cloner_log()->log( [ 'Attempting to create site with data:', $site_data ] );
		if ( function_exists( 'wp_insert_site' ) ) {
			$target_id = wp_insert_site( $site_data );
		} else {
			// Backwards compatibility for pre 5.1.
			$target_id = wpmu_create_blog(
				$site_data['domain'],
				$site_data['path'],
				$site_data['title'],
				$site_data['user_id']
			);
		}

		// Handle results.
		if ( ! is_wp_error( $target_id ) ) {
			ph_cloner_log()->log( "New site '$target_title' (" . get_site_url( $target_id ) . ') created.' );
			$this->set( 'target_id', $target_id );
			$this->set_up_vars();
			$this->save();
                         
                          return true;
		} else {
     
			$this->exit_processes( 'Error creating site:' . $target_id->get_error_message() );
                          return false;
		}
	}
        public function getTargetSite(){
            
        }
        public function exit_processes( $msg ){
            wp_send_json_error($msg);
        }

}
