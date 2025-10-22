-- Minimal test data for CI tests
-- Insert a test user named 'kovarex'
INSERT INTO `users` (
    `id`,
    `name`,
    `email`,
    `pw`,
    `elo_rating_mode`,
    `created`
) VALUES (
    1,
    'kovarex',
    'test@example.com',
    'test',
    1500,
    NOW()
);

-- Insert a test tsumego
INSERT INTO `tsumegos` (
    `id`,
    `num`,
    `created`
) VALUES (
    1,
    1,
    NOW()
);
