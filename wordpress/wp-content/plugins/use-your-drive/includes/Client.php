<?php

namespace TheLion\UseyourDrive;

class Client {

    public $apifilefields = 'capabilities(canEdit,canRename,canDelete,canShare),description,fileExtension,iconLink,id,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width),webContentLink,webViewLink,permissions(id,type,role,domain)';
    public $apifilefieldsexpire = 'id,thumbnailLink,webContentLink,webViewLink';
    public $apilistfilesfields = 'files(capabilities(canEdit,canRename,canDelete,canShare),description,fileExtension,iconLink,id,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width),webContentLink,webViewLink,permissions(id,type,role,domain)),nextPageToken';
    public $apilistfilesexpirefields = 'files(id,thumbnailLink,webContentLink,webViewLink),nextPageToken';
    public $apilistchangesfields = 'changes(file(capabilities(canEdit,canRename,canDelete,canShare),description,fileExtension,iconLink,id,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width),webContentLink,webViewLink,permissions(id,type,role,domain)),removed, fileId),newStartPageToken,nextPageToken';
    public $useteamfolders = false;

    /**
     *
     * @var \TheLion\UseyourDrive\App
     */
    private $_app;

    /**
     *
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;
    private $_user_ip;

    public function __construct(App $_app, Processor $_processor = null) {
        $this->_app = $_app;
        $this->_processor = $_processor;
        $this->_user_ip = $_processor->get_user_ip();


        /* Define if the user can use Team Folders */
        if ($this->get_processor()->get_setting('teamdrives') === 'Yes') {
            $this->useteamfolders = true;
        }
    }

    /*
     * Get AccountInfo
     *
     * @return mixed|WP_Error
     */

    public function get_account_info() {
        $accountInfo = $this->get_app()->get_user()->userinfo->get(array("userIp" => $this->_user_ip));
        return $accountInfo;
    }

    /*
     * Get DriveInfo
     *
     * @return mixed|WP_Error
     */

    public function get_drive_info() {
        $driveInfo = $this->get_app()->get_drive()->about->get(array("fields" => 'importFormats,kind,storageQuota,user', "userIp" => $this->_user_ip));
        return $driveInfo;
    }

    public function get_multiple_entries($entries) {

        if (count($entries) === 1) {
            $api_entry = $this->get_app()->get_drive()->files->get(reset($entries), array("userIp" => $this->_user_ip, "supportsTeamDrives" => $this->useteamfolders, 'fields' => $this->apifilefields));
            return array($api_entry);
        }

        $this->get_library()->setUseBatch(true);
        $batch = new \UYDGoogle_Http_Batch($this->get_library());

        foreach ($entries as $entryid) {
            $batch->add($this->get_app()->get_drive()->files->get($entryid, array("fields" => $this->apifilefields, "supportsTeamDrives" => $this->useteamfolders, "userIp" => $this->_user_ip)), $entryid);
        }

        try {
            if (defined('GOOGLE_API_BATCH')) {
                usleep(mt_rand(10000, 500000));
            } else {
                define('GOOGLE_API_BATCH', true);
            }
            $batch_result = $batch->execute();
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
            throw $ex;
            //return false; CAN CAUSE CORRUPT CACHE
        }
        $this->get_library()->setUseBatch(false);

        return $batch_result;
    }

    public function get_entries_in_subfolders(CacheNode $cachedfolder, $checkauthorized = true) {

        $result = $this->_get_files_recursive($cachedfolder);
        $entries_in_searchedfolder = array();

        foreach ($result['files'] as $file) {
            $cached_entry = $this->get_entry($file['ID'], $checkauthorized);

            if (empty($cached_entry)) {
                continue;
            }

            $entries_in_searchedfolder[$cached_entry->get_id()] = $cached_entry;
        }

        return $entries_in_searchedfolder;
    }

    /* Get entry */

    public function get_entry($entryid = false, $checkauthorized = true) {

        if ($entryid === false) {
            $entryid = $this->get_processor()->get_requested_entry();
        }

        /* Load the root folder when needed */
        $this->get_root_folder();

        /* Get entry from cache */
        $cachedentry = $this->get_cache()->is_cached($entryid);

        /* If entry isn't cached */
        if (!$cachedentry) {

            try {
                $api_entry = $this->get_app()->get_drive()->files->get($entryid, array("userIp" => $this->_user_ip, "supportsTeamDrives" => $this->useteamfolders, 'fields' => $this->apifilefields));
                $entry = new Entry($api_entry);
                $cachedentry = $this->get_cache()->add_to_cache($entry);
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return false;
            }
        }

        if ($checkauthorized === true) {
            if ($entryid !== 'root' && !$this->get_processor()->_is_entry_authorized($cachedentry)) {
                return false;
            }
        }

        return $cachedentry;
    }

    public function update_expired_entry(CacheNode $cachedentry) {
        $entry = $cachedentry->get_entry();
        try {
            $api_entry = $this->get_app()->get_drive()->files->get($entry->get_id(), array("userIp" => $this->_user_ip, "supportsTeamDrives" => $this->useteamfolders, 'fields' => $this->apifilefieldsexpire));

            $entry->set_thumbnails($api_entry->getThumbnailLink());
            $entry->set_direct_download_link($api_entry->getWebContentLink());
            $entry->set_preview_link($api_entry->getWebViewLink());
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
            return false;
        }

        return $this->get_cache()->add_to_cache($entry);
    }

    public function update_expired_folder(CacheNode $cachedentry) {

        if ($cachedentry->get_id() === 'sharedfolder') {
            $shared_folder = $this->get_shared_contents(false);
            return $shared_folder['folder'];
        }


        $entry = $cachedentry->get_entry();

        $params = array('q' => "'" . $entry->get_id() . "' in parents and mimeType != 'application/vnd.google-apps.folder' and trashed = false", "fields" => $this->apilistfilesexpirefields, "pageSize" => 999, "supportsTeamDrives" => $this->useteamfolders, "includeTeamDriveItems" => $this->useteamfolders, "userIp" => $this->_user_ip);
        $folder = $this->get_app()->get_drive()->files->listFiles($params);
        $files_in_folder = $folder->getFiles();
        $nextpagetoken = ($folder->getNextPageToken() !== null) ? $folder->getNextPageToken() : false;

        /* Get all files in folder */
        while ($nextpagetoken) {
            try {
                $params['pageToken'] = $nextpagetoken;
                $more_files = $this->get_app()->get_drive()->files->listFiles($params);
                $files_in_folder = array_merge($files_in_folder, $more_files->getFiles());
                $nextpagetoken = ($more_files->getNextPageToken() !== null) ? $more_files->getNextPageToken() : false;
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return false;
            }
        }
        $folder_items = array();
        $current_children = $cachedentry->get_children();
        foreach ($files_in_folder as $api_entry) {
            if (isset($current_children[$api_entry->getId()])) {
                $current_child = $current_children[$api_entry->getId()];
                $current_child->get_entry()->set_thumbnails($api_entry->getThumbnailLink());
                $current_child->get_entry()->set_direct_download_link($api_entry->getWebContentLink());
                $current_child->get_entry()->set_preview_link($api_entry->getWebViewLink());
                $this->get_cache()->add_to_cache($current_child->get_entry());
            } else {
                $entry = new Entry($api_entry);
                //$cachedentry = $this->get_cache()->add_to_cache($entry);
            }
        }

        $this->get_cache()->add_to_cache($cachedentry->get_entry());

        return $cachedentry;
    }

    public function get_list_subfolders($parents_ids) {

        if (count($parents_ids) === 1) {
            $parents_query = " and ('" . reset($parents_ids) . "' in parents) ";
        } else {
            $parents_query = " and ('" . implode("' in parents or '", $parents_ids) . "' in parents) ";
        }

        /* Set up the query */
        $itemfields = 'files(id,mimeType,name,parents),kind,nextPageToken';
        $params = array(
            'q' => "mimeType='application/vnd.google-apps.folder' $parents_query and trashed = false",
            "fields" => $itemfields,
            "pageSize" => 999,
            "userIp" => $this->_user_ip,
            "supportsTeamDrives" => $this->useteamfolders,
            "includeTeamDriveItems" => $this->useteamfolders,
            "spaces" => 'drive'
        );

        /* Do the request */
        $nextpagetoken = null;
        $folders_found = array();

        do {
            try {
                $search_response = $this->get_app()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return false;
            }

            /* Process the response */
            $more_folders = $search_response->getFiles();
            $folders_found = array_merge($folders_found, $more_folders);

            $nextpagetoken = $search_response->getNextPageToken();
            $params['pageToken'] = $nextpagetoken;
        } while ($nextpagetoken != null);

        return $folders_found;
    }

    /**
     * Function to build a tree structure of all the folders
     * The folders will be added to the cache.
     * This will increase, among others, the search functionality
     * @return boolean
     */
    public function get_folder_structure($subfolders = array()) {

        foreach ($subfolders as $key => $subfolder) {
            if ($subfolder->has_loaded_all_childfolders()) {
                unset($subfolders[$key]);
            }
        }

        unset($subfolders[0]);
        if (isset($subfolders['team-drives'])) {
            $team_folders = $subfolders['team-drives']->get_all_sub_folders();
            $subfolders = array_merge($subfolders, $team_folders);
            unset($subfolders['team-drives']);
        }
        if (isset($subfolders['sharedfolder'])) {
            $shared_folders = $subfolders['sharedfolder']->get_all_sub_folders();
            $subfolders = array_merge($subfolders, $shared_folders);
            unset($subfolders['sharedfolder']);
        }

        $folders_id = array_keys($subfolders);
        $requests = array_chunk($folders_id, 99, true);
        $folders_found = array();
        foreach ($requests as $request) {
            $new_folders_found = $this->get_list_subfolders($request);
            $folders_found = array_merge($folders_found, $new_folders_found);
        }

        $new_sub_folders = array();
        foreach ($folders_found as $folder) {
            $folder_entry = new Entry($folder);

            $cached_node = $this->get_cache()->is_cached($folder_entry->get_id(), 'id', 'as_parent');

            if ($cached_node === false) {
                $cached_node = $this->get_cache()->add_to_cache($folder_entry);
                $cached_node->set_entry($folder_entry);
                $cached_node->set_loaded(false);

                foreach ($folder_entry->get_parents() as $parent_id) {
                    if (isset($subfolders[$parent_id])) {
                        $cached_node->set_parent($subfolders[$parent_id]);
                    }
                }
            }
            $new_sub_folders[$cached_node->get_id()] = $cached_node;
        }

        if (count($new_sub_folders) > 0) {
            $this->get_folder_structure($new_sub_folders);
        }

        return;
    }

    public function create_folder_structure(CacheNode $cachedfolder) {

        /* Build folder structure */
        $this->get_folder_structure($cachedfolder->get_all_sub_folders());
        $cachedfolder->set_loaded_all_childfolders(true);

        /* Save the cache */
        $this->get_cache()->update_cache();
    }

    public function get_root_folder() {

        $root_node = $this->get_cache()->get_root_node();

        if ($root_node !== false) {
            return $root_node;
        }

        $root_api = new \UYDGoogle_Service_Drive_DriveFile();
        $root_api->setId('drive');
        $root_api->setName('Google');
        $root_api->setMimeType('application/vnd.google-apps.folder');
        $root_entry = new Entry($root_api);
        $root_entry->set_special_folder(true);
        $cached_root = $this->get_cache()->add_to_cache($root_entry);
        $cached_root->set_expired(null);
        $cached_root->set_root();
        $cached_root->set_loaded_children(true);
        $cached_root->set_updated();
        $this->get_cache()->set_root_node_id('drive');

        /* First get the My Drive */
        try {
            $mydrive = $this->get_app()->get_drive()->files->get('root', array("userIp" => $this->_user_ip, 'fields' => $this->apifilefields));
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
            return false;
        }
        $mydrive_entry = new Entry($mydrive);
        $mydrive_entry->set_special_folder('mydrive');
        $mydrive_root = $this->get_cache()->add_to_cache($mydrive_entry);
        $mydrive_root->set_parent($cached_root);
        $mydrive_root->set_updated();

        /* Build the root structure in case the user wants to use Team Drives */

        if ($this->useteamfolders === true) {
            $team_drives_api = new \UYDGoogle_Service_Drive_DriveFile();
            $team_drives_api->setId('team-drives');
            $team_drives_api->setName(__('Team Drives', 'useyourdrive'));
            $team_drives_api->setMimeType('application/vnd.google-apps.folder');
            $team_drives_entry = new Entry($team_drives_api);
            $team_drives_entry->set_special_folder('teamdrives');
            $cached_team_drives = $this->get_cache()->add_to_cache($team_drives_entry);
            $cached_team_drives->set_parent($cached_root);
            $cached_team_drives->set_expired(null);
            $cached_team_drives->set_updated();

            /* And get the team drives */
            $team_drive_folder = $this->get_team_drives();
        }

        /* Build the root structure for shared items (not located in My Drive of Team Folders */
        $shared_api = new \UYDGoogle_Service_Drive_DriveFile();
        $shared_api->setId('sharedfolder');
        $shared_api->setName('Shared with me');
        $shared_api->setMimeType('application/vnd.google-apps.folder');
        $shared_entry = new Entry($shared_api);
        $shared_entry->set_special_folder('sharedfolder');
        $shared_root = $this->get_cache()->add_to_cache($shared_entry);
        $shared_root->set_parent($cached_root);
        $shared_root->set_updated();

        $this->get_cache()->set_updated();
        $this->get_cache()->update_cache();

        return $this->get_cache()->get_root_node();
    }

    public function get_my_drive() {
        $cached_root = $this->get_root_folder();

        foreach ($cached_root->get_children() as $cached_child) {
            if ($cached_child->get_entry()->get_special_folder() === 'mydrive') {
                return $cached_child;
            }
        }

        return false;
    }

    /*
     * Get folders and files
     */

    public function get_folder($folderid = false, $checkauthorized = true) {

        if ($folderid === false) {
            $folderid = $this->get_processor()->get_requested_entry();
        }

        /* Load the root folder when needed */
        $root_folder = $this->get_root_folder();

        if ($folderid === 'team-drives') {
            return $this->get_team_drives();
        }

        if ($folderid === 'sharedfolder') {
            return $this->get_shared_contents($checkauthorized);
        }


        $cachedfolder = $this->get_cache()->is_cached($folderid, 'id', false);

        if (!$cachedfolder) {
            $params = array('q' => "'" . $folderid . "' in parents and trashed = false", "fields" => $this->apilistfilesfields, "pageSize" => 999, "supportsTeamDrives" => $this->useteamfolders, "includeTeamDriveItems" => $this->useteamfolders, "userIp" => $this->_user_ip);

            $this->get_library()->setUseBatch(true);
            $batch = new \UYDGoogle_Http_Batch($this->get_library());

            $batch->add($this->get_app()->get_drive()->files->get($folderid, array("fields" => $this->apifilefields, "supportsTeamDrives" => $this->useteamfolders, "userIp" => $this->_user_ip)), 'folder');
            $batch->add($this->get_app()->get_drive()->files->listFiles($params), 'foldercontents');

            try {
                if (defined('GOOGLE_API_BATCH')) {
                    usleep(50000);
                } else {
                    define('GOOGLE_API_BATCH', true);
                }
                $results = $batch->execute();
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return false;
            }

            $this->get_library()->setUseBatch(false);
            $folder = $results['response-folder'];

            if ($folder instanceof \Exception) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $folder->getMessage()));
                return false;
            }

            if ($results['response-foldercontents'] instanceof \Exception) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $results['response-foldercontents']->getMessage()));
                return false;
            }

            $files_in_folder = $results['response-foldercontents']->getFiles();
            $nextpagetoken = ($results['response-foldercontents']->getNextPageToken() !== null) ? $results['response-foldercontents']->getNextPageToken() : false;

            /* Get all files in folder */
            while ($nextpagetoken) {
                try {
                    $params['pageToken'] = $nextpagetoken;
                    $more_files = $this->get_app()->get_drive()->files->listFiles($params);
                    $files_in_folder = array_merge($files_in_folder, $more_files->getFiles());
                    $nextpagetoken = ($more_files->getNextPageToken() !== null) ? $more_files->getNextPageToken() : false;
                } catch (\Exception $ex) {
                    error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                    return false;
                }
            }

            /* Convert the items to Framework Entry */
            $folder_entry = new Entry($folder);

            /* BUG FIX normal API returning different name for Team Drive Name */
            if ($cached_team_drive = $this->get_cache()->get_node_by_id($folder_entry->get_id())) {
                if ($cached_team_drive->has_entry() && $cached_team_drive->get_entry()->get_special_folder() === 'teamdrive') {
                    $folder_entry->set_name($cached_team_drive->get_name());
                }
            }
            /* END BUG FIX */
            if ($cached_my_drive = $this->get_cache()->get_node_by_id($folder_entry->get_id())) {
                if ($cached_my_drive->has_entry() && $cached_my_drive->get_entry()->get_special_folder() === 'mydrive') {
                    $folder_entry->set_special_folder('mydrive');
                }
            }

            $folder_items = array();
            foreach ($files_in_folder as $entry) {
                $folder_items[] = new Entry($entry);
            }

            $cachedfolder = $this->get_cache()->add_to_cache($folder_entry);
            $cachedfolder->set_loaded_children(true);

            /* Add all entries in folder to cache */
            foreach ($folder_items as $item) {
                $newitem = $this->get_cache()->add_to_cache($item);
            }

            $this->get_cache()->update_cache();
        }

        $folder = $cachedfolder;
        $files_in_folder = $cachedfolder->get_children();

        /* Check if folder is in the shortcode-set rootfolder */
        if ($checkauthorized === true) {
            if (!$this->get_processor()->_is_entry_authorized($cachedfolder)) {
                return false;
            }
        }

        return array('folder' => $folder, 'contents' => $files_in_folder);
    }

    /*
     * Get folders and files
     */

    public function get_shared_contents($checkauthorized = true) {

        $cachedfolder = $this->get_cache()->is_cached('sharedfolder', 'id', false);

        if (!$cachedfolder) {
            $params = array('q' => "sharedWithMe = true and trashed = false", "fields" => $this->apilistfilesfields, "pageSize" => 999, "supportsTeamDrives" => $this->useteamfolders, "includeTeamDriveItems" => $this->useteamfolders, "userIp" => $this->_user_ip);

            $shared_entries = array();
            $nextpagetoken = null;
            /* Get all files in folder */
            while ($nextpagetoken || $nextpagetoken === null) {
                try {
                    if ($nextpagetoken !== null) {
                        $params['pageToken'] = $nextpagetoken;
                    }

                    $more_shared_entries = $this->get_app()->get_drive()->files->listFiles($params);
                    $shared_entries = array_merge($shared_entries, $more_shared_entries->getFiles());
                    $nextpagetoken = ($more_shared_entries->getNextPageToken() !== null) ? $more_shared_entries->getNextPageToken() : false;
                } catch (\Exception $ex) {
                    error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                    return false;
                }
            }

            $folder_items = array();
            foreach ($shared_entries as $api_entry) {
                $entry = new Entry($api_entry);
                $cached_entry = $this->get_cache()->add_to_cache($entry);
            }

            $cachedfolder = $this->get_cache()->get_node_by_id('sharedfolder');
            $cachedfolder->set_loaded_children(true);
            $cachedfolder->set_updated();

            $this->get_cache()->update_cache();
        }

        $folder = $cachedfolder;
        $files_in_folder = $cachedfolder->get_children();

        /* Check if folder is in the shortcode-set rootfolder */
        if ($checkauthorized === true) {
            if (!$this->get_processor()->_is_entry_authorized($cachedfolder)) {
                return false;
            }
        }

        return array('folder' => $folder, 'contents' => $files_in_folder);
    }

    public function get_team_drives() {

        $team_drives = array();
        $params = array(
            "fields" => 'kind,nextPageToken,teamDrives(kind,id,name,capabilities,backgroundImageFile,backgroundImageLink)',
            "pageSize" => 10,
            "userIp" => $this->_user_ip
        );

        $nextpagetoken = null;
        /* Get all files in folder */
        while ($nextpagetoken || $nextpagetoken === null) {
            try {
                if ($nextpagetoken !== null) {
                    $params['pageToken'] = $nextpagetoken;
                }

                $more_drives = $this->get_app()->get_drive()->teamdrives->listDrives($params);
                $team_drives = array_merge($team_drives, $more_drives->getTeamDrives());
                $nextpagetoken = ($more_drives->getNextPageToken() !== null) ? $more_drives->getNextPageToken() : false;
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return false;
            }
        }

        $cached_team_drives_folder = $this->get_cache()->get_node_by_id('team-drives');

        if (!empty($team_drives)) {
            foreach ($team_drives as $drive) {
                $drive_item = new DriveEntry($drive);
                $drive_item->set_special_folder('teamdrive');
                $cached_drive_item = $this->get_cache()->add_to_cache($drive_item);
                $cached_drive_item->set_parent($cached_team_drives_folder);
            }

            $this->get_cache()->update_cache();
        }


        $team_drives_in_folder = $cached_team_drives_folder->get_children();

        return array('folder' => $cached_team_drives_folder, 'contents' => $team_drives_in_folder);
    }

    public function search_by_name($query) {

        if ($this->get_processor()->get_shortcode_option('searchfrom') === 'parent') {
            $searchedfolder = $this->get_processor()->get_requested_entry();
        } else {
            $searchedfolder = $this->get_processor()->get_root_folder();
        }

        /* As it is not possible to search directly inside a folder via the Google API
         * First make a list of all the children folders where we should look in */
        $cachedfolder = $this->get_folder($searchedfolder);
        $cachedfolder = $cachedfolder['folder'];

        $folders_to_look_in = array($searchedfolder => $cachedfolder);

        if ($cachedfolder->has_loaded_all_childfolders() === false) {
            $this->create_folder_structure($cachedfolder);
        }

        $all_subfolders = $cachedfolder->get_all_child_folders();
        $folders_to_look_in = array_merge($folders_to_look_in, $all_subfolders);

        /* Remove Root folder */
        unset($folders_to_look_in[0]);
        unset($folders_to_look_in['team-drives']);

        $folders_id = array_keys($folders_to_look_in);

        /* Set search field */
        if ($this->get_processor()->get_shortcode_option('searchcontents') === '1') {
            $field = 'fullText';
        } else {
            $field = 'name';
        }

        if (count($folders_id) === 1) {
            $parents_query = " and ('" . $cachedfolder->get_id() . "' in parents) ";
        } elseif (count($folders_id) > 100) {
            /* If there are to many folders, just search globaly. The Google API doesn't support a very long query */
            $parents_query = '';
        } else {
            $parents_query = " and ('" . implode("' in parents or '", $folders_id) . "' in parents) ";
        }

        /* Set the right corpora */
        $corpora = 'user';
        $teamDriveId = '';
        if ($this->useteamfolders && $cachedfolder->is_in_folder('team-drives')) {

            if ($cachedfolder->get_id() === 'team-drives') {
                $corpora = 'user,allTeamDrives';
            } else {
                $corpora = 'teamDrive';
                $teamDriveId = $cachedfolder->get_teamdrive_id();
            }
        }

        /* Find all items containing query */
        $params = array(
            'q' => "$field contains '" . stripslashes($query) . "' $parents_query and trashed = false",
            "fields" => $this->apilistfilesfields,
            "pageSize" => 100,
            "supportsTeamDrives" => $this->useteamfolders,
            "includeTeamDriveItems" => $this->useteamfolders,
            "corpora" => $corpora,
            "teamDriveId" => empty($teamDriveId) ? '' : $teamDriveId,
            "userIp" => $this->_user_ip
        );

        /* Do the request */
        $nextpagetoken = null;
        $files_found = array();
        $entries_found = array();
        $entries_in_searchedfolder = array();

        do_action('useyourdrive_log_event', 'useyourdrive_searched', $cachedfolder, array('query' => $query));

        do {
            try {
                $search_response = $this->get_app()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return array();
            }

            /* Process the response */
            $more_files = $search_response->getFiles();
            $files_found = array_merge($files_found, $more_files);

            $nextpagetoken = $search_response->getNextPageToken();
            $params['pageToken'] = $nextpagetoken;
        } while ($nextpagetoken !== null);

        $new_parent_folders = array();
        foreach ($files_found as $file) {
            $file_entry = new Entry($file);
            $entries_found[] = $file_entry;
            if ($file_entry->has_parents()) {
                foreach ($file_entry->get_parents() as $parent) {
                    if ($this->get_cache()->get_node_by_id($parent, false) === false) {
                        $new_parent_folders[$parent] = $parent;
                    }
                }
            }
        }

        /* Load all new parents at once */
        $new_parents_folders_api = $this->get_multiple_entries($new_parent_folders);
        foreach ($new_parents_folders_api as $parent) {
            if (!($parent instanceof EntryAbstract)) {
                $parent = new Entry($parent);
            }

            $this->get_cache()->add_to_cache($parent);
        }


        foreach ($entries_found as $entry) {
            /* Check if entries are in cache */
            $cachedentry = $this->get_cache()->is_cached($entry->get_id(), 'id', true);

            /* If not found, add to cache */
            if ($cachedentry === false) {
                $cachedentry = $this->get_cache()->add_to_cache($entry);
            }

            /* Keep all entries that are in searched folder */
            if ($this->get_processor()->_is_entry_authorized($cachedentry) && $cachedentry->is_in_folder($searchedfolder)) {
                $entries_in_searchedfolder[] = $cachedentry;
            }
        }

        /* Update the cache already here so that the Search Output is cached */
        $this->get_cache()->update_cache();

        return $entries_in_searchedfolder;
    }

    public function delete_entries($entries_to_delete = array()) {
        $deleted_entries = array();
        $filelist_deleted = array();

        foreach ($entries_to_delete as $target_entry_path) {
            $target_cached_entry = $this->get_entry($target_entry_path);

            if ($target_cached_entry === false) {
                continue;
            }

            $target_entry = $target_cached_entry->get_entry();

            if ($target_entry->is_file() && $this->get_processor()->get_user()->can_delete_files() === false) {
                error_log('[Use-your-Drive message]: ' . sprintf('Failed to delete %s as user is not allowed to remove files.', $target_entry->get_path()));
                $deleted_entries[$target_entry->get_id()] = false;
                continue;
            }

            if ($target_entry->is_dir() && $this->get_processor()->get_user()->can_delete_folders() === false) {
                error_log('[Use-your-Drive message]: ' . sprintf('Failed to delete %s as user is not allowed to remove folders.', $target_entry->get_path()));
                $deleted_entries[$target_entry->get_id()] = false;
                continue;
            }

            if ($this->get_processor()->get_shortcode_option('demo') === '1') {
                $deleted_entries[$target_entry->get_id()] = false;
                continue;
            }

            try {
                if ($this->get_processor()->get_shortcode_option('deletetotrash') === '1') {
                    /* Create an the entry for Patch */
                    $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
                    $updateentry->setTrashed(true);
                    $deleted_entry = $this->get_app()->get_drive()->files->update($target_entry->get_id(), $updateentry, array("supportsTeamDrives" => $this->useteamfolders, "userIp" => $this->_user_ip));
                } else {
                    $deleted_entry = $this->get_app()->get_drive()->files->delete($target_entry->get_id(), array("supportsTeamDrives" => $this->useteamfolders, "userIp" => $this->_user_ip));
                }

                do_action('useyourdrive_log_event', 'useyourdrive_deleted_entry', $target_cached_entry, array('to_trash' => $this->get_processor()->get_shortcode_option('deletetotrash')));
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

                if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                    return new \WP_Error('broke', $ex->getMessage());
                } else {
                    return new \WP_Error('broke', __('Failed to delete entry', 'useyourdrive'));
                }
            }

            $deleted_entries[$target_entry->get_id()] = $target_cached_entry;
        }

        /* Send email if needed */
        if ($this->get_processor()->get_shortcode_option('notificationdeletion') === '1') {
            $this->get_processor()->send_notification_email('deletion_multiple', $deleted_entries);
        }

        /* Clear Cached Requests */
        CacheRequest::clear_local_cache_for_shortcode($this->get_processor()->get_listtoken());

        /* Remove items from cache */
        $this->get_cache()->pull_for_changes(true);

        return $deleted_entries;
    }

    /*
     * Rename entry from Google Drive
     */

    function rename_entry($new_filename = null) {

        if ($this->get_processor()->get_shortcode_option('demo') === '1') {
            return new \WP_Error('broke', __('Failed to rename entry', 'useyourdrive'));
        }

        if ($new_filename === null && $this->get_processor()->get_shortcode_option('debug') === '1') {
            return new \WP_Error('broke', __('No new name set', 'useyourdrive'));
        }

        /* Get entry meta data */
        $cachedentry = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

        if ($cachedentry === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
            if ($cachedentry === false) {
                if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                    return new \WP_Error('broke', __('Invalid entry', 'useyourdrive'));
                } else {
                    return new \WP_Error('broke', __('Failed to rename entry', 'useyourdrive'));
                }
                return new \WP_Error('broke', __('Failed to rename entry', 'useyourdrive'));
            }
        }

        /* Check if user is allowed to delete from this dir */
        if (!$cachedentry->is_in_folder($this->get_processor()->get_last_folder())) {
            return new \WP_Error('broke', __("You are not authorized to rename files in this directory", 'useyourdrive'));
        }

        $entry = $cachedentry->get_entry();

        /* Check user permission */
        if (!$entry->get_permission('canrename')) {
            return new \WP_Error('broke', __('You are not authorized to rename this file or folder', 'useyourdrive'));
        }

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            return new \WP_Error('broke', __('You are not authorized to rename this file or folder', 'useyourdrive'));
        }

        if (($entry->is_dir()) && ($this->get_processor()->get_user()->can_rename_folders() === false)) {
            return new \WP_Error('broke', __('You are not authorized to rename folder', 'useyourdrive'));
        }

        if (($entry->is_file()) && ($this->get_processor()->get_user()->can_rename_files() === false)) {
            return new \WP_Error('broke', __('You are not authorized to rename this file', 'useyourdrive'));
        }

        $extension = $entry->get_extension();
        $name = (!empty($extension)) ? $new_filename . '.' . $extension : $new_filename;
        $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
        $updateentry->setName($name);

        try {
            $renamed_entry = $this->update_entry($entry->get_id(), $updateentry);

            if ($renamed_entry !== false && $renamed_entry !== null) {
                $this->get_cache()->update_cache();
            }

            do_action('useyourdrive_log_event', 'useyourdrive_renamed_entry', $renamed_entry, array('old_name' => $entry->get_name()));
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

            if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                return new \WP_Error('broke', $ex->getMessage());
            } else {
                return new \WP_Error('broke', __('Failed to rename entry', 'useyourdrive'));
            }
        }


        return $renamed_entry;
    }

    /*
     * Move entry Google Drive
     */

    function move_entry($target = null, $copy = false) {

        if ($this->get_processor()->get_shortcode_option('demo') === '1') {
            return new \WP_Error('broke', __('Failed to move entry', 'useyourdrive'));
        }

        if ($this->get_processor()->get_requested_entry() === null || $target === null) {
            return new \WP_Error('broke', __('Failed to move entry', 'useyourdrive'));
        }

        /* Get entry meta data */
        $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
        $cachedtarget = $this->get_entry($target);
        $cachedcurrentfolder = $this->get_entry($this->get_processor()->get_last_folder());

        if ($cachedentry === false || $cachedtarget === false) {
            return new \WP_Error('broke', __('Failed to move entry', 'useyourdrive'));
        }

        /* Check if user is allowed to delete from this dir */
        if (!$cachedentry->is_in_folder($cachedcurrentfolder->get_id())) {
            return new \WP_Error('broke', __("You are not authorized to move items in this directory", 'useyourdrive'));
        }

        $entry = $cachedentry->get_entry();

        /* Check user permission */
        if (!$entry->get_permission('candelete')) {
            return new \WP_Error('broke', __('You are not authorized to move this file or folder', 'useyourdrive'));
        }

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            return new \WP_Error('broke', __('You are not authorized to move this file or folder', 'useyourdrive'));
        }

        if (($entry->is_dir()) && ($this->get_processor()->get_user()->can_move_folders() === false)) {
            return new \WP_Error('broke', __('You are not authorized to move folder', 'useyourdrive'));
        }

        if (($entry->is_file()) && ($this->get_processor()->get_user()->can_move_files() === false)) {
            return new \WP_Error('broke', __('You are not authorized to move this file', 'useyourdrive'));
        }

        $update_params = array();

        /* Add the new Parent to the Entry */
        $update_params['addParents'] = $cachedtarget->get_id();

        /* Remove old Parent */
        if ($copy === false) {
            $update_params['removeParents'] = $this->get_processor()->get_last_folder();
        }

        /* Create an the entry for Patch */
        $entry = new \UYDGoogle_Service_Drive_DriveFile();

        try {
            $this->get_cache()->remove_from_cache($cachedentry->get_id(), 'moved');
            $moved_entry = $this->update_entry($cachedentry->get_id(), $entry, $update_params);

            do_action('useyourdrive_log_event', 'useyourdrive_moved_entry', $moved_entry);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

            if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                return new \WP_Error('broke', $ex->getMessage());
            } else {
                return new \WP_Error('broke', __('Failed to move entry', 'useyourdrive'));
            }
        }

        return $moved_entry;
    }

    /*
     * Edit descriptions entry from Google Drive
     */

    function update_description($new_description = null) {

        if ($new_description === null && $this->get_processor()->get_shortcode_option('debug') === '1') {
            return new \WP_Error('broke', __('No new description set', 'useyourdrive'));
        }

        /* Get entry meta data */
        $cachedentry = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

        if ($cachedentry === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
            if ($cachedentry === false) {
                if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                    return new \WP_Error('broke', __('Invalid entry', 'useyourdrive'));
                } else {
                    return new \WP_Error('broke', __('Failed to edit entry', 'useyourdrive'));
                }
                return new \WP_Error('broke', __('Failed to edit entry', 'useyourdrive'));
            }
        }

        /* Check if user is allowed to delete from this dir */
        if (!$cachedentry->is_in_folder($this->get_processor()->get_last_folder())) {
            return new \WP_Error('broke', __("You are not authorized to edit files in this directory", 'useyourdrive'));
        }

        $entry = $cachedentry->get_entry();


        /* Check user permission */
        if (!$entry->get_permission('canrename')) {
            return new \WP_Error('broke', __('You are not authorized to edit this file or folder', 'useyourdrive'));
        }

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            return new \WP_Error('broke', __('You are not authorized to edit this file or folder', 'useyourdrive'));
        }

        /* Create an the entry for Patch */
        $updated_entry = new \UYDGoogle_Service_Drive_DriveFile();
        $updated_entry->setDescription($new_description);

        try {
            $edited_entry = $this->update_entry($entry->get_id(), $updated_entry);

            do_action('useyourdrive_log_event', 'useyourdrive_updated_metadata', $edited_entry, array('metadata_field' => 'Description'));
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

            if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                return new \WP_Error('broke', $ex->getMessage());
            } else {
                return new \WP_Error('broke', __('Failed to edit entry', 'useyourdrive'));
            }
        }

        return $edited_entry->get_entry()->get_description();
    }

    /*
     * Update entry from Google Drive
     */

    public function update_entry($entry_id, \UYDGoogle_Service_Drive_DriveFile $entry, $_params = array()) {

        $params = array_merge(array(
            'fields' => 'id', //$this->apifilefields,
            "userIp" => $this->_user_ip
                ), $_params);

        try {
            $result = $this->get_app()->get_drive()->files->update($entry_id, $entry, $params);
            $api_entry = $this->get_app()->get_drive()->files->get($entry_id, array("userIp" => $this->_user_ip, 'fields' => $this->apifilefields, 'supportsTeamDrives' => true));
            $entry = new Entry($api_entry);
            $cachedentry = $this->get_cache()->add_to_cache($entry);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

            return false;
        }

        return $cachedentry;
    }

    /*
     * Add directory to Google Drive
     */

    function add_folder($new_folder_name = null) {
        if ($this->get_processor()->get_shortcode_option('demo') === '1') {
            return new \WP_Error('broke', __('Failed to add folder', 'useyourdrive'));
        }

        if ($new_folder_name === null && $this->get_processor()->get_shortcode_option('debug') === '1') {
            return new \WP_Error('broke', __('No new foldername set', 'useyourdrive'));
        }

        /* Get entry meta data of current folder */
        $cachedentry = $this->get_cache()->is_cached($this->get_processor()->get_last_folder());


        if ($cachedentry === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_last_folder());
            if ($cachedentry === false) {
                if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                    return new \WP_Error('broke', __('Invalid entry', 'useyourdrive'));
                } else {
                    return new \WP_Error('broke', __('Failed to add entry', 'useyourdrive'));
                }
                return new \WP_Error('broke', __('Failed to add entry', 'useyourdrive'));
            }
        }

        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            return new \WP_Error('broke', __('You are not authorized to add folders in this directory', 'useyourdrive'));
        }

        $currentfolder = $cachedentry->get_entry();

        /* Check user permission */
        if (!$currentfolder->get_permission('canadd')) {
            return new \WP_Error('broke', __('You are not authorized to add a folder', 'useyourdrive'));
        }

        $newfolder = new \UYDGoogle_Service_Drive_DriveFile();
        $newfolder->setName($new_folder_name);
        $newfolder->setMimeType('application/vnd.google-apps.folder');
        $newfolder->setParents(array($currentfolder->get_id()));

        try {
            $api_entry = $this->get_app()->get_drive()->files->create($newfolder, array("fields" => $this->apifilefields, "userIp" => $this->_user_ip, 'supportsTeamDrives' => true));

            if ($api_entry !== null) {
                /* Add new file to our Cache */
                $newentry = new Entry($api_entry);
                $new_cached_entry = $this->get_cache()->add_to_cache($newentry);

                do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $new_cached_entry);
            }
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

            if ($this->get_processor()->get_shortcode_option('debug') === '1') {
                return new \WP_Error('broke', $ex->getMessage());
            } else {
                return new \WP_Error('broke', __('Failed to add folder', 'useyourdrive'));
            }
        }

        return $newentry;
    }

    public function copy_folder_recursive(CacheNode $templatefolder, CacheNode $newfolder) {

        if (empty($templatefolder) || empty($newfolder)) {
            return false;
        }

        if ($templatefolder->has_children() === false) {
            return false;
        }

        $template_folder_children = $templatefolder->get_children();

        $this->get_library()->setUseBatch(true);
        $batch = new \UYDGoogle_Http_Batch($this->get_library());
        $new_entries = false;

        foreach ($template_folder_children as $cached_child) {

            $child = $cached_child->get_entry();

            $entry_exists = $this->get_cache()->get_node_by_name($child->get_name(), $newfolder);
            if ($entry_exists !== false) {
                continue;
            }

            $new_entries = true;

            if ($child->is_dir()) {
                /* Create child folder in user folder */
                $newchildfolder = new \UYDGoogle_Service_Drive_DriveFile();
                $newchildfolder->setName($child->get_name());
                $newchildfolder->setMimeType('application/vnd.google-apps.folder');
                $newchildfolder->setParents(array($newfolder->get_id()));

                $batch->add($this->get_app()->get_drive()->files->create($newchildfolder, array("fields" => $this->apifilefields, "userIp" => $this->_user_ip, 'supportsTeamDrives' => true)), $child->get_id());
            } else {
                /* Copy file to new folder */
                $newfile = new \UYDGoogle_Service_Drive_DriveFile();
                $newfile->setName($child->get_name());
                $newfile->setParents(array($newfolder->get_id()));

                $batch->add($this->get_app()->get_drive()->files->copy($child->get_id(), $newfile, array("fields" => $this->apifilefields, "userIp" => $this->_user_ip, 'supportsTeamDrives' => true)), $child->get_id());
            }
        }

        if ($new_entries === false) {
            return true;
        }

        /* Execute the Batch Call */
        try {
            if (defined('GOOGLE_API_BATCH')) {
                usleep(50000);
            } else {
                define('GOOGLE_API_BATCH', true);
            }
            $batch_result = $batch->execute();
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

            return false;
        }

        $this->get_library()->setUseBatch(false);

        /* Process the result */

        foreach ($batch_result as $key => $api_childentry) {
            $newchildentry = new Entry($api_childentry);
            $cachednewchildentry = $this->get_cache()->add_to_cache($newchildentry);
            $original_id = str_replace('response-', '', $key);
            $template_entry = $template_folder_children[$original_id];

            if ($template_entry->get_entry()->is_dir()) {
                /* Copy contents of child folder to new create child user folder */
                $cached_child_template_folder = $this->get_folder($template_entry->get_id(), false);
                $this->copy_folder_recursive($cached_child_template_folder['folder'], $cachednewchildentry);
            }
        }



        return true;
    }

    /**
     * Create thumbnails for Google docs which need a accesstoken
     */
    function build_thumbnail() {
        $cached = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

        if ($cached === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
        } else {
            $cachedentry = $cached;
        }

        if ($cachedentry === false) {
            die();
        }

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            die();
        }

        $thumbnail_original = $cachedentry->get_entry()->get_thumbnail_original();
        if (empty($thumbnail_original)) {
            header("Location: " . $cachedentry->get_entry()->get_default_thumbnail_icon());
            die();
        }

        /* Set the thumbnail attributes & file */
        switch ($_REQUEST['s']) {
            case 'icon':
                $thumbnail_attributes = '=h16-c-nu';
                break;
            case 'small':
                $thumbnail_attributes = '=w400-h300-p-k';
                break;
            case 'cropped':
                $thumbnail_attributes = '=w400-h300-c-nu';
                break;
            case 'large':
                $thumbnail_attributes = '=s0';
                break;
        }

        /* Check if file already exists */
        $thumbnail_file = $cachedentry->get_id() . $thumbnail_attributes . '.png';
        if (file_exists(USEYOURDRIVE_CACHEDIR . '/thumbnails/' . $thumbnail_file) && (filemtime(USEYOURDRIVE_CACHEDIR . '/thumbnails/' . $thumbnail_file) === strtotime($cachedentry->get_entry()->get_last_edited()))) {
            $url = USEYOURDRIVE_CACHEURL . '/thumbnails/' . $thumbnail_file;

            /* Update the cached node */
            switch ($_REQUEST['s']) {
                case 'icon':
                    $cachedentry->get_entry()->set_thumbnail_icon($url);
                case 'small':
                    $cachedentry->get_entry()->set_thumbnail_small($url);
                    break;
                case 'cropped':
                    $cachedentry->get_entry()->set_thumbnail_small_cropped($url);
                    break;
                case 'large':
                    $cachedentry->get_entry()->set_thumbnail_large($url);
                    $thumbnail_attributes = '=s0';
                    break;
            }
            $this->get_cache()->set_updated(true);

            header('Location: ' . $url);
            die();
        }

        /* Build the thumbnail URL where we fetch the thumbnail */

        $downloadlink = $cachedentry->get_entry()->get_thumbnail_original(); // . "&access_token=" . $token['access_token'];
        $downloadlink = str_replace('=s220', $thumbnail_attributes, $downloadlink);

        /* Do the request */
        try {
            $token = json_decode($this->get_library()->getAccessToken());
            $request = new \UYDGoogle_Http_Request($downloadlink, 'GET');
            $this->get_library()->getIo()->setOptions(array(CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true));
            $httpRequest = $this->get_library()->getAuth()->authenticatedRequest($request);


            /* Do the request for new SDK */
//    $token = $this->get_library()->getAccessToken();
//    $httpClient = new \GuzzleHttp\Client(array('verify' => false, 'allow_redirects' => true));
//    $request = $this->get_library()->authorize($httpClient);
//    $response = $request->get($downloadlink);
//
//    if ($response->getStatusCode() !== 200) {
//      die();
//    }

            /* Process the reponse */
            $headers = $httpRequest->getResponseHeaders();


            if (!file_exists(USEYOURDRIVE_CACHEDIR . '/thumbnails')) {
                @mkdir(USEYOURDRIVE_CACHEDIR . '/thumbnails', 0755);
            }

            if (!is_writable(USEYOURDRIVE_CACHEDIR . '/thumbnails')) {
                @chmod(USEYOURDRIVE_CACHEDIR . '/thumbnails', 0755);
            }

            /* Save the thumbnail locally */
            @file_put_contents(USEYOURDRIVE_CACHEDIR . '/thumbnails/' . $thumbnail_file, $httpRequest->getResponseBody()); //New SDK: $response->getBody()
            touch(USEYOURDRIVE_CACHEDIR . '/thumbnails/' . $thumbnail_file, strtotime($cachedentry->get_entry()->get_last_edited()));
            $url = USEYOURDRIVE_CACHEURL . '/thumbnails/' . $thumbnail_file;

            /* Update the cached node */
            switch ($_REQUEST['s']) {
                case 'icon':
                    $cachedentry->get_entry()->set_thumbnail_icon($url);
                case 'small':
                    $cachedentry->get_entry()->set_thumbnail_small($url);
                    break;
                case 'cropped':
                    $cachedentry->get_entry()->set_thumbnail_small_cropped($url);
                    break;
                case 'large':
                    $cachedentry->get_entry()->set_thumbnail_large($url);
                    $thumbnail_attributes = '=s0';
                    break;
            }
            $this->get_cache()->set_updated(true);
            header('Location: ' . $url);
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
            //echo $httpRequest->getResponseBody(); //New SDK: $response->getBody()
        }

        die();
    }

    function get_folder_thumbnails() {

        $thumbnails = array();
        $maximages = 3;
        $target_height = $this->get_processor()->get_shortcode_option('targetheight');

        $folder = $this->get_folder();

        if ($folder === false) {
            return;
        }

        $all_subfolders = $folder['folder']->get_all_sub_folders();
        $folders_id = array();

        foreach ($all_subfolders as $subfolder) {
            $subfolder_entry = $subfolder->get_entry();
            $folder_thumbnails = $subfolder_entry->get_folder_thumbnails();

            /* 1: First if the cache still has valid thumbnails available */
            if (isset($folder_thumbnails['expires']) && $folder_thumbnails['expires'] > time()) {
                $iimages = 1;
                $thumbnails_html = '';

                foreach ($folder_thumbnails['thumbs'] as $folder_thumbnail) {
                    $thumb_url = $subfolder_entry->get_thumbnail_with_size('h' . round($target_height * 1) . '-c-nu', $folder_thumbnail);
                    $thumbnails_html .= "<div class='folder-thumb thumb$iimages' style='width:" . $target_height . "px;height:" . $target_height . "px;background-image: url(" . $thumb_url . ")'></div>";
                    $iimages++;
                }
                $thumbnails[$subfolder->get_id()] = $thumbnails_html;
            } else {

                $cachedentry = $this->get_cache()->is_cached($subfolder->get_id(), 'id', false);
                /* 2: Check if we can use the content of the folder itself */
                if ($cachedentry !== false && !$cachedentry->is_expired()) {

                    $iimages = 1;
                    $thumbnails_html = '';

                    $children = $subfolder->get_children();
                    foreach ($children as $cached_child) {
                        $entry = $cached_child->get_entry();
                        if ($iimages > $maximages) {
                            break;
                        }

                        if (!$entry->has_own_thumbnail() || !$entry->is_file()) {
                            continue;
                        }

                        $thumbnail = $entry->get_thumbnail_with_size('h' . round($target_height * 1) . '-c-nu');
                        $thumbnails_html .= "<div class='folder-thumb thumb$iimages' style='width:" . $target_height . "px;height:" . $target_height . "px;background-image: url(" . $thumbnail . ")'></div>";
                        $iimages++;
                    }

                    $thumbnails[$subfolder->get_id()] = $thumbnails_html;
                } else {
                    /* 3: If we don't have thumbnails available, get them */
                    $folders_id[] = $subfolder->get_id();
                }
            }
        }

        if (count($folders_id) > 0) {

            /* Find all items containing query */
            $params = array(
                "fields" => 'files(id,thumbnailLink),nextPageToken',
                "pageSize" => $maximages,
                "includeTeamDriveItems" => $this->useteamfolders,
                "supportsTeamDrives" => $this->useteamfolders,
                "userIp" => $this->_user_ip);

            $this->get_library()->setUseBatch(true);
            $batch = new \UYDGoogle_Http_Batch($this->get_library());

            foreach ($folders_id as $folder_id) {
                $params['q'] = "'$folder_id' in parents and (mimeType = 'image/gif' or mimeType = 'image/png' or mimeType = 'image/jpeg' or mimeType = 'x-ms-bmp') and trashed = false";
                $batch->add($this->get_app()->get_drive()->files->listFiles($params), $folder_id);
            }

            try {
                $batch_results = $batch->execute();
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                throw $ex;
            }
            $this->get_library()->setUseBatch(false);


            foreach ($batch_results as $batchkey => $result) {
                $folderid = str_replace('response-', '', $batchkey);
                $subfolder = $all_subfolders[$folderid];

                $images = $result->getFiles();

                if (!is_array($images)) {
                    continue;
                }

                $iimages = 1;
                $thumbnails_html = '';
                $folder_thumbs = array();

                foreach ($images as $image) {
                    $entry = new Entry($image);
                    $folder_thumbs[] = $entry->get_thumbnail_small();
                    $thumbnail = $entry->get_thumbnail_with_size('h' . round($target_height * 1) . '-c-nu');
                    $thumbnails_html .= "<div class='folder-thumb thumb$iimages' style='display:none; width:" . $target_height . "px;height:" . $target_height . "px;background-image: url(" . $thumbnail . ")'></div>";
                    $iimages++;
                }

                $subfolder->get_entry()->set_folder_thumbnails(array('expires' => time() + 1800, 'thumbs' => $folder_thumbs));
                $thumbnails[$folderid] = $thumbnails_html;
            }

            $this->get_cache()->set_updated();
        }

        CacheRequest::clear_local_cache_for_shortcode($this->get_processor()->get_listtoken());
        return $thumbnails;
    }

    function preview_entry() {
        /* Check if file is cached and still valid */
        $cached = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

        if ($cached === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
        } else {
            $cachedentry = $cached;
        }

        if ($cachedentry === false) {
            die();
        }

        $entry = $cachedentry->get_entry();

        /* get the last-modified-date of this very file */
        $lastModified = strtotime($entry->get_last_edited());
        /* get a unique hash of this file (etag) */
        $etagFile = md5($lastModified);
        /* get the HTTP_IF_MODIFIED_SINCE header if set */
        $ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false);
        /* get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash) */
        $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
        header("Etag: $etagFile");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60 * 5) . ' GMT');
        header('Cache-Control: must-revalidate');

        /* check if page has changed. If not, send 304 and exit */
        if ($cached !== false) {
            if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModified || $etagHeader == $etagFile) {

                /* Send email if needed */
                if ($this->get_processor()->get_shortcode_option('notificationdownload') === '1') {
                    $this->get_processor()->send_notification_email('download', array($cachedentry));
                }

                do_action('useyourdrive_preview', $cachedentry);

                do_action('useyourdrive_log_event', 'useyourdrive_previewed_entry', $cachedentry);

                header("HTTP/1.1 304 Not Modified");
                exit;
            }
        }

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            die();
        }

        $previewurl = $this->get_embed_url($cachedentry);

        if ($previewurl === false) {
            error_log('[Use-your-Drive message]: ' . sprintf('Cannot generate preview/embed link on line %s', __LINE__));
            die();
        }

        if ($this->get_processor()->get_shortcode_option('previewinline') === '0' && $this->get_processor()->get_user()->can_download()) {
            $previewurl = str_replace('preview?rm=minimal', 'view', $previewurl);
        }

        header('Location: ' . $previewurl);

        if ($this->get_processor()->get_shortcode_option('notificationdownload') === '1') {
            $this->get_processor()->send_notification_email('download', array($cachedentry));
        }

        do_action('useyourdrive_preview', $cachedentry);

        do_action('useyourdrive_log_event', 'useyourdrive_previewed_entry', $cachedentry);

        die();
    }

    /*
     * Download file
     */

    function download_entry() {

        /* Check if file is cached and still valid */
        $cached = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

        if ($cached === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
        } else {
            $cachedentry = $cached;
        }

        if ($cachedentry === false) {
            die();
        }

        $entry = $cachedentry->get_entry();

        /* get the last-modified-date of this very file */
        $lastModified = strtotime($entry->get_last_edited());
        /* get a unique hash of this file (etag) */
        $etagFile = md5($lastModified);
        /* get the HTTP_IF_MODIFIED_SINCE header if set */
        $ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false);
        /* get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash) */
        $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
        header("Etag: $etagFile");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60 * 5) . ' GMT');
        header('Cache-Control: must-revalidate');

        /* check if page has changed. If not, send 304 and exit */
        if ($cached !== false) {
            if (@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModified || $etagHeader == $etagFile) {

                /* Send email if needed */
                if ($this->get_processor()->get_shortcode_option('notificationdownload') === '1') {
                    $this->get_processor()->send_notification_email('download', array($cachedentry));
                }

                do_action('useyourdrive_download', $cachedentry);

                $event_type = (isset($_REQUEST['action']) && $_REQUEST['action'] === 'useyourdrive-stream') ? 'useyourdrive_streamed_entry' : 'useyourdrive_downloaded_entry';
                do_action('useyourdrive_log_event', $event_type, $cachedentry);

                header("HTTP/1.1 304 Not Modified");
                exit;
            }
        }

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedentry)) {
            die();
        }

        /* Send email if needed */
        if ($this->get_processor()->get_shortcode_option('notificationdownload') === '1') {
            $this->get_processor()->send_notification_email('download', array($cachedentry));
        }

        /* Get the complete file */
        $mimetype = (isset($_REQUEST['mimetype'])) ? $_REQUEST['mimetype'] : 'default';

        $this->download_content($cachedentry, $mimetype);

        die();
    }

    public function download_content(CacheNode $cachedentry, $mimetype = 'default') {
        $entry = $cachedentry->get_entry();

        $extension = isset($_REQUEST['extension']) ? $_REQUEST['extension'] : $entry->get_extension();
        $forcedownload = ($this->get_processor()->get_shortcode_option('forcedownload') === '1' || (isset($_REQUEST['dl']) && $_REQUEST['dl'] === '1')) ? true : false;
        $redirect_user = ($this->get_processor()->get_setting('download_method') === 'redirect');

        $token = json_decode($this->get_library()->getAccessToken());
        //NEW SDK: $token = $this->get_library()->getAccessToken();

        /* Set export mimetype if default isn't available (Google Docs) */
        $direct_download_link = $entry->get_direct_download_link();
        if (empty($direct_download_link) && $mimetype === 'default') {
            $exportlinks = $entry->get_save_as();
            $format = reset($exportlinks);
            $mimetype = $format['mimetype'];
            $extension = $format['extension'];
        }

        if ($mimetype === 'default') {
            $event_type = (isset($_REQUEST['action']) && $_REQUEST['action'] === 'useyourdrive-stream') ? 'useyourdrive_streamed_entry' : 'useyourdrive_downloaded_entry';
            do_action('useyourdrive_log_event', $event_type, $cachedentry);
        } else {
            do_action('useyourdrive_log_event', 'useyourdrive_downloaded_entry', $cachedentry, array('exported' => strtoupper($extension)));
        }

        /* If file is too large, redirect to this url */
        $need_bearer = true;
        if ($entry->get_size() >= 25000000) {
            //$this->download_content_large_file($cachedentry);
            /* Unfortunattely no way to control the file name of the downloaded file... */
            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $entry->get_id() . '?alt=media&userIp=' . $this->_user_ip . "&access_token=" . $token->access_token;
            $need_bearer = false;
        } elseif ($mimetype === 'default') {

            /* Make sure that the file is shared */
            if ($redirect_user) {
                $is_shared = $this->has_permission($cachedentry); //$this->get_processor()->get_setting('manage_permissions') === 'Yes')
                if (!$is_shared) {
                    $is_shared = $this->set_permission($cachedentry);
                }

                /* If File is shared and has binary content (=direct_download_link) */
                if ($is_shared && null !== $direct_download_link) {
                    $downloadlink = $direct_download_link . '&userIp=' . $this->_user_ip;
                    if (!$forcedownload) {
                        $downloadlink = str_replace('export=download', 'export=export', $downloadlink);
                    }
                    do_action('useyourdrive_download', $cachedentry, $downloadlink);
                    header("Location: " . $downloadlink);
                    die();
                }
            }

            /* Else download the file via server */
            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $entry->get_id() . '?alt=media&userIp=' . $this->_user_ip . "&access_token=" . $token->access_token;
        } else {
            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $cachedentry->get_id() . '/export?mimeType=' . urlencode($mimetype) . '&alt=media&userIp=' . $this->_user_ip . "&access_token=" . $token->access_token;
        }

        do_action('useyourdrive_download', $cachedentry, $downloadlink);

        /* Do the request */
        if ($this->get_processor()->get_setting('manage_permissions') === 'Yes') {
            $request = new \UYDGoogle_Http_Request($downloadlink, 'GET');
            $this->get_library()->getIo()->setOptions(array(CURLOPT_FOLLOWLOCATION => false));
            $httpRequest = $this->get_library()->getAuth()->authenticatedRequest($request);
            $headers = $httpRequest->getResponseHeaders();
        } else {
            $request = new \UYDGoogle_Http_Request($downloadlink, 'GET');
            $this->get_library()->getIo()->setOptions(array(CURLOPT_FOLLOWLOCATION => false));
            $httpRequest = $this->get_library()->getAuth()->authenticatedRequest($request);
            $headers = $httpRequest->getResponseHeaders();
            $redirect_user = false;
        }

        if ($redirect_user === false && isset($headers['location'])) {
            $request = new \UYDGoogle_Http_Request($headers['location'], 'GET');
            $this->get_library()->getIo()->setOptions(array(CURLOPT_FOLLOWLOCATION => true));
            $httpRequest = $this->get_library()->getAuth()->authenticatedRequest($request);
            $headers = $httpRequest->getResponseHeaders();
        }
        /* NEW SDK: Do the request */
//    $httpClient = new \GuzzleHttp\Client(array('verify' => false, 'allow_redirects' => false, 'track_redirects' => true));
//
//    if ($need_bearer) {
//      $request = $this->get_library()->authorize($httpClient);
//      $response = $request->get($downloadlink);
//    } else {
//      $request = new \GuzzleHttp\Psr7\Request('GET', $downloadlink);
//      $response = $httpClient->send($request);
//    }
//    
//        $headers = $response->getHeaders();
//
//    if (!in_array($response->getStatusCode(), array(200, 301, 302, 303, 304, 307))) {
//      die();
//    }

        if (isset($headers['transfer-encoding'])) {
            unset($headers['transfer-encoding']);
        }

        if (isset($headers['content-disposition']) || !isset($headers['location'])) {
            //$filename = $cachedentry->get_entry()->get_name();
            //$headers['content-disposition'] = 'attachment; filename="' . $filename . '"; filename*=utf-8\' \'' . rawurlencode($filename);

            if (!$forcedownload) {
                header("content-disposition: " . str_replace('attachment;', '', $headers['content-disposition']));
                //$headers['content-disposition'] =  str_replace('attachment;', '', $headers['content-disposition']);
            }
        }

        if (isset($headers['location'])) {
            header("location: " . $headers['location']);
        }

        if (isset($headers['Location'])) {
            header("location: " . $headers['Location']);
        }

        /* foreach ($headers as $key => $header) {
          if (is_array($header)) {
          header("$key: " . implode(' ', $header));
          } else {
          header("$key: " . str_replace("\n", ' ', $header));
          }
          } */

        echo $httpRequest->getResponseBody(); //$response->getBody();
        die();
    }

    public function download_content_large_file(CacheNode $cachedentry) {
        
    }

    public function stream_entry() {
        /* Check if file is cached and still valid */
        $cached = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

        if ($cached === false) {
            $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
        } else {
            $cachedentry = $cached;
        }

        if ($cachedentry === false) {
            die();
        }

        $entry = $cachedentry->get_entry();

        $extension = $entry->get_extension();
        $allowedextensions = array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga');

        if (empty($extension) || !in_array($extension, $allowedextensions)) {
            die();
        }

        $this->download_entry();
    }

    public function get_embed_url(CacheNode $cachedentry) {

        $entry = $cachedentry->get_entry();
        $mimetype = $entry->get_mimetype();

        /* Check the permissions and set it if possible */
        if (!$this->has_permission($cachedentry)) {
            $this->set_permission($cachedentry);
        }

        if ($entry->get_can_edit_by_cloud() && $this->get_processor()->get_user()->can_download() && ((strpos($mimetype, 'google-apps')) !== false)) {
            $arguments = 'edit?usp=drivesdk';
        } else {
            $arguments = 'preview?rm=minimal';
        }

        switch ($mimetype) {
            case 'application/vnd.google-apps.document':
                $preview = 'https://docs.google.com/document/d/' . $cachedentry->get_id() . '/' . $arguments;
                break;

            case 'application/vnd.google-apps.spreadsheet':
                $preview = 'https://docs.google.com/spreadsheets/d/' . $cachedentry->get_id() . '/' . $arguments;
                break;

            case 'application/vnd.google-apps.presentation':
                $preview = 'https://docs.google.com/presentation/d/' . $cachedentry->get_id() . '/preview'; // . $arguments;
                break;

            case 'application/vnd.google-apps.folder':
                $preview = 'https://drive.google.com/open?id=' . $cachedentry->get_id();
                break;

            case 'application/vnd.google-apps.drawing':
                $preview = 'https://docs.google.com/drawings/d/' . $cachedentry->get_id();
                break;


            default:
                $preview = 'https://docs.google.com/file/d/' . $cachedentry->get_id() . '/' . $arguments;
                break;
        }

        /* For images, just return the actual file */
        if (in_array($cachedentry->get_entry()->get_extension(), array('jpg', 'jpeg', 'gif', 'png'))) {
            $preview = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-embed-image&id=" . $cachedentry->get_id();
        }

        do_action('useyourdrive_set_embed_url', $cachedentry, $preview);

        return $preview;
    }

    public function has_permission(CacheNode $cachedentry, $permission = 'read', $force_update = false) {

        $entry = $cachedentry->get_entry();
        $permission_type = ($this->get_processor()->get_setting('permission_domain') === '') ? 'anyone' : 'domain';
        $permission_domain = ($this->get_processor()->get_setting('permission_domain') === '') ? null : $this->get_processor()->get_setting('permission_domain');


        $users = $entry->get_permission('users');

        /* If the permissions are not yet set, grab them via the API */
        if (empty($users) && $cachedentry->get_entry()->get_permission('canshare') || $force_update === true) {
            $users = array();

            $params = array(
                "fields" => 'kind,nextPageToken,permissions(kind,type,role,domain,teamDrivePermissionDetails(teamDrivePermissionType,role))',
                "pageSize" => 100,
                "supportsTeamDrives" => $this->useteamfolders,
                "userIp" => $this->_user_ip
            );

            $nextpagetoken = null;
            /* Get all files in folder */
            while ($nextpagetoken || $nextpagetoken === null) {
                try {
                    if ($nextpagetoken !== null) {
                        $params['pageToken'] = $nextpagetoken;
                    }

                    $more_permissions = $this->get_app()->get_drive()->permissions->listPermissions($entry->get_id(), $params);
                    $users = array_merge($users, $more_permissions->getPermissions());
                    $nextpagetoken = ($more_permissions->getNextPageToken() !== null) ? $more_permissions->getNextPageToken() : false;
                } catch (\Exception $ex) {
                    error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                    return false;
                }
            }

            $entry_permission = array();
            foreach ($users as $user) {
                $entry_permission[$user->getId()] = array('type' => $user->getType(), 'role' => $user->getRole(), 'domain' => $user->getDomain());
            }
            $entry->set_permissions_by_key('users', $entry_permission);
            $this->get_cache()->add_to_cache($entry);

            $users = $entry->get_permission('users');
        }



        if (count($users) > 0) {

            foreach ($users as $user) {
                if (($user['type'] === $permission_type) && (in_array($user['role'], array('reader', 'writer'))) && ($user['domain'] === $permission_domain)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    public function set_permission(CacheNode $cachedentry, $permission = 'read') {

        $permission_type = ($this->get_processor()->get_setting('permission_domain') === '') ? 'anyone' : 'domain';
        $permission_domain = ($this->get_processor()->get_setting('permission_domain') === '') ? null : $this->get_processor()->get_setting('permission_domain');

        /* Set new permission if needed */
        if ($this->get_processor()->get_setting('manage_permissions') === 'Yes' && $cachedentry->get_entry()->get_permission('canshare')) {
            $newPermission = new \UYDGoogle_Service_Drive_Permission();
            $newPermission->setType($permission_type);
            $newPermission->setRole("reader");
            $newPermission->setAllowFileDiscovery(false);
            if ($permission_domain !== null) {
                $newPermission->setDomain($permission_domain);
            }

            $params = array(
                "supportsTeamDrives" => $this->useteamfolders,
                "userIp" => $this->_user_ip
            );

            try {
                $permission = $this->get_app()->get_drive()->permissions->create($cachedentry->get_id(), $newPermission, $params);
                $cachedentry->is_loaded(false);
                $cachedentry = $this->get_entry($cachedentry->get_id());

                do_action('useyourdrive_log_event', 'useyourdrive_updated_metadata', $cachedentry, array('metadata_field' => 'Sharing Permissions'));

                return true;
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                return false;
            }
        }

        return false;
    }

    /*
     * Create zipfile
     */

    public function download_entries_as_zip() {
        /* Check if file is cached and still valid */
        $cachedfolder = $this->get_folder();


        if ($cachedfolder === false || $cachedfolder['folder'] === false) {
            return new \WP_Error('broke', __("Requested directory isn't allowed", 'useyourdrive'));
        }

        $folder = $cachedfolder['folder']->get_entry();

        /* Check if entry is allowed */
        if (!$this->get_processor()->_is_entry_authorized($cachedfolder['folder'])) {
            return new \WP_Error('broke', __("Requested directory isn't allowed", 'useyourdrive'));
        }

        /* Create upload dir if needed */
        $zip_filename = '_zip_' . basename($folder->get_name()) . '_' . uniqid() . '.zip';

        $json_options = 0;
        if (defined('JSON_PRETTY_PRINT')) {
            $json_options |= JSON_PRETTY_PRINT;  // Supported in PHP 5.4+
        }

        if (isset($_REQUEST['files'])) {
            $dirlisting = array('folders' => array(), 'files' => array(), 'bytes' => 0, 'bytes_total' => 0);

            foreach ($_REQUEST['files'] as $fileid) {
                $cached_file = $this->get_entry($fileid);
                $data = $this->_get_files_recursive($cached_file, '', true);
                $dirlisting['files'] = array_merge($dirlisting['files'], $data['files']);
                $dirlisting['folders'] = array_merge($dirlisting['folders'], $data['folders']);
                $dirlisting['bytes_total'] += $data['bytes_total'];
            }
        } else {
            $dirlisting = $this->_get_files_recursive($cachedfolder['folder']);
        }

        if (count($dirlisting['folders']) > 0 || count($dirlisting['files']) > 0) {

            /* Create zip file */
            if (!function_exists('PHPZip\autoload')) {
                try {
                    require_once "PHPZip/autoload.php";
                } catch (\Exception $ex) {
                    error_log('[Use-your-Drive message]: ' . sprintf('PHPZIP Error on line %s: %s', __LINE__, $ex->getMessage()));
                    return new \WP_Error('broke', __('Something went wrong... See settings page', 'useyourdrive'));
                }
            }
            $zip = new \PHPZip\Zip\Stream\ZipStream($zip_filename);

            /* Add folders */
            if (count($dirlisting['folders']) > 0) {

                foreach ($dirlisting['folders'] as $key => $folder) {
                    $zip->addDirectory($folder);
                    unset($dirlisting['folders'][$key]);
                }
            }

            /* Add files */
            if (count($dirlisting['files']) > 0) {

                $downloadedfiles = array();

                foreach ($dirlisting['files'] as $key => $file) {
                    @set_time_limit(60);

                    /* get file */
                    try {
                        $request = new \UYDGoogle_Http_Request($file['url'], 'GET');
                        $this->get_library()->getIo()->setOptions(array(CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true));
                        $httpRequest = $this->get_library()->getAuth()->authenticatedRequest($request);

                        /* NEW SDK
                         * $httpClient = new \GuzzleHttp\Client(array('verify' => false, 'allow_redirects' => true));
                         * $request = new \GuzzleHttp\Psr7\Request('GET', $file['url']);
                         * $httpRequest = $httpClient->send($request);
                         */
                    } catch (\Exception $ex) {
                        error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
                        continue;
                    }

                    if ($httpRequest->getResponseHttpCode() == 200) {
                        ( ob_get_level() > 0 ) ? ob_flush() : flush();
                        $stream = fopen('php://memory', 'r+');
                        fwrite($stream, $httpRequest->getResponseBody()); //NEW SDK: $response->getBody();
                        rewind($stream);

                        try {
                            $zip->addLargeFile($stream, $file['path']);
                        } catch (\Exception $ex) {
                            error_log('[Use-your-Drive message]: ' . sprintf('Error creating ZIP file %s: %s', __LINE__, $ex->getMessage()));
                        }

                        fclose($stream);
                        $dirlisting['bytes'] += $file['bytes'];
                        unset($dirlisting['files'][$key]);

                        $cachedentry = $this->get_cache()->get_node_by_id($file['ID']);
                        $downloadedfiles[] = $cachedentry;

                        do_action('useyourdrive_log_event', 'useyourdrive_downloaded_entry', $cachedentry, array('as_zip' => true));
                    }
                }
            }

            /* Close zip */
            $result = $zip->finalize();

            /* Send email if needed */
            if ($this->get_processor()->get_shortcode_option('notificationdownload') === '1') {
                $this->get_processor()->send_notification_email('download', $downloadedfiles);
            }

            /* Download Zip Hook */
            do_action('useyourdrive_download_zip', $downloadedfiles);

            die();
        } else {
            die('No files or folders selected');
        }
    }

    private function _get_files_recursive(CacheNode $cached_entry, $currentpath = '', $selection = false, &$dirlisting = array('folders' => array(), 'files' => array(), 'bytes' => 0, 'bytes_total' => 0)) {
        $token = json_decode($this->get_library()->getAccessToken()); // NEW SDK: no decode

        /* Get entry meta data */
        if ($cached_entry !== null && $cached_entry !== false && $cached_entry->has_entry()) {

            $entry = $cached_entry->get_entry();
            /* First add Current Folder/File */
            if ($selection) {
                $continue = true;

                /* Check if entry is allowed */
                if (!$this->get_processor()->_is_entry_authorized($cached_entry)) {
                    $continue = false;
                }

                if ($continue) {
                    $location = $entry->get_name();

                    if ($entry->is_dir()) {
                        $dirlisting['folders'][] = $location;
                        $currentpath = $location;
                    } else {

                        if ($entry->get_direct_download_link() === null) {
                            $formats = $entry->get_save_as();
                            $format = reset($formats);
                            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $entry->get_id() . '/export?mimeType=' . urlencode($format['mimetype']) . '&alt=media&userIp=' . $this->_user_ip;
                            $location .= '.' . $format['extension'];
                        } else {
                            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $entry->get_id() . '?alt=media&userIp=' . $this->_user_ip . "&access_token=" . $token->access_token; // NEW SDK $token->access_token;
                        }

                        $dirlisting['files'][] = array('ID' => $entry->get_id(), 'path' => $location, 'url' => $downloadlink, 'bytes' => $entry->get_size());
                        $dirlisting['bytes_total'] += $entry->get_size();
                    }
                }
            }

            $cached_folder = false;
//If Folder add all children
            if ($entry->is_dir()) {

                /* @var CacheNode */
                $cached_folder = $this->get_folder($entry->get_id());
            }

            if ($cached_folder !== false && $cached_folder['folder'] !== false && $cached_folder['folder']->has_children()) {

                foreach ($cached_folder['folder']->get_children() as $cached_child) {

                    $child = $cached_child->get_entry();

                    /* Check if entry is allowed */
                    if (!$this->get_processor()->_is_entry_authorized($cached_child)) {
                        $continue = false;
                    }

                    $location = ($currentpath === '') ? $child->get_name() : $currentpath . '/' . $child->get_name();

                    if ($child->is_dir()) {
                        $dirlisting['folders'][] = $location;
                        $this->_get_files_recursive($cached_child, $location, false, $dirlisting);
                    } else {
//Get download Link
                        if ($child->get_direct_download_link() === null) {
                            $formats = $child->get_save_as();
                            $format = reset($formats);
                            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $child->get_id() . '/export?mimeType=' . urlencode($format['mimetype']) . '&alt=media&userIp=' . $this->_user_ip;
                            $location .= '.' . $format['extension'];
                        } else {
                            $downloadlink = 'https://www.googleapis.com/drive/v3/files/' . $child->get_id() . '?alt=media&userIp=' . $this->_user_ip . "&access_token=" . $token->access_token;
                        }

                        $dirlisting['files'][] = array('ID' => $child->get_id(), 'path' => $location, 'url' => $downloadlink, 'bytes' => $child->get_size());
                        $dirlisting['bytes_total'] += $child->get_size();
                    }
                }
            }
        }

        return $dirlisting;
    }

    public function create_link(CacheNode $cachedentry = null, $shorten_url = true) {
        $link = false;
        $error = false;
        $shorten = (($this->get_processor()->get_setting('shortlinks') !== 'None') && $shorten_url);

        if (($cachedentry === null)) {
            /* Check if file is cached and still valid */
            $cached = $this->get_cache()->is_cached($this->get_processor()->get_requested_entry());

            /* Get the file if not cached */
            if ($cached === false) {
                $cachedentry = $this->get_entry($this->get_processor()->get_requested_entry());
            } else {
                $cachedentry = $cached;
            }
        }

        $viewlink = false;
        $embedlink = false;

        if ($cachedentry !== null && $cachedentry !== false) {

            $entry = $cachedentry->get_entry();
            $embedurl = $this->get_embed_url($cachedentry);

            /* Build direct link */
            $viewurl = str_replace('edit?usp=drivesdk', 'view', $embedurl);
            $viewurl = str_replace('preview?rm=minimal', 'view', $embedurl);
            $viewurl = str_replace('preview', 'view', $embedurl);
            /* For images, just return the actual file */

            $type = 'iframe';
            /* For images, just return the actual file */
            if (in_array($cachedentry->get_entry()->get_extension(), array('jpg', 'jpeg', 'gif', 'png'))) {
                $type = 'image';
                $viewurl = 'https://docs.google.com/file/d/' . $cachedentry->get_entry()->get_id() . '/view';
            }

            if (!empty($embedurl)) {
                $embedlink = ($shorten) ? $this->shorten_url($entry, $embedurl) : $embedurl;
                $viewlink = ($shorten) ? $this->shorten_url($entry, $viewurl) : $viewurl;
            } else {
                $error = __("Can't create link", 'useyourdrive');
            }
        }

        $resultdata = array(
            'id' => $entry->get_id(),
            'name' => $entry->get_name(),
            'link' => $viewlink,
            'embeddedlink' => $embedlink,
            'type' => $type,
            'size' => Helpers::bytes_to_size_1024($entry->get_size()),
            'error' => $error
        );

        do_action('useyourdrive_created_link', $cachedentry);

        do_action('useyourdrive_log_event', 'useyourdrive_created_link_to_entry', $cachedentry, array('url' => $viewlink));

        return $resultdata;
    }

    public function create_links($shorten = true) {
        $links = array('links' => array());

        foreach ($_REQUEST['entries'] as $entry) {

            $cached = $this->get_cache()->is_cached($entry);

            /* Get the file if not cached or doesn't have permissions yet */
            if ($cached === false) {
                $cachedentry = $this->get_entry($entry);
            } else {
                $cachedentry = $cached;
            }

            $links['links'][] = $this->create_link($cachedentry, $shorten);
        }

        return $links;
    }

    public function shorten_url($entry, $url) {

        try {
            switch ($this->get_processor()->get_setting('shortlinks')) {
                case 'Google';
                    $url = new \UYDGoogle_Service_Urlshortener_Url();
                    $url->setLongUrl($url);
                    $url = $this->get_app()->get_urlshortener()->url->insert($url, array("userIp" => $this->_user_ip));
                    return $url->getId();

                    break;

                case 'Bit.ly';
                    require_once 'bitly/bitly.php';

                    $this->bitly = new \Bitly($this->get_processor()->get_setting('bitly_login'), $this->get_processor()->get_setting('bitly_apikey'));
                    $response = $this->bitly->shorten($url);
                    return $response['url'];

                    break;

                case 'Shorte.st';
                    $request = new \UYDGoogle_Http_Request('https://api.shorte' . '.st/s/' . $this->get_processor()->get_setting('shortest_apikey') . '/' . $url, 'GET');
                    $httpRequest = $this->get_library()->execute($request);
                    return $httpRequest['shortenedUrl'];

                    break;

                case 'Rebrandly';
                    $request = new \UYDGoogle_Http_Request('https://api.rebrandly.com/v1/links', 'POST', array('apikey' => $this->get_processor()->get_setting('rebrandly_apikey'), 'Content-Type' => 'application/json', 'workspace' => $this->get_processor()->get_setting('rebrandly_workspace')), json_encode(array('title' => $entry->get_name(), 'destination' => $url, 'domain' => array('fullName' => $this->get_processor()->get_setting('rebrandly_domain')))));
                    $httpRequest = $this->get_library()->execute($request);
                    return 'https://' . $httpRequest['shortUrl'];

                    break;

                case 'Firebase';

                    //$longDynamicLink = 'https://app_code.app.goo.gl/?link=' . urlencode($url);
                    //$url = $this->get_app()->get_firebase()->url->insert($longDynamicLink, array("userIp" => $this->_user_ip));
                    //$url = $this->get_app()->get_firebase()->url->insert($longDynamicLink, array("userIp" => $this->_user_ip));
                    //return $url->getShortLink();

                    break;

                case 'None':
                default:
                    break;
            }
        } catch (\Exception $ex) {
            error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));
            return $url;
        }

        return $url;
    }

    public function pull_for_changes($change_token = false) {

        /* Load the root folder when needed */
        $root_folder = $this->get_root_folder();

        $params = array(
            "userIp" => $this->_user_ip,
            "supportsTeamDrives" => $this->useteamfolders,
        );

        if (empty($change_token)) {
            try {
                $result = $this->get_app()->get_drive()->changes->getStartPageToken($params);
                $change_token = $result->getStartPageToken();
                return array($change_token, array());
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

                return false;
            }
        }

        $params = array(
            "fields" => $this->apilistchangesfields,
            "pageSize" => 999,
            "userIp" => $this->_user_ip,
            "restrictToMyDrive" => false,
            "includeTeamDriveItems" => $this->useteamfolders,
            "supportsTeamDrives" => $this->useteamfolders,
            "spaces" => 'drive'
        );

        $changes = array();

        while ($change_token != null) {
            try {
                $result = $this->get_app()->get_drive()->changes->listChanges($change_token, $params);
                $change_token = $result->getNextPageToken();

                if ($result->getNewStartPageToken() != null) {
                    // Last page, save this token for the next polling interval
                    $new_change_token = $result->getNewStartPageToken();
                }

                $changes = array_merge($changes, $result->getChanges());
            } catch (\Exception $ex) {
                error_log('[Use-your-Drive message]: ' . sprintf('Google API Error on line %s: %s', __LINE__, $ex->getMessage()));

                return false;
            }
        }

        $list_of_update_entries = array();
        foreach ($changes as $change) {

            /* File is removed */
            if ($change->getRemoved()) {
                $list_of_update_entries[$change->getFileId()] = 'deleted';
            } elseif ($change->getFile()->getTrashed()) {
                $list_of_update_entries[$change->getFileId()] = 'deleted';
            } else {
                /* File is updated */
                $list_of_update_entries[$change->getFileId()] = new Entry($change->getFile());
            }
        }


        return array($new_change_token, $list_of_update_entries);
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
     * @return \TheLion\UseyourDrive\Cache
     */
    public function get_cache() {
        return $this->get_processor()->get_cache();
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app() {
        return $this->_app;
    }

    /**
     * 
     * @return \UYDGoogle_Client
     */
    public function get_library() {
        return $this->_app->get_client();
    }

}
