SET @failed_attempts_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'password_resets'
    AND column_name = 'failed_attempts'
);
SET @failed_attempts_ddl = IF(
  @failed_attempts_exists = 0,
  'ALTER TABLE password_resets ADD COLUMN failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER used_at',
  'SELECT 1'
);
PREPARE failed_attempts_stmt FROM @failed_attempts_ddl;
EXECUTE failed_attempts_stmt;
DEALLOCATE PREPARE failed_attempts_stmt;

SET @verified_at_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'password_resets'
    AND column_name = 'verified_at'
);
SET @verified_at_ddl = IF(
  @verified_at_exists = 0,
  'ALTER TABLE password_resets ADD COLUMN verified_at DATETIME NULL AFTER failed_attempts',
  'SELECT 1'
);
PREPARE verified_at_stmt FROM @verified_at_ddl;
EXECUTE verified_at_stmt;
DEALLOCATE PREPARE verified_at_stmt;
