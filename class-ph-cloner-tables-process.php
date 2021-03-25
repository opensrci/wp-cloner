<?php
/**
 * Copy Tables Background Process
 *
 * @package PH_Cloner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PH_Cloner_Tables_Process class.
 *
 * Processes a queue of tables, and delegates and dispatches a new row process for each one.
 */
class PH_Cloner_Tables_Process {

	/**
	 * Process data
	 *
	 * @var string
	 */
        protected $source_prefix = '';
        protected $target_prefix = '';
        protected $source_table= '';
        protected $target_table= '';
        
        /*
         * finishing query need to execute after copying
         * 1 - ['alter_tables'] = alter tables 
         * 2 - ['views'] = create views
         * 
         */
        protected $finishing_query = [];
        
        
        /**
	 * Number of rows to include in a single insert statement.
	 *
	 * @var int
	 */
	protected $rows_per_query = 50;
        
        /* for insert rows */
        protected $insert_query = '';
        
        	/**
	 * Number of rows added to current insert statement.
	 * Used in conjunction with $rows_per_query.
	 *
	 * @var int
	 */
        protected $rows_count   = 0;        

	/**
	 * Array of primary key column names and last touched value by table.
	 *
	 * @var array
	 */
	protected $primary_keys = [];
        
        /*
         * instance of cloner request 
         * which called this process
         * 
         */
        protected $ph_cloner_request= '';
        
	/**
	 * Initialize and set label
	 */
	public function __construct() {
	}
        
	/**
	 * Task clone all the tables 
	 * @param {instance} PH_Cloner_request
	 * @return mixed
	 */
	public function task( $request ) {
                 $this->ph_cloner_request = $request;
		$this->source_id      = $request->get( 'source_id' );
		$this->target_id      = $request->get( 'target_id' );
                 $this->source_prefix  = ph_cloner()->db->get_blog_prefix( $this->source_id );
		$this->target_prefix  = ph_cloner()->db->get_blog_prefix( $this->target_id  );
                 
		// Makes sure that the source and target are available.
                 if ( !$this->source_id  || !$this->target_id  || $this->source_id === $this->target_id  ) {
			$request->exit_processes( __( 'Source and target prefix or id issue. Cannot clone tables.', 'ph-cloner-site-copier' ) );
                 }

		// Queue table cloning background process.
		$this->source_tables = ph_reorder_tables( ph_cloner()->get_site_tables( $this->source_id ) );
		foreach ( $this->source_tables as $st ) {
			$tt = preg_replace( "|^$this->source_prefix|", $this->target_prefix, $st );
                          $this->source_table  = $st;
                          $this->target_table  = $tt;
                          $this->copy_table();
			ph_cloner_log()->log( "QUEUEING clone of *$this->source_table* to *$this->target_table*" );
		}

                 /* complete alter tables finishing */
                 ph_do_query ( $this->finishing_query['alter_tables'] );
                 ph_do_query ( $this->finishing_query['views'] );
	}
        
        /**
	 * copy a table from source to target
	 */

