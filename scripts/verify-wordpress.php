<?php
if(!defined('WP_CLI'))exit;
function bj_assert($ok,$message){if(!$ok)WP_CLI::error($message);WP_CLI::log('PASS '.$message);}
$ids=get_option('bj_page_ids');
bj_assert(count($ids)===8,'Eight standard pages with linked translations');
foreach($ids as $slug=>$langs){bj_assert(count($langs)===2&&pll_get_post($langs['en'],'ar')===$langs['ar'],'Translation link: '.$slug);bj_assert(get_post_field('post_name',$langs['en'])===get_post_field('post_name',$langs['ar']),'Shared slug: '.$slug);}
bj_assert(count(wc_get_products(['limit'=>-1,'status'=>'publish']))===8,'Eight catalogue products, one record each');
bj_assert(!pll_is_translated_post_type('product'),'Products excluded from Polylang translation records');
foreach(wc_get_products(['limit'=>-1,'status'=>'publish']) as $p){bj_assert(!$p->is_purchasable(),'Purchasing blocked: '.$p->get_slug());bj_assert((bool)get_field('title_ar',$p->get_id()),'Arabic ACF title: '.$p->get_slug());}
bj_assert(wc_attribute_taxonomy_id_by_name('pa_size')>0,'Native global Size attribute exists');
bj_assert(count(get_field('retailers','option'))===6,'Six editable regional aggregators');
bj_assert((int)get_field('site_logo','option')===(int)get_theme_mod('custom_logo'),'ACF and native logo synchronized');
bj_assert(bj_csv_cell('=SUM(1,2)')==="'=SUM(1,2)",'CSV formula injection escaped');
$request=new WP_REST_Request('POST','/wc/store/v1/cart/add-item');$result=apply_filters('rest_pre_dispatch',null,rest_get_server(),$request);bj_assert(is_wp_error($result)&&$result->get_error_data()['status']===403,'Store API cart blocked');
$old=get_transient('bj_github_release');set_transient('bj_github_release',['tag_name'=>'v1.0.1','assets'=>[['name'=>'babyjoy.zip','browser_download_url'=>'https://github.com/pmunankarmi/babyjoy/releases/download/v1.0.1/babyjoy.zip']]],60);
$result=apply_filters('update_themes_github.com',false,['Version'=>'1.0.0'],'babyjoy');bj_assert(($result['version']??'')==='1.0.1','GitHub release appears through native theme update hook');if($old===false)delete_transient('bj_github_release');else set_transient('bj_github_release',$old,HOUR_IN_SECONDS);
WP_CLI::success('WordPress integration checks passed.');
