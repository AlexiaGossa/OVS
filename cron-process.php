<?php
define ( 'BASE_DIR', __DIR__ );
date_default_timezone_set ( 'UTC' );
define ('DATETIME_CERTIFICATE', "Y-m-d H:i:s e");

include BASE_DIR."/check/check-ping.php";
include BASE_DIR."/check/cache-curl.php";
include BASE_DIR."/check/check-url.php";
include BASE_DIR."/check/check-cert.php";
include BASE_DIR."/check/check-dns.php";
include BASE_DIR."/check/check-raw.php";

/*

Charge un JSON (s'il existe)
Vérifie l'état du site (https://bsky.app)
Complète le JSON
Ecrit le JSON

*/


function checkService ( $sHost, $sURL )
{
	$fTimeStart = microtime ( true );
	
	//Get DNS of the URL (0 if success)
	$iStateDNS = CheckDNS ( $sURL ); 
	
	//Ping the Host (<0 if error, >=0 if success and delay measurement)
	$fDelayPing = $iStatePing = checkPING ( "icmp", $sHost, 0, 900 );
	if ($iStatePing>=0)
		$sProtoPing = "icmp";
	else
	{
		$fDelayPing = $iStatePing = checkPING ( "tcp", $sHost, 443, 900 );
		if ($iStatePing>=0)
			$sProtoPing = "tcp/443";
		else
		{
			$fDelayPing = $iStatePing = checkPING ( "tcp", $sHost, 80, 900 );
			$sProtoPing = "tcp/80";
		}
	}
	
	
	//Check the website (0 if success)
	//echo "check url\n";
	$iStateWebsite = CheckURL ( $sURL, true, 100 ); //, "Bluesky" );
	
	//Check the website cert (array returned)
	//echo "check cert\n";
	$aStateCert = CheckCert ( $sURL );
	
	/*
	$aOutput = array (
		'iError' => 0,
		'bExpired' => false,
		'iRemainDays' => 0,
		'iRemainHours' => 0,
		'sStartDate' => '',
		'sExpireDate' => '',
		'sSubject' => '',
		'sIssuer' => '',
	*/
	
	$fTimeStop = microtime ( true );
	
	$aReturn = array (
		'bSuccessDNS' 	=> 	($iStateDNS==0)?(true):(false),
		'bSuccessPing' 	=> 	($iStatePing>=0)?(true):(false),
		'bSuccessURL'	=>	($iStateWebsite==0)?(true):(false),
		'bSuccessCert'	=>	(($aStateCert['iError']==0)&&($aStateCert['bExpired']==false))?(true):(false),
		'fLatencyPing'	=>	($fDelayPing<0)?(0):($fDelayPing),
		'fLatencyCheck'	=>	($fTimeStop-$fTimeStart),
		'sProtoPing'	=>  ($sProtoPing),
		'sCheckedUrl'	=>	$sURL,
		'sCheckedHost'	=>	$sHost,
	);
	
	print_r ( $aReturn );
	return 	$aReturn;
	
	
}

/*
checkService ( "bsky.app", "https://bsky.app" );
echo "\n\n";
checkService ( "twitter.com", "https://twitter.com" );
echo "\n\n";
checkService ( "google.fr", "https://google.fr" );
echo "\n\n";
checkService ( "google.com", "https://google.com" );
echo "\n\n";
checkService ( "microsoft.com", "https://microsoft.com" );
echo "\n\n";
*/
/*do {

	checkService ( "ovh.com", "https://ovh.com" );
	checkService ( "www.scaleway.com", "https://www.scaleway.com" );
	sleep ( 55 );
	
} while (1);
echo "\n\n";
*/
checkService ( "ovh.com", "https://ovh.com" );

/*
echo "\ngoogle.fr ICMP : ".checkPING ( "icmp", "www.google.fr", 0, 100, 32 );
echo "\ngoogle.fr TCP : ".checkPING ( "tcp", "www.google.fr", 443, 100, 32 );
echo "\nbsky.app TCP : ".checkPING ( "tcp", "3.18.142.249", 80, 900, 32 );
echo "\nbsky.app TCP : ".checkPING ( "tcp", "3.18.142.249", 443, 900, 32 );
echo "\nbsky.app ICMP : ".checkPING ( "icmp", "3.18.142.249", 443, 900, 32 );
*/


