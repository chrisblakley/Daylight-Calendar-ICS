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
$length = ( isset($_GET['length']) )? intval($_GET['length']) : 15;
$gmt_math = ($gmt*3600)*-1;

$syracuse = ( $lat == 43.0469 && $lng == -76.1444 ) ? 1: 0;
if ( $syracuse ){
	date_default_timezone_set('America/New_York'); //This is only used for the "Last Updated" date
}

?>
BEGIN:VCALENDAR<?php echo "\r\n"; ?>
VERSION:2.0<?php echo "\r\n"; ?>
PRODID:-//hacksw/handcal//NONSGML v1.0//EN<?php echo "\r\n"; //Could this be updated to Gearside? PRODID:-//Gearside Creative//Daylight//EN ?>
CALSCALE:GREGORIAN<?php echo "\r\n"; ?>
METHOD:PUBLISH<?php echo "\r\n"; ?>
X-WR-CALNAME:Gearside - Daylight<?php echo "\r\n"; ?>
<?php
$date = $year-1 . '-01-01'; //Subtract one year so it can carry over at the end of the year/beginning of the year (this messes up leap years, so refer to conditional at the very bottom).
while ( strtotime($date) <= strtotime($year-1 . '-12-31') || strtotime($date) == strtotime($year . '-02-29') ): //The or statement is just for leap days
	$events = array(
		'sunrise' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Sunrise'
		),
		'sunset' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Sunset'
		),
		'civil morning' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Civil Twilight'
		),
		'civil evening' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Civil Twilight'
		),
		'nautical morning' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Nautical Twilight'
		),
		'nautical evening' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Nautical Twilight'
		),
		'astronomical morning' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Astronomical Twilight'
		),
		'astronomical evening' => array(
			'start' => 0,
			'end' => 0,
			'length' => 0,
			'name' => 'Astronomical Twilight'
		)
	);

	//Need to do this separate from and after the creation of the above array so it can self-reference other keys
	if ( isset($_GET['actual']) || isset($_GET['all']) ){
		$events['sunrise']['start'] = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 90.83, $gmt));
		$events['sunset']['start'] = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 90.83, $gmt));
		$events['sunrise']['length'] = $length*60; //Minutes in seconds (Default: 15 minutes)
		$events['sunset']['length'] = $length*60; //Minutes in seconds (Default: 15 minutes)
		$events['sunrise']['end'] = $events['sunrise']['start']+$events['sunrise']['length'];
		$events['sunset']['end'] = $events['sunset']['start']+$events['sunset']['length'];
	}

	if ( isset($_GET['civil']) || isset($_GET['all']) ){
		$events['civil morning']['start'] = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 96, $gmt));
		$events['civil evening']['start'] = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 96, $gmt));
		$events['civil morning']['length'] = $events['sunrise']['start']-$events['civil morning']['start'];
		$events['civil evening']['length'] = $events['civil evening']['start']-$events['sunset']['start'];
		$events['civil morning']['end'] = $events['civil morning']['start']+$events['civil morning']['length'];
		$events['civil evening']['end'] = $events['civil evening']['start']+$events['civil evening']['length'];
	}

	if ( isset($_GET['nautical']) || isset($_GET['all']) ){
		$events['nautical morning']['start'] = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 102, $gmt));
		$events['nautical evening']['start'] = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 102, $gmt));
		$events['nautical morning']['length'] = $events['civil morning']['start']-$events['nautical morning']['start'];
		$events['nautical evening']['length'] = $events['nautical evening']['start']-$events['civil evening']['start'];
		$events['nautical morning']['end'] = $events['nautical morning']['start']+$events['nautical morning']['length'];
		$events['nautical evening']['end'] = $events['nautical evening']['start']+$events['nautical evening']['length'];
	}

	if ( isset($_GET['astronomical']) || isset($_GET['all']) ){
		$events['astronomical morning']['start'] = strtotime($date . '+1 year ' . date_sunrise(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 108, $gmt));
		$events['astronomical evening']['start'] = strtotime($date . '+1 year ' . date_sunset(strtotime($date), SUNFUNCS_RET_STRING, $lat, $lng, 108, $gmt));
		$events['astronomical morning']['length'] = $events['nautical morning']['start']-$events['astronomical morning']['start'];
		$events['astronomical evening']['length'] = $events['astronomical evening']['start']-$events['nautical evening']['start'];
		$events['astronomical morning']['end'] = $events['astronomical morning']['start']+$events['astronomical morning']['length'];
		$events['astronomical evening']['end'] = $events['astronomical evening']['start']+$events['astronomical evening']['length'];
	}

	$dst = ( date('I', strtotime($date . '+1 year +12 hours')) ) ? 1 : 0;

	$last_sync = ( $date == date('Y-m-d', strtotime('Today -1 Year')) && 1==2 ) ? ' [Last Sync]' : '';

	if ( $debug == 1 || array_key_exists('debug', $_GET) ){
		echo "\r\n\r\n------------------\r\n";
		echo ( $date == date('Y-m-d', strtotime('Today -1 Year')) ) ? "(Today!) " : "";
		echo "Debug Info\r\n";
		echo "Last Modified: " . date('l, F j, Y', filemtime(__FILE__)) . "\r\n";
		echo "Date (-1 Year): " . $date . "\r\n";
		echo "Timezone: Requested: " . $gmt . ", Server: " . date_default_timezone_get() . "\r\n";
		echo ( $dst ) ? "DST?: Yes\r\n" : "DST?: No\r\n";

		foreach ( $events as $event ){
			if ( $event['start'] === 0 ){
				continue; //Skip any events that do not have data
			}

			echo $event['name'] . ": " . date('g:ia', strtotime(date('F j Y g:ia', $event['start']) . ' +' . $dst . ' hours')) . ' to ' . date('g:ia', strtotime(date('F j Y g:ia', $event['end']) . ' +' . $dst . ' hours')) . "\r\n\r\n";
		}

		echo "\r\n";
	}
