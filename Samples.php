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

require_once("TChester/Pdf2Text.php");

//	$object = new TChester_Pdf2Text("./Samples/test_mac_flate_meta.pdf");
//	$object = new TChester_Pdf2Text("./Samples/test_mac_flate.pdf");
	$object = new TChester_Pdf2Text("./Samples/test_windows_flate_meta.pdf");
//	$object = new TChester_Pdf2Text("./Samples/test_windows_flate.pdf");
?>
<hr>
<h3>PDF Header</h3>
<?php
	$header = $object->getHeader();
	echo "Header : " . htmlentities($header->header)  . "\n";
	echo "Version: " . htmlentities($header->version) . "\n";
	echo "\n";
?>
<hr>
<h3>PDF Body</h3>
<?php
	$body = $object->getBody();
	
	foreach ($body->objects as $obj)
	{
		echo "key     : " . htmlentities($obj['key'])        . "\n";
		echo "dict    : " . htmlentities($obj['dictionary']) . "\n";
		if ($obj['probableText'])
			echo "contents: " . htmlentities($obj['contents']) . "\n\n";
		else
			echo "contents: \n\n";
	}
	echo "\n";
?>
<hr>
<h3>PDF Trailer</h3>
<?php
	$trailer = $object->getTrailer();
	echo "Dictionary (Raw) : " . htmlentities($trailer->dictionary) . "\n";
	echo "Dictionary (Id1) : " . htmlentities($trailer->id1) . "\n";
	echo "Dictionary (Id2) : " . htmlentities($trailer->id2) . "\n";
	echo "Dictionary (Root): " . htmlentities($trailer->root) . "\n";
	echo "Dictionary (Info): " . htmlentities($trailer->info) . "\n";
	echo "Dictionary (Size): " . htmlentities($trailer->size) . "\n";
	echo "Dictionary (Prev): " . htmlentities($trailer->prev) . "\n";
	echo "Start Xref Offset: " . htmlentities($trailer->startXref) . "\n";
	echo "EOF              : " . htmlentities($trailer->eof) . "\n";
	echo "\n";
?>
<hr>
<h3>PDF Info</h3>
<?php
  echo "Title       : " . htmlentities($object->getTitle())        . "\n";
  echo "Author      : " . htmlentities($object->getAuthor())       . "\n";
  echo "Subject     : " . htmlentities($object->getSubject())      . "\n";
  echo "Keywords    : " . htmlentities($object->getKeywords())     . "\n";
  echo "Creator     : " . htmlentities($object->getCreator())      . "\n";
  echo "Producer    : " . htmlentities($object->getProducer())     . "\n";
  echo "CreationDate: " . htmlentities($object->getCreationDate()) . "\n";
  echo "ModDate     : " . htmlentities($object->getModDate())      . "\n";
  echo "\n";
?>
<hr>
<h3>PDF Contents</h3>
</pre>
<p>
<?php
	echo htmlentities($object->getContents());
?>
</p>
</body>
</html>