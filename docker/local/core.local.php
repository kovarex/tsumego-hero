<?php

// Production-like settings for Deployer test environment
// Mounted as a shared file via docker-compose (persists across releases)
Configure::write('debug', 0);
define('CRON_SECRET', 'example');
