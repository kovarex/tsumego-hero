#!/usr/bin/env bash

# If first argument is non-empty, set filter parameter
if [ -n "$1" ]; then
  filter_parameter="--filter=$1"
else
  filter_parameter=""
fi

phpunit --stop-on-failure $filter_parameter 2>&1 | sed "1{/^PHPUnit /d}" | sed "1{/^$/d}"
