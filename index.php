<?php
define ( 'BASE_DIR', __DIR__ );
date_default_timezone_set ( 'UTC' );
define ('DATETIME_CERTIFICATE', "Y-m-d H:i:s e");

include BASE_DIR."/check/check-ping.php";
include BASE_DIR."/check/cache-curl.php";
include BASE_DIR."/check/check-url.php";
include BASE_DIR."/check/check-cert.php";

//echo "dir = ".__DIR__;
//phpinfo();


/*
print_r ( CheckCert ( "https://www.php.net/manual/en/function.usort.php" ) );
print_r ( CheckCert ( "https://www.google.fr" ) );
print_r ( CheckCert ( "https://www.google.com" ) );
print_r ( CheckCert ( "https://jquery.com" ) );
print_r ( CheckCert ( "https://redhat.com" ) );
//$aData = CheckCert ( "https://10.138.45.10/" );

//$aData = CheckCert ( "https://code.jquery.com/jquery-3.7.1.min.js" );
print_r ( $aData );
*/
//echo "error = ".$iReturn;