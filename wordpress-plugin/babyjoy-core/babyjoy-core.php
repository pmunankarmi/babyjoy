<?php
/**
 * Plugin Name: BabyJoy Core
 * Description: Catalogue-only WooCommerce, bilingual product fields, contact submissions and site options.
 * Version: 1.0.3
 * Requires PHP: 8.1
 * Update URI: https://github.com/pmunankarmi/babyjoy
 */
if (!defined('ABSPATH')) exit;
add_action('plugins_loaded',function(){if(defined('POLYLANG_VERSION')&&!function_exists('polylang_slug_unique_slug_in_language')&&!defined('POLYLANG_PRO'))require __DIR__.'/polylang-slug.php';},30);
require __DIR__.'/fields.php';
require __DIR__.'/admin-content-fields.php';
require __DIR__.'/contact.php';
// Products deliberately have one record. Pages continue to use standard Polylang translations.
add_filter('pll_get_post_types',function($types){unset($types['product'],$types['product_variation']);return $types;},99);
add_filter('pll_get_taxonomies',function($types){foreach($types as $key=>$type) if(str_starts_with($key,'pa_')||in_array($key,['product_cat','product_tag','product_brand'],true)) unset($types[$key]);return $types;},99);
add_filter('woocommerce_is_purchasable','__return_false',99);
add_filter('woocommerce_variation_is_purchasable','__return_false',99);
add_filter('woocommerce_get_price_html','__return_empty_string',99);
add_filter('woocommerce_add_to_cart_validation','__return_false',99);
add_filter('woocommerce_available_payment_gateways','__return_empty_array');
add_action('wp',function(){remove_action('woocommerce_single_product_summary','woocommerce_template_single_add_to_cart',30);remove_action('woocommerce_after_shop_loop_item','woocommerce_template_loop_add_to_cart',10);});
add_action('template_redirect',function(){
 if(function_exists('is_cart') && (is_cart()||is_checkout()||is_account_page())){wp_safe_redirect(home_url('/'));exit;}
 if(isset($_REQUEST['add-to-cart'])||isset($_GET['wc-ajax'])&&in_array($_GET['wc-ajax'],['add_to_cart','checkout'],true)){status_header(403);exit;}
},0);
add_filter('rest_pre_dispatch',function($result,$server,$request){if(preg_match('#^/wc/store/v\d+/(cart|checkout)(/|$)#',$request->get_route()))return new WP_Error('catalogue_only',__('Purchasing is disabled.','babyjoy'),['status'=>403]);return $result;},10,3);
add_action('init',function(){
 register_post_type('bj_submission',['labels'=>['name'=>__('Contact submissions','babyjoy'),'singular_name'=>__('Contact submission','babyjoy')],'public'=>false,'show_ui'=>true,'show_in_rest'=>false,'menu_icon'=>'dashicons-email-alt','supports'=>['title'],'capability_type'=>'post','capabilities'=>['create_posts'=>'do_not_allow','edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','read_private_posts'=>'manage_options','publish_posts'=>'do_not_allow','delete_posts'=>'manage_options'],'map_meta_cap'=>true]);
 add_rewrite_rule('^(en|ar)/product/([^/]+)/?$','index.php?post_type=product&name=$matches[2]&bj_language=$matches[1]','top');
});
add_filter('query_vars',function($q){$q[]='bj_language';return $q;});
add_action('pre_get_posts',function($q){if(!is_admin()&&$q->is_main_query()&&$q->get('bj_language'))$q->set('lang','');});
add_filter('redirect_canonical',function($redirect){return get_query_var('bj_language')?false:$redirect;});
// One canonical logo attachment; the ACF UI and Customizer edit the same theme mod.
add_filter('acf/load_value/name=site_logo',fn($value)=>get_theme_mod('custom_logo',0));
add_filter('acf/update_value/name=site_logo',function($value){set_theme_mod('custom_logo',absint(is_array($value)?($value['ID']??0):$value));return $value;});
add_action('admin_notices',function(){if(!current_user_can('manage_options'))return;foreach(['WooCommerce'=>'WooCommerce','ACF'=>'ACF Pro'] as $class=>$label)if(!class_exists($class))echo '<div class="notice notice-error"><p>'.esc_html(sprintf(__('BabyJoy requires %s to be active.','babyjoy'),$label)).'</p></div>';});
// Keep the catalogue tools while hiding unused store-management navigation.
add_action('admin_menu',function(){
 foreach(['woocommerce','wc-admin&path=/analytics/overview','woocommerce-marketing','wc-admin&path=/payments/overview','wc-payments','wc-orders','edit.php?post_type=shop_order','edit.php?post_type=shop_coupon'] as $slug)remove_menu_page($slug);
 // WooCommerce versions may register Payments/Analytics with a different route.
 global $menu;
 foreach((array)$menu as $item){$slug=$item[2]??'';if(str_starts_with($slug,'wc-admin')||str_starts_with($slug,'admin.php?page=wc-admin')||str_starts_with($slug,'wc-payments')||str_contains($slug,'from=PAYMENTS_MENU_ITEM'))remove_menu_page($slug);}
},999);
// Classic editing across posts, pages, products and widgets.
add_filter('use_block_editor_for_post','__return_false',100);
add_filter('use_block_editor_for_post_type','__return_false',100);
add_filter('use_widgets_block_editor','__return_false',100);
