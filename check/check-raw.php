<?php


/*

	Raw check
	
	Don't forget to add the newline at the end of SMTP command
	SMTP(25) => "EHLO mymessage\n"



function checkContent ( $sContent )
{
	//echo $sContent;
	if (strpos($sContent,"PIPELINING")==false)
		return -1;
	
	if (strpos($sContent,"STARTTLS")==false)
		return -1;
	

	return 0;
}
echo CheckRaw ( "64.233.180.27", 25, "EHLO keeper-us-east-1d.mxtoolbox.com\n", true, 3, "PIPELINING" );
echo CheckRawCallback ( "64.233.180.27", 25, "EHLO keeper-us-east-1d.mxtoolbox.com\n", "checkContent" );

*/


function checkRaw ( $sHostname, $iPort, $sSendCommand = NULL, $bContentExists = false, $iMinimalLenght = 0, $sPattern = NULL )
{
	$oOutput = checkRawInternal ( $sHostname, $iPort, $sSendCommand );
	
	if (gettype($oOutput)!="string")
		return -1;
	
	$iReturnValue = 0;
	
	//Check if content is greater than zero
	if ( ($bContentExists==true) && (strlen($oOutput)==0) )
	{
		$iReturnValue = -2;
	}
		
	//Check minimal lenght
	if ( ($iMinimalLenght>0) && (strlen($oOutput)<$iMinimalLenght) )
	{
		$iReturnValue = -3;
	}
	
	if ( is_null($sPattern)==false )
	{
		if (strpos($oOutput,$sPattern)===false)
		{
			$iReturnValue = -4;
		}
	}
	
	return $iReturnValue;
}

function checkRawCallback ( $sHostname, $iPort, $sSendCommand = NULL, $pCallback = NULL )
{
	$oOutput = checkRawInternal ( $sHostname, $iPort, $sSendCommand );
	
	if (gettype($oOutput)!="string")
		return -1;
	
	$iReturnValue = 0;

	
	if ($pCallback!=NULL)
	{
		$iReturnValue = $pCallback ( $oOutput );
	}

	return $iReturnValue;
}


function checkRawInternal ( $sHostname, $iPort, $sSendCommand = NULL, $iDelayMax = 1 )
{
	//Create the socket
	$oSocket = socket_create ( 
		AF_INET, 
		SOCK_STREAM, 
		0 );
		
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
			'sec' => $iDelayMax,
			'usec' => 0 ) 
		);
		
	socket_set_option (
		$oSocket,
		SOL_SOCKET,
		SO_SNDTIMEO,
		array(
			'sec' => $iDelayMax,
			'usec' => 0 ) 
		);
	
	//If bind is needed
	/*
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
	*/

	//Only use microtime to always have float result
	$fTimeStart = microtime ( 
		true );
	
	//Connect to destination
	$bReturn = @socket_connect (
		$oSocket,
		$sHostname,
		$iPort );
	if ($bReturn!=true)
	{
		socket_close ( $oSocket );
		return -4;
	}
	
	//Empty the array for data return
	$sReturnData[0] = "";
	$sReturnData[1] = "";
	
	//Reveive in UDP or ICMP
	$sReturnData[0] = @socket_read ( 
		$oSocket, 
		65535 );
		
	//Send data if necessary
	if ($sSendCommand!=NULL)
	{
		echo $sSendCommand;
		
		$iReturn = @socket_send ( 
			$oSocket,
			$sSendCommand,
			strlen($sSendCommand),
			0 );
		if ($iReturn===false)
		{
			socket_close ( $oSocket );
			return -5;
		}
	
		//Reveive in UDP or ICMP
		$sReturnData[1] = @socket_read ( 
			$oSocket, 
			65535 );
	}
	
	
	//Almagamation of the 2 socket_read
	$sOutputData = "";
	if ($sReturnData[0]!=false)
		$sOutputData .= $sReturnData[0];
	if ($sReturnData[1]!=false)
		$sOutputData .= $sReturnData[1];
		
	//Only use microtime to always have float result
	$fTimeStop = microtime ( true );
	
	//Close socket
	socket_close ( $oSocket );
	
	return $sOutputData;
}