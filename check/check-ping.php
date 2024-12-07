<?php

//The code was based on the following model for ICMP, but fully re-written.
//https://raw.githubusercontent.com/geerlingguy/Ping/1.x/JJG/Ping.php

/*

*/
function checkPING ( $sProtocol, $sDestinationAddress, $iDestinationPort, $iDelayMax = 100, $iSize = 32, $sSourceAddress = null )
{
	//Create the socket
	switch ($sProtocol)
	{
		case "tcp":
		case "TCP":
			$sRawData = str_pad ( "", $iSize );
			$oSocket = socket_create ( 
				AF_INET, 
				SOCK_STREAM, 
				0 );
			$iReceiveMode = 0;
			break;
			
		case "udp":
		case "UDP":
			$iSize = min ($iSize, 65507);
			$sRawData = str_pad ( "", $iSize );
			$oSocket = socket_create ( 
				AF_INET, 
				SOCK_DGRAM, 
				0 );
				
			$iReceiveMode = 1;
			break;
			
		default:
			$iSize = min ($iSize, 65507);
			$sRawData = checkICMP_CreateDatagram ( $iSize );
			$oSocket = socket_create ( 
				AF_INET, 
				SOCK_RAW, 
				getprotobyname('icmp') );
				
			$iDestinationPort = 0;
			$iReceiveMode = 2;
			break;
	}
	if ($oSocket===false)
	{
		return -2;
	}
	
	//Set recv and send timeout
	socket_set_option (
		$oSocket,
		SOL_SOCKET,
		SO_RCVTIMEO,
		array(
			'sec' => 0,
			'usec' => $iDelayMax * 1000) 
		);
		
	socket_set_option (
		$oSocket,
		SOL_SOCKET,
		SO_SNDTIMEO,
		array(
			'sec' => 0,
			'usec' => $iDelayMax * 1000) 
		);
	
	//If bind is needed
	if ($sSourceAddress!=null)
	{
		$bReturn = @socket_bind (
			$oSocket,
			$sSourceAddress );
			
		if ($bReturn!=true)
		{
			socket_close ( $oSocket );
			return -3;
		}
	}

	//Only use microtime to always have float result
	$fTimeStart = microtime ( 
		true );
	
	//Connect to destination across TCP, UDP or ICMP
	$bReturn = @socket_connect (
		$oSocket,
		$sDestinationAddress,
		$iDestinationPort );
	if ($bReturn!=true)
	{
		socket_close ( $oSocket );
		return -4;
	}

	//Send data using TCP, UDP or ICMP
	$iReturn = @socket_send ( 
		$oSocket,
		$sRawData,
		strlen($sRawData),
		0 );
	if ($iReturn===false)
	{
		socket_close ( $oSocket );
		return -5;
	}
	
	//Only use microtime to always have float result
	$fTimeStop = microtime ( true );
	
	//Reveive in UDP or ICMP
	if ($iReceiveMode)
	{
		$sReturnData = @socket_read ( 
			$oSocket, 
			65535 );
			
		//We have received some data in UDP or ICMP, update TimeStop
		if ($sReturnData!==false)
		{
			$fTimeStop = microtime ( true );
		}
		
		///Check error in ICMP only mode
		if ($iReceiveMode==2)
		{
			if ($sReturnData===false)
			{
				socket_close ( $oSocket );
				return -6;
			}
		}
	}
	
	//Close socket
	socket_close ( $oSocket );
	
	return round ( ($fTimeStop - $fTimeStart) * 1000.0, 1 );
}
	
/*
echo "\ngoogle.fr TCP 80 : ".checkPING ( "tcp", "www.google.fr", 80, 100, 32 );
echo "\ngoogle.fr TCP 443 : ".checkPING ( "tcp", "www.google.fr", 443, 100, 32 );
echo "\ngoogle.fr UDP 80 : ".checkPING ( "udp", "www.google.fr", 80, 100, 32 );
echo "\ngoogle.fr UDP 443 : ".checkPING ( "udp", "www.google.fr", 443, 100, 32 );
echo "\ngoogle.fr ICMP : ".checkPING ( "icmp", "www.google.fr", 0, 100, 32 );

echo "\nquad-9 UDP 53 : ".checkPING ( "udp", "9.9.9.9", 53, 100, 32 );
echo "\nquad-9 UDP 80 : ".checkPING ( "udp", "9.9.9.9", 80, 100, 32 );
echo "\nquad-9 UDP 443 : ".checkPING ( "udp", "9.9.9.9", 443, 100, 32 );
echo "\nquad-9 ICMP : ".checkPING ( "icmp", "9.9.9.9", 0, 100, 32 );

echo "\ngoogle dns UDP 53 : ".checkPING ( "udp", "8.8.8.8", 53, 100, 32 );
echo "\ngoogle dns UDP 80 : ".checkPING ( "udp", "8.8.8.8", 80, 100, 32 );
echo "\ngoogle dns UDP 443 : ".checkPING ( "udp", "8.8.8.8", 443, 100, 32 );
echo "\ngoogle dns ICMP : ".checkPING ( "icmp", "8.8.8.8", 0, 100, 32 );
*/


function checkICMP_CreateDatagram ( $iSizeDatagramData )
{
	//ICMP Type "Echo Request"
	$sType 				= "\x08";
	
	//ICMP Code "Echo request, used to ping"
	$sCode 				= "\x00";
	
	//ICMP Checksum (zero by default, need to calculate)
	$sChecksum 			= "\x00\x00";
	
	//ICMP identifier, 0 by default
	$sIdentifier 		= "\x00\x00";
	
	//ICMP Sequence number, 0 by default
	$sSequenceNumber 	= "\x00\x00";
	
	//Default datagram data
	$sData = str_pad ( "", $iSizeDatagramData );
	
	//Calculate checksum
	$sChecksum = checkICMP_CalculateChecksum ( 
		$sType . $sCode . $sChecksum . $sIdentifier . $sSequenceNumber . $sData );
	
	//Create final datagram
	return $sType . $sCode . $sChecksum . $sIdentifier . $sSequenceNumber . $sData;
}


function checkICMP_CalculateChecksum ( $sPayload )
{
	$sData = $sPayload;
	
	//Add one byte in case of odd data
    if (strlen($sData)&1)
		$sData .= "\x00";

	//Generate an array of 16-bit values
    $arrayWord = unpack('n*', $sData);
	
	//Sum of all values inside the array
    $iSum = array_sum($arrayWord);
	
	//Apply the chekcsum based of RFC 1071
	$iSum = ($iSum & 0xFFFF) + ($iSum >> 16); //First iteration, could output 17-bit value
	$iSum = ($iSum & 0xFFFF) + ($iSum >> 16); //Second iteration to limit to 16-bit value

	//Return NOT valued of iSum as 2-byte string
    return pack('n*', ~$iSum);
}

