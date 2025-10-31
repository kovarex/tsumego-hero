-- Minimal test data for CI tests
-- Insert a test user named 'kovarex'
INSERT INTO `user` (
    `id`,
    `name`,
    `email`,
    `password_hash`,
    `rating`,
    `created`
) VALUES (
    1,
    'kovarex',
    'test@example.com',
    '$2y$10$5.F2n794IrgFcLRBnE.rju1ZoJheRr1fVc4SYq5ICeaJG0C800TRG',
    1500,
    NOW()
);
