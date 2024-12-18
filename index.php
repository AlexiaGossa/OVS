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

//echo "dir = ".__DIR__;
//phpinfo();

try{
    $pdo = new PDO('sqlite:'.dirname(__FILE__).'/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // ERRMODE_WARNING | ERRMODE_EXCEPTION | ERRMODE_SILENT
} catch(Exception $e) {
    echo "Impossible d'accéder à la base de données SQLite : ".$e->getMessage();
    die();
}

//Create a table if not exists : services_measures_UID
//success 	- booleen 				- INTEGER-1B
//value	  	- integer/float/bool 	- REAL-8B/INTEGER-8B
//timestamp - timestamp 			- INTEGER-8B
//latency	- float					- REAL-8B

/*
for ($iServiceIndex=0;$iServiceIndex<100;$iServiceIndex++)
{
	$pdo->exec ( "DROP TABLE IF EXISTS services_measures_UID".$iServiceIndex );
	
	$sStatement = 'CREATE TABLE IF NOT EXISTS services_measures_UID'.$iServiceIndex.' (timestamp INTEGER PRIMARY KEY, success INTEGER, value REAL, latency REAL);';
	$pdo->exec ( $sStatement );
}
*/





//$qry = $db->prepare(
//    'INSERT INTO twocents (path, name, message) VALUES (?, ?, ?)');
//$qry->execute(array($path, $name, $message));


/*

	Scénario de données
	Imaginer un site web complet avec les tests suivants :
	- Ping ICMP => temps de réponse et fonctionne ou pas
	- URL pattern => temps de réponse ainsi que fonctionne ou pas.
	- Test Cert => expiré ou pas.
	
	Comportement variable suivant l'heure de la journée
	- Traffic 1% de 2h à 6h
	- Traffic 20% de 6h à 10h
	- Traffic 10% de 10h à 13h
	- Traffic 30% de 13h à 16h
	- Traffic 9% de 16h à 19h
	- Traffic 10% de 19h à 22h
	- Traffic 20% de 22h à 2h
	- Erreurs URL 1 erreur/semaine à n'importe quel moment
	- Erreurs URL+Ping 5 à 20 erreurs/semaine à n'importe quel moment
	- Erreur du cert 1 erreur par an pendant 2h à la même période : 1 avril
	
	
	
	

*/


$oStatement = $pdo->prepare('SELECT AVG(latency),COUNT(success) FROM services_measures_simulate_ping WHERE success=1');
$oResult = $oStatement->execute();
var_dump($oStatement->fetch());

$oStatement = $pdo->prepare('SELECT AVG(latency),COUNT(success) FROM services_measures_simulate_curl WHERE success=1');
$oResult = $oStatement->execute();
var_dump($oStatement->fetch());

$oStatement = $pdo->prepare('SELECT AVG(latency),COUNT(success) FROM services_measures_simulate_cert WHERE success=1');
$oResult = $oStatement->execute();
var_dump($oStatement->fetch());

$oStatement = $pdo->prepare('SELECT 
	COUNT(t1.success) AS countmins
	FROM services_measures_simulate_curl t1
	INNER JOIN services_measures_simulate_ping t2
		ON t1.timestamp=t2.timestamp
	INNER JOIN services_measures_simulate_cert t3 
		ON t1.timestamp=t3.timestamp	
	WHERE
		t1.success=1 AND t2.success=1 AND t3.success=1;');
$oResult = $oStatement->execute();


$aResult = $oStatement->fetch();
var_dump($aResult);
echo "2-year availability : ".((floatval($aResult['countmins'])/(525600*2.0))*100)."%";
		

die ();



$sSimulationTableName = "services_measures_simulate";


$pdo->exec ( "DROP TABLE IF EXISTS ".$sSimulationTableName."_ping" );
$pdo->exec ( "DROP TABLE IF EXISTS ".$sSimulationTableName."_curl" );
$pdo->exec ( "DROP TABLE IF EXISTS ".$sSimulationTableName."_cert" );

$sTableDefineMetric = "(timestamp INTEGER PRIMARY KEY, success INTEGER, value REAL, latency REAL)";
$pdo->exec ( "CREATE TABLE IF NOT EXISTS ".$sSimulationTableName."_ping ".$sTableDefineMetric );
$pdo->exec ( "CREATE TABLE IF NOT EXISTS ".$sSimulationTableName."_curl ".$sTableDefineMetric );
$pdo->exec ( "CREATE TABLE IF NOT EXISTS ".$sSimulationTableName."_cert ".$sTableDefineMetric );

	


$fTimeSimulationBegin = microtime ( true ); 	//seconds
$fTimeSimulationBegin -= 31557600 * 2.0; 		//back 2 years ago
$pdo->beginTransaction ( );

//List of 24-hour usage
$aHourUsage = array (
			20, 20,
			1, 1, 1, 1,
			20, 20, 20, 20,
			10, 10, 10,
			30, 30, 30,
			9, 9 ,9,
			10, 10, 10,
			20, 20 );
			
//Limit the total to 100%
$fSum = 0;
for ($iIndex=0;$iIndex<24;$iIndex++)
{	
	$fSum += $aHourUsage[$iIndex];
}
$fMul = 100.0 / $fSum;
for ($iIndex=0;$iIndex<24;$iIndex++)
{	
	$aHourUsage[$iIndex] *= $fMul;
}

//print_r ( $aHourUsage );
//die();


$iMinute = 0;

for ($iYear=0;$iYear<2;$iYear++)
{
	for ($iMinuteIndex=0;$iMinuteIndex<525600;$iMinuteIndex++)
	{
		//Get the current time
		$fTimeCurrent = $fTimeSimulationBegin;
		
		//Get Month, Day, Hour, Minute with a little code optimization
		if ( ($iMinuteIndex==0) || ($iMinute==60) )
		{		
			$oDateTime = DateTime::createFromFormat('U', intval($fTimeCurrent) );
			$iMonth 	= intval ( $oDateTime->format("m") );
			$iDay		= intval ( $oDateTime->format("d") );
			$iHour		= intval ( $oDateTime->format("H") );
			$iMinute	= intval ( $oDateTime->format("i") );
		}
		
		//Simulate a ping ICMP 15-25ms with up to 125ms
		$iLatencyPing = 15.0 + (100.0 * $aHourUsage[$iHour]) + (rand(0,100)*0.1);
		$iSuccessPing = true;
		
		//Simulate a CURL 500-600ms with up to 700ms
		$iLatencyCURL = 500.0 + (100.0 * $aHourUsage[$iHour]) + (rand(0,1000)*0.1);
		$iSuccessCURL = true;
		
		//Simulate 1 error/week for the CURL
		$iProbabilityError = rand(0,10079);
		if ($iProbabilityError==0)
		{
			$iLatencyCURL = 5000;
			$iSuccessCURL = false;
		}
		
		//Simulate 20 error/week for the CURL+Ping
		$iProbabilityError = rand(0,503);
		if ($iProbabilityError==0)
		{
			$iLatencyCURL = 5000;
			$iSuccessCURL = false;
			
			$iLatencyPing = 1000;
			$iSuccessPing = false;
		}
		
		//Simulate Cert error on 4/1 9:00 - 10:59
		if ( ($iMonth==4) && ($iDay==1) && ( ($iHour==9) || ($iHour==10) ) )
		{
			$iLatencyCert = 10;
			$iSuccessCert = false;
		}
		else
		{
			$iLatencyCert = 10;
			$iSuccessCert = true;
		}
		
		$oStatement = $pdo->prepare ( 'INSERT into '.$sSimulationTableName.'_ping VALUES (?,?,?,?);' );
		$oStatement->execute (
			array ( 
				intval($fTimeCurrent),
				($iSuccessPing)?(1):(0),
				($iSuccessPing)?(1):(0),
				$iLatencyPing,
			) );
		
		$oStatement = $pdo->prepare ( 'INSERT into '.$sSimulationTableName.'_curl VALUES (?,?,?,?);' );
		$oStatement->execute (
			array ( 
				intval($fTimeCurrent),
				($iSuccessCURL)?(1):(0),
				($iSuccessCURL)?(1):(0),
				$iLatencyCURL,
			) );
		
		$oStatement = $pdo->prepare ( 'INSERT into '.$sSimulationTableName.'_cert VALUES (?,?,?,?);' );
		$oStatement->execute (
			array ( 
				intval($fTimeCurrent),
				($iSuccessCert)?(1):(0),
				($iSuccessCert)?(1):(0),
				$iLatencyCert,
			) );
		
		
		
		
		
		//echo $iMonth." - ".$iDay." ".$iHour." : ".$iMinute."\n";
		
		
		
		//echo $oDateTime->format(DATETIME_CERTIFICATE)."\n";
		
		
		
		$fTimeSimulationBegin 	+= 60.0;
		$iMinute 				+= 1;
	}
	
}

//$fTimeEnd = microtime(true);



$pdo->commit ( );


die();

$fTimeStart= microtime (true);

$fTimeStamp = intval(microtime(true)*1000000000);

$pdo->beginTransaction ( );

for ($iIndex=0;$iIndex<100000;$iIndex++)
{

	for ($iServiceIndex=0;$iServiceIndex<100;$iServiceIndex++)
	{
		$oStatement = $pdo->prepare ( 'INSERT into services_measures_UID'.$iServiceIndex.' VALUES (?,?,?,?);' );
		$oStatement->execute (
			array ( 
				$fTimeStamp,
				rand(0,1),
				10.0 + rand(0,999999)*0.1,
				12.0 + rand(0,999999)*0.0001,
			)
		);
	}	
	$fTimeStamp += 1;
	
}

$pdo->commit ( );

//$pdo->exec("vacuum");

$fTimeEnd= microtime (true);

echo "\nTotal time = ".$fTimeEnd - $fTimeStart;
echo "\n";

//$sStatement = 'INSERT into services_measures_UID VALUES (
//echo $e->getMessage();








/*
print_r ( CheckCert ( "https://www.php.net/manual/en/function.usort.php" ) );
print_r ( CheckCert ( "https://www.google.fr" ) );
print_r ( CheckCert ( "https://www.google.com" ) );
print_r ( CheckCert ( "https://jquery.com" ) );
*/

//print_r ( $aData );
/*


//$aData = CheckCert ( "https://code.jquery.com/jquery-3.7.1.min.js" );

*/