        public function copy_table( $next_row = 0 ) {
		
                 // Implement filter to enable leaving a target table in place, if needed.
		// Otherwise, target table will be dropped and replaced.
		if ( apply_filters( 'ph_cloner_do_drop_target_table', true, $this->target_table ) ) {
			$drop_query  = "DROP TABLE IF EXISTS `$this->target_table`";
			ph_do_query( $drop_query );

                          $return = ph_sql_create_table( $this->source_table, $this->target_table, $this->source_prefix, $this->target_prefix );
                          $create_query = $return['query'];
                          if ($return['views']) {
                              $this->finishing_query['views'][] = $return['views'];
                              ph_cloner_log()->log( "Views [:".$this->source_table."]".$return['views'] );

                          }
                          if ($return['alter_tables']) {
                              $this->finishing_query['alter_tables'][] = $return['alter_tables'];
                              ph_cloner_log()->log( "alter_tables: [".$this->source_table."]".$return['alter_tables'] );

                          }
			// If it was a view, the create query will be returned empty, so skip.
			if ( empty( $create_query ) ) {
				return false;
			}
                        
                          /* abort if create table fails */
			if (! ph_do_query( $create_query ) ) 
                            return;
		}

		// Save row process batches that will actually do the cloning queries.
		// Note that it saves but doesn't dispatch here, because that would cause
		// multiple async requests for this same process, and race conditions.
		// Instead, we'll dispatch it once at the end in the complete() method.
		$where      = apply_filters( 'ph_cloner_rows_where', 'WHERE 1=1', $this->source_table, $this->source_prefix );
		$count_rows = ph_cloner()->db->get_var( "SELECT COUNT(*) rows_qty FROM `$this->source_table` $where" );
		ph_cloner_log()->log( "SELECTED $count_rows with query: SELECT COUNT(*) rows_qty FROM `$this->source_table` $where" );
                
                 /* begin to copy from starting row_num **/
		$row_num = $row_failed = 0;
		if ( $count_rows > 0 ) {
			// Enable picking up a partially-queued table if it was massive and had to cut out in the middle.
			if ( $next_row ) {
				ph_cloner_log()->log( "RESTARTING partially queued table at row *$next_row*" );
			}

                          // Add a rows process item for each found row in the table.
			for ( $i = $next_row; $i < $count_rows; $i++ ) {
                                if( ! $this->copy_row( $i )){
                                    //copy row failed.
                                    ph_cloner_log()->log( "Copy row *$i* from *$this->source_table* to *$this->target_table* has failed." );
                                    $row_failed ++;
                                    continue;
                                };
			}
			ph_cloner_log()->log_error( "QUEUEING *$count_rows* rows (failed: $row_failed ) from *$this->source_table* to *$this->target_table*" );
		} else {
			ph_cloner_log()->log_error( "SKIPPING TABLE *$this->source_table*, 0 rows found." );
		}
                
                 if( $this->insert_query && $this->rows_count ){
                     $this->insert_batch();
                 }
		return true;
	}
        
        /* copy row by row number */
        protected function copy_row( $row_num ){
                 
		$row = $this->get_row( $row_num );

		// Skip if row is empty.
		if ( empty( $row ) ) {
			ph_cloner_log()->log_error( "SKIPPING row *$row_num* in *$this->source_table* because content was empty" );
			return false;
		}

		// Set flag to skip any junk rows which shouldn't/needn't be copied.
		$is_cloner_data = isset( $row['option_name'] ) && preg_match( '/^ph_cloner/', $row['option_name'] );
		$is_transient   = isset( $row['option_name'] ) && preg_match( '/(_transient_rss_|_transient_(timeout_)?feed_)/', $row['option_name'] );
		$is_edit_meta   = isset( $row['meta_key'] ) && preg_match( '/(ph_cloner|_edit_lock|_edit_last)/', $row['meta_key'] );
		$do_copy_row    = apply_filters( 'ph_cloner_do_copy_row', ( ! $is_cloner_data && ! $is_transient && ! $is_edit_meta ), $row, $item );
		if ( ! $do_copy_row ) {
			ph_cloner_log()->log_error( "SKIPPING row in *$this->source_table* because do_copy_row was false:".implode(',',$row) );
			return false;
		}

		// Perform replacements.
		$replaced_in_row   = 0;
		$search_replace    = $this->ph_cloner_request->get_search_replace( $this->source_id, $this->target_id );
		$is_upload_path    = isset( $row['option_name'] ) && 'upload_path' === $row['option_name'];
		$do_search_replace = apply_filters( 'ph_cloner_do_search_replace', ( ! $is_upload_path ), $row, $item );
		if ( $do_search_replace ) {
			foreach ( $row as $field => $value ) {
				$replaced_in_column = ph_recursive_search_replace(
					$value,
					$search_replace['search'],
					$search_replace['replace'],
					$this->ph_cloner_request->get( 'case_sensitive', false )
				);
				$replaced_in_row   += $replaced_in_column;
				$row[ $field ]      = $value;
			}
			if ( $replaced_in_row > 0 ) {
				ph_cloner_log()->log( "PERFORMED *$replaced_in_row* replacements in *$this->target_table*" );
			}
		} else {
			ph_cloner_log()->log_error( "SKIPPING row replacements in *$this->source_table* because do_copy_row was false:".implode(',',$row)  );
		}

		// Remove primary key, if it's a table like wp_options where:
		// 1. The key isn't linked to anything else, so doesn't matter if it changes, and
		// 2. There's a possibility that the table will be added to during the clone process.
		$non_essential_keys = apply_filters(
			'ph_cloner_non_essential_primary_keys',
			[
				'options'  => 'option_id',
				'sitemeta' => 'meta_id',
			]
		);
		foreach ( $non_essential_keys as $table => $key ) {
			if ( preg_match( "/_{$table}$/", $this->source_table ) ) {
				unset( $row[ $key ] );
			}
		}

		// Insert new row.
		$this->insert_row( $row );
                 
                 return true;
        }
        
