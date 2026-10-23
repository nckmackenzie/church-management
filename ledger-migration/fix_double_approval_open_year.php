<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
/**
 * Repair the doubled fund approval of requisition no 199 (dev: requisition ID 201, entry 21788, tblledger 26067-26070).
 * OPEN financial year (2025-2026). Written in task 8.9 STEP 5; NOT run by the task.
 *
 *   php ledger-migration/fix_double_approval_open_year.php --dry-run
 *   php ledger-migration/fix_double_approval_open_year.php --commit
 *   (--config=PATH for a config.php elsewhere)
 *
 * Looks the requisition up by content (congregation 11, req no 199, group, approved 13,060.00), checks every row, removes
 * lines 3-4 of its entry and the repeated tblledger rows, verifies 2 lines remain. Stops without a change on any surprise.
 * Refuses if the entry is in a closed year (then use fix_double_approval_closed_year.php). Details: _doubled_approval.php.
 * Requisition 201 has an approved expense (voucher paid from 121, 13,060): the approval amount does not change, so it
 * stays valid.
 */
require __DIR__ . '/_doubled_approval.php';

exit(fixDoubledApproval([
    'label'  => 'Requisition no 199 (dev ID 201): remove the doubled approval lines',
    'file'   => 'fix_double_approval_open_year',
    'cong'   => 11,
    'reqNo'  => 199,
    'type'   => 'group',
    'amount' => '13060.00',
    'year'   => 'open',
    'bank'   => false,
], $argv));
