<?php



function CacheCurl ( $sUrl )
{
	//Read data from cache
	$oData = $iReturnValue = CacheCurl_ReadData ( $sUrl );
	
	//Need to do a new curl in case of empty cache
	if ($iReturnValue==-1)
	{
		$oData = $iReturnValue = CacheCurl_RealCurl ( $sUrl );
		if (is_array($iReturnValue)==false)
		{
			$oData = array (
				'iError' => $iReturnValue
			);
			
			CacheCurl_WriteData ( $sUrl, $oData );
			
			return $iReturnValue;
		}
		else
		{
			CacheCurl_WriteData ( $sUrl, $oData );
		}
	}
	else
	{
		if (in_array('iError',$oData))
			return intval($oData['iError']);
	}
	
	return $oData;
}

function CacheCurl_WriteData ( $sUrl, $oData )
{
	//Get number of ms from UNIX time
	$iTimeStamp = intval((microtime(true)*1000));	
	
	$sFileName = "cache-curl-".md5($sUrl)."-".$iTimeStamp.".json";
	file_put_contents ( 
		$sFileName,
		json_encode ( $oData, JSON_FORCE_OBJECT ) );
}

function CacheCurl_ReadData ( $sUrl )
{
	$sMD5 = md5($sUrl);
	
	//Get number of ms from UNIX time
	$iTimeStamp = intval((microtime(true)*1000));	
	
	

	$sPattern = "./cache-curl-".$sMD5."-*.json";
	
	$iBestDeltaTS = -1;
	$iBestIndex = -1;

	//Glob has an implicit sort on 2024-11-18
	$oFileEntry = glob($sPattern,GLOB_NOSORT);
	if ($oFileEntry!=false)
	{
		$iCount = count ( $oFileEntry );
		for ($iIndex=0;$iIndex<$iCount;$iIndex++)
		{
			$iDeltaTS = -1;
			
			$iPosition = strpos ( $oFileEntry[$iIndex], $sMD5 );
			if ($iPosition!==false)
			{		
				$sTimeStamp = substr ( $oFileEntry[$iIndex], $iPosition+32+1 );
				$iPosition = strpos ( $sTimeStamp, ".json" );
				if ($iPosition!==false)
				{
					$iTimeStampFile = intval(substr ( $sTimeStamp, 0, $iPosition ));
					$iDeltaTS = $iTimeStamp - $iTimeStampFile;
				}
			}
			
			if ($iDeltaTS==-1)
			{
				$iFileTime = filemtime($oFileEntry[$iIndex]);
				if ($iFileTime!=false)
				{	
					$iTimeStampFile = intval(filemtime($oFileEntry[$iIndex])) * 1000;
					$iDeltaTS = $iTimeStamp - $iTimeStampFile;
				}
			}
			
			//Delete file if older than 1-minute
			if ( ($iDeltaTS==-1) || ($iDeltaTS>60000) )
			{
				unlink ( $oFileEntry[$iIndex] );
			}
			else
			{
				if ( ($iDeltaTS<$iBestDeltaTS) || ($iBestIndex==-1) )
				{
					$iBestDeltaTS = $iDeltaTS;
					$iBestIndex = $iIndex;
				}
			}
		}
		
		//Load most recent file (no more than 5-sec older)
		if ( ($iBestIndex!=-1) && ($iBestDeltaTS<5000) )
		{
			$sFileData = file_get_contents ( $oFileEntry[$iBestIndex] );
			$oFileData = json_decode ( $sFileData, true );
			return $oFileData;
		}
	}
	
	return -1;
}



function CacheCurl_RealCurl ( $sURL )
{
	$aOptions = array (
		CURLOPT_URL						=> $sURL,
		CURLOPT_CUSTOMREQUEST			=> "GET",
		CURLOPT_HTTPGET					=> true,
		CURLOPT_POST					=> false,
		CURLOPT_HEADER					=> false,
		CURLOPT_FOLLOWLOCATION			=> true,
		CURLOPT_MAXREDIRS				=> 8,
		CURLOPT_RETURNTRANSFER			=> true,
		CURLOPT_USERAGENT				=> "OVS",
		CURLOPT_CONNECTTIMEOUT			=> 1,
		CURLOPT_TIMEOUT					=> 5,
	    CURLOPT_SSL_VERIFYPEER			=> false,
		CURLOPT_SSL_VERIFYHOST			=> false,
		CURLOPT_SSL_VERIFYSTATUS		=> false,
		CURLOPT_PROXY_SSL_VERIFYHOST	=> false,
		CURLOPT_PROXY_SSL_VERIFYPEER	=> false,
		CURLOPT_CERTINFO				=> true 
	);
	
	$fTimeStart = microtime ( true );
	
	$oCurl		= curl_init();
	curl_setopt_array ( $oCurl, $aOptions );
	$sContent	= curl_exec ( $oCurl );
	$iErrNo		= curl_errno ( $oCurl );
	$sErrMsg	= curl_error ( $oCurl );
	$aInfo		= curl_getinfo ( $oCurl );
	curl_close ( $oCurl );
	
	$fTimeStop = microtime ( true );
	
	
	
	if ($iErrNo==0)
	{
		//echo "php_curl\n";
		return array (
			"sURL"			=> $sURL,
			"aInfo"			=> $aInfo,
			"sContent"		=> $sContent,
			"DurationMS"	=> round ( ($fTimeStop - $fTimeStart) * 1000.0, 1 )
		);
	}
	else
	{
		//echo "php_curl error = ".$sErrMsg."\n";
		return -1;
	}
}
