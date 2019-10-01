<div class="useyourdrive admin-settings">

  <div class="useyourdrive-header">
    <div class="useyourdrive-logo"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/logo64x64.png" height="64" width="64"/></div>
    <div class="useyourdrive-title"><?php _e('File Browser', 'useyourdrive'); ?></div>
  </div>

  <div class="useyourdrive-panel useyourdrive-panel-full">
    <?php
    $processor = $this->get_processor();
    $params = array('mode' => 'files',
        'dir' => 'drive',
        'viewrole' => 'all',
        'downloadrole' => 'all',
        'uploadrole' => 'all',
        'upload' => '1',
        'rename' => '1',
        'delete' => '1',
        'addfolder' => '1',
        'edit' => '1',
        'candownloadzip' => '1',
        'showsharelink' => '1',
        'searchcontents' => '1',
        'editdescription' => '1');

    $user_folder_backend = apply_filters('useyourdrive_use_user_folder_backend', $processor->get_setting('userfolder_backend'));

    if ($user_folder_backend !== 'No') {
        $params['userfolders'] = $user_folder_backend;

        $private_root_folder = $processor->get_setting('userfolder_backend_auto_root');
        if ($user_folder_backend === 'auto' && !empty($private_root_folder) && isset($private_root_folder['id'])) {
            $params['dir'] = $private_root_folder['id'];

            if (!isset($private_root_folder['view_roles'])) {
                $private_root_folder['view_roles'] = array('none');
            }
            $params['viewuserfoldersrole'] = implode('|', $private_root_folder['view_roles']);
        }
    }

    echo $processor->create_from_shortcode($params);
    ?>
  </div>
</div>