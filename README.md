# WRK Dutyschedule Exporter

The Dutyschedule Feed is a PHP Tool that exports your current duties to a subscribable iCal file.
You can use the generated link to subscribe to the calendar feed and always have your duty schedule up to date in your calendar.

## Installation

Clone the git repo to your server and install the composer dependencies with
```bash
composer install
```
afterwards, please copy over the .env.example file and fill the fields.

The webroot of your server has to be set to the \web directory.

To generate the database table for logging, run
```bash
php -f database\migrations.php
```
from the root directory. This will create the log table.
This table does not hold any sensitive data, it just logs the fetched HTML source for each duty and the generated VEVENT string. This helps debugging dutytypes that I don't know.
If the user opts-in to logging, a key will be generated and displayed at the second page. This Key is regenerated every time a user logs in to this page and embedded in the auth hash. This means, that every new login will result in data from the same user with another key. The key itself is not recoverable so you have to take a note of it if you want to check your log entries. 

## Usage
To generate a url to subscribe to, just point your browser to a the location where you are hosting the script. At first use, you have to specify your NIU username and password. This data will then be base64 encoded and appended to the URL of  the main script.
This url is the url you have to specify in your calendar app.

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## License
[Unlicense](https://unlicense.org/)