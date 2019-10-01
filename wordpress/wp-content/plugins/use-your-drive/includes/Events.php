<?php

namespace TheLion\UseyourDrive;

class Events {

    /**
     *
     * @var \TheLion\UseyourDrive\Main 
     */
    private $_main;

    public function __construct(Main $_main) {
        $this->_main = $_main;

        if ($this->_main->settings['log_events'] === 'Yes') {
            $this->_load_hooks();
        }
    }

    public function _load_hooks() {
        add_action('wp_ajax_nopriv_useyourdrive-event-stats', array(&$this, 'get_stats'));
        add_action('wp_ajax_useyourdrive-event-stats', array(&$this, 'get_stats'));
        add_action('useyourdrive_log_event', array(&$this, 'log_event'), 10, 3);
    }

    /**
     * Log new events
     * Hook into this function with something like: do_action('useyourdrive_log_event', 'useyourdrive_event_type', TheLion\UseyourDrive\CacheNode $cached_entry, array('extra_data' => $value));
     * 
     * @param string $event
     * @param \TheLion\UseyourDrive\CacheNode $cached_entry
     * @param array $extra_data
     */
    public function log_event($event, $cached_entry = NULL, $extra_data = array()) {

        $new_event = array(
            'plugin' => 'use-your-drive',
            'type' => $event,
            'user_id' => get_current_user_id()
        );


        /* @var $cached_entry CacheNode */
        if (!empty($cached_entry)) {
            $new_event['entry_id'] = $cached_entry->get_id();
            $new_event['entry_mimetype'] = $cached_entry->get_entry()->get_mimetype();
            $new_event['entry_is_dir'] = $cached_entry->get_entry()->is_dir();
            $new_event['entry_name'] = $cached_entry->get_name();

            if ($cached_entry->has_parents()) {
                $parents = $cached_entry->get_parents();
                $first_parent = reset($parents);
                $new_event['parent_id'] = $first_parent->get_id();
                $root_id = $this->get_processor()->get_client()->get_root_folder()->get_id();
                $new_event['parent_path'] = $first_parent->get_path($root_id);
            }
        }

        if (!empty($extra_data)) {
            $new_event['extra'] = json_encode($extra_data);
        }

        $location = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (!empty($location)) {
            $new_event['location'] = urlencode($location);
        }

        try {
            Event_DB_Model::insert($new_event);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Cannot log Event on line %s: %s', __LINE__, $ex->getMessage()));
        }
    }

    /**
     * Event processor for Dashboard
     */
    public function get_stats() {
        if (!isset($_REQUEST['type'])) {
            die();
        }

        if ($_REQUEST['type'] === 'log_preview_event' && isset($_REQUEST['id']) && check_ajax_referer('useyourdrive-log')) {
            $cached_entry = $this->get_processor()->get_client()->get_entry($_REQUEST['id'], false);
            $this->log_event('useyourdrive_previewed_entry', $cached_entry);
            die();
        }

        if (!Helpers::check_user_role($this->get_main()->settings['permissions_see_dashboard'])) {
            die();
        }

        $nonce_verification = ($this->get_processor()->get_setting('nonce_validation') === 'Yes');
        $allow_nonce_verification = apply_filters("use_your_drive_allow_nonce_verification", $nonce_verification);

        if ($allow_nonce_verification && is_user_logged_in()) {
            if (check_ajax_referer('useyourdrive-admin-action', false, false) === false) {
                error_log('[Use-your-Drive message]: ' . " Function get_stats() didn't receive a valid nonce");
                die();
            }
        }

        $data = array();

        $this->get_processor()->_set_gzip_compression();

        switch ($_REQUEST['type']) {
            case 'totals':
                $data = $this->get_totals();
                break;

            case 'activities':
                $data = $this->get_activities();
                break;

            case 'topdownloads':
                $data = $this->get_top_downloads();
                break;

            case 'topusers':
                $data = $this->get_top_users();
                break;

            case 'full-log':
                $data = $this->get_full_log();
                break;

            case 'get-detail':
                if (!isset($_REQUEST['detail']) || !isset($_REQUEST['id'])) {
                    die();
                }

                switch ($_REQUEST['detail']) {
                    case 'user':
                        $this->render_user_detail($_REQUEST['id']);
                        break;
                    case 'entry':
                        $this->render_entry_detail($_REQUEST['id']);
                        break;
                }

                break;

            case 'get-download':
                if (isset($_REQUEST['id'])) {
                    $this->start_download_by_id($_REQUEST['id']);
                }

                break;
        }

        echo json_encode($data);

        die();
    }

