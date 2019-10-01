<?php

namespace TheLion\UseyourDrive;

class App {

    /**
     *
     * @var bool 
     */
    private $_own_app = false;

    /**
     *
     * @var string 
     */
    private $_app_key = '538839470620-fvjmtsvik53h255bnu0qjmbr8kvd923i.apps.googleusercontent.com';

    /**
     *
     * @var string 
     */
    private $_app_secret = 'UZ1I3I-D4rPhXpnE8T1ggGhE';

    /**
     *
     * @var string 
     */
    private $_identifier;

    /**
     * 
     * @var \UYDGoogle_Service_Oauth2
     */
    private $_user_info_service;

    /**
     * 
     * @var \UYDGoogle_Service_Drive
     */
    private $_google_drive_service;

    /**
     * 
     * @var \UYDGoogle_Service_Urlshortener
     */
    private $_google_urlshortener_service;

    /**
     * 
     * @var \UYDGoogle_Service_Firebase
     */
    private $_google_firebase_service;

    /**
     * 
     * @var \UYDGoogle_Client
     */
    private $_client;

    /**
     * We don't save your data or share it. 
     * This script just simply creates a redirect with your id and secret to Google Drive and returns the created token.
     * It is exactly the same script as the _authorizeApp.php file in the includes folder of the plugin, 
     * and is used for an easy and one-click authorization process that will always work!
     * @var string 
     */
    private $_redirect_uri = 'https://www.wpcloudplugins.com/use-your-drive/index.php';

    /**
     *
     * @var boolean 
     */
    private $_log_api_request = false;

    /**
     * Contains the location to the token file
     * @var string 
     */
    private $_token_location;

    /**
     * Contains the file handle for the token file
     * @var type 
     */
    private $_token_file_handle = null;

    /**
     *
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;

    public function __construct(Processor $processor) {
        $this->_processor = $processor;
        $this->_token_location = USEYOURDRIVE_CACHEDIR . '/' . get_current_blog_id() . '.access_token';

        /* Call back for refresh token function in SDK client */
        add_action('use-your-drive-refresh-token', array(&$this, 'start_client'));

        if (!function_exists('uyd_google_api_php_client_autoload')) {
            require_once "Google-sdk/src/Google/autoload.php";
        }

        if (!class_exists('UYDGoogle_Client') || (!method_exists('UYDGoogle_Client', 'getLibraryVersion'))) {
            $reflector = new \ReflectionClass('UYDGoogle_Client');
            $error = "Conflict with other Google Library: " . $reflector->getFileName();
            throw new \Exception($error);
        }


        /** Preperation for new Google SDK
         * require_once USEYOURDRIVE_ROOTDIR . "/vendor/autoload.php";
         */
        $own_key = $this->get_processor()->get_setting('googledrive_app_client_id');
        $own_secret = $this->get_processor()->get_setting('googledrive_app_client_secret');

        if (
                (!empty($own_key)) &&
                (!empty($own_secret))
        ) {
            $this->_app_key = $this->get_processor()->get_setting('googledrive_app_client_id');
            $this->_app_secret = $this->get_processor()->get_setting('googledrive_app_client_secret');
            $this->_own_app = true;
        }

        /* Set right redirect URL */
        $this->set_redirect_uri();

