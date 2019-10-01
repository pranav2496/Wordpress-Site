var timeout = setTimeout(reloadChat, 5000);

function reloadChat () {
$('#links').load('http://localhost/prj/wordpress/ #links',function () {
        $(this).unwrap();
        timeout = setTimeout(reloadChat, 5000);
});
}