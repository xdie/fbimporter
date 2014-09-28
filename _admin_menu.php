<?php

/*
 * Avisamos a wordpress de nuestra pagina
 */
add_action('admin_menu', 'fpf_add_admin_page', 99);
function fpf_add_admin_page()
{
	global $fpf_name; 
    add_options_page("$fpf_name Options", 'FB Genus Album Importer' . (defined('FPF_ADDON')?"+":""), 'administrator', "fb-photo-fetcher", 'fpf_admin_page');
}


/**
  * 
  */
add_filter('plugin_action_links', 'fpf_add_plugin_links', 10, 2);
function fpf_add_plugin_links($links, $file)
{
    if( dirname(plugin_basename( __FILE__ )) == dirname($file) )
        $links[] = '<a href="options-general.php?page=' . "fb-photo-fetcher" .'">' . __('Settings','sitemap') . '</a>';
    return $links;
}

/**
 * Styles
 */
add_action('admin_head', 'fpf_admin_styles');
function fpf_admin_styles()
{
    echo '<style type="text/css">'.
            '.fpf-admin_warning     {background-color: #FFEBE8; border:1px solid #C00; padding:0 .6em; margin:10px 0 15px; -khtml-border-radius:3px; -webkit-border-radius:3px; border-radius:3px;}'.
            '.fpf-admin_wrapper     {clear:both; background-color:#FFFEEB; border:1px solid #CCC; padding:0 8px; }'.
            '.fpf-admin_tabs        {width:100%; clear:both; float:left; margin:0 0 -0.1em 0; padding:0;}'.
            '.fpf-admin_tabs li     {list-style:none; float:left; margin:0; padding:0.2em 0.5em 0.2em 0.5em; }'.
            '.fpf-admin_tab_selected{background-color:#FFFEEB; border-left:1px solid #CCC; border-right:1px solid #CCC; border-top:1px solid #CCC;}'.
         '</style>';
}

/**
  * Output the plugin's Admin Page 
  */
