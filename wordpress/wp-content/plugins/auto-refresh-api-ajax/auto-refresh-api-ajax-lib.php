<?php
function auto_refresh_api_ajax_urlIsNotFromDomain($url) {
	$araa_this_server = $_SERVER['SERVER_NAME'];	
	$araa_this_serverArr = explode(".", $araa_this_server);
	if (count($araa_this_serverArr)==2) { # domain without www.
		$araa_this_server = "www.".$araa_this_server;
	}

	if (preg_match("/$araa_this_server/i", $url)) {
		return FALSE;
	}
	return TRUE;
}

function autorefreshapiajax_retrieve_url($url) {
    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) {
        return $response->get_error_message();
    } else {
		return $response["body"];
    }
}

function auto_refresh_api_ajax_get_jsonurl($url) {
	$urlrec = autorefreshapiajax_retrieve_url($url);
	$jsonOfUrl = json_decode($urlrec, TRUE);
	if ($jsonOfUrl) {
		return $jsonOfUrl;
	} else {
		return FALSE;
	}
}

function autorefreshapiajax_ajax_function() {
	$url = trim(get_option('araa_url'));
    $response = autorefreshapiajax_retrieve_url($url); 	
	echo $response;
	die();
}

function auto_refresh_api_ajax_buildurlforexternaldomain($url) {
	if (""==$url) {
		return "";
	}
	$usedurl = $url;
	$araa_this_server = $_SERVER['SERVER_NAME'];	
	$araa_this_serverArr = explode(".", $araa_this_server);
	if (count($araa_this_serverArr)==2) { # domain without www.
		$araa_this_server = "www.".$araa_this_server;
	}
	if (preg_match("/$araa_this_server/i", $url)) {
		return $url;
	}
	$araa_indivualseed = get_option("araa_secvalue");
	$araa_key = md5($url.$araa_indivualseed);
	$usedurl = "http://";
	if (@$_SERVER['HTTPS']) {
		$usedurl = "https://";
	}
	$usedurl .= $araa_this_server."/wp-json/araa/v1/geturl?u=".urlencode($url)."&k=".urlencode($araa_key);	
	return $usedurl;
}
?>