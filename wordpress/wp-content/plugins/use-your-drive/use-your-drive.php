<?php

namespace TheLion\UseyourDrive;

/*
  Plugin Name: WP Cloud Plugin Use-your-Drive (Google Drive)
  Plugin URI: https://www.wpcloudplugins.com/plugins/use-your-drive-wordpress-plugin-for-google-drive/_
  Description: Say hello to the most popular WordPress Google Drive plugin! Start using the Cloud even more efficiently by integrating it on your website.
  Version: 1.11.10
  Author: WP Cloud Plugins
  Author URI: https://www.wpcloudplugins.com
  Text Domain: useyourdrive
 */
/* * ***********SYSTEM SETTINGS****************** */
define('USEYOURDRIVE_VERSION', '1.11.10');
define('USEYOURDRIVE_ROOTPATH', plugins_url('', __FILE__));
define('USEYOURDRIVE_ROOTDIR', __DIR__);
define('USEYOURDRIVE_CACHEDIR', WP_CONTENT_DIR . '/use-your-drive-cache');
define('USEYOURDRIVE_CACHEURL', WP_CONTENT_URL . '/use-your-drive-cache');
define('USEYOURDRIVE_SLUG', dirname(plugin_basename(__FILE__)) . '/use-your-drive.php');
define('USEYOURDRIVE_ADMIN_URL', admin_url('admin-ajax.php'));

require_once 'includes/Autoload.php';

class Main {

    public $settings = false;
    public $events;

