<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', 'register_auto_refresh_api_jax_create_menu');

require_once plugin_dir_path( __FILE__ ) . '/auto-refresh-api-ajax-lib.php';

function register_auto_refresh_api_jax_create_menu() {
	//create new top-level menu
	add_menu_page('Auto Refresh API Ajax', 'Auto Refresh AA', 'administrator', 'unique_araa_menu_slug', 'auto_refresh_api_ajax_settings_page',plugins_url('/images/araa-loadcircle-16x16.png', __FILE__));
	add_submenu_page('unique_araa_menu_slug', 'Options', 'Options', 'administrator', 'unique_araa_menu_slug', 'register_auto_refresh_api_ajax_settings');
	add_action( 'admin_init', 'register_auto_refresh_api_ajax_settings' );//call register settings function
}


/* options BEGIN */
function register_auto_refresh_api_ajax_settings() {
	//register  settings
	register_setting( 'araa-options', 'araa_url' );
	register_setting( 'araa-options', 'araa_url_proxy' );
	register_setting( 'araa-options', 'araa_refreshtime' );
	register_setting( 'araa-options', 'araa_pageselektor' );
	register_setting( 'araa-options', 'araa_pageselektor_initialhide' );
	register_setting( 'araa-options', 'araa_jsonvalue' );
	register_setting( 'araa-options', 'araa_secvalue' );
	register_setting( 'araa-options', 'araa_placeholder' );
}

