<?php

namespace TheLion\UseyourDrive;

class Processor {

    /**
     *
     * @var \TheLion\UseyourDrive\Main 
     */
    private $_main;

    /**
     *
     * @var \TheLion\UseyourDrive\App 
     */
    private $_app;

    /**
     *
     * @var \TheLion\UseyourDrive\Client 
     */
    private $_client;

    /**
     *
     * @var \TheLion\UseyourDrive\User  
     */
    private $_user;

    /**
     *
     * @var \TheLion\UseyourDrive\UserFolders 
     */
    private $_userfolders;

    /**
     *
     * @var \TheLion\UseyourDrive\Cache 
     */
    private $_cache;
    public $options = array();
    protected $lists = array();
    protected $listtoken = '';
    protected $_rootFolder = null;
    protected $_lastFolder = null;
    protected $_folderPath = null;
    protected $_requestedEntry = null;
    protected $_loadscripts = array('general' => false, 'files' => false, 'upload' => false, 'mediaplayer' => false, 'qtip' => false);
    public $userip;
    public $mobile = false;

    /**
     * Construct the plugin object
     */
    public function __construct(Main $_main) {
        $this->_main = $_main;
        register_shutdown_function(array(&$this, 'do_shutdown'));

        $this->settings = get_option('use_your_drive_settings');
        $this->lists = get_option('use_your_drive_lists', array());
        $this->userip = Helpers::get_user_ip();

        if (isset($_REQUEST['mobile']) && ($_REQUEST['mobile'] === 'true')) {
            $this->mobile = true;
        }

        /* If the user wants a hard refresh, set this globally */
        if (isset($_REQUEST['hardrefresh']) && $_REQUEST['hardrefresh'] === 'true' && (!defined('FORCE_REFRESH'))) {
            define('FORCE_REFRESH', true);
        }
    }

    public function start_process() {
        if (!isset($_REQUEST['action'])) {
            error_log('[Use-your-Drive message]: ' . " Function start_process() requires an 'action' request");
            die();
        }

        $authorized = $this->_is_action_authorized();

        if (($authorized === true) && ($_REQUEST['action'] === 'useyourdrive-revoke')) {
            if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
                $this->get_app()->revoke_token();
            }
            die(1);
        }

        if ($_REQUEST['action'] === 'useyourdrive-reset-cache') {
            if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
                $this->reset_complete_cache();
            }
            die(1);
        }

        if ((!isset($_REQUEST['listtoken']))) {
            error_log('[Use-your-Drive message]: ' . " Function start_process() requires a 'listtoken' request");
            die();
        }

        $this->listtoken = $_REQUEST['listtoken'];
        if (!isset($this->lists[$this->listtoken])) {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            error_log('[Use-your-Drive message]: ' . " Function start_process() hasn't received a valid listtoken (" . $this->listtoken . ") on: $url \nLists:\n" . var_export(array_keys($this->lists), true));
            die();
        }

        $this->options = $this->lists[$this->listtoken];

        if (is_wp_error($authorized)) {
            error_log('[Use-your-Drive message]: ' . " Function start_process() isn't authorized");

            if ($this->options['debug'] === '1') {
                die($authorized->get_error_message());
            } else {
                die();
            }
        }

        /* Refresh Cache if needed */
        //$this->get_cache()->reset_cache();

        /* Remove all cache files for current shortcode when refreshing, otherwise check for new changes */
        if (defined('FORCE_REFRESH')) {
            CacheRequest::clear_local_cache_for_shortcode($this->get_listtoken());
            $this->get_cache()->pull_for_changes();
        } else {
            /* Pull for changes if needed */
            if ($this->get_setting('cache_update_via_wpcron') === 'No') {
                $this->get_cache()->pull_for_changes();
            }
        }

        /* Set rootFolder */
        if ($this->options['user_upload_folders'] === 'manual') {
            $userfolder = $this->get_user_folders()->get_manually_linked_folder_for_user();
            if ($userfolder === false) {
                error_log('[Use-your-Drive message]: ' . 'Cannot find a manually linked folder for user');
                die('-1');
            }
            $this->_rootFolder = $userfolder->get_id();
        } else if (($this->options['user_upload_folders'] === 'auto') && !Helpers::check_user_role($this->options['view_user_folders_role'])) {
            $userfolder = $this->get_user_folders()->get_auto_linked_folder_for_user();

            if ($userfolder === false) {
                error_log('[Use-your-Drive message]: ' . 'Cannot find a auto linked folder for user');
                die('-1');
            }
            $this->_rootFolder = $userfolder->get_id();
        } else {
            $this->_rootFolder = $this->options['root'];
        }

        if ($this->get_user()->can_view() === false) {
            error_log('[Use-your-Drive message]: ' . " Function start_process() discovered that an user didn't have the permission to view the plugin");
            die();
        }

        $this->_lastFolder = $this->_rootFolder;
        if (isset($_REQUEST['lastFolder']) && $_REQUEST['lastFolder'] !== '') {
            $this->_lastFolder = $_REQUEST['lastFolder'];
        }

        $this->_requestedEntry = $this->_lastFolder;
        if (isset($_REQUEST['id']) && $_REQUEST['id'] !== '') {
            $this->_requestedEntry = $_REQUEST['id'];
        }

        if (!empty($_REQUEST['folderPath'])) {
            $this->_folderPath = unserialize(base64_decode($_REQUEST['folderPath']));

            if ($this->_folderPath === false || $this->_folderPath === null || !is_array($this->_folderPath)) {
                $this->_folderPath = array($this->_rootFolder);
            }

            $key = array_search($this->_requestedEntry, $this->_folderPath);
            if ($key !== false) {
                array_splice($this->_folderPath, $key);
                if (count($this->_folderPath) === 0) {
                    $this->_folderPath = array($this->_rootFolder);
                }
            }
        } else {
            $this->_folderPath = array($this->_rootFolder);
        }

        /* Check if the request is cached */
        if (in_array($_REQUEST['action'], array('useyourdrive-get-filelist', 'useyourdrive-get-gallery', 'useyourdrive-get-playlist', 'useyourdrive-thumbnail'))) {

            /* And Set GZIP compression if possible */
            $this->_set_gzip_compression();

            if (!defined('FORCE_REFRESH')) {
                $cached_request = new CacheRequest($this);
                if ($cached_request->is_cached()) {
                    echo $cached_request->get_cached_response();
                    die();
                }
            }
        }

        /* Extend the expire date of the shortcode */
        $this->options['expire'] = strtotime('+1 weeks');
        $this->update_lists();

        do_action('useyourdrive_start_process', $_REQUEST['action'], $this);

        switch ($_REQUEST['action']) {

            case 'useyourdrive-get-filelist':

                $filebrowser = new Filebrowser($this);

                if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && $this->options['search'] === '1') { // Search files
                    $filelist = $filebrowser->search_files();
                } else {
                    $filelist = $filebrowser->get_files_list(); // Read folder
                }

                break;

            case 'useyourdrive-download':
                if ($this->get_user()->can_download() === false) {
                    die();
                }

                $file = $this->get_client()->download_entry();
                break;
            case 'useyourdrive-preview':
                $file = $this->get_client()->preview_entry();
                break;
            case 'useyourdrive-thumbnail':
                if (isset($_REQUEST['type']) && $_REQUEST['type'] === 'folder-thumbnails') {
                    $thumbnails = $this->get_client()->get_folder_thumbnails();
                    $response = json_encode($thumbnails);

                    $cached_request = new CacheRequest($this);
                    $cached_request->add_cached_response($response);

                    echo $response;
                } else {
                    $file = $this->get_client()->build_thumbnail();
                }
                break;
            case 'useyourdrive-create-zip':
                $file = $this->get_client()->download_entries_as_zip();
                break;


            case 'useyourdrive-embedded':
                $links = $this->get_client()->create_links(false);
                echo json_encode($links);
                break;
            case 'useyourdrive-create-link':

                if (isset($_REQUEST['entries'])) {
                    $links = $this->get_client()->create_links();
                    echo json_encode($links);
                } else {
                    $link = $this->get_client()->create_link();
                    echo json_encode($link);
                }

                break;

            case 'useyourdrive-get-gallery':
                if (is_wp_error($authorized)) {
// No valid token is set
                    echo json_encode(array('lastpath' => base64_encode(serialize($this->_lastFolder)), 'folder' => '', 'html' => ''));
                    die();
                }

                $gallery = new Gallery($this);

                if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && $this->options['search'] === '1') { // Search files
                    $imagelist = $gallery->search_image_files();
                } else {
                    $imagelist = $gallery->get_images_list(); // Read folder
                }

                break;

            case 'useyourdrive-upload-file':
                $user_can_upload = $this->get_user()->can_upload();

                if (is_wp_error($authorized) || $user_can_upload === false) {
                    die();
                }

                $upload_processor = new Upload($this);

                switch ($_REQUEST['type']) {
                    case 'do-upload':
                        $upload = $upload_processor->do_upload();
                        break;
                    case 'get-status':
                        $status = $upload_processor->get_upload_status();
                        break;
                    case 'get-direct-url':
                        $status = $upload_processor->do_upload_direct();
                        break;
                    case 'upload-convert':
                        $status = $upload_processor->upload_convert();
                        break;
                    case 'upload-postprocess':
                        $status = $upload_processor->upload_post_process();
                        break;
                }

                die();
                break;

            case 'useyourdrive-delete-entry':
            case 'useyourdrive-delete-entries':
