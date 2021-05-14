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
    check_ajax_referer( 'newsite-name-verify', 'security' );
    
    $user_id = get_current_user_id();
    if (!$user_id){
        //not logged in
        wp_send_json_error(__( 'Please login first.', 'ph-cloner-site-copier' ));
    }
    /* get the user's primary site */
    $blog = get_active_blog_for_user($user_id);
    $blog_id = $blog->blog_id;
    
    if ( $blog_id > 4 ){
        //primary site exists
        wp_send_json_error(__( 'Your site was created.', 'ph-cloner-site-copier' ));
    }
    
    //need setup site
    $sitename = isset( $_POST['ph_cloner_newsite_input'] ) ? sanitize_text_field( $_POST['ph_cloner_newsite_input'] )  : '';
    if(!$sitename){
        /*not likely  */
        wp_send_json_error(__( 'Site name required.', 'ph-cloner-site-copier' ));
    }else if(!ph_wp_validate_site($sitename)) {
        wp_send_json_error(__( 'Site name not available, try another one.', 'ph-cloner-site-copier' ));
    } else {
        /* good name */
//todo debug
        //do clone
        //$newsite_id = ph_do_cloner($user_id, $sitename, $sitename);
$newsite_id = 22;

        if($newsite_id){ 
            /* succeed */
            /* remove user from main sites */
            if (1 != $user_id ) remove_user_from_blog($user_id, 0);
            remove_user_from_blog($user_id, 1);
            /* add user to the new site */
            add_user_to_blog( $newsite_id, $user_id, 'shop_manager' );
            ph_cloner()->in_session = false;
            wp_send_json_success( 'New store '.$sitename.' is ready!');
        }else{
            ph_cloner()->in_session = false;
            wp_send_json_error(__( 'Oops, something wrong, please try again.', 'ph-cloner-site-copier' ));
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

    $current_user = get_current_user_id();
    if ($current_user){
        /* get the user's primary site */
        $blog = get_active_blog_for_user($current_user);
        $blog_id = $blog->blog_id;
    }else{
        //not logged in
        return;
    }
    
    if ( 4 >= $blog_id && $current_user ){
        /* user not on subsites */
        ob_start();
        include_once PH_CLONER_PLUGIN_DIR."views/ph_newsite_form.php";
        return ob_get_clean();
    }else if ($blog_id){
        /* user belong to subsite */
        if(!is_admin()){
            wp_redirect(get_site_url($blog_id));
        }
    }
    
}


function ph_do_cloner( $user_id, $target_name, $target_title ){
    // Load classes and functions.
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-log.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-request.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-files-process.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-tables-process.php';
    
    /* init, make sure session is set to start */
    ph_cloner()->in_session = true;
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
