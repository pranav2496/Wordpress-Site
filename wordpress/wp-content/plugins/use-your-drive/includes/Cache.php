<?php

namespace TheLion\UseyourDrive;

class Cache {

    /**
     *  @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

    /**
     * Contains the location to the cache file
     * @var string 
     */
    private $_cache_location;

    /**
     * Contains the file handle in case the plugin has to work
     * with a file for unlocking/locking
     * @var type 
     */
    private $_cache_file_handle = null;

    /**
     * $_nodes contains all the cached entries that are present
     * in the Cache File or Database
     * @var \TheLion\UseyourDrive\CacheNode[] 
     */
    private $_nodes = array();

    /**
     * ID of the root node
     * @var string 
     */
    private $_root_node_id;

    /**
     * Is set to true when a change has been made in the cache.
     * Forcing the plugin to save the cache when needed
     * @var boolean 
     */
    private $_updated = false;

    /**
     * $_last_update contains a timestamp of the latest check
     * for new updates
     * @var string 
     */
    private $_last_check_for_update = null;

    /**
     * $_last_id contains an ID of the latest update check
     * This can be anything (e.g. a File ID or Change ID), it differs per Cloud Service
     * @var mixed 
     */
    private $_last_check_token = null;

    /**
     * How often do we need to poll for changes? (default: 15 minutes)
     * Each Cloud service has its own optimum setting.
     * WARNING: Please don't lower this setting when you are not using your own Apps!!!
     * @var int 
     */
    private $_max_change_age = 900;

    /**
     * Set after how much time the cached noded should be refreshed.
     * This value can be overwritten by Cloud Service Cache classes
     * Default:  needed for download/thumbnails urls (1 hour?)
     * @var int 
     */
    protected $_max_entry_age = 1800;

    public function __construct(Processor $processor) {
        $this->_processor = $processor;

        $root = $processor->get_shortcode_option('root');

        $this->_cache_name = get_current_blog_id() . '.index';
        $this->_cache_location = USEYOURDRIVE_CACHEDIR . '/' . $this->_cache_name;

        /* Load Cache */
        $this->load_cache();
    }

    public function load_cache() {
        $cache = $this->_read_local_cache('close');

        if (function_exists('gzdecode')) {
            $cache = @gzdecode($cache);
        }

        /* 3: Unserialize the Cache, and reset if it became somehow corrupt */
        if (!empty($cache) && !is_array($cache)) {
            $this->_unserialize_cache($cache);
        }

        /* Set all Parent and Children */
        if (count($this->_nodes) > 0) {
            foreach ($this->_nodes as $id => $node) {
                $this->init_cache_node($node);
            }
        }
    }

    public function init_cache_node($node = array()) {
        $id = $node['_id'];
        $node = $this->_nodes[$id] = new CacheNode($this, $node);

        if ($node->has_parents()) {

            foreach ($node->get_parents() as $key => $parent) {

                if ($parent instanceof CacheNode) {
                    continue;
                }

                $parent_id = $parent;
                $parent_node = isset($this->_nodes[$parent_id]) ? $this->_nodes[$parent_id] : false;

                if (!($parent_node instanceof CacheNode)) {
                    $parent_node = $this->init_cache_node($parent_node);
                }

                if ($parent_node !== false) {
                    $node->set_parent($parent_node);
                }
            }
        }

        if ($node->has_children()) {
            foreach ($node->get_children() as $key => $child) {

                if ($child instanceof CacheNode) {
                    continue;
                }

                $child_id = $child;
                $child_node = isset($this->_nodes[$child_id]) ? $this->_nodes[$child_id] : false;

                if (!($child_node instanceof CacheNode)) {
                    $child_node = $this->init_cache_node($child_node);
                }

                if ($child_node !== false) {
                    $child_node->set_parent($node);
                }
            }
        }
        return $node;
    }

    protected function _read_local_cache($close = false) {

        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $this->_create_local_lock(LOCK_SH);
        }

        clearstatcache();
        rewind($this->_get_cache_file_handle());

        $data = fread($this->_get_cache_file_handle(), filesize($this->get_cache_location()));

        if ($close !== false) {
            $this->_unlock_local_cache();
        }

