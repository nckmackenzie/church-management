<!-- Admin -->
<?php
      try{
        $con=new PDO('mysql:host=localhost;dbname='.DB_NAME.'',DB_USER,DB_PASS);
        $con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      }catch(PDOException $err){
        echo $err->getmessage();
      }
    include_once 'congregation-nav.php';
    if ($_SESSION['isParish'] == 1 && $_SESSION['userType'] <=2) {
        include_once 'admin-parish.php';
    }
    elseif ($_SESSION['isParish'] !=1 && $_SESSION['userType'] <= 2) {
        include_once 'admin-nav.php';
    }?>
    <?php if($_SESSION['isParish'] != 1 && $_SESSION['userType'] > 2 && $_SESSION['userType'] != 6) : ?>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu" data-accordion="false">
                <?php include_once 'dbcon.php'; ?>
                <?php if(in_array('Members',GetMenu($con))) :?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-friends"></i>
                            <p class="custom-bold custom-font">
                            MEMBERS
                            <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview custom-font">
                            <?php foreach(GetMembersMenu($con,"Members") as $member) : ?>
                                <li class="nav-item">
                                    <a href="<?php echo URLROOT;?>/<?php echo $member->Path;?>" class="nav-link">
                                        <p><?php echo $member->FormName;?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif;?>
                <?php if(in_array('Finance',GetMenu($con))) :?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-money-check-alt"></i>
                            <p class="custom-bold custom-font">
                            FINANCE
                            <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview custom-font">
                            <?php foreach(GetMembersMenu($con,"Finance") as $member) : ?>
                                <li class="nav-item">
                                    <a href="<?php echo URLROOT;?>/<?php echo $member->Path;?>" class="nav-link">
                                        <p><?php echo $member->FormName;?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif;?>
                <?php if(in_array('Member Reports',GetMenu($con))) :?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p class="custom-bold custom-font">
                            MEMBER REPORTS
                            <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview custom-font">
                            <?php foreach(GetMembersMenu($con,"Member Reports") as $member) : ?>
                                <li class="nav-item">
                                    <a href="<?php echo URLROOT;?>/<?php echo $member->Path;?>" class="nav-link">
                                        <p><?php echo $member->FormName;?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
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
                <?php endif;?>
            </ul>
        </nav><!-- /.sidebar-menu -->
        </div><!-- /.sidebar -->    
        </aside>                    
    <?php endif; ?>
    <!-- Parish Nav -->
    <?php if($_SESSION['isParish'] == 1 && $_SESSION['userType'] > 2 && $_SESSION['userType'] != 6) : ?>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu" data-accordion="false">
                <?php include_once 'dbcon.php'; ?>
                <?php if(in_array('Transactions',GetMenu($con))) :?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-user-friends"></i>
                            <p class="custom-bold custom-font">
                            TRANSACTIONS
                            <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview custom-font">
                            <?php foreach(GetMembersMenu($con,"Transactions") as $member) : ?>
                                <li class="nav-item">
                                    <a href="<?php echo URLROOT;?>/<?php echo $member->Path;?>" class="nav-link">
                                        <p><?php echo $member->FormName;?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif;?>
                <?php if(in_array('Finance Reports',GetMenu($con))) :?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-money-check-alt"></i>
                            <p class="custom-bold custom-font">
                            FINANCE REPORTS
                            <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview custom-font">
                            <?php foreach(GetMembersMenu($con,"Finance Reports") as $member) : ?>
                                <li class="nav-item">
                                    <a href="<?php echo URLROOT;?>/<?php echo $member->Path;?>" class="nav-link">
                                        <p><?php echo $member->FormName;?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
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
                <?php endif;?>
            </ul>
        </nav><!-- /.sidebar-menu -->
        </div><!-- /.sidebar -->    
        </aside>                    
    <?php endif; ?>
    <?php if($_SESSION['isParish'] == 1 && $_SESSION['userType'] == 6) : ?>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cog"></i>
                        <p class="custom-bold custom-font">
                        TRANSACTIONS
                        <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview custom-font">
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/years" class="nav-link">
                                <p>Financial Years</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/customers" class="nav-link">
                                <p>Customers</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/banks" class="nav-link">
                                <p>Banks</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/contributions" class="nav-link">
                                <p>Contributions</p>
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
                            <a href="<?php echo URLROOT;?>/cashreceipts" class="nav-link">
                                <p>Petty Cash Receipt</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/pledges" class="nav-link">
                                <p>Pledges</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/journals" class="nav-link">
                                <p>Journal Entry</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/invoices" class="nav-link">
                                <p>Invoices</p>
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
                            <a href="<?php echo URLROOT;?>/deposits" class="nav-link">
                                <p>Deposits </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/payments" class="nav-link">
                                <p>Payments</p>
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
                        FINANCE REPORTS
                        <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview custom-font">
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/contributions" class="nav-link">
                                <p>Contributions Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/expenses" class="nav-link">
                                <p>Expenses Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/pettycash" class="nav-link">
                                <p>Petty Cash Utilization</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/budgetvsexpense" class="nav-link">
                                <p>Budget Vs Expense Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/invoices" class="nav-link">
                                <p>Invoice Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/incomestatement" class="nav-link">
                                <p>Income Statement</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/trialbalance" class="nav-link">
                                <p>Trial Balance</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/parishreports/balancesheet" class="nav-link">
                                <p>Balance Sheet</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav><!-- /.sidebar-menu -->
    </div><!-- /.sidebar -->
    </aside>
    <?php endif; ?>    
    <?php if($_SESSION['isParish'] != 1 && $_SESSION['userType'] == 6) : ?>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu" data-accordion="false">
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
                            <p>Contributions</p>
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
                        <a href="<?php echo URLROOT;?>/deposits" class="nav-link">
                            <p>Deposits </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo URLROOT;?>/payments" class="nav-link">
                            <p>Payments</p>
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
                        FINANCE REPORTS
                        <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview custom-font">
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/contributions" class="nav-link">
                                <p>Contributions Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/expenses" class="nav-link">
                                <p>Expenses Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/pettycash" class="nav-link">
                                <p>Petty Cash Utilization</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/pledges" class="nav-link">
                                <p>Pledge Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/budgetvsexpense" class="nav-link">
                                <p>Budget Vs Expense Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo URLROOT;?>/reports/invoicereports" class="nav-link">
                                <p>Invoice Reports</p>
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
        </nav><!-- /.sidebar-menu -->
    </div><!-- /.sidebar -->
    </aside>
    <?php endif; ?>