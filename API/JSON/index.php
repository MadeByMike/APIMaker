<?php 
echo $_REQUEST['rules'];
if (file_exists(dirname(__FILE__) . '/../../APIMaker/APIMaker.class.php')) {
	require dirname(__FILE__) . '/../../APIMaker/APIMaker.class.php';
}
?>