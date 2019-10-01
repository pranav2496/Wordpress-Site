<?php

namespace TheLion\UseyourDrive;

class Filebrowser {

    /**
     *
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;
    private $_search = false;
    private $_parentfolders = array();
    private $_layout;

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

    public function get_files_list() {

        $this->_folder = $this->get_processor()->get_client()->get_folder();

        if (($this->_folder !== false)) {
            $this->setLayout();
            $this->filesarray = $this->createFilesArray();
            $this->renderFilelist();
        } else {
            die('Folder is not received');
        }
    }

    public function search_files() {
        $this->_search = true;
        $input = $_REQUEST['query'];
        $this->_folder = array();
        $this->_folder['contents'] = $this->get_processor()->get_client()->search_by_name($input);

        if (($this->_folder !== false)) {
            $this->setLayout();
            $this->filesarray = $this->createFilesArray();

            $this->renderFilelist();
        }
    }

    public function setFolder($folder) {
        $this->_folder = $folder;
    }

    public function setLayout() {

        /* Set layout */
        $this->_layout = $this->get_processor()->get_shortcode_option('filelayout');
        if (isset($_REQUEST['filelayout'])) {
            switch ($_REQUEST['filelayout']) {
                case 'grid':
                    $this->_layout = 'grid';
                    break;
                case 'list':
                    $this->_layout = 'list';
                    break;
            }
        }
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

    public function renderFilelist() {

        /* Create HTML Filelist */
        $filelist_html = "";

        $filelist_html = "<div class='files layout-" . $this->_layout . "'>";
        if (count($this->filesarray) > 0) {

            /* Limit the number of files if needed */
            if ($this->get_processor()->get_shortcode_option('max_files') !== '-1') {
                $this->filesarray = array_slice($this->filesarray, 0, $this->get_processor()->get_shortcode_option('max_files'));
            }

            $hasfilesorfolders = false;

            foreach ($this->filesarray as $item) {
                /* Render folder div */
                if ($item->is_dir()) {
                    if ($this->_layout === 'list') {
                        $filelist_html .= $this->renderDirForList($item);
                    } elseif ($this->_layout === 'grid') {
                        $filelist_html .= $this->renderDirForGrid($item);
                    }


                    if ($item->is_parent_folder() === false) {
                        $hasfilesorfolders = true;
                    }
                }
            }
        }

        if ($this->_search === false && ($this->_folder['folder']->get_entry()->is_special_folder() === false || $this->_folder['folder']->get_entry()->get_special_folder() === 'mydrive')) {
            if ($this->_layout === 'list') {
                $filelist_html .= $this->renderNewFolderForList();
            } elseif ($this->_layout === 'grid') {
                $filelist_html .= $this->renderNewFolderForGrid();
            }
        }

        if (count($this->filesarray) > 0) {
            foreach ($this->filesarray as $item) {
                /* Render files div */
                if ($item->is_file()) {
                    if ($this->_layout === 'list') {
                        $filelist_html .= $this->renderFileForList($item);
                    } elseif ($this->_layout === 'grid') {
                        $filelist_html .= $this->renderFileForGrid($item);
                    }
                    $hasfilesorfolders = true;
                }
            }

            if ($hasfilesorfolders === false) {
                if ($this->get_processor()->get_shortcode_option('show_files') === '1') {
                    $filelist_html .= $this->renderNoResults();
                }
            }
        } else {
            if ($this->get_processor()->get_shortcode_option('show_files') === '1' || $this->_search === true) {
                $filelist_html .= $this->renderNoResults();
            }
        }

        $filelist_html .= "</div>";

        /* Create HTML Filelist title */
        $filepath = '';

        $userfolder = $this->get_processor()->get_user_folders()->get_auto_linked_folder_for_user();

        if ($this->_search === true) {
            $filepath = __('Results', 'useyourdrive');
            //} elseif ($this->_userFolder !== false) {
            //  $filepath = "<a href='javascript:void(0)' class='folder' data-id='" . $this->get_processor()->get_root_folder() . "'>" . $this->_userFolder->get_name() . "</a>";
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
                    }

                    $filepath = " <a href='javascript:void(0)' class='folder' data-id='" . $parentId . "'>" . $title . "</a> &laquo; ";
                } else {
                    $filepath = " <a href='javascript:void(0)' class='folder' data-id='" . $parentId . "'>" . __('Back', 'useyourdrive') . "</a> &laquo; ";
                }