	/**
	 * Get the actual data for this row
	 *
	 * This preloads a number (default: 250) of rows ahead, so a query doesn't have to be run for each row.
	 * We don't want to load too many and risk maxing out memory, but we also don't want to query too often.
	 *
	 * @param int    $source_id ID of sources site.
	 * @param string $source_table Source table name.
	 * @param int    $row_num Index of row to be copied.
	 * @return array
	 */
	protected function get_row( $row_num ) {
		// Make sure the table array is initialized.
		if ( ! isset( $this->preloaded[ $this->source_table ] ) ) {
			$this->preloaded[ $this->source_table ] = [];
		}

		// Get the primary key for this table.
		if ( ! isset( $this->primary_keys[ $this->source_table ] ) ) {
			// Be careful about multiple keys - this can cause issues for some tables like term_relationships.
			// Best to default to LIMIT fetching if there are multiple primary keys, even though that's slower.
			$key_data = ph_cloner()->db->get_results( "SHOW KEYS FROM $this->source_table WHERE Key_name = 'PRIMARY'" );
			$this->primary_keys[ $this->source_table ] = [
				'name'  => $key_data && 1 === count( $key_data ) ? $key_data[0]->Column_name : false,
				'value' => 0,
			];
			ph_cloner_log()->log( [ "CHECKING primary key for *$this->source_table*", $key_data ] );
		}
		$primary_key_name = $this->primary_keys[ $this->source_table ]['name'];
		$primary_key_val  = $this->primary_keys[ $this->source_table ]['value'];

		// Try to preload the next set of data if the current row number isn't already preloaded.
		if ( ! isset( $this->preloaded[ $this->source_table ][ $row_num ] ) ) {
			$preload_amount = apply_filters( 'ph_cloner_rows_preload_amount', 250 );
			// Query the results - handle tables with primary keys more efficiently, but fallback to handle any strange ones that don't.
			if ( $primary_key_name ) {
				// Handle numeric vs non numeric primary keys - comparisons don't work reliably on non numeric,
				// so again fall back to limit statement if more efficient primary key comparison isn't possible.
				if ( is_numeric( $primary_key_val ) && $primary_key_val > 0 ) {
					$where = "WHERE `$primary_key_name` > $primary_key_val ";
					$limit = "LIMIT $preload_amount";
				} else {
					$where = 'WHERE 1=1';
					$limit = "LIMIT $row_num, $preload_amount";
				}
				$order = "ORDER BY `$primary_key_name` ASC";
			} else {
				$where = 'WHERE 1=1';
				$limit = "LIMIT $row_num, $preload_amount";
				$order = '';
			}
			// Compile query, and add filters so that content filtering can be applied at query
			// time for things like excluding certain post types, etc.
			$query = "SELECT $this->source_table.* FROM `$this->source_table`"
				. ' ' . apply_filters( 'ph_cloner_rows_where', $where, $this->source_table, $this->source_prefix )
				. ' ' . apply_filters( 'ph_cloner_rows_order', $order, $this->source_table, $this->source_prefix )
				. ' ' . apply_filters( 'ph_cloner_rows_limit', $limit, $this->source_table, $this->source_prefix );
			// Run it!
			ph_cloner_log()->log( [ "PRELOADING rows for *$this->source_table* with query:", $query ] );
			$rows = ph_cloner()->db->get_results( $query, ARRAY_A );
			// Assign the correct keys for the next preloaded batch, starting with the current row_num, not 0.
			$indexes = array_keys( array_fill( $row_num, count( $rows ), '' ) );
			// Store this batch of data in $preloaded - stays loaded as long as the current instance runs.
			$this->preloaded[ $this->source_table ] = array_combine( $indexes, $rows );
			ph_cloner_log()->log( 'PRELOADED *' . count( $this->preloaded[ $this->source_table ] ) . "* rows for *$this->source_table*" );
		}

		// Return the requested row now, since it should always be in the preloaded array now.
		// (still handle missing row possibility in case something went wrong with the preload query).
		if ( isset( $this->preloaded[ $this->source_table ][ $row_num ] ) ) {
			$row = $this->preloaded[ $this->source_table ][ $row_num ];
			if ( $primary_key_name ) {
				$this->primary_keys[ $this->source_table ]['value'] = $row[ $primary_key_name ];
			}
			return $row;
		} else {
			ph_cloner_log()->log_error( "Missing row *$row_num* in *$this->source_table* - could not be preloaded" );
			return [];
		}
	}