function auto_refresh_api_ajax_settings_page() {
  $araa_errorLevelSaveOptions = auto_refresh_api_ajax_save_settings(); # save new settings if needed
?>
<div class="wrap">
  <?php
  global $pagenow;
  $pageGetIn = sanitize_text_field($_GET['page']);
  if ( $pagenow == 'admin.php' && $_GET['page'] == 'unique_araa_menu_slug' ){
    # define tabs for plugin-admin-menu
    $araa_currenttab = 'settings';
    if ( isset ( $_GET['tab'] ) ) {
      $araa_currenttab = sanitize_text_field($_GET['tab']);
    }
    auto_refresh_api_ajax_admin_tabs($araa_currenttab);

$araa_use_wpadminajax = TRUE;
if (1==intval(get_option('araa_url_proxy'))) {
	$araa_use_wpadminajax = FALSE;
}
	?>

<form method="post" action="admin.php?page=unique_araa_menu_slug&tab=<?php echo $araa_currenttab; ?>">
    <table class="form-table">
      <tr valign="top" bgcolor="white">
        <td colspan="2">
    <?php settings_fields( 'araa-options' ); ?>
    <?php do_settings_sections( 'araa-options' ); ?>
<a href="https://json-content-importer.com/auto-refresh-api-ajax/" target="_blank">See example at https://json-content-importer.com/auto-refresh-api-ajax/</a>
        </td>
      </tr>
    <?php
      # save: failed, no changes or changes-saved?
      if ($araa_errorLevelSaveOptions == -6 ) {
        echo '<tr><td colspan="2"><b>Saving of Refreshtime: must be a number</b></td></tr>';
      } else if ($araa_errorLevelSaveOptions<0) {  
        echo '<tr><td colspan="2"><b>Saving failed, errorcode: '.$araa_errorLevelSaveOptions.'</b></td></tr>';
      } else if ($araa_errorLevelSaveOptions==2) {  
         echo '<tr><td colspan="2"><b>Saving successful: Changed values saved</b></td></tr>';
      } else if ($araa_errorLevelSaveOptions==1) {
         echo '<tr><td colspan="2"><b>Nothing changed, nothing saved</b></td></tr>';
      }
      wp_nonce_field( "araa-set-page" );
	    
		$araa_secvalue = get_option('araa_secvalue');
		$araa_secvalue = stripslashes(sanitize_text_field($araa_secvalue));
        if ($araa_secvalue=="") {
			$araa_secvalue = md5(mt_rand());
		}
        $araa_proxyway = intval(get_option('araa_url_proxy'));

		switch ( $araa_currenttab ){
        case 'settings' :
		
    ?>
      <tr valign="top" bgcolor="white">
        <td colspan="2">
           <b>Where is the data: URL with JSON-data</b>
            <?php
              $araa_errormessage = get_option('araa_url');
              if ($araa_errormessage=="") {
                $araa_errormessage = "";
              }
			  esc_url($araa_errormessage, NULL, 'retrieve');
			  $araa_errormessage = stripslashes(htmlentities($araa_errormessage));
			  $url = $araa_errormessage;
           ?>
           <input type="text" name="araa_url" placeholder="URL with JSON" value="<?php echo $araa_errormessage; ?>" size="100">
			<br>
		   	<?php
				$exampleurl = "http://api.json-content-importer.com/extra/auto-refresh-api-ajax/1.php";
				echo "Example: <a href=\"$exampleurl\" target=_blank>$exampleurl</a><br>";
				$jsonFromURLArr = auto_refresh_api_ajax_check_url($araa_errormessage);
			?>

        </td>
      </tr>
	 <?php
		if ($jsonFromURLArr && auto_refresh_api_ajax_urlIsNotFromDomain($url)) {
	 ?>
      <tr valign="top" bgcolor="white">
        <td colspan="2">
           <b>How to proxy foreign URLs?</b>&nbsp;&nbsp;&nbsp;
            <?php
              if (1==$araa_proxyway) {
                $checkedval1 = "checked";
                $checkedval2 = "";
              } else {
                $checkedval2 = "checked";
                $checkedval1 = "";
			  }
		
		$msgrestapitest = "";
		$msgrestadminajax = "";
		if ($araa_use_wpadminajax) {
			$testurl_adminajax = admin_url( 'admin-ajax.php' )."?action=autorefreshapiajax_ajax_function";
			$tmpadminajax = auto_refresh_api_ajax_get_jsonurl($testurl_adminajax);
			$msgrestadminajax = '<a href="'.$testurl_adminajax.'" target="_blank">WP-Admin-Ajax</a>';
			$msgrestapitest = "WP-Rest-API";
			if ($tmpadminajax) {
				$msgrestadminajax .= "  <font color=green>Test OK</font>";
			} else {
				$msgrestadminajax.= "  <font color=red>TEST FAILED</font>";
			}
		} else {
			$testurl_restapi = auto_refresh_api_ajax_buildurlforexternaldomain($url);
			$tmprestapi = auto_refresh_api_ajax_get_jsonurl($testurl_restapi);
			$msgrestapitest = '<a href="'.$testurl_restapi.'" target="_blank">WP-Rest-API</a>';
			$msgrestadminajax = "WP-Admin-Ajax"; 
			if ($tmprestapi) {
				if ("rest_no_route"==@$tmprestapi["code"]) {
					$msgrestapitest .= "  <font color=red>TEST FAILED: no such endpoint</font>";
				} else {
					$msgrestapitest .= "  <font color=green>Test OK</font>";
				}
			} else {
				$msgrestapitest = "<font color=red>TEST FAILED</font>";
			}
		}


           ?>
           <input type="radio" name="araa_url_proxy" value="1" <?php echo $checkedval1; ?>><?php echo $msgrestapitest ?>&nbsp;&nbsp;&nbsp;
           <input type="radio" name="araa_url_proxy" value="2" <?php echo $checkedval2; ?>><?php echo $msgrestadminajax; ?>
        </td>
      </tr>
	 <?php
		if (1==$araa_proxyway) {
	 ?>
      <tr valign="top" bgcolor="white">
        <td colspan="2">
			<b>Secret key:</b>
			To prevent misuse of the WP Rest API-Proxy we need a secret key, which is defined here. The initial key is created by chance - you can leave it as it is. The possibility to change it here, is helpful in case of unlikely problems.
           <input type="text" name="araa_secvalue" placeholder="Secret key" value="<?php echo htmlentities($araa_secvalue); ?>" size="100">
        </td>
      </tr>       
	 <?php
			} else {
				# send secret as hidden field
				echo '<input type="hidden" name="araa_secvalue" value="'.htmlentities($araa_secvalue).'">';
			}
		} else {
			# send proxy-settings and secret as hidden fielda
			echo '<input type="hidden" name="araa_secvalue" value="'.htmlentities($araa_secvalue).'">';
			echo '<input type="hidden" name="araa_url_proxy" value="'.htmlentities($araa_proxyway).'">';
		}
	 ?>
      <tr valign="top">
        <td colspan="2">
           <b>How often should we update: Refreshtime in Milliseconds:</b>
            <?php
              $araa_errormessage = intval(get_option('araa_refreshtime'));
              if (!($araa_errormessage>0)) {
                $araa_errormessage = 10000;
              }
			  $araa_errormessage = intval($araa_errormessage);
           ?><br>
           <input type="text" name="araa_refreshtime" placeholder="if empty: 10.000ms = 10 second" value="<?php echo $araa_errormessage; ?>" size="6">
        </td>
      </tr>
      <tr valign="top" bgcolor="white">
        <td colspan="2">
           <b>What value in the JSON should be displayed and refreshed: JSON-Path to Value</b>
            <?php
              $araa_errormessage = get_option('araa_jsonvalue');
              if ($araa_errormessage=="") {
                $araa_errormessage = "";
              }
			  $araa_errormessage = stripslashes(htmlentities($araa_errormessage));
           ?><br>
           <input type="text" name="araa_jsonvalue" placeholder="URL with JSON" value="<?php echo $araa_errormessage; ?>" size="100">
		   <br>
		   If you use the above Example-URL: <i>items.0.currentdate</i><br>
		   <?php 
			if ($jsonFromURLArr) {
				auto_refresh_api_ajax_check_jsonvalue($araa_errormessage, $jsonFromURLArr);
			}
		   ?>
        </td>
      </tr>
       <tr valign="top">
        <td colspan="2">
           <b>Where on a page the data should be displayed: DOM-Pageselektor</b>
            <?php
              $araa_errormessage = stripslashes(get_option('araa_pageselektor'));
              if ($araa_errormessage=="") {
                $araa_errormessage = "";
              }
			  $araa_errormessage = htmlentities($araa_errormessage);
           ?><br>
           <input type="text" name="araa_pageselektor" placeholder="Pageselektor" value="<?php echo $araa_errormessage; ?>" size="100">
		   <br>
		   Example: <i>h1[class=&quot;page-title&quot;]</i> (HTML on page: <i>&lt;h1 class=&quot;page-title&quot;&gt;data loading&lt;/h1&gt;</i>). If you use <i>body</i>, the whole page might be overwritten.
        </td>
      </tr>
      <tr valign="top" bgcolor="white">
        <td colspan="2">
           <b>Initial Loading: Set another DOM-Pageselektor (e. g. a wrapping div-box) which is hidden until the first successful API-Ajax-Loading.</b>
            <?php
              $araa_errormessage = stripslashes(get_option('araa_pageselektor_initialhide'));
              if ($araa_errormessage=="") {
                $araa_errormessage = "";
              }
			  $araa_errormessage = htmlentities($araa_errormessage);
           ?><br>
           <input type="text" name="araa_pageselektor_initialhide" placeholder="Hidden-Pageselektor" value="<?php echo $araa_errormessage; ?>" size="100">
		   <br>
		   Example: <i>div[id=&quot;boxcontent&quot;]</i> (HTML on page: <i>&lt;div id=&quot;boxcontent&quot;&gt;  &gt;h1&lt;data loading&gt;/h1&lt;   &lt;/div&gt;</i>). If you use <i>body</i>, the whole page might be hidden.
        </td>
      </tr>
	  
	  
      <tr valign="top">
        <td colspan="2">
           <b>Initial Placeholder: The same text you have in the Wordpress-Page/Post which is replaced by the JSON-data. If set Wordpress tries to load a initial value via the server (later Ajax and the Client updates the this value)</b>
            <?php
              $araa_errormessage = stripslashes(get_option('araa_placeholder'));
              if ($araa_errormessage=="") {
                $araa_errormessage = "";
              }
			  $araa_errormessage = htmlentities($araa_errormessage);
           ?><br>
           <input type="text" name="araa_placeholder" placeholder="Hidden-Pageselektor" value="<?php echo $araa_errormessage; ?>" size="100">
		   <br>
		   Example: "Wait please: loading data is in progress"
        </td>
      </tr>
	  
	  
	  
	  
     <tr valign="top"><td colspan="2">
    <input type="hidden" name="araa-settings-submit" value="savesettings" />
    <input type="submit" name="Submit"  class="button-primary" value="Update Settings" />
        </td></tr>
<?php
  break;
}
}
?>
    </table>
</form>
</div>
<?php
}
/* options END */


