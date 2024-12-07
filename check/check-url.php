<?php

/*
	Demande un CA ou une URL
		Il faut demander au cache s'il a une données CURL récente (moins de 5sec)
			Si la donnée récente existe, elle est chargée.
			Si la donnée récente n'existe pas, toutes les données anciennes sont supprimées et on fait un CURL
			
		Il faut vérifier que les données datées de plus de 1mins doivent être supprimées
		
	
	Assume que l'on est sur un système 64 bits	
	
		

*/



function CheckURL ( $sURL, $bContentExists = false, $iMinimalLenght = 0, $sPattern = NULL )
{
	$aData = $iReturnValue = CacheCurl ( $sURL ); 
	
	if ($iReturnValue<0)
		return -1;
	
	$iReturnValue = 0;
	
	//Check if content is greater than zero
	if ( ($bContentExists==true) && (strlen($aData['sContent'])==0) )
	{
		$iReturnValue = -2;
	}
		
	//Check minimal lenght
	if ( ($iMinimalLenght>0) && (strlen($aData['sContent'])<$iMinimalLenght) )
	{
		$iReturnValue = -3;
	}
	
	if ( is_null($sPattern)==false )
	{
		if (strpos($aData['sContent'],$sPattern)===false)
		{
			$iReturnValue = -4;
		}
	}
	
	return $iReturnValue;
}


/*
function checkPage ( $sContent )
{
	if (strpos($sContent,"popular general-purpose")==false)
		return -1;
	
	if (strpos($sContent,"navbar__search-button-mobile")==false)
		return -1;
	
	return 0;
}

$iReturn = CheckURLCallback ( "https://www.php.net/manual/en/function.usort.php", "checkPage" );
*/

function CheckURLCallback ( $sURL, $pCallback = NULL )
{
	$aData = $iReturnValue = CacheCurl ( $sURL ); 
	
	if ($iReturnValue<0)
		return -1;
	
	$iReturnValue = 0;

	
	if ($pCallback!=NULL)
	{
		$iReturnValue = $pCallback ( $aData['sContent'] );
	}

	return $iReturnValue;
}







//print_r ( CheckURL ( "https://php.net", true, 1, "popular-general-purpose" ) );


//CacheCurl_WriteData ( "https://php.net", CheckURL_RealCurl ( "https://php.net" ) );

//print_r ( CacheCurl ( "https://php.net" ) );