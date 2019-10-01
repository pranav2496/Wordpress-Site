<?php
/* 
Plugin Name: Wp Auto Reload Widget
Plugin URI: http://www.axebelk.com
Description: Wordpress Auto reload widgets for given time delay.
Version: 10.0.5
Author: Vijaya kumar
Author URI: http://www.axebelk.com
*/
if (!defined('ABSPATH'))
    die("Forbidden");
class Wp_Auto_Reload_widgets {
public function __construct(){
 $this->ab_add_options_page();
 add_filter('widget_form_callback', array($this,'ab_widget_form_extend'), 10, 2);
 add_filter('widget_update_callback', array($this,'ab_widget_update'), 10, 2);
 add_filter('dynamic_sidebar_params', array($this,'ab_dynamic_sidebar_params'), 10, 2);
 $ab_ws = get_option('ab_auto_load');
 if (isset($ab_ws['ab_rf_'])) {
    add_action('wp_enqueue_scripts', array($this,'ab_adding_scripts'));
}
}
// ADD WIDGET OPTIONS
public function ab_widget_form_extend($instance, $widget)
{
    if (!isset($instance['classes']))
        $instance['classes'] = null;
    
    $admin_settings_link = admin_url( 'options-general.php?page=options-general.php_auto_reload_widget_settings' );
    $page_link = "<br>Use this Widget ID in <a target='_blank' href='{$admin_settings_link}'>Plugin Settings</a> page";
    $row = "<p>\n";
    $row .= "\t<label for='widget-{$widget->id_base}-{$widget->number}-classes'>Widget ID <small>(Unique)</small></label>\n";
    $row .= "\t<input type='text' name='widget-{$widget->id_base}[{$widget->number}][classes]' id='widget-{$widget->id_base}-{$widget->number}-classes' class='widefat' value='{$instance['classes']}'/>\n";
    $row .= "{$page_link}</p>\n";
    
    echo $row;
    return $instance;
}

public function ab_widget_update($instance, $new_instance)
{
    $instance['classes'] = $new_instance['classes'];
    return $instance;
}
public function ab_dynamic_sidebar_params($params)
{
    global $wp_registered_widgets;
    $widget_id  = $params[0]['widget_id'];
    $widget_obj = $wp_registered_widgets[$widget_id];
    $widget_opt = get_option($widget_obj['callback'][0]->option_name);
    $widget_num = $widget_obj['params'][0]['number'];
    
    if (isset($widget_opt[$widget_num]['classes']) && !empty($widget_opt[$widget_num]['classes']))
        $params[0]['before_widget'] = preg_replace('/class="/', "class=\"{$widget_opt[$widget_num]['classes']} ", $params[0]['before_widget'], 1);
    
    return $params;
}

// ADD OPTIONS PAGE
public function ab_add_options_page(){
require_once("admin-page-class/admin-page-class.php");

$config = array(
    'menu' => 'settings',
    'page_title' => __('Auto Reload Widget Settings', 'apc'),
    'capability' => 'edit_themes',
    'option_group' => 'ab_auto_load',
    'id' => 'admin_page',
    'fields' => array(),
    'local_images' => false,
    'use_with_theme' => false
);

$options_panel = new BF_Admin_Page_Class($config);
$options_panel->OpenTabs_container('');

$options_panel->TabsListing(array(
    'links' => array(
        'options_1' => __('Reload Settings', 'apc'),
        'options_2' => __('Import Export', 'apc')
    )
));

$options_panel->OpenTab('options_1');
$repeater_fields[] = $options_panel->addText('ab_div_title', array(
    'name' => __('Title ', 'apc')
), true);
$repeater_fields[] = $options_panel->addText('ab_div_class', array(
    'name' => __('Widget ID ', 'apc')
), true);
$repeater_fields[] = $options_panel->addText('ab_div_timer', array(
    'name' => __('Time Delay <small>(in Seconds)</small> ', 'apc')
), true);

$options_panel->addRepeaterBlock('ab_rf_', array(
    'sortable' => false,
    'inline' => true,
    'name' => __('Add Reload Widget Settings', 'apc'),
    'fields' => $repeater_fields,
    'desc' => __('Add settings for each widget.', 'apc')
));

$options_panel->CloseTab();

$options_panel->OpenTab('options_2');

$options_panel->Title(__("Import Export", "apc"));

$options_panel->addImportExport();

$options_panel->CloseTab();
}
//ADD JS
public function ab_adding_scripts()
{
    $ab_ws = get_option('ab_auto_load');
    wp_enqueue_script('ab_auto_reload', plugins_url('js/auto_reload.min.js', __FILE__), array(
        'jquery'
    ), '1.1', true);
    if (isset($ab_ws['ab_rf_'])) {
        foreach ($ab_ws['ab_rf_'] as $ab_value) {
            $ab_div[]  = $ab_value['ab_div_class'];
            $ab_time[] = $ab_value['ab_div_timer'];
        }
    }
    wp_localize_script('ab_auto_reload', 'ab_reload', array(
        'ab_div' => json_encode($ab_div),
        'ab_delay' => json_encode($ab_time)
    ));
}

}
new Wp_Auto_Reload_widgets();