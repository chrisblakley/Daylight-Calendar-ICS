<?php
/*
	ICS Validator: http://severinghaus.org/projects/icv/
	Another Validator: http://icalvalid.cloudapp.net/

	//Google Calendar updates every 12 hours (noticed at 9:30am, 9:30pm, 1:00pm, 2:30am).
*/

function is_debug(){
	if ( 1==2 ){ //Use this to force debug mode for everyone
		return true;
	}

	if ( array_key_exists('debug', $_GET) ){ //Test it like this: https://gearside.com/calendars/daylight.php?debug
		return true;
	}

	return false;
}

//Show Errors when debugging
if ( is_debug() ){
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	ini_set('track_errors', 1);
	ini_set('html_errors', 1);
	error_reporting(E_ALL);
}

//Use and ICS file type when not debugging
if ( !is_debug() ){
	header('Content-type: text/calendar; charset=utf-8');
	header('Content-Disposition: attachment; filename=gearside_daylight.ics');
}

//Use this to add a notice at the top of each calendar item's description
$global_notice = "";
//$global_notice = "⚠️ We are working through known issues and improving the accuracy. Apologies for temporary problems.";

//Convert a timestamp integer to a ISO formated UTC timestamp
function iso_date_format($local_timestamp) {
	$given = new DateTime(date('Y-m-d H:i:s', $local_timestamp)); //Create the datetime for the local timezone. Use this format: "2014-12-12 14:18:00"
	$given->setTimezone(new DateTimeZone("UTC")); //Now update the timezone to UTC
	$utc_timestamp = $given->format('Ymd\THis\Z') . "\n"; //Output the UTC timestamp in ISO standard format
	return $utc_timestamp;
}

//Escape strings
function escape_string($string) {
	return preg_replace('/([\,;])/','\\\$1', $string);
}

$year = ( isset($_GET['year']) )? intval($_GET['year']) : date('Y');
$lat = ( isset($_GET['lat']) )? floatval($_GET['lat']) : 43.0469;
$lng = ( isset($_GET['lng']) )? floatval($_GET['lng']) : -76.1444;

$timezone = 'America/New_York'; //Default to American Eastern timezone
if ( isset($_GET['timezone']) ){ //If a timezone name is provided, use it
	$timezone = $_GET['timezone'];
} elseif ( isset($_GET['gmt']) ){ //If a GMT offset is provided, try to find an equivalent timezone
	$timezone_list = DateTimeZone::listIdentifiers(); //Get the list of all timezones
	foreach ( $all_timezones as $timezone_to_check ){ //Loop through all of the timezones
		$tz = new DateTimeZone($timezone_to_check);
		$offset = $tz->getOffset(new DateTime()); //Get the offset in seconds from UTC for the timezone
		$offset_hours = $offset/3600; //Convert the offset to hours
		if ( $offset_hours == intval($_GET['gmt']) ){ //If the offset hours matches the GMT offset, use that timezone
			$timezone = $timezone_to_check;
			break; //Exit the loop
		}
	}
}

date_default_timezone_set($timezone); //Now use the timezone that was determined above

function is_syracuse($lat=false, $lng=false){
	if ( isset($_GET['syracuse']) ){
		return true;
	}

	if ( $lat == 43.0469 && $lng == -76.1444 ){ //Could make this a little more flexible with >= <=
		return true;
	}

	return false;
}

//Shortest Day Length
$shortest_date = $year . '-12-21';
$shortest_sun_info = date_sun_info(strtotime($shortest_date), $lat, $lng);
$shortest_sunrise = $shortest_sun_info['sunrise'];
$shortest_sunset = $shortest_sun_info['sunset'];
$shortest_length = $shortest_sunset-$shortest_sunrise;

//Longest Day Length
$longest_date = $year . '-06-21';
$longest_sun_info = date_sun_info(strtotime($longest_date), $lat, $lng);
$longest_sunrise = $longest_sun_info['sunrise'];
$longest_sunset = $longest_sun_info['sunset'];
$longest_length = $longest_sunset-$longest_sunrise;

