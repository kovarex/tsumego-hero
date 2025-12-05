# tsumego: restart from tsumego-hero

## The goal
To improve the source code of tsumego hero so:
- The page can be moved to a modern server with php 8.4 functionality
- The code is better structured, readable and easily modifiable.
- The functionality of the site is fully covered by automated tests, so it doesn't just "randomly break"
- Current test coverage status can be checked here: https://kovarex.github.io/tsumego-hero/coverage/

## The plan
### Things get broken for a while
There were some breaking changes done
- Clearing obsolete table columns (tsumego.set_id, tsumego.num and similar)
- Changing the table data structure around time mode
- (and more)
These changes just had to be done, so the data structure we are working on is clean.
### Database changes
- There are a lot of database structure changes, mainly related to data normalisation, foreign key usage on all relevant places, and proper index selections.
### Code refactoring
- The rest of the code refactoring should ideally not break stuff, but when the state of the code is taken into consideration, it is really hard to be sure.
- We try to mainly cover the parts to refactor by tests and check the behaviour on tsumego-hero to understand what are we doing.
- It is inevitable part of the plan to refactor the whole code, but not necessary before day D
### Day D
`Day D` is the day where we migrate the tsumego-hero site database into tsumego.com and make it the official new home of the site.
`Day D` can become once the core functionality of the site is covered by tests, and we do some public testing on test.tsumego.com

## Local Setup (part 2)

- Instal ddev [ddev](https://ddev.com/get-started/).

### PHP 8.4 install
8.4 is too new to be installed in an easy way, we have a script you can call to install it on the machine

	setup/php-install.sh

### ddev install
- Copy .ddev.example/ folder to .ddev/


	cp ./ddev.example/* ./ddev/

- Modify the php.ini in ./ddev/php/my-php.ini, add your local ipaddress there, which is needed for debugging
- Then from ROOT of the project run: (project name should be tsumego)


	ddev config
    ddev start

- To jump to your app in browser:


    ddev launch

- Phpmyadmin access:


    ddev phpmyadmin

- ssh login into the docker


    ddev ssh

- Run locally to install all latest dependencies.

    composer update

- Make your own database file, you can use the default one, but you can modify it if you want to use different database or credentials


	cp config/database.example config/database

- Open to browse your project now.

    https://tsumego.ddev.site:33003/

- You can also open the webpage from command line by:


	ddev launch

### Setup on server (part 1)

	git clone https://github.com/kovarex/tsumego-hero.git

### Setup final step (both local and server)

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
