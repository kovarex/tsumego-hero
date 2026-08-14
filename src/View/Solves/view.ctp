<?php

/**
 * @var View $this
 * @var array $post
 */

echo '<time datetime="' . Util::toIso8601($post['Post']['created']) . '" data-format="datetime">' . $post['Post']['created'] . '</time>';
?>