/* define tabs for plugin-admin-menu BEGIN*/
function auto_refresh_api_ajax_admin_tabs( $current = 'settings' ) {
    $tabs = array(
          'settings' => 'Settings of Auto Refresh API AJAX',
          );
    echo '<h2 class="nav-tab-wrapper">';
    foreach( $tabs as $tab => $name ){
        $class = ( $tab == $current ) ? ' nav-tab-active' : '';
        echo "<a class='nav-tab$class' href='?page=unique_araa_menu_slug&tab=$tab'>$name</a>";

    }
    echo '</h2>';
}
/* define tabs for plugin-admin-menu END*/

/* save settings BEGIN*/
function auto_refresh_api_ajax_save_check_value($val, $changefound) {
	$araa_areThereChanges = $changefound;
	$inputValPost = trim(strip_tags(@$_POST[$val])); 
	if (!($inputValPost == get_option($val))) {
		update_option( $val, $inputValPost );
		$araa_areThereChanges = TRUE;
	}
	return $araa_areThereChanges;
}
/* save settings END*/


/* save settings BEGIN*/
function auto_refresh_api_ajax_save_settings() {
  if (!isset($_POST["araa-settings-submit"]) || ($_POST["araa-settings-submit"] != 'savesettings') ) {
    return 0;
  }
  isset($_REQUEST['_wpnonce']) ? $nonce = $_REQUEST['_wpnonce'] : $nonce = NULL;
  $nonceCheck = wp_verify_nonce( $nonce, "araa-set-page" );
  if (!$nonceCheck) {
    return -2;
  }
   global $pagenow;
   $page_get_in = sanitize_key($_GET['page']);
   $tab_get_in = sanitize_key($_GET['tab']);
   if ( $pagenow == 'admin.php' && $page_get_in == 'unique_araa_menu_slug' ){
      if ( isset (  $tab_get_in ) ) {
        $araa_tab = $tab_get_in;
      } else {
        $araa_tab = 'syntax';
      }
      $araa_areThereChanges = FALSE;
      switch ( $araa_tab ){
      case 'settings' :
		$araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_url", $araa_areThereChanges);
		$araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_url_proxy", $araa_areThereChanges);
		$araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_pageselektor", $araa_areThereChanges);
		$araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_pageselektor_initialhide", $araa_areThereChanges);
        $araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_jsonvalue", $araa_areThereChanges);
        $araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_secvalue", $araa_areThereChanges);
        $araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_placeholder", $araa_areThereChanges);
        if (!is_numeric($_POST['araa_refreshtime'] )) {
          return -6;
        } else {
          $araa_areThereChanges = auto_refresh_api_ajax_save_check_value("araa_refreshtime", $araa_areThereChanges);
        }
        if ($araa_areThereChanges) {
          return 2;
        } else {
          return 1;
        }
      break;
      }
   }
   return -3;
}

