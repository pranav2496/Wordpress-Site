<?php

namespace TheLion\UseyourDrive;

class Gallery {

    /**
     *
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;
    private $_search = false;

    public function __construct(Processor $_processor) {
        $this->_processor = $_processor;
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Processor 
     */
    public function get_processor() {
        return $this->_processor;
    }

    public function get_images_list() {

        $this->_folder = $this->get_processor()->get_client()->get_folder();

        if (($this->_folder !== false)) {

            /* Create Image Array */
            $this->imagesarray = $this->createImageArray();

            $this->renderImagesList();
        }
    }

    public function search_image_files() {
        $this->_search = true;
        $input = $_REQUEST['query'];
        $this->_folder = array();
        $this->_folder['contents'] = $this->get_processor()->get_client()->search_by_name($input);

        if (($this->_folder !== false)) {
            //Create Gallery array
            $this->imagesarray = $this->createImageArray();

            $this->renderImagesList();
        }
    }

    public function setFolder($folder) {
        $this->_folder = $folder;
    }

    public function setParentFolder() {
        if ($this->_search === true) {
            return;
        }

        $currentfolder = $this->_folder['folder']->get_entry()->get_id();
        if ($currentfolder !== $this->get_processor()->get_root_folder()) {

            /* Get parent folder from known folder path */
            $cacheparentfolder = $this->get_processor()->get_client()->get_folder($this->get_processor()->get_root_folder());
            $folder_path = $this->get_processor()->get_folder_path();
            $parentid = end($folder_path);
            if ($parentid !== false) {
                $cacheparentfolder = $this->get_processor()->get_client()->get_folder($parentid);
            }

            /* Check if parent folder indeed is direct parent of entry
             * If not, return all known parents */
            $parentfolders = array();
            if ($cacheparentfolder !== false && $cacheparentfolder['folder']->has_children() && array_key_exists($currentfolder, $cacheparentfolder['folder']->get_children())) {
                $parentfolders[] = $cacheparentfolder['folder']->get_entry();
            } else {
                if ($this->_folder['folder']->has_parents()) {
                    foreach ($this->_folder['folder']->get_parents() as $parent) {
                        $parentfolders[] = $parent->get_entry();
                    }
                }
            }
            $this->_parentfolders = $parentfolders;
        }
    }

    public function renderImagesList() {

        // Create HTML Filelist
        $imageslist_html = "";

        if (count($this->imagesarray) > 0) {

            $imageslist_html = "<div class='images image-collage'>";
            foreach ($this->imagesarray as $item) {
                // Render folder div
                if ($item->is_dir()) {
                    $imageslist_html .= $this->renderDir($item);
                }
            }
        }

        $imageslist_html .= $this->renderNewFolder();

        if (count($this->imagesarray) > 0) {
            $i = 0;
            foreach ($this->imagesarray as $item) {

                // Render file div
                if (!$item->is_dir()) {
                    $hidden = (($this->get_processor()->get_shortcode_option('maximages') !== '0') && ($i >= $this->get_processor()->get_shortcode_option('maximages')));
                    $imageslist_html .= $this->renderFile($item, $hidden);
                    $i++;
                }
            }

            $imageslist_html .= "</div>";
        } else {
            if ($this->_search === true) {
                $imageslist_html .= '<div class="no_results">' . __('No files or folders found', 'useyourdrive') . '</div>';
            }
        }



        //Create HTML Filelist title
        $filepath = '';

        $userfolder = $this->get_processor()->get_user_folders()->get_auto_linked_folder_for_user();

        if ($this->_search === true) {
            $filepath = __('Results', 'useyourdrive');
            //} elseif ($this->_userFolder !== false) {
            //    $filepath = "<a href='javascript:void(0)' class='folder' data-id='" . $this->get_processor()->get_root_folder() . "'>" . $userfolder->get_name() . "</a>";
        } else {
            if ($this->get_processor()->get_root_folder() === $this->_folder['folder']->get_entry()->get_id()) {
                $filepath = "<a href='javascript:void(0)' class='folder' data-id='" . $this->_folder['folder']->get_entry()->get_id() . "'><strong>" . $this->get_processor()->get_shortcode_option('root_text') . "</strong></a>";
            } else {

                $parentId = $this->get_processor()->get_root_folder();
                $lastparent = end($this->_parentfolders);
                if ($lastparent !== false) {
                    $parentId = $lastparent->get_id();

                    if ($parentId === $this->get_processor()->get_root_folder() && $userfolder === false) {
                        $title = $this->get_processor()->get_shortcode_option('root_text');
                    } else {
                        $title = $lastparent->get_name();
                        $title = apply_filters('useyourdrive_gallery_entry_text', $title, $lastparent, $this);
                    }

                    $filepath = " <a href='javascript:void(0)' class='folder' data-id='" . $parentId . "'>" . $title . "</a> &laquo; ";
                } else {
                    $filepath = " <a href='javascript:void(0)' class='folder' data-id='" . $parentId . "'>" . __('Back', 'useyourdrive') . "</a> &laquo; ";
                }
                $title = $this->_folder['folder']->get_entry()->get_name();
                $title = apply_filters('useyourdrive_gallery_entry_text', $title, $this->_folder['folder']->get_entry(), $this);
                $filepath .= "<a href='javascript:void(0)' class='folder' data-id='" . $this->_folder['folder']->get_entry()->get_id() . "'><strong>" . $title . "</strong>";
            }
        }

        /* lastFolder contains current folder path of the user */
        $folder_path = $this->get_processor()->get_folder_path();
        if ($this->_search !== true && (end($folder_path) !== $this->_folder['folder']->get_entry()->get_id())) {
            $folder_path[] = $this->_folder['folder']->get_entry()->get_id();
        }

        if ($this->_search === true) {
            $lastFolder = $this->get_processor()->get_last_folder();
            $expires = 0;
        } else {
            $lastFolder = $this->_folder['folder']->get_entry()->get_id();
            $expires = $this->_folder['folder']->get_expired();
        }

        $response = json_encode(array(
            'folderPath' => base64_encode(serialize($folder_path)),
            'lastFolder' => $lastFolder,
            'breadcrumb' => $filepath,
            'html' => $imageslist_html,
            'hasChanges' => defined('HAS_CHANGES'),
            'expires' => $expires));

        if (defined('HAS_CHANGES') === false) {
            $cached_request = new CacheRequest($this->get_processor());
            $cached_request->add_cached_response($response);
        }

        echo $response;
        die();
    }

