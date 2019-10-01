<div class="fileupload-container" style="width:<?php echo $this->options['maxwidth']; ?>;max-width:<?php echo $this->options['maxwidth']; ?>" >
  <div>
    <div id="fileupload-<?php echo $this->listtoken; ?>" class="fileuploadform" data-token='<?php echo $this->listtoken; ?>'>
      <input type="hidden" name="acceptfiletypes" value="<?php echo $acceptfiletypes; ?>">
      <input type="hidden" name="maxfilesize" data-limit="<?php echo ($own_limit) ? 1 : 0; ?>" value="<?php echo $max_file_size; ?>">
      <input type="hidden" name="maxnumberofuploads" value="<?php echo $max_number_of_uploads; ?>">
      <input type="hidden" name="listtoken" value="<?php echo $this->listtoken; ?>">
      <input type="hidden" name="encryption" value="<?php echo ($upload_encryption) ? 1 : 0; ?>">
      <div class="fileupload-drag-drop">
        <div>
          <i class="upload-icon fas fa-plus-circle fa-3x" aria-hidden="true"></i>
          <p><?php _e('Drag your files here', 'useyourdrive'); ?> ...</p>
        </div>
      </div>

      <div class='fileupload-list'>
        <div role="presentation">
          <div class="files"></div>
        </div>
        <input type='hidden' name='fileupload-filelist' id='fileupload-filelist' class='fileupload-filelist' value='<?php echo (isset($_REQUEST['fileupload-filelist']) ? stripslashes($_REQUEST['fileupload-filelist']) : ''); ?>'>
      </div>
      <div class="fileupload-buttonbar">

        <div class="fileupload-buttonbar-text">
          <?php _e('... or find documents on your device', 'useyourdrive'); ?></div>
        <div class="upload-btn-container button">
          <span><?php _e('Add files', 'useyourdrive'); ?></span>
          <input type="file" name="files[]" multiple="multiple" class='upload-input-button' multiple>
        </div>
        <?php if ($this->options['upload_folder'] === '1') { ?>
            <div class="upload-btn-container button upload-folder">
              <span><?php _e('Upload folder', 'useyourdrive'); ?></span>
              <input type="file" name="files[]" multiple="multiple" class='upload-input-button upload-multiple-files' multiple directory webkitdirectory>
            </div>
        <?php } ?>
      </div>
    </div>
  </div>
  <div class="template-row">
    <div class="upload-thumbnail">
      <img class="" src="" />
    </div>

    <div class="upload-file-info">
      <div class="upload-status-container"><i class="upload-status-icon fas fa-circle"></i> <span class="upload-status"></span></div>
      <div class="file-size"></div>
      <div class="file-name"></div>
      <div class="upload-progress">
        <div class="progress progress-striped active ui-progressbar ui-widget ui-widget-content ui-corner-all" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
          <div class="ui-progressbar-value ui-widget-header ui-corner-left" style="display: none; width: 0%;"></div>
        </div>
      </div>
      <div class="upload-error"></div>
    </div>
  </div>
  <div class="fileupload-info-container">
    <?php _e('Max file size: ', 'useyourdrive'); ?> <span class="max-file-size"><?php echo $post_max_size_str; ?></span>
    <?php
    if (!empty($this->options['upload_ext']) && $this->options['upload_ext'] !== '.') {
        echo " | " . __('Allowed formats: ', 'useyourdrive') . ' <span class="file-formats">' . str_replace('|', ', ', $this->options['upload_ext']) . '</span>';
    }
    ?>
  </div>
</div>