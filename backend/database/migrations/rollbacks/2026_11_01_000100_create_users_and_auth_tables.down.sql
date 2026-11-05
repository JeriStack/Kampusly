-- Rollback: 2026_11_01_000100_create_users_and_auth_tables
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `users`;

