<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */



/*
 * Entry point of cloner
 * 
 */
function ph_cloner_start(){
    
    // Load classes and functions.
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-log.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-request.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-files-process.php';
    require_once PH_CLONER_PLUGIN_DIR . 'class-ph-cloner-tables-process.php';
    require_once PH_CLONER_PLUGIN_DIR . 'ph-utils.php';

    ph_cloner_log()->log_clear();
    
    $req = new PH_Cloner_Request();
    
    // Set the current user id, source id and target name, target title
    // so that the original user id can always be accessed by background processes.
    $req->set( 'user_id', get_current_user_id() );
    $req->set( 'source_id', '4' );

    $req->set( 'target_name', 'test');
    $req->set( 'target_title', 'test');
    $req->set( 'target_id', '15');


    $req->set_up_vars();
    $req->save();
    //$req->create_site();
    
    $table_process = new PH_Cloner_Tables_Process();
    /* initialization process manager*/
    $table_process->task($req);

}
