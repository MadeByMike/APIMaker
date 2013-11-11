<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

  <title></title>
  <meta name="description" content="">
  <meta name="author" content="Mike">
  <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.2/css/bootstrap.min.css">
	
</head>
<body>
    <div class="container">
	<?php 
	if (file_exists(dirname(__FILE__) . '/../../APIMaker/APIMaker.class.php')) {
		require dirname(__FILE__) . '/../../APIMaker/APIMaker.class.php';
		$api_maker = new APIMaker();
	}
	?>
	</div><!-- /.container -->
</body>
</html>