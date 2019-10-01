<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package @package Dank Portfolio
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) {
    wp_body_open();
} ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'dank-portfolio' ); ?></a>
<div id="menu-wrapper" class="">
  <?php wp_nav_menu( array( 'theme_location' => 'main', 'container_class' => 'main-menu' ) ); ?>
</div>

	<header id="masthead" class="site-header grid" role="banner">
		<div class="site-branding col-3-12 mobile-col-1-2">
            <h1 class="site-title">
            <?php

                $output = null;

                if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
                    $output .= get_custom_logo();
                } else {
                    $output .= '<a href="'. esc_url( trailingslashit( home_url() ) ).'" title="'.esc_attr( get_bloginfo( 'name' ) ).'" rel="home">';
                    $output .= get_bloginfo( 'name' );
                    $output .= '</a>';
                }

                echo wp_kses_post($output); ?>
                
            </h1>
            <?php

            $description = get_bloginfo( 'description', 'display' );
            if ( ( function_exists( 'the_custom_logo' ) && ! has_custom_logo() ) && $description || is_customize_preview() ) : ?>
                <p class="site-description"><?php echo esc_html($description); /* WPCS: xss ok. */ ?></p>
            <?php endif; ?>
		</div><!-- .site-branding -->
        <div class="push-right">
            <div class="mobile-col-1-2">
            <a class="wp-link" href="#">
              <div id="menu-icon">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
              </div>
            </a>
        </div>
        </div>
   

      <?php do_action('draft-welcome-text')?>
	</header><!-- #masthead -->

	<div id="content" class="site-content grid-wide">
        <?php if (is_front_page() ):
                the_header_image_tag(); 
              endif;
        ?>
