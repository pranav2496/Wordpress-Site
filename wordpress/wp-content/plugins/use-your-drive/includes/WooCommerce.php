<?php

namespace TheLion\UseyourDrive;

class WooCommerce extends \WC_Integration {

    /**
     * @var \TheLion\UseyourDrive\Main 
     */
    private $_main;

    /**
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;

    /**
     * @var \TheLion\UseyourDrive\WooCommerce_Uploads 
     */
    public $uploads;

    /**
     * @var \TheLion\UseyourDrive\WooCommerce_Downloads 
     */
    public $downloads;

    public function __construct() {
        global $UseyourDrive;
        $this->_main = $UseyourDrive;

        /* Add Filter to remove the default 'Guest - ' part from the Private Folder name */
        add_filter('useyourdrive_private_folder_name_guests', array(&$this, 'rename_private_folder_for_guests'));

        if (defined('DOING_AJAX')) {
            return false;
        }

        $this->uploads = new \TheLion\UseyourDrive\WooCommerce_Uploads($this);
        $this->downloads = new \TheLion\UseyourDrive\WooCommerce_Downloads($this);

        $this->id = 'useyourdrive-woocommerce';
        $this->method_title = __('WooCommerce Google Drive', 'useyourdrive');
        $this->method_description = __('Easily add downloadable products right from your Google Drive.', 'useyourdrive') . ' '
                . sprintf(__('To be able to use this integration, you only need to link your Google Account to the plugin on the %s.', 'useyourdrive'), '<a href="' . admin_url('admin.php?page=UseyourDrive_settings#settings_advanced') . '">Use-your-Drive settings page</a>');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
    }

    public function rename_private_folder_for_guests($private_folder_name) {
        return str_replace(__('Guests', 'useyourdrive') . ' - ', '', $private_folder_name);
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor() {
        return $this->_main->get_processor();
    }

    /**
     * @return \TheLion\UseyourDrive\Main
     */
    public function get_main() {
        return $this->_main;
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

class WooCommerce_Downloads {

    /**
     * @var \TheLion\UseyourDrive\WooCommerce 
     */
    private $_woocommerce;

    public function __construct(\TheLion\UseyourDrive\WooCommerce $_woocommerce) {
        $this->_woocommerce = $_woocommerce;

        /* Actions */
        add_action('woocommerce_download_file_force', array(&$this, 'do_direct_download'), 1, 2);
        add_action('woocommerce_download_file_xsendfile', array(&$this, 'do_xsendfile_download'), 1, 2);
        add_action('woocommerce_download_file_redirect', array(&$this, 'do_redirect_download'), 1, 2);

        /* Load custom scripts in the admin area */
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'add_scripts'));
            add_action('edit_form_advanced', array(&$this, 'render_file_selector'), 1, 1);
        }

        /* Add plugin to order table if a directory is downloadable */
        add_action('woocommerce_order_details_before_order_table', array(&$this, 'render_download_folder'), 11);
        add_action('woocommerce_order_details_after_order_table', array(&$this, 'render_download_folder'), 11);
    }

    /**
     * Render the File Browser to allow the user to add files to the Product
     * @param \WP_Post $post
     * @return string
     */
    public function render_file_selector(\WP_Post $post = null) {
        if (isset($post) && $post->post_type !== 'product') {
            return;
        }
        ?>
        <div id='uyd-embedded' style='clear:both;display:none'>
          <?php
          $atts = array('mode' => 'files',
              'filelayout' => 'grid',
              'filesize' => '0',
              'filedate' => '0',
              'addfolder' => '0',
              'showcolumnnames' => '0',
              'downloadrole' => 'none',
              'candownloadzip' => '0',
              'showsharelink' => '0',
              'mcepopup' => 'woocommerce');

          echo $this->get_woocommerce()->get_processor()->create_from_shortcode($atts
          );
          ?>
        </div>
        <?php
    }

