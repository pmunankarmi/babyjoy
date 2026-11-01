<?php
if(!defined('ABSPATH'))exit;
function bj_countries(){return ['kuwait','uae','oman','bahrain','egypt','iraq','jordan'];}
function bj_country(){
 $country=get_query_var('bj_country');
 if(!$country&&is_singular('page'))$country=get_post_meta(get_queried_object_id(),'bj_country',true);
 return in_array($country,bj_countries(),true)?$country:'';
}
function bj_country_home($lang=null){
 $lang=$lang?:bj_language();$country=bj_country();
 return $country?(int)(get_option('bj_country_pages',[])[$country][$lang]??0):(int)(get_option('bj_page_ids',[])['index'][$lang]??0);
}
function bj_retailer_source(){
 $id=bj_country_home('en');return $id?:bj_country_home();
}
function bj_aggregators_enabled(){return (bool)bj_field('aggregators_enabled',bj_retailer_source());}
function bj_retailers(){return (array)bj_field('retailers',bj_retailer_source());}
add_filter('query_vars',function($vars){$vars[]='bj_country';return $vars;});
add_action('init',function(){
 $pages=get_option('bj_page_ids',[]);$countries=get_option('bj_country_pages',[]);
 foreach(bj_countries() as $country)foreach(['en','ar'] as $lang){
  $prefix=$lang==='en'?'en/':'';
  if(!empty($countries[$country][$lang]))add_rewrite_rule('^'.$prefix.$country.'/?$','index.php?page_id='.$countries[$country][$lang].'&bj_country='.$country,'top');
  foreach($pages as $slug=>$ids){if($slug==='index'||empty($ids[$lang]))continue;
   add_rewrite_rule('^'.$prefix.$country.'/'.preg_quote($slug,'#').'/?$','index.php?page_id='.$ids[$lang].'&bj_country='.$country,'top');
  }
  add_rewrite_rule('^'.$prefix.$country.'/product/([^/]+)/?$','index.php?post_type=product&name=$matches[1]&bj_language='.$lang.'&bj_country='.$country,'top');
 }
},30);
add_filter('redirect_canonical',fn($url)=>bj_country()?false:$url,20);
add_filter('page_link',function($url,$id){
 if(is_admin())return $url;
 foreach(get_option('bj_country_pages',[]) as $region=>$pair)foreach($pair as $language=>$page_id)if((int)$id===(int)$page_id)return home_url('/'.($language==='en'?'en/':'').$region.'/');
 $country=bj_country();if(!$country)return $url;
 foreach(get_option('bj_page_ids',[]) as $slug=>$ids)foreach($ids as $lang=>$page_id)if((int)$page_id===(int)$id)return home_url('/'.($lang==='en'?'en/':'').$country.'/'.($slug==='index'?'':$slug.'/'));
 return $url;
},20,2);
// Aggregators belong to the English homepage; either language editor edits the same country settings.
add_action('acf/init',function(){
 if(!function_exists('acf_get_local_fields'))return;
 $fields=array_values(acf_get_local_fields('group_bj_options'));$moved=[];
 foreach($fields as $field)if(in_array($field['name'],['aggregators_enabled','retailers'],true)){
  acf_remove_local_field($field['key']);$field['parent']='group_bj_country_retailers';$moved[]=$field;
 }
 acf_add_local_field_group(['key'=>'group_bj_country_retailers','title'=>'Country aggregators','fields'=>$moved,'location'=>[[['param'=>'post_type','operator'=>'==','value'=>'page']]],'menu_order'=>-5]);
},40);
add_filter('acf/prepare_field',function($field){
 if(!in_array($field['_name']??$field['name']??'',['aggregators_enabled','retailers'],true))return $field;
 $id=absint($_GET['post']??$_POST['post_ID']??0);$homes=array_values(get_option('bj_page_ids',[])['index']??[]);
 foreach(get_option('bj_country_pages',[]) as $pair)$homes=array_merge($homes,array_values($pair));
 return in_array($id,array_map('intval',$homes),true)?$field:false;
},30);
add_filter('acf/load_value',function($value,$id,$field){
 if(in_array($field['name']??'',['aggregators_enabled','retailers'],true)&&function_exists('pll_get_post_language')&&pll_get_post_language($id)==='ar'){
  $en=pll_get_post($id,'en');if($en)return get_field($field['name'],$en,false);
 }return $value;
},30,3);
add_filter('acf/pre_update_value',function($check,$value,$id,$field){
 if(in_array($field['name']??'',['aggregators_enabled','retailers'],true)&&function_exists('pll_get_post_language')&&pll_get_post_language($id)==='ar'){
  $en=pll_get_post($id,'en');if($en&&current_user_can('edit_post',$en)){update_field($field['key'],$value,$en);return true;}
 }return $check;
},20,4);
function bj_country_aggregator_markup($markup){
 $ids=get_option('bj_page_ids',[])['aggregators']??[];
 if(!in_array(get_the_ID(),array_map('intval',array_values($ids)),true))return $markup;
 $doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$markup);$xpath=new DOMXPath($doc);
 $groups=$xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," agg-group ")]');
 $first=$groups->item(0);$html='<div class="agg-group"><div class="agg-grid">';
 if(bj_aggregators_enabled())foreach(bj_retailers() as $r){if(empty($r['retailer_enabled']))continue;$name=bj_language()==='ar'?($r['retailer_name_ar']?:$r['retailer_name']):$r['retailer_name'];$html.='<article class="agg-card"><a class="agg-logo" href="'.esc_url($r['retailer_url']).'" target="_blank" rel="nofollow noopener noreferrer">'.wp_get_attachment_image($r['retailer_logo'],'medium',false,['alt'=>$name]).'</a><h3>'.esc_html($name).'</h3><a class="mt-btn agg-btn" href="'.esc_url($r['retailer_url']).'" target="_blank" rel="nofollow noopener noreferrer">'.esc_html(bj_t('where_to_buy')).'</a></article>';}
 $html.='</div></div>';
 if($first){$fragment=new DOMDocument();$fragment->loadHTML('<?xml encoding="UTF-8">'.$html);$first->parentNode->insertBefore($doc->importNode($fragment->getElementsByTagName('div')->item(0),true),$first);}
 foreach(iterator_to_array($groups) as $node)$node->parentNode->removeChild($node);
 $body=$doc->getElementsByTagName('body')->item(0);$result='';foreach($body->childNodes as $child)$result.=$doc->saveHTML($child);return $result;
}
add_filter('wp_nav_menu_objects',function($items){
 if(bj_aggregators_enabled())return $items;
 return array_values(array_filter($items,fn($item)=>parse_url($item->url,PHP_URL_FRAGMENT)!=='shop'&&!str_contains($item->url,'/aggregators/')));
},30);
