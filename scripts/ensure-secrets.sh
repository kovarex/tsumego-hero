#!/bin/bash
# The secrets of a server that are not in git, made before its new code starts:
# - config/jwt_secret.php: the secret of the session cookie, read by the site and the forum
#   (see config/jwt_secret.example.php),
# - the Security.salt of config/core.local.php (see config/core.local.example.php).
# A missing one, or one too short to be a secret (an example copied as it is), gets a fresh random
# 64-hex value, which is never printed. An existing value is kept, except the salt that was committed
# to git until #711: it is public, so it is replaced wherever it is found.
# Usage: scripts/ensure-secrets.sh <project root>. deploy.sh and the test site's deploy hook run it
# before switching the code; running it again changes nothing.
set -e

root="${1:?usage: scripts/ensure-secrets.sh <project root>}"
jwt_file="$root/config/jwt_secret.php"
core_local="$root/config/core.local.php"

# The sha256 of the salt that config/core.php committed until #711, so the value itself stays out of
# this file. The test sets its own.
compromised_sha256="${ENSURE_SECRETS_COMPROMISED_SHA256:-755d3c6a23e3ca5059e2ce1a754f7589585099548c36d3ce124b779555834cf5}"

# A quote of PHP, either kind
q="['\"]"
salt_line="^[[:space:]]*Configure::write\\([[:space:]]*${q}Security\\.salt${q}"

random_secret() {
	od -An -tx1 -N32 /dev/urandom | tr -d ' \n'
}

# Whether a value is missing as a secret: too short to be one, or the one committed to git
unusable() {
	[ "${#1}" -lt 32 ] || [ "$(printf '%s' "$1" | openssl dgst -sha256 | awk '{print $NF}')" = "$compromised_sha256" ]
}

# Whether there is the group web, the one PHP runs in on NearlyFreeSpeech. Elsewhere (DDEV, CI) PHP
# runs as the owner of the files.
web_group_exists() {
	getent group web > /dev/null 2>&1 || grep -q '^web:' /etc/group 2> /dev/null
}

# Both files are read by PHP: where the group web exists, they belong to it and only it reads them
# besides the owner, whatever mode they had; a file this script makes is its owner's only otherwise.
# A file PHP couldn't read stops the deploy here rather than the site later.
make_readable_by_php() {
	web_group_exists || return 0
	for file in "$jwt_file" "$core_local"; do
		if ! { chgrp web "$file" && chmod 640 "$file"; } 2> /dev/null; then
			echo "${file#"$root"/} could not be given to the group web, so PHP could not read it" >&2
			exit 1
		fi
	done
}

# The value the file returns, prefixed by v: when it is a literal string
jwt_value() {
	sed -nE "s/^[[:space:]]*return[[:space:]]*${q}([^'\"]*)${q}.*/v:\\1/p" "$jwt_file" | head -n 1
}

# The literal value of the first Security.salt line, prefixed by v:, nothing for a value that is not
# a literal string (read from the environment, say), which is left as it is
salt_value() {
	sed -nE "s/${salt_line}[[:space:]]*,[[:space:]]*${q}([^'\"]*)${q}.*/v:\\1/p" "$core_local" | head -n 1
}

write_jwt_secret() {
	secret=$(random_secret)
	(umask 077 && printf "<?php\n\n//The secret of the session cookie, made by scripts/ensure-secrets.sh (see config/jwt_secret.example.php)\nreturn '%s';\n" "$secret" > "$jwt_file")
}

if [ ! -f "$jwt_file" ]; then
	write_jwt_secret
	echo "created config/jwt_secret.php"
else
	value=$(jwt_value)
	if [ -n "$value" ] && unusable "${value#v:}"; then
		write_jwt_secret
		echo "replaced the unusable secret of config/jwt_secret.php"
	fi
fi

if [ ! -f "$core_local" ]; then
	(umask 077 && printf "<?php\n" > "$core_local")
fi
if ! grep -Eq "$salt_line" "$core_local"; then
	if tail -n 3 "$core_local" | grep -q '?>'; then
		echo "config/core.local.php ends with ?>, so no Security.salt can be added to it: remove that tag, or add a salt as config/core.local.example.php shows" >&2
		exit 1
	fi
	salt=$(random_secret)
	printf "\n// The salt of this server, made by scripts/ensure-secrets.sh (see config/core.local.example.php)\nConfigure::write('Security.salt', '%s');\n" "$salt" >> "$core_local"
	echo "added Security.salt to config/core.local.php"
else
	value=$(salt_value)
	if [ -n "$value" ] && unusable "${value#v:}"; then
		# Every Security.salt line gets the new value, written through a file of the same directory
		# whose content goes into the old file, so the file keeps its owner and permissions. The
		# value reaches awk in its environment, never on a command line.
		tmp="$core_local.ensure-secrets.$$"
		(umask 077 && NEW_SALT=$(random_secret) SALT_LINE="$salt_line" awk -v sq="'" '
			$0 ~ ENVIRON["SALT_LINE"] { print "Configure::write(" sq "Security.salt" sq ", " sq ENVIRON["NEW_SALT"] sq ");"; next }
			{ print }' "$core_local" > "$tmp")
		cat "$tmp" > "$core_local"
		rm -f "$tmp"
		echo "replaced the unusable Security.salt of config/core.local.php"
	fi
fi

make_readable_by_php
