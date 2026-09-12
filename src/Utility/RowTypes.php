<?php

namespace App\Utility;

/**
 * Row type aliases generated from the database schema. Do not edit manually.
 * Regenerate after schema changes: ddev exec php scripts/generate-phpstan-row-types.php
 *
 * @phpstan-type AchievementConditionRow array{id?: int|string, value?: int|float|string|null, user_id?: int|string, set_id?: int|string|null, category?: string|null, created?: string, ...}
 * @phpstan-type AchievementRow array{id?: int|string, name?: string|null, description?: string|null, image?: string|null, color?: string|null, order?: int|string|null, xp?: int|string|null, additionalDescription?: string|null, created?: string|null, ...}
 * @phpstan-type AchievementStatusRow array{id?: int|string, user_id?: int|string, achievement_id?: int|string, value?: int|string, created?: string|null, ...}
 * @phpstan-type ActivateRow array{id?: int|string, user_id?: int|string, string?: string|null, created?: string, ...}
 * @phpstan-type AdminActivityRow array{id?: int|string, user_id?: int|string, tsumego_id?: int|string|null, set_id?: int|string|null, type?: int|string, old_value?: string|null, new_value?: string|null, created?: string, ...}
 * @phpstan-type AdminActivityTypeRow array{id?: int|string, name?: string|null, ...}
 * @phpstan-type AnswerRow array{id?: int|string, user_id?: int|string, comment_id?: int|string, message?: string, dismissed?: int|string, created?: string, ...}
 * @phpstan-type DayRecordRow array{id?: int|string, user_id?: int|string|null, date?: string|null, quote?: string|null, gems?: string, gemCounter1?: int|string, gemCounter2?: int|string, gemCounter3?: int|string, ...}
 * @phpstan-type ProgressDeletionRow array{id?: int|string, user_id?: int|string, set_id?: int|string, created?: string, ...}
 * @phpstan-type PublishDateRow array{id?: int|string, tsumego_id?: int|string|null, date?: string|null, ...}
 * @phpstan-type RejectRow array{id?: int|string, type?: string|null, text?: string|null, tsumego_id?: int|string, user_id?: int|string, created?: string, ...}
 * @phpstan-type ScheduleRow array{id?: int|string, date?: string, tsumego_id?: int|string|null, set_id?: int|string|null, published?: int|string, ...}
 * @phpstan-type SetConnectionRow array{id?: int|string, set_id?: int|string, tsumego_id?: int|string, num?: int|string, created?: string, ...}
 * @phpstan-type SetRow array{id?: int|string, user_id?: int|string|null, title?: string|null, title2?: string|null, author?: string|null, description?: string|null, image?: string|null, order?: int|string|null, public?: int|string|null, multiplier?: int|float|string, color?: string|null, created?: string|null, included_in_time_mode?: int|string, board_theme_index?: int|string|null, ...}
 * @phpstan-type SgfRow array{id?: int|string, sgf?: string|null, user_id?: int|string|null, tsumego_id?: int|string, created?: string|null, accepted?: int|string, first_move_color?: string|null, correct_moves?: string|null, ...}
 * @phpstan-type SignatureRow array{id?: int|string, tsumego_id?: int|string, signature?: string|null, created?: string, ...}
 * @phpstan-type SiteRow array{id?: int|string, title?: string|null, body?: string|null, ...}
 * @phpstan-type TagConnectionRow array{id?: int|string, tag_id?: int|string|null, user_id?: int|string|null, tsumego_id?: int|string, approved?: int|string|null, created?: string, ...}
 * @phpstan-type TagRow array{id?: int|string, name?: string, description?: string, link?: string|null, user_id?: int|string|null, approved?: int|string, color?: int|string, hint?: int|string, created?: string, ...}
 * @phpstan-type TimeModeAttemptRow array{id?: int|string, tsumego_id?: int|string, order?: int|string, seconds?: int|float|string|null, points?: int|float|string|null, started?: string|null, time_mode_session_id?: int|string, time_mode_attempt_status_id?: int|string, ...}
 * @phpstan-type TimeModeAttemptStatusRow array{id?: int|string, name?: string, ...}
 * @phpstan-type TimeModeCategoryRow array{id?: int|string, name?: string, seconds?: int|string, ...}
 * @phpstan-type TimeModeRankRow array{id?: int|string, name?: string, ...}
 * @phpstan-type TimeModeSessionRow array{id?: int|string, user_id?: int|string, points?: int|float|string|null, created?: string, time_mode_session_status_id?: int|string, time_mode_category_id?: int|string, time_mode_rank_id?: int|string, ...}
 * @phpstan-type TimeModeSessionStatusRow array{id?: int|string, name?: string, ...}
 * @phpstan-type TsumegoAttemptRow array{id?: int|string, user_id?: int|string, tsumego_id?: int|string, gain?: int|string, solved?: int|string, seconds?: int|string, misplays?: int|string, user_rating?: int|string|null, tsumego_rating?: int|string, created?: string, ...}
 * @phpstan-type TsumegoCommentRow array{id?: int|string, tsumego_id?: int|string, tsumego_issue_id?: int|string|null, message?: string, created?: string, user_id?: int|string, position?: string|null, deleted?: int|string, ...}
 * @phpstan-type TsumegoIssueRow array{id?: int|string, tsumego_issue_status_id?: int|string, tsumego_id?: int|string, user_id?: int|string, created?: string, deleted?: int|string, ...}
 * @phpstan-type TsumegoIssueStatusRow array{id?: int|string, name?: string, ...}
 * @phpstan-type TsumegoRow array{id?: int|string, part?: string|null, part_increment?: int|string|null, description?: string|null, hint?: string|null, author?: string, solved?: int|string|null, failed?: int|string|null, rating?: int|float|string, minimum_rating?: int|float|string|null, maximum_rating?: int|float|string|null, userWin?: int|float|string, userLoss?: int|string, created?: string|null, minLib?: int|string|null, maxLib?: int|string|null, variance?: int|string|null, libertyCount?: int|string|null, insideLiberties?: int|string|null, eyeLiberties1?: int|string|null, eyeLiberties2?: int|string|null, semeaiType?: int|string|null, alternative_response?: int|string, pass?: int|string, activity_value?: int|string, deleted?: string|null, ...}
 * @phpstan-type TsumegoStatusRow array{id?: int|string, user_id?: int|string, tsumego_id?: int|string, status?: string, updated?: string, ...}
 * @phpstan-type TsumegoVariantRow array{id?: int|string, tsumego_id?: int|string, type?: string, numAnswer?: int|float|string, answer1?: string, answer2?: string, answer3?: string, answer4?: string, winner?: string|null, explanation?: string|null, created?: string, ...}
 * @phpstan-type UserContributionRow array{id?: int|string, user_id?: int|string|null, query?: string, collection_size?: int|string, filtered_sets?: string|null, filtered_ranks?: string|null, filtered_tags?: string|null, created_tag?: int|string|null, made_proposal?: int|string|null, reviewed?: int|string|null, score?: int|string|null, reward1?: int|string|null, reward2?: int|string|null, reward3?: int|string|null, created?: string|null, ...}
 * @phpstan-type UserRow array{id?: int|string, name?: string, email?: string, external_id?: string|null, picture?: string|null, premium?: int|string, level?: int|string, mode?: int|string, rating?: int|float|string, default_set_id?: int|string|null, t_glicko?: int|string, dbstorage?: int|string, created?: string|null, lastRefresh?: string|null, xp?: int|string, damage?: int|string, used_potion?: int|string, promoted?: int|string, used_intuition?: int|string, used_revelation?: int|string, readingTrial?: int|string, isAdmin?: int|string, lastHighscore?: int|string, lastLight?: int|string, levelBar?: int|string, lastProfileLeft?: int|string, lastProfileRight?: int|string, last_time_mode_category_id?: int|string|null, daily_solved?: int|string, daily_xp?: int|string, reuse4?: int|string, reuse5?: int|string, penalty?: int|string, reward?: string|null, used_sprint?: int|string, used_rejuvenation?: int|string, used_refinement?: int|string, passwordreset?: string|null, solved?: int|string|null, sortOrder?: int|string, sortColor?: int|string, sound?: string, ip?: string|null, location?: string|null, password_hash?: string, sprint_start?: string|null, login_token?: string|null, boards_bitmask?: int|string, pref_player_color?: int|string, pref_board_orientation?: int|string, ...}
 */
class RowTypes {}
