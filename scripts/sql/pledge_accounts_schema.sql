-- pledge_accounts_schema.sql (task 8.6b), idempotent, MariaDB 10.4/10.6.
-- Run before the 8.6b code and before fn_checkreferencedaccount_v3.sql.
-- The conversion guard refuses values that are not exact cents or exceed DECIMAL(18,2).
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_tmp_pledge_accounts_schema$$
CREATE PROCEDURE sp_tmp_pledge_accounts_schema()
BEGIN
 DECLARE n INT; DECLARE bad INT;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_header' AND COLUMN_NAME='IncomeAccountId';
 IF n=0 THEN ALTER TABLE tblpledges_header ADD IncomeAccountId INT NULL AFTER amountPledged; SELECT 'IncomeAccountId applied' result; ELSE SELECT 'IncomeAccountId skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_header' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='IncomeAccountId';
 IF n=0 THEN ALTER TABLE tblpledges_header ADD KEY IDX_Pledge_IncomeAccountId(IncomeAccountId); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_header' AND CONSTRAINT_NAME='FK_Pledge_IncomeAccount' AND CONSTRAINT_TYPE='FOREIGN KEY';
 IF n=0 THEN ALTER TABLE tblpledges_header ADD CONSTRAINT FK_Pledge_IncomeAccount FOREIGN KEY(IncomeAccountId) REFERENCES tblaccounttypes(ID); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_details' AND COLUMN_NAME='PaymentAccountId';
 IF n=0 THEN ALTER TABLE tblpledges_details ADD PaymentAccountId INT NULL AFTER bankId; SELECT 'PaymentAccountId applied' result; ELSE SELECT 'PaymentAccountId skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_details' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='PaymentAccountId';
 IF n=0 THEN ALTER TABLE tblpledges_details ADD KEY IDX_PledgeDetail_PaymentAccountId(PaymentAccountId); END IF;
 SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_details' AND CONSTRAINT_NAME='FK_PledgeDetail_PaymentAccount' AND CONSTRAINT_TYPE='FOREIGN KEY';
 IF n=0 THEN ALTER TABLE tblpledges_details ADD CONSTRAINT FK_PledgeDetail_PaymentAccount FOREIGN KEY(PaymentAccountId) REFERENCES tblaccounttypes(ID); END IF;
 SELECT COUNT(*) INTO bad FROM tblpledges_header WHERE amountPledged IS NULL OR ABS(amountPledged-ROUND(amountPledged,2))>.001 OR ABS(amountPledged)>=10000000000000000;
 SELECT COUNT(*)+bad INTO bad FROM tblpledges_details WHERE openingBal IS NULL OR amountPaid IS NULL OR balance IS NULL OR ABS(openingBal-ROUND(openingBal,2))>.001 OR ABS(amountPaid-ROUND(amountPaid,2))>.001 OR ABS(balance-ROUND(balance,2))>.001 OR ABS(openingBal)>=10000000000000000 OR ABS(amountPaid)>=10000000000000000 OR ABS(balance)>=10000000000000000;
 IF bad>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='pledge money conversion refused: values are not exact cents or exceed DECIMAL(18,2)'; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_header' AND COLUMN_NAME='amountPledged' AND DATA_TYPE<>'decimal';
 IF n=1 THEN ALTER TABLE tblpledges_header MODIFY amountPledged DECIMAL(18,2) NOT NULL; SELECT 'header money converted' result; ELSE SELECT 'header money skipped' result; END IF;
 SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblpledges_details' AND COLUMN_NAME IN('openingBal','amountPaid','balance') AND DATA_TYPE<>'decimal';
 IF n>0 THEN ALTER TABLE tblpledges_details MODIFY openingBal DECIMAL(18,2) NOT NULL,MODIFY amountPaid DECIMAL(18,2) NOT NULL,MODIFY balance DECIMAL(18,2) NOT NULL; SELECT 'detail money converted' result; ELSE SELECT 'detail money skipped' result; END IF;
END$$
CALL sp_tmp_pledge_accounts_schema()$$
DROP PROCEDURE sp_tmp_pledge_accounts_schema$$

