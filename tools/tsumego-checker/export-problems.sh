#!/bin/sh
# Exports the current accepted SGF of every problem (one JSON object per line) for the solver research.
# Read-only. Usage: sh export-problems.sh DBNAME -h HOST -u USER -p
database="$1"
shift
set -e
mariadb -N -B --raw "$@" "$database" > problems.jsonl -e "
SELECT JSON_OBJECT(
  'set_connection_id', set_connection.id,
  'tsumego_id', tsumego.id,
  'set_id', \`set\`.id,
  'set_title', \`set\`.title,
  'num', set_connection.num,
  'description', tsumego.description,
  'rating', tsumego.rating,
  'sgf_id', sgf.id,
  'sgf', sgf.sgf)
FROM set_connection
JOIN tsumego ON tsumego.id = set_connection.tsumego_id
JOIN \`set\` ON \`set\`.id = set_connection.set_id
JOIN sgf ON sgf.id = (SELECT MAX(current_sgf.id) FROM sgf current_sgf
                      WHERE current_sgf.tsumego_id = tsumego.id AND current_sgf.sgf_status_id = 2)
WHERE tsumego.deleted IS NULL
ORDER BY set_connection.id"
gzip -f problems.jsonl
echo "wrote problems.jsonl.gz with $(gzip -dc problems.jsonl.gz | wc -l) problems"
