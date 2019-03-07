<?php
/*
	ICS Validator: http://severinghaus.org/projects/icv/
	Another Validator: http://icalvalid.cloudapp.net/

	My favorite time of day is 11-14 minutes after sunset (when calculated with zenith 90.83). The beautiful sunset is just finishing up with its deepest colors and streetlights and other man-made lights are on.
	This is just about (slightly before) halfway between sunset as calculated above and civil sunset (zenith of 96).

	//Google Calendar updates every 12 hours (noticed at 9:30am, 9:30pm, 1:00pm, 2:30am).
*/

$debug = 0; //Enable forced debug mode here

if ( $debug == 0 && !array_key_exists('debug', $_GET) ){
	header('Content-type: text/calendar; charset=utf-8');
	header('Content-Disposition: attachment; filename=gearside_daylight.ics');
}

function dateToCal($timestamp) {
	return date('Ymd\THis\Z', $timestamp); // 'Ymd\THis\Z' for UTC time
}

function escapeString($string) {
	return preg_replace('/([\,;])/','\\\$1', $string);
}

$year = ( isset($_GET['year']) )? intval($_GET['year']) : date('Y');
$lat = ( isset($_GET['lat']) )? floatval($_GET['lat']) : 43.0469;
$lng = ( isset($_GET['lng']) )? floatval($_GET['lng']) : -76.1444;
$gmt = ( isset($_GET['gmt']) )? intval($_GET['gmt']) : -5;
$gmt_math = ($gmt*3600)*-1;

$syracuse = ( $lat == 43.0469 && $lng == -76.1444 ) ? 1: 0;
if ( $syracuse ){
	date_default_timezone_set('America/New_York'); //This is only used for the "Last Updated" date
}

if ( isset($_GET['z']) ) {
	if ( is_numeric($_GET['z']) ) {
		$zenith = intval($_GET['z']);
	} else {
		switch ( strtolower($_GET['z']) ) {
			case ('civil') :
			case ('civiltwilight') :
			case ('civil_twilight') :
			case ('civil%20twilight') :
			case ('civil-twilight') :
				$zenith = 96; //Conventionally used to signify twilight
				break;
			case ('nautical') :
			case ('nauticaltwilight') :
			case ('nautical_twilight') :
			case ('nautical%20twilight') :
			case ('nautical-twilight') :
				$zenith = 102; //The point at which the horizon stops being visible at sea.
				break;
			case ('astronomical') :
			case ('astronomicaltwilight') :
			case ('astronomical_twilight') :
			case ('astronomical%20twilight') :
			case ('astronomical-twilight') :
				$zenith = 108; //The point when Sun stops being a source of any illumination.
				break;
			default :
				$zenith = 90+50/60; //The official zenith is 90+(50/60) degrees for true sunrise/sunset
				break;
		}
	}
} else {
	$zenith = 90.83; //The official zenith is 90+(50/60) degrees for true sunrise/sunset
}

//Shortest Day Length
$shortest_date = $year . '-12-21';
$shortest_sunrise = strtotime($shortest_date . ' ' . date_sunrise(strtotime($shortest_date), SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $gmt));
$shortest_sunset = strtotime($shortest_date . ' ' . date_sunset(strtotime($shortest_date), SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $gmt));
$shortest_length = $shortest_sunset-$shortest_sunrise;

//Longest Day Length
$longest_date = $year . '-06-21';
$longest_sunrise = strtotime($longest_date . ' ' . date_sunrise(strtotime($longest_date), SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $gmt));
$longest_sunset = strtotime($longest_date . ' ' . date_sunset(strtotime($longest_date), SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $gmt));
$longest_length = $longest_sunset-$longest_sunrise;

