<div class="list-container" style="width:<?php echo $this->options['maxwidth']; ?>;max-width:<?php echo $this->options['maxwidth']; ?>;">
  <?php
  if ($this->options['show_breadcrumb'] === '1' || $this->options['search'] === '1' || $this->options['show_refreshbutton'] === '1' ||
          $this->get_user()->can_download_zip() || $this->get_user()->can_delete_files() || $this->get_user()->can_delete_folders()) {
      ?>
      <div class="nav-header">
        <?php if ($this->options['show_breadcrumb'] === '1') { ?>
            <a class="nav-home" title="<?php _e('Back to our first folder', 'useyourdrive'); ?>">
              <i class="fas fa-home"></i>
            </a>
            <?php if ($this->options['show_breadcrumb'] === '1') { ?>
                <div class="nav-title"><?php _e('Loading...', 'useyourdrive'); ?></div>
                <?php
            };

            if ($this->options['search'] === '1') {
                ?>
                <a class="nav-search">
                  <i class="fas fa-search"></i>
                </a>

                <div class="search-div">
                  <div class="search-remove"><i class="fas fa-times-circle fa-lg"></i></div>
                  <input name="q" type="text" size="40" aria-label="<?php echo __('Search', 'useyourdrive'); ?>" placeholder="<?php echo __('Search filenames', 'useyourdrive') . (($this->options['searchcontents'] === '1') ? ' ' . __('and within contents', 'useyourdrive') : ''); ?>" class="search-input" />
                </div>
            <?php }; ?>
            <a class="nav-gear" title="<?php _e('Options', 'useyourdrive'); ?>">
              <i class="fas fa-cog"></i>
            </a>
            <div class="gear-menu" data-token="<?php echo $this->listtoken; ?>">
              <ul data-id="<?php echo $this->listtoken; ?>">
                <li style="display: none"><a class="nav-layout nav-layout-grid" title="<?php _e('Thumbnails view', 'useyourdrive'); ?>">
                    <i class="fas fa-th-large fa-lg"></i><?php _e('Thumbnails view', 'useyourdrive'); ?>
                  </a>
                </li>
                <li><a class="nav-layout nav-layout-list" title="<?php _e('List view', 'useyourdrive'); ?>">
                    <i class="fas fa-th-list fa-lg"></i><?php _e('List view', 'useyourdrive'); ?>
                  </a>
                </li>           
                <?php
                if ($this->get_user()->can_upload()) {
                    ?>
                    <li><a class="nav-upload" title="<?php _e('Upload files', 'useyourdrive'); ?>"><i class="fas fa-upload fa-lg"></i><?php _e('Upload files', 'useyourdrive'); ?></a></li>
                    <?php
                }

                if ($this->get_user()->can_download_zip()) {
                    ?>
                    <li><a class="all-files-to-zip"><i class='fas fa-archive fa-lg'></i><?php _e('Download all', 'useyourdrive'); ?> (.zip)</a></li>
                    <li><a class="selected-files-to-zip"><i class='fas fa-archive fa-lg'></i><?php _e('Download selected', 'useyourdrive'); ?> (.zip)</a></li>
                    <?php
                }
                if ($this->get_user()->can_delete_files() || $this->get_user()->can_delete_folders()) {
                    ?>
                    <li><a class="selected-files-delete" title="<?php _e('Delete selected', 'useyourdrive'); ?>"><i class="fas fa-trash fa-lg"></i><?php _e('Delete selected', 'useyourdrive'); ?></a></li>
                    <?php
                }
                if ($this->get_user()->can_share()) {
                    ?>
                    <li><a class='entry_action_shortlink_folder' title='<?php _e('Share folder', 'useyourdrive'); ?>'><i class='fas fa-share-alt fa-lg'></i><?php _e('Share folder', 'useyourdrive'); ?></a></li>
                    <?php
                }
                ?>
                <li class='gear-menu-no-options' style="display: none"><a><i class='fas fa-info-circle fa-lg'></i><?php _e('No options...', 'useyourdrive') ?></a></li>
              </ul>
            </div>
            <?php
        }
        if ($this->options['show_refreshbutton'] === '1') {
            ?>
            <a class="nav-refresh" title="<?php _e('Refresh', 'useyourdrive'); ?>">
              <i class="fas fa-sync"></i>
            </a>
        <?php } ?>

      </div>
  <?php } ?>
  <?php if ($this->options['show_columnnames'] === '1') { ?>
      <div class='column_names'>
        <div class='entry_icon'></div>
        <?php if ($this->get_user()->can_download_zip() || $this->get_user()->can_delete_files() || $this->get_user()->can_delete_folders()) {
            ?>
            <div class='entry_checkallbox'><input type='checkbox' name='select-all-files' class='select-all-files'/></div>
            <?php
        };
        ?>
        <div class='entry_edit'>&nbsp;</div>
        <?php
        if ($this->options['show_filesize'] === '1') {
            ?>
            <div class='entry_size sortable <?php echo ($this->options['sort_field'] === 'size') ? $this->options['sort_order'] : ''; ?>' data-sortname="size"><span class="sort_icon">&nbsp;</span><a class='entry_sort'><?php _e('Size', 'useyourdrive'); ?></a></div>
            <?php
        };

        if ($this->options['show_filedate'] === '1') {
            ?>
            <div class='entry_lastedit sortable <?php echo ($this->options['sort_field'] === 'modified') ? $this->options['sort_order'] : ''; ?>' data-sortname="modified"><a class='entry_sort'><?php _e('Date modified', 'useyourdrive'); ?></a><span class="sort_icon">&nbsp;</span></div>
            <?php
        };
        ?>
        <div class='entry_name sortable <?php echo ($this->options['sort_field'] === 'name') ? $this->options['sort_order'] : ''; ?>' data-sortname="name"><a class='entry_sort'><?php _e('Name', 'useyourdrive'); ?></a><span class="sort_icon">&nbsp;</span></div>
      </div>
  <?php }; ?>
  <div class="file-container">
    <div class="loading initialize"><?php
      $loaders = $this->get_setting('loaders');

      switch ($loaders['style']) {

          case 'custom':
              break;

          case 'beat':
              ?>
              <div class='loader-beat'></div>
              <?php
              break;

          case 'spinner':
              ?>
              <svg class="loader-spinner" viewBox="25 25 50 50">
              <circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="3" stroke-miterlimit="10"></circle>
              </svg>
              <?php
              break;
      }
      ?></div>
    <div class="ajax-filelist" style="<?php echo (!empty($this->options['maxheight'])) ? 'max-height:' . $this->options['maxheight'] . ';overflow-y: scroll;' : '' ?>">&nbsp;</div>
    <div class="scroll-to-top"><a><i class="fas fa-chevron-circle-up fa-2x"></i></a></div>
  </div>
</div>