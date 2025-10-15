<?php
	$string = $_GET['key'];
	$secret_key = 'my_simple_secret_keyx';
    $secret_iv = 'my_simple_secret_ivx';
    $output = false;
    $encrypt_method = "AES-256-CBC";
    $key = hash( 'sha256', $secret_key );
    $iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );
	$output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
	$outputArr = explode("number", $output);
	$set = $outputArr[1];
	$num = $outputArr[0];
	$title = $_GET['title'];
	$filepath = '6473k339312/'.$set.'/'.$num.'.png';
	$filename = $title.' '.$num.'.png';
	$content_len=@filesize($filepath); 
	header("Content-type: application/png"); 
	header("Content-type: application/octet-stream");
	header("Pragma: public");
	header("Expired: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Content-Description: File Transfer");
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');
	header("Content-Transfer-Encoding: binary");
	header('Content-Encoding: none');
	header('Accept-Ranges: bytes'); 
	if($content_len!=false) Header("Content-length: $content_len"); 
	readfile($filepath); 
	/*
	header('Content-Transfer-Encoding: binary');  // For Gecko browsers mainly
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');
	header('Accept-Ranges: bytes');  // Allow support for download resume
	header('Content-Length: ' . filesize($filepath));  // File size
	header('Content-Encoding: none');
	header('Content-Type: application/png');  // Change the mime type if the file is not PDF
	header('Content-Disposition: attachment; filename=' . $filename);  // M
	
	$content_len=@filesize($filepath); 
	header("Content-type: application/png"); 
	header("Content-type: application/octet-stream");
	header("Pragma: public");
	header("Expired: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Content-Description: File Transfer");
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');	
	if($content_len!=false) Header("Content-length: $content_len"); 
	header("Content-Transfer-Encoding: binary");
	header('Content-Encoding: none');
	header('Accept-Ranges: bytes');  // Allow support for download resume
	readfile($filepath);
	*/
?>