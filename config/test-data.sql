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

INSERT INTO time_mode_rank(`name`) VALUES ('15k'),('14k'), ('13k'), ('12k'), ('11k'), ('10k'), ('9k'), ('8k'), ('7k'), ('6k'), ('5k'), ('4k'), ('3k'), ('2k'), ('1k'), ('1d'), ('2d'), ('3d'), ('4d'), ('5d');
INSERT INTO time_mode_category(`name`, `seconds`) VALUES ('Blitz', 30),('Fast', 60), ('Slow', 240);
INSERT INTO time_mode_attempt_result(`name`) VALUES ('solved'),('failed'), ('timeout'), ('skipped');
INSERT INTO time_mode_session_status(`name`) VALUES ('in progress'),('failed'), ('solved');
