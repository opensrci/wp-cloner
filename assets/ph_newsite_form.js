/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

jQuery(document).ready(function($) {
    
    /* submit function */
    $( 'form[name="ph_newsite_form"]' ).on( 'submit', function() {
    var form_data = $(this).serializeArray();
    form_data.push( { "name" : "security", "value" : ajax_nonce } );
 
    $.ajax({
        url : ajax_url,
        type : 'post',
        data : form_data,
        success : function( response ) {
alert( response.data);
            $("#ph_cloner_newsite_input").html = response.response;
        },
        fail : function( err ) {
alert( err.data);
            $("#ph_cloner_newsite_input").html = response.response;
        }
    });
    });

    /*@todo future
     *  input on-change function 
    $( "#ph_cloner_newsite_input").on( 'input', function() {
    var form_data = $(this).serializeArray();
    form_data.push( { "name" : "security", "value" : ajax_nonce } );
    form_data.push( { "name" : "action", "value" : 'newsite_input' } );
    
    $.ajax({
        url : ajax_url,
        type : 'post',
        data : form_data,
        success : function( response ) {
        },
        fail : function( err ) {
        }
    });
    });
    * 
    */
})

