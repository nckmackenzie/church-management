<?php
/**
 * Ledger Statement (task 9.3, decisions 141-142): one account (and, optionally, EVERY descendant) between two dates, from the
 * journal through FinancialReport::linesPage(): opening balance at the from date, paged and searchable lines with the true
 * running balance, totals, closing balance in the account's normal direction (Dr / Cr stated). Same closing balance as the
 * trial balance on the same basis.
 */
class LedgerStatementReport
{
    /**
     * from / to (strict Y-m-d, from <= to), subledgers=1, page / pageSize / search, format=xlsx. The account ID is validated by
     * the controller against the report-safe selector before this runs.
     */
    public static function serve(PDO $db, array $scope, string $entity, int $account): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        [$from, $to] = IncomeStatementReport::dates(['start' => $_GET['from'] ?? null, 'end' => $_GET['to'] ?? null]);
        $sub = ($_GET['subledgers'] ?? '') === '1';
        [$page, $size, $search] = LinesView::params($_GET);
        $period = ['kind' => 'range', 'from' => $from, 'to' => $to, 'direction' => 'normal'];
        $chart = FinancialReport::chart($db, $scope);
        $node = $chart['nodes'][$account] ?? null;
        if ($node === null || !$node['classified']) { IncomeStatementReport::fail('Invalid account'); }
        $title = FinancialReport::title($node['label']) . ($sub && !empty($chart['kids'][$account]) ? ' and its sub accounts' : '');

        if (IncomeStatementReport::wantsXlsx()) {
            $L = FinancialReport::linesPage($db, $scope, $account, $period, $sub, 1, 0, $search);
            $dir = $L['direction'] === 'CREDIT' ? 'credit' : 'debit';
            XlsxWriter::download('ledger-statement-' . $account . '-' . $from . '-to-' . $to . '.xlsx', 'Ledger statement',
                LinesView::sheetRows($L, [$entity, 'Ledger statement: ' . $title, 'Period ' . date('d-m-Y', strtotime($from)) . ' to ' . date('d-m-Y', strtotime($to)),
                                          'Balances are in the ' . $dir . ' direction of the account'], $sub), LinesView::widths($sub));
        }
        $L = FinancialReport::linesPage($db, $scope, $account, $period, $sub, $page, $size, $search);
        IncomeStatementReport::send(LinesView::shape($L) + [
            'entity' => $entity, 'name' => $title, 'from' => $from, 'to' => $to,
            'subledgers' => $sub, 'account' => $account,
            'directionNote' => $L['direction'] === 'CREDIT' ? 'Cr: a credit balance is positive for this account' : 'Dr: a debit balance is positive for this account',
        ]);
    }
}