    /**
     * Construct the plugin object
     */
    public function __construct() {
        $this->load_default_values();

        add_action('init', array(&$this, 'init'));

        if (is_admin() && (!defined('DOING_AJAX') ||
                (isset($_REQUEST['action']) && ($_REQUEST['action'] === 'update-plugin')))) {
            $admin = new \TheLion\UseyourDrive\Admin($this);
        }

        add_action('wp_head', array(&$this, 'load_IE_styles'));

        $priority = add_filter('use-your-drive_enqueue_priority', 10);
        add_action('wp_enqueue_scripts', array(&$this, 'load_scripts'), $priority);
        add_action('wp_enqueue_scripts', array(&$this, 'load_styles'));

        add_action('plugins_loaded', array(&$this, 'load_gravity_forms_addon'), 100);
        add_action('plugins_loaded', array(&$this, 'load_contact_form_addon'), 100);

        add_filter('woocommerce_integrations', array(&$this, 'load_woocommerce_addon'), 10);

        /* Shortcodes */
        add_shortcode('useyourdrive', array(&$this, 'create_template'));

        /* Hook to send notification emails when authorization is lost */
        add_action('useyourdrive_lost_authorisation_notification', array(&$this, 'send_lost_authorisation_notification'));

        /* After the Shortcode hook to make sure that the raw shortcode will not become visible when plugin isn't meeting the requirements */
        if ($this->can_run_plugin() === false) {
            return false;
        }

        /* Add user folder if needed */
        if (isset($this->settings['userfolder_oncreation']) && $this->settings['userfolder_oncreation'] === 'Yes') {
            add_action('user_register', array(&$this, 'user_folder_create'));
        }
        if (isset($this->settings['userfolder_update']) && $this->settings['userfolder_update'] === 'Yes') {
            add_action('profile_update', array(&$this, 'user_folder_update'), 100, 2);
        }
        if (isset($this->settings['userfolder_remove']) && $this->settings['userfolder_remove'] === 'Yes') {
            add_action('delete_user', array(&$this, 'user_folder_delete'));
        }

        add_action('wp_footer', array(&$this, 'load_custom_css'), 100);
        add_action('admin_footer', array(&$this, 'load_custom_css'), 100);

        /* Ajax calls */
        add_action('wp_ajax_nopriv_useyourdrive-get-filelist', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-get-filelist', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-search', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-search', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-get-gallery', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-get-gallery', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-upload-file', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-upload-file', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-delete-entry', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-delete-entry', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-delete-entries', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-delete-entries', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-rename-entry', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-rename-entry', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-move-entry', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-move-entry', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-edit-description-entry', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-edit-description-entry', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-add-folder', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-add-folder', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-get-playlist', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-get-playlist', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-create-zip', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-create-zip', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-download', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-download', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-stream', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-stream', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-preview', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-preview', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-thumbnail', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-thumbnail', array(&$this, 'start_process'));

        add_action('wp_ajax_nopriv_useyourdrive-create-link', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-create-link', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-embedded', array(&$this, 'start_process'));

        add_action('wp_ajax_useyourdrive-reset-cache', array(&$this, 'start_process'));
        add_action('wp_ajax_useyourdrive-revoke', array(&$this, 'start_process'));

        add_action('wp_ajax_useyourdrive-getpopup', array(&$this, 'get_popup'));

        add_action('wp_ajax_nopriv_useyourdrive-embed-image', array(&$this, 'embed_image'));
        add_action('wp_ajax_useyourdrive-embed-image', array(&$this, 'embed_image'));

        add_action('wp_ajax_useyourdrive-linkusertofolder', array(&$this, 'user_folder_link'));
        add_action('wp_ajax_useyourdrive-unlinkusertofolder', array(&$this, 'user_folder_unlink'));
        add_action('wp_ajax_useyourdrive-rating-asked', array(&$this, 'rating_asked'));

        /* add settings link on plugin page */
        add_filter('plugin_row_meta', array(&$this, 'add_settings_link'), 10, 2);

        /* Cron action to update cache */
        add_action('useyourdrive_synchronize_cache', array(&$this, 'synchronize_cache'));

        if (isset($this->settings['log_events']) && $this->settings['log_events'] === 'Yes') {
            $this->events = new \TheLion\UseyourDrive\Events($this);
        }

        define('USEYOURDRIVE_ICON_SET', $this->settings['icon_set']);

        /* Load Gutenberg class */
        //new Gutenberg($this);
    }

    public function init() {
        /* Localize */
        $i18n_dir = dirname(plugin_basename(__FILE__)) . '/languages/';
        load_plugin_textdomain('useyourdrive', false, $i18n_dir);

        /* Cron Job */
        $cron = wp_next_scheduled('useyourdrive_synchronize_cache');
        if ($cron === false && $this->settings['cache_update_via_wpcron'] === 'Yes') {
            wp_schedule_event(time(), 'wp_cloudplugins_20min', 'useyourdrive_synchronize_cache');
        }
    }

    public function can_run_plugin() {
        if ((version_compare(PHP_VERSION, '5.4.0') < 0) || (!function_exists('curl_init'))) {
            return false;
        }

        /* Check Cache Folder */
        if (!file_exists(USEYOURDRIVE_CACHEDIR)) {
            @mkdir(USEYOURDRIVE_CACHEDIR, 0755);
        }

        if (!is_writable(USEYOURDRIVE_CACHEDIR)) {
            @chmod(USEYOURDRIVE_CACHEDIR, 0755);

            if (!is_writable(USEYOURDRIVE_CACHEDIR)) {
                return false;
            }
        }

        if (!file_exists(USEYOURDRIVE_CACHEDIR . '/.htaccess')) {
            return copy(USEYOURDRIVE_ROOTDIR . '/cache/.htaccess', USEYOURDRIVE_CACHEDIR . '/.htaccess');
        }

        return true;
    }

    public function load_default_values() {

        $this->settings = get_option('use_your_drive_settings', array(
            'googledrive_app_client_id' => '',
            'googledrive_app_client_secret' => '',
            'purcase_code' => '',
            'permissions_edit_settings' => array('administrator'),
            'permissions_link_users' => array('administrator', 'editor'),
            'permissions_see_dashboard' => array('administrator', 'editor'),
            'permissions_see_filebrowser' => array('administrator'),
            'permissions_add_shortcodes' => array('administrator', 'editor', 'author', 'contributor'),
            'permissions_add_links' => array('administrator', 'editor', 'author', 'contributor'),
            'permissions_add_embedded' => array('administrator', 'editor', 'author', 'contributor'),
            'custom_css' => '',
            'loaders' => array(),
            'colors' => array(),
            'google_analytics' => 'No',
            'loadimages' => 'googlethumbnail',
            'lightbox_skin' => 'metro-black',
            'lightbox_path' => 'horizontal',
            'lightbox_rightclick' => 'No',
            'lightbox_showcaption' => 'click',
            'mediaplayer_skin' => 'default',
            'userfolder_name' => '%user_login% (%user_email%)',
            'userfolder_oncreation' => 'Yes',
            'userfolder_onfirstvisit' => 'No',
            'userfolder_update' => 'Yes',
            'userfolder_remove' => 'Yes',
            'userfolder_backend' => 'No',
            'userfolder_backend_auto_root' => '',
            'download_template_subject' => '',
            'download_template_subject_zip' => '',
            'download_template' => '',
            'upload_template_subject' => '',
            'upload_template' => '',
            'delete_template_subject' => '',
            'delete_template' => '',
            'filelist_template' => '',
            'manage_permissions' => 'Yes',
            'teamdrives' => 'No',
            'permission_domain' => '',
            'download_method' => 'redirect',
            'lostauthorization_notification' => get_site_option('admin_email'),
            'gzipcompression' => 'No',
            'cache' => 'filesystem',
            'shortlinks' => 'None',
            'bitly_login' => '',
            'bitly_apikey' => '',
            'shortest_apikey' => '',
            'rebrandly_apikey' => '',
            'rebrandly_domain' => '',
            'rebrandly_workspace' => '',
            'always_load_scripts' => 'No',
            'nonce_validation' => 'Yes',
            'cache_update_via_wpcron' => 'Yes',
            'log_events' => 'Yes',
            'icon_set' => '',
        ));

        if ($this->settings === false) {
            return;
        }

        /* Remove 'advancedsettings' option of versions before 1.3.4 */
        $advancedsettings = get_option('use_your_drive_advancedsettings');
        if ($advancedsettings !== false && $this->settings !== false) {
            $this->settings = array_merge($this->settings, $advancedsettings);
            delete_option('use_your_drive_advancedsettings');
            update_option('use_your_drive_settings', $this->settings);
            $this->settings = get_option('use_your_drive_settings');
        }

        $updated = false;
        /* Set default values */

        if (empty($this->settings['google_analytics'])) {
            $this->settings['google_analytics'] = 'No';
            $updated = true;
        }

        if (empty($this->settings['download_template_subject'])) {
            $this->settings['download_template_subject'] = '%sitename% | %visitor% downloaded %filepath%';
            $updated = true;
        }

        if (empty($this->settings['download_template_subject_zip'])) {
            $this->settings['download_template_subject_zip'] = '%sitename% | %visitor% downloaded %number_of_files% file(s) from %folder%';
            $updated = true;
        }

        if (empty($this->settings['download_template'])) {
            $this->settings['download_template'] = 'Hi!

%visitor% has downloaded the following files from your site: 

<ul>%filelist%</ul>';
            $updated = true;
        }

        if (empty($this->settings['upload_template_subject'])) {
            $this->settings['upload_template_subject'] = '%sitename% | %visitor% uploaded (%number_of_files%) file(s) to %folder%';
            $updated = true;
        }

        if (empty($this->settings['upload_template'])) {
            $this->settings['upload_template'] = 'Hi!

%visitor% has uploaded the following file(s) to your Google Drive:

<ul>%filelist%</ul>';
            $updated = true;
        }

        if (empty($this->settings['delete_template_subject'])) {
            $this->settings['delete_template_subject'] = '%sitename% | %visitor% deleted (%number_of_files%) file(s) from %folder%';
            $updated = true;
        }

        if (empty($this->settings['delete_template'])) {
            $this->settings['delete_template'] = 'Hi!

%visitor% has deleted the following file(s) on your Google Drive:

<ul>%filelist%</ul>';
            $updated = true;
        }

        if (empty($this->settings['filelist_template'])) {
            $this->settings['filelist_template'] = '<li><a href="%fileurl%">%filename%</a> (%filesize%)</li>';
            $updated = true;
        } elseif ($this->settings['filelist_template'] === '<li><a href="%fileurl%">%filename%</a> (%filesize%)</li>') {
            $this->settings['filelist_template'] = '<li><a href="%fileurl%">%filepath%</a> (%filesize%)</li>';
            $updated = true;
        }

        if (empty($this->settings['mediaplayer_skin'])) {
            $this->settings['mediaplayer_skin'] = 'default';
            $updated = true;
        }

        if (empty($this->settings['loadimages'])) {
            $this->settings['loadimages'] = 'googlethumbnail';
            $updated = true;
        }
        if (empty($this->settings['lightbox_skin'])) {
            $this->settings['lightbox_skin'] = 'metro-black';
            $updated = true;
        }
        if (empty($this->settings['lightbox_path'])) {
            $this->settings['lightbox_path'] = 'horizontal';
            $updated = true;
        }

        if (empty($this->settings['manage_permissions'])) {
            $this->settings['manage_permissions'] = 'Yes';
            $updated = true;
        }

        if (!isset($this->settings['permission_domain'])) {
            $this->settings['permission_domain'] = '';
            $updated = true;
        }

        if (empty($this->settings['teamdrives'])) {
            $this->settings['teamdrives'] = 'No';
            $updated = true;
        }

        if (empty($this->settings['lostauthorization_notification'])) {
            $this->settings['lostauthorization_notification'] = get_site_option('admin_email');
            $updated = true;
        }

        if (empty($this->settings['gzipcompression'])) {
            $this->settings['gzipcompression'] = 'No';
            $updated = true;
        }

        if (empty($this->settings['cache'])) {
            $this->settings['cache'] = 'filesystem';
            $updated = true;
        }

        if (empty($this->settings['shortlinks'])) {
            $this->settings['shortlinks'] = 'None';
            $this->settings['bitly_login'] = '';
            $this->settings['bitly_apikey'] = '';
            $updated = true;
        }

        if (empty($this->settings['permissions_edit_settings'])) {
            $this->settings['permissions_edit_settings'] = array('administrator');
            $updated = true;
        }
        if (empty($this->settings['permissions_link_users'])) {
            $this->settings['permissions_link_users'] = array('administrator', 'editor');
            $updated = true;
        }
        if (empty($this->settings['permissions_see_filebrowser'])) {
            $this->settings['permissions_see_filebrowser'] = array('administrator');
            $updated = true;
        }
        if (empty($this->settings['permissions_add_shortcodes'])) {
            $this->settings['permissions_add_shortcodes'] = array('administrator', 'editor', 'author', 'contributor');
            $updated = true;
        }
        if (empty($this->settings['permissions_add_links'])) {
            $this->settings['permissions_add_links'] = array('administrator', 'editor', 'author', 'contributor');
            $updated = true;
        }
        if (empty($this->settings['permissions_add_embedded'])) {
            $this->settings['permissions_add_embedded'] = array('administrator', 'editor', 'author', 'contributor');
            $updated = true;
        }

        if (empty($this->settings['download_method'])) {
            $this->settings['download_method'] = 'redirect';
            $updated = true;
        }

        if (empty($this->settings['userfolder_backend'])) {
            $this->settings['userfolder_backend'] = 'No';
            $updated = true;
        }

        if (!isset($this->settings['userfolder_backend_auto_root'])) {
            $this->settings['userfolder_backend_auto_root'] = '';
            $updated = true;
        }

        if (empty($this->settings['colors'])) {
            $this->settings['colors'] = array(
                'style' => 'light',
                'background' => '#f2f2f2',
                'accent' => '#29ADE2',
                'black' => '#222',
                'dark1' => '#666',
                'dark2' => '#999',
                'white' => '#fff',
                'light1' => '#fcfcfc',
                'light2' => '#e8e8e8',
            );
            $updated = true;
        }

        if (empty($this->settings['loaders'])) {
            $this->settings['loaders'] = array(
                'style' => 'spinner',
                'loading' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_loading.gif',
                'no_results' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_no_results.png',
                'error' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_error.png',
                'upload' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_upload.gif',
                'protected' => USEYOURDRIVE_ROOTPATH . '/css/images/loader_protected.png',
            );
            $updated = true;
        }

        if (empty($this->settings['lightbox_rightclick'])) {
            $this->settings['lightbox_rightclick'] = 'No';
            $updated = true;
        }

        if (empty($this->settings['lightbox_showcaption'])) {
            $this->settings['lightbox_showcaption'] = 'click';
            $updated = true;
        }

        if (empty($this->settings['always_load_scripts'])) {
            $this->settings['always_load_scripts'] = 'No';
            $updated = true;
        }

        if (empty($this->settings['nonce_validation'])) {
            $this->settings['nonce_validation'] = 'Yes';
            $updated = true;
        }

        if (!isset($this->settings['shortest_apikey'])) {
            $this->settings['shortest_apikey'] = '';
            $this->settings['rebrandly_apikey'] = '';
            $this->settings['rebrandly_domain'] = '';
            $updated = true;
        }

        if (!isset($this->settings['rebrandly_workspace'])) {
            $this->settings['rebrandly_workspace'] = '';
            $updated = true;
        }

        if (empty($this->settings['permissions_see_dashboard'])) {
            $this->settings['permissions_see_dashboard'] = array('administrator', 'editor');
            $updated = true;
        }

        if (!isset($this->settings['cache_update_via_wpcron'])) {
            $this->settings['cache_update_via_wpcron'] = 'Yes';
            $updated = true;
        }

        if (empty($this->settings['log_events'])) {
            $this->settings['log_events'] = 'Yes';
            $updated = true;
        }

        if (empty($this->settings['icon_set']) || $this->settings['icon_set'] === '/') {
            $this->settings['icon_set'] = USEYOURDRIVE_ROOTPATH . '/css/icons/';
            $updated = true;
        }

        if ($updated) {
            update_option('use_your_drive_settings', $this->settings);
        }

        $version = get_option('use_your_drive_version');

        if (version_compare($version, '1.11') < 0) {
            /* Install Event Database */
            $this->get_events()->install_database();
        }

        /* Update Version number */
        if ($version !== USEYOURDRIVE_VERSION) {

            /* Clear Cache */
            $this->get_processor()->reset_complete_cache();

            update_option('use_your_drive_version', USEYOURDRIVE_VERSION);
        }
    }

    public function add_settings_link($links, $file) {
        $plugin = plugin_basename(__FILE__);

        /* create link */
        if ($file == $plugin && !is_network_admin()) {
            return array_merge(
                    $links, array(sprintf('<a href="https://wpcloudplugins.com/updates" target="_blank">%s</a>', __('Download latest package', 'useyourdrive'))), array(sprintf('<a href="options-general.php?page=%s">%s</a>', 'UseyourDrive_settings', __('Settings', 'useyourdrive'))), array(sprintf('<a href="' . plugins_url('_documentation/index.html', __FILE__) . '" target="_blank">%s</a>', __('Documentation', 'useyourdrive'))), array(sprintf('<a href="https://florisdeleeuwnl.zendesk.com/hc/en-us" target="_blank">%s</a>', __('Support', 'useyourdrive')))
            );
        }

        return $links;
    }

    public function load_scripts() {

        $skin = $this->settings['mediaplayer_skin'];
        if ((!file_exists(USEYOURDRIVE_ROOTDIR . "/skins/$skin/Media.js")) ||
                (!file_exists(USEYOURDRIVE_ROOTDIR . "/skins/$skin/css/style.css")) ||
                (!file_exists(USEYOURDRIVE_ROOTDIR . "/skins/$skin/player.php"))) {
            $skin = 'default';
        }

        wp_register_style('UseyourDrive.Media', plugins_url("/skins/$skin/css/style.css", __FILE__), false, USEYOURDRIVE_VERSION);
        wp_register_script('jQuery.jplayer', plugins_url("/skins/$skin/jquery.jplayer/jplayer.playlist.min.js", __FILE__), array('jquery'), USEYOURDRIVE_VERSION);
        wp_register_script('jQuery.jplayer.playlist', plugins_url("/skins/$skin/jquery.jplayer/jquery.jplayer.min.js", __FILE__), array('jquery'), USEYOURDRIVE_VERSION);

        /* load in footer */
        wp_register_script('UseyourDrive.Media', plugins_url("/skins/$skin/Media.js", __FILE__), array('jquery'), USEYOURDRIVE_VERSION, true);
        wp_register_script('jQuery.iframe-transport', plugins_url('includes/jquery-file-upload/js/jquery.iframe-transport.js', __FILE__), array('jquery'), false, true);
        wp_register_script('jQuery.fileupload-uyd', plugins_url('includes/jquery-file-upload/js/jquery.fileupload.js', __FILE__), array('jquery'), false, true);
        wp_register_script('jQuery.fileupload-process', plugins_url('includes/jquery-file-upload/js/jquery.fileupload-process.js', __FILE__), array('jquery'), false, true);

        wp_register_script('WPCloudplugin.Libraries', plugins_url('includes/js/library.js', __FILE__), array('jquery'), USEYOURDRIVE_VERSION, true);
        wp_register_script('UseyourDrive', plugins_url('includes/js/Main.min.js', __FILE__), array('jquery', 'jquery-ui-widget', 'WPCloudplugin.Libraries'), USEYOURDRIVE_VERSION, true);

        wp_register_script('UseyourDrive.tinymce', plugins_url('includes/js/Tinymce_popup.js', __FILE__), array('jquery'), USEYOURDRIVE_VERSION, true);

        /* Scripts for the Event Dashboard */
        wp_register_script('UseyourDrive.Datatables', plugins_url('includes/datatables/datatables.min.js', __FILE__), array('jquery'), USEYOURDRIVE_VERSION, true);
        wp_register_script('UseyourDrive.ChartJs', plugins_url('includes/chartjs/Chart.bundle.min.js', __FILE__), array('jquery', 'jquery-ui-datepicker'), USEYOURDRIVE_VERSION, true);
        wp_register_script('UseyourDrive.Dashboard', plugins_url('includes/js/Dashboard.min.js', __FILE__), array('UseyourDrive.Datatables', 'UseyourDrive.ChartJs', 'jquery-ui-widget', 'WPCloudplugin.Libraries'), USEYOURDRIVE_VERSION, true);

        $post_max_size_bytes = min(Helpers::return_bytes(ini_get('post_max_size')), Helpers::return_bytes(ini_get('upload_max_filesize')));

        $localize = array(
            'plugin_ver' => USEYOURDRIVE_VERSION,
            'plugin_url' => plugins_url('', __FILE__),
            'ajax_url' => USEYOURDRIVE_ADMIN_URL,
            'js_url' => plugins_url('/skins/' . $this->settings['mediaplayer_skin'] . '/jquery.jplayer', __FILE__),
            'cookie_path' => COOKIEPATH,
            'cookie_domain' => COOKIE_DOMAIN,
            'is_mobile' => wp_is_mobile(),
            'content_skin' => $this->settings['colors']['style'],
            'icons_set' => $this->settings['icon_set'],
            'lightbox_skin' => $this->settings['lightbox_skin'],
            'lightbox_path' => $this->settings['lightbox_path'],
            'lightbox_rightclick' => $this->settings['lightbox_rightclick'],
            'lightbox_showcaption' => $this->settings['lightbox_showcaption'],
            'post_max_size' => $post_max_size_bytes,
            'google_analytics' => (($this->settings['google_analytics'] === 'Yes') ? 1 : 0),
            'log_events' => (($this->settings['log_events'] === 'Yes') ? 1 : 0),
            'refresh_nonce' => wp_create_nonce("useyourdrive-get-filelist"),
            'gallery_nonce' => wp_create_nonce("useyourdrive-get-gallery"),
            'upload_nonce' => wp_create_nonce("useyourdrive-upload-file"),
            'delete_nonce' => wp_create_nonce("useyourdrive-delete-entry"),
            'rename_nonce' => wp_create_nonce("useyourdrive-rename-entry"),
            'move_nonce' => wp_create_nonce("useyourdrive-move-entry"),
            'log_nonce' => wp_create_nonce("useyourdrive-log"),
            'description_nonce' => wp_create_nonce("useyourdrive-edit-description-entry"),
            'addfolder_nonce' => wp_create_nonce("useyourdrive-add-folder"),
            'getplaylist_nonce' => wp_create_nonce("useyourdrive-get-playlist"),
            'createzip_nonce' => wp_create_nonce("useyourdrive-create-zip"),
            'createlink_nonce' => wp_create_nonce("useyourdrive-create-link"),
            'str_loading' => __('Hang on. Waiting for the files...', 'useyourdrive'),
            'str_processing' => __('Processing...', 'useyourdrive'),
            'str_success' => __('Success', 'useyourdrive'),
            'str_error' => __('Error', 'useyourdrive'),
            'str_inqueue' => __('Waiting', 'useyourdrive'),
            'str_uploading_no_limit' => __('Unlimited', 'useyourdrive'),
            'str_uploading' => __('Uploading...', 'useyourdrive'),
            'str_uploading_local' => __('Uploading to Server', 'useyourdrive'),
            'str_uploading_cloud' => __('Uploading to Cloud', 'useyourdrive'),
            'str_uploading_convert' => __('Converting', 'useyourdrive'),
            'str_error_title' => __('Error', 'useyourdrive'),
            'str_close_title' => __('Close', 'useyourdrive'),
            'str_start_title' => __('Start', 'useyourdrive'),
            'str_cancel_title' => __('Cancel', 'useyourdrive'),
            'str_delete_title' => __('Delete', 'useyourdrive'),
            'str_save_title' => __('Save', 'useyourdrive'),
            'str_zip_title' => __('Create zip file', 'useyourdrive'),
            'str_copy_to_clipboard_title' => __('Copy to clipboard', 'useyourdrive'),
            'str_delete' => __('Do you really want to delete:', 'useyourdrive'),
            'str_delete_multiple' => __('Do you really want to delete these files?', 'useyourdrive'),
            'str_rename_failed' => __("That doesn't work. Are there any illegal characters (<>:\"/\|?*) in the filename?", 'useyourdrive'),
            'str_rename_title' => __('Rename', 'useyourdrive'),
            'str_rename' => __('Rename to:', 'useyourdrive'),
            'str_no_filelist' => __("Oops! This shouldn't happen... Try again!", 'useyourdrive'),
            'str_addfolder_title' => __('Add folder', 'useyourdrive'),
            'str_addfolder' => __('New folder', 'useyourdrive'),
            'str_zip_nofiles' => __('No files found or selected', 'useyourdrive'),
            'str_zip_createzip' => __('Creating zip file', 'useyourdrive'),
            'str_share_link' => __('Share file', 'useyourdrive'),
            'str_create_shared_link' => __('Creating shared link...', 'useyourdrive'),
            'str_previous_title' => __('Previous', 'useyourdrive'),
            'str_next_title' => __('Next', 'useyourdrive'),
            'str_xhrError_title' => __('This content failed to load', 'useyourdrive'),
            'str_imgError_title' => __('This image failed to load', 'useyourdrive'),
            'str_startslideshow' => __('Start slideshow', 'useyourdrive'),
            'str_stopslideshow' => __('Stop slideshow', 'useyourdrive'),
            'str_nolink' => __('Not yet linked to a folder', 'useyourdrive'),
            'maxNumberOfFiles' => __('Maximum number of files exceeded', 'useyourdrive'),
            'acceptFileTypes' => __('File type not allowed', 'useyourdrive'),
            'maxFileSize' => __('File is too large', 'useyourdrive'),
            'minFileSize' => __('File is too small', 'useyourdrive'),
            'str_iframe_loggedin' => "<div class='empty_iframe'><h1>" . __('Still Waiting?', 'useyourdrive') . "</h1><span>" . __("If the document doesn't open, you are probably trying to access a protected file which requires you to be logged in on Google.", 'useyourdrive') . " <strong><a href='#' target='_blank' class='empty_iframe_link'>" . __('Try to open the file in a new window.', 'useyourdrive') . "</a></strong></span></div>"
        );

        $localize_dashboard = array(
            'ajax_url' => USEYOURDRIVE_ADMIN_URL,
            'admin_nonce' => wp_create_nonce("useyourdrive-admin-action"),
            'str_close_title' => __('Close', 'useyourdrive'),
            'str_details_title' => __('Details', 'useyourdrive'),
            'content_skin' => $this->settings['colors']['style'],
        );

        wp_localize_script('UseyourDrive', 'UseyourDrive_vars', $localize);
        wp_localize_script('UseyourDrive.Dashboard', 'UseyourDrive_Dashboard_vars', $localize_dashboard);

        if ($this->settings['always_load_scripts'] === 'Yes') {
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-button');
            wp_enqueue_script('jquery-ui-progressbar');
            wp_enqueue_script('jQuery.iframe-transport');
            wp_enqueue_script('jQuery.fileupload-uyd');
            wp_enqueue_script('jQuery.fileupload-process');
            wp_enqueue_script('jQuery.jplayer');
            wp_enqueue_script('jQuery.jplayer.playlist');
            wp_enqueue_script('UseyourDrive.Media');
            wp_enqueue_script('jquery-effects-core');
            wp_enqueue_script('jquery-effects-fade');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('UseyourDrive');
        }
    }

    public function load_styles() {

        $is_rtl_css = (is_rtl() ? '-rtl' : '');

        $skin = $this->settings['lightbox_skin'];
        wp_register_style('ilightbox', plugins_url('includes/iLightBox/css/ilightbox.css', __FILE__));
        wp_register_style('ilightbox-skin-useyourdrive', plugins_url('includes/iLightBox/' . $skin . '-skin/skin.css', __FILE__));
        wp_register_style('qtip', plugins_url('includes/jquery-qTip/jquery.qtip.min.css', __FILE__), null, false);
        wp_register_style('Awesome-Font-5-css', plugins_url('includes/font-awesome/css/fontawesome-all.min.css', __FILE__), false, USEYOURDRIVE_VERSION);
        wp_register_style('UseyourDrive', plugins_url("css/main$is_rtl_css.css", __FILE__), array('Awesome-Font-5-css'), USEYOURDRIVE_VERSION);
        wp_register_style('UseyourDrive.tinymce', plugins_url("css/tinymce$is_rtl_css.css", __FILE__), array('Awesome-Font-5-css'), USEYOURDRIVE_VERSION);

        /* Scripts for the Event Dashboard */
        wp_register_style('UseyourDrive.Datatables.css', plugins_url('includes/datatables/datatables.min.css', __FILE__), null, USEYOURDRIVE_VERSION);


        if ($this->settings['always_load_scripts'] === 'Yes') {
            wp_enqueue_style('ilightbox');
            wp_enqueue_style('ilightbox-skin-useyourdrive');
            wp_enqueue_style('qtip');
            wp_enqueue_style('UseyourDrive.Media');
            wp_enqueue_style('UseyourDrive');
        }
    }

    public function load_IE_styles() {
        echo "<!--[if IE]>\n";
        echo "<link rel='stylesheet' type='text/css' href='" . plugins_url('css/skin-ie.css', __FILE__) . "' />\n";
        echo "<![endif]-->\n";
        echo "<!--[if IE 8]>\n";
        echo "<style>#UseyourDrive .uyd-grid .entry_thumbnail img{margin:0!important}</style>\n";
        echo "<![endif]-->\n";
    }

    public function load_gravity_forms_addon() {
        if (!class_exists("GFForms") || version_compare(\GFCommon::$version, '1.9', '>=') === false) {
            return;
        }

        require_once 'includes/GravityForms.php';
    }

    public function load_contact_form_addon() {
        if (!defined("WPCF7_PLUGIN")) {
            return;
        }

        if (!defined("WPCF7_VERSION") || version_compare(WPCF7_VERSION, '5.0', '>=') === false) {
            return;
        }

        require_once 'includes/ContactForm7.php';
    }

    public function load_woocommerce_addon($integrations) {
        global $woocommerce;

        if (is_object($woocommerce) && version_compare($woocommerce->version, '3.0', '>=')) {
            $integrations[] = __NAMESPACE__ . '\WooCommerce';
        }

        return $integrations;
    }

    public function start_process() {

        if (!isset($_REQUEST['action'])) {
            return false;
        }

        switch ($_REQUEST['action']) {
            case 'useyourdrive-get-filelist':
            case 'useyourdrive-download':
            case 'useyourdrive-stream':
            case 'useyourdrive-preview':
            case 'useyourdrive-thumbnail':
            case 'useyourdrive-create-zip':
            case 'useyourdrive-create-link':
            case 'useyourdrive-embedded':
            case 'useyourdrive-reset-cache':
            case 'useyourdrive-revoke':
            case 'useyourdrive-get-gallery':
            case 'useyourdrive-upload-file':
            case 'useyourdrive-delete-entry':
            case 'useyourdrive-delete-entries':
            case 'useyourdrive-rename-entry':
            case 'useyourdrive-move-entry':
            case 'useyourdrive-edit-description-entry':
            case 'useyourdrive-add-folder':
            case 'useyourdrive-get-playlist':
                require_once(ABSPATH . 'wp-includes/pluggable.php');
                $this->get_processor()->start_process();
                break;
        }
    }

    public function load_custom_css() {
        $css_html = '<!-- Custom UseyourDrive CSS Styles -->' . "\n";
        $css_html .= '<style type="text/css" media="screen">' . "\n";
        $css = '';

        if (!empty($this->settings['custom_css'])) {
            $css .= $this->settings['custom_css'] . "\n";
        }

        if ($this->settings['loaders']['style'] === 'custom') {
            $css .= "#UseyourDrive .loading{  background-image: url(" . $this->settings['loaders']['loading'] . ");}" . "\n";
            $css .= "#UseyourDrive .loading.upload{    background-image: url(" . $this->settings['loaders']['upload'] . ");}" . "\n";
            $css .= "#UseyourDrive .loading.error{  background-image: url(" . $this->settings['loaders']['error'] . ");}" . "\n";
            $css .= "#UseyourDrive .no_results{  background-image: url(" . $this->settings['loaders']['no_results'] . ");}" . "\n";
        }

        $css .= 'iframe[src*="useyourdrive"] {background: url(' . USEYOURDRIVE_ROOTPATH . '/css/images/iframeloader.gif);background-repeat: no-repeat;background-position: center center;}' . "\n";

        $css .= $this->get_color_css();

        $css_html .= \TheLion\UseyourDrive\Helpers::compress_css($css);
        $css_html .= '</style>' . "\n";

        echo $css_html;
    }

    public function get_color_css() {
        $css = file_get_contents(USEYOURDRIVE_ROOTDIR . '/css/skin.' . $this->settings['colors']['style'] . '.min.css');
        return preg_replace_callback('/%(.*)%/iU', array(&$this, 'fill_placeholder_styles'), $css);
    }

    public function fill_placeholder_styles($matches) {
        if (isset($this->settings['colors'][$matches[1]])) {
            return $this->settings['colors'][$matches[1]];
        }

        return 'initial';
    }

    public function create_template($atts = array()) {

        if (is_feed()) {
            return __('Please browse to the page to see this content', 'useyourdrive') . '.';
        }

        if ($this->can_run_plugin() === false) {
            return '<i>>>> ' . __('ERROR: Contact the Administrator to see this content', 'useyourdrive') . ' <<<</i>';
        }

        return $this->get_processor()->create_from_shortcode($atts);
    }

    public function get_popup() {
        include USEYOURDRIVE_ROOTDIR . '/templates/tinymce_popup.php';
        die();
    }

    public function embed_image() {
        $entryid = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;

        if (empty($entryid)) {
            die('-1');
        }

        $this->get_processor()->embed_image($entryid);
        die();
    }

    public function send_lost_authorisation_notification() {
        /* Notify the Admin in case of failure */
        $subject = get_bloginfo() . ' | ' . __('ACTION REQUIRED: WP Cloud Plugin lost authorization to Google Drive account', 'useyourdrive');
        $message = "Hello webmaster,

The Use-your-Drive plugin on " . get_site_url() . " is not able to refresh access to your Google Drive. Please relink the plugin to your account by visiting the Admin Dashboard via: " . admin_url('admin.php?page=UseyourDrive_settings') . ". In case you are not able to reauthorize the plugin, please check the status of the Google Drive API via https://www.google.com/appsstatus and https://status.cloud.google.com/.

If you are receiving those emails often, enable your WP_DEBUG log to allow the plugin to log the error information it receives from the API and contact the WP Cloud Plugins support team.

Kind regards,

WP Cloud Plugins
www.wpcloudplugins.com
";

        $result = wp_mail($this->settings['lostauthorization_notification'], $subject, $message);
    }

    public function ask_for_review($force = false) {

        $rating_asked = get_option('use_your_drive_rating_asked', false);
        if ($rating_asked == true) {
            return;
        }
        $counter = get_option('use_your_drive_shortcode_opened', 0);
        if ($counter < 10) {
            return;
        }
        ?>

        <div class="enjoying-container lets-ask">
          <div class="enjoying-text"><?php _e('Enjoying Use-your-Drive?', 'useyourdrive'); ?></div>
          <div class="enjoying-buttons">
            <a class="enjoying-button" id="enjoying-button-lets-ask-no"><?php _e('Not really', 'useyourdrive'); ?></a>
            <a class="enjoying-button default"  id="enjoying-button-lets-ask-yes"><?php _e('Yes!', 'useyourdrive'); ?></a>
          </div>
        </div>

        <div class="enjoying-container go-for-it" style="display:none">
          <div class="enjoying-text"><?php _e('Great! How about a review, then?', 'useyourdrive'); ?></div>
          <div class="enjoying-buttons">
            <a class="enjoying-button" id="enjoying-button-go-for-it-no"><?php _e('No, thanks', 'useyourdrive'); ?></a>
            <a class="enjoying-button default" id="enjoying-button-go-for-it-yes" href="https://1.envato.market/c/1260925/275988/4415?u=https%3A%2F%2Fcodecanyon.net%2Fitem%2Fuseyourdrive-google-drive-plugin-for-wordpress%2Freviews%2F6219776" target="_blank"><?php _e('Ok, sure!', 'useyourdrive'); ?></a>
          </div>
        </div>

        <div class="enjoying-container mwah" style="display:none">
          <div class="enjoying-text"><?php _e('Would you mind giving us some feedback?', 'useyourdrive'); ?></div>
          <div class="enjoying-buttons">
            <a class="enjoying-button" id="enjoying-button-mwah-no"><?php _e('No, thanks', 'useyourdrive'); ?></a>
            <a class="enjoying-button default" id="enjoying-button-mwah-yes" href="https://docs.google.com/forms/d/e/1FAIpQLSct8a8d-_7iSgcvdqeFoSSV055M5NiUOgt598B95YZIaw7LhA/viewform?usp=pp_url&entry.83709281=Use-your-Drive+(Google+Drive)&entry.450972953&entry.1149244898" target="_blank"><?php _e('Ok, sure!', 'useyourdrive'); ?></a>
          </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function ($) {
              $('#enjoying-button-lets-ask-no').click(function () {
                $('.enjoying-container.lets-ask').fadeOut('fast', function () {
                  $('.enjoying-container.mwah').fadeIn();
                })
              });

              $('#enjoying-button-lets-ask-yes').click(function () {
                $('.enjoying-container.lets-ask').fadeOut('fast', function () {
                  $('.enjoying-container.go-for-it').fadeIn();
                })
              });

              $('#enjoying-button-mwah-no, #enjoying-button-go-for-it-no').click(function () {
                $('.enjoying-container').fadeOut('fast', function () {
                  $(this).remove();
                });
              });

              $('#enjoying-button-go-for-it-yes').click(function () {
                $('.enjoying-container').fadeOut('fast', function () {
                  $(this).remove();
                });
              });

              $('#enjoying-button-mwah-yes').click(function () {
                $('.enjoying-container').fadeOut('fast', function () {
                  $(this).remove();
                });
              });

              $('#enjoying-button-mwah-no, #enjoying-button-go-for-it-no, #enjoying-button-go-for-it-yes, #enjoying-button-mwah-yes').click(function () {
                $.ajax({type: "POST",
                  url: '<?php echo USEYOURDRIVE_ADMIN_URL; ?>',
                  data: {
                    action: 'useyourdrive-rating-asked',
                  }
                });
              });
            })
        </script>
        <?php
    }

