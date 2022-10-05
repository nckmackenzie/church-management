<nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
        data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-users-cog"></i>
                <p class="custom-bold custom-font">
                ADMIN
                <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview custom-font">
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/users/all" class="nav-link">
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/users/rights" class="nav-link">
                        <p>Users Rights</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/users/clonerights" class="nav-link">
                        <p>Clone Users Rights</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/users/activitylog" class="nav-link">
                        <p>Activity Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/services/service_info" class="nav-link">
                        <p>Service Info</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/bookings" class="nav-link">
                        <p>Seat Booking</p>
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-cogs"></i>
                <p class="custom-bold custom-font">
                MASTER ENTRY
                <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview custom-font">
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/congregations/edit/<?php echo $_SESSION['congId'];?>" class="nav-link">
                        <p>Congregation Info</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/districts" class="nav-link">
                        <p>Districts</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/groups" class="nav-link">
                        <p>Groups</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/services" class="nav-link">
                        <p>Services</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/customers" class="nav-link">
                        <p>Customers</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/products" class="nav-link">
                        <p>Products</p>
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
              <i class="nav-icon fas fa-user-friends"></i>
              <p class="custom-bold custom-font">
              MEMBERS
              <i class="right fas fa-angle-left"></i>
              </p>
          </a>
          <ul class="nav nav-treeview custom-font">
            <li class="nav-item">
                <a href="<?php echo URLROOT;?>/members" class="nav-link">
                    <p>Members</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo URLROOT;?>/members/family" class="nav-link">
                    <p>Member Family</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo URLROOT;?>/members/change_district" class="nav-link">
                    <p>Change District</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo URLROOT;?>/officials" class="nav-link">
                    <p>Group Officials</p>
                </a>
            </li>
          </ul>
        </li>
        <li class="nav-item custom-font">
            <a href="<?php echo URLROOT;?>/plans" class="nav-link">
                <i class="nav-icon fas fa-calendar"></i>
                <p>
                    WORK PLAN
                </p>
            </a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-money-check-alt"></i>
                <p class="custom-bold custom-font">
                FINANCE
                <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview custom-font">
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/banks" class="nav-link">
                        <p>Banks</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/contributions" class="nav-link">
                        <p>Receipts</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/mmfreceipts" class="nav-link">
                        <p>MMF Receipts</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/groupfunds" class="nav-link">
                        <p>Group funds requisition</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/groupfunds/approvals" class="nav-link">
                        <p>Group funds approval</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/expenses" class="nav-link">
                        <p>Expenses</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/pledges" class="nav-link">
                        <p>Pledges</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/cashreceipts" class="nav-link">
                        <p>Petty Cash Receipt</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/churchbudgets" class="nav-link">
                        <p>Church Budget</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/groupbudgets" class="nav-link">
                        <p>Group Budget</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/journals" class="nav-link">
                        <p>Journal Entry</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/invoices" class="nav-link">
                        <p>Customer Invoices</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/supplierinvoices" class="nav-link">
                        <p>Supplier Invoices</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/bankbalances" class="nav-link">
                        <p>Bank Balances</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/bankpostings" class="nav-link">
                        <p>Post Bankings</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/clearbankings" class="nav-link">
                        <p>Clear Bankings</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/bankreconcilliations" class="nav-link">
                        <p>Bank Reconcilliation</p>
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-file-alt"></i>
                <p class="custom-bold custom-font">
                MEMBER REPORTS
                <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview custom-font">
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/members" class="nav-link">
                        <p>Member Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/transfered" class="nav-link">
                        <p>Transfered Report</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/membershipstatus" class="nav-link">
                        <p>By Membership Status</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/residenceoccupation" class="nav-link">
                        <p>Residence/Occupation Report</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/family" class="nav-link">
                        <p>Member Family Report</p>
                    </a>
                </li>
                <!--<li class="nav-item">-->
                <!--    <a href="<?php echo URLROOT;?>/member_reports/group_membership" class="nav-link">-->
                <!--        <p>Group Membership Report</p>-->
                <!--    </a>-->
                <!--</li>-->
                <!--<li class="nav-item">-->
                <!--    <a href="<?php echo URLROOT;?>/change-district" class="nav-link">-->
                <!--        <p>Seat Booking Report</p>-->
                <!--    </a>-->
                <!--</li>-->
            </ul>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="nav-icon fas fa-file-alt"></i>
                <p class="custom-bold custom-font">
                FINANCE REPORTS
                <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview custom-font">
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/contributions" class="nav-link">
                        <p>Receipts Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/expenses" class="nav-link">
                        <p>Expenses Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/pledges" class="nav-link">
                        <p>Pledge Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/pettycash" class="nav-link">
                        <p>Petty Cash Utilization</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/budgetvsexpense" class="nav-link">
                        <p>Budget Vs Expense Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/banking" class="nav-link">
                        <p>Banking Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/incomestatement" class="nav-link">
                        <p>Income Statement</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/trialbalance" class="nav-link">
                        <p>Trial Balance</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo URLROOT;?>/reports/balancesheet" class="nav-link">
                        <p>Balance Sheet</p>
                    </a>
                </li>
            </ul>
        </li>
        <li class="nav-item custom-font">
            <a href="<?php echo URLROOT;?>/users/change_password" class="nav-link">
                <i class="nav-icon fas fa-unlock-alt"></i>
                <p>
                    Change Password
                </p>
            </a>
        </li>
    </ul>
</nav>
<!-- /.sidebar-menu -->
</div>
<!-- /.sidebar -->
</aside>