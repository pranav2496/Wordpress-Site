<?php
$loaders = $this->get_setting('loaders');
?><div id='UseyourDrive'>
  <div class='UseyourDrive list-container noaccess'>
    <img src="<?php echo $loaders['protected']; ?>" data-src-retina="<?php echo $loaders['protected']; ?>">
    <p><?php echo __("Your account doesn't have the right permissions to use this", 'useyourdrive') . ". " . __("Contact the webmaster if you would like to have access", 'useyourdrive'); ?>.</p>
  </div>
</div>