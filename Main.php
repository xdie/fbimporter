<?php
/*
 * Plugin Name: Facebook Genus Importer
 * Description: Permite importar cualquier album desde un perfil o pagina de Facebook
 * Author: Rodrigo Gliksberg
 * Version: 2.2.22
 * Author URI: http://www.microbius.com.ar/
 * Plugin URI: http://www.microbius.com.ar/devel
 */

/*
 * Copyright 2010-2014 Rodrigo Gliksberg
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

//Non-database vars
global $fpf_name, $fpf_version, $fpf_identifier, $fpf_homepage;
$fpf_name       = "Facebook Importador de Albums";
$fpf_version    = "2.1.16";
$fpf_identifier = "FBGallery2";
$fpf_homepage   = "http://www.microbius.com.ar/";

//Vars stored in the database
global $fpf_opt_access_token, $fpf_opt_token_expiration, $fpf_opt_last_uid_search;
$fpf_opt_access_token    = 'fpf-graph-token';        //The new Graph-style access_token
$fpf_opt_token_expiration= 'fpf-token-expiration';   //The expiration timestamp of the token
$fpf_opt_last_uid_search = 'fpf-last-search-uid';    //The last userID whose albums we searched for



//Script for creating the admin page
require_once('_admin_menu.php');



//Enqueue stylesheets and lightbox
add_action('wp_enqueue_scripts', 'fpf_enqueue_headerstuff');
function fpf_enqueue_headerstuff()
{
    global $fpf_version;
    wp_enqueue_style('fpf', plugins_url(dirname(plugin_basename(__FILE__))).'/style.css', array(), $fpf_version );
    
  
}


//A wrapper function I use to pull data from the Facebook Graph API
function fpf_get($url)
{
    //Try to access the URL
    $result = wp_remote_get($url, array( 'sslverify' => false ));
    
    //In some rare situations, Wordpress may unexpectedly return WP_Error.  If so, I'll create a Facebook-style error object
    //so my Facebook-style error handling will pick it up without special cases everywhere.
    if(is_wp_error($result))
    {
        $result->error->message = "wp_remote_get() failed!";
        if( method_exists($result, 'get_error_message')) $result->error->message .= " Message: " . $result->get_error_message();
        return $result;
    }
    
    //Otherwise, we're OK - decode the JSON text provided by Facebook into a PHP object.
    return json_decode($result['body']);
}

?>