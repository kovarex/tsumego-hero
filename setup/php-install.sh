#!/usr/bin/env bash

sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.4 -y
sudo apt install php8.4-common php8.4-cli php8.4-opcache php8.4-mysql php8.4-xml php8.4-curl php8.4-zip php8.4-mbstring php8.4-gd php8.4-intl php8.4-bcmath -y
