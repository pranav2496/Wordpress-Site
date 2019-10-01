<?php
namespace TheLion\UseyourDrive;
if (file_exists(dirname(__FILE__) . '/class.plugin-modules.php')) include_once(dirname(__FILE__) . '/class.plugin-modules.php');

class Admin {

    /**
     *
     * @var \TheLion\UseyourDrive\Main 
     */
    private $_main;
    private $settings_key = 'use_your_drive_settings';
    private $plugin_options_key = 'UseyourDrive_settings';
    private $plugin_network_options_key = 'UseyourDrive_network_settings';
    private $plugin_id = 6219776;
    private $settingspage;
    private $filebrowserpage;
    private $shortcodebuilderpage;
    private $dashboardpage;
    private $userpage;

    /**
     * Construct the plugin object
     */
    public function __construct(\TheLion\UseyourDrive\Main $main) {
        $this->_main = $main;

        /* Check if plugin can be used */
        if ($main->can_run_plugin() === false) {
            add_action('admin_notices', array(&$this, 'get_admin_notice'));
            return;
        }

        /* Init */
        add_action('init', array(&$this, 'load_settings'));
        add_action('admin_init', array(&$this, 'register_settings'));
        add_action('admin_init', array(&$this, 'check_for_updates'));
        add_action('admin_enqueue_scripts', array(&$this, 'LoadAdmin'));

        /* add TinyMCE button */
        /* Depends on the theme were to load.... */
        add_action('init', array(&$this, 'load_shortcode_buttons'));
        add_action('admin_head', array(&$this, 'load_shortcode_buttons'));

        /* Add menu's */
        add_action('admin_menu', array(&$this, 'add_admin_menu'));
        add_action('network_admin_menu', array(&$this, 'add_admin_network_menu'));

        /* Network save settings call */
        add_action('network_admin_edit_' . $this->plugin_network_options_key, array($this, 'save_settings_network'));

        /* Save settings call */
        add_filter('pre_update_option_' . $this->settings_key, array($this, 'save_settings'), 10, 2);

        /* Notices */
        add_action('admin_notices', array(&$this, 'get_admin_notice_not_authorized'));

        add_filter('admin_footer_text', array($this, 'admin_footer_text'), 1);
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Main
     */
    public function get_main() {
        return $this->_main;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor() {
        return $this->_main->get_processor();
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app() {
        return $this->get_processor()->get_app();
    }

    public function LoadAdmin($hook) {

        if ($hook == $this->filebrowserpage || $hook == $this->userpage || $hook == $this->settingspage || $hook == $this->shortcodebuilderpage || $hook == $this->dashboardpage) {
            $this->get_main()->load_scripts();
            $this->get_main()->load_styles();

            wp_enqueue_script('jquery-effects-fade');
            wp_enqueue_script('WPCloudplugin.Libraries');

            wp_enqueue_style('qtip');
            wp_enqueue_style('UseyourDrive.tinymce');
            wp_enqueue_style('Awesome-Font-5-css');
        }

        if ($hook == $this->settingspage) {
            wp_enqueue_script('jquery-form');
            wp_enqueue_script('UseyourDrive.tinymce');
            wp_enqueue_script('wp-color-picker-alpha', plugins_url('/wp-color-picker-alpha/wp-color-picker-alpha.min.js', __FILE__), array('wp-color-picker'), '1.0.0', true);
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('jquery-ui-accordion');
            wp_enqueue_media();
            add_thickbox();
        }

        if ($hook == $this->userpage) {
            wp_enqueue_style('UseyourDrive');
            add_thickbox();
        }

        if ($hook == $this->dashboardpage) {
            wp_enqueue_script('UseyourDrive.Dashboard');
            wp_enqueue_style('UseyourDrive.Datatables.css');
            wp_dequeue_style('UseyourDrive');
        }
    }

    /**
     * add a menu
     */
    public function add_admin_menu() {
        /* Add a page to manage this plugin's settings */
        $menuadded = false;

        if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
            add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, array(&$this, 'load_settings_page'), plugin_dir_url(__FILE__) . '../css/images/google_drive_logo_small.png');
            $menuadded = true;
            $this->settingspage = add_submenu_page($this->plugin_options_key, 'Use-your-Drive - ' . __('Settings'), __('Settings'), 'read', $this->plugin_options_key, array(&$this, 'load_settings_page'));
        }


        if (Helpers::check_user_role($this->settings['permissions_see_dashboard']) && ($this->settings['log_events'] === 'Yes')) {
            if (!$menuadded) {
                $this->dashboardpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, array(&$this, 'load_dashboard_page'), plugin_dir_url(__FILE__) . '../css/images/google_drive_logo_small.png');
                $this->dashboardpage = add_submenu_page($this->plugin_options_key, __('Reports', 'useyourdrive'), __('Reports', 'useyourdrive'), 'read', $this->plugin_options_key, array(&$this, 'load_dashboard_page'));
                $menuadded = true;
            } else {
                $this->dashboardpage = add_submenu_page($this->plugin_options_key, __('Reports', 'useyourdrive'), __('Reports', 'useyourdrive'), 'read', $this->plugin_options_key . '_dashboard', array(&$this, 'load_dashboard_page'));
            }
        }

        if (Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) {
            if (!$menuadded) {
                $this->shortcodebuilderpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, array(&$this, 'load_shortcodebuilder_page'), plugin_dir_url(__FILE__) . '../css/images/google_drive_logo_small.png');
                $this->shortcodebuilderpage = add_submenu_page($this->plugin_options_key, __('Shortcode Builder', 'useyourdrive'), __('Shortcode Builder', 'useyourdrive'), 'read', $this->plugin_options_key, array(&$this, 'load_shortcodebuilder_page'));
                $menuadded = true;
            } else {
                $this->shortcodebuilderpage = add_submenu_page($this->plugin_options_key, __('Shortcode Builder', 'useyourdrive'), __('Shortcode Builder', 'useyourdrive'), 'read', $this->plugin_options_key . '_shortcodebuilder', array(&$this, 'load_shortcodebuilder_page'));
            }
        }

