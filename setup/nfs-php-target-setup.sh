#!/usr/bin/env bash

mkdir -p ~/bin
cp setup/files/php-redirect ~/bin/php
chmod +x ~/bin/php
cp setup/files/nfs-permanent.sh ~/.bashrc
cp setup/files/nfs-permanent.sh ~/.profile