//Check if user is allowed to delete entry
                $user_can_delete = $this->get_user()->can_delete_files() || $this->get_user()->can_delete_folders();

                if (is_wp_error($authorized) || $user_can_delete === false) {
                    echo json_encode(array('result' => '-1', 'msg' => __('Failed to delete entry', 'useyourdrive')));
                    die();
                }

                $entries_to_delete = array();
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $entries_to_delete[] = $requested_id;
                }

                $entries = $this->get_client()->delete_entries($entries_to_delete);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry)) {
                        echo json_encode(array('result' => '-1', 'msg' => __('Not all entries could be deleted', 'useyourdrive')));
                        die();
                    }
                }
                echo json_encode(array('result' => '1', 'msg' => __('Entry was deleted', 'useyourdrive')));

                die();
                break;

            case 'useyourdrive-rename-entry':
//Check if user is allowed to rename entry
                $user_can_rename = $this->get_user()->can_rename_files() || $this->get_user()->can_rename_folders();

                if ($user_can_rename === false) {
                    echo json_encode(array('result' => '-1', 'msg' => __('Failed to rename entry', 'useyourdrive')));
                    die();
                }

//Strip unsafe characters
                $newname = rawurldecode($_REQUEST['newname']);
                $new_filename = Helpers::filter_filename($newname, false);

                $file = $this->get_client()->rename_entry($new_filename);

                if (is_wp_error($file)) {
                    echo json_encode(array('result' => '-1', 'msg' => $file->get_error_message()));
                } else {
                    echo json_encode(array('result' => '1', 'msg' => __('Entry was renamed', 'useyourdrive')));
                }

                die();
                break;


            case 'useyourdrive-move-entry':
                /* Check if user is allowed to move entry */
                $user_can_moveentry = $this->get_user()->can_move_files() || $this->get_user()->can_move_folders();

                if ($user_can_moveentry === false) {
                    echo json_encode(array('result' => '-1', 'msg' => __('Failed to move', 'useyourdrive')));
                    die();
                }

                $file = $this->get_client()->move_entry($_REQUEST['target']);

                if (is_wp_error($file)) {
                    echo json_encode(array('result' => '-1', 'msg' => $file->get_error_message()));
                } else {
                    echo json_encode(array('result' => '1', 'msg' => __('Entry was moved', 'useyourdrive')));
                }

                die();
                break;

            case 'useyourdrive-edit-description-entry':
                //Check if user is allowed to rename entry
                $user_can_editdescription = $this->get_user()->can_edit_description();

                if ($user_can_editdescription === false) {
                    echo json_encode(array('result' => '-1', 'msg' => __('Failed to edit description', 'useyourdrive')));
                    die();
                }

                $newdescription = rawurldecode($_REQUEST['newdescription']);
                $result = $this->get_client()->update_description($newdescription);

                if (is_wp_error($result)) {
                    echo json_encode(array('result' => '-1', 'msg' => $result->get_error_message()));
                } else {
                    echo json_encode(array('result' => '1', 'msg' => __('Description was edited', 'useyourdrive'), 'description' => $result));
                }

                die();
                break;


            case 'useyourdrive-add-folder':

//Check if user is allowed to add folder
                $user_can_addfolder = $this->get_user()->can_add_folders();

                if ($user_can_addfolder === false) {
                    echo json_encode(array('result' => '-1', 'msg' => __('Failed to add folder', 'useyourdrive')));
                    die();
                }