    /**
     * Start the download for entry with $id
     * 
     * @param string $id 
     */
    public function start_download_by_id($entry_id) {
        $cached_entry = $this->get_processor()->get_client()->get_entry($entry_id, false);

        if ($cached_entry === false) {
            die();
        }

        $this->get_processor()->get_client()->download_content($cached_entry);
    }

    /**
     * Get totals per event_type
     * 
     * @global $wpdb
     * @param string $where
     * @return array
     */
    public function get_totals($where = 1) {

        /* Get Totals for user */
        if (isset($_REQUEST['detail']) && isset($_REQUEST['id'])) {
            global $wpdb;
            switch ($_REQUEST['detail']) {
                case 'user':
                    $where = $wpdb->prepare('`user_id` = %s', array($_REQUEST['id']));
                    break;
                case 'entry':
                    $where = $wpdb->prepare('`entry_id` = %s', array($_REQUEST['id']));
                    break;
            }
        }


        $sql = "SELECT `type`, COUNT(`id`) as total FROM `" . Event_DB_Model::table() . "` WHERE $where GROUP BY `type`";
        $data = Event_DB_Model::get_custom_sql($sql, array(), 'ARRAY_A');

        $totals = array();
        foreach ($data as $row) {
            $event = $this->get_event_type($row['type']);
            $row = array_merge($row, $event);

            $totals[$row['type']] = $row;
        }

        return $totals;
    }

    /**
     * Return a dataset for ChartJS
     * 
     * @return array
     */
    public function get_activities() {

        /* Get Events from Database */
        $sql = 'SELECT DATE(`datetime`) as date, `type`, COUNT(`id`) as total FROM `' . Event_DB_Model::table() . '` WHERE (`datetime` BETWEEN %s AND %s) GROUP BY DATE(`datetime`), `type` ORDER BY  DATE(`datetime`) DESC';
        $values = array($_REQUEST['start'] . ' 00:00:00', $_REQUEST['end'] . ' 23:59:00');
        $data = Event_DB_Model::get_custom_sql($sql, $values, 'ARRAY_A');

        /* Create return array */
        $raw_chart_data = array('labels' => array(), 'datasets' => array());

        /* Create an empty array with all total set to 0 the selected period */
        $start = new \DateTime($_REQUEST['start']);
        $end = new \DateTime($_REQUEST['end']);
        $end->add(\DateInterval::createFromDateString('1 day'));

        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($start, $interval, $end);

        $empty_period = array();
        foreach ($period as $date) {
            $date_str = $date->format("d-m-Y");
            $empty_period[$date_str] = array(
                'x' => $date_str,
                'y' => (int) 0
            );
        }

        /* Create the dataset for each event type */
        foreach ($data as $day) {
            if (!isset($raw_chart_data['datasets'][$day['type']])) {

                $event = $this->get_event_type($day['type']);
                $hide_on_init = !in_array($day['type'], array('useyourdrive_previewed_entry', 'useyourdrive_downloaded_entry', 'useyourdrive_streamed_entry'));

                $raw_chart_data['datasets'][$day['type']] = array(
                    'label' => $event['text'],
                    'data' => array(),
                    //'fill' => false,
                    'spanGaps' => true,
                    'backgroundColor' => $event['colors']['light'],
                    'borderColor' => $event['colors']['normal'],
                    'pointBackgroundColor' => $event['colors']['dark'],
                    'borderWidth' => 2,
                    'data' => $empty_period,
                    'hidden' => $hide_on_init
                );
            }

            $date = new \DateTime($day['date']);

            $raw_chart_data['datasets'][$day['type']]['data'][$date->format("d-m-Y")] = array(
                'x' => $date->format("d-m-Y"),
                'y' => (int) $day['total']
            );
        }

        /* ChartJS doesn't like arrays with key names, so remove them all */
        foreach ($raw_chart_data['datasets'] as &$dataset) {
            $dataset['data'] = array_values($dataset['data']);
        }

        $chart_data = array('labels' => $raw_chart_data['labels'], 'datasets' => array_values($raw_chart_data['datasets']));

        /* Return the data */
        return $chart_data;
    }

