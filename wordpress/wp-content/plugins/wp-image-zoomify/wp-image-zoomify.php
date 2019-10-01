<?php
/*
 Plugin Name: WP Image Zoomify
 Plugin URI: http://wordpress.org/extend/plugins/wp-image-zoomify/
 Description: Zoomify is simple image light box plugin with zoom effect.
 Version: 0.1
 Author: MD Sultan Nasir Uddin
 Author URI: http://imanik.me
 */

/*
Copyright (c) 2011, MD Sultan Nasir Uddin.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
 *  initiate the plugin using wp_footer hook
 */
function wp_image_zoomify(){
	 ?>
	<script >
		jQuery(document).ready(function($){
			$('img').each(function () {
				if(($(this).attr('rel') === 'zoomify') || ($(this).hasClass('zoomify'))){
					$(this).zoomify()
				}

			});
		})
	</script >
	<?php
}

/**
 * adds the script and style to the plugin
 */
function wp_image_zoomify_scipts(){
	wp_enqueue_script( 'wp-image-zoomify-js', plugin_dir_url( __FILE__ ) . '/js/zoomify.min.js', array('jquery'), '0.2.4', true );
	wp_enqueue_style( 'wp-image-zoomify-css', plugin_dir_url( __FILE__ ) . '/css/zoomify.min.css' );
}

add_action( 'wp_enqueue_scripts', 'wp_image_zoomify_scipts' );
add_action( 'wp_footer', 'wp_image_zoomify', 100 );