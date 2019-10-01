<?php
$page = isset($_GET["page"]) ? '?page=' . $_GET["page"] : '';
$location = get_admin_url(null, 'admin.php' . $page);
$admin_nonce = wp_create_nonce("useyourdrive-admin-action");

function wp_roles_checkbox($name, $selected = array(), $always_include_admin = true) {
    global $wp_roles;
    if (!isset($wp_roles)) {
        $wp_roles = new \WP_Roles();
    }

    $roles = $wp_roles->get_names();

    if ($always_include_admin && !in_array('administrator', $selected)) {
        $selected[] = 'administrator';
    }

    foreach ($roles as $role_value => $role_name) {
        if (in_array($role_value, $selected)) {
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }

        $checkbox = '<div class="useyourdrive-option-checkbox">';
        $checkbox .= '<input class="simple" type="checkbox" name="' . $name . '[]" value="' . $role_value . '" ' . $checked . '>';
        $checkbox .= '<label for="userfolders_method_auto1" class="useyourdrive-option-checkbox-label">' . $role_name . '</label>';
        $checkbox .= '</div>';

        if ($always_include_admin && $role_value === 'administrator') {
            $checkbox .= sprintf("<div style='display:none'> %s </div>", $checkbox);
        }

        echo $checkbox;
    }
}

function create_color_boxes_table($colors, $settings) {

    if (count($colors) === 0) {
        return '';
    }

    $table_html = '<table class="color-table">';

    foreach ($colors as $color_id => $color) {

        $value = isset($settings['colors'][$color_id]) ? sanitize_text_field($settings['colors'][$color_id]) : $color['default'];

        $table_html .= '<tr>';
        $table_html .= "<td>{$color['label']}</td>";
        $table_html .= "<td><input value='$value' data-default-color='{$color['default']}'  name='use_your_drive_settings[colors][$color_id]' id='colors-$color_id' type='text'  class='useyourdrive-color-picker' data-alpha='true' ></td>";
        $table_html .= '</tr>';
    }

    $table_html .= '</table>';
    return $table_html;
}

function create_upload_button_for_custom_images($option) {

    $field_value = $option['value'];
    $button_html = '<div class="upload_row">';

    $button_html .= '<div class="screenshot" id="' . $option['id'] . '_image">' . "\n";

    if ('' !== $field_value) {
        $button_html .= '<img src="' . $field_value . '" alt="" />' . "\n";
        $button_html .= '<a href="javascript:void(0)" class="upload-remove">' . __('Remove Media', 'useyourdrive') . '</a>' . "\n";
    }

    $button_html .= '</div>';

    $button_html .= '<input id="' . esc_attr($option['id']) . '" class="upload useyourdrive-option-input-large" type="text" name="' . esc_attr($option['name']) . '" value="' . esc_attr($field_value) . '" autocomplete="off" />';
    $button_html .= '<input id="upload_image_button" class="upload_button simple-button blue" type="button" value="' . __('Select Image', 'useyourdrive') . '" title="' . __('Upload or select a file from the media library', 'useyourdrive') . '" />';

    if ($field_value !== $option['default']) {
        $button_html .= '<input id="default_image_button" class="default_image_button simple-button" type="button" value="' . __('Default', 'useyourdrive') . '" title="' . __('Fallback to the default value', 'useyourdrive') . '"  data-default="' . $option['default'] . '"/>';
    }

    $button_html .= '</div>' . "\n";

    return $button_html;
}
?>

