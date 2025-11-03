#!/usr/bin/env bash

mkdir -p ~/bin
cp setup/php-redirect ~/bin/php
chmod +x ~/bin/php
cp setup/nfs-permanent ~/.bashrc
cp setup/nfs-permanent ~/.profile