        if (Helpers::check_user_role($this->settings['permissions_link_users'])) {
            if (!$menuadded) {
                $this->userpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, array(&$this, 'load_linkusers_page'), plugin_dir_url(__FILE__) . '../css/images/google_drive_logo_small.png');
                $this->userpage = add_submenu_page($this->plugin_options_key, __('Link Private Folders', 'useyourdrive'), __('Link Private Folders', 'useyourdrive'), 'read', $this->plugin_options_key, array(&$this, 'load_linkusers_page'));
                $menuadded = true;
            } else {
                $this->userpage = add_submenu_page($this->plugin_options_key, __('Link Private Folders', 'useyourdrive'), __('Link Private Folders', 'useyourdrive'), 'read', $this->plugin_options_key . '_linkusers', array(&$this, 'load_linkusers_page'));
            }
        }
        if (Helpers::check_user_role($this->settings['permissions_see_filebrowser'])) {
            if (!$menuadded) {
                $this->filebrowserpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, array(&$this, 'load_filebrowser_page'), plugin_dir_url(__FILE__) . '../css/images/google_drive_logo_small.png');
                $this->filebrowserpage = add_submenu_page($this->plugin_options_key, __('File Browser', 'useyourdrive'), __('File Browser', 'useyourdrive'), 'read', $this->plugin_options_key, array(&$this, 'load_filebrowser_page'));
                $menuadded = true;
            } else {
                $this->filebrowserpage = add_submenu_page($this->plugin_options_key, __('File Browser', 'useyourdrive'), __('File Browser', 'useyourdrive'), 'read', $this->plugin_options_key . '_filebrowser', array(&$this, 'load_filebrowser_page'));
            }
        }
    }

    public function add_admin_network_menu() {
        add_menu_page('Use-your-Drive', 'Use-your-Drive', 'manage_options', $this->plugin_network_options_key, array(&$this, 'load_settings_network_page'), plugin_dir_url(__FILE__) . '../css/images/google_drive_logo_small.png');
    }

    public function register_settings() {
        register_setting($this->settings_key, $this->settings_key);
    }

    function load_settings() {
        $this->settings = (array) get_option($this->settings_key);

        $updated = false;
        if (!isset($this->settings['googledrive_app_client_id'])) {
            $this->settings['googledrive_app_client_id'] = '';
            $this->settings['googledrive_app_client_secret'] = '';
            $updated = true;
        }

        if ($updated) {
            update_option($this->settings_key, $this->settings);
        }
    }

    public function load_settings_page() {
        if (!Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'useyourdrive'));
        }

        include(sprintf("%s/templates/admin.php", USEYOURDRIVE_ROOTDIR));
    }

    public function load_settings_network_page() {
        $useyourdrive_purchaseid = get_site_option('useyourdrive_purchaseid');
        ?>
        <div class="wrap">
          <div class='left' style="min-width:400px; max-width:650px; padding: 0 20px 0 0; float:left">
            <?php if ($_GET['updated']) { ?>
                <div id="message" class="uyd-updated"><p><?php _e('Saved!', 'useyourdrive'); ?></p></div>
            <?php } ?>
            <form action="<?php echo network_admin_url('edit.php?action=' . $this->plugin_network_options_key); ?>" method="post">
              <?php
              echo __('If you would like to receive updates, please insert your Purchase code', 'useyourdrive') . '. ' .
              '<a href="http://support.envato.com/index.php?/Knowledgebase/Article/View/506/54/where-can-i-find-my-purchase-code">' .
              __('Where do I find the purchase code?', 'useyourdrive') . '</a>.';
              ?>
              <table class="form-table">
                <tbody>
                  <tr valign="top">
                    <th scope="row"><?php _e('Purchase Code', 'useyourdrive'); ?></th>
                    <td><input type="text" name="useyourdrive_purchaseid" id="useyourdrive_purchaseid" value="<?php echo $useyourdrive_purchaseid; ?>" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" maxlength="37" style="width:90%"/></td>
                  </tr>
                </tbody>
              </table>
              <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
            </form>
          </div>
        </div>
        <?php
    }

    public function save_settings($new_settings, $old_settings) {

        foreach ($new_settings as $setting_key => &$value) {
            if ($value === 'on') {
                $value = 'Yes';
            }

            if ($setting_key === 'googledrive_app_own' && $value === 'No') {
                $new_settings['googledrive_app_client_id'] = '';
                $new_settings['googledrive_app_client_secret'] = '';
            }

            if ($setting_key === 'gsuite' && $value === 'No') {
                $new_settings['permission_domain'] = '';
                $new_settings['teamdrives'] = 'No';
            }

            if ($setting_key === 'colors') {
                $value = $this->_check_colors($value, $old_settings['colors']);
            }
        }

        if ($new_settings['teamdrives'] !== $old_settings['teamdrives']) {
            $this->get_processor()->reset_complete_cache();
        }

        $new_settings['icon_set'] = rtrim($new_settings['icon_set'], '/') . '/';

        if ($new_settings['icon_set'] !== $old_settings['icon_set']) {
            $this->get_processor()->reset_complete_cache();
        }

        return $new_settings;
    }

    public function save_settings_network() {
        if (current_user_can('manage_network_options')) {
            update_site_option('useyourdrive_purchaseid', $_POST['useyourdrive_purchaseid']);
        }

        wp_redirect(
                add_query_arg(
                        array('page' => $this->plugin_network_options_key, 'updated' => 'true'), network_admin_url('admin.php')
                )
        );
        exit;
    }

    private function _check_colors($colors, $old_colors) {
        $regex = '/(light|dark|transparent|#(?:[0-9a-f]{2}){2,4}|#[0-9a-f]{3}|(?:rgba?|hsla?)\((?:\d+%?(?:deg|rad|grad|turn)?(?:,|\s)+){2,3}[\s\/]*[\d\.]+%?\))/i';

        foreach ($colors as $color_id => &$color) {
            if (preg_match($regex, $color) !== 1) {
                $color = $old_colors[$color_id];
            }
        }

        return $colors;
    }

    public function admin_footer_text($footer_text) {

        $rating_asked = get_option('use_your_drive_rating_asked', false);
        if ($rating_asked == true || (Helpers::check_user_role($this->settings['permissions_edit_settings'])) === false) {
            return $footer_text;
        }

        $current_screen = get_current_screen();

        if (isset($current_screen->id) && in_array($current_screen->id, array($this->filebrowserpage, $this->userpage, $this->settingspage))) {
            $onclick = "jQuery.post( '" . USEYOURDRIVE_ADMIN_URL . "', { action: 'useyourdrive-rating-asked' });jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) )";
            $footer_text = sprintf(
                    __('If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'useyourdrive'), sprintf('<strong>%s</strong>', esc_html__('Use-your-Drive', 'useyourdrive')), '<a href="https://1.envato.market/c/1260925/275988/4415?u=https%3A%2F%2Fcodecanyon.net%2Fitem%2Fuseyourdrive-google-drive-plugin-for-wordpress%2Freviews%2F6219776" target="_blank" class="useyourdrive-rating-link" data-rated="' . esc_attr__('Thanks :)', 'useyourdrive') . '" onclick="' . $onclick . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
            );
        }

        return $footer_text;
    }

    function load_filebrowser_page() {


        if (!Helpers::check_user_role($this->settings['permissions_see_filebrowser'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'letsbox'));
        }


        include(sprintf("%s/templates/admin_filebrowser.php", USEYOURDRIVE_ROOTDIR));
    }

    function load_linkusers_page() {
        if (!Helpers::check_user_role($this->settings['permissions_link_users'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'useyourdrive'));
        }
        $linkusers = new LinkUsers($this->get_main());
        $linkusers->render();
    }

    function load_shortcodebuilder_page() {
        if (!Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'useyourdrive'));
        }

        echo "<iframe src='" . USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-getpopup&standaloneshortcodebuilder=1' width='90%' height='1000' tabindex='-1' frameborder='0'></iframe>";
    }

    function load_dashboard_page() {
        if (!Helpers::check_user_role($this->settings['permissions_see_dashboard'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'useyourdrive'));
        }

        include(sprintf("%s/templates/admin_dashboard.php", USEYOURDRIVE_ROOTDIR));
    }

    public function get_plugin_activated_box() {
        $plugin = dirname(plugin_basename(__FILE__)) . '/use-your-drive.php';

        $purchasecode = $this->settings['purcase_code'];
        if (is_multisite() && is_plugin_active_for_network($plugin)) {
            $site_purchase_code = get_site_option('useyourdrive_purchaseid');

            if (!empty($site_purchase_code)) {
                $purchasecode = $site_purchase_code;
            }
        }

        /* Check if Auto-update is being activated */
        if (isset($_REQUEST['purchase_code']) && isset($_REQUEST['plugin_id']) && ((int) $_REQUEST['plugin_id'] === $this->plugin_id)) {
            $purchasecode = $this->settings['purcase_code'] = sanitize_key($_REQUEST['purchase_code']);
            update_option($this->settings_key, $this->settings);

            if (is_multisite() && is_plugin_active_for_network($plugin)) {
                update_site_option('useyourdrive_purchaseid', sanitize_key($_REQUEST['purchase_code']));
            }
        }


        $box_class = 'uyd-updated';
        $box_text = __('Thanks for registering your product! The plugin is now <strong>Activated</strong> and the <strong>Auto-Updater</strong> enabled', 'useyourdrive') . ". " . __('Your purchasecode', 'useyourdrive') . ": <code style='user-select: initial;'>" . esc_attr($this->settings['purcase_code']) . '</code>';
        if (empty($purchasecode)) {
            $box_class = 'uyd-error';
            $box_text = __('The plugin is <strong>Not Activated</strong> and the <strong>Auto-Updater</strong> disabled', 'useyourdrive') . ". " . __('Please activate your copy in order to have direct access to the latest updates and to get support', 'useyourdrive') . ". ";
            $box_text .= "</p><p><input id='updater_button' type='button' class='simple-button blue' value='" . __('Activate', 'useyourdrive') . "' />";
            $box_text .= '</p><p><a href="#" onclick="$(this).next().slideToggle()">' . __('Or insert your purchasecode manually and press Activate', 'useyourdrive') . '</a><input name="use_your_drive_settings[purcase_code]" id="purcase_code" class="useyourdrive-option-input-large" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" style="display:none" value="' . esc_attr($this->settings['purcase_code']) . '">';
        } else {
            $box_text .= "</p><p><input id='check_updates_button' type='button' class='simple-button blue' value='" . __('Check for Updates', 'useyourdrive') . "' />";
            $box_text .= "<input id='deactivate_license_button' type='button' class='simple-button default' value='" . __('Deactivate License', 'useyourdrive') . "' />";
        }


        return "<div id='message' class='$box_class useyourdrive-option-description'><p>$box_text</p></div>";
    }

    public function get_plugin_authorization_box() {
        $revokebutton = "<div id='revokeDrive_button' type='button' class='simple-button blue'/>" . __('Revoke Authorization', 'useyourdrive') . "&nbsp;<div class='uyd-spinner'></div></div>";

        try {
            $app = $this->get_app();
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Use-your-Drive has encountered an error: %s', $ex->getMessage()));

            $box_class = 'uyd-error';
            $box_text = '<strong>' . __('Use-your-Drive has encountered an error', 'useyourdrive') . "</strong> ";
            $box_text .= '<p><em>' . __('Error Details', 'useyourdrive') . ":</em> <code>" . $ex->getMessage() . '</code></p>';
            return "<div id='message' class='$box_class useyourdrive-option-description'><p>$box_text</p><p>$revokebutton</p></div>";
        }

        $app->set_approval_prompt('force');
        $authorizebutton = "<input id='authorizeDrive_button' type='button' class='simple-button blue'  value='" . __('(Re) Authorize the Plugin!', 'useyourdrive') . "' data-url='{$app->get_auth_url()}'/>";

        $box_redirect_msg = '';

        if ($app->has_access_token()) {

            try {
                $client = $this->get_processor()->get_client();

                $account = $client->get_account_info();
                $account_name = $account->getName();
                $account_email = $account->getEmail();

                $account_space = $client->get_drive_info();
                $account_space_quota_used = Helpers::bytes_to_size_1024($account_space->getStorageQuota()->getUsage());
                $account_space_quota_total = Helpers::bytes_to_size_1024($account_space->getStorageQuota()->getLimit());
                $account_space_quota_total = (empty($account_space_quota_total)) ? __('Unlimited', 'useyourdrive') : $account_space_quota_total;

                $box_class = 'uyd-updated';
                $box_text = __('Use-your-Drive is succesfully authorized and linked with Google account:', 'useyourdrive') . "<br/><strong>$account_name ($account_email - $account_space_quota_used/$account_space_quota_total)</strong>";
                $box_buttons = $revokebutton;
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Use-your-Drive has encountered an error: %s', $ex->getMessage()));

                $box_class = 'uyd-error';
                $box_text = '<strong>' . __('Use-your-Drive has encountered an error', 'useyourdrive') . "</strong> ";
                if ($app->has_plugin_own_app()) {
                    $box_text .= '<p>' . __('Please fall back to the default App by clearing the KEY and Secret on the Advanced settings tab', 'useyourdrive') . '.</p>';
                }

                $box_text .= '<p><em>' . __('Error Details', 'useyourdrive') . ": " . $ex->getMessage() . '</em></p>';
                $box_buttons = $revokebutton . $authorizebutton;
                
                define('USEYOURDRIVE_NOAUTHORIZATION', true);
            }
        } else {
            $box_class = 'uyd-error';
            $box_text = __("Plugin isn't linked to your Drive... Please Authorize!", 'useyourdrive');


            $box_buttons = $authorizebutton;
        }

        return "<div id = 'message' class = '$box_class useyourdrive-option-description'><p>$box_text</p><p>$box_redirect_msg</p><p>$box_buttons</p></div>";
    }

    public function get_plugin_reset_box() {
        $box_text = __('Use-your-Drive uses a cache to improve performance', 'useyourdrive') . ". " . __('If the plugin somehow is causing issues, try to reset the cache first', 'useyourdrive') . ".<br/>";

        $box_button = "<div id='resetDrive_button' type='button' class='simple-button blue'/>" . __('Reset Cache', 'useyourdrive') . "&nbsp;<div class='uyd-spinner'></div></div>";
        return "<div id='message'><p>$box_text</p><p>$box_button</p> </div>";
    }

    public function get_admin_notice($force = false) {

        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>' . __('You need at least PHP 5.4 if you want to use Use-your-Drive', 'useyourdrive') . '. ' .
            __('You are using:', 'useyourdrive') . ' <u>' . phpversion() . '</u></p></div>';
        } elseif (!function_exists('curl_init')) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>' .
            __("We are not able to connect to the Google API as you don't have the cURL PHP extension installed", 'useyourdrive') . '. ' .
            __("Please enable or install the cURL extension on your server", 'useyourdrive') . '. ' .
            '</p></div>';
        } elseif (class_exists('UYDGoogle_Client') && (!method_exists('UYDGoogle_Client', 'getLibraryVersion'))) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>' .
            __("We are not able to connect to the Google API as the plugin is interfering with an other plugin", 'useyourdrive') . '. <br/><br/>' .
            __("The other plugin is using an old version of the Google-Api-PHP-client that isn't capable of running multiple configurations", 'useyourdrive') . '. ' .
            __("Please disable this other plugin if you would like to use Use-your-Drive", 'useyourdrive') . '. ' .
            __("If you would like to use both plugins, ask the developer to update it's code", 'useyourdrive') . '. ' .
            '</p></div>';
        } elseif (!file_exists(USEYOURDRIVE_CACHEDIR) || !is_writable(USEYOURDRIVE_CACHEDIR) || !file_exists(USEYOURDRIVE_CACHEDIR . '/.htaccess')) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>' . sprintf(__('Cannot create the cache directory %s, or it is not writable', 'useyourdrive'), '<code>' . USEYOURDRIVE_CACHEDIR . '</code>') . '. ' .
            sprintf(__('Please check if the directory exists on your server and has %s writing permissions %s', 'useyourdrive'), '<a href="https://codex.wordpress.org/Changing_File_Permissions" target="_blank">', '</a>') . '</p></div>';
        }
    }

    public function get_admin_notice_not_authorized() {
        global $pagenow;
        if ($pagenow == 'index.php' || $pagenow == 'plugins.php') {
            if (current_user_can('manage_options') || current_user_can('edit_theme_options')) {

                $app = new \TheLion\UseyourDrive\App($this->get_processor());

                if ($app->has_access_token() === false || (wp_next_scheduled('useyourdrive_lost_authorisation_notification') !== false)) {
                    $location = get_admin_url(null, 'admin.php?page=UseyourDrive_settings');
                    echo '<div id="message" class="error"><p><strong>Use-your-Drive: </strong>' . __('The plugin isn\'t autorized to use your Google Drive', 'useyourdrive') . '. ' .
                    "<a href='$location' class='button-primary'>" . __('Authorize the plugin!', 'useyourdrive') . '</a></p></div>';
                }
            }
        }
    }

    public function check_for_updates() {
        /* Updater */
        $purchasecode = false;

        $plugin = dirname(plugin_basename(__FILE__)) . '/use-your-drive.php';
        if (is_multisite() && is_plugin_active_for_network($plugin)) {
            $purchasecode = get_site_option('useyourdrive_purchaseid');
        } else {
            $purchasecode = $this->settings['purcase_code'];
        }

        if (!empty($purchasecode)) {
            require_once 'plugin-update-checker/plugin-update-checker.php';
            $updatechecker = \Puc_v4_Factory::buildUpdateChecker('https://www.wpcloudplugins.com/updates/?action=get_metadata&slug=use-your-drive&purchase_code=' . $purchasecode . '&plugin_id=' . $this->plugin_id, plugin_dir_path(__DIR__) . '/use-your-drive.php');
        }
    }

    public function get_system_information() {
        $check = array();

        array_push($check, array('success' => true, 'warning' => false, 'value' => __('WordPress version', 'useyourdrive'), 'description' => get_bloginfo('version')));
        array_push($check, array('success' => true, 'warning' => false, 'value' => __('Plugin version', 'useyourdrive'), 'description' => USEYOURDRIVE_VERSION));
        array_push($check, array('success' => true, 'warning' => false, 'value' => __('Memory limit', 'useyourdrive'), 'description' => (ini_get('memory_limit'))));

        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            array_push($check, array('success' => false, 'warning' => false, 'value' => __('PHP version', 'useyourdrive'), 'description' => phpversion() . ' ' . __('You need at least PHP 5.4 if you want to use Use-your-Drive', 'useyourdrive')));
        } else {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('PHP version', 'useyourdrive'), 'description' => phpversion()));
        }


        /* Check if we can use CURL */
        if (function_exists('curl_init')) {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('cURL PHP extension', 'useyourdrive'), 'description' => __('You have the cURL PHP extension installed and we can access Google with cURL', 'useyourdrive')));
        } else {
            array_push($check, array('success' => false, 'warning' => false, 'value' => __('cURL PHP extension', 'useyourdrive'), 'description' => __("You don't have the cURL PHP extension installed (couldn't find function \"curl_init\"), please enable or install this extension", 'useyourdrive')));
        }

        /* Check if we can use fOpen */
        if (ini_get('allow_url_fopen')) {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is allow_url_fopen enabled?', 'useyourdrive'), 'description' => __('Yes, we can access Google with fopen', 'useyourdrive')));
        } else {
            array_push($check, array('success' => false, 'warning' => false, 'value' => __('Is allow_url_fopen enabled?', 'useyourdrive'), 'description' => __("No, we can't access Google with fopen", 'useyourdrive')));
        }

        /* Check which version of the Google API Client we are using */
        if (class_exists('UYDGoogle_Client') && (method_exists('UYDGoogle_Client', 'getLibraryVersion'))) {
            $googleClient = new \UYDGoogle_Client;
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Version Google Api Client', 'useyourdrive'), 'description' => $googleClient->getLibraryVersion()));
        } else {
            array_push($check, array('success' => false, 'warning' => false, 'value' => __('Version Google Api Client', 'useyourdrive'), 'description' => __("Before version 1.0.0", 'useyourdrive') . '. ' . __("Another plugin is loading an old Google Client library. Use-your-Drive isn't compatible with this version.", 'useyourdrive')));
        }

        /* Check if temp dir is writeable */
        $uploadir = wp_upload_dir();

        if (!is_writable($uploadir['path'])) {
            array_push($check, array('success' => false, 'warning' => false, 'value' => __('Is TMP directory writable?', 'useyourdrive'), 'description' => __('TMP directory', 'useyourdrive') . ' \'' . $uploadir['path'] . '\' ' . __('isn\'t writable. You are not able to upload files to Drive.', 'useyourdrive') . ' ' . __('Make sure TMP directory is writable', 'useyourdrive')));
        } else {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is TMP directory writable?', 'useyourdrive'), 'description' => __('TMP directory is writable', 'useyourdrive')));
        }

        /* Check if cache dir is writeable */
        if (!file_exists(USEYOURDRIVE_CACHEDIR)) {
            @mkdir(USEYOURDRIVE_CACHEDIR, 0755);
        }

        if (!is_writable(USEYOURDRIVE_CACHEDIR)) {
            @chmod(USEYOURDRIVE_CACHEDIR, 0755);

            if (!is_writable(USEYOURDRIVE_CACHEDIR)) {
                array_push($check, array('success' => false, 'warning' => false, 'value' => __('Is CACHE directory writable?', 'useyourdrive'), 'description' => __('CACHE directory', 'useyourdrive') . ' \'' . USEYOURDRIVE_CACHEDIR . '\' ' . __('isn\'t writable. The gallery will load very slowly.', 'useyourdrive') . ' ' . __('Make sure CACHE directory is writable', 'useyourdrive')));
            } else {
                array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is CACHE directory writable?', 'useyourdrive'), 'description' => __('CACHE directory is now writable', 'useyourdrive')));
            }
        } else {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is CACHE directory writable?', 'useyourdrive'), 'description' => __('CACHE directory is writable', 'useyourdrive')));
        }

        /* Check if cache index-file is writeable */
        if (!is_readable(USEYOURDRIVE_CACHEDIR . '/index')) {
            @file_put_contents(USEYOURDRIVE_CACHEDIR . '/index', json_encode(array()));

            if (!is_readable(USEYOURDRIVE_CACHEDIR . '/index')) {
                array_push($check, array('success' => false, 'warning' => false, 'value' => __('Is CACHE-index file writable?', 'useyourdrive'), 'description' => __('-index file', 'useyourdrive') . ' \'' . USEYOURDRIVE_CACHEDIR . 'index' . '\' ' . __('isn\'t writable. The gallery will load very slowly.', 'useyourdrive') . ' ' . __('Make sure CACHE-index file is writable', 'useyourdrive')));
            } else {
                array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is CACHE-index file writable?', 'useyourdrive'), 'description' => __('CACHE-index file is now writable', 'useyourdrive')));
            }
        } else {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is CACHE-index file writable?', 'useyourdrive'), 'description' => __('CACHE-index file is writable', 'useyourdrive')));
        }

        /* Check if cache dir is writeable */
        if (!file_exists(USEYOURDRIVE_CACHEDIR . '/thumbnails')) {
            @mkdir(USEYOURDRIVE_CACHEDIR . '/thumbnails', 0755);
        }

        if (!is_writable(USEYOURDRIVE_CACHEDIR . '/thumbnails')) {
            @chmod(USEYOURDRIVE_CACHEDIR . '/thumbnails', 0755);

            if (!is_writable(USEYOURDRIVE_CACHEDIR . '/thumbnails')) {
                array_push($check, array('success' => false, 'warning' => false, 'value' => __('Is THUMBNAIL directory writable?', 'useyourdrive'), 'description' => __('THUMBNAIL directory', 'useyourdrive') . ' \'' . USEYOURDRIVE_CACHEDIR . '\thumbnails ' . __('isn\'t writable. Thumbnails of Google Doc files will not always properly.', 'useyourdrive') . ' ' . __('Make sure THUMBNAIL directory is writable', 'useyourdrive')));
            } else {
                array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is THUMBNAIL directory writable?', 'useyourdrive'), 'description' => __('THUMBNAIL directory is now writable', 'useyourdrive')));
            }
        } else {
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Is THUMBNAIL directory writable?', 'useyourdrive'), 'description' => __('THUMBNAIL directory is writable', 'useyourdrive')));
        }

        /* Check if we can use ZIP class */
        if (class_exists('ZipArchive')) {
            $message = __("You can use the ZIP function", 'useyourdrive');
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Download files as ZIP', 'useyourdrive'), 'description' => $message));
        } else {
            $message = __("You cannot download files as ZIP", 'useyourdrive');
            array_push($check, array('success' => true, 'warning' => true, 'value' => __('Download files as ZIP', 'useyourdrive'), 'description' => $message));
        }

        if (!extension_loaded('mbstring')) {
            array_push($check, array('success' => false, 'warning' => false, 'value' => __('mbstring extension enabled?', 'useyourdrive'), 'description' => __('The required mbstring extension is not enabled on your server. Please enable this extension.', 'useyourdrive')));
        }

        /* Check if we can use AES Encryption */
        if ((version_compare(phpversion(), '7.1.0', '<=')) && function_exists('mcrypt_encrypt')) {
            $message = __("You can AES256 encrypt files during upload", 'useyourdrive');
            array_push($check, array('success' => true, 'warning' => false, 'value' => __('Upload Encryption', 'useyourdrive'), 'description' => $message));
        } else {
            $message = __("You cannot AES256 encrypt files during upload", 'useyourdrive');
            array_push($check, array('success' => true, 'warning' => true, 'value' => __('Upload Encryption', 'useyourdrive'), 'description' => $message));
        }

        /* Check if Gravity Forms is installed and can be used */
        if (class_exists("GFForms")) {
            $is_correct_version = version_compare(\GFCommon::$version, '1.9', '>=');

            if ($is_correct_version) {
                $message = __("You can use Use-your-Drive in Gravity Forms (" . \GFCommon::$version . ")", 'useyourdrive');
                array_push($check, array('success' => true, 'warning' => false, 'value' => __('Gravity Forms integration', 'useyourdrive'), 'description' => $message));
            } else {
                $message = __("You have Gravity Forms (" . \GFCommon::$version . ") installed, but versions before 1.9 are not supported. Please update Gravity Forms if you want to use this plugin in combination with Gravity Forms", 'useyourdrive');
                array_push($check, array('success' => false, 'warning' => true, 'value' => __('Gravity Forms integration', 'useyourdrive'), 'description' => $message));
            }
        }


        if (defined("WPCF7_PLUGIN")) {
            $is_correct_version = false;
            if (defined("WPCF7_VERSION")) {
                $is_correct_version = version_compare(WPCF7_VERSION, '5.0', '>=');
            }
            if ($is_correct_version) {
                $message = __("You can use Use-your-Drive in Contact Form 7 (" . WPCF7_VERSION . ")", 'useyourdrive');
                array_push($check, array('success' => true, 'warning' => false, 'value' => __('Contact Form integration', 'useyourdrive'), 'description' => $message));
            } else {
                $message = __("You have Contact Form 7 installed, but this version is not supported. Please update Contact Form 7 to the latest version if you want to use this plugin in combination with Contact Form", 'useyourdrive');
                array_push($check, array('success' => false, 'warning' => true, 'value' => __('Contact Form integration', 'useyourdrive'), 'description' => $message));
            }
        }


        if (class_exists("WC_Integration")) {

            global $woocommerce;
            $is_correct_version = (is_object($woocommerce) ? version_compare($woocommerce->version, '3.0', '>=') : false);

            if ($is_correct_version) {
                $message = __("You can use Use-your-Drive in WooCommerce (" . $woocommerce->version . ") for your Digital Products ", 'useyourdrive') . '<br/><br/> ';
                array_push($check, array('success' => true, 'warning' => false, 'value' => __('WooCommerce Digital Products', 'useyourdrive'), 'description' => $message));
            } else {
                $message = __("You have WooCommerce (" . $woocommerce->version . ") installed, but versions before 3.0 are not supported. Please update WooCommerce if you want to use this plugin in combination with WooCommerce", 'useyourdrive');
                array_push($check, array('success' => false, 'warning' => true, 'value' => __('WooCommerce Digital Products', 'useyourdrive'), 'description' => $message));
            }
        }


        /* Create Table */
        $html = '<table border="0" cellspacing="0" cellpadding="0">';

        foreach ($check as $row) {

            $color = ($row['success']) ? 'green' : 'red';
            $color = ($row['warning']) ? 'orange' : $color;

            $html .= '<tr style="vertical-align:top;"><td width="200" style="padding: 5px; color:' . $color . '"><strong>' . $row['value'] . '</strong></td><td style="padding: 5px;">' . $row['description'] . '</td></tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /*
     * Add MCE buttons and script
     */

    public function load_shortcode_buttons() {

        /* Abort early if the user will never see TinyMCE */
        if (
                !(Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) &&
                !(Helpers::check_user_role($this->settings['permissions_add_links'])) &&
                !(Helpers::check_user_role($this->settings['permissions_add_embedded']))
        ) {
            return;
        }

        if (get_user_option('rich_editing') !== 'true')
            return;

        /* Add a callback to regiser our tinymce plugin */
        add_filter("mce_external_plugins", array(&$this, "register_tinymce_plugin"));

        /* Add a callback to add our button to the TinyMCE toolbar */
        add_filter('mce_buttons', array(&$this, 'register_tinymce_plugin_buttons'));

        /* Add custom CSS for placeholders */
        add_editor_style(USEYOURDRIVE_ROOTPATH . '/css/tinymce_editor.css');

        add_action('enqueue_block_editor_assets', array(&$this, 'enqueue_tinymce_css_gutenberg'));
    }

    /* This callback registers our plug-in */

    function register_tinymce_plugin($plugin_array) {
        $plugin_array['useyourdrive'] = USEYOURDRIVE_ROOTPATH . "/includes/js/Tinymce.js";
        return $plugin_array;
    }

    /* This callback adds our button to the toolbar */

    function register_tinymce_plugin_buttons($buttons) {
        /* Add the button ID to the $button array */

        if (Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) {
            $buttons[] = "useyourdrive";
        }
        if (Helpers::check_user_role($this->settings['permissions_add_links'])) {
            $buttons[] = "useyourdrive_links";
        }
        if (Helpers::check_user_role($this->settings['permissions_add_embedded'])) {
            $buttons[] = "useyourdrive_embed";
        }

        return $buttons;
    }

    function enqueue_tinymce_css_gutenberg() {
        wp_enqueue_style('useyourdrive-css-gutenberg', USEYOURDRIVE_ROOTPATH . '/css/tinymce_editor.css');
    }

}