//Current weather forecast
$weather_forecast = array();
$weather_forecast_summary = '';
if ( $syracuse ){
	//Weather Forecast Lookup (Yahoo Weather API)
	function buildBaseString($baseURI, $method, $params){
		$r = array();
		ksort($params);
		foreach( $params as $key => $value ){
			$r[] = "$key=" . rawurlencode($value);
		}
		return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
	}

	function buildAuthorizationHeader($oauth){
		$r = 'Authorization: OAuth ';
		$values = array();
		foreach( $oauth as $key=>$value ){
			$values[] = "$key=\"" . rawurlencode($value) . "\"";
		}
		$r .= implode(', ', $values);
		return $r;
	}

	$url = 'https://weather-ydn-yql.media.yahoo.com/forecastrss';
	$app_id = 'DeJXrq6s';
	$consumer_key = 'dj0yJmk9TUdkTURIclp5VFB6JnM9Y29uc3VtZXJzZWNyZXQmc3Y9MCZ4PTU2'; //Client ID
	$consumer_secret = '624b553e212c750ddfa5212d5442282b2102cd26'; //Client Secret
	$query = array('location' => 'syracuse,ny', 'format' => 'json');

	$oauth = array(
		'oauth_consumer_key' => $consumer_key,
		'oauth_nonce' => uniqid(mt_rand(1, 1000)),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_timestamp' => time(),
		'oauth_version' => '1.0'
	);

	$base_info = buildBaseString($url, 'GET', array_merge($query, $oauth));
	$composite_key = rawurlencode($consumer_secret) . '&';
	$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
	$oauth['oauth_signature'] = $oauth_signature;
	$header = array(buildAuthorizationHeader($oauth), 'X-Yahoo-App-Id: ' . $app_id);

	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_HTTPHEADER => $header,
		CURLOPT_HEADER => false,
		CURLOPT_URL => $url . '?' . http_build_query($query),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false
	));
	$response = curl_exec($ch);
	curl_close($ch);

	$weather_forecast_data = json_decode($response);
	foreach ( $weather_forecast_data->forecasts as $forecast_day ){
		$weather_forecast['day' . date('Ymd', $forecast_day->date)] = array(
			'date' => $forecast_day->date, //UTC time
			'high' => $forecast_day->high . '°F',
			'low' => $forecast_day->low . '°F',
			'forecast' => ucwords($forecast_day->text)
		);
	}
}

?>
BEGIN:VCALENDAR<?php echo "\r\n"; ?>
VERSION:2.0<?php echo "\r\n"; ?>
PRODID:-//hacksw/handcal//NONSGML v1.0//EN<?php echo "\r\n"; //Could this be updated to Gearside? PRODID:-//Gearside Creative//Daylight//EN ?>
CALSCALE:GREGORIAN<?php echo "\r\n"; ?>
METHOD:PUBLISH<?php echo "\r\n"; ?>
X-WR-CALNAME:Gearside - Daylight<?php echo "\r\n"; ?>
<?php
$date = $year-1 . '-01-01'; //Subtract one year so it can carry over at the end of the year/beginning of the year.
while (strtotime($date) <= strtotime($year-1 . '-12-31') ) :
	$sunrise = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $gmt));
	$sunset = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $gmt));

	if ( $syracuse ) {
		$sunrise_civil = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 96, $gmt));
		$sunrise_nautical = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 102, $gmt));
		$sunrise_astronomical = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 108, $gmt));
		$sunset_civil = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 96, $gmt));
		$sunset_nautical = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 102, $gmt));
		$sunset_astronomical = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 108, $gmt));
	}

	$length = $sunset-$sunrise;
	$noon = $sunrise+($length/2);

	$percent = ($length*100)/86400;

	list($hours, $minutes) = explode(':', date('G:i', $sunrise));
	$startTimestamp = mktime($hours, $minutes);

	list($hours, $minutes) = explode(':', date('G:i', $sunset));
	$endTimestamp = mktime($hours, $minutes);

	$seconds = $endTimestamp-$startTimestamp;
	$minutes = ($seconds/60)%60;
	$hours = floor($seconds/(60*60));

	$dst = ( date('I', strtotime($date . '+1 year +12 hours')) ) ? 1 : 0;

	$solar_noon = ( $dst ) ? $noon+3600 : $noon;

	$length_percentile = 0;
	if ( $longest_length-$shortest_length > 0 ){ //Prevent division by 0
		$length_percentile = round((($length-$shortest_length)*100)/($longest_length-$shortest_length), 1);
	}

	//Add the weather info to the summary if it matches the date correctly
	$weather_forecast_summary = ''; //Empty this variable each time
	if ( !empty($weather_forecast) ){ //If we have weather data
		$this_weather_day = 'day' . date('Ymd', strtotime($date . '+1 year '));
		if ( !empty($weather_forecast[$this_weather_day]) ){ //If we have weather data for this specific day
			$weather_forecast_summary = ' ' . $weather_forecast[$this_weather_day]['forecast'] . ' (High: ' . $weather_forecast[$this_weather_day]['high'] . ', Low ' . $weather_forecast[$this_weather_day]['low'] . ')';
		}
	}

	$last_sync = ( $date == date('Y-m-d', strtotime('Today -1 Year')) && 1==2 ) ? ' [Last Sync]' : '';

	if ( $debug == 1 || array_key_exists('debug', $_GET) ){
		echo "\r\n------------------\r\n";
		echo ( $date == date('Y-m-d', strtotime('Today -1 Year')) ) ? "(Today!) " : "";
		echo "Debug Info\r\n";
		echo "Last Modified: " . date('l, F j, Y', filemtime(__FILE__)) . "\r\n";
		echo "Date (-1 Year): " . $date . "\r\n";
		echo "Timezone: Requested: " . $gmt . ", Server: " . date_default_timezone_get() . "\r\n";
		echo "Daylight: " . date('l, F j Y, g:ia', strtotime(date('F j Y g:ia', $sunrise) . ' +' . $dst . ' hours')) . ' to ' . date('l, F j Y, g:ia', strtotime(date('F j Y g:ia', $sunset) . ' +' . $dst . ' hours')) . "\r\n";
		echo "Length: " . $hours . "h " . $minutes . "m (" . round($percent, 1) . "%) of daylight\r\n";
		echo ( $dst ) ? "DST?: Yes\r\n" : "DST?: No\r\n";
		echo "Solar Noon: " . date('g:ia', $solar_noon) . "\r\n";
		if ( $syracuse ) {
			echo "Syracuse Detected!\r\n";
			echo "Civil: " . date('g:ia', strtotime(date('F j Y g:ia', $sunrise_civil) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $sunset_civil) . ' +' . $dst . ' hours')) . " (There is enough natural sunlight that artificial light may not be required to carry out human activities.)\r\n";
			echo "Nautical: " . date('g:ia', strtotime(date('F j Y g:ia', $sunrise_nautical) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $sunset_nautical) . ' +' . $dst . ' hours')) . " (The point at which the horizon stops being visible at sea)\r\n";
			echo "Astronomical: " . date('g:ia', strtotime(date('F j Y g:ia', $sunrise_astronomical) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $sunset_astronomical) . ' +' . $dst . ' hours')) . " (The point when Sun stops being a source of any illumination)\r\n";
		}
		echo "\r\nShortest day this year: " . $shortest_length . "\r\n";
		echo "Longest day this year: " . $longest_length . "\r\n";
		echo "This day length: " . $length . "\r\n";
		echo $length_percentile . " Percentile\r\n";
		echo ( !empty($weather_forecast_summary) )? $weather_forecast_summary : '(No forecast for this date)';

		echo "\r\n";
	}
