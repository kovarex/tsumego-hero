#!/usr/bin/env bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
echo Working in $SCRIPT_DIR

echo Downloading db file
curl https://tsumego-hero.com/files/$1 > $SCRIPT_DIR/db-dump.sql
call $SCRIPT_DIR/import.sh $2 $3 $4
