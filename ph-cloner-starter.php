<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
define("PH_SOURCE_ID", 4);
/* need pre-loaded */
require_once PH_CLONER_PLUGIN_DIR . 'ph-utils.php';


//@todo debug
add_shortcode('ph_cloner_start', 'ph_cloner_start');

function js_new_sitename_verify(){ 
    ?>
<script type="text/javascript">
    var ajax_url = '<?php echo admin_url( "admin-ajax.php" ); ?>';
    var ajax_nonce = '<?php echo wp_create_nonce( "newsite-name-verify" ); ?>';
</script>
<?php
}
add_action ( 'wp_head', 'js_new_sitename_verify' );
 
/**
 * New site form submit.
 *
 * @return void
 */
add_action('wp_ajax_ph_cloner_newsite_submit', 'newsite_submit'); 
add_action('wp_ajax_nopriv_ph_cloner_newsite_submit', 'newsite_submit');

function newsite_submit(){
    // This is a secure process to validate if this request comes from a valid source.
    check_ajax_referer( 'newsite-name-verify', 'security' );
    $sitename = isset( $_POST['ph_cloner_newsite_input'] ) ? sanitize_text_field( $_POST['ph_cloner_newsite_input'] )  : '';
    if(!$sitename){
        /*  */
        wp_send_json_error(__( 'Site name required.', 'ph-cloner-site-copier' ));
    }else if(!ph_wp_validate_site($sitename)) {
        wp_send_json_error(__( 'Site name not available, try another one.', 'ph-cloner-site-copier' ));
    } else {
        /* good name */
        wp_send_json_success( 'Done!');
    }
    exit();
}

/**
 * New site cancel
 *
 * @return void
 */
add_action('wp_ajax_ph_cloner_newsite_cancel', 'newsite_cancel'); 
add_action('wp_ajax_nopriv_ph_cloner_newsite_cancel', 'newsite_cancel');

function newsite_cancel(){
    wp_redirect(get_home_url());
}

/* @todo future
add_action('wp_ajax_ph_cloner_newsite_input', 'newsite_input'); 
add_action('wp_ajax_nopriv_ph_cloner_newsite_input', 'newsite_input');

function newsite_input(){
    // This is a secure process to validate if this request comes from a valid source.
    check_ajax_referer( 'newsite-name-verify', 'security' );
    $sitename    = isset( $_POST['ph_cloner_newsite_input'] ) ? wc_clean( wp_unslash( $_POST['ph_cloner_newsite_input'] ) ) : '';

    echo $sitename;
    wp_die();
}
 * 
 */

$newsite_js = PH_CLONER_PLUGIN_URL.'assets/ph_newsite_form.js';
wp_enqueue_script( 'ph-newsite', $newsite_js , array('jquery') , PH_CLONER_PLUGIN_VERSION );
wp_enqueue_scripts();

/*
 * Entry point of cloner
 * 
 */
function ph_cloner_start(){
    $current_user = wp_get_current_user();
    $site_id = $current_user->get_site_id();
    
    if( 1 == $site_id ){
        /* user on main site */
        include_once PH_CLONER_PLUGIN_DIR."views/ph_newsite_form.php";
    }else{
        /* user belong to subsite */
        echo($site_id);
    }
    exit;
}

function ph_do_cloner( $user_id, $target_name, $target_title ){
    // Load classes and functions.
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-log.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-request.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-files-process.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-tables-process.php';
    /* init */
    ph_cloner();    
    ph_cloner_log()->log_clear();
    $req = new PH_Cloner_Request();
    
    // Set the current user id, source id and target name, target title
    // so that the original user id can always be accessed by background processes.
    $req->set( 'user_id', $user_id );
    $req->set( 'source_id', PH_SOURCE_ID );

    $req->set( 'target_name', $target_name);
    $req->set( 'target_title', $target_title);


    $req->set_up_vars();
    $req->save();
    $table_process = new PH_Cloner_Tables_Process();
    $file_process = new PH_Cloner_Files_Process();
    
    /* creat site  */
    $req->create_site();
   
        
    /* clone tables */
    $table_process->task($req);
    
    /* copy files */
    $file_process->task($req);
    
    return $req->get('target_id');
}