//Current weather forecast
$weather_forecast = array();
$weather_forecast_summary = '';
if ( is_syracuse($lat, $lng) ){
	//Weather Forecast
	$weather_json = file_get_contents('/home/gearside/public_html/weather.gearside.com/v2/data/weather-owm.json');
	if ( !empty($weather_json) ){
		$weather_forecast_data = json_decode($weather_json);
		if ( is_string($weather_forecast_data) ){
			$weather_forecast_data = json_decode($weather_forecast_data); //Decode it again if it is not yet an array. Dunno why I need to do this but it is necessary.
		}

		foreach ( $weather_forecast_data->daily as $forecast_day ){
			$weather_forecast['day' . date('Ymd', $forecast_day->dt)] = array(
				'date' => $forecast_day->dt, //UTC time
				'high' => round($forecast_day->temp->max) . '°F',
				'low' => round($forecast_day->temp->min) . '°F',
				'feels_like' => round($forecast_day->feels_like->day) . '°F', //Not using this because there is not a "max", and "day" is not equivalent to be accurate
				'humidity' => round($forecast_day->humidity) . '%', //Percent
				'wind' => ( $forecast_day->wind_speed > 18 )? round($forecast_day->wind_speed) . 'mph' : '', //Already in MPH from source. Only show if it is high
				'pop' => round(($forecast_day->pop)*100), //Percent
				//'pop_type' => '', //This is not explicitly provided. Would need to do the math on  ->rain vs. ->snow
				'snow_accumulation' => ( !empty($forecast_day->snow) && $forecast_day->snow*0.0393701 > 0.2 )? round($forecast_day->snow*0.0393701, 1) . '"' : '', //Convert millimeters to inches. Only show if more than 1/4"
				'forecast' => ucwords($forecast_day->weather[0]->main),
				'description' => ucwords($forecast_day->weather[0]->description),
				'icon' => get_weather_emoji($forecast_day->weather[0]->main, $forecast_day->weather[0]->description, $forecast_day->temp->max) //Combine the main, summary, and high temperature to figure out the icons
			);
		}
	}
}

