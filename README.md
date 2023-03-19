# Daylight Calendar ICS

This is a dynamically generated .ics calendar that you can host and subscribe to in Google Calendar, iCal, or other calendar software.

Not only will it provide an event each day with the appropriate sunrise and sunset time, it will show the length of the day in hours/minutes as well as in a percent (of 24 hours) and the solar noon for that day. It will also give a percentile compared to the shortest and longest days of the year!

## Options

- [Find your geo coordinates](http://mygeoposition.com/)
- [Find your GMT offset](http://en.wikipedia.org/wiki/List_of_UTC_time_offsets#mediaviewer/File:World_Time_Zones_Map.png)
    
## Instructions

- Upload `daylight.php` and/or `sun.php` to your server (or skip this step and use the one hosted on [gearside.com](https://gearside.com/calendars/daylight.php))
- Point your calendar to the file and use query parameters for the options above.
  - Latitude: `lat`
  - Longitude: `lng`
  - GMT: `gmt`
  - Timezone: `timezone`
  - Year: `year`
  - Event types (`sun.php` only):
    - `actual`
    - `civil`
    - `nautical`
    - `astronomical`
    - `all`

Use `?debug` to directly view the calendar file in a browser with events more easily readable. Be sure not to use `?debug` when subscribing to your calendar as it does not declare itself as an .ics file with that parameter present.

## Examples

#### Basic

Most reliable method:
`https://gearside.com/calendars/daylight.php?lat=43.1234&lng=-76.1234&timezone=America/New_York`

Your mileage may vary if only passing GMT offset:
`https://gearside.com/calendars/daylight.php?lat=43.1234&lng=-76.1234&gmt=-5`

`https://gearside.com/calendars/sun.php?lat=43.1234&lng=-76.1234&gmt=-5&all`

## Notes

Calendar software caches remote .ics files (like this one), so when replacing it you can "bust" the cache by adding another query parameter of random characters such as `&sdfgsfd`.

- [More information available at Gearside.com](https://gearside.com/google-daylight-calendar/)
