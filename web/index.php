<?php
use Silex\Application;
use Silex\Provider\SerializerServiceProvider;
use Symfony\Component\HttpFoundation\Response;

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';

$app = new Application(array('debug' => true));

//register Serializer to use
$app->register(new SerializerServiceProvider('auth'));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

//list possible ICAO codes here
$icaoCodes = array('EGLL',
					'EGGW',
					'EGLF',
					'EGHI',
					'EGKA',
					'EGMD',
					'EGMC');

/**
 * Converting Latitude And Longitude Coordinates Between Decimal And Degrees, Minutes, Seconds
 * https://www.dougv.com/2012/03/converting-latitude-and-longitude-coordinates-between-decimal-and-degrees-minutes-seconds/
 */
function DMS2Decimal($degrees = 0, $minutes = 0, $seconds = 0, $direction = 'n') {
   //converts DMS coordinates to decimal
   //returns false on bad inputs, decimal on success
    
   //direction must be n, s, e or w, case-insensitive
   $d = strtolower($direction);
   $ok = array('n', 's', 'e', 'w');
    
   //degrees must be integer between 0 and 180
   if(!is_numeric($degrees) || $degrees < 0 || $degrees > 180) {
      $decimal = false;
   }
   //minutes must be integer or float between 0 and 59
   elseif(!is_numeric($minutes) || $minutes < 0 || $minutes > 59) {
      $decimal = false;
   }
   //seconds must be integer or float between 0 and 59
   elseif(!is_numeric($seconds) || $seconds < 0 || $seconds > 59) {
      $decimal = false;
   }
   elseif(!in_array($d, $ok)) {
      $decimal = false;
   }
   else {
      //inputs clean, calculate
      $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
       
      //reverse for south or west coordinates; north is assumed
      if($d == 's' || $d == 'w') {
         $decimal *= -1;
      }
   }
    
   return $decimal;
}

/**
 * Authenticate user-browser by cURL
 */
$app->get('/curlAPIauth', function(Silex\Application $app){
	
	try {
		$ch = curl_init();
		if (FALSE === $ch) {
			throw new Exception('failed to initialize cUrl');
		}
		
		$rrPassword = '123456';
		$deviceId = 'e138231a68ad82f054e3d756c6634ba1';
		$apiAccessCode = '3cz63T6bB49b344v81CEu0j';
		//build data for serialization
		$aData = array(
			'USR' => 'andriy.leshchuk@gmail.com',
			'PASSWD' => md5($rrPassword), // is the MD5 encrypted user password
			'DEVICEID' => $deviceId,
			'PCATEGORY' => 'RocketRoute',
			'APPMD5' => $apiAccessCode //is the MD5 encrypted password of the API access code.
		);

		//create XML
		$serializedData = $app['serializer']->serialize($aData, 'xml',
												array(	'xml_encoding' => 'UTF-8',
														'xml_root_node_name' => 'AUTH',
												)
											);
		$dataToSend = array('req' => $serializedData);
		//create nice string
		$dataToSendInQuery = http_build_query($dataToSend);

		curl_setopt($ch, CURLOPT_URL, 'https://flydev.rocketroute.com/remote/auth');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToSendInQuery );
		// receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//get response value
		$serverOutput = curl_exec($ch);

		if (FALSE === $serverOutput) {
			throw new Exception(curl_error($ch), curl_errno($ch));
		}
		
		//make it as an object and return
		$xData = simplexml_load_string($serverOutput);
		
		if ('ERROR' == (string)$xData->RESULT) {
			$response = array(
				'success' => 0,
				'data' => (string)$xData->MESSAGES->MSG
			);
		} else { //on success
			$response = array(
				'success' => 1,
				'data' => 'You have successfuly Authenticated with key: '.(string)$xData->KEY
			);
		}
		
		return $app->json($response);

	} catch(Exception $e) {
		trigger_error(sprintf(
			'Curl failed with error #%d: %s',
			$e->getCode(), $e->getMessage()),
			E_USER_ERROR);
	}
});

/**
 * The main page, with Map
 */
