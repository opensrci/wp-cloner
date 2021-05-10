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
        private $num = 0;
        private $failed = 0;

        /**
	 * Initialize and set label
	 */
	public function __construct() {

	}
        
        
	/**
	 * Process item.
	 *
	 * @param mixed $item Queue item to process.
	 * @return bool
	 */
	public  function task( $request ) {
		$source_dir         = $request->get( 'source_upload_dir' );
		$destination_dir    = $request->get( 'target_upload_dir' );
                 
		// Makes sure that the source and target are available.
                 if ( !$source_dir  || !$destination_dir  || $source_dir === $destination_dir  ) {
			$request->exit_processes( __( 'Source and target prefix or id issue. Cannot copy files.', 'ph-cloner-site-copier' ) );
                 }
                 
                 $this->ph_dir_copy( $source_dir, $destination_dir, );
            
        }


        protected function copy_file( $source, $destination  ) {

		// Make sure source file exists and destination filed does NOT exist.
		if ( ! is_file( $source ) ) {
			ph_cloner_log()->log( "Source file <b>$source</b> not found. Skipping copy." );
			return false;
		} elseif ( is_file( $destination ) && ! ph_cloner_request()->is_mode( 'clone_over' ) ) {
			ph_cloner_log()->log( "Destination file <b>$destination</b> already exists. Skipping copy." );
			return false;
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
			return true;
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

        /**
         * Copy directories and files recursively by queueing them in a background process
         *
         * Skip directories called 'sites' to avoid copying all sites storage in WP > 3.5
         *
         * @param string                $src Source directory path.
         * @param string                $dst Destination directory path (Relative).
         * @param WP_Background_Process $process Background process to use for queueing files.
         * @param int                   $num File number in queue.
         * @return int Number of files found
         */
        protected function ph_dir_copy( $src, $dst ) {
                if ( is_dir( $src ) ) {
                        $files = scandir( $src );
                        // Specify items to ignore when copying.
                        $ignore = apply_filters( 'ph_cloner_dir_copy_ignore', [ 'sites', '.', '..' ] );
                        // Recursively copy files that aren't in the ignore array.
                        foreach ( $files as $file ) {
                                if ( ! in_array( $file, $ignore, true ) ) {
                                        $this->ph_dir_copy( "$src/$file", "$dst/$file" );
                                }
                        }
                } elseif ( file_exists( $src ) ) {
                        if( $this->copy_file( $src, $dst ) ){
                            $this->num++;
                        }else{
                            $this->failed++;
                        }
                }
        }

}
