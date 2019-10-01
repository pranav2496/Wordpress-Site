<?php

namespace TheLion\UseyourDrive;

class LinkUsers {

    /**
     *
     * @var \TheLion\UseyourDrive\Main 
     */
    private $_main;

    /**
     * Construct the plugin object
     */
    public function __construct(\TheLion\UseyourDrive\Main $main) {

        $this->_main = $main;
    }

    public function render() {
        $html = '';
        ?>
        <div class="useyourdrive admin-settings">

          <div class="useyourdrive-header">
            <div class="useyourdrive-logo"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/logo64x64.png" height="64" width="64"/></div>
            <div class="useyourdrive-title"><?php _e('Link Private Folders', 'useyourdrive'); ?></div>
          </div>

          <div class="useyourdrive-panel useyourdrive-panel-full">
            <div>
              <form method="post">
                <input type="hidden" name="page" value="uyd_list_table" />
                <?php
                $users_list = new \TheLion\UseyourDrive\User_List_Table();
                $users_list->views();
                $users_list->prepare_items();
                $users_list->search_box('search', 'search_id');
                $users_list->display();
                ?>
              </form>
            </div>
            <div id='uyd-embedded' style='clear:both;display:none'>
              <?php
              $processor = $this->_main->get_processor();
              $rootfolder = $processor->get_client()->get_root_folder();

              echo $processor->create_from_shortcode(
                      array(
                          'dir' => $rootfolder->get_id(),
                          'mode' => 'files',
                          'showfiles' => '1',
                          'filesize' => '0',
                          'filedate' => '0',
                          'upload' => '0',
                          'delete' => '0',
                          'rename' => '0',
                          'addfolder' => '0',
                          'showbreadcrumb' => '1',
                          'showcolumnnames' => '0',
                          'showfiles' => '0',
                          'downloadrole' => 'none',
                          'candownloadzip' => '0',
                          'showsharelink' => '0',
                          'mcepopup' => 'linkto',
                          'search' => '0'));
              ?>
            </div>
          </div>
        </div>
        <script type="text/javascript">
            jQuery(function ($) {
              /* Add Link to event*/
              $('.useyourdrive .linkbutton').click(function () {
                $('.useyourdrive .thickbox_opener').removeClass("thickbox_opener");
                $(this).parent().addClass("thickbox_opener");
                tb_show("(Re) link to folder", '#TB_inline?height=450&amp;width=800&amp;inlineId=uyd-embedded');
              });

              $('.useyourdrive .unlinkbutton').click(function () {
                var curbutton = $(this),
                        user_id = $(this).attr('data-user-id');

                $.ajax({type: "POST",
                  url: UseyourDrive_vars.ajax_url,
                  data: {
                    action: 'useyourdrive-unlinkusertofolder',
                    userid: user_id,
                    _ajax_nonce: UseyourDrive_vars.createlink_nonce
                  },
                  beforeSend: function () {
                    curbutton.parent().find('.uyd-spinner').show();
                  },
                  success: function (response) {
                    if (response === '1') {
                      curbutton.addClass('hidden');
                      curbutton.prev().removeClass('hidden');
                      curbutton.parent().parent().find('.column-private_folder').text('');
                    } else {
                      location.reload(true);
                    }
                  },
                  complete: function (reponse) {
                    $('.uyd-spinner').hide();
                  },
                  dataType: 'text'
                });
              });
            });
        </script>
        <?php
    }

    /**
     * 
     * @return \TheLion\UseyourDrive\Main
     */
    public function get_main() {
        return $this->_main;
    }

}

