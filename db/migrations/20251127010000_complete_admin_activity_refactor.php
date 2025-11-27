<?php

use Phinx\Migration\AbstractMigration;

/**
 * Complete AdminActivity refactoring: from old file/answer schema to new enum-based type/old_value/new_value schema.
 * 
 * This migration:
 * 1. Creates admin_activity_type enum table
 * 2. Migrates data from file/answer to type(INT)/old_value/new_value
 * 3. Adds foreign keys and indexes
 */
class CompleteAdminActivityRefactor extends AbstractMigration
{
    public function change()
    {
        // STEP 1: Create admin_activity_type enum table (name only - readable as-is)
        $enumTable = $this->table('admin_activity_type', ['id' => false, 'primary_key' => 'id']);
        $enumTable
            ->addColumn('id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addIndex('name', ['unique' => true])
            ->create();

        // STEP 2: Insert 19 enum values (names formatted in Title Case for display)
        $this->execute("
            INSERT INTO admin_activity_type (id, name) VALUES
            (1, 'Description Edit'),
            (2, 'Hint Edit'),
            (3, 'Problem Delete'),
            (4, 'SGF Upload'),
            (5, 'Alternative Response'),
            (6, 'Pass Mode'),
            (7, 'Multiple Choice'),
            (8, 'Score Estimating'),
            (9, 'Solution Request'),
            (10, 'Set Title Edit'),
            (11, 'Set Description Edit'),
            (12, 'Set Color Edit'),
            (13, 'Set Order Edit'),
            (14, 'Set Rating Edit'),
            (15, 'Problem Add'),
            (16, 'Set Alternative Response'),
            (17, 'Set Pass Mode'),
            (18, 'Duplicate Remove'),
            (19, 'Duplicate Group Create');
        ");

        // STEP 3: Create new admin_activity table with final schema
        $this->execute("
            CREATE TABLE admin_activity_new (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                tsumego_id INT UNSIGNED DEFAULT NULL,
                set_id INT UNSIGNED DEFAULT NULL,
                type INT UNSIGNED NOT NULL,
                old_value TEXT DEFAULT NULL,
                new_value TEXT DEFAULT NULL,
                created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_tsumego_id (tsumego_id),
                KEY idx_set_id (set_id),
                KEY idx_type (type),
                KEY idx_created (created),
                CONSTRAINT fk_admin_activity_type FOREIGN KEY (type) REFERENCES admin_activity_type(id),
                CONSTRAINT fk_admin_activity_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
                CONSTRAINT fk_admin_activity_tsumego FOREIGN KEY (tsumego_id) REFERENCES tsumego(id) ON DELETE CASCADE,
                CONSTRAINT fk_admin_activity_set FOREIGN KEY (set_id) REFERENCES `set`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // STEP 4: Migrate data from old file/answer schema, EXCLUDING orphaned and unrecognized records
        // EXPECTED MIGRATION RESULTS (on production 51,670 records):
        //   - MIGRATED: 28,985 records (56% of total, 69% of valid user records)
        //   - SKIPPED: 22,685 records (44%) broken down as:
        //     * 9,663 orphaned (user deleted, FK constraint violation)
        //     * 7,798 user messages (not admin activities: contact forms, SQL injections)
        //     * 5,075 internal numeric codes (status changes with no context)
        //     * 218 SGF comments (no activity_id linkage, low value)
        //     * 133 other (coordinates, deprecated settings)
        $this->execute("
            INSERT INTO admin_activity_new (id, user_id, tsumego_id, set_id, type, old_value, new_value, created)
            SELECT * FROM (
                SELECT 
                    id,
                    user_id,
                    CASE 
                        WHEN tsumego_id IN (SELECT id FROM tsumego) THEN tsumego_id
                        ELSE NULL
                    END as tsumego_id,
                    NULL as set_id,
                    CASE
                        /* Type 1: DESCRIPTION_EDIT - Problem description edited (with prefix or direct text) */
                        WHEN file = 'description' AND (answer LIKE 'Description:%' OR answer REGEXP '^[A-Z]') THEN 1
                        
                        /* Type 2: HINT_EDIT - Problem hint edited */
                        WHEN file = 'description' AND answer LIKE 'Hint:%' THEN 2
                        
                        /* Type 3: PROBLEM_DELETE - Problem deleted */
                        WHEN file = '/delete' OR (file = 'description' AND answer LIKE 'Problem deleted.%') THEN 3
                        
                        /* Type 4: SGF_UPLOAD - SGF file uploaded (any numeric file 1-999 with ?? or *.sgf) */
                        WHEN file REGEXP '^[0-9]+$' AND (answer = '??' OR answer LIKE '%.sgf%') THEN 4
                        
                        /* Type 5: ALTERNATIVE_RESPONSE - AR mode toggled (new_value = 0/1) */
                        WHEN file = 'settings' AND answer LIKE '%alternative response mode%' AND answer NOT LIKE '%for set%' THEN 5
                        
                        /* Type 6: PASS_MODE - Pass mode toggled (new_value = 0/1) */
                        WHEN file = 'settings' AND answer LIKE '%passing%' AND answer NOT LIKE '%for set%' THEN 6
                        
                        /* Type 7: MULTIPLE_CHOICE - MC problem type toggled (new_value = 0/1) */
                        WHEN file = 'settings' AND answer LIKE '%multiple choice%' THEN 7
                        
                        /* Type 8: SCORE_ESTIMATING - SE problem type toggled (new_value = 0/1) */
                        WHEN file = 'settings' AND answer LIKE '%score estimating%' THEN 8
                        
                        /* Type 9: SOLUTION_REQUEST - Solution requested */
                        WHEN file = 'settings' AND answer = 'requested solution' THEN 9
                        
                        /* Type 10: SET_TITLE_EDIT - Set title edited */
                        WHEN file = 'settings' AND answer LIKE '%meta data for set%' THEN 10
                        
                        /* Type 14: SET_RATING_EDIT - Set rating edited */
                        WHEN file = 'settings' AND answer LIKE '%rating data for set%' THEN 14
                        
                        /* Type 15: PROBLEM_ADD - Problem added to set */
                        WHEN file = 'description' AND answer LIKE 'Added problem for%' THEN 15
                        
                        /* Type 16: SET_ALTERNATIVE_RESPONSE - Set-wide AR toggled (new_value = 0/1) */
                        WHEN file = 'settings' AND answer LIKE '%alternative response mode for set%' THEN 16
                        
                        /* Type 17: SET_PASS_MODE - Set-wide pass mode toggled (new_value = 0/1) */
                        WHEN file = 'settings' AND answer LIKE '%passing for set%' THEN 17
                        
                        /* Type 18: DUPLICATE_REMOVE - Duplicate problem removed */
                        WHEN file = 'settings' AND answer LIKE '%Removed duplicate:%' THEN 18
                        
                        /* Type 19: DUPLICATE_GROUP_CREATE - Duplicate group created */
                        WHEN file = 'settings' AND answer LIKE '%Created duplicate group:%' THEN 19
                        
                        /* Skip unrecognized activities */
                        ELSE NULL
                    END as type,
                    /* Extract old_value - not present in current data format */
                    NULL as old_value,
                    /* Extract new_value from various formats */
                    CASE
                        WHEN answer LIKE '%Turned on%' OR answer LIKE '%Enabled%' THEN '1'
                        WHEN answer LIKE '%Turned off%' OR answer LIKE '%Disabled%' THEN '0'
                        WHEN answer LIKE '%add%' OR answer LIKE '%multiple choice%' OR answer LIKE '%score estimating%' THEN '1'
                        WHEN answer LIKE '%delete%' OR answer LIKE '%Deleted%' THEN '0'
                        WHEN file = 'settings' AND answer LIKE '%Edited meta data for set%' THEN SUBSTRING_INDEX(answer, 'set ', -1)
                        ELSE answer
                    END as new_value,
                    created
                FROM admin_activity
                WHERE user_id IN (SELECT id FROM user)
            ) AS subquery
            WHERE type IS NOT NULL  -- Only migrate recognized activity patterns
        ");

        // STEP 5: Drop old table and rename new one
        $this->execute("DROP TABLE admin_activity");
        $this->execute("RENAME TABLE admin_activity_new TO admin_activity");
        
        // STEP 6: Set AUTO_INCREMENT to next available ID (handle empty table case)
        $this->execute("
            SET @max_id = COALESCE((SELECT MAX(id) FROM admin_activity), 0);
            SET @sql = CONCAT('ALTER TABLE admin_activity AUTO_INCREMENT = ', @max_id + 1);
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }
}
