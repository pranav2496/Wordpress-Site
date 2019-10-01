<div id="UseyourDrive" class="UseyourDriveDashboard">
  <div class="useyourdrive admin-settings">

    <div class="wrap">
      <div class="useyourdrive-header">
        <div class="useyourdrive-logo"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/logo64x64.png" height="64" width="64"/></div>
        <div class="useyourdrive-title"><?php _e('Reports', 'useyourdrive'); ?></div>
      </div>

      <div class="useyourdrive-panel">
        <div id="useyourdrive-totals">
          <div class="useyourdrive-box useyourdrive-box25">
            <div class="useyourdrive-box-inner ">
              <div class="useyourdrive-option-title nopadding">
                <div class="useyourdrive-counter-text"><?php echo __('Total Previews', 'useyourdrive'); ?> </div>
                <div class="useyourdrive-counter" data-type="useyourdrive_previewed_entry">
                  <span>
                    <div class="loading"><div class='loader-beat'></div></div>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="useyourdrive-box useyourdrive-box25">
            <div class="useyourdrive-box-inner">
              <div class="useyourdrive-option-title nopadding">
                <div class="useyourdrive-counter-text"><?php echo __('Total Downloads', 'useyourdrive'); ?></div>
                <div class="useyourdrive-counter" data-type="useyourdrive_downloaded_entry">
                  <span>
                    <div class="loading"><div class='loader-beat'></div></div>
                  </span>
                </div></div>
            </div>
          </div>

          <div class="useyourdrive-box useyourdrive-box25">
            <div class="useyourdrive-box-inner">
              <div class="useyourdrive-option-title nopadding">
                <div class="useyourdrive-counter-text"><?php echo __('Items Shared', 'useyourdrive'); ?></div>
                <div class="useyourdrive-counter" data-type="useyourdrive_created_link_to_entry">
                  <span>
                    <div class="loading"><div class='loader-beat'></div></div>
                  </span>
                </div></div>
            </div>
          </div>

          <div class="useyourdrive-box useyourdrive-box25">
            <div class="useyourdrive-box-inner">
              <div class="useyourdrive-option-title nopadding">
                <div class="useyourdrive-counter-text"><?php echo __('Documents Uploaded', 'useyourdrive'); ?></div>
                <div class="useyourdrive-counter" data-type="useyourdrive_uploaded_entry">
                  <span>
                    <div class="loading"><div class='loader-beat'></div></div>
                  </span>
                </div></div>
            </div>
          </div>
        </div>

        <div class="useyourdrive-box">
          <div class="useyourdrive-box-inner">
            <div class="useyourdrive-event-date-selector">
              <label for="chart_datepicker_from"><?php echo __('From', 'useyourdrive'); ?></label>
              <input type="text" id="chart_datepicker_from" name="chart_datepicker_from">
              <label for="chart_datepicker_to"><?php echo __('to', 'useyourdrive'); ?></label>
              <input type="text" id="chart_datepicker_to" name="chart_datepicker_to">
            </div>
            <div class="useyourdrive-option-title"><?php echo __('Events per Day', 'useyourdrive'); ?></div>
            <div class="useyourdrive-events-chart-container" style="height:500px !important; position:relative;">
              <div class="loading"><div class='loader-beat'></div></div>
              <canvas id="useyourdrive-events-chart"></canvas>
            </div>
          </div>
        </div>

        <div class="useyourdrive-box useyourdrive-box50">
          <div class="useyourdrive-box-inner">
            <div class="useyourdrive-option-title"><?php echo __('Top 25 Downloads', 'useyourdrive'); ?></div>
            <table id="top-downloads" class="stripe hover order-column" style="width:100%">
              <thead>
                <tr>
                  <th></th>
                  <th><?php echo __('Document', 'useyourdrive'); ?></th>
                  <th><?php echo __('Total', 'useyourdrive'); ?></th>
                </tr>
              </thead>
            </table>
          </div>
        </div>

        <div class="useyourdrive-box useyourdrive-box50">
          <div class="useyourdrive-box-inner">
            <div class="useyourdrive-option-title"><?php echo __('Top 25 Users with most Downloads', 'useyourdrive'); ?></div>
            <table id="top-users" class="display" style="width:100%">
              <thead>
                <tr>
                  <th></th>
                  <th><?php echo __('User', 'useyourdrive'); ?></th>
                  <th><?php echo __('Downloads', 'useyourdrive'); ?></th>
                </tr>
              </thead>
            </table>
          </div>
        </div>

        <div class="useyourdrive-box">
          <div class="useyourdrive-box-inner">
            <div class="useyourdrive-option-title"><?php echo __('All Events', 'useyourdrive'); ?></div>
            <table id="full-log" class="display" style="width:100%">
              <thead>
                <tr>
                  <th></th>
                  <th class="all"><?php echo __('Description', 'useyourdrive'); ?></th>
                  <th><?php echo __('Date', 'useyourdrive'); ?></th>
                  <th><?php echo __('Event', 'useyourdrive'); ?></th>
                  <th><?php echo __('User', 'useyourdrive'); ?></th>
                  <th><?php echo __('Name', 'useyourdrive'); ?></th>
                  <th><?php echo __('Location', 'useyourdrive'); ?></th>
                  <th><?php echo __('Page', 'useyourdrive'); ?></th>
                  <th><?php echo __('Extra', 'useyourdrive'); ?></th>
                </tr>
              </thead>
            </table>
          </div>
        </div>

        <div class="event-details-template" style="display:none;">
          <div class="event-details-name"></div>

          <div class="useyourdrive-box useyourdrive-box25">
            <div class="useyourdrive-box-inner">
              <div class="event-details-user-template" style="display:none;">
                <div class="event-details-entry-img"></div>
                <a target="_blank" class="event-visit-profile event-button simple-button blue"><i class="fas fa-external-link-square-alt"></i>&nbsp;<?php echo __('Visit Profile'); ?></a>

                <div class="loading"><div class="loader-beat"></div></div>
              </div>

              <div class="event-details-entry-template" style="display:none;">
                <div class="event-details-entry-img"></div>
                <p class="event-details-description"></p>
                <a target="_blank" class="event-download-entry event-button simple-button blue" download><i class="fas fa-download"></i>&nbsp;<?php echo __('Download'); ?></a>

                <div class="loading"><div class="loader-beat"></div></div>
              </div>

              <br/>

              <div class="event-details-totals-template">
                <div class="useyourdrive-option-title tbpadding10 ">
                  <div class="useyourdrive-counter-text"><?php echo __('Previews', 'useyourdrive'); ?> </div>
                  <div class="useyourdrive-counter" data-type="useyourdrive_previewed_entry">
                    <span>
                      <div class="loading"><div class='loader-beat'></div></div>
                    </span>
                  </div>
                </div>

                <div class="useyourdrive-option-title tbpadding10">
                  <div class="useyourdrive-counter-text"><?php echo __('Downloads', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-counter" data-type="useyourdrive_downloaded_entry">
                    <span>
                      <div class="loading"><div class='loader-beat'></div></div>
                    </span>
                  </div>
                </div>

                <div class="useyourdrive-option-title tbpadding10">
                  <div class="useyourdrive-counter-text"><?php echo __('Shared', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-counter" data-type="useyourdrive_created_link_to_entry">
                    <span>
                      <div class="loading"><div class='loader-beat'></div></div>
                    </span>
                  </div>
                </div>

                <div class="useyourdrive-option-title tbpadding10">
                  <div class="useyourdrive-counter-text"><?php echo __('Uploads', 'useyourdrive'); ?></div>
                  <div class="useyourdrive-counter" data-type="useyourdrive_uploaded_entry">
                    <span>
                      <div class="loading"><div class='loader-beat'></div></div>
                    </span>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <div class="useyourdrive-box useyourdrive-box75 event-details-table-template">
            <div class="useyourdrive-box-inner">
              <div class="useyourdrive-option-title"><?php echo __('Logged Events', 'useyourdrive'); ?></div>
              <table id="full-detail-log" class="display" style="width:100%">
                <thead>
                  <tr>
                    <th></th>
                    <th class="all"><?php echo __('Description', 'useyourdrive'); ?></th>
                    <th><?php echo __('Date', 'useyourdrive'); ?></th>
                    <th><?php echo __('Event', 'useyourdrive'); ?></th>
                    <th><?php echo __('User', 'useyourdrive'); ?></th>
                    <th><?php echo __('Name', 'useyourdrive'); ?></th>
                    <th><?php echo __('Location', 'useyourdrive'); ?></th>
                    <th><?php echo __('Page', 'useyourdrive'); ?></th>
                    <th><?php echo __('Extra', 'useyourdrive'); ?></th>
                  </tr>
                </thead>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
