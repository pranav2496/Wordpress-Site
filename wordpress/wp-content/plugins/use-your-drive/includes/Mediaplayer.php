<?php

namespace TheLion\UseyourDrive;

class Mediaplayer {

    /**
     *
     * @var \TheLion\UseyourDrive\Processor 
     */
    private $_processor;

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

    public function get_media_list() {

        $this->_folder = $this->get_processor()->get_client()->get_folder();

        if (($this->_folder === false)) {
            die();
        }

        $subfolders = $this->get_processor()->get_client()->get_entries_in_subfolders($this->_folder['folder']);
        $this->_folder['contents'] = array_merge($subfolders, $this->_folder['contents']);
        $this->mediaarray = $this->createMediaArray();

        if (count($this->mediaarray) > 0) {
            $response = json_encode($this->mediaarray);

            $cached_request = new CacheRequest($this->get_processor());
            $cached_request->add_cached_response($response);
            echo $response;
        }

        die();
    }

    public function setFolder($folder) {
        $this->_folder = $folder;
    }

    public function createMediaArray() {

        $covers = array();
        /* Create covers */
        if (count($this->_folder['contents']) > 0) {

            foreach ($this->_folder['contents'] as $key => $node) {
                $child = $node->get_entry();
                /* Add images to cover array */
                if (isset($child->extension) && (in_array(strtolower($child->extension), array('png', 'jpg', 'jpeg')))) {
                    $covertitle = str_replace('.' . $child->get_extension(), '', $child->get_name());
                    $coverthumb = $child->get_thumbnail_small_cropped();
                    $covers[$covertitle] = $coverthumb;
                    unset($this->_folder['contents'][$key]);
                }
            }
        }

        $playlist = array();
        $files = array();

        //Create Filelist array
        if (count($this->_folder['contents']) > 0) {

            $foldername = $this->_folder['folder']->get_entry()->get_name();

            $files = array();
            foreach ($this->_folder['contents'] as $node) {

                $child = $node->get_entry();

                if ($child->is_dir()) {
                    continue;
                }

                $extension = $child->get_extension();
                $allowedextensions = array('mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga');

                if (empty($extension) || !in_array($extension, $allowedextensions)) {
                    continue;
                }

                $basename = str_replace('.' . $extension, '', $child->get_name());

                /* Check if entry is allowed */
                if (!$this->get_processor()->_is_entry_authorized($node)) {
                    continue;
                }

                if (isset($covers[$basename])) {
                    $thumbnail = $covers[$basename];
                } elseif (isset($covers[$foldername])) {
                    $thumbnail = $covers[$foldername];
                } else {
                    $thumbnail = $child->get_thumbnail_small_cropped();
                }
                $thumbnailsmall = str_replace('=w400-h300-c-nu', '=s200-c', $thumbnail);
                $poster = str_replace('=w400-h300-c-nu', '=s1024', $thumbnail);

                // combine same files with different extensions
                if (!isset($files[$basename])) {

                    $title = str_replace('/' . $this->_folder['folder']->get_name() . '/', '', $node->get_path($this->_folder['folder']->get_id()));
                    $title = str_replace('.' . $extension, '', $title);

                    $files[$basename] = array(
                        'title' => $title,
                        'name' => $basename,
                        'artist' => $child->get_description(),
                        'is_dir' => false,
                        'poster' => $poster,
                        'thumb' => $thumbnailsmall,
                        'extensions' => array(),
                        'size' => $child->get_size(),
                        'edited' => $child->get_last_edited(),
                        'last_edited' => $child->get_last_edited(),
                        'download' => false,
                        'linktoshop' => ($this->get_processor()->get_shortcode_option('linktoshop') !== '') ? $this->get_processor()->get_shortcode_option('linktoshop') : false
                    );
                }

                //Can play mp4 but need to give m4v or m4a
                if ($extension === 'mp4') {
                    $extension = ($this->get_processor()->get_shortcode_option('mode') === 'audio') ? 'm4a' : 'm4v';
                }
                if ($extension === 'ogg') {
                    $extension = ($this->get_processor()->get_shortcode_option('mode') === 'audio') ? 'oga' : 'ogv';
                }

                array_push($files[$basename]['extensions'], strtolower($extension));
                $files[$basename][$extension] = USEYOURDRIVE_ADMIN_URL . "?action=useyourdrive-stream&id=" . $child->get_id() . "&dl=1&listtoken=" . $this->get_processor()->get_listtoken();
                if ($this->get_processor()->get_shortcode_option('linktomedia') === '1' && $this->get_processor()->get_user()->can_download()) {
                    $files[$basename]['download'] = str_replace('useyourdrive-stream', 'useyourdrive-download', $files[$basename][$extension]);
                }
            }

            $files = $this->get_processor()->sort_filelist($files);
        }

        return array_values($files);
    }

}
