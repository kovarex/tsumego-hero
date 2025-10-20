#!/bin/bash

git pull

composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction
