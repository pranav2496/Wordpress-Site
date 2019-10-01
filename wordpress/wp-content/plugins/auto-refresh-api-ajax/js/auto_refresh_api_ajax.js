var $araaapi = jQuery.noConflict();
$araaapi(document).ready( function() {
$araaapi( autorefreshapiajaxparam.araapageselektorinitialhide ).hide();
(function araaupdate() {
	var url2json = autorefreshapiajaxparam.araaurl;
	$araaapi.ajax({
		dataType: 'json',
		url: url2json,
		data: {
			'action' : 'autorefreshapiajax_ajax_function'
		},	
		success: function( data ) {
			function resolve(path, obj) {
				return path.split('.').reduce(function(prev, curr) {
				return prev ? prev[curr] : null
				}, obj || self)
			}
			var araaresolvedjsonvalue = resolve(autorefreshapiajaxparam.araajsonvalue, data);
			$araaapi( autorefreshapiajaxparam.araapageselektor ).html(araaresolvedjsonvalue);
			$araaapi( autorefreshapiajaxparam.araapageselektorinitialhide ).show();
		}
	}).then(function() {           
       setTimeout(araaupdate, autorefreshapiajaxparam.araarefresh);
    });
})();   
})