function fpf_admin_page()
{
//    require_once('../../../wp-config.php');
	global $fpf_name, $fpf_version, $fpf_identifier, $fpf_homepage;
    global $fpf_opt_access_token, $fpf_opt_token_expiration, $fpf_opt_last_uid_search;
    global $fpf_shown_tab;
    global $wpdb; // Para manejar la base de datos
    
    $fpf_shown_tab   = 2;
    $allTabsClass    = "fpf_admin_tab";
    $allTabBtnsClass = "fpf_admin_tab_btn";
    $tab1Id          = "fpf_admin_fbsetup";
    $tab2Id          = "fpf_admin_utils";
    $tab3Id          = "fpf_admin_addon";

    
    ?><div class="wrap">
      <h2><?php echo $fpf_name ?></h2>
    <?php
    
    //Check $_POST for what we're doing, and update any necessary options
    if( isset($_POST[$fpf_opt_access_token]) )  //User connected a facebook session (login+save)
    {
        //We're saving a new access token.  Let's use it to try and fetch the userID, to verify that it's valid before saving.
        //Also, store the expiration timestamp.  We need to store this as the debug_token endpoint is only available to the current
        //app's developer (so a regular user can't get it again - only when the token is first assigned).
        $user = fpf_get("https://graph.facebook.com/me?access_token=".$_POST[$fpf_opt_access_token]."&fields=name,id");
        if( isset($user->id) && !isset($user->error) )
        {
            update_option( $fpf_opt_access_token, $_POST[$fpf_opt_access_token] );
            update_option( $fpf_opt_token_expiration, time() + $_POST[$fpf_opt_token_expiration] );
            ?><div class="updated"><p><strong><?php echo 'Facebook Sesion Iniciada (Nombre: ' . $user->name . ', ID: ' . $user->id . ')' ?></strong></p></div><?php
        }
        else
        {
            update_option( $fpf_opt_access_token, 0 );
            update_option( $fpf_opt_token_expiration, 0 );
            ?><div class="updated"><p><strong><?php echo 'Error: Access Token no valido.  Respuesta: ' . (isset($user->error->message)?$user->error->message:"Desconocido");?></strong></p></div><?php
        }    
    }
    else if( isset($_POST['delete_token']) ) //User wants to remove the current access token.
    {                                        //No need to output an 'updated' message, because the lack of a token will be detected and shown as an error below.
        update_option( $fpf_opt_access_token, 0 );
    }
    else if( isset($_POST[$fpf_opt_last_uid_search]) )    //User clicked "Search," which saves 'last searched uid'
    {
        update_option( $fpf_opt_last_uid_search, $_POST[ $fpf_opt_last_uid_search ] );
        
        
        
    }
	else 												//Allow optional addons to perform actions
	{
		do_action('fpf_extra_panel_actions', $_POST);
	}
    
    //Whenever the admin panel is loaded, verify that the access_token is valid by trying to fetch the name and id.
    //If not, clear it from the database, forcing the user to (re-)validate.
    $access_token = get_option($fpf_opt_access_token);
    $user = fpf_get("https://graph.facebook.com/me?access_token=".$access_token."&fields=name,id");
    if(!$access_token)
    {
        ?><div class="error"><p><strong><?php echo 'Este plugin no tiene un access token valido, por favor asegurese de que inicie sesion en Facebook luego Autorizar con el boton de mas abajo para obtener un nuevo Token. '?></strong></p></div><?php        
    }
    else if(!$user)
    {
        ?><div class="error"><p><strong><?php echo 'Ocurrio un error al validar el  Token por favor Re-Autorizar'?></strong></p></div><?php
        update_option($fpf_opt_access_token, 0);
    }
    else if(isset($user->error))
    {
        ?><div class="error"><p><strong><?php echo $user->error->message . "<br /><br />Por favor Re-Autorizar"?></strong></p></div><?php
        update_option($fpf_opt_access_token, 0);
    }
    
    //Re-get the access_token, in case it was cleared by an error above)
    $access_token = get_option($fpf_opt_access_token);
    if(!$access_token) $fpf_shown_tab = 1;
    ?>

    <!-- Tab Navigation -->
    <script type="text/javascript">
        function fpf_swap_tabs(show_tab_id) 
        {
            //Hide all the tabs, then show just the one specified
            jQuery(".<?php echo $allTabsClass ?>").hide();
            jQuery("#" + show_tab_id).show();

            //Unhighlight all the tab buttons, then highlight just the one specified
            jQuery(".<?php echo $allTabBtnsClass?>").attr("class", "<?php echo $allTabBtnsClass?>");
            jQuery("#" + show_tab_id + "_btn").addClass("fpf-admin_tab_selected");
        }
    </script>  
    
    <div>     
        <ul class="fpf-admin_tabs">
           <li id="<?php echo $tab1Id?>_btn" class="<?php echo $allTabBtnsClass?> <?php echo ($fpf_shown_tab==1?"fpf-admin_tab_selected":"")?>"><a href="javascript:void(0);" onclick="fpf_swap_tabs('<?php echo $tab1Id?>');">Facebook Config</a></li>
           
            
           <?php if (!get_option($fpf_opt_access_token) == 0): ?>
           <li id="<?php echo $tab2Id?>_btn" class="<?php echo $allTabBtnsClass?> <?php echo ($fpf_shown_tab==2?"fpf-admin_tab_selected":"")?>"><a id="alink" href="javascript:void(0);" onclick="fpf_swap_tabs('<?php echo $tab2Id?>')";>Utilidades</a></li>    
           <?php endif; ?>
         
        </ul>
    </div>
    
    <!--Start Main panel content-->
    <div class="fpf-admin_wrapper">
        <div class="<?php echo $allTabsClass ?>" id="<?php echo $tab1Id?>" style="display:<?php echo ($fpf_shown_tab==1?"block":"none")?>">
            <h3>Bienvenidos!</h3>
Este plugin permite Importar Albums desde cualquier cuenta de Facebook.<br /><br />
            Para empezar deben que conectarse a la cuenta con el boton que aparece mas abajo "Iniciar Facebook" lo cual obtendremos el identificador de usuario y un Token de Autorizacion<br /><br />

            Un ejemplo del ID es (1234567890123456789) cual identifica al album que quieres obtener. Para ver las lista de albums debes iniciar sesion.<br /><br />    
<br />
            <hr />
            
            <?php //SECTION - Facebook Authorization. See notes at the bottom of this file. ?>
            <h3>Facebook Autorizacion</h3>
              
 <script>  
       	      
// API DE FACEBOOK Javascript SDK ;)            	      
           	      
         // This is called with the results from from FB.getLoginStatus().
  function statusChangeCallback(response) {
    console.log('statusChangeCallback');
    console.log(response);
    // The response object is returned with a status field that lets the
    // app know the current login status of the person.
    // Full docs on the response object can be found in the documentation
    // for FB.getLoginStatus().
    if (response.status === 'connected') {
      // Logged into your app and Facebook.
  
     testAPI();
    } else if (response.status === 'not_authorized') {
      // The person is logged into Facebook, but not your app.
      document.getElementById('status').innerHTML = 'Por favor' +
        ' iniciar permisos de aplicacion';
    } else {
      // The person is not logged into Facebook, so we're not sure if
      // they are logged into this app or not.
      document.getElementById('status').innerHTML = 'Por favor' +
        ' iniciar session.';
    }
  }

  // This function is called when someone finishes with the Login
  // Button.  See the onlogin handler attached to it in the sample
  // code below.
  function checkLoginState() {
    FB.getLoginStatus(function(response) {
      statusChangeCallback(response);
    });
  }

  window.fbAsyncInit = function() {
  FB.init({
    appId      :  '686978708060166', // Solo con tu APP ID'354182048071312', 
    cookie     : true,  // enable cookies to allow the server to access 
                        // the session
    xfbml      : true,  // parse social plugins on this page
    version    : 'v2.1' // use version 2.1
  });


 
  FB.getLoginStatus(function(response) {
    statusChangeCallback(response);
  });
 

  };

  // Load the SDK asynchronously
  (function(d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = "//connect.facebook.net/en_EN/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));


  // Here we run a very simple test of the Graph API after login is
  // successful.  See statusChangeCallback() for when this call is made.
  function testAPI() {
  console.log('Bienvenido levantando informacion.... ');
    FB.api('/me', function(response) {
      console.log('Successful login for: ' + response.name);
      document.getElementById('status').innerHTML =
        'Gracias por loguearte, ' + response.name + ' ! click en autorizar para un nuevo token! o talvez quieras <a href="javascript:logout()"> Cerrar sesion <a/>';
	console.log(response);
    });
    FB.login(function(response) {
   if (response.authResponse) {
     var access_token =   FB.getAuthResponse()['accessToken'];
     var expiresin = FB.getAuthResponse()['expiresIn']
     var userID = FB.getAuthResponse()['userID']
     console.log(response) ;
     console.log('Access Token = '+ access_token);
    
    
    jQuery('#<?php echo $fpf_opt_access_token?>').val(access_token);
    jQuery('#<?php echo $fpf_opt_token_expiration?>').val(expiresin);
     //document.getElementById("access_token").value = access_token;
     //document.getElementById("userid").value = userID;
               	        
	    document.getElementById('authb').disabled = false;
	          
               
  
   } else {
     console.log('User cancelled login or did not fully authorize.');
   }
 }, {scope: ''});
 
     
  }     	        

function logout() {
	
	FB.logout();
	document.getElementById('status').innerHTML = 'Por favor Iniciar Session.';
	document.getElementById('close_all').submit();
    document.getElementById('authb').disabled = true;     
  
  // jQuery('#close_all').submit();
   
   

}

</script>
            		     	
<fb:login-button scope="public_profile,email,user_photos,user_about_me,manage_pages" onlogin="checkLoginState();">
</fb:login-button>

<div id="status">
</div>
      		
            			
            	<form method="post" id="graph_token_submit" action="">
                    <input type="hidden" id="<?php echo $fpf_opt_access_token?>" name="<?php echo $fpf_opt_access_token?>" value="0" />
                    <input type="hidden" id="<?php echo $fpf_opt_token_expiration?>" name="<?php echo $fpf_opt_token_expiration?>" value="0" />
                    <br>
                     
                    <?php if(!$access_token): ?> 
                                                           
                    <input id="authb" name="authb" type="submit" value="Autorizar" disabled="true"><br>
                                        
      				<?php endif; ?>
                </form>
                  <!--Deauthorize button-->
            <?php if($access_token): ?>
                <form method="post" id="close_all" action="">
                    <input type="hidden" id="delete_token" name="delete_token" value="0" />
                    <input type="submit" id="desbt" class="button-secondary" style="width:127px;" value="Des-Autorizar" />
                </form>
            <?php endif; ?>
                <?php if (is_ssl() && !$access_token): ?>
                    <br clear="all" />
                    <div class="fpf-admin_warning" style="width:70%;">
                        <b>Note:</b> Your Wordpress admin appears to be running over SSL.  Unfortunately, in order to comply with Facebook's security rules, the FPF authorization may only be performed from my server (since I'm the owner of the app) - thus the button is loaded from my server in an iFrame.  Normally this would appear above, but some recent browser updates have begun to silently block "mixed content" pages from loading.  If you don't see a login button, you'll need temporarily enable mixed content (just on this page).  Not to worry, all transactions with Facebook are still encrypted and secure - it's only my simple "wrapper" script that will be sent over http:<br/>
                        <ul style="list-style-type:disc;list-style-position:inside;">
                            <li>In IE10, it will prompt you to "Show all content" at the bottom of the window when you first load this page.  All you need to do is click that button.</li>
                            <li>In Firefox, click the shield to the left of the URL and select "disable protection on this page" from the drop-down.</li>
                            <li>In Chrome, there's a similar shield to the right of the URL that lets you "load unsafe content."</li>
                        </ul>
                        (If you're reluctant to enable these options, please keep in mind that the vast majority of Wordpress installations do not run over SSL - and thus never see this warning.  All you're doing is giving the browser permission load my iFrame over http, even though the rest of the page is https - <i><u>this was the default behavior for all major browsers until mid-2013</u></i> (i.e. see <a target="link1" href="http://stackoverflow.com/questions/18251128/why-am-i-suddenly-getting-a-blocked-loading-mixed-active-content-issue-in-fire">here </a>for FF &amp; <a target="link2" href="http://productforums.google.com/forum/#!topic/chrome/OrwppKWbKnc">here</a> for Chrome).  And as the Facebook logins themselves <i>always</i> run over SSL, there's really nothing being transmitted in an unsafe way.  For more information on mixed content, please see <a target="link3" href="https://developer.mozilla.org/en-US/docs/Security/MixedContent">here</a>.)
                    </div>
                <?php endif; ?>
                
                <?php if($access_token): ?>
                    <span style="float:left;"><small>(Expira en <?php echo human_time_diff(get_option($fpf_opt_token_expiration))?>)</small></span>
                <?php else: ?>
                    <br clear="all"/><br/><small><i>(Nota: Cuando presionamos el boton Login automaticamente se despliega un dialogo para authorizar la aplicacion, asegurese que la aplicacion tenga permisos como manage_pages en la configuracion o que el usuario sea el creador de la aplicacion asiganada.</i></small>
                <?php endif; ?>
                <br clear="all" />
                        
            <hr />
            <?php
            //Output the token expiration, for testing.
            //NOTE: This will only work for MY user account (they only allow the developer of an app to debug that app's access tokens)
            //See https://developers.facebook.com/docs/howtos/login/debugging-access-tokens
            echo "<small><strong>Debug</strong><br />";
            if($access_token)
            {
                echo "Token: $access_token<br />";
                echo "Tiempo que Expira aprox: " . human_time_diff(get_option($fpf_opt_token_expiration)) . "<br />";
                $tokenResponse = fpf_get("https://graph.facebook.com/debug_token?input_token=".get_option($fpf_opt_access_token).'&access_token='.get_option($fpf_opt_access_token));
                if(isset($tokenResponse->data->expires_at))
                {
                    $expiresMin = (int)(($tokenResponse->data->expires_at - time())/60);
                    $expiresH = (int)($expiresMin/60);
                    $expiresMin -= $expiresH*60;
                    echo "Expira en: $expiresH" . "h $expiresMin" . "m";
                }
                else
                    echo "Expira en: No se sabe";
            }
            else
                echo "Token: Ninguno";
            echo "</small>";
            ?>
        </div><!--end tab-->

        <div class="<?php echo $allTabsClass ?>" id="<?php echo $tab2Id?>" style="display:<?php echo ($fpf_shown_tab==2?"block":"none")?>">    
           <?php //SECTION - Search for albums?>
           <h3>Administracion de Albums</h3>
           
           <form id="listalbums" name="listalbums" method="post" action="">
              Selecciona el perfil de usuario o pagina para obtener los albums.
              <br /><br />
               Tu ID de Perfil es: <b><?php echo $user->id?></b>.<br /><div></br>
              <?php
		               $response = fpf_get("https://graph.facebook.com/me/accounts?access_token=$access_token&fields=name,id");
		               $pages = $response->data;
              //print_r($pages);
		              if (!$pages) {
		              		echo '<div class="error"><p><strong>Error al acceder! seguramente la aplicacion no tiene permiso para acceder a las paginas del usuario</strong></p></div>';
		              }
           
              		echo '<label>Tus Paginas son: </label><select id="pagesel" onchange="setID()">';
              	 	echo '<option id="pageid" name="pageid" value="'.$user->id.'">'.$user->name.'</option>';
              		foreach($pages as $page){
		  		
						echo '<option name="pageid" value="'.$page->id.'">'.$page->name.'</option>';
				
					}
		
					echo '</select>';
              
              
              
         ?>
           <script type="text/javascript">
            
           // Evento change del combo
           function setID() {
           
              var newid = document.getElementById("pagesel").value;
              document.getElementById("albumid").value = newid;

           }
           </script>   
             <input type="text" id="albumid" name="<?php echo $fpf_opt_last_uid_search?>" value="<?php echo $user->id;?>" size="20" readonly>
               <input type="submit" class="button-secondary" name="Submit" value="Obtener Albums" />

           </form>
    </div>
           <?php
           // Cuando le damos obtener albums
           add_option($fpf_opt_last_uid_search, $user->id);
           if( isset($_POST[ $fpf_opt_last_uid_search ]) )
           {
               //Obtenemos el nombre y el ID
               $search_uid = get_option($fpf_opt_last_uid_search);
               $response = fpf_get("https://graph.facebook.com/$search_uid?access_token=$access_token&fields=name");
               $search_name = $response->name;
               if(!$search_name) $search_name = "(Unknown User)";
               
               //Lista de albums
               $response = fpf_get("https://graph.facebook.com/$search_uid/albums?access_token=$access_token&limit=999&fields=id,link,name,count,cover_photo");
               $albums = $response->data;
           // print_r($albums);
               echo "<h3 class='hndle' style='padding:6px;'><span>Albums de $search_name:</span></h3>";  
               
           }else {
           echo '<div><p><strong>Obteniendo Albums del Perfil...</strong></p></div>';
           // esto es javascript para levantar los albums automaticamente
            echo '<script type="text/javascript">document.getElementById("listalbums").submit();</script>';
           }
           
/*****************************************/
// Caja de nuestro Addon :)
// by, Rodrigo Gliksberg xdieamd@gmail.com
/*****************************************/



echo "<div class='postbox' style='margin-top:5px; width:77%;'>";
echo "<h3 class='hndle' style='padding:6px;'><span>Importar Album a NextGen Gallery (Beta) </span></h3>";


// Aca sacamos el id de cada cover_photo foto portada del album
foreach($albums as $album){

	$response2 = fpf_get("https://graph.facebook.com/$album->cover_photo?access_token=$access_token&fields=picture,link,source");

	// Box donde mostras foto y titulo del album
	echo '<div style="display: inline-block;border:1px solid #333;background: #fff;margin:10px;text-align: center;width:150px;word-wrap: break-word;">';
	echo '<a href="'.$response2->link.'" target="_blank"><img src="'.$response2->picture.'" align="top"></a></img></br><i>'.$album->name.' ('.$album->count.')</i></div>';
}

// Formulario para importar 

echo '</br></br></br><form name="getphotos" method="post" action=""><input type="hidden" name="getphotos" value="Y">';
echo '<input type="hidden" name="'.$fpf_opt_last_uid_search.'" value="'.get_option($fpf_opt_last_uid_search).'">';

echo '<span style="margin: 2px 2px 10px 5px;"><p style="text-align: left;padding:6px;"><label><b>Seleccionar Album: </b></label><select name="albumsel"  style="width: 222px;">';
foreach($albums as $album){
	echo '<option name="'.$album->name.'" value="'.$album->id.'-'.$album->name.'">'.$album->name.'</option>';
}
echo '</select>';
             
echo '<input type="submit" class="button-secondary" name="Submit" value="Importar Album" /></p></span></form>';

// Si elegio importar algun album 

if( isset($_POST[ 'getphotos' ]) ) {

$album_sel = $_POST['albumsel'];
$albumarr = explode("-", $album_sel);
$albumid = $albumarr[0];
$albumname = $albumarr[1];

// Le damos una rapada al nombre para remplazar espacio con - y ponerlo en lowercase

$newalbum = sanitize_title($albumname);
//var_dump($newalbum);
//print_r($_POST);
$newdir = dirname(getcwd())."/wp-content/gallery/".$newalbum;




if(!mkdir($newdir, 0777)) {
		echo 'Fallo al crear la carpeta... Ya Existe!';
		echo '<div class="error"><p><strong>El Album '.$newalbum.' no se puede importar porque ya existe .</strong></p></div>';
	} else {

// Creamos la galeria en Nextgen Gallery

$table_name_gall = $wpdb->prefix . "ngg_gallery";
$table_name_pics = $wpdb->prefix . "ngg_pictures";

$wpdb->insert( $table_name_gall, array( 'name' => $newalbum, 'slug' => $newalbum, 'path' => 'wp-content/gallery/'.$newalbum));

// Funcion para obtener las fotos de cada album
$response = fpf_get("https://graph.facebook.com/$albumid/photos?access_token=$access_token&fields=source,message,picture");
$photos = $response->data;




// Obtenemos el GalleryID para poder incrustarle las imagenes
$sql = "SELECT gid FROM $table_name_gall WHERE name='$newalbum'";
$resultaid = $wpdb->get_results($sql) or die("Error al obtener la galeria:".mysql_error());

$gid = $resultaid[0]->gid;

foreach ($photos as $photo){

	$imagen = file_get_contents($photo->source);

		if ($imagen) {
		    //Copiamos la imagen al directorio
		    file_put_contents($newdir."/".$photo->id.".jpg",$imagen);
		    // Insertamos la imagen a NGG segun su galleryid
		    $wpdb->insert( $table_name_pics, array( 'image_slug' => $photo->id, 'galleryid' => $gid, 'filename' => $photo->id.".jpg"));     
		}else {
			echo " Error al copiar la imagen:".$photo->id;
		   // var_dump($imagen);
    
}

}

		echo '<div class="updated"><p><strong> Album "'.$albumname.'" con el slug '.$newalbum.' y '.count($photos).' fotos, fue importado con Exito! <a href="./admin.php?page=nggallery-manage-gallery"> Ir a la Galeria NGG</a></strong></p></div>';
		
		echo '<div class="updated"><p><strong>Para usar, poner directamente el shortcode [nggallery id='.$gid.'] en tu pagina o post</strong></p></div>';
}

}
		echo '<hr></div><br><p style="text-align: right;" ><i>Recoded by, Rodrigo Gliksberg (xdieamd@gmail.com)</p></i>';
}



 ?>
    

          
        