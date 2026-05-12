CREATE DATABASE IF NOT EXISTS `mashirikianosacc_mashirikiano`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mashirikianosacc_mashirikiano`;

CREATE TABLE IF NOT EXISTS `members` (
  `MemberID` INT NOT NULL AUTO_INCREMENT,
  `NationalID` VARCHAR(30) NOT NULL,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `PrimaryNumber` VARCHAR(20) NOT NULL,
  `Email` VARCHAR(150) NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Status` ENUM('Pending','Active','Suspended') NOT NULL DEFAULT 'Pending',
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MemberID`),
  UNIQUE KEY `uniq_members_national_id` (`NationalID`),
  KEY `idx_members_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `TranID` VARCHAR(60) NOT NULL,
  `MemberID` INT NULL,
  `NationalID` VARCHAR(30) NOT NULL,
  `FirstName` VARCHAR(100) NOT NULL,
  `LastName` VARCHAR(100) NOT NULL,
  `MSISDN` VARCHAR(20) NOT NULL,
  `Amount` DECIMAL(12,2) NOT NULL,
  `TranTime` DATETIME NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TranID`),
  KEY `idx_transactions_member` (`MemberID`),
  KEY `idx_transactions_national_id` (`NationalID`),
  KEY `idx_transactions_time` (`TranTime`),
  CONSTRAINT `fk_transactions_member`
    FOREIGN KEY (`MemberID`) REFERENCES `members` (`MemberID`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW `member_contribution_totals` AS
SELECT
  m.MemberID,
  m.NationalID,
  m.FirstName,
  m.LastName,
  COALESCE(SUM(t.Amount), 0) AS TotalContributions,
  COUNT(t.TranID) AS TransactionCount
FROM members m
LEFT JOIN transactions t ON t.MemberID = m.MemberID
GROUP BY m.MemberID, m.NationalID, m.FirstName, m.LastName;
