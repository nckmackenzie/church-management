<aside class="main-sidebar sidebar-dark-navy elevation-4 custom-font">
        <!-- Brand Logo -->
        <a href="<?php echo URLROOT;?>/mains" class="brand-link custom-center">
          <span class="brand-text custom-brand text-teal">
            <?php echo strtoupper($_SESSION['congName']);?>
          </span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar text-sm ">
          <!-- Sidebar user panel (optional) -->
          <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
              <img
                src="<?php echo URLROOT;?>/dist/img/icons8_user.png"
                class="img-circle elevation-2"
                alt="User Image"
              />
            </div>
            <div class="info">
              <a href="#" class="d-block"><?php echo strtoupper($_SESSION['userName']);?></a>
            </div>
          </div>
          <!-- Sidebar Menu -->
         