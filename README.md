# Tsumego Hero

Source code of [tsumego.com](https://tsumego.com), a site for practicing tsumego (Go problems).

- Live site: https://tsumego.com
- Testing site: https://test.tsumego.com

## Development principles
- The code should be well structured, readable and easy to modify.
- The site's functionality should be covered by automated tests, so it doesn't "randomly break".
- Current test coverage: https://kovarex.github.io/tsumego-hero/coverage/

## Local Setup (part 1)

- Instal ddev [ddev](https://ddev.com/get-started/).

### PHP 8.4 install
8.4 is too new to be installed in an easy way, we have a script you can call to install it on the machine

	setup/php-install.sh

### ddev install

- Modify the php.ini in ./ddev/php/php.ini, add your local ipaddress there, which is needed for debugging
- Then from ROOT of the project run: (project name should be tsumego)

```
wsl
```

```
ddev start
```

- Install composer, run database migrations, install node project

```
./deploy.sh
```

- Optional: import database dump ~30 min
```
ddev import-db --file=/mnt/c/db-tsumego-xyz.sql
```

- To jump to your app in browser:

```
ddev launch
```
- Phpmyadmin access:

```
ddev phpmyadmin
```
- ssh login into the docker

```
ddev ssh
```
- Run locally to install all latest dependencies.
```
composer install
```
- Make your own database file, you can use the default one, but you can modify it if you want to use different database or credentials

```
cp config/database.example config/database
```

### React/Vite development setup:
 - DDEV automatically runs this on start

```
pnpm run dev
```
  This starts Vite in watch mode - it rebuilds the React bundle automatically when you edit files in `app/`. The bundle is written to `webroot/js/dist/app.js` and loaded by the site with cache-busting timestamps.

- Open to browse your project now.

    https://tsumego.ddev.site:33003/

- You can also open the webpage from command line by:

```
ddev launch
```
### Setup on server (part 1)

	git clone https://github.com/kovarex/tsumego-hero.git .

### Final step (part 2) - both local and server

	./deploy.sh

This when ran for the first time, it will ask for db credentials and generate the proper config files for cake and forums.

Other than that pulls git, installs composer stuff, sets required folders and their access rights, update minification, runs migrations (also for test db in local environment with ddev).
So running ./deploy.sh is the way to always update server or dev enrironment to the up to date state.

### Debug with phpstorm

https://www.jetbrains.com/help/phpstorm/debugging-with-phpstorm-ultimate-guide.html#setup-from-zero
TLDR; The local configuration should have xdebug already setup, all you should need to do is to setup the debug directories in phpstorm

	ALT + SHIFT + S (options) -> PHP -> servers

	For manual testing:

	Name: tsumego.ddev.site
	Host: test.tsumego.ddev.site
	Port: 80
	Debugger: xdebug

	For automated tests, add another entry:

	Name: test.tsumego.ddev.site
	Host: test.test.tsumego.ddev.site
	Port: 80
	Debugger: xdebug

After this, it should just work.

## Database Migrations

This project uses [Phinx](https://phinx.org/) for database migrations.
Phinx is a database migration tool that allows you to version control your database schema changes.
Phinx is configured via `phinx.php` which automatically loads database credentials from CakePHP's `config/database.php`. Migrations are stored in `db/migrations/` and seeds in `db/seeds/`.

- Migrate the current database to the newest version (but you don't have to do it manually, it is included in ./deploy.sh)


	vendor/bin/phinx migrate

- Migrate test database to the newest version


	vendor/bin/phinx migrate -e test

- Create a new migration


	vendor/bin/phinx create <migration name>

It will generate a timestamped file in `db/migrations/`:
This creates a file like `20250130123456_add_user_email_column.php`. Edit the migration to define your schema changes:
I just implement the method up, as reverse migrations are not realistic or useful now.

## Code Quality & Testing
```bash
composer test        # PHPUnit tests
composer cs-check   # PHP CodeSniffer
composer cs-fix     # Auto-fix CS issues
composer stan       # PHPStan on src/ + views

### also:
composer cs-modified # Only run phpcs on modified files
```

### Development Commands Quick Reference
```bash
# Specific folder analysis
composer cs-check -- src/Utility

# Test specific methods (inside ddev container!):
vendor/bin/phpunit path/to/test.php --filter=testMethodName
```

## Deploy
On the server in ROOT:
```
sh deploy.sh
```
It will run git pull + composer install etc.