        /* Process codes/tokens if needed */
        $this->process_authorization();
    }

    public function process_authorization() {
        /* CHECK IF THIS PLUGIN IS DOING THE AUTHORIZATION */
        if (!isset($_REQUEST['action'])) {
            return false;
        }

        if ($_REQUEST['action'] !== 'useyourdrive_authorization') {
            return false;
        }

        $this->get_processor()->reset_complete_cache();

        if (isset($_GET['code'])) {
            $access_token = $this->create_access_token();
            /** Echo To Popup */
            echo '<script type="text/javascript">window.opener.parent.location.href = "' . admin_url('admin.php?page=UseyourDrive_settings') . '"; window.close();</script>';
            die();
        } elseif (isset($_GET['_token'])) {
            $new_access_token = $_GET['_token'];
            $access_token = $this->set_access_token($new_access_token);

            /** Echo To Popup */
            echo '<script type="text/javascript">window.opener.parent.location.href = "' . admin_url('admin.php?page=UseyourDrive_settings') . '"; window.close();</script>';
            die();
        }



        return false;
    }

    public function can_do_own_auth() {
        return true;
    }

    public function has_plugin_own_app() {
        return $this->_own_app;
    }

    public function get_auth_url() {
        return $this->get_client()->createAuthUrl();
    }

    /**
     * 
     * @return \UYDGoogle_Client
     */
    public function start_client() {

        try {
            $this->_client = new \UYDGoogle_Client();
            $this->_client->getLibraryVersion();
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Cannot start Google Client %s', $ex->getMessage()));
            return $ex;
        }


        /** NEW SDK
         * 
         * $origin = isset($_SERVER["HTTP_ORIGIN"]) ? $_SERVER["HTTP_ORIGIN"] : null; // REQUIRED FOR CORS LIKE REQUEST (DIRECT UPLOAD)
         * 
         * $this->client->setHttpClient(new \GuzzleHttp\Client(array(
         * 'verify' => USEYOURDRIVE_ROOTDIR . '/cacerts.pem',
         * 'headers' => array('Origin' => $origin)
         * ))); 
         */
        $this->_client->setApplicationName('WordPress Use-your-Drive ' . USEYOURDRIVE_VERSION);
        $this->_client->setClientId($this->get_app_key());
        $this->_client->setClientSecret($this->get_app_secret());

        $this->_client->setRedirectUri($this->get_redirect_uri());

        $this->_client->setApprovalPrompt('auto');
        $this->_client->setAccessType('offline');

        $this->_client->setScopes(array(
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
                //'https://www.googleapis.com/auth/urlshortener',
        ));

        $state = admin_url('admin.php?page=UseyourDrive_settings&action=useyourdrive_authorization');
        $this->_client->setState(strtr(base64_encode($state), '+/=', '-_~'));

        $this->set_logger();

        if ($this->has_access_token() === false) {
            return $this->_client;
        }

        $access_token = $this->get_access_token();

        if (empty($access_token)) {
            $this->_unlock_token_file();
            return $this->_client;
        }

        if (!empty($access_token)) {

            $this->_client->setAccessToken($access_token);

            /* Token function for new Google SDK  */
            /* $this->client->setTokenCallback(array(&$this, 'storeToken')); */

            /* Check if the AccessToken is still valid * */
            if ($this->_client->isAccessTokenExpired() === false) {
                $this->_unlock_token_file();
                return $this->_client;
            }
        }

        if (!flock($this->_get_token_file_handle(), LOCK_EX | LOCK_NB)) {
            error_log('[Use-your-Drive message]: ' . sprintf('Wait till another process has renewed the Authorization Token'));

            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that 
             * the previous process died and unlock the file manually
             */
            $requires_unlock = ((filemtime($this->get_token_location()) + 60) < (time()));
            if ($requires_unlock) {
                $this->_unlock_token_file();
            }

            if (flock($this->_get_token_file_handle(), LOCK_SH)) {
                clearstatcache();
                rewind($this->_get_token_file_handle());
                $token = fread($this->_get_token_file_handle(), filesize($this->get_token_location()));
                error_log('[Use-your-Drive message]: ' . sprintf('New Authorization Token has been received by another process'));
                $this->_client->setAccessToken($access_token);
                $this->_unlock_token_file();
                return $this->_client;
            }
        }

        //error_log('[Use-your-Drive message]: ' . sprintf('Start renewing the Authorization Token'));

        /* Stop if we need to get a new AccessToken but somehow ended up without a refreshtoken */
        $refresh_token = $this->_client->getRefreshToken();

        if (empty($refresh_token)) {
            error_log('[Use-your-Drive message]: ' . sprintf('No Refresh Token found during the renewing of the current token. We will stop the authorization completely.'));

            define('USEYOURDRIVE_NOAUTHORIZATION', true);

            $this->_unlock_token_file();
            $this->revoke_token();
            return false;
        }

        /* Refresh token */
        try {
            $this->_client->refreshToken($refresh_token);

            /* Store the new token */
            $new_accestoken = $this->_client->getAccessToken();
            $this->set_access_token($new_accestoken);

            $this->_unlock_token_file();
            //error_log('[Use-your-Drive message]: ' . sprintf('Received new Authorization Token'));

            if (($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification')) !== false) {
                wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification');
            }
        } catch (\Exception $ex) {
            $this->_unlock_token_file();
            error_log('[Use-your-Drive message]: ' . sprintf('Cannot refresh Authorization Token'));

            if (!wp_next_scheduled('useyourdrive_lost_authorisation_notification')) {
                wp_schedule_event(time(), 'daily', 'useyourdrive_lost_authorisation_notification');
            }

            define('USEYOURDRIVE_NOAUTHORIZATION', true);

            $this->get_processor()->reset_complete_cache();

            throw $ex;
        }

        return $this->_client;
    }

    public function set_approval_prompt($approval_prompt = 'auto') {
        $this->get_client()->setApprovalPrompt($approval_prompt);
    }

    public function set_logger() {

        if ($this->_log_api_request) {
            /* Logger */
            $this->get_client()->setClassConfig('UYDGoogle_Logger_File', array(
                'file' => USEYOURDRIVE_CACHEDIR . '/api.log',
                'mode' => 0640,
                'lock' => true));

            $this->get_client()->setClassConfig('UYDGoogle_Logger_Abstract', array(
                'level' => 'debug', //'warning' or 'debug'
                'log_format' => "[%datetime%] %level%: %message% %context%\n",
                'date_format' => 'd/M/Y:H:i:s O',
                'allow_newlines' => true));

            /* Uncomment the following line to log communcations.
             * The log is located in /cache/log
             */

            $this->get_client()->setLogger(new \UYDGoogle_Logger_File($this->get_client()));
        }


        /* Logger for new Google SDK 
         * 
         * $logger = new Monolog\Logger('google-api-php-client');
         * $logger->pushHandler(new Monolog\Handler\StreamHandler(USEYOURDRIVE_CACHEDIR . '/api.log', Monolog\Logger::DEBUG));
         * $this->client->setLogger($logger);
         */
    }

    public function create_access_token() {
        try {
            $code = $_REQUEST['code'];
            $state = $_REQUEST['state'];

            //Fetch the AccessToken
            $this->get_client()->authenticate($code);
            $access_token = $this->get_client()->getAccessToken();
            $this->set_access_token($access_token);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Cannot generate Access Token: %s', $ex->getMessage()));
            return new \WP_Error('broke', __("error communicating with Google API: ", 'useyourdrive') . $ex->getMessage());
        }

        return true;
    }

    public function revoke_token() {

        error_log('[Use-your-Drive message]: ' . 'Lost authorization');

        unlink($this->get_token_location());

        $this->get_processor()->set_setting('userfolder_backend_auto_root', null);
        $this->get_processor()->reset_complete_cache();

        if (($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification')) !== false) {
            wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification');
        }

        $this->get_processor()->get_main()->send_lost_authorisation_notification();

        try {
            $this->get_client()->revokeToken();
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . $ex->getMessage());
        }

        return true;
    }

    /* Token function for new Google SDK  */

    public function get_app_key() {
        return $this->_app_key;
    }

    public function get_app_secret() {
        return $this->_app_secret;
    }

    public function set_app_key($_app_key) {
        $this->_app_key = $_app_key;
    }

    public function set_app_secret($_app_secret) {
        $this->_app_secret = $_app_secret;
    }

    public function get_access_token() {
        $this->_get_lock();
        clearstatcache();
        rewind($this->_get_token_file_handle());

        $filesize = filesize($this->get_token_location());
        if ($filesize > 0) {
            $token = fread($this->_get_token_file_handle(), filesize($this->get_token_location()));
        } else {
            $token = '';
        }

        $this->_unlock_token_file();
        if (empty($token)) {
            return null;
        }

        return $token;
    }

    public function set_access_token($_access_token) {
        ftruncate($this->_get_token_file_handle(), 0);
        rewind($this->_get_token_file_handle());

        return fwrite($this->_get_token_file_handle(), $_access_token);
    }

    public function has_access_token() {

        if (defined('USEYOURDRIVE_NOAUTHORIZATION')) {
            return false;
        }

        $access_token = $this->get_access_token();
        return (!empty($access_token));
    }

    public function _get_lock($type = LOCK_SH) {

        if (!flock($this->_get_token_file_handle(), $type)) {
            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that 
             * the previous process died and unlock the file manually
             */
            $requires_unlock = ((filemtime($this->get_token_location()) + 60) < (time()));
            if ($requires_unlock) {
                $this->_unlock_token_file();
            }
            /* Try to lock the file again */
            flock($this->_get_token_file_handle(), $type);
        }

        return $this->_get_token_file_handle();
    }

    protected function _unlock_token_file() {
        $handle = $this->_get_token_file_handle();
        if (!empty($handle)) {
            flock($this->_get_token_file_handle(), LOCK_UN);
            fclose($this->_get_token_file_handle());
            $this->_set_token_file_handle(null);
        }

        clearstatcache();
        return true;
    }

    public function get_token_location() {
        return $this->_token_location;
    }

    protected function _set_token_file_handle($handle) {
        return $this->_token_file_handle = $handle;
    }

    protected function _get_token_file_handle() {

        if (empty($this->_token_file_handle)) {


            /* Check if cache dir is writeable */
            /* Moving from DB storage to file storage in version 1.7.0.7 */
            if (!file_exists($this->get_token_location())) {
                $token = $this->get_processor()->get_setting('googledrive_app_current_token');
                $this->get_processor()->set_setting('googledrive_app_current_token', null);
                $this->get_processor()->set_setting('googledrive_app_refresh_token', null);
                file_put_contents($this->get_token_location(), $token);
            }

            if (!is_writable($this->get_token_location())) {
                @chmod($this->get_token_location(), 0755);

                if (!is_writable($this->get_token_location())) {
                    error_log('[Use-your-Drive message]: ' . sprintf('Cache file (%s) is not writable', $this->get_token_location()));
                    die(sprintf('Cache file (%s) is not writable', $this->get_token_location()));
                }

                file_put_contents($this->get_token_location(), $token);
            }


            $this->_token_file_handle = fopen($this->get_token_location(), 'c+');
            if (!is_resource($this->_token_file_handle)) {
                error_log('[Use-your-Drive message]: ' . sprintf('Cache file (%s) is not writable', $this->get_token_location()));
                die(sprintf('Cache file (%s) is not writable', $this->get_token_location()));
            }
        }

        return $this->_token_file_handle;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor() {
        return $this->_processor;
    }

    /**
     * 
     * @return \UYDGoogle_Client
     */
    public function get_client() {

        if (empty($this->_client)) {
            $this->_client = $this->start_client();
        }

        return $this->_client;
    }

    /**
     * 
     * @return \UYDGoogle_Service_Oauth2
     */
    public function get_user() {
        if (empty($this->_user_info_service)) {
            $client = $this->get_client();
            $this->_user_info_service = new \UYDGoogle_Service_Oauth2($client);
        }
        return $this->_user_info_service;
    }

    /**
     * 
     * @return \UYDGoogle_Service_Drive
     */
    public function get_drive() {
        if (empty($this->_google_drive_service)) {
            $client = $this->get_client();
            $this->_google_drive_service = new \UYDGoogle_Service_Drive($client);
        }
        return $this->_google_drive_service;
    }

    /**
     * 
     * @return \UYDGoogle_Service_Urlshortener
     */
    public function get_urlshortener() {
        if (empty($this->_google_urlshortener_service)) {
            $client = $this->get_client();
            $this->_google_urlshortener_service = new \UYDGoogle_Service_Urlshortener($client);
        }
        return $this->_google_urlshortener_service;
    }

    /**
     * 
     * @return \UYDGoogle_Service_Firebase
     */
    public function get_firebase() {
        if (empty($this->_google_firebase_service)) {
            $client = $this->get_client();
            $this->_google_firebase_service = new \UYDGoogle_Service_Firebase($client);
        }
        return $this->_google_firebase_service;
    }

    public function get_redirect_uri() {
        return $this->_redirect_uri;
    }

    public function set_redirect_uri() {

        /* Only change it if you are using own app */
        if ($this->has_plugin_own_app()) {
            $this->_redirect_uri = USEYOURDRIVE_ROOTPATH . '/includes/_authorizeApp.php';
        }
    }

}