    /**
     * Load all the required Script and Styles
     */
    public function add_scripts() {

        $current_screen = get_current_screen();

        if (!in_array($current_screen->id, array('product', 'shop_order'))) {
            return;
        }

        $this->get_woocommerce()->get_main()->load_styles();
        $this->get_woocommerce()->get_main()->load_scripts();

        // register scripts/styles
        add_thickbox();
        wp_register_style('useyourdrive-woocommerce', USEYOURDRIVE_ROOTPATH . '/css/woocommerce.css');
        wp_register_script('useyourdrive-woocommerce', USEYOURDRIVE_ROOTPATH . '/includes/js/Woocommerce.js', array('jquery'), USEYOURDRIVE_VERSION);

        // enqueue scripts/styles
        wp_enqueue_style('UseyourDrive.tinymce');
        wp_enqueue_style('useyourdrive-woocommerce');
        wp_enqueue_script('useyourdrive-woocommerce');
        wp_enqueue_script('UseyourDrive');

        // register translations
        $translation_array = array(
            'choose_from_googledrive' => __('Choose from Google Drive', 'useyourdrive'),
            'download_url' => 'https://drive.google.com/open?action=useyourdrive-wc-direct-download&id=',
            'file_browser_url' => USEYOURDRIVE_ADMIN_URL . '?action=useyourdrive-getwoocommercepopup'
        );

        wp_localize_script('useyourdrive-woocommerce', 'useyourdrive_woocommerce_translation', $translation_array);
    }

    /**
     * Render the Upload Box on the Order View
     * @param int $order_id
     */
    public function render_download_folder($order_id) {

        /* Only render the upload form once
         * Preferably before the order table, but not all templates have this hook available */
        if (doing_action('woocommerce_order_details_before_order_table')) {
            remove_action('woocommerce_order_details_after_order_table', array(&$this, 'render_download_folder'), 11);
        }

        if (doing_action('woocommerce_order_details_after_order_table')) {
            remove_action('woocommerce_order_details_before_order_table', array(&$this, 'render_download_folder'), 11);
        }

        $order = new \WC_Order($order_id);

        if ($order->has_downloadable_item() === false || $order->is_download_permitted() === false) {
            return false;
        }

        foreach ($order->get_items() as $order_item) {

            $product = new \WC_Product($order_item['product_id']);

            foreach ($product->get_downloads() as $file_id => $file) {

                $url = $file->get_file();

                if (strpos($url, 'useyourdrive-wc-direct-download') === false) {
                    continue;
                }

                $cached_entry = $this->get_entry_for_download_by_url($url);

                if (empty($cached_entry) || $cached_entry->get_entry()->is_file()) {
                    continue;
                }

                $shortcode_params = array(
                    'mode' => 'files',
                    'dir' => $cached_entry->get_id(),
                    'filelayout' => 'grid',
                    'viewrole' => 'all',
                    'downloadrole' => 'all',
                    'candownloadzip' => '1',
                    'searchcontents' => '1',
                    'showbreadcrumb' => '0',
                    'maxheight' => '300px'
                );

                echo '<h2 id="downloads_' . $cached_entry->get_id() . '">' . __('Downloads', 'woocommerce') . ' - ' . $file->get_name() . '</h2>';
                echo $this->get_woocommerce()->get_processor()->create_from_shortcode($shortcode_params);
            }
        }
    }

    /**
     * 
     * @param string $file_path
     * @return \TheLion\UseyourDrive\CacheNode 
     */
    public function get_entry_for_download_by_url($file_path) {

        $download_url = parse_url($file_path);
        parse_str($download_url['query'], $download_url_query);
        $entry_id = $download_url_query['id'];

        $cachedentry = $this->get_woocommerce()->get_processor()->get_client()->get_entry($entry_id, false);

        if ($cachedentry === false) {
            self::download_error(__('File not found', 'woocommerce'));
        }

        return $cachedentry;
    }

