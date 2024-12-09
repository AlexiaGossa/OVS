<?php

/*
	Check if one IPv4 or IPv6 exists
*/
function CheckDNS ( $sURL )
{
	//Parse and get domain name
	$sDomain = parse_url ( $sURL, PHP_URL_HOST );
	if ($sDomain==false)
		return -1;

	//Get all A / AAAA
	$aResult = dns_get_record ( $sDomain, DNS_A | DNS_AAAA );
	if ($aResult==false)
		return -2;
	
	//Count records
	if (count($aResult))
		return 0;
	
	return -3;
}

