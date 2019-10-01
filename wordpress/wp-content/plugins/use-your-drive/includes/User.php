<?php

namespace TheLion\UseyourDrive;

class User {

    /**
     *
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;
    private $_can_view = false;
    private $_can_download = false;
    private $_can_download_zip = false;
    private $_can_delete_files = false;
    private $_can_delete_folders = false;
    private $_can_rename_files = false;
    private $_can_rename_folders = false;
    private $_can_add_folders = false;
    private $_can_upload = false;
    private $_can_move_files = false;
    private $_can_move_folders = false;
    private $_can_share = false;
    private $_can_edit_description = false;

    public function __construct(\TheLion\UseyourDrive\Processor $_processor = null) {
        $this->_processor = $_processor;
        $this->_can_view = Helpers::check_user_role($this->get_processor()->get_shortcode_option('view_role'));

        if ($this->can_view() === false) {
            return;
        }

        $this->_can_download = Helpers::check_user_role($this->get_processor()->get_shortcode_option('download_role'));
        $this->_can_download_zip = ($this->get_processor()->get_shortcode_option('can_download_zip') === '1') && $this->can_download();

        if ($this->get_processor()->get_shortcode_option('delete') === '1') {
            $this->_can_delete_files = Helpers::check_user_role($this->get_processor()->get_shortcode_option('delete_files_role'));
            $this->_can_delete_folders = Helpers::check_user_role($this->get_processor()->get_shortcode_option('delete_folders_role'));
        }

        if ($this->get_processor()->get_shortcode_option('rename') === '1') {
            $this->_can_rename_files = Helpers::check_user_role($this->get_processor()->get_shortcode_option('rename_files_role'));
            $this->_can_rename_folders = Helpers::check_user_role($this->get_processor()->get_shortcode_option('rename_folders_role'));
        }

        $this->_can_add_folders = ($this->get_processor()->get_shortcode_option('addfolder') === '1') && Helpers::check_user_role($this->get_processor()->get_shortcode_option('addfolder_role'));
        $this->_can_upload = ($this->get_processor()->get_shortcode_option('upload') === '1') && Helpers::check_user_role($this->get_processor()->get_shortcode_option('upload_role'));

        if ($this->get_processor()->get_shortcode_option('move') === '1') {
            $this->_can_move_files = Helpers::check_user_role($this->get_processor()->get_shortcode_option('move_files_role'));
            $this->_can_move_folders = Helpers::check_user_role($this->get_processor()->get_shortcode_option('move_folders_role'));
        }


        $this->_can_share = ($this->get_processor()->get_shortcode_option('show_sharelink') === '1') && Helpers::check_user_role($this->get_processor()->get_shortcode_option('share_role'));
        $this->_can_edit_description = ($this->get_processor()->get_shortcode_option('editdescription') === '1') && Helpers::check_user_role($this->get_processor()->get_shortcode_option('editdescription_role'));
    }

    public function can_view() {
        return $this->_can_view;
    }

    public function can_download() {
        return $this->_can_download;
    }

    public function can_download_zip() {
        return $this->_can_download_zip;
    }

    public function can_delete_files() {
        return $this->_can_delete_files;
    }

    public function can_delete_folders() {
        return $this->_can_delete_folders;
    }

    public function can_rename_files() {
        return $this->_can_rename_files;
    }

    public function can_rename_folders() {
        return $this->_can_rename_folders;
    }

    public function can_add_folders() {
        return $this->_can_add_folders;
    }

    public function can_upload() {
        return $this->_can_upload;
    }

    public function can_move_files() {
        return $this->_can_move_files;
    }

    public function can_move_folders() {
        return $this->_can_move_folders;
    }

    public function can_share() {
        return $this->_can_share;
    }

    public function can_edit_description() {
        return $this->_can_edit_description;
    }

    public function get_permissions_hash() {
        $data = get_object_vars($this);
        unset($data['_processor']);

        $data = json_encode($data);
        return md5($data);
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor() {
        return $this->_processor;
    }

}
