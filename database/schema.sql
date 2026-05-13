CREATE DATABASE IF NOT EXISTS `mashirikianosacc_mashirikiano`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mashirikianosacc_mashirikiano`;

CREATE TABLE IF NOT EXISTS `members` (
  `MemberID` VARCHAR(40) NOT NULL,
  `NationalID` VARCHAR(30) NOT NULL,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `PrimaryNumber` VARCHAR(20) NOT NULL,
  `Email` VARCHAR(150) NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Status` ENUM('Active','Suspended','Pending') NOT NULL DEFAULT 'Active',
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MemberID`),
  UNIQUE KEY `uniq_members_national_id` (`NationalID`),
  KEY `idx_members_status` (`Status`),
  KEY `idx_members_name` (`FirstName`, `LastName`),
  KEY `idx_members_phone` (`PrimaryNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_contributions_time` (`TranTime`),
  CONSTRAINT `fk_contributions_member`
    FOREIGN KEY (`MemberID`) REFERENCES `members` (`MemberID`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
