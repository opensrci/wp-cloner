<?php
/**
 * Cloner Logging class.
 *
 * @package PH_Cloner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PH_Cloner_Log class.
 *
 * Utility class for creating debug logs while running cloning processes.
 */
class PH_Cloner_Log {

	/**
	 * table of summary log
	 *
	 * @var string
	 */
	private $log_table = PH_CLONER_LOG_TABLE;

         /**
	 * start time
	 *
	 * @var
	 */
	private $start_time;
         
        /**
	 * end time
	 *
	 * @var
	 */
	private $end_time;
        
	/**
	 * Singleton instance
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
	 * PH_Cloner_Log constructor.
	 */
	public function __construct() {
            $this->instance = $this;
	}

	/**
	 * Determine if logging is enabled or not
	 *
	 * @return bool
	 */
	public function is_debug() {
//@todo debug    
            return true;
	}

	/**
	 * End logging - optionally add footer.
	 *
	 * Footer param is available because it will mess up formatting if we close a log
	 * that another background process is still writing to - if that's a possibility,
	 * don't worry about it and let the browser autoclose the tags.
	 *
	 * @param bool $do_footer Whether to output footer / closing tags.
	 */
	public function end( $do_footer = true ) {
	}

	/**
	 * Run after a wpdb function call to check for and log any sql errors
	 *
	 * Also, log all queries in when additional debugging is on.
	 *
	 * @return void
	 */
	public function handle_any_db_errors() {
                $this->log_error(ph_cloner()->db->last_error);
 	}

	/*
	______________________________________
	|
	|  Log Outputs
	|_____________________________________
	*/

	/**
	 * Write data to log file
	 *
	 * @param mixed $message String or data to log.
	 * @param bool  $raw Whether to include timestamp and tr/td tags.
	 * @return bool
	 */
	public function log_error( $message, $raw = true ) {
                $this->_log($message,"Error:");
	}
        
        /* clear log */
        public function log_clear() {
                /* ignore log */
                $query= "TRUNCATE TABLE `$this->log_table`" ;
                ph_cloner()->db->query( $query );
                return;
        }

        public function log( $message, $raw = true ) {
                /* ignore log */
                //$this->_log($message,"Info:");
                return;
        }

	/**
	 * Write data to log
	 *
	 *
	 * @param mixed $message String or data to log.
	 * @param bool  $raw Whether to include timestamp and tr/td tags.
	 * @return bool
	 */
	public function _log( $message, $prefix ) {
                
               if (is_array($message)){
                    $message = implode(',',$message);
                }

                $query = ph_cloner()->db->prepare( "INSERT INTO  `$this->log_table` ( logtime,log ) VALUES (%s,%s) ",
                           current_time("Y-m-d H:i:s"), $prefix.$message );
                ph_cloner()->db->query( $query );

	}

	/**
	 * 
	 */
	public function add_report( $log, $msg ) {
		$this->log( $log." : ".$msg );
	}

	/**
	 * Delete all log files from the logs directory
	 */
	public function delete_logs() {
               $query = "TRUNCATE `$this->log_table`";
               ph_cloner()->db->query( $query );

 	}

        /**
	 * Save the start time for this cloning process
	 */
	public function log_time() {
                return microtime( true );
	}

        /**
	 * Save the start time for this cloning process
	 */
	public function set_start_time() {
                $this->start_time = microtime( true );
	}

	/**
	 * Get the start time for this cloning process
	 *
	 * @param bool $prepared Whether to format the raw timestamp before returning.
	 * @return string
	 */
	public function get_start_time( ) {
                 $date =$this->start_time;
		return $date ? date( 'Y-m-d H:i:s', $date ) : '';
	}

	/**
	 * Save the end time for this cloning process
	 */
	public function set_end_time() {
                $this->end_time = microtime( true );
	}

	/**
	 * Get the end time for this cloning process
	 *
	 * @param bool $prepared Whether to format the raw timestamp before returning.
	 * @return string
	 */
	public function get_end_time( ) {
                 $date =$this->end_time;
		return $date ? date( 'Y-m-d H:i:s', $date ) : '';
	}

	/**
	 * Get the amount of time elapsed since the saved start time
	 *
	 * @return float
	 */
	public function get_elapsed_time() {
                 $date =$this->end_time - $this->start_time;
		return $date ? date( 'Y-m-d H:i:s', $date ) : '';
	}

	/**
	 * check if timeout
	 *
	 * @return float
	 */
	public function timeout() {
                 return ( microtime( true ) - $this->start_time > 100000 );
	}
        
 
}

/**
 * Return singleton instance of PH_Cloner
 * 
 * @return PH_Cloner
 */
function ph_cloner_log() {
	return PH_Cloner_Log::instance();
}