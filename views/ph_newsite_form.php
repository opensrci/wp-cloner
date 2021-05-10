<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

defined( 'ABSPATH' ) || exit;
$user = wp_get_current_user()->user_login;

?>

<h4>Hello <?php echo $user ?>, don't Have a store yet? </h4>

<form action="" method="post" name="ph_newsite_form" >
    <table>
        <tr style = 'align-center'  class="form-field" >
            <td>
                <label>Choose your store name: </label>
                <input id ="ph_cloner_newsite_input" name="ph_cloner_newsite_input" type="text" placeholder="Type your store name" required>
            </td>
        </tr>
    <br/>
    <br/>
    <tr>
            <td>
                <input type="hidden" name="action" value="ph_cloner_newsite_submit" style="display: none; visibility: hidden; opacity: 0;">
                <button type="submit">Create</button><div class="" id="ph_cloner_newsite_valid_result"/>
            </td>
</form>
<form action="/" method="post">
            
            <td>
                <input type="hidden" name="action_cancel" value="ph_cloner_newsite_cancel" style="display: none; visibility: hidden; opacity: 0;">
                <button type="submit">I'm fine.</button>
            </td>
        </tr>
    </table>
</form>
    