        return $data;
    }

    protected function _create_local_lock($type) {
        /*  Check if file exists */
        $file = $this->get_cache_location();

        if (!file_exists($file)) {
            @file_put_contents($file, $this->_serialize_cache());

            if (!is_writable($file)) {
                error_log('[Use-your-Drive message]: ' . sprintf('Cache file (%s) is not writable', $file));
                die(sprintf('Cache file (%s) is not writable', $file));
            }
        }

        /* Check if the file is more than 1 minute old. */
        $requires_unlock = ((filemtime($file) + 60) < (time()));

        /* Check if file is already opened and locked in this process */
        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $handle = fopen($file, 'c+');
            if (!is_resource($handle)) {
                error_log('[Use-your-Drive message]: ' . sprintf('Cache file (%s) is not writable', $file));
                die(sprintf('Cache file (%s) is not writable', $file));
            }
            $this->_set_cache_file_handle($handle);
        }

        @set_time_limit(60);

        if (!flock($this->_get_cache_file_handle(), $type)) {
            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that 
             * the previous process died and unlock the file manually
             */
            if ($requires_unlock) {
                $this->_unlock_local_cache();
            }
            /* Try to lock the file again */
            flock($this->_get_cache_file_handle(), LOCK_EX);
        }
        @set_time_limit(60);

        return true;
    }

    protected function _save_local_cache() {
        if (!$this->_create_local_lock(LOCK_EX)) {
            return false;
        }

        $data = $this->_serialize_cache($this);

        ftruncate($this->_get_cache_file_handle(), 0);
        rewind($this->_get_cache_file_handle());

        $result = fwrite($this->_get_cache_file_handle(), $data);

        $this->_unlock_local_cache();
        $this->set_updated(false);
        return true;
    }

    protected function _unlock_local_cache() {
        $handle = $this->_get_cache_file_handle();
        if (!empty($handle)) {
            flock($this->_get_cache_file_handle(), LOCK_UN);
            fclose($this->_get_cache_file_handle());
            $this->_set_cache_file_handle(null);
        }

        clearstatcache();
        return true;
    }

    public function reset_cache() {
        $this->_nodes = array();
        $this->set_last_check_for_update();
        $this->set_last_check_token(null);
        $this->update_cache();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {

            if ($path->getExtension() === 'index') {
                try {
                    @unlink($path->getPathname());
                } catch (\Exception $ex) {
                    
                }
            }
        }

        return true;
    }

    public function update_cache($clear_request_cache = true) {
        if ($this->is_updated()) {

            /* Clear Cached Requests, not needed if we only pulled for updates without receiving any changes */
            if ($clear_request_cache) {
                CacheRequest::clear_local_cache_for_shortcode($this->get_processor()->get_listtoken());
            }

            $saved = $this->_save_local_cache();

            $this->set_updated(false);
        }
    }

    public function is_cached($value, $findby = 'id', $as_parent = false) {

        /* Find the node by ID/NAME */
        $node = null;
        if ($findby === 'id') {
            $node = $this->get_node_by_id($value);
        } elseif ($findby === 'name') {
            $node = $this->get_node_by_name($value);
        }

        /* Return if nothing can be found in the cache */
        if (empty($node)) {
            return false;
        }

        if ($node->get_entry() === null) {
            return false;
        }

        if (!$as_parent && !$node->is_loaded()) {
            return false;
        }

        /* Check if the children of the node are loaded. */
        if (!$as_parent && !$node->has_loaded_children()) {
            return false;
        }

        /* Check if the requested node is expired
         * In that case, unset the node and remove the child nodes
         *  */
        if (!$as_parent && $node->is_expired() && $node->get_id() === $this->get_processor()->get_requested_entry()) {
            if ($node->get_entry()->is_dir()) {
                return $this->get_processor()->get_client()->update_expired_folder($node);
            } else {
                return $this->get_processor()->get_client()->update_expired_entry($node);
            }
        }

        return $node;
    }

    /**
     * 
     * @param \TheLion\UseyourDrive\EntryAbstract $entry
     * @return \TheLion\UseyourDrive\CacheNode
     */
    public function add_to_cache(EntryAbstract $entry) {
        /* Check if entry is present in cache */
        $cached_node = $this->get_node_by_id($entry->get_id());

        /* If entry is not yet present in the cache,
         * create a new Node
         */
        if (($cached_node === false)) {
            $cached_node = $this->add_node($entry);
        }
        $cached_node->set_name($entry->get_name());


        $cached_node->set_updated();
        $this->set_updated();

        /* Set new Expire date */
        if ($entry->is_file()) {
            $cached_node->set_expired(time() + $this->get_max_entry_age());
        } else {
            $cached_node->set_is_dir();
        }

        /* Set new Entry in node */
        $cached_node->set_entry($entry);
        $cached_node->set_loaded(true);

        /* Set Loaded_Children to true if entry isn't a folder */
        if ($entry->is_file()) {
            $cached_node->set_loaded_children(true);
        }

        /* If $entry hasn't parents, it is the root or the entry is only shared with the user */
        if (!$entry->has_parents()) {
            $cached_node->set_parents_found(true);

            if ($entry->is_special_folder() === false) {
                $cached_node->set_parent($this->get_node_by_id('sharedfolder'));
            }

            $this->set_updated();
            return $cached_node;
        }

        /*
         * If parents of $entry doesn't exist in our cache yet, 
         * We need to get it via the API
         */
        $getparents = array();
        foreach ($entry->get_parents() as $parent_id) {

            /* In rare occasions, the plugin is receiving a object instead of and ID) */
            if ($parent_id instanceof \UYDGoogle_Service_Drive_DriveFile) {
                $parent_id = $parent_id->getId();
            }

            $parent_in_tree = $this->is_cached($parent_id, 'id', 'as_parent');
            if ($parent_in_tree === false) {
                $getparents[] = $parent_id;
            }
        }

        if (count($getparents) > 0) {
            $parents = $this->get_processor()->get_client()->get_multiple_entries($getparents);

            foreach ($parents as $parent) {
                if (!($parent instanceof EntryAbstract)) {
                    $parent = new Entry($parent);
                }

                $this->add_to_cache($parent);
            }
        }

        /* Link all parents to $entry.
         */

        foreach ($entry->get_parents() as $parent_id) {
            $parent_in_tree = $this->is_cached($parent_id, 'id', 'as_parent');
            /* Parent does already exists in our cache */
            if ($parent_in_tree !== false) {
                $cached_node->set_parent($parent_in_tree);
                $parent_in_tree->set_updated();
            }
        }

        $cached_node->set_parents_found(true);

        $this->set_updated();

        /* Return the cached Node */
        return $cached_node;
    }

    public function remove_from_cache($entry_id, $reason = 'update', $parent_id = false) {
        $node = $this->get_node_by_id($entry_id);

        if ($node === false) {
            return false;
        }

        $node->set_updated();

        if ($reason === 'update') {
            $node->remove_parents();
        } else if ($reason === 'moved') {
            $node->remove_parents();
        } elseif ($reason === 'deleted') {
            $node->remove_parents();
            $node->delete_cache();
            unset($this->_nodes[$entry_id]);
        }

        $this->set_updated();
        return true;
    }

    /**
     * 
     * @return boolean|\TheLion\UseyourDrive\CacheNode
     */
    public function get_root_node() {
        if (count($this->get_nodes()) === 0) {
            return false;
        }

        return $this->get_node_by_id($this->get_root_node_id());
    }

    public function get_root_node_id() {
        return $this->_root_node_id;
    }

    public function set_root_node_id($id) {
        return $this->_root_node_id = $id;
    }

    public function get_node_by_id($id, $loadoninit = true) {
        if (!isset($this->_nodes[$id])) {
            return false;
        }

        if ($loadoninit && !$this->_nodes[$id]->is_initialized() && $this->_nodes[$id]->is_dir()) {
            $this->_nodes[$id]->load();
        }

        return $this->_nodes[$id];
    }

    public function get_node_by_name($name, $parent = null) {
        if (!$this->has_nodes()) {
            return false;
        }

        /**
         * @var $node \TheLion\UseyourDrive\CacheNode
         */
        foreach ($this->_nodes as $node) {
            if ($node->get_name() === $name) {
                if ($parent === null) {
                    return $node;
                }

                if ($node->is_in_folder($parent->get_id())) {
                    return $node;
                }
            }
        }

        return false;
    }

    public function has_nodes() {
        return (count($this->_nodes) > 0 );
    }

    /**
     * @return \TheLion\UseyourDrive\CacheNode[]
     */
    public function get_nodes() {
        return $this->_nodes;
    }

    public function add_node(EntryAbstract $entry) {
// TODO: Set expire based on Cloud Service
        $cached_node = new CacheNode($this, array(
            '_id' => $entry->get_id(),
            '_name' => $entry->get_name(),
            '_initialized' => true
                )
        );
        return $this->set_node($cached_node);
    }

    public function set_node(CacheNode $node) {
        $id = $node->get_id();
        $this->_nodes[$id] = $node;
        return $this->_nodes[$id];
    }

    public function pull_for_changes($force_update = false, $buffer = 10) {
        $force = (defined('FORCE_REFRESH') ? true : $force_update);

        /* Check if we need to check for updates */
        $current_time = time();
        $update_needed = ($this->get_last_check_for_update() + $this->get_max_change_age());
        if (($current_time < $update_needed) && !$force) {
            return false;
        } elseif ($force === true && ($this->get_last_check_for_update() > $current_time - $buffer)) { // Don't pull again if the request was within $buffer seconds
            return false;
        }

        $result = $this->get_processor()->get_client()->pull_for_changes($this->get_last_check_token());

        if (empty($result)) {
            return false;
        }

        list($new_change_token, $changes) = $result;
        $this->set_last_check_token($new_change_token);
        $this->set_last_check_for_update();

        if (is_array($changes) && count($changes) > 0) {
            $result = $this->_process_changes($changes);

            if (!defined('HAS_CHANGES')) {
                define('HAS_CHANGES', true);
            }

            $this->update_cache();
            return true;
        } else {
            $this->update_cache(false);
        }

        return false;
    }

    private function _process_changes($changes = array()) {

        foreach ($changes as $entry_id => $change) {

            if ($change === 'deleted') {
                $this->remove_from_cache($entry_id, 'deleted');
            } else {
                /* Update cache with new entry */
                $this->remove_from_cache($entry_id, 'update');
                if ($change instanceof EntryAbstract) {
                    $cached_entry = $this->add_to_cache($change);
                } else {
                    error_log('Use-your-Drive: No Valid Entry: ' . print_r($change, true));
                }
            }
        }

        $this->set_updated(true);
    }

    public function is_updated() {
        return $this->_updated;
    }

    public function set_updated($value = true) {
        $this->_updated = (bool) $value;
        return $this->_updated;
    }

    public function get_cache_name() {
        return $this->_cache_name;
    }

    public function get_cache_type() {
        return $this->_cache_type;
    }

    public function get_cache_location() {
        return $this->_cache_location;
    }

    protected function _set_cache_file_handle($handle) {
        return $this->_cache_file_handle = $handle;
    }

    protected function _get_cache_file_handle() {
        return $this->_cache_file_handle;
    }

    public function get_last_check_for_update() {
        return $this->_last_check_for_update;
    }

    public function set_last_check_for_update() {
        $this->_last_check_for_update = time();
        $this->set_updated();
        return $this->_last_check_for_update;
    }

    public function get_last_check_token() {
        return $this->_last_check_token;
    }

    public function set_last_check_token($token) {
        $this->_last_check_token = $token;
        return $this->_last_check_token;
    }

    public function get_max_entry_age() {
        return $this->_max_entry_age;
    }

    public function set_max_entry_age($value) {
        return $this->_max_entry_age = $value;
    }

    public function get_max_change_age() {
        return $this->_max_change_age;
    }

    public function set_max_change_age($value) {
        return $this->_max_change_age = $value;
    }

    public function __destruct() {
        $this->update_cache();
    }

    private function _serialize_cache() {

        $nodes_index = array();
        foreach ($this->_nodes as $id => $node) {
            $nodes_index[$id] = $node->to_index();
        }

        $data = array(
            '_nodes' => $nodes_index,
            '_root_node_id' => $this->_root_node_id,
            '_last_check_token' => $this->_last_check_token,
            '_last_check_for_update' => $this->_last_check_for_update
        );

        $data_str = serialize($data);

        if (function_exists('gzencode')) {
            $data_str = gzencode($data_str);
        }

        return $data_str;
    }

    private function _unserialize_cache($data) {
        $values = unserialize($data);
        if ($values !== false) {
            foreach ($values as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor 
     */
    public function get_processor() {
        return $this->_processor;
    }

}
