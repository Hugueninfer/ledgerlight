-- Runs once on first MySQL start (empty data volume).
-- The app user is created by MYSQL_USER/MYSQL_PASSWORD; it still needs rights on the test database.
CREATE DATABASE IF NOT EXISTS `ledgerlight_test`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `ledgerlight_test`.* TO 'ledgerlight'@'%';
FLUSH PRIVILEGES;
