<?php
if(!defined('ABSPATH'))exit;
function bj_language(){ $q=get_query_var('bj_language');if(in_array($q,['en','ar'],true))return $q;return function_exists('pll_current_language')?(pll_current_language('slug')?:'ar'):'ar'; }
function bj_field($name,$id=false){return function_exists('get_field')?get_field($name,$id):get_post_meta($id?:get_the_ID(),$name,true);}
function bj_strings(){static $strings=[];if(!$strings)foreach((array)bj_field('ui_strings','option') as $r)$strings[$r['string_key']]=$r['string_'.bj_language()]?:$r['string_en'];return $strings;}
function bj_t($key){return bj_strings()[$key]??'';}
function bj_page_url($slug,$lang=null){$lang=$lang?:bj_language();if(function_exists('bj_country')&&bj_country())return home_url('/'.($lang==='en'?'en/':'').bj_country().'/'.($slug==='index'?'':$slug.'/'));$ids=get_option('bj_page_ids',[]);return isset($ids[$slug][$lang])?get_permalink($ids[$slug][$lang]):home_url('/');}
function bj_product_url($id,$lang=null){return home_url('/'.(($lang?:bj_language())==='en'?'en/':(function_exists('bj_country')&&bj_country()?'':'ar/')).(function_exists('bj_country')&&bj_country()?bj_country().'/':'').'product/'.get_post_field('post_name',$id).'/');}
function bj_asset($path){$ids=get_option('bj_media_ids',[]);$url=!empty($ids[$path])?wp_get_attachment_url($ids[$path]):false;return $url?:wp_upload_dir()['baseurl'].'/babyjoy/'.ltrim($path,'/');}
function bj_markup($markup,$fields=[],$media=[]){
 $markup=preg_replace_callback('/\{\{measure:(\d+)\}\}/',function($m)use($fields){$value=$fields[(int)$m[1]]['content_value']??$fields[(int)$m[1]]['value']??'';if(preg_match('/^(.+?)\s+([^\d\s]+)$/u',$value,$parts))return esc_html($parts[1]).' <small>'.esc_html($parts[2]).'</small>';return esc_html($value);},$markup);
 $markup=preg_replace_callback('/\{\{text:(\d+)\}\}/',fn($m)=>esc_html($fields[(int)$m[1]]['content_value']??$fields[(int)$m[1]]['value']??''),$markup);
 $overrides=[];foreach((array)$media as $r)if(is_array($r)&&!empty($r['media_key'])){ $url=wp_get_attachment_url($r['media_file']);if($url)$overrides[$r['media_key']]=$url; }
 $markup=preg_replace_callback('/\{\{asset:([^}]+)\}\}/',fn($m)=>esc_url($overrides[$m[1]]??bj_asset($m[1])),$markup);
 $markup=str_replace('href="./"','href="'.esc_url(bj_page_url('index')).'"',$markup);
 $markup=preg_replace_callback('/href="([^"#]*\.html)(#[^"]*)?"/',function($m){$base=basename($m[1],'.html');$hash=$m[2]??'';if(str_starts_with($base,'product-')){$p=get_page_by_path(substr($base,8),OBJECT,'product');$url=$p?bj_product_url($p->ID):bj_page_url('products');}else $url=bj_page_url($base);return 'href="'.esc_url($url.$hash).'"';},$markup);
 $markup=preg_replace_callback('/href="(#(?:test|shop|size-guide|olive|compressed|size8|wipes))"/',fn($m)=>'href="'.esc_url(bj_page_url('index').$m[1]).'"',$markup);
 // Contact address comes from the site's native options, not the imported source.
 $email=bj_field('contact_email','option');
 $markup=preg_replace('/mailto:[^"\s<]+/','mailto:'.esc_attr($email),$markup);
 $markup=preg_replace('/[a-zA-Z0-9._%+-]+@(?:ughi|gulfhygienic)\.com\.sa/',esc_html($email),$markup);
 $markup=do_shortcode($markup);if(!bj_aggregators_enabled())$markup=preg_replace('/<section\b[^>]*id="shop"[^>]*>.*?<\/section>/s','',$markup);return function_exists('bj_country_aggregator_markup')?bj_country_aggregator_markup($markup):$markup;
}
function bj_chrome($part){$data=get_option('bj_chrome',[])[bj_language()][$part]??[];$name=$part.'_'.bj_language();$rows=bj_field($name,'option');$fields=$rows?array_map(fn($r)=>['value'=>$r[$name.'_value']],$rows):($data['fields']??[]);return bj_markup($data['markup']??'',$fields);}
function bj_socials(){foreach((array)bj_field('socials','option') as $r)echo '<a href="'.esc_url($r['social_url']).'" target="_blank" rel="noopener noreferrer">'.wp_get_attachment_image($r['social_icon'],'thumbnail',false,['alt'=>$r['social_name']]).'</a>';}
function bj_nav($location='primary'){$menu=wp_get_nav_menu_object('BabyJoy '.bj_language());wp_nav_menu(['menu'=>$menu?$menu->term_id:0,'theme_location'=>$location,'container'=>false,'fallback_cb'=>false,'depth'=>1,'items_wrap'=>'<ul class="bj-menu">%3$s</ul>']);}
add_shortcode('babyjoy_socials',function(){ob_start();echo '<ul class="mt-social d-flex justify-content-center align-items-center">';foreach((array)bj_field('socials','option') as $r){if(!is_array($r))continue;echo '<li><a href="'.esc_url($r['social_url']).'" target="_blank" rel="noopener noreferrer">'.wp_get_attachment_image($r['social_icon'],'thumbnail',false,['alt'=>$r['social_name']]).'</a></li>';}echo '</ul>';return ob_get_clean();});

add_filter('wp_nav_menu_objects',function($items){foreach($items as $item){$hash=parse_url($item->url,PHP_URL_FRAGMENT);if($hash&&in_array($hash,['test','shop','size-guide','olive','compressed','size8','wipes'],true))$item->url=bj_page_url('index').'#'.$hash;}return $items;});
add_filter('pre_get_document_title',function($title){return is_singular('product')?bj_product_title(get_queried_object_id()).' — '.get_bloginfo('name'):$title;});
add_shortcode('babyjoy_contact_details',function(){
 $email=bj_field('contact_email','option');$phone=bj_field('contact_phone','option');$address=bj_field(bj_language()==='ar'?'contact_address_ar':'contact_address','option');$html='';
 if($email)$html.='<p>'.esc_html(bj_t('email')).': <a href="'.esc_url('mailto:'.$email).'">'.esc_html($email).'</a></p>';
 if($phone)$html.='<p>'.esc_html(bj_t('phone')).': <a href="'.esc_url('tel:'.preg_replace('/[^+0-9]/','',$phone)).'">'.esc_html($phone).'</a></p>';
 if($address)$html.='<p>'.nl2br(esc_html($address)).'</p>';return $html;
});
add_filter('body_class',function($classes){if(!bj_aggregators_enabled())$classes[]='bj-no-aggregators';return $classes;});
