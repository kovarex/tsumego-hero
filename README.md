# tsumego-hero-app

## Status & Requirements

- composer
- CakePHP 2.x latest
- PHP 8.2
- MySQL 8.0

Also:
- For best PHP dev I recommend [PHPStorm IDE](https://www.jetbrains.com/phpstorm/).
- For local env best to use [ddev](https://ddev.com/get-started/).

## Setup

To locally develop and setup, use ddev from inside app/ folder:
- Copy .ddev.example/ folder to .ddev/
- Modify the config.yaml file to your needs.

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

    https://tsumego3.ddev.site/

to browse your project now.

## Code Quality & Testing
```bash
composer test        # PHPUnit tests
composer cs-check   # PHP CodeSniffer
composer cs-fix     # Auto-fix CS issues
composer stan       # PHPStan static analysis

### Development Commands Quick Reference
```bash

# Specific folder analysis
composer stan -- src/Controller
composer cs-check -- src/Utility

# Test specific methods (side ddev container!):
vendor/bin/phpunit path/to/test.php --filter=testMethodName
```