function auto_refresh_api_ajax_check_jsonvalue($jsonpath, $jsonArr) {
	$pathArr = explode(".", $jsonpath);
	$tmpArr = "";
	$j = 1;
	foreach ($pathArr as $i) {
		$tmpArr .= $i.".";
		echo "check: <b>".substr($tmpArr, 0, strlen($tmpArr)-1)."</b><br>";
		$jsonArr = @$jsonArr[$i];
		$res = @json_encode($jsonArr);
		if ($res=="null") {
			echo "<font color=red>Value NOT found in JSON, check JSON-path to value!</font><br>";
		} else {
			if ($j==count($pathArr)) {
				echo "<b>This value will be updated:</b><br>";
			}
			echo htmlentities(print_r($res, TRUE));
		}
		$j++;
		echo "<hr>";
	}
}

function auto_refresh_api_ajax_check_url($url) {
	if (""==$url) {
		echo "<font color=red>URL not defined</font><br>";
		return FALSE; # no action
	}
	$usedurl = $url;
	$getjson = auto_refresh_api_ajax_get_jsonurl($usedurl);
	#$jsonOfUrl = json_decode($getjson, TRUE);
	if (!$getjson) {
		echo "<font color=red>ERROR: </font><a href=$usedurl target=_blank>URL contains invalid JSON</a><br>";
		return FALSE;
	}
	echo "<font color=green>Ok: </font><a href=$usedurl target=_blank>URL gives valid JSON</a><br>";
	if (auto_refresh_api_ajax_urlIsNotFromDomain($url)) {
		echo 'This URL is from another domain, therefore the Ajax-request must be proxied: <br>
			Retrieving an URL with Javascript-Ajax is based on the <a href="https://en.wikipedia.org/wiki/Same-origin_policy" target=_blank>Same-origin policy</a>.
			That we can use foreign domains, this plugin has two ways to solve that with an http-proxy (besides that <a href="https://en.wikipedia.org/wiki/Cross-origin_resource_sharing" target=_blank>CORS</a> might be an option by setting the http-header).
			One is via "WP-Rest-API", the other via "WP-Admin-Ajax" (default). Depending on your Wordpress-Installation both, one or neither is working. Maybe also other plugins define this.<br>';
	} 
	return $getjson;
}
?>