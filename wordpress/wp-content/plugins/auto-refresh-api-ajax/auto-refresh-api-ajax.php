<?php
/*
Plugin Name: Auto Refresh API Ajax
Plugin URI: https://json-content-importer.com/auto-refresh-api-ajax/
Description: Load JSON-data via Ajax, display it and reload it
Version: 1.2.1
Author: Bernhard Kux
Author URI: https://json-content-importer.com
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
/* block direct requests */
if ( !function_exists( 'add_action' ) ) {
	echo 'Hello, this is a plugin: You must not call me directly.';
	exit;
}
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AUTO_REFRESH_API_AJAX_VERSION', '1.1.0' );  



#load lib
require_once plugin_dir_path( __FILE__ ) . '/auto-refresh-api-ajax-lib.php';

# url with json from plugin options
$araa_url = trim(get_option('araa_url'));
$araa_url_input = $araa_url;

# in case we have to handle a foreign url: wp-ajax or wp-rest-API?
# default: wp-ajax
$araa_use_wpadminajax = TRUE;
if (1==intval(get_option('araa_url_proxy'))) {
	$araa_use_wpadminajax = FALSE;
}

if (auto_refresh_api_ajax_urlIsNotFromDomain($araa_url)) {
	# url is not from same origin
	if ($araa_use_wpadminajax) {
		# request via admin-ajax.php: define function to retrieve URL
		add_action( 'wp_ajax_autorefreshapiajax_ajax_function', 'autorefreshapiajax_ajax_function' );
		add_action( 'wp_ajax_nopriv_autorefreshapiajax_ajax_function', 'autorefreshapiajax_ajax_function' );
	} else {
		# request via wp rest api: create endpoint
		add_action( 'rest_api_init', function () {
			register_rest_route( 'araa/v1', '/geturl/', array(
				'methods' => 'GET',
				'callback' => 'auto_refreh_ajax_api_geturl_wpjson',
			) );
		} );	
	}
	add_action( 'wp_enqueue_scripts', 'auto_refresh_api_ajax_wprest_enqueue_scripts');
}

require_once plugin_dir_path( __FILE__ ) . '/options.php';

function auto_refresh_api_ajax_wprest_enqueue_scripts() {
	global $araa_use_wpadminajax, $araa_url_input;
	if ($araa_use_wpadminajax) {
		$araa_url = admin_url( 'admin-ajax.php' );
		//wp_localize_script( 'jquery', $araa_url, admin_url( 'admin-ajax.php' ) ); 
	} else {
		$araa_url = trim(get_option('araa_url'));
		esc_url($araa_url, NULL, 'retrieve');
		$araa_url = stripslashes(htmlentities($araa_url)); 	
		$araa_url = auto_refresh_api_ajax_buildurlforexternaldomain($araa_url);
	}
	if (""==$araa_url) {
		return TRUE;
	}

	$araa_refreshtime = get_option('araa_refreshtime');
	if (""==$araa_refreshtime) {
		$araa_refreshtime = 10000;
	}

	$araa_pageselektor = get_option('araa_pageselektor');
	if (""==$araa_pageselektor) {
		return TRUE;
	}
	
	$araa_pageselektor_initialhide = get_option('araa_pageselektor_initialhide');

	$araa_jsonvalue = get_option('araa_jsonvalue');
	if (""==$araa_jsonvalue) {
		return TRUE;
	}

## initial value
$initalvalueresponse = @wp_remote_get($araa_url_input);
if ( is_array( $initalvalueresponse ) ) {
	$initbody = @$initalvalueresponse['body'];
	$initalvaluerequestArr = @json_decode($initbody, TRUE);
	$initval = @$initalvaluerequestArr[$araa_jsonvalue];
	$initplaceholder = get_option('araa_placeholder');

	if (""!=$initval && ""!=$initplaceholder) {
		add_action( 'wp_head', function() use ( $initval, $initplaceholder ) {
			ob_start(function($buffer) use ($initval, $initplaceholder) {
				return preg_replace('/'.$initplaceholder.'/i', $initval, $buffer); 
				});		
			}, 11 
		);
		
		function buffer_end() { ob_end_flush(); }
		add_action('wp_footer', 'buffer_end');
	}
}

	wp_enqueue_script('auto_refresh_api_ajax', plugins_url( 'js/auto_refresh_api_ajax.js', __FILE__ ), array('jquery'), AUTO_REFRESH_API_AJAX_VERSION);
	$auto_refresh_api_ajax_param_array = array(
		'araaurl' => $araa_url,
		'araarefresh' => $araa_refreshtime,
		'araapageselektor' => stripslashes($araa_pageselektor),
		'araapageselektorinitialhide' => stripslashes($araa_pageselektor_initialhide),
		'araajsonvalue' => $araa_jsonvalue,
	);
	wp_localize_script( 'auto_refresh_api_ajax', 'autorefreshapiajaxparam', $auto_refresh_api_ajax_param_array );

}

# WP-REST API 
function auto_refreh_ajax_api_geturl_wpjson( $data ) {
	$araa_url = $data->get_param( 'u' );
	esc_url($araa_url, NULL, "request");
	if (""==$araa_url) {
		$getjsonArr{"araaerror"} = "missing parameter";
		return $getjsonArr;
	}
	$araa_key = sanitize_key($data->get_param( 'k' ));
	if (""==$araa_key) {
		$getjsonArr{"araaerror"} = "missing parameter";
		return $getjsonArr;
	}
	$araa_indivualseed = get_option("araa_secvalue");
	$araa_nonce = md5($araa_url.$araa_indivualseed);
	if ($araa_nonce!=$araa_key) {
		$getjsonArr{"araaerror"} = "invalid call";
		return $getjsonArr;
	}
	$getjson = trim(@file_get_contents($araa_url));
	if (""==$getjson) {
		$getjsonArr{"araaerror"} = "request failed";
		return $getjsonArr;
	}
	$araa_jsonOfUrl =json_decode($getjson, TRUE);
	$araa_errorlevel = json_last_error();
	if (0!=$araa_errorlevel) {
		$getjsonArr{"araaerror"} = "json not valid: ".$araa_errorlevel;
		return $getjsonArr;
	}
	if (is_null($araa_jsonOfUrl)) {
		$getjsonArr{"araaerror"} = "json not valid: decoding failed";
		return $getjsonArr;
	}
	return $araa_jsonOfUrl;
}
?>