<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

defined( 'ABSPATH' ) || exit;
$user = wp_get_current_user()->user_login;

?>

<h4>Hello <?php echo $user ?>, don't you have a store yet? </h4>

<form action="" method="post" name="ph_newsite_form" >
    <table>
        <tr style = 'align-center'  class="form-field" >
            <td>
                <label>Choose your store name: </label>
                <input id ="ph_cloner_newsite_input" name="ph_cloner_newsite_input" type="text" placeholder="your store name" required>
            </td>
        </tr>
    <br/>
    <br/>
    <tr>
        <td colspan="2">
                <input type="hidden" name="action" value="ph_cloner_newsite_submit" style="display: none; visibility: hidden; opacity: 0;">
                <span id="ph_cloner_notice" style="display:none" ></span>
            </td>
    </tr>
    <tr>
            <td>
                <button class="wp-block-button__link has-text-color has-background"
                        style="background-color:#382b73;color:#ffffff"
                        type="submit" 
                        >Create
                </button>
                <span class="spinner" id="ph_cloner_spinner" style="display:none">
                     <div class="bounce1"></div>
                     <div class="bounce2"></div>
                     <div class="bounce3"></div>
                     <div class="bounce4"></div>
                     <span>Working hard on it, need a couple of minutes ...</span>
                 </span>                
            </td>
</form>
    
            <td>
                <form action="https://demo.pos.host" method="post">
                <input type="hidden" name="action_demo" value="ph_cloner_demo" style="display: none; visibility: hidden; opacity: 0;">
                <button class="wp-block-button__link has-text-color has-background"
                        style="background-color:#BFBFBF;color:#382b73"
                        type="submit">Demo</button>
                </form>
            </td>

            <td>
                <form action="/" method="post">
                <input type="hidden" name="action_cancel" value="ph_cloner_newsite_cancel" style="display: none; visibility: hidden; opacity: 0;">
                <button class="wp-block-button__link has-text-color has-background"
                        style="color:#000;background-color:#BFBFBF;"
                        type="submit">I'm fine.</button>
                </form>
            </td>

        </tr>
    </table>
<style>
.spinner {
}

.spinner > div {
  width: 12px;
  height: 12px;
  background-color: #382b73;

  border-radius: 100%;
  display: inline-block;
  -webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;
  animation: sk-bouncedelay 1.4s infinite ease-in-out both;
}

.spinner .bounce1 {
  -webkit-animation-delay: -0.75s;
  animation-delay: -0.75s;
}

.spinner .bounce2 {
  -webkit-animation-delay: -0.50s;
  animation-delay: -0.50s;
}

.spinner .bounce3 {
  -webkit-animation-delay: -0.25s;
  animation-delay: -0.25s;
}

@-webkit-keyframes sk-bouncedelay {
  0%, 80%, 100% { -webkit-transform: scale(0) }
  40% { -webkit-transform: scale(1.0) }
}

@keyframes sk-bouncedelay {
  0%, 80%, 100% { 
    -webkit-transform: scale(0);
    transform: scale(0);
  } 40% { 
    -webkit-transform: scale(1.0);
    transform: scale(1.0);
  }
}
</style>
    