CREATE OR REPLACE VIEW vw_pledgewithbalances AS
 SELECT h.ID,IF(h.category=1,UCASE(m.memberName),IF(h.category=2,UCASE(g.groupName),UCASE(t.districtName))) pledger,
 IF(h.category=1,'MEMBER',IF(h.category=2,'GROUP','DISTRICT')) category,FORMAT(h.amountPledged,2) amountPledged,
 FORMAT(COALESCE((SELECT SUM(d.amountPaid) FROM tblpledges_details d WHERE d.pledgeId=h.ID),0),2) amountPaid,
 FORMAT(h.amountPledged-COALESCE((SELECT SUM(d.amountPaid) FROM tblpledges_details d WHERE d.pledgeId=h.ID),0),2) balance,
 IF(h.category=1,m.contact,IF(h.category=2,(SELECT mm.contact FROM tblmember mm WHERE mm.ID=getChairmanId(h.pledgerGroup)),(SELECT u.contact FROM tblusers u WHERE u.districtId=h.pledgerDistrict LIMIT 1))) contact,
 h.deleted,h.congregationId FROM tblpledges_header h LEFT JOIN tblmember m ON h.pledgerMember=m.ID LEFT JOIN tblgroups g ON h.pledgerGroup=g.ID LEFT JOIN tbldistricts t ON h.pledgerDistrict=t.ID
 WHERE h.deleted=0 AND h.amountPledged>COALESCE((SELECT SUM(d.amountPaid) FROM tblpledges_details d WHERE d.pledgeId=h.ID),0)$$
CREATE OR REPLACE VIEW vw_loadpledgebalances AS
 SELECT h.ID,h.category,IF(h.category=1,UCASE(m.memberName),IF(h.category=2,UCASE(g.groupName),UCASE(t.districtName))) pledger,IF(h.category=1,'MEMBER',IF(h.category=2,'GROUP','DISTRICT')) cat,
 FORMAT(h.amountPledged,2) amountPledged,FORMAT(COALESCE((SELECT SUM(d.amountPaid) FROM tblpledges_details d WHERE d.pledgeId=h.ID),0),2) amountPaid,FORMAT(h.amountPledged-COALESCE((SELECT SUM(d.amountPaid) FROM tblpledges_details d WHERE d.pledgeId=h.ID),0),2) balance
 FROM tblpledges_header h LEFT JOIN tblmember m ON h.pledgerMember=m.ID LEFT JOIN tblgroups g ON h.pledgerGroup=g.ID LEFT JOIN tbldistricts t ON h.pledgerDistrict=t.ID
 WHERE h.deleted=0 AND h.amountPledged>COALESCE((SELECT SUM(d.amountPaid) FROM tblpledges_details d WHERE d.pledgeId=h.ID),0)$$
DROP FUNCTION IF EXISTS getTotalPledge$$
CREATE FUNCTION getTotalPledge(pid INT) RETURNS DECIMAL(18,2) READS SQL DATA
BEGIN DECLARE totalPaid DECIMAL(18,2);SELECT COALESCE(SUM(d.amountPaid),0) INTO totalPaid FROM tblpledges_header h LEFT JOIN tblpledges_details d ON d.pledgeId=h.ID WHERE h.ID=pid AND h.deleted=0;RETURN COALESCE(totalPaid,0);END$$
DROP PROCEDURE IF EXISTS sp_get_pledges$$
CREATE PROCEDURE sp_get_pledges(IN actions INT,startd DATE,endd DATE,cong INT)
BEGIN
 SELECT h.ID,h.pledgeDate,UCASE(IF(h.category=1,m.memberName,IF(h.category=2,g.groupName,t.districtName))) pledger,h.amountPledged,getamountpaid_pledge(h.ID) amountPaid,h.amountPledged-getamountpaid_pledge(h.ID) balance
 FROM tblpledges_header h LEFT JOIN tblmember m ON h.pledgerMember=m.ID LEFT JOIN tblgroups g ON h.pledgerGroup=g.ID LEFT JOIN tbldistricts t ON h.pledgerDistrict=t.ID
 WHERE h.deleted=0 AND h.pledgeDate BETWEEN startd AND endd AND h.congregationId=cong
 AND (actions=1 OR (actions=2 AND h.amountPledged-getamountpaid_pledge(h.ID)>0) OR (actions=3 AND h.amountPledged-getamountpaid_pledge(h.ID)=0));
END$$
DELIMITER ;
SELECT COUNT(*) legacy_pledges_with_payments_missing_income FROM tblpledges_header h WHERE h.IncomeAccountId IS NULL AND EXISTS(SELECT 1 FROM tblpledges_details d WHERE d.pledgeId=h.ID);