//Strip unsafe characters
                $newfolder = rawurldecode($_REQUEST['newfolder']);
                $new_foldername = Helpers::filter_filename($newfolder, false);

                $file = $this->get_client()->add_folder($new_foldername);

                if (is_wp_error($file)) {
                    echo json_encode(array('result' => '-1', 'msg' => $file->get_error_message()));
                } else {
                    echo json_encode(array('result' => '1', 'msg' => __('Folder', 'useyourdrive') . ' ' . $newfolder . ' ' . __('was added', 'useyourdrive'), 'lastFolder' => $file->get_id()));
                }
                die();
                break;

            case 'useyourdrive-get-playlist':
                $mediaplayer = new Mediaplayer($this);
                $playlist = $mediaplayer->get_media_list();

                break;

            case 'useyourdrive-stream':
                $file = $this->get_client()->stream_entry();
                break;

            default:
                error_log('[Use-your-Drive message]: ' . sprintf('No valid AJAX call: %s', $_REQUEST['action']));
                die('Use-your-Drive: ' . __('no valid AJAX call', 'useyourdrive'));
        }

        die();
    }

    public function create_from_shortcode($atts) {

        $atts = (is_string($atts)) ? array() : $atts;
        $atts = $this->remove_deprecated_options($atts);

//Create a unique identifier
        $this->listtoken = md5(USEYOURDRIVE_VERSION . serialize($atts));

//Read shortcode
        extract(shortcode_atts(array(
            'dir' => false,
            'class' => '',
            'startid' => false,
            'mode' => 'files',
            'userfolders' => '0',
            'usertemplatedir' => '',
            'viewuserfoldersrole' => 'administrator',
            'userfoldernametemplate' => '',
            'showfiles' => '1',
            'maxfiles' => '-1',
            'showfolders' => '1',
            'filesize' => '1',
            'filedate' => '1',
            'filelayout' => 'grid',
            'showcolumnnames' => '1',
            'showext' => '1',
            'sortfield' => 'name',
            'sortorder' => 'asc',
            'showbreadcrumb' => '1',
            'candownloadzip' => '0',
            'canpopout' => '0',
            'showsharelink' => '0',
            'showrefreshbutton' => '1',
            'roottext' => __('Start', 'useyourdrive'),
            'search' => '1',
            'searchcontents' => '0',
            'searchfrom' => 'parent',
            'include' => '*',
            'includeext' => '*',
            'exclude' => '*',
            'excludeext' => '*',
            'maxwidth' => '100%',
            'maxheight' => '',
            'viewrole' => 'administrator|editor|author|contributor|subscriber|pending|guest',
            'downloadrole' => 'administrator|editor|author|contributor|subscriber|pending|guest',
            'sharerole' => 'all',
            'previewinline' => '1',
            'forcedownload' => '0',
            'maximages' => '25',
            'quality' => '90',
            'slideshow' => '0',
            'pausetime' => '5000',
            'showfilenames' => '0',
            'targetheight' => '200',
            'mediaextensions' => '',
            'autoplay' => '0',
            'hideplaylist' => '0',
            'covers' => '0',
            'linktomedia' => '0',
            'linktoshop' => '',
            'notificationupload' => '0',
            'notificationdownload' => '0',
            'notificationdeletion' => '0',
            'notificationemail' => '%admin_email%',
            'notification_skipemailcurrentuser' => '0',
            'upload' => '0',
            'upload_folder' => '1',
            'uploadext' => '.',
            'uploadrole' => 'administrator|editor|author|contributor|subscriber',
            'upload_encryption' => '0',
            'upload_encryption_passphrase' => '',
            'maxfilesize' => '0',
            'maxnumberofuploads' => '-1',
            'convert' => '0',
            'convertformats' => 'all',
            'overwrite' => '0',
            'delete' => '0',
            'deletefilesrole' => 'administrator|editor',
            'deletefoldersrole' => 'administrator|editor',
            'deletetotrash' => '1',
            'rename' => '0',
            'renamefilesrole' => 'administrator|editor',
            'renamefoldersrole' => 'administrator|editor',
            'move' => '0',
            'movefilesrole' => 'administrator|editor',
            'movefoldersrole' => 'administrator|editor',
            'editdescription' => '0',
            'editdescriptionrole' => 'administrator|editor',
            'addfolder' => '0',
            'addfolderrole' => 'administrator|editor',
            'mcepopup' => '0',
            'debug' => '0',
            'demo' => '0'
                        ), $atts));

        if (!isset($this->lists[$this->listtoken])) {

            $authorized = $this->_is_action_authorized();

            if (is_wp_error($authorized)) {
                if ($debug === '1') {
                    return "<div id='message' class='error'><p>" . $authorized->get_error_message() . "</p></div>";
                }
                return '<i>>>> ' . __('ERROR: Contact the Administrator to see this content', 'useyourdrive') . ' <<<</i>';
            }

            $this->lists[$this->listtoken] = array();

//Set Session Data
            switch ($mode) {
                case 'audio':
                case 'video':
                    $mediaextensions = explode('|', $mediaextensions);
                    break;
                case 'gallery':
                    $includeext = ($includeext == '*') ? 'gif|jpg|jpeg|png|bmp' : $includeext;
                    $uploadext = ($uploadext == '.') ? 'gif|jpg|jpeg|png|bmp' : $uploadext;
                    $mediaextensions = '';
                case 'search':
                    $searchfrom = 'root';
                default:
                    $mediaextensions = '';
                    break;
            }

            $rootfolder = $this->get_client()->get_root_folder();
            if (is_wp_error($rootfolder)) {
                if ($debug === '1') {
                    return "<div id='message' class='error'><p>" . $rootfolder->get_error_message() . "</p></div>";
                }
                return false;
            } elseif (empty($rootfolder)) {
                if ($debug === '1') {
                    return "<div id='message' class='error'><p>" . __('Please authorize Use-your-Drive', 'useyourdrive') . "</p></div>";
                }
                return false;
            }
            $rootfolderid = $rootfolder->get_id();

            if (empty($dir)) {
                $dir = $this->get_client()->get_my_drive()->get_id();
            }

//Force $candownloadzip = 0 if we can't use ZipArchive
            if (!class_exists('ZipArchive')) {
                $candownloadzip = '0';
            }

            if ($upload_encryption === '1' && (version_compare(phpversion(), '7.1.0', '>'))) {
                $upload_encryption = '0';
            }

            $convertformats = explode('|', $convertformats);

// Explode roles
            $viewrole = explode('|', $viewrole);
            $downloadrole = explode('|', $downloadrole);
            $sharerole = explode('|', $sharerole);
            $uploadrole = explode('|', $uploadrole);
            $deletefilesrole = explode('|', $deletefilesrole);
            $deletefoldersrole = explode('|', $deletefoldersrole);
            $renamefilesrole = explode('|', $renamefilesrole);
            $renamefoldersrole = explode('|', $renamefoldersrole);
            $movefilesrole = explode('|', $movefilesrole);
            $movefoldersrole = explode('|', $movefoldersrole);
            $editdescriptionrole = explode('|', $editdescriptionrole);
            $addfolderrole = explode('|', $addfolderrole);
            $viewuserfoldersrole = explode('|', $viewuserfoldersrole);

            $this->options = array(
                'root' => $dir,
                'class' => $class,
                'base' => $rootfolderid,
                'startid' => $startid,
                'mode' => $mode,
                'user_upload_folders' => $userfolders,
                'user_template_dir' => $usertemplatedir,
                'view_user_folders_role' => $viewuserfoldersrole,
                'user_folder_name_template' => $userfoldernametemplate,
                'media_extensions' => $mediaextensions,
                'autoplay' => $autoplay,
                'hideplaylist' => $hideplaylist,
                'covers' => $covers,
                'linktomedia' => $linktomedia,
                'linktoshop' => $linktoshop,
                'show_files' => $showfiles,
                'show_folders' => $showfolders,
                'show_filesize' => $filesize,
                'show_filedate' => $filedate,
                'max_files' => $maxfiles,
                'filelayout' => $filelayout,
                'show_columnnames' => $showcolumnnames,
                'show_ext' => $showext,
                'sort_field' => $sortfield,
                'sort_order' => $sortorder,
                'show_breadcrumb' => $showbreadcrumb,
                'can_download_zip' => $candownloadzip,
                'can_popout' => $canpopout,
                'show_sharelink' => $showsharelink,
                'show_refreshbutton' => $showrefreshbutton,
                'root_text' => $roottext,
                'search' => $search,
                'searchcontents' => $searchcontents,
                'searchfrom' => $searchfrom,
                'include' => explode('|', htmlspecialchars_decode($include)),
                'include_ext' => explode('|', strtolower($includeext)),
                'exclude' => explode('|', htmlspecialchars_decode($exclude)),
                'exclude_ext' => explode('|', strtolower($excludeext)),
                'maxwidth' => $maxwidth,
                'maxheight' => $maxheight,
                'view_role' => $viewrole,
                'download_role' => $downloadrole,
                'share_role' => $sharerole,
                'previewinline' => $previewinline,
                'forcedownload' => $forcedownload,
                'maximages' => $maximages,
                'notificationupload' => $notificationupload,
                'notificationdownload' => $notificationdownload,
                'notificationdeletion' => $notificationdeletion,
                'notificationemail' => $notificationemail,
                'notification_skip_email_currentuser' => $notification_skipemailcurrentuser,
                'upload' => $upload,
                'upload_folder' => $upload_folder,
                'upload_ext' => strtolower($uploadext),
                'upload_role' => $uploadrole,
                'upload_encryption' => $upload_encryption,
                'upload_encryption_passphrase' => $upload_encryption_passphrase,
                'maxfilesize' => $maxfilesize,
                'maxnumberofuploads' => $maxnumberofuploads,
                'convert' => $convert,
                'convert_formats' => $convertformats,
                'overwrite' => $overwrite,
                'delete' => $delete,
                'delete_files_role' => $deletefilesrole,
                'delete_folders_role' => $deletefoldersrole,
                'deletetotrash' => $deletetotrash,
                'rename' => $rename,
                'rename_files_role' => $renamefilesrole,
                'rename_folders_role' => $renamefoldersrole,
                'move' => $move,
                'move_files_role' => $movefilesrole,
                'move_folders_role' => $movefoldersrole,
                'editdescription' => $editdescription,
                'editdescription_role' => $editdescriptionrole,
                'addfolder' => $addfolder,
                'addfolder_role' => $addfolderrole,
                'quality' => $quality,
                'show_filenames' => $showfilenames,
                'targetheight' => $targetheight,
                'slideshow' => $slideshow,
                'pausetime' => $pausetime,
                'mcepopup' => $mcepopup,
                'debug' => $debug,
                'demo' => $demo,
                'expire' => strtotime('+1 weeks'),
                'listtoken' => $this->listtoken);

            $this->options = apply_filters('useyourdrive_shortcode_add_options', $this->options, $this, $atts);

            $this->update_lists();

            $this->options = apply_filters('useyourdrive_shortcode_set_options', $this->options, $this, $atts);

//Create userfolders if needed

            if (($this->options['user_upload_folders'] === 'auto')) {
                if ($this->settings['userfolder_onfirstvisit'] === 'Yes') {

                    $allusers = array();
                    $roles = $this->options['view_role'];

                    foreach ($roles as $role) {
                        $users_query = new \WP_User_Query(array(
                            'fields' => 'all_with_meta',
                            'role' => $role,
                            'orderby' => 'display_name'
                        ));
                        $results = $users_query->get_results();
                        if ($results) {
                            $allusers = array_merge($allusers, $results);
                        }
                    }

                    $userfolder = $this->get_user_folders()->create_user_folders($allusers);
                }
            }
        } else {
            $this->options = $this->lists[$this->listtoken];
            $this->options = apply_filters('useyourdrive_shortcode_set_options', $this->options, $this, $atts);
            $this->_clean_lists();
        }

        ob_start();
        $this->render_template();

        return ob_get_clean();
    }

    public function render_template() {

        /* Reload User Object for this new shortcode */
        $user = $this->get_user('reload');


        if ($this->get_user()->can_view() === false) {
            return;
        }


// Render the  template


        $dataid = ''; //(($this->options['user_upload_folders'] !== '0') && !Helpers::check_user_role($this->options['view_user_folders_role'])) ? '' : $this->options['root'];

        $colors = $this->get_setting('colors');

        if ($this->options['user_upload_folders'] === 'manual') {
            $userfolder = get_user_option('use_your_drive_linkedto');
            if (is_array($userfolder) && isset($userfolder['folderid'])) {
                $dataid = $userfolder['folderid'];
            } else {
                $defaultuserfolder = get_site_option('use_your_drive_guestlinkedto');
                if (is_array($defaultuserfolder) && isset($defaultuserfolder['folderid'])) {
                    $dataid = $defaultuserfolder['folderid'];
                } else {
                    echo "<div id='UseyourDrive' class='{$colors['style']}'>";
                    $this->load_scripts('general');
                    include(sprintf("%s/templates/noaccess.php", USEYOURDRIVE_ROOTDIR));
                    echo "</div>";
                    return;
                }
            }
        }

        $dataorgid = $dataid;
        $dataid = ($this->options['startid'] !== false) ? $this->options['startid'] : $dataid;

        $shortcode_class = ($this->options['mcepopup'] === 'shortcode' || in_array($this->options['mode'], array('audio', 'video'))) ? 'initiate' : '';

        do_action('useyourdrive_before_shortcode', $this);

        echo "<div id='UseyourDrive' class='{$colors['style']} {$this->options['class']} {$this->options['mode']} {$shortcode_class}'>";
        echo "<noscript><div class='UseyourDrive-nojsmessage'>" . __('To view the Google Drive folders, you need to have JavaScript enabled in your browser', 'useyourdrive') . ".<br/>";
        echo "<a href='http://www.enable-javascript.com/' target='_blank'>" . __('To do so, please follow these instructions', 'useyourdrive') . "</a>.</div></noscript>";

        switch ($this->options['mode']) {
            case 'files':

                $this->load_scripts('files');

                echo "<div id='UseyourDrive-$this->listtoken' class='UseyourDrive files uyd-" . $this->options['filelayout'] . " jsdisabled' data-list='files' data-token='$this->listtoken' data-id='" . $dataid . "' data-path='" . base64_encode(serialize($this->_folderPath)) . "' data-sort='" . $this->options['sort_field'] . ":" . $this->options['sort_order'] . "' data-org-id='" . $dataorgid . "' data-org-path='" . base64_encode(serialize($this->_folderPath)) . "' data-layout='" . $this->options['filelayout'] . "' data-popout='" . $this->options['can_popout'] . "'>";


                if ($this->options['mcepopup'] === 'shortcode') {
                    echo "<div class='selected-folder'><strong>" . __('Selected folder', 'useyourdrive') . ": </strong><span class='current-folder-raw'></span></div>";
                }

                if ($this->get_shortcode_option('mcepopup') === 'linkto' || $this->get_shortcode_option('mcepopup') === 'linktobackendglobal') {
                    $rootfolder = $this->get_client()->get_root_folder();
                    $button_text = __('Use the Root Folder of your Account', 'useyourdrive');

                    if ($rootfolder->get_id() !== 'drive') {
                        echo '<div data-id="' . $rootfolder->get_id() . '" data-name="' . $rootfolder->get_name() . '">';
                        echo '<div class="entry_linkto entry_linkto_root">';
                        echo '<span><input class="button-secondary" type="submit" title="' . $button_text . '" value="' . $button_text . '"></span>';
                        echo "</div>";
                        echo "</div>";
                    }
                }

                include(sprintf("%s/templates/frontend.php", USEYOURDRIVE_ROOTDIR));
                $this->render_uploadform();

                echo "</div>";
                break;

            case 'upload':

                echo "<div id='UseyourDrive-$this->listtoken' class='UseyourDrive upload jsdisabled'  data-token='$this->listtoken' data-id='" . $dataid . "' data-path='" . base64_encode(serialize($this->_folderPath)) . "' >";
                $this->render_uploadform();
                echo "</div>";
                break;


            case 'gallery':

                $this->load_scripts('files');

                $nextimages = '';
                if (($this->options['maximages'] !== '0')) {
                    $nextimages = "data-loadimages='" . $this->options['maximages'] . "'";
                }

                echo "<div id='UseyourDrive-$this->listtoken' class='UseyourDrive gridgallery jsdisabled' data-list='gallery' data-token='$this->listtoken' data-id='" . $dataid . "' data-path='" . base64_encode(serialize($this->_folderPath)) . "' data-sort='" . $this->options['sort_field'] . ":" . $this->options['sort_order'] . "' data-org-id='" . $dataid . "' data-org-path='" . base64_encode(serialize($this->_folderPath)) . "' data-targetheight='" . $this->options['targetheight'] . "' data-slideshow='" . $this->options['slideshow'] . "' data-pausetime='" . $this->options['pausetime'] . "' $nextimages>";
                include(sprintf("%s/templates/gallery.php", USEYOURDRIVE_ROOTDIR));
                $this->render_uploadform();
                echo "</div>";
                break;

            case 'search':
                echo "<div id='UseyourDrive-$this->listtoken' class='UseyourDrive files uyd-" . $this->options['filelayout'] . " searchlist jsdisabled' data-list='search' data-token='$this->listtoken' data-id='" . $dataid . "' data-path='" . base64_encode(serialize($this->_folderPath)) . "' data-sort='" . $this->options['sort_field'] . ":" . $this->options['sort_order'] . "' data-org-id='" . $dataorgid . "' data-org-path='" . base64_encode(serialize($this->_folderPath)) . "' data-layout='" . $this->options['filelayout'] . "' data-popout='" . $this->options['can_popout'] . "'>";
                $this->load_scripts('files');
                include(sprintf("%s/templates/search.php", USEYOURDRIVE_ROOTDIR));
                echo "</div>";
                break;

            case 'video':
            case 'audio':
                $skin = $this->settings['mediaplayer_skin'];
                $mp4key = array_search('mp4', $this->options['media_extensions']);
                if ($mp4key !== false) {
                    unset($this->options['media_extensions'][$mp4key]);
                    if ($this->options['mode'] === 'video') {
                        if (!in_array('m4v', $this->options['media_extensions'])) {
                            $this->options['media_extensions'][] = 'm4v';
                        }
                    } else {
                        if (!in_array('m4a', $this->options['media_extensions'])) {
                            $this->options['media_extensions'][] = 'm4a';
                        }
                    }
                }

                $oggkey = array_search('ogg', $this->options['media_extensions']);
                if ($oggkey !== false) {
                    unset($this->options['media_extensions'][$oggkey]);
                    if ($this->options['mode'] === 'video') {
                        if (!in_array('ogv', $this->options['media_extensions'])) {
                            $this->options['media_extensions'][] = 'ogv';
                        }
                    } else {
                        if (!in_array('oga', $this->options['media_extensions'])) {
                            $this->options['media_extensions'][] = 'oga';
                        }
                    }
                }

                $extensions = join(',', $this->options['media_extensions']);
                $coverclass = 'nocover';
                if ($this->options['mode'] === 'audio' && $this->options['covers'] === '1') {
                    $coverclass = 'cover';
                }

                $this->load_scripts('mediaplayer');

                if ($extensions !== '') {
                    echo "<div id='UseyourDrive-$this->listtoken' class='UseyourDrive media " . $this->options['mode'] . " $coverclass jsdisabled' data-list='media' data-token='$this->listtoken' data-extensions='" . $extensions . "' data-id='" . $dataid . "' data-sort='" . $this->options['sort_field'] . ":" . $this->options['sort_order'] . "' data-autoplay='" . $this->options['autoplay'] . "'>";
                    include(sprintf("%s/skins/%s/player.php", USEYOURDRIVE_ROOTDIR, $skin));
                    echo "</div>";
                } else {
                    echo '<strong>Use-your-Drive:</strong>' . __('Please update your mediaplayer shortcode', 'useyourdrive');
                }

                break;
        }

        echo "<script type='text/javascript'>if (typeof(jQuery) !== 'undefined' && typeof(jQuery.cp) !== 'undefined' && typeof(jQuery.cp.UseyourDrive) === 'function') { jQuery('#UseyourDrive-$this->listtoken').UseyourDrive(UseyourDrive_vars); };</script>";
        echo "</div>";

        do_action('useyourdrive_after_shortcode', $this);

        $this->load_scripts('general');
    }

    public function render_uploadform() {
        $user_can_upload = $this->get_user()->can_upload();

        if ($user_can_upload === false) {
            return;
        }

        $own_limit = ($this->options['maxfilesize'] !== '0');
        $post_max_size_bytes = min(Helpers::return_bytes(ini_get('post_max_size')), Helpers::return_bytes(ini_get('upload_max_filesize')));
        $max_file_size = ($this->options['maxfilesize'] !== '0') ? Helpers::return_bytes($this->options['maxfilesize']) : ($post_max_size_bytes);
        $post_max_size_str = Helpers::bytes_to_size_1024($max_file_size);
        $acceptfiletypes = '.(' . $this->options['upload_ext'] . ')$';
        $max_number_of_uploads = $this->options['maxnumberofuploads'];
        $upload_encryption = ($this->options['upload_encryption'] === '1' && (version_compare(phpversion(), '7.1.0', '<=')));

        $this->load_scripts('upload');
        include(sprintf("%s/templates/uploadform.php", USEYOURDRIVE_ROOTDIR));
    }

    public function get_last_folder() {
        return $this->_lastFolder;
    }

    public function get_last_path() {
        return $this->_lastPath;
    }

    protected function set_last_path($path) {
        $this->_lastPath = $path;
        if ($this->_lastPath === '') {
            $this->_lastPath = null;
        }
        return $this->_lastPath;
    }

    public function get_root_folder() {
        return $this->_rootFolder;
    }

    public function get_folder_path() {
        return $this->_folderPath;
    }

    public function get_listtoken() {
        return $this->listtoken;
    }

    protected function load_scripts($template) {
        if ($this->_loadscripts[$template] === true) {
            return false;
        }

        switch ($template) {
            case 'general':
                wp_enqueue_style('UseyourDrive');
                wp_enqueue_script('UseyourDrive');
                break;
            case 'files':
                wp_enqueue_style('qtip');

                if ($this->get_user()->can_move_files() || $this->get_user()->can_move_folders()) {
                    wp_enqueue_script('jquery-ui-droppable');
                    wp_enqueue_script('jquery-ui-draggable');
                }

                wp_enqueue_script('jquery-effects-core');
                wp_enqueue_script('jquery-effects-fade');
                wp_enqueue_style('ilightbox');
                wp_enqueue_style('ilightbox-skin-useyourdrive');
                break;
            case 'mediaplayer':
                wp_enqueue_style('UseyourDrive.Media');
                wp_enqueue_script('jQuery.jplayer');
                wp_enqueue_script('jQuery.jplayer.playlist');
                wp_enqueue_script('UseyourDrive.Media');
                break;
            case 'upload':
                wp_enqueue_script('jquery-ui-droppable');
                wp_enqueue_script('jquery-ui-button');
                wp_enqueue_script('jquery-ui-progressbar');
                wp_enqueue_script('jQuery.iframe-transport');
                wp_enqueue_script('jQuery.fileupload-uyd');
                wp_enqueue_script('jQuery.fileupload-process');
                break;
        }

        $this->_loadscripts[$template] = true;
    }

    protected function remove_deprecated_options($options = array()) {
        /* Deprecated Shuffle, v1.3 */
        if (isset($options['shuffle'])) {
            unset($options['shuffle']);
            $options['sortfield'] = 'shuffle';
        }
        /* Changed Userfolders, v1.3 */
        if (isset($options['userfolders']) && $options['userfolders'] === '1') {
            $options['userfolders'] = 'auto';
        }

        if (isset($options['partiallastrow'])) {
            unset($options['partiallastrow']);
        }

        /* Changed Rename/Delete/Move Folders & Files v1.5.2 */
        if (isset($options['move_role'])) {
            $options['move_files_role'] = $options['move_role'];
            $options['move_folders_role'] = $options['move_role'];
            unset($options['move_role']);
        }

        if (isset($options['rename_role'])) {
            $options['rename_files_role'] = $options['rename_role'];
            $options['rename_folders_role'] = $options['rename_role'];
            unset($options['rename_role']);
        }

        if (isset($options['delete_role'])) {
            $options['delete_files_role'] = $options['delete_role'];
            $options['delete_folders_role'] = $options['delete_role'];
            unset($options['delete_role']);
        }

        /* Changed 'ext' to 'include_ext' v1.5.2 */
        if (isset($options['ext'])) {
            $options['include_ext'] = $options['ext'];
            unset($options['ext']);
        }

        if (isset($options['maxfiles']) && empty($options['maxfiles'])) {
            unset($options['maxfiles']);
        }

        /* Convert bytes in version before 1.8 to MB */
        if (isset($options['maxfilesize']) && !empty($options['maxfilesize']) && ctype_digit($options['maxfilesize'])) {
            $options['maxfilesize'] = Helpers::bytes_to_size_1024($options['maxfilesize']);
        }


        return $options;
    }

    protected function update_lists() {


        $this->lists[$this->listtoken] = $this->options;
        $this->_clean_lists();
        update_option('use_your_drive_lists', $this->lists);
    }

    public function sort_filelist($foldercontents) {

        $sort_field = 'name';
        $sort_order = SORT_ASC;

        if (count($foldercontents) > 0) {
// Sort Filelist, folders first
            $sort = array();

            if (isset($_REQUEST['sort'])) {
                $sort_options = explode(':', $_REQUEST['sort']);

                if ($sort_options[0] === 'shuffle') {
                    shuffle($foldercontents);
                    return $foldercontents;
                }

                if (count($sort_options) === 2) {

                    switch ($sort_options[0]) {
                        case 'name':
                            $sort_field = 'name';
                            break;
                        case 'size':
                            $sort_field = 'size';
                            break;
                        case 'modified':
                            $sort_field = 'last_edited';
                            break;
                        case 'created':
                            $sort_field = 'created_time';
                            break;
                    }

                    switch ($sort_options[1]) {
                        case 'asc':
                            $sort_order = SORT_ASC;
                            break;
                        case 'desc':
                            $sort_order = SORT_DESC;
                            break;
                    }
                }
            }

            list($sort_field, $sort_order) = apply_filters('useyourdrive_sort_filelist_settings', array($sort_field, $sort_order), $foldercontents, $this);

            foreach ($foldercontents as $k => $v) {
                if ($v instanceof EntryAbstract) {
                    $sort['is_dir'][$k] = $v->is_dir();
                    $sort['sort'][$k] = strtolower($v->{'get_' . $sort_field}());
                } else {
                    $sort['is_dir'][$k] = $v['is_dir'];
                    $sort['sort'][$k] = $v[$sort_field];
                }
            }

            /* Sort by dir desc and then by name asc */
            if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                array_multisort($sort['is_dir'], SORT_DESC, SORT_REGULAR, $sort['sort'], $sort_order, SORT_NATURAL, $foldercontents, SORT_ASC, SORT_NATURAL);
            } else {
                array_multisort($sort['is_dir'], SORT_DESC, $sort['sort'], $sort_order, $foldercontents);
            }
        }

        $foldercontents = apply_filters('useyourdrive_sort_filelist', $foldercontents, $sort_field, $sort_order, $this);

        return $foldercontents;
    }

    public function send_notification_email($emailtype = false, $entries = array()) {

        if ($emailtype === false) {
            return;
        }

        /* Current site url */
        $currenturl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        /* Root Folder of the current shortcode */
        $rootfolder = $this->get_client()->get_entry($this->_rootFolder, false);

        /* Vistor name and email */
        $visitor = __('A guest', 'useyourdrive');
        $visitor_email = '';
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $visitor = $current_user->display_name;
            $visitor_email = $current_user->user_email;
        }

        /* Set $linked_user_email for Manually linked folders */
        $linked_user_email = '';
        if ($this->options['user_upload_folders'] === 'manual') {
            /* Folder of the current call */
            if (count($entries) > 0) {
                $current_folder = $entries[0];
            } else {
                $current_folder = $this->get_client()->get_entry($this->get_requested_entry(), false);
            }


            if ($current_folder !== false) {

                global $wpdb;

                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => $wpdb->prefix . 'use_your_drive_linkedto',
                        'value' => '"' . $current_folder->get_id() . '"',
                        'compare' => 'LIKE'
                    )
                );

                $all_parent_folders = $current_folder->get_all_parent_folders();
                if (count($all_parent_folders) > 0) {
                    foreach ($all_parent_folders as $parent_folder) {
                        $meta_query[] = array(
                            'key' => $wpdb->prefix . 'use_your_drive_linkedto',
                            'value' => '"' . $parent_folder->get_id() . '"',
                            'compare' => 'LIKE'
                        );
                    };
                }

                $linked_users = (get_users(array('meta_query' => $meta_query)));
                $linked_users_emails = array();

                if (count($linked_users) > 0) {
                    foreach ($linked_users as $linked_user) {
                        $linked_users_emails[] = $linked_user->user_email;
                    }
                }

                $linked_user_email = implode(',', $linked_users_emails);
            }
        }

        $ip = $this->userip;

        /* Geo location if required */
        $location = Helpers::get_user_location($ip);

        /* Create FileList */
        $_filelisttemplate = trim($this->settings['filelist_template']);
        $filelist = '';
        foreach ($entries as $cachedentry) {
            $entry = $cachedentry->get_entry();
            $filename = $entry->get_name();
            $filepath = $cachedentry->get_path($this->_rootFolder);
            $direct_download_link = $entry->get_direct_download_link();

            $fileline = strtr($_filelisttemplate, array(
                "%filename%" => $filename,
                "%filesize%" => Helpers::bytes_to_size_1024($entry->get_size()),
                "%fileurl%" => (!empty($direct_download_link) ? $entry->get_direct_download_link() : $entry->get_preview_link()),
                "%filepath%" => $filepath
            ));
            $filelist .= $fileline;
        }

        /* Create Message */
        switch ($emailtype) {
            case 'download':
                if (count($entries) === 1) {
                    $subject = trim($this->settings['download_template_subject']);
                } else {
                    $subject = trim($this->settings['download_template_subject_zip']);
                }
                $message = trim($this->settings['download_template']);
                break;
            case 'upload':
                $subject = trim($this->settings['upload_template_subject']);
                $message = trim($this->settings['upload_template']);
                break;
            case 'deletion':
            case 'deletion_multiple':
                $subject = trim($this->settings['delete_template_subject']);
                $message = trim($this->settings['delete_template']);
                break;
        }

        /* Replace filters */
        /* Get emailaddress */
        $recipients = strtr(trim($this->options['notificationemail']), array(
            "%admin_email%" => get_site_option('admin_email'),
            "%user_email%" => $visitor_email,
            "%linked_user_email%" => $linked_user_email
        ));

        $subject = strtr($subject, array(
            "%sitename%" => get_bloginfo(),
            "%number_of_files%" => count($entries),
            "%visitor%" => $visitor,
            "%user_email%" => $visitor_email,
            "%linked_user_email%" => $linked_user_email,
            "%ip%" => $ip,
            "%location%" => $location,
            "%filename%" => $filename,
            "%filepath%" => $filepath,
            '%folder%' => $rootfolder->get_entry()->get_name()
        ));

        $message = strtr($message, array(
            "%visitor%" => $visitor,
            "%user_email%" => $visitor_email,
            "%currenturl%" => $currenturl,
            "%filelist%" => $filelist,
            "%ip%" => $ip,
            "%location%" => $location
        ));

        $recipients = array_unique(array_map('trim', explode(',', $recipients)));

        if ($this->options['notification_skip_email_currentuser'] === '1' && is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $current_user_email = $current_user->user_email;
            $recipients = array_diff($recipients, array($current_user_email));
        }

        /* Create Notifaction variable for hook */
        $notification = array(
            'type' => $emailtype,
            'recipients' => $recipients,
            'subject' => $subject,
            'message' => $message,
            'files' => $entries
        );

        /* Executes hook */
        $notification = apply_filters('useyourdrive_notification', $notification);

        /* Send mail */
        try {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $htmlmessage = nl2br($notification['message']);

            foreach ($notification['recipients'] as $recipient) {
                $result = wp_mail($recipient, $notification['subject'], $htmlmessage, $headers);
            }
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Could not send notification email on line %s: %s', __LINE__, $ex->getMessage()));
        }
    }

    private function _clean_lists() {
        $now = time();
        foreach ($this->lists as $token => $list) {

            if (!isset($list['expire']) || ($list['expire']) < $now) {
                unset($this->lists[$token]);
            }
        }
    }

    private function _is_action_authorized($hook = false) {
        $nonce_verification = ($this->get_setting('nonce_validation') === 'Yes');
        $allow_nonce_verification = apply_filters("use_your_drive_allow_nonce_verification", $nonce_verification);

        if ($allow_nonce_verification && isset($_REQUEST['action']) && ($hook === false) && is_user_logged_in()) {

            $is_authorized = false;

            switch ($_REQUEST['action']) {
                case 'useyourdrive-upload-file':
                case 'useyourdrive-get-filelist':
                case 'useyourdrive-get-gallery':
                case 'useyourdrive-get-playlist':
                case 'useyourdrive-rename-entry':
                case 'useyourdrive-move-entry':
                case 'useyourdrive-edit-description-entry':
                case 'useyourdrive-add-folder':
                case 'useyourdrive-create-zip':
                    $is_authorized = check_ajax_referer($_REQUEST['action'], false, false);
                    break;
                case 'useyourdrive-delete-entry':
                case 'useyourdrive-delete-entries':
                    $is_authorized = check_ajax_referer('useyourdrive-delete-entry', false, false);
                    break;
                case 'useyourdrive-create-link':
                case 'useyourdrive-embedded':
                    $is_authorized = check_ajax_referer('useyourdrive-create-link', false, false);
                    break;
                case 'useyourdrive-reset-cache':
                case 'useyourdrive-revoke':
                    $is_authorized = check_ajax_referer('useyourdrive-admin-action', false, false);
                    break;
                case 'useyourdrive-download':
                case 'useyourdrive-stream':
                case 'useyourdrive-preview':
                case 'useyourdrive-thumbnail':
                case 'useyourdrive-getpopup':
                    $is_authorized = true;
                    break;

                case 'edit': // Required for integration one Page/Post pages
                    $is_authorized = true;
                    break;
                case 'editpost': // Required for Yoast SEO Link Watcher trying to build the shortcode
                case 'wpseo_filter_shortcodes':
                    return false;
                default:
                    error_log('[Use-your-Drive message]: ' . " Function _is_action_authorized() didn't receive a valid action: " . $_REQUEST['action']);
                    die();
            }

            if ($is_authorized === false) {
                error_log('[Use-your-Drive message]: ' . " Function _is_action_authorized() didn't receive a valid nonce");
                die();
            }
        }

        if (!$this->get_app()->has_access_token()) {
            error_log('[Use-your-Drive message]: ' . " Function _is_action_authorized() discovered that the plugin doesn't have an access token");
            return new \WP_Error('broke', '<strong>' . __("Use-your-Drive needs your help!", 'useyourdrive') . '</strong> ' . __('Authorize the plugin.', 'useyourdrive') . '.');
        }

        $this->get_client();

        return true;
    }

    /*
     * Check if $entry is allowed
     */

    public function _is_entry_authorized(CacheNode $cachedentry) {
        $entry = $cachedentry->get_entry();

        if (empty($entry)) {
            return false;
        }
        /* Return in case a direct call is being made, and no shortcode is involved */
        if (empty($this->options)) {
            return true;
        }

        /* Skip entry if its a file, and we dont want to show files */
        if (($entry->is_file()) && ($this->get_shortcode_option('show_files') === '0')) {
            return false;
        }
        /* Skip entry if its a folder, and we dont want to show folders */
        if (($entry->is_dir()) && ($this->get_shortcode_option('show_folders') === '0') && ($entry->get_id() !== $this->get_requested_entry())) {
            return false;
        }

        /* Only add allowed files to array */
        $extension = $entry->get_extension();
        $allowed_extensions = $this->get_shortcode_option('include_ext');
        if (($entry->is_file()) && (!in_array(strtolower($extension), $allowed_extensions)) && $allowed_extensions[0] != '*') {
            return false;
        }

        /* Hide files with extensions */
        $hide_extensions = $this->get_shortcode_option('exclude_ext');
        if (($entry->is_file()) && !empty($extension) && (in_array(strtolower($extension), $hide_extensions)) && $hide_extensions[0] != '*') {
            return false;
        }

        /* skip excluded folders and files */
        $hide_entries = $this->get_shortcode_option('exclude');
        if ($hide_entries[0] != '*') {
            if (in_array($entry->get_name(), $hide_entries)) {
                return false;
            }
            if (in_array($entry->get_id(), $hide_entries)) {
                return false;
            }
        }

        /* only allow included folders and files */
        $include_entries = $this->get_shortcode_option('include');
        if ($include_entries[0] != '*') {
            if ($entry->is_dir() && ($entry->get_id() === $this->get_requested_entry())) {
                
            } else {
                if (in_array($entry->get_name(), $include_entries) || in_array($entry->get_id(), $include_entries)) {
                    
                } else {
                    return false;
                }
            }
        }

        /* Make sure that files and folders from hidden folders are not allowed */
        if ($hide_entries[0] != '*') {
            foreach ($hide_entries as $hidden_entry) {
                $cached_hidden_entry = $this->get_cache()->get_node_by_name($hidden_entry);

                if ($cached_hidden_entry === false) {
                    $cached_hidden_entry = $this->get_cache()->get_node_by_id($hidden_entry);
                }

                if ($cached_hidden_entry !== false && $cached_hidden_entry->get_entry()->is_dir()) {
                    if ($cachedentry->is_in_folder($cached_hidden_entry->get_id())) {
                        return false;
                    }
                }
            }
        }

        /* If only showing Shared Files */
        /* if (1) {
          if ($entry->is_file()) {
          if (!$entry->getShared() && $entry->getOwnedByMe()) {
          return false;
          }
          }
          } */

        /* Is file in the selected root Folder? */
        if (!$cachedentry->is_in_folder($this->get_root_folder())) {
            return false;
        }
        return true;
    }

    public function embed_image($entryid) {
        $cachedentry = $this->get_client()->get_entry($entryid, false);

        if ($cachedentry === false) {
            return false;
        }

        if (in_array($cachedentry->get_entry()->get_extension(), array('jpg', 'jpeg', 'gif', 'png'))) {
            $download = $this->get_client()->download_content($cachedentry);
        }


        return true;
    }

    public function set_requested_entry($entry_id) {
        return $this->_requestedEntry = $entry_id;
    }

    public function get_requested_entry() {
        return $this->_requestedEntry;
    }

    public function get_import_formats() {

        $importFormats = array(
            "application/x-vnd.oasis.opendocument.presentation" =>
            "application/vnd.google-apps.presentation"
            ,
            "text/tab-separated-values" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "image/jpeg" =>
            "application/vnd.google-apps.document"
            ,
            "image/bmp" =>
            "application/vnd.google-apps.document"
            ,
            "image/gif" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.ms-excel.sheet.macroenabled.12" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "application/vnd.openxmlformats-officedocument.wordprocessingml.template" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.ms-powerpoint.presentation.macroenabled.12" =>
            "application/vnd.google-apps.presentation"
            ,
            "application/vnd.ms-word.template.macroenabled.12" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" =>
            "application/vnd.google-apps.document"
            ,
            "image/pjpeg" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.google-apps.script+text/plain" =>
            "application/vnd.google-apps.script"
            ,
            "application/vnd.ms-excel" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "application/vnd.sun.xml.writer" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.ms-word.document.macroenabled.12" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.ms-powerpoint.slideshow.macroenabled.12" =>
            "application/vnd.google-apps.presentation"
            ,
            "text/rtf" =>
            "application/vnd.google-apps.document"
            ,
            "text/plain" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.oasis.opendocument.spreadsheet" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "application/x-vnd.oasis.opendocument.spreadsheet" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "image/png" =>
            "application/vnd.google-apps.document"
            ,
            "application/x-vnd.oasis.opendocument.text" =>
            "application/vnd.google-apps.document"
            ,
            "application/msword" =>
            "application/vnd.google-apps.document"
            ,
            "application/pdf" =>
            "application/vnd.google-apps.document"
            ,
            "application/json" =>
            "application/vnd.google-apps.script"
            ,
            "application/x-msmetafile" =>
            "application/vnd.google-apps.drawing"
            ,
            "application/vnd.openxmlformats-officedocument.spreadsheetml.template" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "application/vnd.ms-powerpoint" =>
            "application/vnd.google-apps.presentation"
            ,
            "application/vnd.ms-excel.template.macroenabled.12" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "image/x-bmp" =>
            "application/vnd.google-apps.document"
            ,
            "application/rtf" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.openxmlformats-officedocument.presentationml.template" =>
            "application/vnd.google-apps.presentation"
            ,
            "image/x-png" =>
            "application/vnd.google-apps.document"
            ,
            "text/html" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.oasis.opendocument.text" =>
            "application/vnd.google-apps.document"
            ,
            "application/vnd.openxmlformats-officedocument.presentationml.presentation" =>
            "application/vnd.google-apps.presentation"
            ,
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "application/vnd.google-apps.script+json" =>
            "application/vnd.google-apps.script"
            ,
            "application/vnd.openxmlformats-officedocument.presentationml.slideshow" =>
            "application/vnd.google-apps.presentation"
            ,
            "application/vnd.ms-powerpoint.template.macroenabled.12" =>
            "application/vnd.google-apps.presentation"
            ,
            "text/csv" =>
            "application/vnd.google-apps.spreadsheet"
            ,
            "application/vnd.oasis.opendocument.presentation" =>
            "application/vnd.google-apps.presentation"
            ,
            "image/jpg" =>
            "application/vnd.google-apps.document"
            ,
            "text/richtext" =>
            "application/vnd.google-apps.document"
        );

        return $importFormats;
    }

    public function is_mobile() {
        return $this->mobile;
    }

    public function get_setting($key) {
        if (!isset($this->settings[$key])) {
            return null;
        }

        return $this->settings[$key];
    }

    public function set_setting($key, $value) {
        $this->settings[$key] = $value;
        $success = update_option('use_your_drive_settings', $this->settings);
        $this->settings = get_option('use_your_drive_settings');
        return $success;
    }

    public function get_shortcode() {
        return $this->options;
    }

    public function get_shortcode_option($key) {
        if (!isset($this->options[$key])) {
            return null;
        }
        return $this->options[$key];
    }

    public function set_shortcode($listtoken) {

        if (isset($this->lists[$listtoken])) {
            $this->options = $this->lists[$listtoken];
            $this->listtoken = $listtoken;
        }

        return $this->options;
    }

    public function _set_gzip_compression() {
        /* Compress file list if possible */
        if ($this->settings['gzipcompression'] === 'Yes') {
            $zlib = (ini_get('zlib.output_compression') == '' || !ini_get('zlib.output_compression')) && (ini_get('output_handler') != 'ob_gzhandler');
            if ($zlib === true) {
                if (extension_loaded('zlib')) {
                    if (!in_array('ob_gzhandler', ob_list_handlers())) {
                        ob_start('ob_gzhandler');
                    }
                }
            }
        }
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
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app() {
        if (empty($this->_app)) {
            $this->_app = new \TheLion\UseyourDrive\App($this);
            $this->_app->start_client();
        }

        return $this->_app;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Client
     */
    public function get_client() {
        if (empty($this->_client)) {
            $this->_client = new \TheLion\UseyourDrive\Client($this->get_app(), $this);
        }

        return $this->_client;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Cache
     */
    public function get_cache() {
        if (empty($this->_cache)) {
            $this->_cache = new \TheLion\UseyourDrive\Cache($this);
        }

        return $this->_cache;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\User
     */
    public function get_user($force_reload = false) {
        if (empty($this->_user) || $force_reload) {
            $this->_user = new \TheLion\UseyourDrive\User($this);
        }

        return $this->_user;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\UserFolders
     */
    public function get_user_folders() {
        if (empty($this->_userfolders)) {
            $this->_userfolders = new \TheLion\UseyourDrive\UserFolders($this);
        }

        return $this->_userfolders;
    }

    public function get_user_ip() {
        return $this->userip;
    }

    public function reset_complete_cache() {
        update_option('use_your_drive_lists', array());
        update_option('use_your_drive_cache', array(
            'last_update' => null,
            'last_cache_id' => '',
            'locked' => false,
            'cache' => ''
        ));

        if (!file_exists(USEYOURDRIVE_CACHEDIR)) {
            return false;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {

            if ($path->isDir()) {
                continue;
            }
            if ($path->getFilename() === '.htaccess') {
                continue;
            }

            if ($path->getExtension() === 'access_token') {
                continue;
            }

            try {
                @unlink($path->getPathname());
            } catch (\Exception $ex) {
                continue;
            }
        }
        return true;
    }

    public function do_shutdown() {
        $error = error_get_last();
        if ($error['type'] === E_ERROR) {
            // fatal error has occured
            $this->get_cache()->reset_cache();
        }
    }

}
