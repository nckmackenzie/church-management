<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
/**
 * *** DIRECT SQL INTO A CLOSED FINANCIAL YEAR - REQUIRES SIGN-OFF BEFORE --commit ***
 *
 * Repair the doubled fund approval of requisition no 57 (dev: requisition ID 58, entry 13572, tblledger 7880-7883,
 * bank rows 728 and 729). CLOSED financial year (2023-2024). Written in task 8.9 STEP 5; NOT run by the task.
 * It bypasses the year lock on purpose (the Ledger refuses edits in a closed year), so the person who owns the closed
 * year must approve it first. It changes: lines 3-4 of the entry (deleted), the repeated tblledger rows (deleted = 1) and
 * the duplicate bank row 729 (deleted = 1; 6,875 too much on bank 105 and in its reconciliation).
 *
 *   php ledger-migration/fix_double_approval_closed_year.php --dry-run
 *   php ledger-migration/fix_double_approval_closed_year.php --commit --i-have-sign-off
 *   (--config=PATH for a config.php elsewhere)
 *
 * Looks the requisition up by content (congregation 11, req no 57, group, approved 6,875.00), checks every row (2 identical
 * bank rows, amounts equal to the approved amount) and stops without a change on any surprise. The removed rows are saved to
 * ledger-migration/logs/*_undo.json. Check afterwards that the bank reconciliation of bank 105 for 2024-03 still agrees
 * with the statement: the duplicate row may already have been matched. Details: _doubled_approval.php.
 */
require __DIR__ . '/_doubled_approval.php';

exit(fixDoubledApproval([
    'label'        => 'Requisition no 57 (dev ID 58): remove the doubled approval lines and duplicate bank row (CLOSED YEAR, direct SQL)',
    'file'         => 'fix_double_approval_closed_year',
    'cong'         => 11,
    'reqNo'        => 57,
    'type'         => 'group',
    'amount'       => '6875.00',
    'year'         => 'closed',
    'bank'         => true,
    'needsSignOff' => true,
], $argv));
