<?php

use App\Utility\Util;

/**
 * A time in the list of challenges, as "2 days ago" with the exact time in the tooltip.
 *
 * @var \App\View\AppView $this
 * @var string $time From the database
 */

?>
<time class="challenges__when" datetime="<?= Util::toIso8601($time) ?>" data-format="relative"><?= date('Y-m-d H:i', (int) strtotime($time)) ?></time><?php
