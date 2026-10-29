#!/bin/bash
#Experiment, not for merging: runs the same browser tests under each web server setup on one runner
set -u
FILES="tests/TestCase/Controller/PlayGameplayBrowserTest.php tests/TestCase/Controller/CommentsControllerTest.php tests/TestCase/Controller/TagTest.php tests/TestCase/AccountWidgetTest.php"
start()
{
	pkill -f "php -S 0.0.0.0:8080" || true
	docker rm -f caddy >/dev/null 2>&1 || true
	sleep 1
	if [ "$1" = 1 ]; then PHP_CLI_SERVER_WORKERS=8 nohup php -S 0.0.0.0:8080 -t webroot .github/ci/router.php > server.log 2>&1 &
	else nohup php -S 0.0.0.0:8080 -t webroot .github/ci/router.php > server.log 2>&1 & fi
	if [ "$2" = 1 ]; then caddyfile="$PWD/Caddyfile"; else caddyfile="$PWD/.github/ci/Caddyfile.old"; fi
	docker run -d --name caddy -p 8443:8443 --add-host=host.docker.internal:host-gateway \
		--sysctl net.ipv6.conf.all.disable_ipv6=1 --sysctl net.ipv6.conf.default.disable_ipv6=1 \
		-v "$caddyfile:/etc/caddy/Caddyfile" -v "$PWD/webroot:/srv:ro" caddy:2 >/dev/null
	until curl -sk -o /dev/null https://localhost:8443/empty.php; do sleep 0.2; done
}
for round in 1 2; do
	for config in "0 0" "1 0" "0 1" "1 1"; do
		start $config
		begin=$(date +%s.%N)
		vendor/bin/phpunit $FILES > phpunit.out 2>&1; code=$?
		end=$(date +%s.%N)
		echo "RESULT round=$round workers=${config% *} caddy_static=${config#* } exit=$code seconds=$(awk "BEGIN {print $end - $begin}") $(grep -E '^(OK|Tests:)' phpunit.out)" | tee -a results.txt
		[ $code = 0 ] || tail -40 phpunit.out
	done
done
cat results.txt
