<div class="list-container" style="width:<?php echo $this->options['maxwidth']; ?>;max-width:<?php echo $this->options['maxwidth']; ?>;">
  <div class="nav-header UseyourDrive" id="search-<?php echo $this->listtoken; ?>">
    <div class="search-div">
      <a class="" href="#"><i class="fas fa-search submit-search"></i></a>
      <input name="q" type="text" size="40" aria-label="<?php echo __('Search', 'useyourdrive'); ?>" placeholder="<?php echo __('Search for files', 'useyourdrive') . (($this->options['searchcontents'] === '1') ? ' ' . __('and content', 'useyourdrive') : ''); ?>" class="search-input" />
    </div>
  </div>
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
  </div>
</div>