<?php
if(!defined('WP_CLI'))exit;
$base=get_option('bj_page_ids')['index'];$countries=get_option('bj_country_pages',[]);
// Preserve the existing Saudi aggregator configuration before removing its options UI.
if(!metadata_exists('post',$base['en'],'aggregators_enabled')){
 update_field('field_bj_aggregators_enabled',get_option('options_aggregators_enabled',1),$base['en']);
 update_field('field_bj_retailers',get_field('retailers','option')?:[],$base['en']);
}
$references=json_decode(file_get_contents(__DIR__.'/../build/country-retailers.json'),true);
require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
$names=['city-hyper-min'=>'City Hypermarket','lulu-min'=>'LuLu','yahal-min'=>'Yahaal','talabat'=>'Talabat','union-coop-min'=>'Union Coop','noon-logo-1'=>'Noon','amazon-logo'=>'Amazon','first-cry'=>'FirstCry','mumzworld-min'=>'Mumzworld','carrefour'=>'Carrefour'];
foreach(bj_countries() as $country){
 foreach(['en','ar'] as $lang){
  $id=(int)($countries[$country][$lang]??0);
  if(!$id){$source=get_post($base[$lang]);$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>ucfirst($country).' — '.$source->post_title,'post_name'=>$country,'post_content'=>$source->post_content],true);if(is_wp_error($id))WP_CLI::error($id->get_error_message());
   foreach(get_post_meta($base[$lang]) as $key=>$values){if(in_array($key,['_edit_lock','_edit_last','aggregators_enabled','retailers'])||str_starts_with($key,'retailers_')||str_starts_with($key,'_retailers'))continue;foreach($values as $value)add_post_meta($id,$key,maybe_unserialize($value));}
   pll_set_post_language($id,$lang);update_post_meta($id,'bj_country',$country);$countries[$country][$lang]=$id;
  }
 }
 pll_save_post_translations($countries[$country]);
 $id=$countries[$country]['en'];
 if(!metadata_exists('post',$id,'bj_country_retailers_seeded')){
  $rows=[];foreach($references[$country]??[] as $ref){
   $existing=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'bj_country_logo_source','meta_value'=>$ref['img'],'numberposts'=>1,'fields'=>'ids']);
   $attachment=$existing[0]??media_sideload_image($ref['img'],$id,null,'id');if(is_wp_error($attachment))WP_CLI::error($attachment->get_error_message());update_post_meta($attachment,'bj_country_logo_source',$ref['img']);
   $key=pathinfo(parse_url($ref['img'],PHP_URL_PATH),PATHINFO_FILENAME);$name=$names[$key]??$key;
   $rows[]=['retailer_name'=>$name,'retailer_name_ar'=>$name,'retailer_url'=>$ref['url'],'retailer_logo'=>$attachment,'retailer_enabled'=>1];
  }
  update_field('field_bj_retailers',$rows,$id);update_field('field_bj_aggregators_enabled',!empty($rows),$id);update_post_meta($id,'bj_country_retailers_seeded',1);
 }
 update_option('bj_country_pages',$countries);
 WP_CLI::log($country.': '.implode(', ',$countries[$country]));
}
// Register newly-created routes in this same CLI request before flushing.
flush_rewrite_rules();
WP_CLI::success('Country homepages and page-owned aggregators configured. Existing page content preserved.');
