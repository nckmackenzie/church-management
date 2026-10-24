-- GENERATED FROM DEV (task 8.9 follow-up 3, mysqldump --no-data --routines --triggers of the dev database `cms`, 2026-10-23) - REVIEW.
-- REFERENCE ONLY: DO NOT RUN THIS FILE ON PRODUCTION (it would try to create every table). Its purpose is the schema diff of
-- runbook step 1.5: dump production the same way and compare, e.g.
--     mysqldump -u root -p --no-data --routines --triggers --skip-comments --skip-add-drop-table cms > prod_schema.sql
--     php scripts/logs/schemadiff.php prod_schema.sql ledger-migration/dev_schema_snapshot.sql   (or any diff tool; ignore AUTO_INCREMENT=)
-- Differences that are NOT created by a script of the runbook are dev changes made by hand and must be classified before the cutover.
-- Contains 80 tables, 33 views, 108 functions / procedures and no triggers (dev has none).

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `congregationId` int(11) NOT NULL,
  `entryDate` date NOT NULL,
  `transactionTypeId` int(11) NOT NULL,
  `transactionId` int(11) NOT NULL,
  `journalNo` int(11) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `activeKey` tinyint(4) GENERATED ALWAYS AS (if(`deleted` = 0,1,NULL)) STORED,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_je_source_active` (`congregationId`,`transactionTypeId`,`transactionId`,`activeKey`),
  UNIQUE KEY `uq_je_journalno` (`congregationId`,`journalNo`),
  KEY `ix_je_date` (`congregationId`,`deleted`,`entryDate`),
  KEY `ix_je_txntype` (`transactionTypeId`),
  CONSTRAINT `fk_je_congregation` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `fk_je_txntype` FOREIGN KEY (`transactionTypeId`) REFERENCES `tbltransactiontypes` (`ID`),
  CONSTRAINT `chk_je_deleted` CHECK (`deleted` in (0,1)),
  CONSTRAINT `chk_je_journalno` CHECK (`journalNo` is null or `transactionTypeId` = 5)
) ENGINE=InnoDB AUTO_INCREMENT=25063 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_lines` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `journalEntryId` int(11) NOT NULL,
  `accountId` int(11) NOT NULL,
  `dc` enum('DEBIT','CREDIT') NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `memo` varchar(500) DEFAULT NULL,
  `lineNumber` smallint(5) unsigned NOT NULL,
  `legacyLedgerId` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_jl_line` (`journalEntryId`,`lineNumber`),
  UNIQUE KEY `uq_jl_legacy` (`legacyLedgerId`),
  KEY `ix_jl_account` (`accountId`,`journalEntryId`),
  CONSTRAINT `fk_jl_account` FOREIGN KEY (`accountId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `fk_jl_entry` FOREIGN KEY (`journalEntryId`) REFERENCES `journal_entries` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `chk_jl_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=53585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_number_seq` (
  `congregationId` int(11) NOT NULL,
  `lastJournalNo` int(11) NOT NULL,
  PRIMARY KEY (`congregationId`),
  CONSTRAINT `fk_jns_congregation` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_number_seq` (
  `congregationId` int(11) NOT NULL,
  `kind` varchar(10) NOT NULL,
  `fiscalYearId` int(11) NOT NULL DEFAULT 0,
  `lastNo` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`congregationId`,`kind`,`fiscalYearId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblaccounttypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `accountType` varchar(100) NOT NULL,
  `accountTypeId` int(11) DEFAULT NULL,
  `parentId` int(11) DEFAULT NULL,
  `isSubCategory` tinyint(4) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `isBank` tinyint(4) NOT NULL DEFAULT 0,
  `accountNo` varchar(100) DEFAULT NULL,
  `forGroup` tinyint(4) NOT NULL DEFAULT 0,
  `isEditable` tinyint(4) NOT NULL DEFAULT 1,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `congregationId` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=298 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblactivities` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ActivityName` varchar(255) NOT NULL,
  `CongregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_CongregationId_Activity` (`CongregationId`),
  CONSTRAINT `FK_CongregationId_Activity` FOREIGN KEY (`CongregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblagegroup` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ageGroupName` text NOT NULL,
  `fromAge` int(11) NOT NULL,
  `toAge` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblbankbalances` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TransactionDate` date NOT NULL,
  `BankId` int(11) NOT NULL,
  `Amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `CongregationId` int(11) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblbankpostings` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `bankId` int(11) NOT NULL,
  `transactionDate` date NOT NULL,
  `debit` float NOT NULL,
  `credit` float NOT NULL,
  `transactionMethod` int(11) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `cleared` tinyint(4) NOT NULL DEFAULT 0,
  `clearedDare` date DEFAULT NULL,
  `accountId` int(11) DEFAULT NULL,
  `narration` varchar(255) DEFAULT NULL,
  `transactionType` int(11) NOT NULL,
  `transactionId` int(11) DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_transactionMethod_bank` (`transactionMethod`),
  KEY `FK_congregationId_Bank` (`congregationId`),
  KEY `IDX_BankPostings_Transaction` (`transactionType`,`transactionId`),
  CONSTRAINT `FK_congregationId_Bank` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_transactionMethod_bank` FOREIGN KEY (`transactionMethod`) REFERENCES `tbltransactionmethods` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=13287 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblbanksubaccounts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `AccountName` varchar(100) NOT NULL,
  `BankId` int(11) NOT NULL,
  `AccountId` int(11) NOT NULL,
  `GroupDistrict` varchar(10) NOT NULL,
  `GroupId` int(11) DEFAULT NULL,
  `DistrictId` int(11) DEFAULT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblbanktransactions` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TransactionDate` date NOT NULL,
  `TransactionTypeId` int(11) NOT NULL,
  `BankId` int(11) NOT NULL,
  `Amount` decimal(18,2) NOT NULL,
  `TransferToId` int(11) DEFAULT NULL,
  `CashAccountId` int(11) DEFAULT NULL,
  `Reference` varchar(150) DEFAULT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `IsSubInterTransfer` bigint(20) NOT NULL DEFAULT 0,
  `DepositToSubAccounts` tinyint(4) NOT NULL DEFAULT 0,
  `CongregationId` int(11) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_BankId_BankTransactions` (`BankId`),
  KEY `FK_TransferedToId_BankTransactions` (`TransferToId`),
  KEY `FK_CongregationId_BankTransactions` (`CongregationId`),
  KEY `IX_CashAccountId_BankTransactions` (`CashAccountId`),
  CONSTRAINT `FK_BankId_BankTransactions` FOREIGN KEY (`BankId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `FK_CashAccount_BankTransactions` FOREIGN KEY (`CashAccountId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `FK_CongregationId_BankTransactions` FOREIGN KEY (`CongregationId`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_TransferedToId_BankTransactions` FOREIGN KEY (`TransferToId`) REFERENCES `tblaccounttypes` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=687 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblbanktransactions_subaccounts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TransactionDate` date DEFAULT NULL,
  `TransactionId` int(11) NOT NULL,
  `SubAccountId` int(11) NOT NULL,
  `Amount` decimal(18,2) NOT NULL,
  `FromAccountId` int(11) DEFAULT NULL,
  `ToAccountId` int(11) DEFAULT NULL,
  `Narration` varchar(255) DEFAULT NULL,
  `TransactionType` int(11) DEFAULT NULL,
  `Reference` varchar(120) DEFAULT NULL,
  `CongregationId` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_TransactionId_BankTxn` (`TransactionId`),
  KEY `FK_SubbAccountId_BankTxn` (`SubAccountId`),
  CONSTRAINT `FK_SubbAccountId_BankTxn` FOREIGN KEY (`SubAccountId`) REFERENCES `tblbanksubaccounts` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1456 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblchurchbudget_details` (
  `tid` int(11) NOT NULL AUTO_INCREMENT,
  `ID` int(11) NOT NULL,
  `accountId` int(11) NOT NULL,
  `amount` float NOT NULL,
  PRIMARY KEY (`tid`),
  KEY `FK_headerId_budgetChurch` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblchurchbudget_header` (
  `ID` int(11) NOT NULL,
  `budgetName` varchar(50) NOT NULL,
  `yearId` int(11) NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_congregationId_tblbudget` (`congregationId`),
  KEY `FK_yearId_tblbudget` (`yearId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblchurchrequisitioncategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoryName` varchar(45) NOT NULL,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcolumns` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `columnName` varchar(255) NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcongregation` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ParishName` varchar(255) DEFAULT 'KALIMONI PARISH',
  `CongregationName` varchar(255) NOT NULL,
  `contact` tinytext DEFAULT NULL,
  `email` tinytext DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `AboutUs` varchar(255) DEFAULT NULL,
  `IsParish` tinyint(4) DEFAULT NULL,
  `prefix` tinytext DEFAULT NULL,
  `InaugurationDate` date DEFAULT NULL,
  `SactuaryType` varchar(15) DEFAULT NULL,
  `YearStarted` int(11) DEFAULT NULL,
  `FoundationStone` date DEFAULT NULL,
  `DedicationDate` date DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcontributioncategories` (
  `ID` int(11) NOT NULL,
  `categoryName` varchar(20) NOT NULL,
  `ForParish` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcontributions_details` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `HeaderId` int(11) DEFAULT NULL,
  `contributionDate` date NOT NULL,
  `contributionTypeId` int(11) NOT NULL,
  `paymentMethodId` int(11) NOT NULL,
  `bankId` int(11) DEFAULT NULL,
  `amount` float NOT NULL,
  `category` int(11) NOT NULL,
  `contributor` int(11) DEFAULT NULL,
  `contributotGroup` int(11) DEFAULT NULL,
  `contributotDistrict` int(11) DEFAULT NULL,
  `contributotService` int(11) DEFAULT NULL,
  `contributotCong` int(11) DEFAULT NULL,
  `paymentReference` varchar(255) DEFAULT NULL,
  `narration` varchar(50) DEFAULT NULL,
  `incomeType` int(11) NOT NULL DEFAULT 1,
  `forGroup` tinyint(4) NOT NULL DEFAULT 0,
  `subaccount` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_paymethod_contributions` (`paymentMethodId`),
  KEY `FK_categoryId` (`category`),
  KEY `FK_memberId_contribtions` (`contributor`),
  KEY `FK_groupId_contributions` (`contributotGroup`),
  KEY `FK_district_contributions` (`contributotDistrict`),
  KEY `FK_service_contributions` (`contributotService`),
  KEY `FK_ContTypeId_Contributions` (`contributionTypeId`),
  CONSTRAINT `FK_ContTypeId_Contributions` FOREIGN KEY (`contributionTypeId`) REFERENCES `tblaccounttypes` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=12683 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcontributions_header` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `fiscalYearId` int(11) NOT NULL,
  `postedBy` int(11) NOT NULL,
  `postedDate` date NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `approvedBy` int(11) DEFAULT NULL,
  `approvedDate` date DEFAULT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  `receiptNo` varchar(15) DEFAULT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_fiscalid_contributions` (`fiscalYearId`),
  KEY `FK_postBy_contributions` (`postedBy`),
  KEY `FK_approvedBy_contributions` (`approvedBy`),
  KEY `FK_CongregationId_Contributions` (`congregationId`)
) ENGINE=InnoDB AUTO_INCREMENT=11618 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblcustomers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `customerName` varchar(255) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `pin` varchar(15) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contactPerson` varchar(255) DEFAULT NULL,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_congregationId_Customer` (`congregationId`),
  CONSTRAINT `FK_congregationId_Customer` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbldesposits` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `DepositDate` date NOT NULL,
  `BankId` int(11) NOT NULL,
  `Amount` int(11) NOT NULL,
  `Reference` varchar(150) DEFAULT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `CongregationId` int(11) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_BankId_Deposits` (`BankId`),
  KEY `FK_CongregationId_Deposits` (`CongregationId`),
  CONSTRAINT `FK_BankId_Deposits` FOREIGN KEY (`BankId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `FK_CongregationId_Deposits` FOREIGN KEY (`CongregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbldistrictchange` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `memberId` int(11) NOT NULL,
  `fromId` int(11) NOT NULL,
  `toid` int(11) NOT NULL,
  `transferDate` date NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_member_id` (`memberId`),
  KEY `FK_from` (`fromId`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbldistricts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `districtName` varchar(255) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_tblDistricts_CongregationID` (`congregationId`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbleldermovement` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ElderId` int(11) NOT NULL,
  `FromCongregation` int(11) DEFAULT NULL,
  `ToCongregation` int(11) NOT NULL,
  `FromDistrict` int(11) DEFAULT NULL,
  `ToDistrict` int(11) NOT NULL,
  `TransferDate` date DEFAULT NULL,
  `IsTransfer` tinyint(4) NOT NULL DEFAULT 1,
  `Reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_Elderid` (`ElderId`),
  KEY `FK_FromCong` (`FromCongregation`),
  KEY `FK_FromDist` (`FromDistrict`),
  KEY `FK_ToCong` (`ToCongregation`),
  KEY `FK_ToDist` (`ToDistrict`),
  CONSTRAINT `FK_Elderid` FOREIGN KEY (`ElderId`) REFERENCES `tblelders` (`ID`),
  CONSTRAINT `FK_FromCong` FOREIGN KEY (`FromCongregation`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_FromDist` FOREIGN KEY (`FromDistrict`) REFERENCES `tbldistricts` (`ID`),
  CONSTRAINT `FK_ToCong` FOREIGN KEY (`ToCongregation`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_ToDist` FOREIGN KEY (`ToDistrict`) REFERENCES `tbldistricts` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblelders` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ElderName` varchar(150) NOT NULL,
  `Contact` varchar(10) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblexpenseaccounts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `expenseAccount` varchar(255) NOT NULL,
  `expenseCategory` int(11) NOT NULL DEFAULT 3,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblexpenses` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `fiscalYearId` int(11) DEFAULT NULL,
  `voucherNo` int(11) NOT NULL,
  `expenseType` int(11) NOT NULL,
  `expenseDate` date NOT NULL,
  `accountId` int(11) NOT NULL,
  `groupId` int(11) DEFAULT NULL,
  `districtId` int(11) DEFAULT NULL,
  `paymethodId` int(11) NOT NULL,
  `deductfrom` varchar(100) DEFAULT NULL,
  `bankId` int(11) DEFAULT NULL,
  `PaidFromAccountId` int(11) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `narration` varchar(50) DEFAULT NULL,
  `paymentReference` varchar(100) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `hasAttachment` tinyint(4) NOT NULL DEFAULT 0,
  `fileName` varchar(255) DEFAULT NULL,
  `requisitionId` int(11) DEFAULT NULL,
  `postedBy` int(11) NOT NULL,
  `postedDate` date NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_congregationId_expenses` (`congregationId`),
  KEY `FK_groupId_expense` (`groupId`),
  KEY `FK_paymethod_expenses` (`paymethodId`),
  KEY `FK_accountId_Expense` (`accountId`),
  KEY `IDX_Expense_PaidFromAccountId` (`PaidFromAccountId`),
  CONSTRAINT `FK_Expense_PaidFromAccount` FOREIGN KEY (`PaidFromAccountId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `FK_accountId_Expense` FOREIGN KEY (`accountId`) REFERENCES `tblaccounttypes` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblfiscalyears` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `yearName` tinytext NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  `closed` tinyint(4) NOT NULL DEFAULT 0,
  `createdDate` date NOT NULL,
  `createdBy` int(11) NOT NULL,
  `closedDate` date DEFAULT NULL,
  `prefixReferences` tinyint(4) NOT NULL DEFAULT 0,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_userId_tblyears` (`createdBy`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblforms` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FormName` varchar(60) NOT NULL,
  `Module` varchar(50) DEFAULT NULL,
  `ModuleId` int(11) DEFAULT NULL,
  `Path` varchar(150) NOT NULL,
  `MenuOrder` int(11) DEFAULT NULL,
  `ParishNav` tinyint(4) NOT NULL DEFAULT 0,
  `CongregationNav` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblfundrequisition` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ReqNo` int(11) DEFAULT NULL,
  `RequisitionDate` date NOT NULL,
  `RequestType` varchar(15) DEFAULT 'group',
  `DistrictId` int(11) DEFAULT NULL,
  `GroupId` int(11) DEFAULT NULL,
  `ChurchCategoryId` int(11) DEFAULT NULL,
  `Purpose` varchar(155) NOT NULL,
  `AmountRequested` decimal(18,2) DEFAULT NULL,
  `RequestedBy` int(11) DEFAULT NULL,
  `AmountApproved` decimal(18,2) DEFAULT NULL,
  `ApprovalDate` date DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `Status` int(11) NOT NULL DEFAULT 0,
  `DontDeduct` tinyint(4) NOT NULL DEFAULT 0,
  `ReasonForRejection` varchar(150) DEFAULT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  `CongregationId` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_GroupId_Req` (`GroupId`),
  KEY `FK_UserId_Req` (`ApprovedBy`),
  CONSTRAINT `FK_GroupId_Req` FOREIGN KEY (`GroupId`) REFERENCES `tblgroups` (`ID`),
  CONSTRAINT `FK_UserId_Req` FOREIGN KEY (`ApprovedBy`) REFERENCES `tblusers` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=535 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgender` (
  `ID` int(11) NOT NULL,
  `gender` tinytext NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblglaccounts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `accountName` varchar(255) NOT NULL,
  `accountTypeId` int(11) NOT NULL,
  `narration` varchar(255) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgroupbudget_details` (
  `tid` int(11) NOT NULL AUTO_INCREMENT,
  `ID` int(11) NOT NULL,
  `accountId` int(11) NOT NULL,
  `amount` float NOT NULL,
  PRIMARY KEY (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgroupbudget_header` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `groupId` int(11) NOT NULL,
  `fiscalYearId` int(11) NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_groupid_groupbudget` (`groupId`),
  KEY `FK_congregationId_groupbudget` (`congregationId`),
  KEY `FK_fiscal_groupbudget` (`fiscalYearId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgroupmembership` (
  `memberId` int(11) NOT NULL,
  `groupId` int(11) NOT NULL,
  KEY `FK_memberId_groupMembership` (`memberId`),
  KEY `FK_groupId_groupMembership` (`groupId`),
  CONSTRAINT `FK_groupId_groupMembership` FOREIGN KEY (`groupId`) REFERENCES `tblgroups` (`ID`),
  CONSTRAINT `FK_memberId_groupMembership` FOREIGN KEY (`memberId`) REFERENCES `tblmember` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgroupofficials` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `yearId` int(11) NOT NULL,
  `groupId` int(11) DEFAULT NULL,
  `chairmanId` int(11) DEFAULT NULL,
  `secretaryId` int(11) DEFAULT NULL,
  `treasurerId` int(11) DEFAULT NULL,
  `vchairmanId` int(11) DEFAULT NULL,
  `vsecretaryId` int(11) DEFAULT NULL,
  `vtreasurerId` int(11) DEFAULT NULL,
  `patronId` int(11) DEFAULT NULL,
  `congregationId` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_yearId` (`yearId`),
  KEY `FK_groupId` (`groupId`),
  KEY `FK_chairmanId` (`chairmanId`),
  KEY `FK_secretaryId` (`secretaryId`),
  KEY `FK_treasurerId` (`treasurerId`),
  KEY `FK_vchairId` (`vchairmanId`),
  KEY `FK_vsec` (`vsecretaryId`),
  KEY `FK_vtreasId` (`vtreasurerId`),
  KEY `FK_patronId` (`patronId`),
  KEY `FK_congregation` (`congregationId`),
  CONSTRAINT `FK_chairmanId` FOREIGN KEY (`chairmanId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_congregation` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_groupId` FOREIGN KEY (`groupId`) REFERENCES `tblgroups` (`ID`),
  CONSTRAINT `FK_patronId` FOREIGN KEY (`patronId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_secretaryId` FOREIGN KEY (`secretaryId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_treasurerId` FOREIGN KEY (`treasurerId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_vchairId` FOREIGN KEY (`vchairmanId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_vsec` FOREIGN KEY (`vsecretaryId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_vtreasId` FOREIGN KEY (`vtreasurerId`) REFERENCES `tblmember` (`ID`),
  CONSTRAINT `FK_yearId` FOREIGN KEY (`yearId`) REFERENCES `tblfiscalyears` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblgroups` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `groupID` tinytext DEFAULT NULL,
  `groupName` tinytext NOT NULL,
  `active` tinyint(4) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_tblGroups_CongregationID` (`congregationId`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblincomecategories` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `categoryName` varchar(255) NOT NULL,
  `forGroups` tinyint(4) NOT NULL DEFAULT 0,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblindustries` (
  `ID` int(11) NOT NULL,
  `industry` varchar(255) NOT NULL,
  `specify` tinyint(4) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblinvoice_details` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `productId` int(11) NOT NULL,
  `qty` float NOT NULL,
  `rate` float NOT NULL,
  `gross` float NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblinvoice_details_suppliers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `productId` int(11) NOT NULL,
  `qty` float NOT NULL,
  `rate` float NOT NULL,
  `gross` float NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblinvoice_header` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceDate` date NOT NULL,
  `duedate` date DEFAULT NULL,
  `customerId` int(11) NOT NULL,
  `invoiceNo` int(11) NOT NULL,
  `fiscalYearId` int(11) NOT NULL,
  `vattype` int(11) DEFAULT NULL,
  `vatId` int(11) DEFAULT NULL,
  `exclusiveVat` float DEFAULT NULL,
  `vat` float DEFAULT NULL,
  `inclusiveVat` float DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `postedBy` int(11) NOT NULL,
  `postedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblinvoice_header_suppliers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceDate` date NOT NULL,
  `duedate` date DEFAULT NULL,
  `supplierId` int(11) NOT NULL,
  `invoiceNo` int(11) DEFAULT NULL,
  `fiscalYearId` int(11) NOT NULL,
  `vattype` int(11) DEFAULT NULL,
  `vatId` int(11) DEFAULT NULL,
  `exclusiveVat` float DEFAULT NULL,
  `vat` float DEFAULT NULL,
  `inclusiveVat` float DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `postedBy` int(11) NOT NULL,
  `postedDate` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_SupplierId_Invoice` (`supplierId`),
  KEY `FK_FiscalYear_SuppInvoice` (`fiscalYearId`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblinvoice_payments` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_Id` int(11) NOT NULL,
  `paymentDate` date NOT NULL,
  `amount` float NOT NULL,
  `paymentId` int(11) DEFAULT NULL,
  `bankId` int(11) DEFAULT NULL,
  `paymentReference` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblinvoice_payments_suppliers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `paymentNo` int(11) DEFAULT NULL,
  `paymentNo2` int(11) DEFAULT NULL,
  `invoice_Id` int(11) NOT NULL,
  `paymentDate` date NOT NULL,
  `amount` float NOT NULL,
  `paymentId` int(11) DEFAULT NULL,
  `bankId` int(11) DEFAULT NULL,
  `paymentReference` varchar(100) DEFAULT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblledger` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `transactionDate` date NOT NULL,
  `account` varchar(100) NOT NULL,
  `parentaccount` varchar(100) DEFAULT NULL,
  `debit` decimal(18,2) NOT NULL,
  `credit` decimal(18,2) NOT NULL,
  `narration` varchar(255) DEFAULT NULL,
  `reference` varchar(155) DEFAULT NULL,
  `accountId` int(11) NOT NULL,
  `transactionType` int(11) NOT NULL,
  `transactionId` int(11) NOT NULL,
  `isJournal` tinyint(4) NOT NULL DEFAULT 0,
  `journalNo` int(11) DEFAULT NULL,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_congregationId_tblledger` (`congregationId`)
) ENGINE=InnoDB AUTO_INCREMENT=34927 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbllogs` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL,
  `activity` text NOT NULL,
  `activityDate` date NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_userId_tbllogs` (`userId`),
  KEY `FK_congregationId_tbllogs` (`congregationId`)
) ENGINE=InnoDB AUTO_INCREMENT=19940 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmaritaltatus` (
  `ID` int(11) NOT NULL,
  `maritalStatus` tinytext NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmember` (
  `ID` int(11) NOT NULL,
  `memberId` varchar(10) DEFAULT NULL,
  `memberName` varchar(100) DEFAULT NULL,
  `idNo` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `genderId` int(11) DEFAULT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `maritalStatusId` int(11) DEFAULT NULL,
  `marriageType` int(11) DEFAULT NULL,
  `marriageDate` date DEFAULT NULL,
  `registrationDate` date DEFAULT NULL,
  `memberStatus` int(11) DEFAULT NULL,
  `transferType` int(11) DEFAULT NULL,
  `transferTointernal` int(11) DEFAULT NULL,
  `transferToexternal` varchar(255) DEFAULT NULL,
  `passedOn` date DEFAULT NULL,
  `photo` longblob DEFAULT NULL,
  `baptised` tinyint(4) DEFAULT NULL,
  `baptisedDate` date DEFAULT NULL,
  `membershipStatus` int(11) DEFAULT NULL,
  `confirmed` tinyint(4) DEFAULT NULL,
  `confirmedDate` date DEFAULT NULL,
  `commissioned` tinyint(4) DEFAULT NULL,
  `commissionedDate` date DEFAULT NULL,
  `districtId` int(11) DEFAULT NULL,
  `positionId` int(11) DEFAULT NULL,
  `occupation` varchar(255) DEFAULT NULL,
  `other` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `residence` varchar(255) DEFAULT NULL,
  `groups` varchar(255) DEFAULT NULL,
  `approved` tinyint(4) NOT NULL DEFAULT 1,
  `congregationId` int(11) NOT NULL,
  `initialRegistration` tinyint(4) DEFAULT 1,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_MaritalStatus_Member` (`maritalStatusId`),
  KEY `FK_district_member` (`districtId`),
  KEY `FK_Position_Member` (`positionId`),
  KEY `FK_CongregationId_Member` (`congregationId`),
  KEY `FK_genderId_tblMember` (`genderId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmember_family` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `type` int(11) NOT NULL DEFAULT 1,
  `memberId` int(11) DEFAULT NULL,
  `memberName` varchar(255) DEFAULT NULL,
  `familyMemberId` int(11) DEFAULT NULL,
  `relationshipId` int(11) DEFAULT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmemberchildren` (
  `memberId` int(11) NOT NULL,
  `childName` tinytext DEFAULT NULL,
  `gender` tinyint(4) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `baptised` tinyint(4) DEFAULT NULL,
  `baptisedDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmemberspouse` (
  `memberId` int(11) NOT NULL,
  `spouseMember` tinyint(4) DEFAULT NULL,
  `spouseId` int(11) DEFAULT NULL,
  `spouseName` tinytext DEFAULT NULL,
  `placeOfworship` tinytext DEFAULT NULL,
  KEY `FK_memberId_tblSpouse` (`memberId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmembertransfers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `memberId` int(11) NOT NULL,
  `fromId` int(11) NOT NULL,
  `toId` int(11) NOT NULL,
  `transferDate` date NOT NULL,
  `reason` text NOT NULL,
  `fromDistrict` int(11) DEFAULT NULL,
  `toDistrict` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_member_transfers` (`memberId`),
  KEY `FK_congregation_from` (`fromId`),
  KEY `FK_congregation_to` (`toId`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblmmf` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `TransactionDate` date NOT NULL,
  `Type` varchar(10) DEFAULT NULL,
  `DistrictId` int(11) DEFAULT NULL,
  `GroupId` int(11) DEFAULT NULL,
  `Debit` decimal(18,2) DEFAULT 0.00,
  `Credit` decimal(18,2) DEFAULT 0.00,
  `BankId` int(11) DEFAULT NULL,
  `SubAccountId` int(11) DEFAULT NULL,
  `Reference` varchar(25) DEFAULT NULL,
  `Narration` varchar(150) DEFAULT NULL,
  `TransactionType` int(11) DEFAULT NULL,
  `TransactionId` int(11) DEFAULT NULL,
  `CongregationId` int(11) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_CongregationId_MMF` (`CongregationId`),
  CONSTRAINT `FK_CongregationId_MMF` FOREIGN KEY (`CongregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1882 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblnotifications` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(30) DEFAULT NULL,
  `message` varchar(100) DEFAULT NULL,
  `seen` tinyint(4) DEFAULT NULL,
  `districtId` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_districtId` (`districtId`),
  CONSTRAINT `FK_districtId` FOREIGN KEY (`districtId`) REFERENCES `tbldistricts` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblparishlcc` (
  `ID` int(11) NOT NULL,
  `Name` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblparishofficials` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `officialsId` int(11) NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  `parishMinisterId` int(11) NOT NULL,
  `sessionClerkId` int(11) NOT NULL,
  `financeChairId` int(11) NOT NULL,
  `treasurerId` int(11) NOT NULL,
  `pairingElderId` int(11) NOT NULL,
  `reasonForChange` varchar(255) DEFAULT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpaymentmethods` (
  `ID` int(11) NOT NULL,
  `paymentMethod` varchar(10) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpettycash` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ReceiptNo` int(11) DEFAULT NULL,
  `TransactionDate` date NOT NULL,
  `Debit` decimal(18,2) DEFAULT 0.00,
  `Credit` decimal(18,2) DEFAULT 0.00,
  `IsReceipt` tinyint(4) DEFAULT NULL,
  `BankId` int(11) DEFAULT NULL,
  `PettyCashAccountId` int(11) NOT NULL DEFAULT 98,
  `Reference` varchar(50) DEFAULT NULL,
  `Narration` varchar(150) DEFAULT NULL,
  `ExpenseId` int(11) DEFAULT NULL,
  `TransactionType` int(11) DEFAULT NULL,
  `TransactionId` int(11) DEFAULT NULL,
  `CongregationId` int(11) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_Congregation_PettyCash` (`CongregationId`),
  KEY `IDX_PettyCashAccountId` (`PettyCashAccountId`),
  KEY `IDX_PettyCash_ExpenseId` (`ExpenseId`),
  CONSTRAINT `FK_Congregation_PettyCash` FOREIGN KEY (`CongregationId`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_PettyCashAccount_PettyCash` FOREIGN KEY (`PettyCashAccountId`) REFERENCES `tblaccounttypes` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1878 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpledges_details` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `pledgeId` int(11) NOT NULL,
  `paymentDate` date NOT NULL,
  `openingBal` float NOT NULL,
  `amountPaid` float NOT NULL,
  `balance` float NOT NULL,
  `paymethodId` int(11) DEFAULT NULL,
  `bankId` int(11) DEFAULT NULL,
  `payReference` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_pledgeId_pledgePay` (`pledgeId`),
  KEY `FK_bankId_Pledge` (`bankId`),
  KEY `FK_paymentId_Pledge` (`paymethodId`),
  CONSTRAINT `FK_bankId_Pledge` FOREIGN KEY (`bankId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `FK_paymentId_Pledge` FOREIGN KEY (`paymethodId`) REFERENCES `tblpaymentmethods` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpledges_header` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `pledgeDate` date NOT NULL,
  `category` int(11) NOT NULL,
  `pledgerMember` int(11) DEFAULT NULL,
  `pledgerGroup` int(11) DEFAULT NULL,
  `pledgerDistrict` int(11) DEFAULT NULL,
  `amountPledged` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_district_pledge` (`pledgerDistrict`),
  KEY `FK_group_pledge` (`pledgerGroup`),
  KEY `FK_member_group` (`pledgerMember`),
  KEY `FK_congregation_pledge` (`congregationId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblpositions` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `positionName` tinytext NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_tblPositions_CongregationID` (`congregationId`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblproducts` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `productName` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `rate` float NOT NULL,
  `accountId` int(11) NOT NULL,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblrelationship` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `relationship` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblrolerights` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `RoleId` int(11) NOT NULL,
  `FormId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_RoleId_rights` (`RoleId`),
  KEY `FK_FormId_rights` (`FormId`),
  CONSTRAINT `FK_FormId_rights` FOREIGN KEY (`FormId`) REFERENCES `tblforms` (`ID`),
  CONSTRAINT `FK_RoleId_rights` FOREIGN KEY (`RoleId`) REFERENCES `tblroles` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblroles` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `RoleName` varchar(100) NOT NULL,
  `Deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblseatbooking` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `serviceDate` date NOT NULL,
  `serviceId` int(11) NOT NULL,
  `memberId` int(11) NOT NULL,
  `seat` int(11) NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_serviceId_Booking` (`serviceId`),
  KEY `FK_serviceId_congregation` (`congregationId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblseats` (
  `ID` int(11) NOT NULL,
  `columnId` int(11) DEFAULT NULL,
  `seatNo` varchar(100) NOT NULL,
  `congregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_columnId` (`columnId`),
  KEY `FK_congregationId` (`congregationId`),
  CONSTRAINT `FK_columnId` FOREIGN KEY (`columnId`) REFERENCES `tblcolumns` (`ID`),
  CONSTRAINT `FK_congregationId` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblserviceinfo` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `serviceId` int(11) NOT NULL,
  `serviceDate` date NOT NULL,
  `headedBy` tinytext DEFAULT NULL,
  `preacher` tinytext DEFAULT NULL,
  `attendance` tinytext DEFAULT NULL,
  `envelopePledge` double DEFAULT NULL,
  `ordinary` double DEFAULT NULL,
  `special` double DEFAULT NULL,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblservices` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `serviceName` tinytext NOT NULL,
  `serviceTime` tinytext DEFAULT NULL,
  `maximum` int(11) NOT NULL DEFAULT 5000,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblsuppliers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `supplierName` varchar(255) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `pin` varchar(15) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contactPerson` varchar(255) DEFAULT NULL,
  `congregationId` int(11) NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `FK_congregationId_Supplier` (`congregationId`),
  CONSTRAINT `FK_congregationId_Supplier` FOREIGN KEY (`congregationId`) REFERENCES `tblcongregation` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbltransactionmethods` (
  `ID` int(11) NOT NULL,
  `methodName` varchar(50) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbltransactiontypes` (
  `ID` int(11) NOT NULL,
  `TransactionType` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbluserrights` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `FormId` int(11) NOT NULL,
  `access` tinyint(4) NOT NULL,
  `save` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `unique_user_form` (`UserId`,`FormId`),
  KEY `FK_UserId_Rights` (`UserId`),
  CONSTRAINT `FK_UserId_Rights` FOREIGN KEY (`UserId`) REFERENCES `tblusers` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=10911 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblusers` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserID` varchar(100) NOT NULL,
  `UserName` varchar(255) NOT NULL,
  `UsertypeId` int(11) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Active` tinyint(4) NOT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `districtId` int(11) DEFAULT NULL,
  `superUser` tinyint(4) NOT NULL DEFAULT 0,
  `CongregationId` int(11) NOT NULL,
  `RoleId` int(11) DEFAULT NULL,
  `TransferId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_tblDistrict_User` (`districtId`),
  KEY `FK_RoleId_User` (`RoleId`),
  CONSTRAINT `FK_RoleId_User` FOREIGN KEY (`RoleId`) REFERENCES `tblroles` (`ID`),
  CONSTRAINT `FK_tblDistrict_User` FOREIGN KEY (`districtId`) REFERENCES `tbldistricts` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblusertype` (
  `ID` int(11) NOT NULL,
  `UserType` varchar(100) NOT NULL,
  `visible` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblvats` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `vatName` varchar(100) NOT NULL,
  `rate` float NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `deleted` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblworkplan` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Level` int(11) NOT NULL,
  `FiscalYearId` int(11) NOT NULL,
  `WorkPlanName` varchar(150) NOT NULL,
  `Theme` int(11) NOT NULL,
  `MeetingDate` int(11) DEFAULT NULL,
  `Activty` int(11) NOT NULL,
  `Reason` varchar(255) NOT NULL,
  `EstimatedCost` decimal(18,2) NOT NULL,
  `FromDate` date NOT NULL,
  `ToDate` date NOT NULL,
  `ImplementaionDate` date DEFAULT NULL,
  `AccountId` int(11) NOT NULL,
  `OfficeResponsible` int(11) NOT NULL,
  `Other` varchar(150) DEFAULT NULL,
  `Collaborator` varchar(255) DEFAULT NULL,
  `Results` int(11) DEFAULT NULL,
  `ActualCost` decimal(18,2) DEFAULT NULL,
  `EvidenceOfActivity` varchar(255) DEFAULT NULL,
  `Remarks` varchar(255) DEFAULT NULL,
  `FileName` varchar(255) DEFAULT NULL,
  `WorkPlanStatus` int(11) NOT NULL DEFAULT 0,
  `CongregationId` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `FK_FiscalYear_workplan` (`FiscalYearId`),
  KEY `FK_Account_workplan` (`AccountId`),
  KEY `FK_Congregation_workplan` (`CongregationId`),
  CONSTRAINT `FK_Account_workplan` FOREIGN KEY (`AccountId`) REFERENCES `tblaccounttypes` (`ID`),
  CONSTRAINT `FK_Congregation_workplan` FOREIGN KEY (`CongregationId`) REFERENCES `tblcongregation` (`ID`),
  CONSTRAINT `FK_FiscalYear_workplan` FOREIGN KEY (`FiscalYearId`) REFERENCES `tblfiscalyears` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tblyesno` (
  `ID` int(11) NOT NULL,
  `yesno` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_banktransactions` AS SELECT
 1 AS `ID`,
  1 AS `TransactionDate`,
  1 AS `BankName`,
  1 AS `Amount`,
  1 AS `TransactionType`,
  1 AS `Reference`,
  1 AS `CongregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_byage` AS SELECT
 1 AS `memberName`,
  1 AS `gender`,
  1 AS `age`,
  1 AS `contact`,
  1 AS `district`,
  1 AS `remark`,
  1 AS `districtId`,
  1 AS `congregation` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_cashreceipts` AS SELECT
 1 AS `ID`,
  1 AS `TransactionDate`,
  1 AS `ReceiptNo`,
  1 AS `Debit`,
  1 AS `Bank`,
  1 AS `PettyCashAccount`,
  1 AS `PettyCashAccountId`,
  1 AS `Reference`,
  1 AS `IsReceipt`,
  1 AS `CongregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_contributorspertxn` AS SELECT
 1 AS `ID`,
  1 AS `HeaderId`,
  1 AS `contributionTypeId`,
  1 AS `accountType`,
  1 AS `amount`,
  1 AS `category`,
  1 AS `categoryname`,
  1 AS `contributorid`,
  1 AS `contributor`,
  1 AS `subaccount` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_deposits` AS SELECT
 1 AS `ID`,
  1 AS `DepositDate`,
  1 AS `Bank`,
  1 AS `AmountDeposited`,
  1 AS `Reference`,
  1 AS `CongregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_elders` AS SELECT
 1 AS `ID`,
  1 AS `ElderName`,
  1 AS `Contact` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_expenses` AS SELECT
 1 AS `ID`,
  1 AS `expenseDate`,
  1 AS `expenseType`,
  1 AS `voucherNo`,
  1 AS `account`,
  1 AS `amount`,
  1 AS `category`,
  1 AS `costcentre`,
  1 AS `status`,
  1 AS `congregationId`,
  1 AS `fileName` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_expensevoucher` AS SELECT
 1 AS `ID`,
  1 AS `expenseDate`,
  1 AS `expenseType`,
  1 AS `voucherNo`,
  1 AS `account`,
  1 AS `amount`,
  1 AS `category`,
  1 AS `costcentre`,
  1 AS `narration`,
  1 AS `CongregationName` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_expensevoucher_2` AS SELECT
 1 AS `ID`,
  1 AS `expenseDate`,
  1 AS `expenseType`,
  1 AS `voucherNo`,
  1 AS `account`,
  1 AS `amount`,
  1 AS `category`,
  1 AS `costcentre`,
  1 AS `narration`,
  1 AS `paymentMethod`,
  1 AS `CongregationName` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_family` AS SELECT
 1 AS `Main`,
  1 AS `other`,
  1 AS `relation`,
  1 AS `district` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_getcontribution` AS SELECT
 1 AS `ID`,
  1 AS `contributionDate`,
  1 AS `contType`,
  1 AS `amount`,
  1 AS `Category`,
  1 AS `contrby`,
  1 AS `State`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_getcontributions` AS SELECT
 1 AS `ID`,
  1 AS `contributionDate`,
  1 AS `contType`,
  1 AS `amount`,
  1 AS `Category`,
  1 AS `contrby`,
  1 AS `State`,
  1 AS `congregationId`,
  1 AS `deleted`,
  1 AS `fiscalYearId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_getexpenses` AS SELECT
 1 AS `ID`,
  1 AS `expenseDate`,
  1 AS `expenseType`,
  1 AS `voucherNo`,
  1 AS `account`,
  1 AS `amount`,
  1 AS `category`,
  1 AS `costcentre`,
  1 AS `status`,
  1 AS `congregationId`,
  1 AS `fileName`,
  1 AS `deleted` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_getexpenses_2` AS SELECT
 1 AS `ID`,
  1 AS `expenseDate`,
  1 AS `expenseType`,
  1 AS `voucherNo`,
  1 AS `account`,
  1 AS `amount`,
  1 AS `category`,
  1 AS `costcentre`,
  1 AS `status`,
  1 AS `congregationId`,
  1 AS `fileName`,
  1 AS `deleted` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_group_requisitions` AS SELECT
 1 AS `ID`,
  1 AS `ReqNo`,
  1 AS `RequestDate`,
  1 AS `RequestedBy`,
  1 AS `AmountReq`,
  1 AS `State`,
  1 AS `Status`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_groupofficials` AS SELECT
 1 AS `ID`,
  1 AS `yearName`,
  1 AS `groupName`,
  1 AS `chairman`,
  1 AS `treasurer`,
  1 AS `secretary`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_invoices` AS SELECT
 1 AS `ID`,
  1 AS `invoiceDate`,
  1 AS `invoiceNo`,
  1 AS `customer`,
  1 AS `duedate`,
  1 AS `balance`,
  1 AS `state`,
  1 AS `status`,
  1 AS `postedBy` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_loadpledgebalances` AS SELECT
 1 AS `ID`,
  1 AS `category`,
  1 AS `pledger`,
  1 AS `cat`,
  1 AS `amountPledged`,
  1 AS `amountPaid`,
  1 AS `balance` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_members_minimal` AS SELECT
 1 AS `ID`,
  1 AS `memberName`,
  1 AS `districtId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_pledgewithbalances` AS SELECT
 1 AS `ID`,
  1 AS `pledger`,
  1 AS `category`,
  1 AS `amountPledged`,
  1 AS `amountPaid`,
  1 AS `balance`,
  1 AS `contact`,
  1 AS `deleted`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_requisition_approvals` AS SELECT
 1 AS `ID`,
  1 AS `ReqNo`,
  1 AS `State`,
  1 AS `ReqDate`,
  1 AS `GroupName`,
  1 AS `AmountRequested`,
  1 AS `AmountApproved`,
  1 AS `ApprovedBy`,
  1 AS `DiffInDate`,
  1 AS `Status`,
  1 AS `CongregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_supplier_payments` AS SELECT
 1 AS `ID`,
  1 AS `supplierId`,
  1 AS `Supplier`,
  1 AS `invoiceNo`,
  1 AS `invoiceDate`,
  1 AS `Balance`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_supplier_with_bals` AS SELECT
 1 AS `ID`,
  1 AS `supplierId`,
  1 AS `Supplier`,
  1 AS `invoiceNo`,
  1 AS `invoiceDate`,
  1 AS `Balance`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_supplierpayments` AS SELECT
 1 AS `paymentNo`,
  1 AS `supplierId`,
  1 AS `supplierName`,
  1 AS `paymentDate`,
  1 AS `AmountPaid`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_supplierpayments2` AS SELECT
 1 AS `ID`,
  1 AS `paymentNo`,
  1 AS `supplierId`,
  1 AS `supplierName`,
  1 AS `paymentDate`,
  1 AS `AmountPaid`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_suppliers` AS SELECT
 1 AS `ID`,
  1 AS `supplierName`,
  1 AS `contact`,
  1 AS `contactPerson`,
  1 AS `balance`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_user_rights` AS SELECT
 1 AS `UserId`,
  1 AS `FormName` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_users` AS SELECT
 1 AS `ID`,
  1 AS `userId`,
  1 AS `username`,
  1 AS `usertype`,
  1 AS `status`,
  1 AS `contact`,
  1 AS `district`,
  1 AS `CongregationId` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vwcontributions` AS SELECT
 1 AS `ID`,
  1 AS `contributionDate`,
  1 AS `contType`,
  1 AS `amount`,
  1 AS `categoryName`,
  1 AS `contributor` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vwglaccounts` AS SELECT
 1 AS `ID`,
  1 AS `accountName`,
  1 AS `accountTypeId`,
  1 AS `accountType`,
  1 AS `active`,
  1 AS `status`,
  1 AS `narration` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vwloadgroups` AS SELECT
 1 AS `ID`,
  1 AS `groupName`,
  1 AS `active`,
  1 AS `groupStatus` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vwmemberreport` AS SELECT
 1 AS `Member ID`,
  1 AS `Member Name`,
  1 AS `ID No`,
  1 AS `Gender`,
  1 AS `Contact`,
  1 AS `Marital Status`,
  1 AS `Marriage Date`,
  1 AS `Registration Date`,
  1 AS `Status`,
  1 AS `Baptised`,
  1 AS `Baptised Date`,
  1 AS `Membership Status`,
  1 AS `Confirmed`,
  1 AS `Confirmed Date`,
  1 AS `Commissioned`,
  1 AS `Commissioned Date`,
  1 AS `District`,
  1 AS `Position`,
  1 AS `Occupation`,
  1 AS `Residence`,
  1 AS `Email` */;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vwmembers` AS SELECT
 1 AS `ID`,
  1 AS `memberId`,
  1 AS `memberName`,
  1 AS `idNo`,
  1 AS `dob`,
  1 AS `genderId`,
  1 AS `gender`,
  1 AS `contact`,
  1 AS `maritalStatusId`,
  1 AS `marriageType`,
  1 AS `marriageDate`,
  1 AS `registrationDate`,
  1 AS `memberStatus`,
  1 AS `passedOn`,
  1 AS `baptised`,
  1 AS `baptisedDate`,
  1 AS `membershipStatus`,
  1 AS `confirmed`,
  1 AS `confirmedDate`,
  1 AS `commissioned`,
  1 AS `commissionedDate`,
  1 AS `districtId`,
  1 AS `district`,
  1 AS `positionId`,
  1 AS `position`,
  1 AS `occupation`,
  1 AS `other`,
  1 AS `email`,
  1 AS `residence`,
  1 AS `congregationId` */;
SET character_set_client = @saved_cs_client;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `brand_level`(`brand_id` INT) RETURNS int(11)
begin
   declare p_id, l int;
   set l = 0; 
   repeat
      set l = l + 1;
      select parentId into brand_id from tblaccounttypes where ID = brand_id;
   until brand_id = 0 or brand_id is NULL end repeat;
   return if(brand_id=0, l, NULL);
end ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_checkreferencedaccount`(`aid` INT) RETURNS int(11)
    DETERMINISTIC
BEGIN
	DECLARE accountname VARCHAR(150);
    DECLARE churchbudget INT;
    DECLARE bankpostings INT;
    DECLARE contributions INT;
    DECLARE expenses INT;
    DECLARE expensespaidfrom INT;
    DECLARE groupbudget INT;
    DECLARE ledger INT;
    DECLARE ledgerparent INT;
    DECLARE products INT;
    DECLARE pledges INT;
    SET accountname = (SELECT TRIM(LOWER(accountType)) AS AccountName FROM tblaccounttypes WHERE (ID = aid));
    SET churchbudget = (SELECT COUNT(*) FROM tblchurchbudget_details WHERE accountId = aid);
    SET bankpostings = (SELECT COUNT(*) FROM tblbankpostings  WHERE bankId = aid);
    SET contributions = (SELECT COUNT(*) FROM tblcontributions_details WHERE contributionTypeId = aid);
    SET expenses = (SELECT COUNT(*) FROM tblexpenses WHERE accountId = aid);
    SET expensespaidfrom = (SELECT COUNT(*) FROM tblexpenses WHERE PaidFromAccountId = aid);
    SET groupbudget = (SELECT COUNT(*) FROM tblgroupbudget_details  WHERE (accountId = aid));
    SET ledger = (SELECT COUNT(*) FROM tblledger WHERE TRIM(LOWER(account)) = accountname);
    SET ledgerparent = (SELECT COUNT(*) FROM tblledger WHERE TRIM(LOWER(parentaccount)) = accountname);
    SET products = (SELECT COUNT(*) FROM tblproducts WHERE accountId = aid);
    SET pledges = (SELECT COUNT(*) FROM tblpledges_header WHERE category = aid);
    RETURN churchbudget + bankpostings + contributions + expenses + expensespaidfrom + groupbudget + ledger + ledgerparent + products + pledges ;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_get_supplier_balance`(`supplier` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE TotalInvoicesAmount DECIMAL(18,2);
    DECLARE TotalInvoicesPayments DECIMAL(18,2);
    SET TotalInvoicesAmount = (SELECT IFNULL(SUM(inclusiveVat),0) AS SumOfInvoices 
                               FROM tblinvoice_header_suppliers
                               WHERE supplierId = supplier AND deleted = 0);
    SET TotalInvoicesPayments = (SELECT IFNULL(SUM(amount),0) AS SumOfPayments 
                                 FROM tblinvoice_payments_suppliers p join tblinvoice_header_suppliers h
                                 ON p.invoice_Id = h.ID
                                 WHERE (h.supplierId = supplier) AND (h.deleted = 0));
	RETURN TotalInvoicesAmount - TotalInvoicesPayments;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getAmountPaid`(`id` INT) RETURNS decimal(10,0)
BEGIN 
  DECLARE paidAmount decimal;
  SET paidAmount = (SELECT IFNULL(SUM(amount),0) as paid FROM tblinvoice_payments WHERE invoice_Id=id);
  RETURN paidAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getamountpaid_pledge`(`pledge` INT) RETURNS decimal(18,2)
BEGIN
    	DECLARE AmountPaid_Pledge decimal(18,2);
        SET AmountPaid_Pledge = (SELECT IFNULL(SUM(amountPaid),0) As amountPaid FROM tblpledges_details WHERE (pledgeId = pledge));
        RETURN AmountPaid_Pledge;
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getbudgetedamount`(`account` INT, `fiscalyear` INT, `gid` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE BudgtedAmount DECIMAL(18,2);
    SET BudgtedAmount = (SELECT IFNULL(amount,0) AS amountbudgeted
						 FROM   tblgroupbudget_details d inner join tblgroupbudget_header h on 
                         		d.ID = h.ID
                         WHERE  (h.fiscalYearId = fiscalyear) AND (d.accountId = account)
                          		AND (h.groupId = gid));
    RETURN BudgtedAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getbudgetedamount_lcc`(`account` INT, `fiscalyear` INT, `cong` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE BudgtedAmount DECIMAL(18,2);
    SET BudgtedAmount = (SELECT IFNULL(amount,0) AS amountbudgeted
						 FROM   tblchurchbudget_details d inner join tblchurchbudget_header h on 
                         		d.ID = h.ID
                         WHERE  (h.yearId = fiscalyear) AND (d.accountId = account) AND (h.congregationId = cong));
    RETURN BudgtedAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getChairmanId`(`pledgerid` INT) RETURNS int(11)
BEGIN
    	DECLARE mid INT;
           SET mid= (SELECT chairmanId FROM tblgroupofficials 
                     WHERE (groupId=pledgerid) AND (yearId=getYearID()));
        RETURN mid;
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getcongregationname`(`cong` INT) RETURNS varchar(150) CHARSET latin1 COLLATE latin1_swedish_ci
    DETERMINISTIC
BEGIN 
  DECLARE congregation varchar(150);
  SET congregation = (SELECT CongregationName FROM tblcongregation WHERE ID=cong);
  RETURN congregation;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getcontributor`(`cid` INT, `cont` INT) RETURNS varchar(150) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
BEGIN
	DECLARE Contributor VARCHAR(150);
    IF cid = 1 THEN
    	SET Contributor = (SELECT memberName FROM tblmember WHERE (ID = cont)); 
    ELSEIF cid = 2  THEN
    	SET Contributor = (SELECT groupName FROM tblgroups WHERE (ID = cont));
    ELSEIF cid = 3 THEN
    	SET Contributor = (SELECT districtName FROM tbldistricts WHERE (ID = cont));
    ELSEIF cid = 4  THEN
    	SET Contributor = (SELECT serviceName FROM tblservices WHERE (ID = cont));
    ELSEIF cid > 4  THEN
    	SET Contributor = (SELECT CongregationName FROM tblcongregation WHERE (ID = cont));
    END IF;
    RETURN Contributor;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getcontributorid`(`cid` INT, `tid` INT) RETURNS varchar(150) CHARSET latin1 COLLATE latin1_swedish_ci
BEGIN
	DECLARE ContributorId VARCHAR(150);
    IF cid = 1 THEN
    	SET ContributorId = (SELECT contributor FROM tblcontributions_details WHERE (ID = tid)); 
    ELSEIF cid = 2  THEN
    	SET ContributorId = (SELECT contributotGroup FROM tblcontributions_details WHERE (ID = tid));
    ELSEIF cid = 3 THEN
    	SET ContributorId = (SELECT contributotDistrict FROM tblcontributions_details WHERE (ID = tid));
    ELSEIF cid = 4 THEN
    	SET ContributorId = (SELECT contributotService FROM tblcontributions_details WHERE (ID = tid));    
    ELSEIF cid > 4 THEN
    	SET ContributorId = (SELECT contributotCong FROM tblcontributions_details WHERE (ID = tid));
    END IF;
    RETURN ContributorId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getcurrentcongregation`(`elderid` INT) RETURNS int(11)
    DETERMINISTIC
BEGIN 
  DECLARE congregationid int;
  SET congregationid = (SELECT ToCongregation FROM tbleldermovement WHERE ElderId=elderid ORDER BY ID DESC LIMIT 1);
  RETURN congregationid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getcurrentdistrict`(`elderid` INT) RETURNS int(11)
    DETERMINISTIC
BEGIN 
  DECLARE districtid int;
  SET districtid = (SELECT ToDistrict FROM tbleldermovement WHERE ElderId=elderid ORDER BY ID DESC LIMIT 1);
  RETURN districtid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getdistrictname`(`dist` INT) RETURNS varchar(150) CHARSET latin1 COLLATE latin1_swedish_ci
    DETERMINISTIC
BEGIN 
  DECLARE district varchar(150);
  SET district = (SELECT districtName FROM tbldistricts WHERE ID=dist);
  RETURN district;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getexpensedamount`(`aid` INT, `edate` DATE, `congid` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE ExpensedAmount DECIMAL(18,2);
    DECLARE YearId INT;
    SET YearId = (SELECT getyearidbydate(edate) As yid);
    SET ExpensedAmount = (SELECT IFNULL(SUM(amount),0) AS SumOfAmount
                              FROM tblexpenses e
                              WHERE (e.fiscalYearId = YearId) AND (e.expenseType = 1) 
                                     AND (e.accountId = aid) AND (e.deleted = 0)
                                     AND (e.congregationId = congid));
    RETURN ExpensedAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getexpensetotal_group`(`account` INT, `fiscalyear` INT, `gid` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE ExpensedTotalAmount DECIMAL(18,2);
    SET ExpensedTotalAmount = (SELECT IFNULL(SUM(amount),0) AS amounte
						  	   FROM   tblexpenses
                          	   WHERE  (fiscalYearId = fiscalyear) AND (accountId = account)
                          		      AND (groupId = gid) AND (deleted = 0));
    RETURN ExpensedTotalAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getexpensetotal_lcc`(`account` INT, `fiscalyear` INT, `cong` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE ExpensedTotalAmount DECIMAL(18,2);
    SET ExpensedTotalAmount = (SELECT IFNULL(SUM(amount),0) AS amounte
						  	   FROM   tblexpenses
                          	   WHERE  (fiscalYearId = fiscalyear) AND (accountId = account)
                          		      AND (groupId IS NULL) AND (congregationId = cong) AND (deleted = 0));
    RETURN ExpensedTotalAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getgroupexpensedamount`(`aid` INT, `edate` DATE, `gid` INT, `congid` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE ExpensedAmount DECIMAL(18,2);
    DECLARE YearId INT;
    SET YearId = (SELECT getyearidbydate(edate) As yid);
    SET ExpensedAmount = (SELECT IFNULL(SUM(amount),0) AS SumOfAmount
                              FROM tblexpenses e
                              WHERE (e.fiscalYearId = YearId) AND (e.expenseType = 2) 
                                     AND (e.accountId = aid) AND (e.groupId = gid) AND (e.deleted = 0)
                                     AND (e.congregationId = congid));
    RETURN ExpensedAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getinvoicebalance`(`invoiceno` INT) RETURNS decimal(18,2)
BEGIN
    	DECLARE InvoiceAmount DECIMAL(18,2);
        DECLARE AmountPaid DECIMAL(18,2);
        SET InvoiceAmount = (SELECT IFNULL(inclusiveVat,0) as amount 
                             FROM   tblinvoice_header 
                             WHERE  (ID = invoiceno));
    	SET AmountPaid = (SELECT IFNULL(SUM(amount),0) AS amountpaid 
                          FROM   tblinvoice_payments 
                          WHERE  (invoice_Id = invoiceno));
        RETURN (InvoiceAmount - AmountPaid);
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getinvoicebalance_supplier`(`invoiceno` INT) RETURNS decimal(18,2)
BEGIN
    	DECLARE InvoiceAmount DECIMAL(18,2);
        DECLARE AmountPaid DECIMAL(18,2);
        SET InvoiceAmount = (SELECT IFNULL(h.inclusiveVat,0) as amount 
                             FROM   tblinvoice_header_suppliers h 
                             WHERE  (ID = invoiceno));
    	SET AmountPaid = (SELECT IFNULL(SUM(p.amount),0) AS amountpaid 
                          FROM   tblinvoice_payments_suppliers p 
                          WHERE  (invoice_Id = invoiceno));
        RETURN (InvoiceAmount - AmountPaid);
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getinvoicebalance_supplier_bydate`(`invoiceno` INT, `pdate` DATE) RETURNS decimal(18,2)
BEGIN
    	DECLARE InvoiceAmount DECIMAL(18,2);
        DECLARE AmountPaid DECIMAL(18,2);
        SET InvoiceAmount = (SELECT IFNULL(h.inclusiveVat,0) as amount 
                             FROM   tblinvoice_header_suppliers h 
                             WHERE  (ID = invoiceno));
    	SET AmountPaid = (SELECT IFNULL(SUM(p.amount),0) AS amountpaid 
                          FROM   tblinvoice_payments_suppliers p 
                          WHERE  (invoice_Id = invoiceno) AND (p.paymentDate <= pdate));
        RETURN (InvoiceAmount - AmountPaid);
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getinvoice_paid`(`invoiceno` INT) RETURNS decimal(18,2)
BEGIN
    	DECLARE AmountPaid DECIMAL(18,2);
        SET AmountPaid = (SELECT IFNULL(SUM(amount),0) AS amountpaid 
                          FROM   tblinvoice_payments 
                          WHERE  (invoice_Id = invoiceno));
        RETURN AmountPaid;
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getinvoice_paid_supplier`(`invoiceno` INT) RETURNS decimal(18,2)
BEGIN
    	DECLARE AmountPaid DECIMAL(18,2);
        SET AmountPaid = (SELECT IFNULL(SUM(amount),0) AS amountpaid 
                          FROM   tblinvoice_payments_suppliers 
                          WHERE  (invoice_Id = invoiceno));
        RETURN AmountPaid;
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getmmfopeningbal`(`gid` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate < sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate < sdate));
    RETURN Debits - Credits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getmmfopeningbalbydate`(`gid` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate <= sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (GroupId = gid) AND (Deleted = 0) AND (TransactionDate <= sdate));
    RETURN Debits - Credits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getmmfopeningbalbydate_district`(`did` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate <= sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate <= sdate));
    RETURN Debits - Credits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getmmfopeningbal_district`(`did` INT, `sdate` DATE) RETURNS decimal(18,2)
BEGIN
    DECLARE Debits DECIMAL(18,2);
    DECLARE Credits DECIMAL(18,2);
    SET Debits = (SELECT IFNULL(SUM(Debit),0) FROM tblmmf
                  WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate < sdate));
    SET Credits = (SELECT IFNULL(SUM(Credit),0) FROM tblmmf
                   WHERE (DistrictId = did) AND (Deleted = 0) AND (TransactionDate < sdate));
    RETURN Debits - Credits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getmonthexpense`(`account` INT, `fiscalyear` INT, `emonth` INT, `cong` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE ExpensedAmount DECIMAL(18,2);
    SET ExpensedAmount = (SELECT IFNULL(SUM(amount),0) AS amounte
						  FROM   tblexpenses
                          WHERE  (fiscalYearId = fiscalyear) AND (accountId = account)
                          		 AND (MONTH(expenseDate) = emonth) AND (groupId IS NULL)
                         		 AND (congregationId = cong) AND (deleted = 0));
    RETURN ExpensedAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getmonthexpense_group`(`account` INT, `fiscalyear` INT, `emonth` INT, `gid` INT, `cong` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE ExpensedAmount DECIMAL(18,2);
    SET ExpensedAmount = (SELECT IFNULL(SUM(amount),0) AS amounte
						  FROM   tblexpenses
                          WHERE  (fiscalYearId = fiscalyear) AND (accountId = account)
                          		 AND (MONTH(expenseDate) = emonth) AND (groupId = gid)
                         		 AND (congregationId = cong) AND (deleted = 0));
    RETURN ExpensedAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getopeningbal`(tdate DATE, congid INT, accid INT) RETURNS decimal(18,2)
BEGIN
    DECLARE DebitTotal DECIMAL(18,2);
    DECLARE CreditTotal DECIMAL(18,2);
    SET DebitTotal = (SELECT IFNULL(SUM(Debit),0) FROM tblpettycash
                      WHERE TransactionDate < tdate AND CongregationId = congid
                            AND PettyCashAccountId = accid AND Deleted = 0);
    SET CreditTotal = (SELECT IFNULL(SUM(Credit),0) FROM tblpettycash
                       WHERE TransactionDate < tdate AND CongregationId = congid
                             AND PettyCashAccountId = accid AND Deleted = 0);
    RETURN DebitTotal - CreditTotal;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getpaymenttotalbysupplier`(`payno` INT, `suppid` INT) RETURNS decimal(18,2)
BEGIN
	DECLARE TotalPayment DECIMAL(18,2);
    SET TotalPayment = (SELECT 
							IFNULL(SUM(amount),0) AS SumOfAmount
						FROM `tblinvoice_payments_suppliers` p 
                        	  join tblinvoice_header_suppliers s on p.invoice_Id = s.ID
						WHERE p.paymentNo = payno AND s.supplierId = suppid);
    RETURN TotalPayment;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getrequisitionbalance`(`rid` INT) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE ApprovedAmount decimal(18,2);
  DECLARE SpentAmount decimal(18,2);
  SET ApprovedAmount = (SELECT IFNULL(SUM(AmountApproved),0) AS AmountApproved FROM tblfundrequisition WHERE ID=rid);
  SET SpentAmount = (SELECT IFNULL(SUM(amount),0) AS SpentAmount FROM tblexpenses WHERE (requisitionId=rid) AND (deleted = 0));
  RETURN ApprovedAmount - SpentAmount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotalcredits`(`acc` VARCHAR(100), `startdate` DATE, `enddate` DATE, `cong` INT) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalCredits decimal(18,2);
  SET TotalCredits = (SELECT IFNULL(SUM(credit),0) as sumofcredit FROM tblledger WHERE account=acc 
                     AND transactionDate BETWEEN startdate AND enddate AND congregationId = cong AND (deleted = 0));
  RETURN TotalCredits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotalcredits_bs`(`acc` VARCHAR(100), `asatdate` DATE, `cong` INT) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalCredits Decimal(18,2);
  SET TotalCredits = (SELECT IFNULL(SUM(credit),0) AS sumofcredits 
                      FROM tblledger WHERE account = acc AND transactionDate <= asatdate AND congregationId = cong AND deleted = 0);
  RETURN TotalCredits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotalcredits_bs_parish`(`acc` VARCHAR(100), `asatdate` DATE) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalCredits Decimal(18,2);
  SET TotalCredits = (SELECT IFNULL(SUM(credit),0) AS sumofcredits 
                      FROM tblledger WHERE account = acc AND transactionDate <= asatdate AND deleted = 0);
  RETURN TotalCredits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotalcredits_parent`(`acc` VARCHAR(100), `startdate` DATE, `enddate` DATE, `cong` INT) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalCredits Decimal(18,2);
  SET TotalCredits = (SELECT IFNULL(SUM(credit),0) as sumofcredit FROM tblledger WHERE parentaccount=acc 
                     AND transactionDate BETWEEN startdate AND enddate AND congregationId = cong AND deleted=0);
  RETURN TotalCredits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotalcredits_parish`(`acc` VARCHAR(100), `startdate` DATE, `enddate` DATE) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalCredits decimal(18,2);
  SET TotalCredits = (SELECT IFNULL(SUM(credit),0) as sumofcredit FROM tblledger WHERE account=acc 
                     AND transactionDate BETWEEN startdate AND enddate AND deleted = 0);
  RETURN TotalCredits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotaldebits`(`acc` VARCHAR(100), `startdate` DATE, `enddate` DATE, `cong` INT) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalDebits Decimal(18,2);
  SET TotalDebits = (SELECT IFNULL(SUM(debit),0) as sumofdebit FROM tblledger WHERE account=acc 
                     AND transactionDate BETWEEN startdate AND enddate AND congregationId = cong AND deleted=0);
  RETURN TotalDebits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotaldebits_bs`(`acc` VARCHAR(100), `asatdate` DATE, `cong` INT) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalDebits Decimal(18,2);
  SET TotalDebits = (SELECT IFNULL(SUM(debit),0) AS sumofdebits 
                     FROM tblledger WHERE account = acc AND transactionDate <= asatdate AND congregationId = cong AND deleted = 0);
  RETURN TotalDebits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotaldebits_bs_parish`(`acc` VARCHAR(100), `asatdate` DATE) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalDebits Decimal(18,2);
  SET TotalDebits = (SELECT IFNULL(SUM(debit),0) AS sumofdebits 
                     FROM tblledger WHERE account = acc AND transactionDate <= asatdate AND deleted = 0);
  RETURN TotalDebits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotaldebits_parent`(`acc` VARCHAR(100), `startdate` DATE, `enddate` DATE, `cong` INT) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalDebits Decimal(18,2);
  SET TotalDebits = (SELECT IFNULL(SUM(debit),0) as sumofdebit FROM tblledger WHERE parentaccount=acc 
                     AND transactionDate BETWEEN startdate AND enddate AND congregationId = cong AND deleted=0);
  RETURN TotalDebits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `gettotaldebits_parish`(`acc` VARCHAR(100), `startdate` DATE, `enddate` DATE) RETURNS decimal(18,2)
BEGIN 
  DECLARE TotalDebits Decimal(18,2);
  SET TotalDebits = (SELECT IFNULL(SUM(debit),0) as sumofdebit FROM tblledger WHERE account=acc 
                     AND transactionDate BETWEEN startdate AND enddate AND deleted = 0);
  RETURN TotalDebits;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getTotalPledge`(`pid` INT) RETURNS decimal(10,0)
BEGIN 
  DECLARE totalPaid decimal;
  SET totalPaid = (SELECT IFNULL(SUM(amountPaid),0) as amountPaid FROM `tblpledges_details` WHERE pledgeId = pid);
  RETURN totalPaid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getYearID`() RETURNS int(11)
BEGIN 
  	DECLARE yearId INT;
  	SET yearId = (SELECT ID FROM tblfiscalyears WHERE CURDATE() BETWEEN startDate AND endDate AND (deleted = 0));
  	RETURN yearId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `getyearidbydate`(`tdate` DATE) RETURNS int(11)
BEGIN 
  	DECLARE YearId INT;
  	SET YearId = (SELECT ID 
                  FROM tblfiscalyears 
                  WHERE tdate BETWEEN startDate AND endDate AND (deleted = 0));
  	RETURN YearId;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_balance_by_date`(`category` VARCHAR(15), `categoryid` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE subaccount_diff decimal(18,2);
  DECLARE expense_total decimal(18,2);
  
  SET subaccount_diff = (SELECT COALESCE(sum(s.Amount),0) 
                         FROM tblbanktransactions_subaccounts s
                         WHERE (s.SubAccountId = categoryid) AND (s.TransactionDate <= edate));
                   
  SET expense_total = (SELECT get_subaccount_expense_total(category,categoryid,edate));
  
  RETURN subaccount_diff - expense_total;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_balance_by_date_district`(`did` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE subaccount_diff decimal(18,2);
  DECLARE expense_total decimal(18,2);
  
  SET subaccount_diff = (SELECT COALESCE(sum(s.Amount),0) 
                         FROM tblbanktransactions_subaccounts s
                         WHERE (s.SubAccountId = categoryid) AND (s.TransactionDate <= edate));
                   
  SET expense_total = (SELECT get_subaccount_expense_total_district(did,edate));
  
  RETURN subaccount_diff - expense_total;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_balance_by_date_group`(`gid` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE subaccount_diff decimal(18,2);
  DECLARE expense_total decimal(18,2);
  
  SET subaccount_diff = (SELECT COALESCE(sum(s.Amount),0) 
                         FROM tblbanktransactions_subaccounts s
                         WHERE (s.SubAccountId = categoryid) AND (s.TransactionDate <= edate));
                   
  SET expense_total = (SELECT get_subaccount_expense_total_group(gid,edate));
  
  RETURN subaccount_diff - expense_total;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_expense_op_district`(`did` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE total_expense decimal(18,2);

  SET total_expense = (SELECT COALESCE(SUM(amount),0) AS total 
                       FROM tblexpenses 
                       WHERE (deleted=0) AND (expenseDate < edate) AND (districtId = did) AND (requisitionId IS NOT NULL));
  
  RETURN total_expense;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_expense_op_group`(`gid` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE total_expense decimal(18,2);

  SET total_expense = (SELECT COALESCE(SUM(amount),0) AS total 
                       FROM tblexpenses 
                       WHERE (deleted=0) AND (expenseDate < edate) AND (groupId = gid) AND (requisitionId IS NOT NULL));
  
  RETURN total_expense;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_expense_total`(`category` VARCHAR(15), `categoryid` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE total_expense decimal(18,2);
  
  IF category = 'group' THEN
  	SET total_expense = (SELECT COALESCE(SUM(amount),0) AS total 
                         FROM tblexpenses 
                         WHERE (deleted=0) AND (expenseDate <= edate) AND (groupId = categoryid) AND (requisitionId IS NOT NULL));
  ELSE
  	SET total_expense = (SELECT COALESCE(SUM(amount),0) AS total 
                         FROM tblexpenses 
                         WHERE (deleted=0) AND (expenseDate <= edate) AND (districtId = categoryid) AND (requisitionId IS NOT NULL));
  END IF;
  
  RETURN total_expense;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_expense_total_district`(`did` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE total_expense decimal(18,2);

  SET total_expense = (SELECT COALESCE(SUM(amount),0) AS total 
                       FROM tblexpenses 
                       WHERE (deleted=0) AND (expenseDate <= edate) AND (districtId = did) AND (requisitionId IS NOT NULL));
  
  RETURN total_expense;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_expense_total_group`(`gid` INT, `edate` DATE) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE total_expense decimal(18,2);

  SET total_expense = (SELECT COALESCE(SUM(amount),0) AS total 
                       FROM tblexpenses 
                       WHERE (deleted=0) AND (expenseDate <= edate) AND (groupId = gid) AND (requisitionId IS NOT NULL));
  
  RETURN total_expense;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_opening_balance_district`(`datefrom` DATE, `sid` INT, `did` INT) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE subaccount_diff decimal(18,2);
  DECLARE expense_total decimal(18,2);
  
  SET subaccount_diff = (SELECT IFNULL(SUM(Amount),0) AS OpeningBalance 
         			     FROM   tblbanktransactions_subaccounts 
         			     WHERE (SubAccountId=sid) AND (TransactionDate < datefrom));
                   
  SET expense_total = (SELECT get_subaccount_expense_op_district(did,datefrom));
  
  RETURN subaccount_diff - expense_total;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_subaccount_opening_balance_group`(`datefrom` DATE, `sid` INT, `gid` INT) RETURNS decimal(18,2)
    DETERMINISTIC
BEGIN 
  DECLARE subaccount_diff decimal(18,2);
  DECLARE expense_total decimal(18,2);
  
  SET subaccount_diff = (SELECT IFNULL(SUM(Amount),0) AS OpeningBalance 
         			     FROM   tblbanktransactions_subaccounts 
         			     WHERE (SubAccountId=sid) AND (TransactionDate < datefrom));
                   
  SET expense_total = (SELECT get_subaccount_expense_op_group(gid,datefrom));
  
  RETURN subaccount_diff - expense_total;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `get_year_start_date`(`edate` DATE) RETURNS date
    DETERMINISTIC
BEGIN 
  DECLARE start_date date;

  SET start_date = (SELECT startDate FROM tblfiscalyears WHERE edate BETWEEN startDate AND endDate);
  
  RETURN start_date;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `getgroupstatement`(IN `reqid` INT)
BEGIN
    SELECT 
        `TransactionDate`,
        `Description`,
        `AmountIn`,
        `AmountOut`,
        (@running_balance := @running_balance + (`AmountIn` - `AmountOut`)) AS Balance
    FROM (
        SELECT 
            `ApprovalDate` AS TransactionDate,
            'Funds Approval' AS Description,
            `AmountApproved` AS AmountIn,
            0 AS AmountOut
        FROM `tblfundrequisition` 
        WHERE ID = reqid
        UNION ALL
        SELECT 
            e.expenseDate AS TransactionDate,
            e.narration AS Description,
            0 AS AmountIn,
            e.amount AS AmountOut
        FROM tblexpenses e
        WHERE e.deleted = 0 AND e.requisitionId = reqid
    ) AS combined_data, (SELECT @running_balance := 0) AS rb
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `getPledge`(IN `pledgeid` INT)
BEGIN
SELECT IF(h.category=1,UCASE(m.memberName),IF(h.category=2,ucase(g.groupName),ucase(d.districtName))) as pledger,
	   FORMAT(h.amountPledged,2) AS amountPledged,getTotalPledge(h.ID) as totalPaid,(h.amountPledged - getTotalPledge(h.ID)) as balance
FROM tblpledges_header h left join tblmember m on h.pledgerMember=m.ID left join tblgroups g on h.pledgerGroup
	 =g.ID left join tbldistricts d on h.pledgerDistrict=d.ID
WHERE h.ID = pledgeid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `pledgesWithBalances`()
BEGIN
	SELECT DISTINCT H.ID,IF(H.category=1,UCASE(M.memberName),IF(H.category=2,UCASE(G.groupName), 
       UCASE(T.districtName))) AS pledger,IF(H.category=1,'MEMBER',IF(H.category=2,'GROUP',
       'DISTRICT')) AS category,FORMAT(H.amountPledged,2) AS amountPledged,FORMAT((SELECT IFNULL
       (SUM(amountPaid),0) FROM tblpledges_details WHERE pledgeId=H.ID),2) AS amountPaid,
       FORMAT(H.amountPledged - (SELECT IFNULL(SUM(amountPaid),0) FROM tblpledges_details 
       WHERE pledgeId=H.ID),2) as balance,
       IF(H.category=1,(SELECT contact FROM tblmember WHERE (ID=H.pledgerMember)),
       IF(H.category=2,(SELECT contact FROM tblmember WHERE (ID=getChairmanId(H.pledgerGroup))),
         (SELECT contact FROM tblusers WHERE (districtId=H.pledgerDistrict)))) AS contact
FROM tblpledges_header H INNER JOIN tblpledges_details D ON H.ID=D.pledgeId LEFT JOIN 
       tblmember M ON H.pledgerMember=M.ID LEFT JOIN tblgroups G ON H.pledgerGroup =
       G.ID LEFT JOIN tbldistricts T ON  H.pledgerDistrict=T.ID 
WHERE H.amountPledged > (SELECT IFNULL(SUM(amountPaid),0) FROM 
       tblpledges_details WHERE pledgeId=H.ID);
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `spGetInvoices`(IN `congid` INT)
BEGIN
    select h.ID AS ID,
           h.invoiceDate AS invoiceDate,
           h.invoiceNo AS invoiceNo,
           ucase(c.customerName) AS customer,
           h.duedate AS duedate,
           h.inclusiveVat - (select ifnull(sum(tblinvoice_payments.amount),0) AS paid 
                             from   tblinvoice_payments 
                             where  tblinvoice_payments.invoice_Id = h.ID) AS balance,
           if(h.status = 0,'Unpaid',if(h.status = 1,'Partially Paid','Paid')) AS state,
           h.status AS status,
           h.postedBy AS postedBy 
    from   tblinvoice_header h join tblcustomers c on h.customerId = c.ID 
    where  h.fiscalYearId = getYearID() AND (h.congregationId = congid)
    order by h.status,h.invoiceNo desc;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `spGetInvoices_suppliers`(IN `congid` INT)
BEGIN
    select h.ID AS ID,
           h.invoiceDate AS invoiceDate,
           h.invoiceNo AS invoiceNo,
           ucase(s.supplierName) AS supplier,
           h.duedate AS duedate,
           h.inclusiveVat - (select ifnull(sum(amount),0) AS paid 
                             from   tblinvoice_payments_suppliers 
                             where  invoice_Id = h.ID) AS balance,
           if(h.status = 0,'Unpaid',if(h.status = 1,'Partially Paid','Paid')) AS state,
           h.status AS status,
           h.postedBy AS postedBy 
    from   tblinvoice_header_suppliers h join tblsuppliers s on h.supplierId = s.ID 
    where  h.fiscalYearId = getYearID() AND (h.congregationId = congid) AND (h.deleted = 0) AND (h.invoiceNo IS NOT NULL)
    order by h.status,h.invoiceNo desc;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `spGetOfficials`()
BEGIN
    SELECT   o.ID,
             DATE_FORMAT(o.startDate,'%d/%m/%Y') AS startDate,
             DATE_FORMAT(o.endDate,'%d/%m/%Y') AS endDate,
             ucase(m.memberName) As parishminister,
             ucase(s.memberName) As sessionClerk,
             ucase(f.memberName) as financeChair,
             ucase(t.memberName) as treasurer,
             ucase(p.memberName) as pairingElder
    FROM    `tblparishofficials` o inner join tblmember m on o.parishMinisterId
            = m.ID inner join tblmember s on o.sessionClerkId = s.ID 
            inner join tblmember f on o.financeChairId = f.ID inner join
            tblmember t on o.treasurerId = t.ID inner join tblmember p on 
            o.pairingElderId = p.ID
    WHERE    o.deleted = 0;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_BalanceSheet_AccountDrilldown`(IN `p_account` VARCHAR(100), IN `p_congregationId` INT, IN `p_startDate` DATE, IN `p_endDate` DATE)
BEGIN
    SELECT
        l.transactionDate,
        CASE WHEN l.accountId = 3 THEN debit - credit ELSE credit - debit END AS amount,
        l.narration,
        t.TransactionType
    FROM tblledger l
    left join tbltransactiontypes t on l.transactionType = t.ID 
    WHERE account = p_account
        AND l.deleted = 0
        AND l.congregationId = p_congregationId
        AND l.transactionDate >= p_startDate
        AND l.transactionDate <= p_endDate
    ORDER BY l.transactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_BalanceSheet_Assets`(IN `p_congregationId` INT, IN `p_asOfDate` DATE)
BEGIN
    SELECT
        parentaccount AS account,
        SUM(debit - credit) AS amount
    FROM tblledger
    WHERE accountId = 3
        AND deleted = 0
        AND congregationId = p_congregationId
        AND transactionDate <= p_asOfDate
    GROUP BY parentaccount
    ORDER BY parentaccount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_balancesheet_assets_parish`(IN `asatdate` DATE)
BEGIN
	SELECT  DISTINCT account,((gettotaldebits_bs_parish(account,asatdate)) -  
				    (gettotalcredits_bs_parish(account,asatdate))) as bal
	FROM    tblledger
    WHERE   transactionDate <= asatdate AND accountId = 3 AND deleted = 0;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_BalanceSheet_LiabilitiesEquity`(IN `p_congregationId` INT, IN `p_asOfDate` DATE)
BEGIN
    SELECT
        parentaccount AS account,
        SUM(credit - debit) AS amount
    FROM tblledger
    WHERE accountId IN (4, 6)
        AND deleted = 0
        AND congregationId = p_congregationId
        AND transactionDate <= p_asOfDate
    GROUP BY parentaccount

    UNION ALL

    
    
    
    SELECT
        'Retained Earnings' AS account,
        SUM(credit - debit) AS amount
    FROM tblledger
    WHERE accountId IN (1, 2, 5)  
        AND deleted = 0
        AND congregationId = p_congregationId
        AND transactionDate <= p_asOfDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_balanceSheet_liablityequity`(IN `asatdate` DATE, IN `cong` INT)
BEGIN
	SELECT  DISTINCT account,((gettotaldebits_bs(account,asatdate,cong)) -  
				    (gettotalcredits_bs(account,asatdate,cong))) as bal
	FROM    tblledger
    WHERE   (transactionDate <= asatdate) AND (accountId = 4 OR accountId = 6) AND (congregationId = cong) AND (deleted=0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_balanceSheet_liablityequity_parish`(IN `asatdate` DATE)
BEGIN
	SELECT  DISTINCT account,((gettotaldebits_bs_parish(account,asatdate)) -  
				    (gettotalcredits_bs_parish(account,asatdate))) as bal
	FROM    tblledger
    WHERE   (transactionDate <= asatdate) AND (accountId = 4 OR accountId = 6) AND (deleted = 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_church_budgetvexpense`(IN `fyear` INT, `cong` INT)
BEGIN
    SELECT 	 a.ID,
             ucase(a.accountType) as accountType,
             getbudgetedamount_lcc(a.ID,fyear,cong) as budgetedAmount,
             getmonthexpense(a.ID,fyear,1,cong) as Jan,
             getmonthexpense(a.ID,fyear,2,cong) as Feb,
             getmonthexpense(a.ID,fyear,3,cong) as Mar,
             getmonthexpense(a.ID,fyear,4,cong) as Apr,
             getmonthexpense(a.ID,fyear,5,cong) as May,
             getmonthexpense(a.ID,fyear,6,cong) as Jun,
             getmonthexpense(a.ID,fyear,7,cong) as Jul,
             getmonthexpense(a.ID,fyear,8,cong) as Aug,
             getmonthexpense(a.ID,fyear,9,cong) as Sep,
             getmonthexpense(a.ID,fyear,10,cong) as Oct,
             getmonthexpense(a.ID,fyear,11,cong) as Nov,
             getmonthexpense(a.ID,fyear,12,cong) as Dece,
             getexpensetotal_lcc(a.ID,fyear,cong) as ExpenseTotal,
             (getbudgetedamount_lcc(a.ID,fyear,cong) - getexpensetotal_lcc(a.ID,fyear,cong)) as variance
    FROM     tblchurchbudget_details d inner join tblchurchbudget_header h on d.ID = h.ID
             left join tblaccounttypes a on d.accountId = a.ID
    WHERE    (h.yearId = fyear) AND (h.congregationId=cong);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_contribitions_report_all`(IN `cong` INT, IN `startd` DATE, IN `endd` DATE)
BEGIN
		SELECT   DISTINCT d.ID,
		 	     DATE_FORMAT(d.contributionDate,'%d/%m/%Y') AS contdate,
	             ucase(a.accountType) as conttype,
                 ucase(IF(d.category = 1,m.memberName,IF(d.category=2,g.groupName,
                       IF(d.category=3,t.districtName,s.serviceName)))) As cont,
                 d.amount,
                 ucase(p.paymentMethod) as paymethod,
                 d.paymentReference
		FROM     tblcontributions_header h inner join tblcontributions_details d
		 		 left join tblmember m on d.contributor = m.ID left join tblgroups g
         		 on d.contributotGroup = g.ID left join tbldistricts t on d.contributotDistrict
         		 = t.ID left join tblservices s on d.contributotService = s.ID inner join 
         		 tblaccounttypes a on d.contributionTypeId = a.ID left join tblpaymentmethods p
         		 on d.paymentMethodId = p.ID
        WHERE    (h.congregationId = cong) AND (h.status = 1) AND (d.contributionDate BETWEEN startd AND endd)
        ORDER BY d.contributionDate;
  	END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_expenses_all`(IN `cong` INT, `startd` DATE, `endd` DATE)
BEGIN
        SELECT   DATE_FORMAT(e.expenseDate,'%d/%m/%Y') AS expenseDate,
                 voucherNo,
                 ucase(a.accountType) as accountType, 
                 ucase(IF(e.expenseType = 1,'lcc',g.groupName)) As costcenter,
                 e.amount,ucase(p.paymentMethod) as paymethod,
                 ucase(e.paymentReference) as payref
        FROM     tblexpenses e inner join tblaccounttypes a on e.accountId=a.ID inner join tblpaymentmethods p
                 on e.paymethodId = p.ID left join tblgroups g on e.groupId = g.ID
        WHERE    (e.congregationId = cong) AND (e.expenseDate BETWEEN startd AND endd) AND (e.status = 1) AND (e.deleted = 0)
        ORDER BY e.expenseDate;
    END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getcustomerpayments`(IN `cid` INT, IN `sdate` DATE, IN `edate` DATE)
BEGIN
    SELECT DATE_FORMAT(p.paymentDate,'%d/%m/%y') As PaymentDate,
       	   FORMAT(p.amount,2) As Amount,
           UCASE(m.paymentMethod) As PaymentMethod,
           UCASE(p.paymentReference) As PayReference
	FROM   tblinvoice_payments p INNER JOIN tblinvoice_header h on p.invoice_Id = h.ID
       INNER JOIN tblpaymentmethods m on p.paymentId = m.ID
	WHERE  (h.customerId = cid) AND (p.paymentDate BETWEEN sdate AND edate)
	ORDER BY p.paymentDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getinvoicepaymentdetails`(IN `payno` INT, IN `suppid` INT)
BEGIN
    SELECT 
        h.invoiceNo,
        h.invoiceDate,
        h.duedate,
        h.inclusiveVat,
        getinvoicebalance_supplier_bydate(p.invoice_Id,p.paymentDate) AS Balance,
        p.amount
    FROM `tblinvoice_payments_suppliers` p join tblinvoice_header_suppliers h on p.invoice_Id = h.ID
    WHERE p.paymentNo = payno AND h.supplierId = suppid;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getinvoice_due`(IN `cong` INT)
BEGIN
SELECT h.ID,
 	   h.invoiceNo,
       DATE_FORMAT(h.invoiceDate,'%d/%m/%Y') AS invoiceDate, 
       DATE_FORMAT(h.duedate,'%d/%m/%Y') AS duedate,
       DATEDIFF(CURDATE(),h.duedate) AS duedays,
       ucase(c.customerName) as customer,
       h.inclusiveVat,
       getinvoice_paid(h.ID) as amountpaid,
       getinvoicebalance(h.ID) as balance
FROM   tblinvoice_header h inner join tblcustomers c on h.customerId = c.ID
WHERE  h.status < 2 AND (h.duedate < CURDATE());
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getinvoice_fullypaid`(IN `cong` INT, `startdate` DATE, `enddate` DATE)
BEGIN
    SELECT h.ID,
           DATE_FORMAT(h.invoiceDate,'%d/%m/%Y') AS invoiceDate, 
           h.invoiceNo,
           ucase(c.customerName) as customer,
           h.inclusiveVat,
           getinvoice_paid(h.ID) as amountpaid
    FROM   tblinvoice_header h inner join tblcustomers c on h.customerId = c.ID
    WHERE  h.status = 2 AND (h.invoiceDate BETWEEN startdate AND enddate);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getinvoice_wih_balances`(IN `cong` INT)
BEGIN
    SELECT 
        ucase(s.supplierName) As supplierName,
        UCASE(h.invoiceNo) as invoiceNo,
        DATE_FORMAT(h.invoiceDate,'%d-%m-%y') As invoiceDate,
        DATE_FORMAT(h.duedate,'%d-%m-%y') As dueDate,
        h.inclusiveVat,
        getinvoice_paid_supplier(h.ID) As amountPaid,
        getinvoicebalance_supplier(h.ID) As Balance
    FROM tblinvoice_header_suppliers h join tblsuppliers s on h.supplierId = s.ID  
    WHERE (h.deleted = 0) AND (h.congregationId = cong) AND (h.status <> 2)
    	  AND (h.invoiceNo IS NOT NULL)
    ORDER BY h.invoiceDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getinvoice_withbalances`(IN `cong` INT, `startdate` DATE, `enddate` DATE)
BEGIN
    SELECT h.ID,
           DATE_FORMAT(h.invoiceDate,'%d/%m/%Y') AS invoiceDate, 
           h.invoiceNo,
           ucase(c.customerName) as customer,
           h.inclusiveVat,
           getinvoice_paid(h.ID) as amountpaid,
           getinvoicebalance(h.ID) as balance
    FROM   tblinvoice_header h inner join tblcustomers c on h.customerId = c.ID
    WHERE  h.status < 2 AND (h.invoiceDate BETWEEN startdate AND enddate);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getPledge`(IN `pledgeid` INT)
BEGIN
SELECT IF(h.category=1,UCASE(m.memberName),IF(h.category=2,ucase(g.groupName),ucase(d.districtName))) as pledger,
	   FORMAT(h.amountPledged,2) AS amountPledged,getTotalPledge(h.ID) as totalPaid,(h.amountPledged - getTotalPledge(h.ID)) as 	 balance,h.congregationId,h.deleted
FROM tblpledges_header h left join tblmember m on h.pledgerMember=m.ID left join tblgroups g on h.pledgerGroup
	 =g.ID left join tbldistricts d on h.pledgerDistrict=d.ID
WHERE h.ID = pledgeid ;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_getsupplierpayments`(IN `sid` INT, IN `sdate` DATE, IN `edate` DATE)
BEGIN
    SELECT DATE_FORMAT(p.paymentDate,'%d/%m/%y') As PaymentDate,
       	   FORMAT(p.amount,2) As Amount,
           UCASE(m.paymentMethod) As PaymentMethod,
           UCASE(p.paymentReference) As PayReference
	FROM   tblinvoice_payments_suppliers p INNER JOIN tblinvoice_header_suppliers h on p.invoice_Id = h.ID
       INNER JOIN tblpaymentmethods m on p.paymentId = m.ID
	WHERE  (h.supplierId = sid) AND (p.paymentDate BETWEEN sdate AND edate)
	ORDER BY p.paymentDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_account_statement`(IN `sdate` DATE, IN `edate` DATE, IN `acc` VARCHAR(150), IN `cong` INT)
BEGIN
    DECLARE openingBalance DECIMAL(10, 2);

    
    SELECT 
        COALESCE(SUM(debit) - SUM(credit), 0) INTO openingBalance
    FROM tblledger
    WHERE transactionDate < sdate AND account = acc AND deleted = 0 AND congregationId = cong;

    
    SET @balance := openingBalance;

    
    SELECT 
        NULL AS ID,
        sdate AS transactionDate,
        acc AS account,
        0 AS debit,
        0 AS credit,
        @balance AS runningBalance,
        'Opening Balance' AS narration,
        NULL AS reference

    UNION ALL

    SELECT 
        ID,
        transactionDate,
        account,
        debit,
        credit,
        (@balance := @balance + debit - credit) AS runningBalance,
        narration,
        reference
    FROM (
        SELECT 
            ID,
            transactionDate,
            account,
            debit,
            credit,
            narration,
            reference
        FROM tblledger
        WHERE transactionDate BETWEEN sdate AND edate 
        AND account = acc 
        AND deleted = 0 
        AND congregationId = cong
        ORDER BY transactionDate, ID
    ) AS sortedTransactions;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_account_statement_parent`(IN `sdate` DATE, IN `edate` DATE, IN `acc` VARCHAR(150), IN `cong` INT)
BEGIN
    DECLARE openingBalance DECIMAL(10, 2);

    
    SELECT 
        COALESCE(SUM(debit) - SUM(credit), 0) INTO openingBalance
    FROM tblledger
    WHERE transactionDate < sdate AND (account = acc or parentaccount = acc) AND deleted = 0 AND congregationId = cong;

    
    SET @balance := openingBalance;

    
    SELECT 
        NULL AS ID,
        sdate AS transactionDate,
        acc AS account,
        0 AS debit,
        0 AS credit,
        @balance AS runningBalance,
        'Opening Balance' AS narration,
        NULL AS reference

    UNION ALL

    SELECT 
        ID,
        transactionDate,
        account,
        debit,
        credit,
        (@balance := @balance + debit - credit) AS runningBalance,
        narration,
        reference
    FROM (
        SELECT 
            ID,
            transactionDate,
            account,
            debit,
            credit,
            narration,
            reference
        FROM tblledger
        WHERE transactionDate BETWEEN sdate AND edate 
        AND (account = acc OR parentaccount = acc)
        AND deleted = 0 
        AND congregationId = cong
        ORDER BY transactionDate, ID
    ) AS sortedTransactions;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_assets`(IN `p_date` DATE, IN `cong` INT)
BEGIN
    SELECT 
        l.parentaccount,
        SUM(l.debit - l.credit) AS balance
    FROM 
    	tblledger l
    WHERE 
    	l.transactionDate <= p_date AND l.deleted = 0 AND l.congregationId = cong  AND l.accountId = 3 
    GROUP BY l.parentaccount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_assets_v1`(IN `p_date` DATE, IN `p_congregation_id` INT)
BEGIN
    DECLARE v_start_date DATE;

    SET v_start_date = get_year_start_date(p_date);

    SELECT
        l.parentaccount,
        SUM(l.debit - l.credit) AS balance
    FROM tblledger AS l
    WHERE l.transactionDate >= v_start_date
      AND l.transactionDate < p_date + INTERVAL 1 DAY
      AND l.deleted = 0
      AND l.congregationId = p_congregation_id
      AND l.accountId = 3
    GROUP BY l.parentaccount;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_liabilities_equity_v2`(IN `p_date` DATE, IN `cong` INT)
BEGIN
    DECLARE total_income DECIMAL(15,2);
    DECLARE total_expense DECIMAL(15,2);
    DECLARE net_income DECIMAL(15,2);

    SELECT IFNULL(SUM(credit - debit), 0) INTO total_income
    FROM 
    	tblledger l
    WHERE 
    	l.transactionDate <= p_date AND l.deleted = 0 AND l.accountId = 1 AND l.congregationId = cong;  

	SELECT 
    	IFNULL(SUM(debit - credit), 0) INTO total_expense
    FROM 
    	tblledger l
    WHERE 
    	l.transactionDate <= p_date AND l.deleted = 0 AND l.accountId = 2 AND l.congregationId = cong;

    
    SET net_income = total_income - total_expense;

    
    SELECT 
        l.parentaccount,
        SUM(l.credit - l.debit) AS balance
    FROM 
    	tblledger l
    WHERE 
    	l.transactionDate <= p_date  AND l.congregationId = cong AND l.deleted = 0  AND l.accountId IN (4, 6)  
    GROUP BY l.parentaccount

    UNION ALL

    SELECT 
        'Net Income' AS parentaccount,
        net_income AS balance;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_liabilities_equity_v3`(IN `t_date` DATE, IN `cong` INT)
BEGIN
    DECLARE start_date DATE;
    DECLARE total_income DECIMAL(15,2);
    DECLARE total_expense DECIMAL(15,2);
    DECLARE net_income DECIMAL(15,2);

    SET start_date = get_year_start_date(t_date);

    
    SELECT IFNULL(SUM(l.credit - l.debit), 0)
    INTO total_income
    FROM tblledger AS l
    WHERE l.transactionDate >= start_date
      AND l.transactionDate < t_date + INTERVAL 1 DAY
      AND l.deleted = 0
      AND l.accountId = 1
      AND l.congregationId = cong;

    
    SELECT IFNULL(SUM(l.debit - l.credit), 0)
    INTO total_expense
    FROM tblledger AS l
    WHERE l.transactionDate >= start_date
      AND l.transactionDate < t_date + INTERVAL 1 DAY
      AND l.deleted = 0
      AND l.accountId = 2
      AND l.congregationId = cong;

    SET net_income = total_income - total_expense;

    
    SELECT
        l.parentaccount,
        SUM(l.credit - l.debit) AS balance
    FROM tblledger AS l
    WHERE l.transactionDate >= start_date
      AND l.transactionDate < t_date + INTERVAL 1 DAY
      AND l.congregationId = cong
      AND l.deleted = 0
      AND l.accountId IN (4, 6)
    GROUP BY l.parentaccount

    UNION ALL

    SELECT
        'Net Income' AS parentaccount,
        net_income AS balance;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payments_all`(`sdate` DATE, `edate` DATE, IN `congid` INT)
BEGIN
    SELECT 
        DATE_FORMAT(p.paymentDate,'%d-%m-%y') As paymentDate,
        p.paymentNo,
        ucase(s.supplierName) As supplierName,
        h.invoiceNo,
        p.amount,
        ucase(p.paymentReference) As paymentReference
    FROM   tblinvoice_payments_suppliers p join tblinvoice_header_suppliers h on p.invoice_Id = h.ID 
           left join tblpaymentmethods m on p.paymentId = m.ID join tblsuppliers s on h.supplierId = s.ID
    WHERE (h.deleted = 0) AND (h.congregationId = congid) 
    	   AND (p.paymentDate BETWEEN sdate AND edate)
    ORDER BY paymentDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payments_by_invoiceno`(IN `invoiceno` VARCHAR(50))
BEGIN
    SELECT 
        DATE_FORMAT(p.paymentDate,'%d-%m-%y') As paymentDate,
        p.paymentNo,
        p.amount,
        ucase(m.paymentMethod) as payMethod,
        ucase(p.paymentReference) As paymentReference
    FROM   tblinvoice_payments_suppliers p join tblinvoice_header_suppliers h on p.invoice_Id = h.ID 
           left join tblpaymentmethods m on p.paymentId = m.ID
    WHERE h.invoiceNo = invoiceno
    ORDER BY paymentDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payments_by_supplier`(IN `suppid` INT, `sdate` DATE, `edate` DATE, IN `congid` INT)
BEGIN
    SELECT 
        DATE_FORMAT(p.paymentDate,'%d-%m-%y') As paymentDate,
        p.paymentNo,
        h.invoiceNo,
        p.amount,
        ucase(m.paymentMethod) as payMethod,
        ucase(p.paymentReference) As paymentReference
    FROM   tblinvoice_payments_suppliers p join tblinvoice_header_suppliers h on p.invoice_Id = h.ID 
           left join tblpaymentmethods m on p.paymentId = m.ID
    WHERE (h.supplierId = suppid) AND (h.deleted = 0) AND (h.congregationId = congid) 
    	   AND (p.paymentDate BETWEEN sdate AND edate)
    ORDER BY paymentDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_plans`(IN `cong` INT)
BEGIN
	SELECT  w.ID,
    	    w.WorkPlanName,
            IF(w.Level = 1,'Parish',IF(w.Level = 2,'LCC',IF(w.Level=3,'Group','District'))) AS `Level`,
            f.yearName,
            IF(w.WorkPlanStatus=0,'Unsubmitted','Submitted') as `status`
    FROM    tblworkplan w inner join tblfiscalyears f on w.FiscalYearId=f.ID
    WHERE   w.CongregationId = cong;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_pledges`(IN `actions` INT, `startd` DATE, `endd` DATE, `cong` INT)
BEGIN
	IF actions = 1 THEN
    	SELECT   DISTINCT h.ID,
        		 h.pledgeDate,
        		 ucase(IF(h.category = 1,m.memberName,IF(h.category=2,g.groupName,t.districtName))) As pledger,
                 h.amountPledged,
                 getamountpaid_pledge(h.ID) As amountPaid,
                 (h.amountPledged - getamountpaid_pledge(h.ID)) as balance
        FROM     tblpledges_header h inner join tblpledges_details d on h.ID=d.pledgeId
        		 left join tblmember m on h.pledgerMember = m.ID left join tblgroups g on
                 h.pledgerGroup = g.ID left join tbldistricts t on h.pledgerDistrict = t.ID
        WHERE    (h.deleted = 0) AND (h.pledgeDate BETWEEN startd AND endd) AND (h.congregationId = cong);
    ELSEIF actions = 2 THEN
    	SELECT   DISTINCT h.ID,
        		 h.pledgeDate,
        		 ucase(IF(h.category = 1,m.memberName,IF(h.category=2,g.groupName,t.districtName))) As pledger,
                 h.amountPledged,
                 getamountpaid_pledge(h.ID) As amountPaid,
                 (h.amountPledged - getamountpaid_pledge(h.ID)) as balance
        FROM     tblpledges_header h inner join tblpledges_details d on h.ID=d.pledgeId
        		 left join tblmember m on h.pledgerMember = m.ID left join tblgroups g on
                 h.pledgerGroup = g.ID left join tbldistricts t on h.pledgerDistrict = t.ID
        WHERE    (h.deleted = 0) AND (h.pledgeDate BETWEEN startd AND endd) AND (h.congregationId = cong)
        HAVING   balance > 0 ;
     ELSEIF actions = 3 THEN
    	SELECT   DISTINCT h.ID,
        		 h.pledgeDate,
        		 ucase(IF(h.category = 1,m.memberName,IF(h.category=2,g.groupName,t.districtName))) As pledger,
                 h.amountPledged,
                 getamountpaid_pledge(h.ID) As amountPaid,
                 (h.amountPledged - getamountpaid_pledge(h.ID)) as balance
        FROM     tblpledges_header h inner join tblpledges_details d on h.ID=d.pledgeId
        		 left join tblmember m on h.pledgerMember = m.ID left join tblgroups g on
                 h.pledgerGroup = g.ID left join tbldistricts t on h.pledgerDistrict = t.ID
        WHERE    (h.deleted = 0) AND (h.pledgeDate BETWEEN startd AND endd) AND (h.congregationId = cong)
        HAVING   balance = 0 ;
     END IF ;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_pledge_payments`(IN `cong` INT, IN `startd` DATE, IN `endd` DATE)
BEGIN
	SELECT   d.ID,
		 	 d.paymentDate,
		 	 ucase(IF(h.category = 1,m.memberName,IF(h.category=2,g.groupName,t.districtName))) as pledger,
         	 d.amountPaid,
         	 ucase(p.paymentMethod) as paymentMethod,
             ucase(d.payReference) as payReference
	FROM     tblpledges_details d inner join tblpledges_header h on d.pledgeId = h.ID
		     left join tblmember m on h.pledgerMember = m.ID left join tblgroups g on h.pledgerGroup
             = g.ID left join tbldistricts t on h.pledgerDistrict = t.ID left join tblpaymentmethods p
             on d.paymethodId = p.ID
	WHERE    d.amountPaid > 0 AND (h.congregationId = cong) AND (h.deleted = 0) AND (d.paymentDate BETWEEN startd AND endd);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_rights`(IN `userid` INT)
BEGIN
    SELECT 	u.FormId as ID,
            f.FormName,
            f.Path,
            u.access as access
    FROM   `tbluserrights` u inner join tblforms f on u.FormId = f.ID
    WHERE   U.UserId = userid
    UNION ALL
    SELECT  ID,
            FormName,
            `Path`,
            0 as access
    FROM    tblforms
    WHERE   ID NOT IN (SELECT FormId FROM tbluserrights WHERE (UserId = userid));
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_subaccounts_balances`(IN `edate` DATE)
BEGIN
    SELECT 
    	DISTINCT a.ID,
    	a.AccountName,
        get_subaccount_balance_by_date(a.GroupDistrict, IF(a.GroupDistrict = 'group',a.GroupId,a.DistrictId) ,edate) AS Balance
    FROM 
    	tblbanksubaccounts a
    WHERE
    	a.Deleted = 0
    ORDER BY
    	a.AccountName;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_subaccounts_balances_breakdown_district`(IN `edate` DATE, IN `did` INT, IN `sid` INT)
BEGIN
    SELECT 
        TransactionDate,
        Amount,
        Narration,
        Reference 
    FROM 
        tblbanktransactions_subaccounts
    WHERE
        (TransactionDate <= edate) AND (SubAccountId = sid)
        
    UNION ALL
    
    SELECT
    	e.expenseDate AS TransactionDate,
        e.amount  AS Amount,
        e.narration as Narration,
        e.paymentReference AS Reference
    FROM
    	tblexpenses e
    WHERE
    	(e.deleted = 0) AND (e.requisitionId IS NOT NULL) AND (e.expenseDate <= edate) AND (e.districtId = did)
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_subaccount_statement`(IN `datefrom` DATE, IN `dateto` DATE, IN `subaccount` INT)
BEGIN
	SELECT
		datefrom AS TransactionDate,
        '' AS Reference,
        (SELECT IFNULL(SUM(Amount),0) AS OpeningBalance 
         			   FROM tblbanktransactions_subaccounts 
         			   WHERE (SubAccountId=subaccount) AND (TransactionDate < datefrom)) AS AmountIn,
        0 as AmountOut,
        'Opening Balance' As Narration
	UNION ALL
    SELECT 
        TransactionDate,
        Reference,
        IF(Amount > 0,Amount,0) As AmountIn,
        IF(Amount < 0,Amount,0) As AmountOut,
        Narration
    FROM
        tblbanktransactions_subaccounts 
    WHERE 
        (SubAccountId=subaccount) AND (TransactionDate BETWEEN datefrom AND dateto)
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_subaccount_statement_with_expense_district`(IN `datefrom` DATE, IN `dateto` DATE, IN `subaccount` INT, IN `did` INT)
BEGIN
    
    SELECT
        datefrom AS TransactionDate,
        '' AS Reference,
        get_subaccount_opening_balance_district(datefrom,subaccount,did) AS AmountIn,
        0 as AmountOut,
        'Opening Balance' As Narration
        
	UNION ALL

    SELECT 
        TransactionDate,
        Reference,
        IF(Amount > 0,Amount,0) As AmountIn,
        IF(Amount < 0,Amount,0) As AmountOut,
        Narration
    FROM
        tblbanktransactions_subaccounts 
    WHERE 
        (SubAccountId=subaccount) AND (TransactionDate BETWEEN datefrom AND dateto)
        
    UNION ALL

    SELECT
        expenseDate as TransactionDate,
        paymentReference as Reference,
        0 As AmountIn,
        (amount * -1) as AmountOut,
        narration as Narration
    FROM
        tblexpenses
    WHERE
        (deleted = 0) AND 
        (status = 1) AND 
        (expenseDate BETWEEN datefrom and dateto) AND 
        (requisitionId IS NOT NULL) AND 
        (districtId = did)
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_subaccount_statement_with_expense_group`(IN `datefrom` DATE, IN `dateto` DATE, IN `subaccount` INT, IN `gid` INT)
BEGIN
    
    SELECT
        datefrom AS TransactionDate,
        '' AS Reference,
        get_subaccount_opening_balance_group(datefrom,subaccount,gid) AS AmountIn,
        0 as AmountOut,
        'Opening Balance' As Narration
        
	UNION ALL

    SELECT 
        TransactionDate,
        Reference,
        IF(Amount > 0,Amount,0) As AmountIn,
        IF(Amount < 0,Amount,0) As AmountOut,
        Narration
    FROM
        tblbanktransactions_subaccounts 
    WHERE 
        (SubAccountId=subaccount) AND (TransactionDate BETWEEN datefrom AND dateto)
        
    UNION ALL

    SELECT
        expenseDate as TransactionDate,
        paymentReference as Reference,
        0 As AmountIn,
        (amount * -1) as AmountOut,
        narration as Narration
    FROM
        tblexpenses
    WHERE
        (deleted = 0) AND 
        (status = 1) AND 
        (expenseDate BETWEEN datefrom and dateto) AND 
        (requisitionId IS NOT NULL) AND 
        (groupId = gid)
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_trial_balance_updated`(IN `asofdate` DATE, IN `reportType` ENUM('summary','detailed'), IN `cong` INT)
BEGIN
    IF reportType = 'summary' THEN
       SELECT 
            parentaccount AS account,
            SUM(debit) AS total_debit,
            SUM(credit) AS total_credit,
            SUM(debit) - SUM(credit) AS net_balance
        FROM tblledger
        WHERE transactionDate <= asofdate AND deleted = 0 AND congregationId=cong
        AND parentaccount IS NOT NULL
        GROUP BY parentaccount
        ORDER BY parentaccount;
    ELSEIF reportType = 'detailed' THEN
        SELECT 
            account,
            parentaccount,
            SUM(debit) AS total_debit,
            SUM(credit) AS total_credit,
            SUM(debit) - SUM(credit) AS net_balance
        FROM tblledger
        WHERE transactionDate <= asofdate AND deleted = 0 AND congregationId = cong
        GROUP BY account
        ORDER BY parentaccount, account;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_budgetvexpense`(IN `fyear` INT, IN `cong` INT, IN `gid` INT)
BEGIN
    SELECT 	 a.ID,
             ucase(a.accountType) as accountType,
             getbudgetedamount(a.ID,fyear,gid) as budgetedAmount,
             getmonthexpense_group(a.ID,fyear,1,gid,cong) as Jan,
             getmonthexpense_group(a.ID,fyear,2,gid,cong) as Feb,
             getmonthexpense_group(a.ID,fyear,3,gid,cong) as Mar,
             getmonthexpense_group(a.ID,fyear,4,gid,cong) as Apr,
             getmonthexpense_group(a.ID,fyear,5,gid,cong) as May,
             getmonthexpense_group(a.ID,fyear,6,gid,cong) as Jun,
             getmonthexpense_group(a.ID,fyear,7,gid,cong) as Jul,
             getmonthexpense_group(a.ID,fyear,8,gid,cong) as Aug,
             getmonthexpense_group(a.ID,fyear,9,gid,cong) as Sep,
             getmonthexpense_group(a.ID,fyear,10,gid,cong) as Oct,
             getmonthexpense_group(a.ID,fyear,11,gid,cong) as Nov,
             getmonthexpense_group(a.ID,fyear,12,gid,cong) as Dece,
             getexpensetotal_group(a.ID,fyear,gid) as ExpenseTotal,
             (getbudgetedamount(a.ID,fyear,gid) - getexpensetotal_group(a.ID,fyear,gid)) as variance
    FROM     tblaccounttypes a left join tblgroupbudget_details d on a.ID = d.accountId
             inner join tblgroupbudget_header h on d.ID = h.ID
    WHERE    (h.fiscalYearId = fyear) AND (h.congregationId=cong) AND (h.groupId = gid);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_menu_pairing`(IN `user1` INT, IN `user2` INT)
BEGIN
    INSERT INTO tbluserrights (UserId,FormId,access)
    SELECT user2,FormId,access 
    FROM   tbluserrights 
    WHERE  (UserId=user1);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_mmf_statement`(`gid` INT, `sdate` DATE, `edate` DATE)
BEGIN
    SELECT
        sdate AS TransactionDate,
        'Opening Balance' AS Narration,
        '' As Reference,
        getmmfopeningbal(gid,sdate) As Debit,
        0 As Credit
    UNION ALL
    SELECT
        TransactionDate,
        '' As Narration,
        Reference,
        Debit,
        Credit
    FROM
        tblmmf
    WHERE
        (TransactionDate BETWEEN sdate AND edate) AND (GroupId = gid) AND (Deleted = 0)
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pettycashutilization`(IN sdate DATE, IN edate DATE, IN congid INT, IN accid INT)
BEGIN
    SELECT DATE_FORMAT(sdate,'%d/%m/%y') AS TransactionDate,
           'Opening Balance' AS Narration,
           getopeningbal(sdate,congid,accid) AS Debit,
           0 AS Credit,
           '' AS Reference
    UNION ALL
    SELECT
        DATE_FORMAT(TransactionDate,'%d/%m/%y') AS TransactionDate,
        UCASE(Narration) AS Narration,
        FORMAT(Debit,2) AS Debit,
        FORMAT(Credit,2) AS Credit,
        UCASE(Reference) AS Reference
    FROM
        tblpettycash
    WHERE
        (TransactionDate BETWEEN sdate AND edate) AND (CongregationId = congid)
        AND (PettyCashAccountId = accid) AND (Deleted = 0)
    ORDER BY TransactionDate;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_role_pairing`(IN `user1` INT, IN `role` INT)
BEGIN
    INSERT INTO tbluserrights (UserId,FormId,access)
    SELECT user1,FormId,1 
    FROM   tblrolerights 
    WHERE  (RoleId=role);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_trialbalance`(IN `startdate` DATE, IN `enddate` DATE, IN `cong` INT)
BEGIN
	SELECT 	DISTINCT account,@balance := ((gettotaldebits(account,startdate,enddate,cong)) -  
				 (gettotalcredits(account,startdate,enddate,cong))) as bal,
                 IF(@balance > 0,@balance,'') As Debit,IF(@balance < 0,@balance * -1,'') As credit
	FROM    tblledger
    WHERE   (congregationId = cong) AND (transactionDate BETWEEN startdate AND enddate) AND (deleted = 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_trialbalance_detailed_v2`(IN `startdate` DATE, IN `enddate` DATE, IN `cong` INT)
BEGIN
    SELECT 
    	l.account AS account,
        CASE 
            WHEN SUM(l.debit - l.credit) >= 0 THEN SUM(l.debit - l.credit)
            ELSE 0
        END AS debit,
        CASE 
            WHEN SUM(l.debit - l.credit) < 0 THEN ABS(SUM(l.debit - l.credit))
            ELSE 0
        END AS credit,
        CASE accountId
            WHEN 3 THEN 1
            WHEN 4 THEN 2
            WHEN 6 THEN 3
            WHEN 5 THEN 4
            WHEN 1 THEN 5
            WHEN 2 THEN 6
            ELSE 7
        END AS type_order
    FROM 
        tblledger l
    WHERE 
        l.congregationId = cong
        AND l.deleted = 0 
        AND l.transactionDate BETWEEN startdate AND enddate
    GROUP BY 
        l.account
    ORDER BY 
        type_order, l.account;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_trialbalance_parish`(IN `startdate` DATE, IN `enddate` DATE)
BEGIN
	SELECT 	DISTINCT account,@balance := ((gettotaldebits_parish(account,startdate,enddate)) -  
				 (gettotalcredits_parish(account,startdate,enddate))) as bal,
                 IF(@balance > 0,@balance,'') As Debit,IF(@balance < 0,@balance * -1,'') As credit
	FROM    tblledger
    WHERE   (transactionDate BETWEEN startdate AND enddate) AND deleted = 0;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_trialbalance_summary`(IN `startdate` DATE, IN `enddate` DATE, IN `cong` INT)
BEGIN
	SELECT 	DISTINCT parentaccount,@balance := ((gettotaldebits_parent(parentaccount,startdate,enddate,cong)) -  
				 (gettotalcredits_parent(parentaccount,startdate,enddate,cong))) as bal,
                 IF(@balance > 0,@balance,'') As Debit,IF(@balance < 0,@balance * -1,'') As credit
	FROM    tblledger
    WHERE   (congregationId = cong) AND (transactionDate BETWEEN startdate AND enddate) AND (deleted = 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_trialbalance_summary_v2`(IN `startdate` DATE, IN `enddate` DATE, IN `cong` INT)
BEGIN
    SELECT 
    	`parentaccount` AS account,               
        CASE 
            WHEN SUM(l.debit - l.credit) >= 0 THEN SUM(l.debit - l.credit)
            ELSE 0
        END AS debit,
        CASE 
            WHEN SUM(l.debit - l.credit) < 0 THEN ABS(SUM(l.debit - l.credit))
            ELSE 0
        END AS credit,
        CASE accountId
            WHEN 3 THEN 1
            WHEN 4 THEN 2
            WHEN 6 THEN 3
            WHEN 5 THEN 4
            WHEN 1 THEN 5
            WHEN 2 THEN 6
            ELSE 7
        END AS type_order
    FROM 
        tblledger l            
    WHERE 
        l.congregationId = cong
        AND l.deleted = 0 and l.transactionDate BETWEEN startdate and enddate
    GROUP BY 
        `parentaccount`
    ORDER BY 
        type_order, `parentaccount`;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `vw_banktransactions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_banktransactions` AS select `t`.`ID` AS `ID`,`t`.`TransactionDate` AS `TransactionDate`,`a`.`accountType` AS `BankName`,`t`.`Amount` AS `Amount`,`m`.`methodName` AS `TransactionType`,`t`.`Reference` AS `Reference`,`t`.`CongregationId` AS `CongregationId` from ((`tblbanktransactions` `t` join `tblaccounttypes` `a` on(`t`.`BankId` = `a`.`ID`)) join `tbltransactionmethods` `m` on(`t`.`TransactionTypeId` = `m`.`ID`)) where `t`.`Deleted` = 0 order by `t`.`ID` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_byage`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_byage` AS select ucase(`m`.`memberName`) AS `memberName`,ucase(`g`.`gender`) AS `gender`,year(current_timestamp()) - year(`m`.`dob`) - (right(current_timestamp(),5) < right(`m`.`dob`,5)) AS `age`,`m`.`contact` AS `contact`,ucase(`d`.`districtName`) AS `district`,if(`m`.`membershipStatus` = 1,'FULL',if(`m`.`membershipStatus` = 2,'ADHERENT',if(`m`.`membershipStatus` = 3,'ASSOCIATE',if(`m`.`membershipStatus` = 4,'UNDER-12','NOT SPECIFIED')))) AS `remark`,`m`.`districtId` AS `districtId`,`m`.`congregationId` AS `congregation` from ((`tblmember` `m` left join `tblgender` `g` on(`m`.`genderId` = `g`.`ID`)) left join `tbldistricts` `d` on(`m`.`districtId` = `d`.`ID`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_cashreceipts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_cashreceipts` AS select `p`.`ID` AS `ID`,date_format(`p`.`TransactionDate`,'%d/%m/%Y') AS `TransactionDate`,`p`.`ReceiptNo` AS `ReceiptNo`,`p`.`Debit` AS `Debit`,concat(ucase(`a`.`accountType`),'-',ifnull(`a`.`accountNo`,'')) AS `Bank`,ucase(`pc`.`accountType`) AS `PettyCashAccount`,`p`.`PettyCashAccountId` AS `PettyCashAccountId`,`p`.`Reference` AS `Reference`,`p`.`IsReceipt` AS `IsReceipt`,`p`.`CongregationId` AS `CongregationId` from ((`tblpettycash` `p` join `tblaccounttypes` `a` on(`p`.`BankId` = `a`.`ID`)) join `tblaccounttypes` `pc` on(`p`.`PettyCashAccountId` = `pc`.`ID`)) where `p`.`Deleted` = 0 and `p`.`IsReceipt` = 1 and `p`.`TransactionType` is null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_contributorspertxn`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_contributorspertxn` AS select `d`.`ID` AS `ID`,`d`.`HeaderId` AS `HeaderId`,`d`.`contributionTypeId` AS `contributionTypeId`,ucase(`a`.`accountType`) AS `accountType`,`d`.`amount` AS `amount`,`d`.`category` AS `category`,ucase(`c`.`categoryName`) AS `categoryname`,`getcontributorid`(`d`.`category`,`d`.`ID`) AS `contributorid`,ucase(`getcontributor`(`d`.`category`,`getcontributorid`(`d`.`category`,`d`.`ID`))) AS `contributor`,`d`.`subaccount` AS `subaccount` from (((`tblcontributions_details` `d` join `tblaccounttypes` `a` on(`d`.`contributionTypeId` = `a`.`ID`)) join `tblcontributioncategories` `c` on(`d`.`category` = `c`.`ID`)) join `tblcontributions_header` `h` on(`d`.`HeaderId` = `h`.`ID`)) where `h`.`Deleted` = 0 order by `d`.`HeaderId`,`d`.`ID` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_deposits`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_deposits` AS select `d`.`ID` AS `ID`,date_format(`d`.`DepositDate`,'%d/%m/%y') AS `DepositDate`,ucase(concat(`a`.`accountType`,'-',`a`.`accountNo`)) AS `Bank`,format(`d`.`Amount`,2) AS `AmountDeposited`,ucase(`d`.`Reference`) AS `Reference`,`d`.`CongregationId` AS `CongregationId` from (`tbldesposits` `d` join `tblaccounttypes` `a` on(`d`.`BankId` = `a`.`ID`)) where `d`.`Deleted` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_elders`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_elders` AS select `e`.`ID` AS `ID`,`e`.`ElderName` AS `ElderName`,`e`.`Contact` AS `Contact` from `tblelders` `e` where `e`.`Deleted` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_expenses`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_expenses` AS select `e`.`ID` AS `ID`,`e`.`expenseDate` AS `expenseDate`,`e`.`expenseType` AS `expenseType`,`e`.`voucherNo` AS `voucherNo`,ucase(`a`.`accountType`) AS `account`,format(`e`.`amount`,2) AS `amount`,if(`e`.`expenseType` = 1,'CHURCH EXPENSE','GROUP EXPENSE') AS `category`,if(`e`.`expenseType` = 1,'CHURCH',ucase(`g`.`groupName`)) AS `costcentre`,`e`.`status` AS `status`,`e`.`congregationId` AS `congregationId`,`e`.`fileName` AS `fileName` from ((`tblexpenses` `e` join `tblaccounttypes` `a` on(`e`.`accountId` = `a`.`ID`)) left join `tblgroups` `g` on(`e`.`groupId` = `g`.`ID`)) where `e`.`fiscalYearId` = `getYearID`() order by `e`.`ID` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_expensevoucher`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_expensevoucher` AS select `e`.`ID` AS `ID`,date_format(`e`.`expenseDate`,'%d/%m/%Y') AS `expenseDate`,`e`.`expenseType` AS `expenseType`,`e`.`voucherNo` AS `voucherNo`,ucase(`a`.`accountType`) AS `account`,format(`e`.`amount`,2) AS `amount`,if(`e`.`expenseType` = 1,'CHURCH EXPENSE','GROUP EXPENSE') AS `category`,if(`e`.`expenseType` = 1,'CHURCH',ucase(`g`.`groupName`)) AS `costcentre`,ucase(`e`.`narration`) AS `narration`,ucase(`c`.`CongregationName`) AS `CongregationName` from (((`tblexpenses` `e` join `tblaccounttypes` `a` on(`e`.`accountId` = `a`.`ID`)) left join `tblgroups` `g` on(`e`.`groupId` = `g`.`ID`)) join `tblcongregation` `c` on(`e`.`congregationId` = `c`.`ID`)) where `e`.`deleted` = 0 order by `e`.`ID` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_expensevoucher_2`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_expensevoucher_2` AS select `e`.`ID` AS `ID`,date_format(`e`.`expenseDate`,'%d/%m/%Y') AS `expenseDate`,`e`.`expenseType` AS `expenseType`,`e`.`voucherNo` AS `voucherNo`,ucase(`a`.`accountType`) AS `account`,format(`e`.`amount`,2) AS `amount`,if(`e`.`expenseType` = 1,'CHURCH EXPENSE','GROUP EXPENSE') AS `category`,if(`e`.`expenseType` = 1,'CHURCH',ucase(`g`.`groupName`)) AS `costcentre`,ucase(`e`.`narration`) AS `narration`,ucase(`p`.`paymentMethod`) AS `paymentMethod`,ucase(`c`.`CongregationName`) AS `CongregationName` from ((((`tblexpenses` `e` join `tblaccounttypes` `a` on(`e`.`accountId` = `a`.`ID`)) left join `tblgroups` `g` on(`e`.`groupId` = `g`.`ID`)) join `tblcongregation` `c` on(`e`.`congregationId` = `c`.`ID`)) join `tblpaymentmethods` `p` on(`e`.`paymethodId` = `p`.`ID`)) where `e`.`deleted` = 0 order by `e`.`ID` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_family`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_family` AS select ucase(`m`.`memberName`) AS `Main`,ucase(`c`.`memberName`) AS `other`,ucase(`r`.`relationship`) AS `relation`,`m`.`districtId` AS `district` from (((`tblmember_family` `f` join `tblmember` `m` on(`f`.`memberId` = `m`.`ID`)) join `tblmember` `c` on(`f`.`familyMemberId` = `c`.`ID`)) join `tblrelationship` `r` on(`f`.`relationshipId` = `r`.`ID`)) where `f`.`type` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_getcontribution`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_getcontribution` AS select `d`.`ID` AS `ID`,`d`.`contributionDate` AS `contributionDate`,ucase(`a`.`accountType`) AS `contType`,format(`d`.`amount`,2) AS `amount`,ucase(`c`.`categoryName`) AS `Category`,if(`d`.`category` = 1,ucase(`m`.`memberName`),if(`d`.`category` = 2,ucase(`g`.`groupName`),if(`d`.`category` = 3,ucase(`t`.`districtName`),ucase(`s`.`serviceName`)))) AS `contrby`,if(`h`.`status` = 0,'Unapproved','Approved') AS `State`,`h`.`congregationId` AS `congregationId` from (((((((`tblcontributions_details` `d` join `tblaccounttypes` `a` on(`d`.`contributionTypeId` = `a`.`ID`)) join `tblcontributioncategories` `c` on(`d`.`category` = `c`.`ID`)) left join `tblmember` `m` on(`d`.`contributor` = `m`.`ID`)) left join `tblgroups` `g` on(`d`.`contributotGroup` = `g`.`ID`)) left join `tbldistricts` `t` on(`d`.`contributotDistrict` = `t`.`ID`)) left join `tblservices` `s` on(`d`.`contributotService` = `s`.`ID`)) join `tblcontributions_header` `h` on(`d`.`ID` = `h`.`ID`)) where `h`.`fiscalYearId` = `getYearID`() order by `h`.`postedDate` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_getcontributions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_getcontributions` AS select `h`.`ID` AS `ID`,`d`.`contributionDate` AS `contributionDate`,ucase(`a`.`accountType`) AS `contType`,format(`d`.`amount`,2) AS `amount`,ucase(`c`.`categoryName`) AS `Category`,if(`d`.`category` = 1,ucase(`m`.`memberName`),if(`d`.`category` = 2,ucase(`g`.`groupName`),if(`d`.`category` = 3,ucase(`t`.`districtName`),ucase(`s`.`serviceName`)))) AS `contrby`,if(`h`.`status` = 1,'Approved','Unapproved') AS `State`,`h`.`congregationId` AS `congregationId`,`h`.`Deleted` AS `deleted`,`h`.`fiscalYearId` AS `fiscalYearId` from (((((((`tblcontributions_header` `h` join `tblcontributions_details` `d` on(`h`.`ID` = `d`.`ID`)) join `tblaccounttypes` `a` on(`d`.`contributionTypeId` = `a`.`ID`)) join `tblcontributioncategories` `c` on(`d`.`category` = `c`.`ID`)) left join `tblmember` `m` on(`d`.`contributor` = `m`.`ID`)) left join `tblgroups` `g` on(`d`.`contributotGroup` = `g`.`ID`)) left join `tbldistricts` `t` on(`d`.`contributotDistrict` = `t`.`ID`)) left join `tblservices` `s` on(`d`.`contributotService` = `s`.`ID`)) where `h`.`fiscalYearId` = `getYearID`() */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_getexpenses`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_getexpenses` AS select `e`.`ID` AS `ID`,`e`.`expenseDate` AS `expenseDate`,`e`.`expenseType` AS `expenseType`,`e`.`voucherNo` AS `voucherNo`,ucase(`a`.`accountType`) AS `account`,format(`e`.`amount`,2) AS `amount`,if(`e`.`expenseType` = 1,'CHURCH EXPENSE','GROUP EXPENSE') AS `category`,if(`e`.`expenseType` = 1,'CHURCH',ucase(`g`.`groupName`)) AS `costcentre`,`e`.`status` AS `status`,`e`.`congregationId` AS `congregationId`,`e`.`fileName` AS `fileName`,`e`.`deleted` AS `deleted` from ((`tblexpenses` `e` join `tblaccounttypes` `a` on(`e`.`accountId` = `a`.`ID`)) left join `tblgroups` `g` on(`e`.`groupId` = `g`.`ID`)) order by `e`.`ID` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_getexpenses_2`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_getexpenses_2` AS select `e`.`ID` AS `ID`,`e`.`expenseDate` AS `expenseDate`,`e`.`expenseType` AS `expenseType`,`e`.`voucherNo` AS `voucherNo`,ucase(`a`.`accountType`) AS `account`,format(`e`.`amount`,2) AS `amount`,if(`e`.`expenseType` = 1,'CHURCH EXPENSE',if(`e`.`expenseType` = 2,'GROUP EXPENSE','DISTRICT EXPENSE')) AS `category`,if(`e`.`expenseType` = 1,'CHURCH',if(`e`.`expenseType` = 2,ucase(`g`.`groupName`),ucase(`d`.`districtName`))) AS `costcentre`,`e`.`status` AS `status`,`e`.`congregationId` AS `congregationId`,`e`.`fileName` AS `fileName`,`e`.`deleted` AS `deleted` from (((`tblexpenses` `e` join `tblaccounttypes` `a` on(`e`.`accountId` = `a`.`ID`)) left join `tblgroups` `g` on(`e`.`groupId` = `g`.`ID`)) left join `tbldistricts` `d` on(`e`.`districtId` = `d`.`ID`)) order by `e`.`ID` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_group_requisitions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_group_requisitions` AS select `f`.`ID` AS `ID`,`f`.`ReqNo` AS `ReqNo`,`f`.`RequisitionDate` AS `RequestDate`,if(`f`.`RequestType` = 'group',ucase(`g`.`groupName`),if(`f`.`RequestType` = 'district',ucase(`d`.`districtName`),ucase(`c`.`categoryName`))) AS `RequestedBy`,format(`f`.`AmountRequested`,2) AS `AmountReq`,if(`f`.`Status` = 0,'Pending Approval',if(`f`.`Status` = 1,'Approved','Rejected')) AS `State`,`f`.`Status` AS `Status`,`f`.`CongregationId` AS `congregationId` from (((`tblfundrequisition` `f` left join `tblgroups` `g` on(`f`.`GroupId` = `g`.`ID`)) left join `tbldistricts` `d` on(`f`.`DistrictId` = `d`.`ID`)) left join `tblchurchrequisitioncategories` `c` on(`f`.`ChurchCategoryId` = `c`.`id`)) where `f`.`Deleted` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_groupofficials`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_groupofficials` AS select `o`.`ID` AS `ID`,ucase(`f`.`yearName`) AS `yearName`,ucase(`g`.`groupName`) AS `groupName`,ucase(`c`.`memberName`) AS `chairman`,ucase(`t`.`memberName`) AS `treasurer`,ucase(`s`.`memberName`) AS `secretary`,`o`.`congregationId` AS `congregationId` from (((((`tblgroupofficials` `o` join `tblfiscalyears` `f` on(`o`.`yearId` = `f`.`ID`)) join `tblgroups` `g` on(`o`.`groupId` = `g`.`ID`)) join `tblmember` `c` on(`o`.`chairmanId` = `c`.`ID`)) join `tblmember` `t` on(`o`.`treasurerId` = `t`.`ID`)) join `tblmember` `s` on(`o`.`secretaryId` = `s`.`ID`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_invoices`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_invoices` AS select `h`.`ID` AS `ID`,`h`.`invoiceDate` AS `invoiceDate`,`h`.`invoiceNo` AS `invoiceNo`,ucase(`c`.`customerName`) AS `customer`,`h`.`duedate` AS `duedate`,`h`.`inclusiveVat` - (select ifnull(sum(`tblinvoice_payments`.`amount`),0) AS `paid` from `tblinvoice_payments` where `tblinvoice_payments`.`invoice_Id` = `h`.`ID`) AS `balance`,if(`h`.`status` = 0,'Unpaid',if(`h`.`status` = 1,'Partially Paid','Paid')) AS `state`,`h`.`status` AS `status`,`h`.`postedBy` AS `postedBy` from (`tblinvoice_header` `h` join `tblcustomers` `c` on(`h`.`customerId` = `c`.`ID`)) order by `h`.`status`,`h`.`invoiceNo` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_loadpledgebalances`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_loadpledgebalances` AS select distinct `h`.`ID` AS `ID`,`h`.`category` AS `category`,if(`h`.`category` = 1,ucase(`m`.`memberName`),if(`h`.`category` = 2,ucase(`g`.`groupName`),ucase(`t`.`districtName`))) AS `pledger`,if(`h`.`category` = 1,'MEMBER',if(`h`.`category` = 2,'GROUP','DISTRICT')) AS `cat`,format(`h`.`amountPledged`,2) AS `amountPledged`,format((select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`),2) AS `amountPaid`,format(`h`.`amountPledged` - (select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`),2) AS `balance` from ((((`tblpledges_header` `h` join `tblpledges_details` `d` on(`h`.`ID` = `d`.`pledgeId`)) left join `tblmember` `m` on(`h`.`pledgerMember` = `m`.`ID`)) left join `tblgroups` `g` on(`h`.`pledgerGroup` = `g`.`ID`)) left join `tbldistricts` `t` on(`h`.`pledgerDistrict` = `t`.`ID`)) where `h`.`amountPledged` > (select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_members_minimal`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_members_minimal` AS select `tblmember`.`ID` AS `ID`,`tblmember`.`memberName` AS `memberName`,`tblmember`.`districtId` AS `districtId` from `tblmember` where `tblmember`.`memberStatus` = 1 and `tblmember`.`deleted` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_pledgewithbalances`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_pledgewithbalances` AS select distinct `h`.`ID` AS `ID`,if(`h`.`category` = 1,ucase(`m`.`memberName`),if(`h`.`category` = 2,ucase(`g`.`groupName`),ucase(`t`.`districtName`))) AS `pledger`,if(`h`.`category` = 1,'MEMBER',if(`h`.`category` = 2,'GROUP','DISTRICT')) AS `category`,format(`h`.`amountPledged`,2) AS `amountPledged`,format((select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`),2) AS `amountPaid`,format(`h`.`amountPledged` - (select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`),2) AS `balance`,if(`h`.`category` = 1,(select `tblmember`.`contact` from `tblmember` where `tblmember`.`ID` = `h`.`pledgerMember`),if(`h`.`category` = 2,(select `tblmember`.`contact` from `tblmember` where `tblmember`.`ID` = `getChairmanId`(`h`.`pledgerGroup`)),(select `tblusers`.`contact` from `tblusers` where `tblusers`.`districtId` = `h`.`pledgerDistrict`))) AS `contact`,`h`.`deleted` AS `deleted`,`h`.`congregationId` AS `congregationId` from ((((`tblpledges_header` `h` join `tblpledges_details` `d` on(`h`.`ID` = `d`.`pledgeId`)) left join `tblmember` `m` on(`h`.`pledgerMember` = `m`.`ID`)) left join `tblgroups` `g` on(`h`.`pledgerGroup` = `g`.`ID`)) left join `tbldistricts` `t` on(`h`.`pledgerDistrict` = `t`.`ID`)) where `h`.`amountPledged` > (select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`) order by format(`h`.`amountPledged` - (select ifnull(sum(`tblpledges_details`.`amountPaid`),0) from `tblpledges_details` where `tblpledges_details`.`pledgeId` = `h`.`ID`),2) desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_requisition_approvals`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_requisition_approvals` AS select `f`.`ID` AS `ID`,`f`.`ReqNo` AS `ReqNo`,if(`f`.`Status` = 0,'Pending Approval',if(`f`.`Status` = 1,'Approved','Rejected')) AS `State`,`f`.`RequisitionDate` AS `ReqDate`,if(`f`.`RequestType` = 'group',ucase(`g`.`groupName`),ucase(`d`.`districtName`)) AS `GroupName`,format(`f`.`AmountRequested`,2) AS `AmountRequested`,format(`f`.`AmountApproved`,2) AS `AmountApproved`,ucase(`u`.`UserName`) AS `ApprovedBy`,to_days(curdate()) - to_days(`f`.`ApprovalDate`) AS `DiffInDate`,`f`.`Status` AS `Status`,`f`.`CongregationId` AS `CongregationId` from (((`tblfundrequisition` `f` left join `tblgroups` `g` on(`f`.`GroupId` = `g`.`ID`)) left join `tbldistricts` `d` on(`f`.`DistrictId` = `d`.`ID`)) left join `tblusers` `u` on(`f`.`ApprovedBy` = `u`.`ID`)) where `f`.`Deleted` = 0 order by `f`.`Status`,`f`.`RequisitionDate` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_supplier_payments`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_supplier_payments` AS select `h`.`ID` AS `ID`,`h`.`supplierId` AS `supplierId`,ucase(`s`.`supplierName`) AS `Supplier`,`h`.`invoiceNo` AS `invoiceNo`,date_format(`h`.`invoiceDate`,'%d/%m/%y') AS `invoiceDate`,`getinvoicebalance_supplier`(`h`.`ID`) AS `Balance`,`h`.`congregationId` AS `congregationId` from (`tblinvoice_header_suppliers` `h` join `tblsuppliers` `s` on(`h`.`supplierId` = `s`.`ID`)) where `h`.`deleted` = 0 having `getinvoicebalance_supplier`(`h`.`ID`) > 0 order by `h`.`duedate` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_supplier_with_bals`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_supplier_with_bals` AS select `h`.`ID` AS `ID`,`h`.`supplierId` AS `supplierId`,ucase(`s`.`supplierName`) AS `Supplier`,`h`.`invoiceNo` AS `invoiceNo`,date_format(`h`.`invoiceDate`,'%d/%m/%y') AS `invoiceDate`,`getinvoicebalance_supplier`(`h`.`ID`) AS `Balance`,`h`.`congregationId` AS `congregationId` from (`tblinvoice_header_suppliers` `h` join `tblsuppliers` `s` on(`h`.`supplierId` = `s`.`ID`)) having `getinvoicebalance_supplier`(`h`.`ID`) > 0 order by `h`.`duedate` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_supplierpayments`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_supplierpayments` AS select `p`.`paymentNo` AS `paymentNo`,`h`.`supplierId` AS `supplierId`,ucase(`s`.`supplierName`) AS `supplierName`,`p`.`paymentDate` AS `paymentDate`,sum(`p`.`amount`) AS `AmountPaid`,`h`.`congregationId` AS `congregationId` from ((`tblinvoice_payments_suppliers` `p` join `tblinvoice_header_suppliers` `h` on(`p`.`invoice_Id` = `h`.`ID`)) join `tblsuppliers` `s` on(`h`.`supplierId` = `s`.`ID`)) group by `p`.`paymentNo`,`s`.`supplierName`,`p`.`paymentDate` order by `p`.`paymentNo` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_supplierpayments2`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_supplierpayments2` AS select `p`.`ID` AS `ID`,`p`.`paymentNo2` AS `paymentNo`,`h`.`supplierId` AS `supplierId`,ucase(`s`.`supplierName`) AS `supplierName`,`p`.`paymentDate` AS `paymentDate`,`p`.`amount` AS `AmountPaid`,`h`.`congregationId` AS `congregationId` from ((`tblinvoice_payments_suppliers` `p` join `tblinvoice_header_suppliers` `h` on(`p`.`invoice_Id` = `h`.`ID`)) join `tblsuppliers` `s` on(`h`.`supplierId` = `s`.`ID`)) order by `p`.`paymentNo` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_suppliers`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_suppliers` AS select `tblsuppliers`.`ID` AS `ID`,ucase(`tblsuppliers`.`supplierName`) AS `supplierName`,`tblsuppliers`.`contact` AS `contact`,ucase(`tblsuppliers`.`contactPerson`) AS `contactPerson`,`fn_get_supplier_balance`(`tblsuppliers`.`ID`) AS `balance`,`tblsuppliers`.`congregationId` AS `congregationId` from `tblsuppliers` where `tblsuppliers`.`deleted` = 0 order by ucase(`tblsuppliers`.`supplierName`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_user_rights`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_user_rights` AS select `u`.`UserId` AS `UserId`,lcase(`f`.`FormName`) AS `FormName` from (`tbluserrights` `u` join `tblforms` `f` on(`u`.`FormId` = `f`.`ID`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_users`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_users` AS select `u`.`ID` AS `ID`,ucase(`u`.`UserID`) AS `userId`,ucase(`u`.`UserName`) AS `username`,ucase(`t`.`UserType`) AS `usertype`,if(`u`.`Active` = 1,'Active','Inactive') AS `status`,`u`.`contact` AS `contact`,ucase(`d`.`districtName`) AS `district`,`u`.`CongregationId` AS `CongregationId` from ((`tblusers` `u` join `tblusertype` `t` on(`u`.`UsertypeId` = `t`.`ID`)) left join `tbldistricts` `d` on(`u`.`districtId` = `d`.`ID`)) order by `u`.`ID` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vwcontributions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vwcontributions` AS select `cd`.`ID` AS `ID`,`cd`.`contributionDate` AS `contributionDate`,ucase(`ga`.`accountName`) AS `contType`,`cd`.`amount` AS `amount`,ucase(`cc`.`categoryName`) AS `categoryName`,if(`cd`.`category` = 1,ucase(`me`.`memberName`),if(`cd`.`category` = 2,ucase(`gs`.`groupName`),if(`cd`.`category` = 3,ucase(`ds`.`districtName`),ucase(`sv`.`serviceName`)))) AS `contributor` from (((((((`tblcontributions_details` `cd` join `tblglaccounts` `ga` on(`cd`.`contributionTypeId` = `ga`.`ID`)) join `tblcontributioncategories` `cc` on(`cd`.`category` = `cc`.`ID`)) join `tblcontributions_header` `ch` on(`cd`.`ID` = `ch`.`ID`)) left join `tblmember` `me` on(`cd`.`contributor` = `me`.`ID`)) left join `tblgroups` `gs` on(`cd`.`contributotGroup` = `gs`.`ID`)) left join `tbldistricts` `ds` on(`cd`.`contributotDistrict` = `ds`.`ID`)) left join `tblservices` `sv` on(`cd`.`contributotService` = `sv`.`ID`)) where `ch`.`Deleted` = 0 order by `cd`.`contributionDate` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vwglaccounts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vwglaccounts` AS select `g`.`ID` AS `ID`,ucase(`g`.`accountName`) AS `accountName`,`g`.`accountTypeId` AS `accountTypeId`,ucase(`a`.`accountType`) AS `accountType`,`g`.`active` AS `active`,if(`g`.`active` = 1,'Active','Inactive') AS `status`,ucase(`g`.`narration`) AS `narration` from (`tblglaccounts` `g` join `tblaccounttypes` `a` on(`g`.`accountTypeId` = `a`.`ID`)) where `g`.`deleted` = 0 order by `g`.`accountTypeId`,ucase(`g`.`accountName`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vwloadgroups`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vwloadgroups` AS select `tblgroups`.`ID` AS `ID`,ucase(`tblgroups`.`groupName`) AS `groupName`,`tblgroups`.`active` AS `active`,if(`tblgroups`.`active` = 1,'Active','Inactive') AS `groupStatus` from `tblgroups` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vwmemberreport`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vwmemberreport` AS select `m`.`memberId` AS `Member ID`,ucase(`m`.`memberName`) AS `Member Name`,`m`.`idNo` AS `ID No`,ucase(`g`.`gender`) AS `Gender`,`m`.`contact` AS `Contact`,ucase(`s`.`maritalStatus`) AS `Marital Status`,`m`.`marriageDate` AS `Marriage Date`,`m`.`registrationDate` AS `Registration Date`,if(`m`.`memberStatus` = 1,'ACTIVE',if(`m`.`memberStatus` = 2,'DORMANT','PASSED ON')) AS `Status`,if(`m`.`baptised` = 1,'YES',if(`m`.`baptised` is null,NULL,'NO')) AS `Baptised`,`m`.`baptisedDate` AS `Baptised Date`,if(`m`.`membershipStatus` = 1,'FULL',if(`m`.`membershipStatus` is null,NULL,'ADHERENT')) AS `Membership Status`,if(`m`.`confirmed` = 1,'YES',if(`m`.`confirmed` is null,NULL,'NO')) AS `Confirmed`,`m`.`confirmedDate` AS `Confirmed Date`,if(`m`.`commissioned` = 1,'YES',if(`m`.`commissioned` is null,NULL,'NO')) AS `Commissioned`,`m`.`commissionedDate` AS `Commissioned Date`,ucase(`d`.`districtName`) AS `District`,ucase(`p`.`positionName`) AS `Position`,ucase(`m`.`occupation`) AS `Occupation`,ucase(`m`.`residence`) AS `Residence`,`m`.`email` AS `Email` from ((((`tblmember` `m` join `tblgender` `g` on(`m`.`genderId` = `g`.`ID`)) left join `tblmaritaltatus` `s` on(`m`.`maritalStatusId` = `s`.`ID`)) left join `tbldistricts` `d` on(`m`.`districtId` = `d`.`ID`)) left join `tblpositions` `p` on(`m`.`positionId` = `p`.`ID`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vwmembers`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vwmembers` AS select `m`.`ID` AS `ID`,ucase(`m`.`memberId`) AS `memberId`,ucase(`m`.`memberName`) AS `memberName`,`m`.`idNo` AS `idNo`,`m`.`dob` AS `dob`,`m`.`genderId` AS `genderId`,ucase(`g`.`gender`) AS `gender`,`m`.`contact` AS `contact`,`m`.`maritalStatusId` AS `maritalStatusId`,`m`.`marriageType` AS `marriageType`,`m`.`marriageDate` AS `marriageDate`,`m`.`registrationDate` AS `registrationDate`,`m`.`memberStatus` AS `memberStatus`,`m`.`passedOn` AS `passedOn`,`m`.`baptised` AS `baptised`,`m`.`baptisedDate` AS `baptisedDate`,`m`.`membershipStatus` AS `membershipStatus`,`m`.`confirmed` AS `confirmed`,`m`.`confirmedDate` AS `confirmedDate`,`m`.`commissioned` AS `commissioned`,`m`.`commissionedDate` AS `commissionedDate`,`m`.`districtId` AS `districtId`,ucase(`d`.`districtName`) AS `district`,`m`.`positionId` AS `positionId`,ucase(`p`.`positionName`) AS `position`,ucase(`m`.`occupation`) AS `occupation`,`m`.`other` AS `other`,`m`.`email` AS `email`,`m`.`residence` AS `residence`,`m`.`congregationId` AS `congregationId` from (((`tblmember` `m` join `tblgender` `g` on(`m`.`genderId` = `g`.`ID`)) left join `tbldistricts` `d` on(`m`.`districtId` = `d`.`ID`)) left join `tblpositions` `p` on(`m`.`positionId` = `p`.`ID`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