    public function get_redirect_url_for_entry(CacheNode $cached_entry) {

        if ($cached_entry->get_entry()->is_dir()) {
            $order_id = wc_get_order_id_by_order_key($_GET['order']);

            if (empty($order_id)) {
                self::download_error(__('Order not found', 'woocommerce'));
            }
            $order = new \WC_Order($order_id);
            wp_redirect($order->get_view_order_url());
            die();
        }

        do_action('useyourdrive_log_event', 'useyourdrive_download', $cached_entry);

        $transient_url = self::get_download_url_transient($cached_entry->get_id());
        if (!empty($transient_url)) {
            return $transient_url;
        }

        $token = json_decode($this->get_woocommerce()->get_processor()->get_app()->get_access_token());

        $direct_download_url = $cached_entry->get_entry()->get_direct_download_link();

        if (empty($direct_download_url)) {
            $export_options = $cached_entry->get_entry()->get_save_as();
            /* Google Docs need to be exported, preferably to PDF */
            if (isset($export_options['PDF'])) {
                $format = $export_options['PDF'];
                $mimetype = $format['mimetype'];
                $extension = $format['extension'];
            } else {
                $format = reset($exportlinks);
                $mimetype = $format['mimetype'];
                $extension = $format['extension'];
            }
            $redirect_link = 'https://www.googleapis.com/drive/v3/files/' . $cached_entry->get_id() . '/export?mimeType=' . urlencode($mimetype) . '&alt=media&access_token=' . $token->access_token;
        } else {
            $redirect_link = $direct_download_url . '&access_token=' . $token->access_token;
            $redirect_link = 'https://www.googleapis.com/drive/v3/files/' . $cached_entry->get_id() . '?alt=media&access_token=' . $token->access_token;
        }

        /* The download url V3 does return is actually a redirect, so lets get that redirect url instead */
        $body = file_get_contents($redirect_link, true);
        preg_match('/HREF="(.+)">/', $body, $location);
        $downloadlink = $location[1];

        self::set_download_url_transient($cached_entry->get_id(), $downloadlink);

        return $downloadlink;
    }

    public function do_direct_download($file_path, $filename) {

        if (strpos($file_path, 'useyourdrive-wc-direct-download') === false) {
            return false; // Do nothing
        }

        $cached_entry = $this->get_entry_for_download_by_url($file_path);
        $downloadlink = $this->get_redirect_url_for_entry($cached_entry);

        $direct_download_url = $cached_entry->get_entry()->get_direct_download_link();

        if (empty($direct_download_url)) {
            $filename = $filename . '.' . $extension;
        } else {
            $filename = $cached_entry->get_entry()->get_name();
        }

        /* Download the file */
        self::download_headers($downloadlink, $filename);

        if (!\WC_Download_Handler::readfile_chunked($downloadlink)) {
            $this->do_redirect_download($file_path, $filename);
        }

        exit;
    }

    public function do_xsendfile_download($file_path, $filename) {

        if (strpos($file_path, 'useyourdrive-wc-direct-download') === false) {
            return false; // Do nothing
        }

        // Fallback
        $this->do_direct_download($file_path, $filename);
    }

    public function do_redirect_download($file_path, $filename) {

        if (strpos($file_path, 'useyourdrive-wc-direct-download') === false) {
            return false; // Do nothing
        }

        $cached_entry = $this->get_entry_for_download_by_url($file_path);
        $downloadlink = $this->get_redirect_url_for_entry($cached_entry);

        /* Redirect to the file */
        header('Location: ' . $downloadlink);
        exit;
    }

    /**
     * Get content type of a download.
     * @param  string $file_path
     * @return string
     * @access private
     */
    private static function get_download_content_type($file_path) {
        $file_extension = strtolower(substr(strrchr($file_path, "."), 1));
        $ctype = "application/force-download";

        foreach (get_allowed_mime_types() as $mime => $type) {
            $mimes = explode('|', $mime);
            if (in_array($file_extension, $mimes)) {
                $ctype = $type;
                break;
            }
        }

        return $ctype;
    }

    /**
     * Set headers for the download.
     * @param  string $file_path
     * @param  string $filename
     * @access private
     */
    private static function download_headers($file_path, $filename) {
        self::check_server_config();
        self::clean_buffers();
        nocache_headers();

        header("X-Robots-Tag: noindex, nofollow", true);
        header("Content-Type: " . self::get_download_content_type($file_path));
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=\"" . $filename . "\";");
        header("Content-Transfer-Encoding: binary");