                $filepath .= "<a href='javascript:void(0)' class='folder' data-id='" . $this->_folder['folder']->get_entry()->get_id() . "'><strong>" . $this->_folder['folder']->get_entry()->get_name() . "</strong>";

                $filepath .= "</a>";
            }
        }

        $raw_path = '';
        if (($this->_search !== true) && (current_user_can('edit_posts') || current_user_can('edit_pages')) && (get_user_option('rich_editing') == 'true')) {
            $raw_path = $this->_folder['folder']->get_entry()->get_name();
        }

        $folder_path = $this->get_processor()->get_folder_path();
        /* lastFolder contains current folder path of the user */
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
            'rawpath' => $raw_path,
            'folderPath' => base64_encode(serialize($folder_path)),
            'lastFolder' => $lastFolder,
            'breadcrumb' => $filepath,
            'html' => $filelist_html,
            'hasChanges' => defined('HAS_CHANGES'),
            'expires' => $expires));

        if (defined('HAS_CHANGES') === false) {
            $cached_request = new CacheRequest($this->get_processor());
            $cached_request->add_cached_response($response);
        }

        echo $response;
        die();
    }

    public function renderNoResults() {
        $html = '';

        $_img = $this->get_processor()->get_setting('loaders');

        if ($this->_layout === 'list') {
            $html .= '
  <div class="entry folder no-entries">
<div class="entry_icon">
<img src="' . $_img['no_results'] . '" ></div>
<div class="entry_name"><a class="entry_link">' . __('No files or folders found', 'useyourdrive') . '</a></div></div>
';
        } else {
            $html .= '<div class="entry file no-entries">
<div class="entry_block">
<div class="entry_thumbnail"><div class="entry_thumbnail-view-bottom"><div class="entry_thumbnail-view-center">
<a class="entry_link"><img class="preloading" src="' . USEYOURDRIVE_ROOTPATH . '/css/images/transparant.png" data-src="' . $_img['no_results'] . '" data-src-retina="' . $_img['no_results'] . '"></a></div></div></div>
<div class="entry_name"><a class="entry_link"><div class="entry-name-view"><span><strong>' . __('No files or folders found', 'useyourdrive') . '</strong></span></div></a></div>
</div>
</div>';
        }

        return $html;
    }

    public function renderDirForList(EntryAbstract $item) {
        $return = '';

        $classmoveable = ($this->get_processor()->get_user()->can_move_folders()) ? 'moveable' : '';
        $isparent = (isset($this->_folder['folder'])) ? $this->_folder['folder']->is_in_folder($item->get_id()) : false;

        $return .= "<div class='entry $classmoveable folder " . ($isparent ? 'pf' : '') . "' data-id='" . $item->get_id() . "' data-name='" . htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, "UTF-8") . "'>\n";
        $return .= "<div class='entry_icon'><img src='" . $item->get_icon() . "'/></div>";

        if (!$isparent) {
            if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_folders()) {
                $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
            }


            if ($this->get_processor()->get_shortcode_option('mcepopup') === 'links') {
                $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
            }

            $return .= "<div class='entry_edit'>";
            $return .= $this->renderDescription($item);
            $return .= $this->renderEditItem($item);
            $return .= "</div>";
        }

        $return .= "<div class='entry_name'><a class='entry_link' title='{$item->get_basename()}'>" . ($isparent ? '<strong>' . __('Previous folder', 'useyourdrive') . ' (' . $item->get_name() . ')</strong>' : $item->get_name()) . "</a></div>";

        $return .= "</div>\n";
        return $return;
    }

    public function renderDirForGrid(EntryAbstract $item) {
        $return = '';

        $classmoveable = ($this->get_processor()->get_user()->can_move_folders()) ? 'moveable' : '';
        $isparent = (isset($this->_folder['folder'])) ? $this->_folder['folder']->is_in_folder($item->get_id()) : false;

        $return .= "<div class='entry $classmoveable folder " . ($isparent ? 'pf' : '') . "' data-id='" . $item->get_id() . "' data-name='" . htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, "UTF-8") . "'>\n";
        if (!$isparent) {
            if ($this->get_processor()->get_shortcode_option('mcepopup') === 'linkto' || $this->get_processor()->get_shortcode_option('mcepopup') === 'linktobackendglobal') {
                $return .= "<div class='entry_linkto'>\n";
                $return .= "<span>" . "<input class='button-secondary' type='submit' title='" . __('Select folder', 'useyourdrive') . "' value='" . __('Select folder', 'useyourdrive') . "'>" . '</span>';
                $return .= "</div>";
            }

            if ($this->get_processor()->get_shortcode_option('mcepopup') === 'woocommerce') {
                $return .= "<div class='entry_woocommerce_link'>\n";
                $return .= "<span>" . "<input class='button-secondary' type='button' title='" . __('Select folder', 'useyourdrive') . "' value='" . __('Select folder', 'useyourdrive') . "'>" . '</span>';
                $return .= "</div>";
            }
        }

        $return .= "<div class='entry_block'>\n";

        if (!$isparent) {
            $return .= "<div class='entry_edit'>";

            if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_folders()) {
                $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
            }

            if (($this->get_processor()->get_shortcode_option('mcepopup') === 'links')) {
                $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
            }

            $return .= $this->renderEditItem($item);
            $return .= $this->renderDescription($item);
            $return .= "</div>";
        }

        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $return .= "<a class='entry_link' title='{$item->get_basename()}'><div class='preloading'></div><img class='preloading' src='" . USEYOURDRIVE_ROOTPATH . "/css/images/transparant.png' data-src='" . $item->get_thumbnail_small() . "' data-src-retina='" . $item->get_thumbnail_small() . "'/></a>";
        $return .= "</div></div></div>\n";
        $return .= "<div class='entry_name'><a class='entry_link' title='{$item->get_basename()}'><div class='entry-name-view'><span>";

        $return .= (($isparent) ? '<strong>' . __('Previous folder', 'useyourdrive') . ' (' . $item->get_name() . ')</strong>' : $item->get_name()) . " </span></div></a>";
        $return .= "</div>\n";
        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderFileForList(EntryAbstract $item) {
        $return = '';
        $classmoveable = ($this->get_processor()->get_user()->can_move_files()) ? 'moveable' : '';

        $thumbnail_small = (strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail') === false) ? $item->get_thumbnail_small() : $item->get_thumbnail_small() . '&listtoken=' . $this->get_processor()->get_listtoken();
        $return .= "<div class='entry file $classmoveable' data-id='" . $item->get_id() . "' data-name='" . htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, "UTF-8") . "' " . ((!empty($thumbnail_small)) ? "data-tooltip=''" : '') . ">\n";
        $return .= "<div class='entry_icon'><img src='" . $item->get_icon() . "'/></div>";

        $link = $this->renderFileNameLink($item);
        $title = $link['filename'] . ((($this->get_processor()->get_shortcode_option('show_filesize') === '1') && ($item->get_size() > 0)) ? ' (' . Helpers::bytes_to_size_1024($item->get_size()) . ')' : '&nbsp;');

        if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_files()) {
            $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
        }

        if ((in_array($this->get_processor()->get_shortcode_option('mcepopup'), array('links', 'embedded')))) {
            $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
        }

        $return .= "<div class='entry_edit_placheholder'><div class='entry_edit'>";
        $return .= $this->renderDescription($item);
        $return .= $this->renderEditItem($item);
        $return .= "</div></div>";

        $caption_description = '';
        if (strpos($item->get_mimetype(), 'video') === false) {
            $caption_description = ((!empty($item->description)) ? $item->get_description() : $item->get_name());
        }
        $download_url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
        $caption = ($this->get_processor()->get_user()->can_download()) ? '<a href="' . $download_url . '" title="' . __('Download', 'useyourdrive') . '" download="' . $item->get_name() . '"><i class="fas fa-arrow-circle-down" aria-hidden="true"></i></a>&nbsp' : '';
        $caption .= $caption_description;

        $add_caption = true;
        if (in_array($item->get_extension(), array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga'))) {
            /* Don't overlap the player controls with the caption */
            $add_caption = false;
        }

        $return .= "<a " . $link['url'] . " " . $link['target'] . " class='entry_link " . $link['class'] . "' title='$title' " . $link['lightbox'] . " data-filename='" . $link['filename'] . "' " . (($add_caption) ? "data-caption='$caption'" : '') . " {$link['extra_attr']}>";

        if ($this->get_processor()->get_shortcode_option('show_filesize') === '1') {
            $size = ($item->get_size() > 0) ? Helpers::bytes_to_size_1024($item->get_size()) : '&nbsp;';
            $return .= "<div class='entry_size'>" . $size . "</div>";
        }

        if ($this->get_processor()->get_shortcode_option('show_filedate') === '1') {
            $return .= "<div class='entry_lastedit'>" . $item->get_last_edited_str() . "</div>";
        }

        if (!empty($thumbnail_small)) {
            $return .= "<div class='description_textbox'>";
            $return .= "<img src='" . $thumbnail_small . "' width='150'>";
            $return .= "</div>";
        }

        $return .= "<div class='entry_name'>" . $link['filename'];

        if (($this->get_processor()->get_shortcode_option('mcepopup') === 'shortcode') && (in_array($item->get_extension(), array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga')))) {
            $return .= "&nbsp;<a class='entry_media_shortcode'><i class='fas fa-code'></i></a>";
        }

        if ($this->_search === true) {
            $return .= "<div class='entry_foundpath'>" . $item->get_path() . "</div>";
        }

        $return .= "</div>";
        $return .= "</a>";

        $return .= $link['lightbox_inline'];

        $return .= "</div>\n";

        return $return;
    }

    public function renderFileForGrid(EntryAbstract $item) {
        $link = $this->renderFileNameLink($item);
        $title = $link['filename'] . ((($this->get_processor()->get_shortcode_option('show_filesize') === '1') && ($item->get_size() > 0)) ? ' (' . Helpers::bytes_to_size_1024($item->get_size()) . ')' : '&nbsp;');

        $classmoveable = ($this->get_processor()->get_user()->can_move_files()) ? 'moveable' : '';

        $return = '';
        $return .= "<div class='entry file $classmoveable' data-id='" . $item->get_id() . "' data-name='" . htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, "UTF-8") . "'>\n";
        $return .= "<div class='entry_block'>\n";

        $return .= "<div class='entry_edit'>";

        if ($this->get_processor()->get_user()->can_download_zip() || $this->get_processor()->get_user()->can_delete_files()) {
            $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
        }

        if ((in_array($this->get_processor()->get_shortcode_option('mcepopup'), array('links', 'embedded')))) {
            $return .= "<div class='entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='" . $item->get_id() . "'/></div>";
        }

        $return .= $this->renderEditItem($item);
        $return .= $this->renderDescription($item);
        $return .= "</div>";

        $caption_description = '';
        if (strpos($item->get_mimetype(), 'video') === false) {
            $caption_description = ((!empty($item->description)) ? $item->get_description() : $item->get_name());
        }
        $download_url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
        $caption = ($this->get_processor()->get_user()->can_download()) ? '<a href="' . $download_url . '" title="' . __('Download', 'useyourdrive') . '"><i class="fas fa-arrow-circle-down" aria-hidden="true"></i></a>&nbsp' : '';
        $caption .= $caption_description;

        $add_caption = true;
        if (in_array($item->get_extension(), array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga'))) {
            /* Don't overlap the player controls with the caption */
            $add_caption = false;
        }

        $return .= "<a " . $link['url'] . " " . $link['target'] . " class='entry_link " . $link['class'] . "' " . $link['onclick'] . " title='" . $title . "' " . $link['lightbox'] . " data-filename='" . $link['filename'] . "' " . (($add_caption) ? "data-caption='$caption'" : '') . " {$link['extra_attr']} >";

        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $thumbnail_small = (strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail') === false) ? $item->get_thumbnail_with_size('w400-h300-p-k') : $item->get_thumbnail_small() . '&listtoken=' . $this->get_processor()->get_listtoken();
        $return .= "<div class='preloading'></div>";
        $return .= "<img class='preloading' src='" . USEYOURDRIVE_ROOTPATH . "/css/images/transparant.png' data-src='" . $thumbnail_small . "' data-src-retina='" . $thumbnail_small . "' data-src-backup='" . $item->get_default_thumbnail_icon() . "'/>";
        $return .= "</div></div></div>\n";

        $return .= "<div class='entry_name'>";

        $return .= "<div class='entry-name-view'><span>" . $link['filename'] . "</span></div>";
        $return .= "</div>\n";
        $return .= "</a>\n";

        $return .= $link['lightbox_inline'];

        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderFileNameLink(EntryAbstract $item) {
        $class = '';
        $url = '';
        $target = '';
        $onclick = '';
        $datatype = 'iframe';
        $lightbox_inline = '';
        $extra_attr = '';

        $permissions = $item->get_permissions();
        $usercanpreview = ($permissions['canpreview']) && $this->get_processor()->get_shortcode_option('forcedownload') === '0';
        $usercanread = $this->get_processor()->get_user()->can_download();

        /* If we don't need to create a link */
        if (($this->get_processor()->get_shortcode_option('mcepopup') !== '0') || (!$usercanpreview)) {
            if ($usercanread) {
                $url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
                $class = 'entry_action_download';
                $extra_attr = "download='{$item->get_name()}'";
            }

            if ($this->get_processor()->get_shortcode_option('mcepopup') === 'woocommerce') {
                $class = 'entry_woocommerce_link';
            }

            /* No Url */
        } elseif ($usercanread && $this->get_processor()->get_shortcode_option('forcedownload') === '1') {
            /* If is set to force download */
            $url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
            $class = 'entry_action_download';
            $extra_attr = "download='{$item->get_name()}'";
        } elseif (($usercanread && !$item->get_can_preview_by_cloud())) {
            /* If the file doesn't have a preview */
            $url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
            $class = 'entry_action_download';
            $extra_attr = "download='{$item->get_name()}'";

            /* If file is image */
            if (in_array($item->get_extension(), array('jpg', 'jpeg', 'gif', 'png'))) {
                $class = 'ilightbox-group';
                $datatype = 'image';
                $extra_attr = '';

                if ($this->get_processor()->get_setting('loadimages') === 'googlethumbnail') {
                    $url = $item->get_thumbnail_large();
                }
            } else if (in_array($item->get_extension(), array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga'))) {
                //$datatype = 'inline';
                //$url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-stream&id=" . $item->get_id() . "&dl=0&listtoken=" . $this->get_processor()->get_listtoken();
            }
        } elseif ($usercanpreview && $item->get_can_preview_by_cloud()) {
            /* If user can't dowload a file or can preview and file can be previewd */
            $url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-preview&id=" . urlencode($item->get_id()) . "&listtoken=" . $this->get_processor()->get_listtoken();
            $onclick = "sendDriveGooglePageView('Preview', '" . $item->get_basename() . ((!empty($item->extension)) ? '.' . $item->get_extension() : '') . "');";
            $class = 'ilightbox-group';

            /* If file is image */
            if (in_array($item->get_extension(), array('jpg', 'jpeg', 'gif', 'png'))) {
                $datatype = 'image';

                if ($this->get_processor()->get_setting('loadimages') === 'googlethumbnail') {
                    $url = $item->get_thumbnail_large();
                }
            } else if (in_array($item->get_extension(), array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga'))) {
                //$datatype = 'inline';
                //$url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-stream&id=" . $item->get_id() . "&dl=0&listtoken=" . $this->get_processor()->get_listtoken();
            }

            /* Overwrite if preview inline is disabled */
            if ($this->get_processor()->get_shortcode_option('previewinline') === '0') {
                $onclick = "sendDriveGooglePageView('Preview (new window)', '" . $item->get_basename() . ((!empty($item->extension)) ? '.' . $item->get_extension() : '') . "');";
                $class = 'entry_action_external_view';
                $target = "_blank";
            }
        } else {
            /* No Url */
        }

        $filename = $item->get_basename();
        $filename .= (($this->get_processor()->get_shortcode_option('show_ext') === '1' && !empty($item->extension)) ? '.' . $item->get_extension() : '');

        /* Lightbox Settings */
        $lightbox = "rel='ilightbox[" . $this->get_processor()->get_listtoken() . "]' ";
        $lightbox .= 'data-type="' . $datatype . '"';

        $thumbnail_small = (strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail') === false) ? $item->get_thumbnail_small() : $item->get_thumbnail_small() . '&listtoken=' . $this->get_processor()->get_listtoken();
        if ($datatype === 'iframe') {
            $lightbox .= 'data-options="thumbnail: \'' . $thumbnail_small . '\', width: \'85%\', height: \'80%\', mousewheel: false"';
        } elseif ($datatype === 'inline') {
            $id = 'ilightbox_' . $this->get_processor()->get_listtoken() . '_' . md5($item->get_id());
            $html5_element = (strpos($item->get_mimetype(), 'video') === false) ? 'audio' : 'video';
            $lightbox .= ' data-options="mousewheel: false, width: \'85%\', height: \'85%\', thumbnail: \'' . $thumbnail_small . '\'"';
            $thumbnail = ($html5_element === 'audio') ? '<div class="html5_player_thumbnail"><img src="' . $item->get_thumbnail_large() . '"/><h3>' . $item->get_basename() . '</h3></div>' : '';
            $download = ($usercanread) ? '' : 'controlsList="nodownload"';
            $lightbox_inline = '<div id="' . $id . '" class="html5_player" style="display:none;"><div class="html5_player_container"><div style="width:100%"><' . $html5_element . ' controls ' . $download . ' preload="metadata"  poster="' . $item->get_thumbnail_large() . '"> <source data-src="' . $url . '" type="' . $item->get_mimetype() . '">' . __('Your browser does not support HTML5. You can only download this file', 'useyourdrive') . '</' . $html5_element . '></div>' . $thumbnail . '</div></div>';
            $url = '#' . $id;
        } else {
            $lightbox .= 'data-options="thumbnail: \'' . $thumbnail_small . '\'"';
        }

        if (!empty($url)) {
            $url = "href='" . $url . "'";
        }
        if (!empty($target)) {
            $target = "target='" . $target . "'";
        }
        if (!empty($onclick)) {
            $onclick = 'onclick="' . $onclick . '"';
        }

        /* Return Values */
        return array('filename' => htmlspecialchars($filename, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, "UTF-8"), 'class' => $class, 'url' => $url, 'lightbox' => $lightbox, 'lightbox_inline' => $lightbox_inline, 'target' => $target, 'onclick' => $onclick, 'extra_attr' => $extra_attr);
    }

    public function renderDescription(EntryAbstract $item) {
        $html = '';


        if ($item->is_special_folder()) {
            return $html;
        }


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

    public function renderEditItem(EntryAbstract $item) {
        $html = '';

        if ($item->is_special_folder()) {
            return $html;
        }

        $permissions = $item->get_permissions();

        $usercanpreview = $permissions['canpreview'];
        $usercanshare = $permissions['canshare'] && $this->get_processor()->get_user()->can_share();
        $usercanread = $this->get_processor()->get_user()->can_download();
        $usercanrename = ($item->is_dir()) ? $this->get_processor()->get_user()->can_rename_folders() : $this->get_processor()->get_user()->can_rename_files();
        $usercandelete = ($item->is_dir()) ? $this->get_processor()->get_user()->can_delete_folders() : $this->get_processor()->get_user()->can_delete_files();

        $filename = $item->get_basename();
        $filename .= (($this->get_processor()->get_shortcode_option('show_ext') === '1' && !empty($item->extension)) ? '.' . $item->get_extension() : '');

        /* View */
        if (($usercanpreview) && $this->get_processor()->get_shortcode_option('forcedownload') !== '1' && ($item->is_file()) && !($item->get_extension() === 'zip')) {
            if (($this->get_processor()->get_shortcode_option('previewinline') === '1')) {
                $html .= "<li><a class='entry_action_view' title='" . __('Preview', 'useyourdrive') . "'><i class='fas fa-eye fa-lg'></i>&nbsp;" . __('Preview', 'useyourdrive') . "</a></li>";
            }

            if ($item->get_can_preview_by_cloud() && $usercanread) {
                $url = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-preview&id=" . urlencode($item->get_id()) . "&listtoken=" . $this->get_processor()->get_listtoken();
                $onclick = "sendDriveGooglePageView('Preview (new window)', '" . $item->get_basename() . ((!empty($item->extension)) ? '.' . $item->get_extension() : '') . "');";
                $html .= "<li><a href='$url' target='_blank' class='entry_action_external_view' onclick=\"$onclick\" title='" . __('Preview in new window', 'useyourdrive') . "'><i class='fas fa-desktop fa-lg'></i>&nbsp;" . __('Preview in new window', 'useyourdrive') . "</a></li>";
            }
        }

        /* Download */
        if (($usercanread) && ($item->is_file()) && (count($item->get_save_as()) === 0)) {
            $html .= "<li><a href='" . USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken() . "' class='entry_action_download' download='" . $item->get_name() . "' data-filename='" . $filename . "' title='" . __('Download', 'useyourdrive') . "'><i class='fas fa-download fa-lg'></i>&nbsp;" . __('Download', 'useyourdrive') . "</a></li>";
        } elseif (($usercanread) && ($item->is_file()) && (count($item->get_save_as()) > 0)) {
            /* Exportformats */
            if (count($item->get_save_as()) > 0) {
                foreach ($item->get_save_as() as $name => $exportlinks) {
                    $html .= "<li><a href='" . USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-download&id=" . $item->get_id() . "&dl=1&mimetype=" . $exportlinks['mimetype'] . "&extension=" . $exportlinks['extension'] . "&listtoken=" . $this->get_processor()->get_listtoken() . "' class='entry_action_export' download='" . $item->get_name() . "' data-filename='" . $filename . "'><i class='fa " . $exportlinks['icon'] . " fa-lg'></i>&nbsp;" . __('Download as', 'useyourdrive') . " " . $name . "</a>";
                }
            }
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
        if ($usercandelete && $item->get_permission('candelete')) {
            $html .= "<li><a class='entry_action_delete' title='" . __('Delete', 'useyourdrive') . "'><i class='fas fa-trash fa-lg'></i>&nbsp;" . __('Delete', 'useyourdrive') . "</a></li>";
        }

        if ($html !== '') {
            return "<a class='entry_edit_menu'><i class='fas fa-chevron-circle-down fa-lg'></i></a><div id='menu-" . $item->get_id() . "' class='uyd-dropdown-menu'><ul data-id='" . $item->get_id() . "' data-name='" . $item->get_basename() . "'>" . $html . "</ul></div>\n";
        }

        return $html;
    }

    public function renderNewFolderForList() {
        $html = '';
        if ($this->_search === false) {

            if ($this->get_processor()->get_user()->can_add_folders()) {
                $html .= "<div class='entry folder newfolder'>";
                $html .= "<div class='entry_icon'><i class='fas fa-plus-circle' aria-hidden='true'></i></div>";
                $html .= "<div class='entry_name'>" . __('Add folder', 'useyourdrive') . "</div>";
                $html .= "<div class='entry_description'>" . __('Add a new folder in this directory', 'useyourdrive') . "</div>";
                $html .= "</div>";
            }
        }
        return $html;
    }

    public function renderNewFolderForGrid() {
        $return = '';
        if ($this->_search === false) {

            if ($this->get_processor()->get_user()->can_add_folders()) {

                $icon_set = $this->get_processor()->get_setting('icon_set');

                $return .= "<div class='entry folder newfolder'>\n";
                $return .= "<div class='entry_block'>\n";
                $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
                $return .= "<a class='entry_link'><img class='preloading' src='" . USEYOURDRIVE_ROOTPATH . "/css/images/transparant.png' data-src='" . $icon_set . "icon_10_addfolder_xl128.png' /></a>";
                $return .= "</div></div></div>\n";
                $return .= "<div class='entry_name'><a class='entry_link'><div class='entry-name-view'><span>" . __('Add folder', 'useyourdrive') . "</span></div></a>";
                $return .= "</div>\n";
                $return .= "</div>\n";
                $return .= "</div>\n";
            }
        }
        return $return;
    }

    public function createFilesArray() {
        $filesarray = array();

        $this->setParentFolder();

//Add folders and files to filelist
        if (count($this->_folder['contents']) > 0) {

            foreach ($this->_folder['contents'] as $node) {

                /* Check if entry is allowed */
                if (!$this->get_processor()->_is_entry_authorized($node)) {
                    continue;
                } else {
                    $filesarray[] = $node->get_entry();
                }
            }

            $filesarray = $this->get_processor()->sort_filelist($filesarray);
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

        return $filesarray;
    }

}
