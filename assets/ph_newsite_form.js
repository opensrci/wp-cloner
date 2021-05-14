/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

jQuery(document).ready(function($) {
    
    /* submit function */
    $( 'form[name="ph_newsite_form"]' ).on( 'submit', function(e) {
    e.preventDefault();        
    var form_data = $(this).serializeArray();
    form_data.push( { "name" : "security", "value" : ajax_nonce } );
    $('#ph_cloner_spinner').show();
    $(':input[type="submit"]').prop('disabled', true);
    $.ajax({
        url : ajax_url,
        type : 'post',
        data : form_data,
        success : function( response ) {
            $("#ph_cloner_spinner").hide();
            if( response.success ) {
                $("#ph_cloner_notice").css({"color":"#558b2f" });
            }else{
                $("#ph_cloner_notice").css({"color":"#F76c6c"});
            }
            $("#ph_cloner_notice").text(response.data);
            $("#ph_cloner_notice").show();
            $(':input[type="submit"]').prop('disabled', false);
            location.reload();
        },
        fail : function( err ) {
            $("#ph_cloner_spinner").hide();
            $("#ph_cloner_notice").css({"background-color":"#F76c6c", "color":"white" });
            $("#ph_cloner_notice").text(err.data);
            $("#ph_cloner_notice").show();
            $(':input[type="submit"]').prop('disabled', false);
        }
    });
    });

})