    /**
     * Get the top 25 to downloads
     * 
     * @return array
     */
    public function get_top_downloads() {

        $columns = array(
            array(
                'db' => 'MAX(`entry_id`)', 'dt' => 'entry_id', 'field' => 'entry_id', 'as' => 'entry_id'
            ),
            array(
                'db' => 'MAX(`entry_mimetype`)', 'dt' => 'icon', 'field' => 'entry_mimetype', 'as' => 'entry_mimetype', 'formatter' => function( $value, $row ) {
                    return Helpers::get_default_thumbnail_icon($value);
                }
            ),
            array(
                'db' => 'MAX(`parent_path`)', 'dt' => 'parent_path', 'field' => 'parent_path', 'as' => 'parent_path', 'formatter' => function( $value, $row ) {
                    return $value;
                }
            ),
            array(
                'db' => 'MAX(`entry_name`)', 'dt' => 'entry_name', 'field' => 'entry_name', 'as' => 'entry_name', 'formatter' => function( $value, $row ) {
                    return $value;
                }
            ),
            array(
                'db' => 'COUNT(`id`)', 'dt' => 'total', 'field' => 'total', 'as' => 'total'
            ),
        );

        $where = "`plugin` = 'use-your-drive' AND `type` IN ('useyourdrive_downloaded_entry')";
        $groupBy = "`entry_id`";

        return $this->get_results($columns, $where, $groupBy, true, '', 'LIMIT 0, 25');
    }

    /**
     * Get the top 25 of the users with most downloads
     * 
     * @return array
     */
    public function get_top_users() {

        $columns = array(
            array(
                'db' => 'MAX(`user_id`)', 'dt' => 'icon', 'field' => 'icon', 'as' => 'icon', 'formatter' => function( $value, $row ) {
                    if ($value == 0) {
                        return USEYOURDRIVE_ROOTPATH . '/css/images/usericon.png';
                    }
                    $user = get_userdata($value);

                    if ($user === false) {
                        return USEYOURDRIVE_ROOTPATH . '/css/images/usericon.png';
                    }

                    if (function_exists('get_wp_user_avatar')) {
                        $display_gravatar = get_wp_user_avatar_url($user->user_email, 32);
                    } else {
                        $display_gravatar = get_avatar_url($user->user_email, 32);
                        if ($display_gravatar === false) {
                            //Gravatar is disabled, show default image.
                            $display_gravatar = USEYOURDRIVE_ROOTPATH . '/css/images/usericon.png';
                        }
                    }
                    return $display_gravatar;
                }
            ),
            array(
                'db' => 'MAX(`user_id`)', 'dt' => 'user_id', 'field' => 'user_id', 'as' => 'user_id'
            ),
            array(
                'db' => 'MAX(`user_id`)', 'dt' => 'user_name', 'field' => 'user_id', 'as' => 'user_id', 'formatter' => function( $value, $row ) {
                    if ($value == 0) {
                        return __('Visitors', 'useyourdrive');
                    }

                    $wp_user = get_userdata($value);

                    if ($wp_user === false) {
                        return __('Visitor') . ' #' . $value . ' (' . __('Deleted') . ')';
                    }

                    $user_name = $wp_user->user_login;
                    if ($wp_user->first_name) {
                        $user_name = $wp_user->first_name;
                        if ($wp_user->last_name) {
                            $user_name .= ' ' . $wp_user->last_name;
                        }
                    }

                    return $user_name;
                }
            ),
            array(
                'db' => 'COUNT(`entry_id`)', 'dt' => 'total', 'field' => 'total', 'as' => 'total'
            ),
        );

        $where = "`plugin` = 'use-your-drive' AND `type` IN ('useyourdrive_downloaded_entry')";
        $groupBy = "`user_id`";

        return $this->get_results($columns, $where, $groupBy, true, '', 'LIMIT 0, 25');
    }

