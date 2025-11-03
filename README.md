# tsumego-hero-app

## Status & Requirements

- composer
- CakePHP 2.x latest
- PHP 8.4
- MySQL 8.0

Also:
- For best PHP dev I recommend [PHPStorm IDE](https://www.jetbrains.com/phpstorm/).
- For local env best to use [ddev](https://ddev.com/get-started/).

##PHP 8.4 install

to install it in the linux (outside docker), you can do:
./setup/php-install.sh

## Debug with phpstorm
https://www.jetbrains.com/help/phpstorm/debugging-with-phpstorm-ultimate-guide.html#setup-from-zero

For command line, this needs to be specified locally
export XDEBUG_MODE=debug& export XDEBUG_SESSION=1


## Setup

To locally develop and setup, use ddev from inside app/ folder:
- Copy .ddev.example/ folder to .ddev/
- Modify the config.yaml file to your needs (shouldn't be needed)

By default, it uses `tsumego` as name.

Then from ROOT of the project run:

    ddev config (ONLY if you didnt copy the existing config.yaml!)
    ddev start

To jump to your app in browser:

    ddev launch

dbs (PHPMyAdmin):

    ddev phpmyadmin

ssh login:

    ddev ssh

Run

    composer update

locally to install all latest dependencies.

Make sure to create your own database.php file in config/ with your DB credentials.
And import any data you need.

Open

    https://tsumego.ddev.site:33003/

to browse your project now.

## Code Quality & Testing
```bash
composer test        # PHPUnit tests
composer cs-check   # PHP CodeSniffer
composer cs-fix     # Auto-fix CS issues
composer stan       # PHPStan static analysis

### also:
composer cs-modified # Only run phpcs on modified files
```

### Development Commands Quick Reference
```bash
# Specific folder analysis
composer stan -- src/Controller
composer cs-check -- src/Utility

# Test specific methods (side ddev container!):
vendor/bin/phpunit path/to/test.php --filter=testMethodName
```

## Deploy
On the server in ROOT:
```
sh deploy.sh
```
It will run git pull + composer install etc.
