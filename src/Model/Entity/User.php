<?php

namespace App\Model\Entity;

use DateTimeImmutable;

/**
 * @property int $id
 * @property string|null $name
 * @property string $display_name
 * @property bool $needs_display_name_change
 * @property string $email
 * @property string|null $unconfirmed_email
 * @property string|null $external_id
 * @property string|null $picture
 * @property int|null $premium_id
 * @property int $level
 * @property int $mode_id
 * @property float $rating
 * @property float $highest_rating
 * @property int|null $default_set_id
 * @property int $rating_mode_difficulty_selection
 * @property DateTimeImmutable|null $created
 * @property int $xp
 * @property int $damage
 * @property int $used_potion
 * @property int $used_intuition
 * @property int $used_revelation
 * @property int $user_role_id
 * @property int|null $highscore_page_id
 * @property int $theme_id
 * @property int $level_bar_id
 * @property int|null $last_time_mode_category_id
 * @property int $daily_solved
 * @property int $daily_xp
 * @property bool $blocked
 * @property int $used_sprint
 * @property int $used_rejuvenation
 * @property int $used_refinement
 * @property string|null $password_reset_token
 * @property int|null $solved
 * @property bool $sound
 * @property string $password_hash
 * @property DateTimeImmutable|null $sprint_start
 * @property int $boards_bitmask
 * @property bool $player_color_setting
 * @property bool $board_orientation_setting
 * @property DateTimeImmutable|null $deleted
 * @property string|null $deleted_name
 * @property string|null $deleted_external_id
 * @property string|null $deletion_token
 * @property DateTimeImmutable|null $deletion_token_created
 * @property int $avatar_setting_id
 * @property string|null $uploaded_avatar_hash
 * @property string|null $unconfirmed_email_token
 * @property string|null $unconfirmed_email_cancel_token
 * @property DateTimeImmutable|null $unconfirmed_email_created
 * @property DateTimeImmutable|null $password_reset_token_created
 * @property int $date_format_id
 * @property bool $email_on_new_message
 * @property string $collection_grouping
 * @property int $collection_size
 * @property string|null $filtered_sets
 * @property string|null $filtered_ranks
 * @property string|null $filtered_tags
 * @property bool $contribution_revelation_bonus
 */
class User extends AppEntity {}