    /**
     * Get the events filtered via Datatables requests
     * 
     * @global $wpdb
     * @return array
     */
    public function get_full_log() {
        $columns = array(
            array(
                'db' => 'id', 'dt' => 'id', 'field' => 'id',
            ),
            array(
                'db' => 'type', 'dt' => 'icon', 'field' => 'type', 'formatter' => function( $value, $row ) {
                    $event = $this->get_event_type($value);
                    return $event['icon'];
                }
            ),
            array(
                'db' => 'type', 'dt' => 'description', 'field' => 'type', 'formatter' => function( $value, $row ) {
                    return $this->get_event_description($row);
                }
            ),
            array(
                'db' => 'datetime', 'dt' => 'datetime', 'field' => 'datetime', 'formatter' => function( $value, $row ) {
                    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($value));
                }
            ),
            array(
                'db' => 'type', 'dt' => 'type', 'field' => 'type', 'formatter' => function( $value, $row ) {
                    $event = $this->get_event_type($value);
                    return $event['text'];
                }
            ),
            array(
                'db' => 'user_id', 'dt' => 'user', 'field' => 'user_id', 'formatter' => function( $value, $row ) {
                    if ($value == 0) {
                        return __('A visitor', 'useyourdrive');
                    }

                    $user = get_userdata($value);

                    if ($user === false) {
                        return ' #' . $value . ' (' . __('Deleted') . ')';
                    }

                    return $user->user_login;
                }
            ),
            array(
                'db' => 'user_id', 'dt' => 'user_id', 'field' => 'user_id'
            ),
            array(
                'db' => 'entry_id', 'dt' => 'entry_id', 'field' => 'entry_id'
            ),
            array(
                'db' => 'entry_mimetype', 'dt' => 'entry_mimetype', 'field' => 'entry_mimetype'
            ),
            array(
                'db' => 'entry_is_dir', 'dt' => 'entry_is_dir', 'field' => 'entry_is_dir'
            ),
            array(
                'db' => 'entry_name', 'dt' => 'entry_name', 'field' => 'entry_name', 'formatter' => function( $value, $row ) {
                    return $value;
                }
            ),
            array(
                'db' => 'parent_id', 'dt' => 'parent_id', 'field' => 'parent_id'
            ),
            array(
                'db' => 'parent_path', 'dt' => 'parent_path', 'field' => 'parent_path', 'formatter' => function( $value, $row ) {
                    return $value;
                }
            ),
            array(
                'db' => 'location', 'dt' => 'location', 'field' => 'location', 'formatter' => function( $value, $row ) {
                    return str_replace(get_home_url(), '', urldecode($value));
                }
            ),
            array(
                'db' => 'location', 'dt' => 'location_full', 'field' => 'location', 'formatter' => function( $value, $row ) {
                    return urldecode($value);
                }
            ),
            array(
                'db' => 'extra', 'dt' => 'extra', 'field' => 'extra', 'formatter' => function( $value, $row ) {
                    if (empty($value)) {
                        return '';
                    }

                    $extra_data = json_decode($value, true);

                    $extra_data_str = '<ul>';
                    if (is_array($extra_data)) {
                        foreach ($extra_data as $key => $val) {
                            if (is_array($val)) {
                                $extra_data_str .= '<li>' . $key . ' => ' . print_r($val, true) . '</li>';
                            } else {
                                $extra_data_str .= '<li>' . $key . ' => ' . $val . '</li>';
                            }
                        }
                    }
                    $extra_data_str .= '</ul>';

                    return $extra_data_str;
                }
            ),
        );