    public function renderDir($item) {
        $return = "";

        $target_height = $this->get_processor()->get_shortcode_option('targetheight');

        $classmoveable = ($this->get_processor()->get_user()->can_move_folders()) ? 'moveable' : '';
        $isparent = (isset($this->_folder['folder'])) ? $this->_folder['folder']->is_in_folder($item->get_id()) : false;
        $folder_thumbnails = $item->get_folder_thumbnails();
        $has_thumbnails = (isset($folder_thumbnails['expires']) && $folder_thumbnails['expires'] > time());

        if ($isparent) {
            $return .= "<div class='image-container image-folder pf' data-id='" . $item->get_id() . "' data-name='" . $item->get_basename() . "'>";
        } else {
            $loadthumbs = $has_thumbnails ? "" : "loadthumbs";
            $return .= "<div class='image-container image-folder entry $classmoveable $loadthumbs' data-id='" . $item->get_id() . "' data-name='" . $item->get_basename() . "'>";

            $return .= "<div class='entry_edit'>";
            $return .= $this->renderEditItem($item);
            $return .= $this->renderDescription($item);


            if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_folders()) {
                $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
            }
            $return .= "</div>";
        }
        $return .= "<a title='" . $item->get_name() . "'>";

        $return .= "<div class='preloading'></div>";

        $return .= "<img class='preloading image-folder-img' src='" . USEYOURDRIVE_ROOTPATH . "/css/images/transparant.png' data-src='" . plugins_url('css/images/folder.png', dirname(__FILE__)) . "' width='$target_height' height='$target_height' style='width:{$target_height}px !important;height:{$target_height}px !important; '/>";

        if ($has_thumbnails) {

            $iimages = 1;

            foreach ($folder_thumbnails['thumbs'] as $folder_thumbnail) {
                $thumb_url = $item->get_thumbnail_with_size('h' . round($target_height * 1) . '-c-nu', $folder_thumbnail);
                $return .= "<div class='folder-thumb thumb$iimages' style='width:" . $target_height . "px;height:" . $target_height . "px;background-image: url(" . $thumb_url . ")'></div>";
                $iimages++;
            }
        }

        $text = $item->get_name();
        $text = apply_filters('useyourdrive_gallery_entry_text', $text, $item, $this);

        $return .= "<div class='folder-text'>" . ($isparent ? '<strong>' . __('Previous folder', 'useyourdrive') . ' (' . $text . ')</strong>' : $text) . "</div></a>";

        $return .= "</div>\n";

