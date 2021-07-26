<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
define("PH_SOURCE_ID", 46);
define("PH_START_SITE_ID", 3);
/* need pre-loaded */
require_once PH_CLONER_PLUGIN_DIR . 'ph-utils.php';

//short code for register
add_shortcode('ph_cloner_start', 'ph_cloner_start');

/* add action for pre-signon */
add_action('lrm/login_pre_signon', 'ph_user_site_redirect', 10 );
function ph_user_site_redirect($user_info){
    $user_id = get_user_by("login",$user_info['user_login'])->ID;
    $site_id = get_user_meta( $user_id, 'primary_blog', true );
    switch_to_blog($site_id);
}

/* add script */
add_action ( 'wp_head', 'js_new_sitename_verify' );
function js_new_sitename_verify(){ 
    ?>
<script type="text/javascript">
    var ajax_url = '<?php echo admin_url( "admin-ajax.php" ); ?>';
    var ajax_nonce = '<?php echo wp_create_nonce( "newsite-name-verify" ); ?>';
</script>
<?php
}

/**
 * New site form submit.
 *
 * @return void
 */
add_action('wp_ajax_ph_cloner_newsite_namecheck', 'newsite_namecheck'); 
add_action('wp_ajax_nopriv_ph_cloner_newsite_namecheck', 'newsite_namecheck');

function newsite_namecheck(){
    check_ajax_referer( 'newsite-name-verify', 'security' );
    $user_id = get_current_user_id();
    if (!$user_id){
        //not logged in
        wp_send_json_error(__( 'Please login first.', 'ph-cloner-site-copier' ));
    }
    /* get the user's primary site */
    $blog_id = get_user_meta( $user_id, 'primary_blog', true );

    if ( $blog_id >= PH_START_SITE_ID ){
        //primary site exists
        wp_send_json_error(__( 'Your site was created already.', 'ph-cloner-site-copier' ));
    }
    //need check
    $sitename = isset( $_POST['sitename'] ) ? sanitize_title( $_POST['sitename'] )  : '';
    if(!ph_wp_validate_site($sitename)) {
        wp_send_json_error(__( 'Site name is not available, try another one.', 'ph-cloner-site-copier' ));
    } else {
        wp_send_json_success( __( 'Site url: '.$sitename.'.pos.host', 'ph-cloner-site-copier' ));
    }
}

/* update blogname */
function ph_update_blogname($site_id, $blogname){
    global $wpdb;
    if ( empty( $site_id ) || empty($blogname)) {
        return false;
    }
    switch_to_blog( $site_id );
    $serialized_value = maybe_serialize( sanitize_option( 'blogname', $blogname ) );
    $update_args = array(
        'option_value' => $serialized_value,
        'autoload' => 'yes',
    );
    $result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => 'blogname' ) );
    restore_current_blog();
    
    if ( ! $result ) {
        return false;
    }
}

/**
 * New site form submit.
 *
 * @return void
 */
add_action('wp_ajax_ph_cloner_newsite_submit', 'newsite_submit'); 
add_action('wp_ajax_nopriv_ph_cloner_newsite_submit', 'newsite_submit');

function newsite_submit(){
    check_ajax_referer( 'newsite-name-verify', 'security' );
    $user_id = get_current_user_id();
    if (!$user_id){
        //not logged in
        wp_send_json_error(__( 'Please login first.', 'ph-cloner-site-copier' ));
    }
    /* get the user's primary site */
    $blog_id = get_user_meta( $user_id, 'primary_blog', true );

    if ( $blog_id >= PH_START_SITE_ID ){
        //primary site exists
        wp_send_json_error(__( 'Your site was created already.', 'ph-cloner-site-copier' ));
    }
    
    //need setup site
    $storename = isset( $_POST['ph_cloner_newsite_input'] ) ? sanitize_text_field( $_POST['ph_cloner_newsite_input'] )  : '';
    $sitename = sanitize_title($storename);
    if(!$sitename){
        /*not likely  */
        wp_send_json_error(__( 'Site name is required.', 'ph-cloner-site-copier' ));
    }else if(!ph_wp_validate_site($sitename)) {
        wp_send_json_error(__( 'Site name is not available, try another one.', 'ph-cloner-site-copier' ));
    } else {
        /* good name */
        //do clone
        //$user_id - new site admin, hard coded in function to 1 ( network super admin )
        $newsite_id = ph_do_cloner($user_id, $sitename, $storename);

        if($newsite_id){ 
            /* succeed */
            /* remove user from main sites */
            if ( 1 != $user_id ){
                remove_user_from_blog($user_id, 0);
                remove_user_from_blog($user_id, 1);
            }

            /* add user to the new site */
            $ret = ph_update_blogname($newsite_id,$storename);
            
            /* remove admin */
            remove_user_from_blog($user_id, $newsite_id);
            remove_user_from_blog(1, $newsite_id);

            /* add user to the new site */
            add_user_to_blog( $newsite_id, $user_id, 'shop_manager' );
            wp_send_json_success( 'New store '.$sitename.' is ready!');
        }else{
            wp_send_json_error(__( 'Oops, something went wrong, please try again.', 'ph-cloner-site-copier' ));
        }
    }
}

$newsite_js = PH_CLONER_PLUGIN_URL.'assets/ph_newsite_form.js';
wp_enqueue_script( 'ph-newsite', $newsite_js , array('jquery') , PH_CLONER_PLUGIN_VERSION );
wp_enqueue_scripts();

/*
 * Entry point of cloner
 * show create site form for logged in users
 * who hasn't got site yet.
 * 
 */
function ph_cloner_start(){

    $user_id = get_current_user_id();
    if( !$user_id ){
     /* not logged in, return for login */
        return;
    }
    
    $current_site_id = get_current_blog_id();
    $site_id = get_user_meta( $user_id, 'primary_blog', true );

    /* if not super admin, send to user's subsite if not from main site or 
     * user has assigned to a subsite
     * 
     *  */
    if ( 1 != $user_id && ( 1 != $current_site_id || ( PH_START_SITE_ID <= $site_id  ) ) ){
        $to_url = get_site_url($site_id);
        /* redirect to user's site or home site */
        $to_url = $to_url?$to_url:get_site_url(1);
        wp_redirect($to_url);
        return;
    }
    
    /* available user: logged on main site and no subsites */
    ob_start();
    include_once PH_CLONER_PLUGIN_DIR."views/ph_newsite_form.php";
    return ob_get_clean();
}

function ph_do_cloner( $user_id, $target_name, $target_title ){
    // Load classes and functions.
    // $target_title will be ignored.
    
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-log.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-request.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-files-process.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-tables-process.php';
    
    /* init, make sure session is set to start */
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
