<?php
    include_once 'congregation-nav.php';

    $con = new Database;
    if((int)$_SESSION['userType'] > 2 && (int)$_SESSION['userType'] !== 6){
        $menuitems = getusermenuitems($con->dbh,(int)$_SESSION['userId'],!converttobool($_SESSION['isParish']));
    }
    $menuicons = [
        'master entry' => 'fa-cogs',
        'members' => 'fa-user-friends',
        'finance' => 'fa-money-check-alt',
        'member reports' => 'fa-file-alt',
        'finance reports' => 'fa-file-alt',
        'transactions' => 'fa-cog'
    ];
    if ($_SESSION['isParish'] == 1 && $_SESSION['userType'] <=2) {
        include_once 'admin-parish.php';
    }
    elseif ($_SESSION['isParish'] !=1 && $_SESSION['userType'] <= 2) {
        include_once 'admin-nav.php';
    }?>
    <?php if((int)$_SESSION['userType'] > 2 && (int)$_SESSION['userType'] !== 6) : ?>
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent"
                data-widget="treeview" role="menu" data-accordion="false">
                
                <?php foreach($menuitems as $menuitem) :?>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas <?php echo $menuicons[$menuitem];?>"></i>
                            <p class="custom-bold custom-font">
                            <?php echo strtoupper($menuitem) ;?>
                            <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview custom-font">
                            <?php $navitems = getmodulemenuitems($con->dbh,(int)$_SESSION['userId'],$menuitem,!converttobool($_SESSION['isParish'])) ;?>
                            <?php foreach($navitems as $navitem) : ?>
                                <li class="nav-item">
                                    <a href="<?php echo URLROOT;?>/<?php echo $navitem->Path;?>" class="nav-link">
                                        <p><?php echo ucwords($navitem->FormName);?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach;?>
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
    <?php if($_SESSION['isParish'] == 1 && $_SESSION['userType'] == 6) : ?>
        <?php include_once('finance-parish.php'); ?>
    <?php endif; ?>    
    <?php if($_SESSION['isParish'] != 1 && $_SESSION['userType'] == 6) : ?>
        <?php include_once('finance-nav.php'); ?>
    <?php endif; ?>