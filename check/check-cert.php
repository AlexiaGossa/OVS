<?php


function CheckCert ( $sURL )
{
	$aOutput = array (
		'iError' => 0,
		'bExpired' => false,
		'iRemainDays' => 0,
		'iRemainHours' => 0,
		'sStartDate' => '',
		'sExpireDate' => '',
		'sSubject' => '',
	);
	
	$aData = $iReturnValue = CacheCurl ( $sURL );
	
	if ($iReturnValue<0)
	{
		$aOutput['iError'] = $iReturnValue;
	}
	else
	{
		$aData['sContent'] = '';
		
		$aCertificate = array ( );
		if (isset($aData['aInfo']['certinfo']['0']['Expire date']))
		{
			$aOutput['sSubject'] 		= $aData['aInfo']['certinfo']['0']['Subject'];
			
			//$aData['aInfo']['certinfo']['0']['Expire date'] = "Dec 5 23:59:59 2024 GMT";
			$oTimezoneUTC 				= new DateTimeZone("UTC");
			$dateExpire 				= new DateTime ( $aData['aInfo']['certinfo']['0']['Expire date'] );
			$dateStart					= new DateTime ( $aData['aInfo']['certinfo']['0']['Start date'] );
			$dateExpire->setTimezone ( $oTimezoneUTC );
			$dateStart->setTimezone ( $oTimezoneUTC );
			$aOutput['sExpireDate'] 	= $dateExpire->format( DATETIME_CERTIFICATE );
			$aOutput['sStartDate'] 		= $dateStart->format( DATETIME_CERTIFICATE );
			
			$dateCurrent				= new DateTime ( );
			$iTimeStampCurrent			= $dateCurrent->getTimestamp ( );
			$iTimeStampExpire			= $dateExpire->getTimestamp ( );
			$fDeltaHours				= ($iTimeStampExpire - $iTimeStampCurrent)/3600.0;
			$fDeltaDays					= $fDeltaHours/24.0;
			$aOutput['iRemainDays']		= intval($fDeltaDays);
			$aOutput['iRemainHours']	= intval($fDeltaHours);
			
			if ($fDeltaHours<=0)
				$aOutput['bExpired'] = true;
		}
	}
	
	return $aOutput;
}
