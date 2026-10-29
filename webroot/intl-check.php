<?php
//Temporary check, not for merging: is intl loaded in the web php?
header('Content-Type: text/plain');
echo 'intl in web php: ' . (extension_loaded('intl') ? 'yes, ICU ' . INTL_ICU_VERSION : 'no') . ' (' . PHP_VERSION . ")\n";