    public function rating_asked() {
        update_option('use_your_drive_rating_asked', true);
    }

    public function user_folder_link() {
        check_ajax_referer('useyourdrive-create-link');

        $userfolders = new UserFolders($this->get_processor());

        $linkedto = array('folderid' => rawurldecode($_REQUEST['id']), 'foldertext' => rawurldecode($_REQUEST['text']));
        $userid = $_REQUEST['userid'];

        if (Helpers::check_user_role($this->settings['permissions_link_users'])) {
            $userfolders->manually_link_folder($userid, $linkedto);
        };
    }

    public function user_folder_unlink() {
        check_ajax_referer('useyourdrive-create-link');

        $userfolders = new UserFolders($this->get_processor());

        $userid = $_REQUEST['userid'];

        if (Helpers::check_user_role($this->settings['permissions_link_users'])) {
            $userfolders->manually_unlink_folder($userid);
        }
    }

    public function user_folder_create($user_id) {
        $userfolders = new UserFolders($this->get_processor());
        $userfolders->create_user_folders_for_shortcodes($user_id);
    }

    public function user_folder_update($user_id, $old_user_data = false) {
        $userfolders = new UserFolders($this->get_processor());
        $userfolders->update_user_folder($user_id, $old_user_data);
    }

    public function user_folder_delete($user_id) {
        $userfolders = new UserFolders($this->get_processor());
        $userfolders->remove_user_folder($user_id);
    }