/*

	Une métrique ?
	- Un service
	- Une valeur
	- Success/Fail
	- Timestamp
	- Moyen de génération
	
	
	Service ?
	Quelque chose qui est surveillé
	
	Une table "services_list" contenant la liste des services
	- Nom 							(name 			- string 			- UserDefined) 
	- Description 					(description 	- string 			- UserDefined)
	- Moyen de générer la data 		(generator 		- string/json 		- UserDefined)
	- Type de mesures obtenues		(output 		- string ou integer - UserDefined)
	- Fréquence en minutes			(frequecy 		- integer 			- UserDefined)	
	- Nom de la table des mesures	(table_name 	- string 			- Internal)
	table_name = "service_measures_UID"
	UID = PHP uniqid
	
	Une table par service "service_measures_UID" contenant les mesures d'un service
	- Success/fail									(success	- booleen)				INTEGER-1B
	- Une valeur (entier, flottant, booleen)		(value		- integer/float/bool)	REAL-8B/INTEGER-8B		
	- Timestamp										(timestamp 	- timestamp)			INTEGER-8B				Seconds
	- Durée de la mesure							(latency	- float)				REAL-8B					Milliseconds
	
	1 mesure / min
	60 mesures / heure
	1440 mesures / jour
	525600 mesures / an
	1Mmesures/2 ans
	
	La table service_measures_UID en raw façon C/C++/ASM
		25 octets/ligne
		25Moctets/2ans
	

	Données générées pour une simulation de 100 services
	
		
	Insertion de 100 services pour 10000 lignes/service.
	Attention : on insère pour les 100 services une seule ligne à la fois avec des données typiques.
		Avec/Sans Vacuum => 33202176 octets => 33,2 octets/ligne pour un total de 5,6sec
		
	Insertion de 100 services pour 100000 lignes/service.
	Attention : on insère pour les 100 services une seule ligne à la fois avec des données typiques.
		Sans Vacuum => 328519680 octets => 32,9 octets/ligne pour un total de 51,2sec
		
	Bilan :
	1 ligne d'un service => 33 octets
	Avec 2Go d'espace de stockage => 650752 lignes pour 100 services
	=> un peu plus de 1 an.
	
	

	Tests autres
	
	La table services_measures_UID façon SQLite
		1001 lignes => 53248 octets
		53.2 octets/ligne
		
		Données très compressibles
		1M lignes => 13070336 octets
		13,1 octets/ligne
		
		Données compressibles (timestamp=0 à n-1) - aucune différence avec un vacuum
		1M lignes => 26660864 octets
		26,7 octets/lignes
		
		Données "typiques" moins compressibles (timestamp réel) - aucune différence avec un vacuum
		1M lignes => 32796672 octets
		32,8 octets/lignes
		
		Données pas compressibles (random) - aucune différence avec un vacuum
		1M lignes => 40636416 octets
		40,6 octets/lignes	
	
	
	
	Sans la transaction globale
	10000 lignes insérées => 28s
	
	Avec une transaction globale
	10000 lignes insérées => 32ms
	100000 lignes insérées => 175ms
	1000000 lignes insérées => 1,62s
	
	

	
	INTEGER
	REAL
	TEXT (UTF)
	BLOB
	
	Mesure de temps ?
	On mesure une durée avec une précision de la microseconde.
	Problème, il faut stocker le résultat. Mais SQLite c'est 8 octets par résultat (REAL).
	La mesure va de la microseconde à 1min soit 60000000us.
	Hypothèse : On fait une mais on ne garde qu'une précision de 0,1ms (100us).
	Notre mesure maxi fera : 600000 * 0,1ms.
	
	La valeur de la mesure se fait sur un INT16 donc -2^15 à +2^15-1
	0 à 32767ms
	
	
	
	
	

*/