        return $return;
    }

    public function renderFile($item, $hidden = false) {

        $class = ($hidden) ? 'hidden' : '';
        $target_height = $this->get_processor()->get_shortcode_option('targetheight');

        $classmoveable = ($this->get_processor()->get_user()->can_move_files()) ? 'moveable' : '';

        $return = "<div class='image-container $class entry $classmoveable' data-id='" . $item->get_id() . "' data-name='" . $item->get_name() . "'>";

        $return .= "<div class='entry_edit'>";
        $return .= $this->renderEditItem($item);
        $return .= $this->renderDescription($item);

        if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_files()) {
            $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
        }
        $return .= "</div>";

        $thumbnail = 'data-options="thumbnail: \'' . $item->get_thumbnail_small() . '\'"';

        $link = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . urlencode($item->get_id()) . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
        if ($this->get_processor()->get_setting('loadimages') === 'googlethumbnail') {
            $link = $item->get_thumbnail_large();
        }

        $caption_description = ((!empty($item->description)) ? $item->get_description() : $item->get_name());
        $download_url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
        $caption = ($this->get_processor()->get_user()->can_download()) ? '<a href="' . $download_url . '" title="' . __('Download', 'useyourdrive') . '"><i class="fas fa-arrow-circle-down fa-2x" aria-hidden="true"> </i>Download &nbsp</a>' : '';
        //$caption .= $caption_description;
        $caption = apply_filters('useyourdrive_gallery_lightbox_caption', $caption, $item, $this);

        $return .= "<a href='" . $link . "' title='" . $item->get_basename() . "' class='ilightbox-group' data-type='image' $thumbnail rel='ilightbox[" . $this->get_processor()->get_listtoken() . "]' data-caption='$caption'><span class='image-rollover'></span>";

        $return .= "<div class='preloading'></div>";

        $width = $height = $target_height;
        if ($item->get_media('width')) {
            $width = round(($target_height / $item->get_media('height')) * $item->get_media('width'));
        }

        $return .= "<img referrerPolicy='no-referrer' class='preloading $class' src='" . USEYOURDRIVE_ROOTPATH . "/css/images/transparant.png' data-src='" . $item->get_thumbnail_with_size('h' . round($target_height * 1) . '-nu') . "' data-src-retina='" . $item->get_thumbnail_with_size('h' . round($target_height * 2) . '-nu') . "' width='$width' height='$height' style='width:{$width}px !important;height:{$height}px !important; '/>";

        $text = '';
        if ($this->get_processor()->get_shortcode_option('show_filenames') === '1') {
            $text = $item->get_basename();
            $text = apply_filters('useyourdrive_gallery_entry_text', $text, $item, $this);
            $return .= "<div class='entry-text'>" . $text . "</div>";
        }

        $return .= "</a>";
        $return .= "</div>\n";

        return $return;
    }

    public function renderDescription($item) {
        $html = '';

        if (($this->get_processor()->get_shortcode_option('editdescription') === '0') && empty($item->description)) {
            return $html;
        }

        $title = $item->get_basename() . ((($this->get_processor()->get_shortcode_option('show_filesize') === '1') && ($item->get_size() > 0)) ? ' (' . Helpers::bytes_to_size_1024($item->get_size()) . ')' : '&nbsp;');

        $html .= "<a class='entry_description'><i class='fas fa-info-circle fa-lg'></i></a>\n";
        $html .= "<div class='description_textbox'>";

        if ($this->get_processor()->get_user()->can_edit_description()) {
            $html .= "<span class='entry_edit_description'><a class='entry_action_description' data-id='" . $item->get_id() . "'><i class='fas fa-pen-square fa-lg'></i></a></span>";
        }

        $nodescription = ($this->get_processor()->get_user()->can_edit_description()) ? __('Add a description', 'useyourdrive') : __('No description', 'useyourdrive');
        $description = (!empty($item->description)) ? nl2br($item->get_description()) : $nodescription;

        $html .= "<div class='description_title'>$title</div><div class='description_text'>" . $description . "</div>";
        $html .= "</div>";

        return $html;
    }

    public function renderEditItem($item) {
        $html = '';

        $permissions = $item->get_permissions();

        $usercanread = $this->get_processor()->get_user()->can_download();
        $usercanrename = ($item->is_dir()) ? $this->get_processor()->get_user()->can_rename_folders() : $this->get_processor()->get_user()->can_rename_files();
        $usercandelete = ($item->is_dir()) ? $this->get_processor()->get_user()->can_delete_folders() : $this->get_processor()->get_user()->can_delete_files();
        $usercanshare = $permissions['canshare'] && $this->get_processor()->get_user()->can_share();

        $filename = $item->get_basename();
        $filename .= (($this->get_processor()->get_shortcode_option('show_ext') === '1' && !empty($item->extension)) ? '.' . $item->get_extension() : '');

       /* Download */
        if ($usercanread && $item->is_file()) {
            $html .= "<li><a href='" . USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken() . "' download='" . $filename . "' class='entry_action_download' title='" . __('Download', 'useyourdrive') . "'><i class='fas fa-download fa-lg'></i>&nbsp;" . __('Download', 'useyourdrive') . "</a></li>";
        }



        /* Shortlink */
        if ($usercanshare) {
            $html .= "<li><a class='entry_action_shortlink' title='" . __('Share', 'useyourdrive') . "'><i class='fas fa-share-alt fa-lg'></i>&nbsp;" . __('Share', 'useyourdrive') . "</a></li>";
        }

        /* Rename */
        if ($usercanrename) {
            $html .= "<li><a class='entry_action_rename' title='" . __('Rename', 'useyourdrive') . "'><i class='fas fa-tag fa-lg'></i>&nbsp;" . __('Rename', 'useyourdrive') . "</a></li>";
        }

        /* Delete */
        if ($usercandelete) {
            $html .= "<li><a class='entry_action_delete' title='" . __('Delete', 'useyourdrive') . "'><i class='fas fa-trash fa-lg'></i>&nbsp;" . __('Delete', 'useyourdrive') . "</a></li>";
        }

        if ($html !== '') {
            return "<a class='entry_edit_menu'></a><div id='menu-" . $item->get_id() . "' class='entry_action_download'><ul data-id='" . $item->get_id() . "' data-name='" . $item->get_basename() . "'>" . $html . "</ul></div>\n";
        }

        return $html;
    }

    public function renderNewFolder() {
        $html = '';
        if ($this->_search === false) {

            if ($this->get_processor()->get_user()->can_add_folders()) {
                $height = $this->get_processor()->get_shortcode_option('targetheight');
                $html .= "<div class='image-container image-folder image-add-folder grey newfolder'>";
                $html .= "<a title='" . __('Add folder', 'useyourdrive') . "'>";
                $html .= "<img class='preloading' src='" . USEYOURDRIVE_ROOTPATH . "/css/images/transparant.png' data-src='" . plugins_url('css/images/folder.png', dirname(__FILE__)) . "' width='$height' height='$height' style='width:" . $height . "px;height:" . $height . "px;'/>";
                $html .= "<div class='folder-text'>" . __('Add folder', 'useyourdrive') . "</div>";
                $html .= "</a>";
                $html .= "</div>\n";
            }
        }
        return $html;
    }

    public function createImageArray() {
        $imagearray = array();

        $this->setParentFolder();

        //Add folders and files to filelist
        if (count($this->_folder['contents']) > 0) {

            foreach ($this->_folder['contents'] as $node) {

                $child = $node->get_entry();

                /* Check if entry is allowed */
                if (!$this->get_processor()->_is_entry_authorized($node)) {
                    continue;
                }

                /* Check if entry has thumbnail */
                if (!$child->has_own_thumbnail() && $child->is_file()) {
                    continue;
                }

                $imagearray[] = $child;
            }

            $imagearray = $this->get_processor()->sort_filelist($imagearray);
        }

        /* Limit the number of files if needed */
        if ($this->get_processor()->get_shortcode_option('max_files') !== '-1') {
            $imagearray = array_slice($imagearray, 0, $this->get_processor()->get_shortcode_option('max_files'));
        }

        // Add 'back to Previous folder' if needed
        if (isset($this->_folder['folder'])) {
            $folder = $this->_folder['folder']->get_entry();

            $add_parent_folder_item = true;

            if ($this->_search || $folder->get_id() === $this->get_processor()->get_root_folder()) {
                $add_parent_folder_item = false;
            } elseif ($this->get_processor()->get_user()->can_move_files() || $this->get_processor()->get_user()->can_move_folders()) {
                $add_parent_folder_item = true;
            } elseif ($this->get_processor()->get_shortcode_option('show_breadcrumb') === '1') {
                $add_parent_folder_item = false;
            }

            if ($add_parent_folder_item) {

                foreach ($this->_parentfolders as $parentfolder) {
                    array_unshift($filesarray, $parentfolder);
                }
            }
        }

        return $imagearray;
    }

}