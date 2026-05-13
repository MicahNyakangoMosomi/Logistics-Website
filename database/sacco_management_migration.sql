USE `mashirikianosacc_mashirikiano`;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `AdminUserID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `FullName` VARCHAR(150) NOT NULL,
  `Email` VARCHAR(150) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Role` ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `Status` ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminUserID`),
  UNIQUE KEY `uniq_admin_users_email` (`Email`),
  KEY `idx_admin_users_role` (`Role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @admin_password_column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'admin_users'
    AND COLUMN_NAME = 'Password'
);

SET @add_admin_password_sql := IF(
  @admin_password_column_exists > 0,
  'SELECT "admin_users.Password already exists" AS migration_notice',
  'ALTER TABLE `admin_users` ADD COLUMN `Password` VARCHAR(255) NOT NULL DEFAULT "" AFTER `Email`'
);

PREPARE add_admin_password_stmt FROM @add_admin_password_sql;
EXECUTE add_admin_password_stmt;
DEALLOCATE PREPARE add_admin_password_stmt;

CREATE TABLE IF NOT EXISTS `contributions` (
  `ContributionID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `TranID` VARCHAR(60) NOT NULL,
  `MemberID` VARCHAR(40) NULL,
  `NationalID` VARCHAR(30) NOT NULL,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `MSISDN` VARCHAR(20) NOT NULL,
  `Amount` DECIMAL(12,2) NOT NULL,
  `TranTime` DATETIME NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ContributionID`),
  UNIQUE KEY `uniq_contributions_tran_id` (`TranID`),
  KEY `idx_contributions_member` (`MemberID`),
  KEY `idx_contributions_national_id` (`NationalID`),
  KEY `idx_contributions_time` (`TranTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @transactions_table_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
);

SET @copy_transactions_sql := IF(
  @transactions_table_exists > 0,
  'INSERT IGNORE INTO `contributions` (`TranID`, `MemberID`, `NationalID`, `FirstName`, `LastName`, `MSISDN`, `Amount`, `TranTime`, `CreatedAt`) SELECT `TranID`, CAST(`MemberID` AS CHAR), `NationalID`, `FirstName`, `LastName`, `MSISDN`, `Amount`, `TranTime`, `CreatedAt` FROM `transactions`',
  'SELECT "No transactions table found; skipping old transaction copy" AS migration_notice'
);

PREPARE copy_transactions_stmt FROM @copy_transactions_sql;
EXECUTE copy_transactions_stmt;
DEALLOCATE PREPARE copy_transactions_stmt;

SET @fk_name := (
  SELECT CONSTRAINT_NAME
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions'
    AND COLUMN_NAME = 'MemberID'
    AND REFERENCED_TABLE_NAME = 'members'
  LIMIT 1
);

SET @drop_fk_sql := IF(
  @fk_name IS NULL,
  'SELECT "No transactions.MemberID foreign key found" AS migration_notice',
  CONCAT('ALTER TABLE `transactions` DROP FOREIGN KEY `', @fk_name, '`')
);

PREPARE drop_fk_stmt FROM @drop_fk_sql;
EXECUTE drop_fk_stmt;
DEALLOCATE PREPARE drop_fk_stmt;

ALTER TABLE `members`
  MODIFY `MemberID` VARCHAR(40) NOT NULL,
  MODIFY `Status` ENUM('Active','Suspended','Pending') NOT NULL DEFAULT 'Active';

SET @alter_transactions_member_sql := IF(
  @transactions_table_exists > 0,
  'ALTER TABLE `transactions` MODIFY `MemberID` VARCHAR(40) NULL',
  'SELECT "No transactions table found; skipping transactions.MemberID conversion" AS migration_notice'
);

PREPARE alter_transactions_member_stmt FROM @alter_transactions_member_sql;
EXECUTE alter_transactions_member_stmt;
DEALLOCATE PREPARE alter_transactions_member_stmt;

SET @contributions_fk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contributions'
    AND COLUMN_NAME = 'MemberID'
    AND REFERENCED_TABLE_NAME = 'members'
);

SET @add_contributions_fk_sql := IF(
  @contributions_fk_exists > 0,
  'SELECT "contributions.MemberID foreign key already exists" AS migration_notice',
  'ALTER TABLE `contributions` ADD CONSTRAINT `fk_contributions_member` FOREIGN KEY (`MemberID`) REFERENCES `members` (`MemberID`) ON UPDATE CASCADE ON DELETE SET NULL'
);

PREPARE add_contributions_fk_stmt FROM @add_contributions_fk_sql;
EXECUTE add_contributions_fk_stmt;
DEALLOCATE PREPARE add_contributions_fk_stmt;

CREATE OR REPLACE VIEW `member_contribution_totals` AS
SELECT
  m.MemberID,
  m.NationalID,
  m.FirstName,
  m.LastName,
  COALESCE(SUM(c.Amount), 0) AS TotalContributions,
  COUNT(c.ContributionID) AS ContributionCount
FROM members m
LEFT JOIN contributions c ON c.MemberID = m.MemberID
GROUP BY m.MemberID, m.NationalID, m.FirstName, m.LastName;
