<?php
	error_reporting(E_ALL);
	ini_set('display_errors', true);
	ini_set('html_errors', false);
?>

<html>
<head><title>PDF-2-Text</title></head>
<body>
<pre>
<?php

ini_set("max_execution_time", "3000");
require_once("TChester/Pdf2Text.php");

$handle = opendir('./Samples'); 
while (false !== ($filename = readdir($handle))){ 
  $extension = strtolower(substr(strrchr($filename, '.'), 1)); 
  if($extension == 'pdf'){ 
      echo "Processing: " . htmlentities($filename) . "\n";
      
      $object = new TChester_Pdf2Text("./files/". $filename);
  } 
}

?>

</p>
</body>
</html>