?>
<?php foreach( $events as $event ): //Need to make 6 events per day ?>
<?php
	if ( $event['start'] === 0 ){
		continue; //Skip any events that do not have data
	}
?>
BEGIN:VEVENT<?php echo "\r\n"; ?>
CREATED:<?php echo dateToCal(strtotime($date)) . "\r\n"; ?>
DTSTART:<?php echo dateToCal($event['start']+$gmt_math) . "\r\n"; ?>
DTEND:<?php echo dateToCal($event['end']+$gmt_math) . "\r\n"; ?>
DTSTAMP:<?php echo dateToCal(time()) . "\r\n"; ?>
LAST-MODIFIED:<?php echo dateToCal(filemtime(__FILE__)) . "\r\n"; ?>
UID:<?php echo md5(uniqid(mt_rand(), true)) . "@gearside.com" . "\r\n"; ?>
DESCRIPTION:<?php echo escapeString('Sun calendar by Gearside.com') . "\r\n"; //This is for additional information ?>
URL;VALUE=URI:<?php echo escapeString('http://gearside.com/calendars/sun.ics') . "\r\n"; ?>
SUMMARY:<?php echo escapeString($event['name'] . $last_sync) . "\r\n"; //Shows up in the title of the event ?>
RRULE:FREQ=YEARLY;COUNT=3<?php echo "\r\n"; ?>
END:VEVENT<?php echo "\r\n"; ?>
<?php endforeach; ?>
<?php
	if ( $date == $year-1 . '-02-28' && date('L', strtotime($year . '-02-29')) ){ //If is Feb 28th and if tomorrow is a leap day
		$date = date("Y-m-d", strtotime($year . '-02-29')); //Set the year to the current year (rather than the previous year)
	} elseif ( $date == $year . '-02-29' ){ //If this *is* leap day
		$date = date("Y-m-d", strtotime($year-1 . '-03-01')); //Set the year back to the previous year on March 1
	} else {
		$date = date("Y-m-d", strtotime("+1 day", strtotime($date))); //Increment the date as normal
	}
endwhile; ?>
END:VCALENDAR<?php echo "\r\n"; ?>
<?php die; ?>

X-WR-CALDESC:Visualize daylight<?php echo "\r\n"; ?>