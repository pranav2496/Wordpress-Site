<head>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js"></script>
</head>

<script language="javascript" type="text/javascript">
var timeout = setTimeout(reloadChat, 5000);

function reloadChat () {
$('#links').load('http://localhost/prj/wordpress/ #links',function () {
        $(this).unwrap();
        timeout = setTimeout(reloadChat, 5000);
});
}
</script>