<?php
/** Run with wp eval-file scripts/import-wordpress.php /absolute/path/build/import-data.json */
if(!defined('WP_CLI'))exit;
if(!function_exists('update_field')||!function_exists('wc_get_product'))WP_CLI::error('Activate ACF Pro, WooCommerce, Polylang and BabyJoy Core first.');
$file=$args[0]??dirname(__DIR__).'/build/import-data.json';$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
require_once ABSPATH.'wp-admin/includes/image.php';
global $source,$media;
$source=dirname(realpath($file),2).'/wordpress-content/uploads/babyjoy/';$media=get_option('bj_media_ids',[]);
function bj_import_media($path){global $source,$media;if(isset($media[$path])&&get_post($media[$path]))return $media[$path];$file=$source.$path;if(!is_file($file))return 0;$uploads=wp_upload_dir();$dest=$uploads['basedir'].'/babyjoy/'.$path;wp_mkdir_p(dirname($dest));copy($file,$dest);$type=wp_check_filetype($file);$id=wp_insert_attachment(['post_mime_type'=>$type['type']?:('svg'===pathinfo($file,PATHINFO_EXTENSION)?'image/svg+xml':'application/octet-stream'),'post_title'=>pathinfo($file,PATHINFO_FILENAME),'post_status'=>'inherit'],$dest);if(is_wp_error($id))return 0;update_attached_file($id,$dest);if(in_array(pathinfo($file,PATHINFO_EXTENSION),['jpg','jpeg','png','webp'],true)){add_filter('intermediate_image_sizes_advanced','__return_empty_array',999);$meta=wp_generate_attachment_metadata($id,$dest);wp_update_attachment_metadata($id,$meta);remove_filter('intermediate_image_sizes_advanced','__return_empty_array',999);}return $media[$path]=$id;}
// Explicitly initialize the two standard page languages.
if(function_exists('PLL')){
 $model=PLL()->model;
 foreach([['name'=>'العربية','slug'=>'ar','locale'=>'ar','rtl'=>1,'term_group'=>0],['name'=>'English','slug'=>'en','locale'=>'en_US','rtl'=>0,'term_group'=>1]] as $language){if(!$model->get_language($language['slug']))$model->add_language($language);}
 $options=get_option('polylang',[]);$options['default_lang']='ar';$options['hide_default']=1;$options['force_lang']=1;$options['rewrite']=1;$options['post_types']=array_values(array_diff($options['post_types']??[],['product','product_variation']));update_option('polylang',$options);
}
$attribute_id=wc_attribute_taxonomy_id_by_name('pa_size');if(!$attribute_id){$attribute_id=wc_create_attribute(['name'=>'Size','slug'=>'size','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);delete_transient('wc_attribute_taxonomies');}
if(!taxonomy_exists('pa_size'))register_taxonomy('pa_size',['product'],['label'=>'Size','hierarchical'=>false,'show_ui'=>false]);
$pages=get_option('bj_page_ids',[]);
foreach($data['pages'] as $page){$lang=$page['language'];$slug=$page['slug'];$id=$pages[$slug][$lang]??0;
 $post=['post_type'=>'page','post_status'=>'publish','post_title'=>$page['title'],'post_name'=>$slug==='index'?'home':$slug];if($id)$post['ID']=$id;
 $id=wp_insert_post($post,true);if(is_wp_error($id))WP_CLI::error($id->get_error_message());
 update_post_meta($id,'bj_layout',wp_slash($page['markup']));
 $fields=array_map(fn($r)=>['content_label'=>$r['label'],'content_value'=>$r['value']],$page['fields']);update_field('field_bj_content_fields',$fields,$id);
 preg_match_all('/\{\{asset:([^}]+)\}\}/',$page['markup'],$matches);$items=[];foreach(array_unique($matches[1]) as $path)$items[]=['media_key'=>$path,'media_file'=>bj_import_media($path)];update_field('field_bj_media_fields',$items,$id);
 if(function_exists('pll_set_post_language'))pll_set_post_language($id,$lang);$pages[$slug][$lang]=$id;
}
foreach($pages as $translations)if(function_exists('pll_save_post_translations'))pll_save_post_translations($translations);
update_option('bj_page_ids',$pages);update_option('show_on_front','page');update_option('page_on_front',$pages['index']['ar']);
$product_order=['olive-tape','olive-pants','compressed-tape','pants','super-big-pants','wipes-sensitive','wipes-thick','wipes-healthy'];usort($data['products'],fn($a,$b)=>array_search($a['slug'],$product_order)<=>array_search($b['slug'],$product_order));
foreach($data['products'] as $order=>$item){
 $existing=get_page_by_path($item['slug'],OBJECT,'product');$p=$existing?wc_get_product($existing->ID):null;
 if(!$p)$p=$item['sizes']?new WC_Product_Variable():new WC_Product_Simple();
 $p->set_name($item['title']);$p->set_slug($item['slug']);$p->set_status('publish');$p->set_menu_order($order);$p->set_catalog_visibility('visible');$p->set_image_id(bj_import_media($item['image']));$attributes=[];
 foreach($item['attributes'] as $a){$attr=new WC_Product_Attribute();$attr->set_name($a['name']);$attr->set_options([$a['value']]);$attr->set_visible(true);$attributes[]=$attr;}
 if($item['sizes']){$attr=new WC_Product_Attribute();$attr->set_id($attribute_id);$attr->set_name('pa_size');$term_ids=[];foreach($item['sizes'] as $size){$slug='size-'.str_replace('+','-plus',$size['size']);$term=get_term_by('slug',$slug,'pa_size');$term_ids[]=$term?$term->term_id:wp_insert_term($size['size'],'pa_size',['slug'=>$slug])['term_id'];}$attr->set_options($term_ids);$attr->set_visible(true);$attr->set_variation(true);$attributes[]=$attr;}
 $p->set_attributes($attributes);$id=$p->save();
 update_field('field_bj_title_ar',$item['title_ar'],$id);update_field('field_bj_collection',$item['collection'],$id);update_field('field_bj_diaper_type',$item['type'],$id);
 $rows=[];foreach($item['benefits'] as $i=>$benefit)$rows[]=['benefit_en'=>$benefit,'benefit_ar'=>$item['benefits_ar'][$i]??''];update_field('field_bj_benefits',$rows,$id);
 update_field('field_bj_attribute_labels_ar',array_map(fn($a)=>['attribute_name'=>$a['name'],'attribute_label_ar'=>$a['name_ar'],'attribute_value_ar'=>$a['value_ar']],$item['attributes']),$id);
 $old=[];$old_ids=get_posts(['post_type'=>'product_variation','post_parent'=>$id,'post_status'=>['publish','private'],'fields'=>'ids','numberposts'=>-1]);
 foreach($old_ids as $vid){$term_slug=get_post_meta($vid,'attribute_pa_size',true);$term=$term_slug?get_term_by('slug',$term_slug,'pa_size'):false;$label=$term?$term->name:get_post_meta($vid,'attribute_size',true);if($label!==''&&(!isset($old[$label])||$term))$old[$label]=$vid;}
 $used=[];
 foreach($item['sizes'] as $size){$v=new WC_Product_Variation($old[$size['size']]??0);$v->set_parent_id($id);$v->set_status('publish');$term=get_term_by('name',$size['size'],'pa_size');$v->set_attributes(['pa_size'=>$term?$term->slug:$size['size']]);$v->set_image_id(bj_import_media($size['image']));$v->set_manage_stock(false);$v->set_stock_status('instock');$vid=$v->save();$used[]=$vid;foreach(['min_weight'=>$size['min'],'max_weight'=>$size['max'],'pack_counts'=>$size['packs'],'size_label_ar'=>$size['size']] as $key=>$value)update_post_meta($vid,'bj_'.$key,$value===null?'':$value);}
 foreach(array_diff($old_ids,$used) as $unused)wp_trash_post($unused);
 if($p->is_type('variable')){wc_delete_product_transients($id);WC_Product_Variable::sync($id);}
 wp_set_object_terms($id,$item['collection'],'product_cat');
}
update_field('field_bj_aggregators_enabled',1,'option');
update_field('field_bj_retailers',array_map(fn($r)=>['retailer_name'=>$r['name'],'retailer_name_ar'=>$r['name'],'retailer_url'=>$r['url'],'retailer_logo'=>bj_import_media($r['logo']),'retailer_enabled'=>1],$data['retailers']),'option');
update_field('field_bj_socials',array_map(fn($r)=>['social_name'=>$r['name'],'social_url'=>$r['url'],'social_icon'=>bj_import_media($r['icon'])],$data['socials']),'option');
update_field('field_bj_contact_email','babyjoy@ughi.com.sa','option');
// Notifications stay disabled in local development. Configure recipient explicitly on production.
update_field('field_bj_notification_email','','option');
$strings=[
'Size'=>['Size','المقاس'],'home'=>['Home','الرئيسية'],'products'=>['Products','المنتجات'],'select_size'=>['Select a size','اختاري المقاس'],'size'=>['Size','المقاس'],'weight_range'=>['Weight range','نطاق الوزن'],'pack_counts'=>['Diapers per pack','عدد الحفاضات في العبوة'],'benefits'=>['Benefits','المميزات'],'where_to_buy'=>['Where to buy','أماكن الشراء'],'size_guide'=>['Size guide','دليل المقاسات'],'kg'=>['kg','كغ'],'no_match'=>['No matching size for this weight.','لا يوجد مقاس مطابق لهذا الوزن.'],'also_fits'=>['Also fits','يناسب أيضاً'],'range_pending'=>['Weight range not confirmed','نطاق الوزن غير مؤكد'],'close'=>['Close','إغلاق'],'contact_title'=>['Contact us','تواصلي معنا'],'name'=>['Name','الاسم'],'email'=>['Email','البريد الإلكتروني'],'phone'=>['Phone','رقم الهاتف'],'message'=>['Message','الرسالة'],'consent'=>['I consent to my details being used to respond to this enquiry.','أوافق على استخدام بياناتي للرد على هذا الاستفسار.'],'send'=>['Send message','إرسال الرسالة'],'sending'=>['Sending…','جارٍ الإرسال…'],'contact_success'=>['Thank you. Your message has been received.','شكراً، تم استلام رسالتك.'],'contact_error'=>['Unable to send. Please try again.','تعذر الإرسال. يرجى المحاولة مرة أخرى.'],'sound_on'=>['Sound on','تشغيل الصوت'],'sound_off'=>['Sound off','إيقاف الصوت']];
$rows=[];foreach($strings as $key=>$values)$rows[]=['string_key'=>$key,'string_en'=>$values[0],'string_ar'=>$values[1]];update_field('field_bj_ui_strings',$rows,'option');
$chrome=$data['chrome'];foreach($chrome as &$lang)foreach(['header','footer'] as $part){preg_match_all('/\{\{asset:([^}]+)\}\}/',$lang[$part]['markup'],$m);foreach(array_unique($m[1]) as $path)bj_import_media($path);}unset($lang);
update_option('bj_chrome',$chrome);
foreach(['en','ar'] as $lang)foreach(['header','footer'] as $part){$name=$part.'_'.$lang;update_field('field_bj_'.$name,array_map(fn($r)=>[$name.'_label'=>$r['label'],$name.'_value'=>$r['value']],$chrome[$lang][$part]['fields']),'option');}
set_theme_mod('custom_logo',bj_import_media('img/logos/babyjoy-wordmark-en.webp'));
$locations=[];$menus=[];foreach(['en','ar'] as $lang){
 $name='BabyJoy '.$lang;$menu=wp_get_nav_menu_object($name);$menu_id=$menu?$menu->term_id:wp_create_nav_menu($name);$existing_items=array_values(wp_get_nav_menu_items($menu_id)?:[]);
 foreach($data['chrome'][$lang]['nav'] as $i=>$item){$url=$item['url'];$anchor=str_starts_with($url,'#')?$url:'';$slug=$anchor?'index':basename($url,'.html');$target=$pages[$slug][$lang]??$pages['index'][$lang];$settings=['menu-item-title'=>$item['label'],'menu-item-position'=>$i+1,'menu-item-status'=>'publish'];
 if($anchor){$settings['menu-item-type']='custom';$settings['menu-item-url']=get_permalink($target).$anchor;}else{$settings['menu-item-type']='post_type';$settings['menu-item-object']='page';$settings['menu-item-object-id']=$target;}
 wp_update_nav_menu_item($menu_id,$existing_items[$i]->ID??0,$settings);
 }if(function_exists('pll_set_term_language'))pll_set_term_language($menu_id,$lang);$menus[$lang]=$menu_id;
}
if(function_exists('pll_save_term_translations'))pll_save_term_translations($menus);$locations['primary']=$menus['ar'];set_theme_mod('nav_menu_locations',$locations);
update_option('bj_media_ids',$media);update_option('woocommerce_onboarding_profile',['completed'=>true]);update_option('woocommerce_task_list_hidden','yes');update_option('blog_public',0);update_option('permalink_structure','/%postname%/');flush_rewrite_rules();
WP_CLI::success('Imported '.count($data['pages']).' pages, '.count($data['products']).' products, '.count($media).' media files.');
