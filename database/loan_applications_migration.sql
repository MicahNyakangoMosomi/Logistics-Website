USE `mashirikianosacc_mashirikiano`;

CREATE TABLE IF NOT EXISTS `loan_applications` (
  `LoanApplicationID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `MemberID` VARCHAR(40) NOT NULL,
  `LoanType` VARCHAR(100) NOT NULL,
  `Amount` DECIMAL(12,2) NOT NULL,
  `ReturnDate` DATE NOT NULL,
  `Status` ENUM('Pending', 'Approved', 'Not Approved') NOT NULL DEFAULT 'Pending',
  `RejectionReason` VARCHAR(255) NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`LoanApplicationID`),
  KEY `idx_loan_applications_member` (`MemberID`),
  KEY `idx_loan_applications_status` (`Status`),
  CONSTRAINT `fk_loan_applications_member`
    FOREIGN KEY (`MemberID`) REFERENCES `members` (`MemberID`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
