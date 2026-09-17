<?php

namespace App\Utility;

/**
 * Row type aliases generated from the database schema. Do not edit manually.
 * Regenerate after schema changes: ddev exec php scripts/generate-phpstan-row-types.php
 *
 * @phpstan-type AchievementConditionRow array{id?: int, value?: float|null, user_id?: int, set_id?: int|null, category?: string|null, created?: string, ...}
 * @phpstan-type AchievementRow array{id?: int, name?: string|null, description?: string|null, image?: string|null, color?: string|null, order?: int|null, xp?: int|null, additionalDescription?: string|null, created?: string|null, ...}
 * @phpstan-type AchievementStatusRow array{id?: int, user_id?: int, achievement_id?: int, value?: int, created?: string|null, ...}
 * @phpstan-type ActivateRow array{id?: int, user_id?: int, string?: string|null, created?: string, ...}
 * @phpstan-type AdminActivityRow array{id?: int, user_id?: int, tsumego_id?: int|null, set_id?: int|null, type?: int, old_value?: string|null, new_value?: string|null, created?: string, ...}
 * @phpstan-type AdminActivityTypeRow array{id?: int, name?: string|null, ...}
 * @phpstan-type AnswerRow array{id?: int, user_id?: int, comment_id?: int, message?: string, dismissed?: int, created?: string, ...}
 * @phpstan-type DayRecordRow array{id?: int, user_id?: int|null, date?: string|null, quote?: string|null, gems?: string, gemCounter1?: int, gemCounter2?: int, gemCounter3?: int, ...}
 * @phpstan-type ProgressDeletionRow array{id?: int, user_id?: int, set_id?: int, created?: string, ...}
 * @phpstan-type PublishDateRow array{id?: int, tsumego_id?: int|null, date?: string|null, ...}
 * @phpstan-type RejectRow array{id?: int, type?: string|null, text?: string|null, tsumego_id?: int, user_id?: int, created?: string, ...}
 * @phpstan-type ScheduleRow array{id?: int, date?: string, tsumego_id?: int|null, set_id?: int|null, published?: int, ...}
 * @phpstan-type SetConnectionRow array{id?: int, set_id?: int, tsumego_id?: int, num?: int, created?: string, ...}
 * @phpstan-type SetRow array{id?: int, user_id?: int|null, title?: string|null, title2?: string|null, author?: string|null, description?: string|null, image?: string|null, order?: int|null, public?: int|null, multiplier?: float, color?: string|null, created?: string|null, included_in_time_mode?: int, board_theme_index?: int|null, ...}
 * @phpstan-type SgfRow array{id?: int, sgf?: string|null, user_id?: int|null, tsumego_id?: int, created?: string|null, accepted?: int, first_move_color?: string|null, correct_moves?: string|null, ...}
 * @phpstan-type SignatureRow array{id?: int, tsumego_id?: int, signature?: string|null, created?: string, ...}
 * @phpstan-type SiteRow array{id?: int, title?: string|null, body?: string|null, ...}
 * @phpstan-type TagConnectionRow array{id?: int, tag_id?: int|null, user_id?: int|null, tsumego_id?: int, approved?: int|null, created?: string, ...}
 * @phpstan-type TagRow array{id?: int, name?: string, description?: string, link?: string|null, user_id?: int|null, approved?: int, color?: int, hint?: int, created?: string, ...}
 * @phpstan-type TimeModeAttemptRow array{id?: int, tsumego_id?: int, order?: int, seconds?: string|null, points?: string|null, started?: string|null, time_mode_session_id?: int, time_mode_attempt_status_id?: int, ...}
 * @phpstan-type TimeModeAttemptStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TimeModeCategoryRow array{id?: int, name?: string, seconds?: int, ...}
 * @phpstan-type TimeModeRankRow array{id?: int, name?: string, ...}
 * @phpstan-type TimeModeSessionRow array{id?: int, user_id?: int, points?: string|null, created?: string, time_mode_session_status_id?: int, time_mode_category_id?: int, time_mode_rank_id?: int, ...}
 * @phpstan-type TimeModeSessionStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TsumegoAttemptRow array{id?: int, user_id?: int, tsumego_id?: int, gain?: int, solved?: int, seconds?: int, misplays?: int, user_rating?: int|null, tsumego_rating?: int, created?: string, ...}
 * @phpstan-type TsumegoCommentRow array{id?: int, tsumego_id?: int, tsumego_issue_id?: int|null, message?: string, created?: string, user_id?: int, position?: string|null, deleted?: int, ...}
 * @phpstan-type TsumegoIssueRow array{id?: int, tsumego_issue_status_id?: int, tsumego_id?: int, user_id?: int, created?: string, deleted?: int, ...}
 * @phpstan-type TsumegoIssueStatusRow array{id?: int, name?: string, ...}
 * @phpstan-type TsumegoRow array{id?: int, part?: string|null, part_increment?: int|null, description?: string|null, hint?: string|null, author?: string, author_user_id?: int|null, solved?: int|null, failed?: int|null, rating?: float, minimum_rating?: float|null, maximum_rating?: float|null, userWin?: float, userLoss?: int, created?: string|null, minLib?: int|null, maxLib?: int|null, variance?: int|null, libertyCount?: int|null, insideLiberties?: int|null, eyeLiberties1?: int|null, eyeLiberties2?: int|null, semeaiType?: int|null, alternative_response?: int, pass?: int, activity_value?: int, deleted?: string|null, ...}
 * @phpstan-type TsumegoStatusRow array{id?: int, user_id?: int, tsumego_id?: int, status?: string, updated?: string, ...}
 * @phpstan-type TsumegoVariantRow array{id?: int, tsumego_id?: int, type?: string, numAnswer?: float, answer1?: string, answer2?: string, answer3?: string, answer4?: string, winner?: string|null, explanation?: string|null, created?: string, ...}
 * @phpstan-type UserContributionRow array{id?: int, user_id?: int|null, query?: string, collection_size?: int, filtered_sets?: string|null, filtered_ranks?: string|null, filtered_tags?: string|null, created_tag?: int|null, made_proposal?: int|null, reviewed?: int|null, score?: int|null, reward1?: int|null, reward2?: int|null, reward3?: int|null, created?: string|null, ...}
 * @phpstan-type UserRow array{id?: int, name?: string|null, display_name?: string, needs_display_name_change?: int, email?: string, external_id?: string|null, picture?: string|null, premium?: int, level?: int, mode?: int, rating?: float, default_set_id?: int|null, t_glicko?: int, dbstorage?: int, created?: string|null, lastRefresh?: string|null, xp?: int, damage?: int, used_potion?: int, promoted?: int, used_intuition?: int, used_revelation?: int, readingTrial?: int, isAdmin?: int, lastHighscore?: int, lastLight?: int, levelBar?: int, lastProfileLeft?: int, lastProfileRight?: int, last_time_mode_category_id?: int|null, daily_solved?: int, daily_xp?: int, reuse4?: int, reuse5?: int, penalty?: int, reward?: string|null, used_sprint?: int, used_rejuvenation?: int, used_refinement?: int, passwordreset?: string|null, solved?: int|null, sortOrder?: int, sortColor?: int, sound?: string, ip?: string|null, location?: string|null, password_hash?: string, sprint_start?: string|null, login_token?: string|null, boards_bitmask?: int, pref_player_color?: int, pref_board_orientation?: int, ...}
 */
class RowTypes {}