<div class="useyourdrive admin-settings">
  <form id="useyourdrive-options" method="post" action="options.php">
    <?php wp_nonce_field('update-options'); ?>
    <?php settings_fields('use_your_drive_settings'); ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="use_your_drive_settings[purcase_code]" id="purcase_code" value="<?php echo esc_attr($this->settings['purcase_code']); ?>">

    <div class="wrap">
      <div class="useyourdrive-header">
        <div class="useyourdrive-logo"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/logo64x64.png" height="64" width="64"/></div>
        <div class="useyourdrive-form-buttons"> <div id="save_settings" class="simple-button default save_settings" name="save_settings"><?php _e("Save Settings", 'useyourdrive'); ?>&nbsp;<div class='uyd-spinner'></div></div></div>
        <div class="useyourdrive-title">Use-your-Drive <?php _e('Settings', 'useyourdrive'); ?></div>
      </div>


      <div id="" class="useyourdrive-panel useyourdrive-panel-left">      
        <div class="useyourdrive-nav-header"><?php _e('Settings', 'useyourdrive'); ?></div>

        <ul class="useyourdrive-nav-tabs">
          <li id="settings_general_tab" data-tab="settings_general" class="current"><a ><?php _e('General', 'useyourdrive'); ?></a></li>
          <li id="settings_layout_tab" data-tab="settings_layout" ><a ><?php _e('Layout', 'useyourdrive'); ?></a></li>
          <li id="settings_userfolders_tab" data-tab="settings_userfolders" ><a ><?php _e('Private Folders', 'useyourdrive'); ?></a></li>
          <li id="settings_advanced_tab" data-tab="settings_advanced" ><a ><?php _e('Advanced', 'useyourdrive'); ?></a></li>
          <li id="settings_notifications_tab" data-tab="settings_notifications" ><a ><?php _e('Notifications', 'useyourdrive'); ?></a></li>
          <li id="settings_permissions_tab" data-tab="settings_permissions" ><a><?php _e('Permissions', 'useyourdrive'); ?></a></li>
          <li id="settings_stats_tab" data-tab="settings_stats" ><a><?php _e('Statistics', 'useyourdrive'); ?></a></li>
          <li id="settings_system_tab" data-tab="settings_system" ><a><?php _e('System information', 'useyourdrive'); ?></a></li>
          <li id="settings_help_tab" data-tab="settings_help" ><a><?php _e('Need help?', 'useyourdrive'); ?></a></li>
        </ul>

        <div class="useyourdrive-nav-header" style="margin-top: 50px;"><?php _e('Other Cloud Plugins', 'useyourdrive'); ?></div>
        <ul class="useyourdrive-nav-tabs">
          <li id="settings_help_tab" data-tab="settings_help"><a href="https://1.envato.market/c/1260925/275988/4415?u=https%3A%2F%2Fcodecanyon.net%2Fitem%2Foutofthebox-dropbox-plugin-for-wordpress-%2F5529125" target="_blank" style="color:#0078d7;">Dropbox <i class="fas fa-external-link-square-alt" aria-hidden="true"></i></a></li>
          <li id="settings_help_tab" data-tab="settings_help"><a href="https://1.envato.market/c/1260925/275988/4415?u=https%3A%2F%2Fcodecanyon.net%2Fitem%2Fshareonedrive-onedrive-plugin-for-wordpress%2F11453104" target="_blank" style="color:#0078d7;">OneDrive <i class="fas fa-external-link-square-alt" aria-hidden="true"></i></a></li>
          <li id="settings_help_tab" data-tab="settings_help"><a href="https://1.envato.market/c/1260925/275988/4415?u=https%3A%2F%2Fcodecanyon.net%2Fitem%2Fletsbox-box-plugin-for-wordpress%2F8204640" target="_blank" style="color:#0078d7;">Box <i class="fas fa-external-link-square-alt" aria-hidden="true"></i></a></li>
        </ul> 

        <div class="useyourdrive-nav-footer"><a href="<?php echo admin_url('update-core.php'); ?>"><?php _e('Version', 'useyourdrive'); ?>: <?php echo USEYOURDRIVE_VERSION; ?></a></div>
      </div>


      <div class="useyourdrive-panel useyourdrive-panel-right">

        <!-- General Tab -->
        <div id="settings_general" class="useyourdrive-tab-panel current">

          <div class="useyourdrive-tab-panel-header"><?php _e('General', 'useyourdrive'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Authorization', 'useyourdrive'); ?></div>
          <?php
          echo $this->get_plugin_authorization_box();
          ?>
          <div class="useyourdrive-option-title"><?php _e('Plugin License', 'useyourdrive'); ?></div>
          <?php
          echo $this->get_plugin_activated_box();
          ?>
        </div>
        <!-- End General Tab -->


        <!-- Layout Tab -->
        <div id="settings_layout"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Layout', 'useyourdrive'); ?></div>

          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Loading Spinner & Images', 'useyourdrive'); ?>         </div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Select Loader Spinner', 'useyourdrive'); ?></div>
              <select type="text" name="use_your_drive_settings[loaders][style]" id="loader_style">
                <option value="beat" <?php echo ($this->settings['loaders']['style'] === "beat" ? "selected='selected'" : ''); ?>><?php _e('Beat', 'useyourdrive'); ?></option>
                <option value="spinner" <?php echo ($this->settings['loaders']['style'] === "spinner" ? "selected='selected'" : ''); ?>><?php _e('Spinner', 'useyourdrive'); ?></option>
                <option value="custom" <?php echo ($this->settings['loaders']['style'] === "custom" ? "selected='selected'" : ''); ?>><?php _e('Custom Image (selected below)', 'useyourdrive'); ?></option>
              </select>

              <div class="useyourdrive-option-title"><?php _e('General Loader', 'useyourdrive'); ?></div>
              <?php
              $button = array('value' => $this->settings['loaders']['loading'], 'id' => 'loaders_loading', 'name' => 'use_your_drive_settings[loaders][loading]', 'default' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_loading.gif');
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('Upload Loader', 'useyourdrive'); ?></div>
              <?php
              $button = array('value' => $this->settings['loaders']['upload'], 'id' => 'loaders_upload', 'name' => 'use_your_drive_settings[loaders][upload]', 'default' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_upload.gif');
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('No Results', 'useyourdrive'); ?></div>
              <?php
              $button = array('value' => $this->settings['loaders']['no_results'], 'id' => 'loaders_no_results', 'name' => 'use_your_drive_settings[loaders][no_results]', 'default' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_no_results.png');
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('Access Forbidden Image', 'useyourdrive'); ?></div>
              <?php
              $button = array('value' => $this->settings['loaders']['protected'], 'id' => 'loaders_protected', 'name' => 'use_your_drive_settings[loaders][protected]', 'default' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_protected.png');
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('Error Image', 'useyourdrive'); ?></div>
              <?php
              $button = array('value' => $this->settings['loaders']['error'], 'id' => 'loaders_error', 'name' => 'use_your_drive_settings[loaders][error]', 'default' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_error.png');
              echo create_upload_button_for_custom_images($button);
              ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Color Palette', 'useyourdrive'); ?></div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Content Skin', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e("Select the general content skin", 'useyourdrive'); ?>.</div>
              <select name="skin_selectbox" id="content_skin_selectbox" class="ddslickbox">
                <option value="dark" <?php echo ($this->settings['colors']['style'] === "dark" ? "selected='selected'" : ''); ?> data-imagesrc="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/skin-dark.png" data-description=""><?php _e('Dark', 'useyourdrive'); ?></option>
                <option value="light" <?php echo ($this->settings['colors']['style'] === "light" ? "selected='selected'" : ''); ?> data-imagesrc="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/skin-light.png" data-description=""><?php _e('Light', 'useyourdrive'); ?></option>
              </select>
              <input type="hidden" name="use_your_drive_settings[colors][style]" id="content_skin" value="<?php echo esc_attr($this->settings['colors']['style']); ?>">

              <?php
              $colors = array(
                  'background' => array(
                      'label' => __('Content Background Color', 'useyourdrive'),
                      'default' => '#f2f2f2'
                  ),
                  'accent' => array(
                      'label' => __('Accent Color', 'useyourdrive'),
                      'default' => '#29ADE2'
                  ),
                  'black' => array(
                      'label' => __('Black', 'useyourdrive'),
                      'default' => '#222'
                  ),
                  'dark1' => array(
                      'label' => __('Dark 1', 'useyourdrive'),
                      'default' => '#666666'
                  ),
                  'dark2' => array(
                      'label' => __('Dark 2', 'useyourdrive'),
                      'default' => '#999999'
                  ),
                  'white' => array(
                      'label' => __('White', 'useyourdrive'),
                      'default' => '#fff'
                  ),
                  'light1' => array(
                      'label' => __('Light 1', 'useyourdrive'),
                      'default' => '#fcfcfc'
                  ),
                  'light2' => array(
                      'label' => __('Light 2', 'useyourdrive'),
                      'default' => '#e8e8e8'
                  )
              );

              echo create_color_boxes_table($colors, $this->settings);
              ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Icons', 'useyourdrive'); ?></div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Icon Set', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e(sprintf("Location to the icon set you want to use. When you want to use your own set, just make a copy of the default icon set folder (<code>%s</code>) and place it in the <code>wp-content/</code> folder", USEYOURDRIVE_ROOTPATH . '/css/icons/'), 'useyourdrive'); ?>.</div>

              <div class="uyd-warning">
                <i><strong><?php _e('NOTICE', 'useyourdrive'); ?></strong>: <?php _e('Modifications to the default icons set will be lost during an update.', 'useyourdrive'); ?>.</i>
              </div>

              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[icon_set]" id="icon_set" value="<?php echo esc_attr($this->settings['icon_set']); ?>">  
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Lightbox', 'useyourdrive'); ?></div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Lightbox Skin', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Select which skin you want to use for the lightbox', 'useyourdrive'); ?>.</div>
              <select name="lightbox_skin_selectbox" id="lightbox_skin_selectbox" class="ddslickbox">
                <?php
                foreach (new DirectoryIterator(USEYOURDRIVE_ROOTDIR . '/includes/iLightBox/') as $fileInfo) {
                    if ($fileInfo->isDir() && !$fileInfo->isDot() && (strpos($fileInfo->getFilename(), 'skin') !== false)) {
                        if (file_exists(USEYOURDRIVE_ROOTDIR . '/includes/iLightBox/' . $fileInfo->getFilename() . '/skin.css')) {
                            $selected = '';
                            $skinname = str_replace('-skin', '', $fileInfo->getFilename());

                            if ($skinname === $this->settings['lightbox_skin']) {
                                $selected = 'selected="selected"';
                            }

                            $icon = file_exists(USEYOURDRIVE_ROOTDIR . '/includes/iLightBox/' . $fileInfo->getFilename() . '/thumb.jpg') ? USEYOURDRIVE_ROOTPATH . '/includes/iLightBox/' . $fileInfo->getFilename() . '/thumb.jpg' : '';
                            echo '<option value="' . $skinname . '" data-imagesrc="' . $icon . '" data-description="" ' . $selected . '>' . $fileInfo->getFilename() . "</option>\n";
                        }
                    }
                }
                ?>
              </select>
              <input type="hidden" name="use_your_drive_settings[lightbox_skin]" id="lightbox_skin" value="<?php echo esc_attr($this->settings['lightbox_skin']); ?>">


              <div class="useyourdrive-option-title"><?php _e('Lightbox Scroll', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e("Sets path for switching windows. Possible values are 'vertical' and 'horizontal' and the default is 'vertical", 'useyourdrive'); ?>.</div>
              <select type="text" name="use_your_drive_settings[lightbox_path]" id="lightbox_path">
                <option value="horizontal" <?php echo ($this->settings['lightbox_path'] === "horizontal" ? "selected='selected'" : ''); ?>><?php _e('Horizontal', 'useyourdrive'); ?></option>
                <option value="vertical" <?php echo ($this->settings['lightbox_path'] === "vertical" ? "selected='selected'" : ''); ?>><?php _e('Vertical', 'useyourdrive'); ?></option>
              </select>

              <div class="useyourdrive-option-title"><?php _e('Lightbox Image Source', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e("Select the source of the images. Large thumbnails load fast, orignal files will take some time to load", 'useyourdrive'); ?>.</div>
              <select type="text" name="use_your_drive_settings[loadimages]" id="loadimages">
                <option value="googlethumbnail" <?php echo ($this->settings['loadimages'] === "googlethumbnail" ? "selected='selected'" : ''); ?>><?php _e('Fast - Large preview thumbnails', 'useyourdrive'); ?></option>
                <option value="original" <?php echo ($this->settings['loadimages'] === "original" ? "selected='selected'" : ''); ?>><?php _e('Slow - Show orginal files', 'useyourdrive'); ?></option>
              </select>

              <div class="useyourdrive-option-title"><?php _e('Allow Mouse Click on Image', 'useyourdrive'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[lightbox_rightclick]'/>
                  <input type="checkbox" name="use_your_drive_settings[lightbox_rightclick]" id="lightbox_rightclick" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['lightbox_rightclick'] === "Yes") ? 'checked="checked"' : ''; ?>/>
                  <label class="useyourdrive-onoffswitch-label" for="lightbox_rightclick"></label>
                </div>
              </div>
              <div class="useyourdrive-option-description"><?php _e("Should people be able to access the right click context menu to e.g. save the image?", 'useyourdrive'); ?>.</div>

              <div class="useyourdrive-option-title"><?php _e('Lightbox Caption', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e("Choose when the caption containing the title and (if available) description are shown", 'useyourdrive'); ?>.</div>
              <select type="text" name="use_your_drive_settings[lightbox_showcaption]" id="lightbox_showcaption">
                <option value="click" <?php echo ($this->settings['lightbox_showcaption'] === "click" ? "selected='selected'" : ''); ?>><?php _e('Show caption after clicking on the Lightbox', 'useyourdrive'); ?></option>
                <option value="mouseenter" <?php echo ($this->settings['lightbox_showcaption'] === "mouseenter" ? "selected='selected'" : ''); ?>><?php _e('Show caption when Lightbox opens', 'useyourdrive'); ?></option>
              </select>              



            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Media Player Skin', 'useyourdrive'); ?></div>
            <div>
              <div class="useyourdrive-option-description"><?php _e("Select which skin you want to use for the Media Player", 'useyourdrive'); ?>.</div>
              <select name="mediaplayer_skin_selectbox" id="mediaplayer_skin_selectbox" class="ddslickbox">
                <?php
                foreach (new DirectoryIterator(USEYOURDRIVE_ROOTDIR . '/skins/') as $fileInfo) {
                    if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                        if (file_exists(USEYOURDRIVE_ROOTDIR . '/skins/' . $fileInfo->getFilename() . '/Media.js')) {
                            $selected = '';
                            if ($fileInfo->getFilename() === $this->settings['mediaplayer_skin']) {
                                $selected = 'selected="selected"';
                            }

                            $icon = file_exists(USEYOURDRIVE_ROOTDIR . '/skins/' . $fileInfo->getFilename() . '/thumb.jpg') ? USEYOURDRIVE_ROOTPATH . '/skins/' . $fileInfo->getFilename() . '/thumb.jpg' : '';
                            echo '<option value="' . $fileInfo->getFilename() . '" data-imagesrc="' . $icon . '" data-description="" ' . $selected . '>' . $fileInfo->getFilename() . "</option>\n";
                        }
                    }
                }
                ?>
              </select>
              <input type="hidden" name="use_your_drive_settings[mediaplayer_skin]" id="mediaplayer_skin" value="<?php echo esc_attr($this->settings['mediaplayer_skin']); ?>">
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Custom CSS', 'useyourdrive'); ?></div>
            <div>
              <div class="useyourdrive-option-description"><?php _e("If you want to modify the looks of the plugin slightly, you can insert here your custom CSS. Don't edit the CSS files itself, because those modifications will be lost during an update.", 'useyourdrive'); ?>.</div>
              <textarea name="use_your_drive_settings[custom_css]" id="custom_css" cols="" rows="10"><?php echo esc_attr($this->settings['custom_css']); ?></textarea> 
            </div>
          </div>

        </div>
        <!-- End Layout Tab -->

        <!-- UserFolders Tab -->
        <div id="settings_userfolders"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Private Folders', 'useyourdrive'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Create Private Folders on registration', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[userfolder_oncreation]'/>
              <input type="checkbox" name="use_your_drive_settings[userfolder_oncreation]" id="userfolder_oncreation" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['userfolder_oncreation'] === "Yes") ? 'checked="checked"' : ''; ?>/>
              <label class="useyourdrive-onoffswitch-label" for="userfolder_oncreation"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e("Create a new Private Folders automatically after a new user has been created", 'useyourdrive'); ?>.</div>

          <div class="useyourdrive-option-title"><?php _e('Create all Private Folders on first visit', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[userfolder_onfirstvisit]'/>
              <input type="checkbox" name="use_your_drive_settings[userfolder_onfirstvisit]" id="userfolder_onfirstvisit" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['userfolder_onfirstvisit'] === "Yes") ? 'checked="checked"' : ''; ?>/>
              <label class="useyourdrive-onoffswitch-label" for="userfolder_onfirstvisit"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e("Create all Private Folders the first time the page with the shortcode is visited", 'useyourdrive'); ?>.</div>
          <div class="uyd-warning">
            <i><strong><?php _e('NOTICE', 'useyourdrive'); ?></strong>: ><?php _e("Creating User Folders takes around 1 sec per user, so it isn't recommended to create those on first visit when you have tons of users", 'useyourdrive'); ?>.</i>
          </div>


          <div class="useyourdrive-option-title"><?php _e('Update Private Folders after profile update', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[userfolder_update]'/>
              <input type="checkbox" name="use_your_drive_settings[userfolder_update]" id="userfolder_update" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['userfolder_update'] === "Yes") ? 'checked="checked"' : ''; ?>/>
              <label class="useyourdrive-onoffswitch-label" for="userfolder_update"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e("Update the folder name of the user after they have updated their profile", 'useyourdrive'); ?>.</div>

          <div class="useyourdrive-option-title"><?php _e('Remove Private Folders after account removal', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[userfolder_remove]'/>
              <input type="checkbox" name="use_your_drive_settings[userfolder_remove]" id="userfolder_remove" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['userfolder_remove'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="userfolder_remove"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e("Try to remove Private Folders after they are deleted", 'useyourdrive'); ?>.</div>

          <div class="useyourdrive-option-title"><?php _e('Private Folders in Back-End', 'useyourdrive'); ?></div>
          <div class="useyourdrive-option-description"><?php _e("Enables Private Folders in the Shortcode Builder and Back-End File Browser", 'useyourdrive'); ?>.</div>
          <select type="text" name="use_your_drive_settings[userfolder_backend]" id="userfolder_backend" data-div-toggle="private-folders-auto" data-div-toggle-value="auto">
            <option value="No" <?php echo ($this->settings['userfolder_backend'] === "No" ? "selected='selected'" : ''); ?>>No</option>
            <option value="manual" <?php echo ($this->settings['userfolder_backend'] === "manual" ? "selected='selected'" : ''); ?>><?php _e('Yes, I link the users Manually', 'useyourdrive'); ?></option>
            <option value="auto" <?php echo ($this->settings['userfolder_backend'] === "auto" ? "selected='selected'" : ''); ?>><?php _e('Yes, let the plugin create the User Folders for me', 'useyourdrive'); ?></option>
          </select>
          <div class="uyd-warning">
            <i><strong>NOTICE</strong>: <?php _e("This setting only restrict access of the File Browsers in the Admin Dashboard (e.g. the one in the Shortcode Builder). To enable Private Folders for your own Shortcodes, use the Shortcode Builder", 'useyourdrive'); ?>. </i>
          </div>

          <?php
          if ($this->get_app()->has_access_token()) {
              try {
                  $this->get_app()->start_client();
                  $rootfolder = $this->get_processor()->get_client()->get_root_folder();
                  ?>
                  <div class="useyourdrive-suboptions private-folders-auto <?php echo (($this->settings['userfolder_backend']) === 'auto') ? '' : 'hidden' ?> ">
                    <div class="useyourdrive-option-title"><?php _e('Root folder for Private Folders', 'useyourdrive'); ?></div>
                    <div class="useyourdrive-option-description"><?php _e("Select in which folder the Private Folders should be created", 'useyourdrive'); ?>. <?php _e('Current selected folder', 'useyourdrive'); ?>:</div>
                    <?php
                    $private_auto_folder = $this->settings['userfolder_backend_auto_root'];

                    if (empty($private_auto_folder)) {
                        $root = $this->get_processor()->get_client()->get_root_folder();
                        $private_auto_folder = array();
                        $private_auto_folder['id'] = $root->get_entry()->get_id();
                        $private_auto_folder['name'] = $root->get_entry()->get_name();
                        $private_auto_folder['view_roles'] = array('administrator');
                    }
                    ?>
                    <input class="useyourdrive-option-input-large private-folders-auto-current" type="text" value="<?php echo $private_auto_folder['name']; ?>" disabled="disabled">
                    <input class="private-folders-auto-input-id" type='hidden' value='<?php echo $private_auto_folder['id']; ?>' name='use_your_drive_settings[userfolder_backend_auto_root][id]'/>
                    <input class="private-folders-auto-input-name" type='hidden' value='<?php echo $private_auto_folder['name']; ?>' name='use_your_drive_settings[userfolder_backend_auto_root][name]'/>
                    <div id="root_folder_button" type="button" class="button-primary private-folders-auto-button"><?php _e('Select Folder', 'useyourdrive'); ?>&nbsp;<div class='uyd-spinner'></div></div>

                    <div id='uyd-embedded' style='clear:both;display:none'>
                      <?php
                      echo $this->get_processor()->create_from_shortcode(
                              array('mode' => 'files',
                                  'showfiles' => '1',
                                  'filesize' => '0',
                                  'filedate' => '0',
                                  'upload' => '0',
                                  'delete' => '0',
                                  'rename' => '0',
                                  'addfolder' => '0',
                                  'showbreadcrumb' => '1',
                                  'showcolumnnames' => '0',
                                  'showfiles' => '0',
                                  'downloadrole' => 'none',
                                  'candownloadzip' => '0',
                                  'showsharelink' => '0',
                                  'mcepopup' => 'linktobackendglobal',
                                  'search' => '0'));
                      ?>
                    </div>

                    <br/><br/>
                    <div class="useyourdrive-option-title"><?php _e('Full Access', 'useyourdrive'); ?></div>
                    <div class="useyourdrive-option-description"><?php _e('By default only Administrator users will be able to navigate through all Private Folders', 'useyourdrive'); ?>. <?php _e('When you want other User Roles to be able do browse to the Private Folders as well, please check them below', 'useyourdrive'); ?>.</div>

                    <?php
                    $selected = (isset($private_auto_folder['view_roles'])) ? $private_auto_folder['view_roles'] : array();
                    wp_roles_checkbox('use_your_drive_settings[userfolder_backend_auto_root][view_roles]', $selected, false);
                    ?>
                  </div>
                  <?php
              } catch (\Exception $ex) {
                  
              }
          }
          ?>

          <div class="useyourdrive-option-title"><?php _e('Name Template', 'useyourdrive'); ?></div>
          <div class="useyourdrive-option-description"><?php _e("Template name for automatically created Private Folders. You can use <code>%user_login%</code>, <code>%user_email%</code>, <code>%display_name%</code>, <code>%ID%</code>, <code>%user_role%</code>, <code>%jjjj-mm-dd%</code>", 'useyourdrive'); ?>.</div>
          <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[userfolder_name]" id="userfolder_name" value="<?php echo esc_attr($this->settings['userfolder_name']); ?>">

        </div>
        <!-- End UserFolders Tab -->


        <!--  Advanced Tab -->
        <div id="settings_advanced"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Advanced', 'useyourdrive'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('"Lost Authorization" notification', 'useyourdrive'); ?></div>
          <div class="useyourdrive-option-description"><?php _e('If the plugin somehow loses its authorization, a notification email will be send to the following email address', 'useyourdrive'); ?>:</div>
          <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[lostauthorization_notification]" id="lostauthorization_notification" value="<?php echo esc_attr($this->settings['lostauthorization_notification']); ?>">  

          <div class="useyourdrive-option-title"><?php _e('Own Google App', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[googledrive_app_own]'/>
              <input type="checkbox" name="use_your_drive_settings[googledrive_app_own]" id="googledrive_app_own" class="useyourdrive-onoffswitch-checkbox" <?php echo (empty($this->settings['googledrive_app_client_id']) || empty($this->settings['googledrive_app_client_secret'])) ? '' : 'checked="checked"'; ?> data-div-toggle="own-app"/>
              <label class="useyourdrive-onoffswitch-label" for="googledrive_app_own"></label>
            </div>
          </div>

          <div class="useyourdrive-suboptions own-app <?php echo (empty($this->settings['googledrive_app_client_id']) || empty($this->settings['googledrive_app_client_secret'])) ? 'hidden' : '' ?> ">
            <div class="useyourdrive-option-description">
              <strong>Using your own Google App is <u>optional</u></strong>. For an easy setup you can just use the default App of the plugin itself by leaving the ID and Secret empty. The advantage of using your own app is limited. If you decided to create your own Google App anyway, please enter your settings. In the <a href="https://florisdeleeuwnl.zendesk.com/hc/en-us/articles/201804806--How-do-I-create-my-own-Google-Drive-App-" target="_blank">documentation</a> you can find how you can create a Google App.
              <br/><br/>
              <div class="uyd-warning">
                <i><strong><?php _e('NOTICE', 'useyourdrive'); ?></strong>: <?php _e('If you encounter any issues when trying to use your own App with Use-your-Drive, please fall back on the default App by disabling this setting', 'useyourdrive'); ?>.</i>
              </div>
            </div>

            <div class="useyourdrive-option-title"><?php _e('Google Client ID', 'useyourdrive'); ?></div>
            <div class="useyourdrive-option-description"><?php _e('<strong>Only</strong> if you want to use your own App, insert your Google App  Client ID here', 'useyourdrive'); ?>.</div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[googledrive_app_client_id]" id="googledrive_app_client_id" value="<?php echo esc_attr($this->settings['googledrive_app_client_id']); ?>" placeholder="<--- <?php _e('Leave empty for easy setup', 'useyourdrive') ?> --->" >

            <div class="useyourdrive-option-title"><?php _e('Google Client Secret', 'useyourdrive'); ?></div>
            <div class="useyourdrive-option-description"><?php _e('If you want to use your own App, insert your Google App Client secret here', 'useyourdrive'); ?>.</div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[googledrive_app_client_secret]" id="googledrive_app_client_secret" value="<?php echo esc_attr($this->settings['googledrive_app_client_secret']); ?>" placeholder="<--- <?php _e('Leave empty for easy setup', 'useyourdrive') ?> --->" >   

            <div>
              <div class="useyourdrive-option-title"><?php _e('OAuth 2.0 Redirect URI', 'useyourdrive'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Set the redirect URI in your application to the following', 'useyourdrive'); ?>:</div>
              <code style="user-select:initial">
                <?php
                if ($this->get_app()->has_plugin_own_app()) {
                    echo $this->get_app()->get_redirect_uri();
                } else {
                    _e('Enter Client ID and Secret, save settings and reload the page to see the Redirect URI you will need', 'useyourdrive');
                }
                ?>
              </code>
            </div>
          </div>

          <?php
          $using_gsuite = (!empty($this->settings['permission_domain']) || $this->settings['teamdrives'] === "Yes");
          ?>

          <div class="useyourdrive-option-title"><?php _e('Using Google G Suite?', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[gsuite]'/>
              <input type="checkbox" name="use_your_drive_settings[gsuite]" id="gsuite" class="useyourdrive-onoffswitch-checkbox" <?php echo ($using_gsuite) ? 'checked="checked"' : ''; ?> data-div-toggle="gsuite"/>
              <label class="useyourdrive-onoffswitch-label" for="gsuite"></label>
            </div>
          </div>

          <div class="useyourdrive-suboptions gsuite <?php echo ($using_gsuite) ? '' : 'hidden' ?> ">
            <div class="useyourdrive-option-title"><?php _e('Your Google G Suite Domain', 'useyourdrive'); ?></div>
            <div class="useyourdrive-option-description"><?php _e('If you have a Google G Suite Domain and you want to share your documents ONLY with users having an account in your G Suite Domain, please insert your domain. If you want your documents to be accessible to the public, leave this setting empty.', 'useyourdrive'); ?>.</div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[permission_domain]" id="permission_domain" value="<?php echo esc_attr($this->settings['permission_domain']); ?>">   

            <div class="useyourdrive-option-title"><?php _e('Enable Team Drives', 'useyourdrive'); ?>
              <div class="useyourdrive-onoffswitch">
                <input type='hidden' value='No' name='use_your_drive_settings[teamdrives]'/>
                <input type="checkbox" name="use_your_drive_settings[teamdrives]" id="teamdrives" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['teamdrives'] === "Yes") ? 'checked="checked"' : ''; ?> />
                <label class="useyourdrive-onoffswitch-label" for="teamdrives"></label>
              </div>
            </div>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Manage Permission', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[manage_permissions]'/>
              <input type="checkbox" name="use_your_drive_settings[manage_permissions]" id="manage_permissions" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['manage_permissions'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="manage_permissions"></label>
            </div>
            <div class="useyourdrive-option-description"><?php _e('If you want to manage the sharing permissions by yourself or if you want users to login to Google, disabled the -Manage Permissions- function.', 'useyourdrive'); ?>.</div>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Load Javascripts on all pages', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[always_load_scripts]'/>
              <input type="checkbox" name="use_your_drive_settings[always_load_scripts]" id="always_load_scripts" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['always_load_scripts'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="always_load_scripts"></label>
            </div>
            <div class="useyourdrive-option-description"><?php _e('By default the plugin will only load it scripts when the shortcode is present on the page. If you are dynamically loading content via AJAX calls and the plugin does not show up, please enable this setting', 'useyourdrive'); ?>.</div>
          </div>


          <div class="useyourdrive-option-title"><?php _e('Enable Gzip compression', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[gzipcompression]'/>
              <input type="checkbox" name="use_your_drive_settings[gzipcompression]" id="gzipcompression" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['gzipcompression'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="gzipcompression"></label>
            </div>
          </div>

          <div class="useyourdrive-option-description"><?php _e("Enables gzip-compression if the visitor's browser can handle it. This will increase the performance of the plugin if you are displaying large amounts of files and it reduces bandwidth usage as well. It uses the PHP <code>ob_gzhandler()</code> callback. Please use this setting with caution. Always test if the plugin still works on the Front-End as some servers are already configured to gzip content!", 'useyourdrive'); ?></div>

          <div class="option"  style="display:none">
            <select type="text" name="use_your_drive_settings[cache]" id="cache">
              <option value="filesystem" <?php echo ($this->settings['cache'] === "filesystem" ? "selected='selected'" : ''); ?>><?php _e('File Based Cache', 'useyourdrive'); ?></option>
              <option value="database" <?php echo ($this->settings['cache'] === "database" ? "selected='selected'" : ''); ?>><?php _e('Database Based Cache', 'useyourdrive'); ?></option>
            </select>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Nonce Validation', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[nonce_validation]'/>
              <input type="checkbox" name="use_your_drive_settings[nonce_validation]" id="nonce_validation" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['nonce_validation'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="nonce_validation"></label>
            </div></div>
          <div class="useyourdrive-option-description"><?php _e('The plugin uses, among others, the WordPress Nonce system to protect you against several types of attacks including CSRF. Disable this in case you are encountering a conflict with a plugin that alters this system', 'useyourdrive'); ?>. </div>
          <div class="uyd-warning">
            <i><strong>NOTICE</strong>: Please use this setting with caution!</i>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Syncronize via WP-Cron', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[cache_update_via_wpcron]'/>
              <input type="checkbox" name="use_your_drive_settings[cache_update_via_wpcron]" id="cache_update_via_wpcron" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['cache_update_via_wpcron'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="cache_update_via_wpcron"></label>
            </div></div>
          <div class="useyourdrive-option-description"><?php _e('Use WP-Cron to synchronize the cache of the plugin with the linked cloud account. If you are using tens of shortcodes and encounter performance issues, try to disable this setting', 'useyourdrive'); ?>. </div>
          <div class="uyd-updated">
            <i><strong>TIP</strong>: <?php echo __('As WP-Cron does not run continuously, you can increase the loading performance by creating a Cron job via your server configuration panel!', 'useyourdrive'); ?> <a href="https://developer.wordpress.org/plugins/cron/hooking-into-the-system-task-scheduler/" target="_blank"><?php echo __('More information'); ?></a></i>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Download method', 'useyourdrive'); ?></div>
          <div class="useyourdrive-option-description"><?php _e('Select the method that should be used to download your files. Default is to redirect the user to a temporarily url on Google Server. If you want to use your server as a proxy to the Google Server just set it to Download via Server', 'useyourdrive'); ?>.</div>
          <select type="text" name="use_your_drive_settings[download_method]" id="download_method">
            <option value="redirect" <?php echo ($this->settings['download_method'] === "redirect" ? "selected='selected'" : ''); ?>><?php _e('Redirect to download url (fast)', 'useyourdrive'); ?></option>
            <option value="proxy" <?php echo ($this->settings['download_method'] === "proxy" ? "selected='selected'" : ''); ?>><?php _e('Use your Server as proxy (slow)', 'useyourdrive'); ?></option>
          </select>   

          <div class="useyourdrive-option-title"><?php _e('Shortlinks API', 'useyourdrive'); ?></div>
          <div class="useyourdrive-option-description"><?php _e('Select which Url Shortener Service you want to use', 'useyourdrive'); ?>.</div>
          <select type="text" name="use_your_drive_settings[shortlinks]" id="shortlinks">
            <option value="None"  <?php echo ($this->settings['shortlinks'] === "None" ? "selected='selected'" : ''); ?>>None</option>
            <option value="Google"  <?php echo ($this->settings['shortlinks'] === "Google" ? "selected='selected'" : ''); ?>>Google Urlshortener (Deprecated since April 13, 2018)</option>
            <!-- <option value="Firebase"  <?php echo ($this->settings['shortlinks'] === "Firebase" ? "selected='selected'" : ''); ?>>Google Firebase Dynamic Links</option> -->
            <option value="Shorte.st"  <?php echo ($this->settings['shortlinks'] === "Shorte.st" ? "selected='selected'" : ''); ?>>Shorte.st</option>
            <option value="Rebrandly"  <?php echo ($this->settings['shortlinks'] === "Rebrandly" ? "selected='selected'" : ''); ?>>Rebrandly</option>
            <option value="Bit.ly"  <?php echo ($this->settings['shortlinks'] === "Bit.ly" ? "selected='selected'" : ''); ?>>Bit.ly</option>
          </select>   

          <div class="useyourdrive-suboptions option shortest" <?php echo ($this->settings['shortlinks'] !== "Shorte.st" ? "style='display:none;'" : ''); ?>>
            <div class="useyourdrive-option-description"><?php _e('Sign up for Shorte.st', 'useyourdrive'); ?> and <a href="https://shorte<?php echo '.st/tools/api'; ?>" target="_blank">grab your API token</a></div>

            <div class="useyourdrive-option-title"><?php _e('API token', 'useyourdrive'); ?></div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[shortest_apikey]" id="shortest_apikey" value="<?php echo esc_attr($this->settings['shortest_apikey']); ?>">
          </div>

          <div class="useyourdrive-suboptions option bitly" <?php echo ($this->settings['shortlinks'] !== "Bit.ly" ? "style='display:none;'" : ''); ?>>
            <div class="useyourdrive-option-description"><a href="https://bitly.com/a/sign_up" target="_blank"><?php _e('Sign up for Bitly', 'useyourdrive'); ?></a> and <a href="https://bitly.com/a/oauth_apps" target="_blank">generate a Generic Access Token</a></div>

            <div class="useyourdrive-option-title"><?php _e('Bitly login', 'useyourdrive'); ?></div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[bitly_login]" id="bitly_login" value="<?php echo esc_attr($this->settings['bitly_login']); ?>">

            <div class="useyourdrive-option-title"><?php _e('Bitly apiKey', 'useyourdrive'); ?></div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[bitly_apikey]" id="bitly_apikey" value="<?php echo esc_attr($this->settings['bitly_apikey']); ?>">
          </div> 

          <div class="useyourdrive-suboptions option rebrandly" <?php echo ($this->settings['shortlinks'] !== "Rebrandly" ? "style='display:none;'" : ''); ?>>
            <div class="useyourdrive-option-description"><a href="https://app.rebrandly.com/" target="_blank"><?php _e('Sign up for Rebrandly', 'useyourdrive'); ?></a> and <a href="https://app.rebrandly.com/account/api-keys" target="_blank">grab your API token</a></div>

            <div class="useyourdrive-option-title"><?php _e('Rebrandly apiKey', 'useyourdrive'); ?></div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[rebrandly_apikey]" id="rebrandly_apikey" value="<?php echo esc_attr($this->settings['rebrandly_apikey']); ?>">

            <div class="useyourdrive-option-title"><?php _e('Rebrandly Domain (optional)', 'useyourdrive'); ?></div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[rebrandly_domain]" id="rebrandly_domain" value="<?php echo esc_attr($this->settings['rebrandly_domain']); ?>">

            <div class="useyourdrive-option-title"><?php _e('Rebrandly WorkSpace ID (optional)', 'useyourdrive'); ?></div>
            <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[rebrandly_workspace]" id="rebrandly_workspace" value="<?php echo esc_attr($this->settings['rebrandly_workspace']); ?>">
          </div> 

        </div>
        <!-- End Advanced Tab -->

        <!-- Notifications Tab -->
        <div id="settings_notifications"  class="useyourdrive-tab-panel">

          <div class="useyourdrive-tab-panel-header"><?php _e('Notifications', 'useyourdrive'); ?></div>


          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Download Notifications', 'useyourdrive'); ?>         </div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Subject download notification', 'useyourdrive'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[download_template_subject]" id="download_template_subject" value="<?php echo esc_attr($this->settings['download_template_subject']); ?>">
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>%number_of_files%</code>, <code>%visitor%</code>, <code>%user_email%</code>, <code>%ip%</code>, <code>%location%</code>, <code>%filename%</code>, <code>%filepath%</code>, <code>%folder%</code></div>

              <div class="useyourdrive-option-title"><?php _e('Subject zip notification', 'useyourdrive'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[download_template_subject_zip]" id="download_template_subject_zip" value="<?php echo esc_attr($this->settings['download_template_subject_zip']); ?>">
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>%number_of_files%</code>, <code>%visitor%</code>, <code>%user_email%</code>, <code>%ip%</code>, <code>%location%</code>, <code>%filename%</code>, <code>%filepath%</code>, <code>%folder%</code></div>

              <div class="useyourdrive-option-title"><?php _e('Template download', 'useyourdrive'); ?>:</div>
              <?php
              ob_start();
              wp_editor($this->settings['download_template'], 'use_your_drive_settings_download_template', array(
                  'textarea_name' => 'use_your_drive_settings[download_template]',
                  'teeny' => true,
                  'textarea_rows' => 15,
                  'media_buttons' => false
              ));
              echo ob_get_clean();
              ?>
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>%currenturl%</code>, <code>%filelist%</code>,  <code>%ip%</code>, <code>%location%</code></div>

            </div>


            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Upload Notifications', 'useyourdrive'); ?>         </div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Subject upload notification', 'useyourdrive'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[upload_template_subject]" id="upload_template_subject" value="<?php echo esc_attr($this->settings['upload_template_subject']); ?>">
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>%number_of_files%</code>, <code>%visitor%</code>, <code>%user_email%</code>, <code>%ip%</code>, <code>%location%</code>, <code>%filename%</code>, <code>%filepath%</code>, <code>%folder%</code></div>

              <div class="useyourdrive-option-title"><?php _e('Template upload', 'useyourdrive'); ?>:</div>
              <?php
              ob_start();
              wp_editor($this->settings['upload_template'], 'use_your_drive_settings_upload_template', array(
                  'textarea_name' => 'use_your_drive_settings[upload_template]',
                  'teeny' => true,
                  'textarea_rows' => 15,
                  'media_buttons' => false
              ));
              echo ob_get_clean();
              ?>
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>%currenturl%</code>, <code>%filelist%</code>,  <code>%ip%</code>, <code>%location%</code></div>

            </div>


            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Delete Notifications', 'useyourdrive'); ?>         </div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Subject deletion notification', 'useyourdrive'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[delete_template_subject]" id="delete_template_subject" value="<?php echo esc_attr($this->settings['delete_template_subject']); ?>">
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>"%number_of_files%</code>, <code>%visitor%</code>, <code>%user_email%</code>, <code>%ip%</code>, <code>%location%</code>, <code>%filename%</code>, <code>%filepath%</code>, <code>%folder%</code></div>

              <div class="useyourdrive-option-title"><?php _e('Template deletion', 'useyourdrive'); ?>:</div>

              <?php
              ob_start();
              wp_editor($this->settings['delete_template'], 'use_your_drive_settings_delete_template', array(
                  'textarea_name' => 'use_your_drive_settings[delete_template]',
                  'teeny' => true,
                  'textarea_rows' => 15,
                  'media_buttons' => false
              ));
              echo ob_get_clean();
              ?>
              <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%sitename%</code>, <code>%currenturl%</code>, <code>%filelist%</code>,  <code>%ip%</code>, <code>%location%</code></div>

            </div>

          </div>

          <div class="useyourdrive-option-title"><?php _e('Template %filelist% placeholder', 'useyourdrive'); ?>:</div>
          <div class="useyourdrive-option-description"><?php _e('Template for File item in File List in the download/upload/delete notification template', 'useyourdrive'); ?>.</div>
          <?php
          ob_start();
          wp_editor($this->settings['filelist_template'], 'use_your_drive_settings_filelist_template', array(
              'textarea_name' => 'use_your_drive_settings[filelist_template]',
              'teeny' => true,
              'textarea_rows' => 15,
              'media_buttons' => false
          ));
          echo ob_get_clean();
          ?>
          <div class="useyourdrive-option-description"><?php _e('Available placeholders', 'useyourdrive'); ?>: <code>%filename%</code>, <code>%filesize%</code>, <code>%fileurl%</code>,  <code>%filepath%</code></div>


        </div>
        <!-- End Notifications Tab -->

        <!--  Permissions Tab -->
        <div id="settings_permissions"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Permissions', 'useyourdrive'); ?></div>

          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Change Plugin Settings', 'useyourdrive'); ?>         </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_edit_settings]', $this->settings['permissions_edit_settings']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Link Users to Private Folders', 'useyourdrive'); ?>        </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_link_users]', $this->settings['permissions_link_users']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('See Reports', 'useyourdrive'); ?>        </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_see_dashboard]', $this->settings['permissions_see_dashboard']); ?>
            </div>                 

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('See Back-End Filebrowser', 'useyourdrive'); ?>        </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_see_filebrowser]', $this->settings['permissions_see_filebrowser']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Add Plugin Shortcodes', 'useyourdrive'); ?>         </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_add_shortcodes]', $this->settings['permissions_add_shortcodes']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Add Direct Links', 'useyourdrive'); ?>        </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_add_links]', $this->settings['permissions_add_links']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Embed Documents', 'useyourdrive'); ?>        </div>
            <div>
              <?php wp_roles_checkbox('use_your_drive_settings[permissions_add_embedded]', $this->settings['permissions_add_embedded']); ?>
            </div>

          </div>

        </div>
        <!-- End Permissions Tab -->

        <!--  Statistics Tab -->
        <div id="settings_stats"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Statistics', 'useyourdrive'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Log Events', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[log_events]'/>
              <input type="checkbox" name="use_your_drive_settings[log_events]" id="log_events" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['log_events'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="log_events"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e("Register all plugin events", "useyourdrive"); ?>.</div>

          <div class="useyourdrive-option-title"><?php _e('Google Analytics', 'useyourdrive'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[google_analytics]'/>
              <input type="checkbox" name="use_your_drive_settings[google_analytics]" id="google_analytics" class="useyourdrive-onoffswitch-checkbox" <?php echo ($this->settings['google_analytics'] === "Yes") ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="google_analytics"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e("Would you like to see some statistics about your files? Use-your-Drive can send all download/upload events to Google Analytics", "useyourdrive"); ?>. <?php _e("If you enable this feature, please make sure you already added your <a href='https://support.google.com/analytics/answer/1008080?hl=en'>Google Analytics web tracking</a> code to your site.", "useyourdrive"); ?>.</div>
        </div>
        <!-- End Statistics Tab -->

        <!-- System info Tab -->
        <div id="settings_system"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('System information', 'useyourdrive'); ?></div>
          <?php echo $this->get_system_information(); ?>
        </div>
        <!-- End System info -->

        <!-- Help Tab -->
        <div id="settings_help"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Need help?', 'useyourdrive'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Support & Documentation', 'useyourdrive'); ?></div>
          <div id="message">
            <p><?php _e('Check the documentation of the plugin in case you encounter any problems or are looking for support.', 'useyourdrive'); ?></p>
            <div id='documentation_button' type='button' class='simple-button blue'><?php _e('Open Documentation', 'useyourdrive'); ?></div>
          </div>
          <br/>
          <div class="useyourdrive-option-title"><?php _e('Reset Cache', 'useyourdrive'); ?></div>
          <?php echo $this->get_plugin_reset_box(); ?>

        </div>  
      </div>
      <!-- End Help info -->
    </div>
  </form>
  <script type="text/javascript" >
      jQuery(document).ready(function ($) {
        var media_library;

        $(".useyourdrive-accordion").accordion({
          active: false,
          collapsible: true,
          header: ".useyourdrive-accordion-title",
          heightStyle: "content",
          classes: {
            "ui-accordion-header": "useyourdrive-accordion-top",
            "ui-accordion-header-collapsed": "useyourdrive-accordion-collapsed",
            "ui-accordion-content": "useyourdrive-accordion-content"
          },
          icons: {
            "header": "fas fa-angle-down",
            "activeHeader": "fas fa-angle-up"
          }
        });
        $('.useyourdrive-accordion .ui-accordion-header span').removeClass('ui-icon ui-accordion-header-icon');

        $('.useyourdrive-color-picker').wpColorPicker();
        $('#content_skin_selectbox').ddslick({
          width: '598px',
          background: '#f4f4f4',
          onSelected: function (item) {
            $("#content_skin").val($('#content_skin_selectbox').data('ddslick').selectedData.value);
          }
        });
        $('#lightbox_skin_selectbox').ddslick({
          width: '598px',
          background: '#f4f4f4',
          onSelected: function (item) {
            $("#lightbox_skin").val($('#lightbox_skin_selectbox').data('ddslick').selectedData.value);
          }
        });
        $('#mediaplayer_skin_selectbox').ddslick({
          width: '598px',
          background: '#f4f4f4',
          onSelected: function (item) {
            $("#mediaplayer_skin").val($('#mediaplayer_skin_selectbox').data('ddslick').selectedData.value);
          }
        });
        $('#shortlinks').on('change', function () {
          $('.option.bitly, .option.shortest, .option.rebrandly').hide();
          if ($(this).val() == 'Bit.ly') {
            $('.option.bitly').show();
          }
          if ($(this).val() == 'Shorte.st') {
            $('.option.shortest').show();
          }
          if ($(this).val() == 'Rebrandly') {
            $('.option.rebrandly').show();
          }

        });
        $('.upload_button').click(function () {
          var input_field = $(this).prev("input").attr("id");
          media_library = wp.media.frames.file_frame = wp.media({
            title: '<?php echo __('Select your image', 'useyourdrive'); ?>',
            button: {
              text: '<?php echo __('Use this Image', 'useyourdrive'); ?>'
            },
            multiple: false
          });
          media_library.on("select", function () {
            var attachment = media_library.state().get('selection').first().toJSON();

            var mime = attachment.mime;
            var regex = /^image\/(?:jpe?g|png|gif|svg)$/i;
            var is_image = mime.match(regex)

            if (is_image) {
              $("#" + input_field).val(attachment.url);
              $("#" + input_field).trigger('change');
            }

            $('.upload-remove').click(function () {
              $(this).hide();
              $(this).parent().parent().find(".upload").val('');
              $(this).parent().parent().find(".screenshot").slideUp();
            })
          })
          media_library.open()
        });

        $('.upload-remove').click(function () {
          $(this).hide();
          $(this).parent().parent().find(".upload").val('');
          $(this).parent().parent().find(".screenshot").slideUp();
        })

        $('.default_image_button').click(function () {
          $(this).parent().find(".upload").val($(this).attr('data-default'));
          $('input.upload').trigger('change');
        });

        $('input.upload').change(function () {
          var img = '<img src="' + $(this).val() + '" />'
          img += '<a href="javascript:void(0)" class="upload-remove">' + '<?php echo __('Remove Media', 'useyourdrive'); ?>' + "</a>";
          $(this).parent().find(".screenshot").slideDown().html(img);

          var default_button = $(this).parent().find(".default_image_button");
          default_button.hide();
          if ($(this).val() !== default_button.attr('data-default')) {
            default_button.fadeIn();
          }
        });

        $('#authorizeDrive_button').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#authorizeDrive_options').fadeIn();
          popup = window.open($(this).attr('data-url'), "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,width=900,height=700");

          var i = sessionStorage.length;
          while (i--) {
            var key = sessionStorage.key(i);
            if (/CloudPlugin/.test(key)) {
              sessionStorage.removeItem(key);
            }
          }

        });
        $('#revokeDrive_button').click(function () {
          $(this).addClass('disabled');
          $(this).find('.uyd-spinner').show();
          $.ajax({type: "POST",
            url: '<?php echo USEYOURDRIVE_ADMIN_URL; ?>',
            data: {
              action: 'useyourdrive-revoke',
              _ajax_nonce: '<?php echo $admin_nonce; ?>'
            },
            complete: function (response) {
              location.reload(true)
            },
            dataType: 'json'
          });
        });
        $('#resetDrive_button').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').show();
          $.ajax({type: "POST",
            url: '<?php echo USEYOURDRIVE_ADMIN_URL; ?>',
            data: {
              action: 'useyourdrive-reset-cache',
              _ajax_nonce: '<?php echo $admin_nonce; ?>'
            },
            complete: function (response) {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').hide();
            },
            dataType: 'json'
          });

          var i = sessionStorage.length;
          while (i--) {
            var key = sessionStorage.key(i);
            if (/CloudPlugin/.test(key)) {
              sessionStorage.removeItem(key);
            }
          }

        });
        $('#updater_button').click(function () {

          if ($('#purcase_code.useyourdrive-option-input-large').val()) {
            $('#useyourdrive-options').submit();
            return;
          }

          popup = window.open('https://www.wpcloudplugins.com/updates/activate.php?init=1&client_url=<?php echo strtr(base64_encode($location), '+/=', '-_~'); ?>&plugin_id=<?php
          echo $this->plugin_id;
          ?>', "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,width=900,height=700");
        });
        $('#check_updates_button').click(function () {
          window.location = '<?php echo admin_url('update-core.php'); ?>';
        });
        $('#purcase_code.useyourdrive-option-input-large').focusout(function () {
          var purchase_code_regex = '^([a-z0-9]{8})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{12})$';
          if ($(this).val().match(purchase_code_regex)) {
            $(this).css('color', 'initial');
          } else {
            $(this).css('color', '#dc3232');
          }
        });
        $('#deactivate_license_button').click(function () {
          $('#purcase_code').val('');
          $('#useyourdrive-options').submit();
        });

        $('#root_folder_button').click(function () {
          var $button = $(this);
          $(this).parent().addClass("thickbox_opener");
          $button.addClass('disabled');
          $button.find('.uyd-spinner').show();
          tb_show("Select Folder", '#TB_inline?height=450&amp;width=800&amp;inlineId=uyd-embedded');
        });
        $('#documentation_button').click(function () {
          popup = window.open('<?php echo plugins_url('_documentation/index.html', dirname(__FILE__)); ?>', "_blank");
        });
        $('#save_settings').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#useyourdrive-options').ajaxSubmit({
            success: function () {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').fadeOut();

              if (location.hash === '#settings_advanced') {
                location.reload(true);
              }
            },
            error: function () {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').fadeOut();
              location.reload(true);
            },
          });
          //setTimeout("$('#saveMessage').hide('slow');", 5000);
          return false;
        });
      }
      );


  </script>
</div>