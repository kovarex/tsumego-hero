<?php

namespace App\Utility;

//Row type aliases generated from the database schema. Do not edit manually.
//Regenerate after schema changes: ddev exec php scripts/generate-phpstan-row-types.php
/**
 * @phpstan-type AchievementConditionCategoryRow array{id?: int, name?: string, ...}
 * @phpstan-type AchievementConditionRow array{id?: int, value?: float|null, user_id?: int, achievement_condition_category_id?: int, set_id?: int|null, set_id_or_zero?: int|null, ...}
 * @phpstan-type AchievementRow array{id?: int, name?: string|null, description?: string|null, image?: string|null, color?: string|null, order?: int|null, xp?: int|null, additional_description?: string|null, created?: string|null, ...}
 * @phpstan-type AchievementStatusRow array{id?: int, user_id?: int, achievement_id?: int, value?: int, created?: string, ...}
 * @phpstan-type AdminActivityRow array{id?: int, user_id?: int, tsumego_id?: int|null, set_id?: int|null, type?: int, old_value?: string|null, new_value?: string|null, created?: string, ...}
 * @phpstan-type AdminActivityTypeRow array{id?: int, name?: string|null, ...}
 * @phpstan-type AdminPageViewRow array{user_id?: int, admin_page_id?: int, viewed?: string, ...}
 * @phpstan-type AvatarSettingRow array{id?: int, name?: string, ...}
 * @phpstan-type ChallengeParticipantRow array{id?: int, challenge_id?: int, user_id?: int, invited_by_user_id?: int|null, created?: string, declined?: string|null, ...}
 * @phpstan-type ChallengeProblemRow array{id?: int, challenge_id?: int, tsumego_id?: int, order?: int, ...}
 * @phpstan-type ChallengeRow array{id?: int, created_by_user_id?: int, minimum_time_mode_rank_id?: int, maximum_time_mode_rank_id?: int, seconds_per_problem?: int, created?: string, ...}
 * @phpstan-type ClassroomAssignmentProblemRow array{id?: int, classroom_assignment_id?: int, set_connection_id?: int, position?: int, ...}
 * @phpstan-type ClassroomAssignmentResultRow array{id?: int, classroom_assignment_id?: int, user_id?: int, tsumego_id?: int, misplays?: int, solved?: string|null, ...}
 * @phpstan-type ClassroomAssignmentRow array{id?: int, classroom_id?: int, title?: string|null, due?: string, published?: string|null, created?: string, ...}
 * @phpstan-type ClassroomMemberRow array{id?: int, classroom_id?: int, user_id?: int, classroom_member_status_id?: int, created?: string, ...}
 * @phpstan-type ClassroomMemberStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type DateFormatRow array{id?: int, name?: string, ...}
 * @phpstan-type HighscorePageRow array{id?: int, name?: string, ...}
 * @phpstan-type LevelBarRow array{id?: int, name?: string, ...}
 * @phpstan-type MessageRow array{id?: int, sender_user_id?: int, recipient_user_id?: int, text?: string, created?: string, read_at?: string|null, ...}
 * @phpstan-type MistakeTrainingDayRow array{id?: int, user_id?: int, day?: string, due?: int, reviewed?: int, cleared?: int, xp?: int, cleared_at?: string|null, missed_days?: int, ...}
 * @phpstan-type MistakeTrainingPoolRow array{id?: int, user_id?: int, tsumego_id?: int, rung?: int, lapses?: int, next_due?: string|null, last_review_day?: string|null, last_mistake_training_result_id?: int|null, created?: string, modified?: string, ...}
 * @phpstan-type MistakeTrainingResultRow array{id?: int, name?: string, ...}
 * @phpstan-type MistakeTrainingReviewRow array{id?: int, user_id?: int, tsumego_id?: int, day?: string, mistake_training_result_id?: int, rung_before?: int, rung_after?: int, xp?: int, created?: string, ...}
 * @phpstan-type ModeRow array{id?: int, name?: string, ...}
 * @phpstan-type PremiumRow array{id?: int, name?: string, ...}
 * @phpstan-type ProgressDeletionRow array{id?: int, user_id?: int, set_id?: int, set_partition?: int|null, created?: string, ...}
 * @phpstan-type RejectRow array{id?: int, reject_type_id?: int, tsumego_id?: int|null, tag_id?: int|null, tag_name?: string|null, sgf_id?: int|null, user_id?: int, rejected_by_user_id?: int|null, comment?: string|null, created?: string, ...}
 * @phpstan-type RejectTypeRow array{id?: int, name?: string, ...}
 * @phpstan-type SetConnectionRow array{id?: int, set_id?: int, tsumego_id?: int, num?: int, created?: string, ...}
 * @phpstan-type SetRow array{id?: int, user_id?: int|null, title?: string|null, title2?: string|null, description?: string|null, image?: string|null, order?: int|null, public?: int, color?: string|null, created?: string|null, included_in_time_mode?: int, board_theme_index?: int|null, deleted?: string|null, ...}
 * @phpstan-type SgfRow array{id?: int, sgf?: string|null, user_id?: int|null, accepted_by_user_id?: int|null, tsumego_id?: int, sgf_status_id?: int, created?: string|null, accepted?: string|null, first_move_color?: string|null, correct_moves?: string|null, source_sgf_id?: int|null, transformation_from_source?: string|null, description?: string|null, ...}
 * @phpstan-type SgfStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TagConnectionRow array{id?: int, tag_id?: int, user_id?: int|null, tsumego_id?: int, approved?: int, created?: string, ...}
 * @phpstan-type TagRow array{id?: int, name?: string, description?: string, link?: string|null, user_id?: int|null, approved?: int, color?: int, hint?: int, created?: string, ...}
 * @phpstan-type ThemeRow array{id?: int, name?: string, ...}
 * @phpstan-type TimeModeAttemptRow array{id?: int, tsumego_id?: int, order?: int, seconds?: string|null, points?: string|null, started?: string|null, time_mode_session_id?: int, time_mode_attempt_status_id?: int, ...}
 * @phpstan-type TimeModeAttemptStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TimeModeSessionRow array{id?: int, user_id?: int, points?: string|null, created?: string, time_mode_session_status_id?: int, time_mode_category_id?: int|null, time_mode_rank_id?: int|null, challenge_id?: int|null, ...}
 * @phpstan-type TimeModeSessionStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TsumegoAttemptRow array{id?: int, user_id?: int, tsumego_id?: int, gain?: int, solved?: int, seconds?: int, misplays?: int, user_rating?: int|null, tsumego_rating?: int, created?: string, ...}
 * @phpstan-type TsumegoCommentRow array{id?: int, tsumego_id?: int, tsumego_issue_id?: int|null, message?: string, created?: string, user_id?: int, position?: string|null, deleted?: string|null, ...}
 * @phpstan-type TsumegoIssueRow array{id?: int, tsumego_issue_status_id?: int, tsumego_id?: int, user_id?: int, created?: string, deleted?: string|null, ...}
 * @phpstan-type TsumegoIssueStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TsumegoRow array{id?: int, description?: string|null, hint?: string|null, author?: string, author_user_id?: int|null, rating?: float, minimum_rating?: float|null, maximum_rating?: float|null, created?: string|null, minimum_liberties?: int|null, maximum_liberties?: int|null, variance?: int|null, liberty_count?: int|null, inside_liberties?: int|null, semeai_type?: int|null, alternative_response?: int, pass?: int, deleted?: string|null, ...}
 * @phpstan-type TsumegoStatusRow array{id?: int, user_id?: int, tsumego_id?: int, status?: string, updated?: string, ...}
 * @phpstan-type TsumegoVariantRow array{id?: int, tsumego_id?: int, tsumego_variant_type_id?: int, correct_answer?: int, answer1?: string, answer2?: string, answer3?: string, answer4?: string, winner?: string|null, explanation?: string|null, created?: string, ...}
 * @phpstan-type TsumegoVariantTypeRow array{id?: int, name?: string, ...}
 * @phpstan-type UserAvatarRow array{user_id?: int, picture?: string, ...}
 * @phpstan-type UserRoleRow array{id?: int, name?: string, ...}
 * @phpstan-type UserRow array{id?: int, name?: string|null, display_name?: string, needs_display_name_change?: int, email?: string, unconfirmed_email?: string|null, external_id?: string|null, picture?: string|null, premium_id?: int|null, level?: int, mode_id?: int, rating?: float, highest_rating?: float, default_set_id?: int|null, rating_mode_difficulty_selection?: int, created?: string|null, xp?: int, damage?: int, used_potion?: int, used_intuition?: int, used_revelation?: int, user_role_id?: int, highscore_page_id?: int|null, theme_id?: int, level_bar_id?: int, last_time_mode_category_id?: int|null, daily_solved?: int, daily_xp?: int, blocked?: int, used_sprint?: int, used_rejuvenation?: int, used_refinement?: int, password_reset_token?: string|null, solved?: int|null, sound?: int, password_hash?: string, sprint_start?: string|null, boards_bitmask?: int, player_color_setting?: int, board_orientation_setting?: int, deleted?: string|null, deleted_name?: string|null, deleted_external_id?: string|null, deletion_token?: string|null, deletion_token_created?: string|null, avatar_setting_id?: int, uploaded_avatar_hash?: string|null, unconfirmed_email_token?: string|null, unconfirmed_email_cancel_token?: string|null, unconfirmed_email_created?: string|null, password_reset_token_created?: string|null, date_format_id?: int, email_on_new_message?: int, collection_grouping?: string, collection_size?: int, filtered_sets?: string|null, filtered_ranks?: string|null, filtered_tags?: string|null, contribution_revelation_bonus?: int, ...}
 */
class RowTypes {}
