/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/*
 * 
 * @param {array} data
 * @returns {undefined}
 */
function newsite_ajax($,form_data,reload_on_success ){
    var resp = {};
    function response( ret ) {
            $("#ph_cloner_spinner").hide();
            $(':input[type="submit"]').prop('disabled', false);
            $('#menu-main').show();
    }
    form_data.push( { "name" : "security", "value" : ajax_nonce } );
    $("#ph_cloner_spinner").show();
    $(':input[type="submit"]').prop('disabled', true);
    $('#menu-main').hide();

        $.ajax({
            url : ajax_url,
            type : 'post',
            data : form_data,
            success : function(response){
                $("#ph_cloner_spinner").hide();
                $(':input[type="submit"]').prop('disabled', false);
                $('#menu-main').show();
                if( response.success ) {
                    $("#ph_cloner_notice").css({"background-color":"#558b2f", "color":"#ffffff" });
                    $("#ph_cloner_notice").show();
                    if(reload_on_success){
                        location.reload();
                    }
                }else{
                    $("#ph_cloner_notice").css({"background-color":"#F76c6c", "color":"#ffffff"});
                }
                $("#ph_cloner_notice").text(response.data);
                $("#ph_cloner_notice").show();
            },
            error :  function(response){
                $("#ph_cloner_spinner").hide();
                $(':input[type="submit"]').prop('disabled', false);
                $('#menu-main').show();
                $("#ph_cloner_notice").css({"background-color":"#F76c6c", "color":"#ffffff"});
                $("#ph_cloner_notice").text("Oops, something wrong. " + response.data);
                $("#ph_cloner_notice").show();
            },
        });
}

jQuery(document).ready(function($) {
    var response;
    
    /* function form submit*/
    $( 'form[name="ph_newsite_form"]' ).on( 'submit', function(e) {
        e.preventDefault();        
        var form_data = $(this).serializeArray();
        form_data["sitename"] = $('#ph_cloner_newsite_input').val();
        
        newsite_ajax($, form_data, reload_on_success=true)

    });
 
    /* function input change*/
    $( '#ph_cloner_newsite_input' ).change( function(e) {
        e.preventDefault(); 
        
        var form_data = [];
        form_data.push( { "name" : "sitename", "value" : $('#ph_cloner_newsite_input').val()} );
        form_data.push( { "name" : "action", "value" :'ph_cloner_newsite_namecheck'} );

        newsite_ajax($, form_data, reload_on_success=false)
    })
})