// WP_List_Table is not loaded automatically so we need to load it in our application
if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class User_List_Table extends \WP_List_Table {

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items() {
        global $role, $usersearch;

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();


        $usersearch = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $role = isset($_REQUEST['role']) ? $_REQUEST['role'] : '';
        $per_page = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
        $users_per_page = $this->get_items_per_page($per_page);
        $paged = $this->get_pagenum();
        if ('none' === $role) {
            $args = array(
                'number' => $users_per_page,
                'offset' => ( $paged - 1 ) * $users_per_page,
                'include' => wp_get_users_with_no_role($this->site_id),
                'search' => $usersearch,
                'fields' => 'all_with_meta'
            );
        } else {
            $args = array(
                'number' => $users_per_page,
                'offset' => ( $paged - 1 ) * $users_per_page,
                'role' => $role,
                'search' => $usersearch,
                'fields' => 'all_with_meta'
            );
        }
        if ('' !== $args['search'])
            $args['search'] = '*' . $args['search'] . '*';
        if ($this->is_site_users)
            $args['blog_id'] = $this->site_id;
        if (isset($_REQUEST['orderby']))
            $args['orderby'] = $_REQUEST['orderby'];
        if (isset($_REQUEST['order']))
            $args['order'] = $_REQUEST['order'];

        $args = apply_filters('users_list_table_query_args', $args);
        $wp_user_search = new \WP_User_Query($args);

        $data = $this->table_data($wp_user_search->get_results());

        $this->set_pagination_args(array(
            'total_items' => $wp_user_search->get_total() + 1,
            'per_page' => $users_per_page,
        ));

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns() {
        $columns = array(
            'id' => 'ID',
            'avatar' => '',
            'username' => __('Username'),
            'name' => __('Name'),
            'email' => __('Email'),
            'role' => __('Role'),
            'private_folder' => __('Private Folder', 'useyourdrive'),
            'buttons' => ''
        );
        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array('id');
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        return array(
            'username' => array('username', false),
            'name' => array('name', false),
            'email' => array('email', false),
            'role' => array('role', false),
            'private_folder' => array('private_folder', false)
        );
    }

    protected function get_views() {
        global $role;
        $wp_roles = wp_roles();

        $parts = parse_url(home_url());
        $url = get_admin_url(null, 'admin.php?page=UseyourDrive_settings_linkusers');

        $users_of_blog = count_users();

        $total_users = $users_of_blog['total_users'] + 1;
        $avail_roles = & $users_of_blog['avail_roles'];
        unset($users_of_blog);
        $current_link_attributes = empty($role) ? ' class="current" aria-current="page"' : '';
        $role_links = array();
        $role_links['all'] = "<a href='$url'$current_link_attributes>" . sprintf(_nx('All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_users, 'users'), number_format_i18n($total_users)) . '</a>';
        foreach ($wp_roles->get_names() as $this_role => $name) {
            if (!isset($avail_roles[$this_role]))
                continue;
            $current_link_attributes = '';
            if ($this_role === $role) {
                $current_link_attributes = ' class="current" aria-current="page"';
            }
            $name = translate_user_role($name);
            /* translators: User role name with count */
            $name = sprintf('%1$s <span class="count">(%2$s)</span>', $name, number_format_i18n($avail_roles[$this_role]));
            $role_links[$this_role] = "<a href='" . esc_url(add_query_arg('role', $this_role, $url)) . "'$current_link_attributes>$name</a>";
        }
        if (!empty($avail_roles['none'])) {
            $current_link_attributes = '';
            if ('none' === $role) {
                $current_link_attributes = ' class="current" aria-current="page"';
            }
            $name = __('No role');
            /* translators: User role name with count */
            $name = sprintf('%1$s <span class="count">(%2$s)</span>', $name, number_format_i18n($avail_roles['none']));
            $role_links['none'] = "<a href='" . esc_url(add_query_arg('role', 'none', $url)) . "'$current_link_attributes>$name</a>";
        }
        return $role_links;
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data($users) {
        $data = array();

        /* Guest Data */
        $guestfolder = get_site_option('use_your_drive_guestlinkedto');

        $data[] = array(
            'id' => 'GUEST',
            'avatar' => '<img src="' . USEYOURDRIVE_ROOTPATH . '/css/images/usericon.png" style="height:32px"/>',
            'username' => __('Guest', 'useyourdrive'),
            'name' => '...' . __('Default folder for Guests and non-linked Users', 'useyourdrive'),
            'email' => '',
            'role' => '',
            'private_folder' => $guestfolder,
            'buttons' => ''
        );


        //$users = get_users();

        foreach ($users as $user) {

            /* Gravatar */
            if (function_exists('get_wp_user_avatar')) {
                $display_gravatar = get_wp_user_avatar($user->user_email, 32);
            } else {
                $display_gravatar = get_avatar($user->user_email, 32);
                if ($display_gravatar === false) {
                    //Gravatar is disabled, show default image.
                    $display_gravatar = '<img src="' . USEYOURDRIVE_ROOTPATH . '/css/images/usericon.png" style="height:32px"/>';
                }
            }

            $curfolder = get_user_option('use_your_drive_linkedto', $user->ID);
            $data[] = array(
                'id' => $user->ID,
                'avatar' => $display_gravatar,
                'username' => $user->user_login,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'role' => implode(', ', $this->get_role_list($user)),
                'private_folder' => $curfolder,
                'buttons' => ''
            );
        }

        return $data;
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'avatar':
            case 'email':
            case 'role':
            case 'name':
                return $item[$column_name];

            case 'username':

                if ($item['id'] === 'GUEST') {
                    return "<strong>" . $item[$column_name] . "</strong>";
                }

                return '<strong><a href="' . get_edit_user_link($item['id']) . '" title="' . $item[$column_name] . '">' . $item[$column_name] . '</a></strong>';

            case 'private_folder':
                if (isset($item[$column_name]['foldertext'])) {
                    return $item[$column_name]['foldertext'];
                }

                return '';


            case 'buttons':
                $private_folder = $item['private_folder'];

                $has_link = (!(empty($private_folder) || !is_array($private_folder) || !isset($private_folder['foldertext'])));

                $buttons_html = '<a href="#" title="' . __('Create link with Private Folder', 'useyourdrive') . '" class="linkbutton ' . (($has_link) ? 'hidden' : '') . '" data-user-id="' . $item['id'] . '"><i class="fas fa-link" aria-hidden="true"></i> <span class="linkedto">' . __('Link to Private Folder', 'useyourdrive') . '</span></a>';
                $buttons_html .= '<a href="#" title="' . __('Break link with Private Folder', 'useyourdrive') . '" class="unlinkbutton ' . (($has_link) ? '' : 'hidden') . '" data-user-id="' . $item['id'] . '"><i class="fas fa-chain-broken" aria-hidden="true"></i> <span class="linkedto">' . __('Unlink', 'useyourdrive') . '</span></a>';
                $buttons_html .= '<div class="uyd-spinner"></div>';

                return $buttons_html;

            default:
                return print_r($item, true);
        }
    }

    /**
     * Output 'no users' message.
     * 
     */
    public function no_items() {
        _e('No users found.');
    }

    protected function get_role_list($user_object) {
        $wp_roles = wp_roles();
        $role_list = array();
        foreach ($user_object->roles as $role) {
            if (isset($wp_roles->role_names[$role])) {
                $role_list[$role] = translate_user_role($wp_roles->role_names[$role]);
            }
        }
        if (empty($role_list)) {
            $role_list['none'] = _x('None', 'no user roles');
        }

        return apply_filters('get_role_list', $role_list, $user_object);
    }

}