        $where = "`plugin` = 'use-your-drive'";

        if (!empty($_REQUEST['detail']) && !empty($_REQUEST['id'])) {
            global $wpdb;

            switch ($_REQUEST['detail']) {
                case 'user':
                    $sql = ' AND `user_id` = %s ';
                    break;
                case 'entry':
                    $sql = ' AND `entry_id` = %s ';
                    break;
            }
            $where .= $wpdb->prepare($sql, array($_REQUEST['id']));
        }

        return $this->get_results($columns, $where, NULL);
    }

    /**
     * Get the properties for the event types (e.g. Event name, Colors, Icons, etc)
     * @param string $type
     * @return array
     */
    public function get_event_type($type) {

        switch ($type) {
            case 'useyourdrive_previewed_entry':
                $text = __('Previews', 'useyourdrive');
                $icon = 'fa-eye';
                $colors = array(
                    'light' => '#9c27b050',
                    'normal' => '#9c27b0',
                    'dark' => '#7b1fa2'
                );
                break;
            case 'useyourdrive_downloaded_entry':
                $text = __('Downloads', 'useyourdrive');
                $icon = 'fa-download';
                $colors = array(
                    'light' => '#673ab750',
                    'normal' => '#673ab7',
                    'dark' => '#512da8'
                );
                break;
            case 'useyourdrive_streamed_entry':
                $text = __('Streams', 'useyourdrive');
                $icon = 'fa-play-circle';
                $colors = array(
                    'light' => '#3f51b550',
                    'normal' => '#3f51b5',
                    'dark' => '#303f9f'
                );
                break;

            case 'useyourdrive_created_link_to_entry':
                $text = __('Shared', 'useyourdrive');
                $icon = 'fa-share-alt';
                $colors = array(
                    'light' => '#00bcd450',
                    'normal' => '#00bcd4',
                    'dark' => '#0097a7'
                );
                break;
            case 'useyourdrive_renamed_entry':
                $text = __('Renamed', 'useyourdrive');
                $icon = 'fa-tag';
                $colors = array(
                    'light' => '#00968850',
                    'normal' => '#009688',
                    'dark' => '#00796b'
                );
                break;
            case 'useyourdrive_deleted_entry':
                $text = __('Deleted', 'useyourdrive');
                $icon = 'fa-trash';
                $colors = array(
                    'light' => '#f4433650',
                    'normal' => '#f44336',
                    'dark' => '#d32f2f'
                );
                break;
            case 'useyourdrive_created_entry':
                $text = __('Created', 'useyourdrive');
                $icon = 'fa-plus-circle';
                $colors = array(
                    'light' => '#4caf5050',
                    'normal' => '#4caf50',
                    'dark' => '#388e3c'
                );
                break;
            case 'useyourdrive_updated_metadata':
                $text = __('Updated metadata', 'useyourdrive');
                $icon = 'fa-wrench';
                $colors = array(
                    'light' => '#ff572250',
                    'normal' => '#ff5722',
                    'dark' => '#e64a19'
                );
                break;
            case 'useyourdrive_moved_entry':
                $text = __('Moved', 'useyourdrive');
                $icon = 'fa-arrows-alt-h';
                $colors = array(
                    'light' => '#ffeb3b50',
                    'normal' => 'ffeb3b',
                    'dark' => '#fbc02d'
                );
                break;
            case 'useyourdrive_uploaded_entry':
                $text = __('Uploads', 'useyourdrive');
                $icon = 'fa-upload';
                $colors = array(
                    'light' => '#cddc3950',
                    'normal' => '#cddc39',
                    'dark' => '#afb42b'
                );
                break;
            case 'useyourdrive_searched':
                $text = __('Searched', 'useyourdrive');
                $icon = 'fa-search';
                $colors = array(
                    'light' => '#ffc10750',
                    'normal' => '#ffc107',
                    'dark' => '#ffa000'
                );
                break;
            default:
                $text = $type;
                $icon = 'fa-star';
                $colors = array(
                    'light' => '#9e9e9e50',
                    'normal' => '#9e9e9e',
                    'dark' => '#424242'
                );
                break;
        }
        return array('text' => $text, 'icon' => $icon, 'colors' => $colors);
    }

    /**
     * Create an event description for a specific Event Database Row
     * 
     * @param string $event_row
     * @return string
     */
    public function get_event_description($event_row) {

        /* Get the User */
        if ($event_row['user_id'] === '0') {
            $user = __('A visitor', 'useyourdrive');
        } elseif ($wp_user = get_userdata($event_row['user_id'])) {
            $user = $wp_user->user_login;
            if ($wp_user->first_name) {
                $user = $wp_user->first_name;
                if ($wp_user->last_name) {
                    $user .= ' ' . $wp_user->last_name;
                }
            }
            $user = "<a href='#{$event_row['user_id']}' class='open-user-details' data-user-id='{$event_row['user_id']}'>{$user}</a>";
        } else {
            $user = __('User') . ' #' . $event_row['user_id'] . ' (' . __('Deleted') . ')';
        }

        /* Is entry  a file or a folder */
        $file_or_folder = ($event_row['entry_is_dir'] === '1') ? __('folder', 'useyourdrive') : __('file', 'useyourdrive');

        /* Generate File link */
        $entry_link = "<a href='#{$event_row['entry_id']}' title='{$event_row['parent_path']}/{$event_row['entry_name']}' class='open-entry-details' data-entry-id='{$event_row['entry_id']}'>{$event_row['entry_name']}</a>";

        /* Generate link to parent folder */
        $parent_folder_link = "<a href='#{$event_row['parent_id']}' title='{$event_row['parent_path']}' class='open-entry-details' data-entry-id='{$event_row['parent_id']}'>{$event_row['parent_path']}</a>";

        /* Decode the Extra data field */
        $data = json_decode($event_row['extra'], true);

        /* Create the description */
        switch ($event_row['type']) {
            case 'useyourdrive_previewed_entry':
                $description = sprintf(__('%s previewed the %s %s', 'useyourdrive'), $user, $file_or_folder, $entry_link);
                break;
            case 'useyourdrive_downloaded_entry':
                $exported = ($data && isset($data['exported'])) ? $data['exported'] : false;
                $as_zip = ($data && isset($data['as_zip'])) ? $exported = 'ZIP' : false;

                $description = sprintf(__('%s downloaded the %s %s', 'useyourdrive'), $user, $file_or_folder, $entry_link);

                if ($exported !== false) {
                    $description = sprintf(__('%s downloaded the %s %s as %s file', 'useyourdrive'), $user, $file_or_folder, $entry_link, $exported);
                }
                break;
            case 'useyourdrive_streamed_entry':
                $description = sprintf(__('%s streamed the %s %s', 'useyourdrive'), $user, $file_or_folder, $entry_link);
                break;
            case 'useyourdrive_created_link_to_entry':
                $created_url = ($data && isset($data['url'])) ? "<a href='{$data['url']}' target='_blank'>" . __('Shared link', 'useyourdrive') . "</a>" : '';
                $description = sprintf(__('%s created a %s for the %s %s', 'useyourdrive'), $user, $created_url, $file_or_folder, $entry_link);
                break;
            case 'useyourdrive_renamed_entry':
                $previous_name = ($data && isset($data['oldname'])) ? $data['oldname'] . ' ' : '';
                $description = sprintf(__('%s renamed the %s %s to %s', 'useyourdrive'), $user, $file_or_folder, $previous_name, $entry_link);
                break;
            case 'useyourdrive_deleted_entry':
                $description = sprintf(__('%s deleted the %s %s', 'useyourdrive'), $user, $file_or_folder, $entry_link);
                break;
            case 'useyourdrive_created_entry':
                $description = sprintf(__('%s added the %s %s in %s', 'useyourdrive'), $user, $file_or_folder, $entry_link, $parent_folder_link);
                break;
            case 'useyourdrive_updated_metadata':
                $metadata_field = ($data && isset($data['metadata_field'])) ? $data['metadata_field'] : '';
                $description = sprintf(__('%s updated the %s of the %s %s', 'useyourdrive'), $user, $metadata_field, $file_or_folder, $entry_link);
                break;
            case 'useyourdrive_moved_entry':
                $description = sprintf(__('%s moved the %s %s to %s', 'useyourdrive'), $user, $file_or_folder, $entry_link, $parent_folder_link);
                break;
            case 'useyourdrive_uploaded_entry':
                $description = sprintf(__('%s added the %s %s in %s', 'useyourdrive'), $user, $file_or_folder, $entry_link, $parent_folder_link);
                break;
            case 'useyourdrive_searched':
                $search_query = ($data && isset($data['query'])) ? $data['query'] : '';
                $description = sprintf(__('%s searched for: %s', 'useyourdrive'), $user, $search_query);
                break;
            default:
                $description = sprintf(__('%s performed an action: %s', 'useyourdrive'), $user, $event_row['type']);
                break;
        }

        return $description;
    }

    /**
     * Get the user details for Dashboard
     * 
     * @param string|int $user_id
     */
    public function render_user_detail($user_id) {
        $wp_user = get_userdata($user_id);

        if (function_exists('get_wp_user_avatar')) {
            $display_gravatar = get_wp_user_avatar_url($wp_user->user_email, array('size' => 512));
        } else {
            $display_gravatar = get_avatar_url($wp_user->user_email, array('size' => 512));
        }

        $user_name = $wp_user->user_login;
        if ($wp_user->first_name) {
            $user_name = $wp_user->first_name;
            if ($wp_user->last_name) {
                $user_name .= ' ' . $wp_user->last_name;
            }
        }


        $roles_translated = array();
        $wp_roles = new \WP_Roles();

        foreach ($wp_user->roles as $role) {
            $roles_translated[] = $wp_roles->role_names[$role];
        }


        $return = array(
            'user' => array(
                'user_id' => $wp_user->ID,
                'user_name' => $user_name,
                'user_email' => $wp_user->user_email,
                'user_link' => get_edit_user_link($wp_user->ID),
                'user_roles' => implode(', ', $roles_translated),
                'avatar' => $display_gravatar
            )
        );

        echo json_encode($return);
        die();
    }

    /**
     * Get the details for a specific entry
     * @param string|int $entry_id
     */
    public function render_entry_detail($entry_id) {

        $cached_entry = $this->get_processor()->get_client()->get_entry($entry_id, false);

        if ($cached_entry === false) {

            $sql = 'SELECT * FROM `' . Event_DB_Model::table() . '` WHERE `entry_id` = %s';
            $values = array($entry_id);
            $data = Event_DB_Model::get_custom_sql($sql, $values, 'ARRAY_A');

            $return = array(
                'entry' => array(
                    'entry_id' => $entry_id,
                    'entry_name' => $data[0]['entry_name'],
                    'entry_description' => __('The file you are looking for cannot be found', 'useyourdrive') . '.' . __('The file is probably deleted from the cloud', 'useyourdrive') . '.',
                    'entry_link' => false,
                    'entry_thumbnails' => Helpers::get_default_thumbnail_icon($data[0]['entry_mimetype'])
                )
            );

            echo json_encode($return);

            die();
        }

        $entry = $cached_entry->get_entry();

        $download_link = ($entry->is_file()) ? USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-event-stats&type=get-download&id=" . $entry->get_id() : false;

        $return = array(
            'entry' => array(
                'entry_id' => $entry->get_id(),
                'entry_name' => $entry->get_name(),
                'entry_description' => ($entry->get_description()) ? $entry->get_description() : '',
                'entry_link' => $download_link,
                'entry_thumbnails' => ($entry->get_thumbnail_large()) ? $entry->get_thumbnail_large() : $entry->get_default_thumbnail_icon()
            )
        );

        echo json_encode($return);

        die();
    }

    /**
     * Do all the SQL action for Datatables request via the SSP class
     */
    public function get_results($columns, $where, $groupBy, $join = true, $having = '', $limit = '') {
        $table = Event_DB_Model::table();

        $primaryKey = 'id';

        require_once( 'datatables/ssp.class.php' );

        return SSP::simple($_GET, $table, $primaryKey, $columns, $join, $where, $groupBy, $having, $limit);
    }

    /**
     * Create the event table in the WordPress Database
     * @global  $wpdb
     */
    static function install_database() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = Event_DB_Model::table();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `plugin` varchar(50) NOT NULL,
                    `type` varchar(50) NOT NULL,
                    `user_id` bigint(20) NOT NULL,
                    `entry_id` varchar(255) NOT NULL,
                    `entry_mimetype` varchar(255) NOT NULL,
                    `entry_is_dir` BOOLEAN NOT NULL, 
                    `entry_name` TEXT NOT NULL,
                    `parent_id` varchar(255) NOT NULL,
                    `parent_path` TEXT NOT NULL,
                    `location` TEXT,
                    `extra` TEXT NOT NULL,
                    UNIQUE KEY id (id)
                    ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }

    static function drop_database() {

        $table_name = Event_DB_Model::table();

        global $wpdb;
        $result = $wpdb->query("DROP TABLE IF EXISTS $table_name");

        return $result;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor 
     */
    public function get_processor() {
        return $this->get_main()->get_processor();
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Main
     */
    public function get_main() {
        return $this->_main;
    }

}

class Event_DB_Model {

    static $primary_key = 'id';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'wp_cloudplugins_log';
    }

    private static function _fetch_sql($value) {
        global $wpdb;
        $sql = sprintf('SELECT * FROM %s WHERE %s = %%s', self::table(), static::$primary_key);
        return $wpdb->prepare($sql, $value);
    }

    static function get($value) {
        global $wpdb;
        return $wpdb->get_row(self::_fetch_sql($value));
    }

    static function get_custom_sql($sql, $values, $output = "OBJECT") {
        global $wpdb;
        if (!empty($values)) {
            $prepared = $wpdb->prepare($sql, $values);
            return $wpdb->get_results($prepared, $output);
        } else {
            return $wpdb->get_results($sql, $output);
        }
    }

    static function insert($data) {
        global $wpdb;
        $wpdb->insert(self::table(), $data);
    }

    static function update($data, $where) {
        global $wpdb;
        $wpdb->update(self::table(), $data, $where);
    }

    static function delete($value) {
        global $wpdb;
        $sql = sprintf('DELETE FROM %s WHERE %s = %%s', self::table(), static::$primary_key);
        return $wpdb->query($wpdb->prepare($sql, $value));
    }

    static function insert_id() {
        global $wpdb;
        return $wpdb->insert_id;
    }

    static function time_to_date($time) {
        return gmdate('Y-m-d H:i:s', $time);
    }

    static function now() {
        return self::time_to_date(time());
    }

    static function date_to_time($date) {
        return strtotime($date . ' GMT');
    }

}
