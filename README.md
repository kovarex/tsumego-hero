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

## Database Migrations

This project uses [Phinx](https://phinx.org/) for database migrations.
Phinx is a database migration tool that allows you to version control your database schema changes.

### Configuration

Phinx is configured via `phinx.php` which automatically loads database credentials from CakePHP's `config/database.php`. Migrations are stored in `db/migrations/` and seeds in `db/seeds/`.

### Common Migration Commands

```bash
# Create a new migration
vendor/bin/phinx create MyNewMigration

# Run all pending migrations
composer migrate
# or: vendor/bin/phinx migrate

# Rollback the last migration
composer migrate-rollback
# or: vendor/bin/phinx rollback

# Check migration status
composer migrate-status
# or: vendor/bin/phinx status

# Run migrations for test environment
composer migrate-test
# or: vendor/bin/phinx migrate -e test

# Seed the database
vendor/bin/phinx seed:run
```

### Creating Migrations

When you create a new migration, it will generate a timestamped file in `db/migrations/`:

```bash
vendor/bin/phinx create AddUserEmailColumn
```

This creates a file like `20250130123456_add_user_email_column.php`. Edit the migration to define your schema changes:

```php
<?php
use Phinx\Migration\AbstractMigration;

class AddUserEmailColumn extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        $table->addColumn('email', 'string', ['limit' => 255, 'null' => false])
              ->addIndex(['email'], ['unique' => true])
              ->update();
    }
}
```

### Best Practices

- Always use `change()` method when possible (Phinx can automatically reverse it)
- Use `up()` and `down()` methods for complex migrations that can't be auto-reversed
- Follow CakePHP's table naming conventions (plural, snake_case)
- Foreign keys should follow pattern: `{singular_table}_id`
- Always test migrations in development before running in production
- Run migrations as part of deployment process

### Documentation

- [Phinx Documentation](https://book.cakephp.org/phinx/0/en/index.html)
- [Writing Migrations](https://book.cakephp.org/phinx/0/en/migrations.html)
- [Seeding Data](https://book.cakephp.org/phinx/0/en/seeding.html)

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
