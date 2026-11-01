<?php
if(!defined('ABSPATH'))exit;
require __DIR__.'/inc/content.php';
require __DIR__.'/inc/bilingual-pages.php';
require __DIR__.'/inc/catalogue.php';
require __DIR__.'/inc/updater.php';
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('custom-logo');add_theme_support('html5',['search-form','gallery','caption','style','script']);add_theme_support('woocommerce');register_nav_menus(['primary'=>'Primary navigation','footer'=>'Footer navigation']);load_theme_textdomain('babyjoy',__DIR__.'/languages');});
add_filter('woocommerce_enqueue_styles','__return_empty_array');
add_action('wp_enqueue_scripts',function(){
 $base=get_template_directory_uri().'/assets/';
 foreach(['vendor/bootstrap/bootstrap.min.css','css/fonts.css','css/mt-style.css','css/bj-extra.css','css/bj-fixes.css','css/imported.css','css/aggregators.css','css/wordpress.css'] as $i=>$file)wp_enqueue_style('bj-'.$i,$base.$file,[],filemtime(__DIR__.'/assets/'.$file));
 wp_enqueue_script('bj-runtime',$base.'js/wordpress.js',[],filemtime(__DIR__.'/assets/js/wordpress.js'),true);
 wp_localize_script('bj-runtime','BabyJoy',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bj_contact'),'language'=>bj_language(),'products'=>bj_catalogue_data(),'strings'=>bj_strings()]);
});