        if ($size = @filesize($file_path)) {
            header("Content-Length: " . $size);
        }
    }

    /**
     * Check and set certain server config variables to ensure downloads work as intended.
     */
    private static function check_server_config() {
        wc_set_time_limit(0);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        @session_write_close();
    }

    /**
     * Clean all output buffers.
     *
     * Can prevent errors, for example: transfer closed with 3 bytes remaining to read.
     *
     * @access private
     */
    private static function clean_buffers() {
        if (ob_get_level()) {
            $levels = ob_get_level();
            for ($i = 0; $i < $levels; $i++) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }
    }

    /**
     * Die with an error message if the download fails.
     * @param  string $message
     * @param  string  $title
     * @param  integer $status
     * @access private
     */
    private static function download_error($message, $title = '', $status = 404) {
        if (!strstr($message, '<a ')) {
            $message .= ' <a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="wc-forward">' . esc_html__('Go to shop', 'woocommerce') . '</a>';
        }
        wp_die($message, $title, array('response' => $status));
    }

    static public function get_download_url_transient($entry_id) {
        return get_transient('useyourdrive_wc_download_' . $entry_id);
    }

    static public function set_download_url_transient($entry_id, $url) {
        /* Update progress */
        return set_transient('useyourdrive_wc_download_' . $entry_id, $url, HOUR_IN_SECONDS);
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\WooCommerce
     */
    public function get_woocommerce() {
        return $this->_woocommerce;
    }

}

class WooCommerce_Uploads {

    /**
     *
     * @var \TheLion\UseyourDrive\WooCommerce 
     */
    private $_woocommerce;

