<?php
$settings = (array) get_option('use_your_drive_settings');

if (
        !(\TheLion\UseyourDrive\Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) &&
        !(\TheLion\UseyourDrive\Helpers::check_user_role($this->settings['permissions_add_links'])) &&
        !(\TheLion\UseyourDrive\Helpers::check_user_role($this->settings['permissions_add_embedded']))
) {
    die();
}

$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'default';
$standalone = isset($_REQUEST['standaloneshortcodebuilder']);

function wp_roles_checkbox($name, $selected = array()) {
    global $wp_roles;
    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }

    $roles = $wp_roles->get_names();


    foreach ($roles as $role_value => $role_name) {
        if (in_array($role_value, $selected) || $selected[0] == 'all') {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }
        echo '<div class="useyourdrive-option-checkbox">';
        echo '<input class="simple" type="checkbox" name="' . $name . '[]" value="' . $role_value . '" ' . $checked . '>';
        echo '<label for="userfolders_method_auto1" class="useyourdrive-option-checkbox-label">' . $role_name . '</label>';
        echo '</div>';
    }
    if (in_array('guest', $selected) || $selected[0] == 'all') {
        $checked = 'checked="checked"';
    } else {
        $checked = '';
    }
    echo '<div class="useyourdrive-option-checkbox">';
    echo '<input class="simple" type="checkbox" name="' . $name . '[]" value="guest" ' . $checked . '>';
    echo '<label for="userfolders_method_auto1" class="useyourdrive-option-checkbox-label">' . __('Guest', 'useyourdrive') . '</label>';
    echo '</div>';
}

$this->load_scripts();
$this->load_styles();
$this->load_custom_css();

function UseyourDrive_remove_all_scripts() {
    global $wp_scripts;
    $wp_scripts->queue = array();

    wp_enqueue_script('jquery-effects-fade');
    wp_enqueue_script('jquery');
    wp_enqueue_script('UseyourDrive');
    wp_enqueue_script('UseyourDrive.tinymce');
}

function UseyourDrive_remove_all_styles() {
    global $wp_styles;
    $wp_styles->queue = array();
    wp_enqueue_style('qtip');
    wp_enqueue_style('UseyourDrive.tinymce');
    wp_enqueue_style('UseyourDrive');
    wp_enqueue_style('Awesome-Font-5-css');
}

add_action('wp_print_scripts', 'UseyourDrive_remove_all_scripts', 1000);
add_action('wp_print_styles', 'UseyourDrive_remove_all_styles', 1000);

/* Count number of openings for rating dialog */
$counter = get_option('use_your_drive_shortcode_opened', 0) + 1;
update_option('use_your_drive_shortcode_opened', $counter);