$app->get('/', function(Silex\Application $app) use ($icaoCodes){
/*	$rrPassword = '123456';
	$deviceId = 'e138231a68ad82f054e3d756c6634ba1';
	$apiAccessCode = '3cz63T6bB49b344v81CEu0j';
	//build data for serialization
	$aData = array(
		'USR' => 'andriy.leshchuk@gmail.com',
		'PASSWD' => md5($rrPassword), // is the MD5 encrypted user password
		'DEVICEID' => $deviceId,
		'PCATEGORY' => 'RocketRoute',
		'APPMD5' => $apiAccessCode //is the MD5 encrypted password of the API access code.
	);
	
	//create XML
	$serializedData = $app['serializer']->serialize($aData, 'xml',
											array(	'xml_encoding' => 'UTF-8',
													'xml_root_node_name' => 'AUTH',
											)
										);
*/	
	return $app['twig']->render('index.html.twig', array(
//		'authXML' => $serializedData,
//		'icaoCodes' => $icaoCodes
	));
});

/**
 * Call RocketRoute server by passed ICAO code to retrieve required data.
 * 
 * @param object $app the Application object
 * @param string $code
 * 
 * @return string simple XML
 */
function getNOTAMForCode($app, $code){
	$rrPassword = '123456';
	$aData = array(
		'USR' => 'andriy.leshchuk@gmail.com',
		'PASSWD' => md5($rrPassword),
		'ICAO' => strtoupper($code)
	);
	//connect to RR
	$client = new SoapClient('https://apidev.rocketroute.com/notam/v1/service.wsdl');
	
	//create XML
	$serializedData = $app['serializer']->serialize($aData, 'xml',
											array(	'xml_encoding' => 'UTF-8',
													'xml_root_node_name' => 'REQNOTAM',
											)
										);
	
	//get data
	$notamData = $client->getNotam($serializedData);

	return $notamData;
}

/**
 * Parse by regular expresssions the NOTAM coordinates
 * 
 * @param string $nGeolocation NOTAM location string
 * @return array (lat, lng)
 */
function parseLocation($nGeolocation){
	$gData = $glData = array();
	//get string with geolocation
	$gDataExists = preg_match('/[0-9NSEW]+$/', $nGeolocation, $gData );
	if (!$gDataExists && !empty($gData[0])) {
		return false;
	}
	//parse geolocation to google acceptable lat/lng
	preg_match('/(\d+)(N|S)(\d+)(E|W)/', $gData[0], $glData );
	//decompose an array
	list(, $latitude, $nsValue, $longitude, $ewValue) = $glData;
	//get degrees and minutes from Lat,Lng
	$latDeg = substr((string)$latitude, 0, 2);
	$latMin = substr((string)$latitude, 2);
	$lngDeg = substr((string)$longitude, 0, 3);
	$lngMin = substr((string)$longitude, 3);
	//parse degrees to plain gMap format
	$gLatitude = DMS2Decimal($latDeg, $latMin, 0, strtolower($nsValue));
	$gLongitude = DMS2Decimal($lngDeg, $lngMin, 0, strtolower($ewValue));

	$result = array(
		'lat' => $gLatitude,
		'lng' => $gLongitude,
	);
	return $result;
}

/**
 * Make call to Rocket Route API and return NOTAM data.
 * 
 * @return string JSON data => (lat, lng, message)
 */
$app->get('/getNotam/{_code}', function($_code, Silex\Application $app) {
	//default response holder
	$response = array(
		'success' => 0,
		'data' => 'Unexpected error happened.'
	);
	
	$notamData = getNOTAMForCode($app, $_code);
	//make it as an object
	$oNotam = simplexml_load_string($notamData);
	
	//return as XML, for debugging issue
//	return new Response($notamData, 200,  array(
//        "Content-Type" => $app['request']->getMimeType('xml')
//    ));
	
	if ('0' != (string)$oNotam->RESULT) {
		$response['data'] = 'API returned an error: '.(string)$oNotam->MESSAGE.'.';
        return $app->json($response);
	}
	
	$notamToJsonArray = array();
	
	//build array of NOTAM data
	foreach ($oNotam->NOTAMSET->NOTAM as $nData) {
		$nGeolocation = (string)$nData->ItemQ; //string 'EGTT/QWELW/IV/BO /AW /000/999/5129N00009W'
		if (!empty($nGeolocation)) {
			$locData = parseLocation($nGeolocation);
			if ($locData) {
				$notamToJsonArray[] = array(
					'lat' => $locData['lat'],
					'lng' => $locData['lng'],
					'message' => (string)$nData->ItemE
				);
			}
		}
	}
	//when nothing found, return an error
	if (empty($notamToJsonArray)) {
		$response['data'] = 'No active NOTAM information was found for this airport. Please try another one.';
        return $app->json($response);
	}
	
	$response = array(
		'success' => 1,
		'data' => $notamToJsonArray
	);
	
	return $app->json($response);
})->assert('_code', '[a-zA-Z]{4}');


$app->run();
