<?php

namespace TheLion\UseyourDrive;

class Helpers {

    /**
     *  Do so magic to make sure that the path is correctly set according to the rules
     * @param string $path
     * @return string
     */
    static public function clean_folder_path($path) {

        $path = html_entity_decode($path);
        $special_chars = array("?", "<", ">", ":", "\"", "*", "|");
        $path = str_replace($special_chars, '', $path);
        $path = trim($path, "/");
        $path = str_replace(array('\\', '//'), '/', $path);

        if (!empty($path)) {
            $path = '/' . $path;
        }

        return $path;
    }

    static public function get_pathinfo($path) {
        preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $path, $m);
        if (isset($m[1]))
            $ret['dirname'] = $m[1];
        if (isset($m[2]))
            $ret['basename'] = $m[2];
        if (isset($m[5]))
            $ret['extension'] = $m[5];
        if (isset($m[3]))
            $ret['filename'] = $m[3];

        if (substr($path, -1) === '.') {
            $ret['basename'] .= '.';
            unset($ret['extension']);
        }

        return $ret;
    }

    static public function filter_filename($filename, $beautify = true) {
        // sanitize filename
        $filename = preg_replace(
                '~
          [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
          [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
          [\x7F\xA0\xAD]          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
          ~x', '-', $filename);

        //        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        //        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        //
          // avoids ".", ".." or ".hiddenFiles"
        $filename = ltrim($filename, '.-');
        // optional beautification
        if ($beautify) {
            $filename = self::beautify_filename($filename);
        }
        // maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');

        return $filename;
    }

    static public function beautify_filename($filename) {
        // reduce consecutive characters
        $filename = preg_replace(array(
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/'
                ), '-', $filename);
        $filename = preg_replace(array(
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
                ), '.', $filename);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        if (function_exists('mb_strtolower') && function_exists('mb_detect_encoding')) {
            $filename = mb_strtolower($filename, mb_detect_encoding($filename));
        }
        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');
        return $filename;
    }

    /**
     * Checks if a particular user has a role.
     * Returns true if a match was found.
     *
     * @param array $roles Roles array.
     * @return bool
     */
    static public function check_user_role($roles_to_check = array()) {

        if (in_array('all', $roles_to_check)) {
            return true;
        }

        if (in_array('none', $roles_to_check)) {
            return false;
        }

        if (in_array('guest', $roles_to_check)) {
            return true;
        }

        if (is_super_admin()) {
            return true;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        if (empty($user) || (!($user instanceof \WP_User))) {
            return false;
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $roles_to_check)) {
                return true;
            }
        }

        return false;
    }

    static public function get_user_name() {

        if (!is_user_logged_in()) {
            return __('Guest', 'useyourdrive');
        }

        $current_user = wp_get_current_user();
        return $current_user->display_name;
    }

    static public function get_user_email() {

        if (!is_user_logged_in()) {
            return null;
        }

        $current_user = wp_get_current_user();
        return $current_user->user_email;
    }

    static public function get_user_ip() {
        /* User IP */
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //check ip from share internet 
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy 
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '';
        }
        return apply_filters('wpb_get_ip', $ip);
    }

    static public function get_user_location($ip = null) {
        $userip = empty($ip) ? $this->get_user_ip() : $ip;

        try {
            $geolocation = (unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=' . $userip)));

            if ($geolocation !== false && $geolocation['geoplugin_status'] === 200) {
                return $geolocation['geoplugin_city'] . ', ' . $geolocation['geoplugin_region'] . ', ' . $geolocation['geoplugin_countryName'];
            } else {
                return '-location unknown-';
            }
        } catch (\Exception $ex) {
            return '-location unknown-';
        }
    }

    static public function return_bytes($size_str) {
        if (empty($size_str)) {
            return $size_str;
        }

        $unit = substr($size_str, -1);
        if (($unit === 'B' || $unit === 'b') && (!ctype_digit(substr($size_str, -2)))) {
            $unit = substr($size_str, -2, 1);
        }

        switch ($unit) {
            case 'M': case 'm': return (int) $size_str * 1048576;
            case 'K': case 'k': return (int) $size_str * 1024;
            case 'G': case 'g': return (int) $size_str * 1073741824;
            default: return $size_str;
        }
    }

    static public function bytes_to_size_1024($bytes, $precision = 0) {
        if (empty($bytes)) {
            return $bytes;
        }

        /* human readable format -- powers of 1024 */
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
        return @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision) . ' ' . $unit[$i];
    }

    static public function remove_element_with_value($array, $key, $value) {
        foreach ($array as $subKey => $subArray) {
            if ($subArray[$key] == $value) {
                unset($array[$subKey]);
            }
        }

        return $array;
    }

    static public function find_item_in_array_with_value($array, $key, $search) {


        $columns = array_map(function($e) use ($key) {
            return is_object($e) ? $e->$key : $e[$key];
        }, $array);

        return array_search($search, $columns);
    }

    static public function compress_css($minify) {

        $minify = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $minify);
        $minify = str_replace(': ', ':', $minify);
        $minify = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $minify);

        return $minify;
    }

    static public function get_default_thumbnail_icon($mimetype) {
        switch ($mimetype) {

            case 'application/vnd.google-apps.folder':
                $thumbnailicon = 'icon_10_folder_xl128.png';
                break;
            case 'application/vnd.google-apps.audio':
            case 'audio/mpeg':
                $thumbnailicon = 'icon_11_audio_xl128.png';
                break;
            case 'application/vnd.google-apps.document':
            case 'application/vnd.oasis.opendocument.text':
            case 'text/plain':
                $thumbnailicon = 'icon_11_document_xl128.png';
                break;
            case 'application/vnd.google-apps.drawing':
                $thumbnailicon = 'icon_11_drawing_xl128.png';
                break;
            case 'application/vnd.google-apps.form':
                $thumbnailicon = 'icon_11_form_xl128.png';
                break;
            case 'application/vnd.google-apps.fusiontable':
                $thumbnailicon = 'icon_11_table_xl128.png';
                break;
            case 'application/vnd.google-apps.photo':
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
            case 'image/bmp':
                $thumbnailicon = 'icon_11_image_xl128.png';
                break;
            case 'application/vnd.google-apps.presentation':
            case 'application/vnd.oasis.opendocument.presentation':
                $thumbnailicon = 'icon_11_presentation_xl128.png';
                break;
            case 'application/vnd.google-apps.script':
            case 'application/x-httpd-php':
            case 'text/js':
                $thumbnailicon = 'icon_11_script_xl128.png';
                break;
            case 'application/vnd.google-apps.sites':
                $thumbnailicon = 'icon_11_sites_xl128.png';
                break;
            case 'application/vnd.google-apps.spreadsheet':
            case 'application/vnd.oasis.opendocument.spreadsheet':
                $thumbnailicon = 'icon_11_spreadsheet_xl128.png';
                break;
            case 'application/vnd.google-apps.video':
                $thumbnailicon = 'icon_11_video_xl128.png';
                break;

            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                $thumbnailicon = 'icon_11_excel_xl128.png';
                break;
            case 'application/msword':
                $thumbnailicon = 'icon_11_word_xl128.png';
                break;


            case 'application/pdf':
                $thumbnailicon = 'icon_11_pdf_xl128.png';
                break;
            default:
                $thumbnailicon = 'icon_10_generic_xl128.png';
                break;
        }

        return USEYOURDRIVE_ICON_SET . $thumbnailicon;
    }

}
