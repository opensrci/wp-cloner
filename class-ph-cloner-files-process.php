<?php
/**
 * Copy Files Background Process
 *
 * @package PH_Cloner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PH_Cloner_Files_Process class.
 *
 * Processes a queue of files and copies them to the uploads directory of a target site.
 */
class PH_Cloner_Files_Process {

	/**
	 * Ajax action.
	 *
	 * @var string
	 */
	protected $action = 'files_process';

	/**
	 * Initialize and set label
	 */
	public function __construct() {

		// Set a lower maximum batch size for files since queue items are bigger (more text for paths).
		add_filter( $this->identifier . '_max_batch', [ $this, 'max_batch' ] );
	}

	/**
	 * Process item.
	 *
	 * @param mixed $item Queue item to process.
	 * @return bool
	 */
	protected function task( $item ) {
		$source      = $item['source'];
		$destination = $item['destination'];

		// Make sure source file exists and destination filed does NOT exist.
		if ( ! is_file( $source ) ) {
			ph_cloner_log()->log( "Source file <b>$source</b> not found. Skipping copy." );
			return false;
		} elseif ( is_file( $destination ) && ! ph_cloner_request()->is_mode( 'clone_over' ) ) {
			ph_cloner_log()->log( "Destination file <b>$destination</b> already exists. Skipping copy." );
			return parent::task( $item );
		}

		// Create destination directory if it doesn't exist already.
		$destination_dir = dirname( $destination );
		if ( ! is_dir( $destination_dir ) ) {
			$missing_dirs = [];
			// Go back up the tree until we get to a dir that DOES exist.
			while ( ! is_dir( $destination_dir ) ) {
				$missing_dirs[]  = $destination_dir;
				$destination_dir = dirname( $destination_dir );
			}
			// Fill in all levels of missing directories.
			foreach ( array_reverse( $missing_dirs ) as $dir ) {
				if ( mkdir( $dir ) ) {
					ph_cloner_log()->log( "Creating directory $dir" );
				} else {
					ph_cloner_log()->log( "Could not create destination $dir. Skipping copy of $source." );
					return false;
				}
			}
		}

		// Attempt to copy file.
		if ( copy( $source, $destination ) ) {
			ph_cloner_log()->log( "Copied file <b>$source</b> to <b>$destination</b>." );
			return parent::task( $item );
		} else {
			ph_cloner_log()->log( "Failed copying file <b>$source</b> to <b>$destination</b>." );
			return false;
		}

	}

	/**
	 * Set a lower maximum batch size for files since queue items are bigger (more text for paths).
	 *
	 * @param int $max Default maximum number of batch items.
	 * @return int
	 */
	public function max_batch( $max ){
		return 2500;
	}

}