    public function __construct(\TheLion\UseyourDrive\WooCommerce $_woocommerce) {
        $this->_woocommerce = $_woocommerce;

        /* Add Tabs & Content to Product Edit Page */
        add_action('admin_head', array(&$this, 'add_product_data_tab_scripts_and_style'));
        add_filter('product_type_options', array(&$this, 'add_uploadable_product_option'));
        add_filter('woocommerce_product_data_tabs', array(&$this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array(&$this, 'add_product_data_tab_content'));
        add_action('woocommerce_process_product_meta_simple', array(&$this, 'save_product_data_fields'));
        add_action('woocommerce_process_product_meta_variable', array(&$this, 'save_product_data_fields'));
        add_action('woocommerce_process_product_meta_composite', array(&$this, 'save_product_data_fields'));

        /* Add Upload button to my Order Table */
        add_filter('woocommerce_my_account_my_orders_actions', array(&$this, 'add_orders_column_actions'), 10, 2);

        /* Add Upload Box to Order Page */
        //add_action('woocommerce_view_order', array(&$this, 'render_upload_field'), 11);
        add_action('woocommerce_order_details_before_order_table', array(&$this, 'render_upload_field'), 11);
        add_action('woocommerce_order_details_after_order_table', array(&$this, 'render_upload_field'), 11);

        /* Add link to upload box in the Thank You text */
        add_filter('woocommerce_thankyou_order_received_text', array(&$this, 'change_order_received_text'), 10, 2);

        /* Add Upload Box to Admin Order Page */
        add_action('add_meta_boxes', array(&$this, 'add_meta_box'), 10, 2);
    }

    /**
     *  Add a Meta Box to the Order Page where you can find all the uploaded files for the order
     */
    public function add_meta_box($post_type, $post) {

        if (!in_array($post_type, array('shop_order'))) {
            return;
        }

        $order = new \WC_Order($post->ID);

        if (false === $this->requires_order_uploads($order)) {
            return false;
        }

        add_meta_box('woocommerce-useyourdrive-box-order-detail', __('Uploaded Files', 'useyourdrive'), array(&$this, 'render_meta_box'), 'shop_order', 'advanced', 'high');
    }

    /**
     * Add link to upload box in the Thank You text
     * @param string $thank_you_text
     * @param \WC_Order $order
     * @return string
     */
    public function change_order_received_text($thank_you_text, \WC_Order $order) {
        if (false === $this->requires_order_uploads($order)) {
            return $thank_you_text;
        }

        $order_url = '#uploads';
        $thank_you_text .= ' ' . sprintf(__('You can now %sstart uploading your documents%s', 'useyourdrive'), '<a href="' . $order_url . '">', '</a>') . '.';
        return $thank_you_text;
    }

    /**
     * Add new Product Type to the Product Data Meta Box
     * @param array $product_type_options
     * @return array
     */
    public function add_uploadable_product_option($product_type_options) {
        $product_type_options['uploadable'] = array(
            'id' => '_uploadable',
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label' => __('Uploads', 'useyourdrive'),
            'description' => __('Allows your customers to upload files when ordering this product.', 'useyourdrive'),
            'default' => 'no'
        );
        return $product_type_options;
    }

    /**
     * Add new Data Tab to the Product Data Meta Box
     * @param array $product_data_tabs
     * @return array
     */
    public function add_product_data_tab($product_data_tabs) {
        $product_data_tabs['cloud-uploads-drive'] = array(
            'label' => __('Upload to Google Drive', 'useyourdrive'),
            'target' => 'cloud_uploads_data_drive',
            'class' => array('show_if_uploadable')
        );

        return $product_data_tabs;
    }

    /**
     * Add the content of the new Data Tab
     */
    public function add_product_data_tab_content() {
        global $post;

        $default_shortcode = '[useyourdrive mode="files" viewrole="all" userfolders="auto" downloadrole="all" upload="1" uploadrole="all" rename="1" renamefilesrole="all" renamefoldersrole="all" editdescription="1" editdescriptionrole="all" delete="1" deletefilesrole="all" deletefoldersrole="all" viewuserfoldersrole="none" search="0" showbreadcrumb="0"]';
        $shortcode = get_post_meta($post->ID, 'useyourdrive_upload_box_shortcode', true);
        ?> 
        <div id='cloud_uploads_data_drive' class='panel woocommerce_options_panel' style="display:none" >
          <div class="cloud_uploads_data_panel options_group">
            <?php
            woocommerce_wp_checkbox(
                    array(
                        'id' => 'useyourdrive_upload_box',
                        'label' => __('Upload to Google Drive', 'useyourdrive')
                    )
            );
            ?>
            <div class="show_if_useyourdrive_upload_box">
              <h4><?php echo 'Google Drive ' . __('Upload Box Settings', 'useyourdrive') ?></h4>
              <?php
              $default_box_title = 'Uploads';
              $box_title = get_post_meta($post->ID, 'useyourdrive_upload_box_title', true);

              woocommerce_wp_text_input(
                      array(
                          'id' => 'useyourdrive_upload_box_title',
                          'label' => __('Title Upload Box', 'useyourdrive'),
                          'placeholder' => $default_box_title,
                          'desc_tip' => false,
                          'description' => '<br><br>' . __('Enter the title for the upload box', 'useyourdrive') . '. ' . __('You can use the placeholders <code>%wc_order_id%</code>, <code>%wc_product_id%</code>, <code>%wc_product_name%</code>, <code>%jjjj-mm-dd%</code>', 'useyourdrive'),
                          'value' => empty($box_title) ? $default_box_title : $box_title
                      )
              );

              $default_box_description = '';
              $box_description = get_post_meta($post->ID, 'useyourdrive_upload_box_description', true);

              woocommerce_wp_textarea_input(
                      array(
                          'id' => 'useyourdrive_upload_box_description',
                          'label' => __('Description Upload Box', 'useyourdrive'),
                          'placeholder' => $default_box_description,
                          'desc_tip' => false,
                          'description' => '<br><br>' . __('Enter a short description of what the customer needs to upload', 'useyourdrive') . '.',
                          'value' => empty($box_description) ? $default_box_description : $box_description
                      )
              );
              ?>

              <p class="form-field useyourdrive_upload_folder ">
                <label for="useyourdrive_upload_folder">Upload Box</label>
                <a href="#TB_inline?height=450&amp;width=800&amp;inlineId=uyd-embedded" class="button insert-googledrive UseyourDrive-shortcodegenerator" style="float:none"><?php echo __('Build your Upload Box', 'useyourdrive') ?></a>
                <a href="#" class="" style="float:none" onclick="jQuery('#useyourdrive_upload_box_shortcode').fadeToggle()"><?php echo __('Edit Shortcode Manually', 'useyourdrive') ?></a>
                <br/><br/>
                <textarea class="long" style="display:none" name="useyourdrive_upload_box_shortcode" id="useyourdrive_upload_box_shortcode" placeholder="<?php echo $default_shortcode; ?>"  rows="3" cols="20"><?php echo (empty($shortcode)) ? $default_shortcode : $shortcode; ?></textarea>
              </p>

              <?php
              $default_folder_template = '%wc_order_id% - %wc_product_name% - %user_email%';
              $folder_template = get_post_meta($post->ID, 'useyourdrive_upload_box_folder_template', true);

              woocommerce_wp_text_input(
                      array(
                          'id' => 'useyourdrive_upload_box_folder_template',
                          'label' => __('Upload Folder Name', 'useyourdrive'),
                          'description' => '<br><br>' . __('Unique folder name where the uploads should be stored. Make sure that Private Folder feature is enabled in the shortcode', 'useyourdrive') . '. ' . __('You can use the placeholders <code>%wc_order_id%</code>, <code>%wc_product_id%</code>, <code>%wc_product_name%</code>, <code>%user_login%</code>, <code>%user_email%</code>, <code>%display_name%</code>, <code>%ID%</code>, <code>%user_role%</code>, <code>%jjjj-mm-dd%</code>', 'useyourdrive'),
                          'desc_tip' => false,
                          'placeholder' => $default_folder_template,
                          'value' => empty($folder_template) ? $default_folder_template : $folder_template
                      )
              );
              ?>
            </div>
          </div>
        </div><?php
    }

    /**
     * Add the scripts and styles required for the new Data Tab
     */
    public function add_product_data_tab_scripts_and_style() {
        ?>
        <style>
          #woocommerce-product-data ul.wc-tabs li.cloud-uploads-drive_options a:before { font-family: Dashicons; content: '\f176'; }
          .show_if_useyourdrive_upload_box{
            background: #fff;
            border: 1px solid #e5e5e5;
            margin: 5px 15px 10px;
            padding: 1px 12px;
            position: relative;
            overflow: hidden;
          }
        </style>
        <script>
            jQuery(document).ready(function ($) {
              $('input#_uploadable').change(function () {
                var is_uploadable = $('input#_uploadable:checked').size();
                $('.show_if_uploadable').hide();
                $('.hide_if_uploadable').hide();
                if (is_uploadable) {
                  $('.hide_if_uploadable').hide();
                }
                if (is_uploadable) {
                  $('.show_if_uploadable').show();
                }
              });
              $('input#_uploadable').trigger('change');

              $('input#useyourdrive_upload_box').change(function () {
                var useyourdrive_upload_box = $('input#useyourdrive_upload_box:checked').size();
                $('.show_if_useyourdrive_upload_box').hide();
                if (useyourdrive_upload_box) {
                  $('.show_if_useyourdrive_upload_box').show();
                }
              });
              $('input#useyourdrive_upload_box').trigger('change');

              /* Shortcode Generator Popup */
              $('.UseyourDrive-shortcodegenerator').click(function () {
                var shortcode = $("#useyourdrive_upload_box_shortcode").val();
                shortcode = shortcode.replace('[useyourdrive ', '').replace('"]', '');
                var query = encodeURIComponent(shortcode).split('%3D%22').join('=').split('%22%20').join('&');
                tb_show("Build Shortcode for Form", ajaxurl + '?action=useyourdrive-getpopup&' + query + '&type=woocommerce&TB_iframe=true&height=600&width=800');
              });
            });
        </script>
        <?php
    }

    /**
     * Save the new added input fields properly
     * @param int $post_id
     */
    public function save_product_data_fields($post_id) {
        $is_uploadable = isset($_POST['_uploadable']) ? 'yes' : 'no';
        update_post_meta($post_id, '_uploadable', $is_uploadable);

        $useyourdrive_upload_box = isset($_POST['useyourdrive_upload_box']) ? 'yes' : 'no';
        update_post_meta($post_id, 'useyourdrive_upload_box', $useyourdrive_upload_box);


        if (isset($_POST['useyourdrive_upload_box_title'])) {
            update_post_meta($post_id, 'useyourdrive_upload_box_title', $_POST['useyourdrive_upload_box_title']);
        }

        if (isset($_POST['useyourdrive_upload_box_description'])) {
            update_post_meta($post_id, 'useyourdrive_upload_box_description', $_POST['useyourdrive_upload_box_description']);
        }

        if (isset($_POST['useyourdrive_upload_box_shortcode'])) {
            update_post_meta($post_id, 'useyourdrive_upload_box_shortcode', $_POST['useyourdrive_upload_box_shortcode']);
        }

        if (isset($_POST['useyourdrive_upload_box_folder_template'])) {
            update_post_meta($post_id, 'useyourdrive_upload_box_folder_template', $_POST['useyourdrive_upload_box_folder_template']);
        }
    }

    /**
     * Add an 'Upload' Action to the Order Table
     * @param array $actions
     * @param \WC_Order $order
     * @return array
     */
    public function add_orders_column_actions($actions, \WC_Order $order) {

        if ($this->requires_order_uploads($order)) {
            $actions['upload'] = array(
                'url' => $order->get_view_order_url() . '#uploads',
                'name' => __('Upload files', 'useyourdrive')
            );
        }

        return $actions;
    }

    /**
     * Render the Upload Box on the Order View
     * @param int $order_id
     */
    public function render_upload_field($order_id) {

        /* Only render the upload form once
         * Preferably before the order table, but not all templates have this hook available */
        if (doing_action('woocommerce_order_details_before_order_table')) {
            remove_action('woocommerce_order_details_after_order_table', array(&$this, 'render_upload_field'), 11);
        }

        if (doing_action('woocommerce_order_details_after_order_table')) {
            remove_action('woocommerce_order_details_before_order_table', array(&$this, 'render_upload_field'), 11);
        }

        $order = new \WC_Order($order_id);

        foreach ($order->get_items() as $order_item) {

            $product = new \WC_Product($order_item['product_id']);

            if (false === $this->requires_product_uploads($product)) {
                continue;
            }

            $box_title = get_post_meta($product->get_id(), 'useyourdrive_upload_box_title', true);
            $box_description = get_post_meta($product->get_id(), 'useyourdrive_upload_box_description', true);
            $shortcode = get_post_meta($product->get_id(), 'useyourdrive_upload_box_shortcode', true);
            $folder_template = get_post_meta($product->get_id(), 'useyourdrive_upload_box_folder_template', true);

            $shortcode_params = shortcode_parse_atts($shortcode);
            $shortcode_params['userfoldernametemplate'] = $this->set_placeholders($folder_template, $order, $product);

            $show_box = apply_filters('useyourdrive_woocommerce_show_upload_field', true, $order, $product, $this);

            if ($show_box) {
                do_action('useyourdrive_woocommerce_before_render_upload_field', array($order, $product, $this));

                echo '<h2 id="uploads">' . $this->set_placeholders($box_title, $order, $product) . '</h2>';

                if (!empty($box_description)) {
                    echo '<p>' . $box_description . '</p>';
                }

                /* Don't show the upload box when there isn't select a root folder */
                if (empty($shortcode_params['dir']) && $shortcode_params['userfolder'] !== 'manual') {
                    echo sprintf(__('Please %sconfigure%s the upload location for this product', 'useyourdrive'), '', '') . '.';
                    continue;
                }

                echo $this->get_woocommerce()->get_processor()->create_from_shortcode($shortcode_params);

                do_action('useyourdrive_woocommerce_before_render_upload_field', array($order, $product, $this));
            }
        }
    }

    /**
     * Render the Meta Box
     */
    public function render_meta_box(\WP_Post $post) {
        $order = new \WC_Order($post->ID);

        foreach ($order->get_items() as $order_item) {

            $product = new \WC_Product($order_item['product_id']);

            if (false === $this->requires_product_uploads($product)) {
                continue;
            }

            $shortcode = get_post_meta($product->get_id(), 'useyourdrive_upload_box_shortcode', true);
            $folder_template = get_post_meta($product->get_id(), 'useyourdrive_upload_box_folder_template', true);

            $shortcode_params = shortcode_parse_atts($shortcode);
            $shortcode_params['userfoldernametemplate'] = $this->set_placeholders($folder_template, $order, $product);

            /* Always show the File Browser mode in the Dashboard */
            $shortcode_params['mode'] = 'files';

            /* Don't show the upload box when there isn't select a root folder */
            if (empty($shortcode_params['dir']) && $shortcode_params['userfolder'] !== 'manual') {
                $product_url = admin_url('post.php?post=' . $product->get_id() . '&action=edit');
                echo sprintf(__('Please %sconfigure%s the upload location for this product', 'useyourdrive'), '<a href="' . $product_url . '">', '</a>') . '.';
                continue;
            }

            echo $this->get_woocommerce()->get_processor()->create_from_shortcode($shortcode_params);
        }
    }

    /**
     * Checks if the order uses this upload functionality
     * @param \WC_Order $order
     * @return boolean
     */
    public function requires_order_uploads(\WC_Order $order) {

        foreach ($order->get_items() as $order_item) {

            $product = new \WC_Product($order_item['product_id']);
            $requires_upload = $this->requires_product_uploads($product);

            if ($requires_upload) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the product uses this upload functionality
     * @param \WC_Product $product
     * @return boolean
     */
    public function requires_product_uploads(\WC_Product $product) {

        $_uploadable = get_post_meta($product->get_id(), '_uploadable', true);
        $_useyourdrive_upload_box = get_post_meta($product->get_id(), 'useyourdrive_upload_box', true);

        if ('yes' === $_uploadable && 'yes' === $_useyourdrive_upload_box) {
            return true;
        }

        return false;
    }

    /**
     * Fill the placeholders with the User/Product/Order information
     * @param string $template
     * @param \WC_Order $order
     * @param \WC_Product $product
     * @return string
     */
    public function set_placeholders($template, \WC_Order $order, \WC_Product $product) {

        $user = $order->get_user();

        /* Guest User */
        if (false === $user) {
            $user_id = $order->get_order_key();
            $user = new \stdClass();
            $user->user_login = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $user->display_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $user->user_firstname = $order->get_billing_first_name();
            $user->user_lastname = $order->get_billing_last_name();
            $user->user_email = $order->get_billing_email();
            $user->ID = $user_id;
            $user->user_role = __('Guest', 'useyourdrive');
        }

        $output = strtr($template, array(
            "%wc_order_id%" => $order->get_order_number(),
            "%wc_product_id%" => $product->get_id(),
            "%wc_product_name%" => $product->get_name(),
            "%user_login%" => isset($user->user_login) ? $user->user_login : '',
            "%user_email%" => isset($user->user_email) ? $user->user_email : '',
            "%user_firstname%" => isset($user->user_firstname) ? $user->user_firstname : '',
            "%user_lastname%" => isset($user->user_lastname) ? $user->user_lastname : '',
            "%display_name%" => isset($user->display_name) ? $user->display_name : '',
            "%ID%" => isset($user->ID) ? $user->ID : '',
            "%user_role%" => isset($user->roles) ? implode(',', $user->roles) : '',
            "%jjjj-mm-dd%" => date('Y-m-d')
        ));

        return $output;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\WooCommerce
     */
    public function get_woocommerce() {
        return $this->_woocommerce;
    }

}