    public function synchronize_cache() {

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true) {
            $this->settings['cache_update_via_wpcron'] = 'No';
            update_option('use_your_drive_settings', $this->settings);
        }

        if ($this->settings['cache_update_via_wpcron'] === 'No') {
            $timestamp = wp_next_scheduled('useyourdrive_synchronize_cache');
            wp_unschedule_event($timestamp, 'useyourdrive_synchronize_cache');
            error_log('[Use-your-Drive message]: Removed WP Cron');
            return;
        }

        if ($this->get_app()->has_access_token() === false) {
            error_log('[Use-your-Drive message]: WP Cron cannot be run without access to the cloud');
            return;
        }

        @set_time_limit(120);
        $processor = $this->get_processor();

        //error_log('[Use-your-Drive message]: WP Cron checking for changes on Cloud Account');

        try {
            $processor->get_cache()->pull_for_changes(true);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Use-your-Drive WP WP Cron job has encountered an error: %s', $ex->getMessage()));
            return;
        }

        $shortcodes = get_option('use_your_drive_lists', array());
        $folders_to_update = array();

        foreach ($shortcodes as $listtoken => $shortcode) {
            $shortcode = apply_filters('useyourdrive_shortcode_set_options', $shortcode, $processor, array());

            $shortcode_root_id = ($shortcode['startid'] !== false) ? $shortcode['startid'] : $shortcode['root'];

            if ($shortcode_root_id === '0') {
                $shortcode_root_id = $processor->get_client()->get_my_drive()->get_id();
            }

            $folders_to_update[$shortcode_root_id] = $shortcode_root_id;
        }