//Determine emoji icon based on forecast summary
//https://emojipedia.org/search/?q=weather
function get_weather_emoji($main='', $description='', $high=false){
	$forecast = strtolower($main) .' ' . strtolower($description);

	$emoji_icon = '';
	if ( strpos($forecast, 'mostly') !== false || strpos($forecast, 'partly') !== false ){
		$emoji_icon = '⛅';
	} elseif ( strpos($forecast, 'cloud') !== false || strpos($forecast, 'fog') !== false ){
		$emoji_icon = '☁️';
	} elseif ( strpos($forecast, 'sunny') !== false || strpos($forecast, 'clear') !== false ){
		$emoji_icon = '☀️';
	} elseif ( strpos($forecast, 'snow') !== false ){
		$emoji_icon = '❄️';
	} elseif ( strpos($forecast, 'rain') !== false || strpos($forecast, 'shower') !== false ){
		$emoji_icon = '🌧️';
	} elseif ( strpos($forecast, 'storm') !== false ){
		$emoji_icon = '⛈️';
	} elseif ( strpos($forecast, 'wind') !== false ){
		$emoji_icon = '🌬️';
	}

	$rounded_high = round($high);
	if ( !empty($rounded_high) ){
		if ( $rounded_high >= 90 ){
			$emoji_icon .= '🥵';
		} elseif ( $rounded_high >= 75 ){
			$emoji_icon .= '🔴';
		} elseif ( $rounded_high >= 60 ){
			$emoji_icon .= '🟠';
		} elseif ( $rounded_high >= 45 ){
			$emoji_icon .= '🟣';
		} elseif ( $rounded_high >= 32 ){
			$emoji_icon .= '🔵';
		} elseif ( $rounded_high < 32 ){
			$emoji_icon .= '🥶';
		}
	}

	return $emoji_icon;
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
	$timestamp = strtotime($date);
	$next_year_timestamp = strtotime('+1 year', $timestamp);
	$sun_info = date_sun_info($next_year_timestamp, $lat, $lng);

	$sunrise = $sun_info['sunrise'];
	$sunset = $sun_info['sunset'];

	$sunrise_civil = $sun_info['civil_twilight_begin'];
	$sunrise_nautical = $sun_info['nautical_twilight_begin'];
	$sunrise_astronomical = $sun_info['astronomical_twilight_begin'];
	$sunset_civil = $sun_info['civil_twilight_end'];
	$sunset_nautical = $sun_info['nautical_twilight_end'];
	$sunset_astronomical = $sun_info['astronomical_twilight_end'];

	$length = $sunset-$sunrise;
	$solar_noon = $sunrise+($length/2);

	$percent = ($length*100)/86400;

	list($hours, $minutes) = explode(':', date('G:i', $sunrise));
	$startTimestamp = mktime($hours, $minutes);

	list($hours, $minutes) = explode(':', date('G:i', $sunset));
	$endTimestamp = mktime($hours, $minutes);

	$seconds = $endTimestamp-$startTimestamp;
	$minutes = ($seconds/60)%60;
	$hours = floor($seconds/(60*60));

	$length_percentile = 0;
	if ( $longest_length-$shortest_length > 0 ){ //Prevent division by 0
		$length_percentile = round((($length-$shortest_length)*100)/($longest_length-$shortest_length), 1);
	}

	//Add the weather info to the summary if it matches the date correctly
	$weather_forecast_summary = ''; //Empty this variable each time
	$weather_icon = ''; //Default emoji icon.
	if ( !empty($weather_forecast) ){ //If we have weather data
		$this_weather_day = 'day' . date('Ymd', strtotime($date . '+1 year '));
		if ( !empty($weather_forecast[$this_weather_day]) ){ //If we have weather data for this specific day
			$weather_icon = $weather_forecast[$this_weather_day]['icon'];
			$weather_forecast_summary = ' ' . $weather_forecast[$this_weather_day]['forecast'] . ' (High: ' . $weather_forecast[$this_weather_day]['high'] . ', Low ' . $weather_forecast[$this_weather_day]['low'] . ').';

			if ( !empty($weather_forecast[$this_weather_day]['pop']) && $weather_forecast[$this_weather_day]['pop'] != 0 ){
				$precip_icon = ( $weather_forecast[$this_weather_day]['pop'] >= 90 )? '💦' : '';
				$weather_forecast_summary .= ' ' . $precip_icon . ' ' . $weather_forecast[$this_weather_day]['pop'] . '% chance of precipitation.';
			}

			if ( !empty($weather_forecast[$this_weather_day]['wind']) ){
				$weather_forecast_summary .= ' ⚠️ Windy (' . $weather_forecast[$this_weather_day]['wind'] . ').';
			}

			if ( !empty($weather_forecast[$this_weather_day]['snow_accumulation']) ){
				$weather_forecast_summary .= ' ⚠️ ' . $weather_forecast[$this_weather_day]['snow_accumulation'] . ' Snow.';
			}
		}
	}

	//Only show the solar noon time if weather is unavailable (to avoid clutter).
	$solar_noon_summary = '';
	if ( empty($weather_forecast_summary) ){
		$solar_noon_summary = 'Solar noon: ' . date('g:ia', intval($solar_noon)) . '.';
	}

	if ( is_debug() ){
		echo "\r\n------------------\r\n";
		echo ( $date == date('Y-m-d', strtotime('Today -1 Year')) ) ? "(Today!) " : "";
		echo "Debug Info\r\n";
		echo "Last Modified: " . date('l, F j, Y', filemtime(__FILE__)) . "\r\n";
		echo "Date (-1 Year): " . $date . "\r\n";
		echo "Timezone Used: " . $timezone . "\r\n";
		echo "Sunrise: " . $sunrise . "\r\n";
		echo "Sunset: " . $sunset . "\r\n";
		echo "Daylight: " . date('l, F j Y, g:ia', $sunrise) . ' to ' . date('l, F j Y, g:ia', $sunset) . "\r\n";
		echo "Length: " . $hours . "h " . $minutes . "m (" . round($percent, 1) . "%) of daylight\r\n";
		echo "Solar Noon: " . date('g:ia', intval($solar_noon)) . "\r\n";
		if ( is_syracuse($lat, $lng) ) {
			echo "Syracuse Detected! This request is eligible for a weather forecast!\r\n";
		}
		echo "Civil: " . date('g:ia', $sunrise_civil) . ' to ' . date('g:ia', $sunset_civil) . " (There is enough natural sunlight that artificial light may not be required to carry out human activities.)\r\n";
		echo "Nautical: " . date('g:ia', $sunrise_nautical) . ' to ' . date('g:ia', $sunset_nautical) . " (The point at which the horizon stops being visible at sea)\r\n";
		echo "Astronomical: " . date('g:ia', $sunrise_astronomical) . ' to ' . date('g:ia', $sunset_astronomical) . " (The point when Sun stops being a source of any illumination)\r\n";
		echo "\r\nShortest day this year: " . $shortest_length . "\r\n";
		echo "Longest day this year: " . $longest_length . "\r\n";
		echo "This day length: " . $length . "\r\n";
		echo $length_percentile . " Percentile\r\n";
		echo ( !empty($weather_forecast_summary) )? $weather_forecast_summary : '(No forecast for this date)';

		echo "\r\n";
	}
?>
BEGIN:VEVENT<?php echo "\r\n"; ?>
CREATED:<?php echo iso_date_format(strtotime($date)) . "\r\n"; ?>
DTSTART:<?php echo iso_date_format($sunrise) . "\r\n"; ?>
DTEND:<?php echo iso_date_format($sunset) . "\r\n"; ?>
DTSTAMP:<?php echo iso_date_format(time()) . "\r\n"; ?>
LAST-MODIFIED:<?php echo iso_date_format(filemtime(__FILE__)) . "\r\n"; ?>
UID:<?php echo md5($date . "@gearside.com") . "\r\n"; ?>
DESCRIPTION:<?php echo escape_string(
	$global_notice . " \\n\\n" .
	"Daylight: " . date('g:ia', $sunrise) . ' to ' . date('g:ia', $sunset) . " \\n\\n" .
	"Length: " . $hours . "h " . $minutes . "m (" . round($percent, 1) . "%) of daylight (" . $length_percentile . " percentile)\\n\\n" .
	$solar_noon_summary . "\\n\\n" .
	$weather_forecast_summary . "\\n\\n" .
	"Civil Twilight: " . date('g:ia', $sunrise_civil) . ' to ' . date('g:ia', $sunset_civil) . "\\n(There is enough natural sunlight that artificial light may not be required to carry out human activities.)\\n\\n" .
	"Nautical Twilight: " . date('g:ia', $sunrise_nautical) . ' to ' . date('g:ia', $sunset_nautical) . "\\n(The point at which the horizon stops being visible at sea)\\n\\n" .
	"Astronomical Twilight: " . date('g:ia', $sunrise_astronomical) . ' to ' . date('g:ia', $sunset_astronomical) . "\\n(The point when Sun stops being a source of any illumination)\\n\\n" .
	'[Last Updated: ' . date('l, F j, Y, g:ia') . '] \\n\\n' .
	"Calendar by Gearside.com") . "\r\n"; //This is for additional information ?>
URL;VALUE=URI:<?php echo escape_string('http://gearside.com/calendars/daylight.ics') . "\r\n"; ?>
SUMMARY:<?php echo $weather_icon . ' ' . escape_string($hours . 'h ' . $minutes . 'm (' . round($percent, 1) . '%) [' . $length_percentile . ' Percentile]. ' . $weather_forecast_summary) . "\r\n"; //Shows up in the title of the event ?>
RRULE:FREQ=YEARLY;COUNT=3<?php echo "\r\n"; ?>
END:VEVENT<?php echo "\r\n"; ?>
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