/* Initialize shortcode vars */
$mode = (isset($_REQUEST['mode'])) ? $_REQUEST['mode'] : 'files';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>
      <?php
      if ($type === 'default') {
          $title = __('Shortcode Builder', 'useyourdrive');
          $mcepopup = 'shortcode';
      } else if ($type === 'links') {
          $title = __('Insert direct Links', 'useyourdrive');
          $mcepopup = 'links';
      } else if ($type === 'embedded') {
          $title = __('Embed files', 'useyourdrive');
          $mcepopup = 'embedded';
      } else if ($type === 'gravityforms') {
          $title = __('Shortcode Builder', 'useyourdrive');
          $mcepopup = 'shortcode';
      } else if ($type === 'woocommerce') {
          $title = __('Shortcode Builder', 'useyourdrive');
          $mcepopup = 'shortcode';
      } else if ($type === 'contactforms7') {
          $title = __('Shortcode Builder', 'useyourdrive');
          $mcepopup = 'shortcode';
      }
      ?></title>
    <?php if ($type !== 'gravityforms' && $type !== 'contactforms7' && $standalone === false) { ?>
        <script type="text/javascript" src="<?php echo site_url(); ?>/wp-includes/js/tinymce/tiny_mce_popup.js"></script>
    <?php } ?>

    <?php wp_print_scripts(); ?>
    <?php wp_print_styles(); ?>
  </head>

  <body class="useyourdrive" data-mode="<?php echo $mode; ?>">
    <?php $this->ask_for_review(); ?>

    <form action="#">

      <div class="wrap">
        <div class="useyourdrive-header">
          <div class="useyourdrive-logo"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/logo64x64.png" height="64" width="64"/></div>
          <div class="useyourdrive-form-buttons">
            <?php if ($type === 'default') { ?>
                <?php if ($standalone) { ?>
                    <div id="get_shortcode" class="simple-button default get_shortcode" name="get_shortcode" title="<?php _e("Get raw Shortcode", 'useyourdrive'); ?>"><?php _e("Create Shortcode", 'useyourdrive'); ?><i class="fas fa-code" aria-hidden="true"></i></div>
                <?php } else { ?>
                    <div id="get_shortcode" class="simple-button default get_shortcode" name="get_shortcode" title="<?php _e("Get raw Shortcode", 'useyourdrive'); ?>"><?php _e("Raw", 'useyourdrive'); ?><i class="fas fa-code" aria-hidden="true"></i></div>
                    <div id="doinsert"  class="simple-button default insert_shortcode" name="insert"><?php _e("Insert Shortcode", 'useyourdrive'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>                    
                <?php } ?>
            <?php } elseif ($type === 'links') { ?>
                <div id="doinsert" class="simple-button default insert_links" name="insert"  ><?php _e("Insert Links", 'useyourdrive'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>
            <?php } elseif ($type === 'embedded') { ?>
                <div id="doinsert" class="simple-button default insert_embedded" name="insert" ><?php _e("Embed Files", 'useyourdrive'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>
            <?php } elseif ($type === 'gravityforms') { ?>
                <div id="doinsert" class="simple-button default insert_shortcode_gf" name="insert"><?php _e("Insert Shortcode", 'useyourdrive'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>
            <?php } elseif ($type === 'woocommerce') { ?>
                <div id="doinsert" class="simple-button default insert_shortcode_woocommerce" name="insert"><?php _e("Insert Shortcode", 'useyourdrive'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>
            <?php } elseif ($type === 'contactforms7') { ?>
                <div id="doinsert" class="simple-button default insert_shortcode_cf" name="insert"><?php _e("Insert Shortcode", 'useyourdrive'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>
            <?php } ?>
          </div>

          <div class="useyourdrive-title"><?php echo $title; ?></div>

        </div>
        <?php
        if ($type === 'links' || $type === 'embedded') {
            echo '<div class="useyourdrive-panel useyourdrive-panel-full">';
            if ($type === 'embedded') {
                echo "<p>" . __('Please note that the embedded files need to be public (with link)', 'useyourdrive') . "</p>";
            }

            $rootfolder = $this->get_processor()->get_client()->get_root_folder();

            $atts = array(
                'dir' => $rootfolder->get_id(),
                'mode' => 'files',
                'showfiles' => '1',
                'upload' => '0',
                'delete' => '0',
                'rename' => '0',
                'addfolder' => '0',
                'showcolumnnames' => '0',
                'viewrole' => 'all',
                'candownloadzip' => '0',
                'showsharelink' => '0',
                'previewinline' => '0',
                'mcepopup' => $mcepopup,
                'includeext' => '*',
                '_random' => 'embed'
            );

            $user_folder_backend = apply_filters('useyourdrive_use_user_folder_backend', $this->settings['userfolder_backend']);

            if ($user_folder_backend !== 'No') {
                $atts['userfolders'] = $user_folder_backend;

                $private_root_folder = $this->settings['userfolder_backend_auto_root'];
                if ($user_folder_backend === 'auto' && !empty($private_root_folder) && isset($private_root_folder['id'])) {
                    $atts['dir'] = $private_root_folder['id'];

                    if (!isset($private_root_folder['view_roles'])) {
                        $private_root_folder['view_roles'] = array('none');
                    }
                    $atts['viewuserfoldersrole'] = implode('|', $private_root_folder['view_roles']);
                }
            }

            echo $this->create_template($atts);
            echo '</div>';
            ?>
            <?php
        } else {
            ?>

            <div id="" class="useyourdrive-panel useyourdrive-panel-left">
              <div class="useyourdrive-nav-header"><?php _e('Shortcode Settings', 'useyourdrive'); ?></div>
              <ul class="useyourdrive-nav-tabs">
                <li id="settings_general_tab" data-tab="settings_general" class="current"><a><span><?php _e('General', 'useyourdrive'); ?></span></a></li>
                <li id="settings_folder_tab" data-tab="settings_folders"><a><span><?php _e('Folders', 'useyourdrive'); ?></span></a></li>
                <li id="settings_mediafiles_tab" data-tab="settings_mediafiles"><a><span><?php _e('Media Files', 'useyourdrive'); ?></span></a></li>
                <li id="settings_layout_tab" data-tab="settings_layout"><a><span><?php _e('Layout', 'useyourdrive'); ?></span></a></li>
                <li id="settings_sorting_tab" data-tab="settings_sorting"><a><span><?php _e('Sorting', 'useyourdrive'); ?></span></a></li>
                <li id="settings_advanced_tab" data-tab="settings_advanced"><a><span><?php _e('Advanced', 'useyourdrive'); ?></span></a></li>
                <li id="settings_exclusions_tab" data-tab="settings_exclusions"><a><span><?php _e('Exclusions', 'useyourdrive'); ?></span></a></li>
                <li id="settings_upload_tab" data-tab="settings_upload"><a><span><?php _e('Upload Box', 'useyourdrive'); ?></span></a></li>
                <li id="settings_notifications_tab" data-tab="settings_notifications"><a><span><?php _e('Notifications', 'useyourdrive'); ?></span></a></li>
                <li id="settings_manipulation_tab" data-tab="settings_manipulation"><a><span><?php _e('File Manipulation', 'useyourdrive'); ?></span></a></li>
                <li id="settings_permissions_tab" data-tab="settings_permissions" class=""><a><span><?php _e('User Permissions', 'useyourdrive'); ?></span></a></li>
              </ul>
            </div>

            <div class="useyourdrive-panel useyourdrive-panel-right">

              <!-- General Tab -->
              <div id="settings_general" class="useyourdrive-tab-panel current">

                <div class="useyourdrive-tab-panel-header"><?php _e('General', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Plugin Mode', 'useyourdrive'); ?></div>
                <div class="useyourdrive-option-description"><?php _e('Select how you want to use Use-your-Drive in your post or page', 'useyourdrive'); ?>:</div>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="files" name="mode" <?php echo (($mode === 'files') ? 'checked="checked"' : ''); ?> value="files" class="mode"/>
                  <label for="files" class="useyourdrive-option-radio-label"><?php _e('File browser', 'useyourdrive'); ?></label>
                </div>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="upload" name="mode" <?php echo (($mode === 'upload') ? 'checked="checked"' : ''); ?> value="upload" class="mode"/>
                  <label for="upload" class="useyourdrive-option-radio-label"><?php _e('Upload Box', 'useyourdrive'); ?></label>
                </div>
                <?php if ($type !== 'gravityforms' && $type !== 'contactforms7') { ?>
                    <div class="useyourdrive-option-radio">
                      <input type="radio" id="gallery" name="mode" <?php echo (($mode === 'gallery') ? 'checked="checked"' : ''); ?> value="gallery" class="mode"/>
                      <label for="gallery" class="useyourdrive-option-radio-label"><?php _e('Photo gallery', 'useyourdrive'); ?></label>
                    </div>
                    <div class="useyourdrive-option-radio">
                      <input type="radio" id="audio" name="mode" <?php echo (($mode === 'audio') ? 'checked="checked"' : ''); ?> value="audio" class="mode"/>
                      <label for="audio" class="useyourdrive-option-radio-label"><?php _e('Audio player', 'useyourdrive'); ?></label>
                    </div>
                    <div class="useyourdrive-option-radio">
                      <input type="radio" id="video" name="mode" <?php echo (($mode === 'video') ? 'checked="checked"' : ''); ?> value="video" class="mode"/>
                      <label for="video" class="useyourdrive-option-radio-label"><?php _e('Video player', 'useyourdrive'); ?></label>
                    </div>
                    <div class="useyourdrive-option-radio">
                      <input type="radio" id="search" name="mode" <?php echo (($mode === 'search') ? 'checked="checked"' : ''); ?> value="search" class="mode"/>
                      <label for="search" class="useyourdrive-option-radio-label"><?php _e('Search Box', 'useyourdrive'); ?></label>
                    </div>
                    <?php
                } else {
                    ?>
                    <br/>
                    <div class="uyd-updated">
                      <i><strong>TIP</strong>: <?php _e("Don't forget to check the Upload Permissions on the User Permissions tab", 'useyourdrive'); ?>. <?php _e("By default, only logged-in users can upload files", 'useyourdrive'); ?>.</i>
                    </div>
                    <?php
                }
                ?>

              </div>
              <!-- End General Tab -->
              <!-- User Folders Tab -->
              <div id="settings_folders" class="useyourdrive-tab-panel">

                <div class="useyourdrive-tab-panel-header"><?php _e('Folders', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Select start Folder', 'useyourdrive'); ?></div>
                <div class="useyourdrive-option-description"><?php _e('Select which folder should be used as starting point, or in case the Smart Client Area is enabled should be used for the Private Folders', 'useyourdrive'); ?>. <?php _e('Users will not be able to navigate outside this folder', 'useyourdrive'); ?>.</div>
                <div class="root-folder">
                  <?php
                  $rootfolder = $this->get_processor()->get_client()->get_root_folder();

                  $atts = array(
                      'dir' => $rootfolder->get_id(),
                      'mode' => 'files',
                      'maxheight' => '300px',
                      'filelayout' => 'list',
                      'showfiles' => '1',
                      'filesize' => '0',
                      'filedate' => '0',
                      'upload' => '0',
                      'delete' => '0',
                      'rename' => '0',
                      'addfolder' => '0',
                      'showbreadcrumb' => '1',
                      'showcolumnnames' => '0',
                      'search' => '0',
                      'roottext' => '',
                      'viewrole' => 'all',
                      'downloadrole' => 'none',
                      'candownloadzip' => '0',
                      'showsharelink' => '0',
                      'previewinline' => '0',
                      'mcepopup' => $mcepopup
                  );

                  if (isset($_REQUEST['dir'])) {
                      $atts['startid'] = $_REQUEST['dir'];
                  }

                  $user_folder_backend = apply_filters('useyourdrive_use_user_folder_backend', $this->settings['userfolder_backend']);

                  if ($user_folder_backend !== 'No') {
                      $atts['userfolders'] = $user_folder_backend;

                      $private_root_folder = $this->settings['userfolder_backend_auto_root'];
                      if ($user_folder_backend === 'auto' && !empty($private_root_folder) && isset($private_root_folder['id'])) {
                          $atts['dir'] = $private_root_folder['id'];

                          if (!isset($private_root_folder['view_roles'])) {
                              $private_root_folder['view_roles'] = array('none');
                          }
                          $atts['viewuserfoldersrole'] = implode('|', $private_root_folder['view_roles']);
                      }
                  }

                  echo $this->create_template($atts);
                  ?>
                </div>

                <br/>
                <div class="useyourdrive-tab-panel-header"><?php _e('Smart Client Area', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Use Private Folders', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_linkedfolders" id="UseyourDrive_linkedfolders" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['userfolders'])) ? 'checked="checked"' : ''; ?> data-div-toggle='option-userfolders'/>
                    <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_linkedfolders"></label>
                  </div>
                </div>

                <div class="useyourdrive-option-description">
                  <?php echo sprintf(__('The plugin can easily and securily share documents on your %s with your users/clients', 'useyourdrive'), 'useyourdrive'); ?>. 
                  <?php _e('This allows your clients to preview, download and manage their documents in their own private folder', 'useyourdrive'); ?>.
                  <?php echo sprintf(__('Specific permissions can always be set via %s', 'useyourdrive'), '<a href="#" onclick="jQuery(\'li[data-tab=settings_permissions]\').trigger(\'click\')">' . __('User Permissions', 'useyourdrive') . '</a>'); ?>. 

                  <?php _e('The Smart Client Area can be useful in some situations, for example', 'useyourdrive'); ?>:
                  <ul>
                    <li><?php _e('You want to share documents with your clients privately', 'useyourdrive'); ?></li>
                    <li><?php _e('You want your clients, users or guests upload files to their own folder', 'useyourdrive'); ?></li>
                    <li><?php _e('You want to give your customers a private folder already filled with some files directly after they register', 'useyourdrive'); ?></li>
                  </ul>
                </div>

                <div class="option option-userfolders forfilebrowser foruploadbox forgallery <?php echo (isset($_REQUEST['userfolders'])) ? '' : 'hidden'; ?>">

                  <div class="useyourdrive-option-title"><?php _e('Mode', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-option-description"><?php _e('Do you want to link your users manually to their Private Folder or should the plugin handle this automatically for you', 'useyourdrive'); ?>.</div>

                  <?php
                  $userfolders = 'auto';
                  if (isset($_REQUEST['userfolders'])) {
                      $userfolders = $_REQUEST['userfolders'];
                  }
                  ?>
                  <div class="useyourdrive-option-radio">
                    <input type="radio" id="userfolders_method_manual" name="UseyourDrive_userfolders_method"<?php echo ($userfolders === 'manual') ? 'checked="checked"' : ''; ?> value="manual"/>
                    <label for="userfolders_method_manual" class="useyourdrive-option-radio-label"><?php echo sprintf(__('I will link the users manually via %sthis page%s', 'useyourdrive'), '<a href="' . admin_url('admin.php?page=UseyourDrive_settings_linkusers') . '" target="_blank">', '</a>'); ?></label>
                  </div>
                  <div class="useyourdrive-option-radio">
                    <input type="radio" id="userfolders_method_auto" name="UseyourDrive_userfolders_method" <?php echo ($userfolders === 'auto') ? 'checked="checked"' : ''; ?> value="auto"/>
                    <label for="userfolders_method_auto" class="useyourdrive-option-radio-label"><?php _e('Let the plugin automatically manage the Private Folders for me in the folder I have selected above', 'useyourdrive'); ?></label>
                  </div>

                  <div class="option-userfolders_auto">
                    <div class="useyourdrive-option-title"><?php _e('Template Folder', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_userfolders_template" id="UseyourDrive_userfolders_template" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['usertemplatedir'])) ? 'checked="checked"' : ''; ?> data-div-toggle='userfolders-template-option'/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_userfolders_template"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description">
                      <?php _e('Newly created Private Folders can be prefilled with files from a template', 'useyourdrive'); ?>. <?php _e('The content of the template folder selected will be copied to the user folder', 'useyourdrive'); ?>.
                    </div>

                    <div class="userfolders-template-option <?php echo (isset($_REQUEST['usertemplatedir'])) ? '' : 'hidden'; ?>">
                      <div class="template-folder">
                        <?php
                        $user_folders = (($user_folder_backend === 'No') ? '0' : $this->settings['userfolder_backend']);

                        $atts = array(
                            'mode' => 'files',
                            'filelayout' => 'list',
                            'maxheight' => '300px',
                            'showfiles' => '1',
                            'filesize' => '0',
                            'filedate' => '0',
                            'upload' => '0',
                            'delete' => '0',
                            'rename' => '0',
                            'addfolder' => '0',
                            'showbreadcrumb' => '1',
                            'showcolumnnames' => '0',
                            'viewrole' => 'all',
                            'downloadrole' => 'none',
                            'candownloadzip' => '0',
                            'showsharelink' => '0',
                            'userfolders' => $user_folders,
                            'mcepopup' => $mcepopup
                        );

                        if (isset($_REQUEST['usertemplatedir'])) {
                            $atts['startid'] = $_REQUEST['usertemplatedir'];
                        }

                        echo $this->create_template($atts);
                        ?>
                      </div>
                    </div>

                    <div class="useyourdrive-option-title"><?php _e('Full Access', 'useyourdrive'); ?></div>
                    <div class="useyourdrive-option-description"><?php _e('By default only Administrator users will be able to navigate through all Private Folders', 'useyourdrive'); ?>. <?php _e('When you want other User Roles to be able do browse to the Private Folders as well, please check them below', 'useyourdrive'); ?>.</div>

                    <?php
                    $selected = (isset($_REQUEST['viewuserfoldersrole'])) ? explode('|', $_REQUEST['viewuserfoldersrole']) : array('administrator');
                    wp_roles_checkbox('UseyourDrive_view_user_folders_role', $selected);
                    ?>
                  </div>
                </div>

              </div>
              <!-- End User Folders Tab -->
              <!-- Media Files Tab -->
              <div id="settings_mediafiles"  class="useyourdrive-tab-panel">

                <div class="useyourdrive-tab-panel-header"><?php _e('Media Files', 'useyourdrive'); ?></div>

                <div class="foraudio">
                  <div class="useyourdrive-option-title"><?php _e('Provided formats', 'useyourdrive'); ?>*</div>
                  <div class="useyourdrive-option-description"><?php _e('Select which sort of media files you will provide', 'useyourdrive'); ?>. <?php _e('You may provide the same file with different extensions to increase cross-browser support', 'useyourdrive'); ?>. <?php _e('Do always supply a mp3 (audio) or m4v/mp4 (video)file to support all browsers', 'useyourdrive'); ?>.</div>
                  <?php
                  $mediaextensions = (!isset($_REQUEST['mediaextensions']) || ($mode !== 'audio')) ? array() : explode('|', $_REQUEST['mediaextensions']);
                  ?>

                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_mp3" type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('mp3', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='mp3' /><label for="mediaextensions_mp3" class="useyourdrive-option-checkbox-label">mp3</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_mp4"  type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('mp4', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='mp4' /><label for="mediaextensions_mp4" class="useyourdrive-option-checkbox-label">mp4</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_m4a" type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('m4a', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='m4a' /><label for="mediaextensions_m4a" class="useyourdrive-option-checkbox-label">m4a</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_ogg"  type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('ogg', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='ogg' /><label for="mediaextensions_ogg" class="useyourdrive-option-checkbox-label">ogg</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_oga" type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('oga', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='oga' /><label for="mediaextensions_oga" class="useyourdrive-option-checkbox-label">oga</label></div>
                </div>        

                <div class="forvideo">
                  <div class="useyourdrive-option-title"><?php _e('Provided formats', 'useyourdrive'); ?>*</div>
                  <div class="useyourdrive-option-description"><?php _e('Select which sort of media files you will provide', 'useyourdrive'); ?>. <?php _e('You may provide the same file with different extensions to increase cross-browser support', 'useyourdrive'); ?>. <?php _e('Do always supply a mp3 (audio) or m4v/mp4 (video)file to support all browsers', 'useyourdrive'); ?>.</div>
                  <?php
                  $mediaextensions = (!isset($_REQUEST['mediaextensions']) || ($mode !== 'video')) ? array() : explode('|', $_REQUEST['mediaextensions']);
                  ?>

                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_mp4" type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('mp4', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='mp4' /><label for="mediaextensions_mp4" class="useyourdrive-option-checkbox-label">mp4</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_m4v"  type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('m4v', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='m4v' /><label for="mediaextensions_m4v" class="useyourdrive-option-checkbox-label">m4v</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_ogg" type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('ogg', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='ogg' /><label for="mediaextensions_ogg" class="useyourdrive-option-checkbox-label">ogg</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_ogv"  type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('ogv', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='ogv' /><label for="mediaextensions_ogv" class="useyourdrive-option-checkbox-label">ogv</label></div>
                  <div class="useyourdrive-option-checkbox" style="display: inline-block;"><input id="mediaextensions_webmv" type="checkbox" name="UseyourDrive_mediaextensions[]" <?php echo (in_array('webmv', $mediaextensions)) ? 'checked="checked"' : ''; ?> value='webmv' /><label for="mediaextensions_webmv" class="useyourdrive-option-checkbox-label">webmv</label></div>
                </div>  

                <div class="useyourdrive-option-title"><?php _e('Auto Play', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_autoplay" id="UseyourDrive_autoplay" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['autoplay']) && $_REQUEST['autoplay'] === '1') ? 'checked="checked"' : ''; ?>>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_autoplay"></label>
                  </div>
                </div>

                <div class="useyourdrive-option-title"><?php _e('Show Playlist on start', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_showplaylist" id="UseyourDrive_showplaylist" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['hideplaylist']) && $_REQUEST['hideplaylist'] === '1') ? '' : 'checked="checked"'; ?>>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showplaylist"></label>
                  </div>
                </div>   

                <div class="foraudio">
                  <div class="useyourdrive-option-title"><?php _e('Display covers', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_covers" id="UseyourDrive_covers" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['covers']) && $_REQUEST['covers'] === '1') ? 'checked="checked"' : ''; ?>>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_covers"></label>
                    </div>
                  </div>   
                  <div class="useyourdrive-option-description"><?php _e('You can show covers of your audio files in the Audio Player', 'useyourdrive'); ?>. <?php _e('Add a *.png or *.jpg file with the same name as your audio file in the same folder as your audio files. You can also add a cover with the name of the folder to show the cover for all audio files in the album', 'useyourdrive'); ?>. <?php _e('If no cover is available, a placeholder will be used', 'useyourdrive'); ?>.</div>
                </div>


                <div class="useyourdrive-option-title"><?php _e('Download Button', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_linktomedia" id="UseyourDrive_linktomedia" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['linktomedia']) && $_REQUEST['linktomedia'] === '1') ? 'checked="checked"' : ''; ?>>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_linktomedia"></label>
                  </div>
                </div>   

                <div class="useyourdrive-option-title"><?php _e('Purchase Button', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_mediapurchase" id="UseyourDrive_mediapurchase" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['linktoshop']) && $_REQUEST['linktoshop'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle='webshop-options'>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_mediapurchase"></label>
                  </div>
                </div>  


                <div class="option webshop-options <?php echo (isset($_REQUEST['linktoshop'])) ? '' : 'hidden'; ?>">
                  <div class="useyourdrive-option-title"><?php _e('Link to webshop', 'useyourdrive'); ?></div>  
                  <input class="useyourdrive-option-input-large" type="text" name="UseyourDrive_linktoshop" id="UseyourDrive_linktoshop" placeholder="https://www.yourwebshop.com/" value="<?php echo (isset($_REQUEST['linktoshop'])) ? $_REQUEST['linktoshop'] : ''; ?>"/>
                </div>

              </div>
              <!-- End Media Files Tab -->

              <!-- Layout Tab -->
              <div id="settings_layout"  class="useyourdrive-tab-panel">

                <div class="useyourdrive-tab-panel-header"><?php _e('Layout', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Plugin container width', 'useyourdrive'); ?></div>
                <div class="useyourdrive-option-description"><?php _e("Set max width for the Use-your-Drive container", "useyourdrive"); ?>. <?php _e("You can use pixels or percentages, eg '360px', '480px', '70%'", "useyourdrive"); ?>. <?php _e('Leave empty for default value', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_max_width" id="UseyourDrive_max_width" placeholder="100%" value="<?php echo (isset($_REQUEST['maxwidth'])) ? $_REQUEST['maxwidth'] : ''; ?>"/>


                <div class="forfilebrowser forgallery forsearch">
                  <div class="useyourdrive-option-title"><?php _e('Plugin container height', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-option-description"><?php _e("Set max height for the Use-your-Drive container", "useyourdrive"); ?>. <?php _e("You can use pixels or percentages, eg '360px', '480px', '70%'", "useyourdrive"); ?>. <?php _e('Leave empty for default value', 'useyourdrive'); ?>.</div>
                  <input type="text" name="UseyourDrive_max_height" id="UseyourDrive_max_height" placeholder="auto" value="<?php echo (isset($_REQUEST['maxheight'])) ? $_REQUEST['maxheight'] : ''; ?>"/>
                </div>

                <div class="useyourdrive-option-title"><?php _e('Custom CSS Class', 'useyourdrive'); ?></div>
                <div class="useyourdrive-option-description"><?php _e('Add your own custom classes to the plugin container. Multiple classes can be added seperated by a whitespace', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_class" id="UseyourDrive_class" value="<?php echo (isset($_REQUEST['class'])) ? $_REQUEST['class'] : '' ?>" autocomplete="off"/>

                <div class="forfilebrowser forsearch">
                  <div class="useyourdrive-option-title"><?php _e('File Browser view', 'useyourdrive'); ?></div>
                  <?php
                  $filelayout = (!isset($_REQUEST['filelayout'])) ? 'grid' : $_REQUEST['filelayout'];
                  ?>
                  <div class="useyourdrive-option-radio">
                    <input type="radio" id="file_layout_grid" name="UseyourDrive_file_layout"  <?php echo ($filelayout === 'grid') ? 'checked="checked"' : ''; ?> value="grid" />
                    <label for="file_layout_grid" class="useyourdrive-option-radio-label"><?php _e('Grid/Thumbnail View', 'useyourdrive'); ?></label>
                  </div>
                  <div class="useyourdrive-option-radio">
                    <input type="radio" id="file_layout_list" name="UseyourDrive_file_layout"  <?php echo ($filelayout === 'list') ? 'checked="checked"' : ''; ?> value="list" />
                    <label for="file_layout_list" class="useyourdrive-option-radio-label"><?php _e('List View', 'useyourdrive'); ?></label>
                  </div>
                </div>

                <div class=" forfilebrowser forgallery">
                  <div class="useyourdrive-option-title"><?php _e('Show header', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_breadcrumb" id="UseyourDrive_breadcrumb" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showbreadcrumb']) && $_REQUEST['showbreadcrumb'] === '0') ? '' : 'checked="checked"'; ?> data-div-toggle="header-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_breadcrumb"></label>
                    </div>
                  </div>  

                  <div class="option header-options <?php echo (isset($_REQUEST['showbreadcrumb']) && $_REQUEST['showbreadcrumb'] === '0') ? 'hidden' : ''; ?>">
                    <div class="useyourdrive-option-title"><?php _e('Show refresh button', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_showrefreshbutton" id="UseyourDrive_showrefreshbutton" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showrefreshbutton']) && $_REQUEST['showrefreshbutton'] === '0') ? '' : 'checked="checked"'; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showrefreshbutton"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Add a refresh button in the header so users can refresh the file list and pull changes', 'useyourdrive'); ?></div>

                    <div class="useyourdrive-option-title"><?php _e('Breadcrumb text for top folder', 'useyourdrive'); ?></div>
                    <input type="text" name="UseyourDrive_roottext" id="UseyourDrive_roottext" placeholder="<?php _e('Start', 'useyourdrive'); ?>" value="<?php echo (isset($_REQUEST['roottext'])) ? $_REQUEST['roottext'] : ''; ?>"/>
                  </div>
                </div>
                <div class=" forfilebrowser forsearch forgallery">
                  <div class="option forfilebrowser forsearch forlistonly">
                    <div class="useyourdrive-option-title"><?php _e('Show columnnames', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_showcolumnnames" id="UseyourDrive_showcolumnnames" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showcolumnnames']) && $_REQUEST['showcolumnnames'] === '0') ? '' : 'checked="checked"'; ?> data-div-toggle="columnnames-options"/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showcolumnnames"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Display the columnnames of the date and filesize in the List View of the File Browser', 'useyourdrive'); ?></div>

                    <div class="columnnames-options">
                      <div class="option-filesize">
                        <div class="useyourdrive-option-title"><?php _e('Show file size', 'useyourdrive'); ?>
                          <div class="useyourdrive-onoffswitch">
                            <input type="checkbox" name="UseyourDrive_filesize" id="UseyourDrive_filesize" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['filesize']) && $_REQUEST['filesize'] === '0') ? '' : 'checked="checked"'; ?>/>
                            <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_filesize"></label>
                          </div>
                        </div>
                        <div class="useyourdrive-option-description"><?php _e('Display or Hide column with file sizes in List view', 'useyourdrive'); ?></div>
                      </div>

                      <div class="option-filedate">
                        <div class="useyourdrive-option-title"><?php _e('Show last modified date', 'useyourdrive'); ?>
                          <div class="useyourdrive-onoffswitch">
                            <input type="checkbox" name="UseyourDrive_filedate" id="UseyourDrive_filedate" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['filedate']) && $_REQUEST['filedate'] === '0') ? '' : 'checked="checked"'; ?>/>
                            <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_filedate"></label>
                          </div>
                        </div>
                        <div class="useyourdrive-option-description"><?php _e('Display or Hide column with last modified date in List view', 'useyourdrive'); ?></div>
                      </div>
                    </div>
                  </div>

                  <div class="option forfilebrowser forsearch forgallery">
                    <div class="useyourdrive-option-title"><?php _e('Show file extension', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_showext" id="UseyourDrive_showext" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showext']) && $_REQUEST['showext'] === '0') ? '' : 'checked="checked"'; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showext"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Display or Hide the file extensions', 'useyourdrive'); ?></div>

                    <div class="useyourdrive-option-title"><?php _e('Show files', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_showfiles" id="UseyourDrive_showfiles" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showfiles']) && $_REQUEST['showfiles'] === '0') ? '' : 'checked="checked"'; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showfiles"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Display or Hide files', 'useyourdrive'); ?></div>
                  </div>

                  <div class="useyourdrive-option-title"><?php _e('Show folders', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_showfolders" id="UseyourDrive_showfolders" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showfolders']) && $_REQUEST['showfolders'] === '0') ? '' : 'checked="checked"'; ?>/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showfolders"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('Display or Hide child folders', 'useyourdrive'); ?></div>

                  <div class="showfiles-options">
                    <div class="useyourdrive-option-title"><?php _e('Amount of files', 'useyourdrive'); ?>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Number of files to show', 'useyourdrive'); ?>. <?php _e('Can be used for instance to only show the last 5 updated documents', 'useyourdrive'); ?>. <?php _e("Leave this field empty or set it to -1 for no limit", 'useyourdrive'); ?></div>
                    <input type="text" name="UseyourDrive_maxfiles" id="UseyourDrive_maxfiles" placeholder="-1" value="<?php echo (isset($_REQUEST['maxfiles'])) ? $_REQUEST['maxfiles'] : ''; ?>"/>
                  </div>
                </div>

                <div class="option forgallery">
                  <div class="useyourdrive-option-title"><?php _e('Show file names', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_showfilenames" id="UseyourDrive_showfilenames" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showfilenames']) && $_REQUEST['showfilenames'] === '1') ? 'checked="checked"' : ''; ?>/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showfilenames"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('Display or Hide the file names in the gallery', 'useyourdrive'); ?></div>

                  <div class="useyourdrive-option-title"><?php _e('Gallery row height', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-option-description"><?php _e("The ideal height you want your grid rows to be", 'useyourdrive'); ?>. <?php _e("It won't set it exactly to this as plugin adjusts the row height to get the correct width", 'useyourdrive'); ?>. <?php _e('Leave empty for default value', 'useyourdrive'); ?> (200px).</div>
                  <input type="text" name="UseyourDrive_targetHeight" id="UseyourDrive_targetHeight" placeholder="200" value="<?php echo (isset($_REQUEST['targetheight'])) ? $_REQUEST['targetheight'] : ''; ?>"/>

                  <div class="useyourdrive-option-title"><?php _e('Number of images lazy loaded', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-option-description"><?php _e("Number of images to be loaded each time", 'useyourdrive'); ?>. <?php _e("Set to 0 to load all images at once", 'useyourdrive'); ?>.</div>
                  <input type="text" name="UseyourDrive_maximage" id="UseyourDrive_maximage" placeholder="25" value="<?php echo (isset($_REQUEST['maximages'])) ? $_REQUEST['maximages'] : ''; ?>"/>

                  <!---
                  <div class="useyourdrive-option-title"><?php _e('Show Folder Thumbnails in Gallery', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_folderthumbs" id="UseyourDrive_folderthumbs" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['folderthumbs']) && $_REQUEST['folderthumbs'] === '1') ? 'checked="checked"' : ''; ?> />
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_folderthumbs"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e("Do you want to show thumbnails for the Folders in the gallery mode?", 'useyourdrive'); ?> <?php _e("Please note, when enabled the loading performance can drop proportional to the number of folders present in the Gallery", 'useyourdrive'); ?>.</div>
                  -->

                  <div class="useyourdrive-option-title"><?php _e('Slideshow', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_slideshow" id="UseyourDrive_slideshow" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['slideshow']) && $_REQUEST['slideshow'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="slideshow-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_slideshow"></label>
                    </div>
                  </div>

                  <div class="slideshow-options">                  
                    <div class="useyourdrive-option-description"><?php _e('Enable or disable the Slideshow mode in the Lightbox', 'useyourdrive'); ?></div>                  
                    <div class="useyourdrive-option-title"><?php _e('Delay between cycles (ms)', 'useyourdrive'); ?></div>
                    <div class="useyourdrive-option-description"><?php _e('Delay between cycles in milliseconds, the default is 5000', 'useyourdrive'); ?>.</div>
                    <input type="text" name="UseyourDrive_pausetime" id="UseyourDrive_pausetime" placeholder="5000" value="<?php echo (isset($_REQUEST['pausetime'])) ? $_REQUEST['pausetime'] : ''; ?>"/>
                  </div>
                </div>
              </div>
              <!-- End Layout Tab -->

              <!-- Sorting Tab -->
              <div id="settings_sorting"  class="useyourdrive-tab-panel">

                <div class="useyourdrive-tab-panel-header"><?php _e('Sorting', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Sort field', 'useyourdrive'); ?></div>
                <?php
                $sortfield = (!isset($_REQUEST['sortfield'])) ? 'name' : $_REQUEST['sortfield'];
                ?>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="name" name="sort_field" <?php echo ($sortfield === 'name') ? 'checked="checked"' : ''; ?> value="name"/>
                  <label for="name" class="useyourdrive-option-radio-label"><?php _e('Name', 'useyourdrive'); ?></label>
                </div>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="size" name="sort_field" <?php echo ($sortfield === 'size') ? 'checked="checked"' : ''; ?> value="size" />
                  <label for="size" class="useyourdrive-option-radio-label"><?php _e('Size', 'useyourdrive'); ?></label>
                </div>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="created" name="sort_field" <?php echo ($sortfield === 'created') ? 'checked="checked"' : ''; ?> value="created" />
                  <label for="created" class="useyourdrive-option-radio-label"><?php _e('Date of creation', 'useyourdrive'); ?></label>
                </div>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="modified" name="sort_field" <?php echo ($sortfield === 'modified') ? 'checked="checked"' : ''; ?> value="modified" />
                  <label for="modified" class="useyourdrive-option-radio-label"><?php _e('Date modified', 'useyourdrive'); ?></label>
                </div>
                <div class="useyourdrive-option-radio">
                  <input type="radio" id="shuffle" name="sort_field" <?php echo ($sortfield === 'shuffle') ? 'checked="checked"' : ''; ?> value="shuffle" />
                  <label for="shuffle" class="useyourdrive-option-radio-label"><?php _e('Shuffle/Random', 'useyourdrive'); ?></label>
                </div>

                <div class="option-sort-field">
                  <div class="useyourdrive-option-title"><?php _e('Sort order', 'useyourdrive'); ?></div>

                  <?php
                  $sortorder = (isset($_REQUEST['sortorder']) && $_REQUEST['sortorder'] === 'desc') ? 'desc' : 'asc';
                  ?>
                  <div class="useyourdrive-option-radio">
                    <input type="radio" id="asc" name="sort_order" <?php echo ($sortorder === 'asc') ? 'checked="checked"' : ''; ?> value="asc"/>
                    <label for="asc" class="useyourdrive-option-radio-label"><?php _e('Ascending', 'useyourdrive'); ?></label>
                  </div>
                  <div class="useyourdrive-option-radio">
                    <input type="radio" id="desc" name="sort_order" <?php echo ($sortorder === 'desc') ? 'checked="checked"' : ''; ?> value="desc"/>
                    <label for="desc" class="useyourdrive-option-radio-label"><?php _e('Descending', 'useyourdrive'); ?></label>
                  </div>
                </div>
              </div>
              <!-- End Sorting Tab -->
              <!-- Advanced Tab -->
              <div id="settings_advanced"  class="useyourdrive-tab-panel">
                <div class="useyourdrive-tab-panel-header"><?php _e('Advanced', 'useyourdrive'); ?></div>

                <div class="option forfilebrowser forgallery forsearch">
                  <div class="useyourdrive-option-title"><?php _e('Allow Preview', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_allow_preview" id="UseyourDrive_allow_preview" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['forcedownload']) && $_REQUEST['forcedownload'] === '1') ? '' : 'checked="checked"'; ?> data-div-toggle="preview-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_allow_preview"></label>
                    </div>
                  </div>
                </div>

                <div class="option preview-options <?php echo (isset($_REQUEST['forcedownload']) && $_REQUEST['forcedownload'] === '1') ? 'hidden' : ''; ?>">
                  <div class="useyourdrive-option-title"><?php _e('Inline Preview', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_previewinline" id="UseyourDrive_previewinline" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['previewinline']) && $_REQUEST['previewinline'] === '0') ? '' : 'checked="checked"'; ?> data-div-toggle="preview-options-inline"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_previewinline"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('Open preview inside a lightbox or open in a new window', 'useyourdrive'); ?></div>

                  <div class="option preview-options-inline <?php echo (isset($_REQUEST['previewinline']) && $_REQUEST['previewinline'] === '0') ? 'hidden' : ''; ?>">
                    <div class="useyourdrive-option-title"><?php _e('Enable Google pop out Button', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_canpopout" id="UseyourDrive_canpopout"  class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['canpopout']) && $_REQUEST['canpopout'] === '1') ? 'checked="checked"' : ''; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_canpopout"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Disables the Google Pop Out button which is visible in the inline preview for a couple of file formats', 'useyourdrive'); ?>. </div>
                  </div>
                </div>

                <div class="option forfilebrowser foruploadbox forgallery">
                  <div class="useyourdrive-option-title"><?php _e('Allow Searching', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_search" id="UseyourDrive_search" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['search']) && $_REQUEST['search'] === '0') ? '' : 'checked="checked"'; ?> data-div-toggle="search-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_search"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('The search function allows your users to find files by filename and content (when files are indexed)', 'useyourdrive'); ?></div>
                </div>

                <div class="option search-options <?php echo (isset($_REQUEST['search']) && $_REQUEST['search'] === '1') ? '' : 'hidden'; ?>">
                  <div class="useyourdrive-option-title"><?php _e('Perform Full-Text search', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_search_field" id="UseyourDrive_search_field"  class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['searchcontents']) && $_REQUEST['searchcontents'] === '1') ? 'checked="checked"' : ''; ?>/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_search_field"></label>
                    </div>
                  </div>
                </div>

                <?php
                if (class_exists('ZipArchive')) {
                    ?>
                    <div class="useyourdrive-option-title"><?php _e('Allow ZIP Download', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_candownloadzip" id="UseyourDrive_candownloadzip" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['candownloadzip']) && $_REQUEST['candownloadzip'] === '1') ? 'checked="checked"' : ''; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_candownloadzip"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Allow users to download multiple files at once', 'useyourdrive'); ?></div>
                <?php } ?>

              </div>
              <!-- End Advanced Tab -->
              <!-- Exclusions Tab -->
              <div id="settings_exclusions"  class="useyourdrive-tab-panel">
                <div class="useyourdrive-tab-panel-header"><?php _e('Exclusions', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Only show files with those extensions', 'useyourdrive'); ?>:</div>
                <div class="useyourdrive-option-description"><?php echo __('Add extensions separated with | e.g. (jpg|png|gif)', 'useyourdrive') . '. ' . __('Leave empty to show all files', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_include_ext" id="UseyourDrive_include_ext" class="useyourdrive-option-input-large" value="<?php echo (isset($_REQUEST['includeext'])) ? $_REQUEST['includeext'] : ''; ?>"/>

                <div class="useyourdrive-option-title"><?php _e('Only show the following files or folders', 'useyourdrive'); ?>:</div>
                <div class="useyourdrive-option-description"><?php echo __('Add files or folders by name or Google Drive ID separated with | e.g. (file1.jpg|long folder name)', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_include" id="UseyourDrive_include" class="useyourdrive-option-input-large" value="<?php echo (isset($_REQUEST['include'])) ? $_REQUEST['include'] : ''; ?>"/>

                <div class="useyourdrive-option-title"><?php _e('Hide files with those extensions', 'useyourdrive'); ?>:</div>
                <div class="useyourdrive-option-description"><?php echo __('Add extensions separated with | e.g. (jpg|png|gif)', 'useyourdrive') . '. ' . __('Leave empty to show all files', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_exclude_ext" id="UseyourDrive_exclude_ext" class="useyourdrive-option-input-large" value="<?php echo (isset($_REQUEST['excludeext'])) ? $_REQUEST['excludeext'] : ''; ?>"/>

                <div class="useyourdrive-option-title"><?php _e('Hide the following files or folders', 'useyourdrive'); ?>:</div>
                <div class="useyourdrive-option-description"><?php echo __('Add files or folders by name or Google Drive ID separated with | e.g. (file1.jpg|long folder name)', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_exclude" id="UseyourDrive_exclude"  class="useyourdrive-option-input-large" value="<?php echo (isset($_REQUEST['exclude'])) ? $_REQUEST['exclude'] : ''; ?>"/>

              </div>
              <!-- End Exclusions Tab -->

              <!-- Upload Tab -->
              <div id="settings_upload"  class="useyourdrive-tab-panel">

                <div class="useyourdrive-tab-panel-header"><?php _e('Upload Box', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Allow Upload', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_upload" id="UseyourDrive_upload" data-div-toggle="upload-options" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['upload']) && $_REQUEST['upload'] === '1') ? 'checked="checked"' : ''; ?>/>
                    <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_upload"></label>
                  </div>
                </div>
                <div class="useyourdrive-option-description"><?php _e('Allow users to upload files', 'useyourdrive'); ?>. <?php echo sprintf(__('You can select which Users Roles should be able to upload via %s', 'useyourdrive'), '<a href="#" onclick="jQuery(\'li[data-tab=settings_permissions]\').trigger(\'click\')">' . __('User Permissions', 'useyourdrive') . '</a>'); ?>.</div>

                <div class="option upload-options <?php echo (isset($_REQUEST['upload']) && $_REQUEST['upload'] === '1' && in_array($mode, array('files', 'upload', 'gallery'))) ? '' : 'hidden'; ?>">

                  <div class="useyourdrive-option-title"><?php _e('Allow folder upload', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_upload_folder" id="UseyourDrive_upload_folder"  class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['upload_folder']) && $_REQUEST['upload_folder'] === '0') ? '' : 'checked="checked"'; ?>/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_upload_folder"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('Adds an Add Folder button to the upload form if the browser supports it', 'useyourdrive'); ?>. </div>


                  <div class="useyourdrive-option-title"><?php _e('Overwrite existing files', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_overwrite" id="UseyourDrive_overwrite"  class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['overwrite']) && $_REQUEST['overwrite'] === '1') ? 'checked="checked"' : ''; ?>/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_overwrite"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('Overwrite already existing files or auto-rename the new uploaded files', 'useyourdrive'); ?>. </div>

                  <div class="useyourdrive-option-title"><?php _e('Restrict file extensions', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-option-description"><?php echo __('Add extensions separated with | e.g. (jpg|png|gif)', 'useyourdrive') . ' ' . __('Leave empty for no restricion', 'useyourdrive', 'useyourdrive'); ?>.</div>
                  <input type="text" name="UseyourDrive_upload_ext" id="UseyourDrive_upload_ext" value="<?php echo (isset($_REQUEST['uploadext'])) ? $_REQUEST['uploadext'] : ''; ?>"/>

                  <div class="useyourdrive-option-title"><?php _e('Max uploads per session', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-option-description"><?php echo __('Number of maximum uploads per upload session', 'useyourdrive') . ' ' . __('Leave empty for no restricion', 'useyourdrive'); ?>.</div>
                  <input type="text" name="UseyourDrive_maxnumberofuploads" id="UseyourDrive_maxnumberofuploads" placeholder="-1" value="<?php echo (isset($_REQUEST['maxnumberofuploads'])) ? $_REQUEST['maxnumberofuploads'] : ''; ?>"/>

                  <div class="useyourdrive-option-title"><?php _e('Maximum file size', 'useyourdrive'); ?></div>
                  <?php
                  $max_size_bytes = min(\TheLion\UseyourDrive\Helpers::return_bytes(ini_get('post_max_size')), \TheLion\UseyourDrive\Helpers::return_bytes(ini_get('upload_max_filesize')));
                  $max_size_string = \TheLion\UseyourDrive\Helpers::bytes_to_size_1024($max_size_bytes);


                  /* Convert bytes in version before 1.8 to MB */
                  $max_size_value = (isset($_REQUEST['maxfilesize']) ? $_REQUEST['maxfilesize'] : '');
                  if (!empty($max_size_value) && ctype_digit($max_size_value)) {
                      $max_size_value = \TheLion\UseyourDrive\Helpers::bytes_to_size_1024($max_size_value);
                  }
                  ?>
                  <div class="useyourdrive-option-description"><?php _e('Max filesize for uploading in bytes', 'useyourdrive'); ?>. <?php echo __('Leave empty for server maximum ', 'useyourdrive'); ?> (<?php echo $max_size_string; ?>).</div>
                  <input type="text" name="UseyourDrive_maxfilesize" id="UseyourDrive_maxfilesize" placeholder="<?php echo $max_size_string; ?>" value="<?php echo $max_size_value; ?>"/>

                  <div style="<?php echo ((version_compare(phpversion(), '7.1.0', '<='))) ? '' : "display:none;" ?>">
                    <div class="useyourdrive-option-title"><?php _e('Encryption', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_encryption" id="UseyourDrive_encryption"  data-div-toggle="upload-encryption-options" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['upload_encryption']) && $_REQUEST['upload_encryption'] === '1') ? 'checked="checked"' : ''; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_encryption"></label>
                      </div>
                    </div>
                    <div class="useyourdrive-option-description"><?php _e('Use the powerful 256-bit AES Crypt encryption to securely store your files in the cloud', 'useyourdrive'); ?>. <?php _e('The encryption takes place on your server before the files are uploaded', 'useyourdrive'); ?>. <?php _e('You can decrypt the files with a tool like: ', 'useyourdrive'); ?> <a href="https://www.aescrypt.com/download/" target="_blank">AES Crypt</a>.</div>
                    <div class="option upload-encryption-options <?php echo (isset($_REQUEST['upload_encryption']) && $_REQUEST['upload_encryption'] === '1') ? '' : 'hidden'; ?>">
                      <div class="useyourdrive-option-title"><?php _e('Passphrase'); ?></div>
                      <input type="text" name="UseyourDrive_encryption_passphrase" id="UseyourDrive_encryption_passphrase" class="useyourdrive-option-input-large" value="<?php echo (isset($_REQUEST['upload_encryption_passphrase'])) ? $_REQUEST['upload_encryption_passphrase'] : ''; ?>"/>
                    </div>
                  </div>

                  <div class="useyourdrive-option-title"><?php _e('Convert to Google Docs when possible', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_upload_convert" id="UseyourDrive_upload_convert" data-div-toggle="upload-convert-options" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['convert']) && $_REQUEST['convert'] === '1') ? 'checked="checked"' : ''; ?>/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_upload_convert"></label>
                    </div>
                  </div>

                  <div class="option upload-convert-options <?php echo (isset($_REQUEST['convert']) && $_REQUEST['convert'] === '1') ? '' : 'hidden'; ?>">
                    <div class="useyourdrive-option-title"><?php _e('Convert following mimetypes', 'useyourdrive'); ?></div>
                    <?php
                    $importFormats = array(
                        "application/msword" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.openxmlformats-officedocument.wordprocessingml.document" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.openxmlformats-officedocument.wordprocessingml.template" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.ms-word.document.macroenabled.12" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.ms-word.template.macroenabled.12" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/x-vnd.oasis.opendocument.text" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/pdf" =>
                        "application/vnd.google-apps.document"
                        ,
                        "text/html" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.oasis.opendocument.text" =>
                        "application/vnd.google-apps.document"
                        ,
                        "text/richtext" =>
                        "application/vnd.google-apps.document"
                        ,
                        "text/rtf" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/rtf" =>
                        "application/vnd.google-apps.document"
                        ,
                        "text/plain" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.sun.xml.writer" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.ms-excel" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/vnd.ms-excel.sheet.macroenabled.12" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/vnd.ms-excel.template.macroenabled.12" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/vnd.openxmlformats-officedocument.spreadsheetml.template" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/vnd.oasis.opendocument.spreadsheet" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/x-vnd.oasis.opendocument.spreadsheet" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "text/tab-separated-values" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "text/csv" =>
                        "application/vnd.google-apps.spreadsheet"
                        ,
                        "application/vnd.ms-powerpoint" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.openxmlformats-officedocument.presentationml.template" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.openxmlformats-officedocument.presentationml.presentation" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.openxmlformats-officedocument.presentationml.slideshow" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.oasis.opendocument.presentation" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.ms-powerpoint.template.macroenabled.12" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.ms-powerpoint.presentation.macroenabled.12" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/vnd.ms-powerpoint.slideshow.macroenabled.12" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "application/x-vnd.oasis.opendocument.presentation" =>
                        "application/vnd.google-apps.presentation"
                        ,
                        "image/jpg" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/jpeg" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/bmp" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/x-bmp" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/gif" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/png" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/x-png" =>
                        "application/vnd.google-apps.document"
                        ,
                        "image/pjpeg" =>
                        "application/vnd.google-apps.document"
                        ,
                        "application/vnd.google-apps.script+text/plain" =>
                        "application/vnd.google-apps.script"
                        ,
                        "application/json" =>
                        "application/vnd.google-apps.script"
                        ,
                        "application/vnd.google-apps.script+json" =>
                        "application/vnd.google-apps.script"
                        ,
                        "application/x-msmetafile" =>
                        "application/vnd.google-apps.drawing"
                    );


                    $selected_formats = (isset($_REQUEST['convertformats'])) ? explode('|', $_REQUEST['convertformats']) : array_keys($importFormats);

                    foreach ($importFormats as $mimetype => $import_mimetype) {
                        if (in_array($mimetype, $selected_formats)) {
                            $checked = 'checked="checked"';
                        } else {
                            $checked = '';
                        }
                        echo '<div class="useyourdrive-option-checkbox">';
                        echo '<input class="simple" type="checkbox" name="UseyourDrive_upload_convert_formats[]" id="UseyourDrive_upload_convert_formats" value="' . $mimetype . '" ' . $checked . '>';
                        echo '<label for="userfolders_method_auto1" class="useyourdrive-option-checkbox-label">' . $mimetype . '</label>';
                        echo '</div>';
                    }
                    ?>
                  </div>

                </div>
              </div>
              <!-- End Upload Tab -->

              <!-- Notifications Tab -->
              <div id="settings_notifications"  class="useyourdrive-tab-panel">

                <div class="useyourdrive-tab-panel-header"><?php _e('Notifications', 'useyourdrive'); ?></div>

                <div class="useyourdrive-option-title"><?php _e('Download email notification', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_notificationdownload" id="UseyourDrive_notificationdownload" class="useyourdrive-onoffswitch-checkbox"  <?php echo (isset($_REQUEST['notificationdownload']) && $_REQUEST['notificationdownload'] === '1') ? 'checked="checked"' : ''; ?>/>
                    <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_notificationdownload"></label>
                  </div>
                </div>

                <div class="useyourdrive-option-title"><?php _e('Upload email notification', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_notificationupload" id="UseyourDrive_notificationupload" class="useyourdrive-onoffswitch-checkbox"  <?php echo (isset($_REQUEST['notificationupload']) && $_REQUEST['notificationupload'] === '1') ? 'checked="checked"' : ''; ?>/>
                    <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_notificationupload"></label>
                  </div>
                </div>
                <div class="useyourdrive-option-title"><?php _e('Delete email notification', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_notificationdeletion" id="UseyourDrive_notificationdeletion" class="useyourdrive-onoffswitch-checkbox"  <?php echo (isset($_REQUEST['notificationdeletion']) && $_REQUEST['notificationdeletion'] === '1') ? 'checked="checked"' : ''; ?>/>
                    <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_notificationdeletion"></label>
                  </div>
                </div>

                <div class="useyourdrive-option-title"><?php _e('Receiver', 'useyourdrive'); ?></div>
                <div class="useyourdrive-option-description"><?php _e('On which email address would you like to receive the notification? You can use <code>%admin_email%</code>, <code>%user_email%</code> (user that executes the action) and <code>%linked_user_email%</code> (Manually linked Private Folders)', 'useyourdrive'); ?>.</div>
                <input type="text" name="UseyourDrive_notification_email" id="UseyourDrive_notification_email" class="useyourdrive-option-input-large" placeholder="<?php echo get_site_option('admin_email'); ?>" value="<?php echo (isset($_REQUEST['notificationemail'])) ? $_REQUEST['notificationemail'] : ''; ?>" />

                <div class="useyourdrive-option-title"><?php _e('Skip notification of the user that executes the action', 'useyourdrive'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type="checkbox" name="UseyourDrive_notification_skip_email_currentuser" id="UseyourDrive_notification_skip_email_currentuser" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['notification_skipemailcurrentuser']) && $_REQUEST['notification_skipemailcurrentuser'] === '1') ? 'checked="checked"' : ''; ?>/>
                    <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_notification_skip_email_currentuser"></label>
                  </div>
                </div>

              </div>
              <!-- End Notifications Tab -->

              <!-- Manipulation Tab -->
              <div id="settings_manipulation"  class="useyourdrive-tab-panel">
                <div class="useyourdrive-tab-panel-header"><?php _e('File Manipulation', 'useyourdrive'); ?></div>

                <div class="option forfilebrowser forgallery forsearch">
                  <div class="useyourdrive-option-title"><?php _e('Allow Sharing', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_showsharelink" id="UseyourDrive_showsharelink" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['showsharelink']) && $_REQUEST['showsharelink'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="sharing-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_showsharelink"></label>
                    </div>
                  </div>
                  <div class="useyourdrive-option-description"><?php _e('Allow users to generate permanent shared links to the files', 'useyourdrive'); ?></div>

                  <div class="useyourdrive-option-title"><?php _e('Edit descriptions', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_editdescription" id="UseyourDrive_editdescription" class="useyourdrive-onoffswitch-checkbox"  <?php echo (isset($_REQUEST['editdescription']) && $_REQUEST['editdescription'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="editdescription-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_editdescription"></label>
                    </div>
                  </div>

                  <div class="useyourdrive-option-title"><?php _e('Rename files and folders', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_rename" id="UseyourDrive_rename" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['rename']) && $_REQUEST['rename'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="rename-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_rename"></label>
                    </div>
                  </div>

                  <div class="useyourdrive-option-title"><?php _e('Move files and folders', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_move" id="UseyourDrive_move" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['move']) && $_REQUEST['move'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="move-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_move"></label>
                    </div>
                  </div>

                  <div class="useyourdrive-option-title"><?php _e('Delete files and folders', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_delete" id="UseyourDrive_delete" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['delete']) && $_REQUEST['delete'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="delete-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_delete"></label>
                    </div>
                  </div>

                  <div class="option delete-options <?php echo (isset($_REQUEST['delete']) && $_REQUEST['delete'] === '1') ? '' : 'hidden'; ?>">
                    <div class="useyourdrive-option-title"><?php _e('Delete to trash', 'useyourdrive'); ?>
                      <div class="useyourdrive-onoffswitch">
                        <input type="checkbox" name="UseyourDrive_deletetotrash" id="UseyourDrive_deletetotrash" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['deletetotrash']) && $_REQUEST['deletetotrash'] === '0') ? '' : 'checked="checked"'; ?>/>
                        <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_deletetotrash"></label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="option forfilebrowser forgallery">
                  <div class="useyourdrive-option-title"><?php _e('Create new folders', 'useyourdrive'); ?>
                    <div class="useyourdrive-onoffswitch">
                      <input type="checkbox" name="UseyourDrive_addfolder" id="UseyourDrive_addfolder" class="useyourdrive-onoffswitch-checkbox" <?php echo (isset($_REQUEST['addfolder']) && $_REQUEST['addfolder'] === '1') ? 'checked="checked"' : ''; ?> data-div-toggle="addfolder-options"/>
                      <label class="useyourdrive-onoffswitch-label" for="UseyourDrive_addfolder"></label>
                    </div>
                  </div>
                </div>

                <br/><br/>

                <div class="useyourdrive-option-description">
                  <?php echo sprintf(__('Select via %s which User Roles are able to perform the actions', 'useyourdrive'), '<a href="#" onclick="jQuery(\'li[data-tab=settings_permissions]\').trigger(\'click\')">' . __('User Permissions', 'useyourdrive') . '</a>'); ?>.
                </div>

              </div>
              <!-- End Manipulation Tab -->
              <!-- Permissions Tab -->
              <div id="settings_permissions"  class="useyourdrive-tab-panel">
                <div class="useyourdrive-tab-panel-header"><?php _e('User Permissions', 'useyourdrive'); ?></div>

                <div class="option forfilebrowser foruploadbox forupload forgallery foraudio forvideo forsearch useyourdrive-permissions-box">
                  <div class="useyourdrive-option-title"><?php _e('Who can see the plugin', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['viewrole'])) ? explode('|', $_REQUEST['viewrole']) : array('administrator', 'author', 'contributor', 'editor', 'subscriber', 'pending', 'guest');
                  wp_roles_checkbox('UseyourDrive_view_role', $selected);
                  ?>

                  <div class="useyourdrive-option-title"><?php _e('Who can download', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['downloadrole'])) ? explode('|', $_REQUEST['downloadrole']) : array('all');
                  wp_roles_checkbox('UseyourDrive_download_role', $selected);
                  ?>

                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery foruploadbox forupload upload-options">
                  <div class="useyourdrive-option-title"><?php _e('Who can upload', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['uploadrole'])) ? explode('|', $_REQUEST['uploadrole']) : array('administrator', 'author', 'contributor', 'editor', 'subscriber');
                  wp_roles_checkbox('UseyourDrive_upload_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch sharing-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can share content', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['sharerole'])) ? explode('|', $_REQUEST['sharerole']) : array('all');
                  wp_roles_checkbox('UseyourDrive_share_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch editdescription-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can edit descriptions', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['editdescriptionrole'])) ? explode('|', $_REQUEST['editdescriptionrole']) : array('administrator', 'editor');
                  wp_roles_checkbox('UseyourDrive_editdescription_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch rename-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can rename files', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['renamefilesrole'])) ? explode('|', $_REQUEST['renamefilesrole']) : array('administrator', 'author', 'contributor', 'editor');
                  wp_roles_checkbox('UseyourDrive_rename_files_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch rename-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can rename folders', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['renamefoldersrole'])) ? explode('|', $_REQUEST['renamefoldersrole']) : array('administrator', 'author', 'contributor', 'editor');
                  wp_roles_checkbox('UseyourDrive_rename_folders_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch move-options">
                  <div class="useyourdrive-option-title"><?php _e('Who can move files', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['movefilesrole'])) ? explode('|', $_REQUEST['movefilesrole']) : array('administrator', 'editor');
                  wp_roles_checkbox('UseyourDrive_move_files_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch move-options">
                  <div class="useyourdrive-option-title"><?php _e('Who can move folders', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['movefoldersrole'])) ? explode('|', $_REQUEST['movefoldersrole']) : array('administrator', 'editor');
                  wp_roles_checkbox('UseyourDrive_move_folders_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch delete-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can delete files', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['deletefilesrole'])) ? explode('|', $_REQUEST['deletefilesrole']) : array('administrator', 'author', 'contributor', 'editor');
                  wp_roles_checkbox('UseyourDrive_delete_files_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery forsearch delete-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can delete folders', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['deletefoldersrole'])) ? explode('|', $_REQUEST['deletefoldersrole']) : array('administrator', 'author', 'contributor', 'editor');
                  wp_roles_checkbox('UseyourDrive_delete_folders_role', $selected);
                  ?>
                </div>

                <div class="option useyourdrive-permissions-box forfilebrowser forgallery addfolder-options ">
                  <div class="useyourdrive-option-title"><?php _e('Who can create new folders', 'useyourdrive'); ?></div>
                  <?php
                  $selected = (isset($_REQUEST['addfolderrole'])) ? explode('|', $_REQUEST['addfolderrole']) : array('administrator', 'author', 'contributor', 'editor');
                  wp_roles_checkbox('UseyourDrive_addfolder_role', $selected);
                  ?>
                </div>
              </div>
              <!-- End Permissions Tab -->

            </div>
            <?php
        }
        ?>

        <div class="footer">

        </div>
      </div>
    </form>
  </body>
</html>