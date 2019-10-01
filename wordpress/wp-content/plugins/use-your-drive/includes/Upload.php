<?php

namespace TheLion\UseyourDrive;

class Upload {

    /**
     *
     * @var \TheLion\UseyourDrive\Client 
     */
    private $_client;

    /**
     *
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;

    /**
     *
     * @var WPC_UploadHandler 
     */
    private $_upload_handler;

    public function __construct(\TheLion\UseyourDrive\Processor $_processor = null) {
        $this->_client = $_processor->get_client();
        $this->_processor = $_processor;

        /* Upload File to server */
        if (!class_exists('WPC_UploadHandler')) {
            require('jquery-file-upload/server/UploadHandler.php');
        }
    }

    public function do_upload() {

        if ($this->get_processor()->get_shortcode_option('demo') === '1') {
            /* TO DO LOG + FAIL ERROR */
            die(-1);
        }

        $shortcode_max_file_size = $this->get_processor()->get_shortcode_option('maxfilesize');
        $accept_file_types = '/.(' . $this->get_processor()->get_shortcode_option('upload_ext') . ')$/i';
        $post_max_size_bytes = min(Helpers::return_bytes(ini_get('post_max_size')), Helpers::return_bytes(ini_get('upload_max_filesize')));
        $max_file_size = ($shortcode_max_file_size !== '0') ? Helpers::return_bytes($shortcode_max_file_size) : $post_max_size_bytes;
        $use_upload_encryption = ($this->get_processor()->get_shortcode_option('upload_encryption') === '1' && (version_compare(phpversion(), '7.1.0', '<=')));

        $options = array(
            'access_control_allow_methods' => array('POST', 'PUT'),
            'accept_file_types' => $accept_file_types,
            'inline_file_types' => '/\.____$/i',
            'orient_image' => false,
            'image_versions' => array(),
            'max_file_size' => $max_file_size,
            'print_response' => false
        );

        $error_messages = array(
            1 => __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'useyourdrive'),
            2 => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'useyourdrive'),
            3 => __('The uploaded file was only partially uploaded', 'useyourdrive'),
            4 => __('No file was uploaded', 'useyourdrive'),
            6 => __('Missing a temporary folder', 'useyourdrive'),
            7 => __('Failed to write file to disk', 'useyourdrive'),
            8 => __('A PHP extension stopped the file upload', 'useyourdrive'),
            'post_max_size' => __('The uploaded file exceeds the post_max_size directive in php.ini', 'useyourdrive'),
            'max_file_size' => __('File is too big', 'useyourdrive'),
            'min_file_size' => __('File is too small', 'useyourdrive'),
            'accept_file_types' => __('Filetype not allowed', 'useyourdrive'),
            'max_number_of_files' => __('Maximum number of files exceeded', 'useyourdrive'),
            'max_width' => __('Image exceeds maximum width', 'useyourdrive'),
            'min_width' => __('Image requires a minimum width', 'useyourdrive'),
            'max_height' => __('Image exceeds maximum height', 'useyourdrive'),
            'min_height' => __('Image requires a minimum height', 'useyourdrive')
        );

        $this->upload_handler = new \WPC_UploadHandler($options, false, $error_messages);
        $response = @$this->upload_handler->post(false);

        /* Upload files to Google */
        foreach ($response['files'] as &$file) {
            /* Set return Object */
            $file->listtoken = $this->get_processor()->get_listtoken();
            $file->hash = $_POST['hash'];

            /* Set Progress */
            $return = array('file' => $file, 'status' => array('bytes_up_so_far' => 0, 'total_bytes_up_expected' => $file->size, 'percentage' => 0, 'progress' => 'starting'));
            self::set_upload_progress($file->hash, $return);

            if (isset($file->error)) {
                $file->error = __('Uploading failed', 'useyourdrive') . ': ' . $file->error;
                $return['file'] = $file;
                $return['status']['progress'] = 'failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[Use-your-Drive message]: ' . sprintf('Uploading failed: %s', $file->error));
                die();
            }

            if ($use_upload_encryption) {
                $return['status']['progress'] = 'encrypting';
                self::set_upload_progress($file->hash, $return);


                $result = $this->do_encryption($file);

                if ($result) {
                    $file->name .= '.aes';
                    clearstatcache();
                    $file->size = filesize($file->tmp_path);
                }
            }
            
            /* Write file */
            $chunkSizeBytes = 1 * 1024 * 1024;

            /* Update Mime-type if needed (for IE8 and lower?) */
            include_once 'mime-types/mime-types.php';
            $fileExtension = pathinfo($file->name, PATHINFO_EXTENSION);
            $file->type = UseyourDrive_getMimeType($fileExtension);

            /* Overwrite if needed */
            $current_entry_id = false;
            if ($this->get_processor()->get_shortcode_option('overwrite') === '1') {
                $parent_folder = $this->get_client()->get_folder($this->get_processor()->get_last_folder());
                $current_entry = $this->get_client()->get_cache()->get_node_by_name($file->name, $parent_folder['folder']);

                if (!empty($current_entry)) {
                    $current_entry_id = $current_entry->get_id();
                }
            }

            /* Create new Google File */
            $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
            $googledrive_file->setName($file->name);
            $googledrive_file->setMimeType($file->type);

            /* Convert file if needed */
            $file->convert = false;
            if ($this->get_processor()->get_shortcode_option('convert') === '1') {
                $importformats = $this->get_processor()->get_import_formats();
                $convertformats = $this->get_processor()->get_shortcode_option('convert_formats');
                if ($convertformats[0] === 'all' || in_array($file->type, $convertformats)) {
                    if (isset($importformats[$file->type])) {
                        $file->convert = $importformats[$file->type];
                        $filename = pathinfo($file->name, PATHINFO_FILENAME);
                        $googledrive_file->setName($filename);
                    }
                }
            }

            /* Call the API with the media upload, defer so it doesn't immediately return. */
            $this->get_app()->get_client()->setDefer(true);

            try {
                if ($current_entry_id === false) {
                    $googledrive_file->setParents(array($this->get_processor()->get_last_folder()));
                    $request = $this->get_app()->get_drive()->files->create($googledrive_file, array("userIp" => $this->get_processor()->get_user_ip(), 'supportsTeamDrives' => true));
                } else {
                    $request = $this->get_app()->get_drive()->files->update($current_entry_id, $googledrive_file, array("userIp" => $this->get_processor()->get_user_ip(), 'supportsTeamDrives' => true));
                }
            } catch (\Exception $ex) {
                $file->error = __('Not uploaded to Google Drive', 'useyourdrive') . ': ' . $ex->getMessage();
                $return['status']['progress'] = 'failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[Use-your-Drive message]: ' . sprintf('Not uploaded to Google Drive: %s', $ex->getMessage()));
                die();
            }

            /* Create a media file upload to represent our upload process. */
            $media = new \UYDGoogle_Http_MediaFileUpload(
                    $this->get_app()->get_client(), $request, $file->type, null, true, $chunkSizeBytes
            );

            $filesize = filesize($file->tmp_path);
            $media->setFileSize($filesize);


            /* Start partialy upload 
              Upload the various chunks. $status will be false until the process is
              complete. */
            try {
                $upload_status = false;
                $bytesup = 0;
                $handle = fopen($file->tmp_path, "rb");
                while (!$upload_status && !feof($handle)) {
                    @set_time_limit(60);
                    $chunk = fread($handle, $chunkSizeBytes);
                    $upload_status = $media->nextChunk($chunk);
                    $bytesup += $chunkSizeBytes;

                    /* Update progress */
                    /* Update the progress */
                    $status = array(
                        'bytes_up_so_far' => $bytesup,
                        'total_bytes_up_expected' => $file->size,
                        'percentage' => ( round(($bytesup / $file->size) * 100) ),
                        'progress' => 'uploading'
                    );

                    $current = self::get_upload_progress($file->hash);
                    $current['status'] = $status;
                    self::set_upload_progress($file->hash, $current);
                }

                fclose($handle);
            } catch (\Exception $ex) {
                $file->error = __('Not uploaded to Google Drive', 'useyourdrive') . ': ' . $ex->getMessage();
                $return['file'] = $file;
                $return['status']['progress'] = 'failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[Use-your-Drive message]: ' . sprintf('Not uploaded to Google Drive: %s', $ex->getMessage()));
                die();
            }

            $this->get_app()->get_client()->setDefer(false);

            if (empty($upload_status)) {
                $file->error = __('Not uploaded to Google Drive', 'useyourdrive');
                $return['file'] = $file;
                $return['status']['progress'] = 'failed';
                self::set_upload_progress($file->hash, $return);
                echo json_encode($return);

                error_log('[Use-your-Drive message]: ' . sprintf('Not uploaded to Google Drive'));
                die();
            }

            /* check if uploaded file has size */
            usleep(500000); // wait a 0.5 sec so Google can create a thumbnail.
            $api_entry = $this->get_app()->get_drive()->files->get($upload_status->getId(), array("userIp" => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsTeamDrives' => true));

            if (($api_entry->getSize() === 0) && (strpos($api_entry->getMimetype(), 'google-apps') === false)) {
                $deletedentry = $this->get_app()->get_drive()->files->delete($api_entry->getId(), array("userIp" => $this->get_processor()->get_user_ip(), 'supportsTeamDrives' => true));
                $file->error = __('Not succesfully uploaded to Google Drive', 'useyourdrive');
                $return['status']['progress'] = 'failed';

                return;
            }

            /* Add new file to our Cache */
            $entry = new Entry($api_entry);
            $cachedentry = $this->get_processor()->get_cache()->add_to_cache($entry);
            $file->completepath = $cachedentry->get_path($this->get_processor()->get_root_folder());
            $file->fileid = $cachedentry->get_id();
            $file->filesize = Helpers::bytes_to_size_1024($file->size);
            $file->link = urlencode($cachedentry->get_entry()->get_preview_link());
            $file->folderurl = false;

            foreach ($cachedentry->get_parents() as $parent) {
                $folderurl = $parent->get_entry()->get_preview_link();
                $file->folderurl = urlencode($folderurl);
            }
        }

        $return['file'] = $file;
        $return['status']['progress'] = 'finished';
        $return['status']['percentage'] = '100';
        self::set_upload_progress($file->hash, $return);

        /* Create response */
        echo json_encode($return);
        die();
    }

    public function do_upload_direct() {
        if ((!isset($_REQUEST['filename'])) || (!isset($_REQUEST['file_size'])) || (!isset($_REQUEST['mimetype']))) {
            die();
        }

        if ($this->get_processor()->get_shortcode_option('demo') === '1') {
            echo json_encode(array('result' => 0));
            die();
        }

        $name = $_REQUEST['filename'];
        $size = $_REQUEST['file_size'];
        $mimetype = $_REQUEST['mimetype'];

        $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
        $googledrive_file->setName($name);
        $googledrive_file->setMimeType($mimetype);


        /* Convert file if needed */
        $convert = false;
        if ($this->get_processor()->get_shortcode_option('convert') === '1') {
            $importformats = $this->get_processor()->get_import_formats();
            $convert_formats = $this->get_processor()->get_shortcode_option('convert_formats');
            if ($convert_formats[0] === 'all' || in_array($mimetype, $convert_formats)) {
                if (isset($importformats[$mimetype])) {
                    $convert = $importformats[$mimetype];
                    $name = pathinfo($name, PATHINFO_FILENAME);
                    $googledrive_file->setName($name);
                }
            }
        }

        /* Overwrite if needed */
        $current_entry_id = false;
        if ($this->get_processor()->get_shortcode_option('overwrite') === '1') {
            $parent_folder = $this->get_client()->get_folder($this->get_processor()->get_last_folder());
            $current_entry = $this->get_client()->get_cache()->get_node_by_name($name, $parent_folder['folder']);

            if (!empty($current_entry)) {
                $current_entry_id = $current_entry->get_id();
            }
        }

        /* Call the API with the media upload, defer so it doesn't immediately return. */
        $this->get_app()->get_client()->setDefer(true);
        if (empty($current_entry_id)) {
            $googledrive_file->setParents(array($this->get_processor()->get_last_folder()));
            $request = $this->get_app()->get_drive()->files->create($googledrive_file, array("userIp" => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsTeamDrives' => true));
        } else {
            $request = $this->get_app()->get_drive()->files->update($current_entry_id, $googledrive_file, array("userIp" => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsTeamDrives' => true));
        }

        /* Create a media file upload to represent our upload process. */

        /*    $origin = isset($_SERVER["HTTP_ORIGIN"]) ? $_SERVER["HTTP_ORIGIN"] : null; // REQUIRED FOR CORS LIKE REQUEST (DIRECT UPLOAD)

          $this->get_app()->get_client()->setHttpClient(new \GuzzleHttp\Client(array(
          'verify' => USEYOURDRIVE_ROOTDIR . '/cacerts.pem',
          'headers' => array('Origin' => $origin) */

        $origin = $_REQUEST['orgin'];
        $request_headers = $request->getRequestHeaders();
        $request_headers['Origin'] = $origin;
        $request->setRequestHeaders($request_headers);

        $chunkSizeBytes = 5 * 1024 * 1024;
        $media = new \UYDGoogle_Http_MediaFileUpload(
                $this->get_app()->get_client(), $request, $mimetype, null, true, $chunkSizeBytes
        );
        $media->setFileSize($size);

        try {
            $url = $media->getResumeUri();
            echo json_encode(array('result' => 1, 'url' => $url, 'convert' => $convert));
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Not uploaded to Google Drive: %s', $ex->getMessage()));
            echo json_encode(array('result' => 0));
        }

        die();
    }

    static public function get_upload_progress($file_hash) {
        return get_transient('useyourdrive_upload_' . substr($file_hash, 0, 40));
    }

    static public function set_upload_progress($file_hash, $status) {
        /* Update progress */
        return set_transient('useyourdrive_upload_' . substr($file_hash, 0, 40), $status, HOUR_IN_SECONDS);
    }

    public function get_upload_status() {
        $hash = $_REQUEST['hash'];

        /* Try to get the upload status of the file */
        for ($_try = 1; $_try < 6; $_try++) {
            $result = self::get_upload_progress($hash);

            if ($result !== false) {

                if ($result['status']['progress'] === 'failed' || $result['status']['progress'] === 'finished') {
                    delete_transient('useyourdrive_upload_' . substr($hash, 0, 40));
                }

                break;
            }

            /* Wait a moment, perhaps the upload still needs to start */
            usleep(500000 * $_try);
        }

        if ($result === false) {
            $result = array('file' => false, 'status' => array('bytes_up_so_far' => 0, 'total_bytes_up_expected' => 0, 'percentage' => 0, 'progress' => 'failed'));
        }

        echo json_encode($result);
        die();
    }

    public function upload_convert() {
        if (!isset($_REQUEST['fileid']) || !isset($_REQUEST['convert'])) {
            die();
        }
        $file_id = $_REQUEST['fileid'];
        $convert = $_REQUEST['convert'];

        $this->get_processor()->get_cache()->pull_for_changes(true);

        $cachedentry = $this->get_client()->get_entry($file_id);
        if ($cachedentry === false) {
            echo json_encode(array('result' => 0));
            die();
        }

        /* If needed convert document. Only possible by copying the file and removing the old one */
        try {
            $entry = new \UYDGoogle_Service_Drive_DriveFile();
            $entry->setMimeType($convert);
            $api_entry = $this->get_app()->get_drive()->files->copy($cachedentry->get_id(), $entry, array("userIp" => $this->get_processor()->get_user_ip(), 'fields' => $this->get_client()->apifilefields, 'supportsTeamDrives' => true));

            if ($api_entry !== false && $api_entry !== null) {
                $new_id = $api_entry->getId();
                /* Remove file from Cache */
                $deleted_entry = $this->get_app()->get_drive()->files->delete($cachedentry->get_id(), array("userIp" => $this->get_processor()->get_user_ip(), 'supportsTeamDrives' => true));
                $this->get_processor()->get_cache()->remove_from_cache($cachedentry->get_id(), 'deleted');
            }
        } catch (\Exception $ex) {
            echo json_encode(array('result' => 0));
            error_log('[Use-your-Drive message]: ' . sprintf('Upload not converted on Google Drive', $ex->getMessage()));
            die();
        }

        echo json_encode(array('result' => 1, 'fileid' => $new_id));
        die();
    }

    public function upload_post_process() {
        if ((!isset($_REQUEST['files'])) || count($_REQUEST['files']) === 0) {
            echo json_encode(array('result' => 0));
            die();
        }

        /* Update the cache to process all changes */
        $this->get_processor()->get_cache()->pull_for_changes(true);

        $uploaded_files = $_REQUEST['files'];
        $_uploaded_entries = array();

        foreach ($uploaded_files as $file_id) {

            $cachedentry = $this->get_client()->get_entry($file_id, false);

            if ($cachedentry === false) {
                continue;
            }

            /* Upload Hook */
            $cachedentry = apply_filters('useyourdrive_upload', $cachedentry, $this->_processor);
            $_uploaded_entries[] = $cachedentry;

            do_action('useyourdrive_log_event', 'useyourdrive_uploaded_entry', $cachedentry);
        }

        /* Send email if needed */
        if (count($_uploaded_entries) > 0) {
            if ($this->get_processor()->get_shortcode_option('notificationupload') === '1') {
                $this->get_processor()->send_notification_email('upload', $_uploaded_entries);
            }
        }

        /* Return information of the files */
        $files = array();
        foreach ($_uploaded_entries as $cachedentry) {

            $file = array();
            $file['name'] = $cachedentry->get_entry()->get_name();
            $file['type'] = $cachedentry->get_entry()->get_mimetype();
            $file['completepath'] = $cachedentry->get_path($this->get_processor()->get_root_folder());
            $file['fileid'] = $cachedentry->get_id();
            $file['filesize'] = Helpers::bytes_to_size_1024($cachedentry->get_entry()->get_size());
            $file['link'] = urlencode($cachedentry->get_entry()->get_preview_link());
            $file['folderurl'] = false;

            foreach ($cachedentry->get_parents() as $parent) {
                $folderurl = $parent->get_entry()->get_preview_link();
                $file['folderurl'] = urlencode($folderurl);
            }

            $files[$file['fileid']] = $file;
        }

        do_action('useyourdrive_upload_post_process', $_uploaded_entries, $this->_processor);

        /* Clear Cached Requests */
        CacheRequest::clear_local_cache_for_shortcode($this->get_processor()->get_listtoken());

        echo json_encode(array('result' => 1, 'files' => $files));
    }

    public function do_encryption($file) {

        $file_location = $file->tmp_path;
        $passphrase = $this->get_processor()->get_shortcode_option('upload_encryption_passphrase');

        try {
            require_once 'encryption/AESCryptFileLib.php';
            require_once 'encryption/aes256/MCryptAES256Implementation.php';
            $encryption = new \MCryptAES256Implementation();

            $lib = new \AESCryptFileLib($encryption);
            $encrypted_file = $lib->encryptFile($file_location, $passphrase, $file_location);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Upload not encrypted', $ex->getMessage()));
            return false;
        }

        return true;
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
     * @return \TheLion\UseyourDrive\Client
     */
    public function get_client() {
        return $this->_client;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app() {
        return $this->get_processor()->get_app();
    }

}
