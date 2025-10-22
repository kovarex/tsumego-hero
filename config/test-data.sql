-- Minimal test data for CI tests
-- Insert a test user named 'kovarex'
INSERT INTO `users` (
    `id`,
    `name`,
    `email`,
    `password`,
    `elo_rating_mode`,
    `created`,
    `modified`
) VALUES (
    1,
    'kovarex',
    'test@example.com',
    'test',
    1500,
    NOW(),
    NOW()
);

-- Insert a test tsumego
INSERT INTO `tsumegos` (
    `id`,
    `num`,
    `created`,
    `modified`
) VALUES (
    1,
    1,
    NOW(),
    NOW()
);