	/**
	 * Perform the row insertion, or queue and insert together
	 *
	 * @param array  $row Row of data to insert.
	 * @param string $target_table Name of table to insert into.
	 */
	protected function insert_row( $row) {
		$field_names = array_map( 'ph_sql_backquote', array_keys( $row ) );
		$field_list  = implode( ', ', $field_names );

		// Add necessary syntax before row values are appended.
		if ( empty( $this->insert_query ) ) {
			// Start off insert statement if one hasn't been started yet.
			$this->insert_query = "INSERT INTO `$this->target_table` ( $field_list ) VALUES\n";
		} elseif ( ! empty( $this->current_table ) && $this->current_table !== $this->target_table ) {
			// Track current table and force start of new insert statement if needed.
			$this->insert_query .= ";\nINSERT INTO `$this->target_table` ( $field_list ) VALUES\n";
		} else {
			// If still under the maximum rows per query, just add a comma and keep using current insert.
			$this->insert_query .= ",\n";
		}

		// Prepare data and add this row to the query.
		$formats             = implode( ', ', ph_prepare_row_formats( $row, $this->target_table ) );
		$this->insert_query .= ph_cloner()->db->prepare( "( $formats )\n", $row );
		$this->current_table = $this->target_table;
		$this->rows_count++;

		// Insert the previous accumulated query and start new, if reaching max query size.
		if ( ! empty( $this->insert_query ) && $this->is_query_maxed() ) {
			$this->insert_batch();
		}
	}

	/**
	 * Insert the whole current group of accumulated row insertions.
	 */
	protected function insert_batch() {
		// Break into single queries to handle servers where multiple insert statements in one query are not allowed.
		// $inserts = preg_split( '/;(?=\sINSERT)/', $this->insert_query );
		// $inserts = explode( '\n', $this->insert_query );
                 ph_do_query( $this->insert_query  );
		// Reset.
		$this->insert_query = '';
		$this->rows_count   = 0;
	}

	/**
	 * Check if current insert query is close to max size, in rows or length
	 *
	 * @return bool
	 */
	protected function is_query_maxed() {
		$packet_max          = ph_get_sql_variable( 'max_allowed_packet', 50000 );
		$exceeded_packet_max = strlen( $this->insert_query ) >= .9 * $packet_max;
                
		$exceeded_row_max    = $this->rows_count >= $this->rows_per_query;
		return $exceeded_row_max || $exceeded_packet_max;
	}

}
