# Etix Public Events API Wrapper (PHP)

This is an Object Oriented interface to the Etix Public Activites API.  You will need a valid [API Key](https://boxoffice.etix.com/ticket/admin/userAdmin/apiKeys.vw) for your user.

A tip of the hat to the excellent [php-curl-class](https://github.com/php-curl-class/php-curl-class) on which this is based.

### Installation

This package is prepared with [Composer](https://getcomposer.org/), start by resolving dependencies:

    $ composer install

Keep things like the autoloader and libraries up to date:

    $ composer update

### Testing

PHPUnit tests are included, to run them against our test environment use the following command:

    $ ./vendor/bin/phpunit test/

### Examples
In your code include Composers autoloader before invoking the ```Etix\Api``` classes.

```php
require_once 'vendor/autoload.php';
use Etix\Api;
```
Retrieve events for a single venue:
```php
$etix = new Etix\Api\Connection();
$etix->addVenue( 1234 ); // Venue ID
$events = $etix->fetch();

foreach( $events as $event ) {
  echo "[{$event->id}] {$event->name} at {$event->venue->name}";
}
// Result:
// [54321] Test Events at The Test Area
```