?>
BEGIN:VEVENT<?php echo "\r\n"; ?>
CREATED:<?php echo dateToCal(strtotime($date)) . "\r\n"; ?>
DTSTART:<?php echo dateToCal($sunrise+$gmt_math) . "\r\n"; ?>
DTEND:<?php echo dateToCal($sunset+$gmt_math) . "\r\n"; ?>
DTSTAMP:<?php echo dateToCal(time()) . "\r\n"; ?>
LAST-MODIFIED:<?php echo dateToCal(filemtime(__FILE__)) . "\r\n"; ?>
UID:<?php echo md5(uniqid(mt_rand(), true)) . "@gearside.com" . "\r\n"; ?>
<?php if ( $syracuse ) : ?>
DESCRIPTION:<?php echo escapeString(
	"Civil: " . date('g:ia', strtotime(date('F j Y g:ia', $sunrise_civil) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $sunset_civil) . ' +' . $dst . ' hours')) . " (There is enough natural sunlight that artificial light may not be required to carry out human activities.) --- " .
	"Nautical: " . date('g:ia', strtotime(date('F j Y g:ia', $sunrise_nautical) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $sunset_nautical) . ' +' . $dst . ' hours')) . " (The point at which the horizon stops being visible at sea) --- " .
	"Astronomical: " . date('g:ia', strtotime(date('F j Y g:ia', $sunrise_astronomical) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $sunset_astronomical) . ' +' . $dst . ' hours')) . " (The point when Sun stops being a source of any illumination) --- " . ' [Last Updated: ' . date('l, F j, Y g:ia') . '] ' .
	"Calendar by Gearside.com") . "\r\n"; //This is for additional information ?>
<?php else : ?>
DESCRIPTION:<?php echo escapeString('Daylight calendar by Gearside.com') . "\r\n"; //This is for additional information ?>
<?php endif; ?>
URL;VALUE=URI:<?php echo escapeString('http://gearside.com/calendars/daylight.ics') . "\r\n"; ?>
SUMMARY:<?php echo escapeString($hours . 'h ' . $minutes . 'm (' . round($percent, 1) . '%) [' . $length_percentile . ' Percentile]. Solar noon: ' . date('g:ia', $solar_noon) . '. ' . $weather_forecast_summary . $last_sync) . "\r\n"; //Shows up in the title of the event ?>
RRULE:FREQ=YEARLY;COUNT=3<?php echo "\r\n"; ?>
END:VEVENT<?php echo "\r\n"; ?>
<?php
$date = date ("Y-m-d", strtotime("+1 day", strtotime($date)));
endwhile; ?>
END:VCALENDAR<?php echo "\r\n"; ?>
<?php die; ?>

X-WR-CALDESC:Visualize daylight<?php echo "\r\n"; ?>
