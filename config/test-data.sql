-- Minimal test data for CI tests
-- Insert a test user named 'kovarex'
INSERT INTO `users` (
    `id`,
    `name`,
    `email`,
    `password_hash`,
    `elo_rating_mode`,
    `created`
) VALUES (
    1,
    'kovarex',
    'test@example.com',
    '$2y$10$5.F2n794IrgFcLRBnE.rju1ZoJheRr1fVc4SYq5ICeaJG0C800TRG', --password hash of 'test'
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