        foreach ($folders_to_update as $shortcode_root_id) {

            //error_log('[Use-your-Drive message]: WP Cron updating folder: ' . $shortcode_root_id);

            try {
                $processor->set_requested_entry($shortcode_root_id);
                $processor->get_client()->get_folder($shortcode_root_id, false);
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Use-your-Drive WP Cron job has encountered an error: %s', $ex->getMessage()));
                return;
            }
        }
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Events
     */
    public function get_events() {
        if (empty($this->_events)) {
            $this->_events = new \TheLion\UseyourDrive\Events($this);
        }

        return $this->_events;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor() {
        if (empty($this->_processor)) {
            $this->_processor = new \TheLion\UseyourDrive\Processor($this);
        }

        return $this->_processor;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app() {
        if (empty($this->_app)) {
            $this->_app = new \TheLion\UseyourDrive\App($this->get_processor());
            $this->_app->start_client();
        }

        return $this->_app;
    }

}

/* Installation and uninstallation hooks */
register_activation_hook(__FILE__, __NAMESPACE__ . '\UseyourDrive_Network_Activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\UseyourDrive_Network_Deactivate');
register_uninstall_hook(__FILE__, __NAMESPACE__ . '\UseyourDrive_Network_Uninstall');

$UseyourDrive = new \TheLion\UseyourDrive\Main();

/**
 * Activate the plugin on network
 */
function UseyourDrive_Network_Activate($network_wide) {
    if (is_multisite() && $network_wide) { // See if being activated on the entire network or one blog
        global $wpdb;

        // Get this so we can switch back to it later
        $current_blog = $wpdb->blogid;
        // For storing the list of activated blogs
        $activated = array();

        // Get all blogs in the network and activate plugin on each one
        $sql = "SELECT blog_id FROM %d";
        $blog_ids = $wpdb->get_col($wpdb->prepare($sql, $wpdb->blogs));
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            UseyourDrive_Activate(); // The normal activation function
            $activated[] = $blog_id;
        }

        // Switch back to the current blog
        switch_to_blog($current_blog);

        // Store the array for a later function
        update_site_option('use_your_drive_activated', $activated);
    } else { // Running on a single blog
        UseyourDrive_Activate(); // The normal activation function
    }
}

/**
 * Activate the plugin
 */
function UseyourDrive_Activate() {
    add_option('use_your_drive_settings', array(
        'googledrive_app_client_id' => '',
        'googledrive_app_client_secret' => '',
        'purcase_code' => '',
        'permissions_edit_settings' => array('administrator'),
        'permissions_link_users' => array('administrator', 'editor'),
        'permissions_see_dashboard' => array('administrator', 'editor'),
        'permissions_see_filebrowser' => array('administrator'),
        'permissions_add_shortcodes' => array('administrator', 'editor', 'author', 'contributor'),
        'permissions_add_links' => array('administrator', 'editor', 'author', 'contributor'),
        'permissions_add_embedded' => array('administrator', 'editor', 'author', 'contributor'),
        'custom_css' => '',
        'google_analytics' => 'No',
        'loadimages' => 'googlethumbnail',
        'lightbox_skin' => 'metro-black',
        'lightbox_path' => 'horizontal',
        'mediaplayer_skin' => 'default',
        'userfolder_name' => '%user_login% (%user_email%)',
        'userfolder_oncreation' => 'Yes',
        'userfolder_onfirstvisit' => 'No',
        'userfolder_update' => 'Yes',
        'userfolder_remove' => 'Yes',
        'userfolder_backend' => 'No',
        'userfolder_backend_auto_root' => '',
        'download_template_subject' => '',
        'download_template_subject_zip' => '',
        'download_template' => '',
        'upload_template_subject' => '',
        'upload_template' => '',
        'delete_template_subject' => '',
        'delete_template' => '',
        'filelist_template' => '',
        'manage_permissions' => 'Yes',
        'permission_domain' => '',
        'teamdrives' => 'No',
        'download_method' => 'redirect',
        'lostauthorization_notification' => get_site_option('admin_email'),
        'gzipcompression' => 'No',
        'cache' => 'filesystem',
        'shortlinks' => 'None',
        'bitly_login' => '',
        'bitly_apikey' => '',
        'shortest_apikey' => '',
        'rebrandly_apikey' => '',
        'rebrandly_domain' => '',
        'rebrandly_workspace' => '',
        'always_load_scripts' => 'No',
        'nonce_validation' => 'Yes',
        'cache_update_via_wpcron' => 'Yes',
        'log_events' => 'Yes',
        'icon_set' => ''
            )
    );

    update_option('use_your_drive_lists', array());

    add_option('use_your_drive_cache', array(
        'last_update' => null,
        'last_cache_id' => '',
        'locked' => false,
        'cache' => ''
    ));

    /* Install Event Log */
    Events::install_database();
}

/**
 * Deactivate the plugin on network
 */
function UseyourDrive_Network_Deactivate($network_wide) {
    if (is_multisite() && $network_wide) { // See if being activated on the entire network or one blog
        global $wpdb;

        // Get this so we can switch back to it later
        $current_blog = $wpdb->blogid;

        // If the option does not exist, plugin was not set to be network active
        if (get_site_option('use_your_drive_activated') === false) {
            return false;
        }

        // Get all blogs in the network
        $activated = get_site_option('use_your_drive_activated'); // An array of blogs with the plugin activated

        $sql = "SELECT blog_id FROM %d";
        $blog_ids = $wpdb->get_col($wpdb->prepare($sql, $wpdb->blogs));
        foreach ($blog_ids as $blog_id) {
            if (!in_array($blog_id, $activated)) { // Plugin is not activated on that blog
                switch_to_blog($blog_id);
                UseyourDrive_Deactivate();
            }
        }

        // Switch back to the current blog
        switch_to_blog($current_blog);

        // Store the array for a later function
        update_site_option('use_your_drive_activated', $activated);
    } else { // Running on a single blog
        UseyourDrive_Deactivate();
    }
}

/**
 * Deactivate the plugin
 */
function UseyourDrive_Deactivate() {

    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {

        if ($path->getFilename() === '.htaccess') {
            continue;
        }

        if ($path->getExtension() === 'access_token') {
            continue;
        }

        $path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
    }

    if (($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification')) !== false) {
        wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification');
    }

    if (($timestamp = wp_next_scheduled('useyourdrive_synchronize_cache')) !== false) {
        wp_unschedule_event($timestamp, 'useyourdrive_synchronize_cache');
    }

    delete_option('use_your_drive_lists');
    delete_option('use_your_drive_cache');
}

function UseyourDrive_Network_Uninstall($network_wide) {
    if (is_multisite() && $network_wide) { // See if being activated on the entire network or one blog
        global $wpdb;

        // Get this so we can switch back to it later
        $current_blog = $wpdb->blogid;

        // If the option does not exist, plugin was not set to be network active
        if (get_site_option('use_your_drive_activated') === false) {
            return false;
        }

        // Get all blogs in the network
        $activated = get_site_option('use_your_drive_activated'); // An array of blogs with the plugin activated

        $sql = "SELECT blog_id FROM %d";
        $blog_ids = $wpdb->get_col($wpdb->prepare($sql, $wpdb->blogs));
        foreach ($blog_ids as $blog_id) {
            if (!in_array($blog_id, $activated)) { // Plugin is not activated on that blog
                switch_to_blog($blog_id);
                UseyourDrive_Uninstall();
            }
        }

        // Switch back to the current blog
        switch_to_blog($current_blog);


        delete_option('use_your_drive_activated');
    } else { // Running on a single blog
        UseyourDrive_Uninstall();
    }
}

function UseyourDrive_Uninstall() {
    /* Remove Database settings */
    delete_option('use_your_drive_lists');
    delete_option('use_your_drive_activated');
    delete_option('use_your_drive_cache');
    delete_site_option('use_your_drive_guestlinkedto');
    delete_option('use_your_drive_version');

    /* Remove Event Log */
    Events::drop_database();

    /* Remove Cache Files */
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {

        if ($path->getFilename() === '.htaccess') {
            continue;
        }

        $path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
    }
}

/* add new cron schedule to update cache every 20 minutes */
if (!function_exists('wpcloud_cron_schedules')) {

    function wpcloud_cron_schedules($schedules) {
        if (!isset($schedules["wp_cloudplugins_20min"])) {
            $schedules["wp_cloudplugins_20min"] = array(
                'interval' => 1200,
                'display' => __('Once every 20 minutes'));
        }
        return $schedules;
    }

    add_filter('cron_schedules', __NAMESPACE__ . '\wpcloud_cron_schedules');
}
