<?php
if(!defined('ABSPATH'))exit;
/** Resolve the real Polylang translation; never create a second copy of its values. */
function bj_arabic_page_id($english_id){
 $english_id=absint($english_id);
 return function_exists('pll_get_post')&&pll_get_post_language($english_id)==='en'?(int)pll_get_post($english_id,'ar'):0;
}
/** The other member of the current English/Arabic translation pair. */
function bj_editor_translation_id($post_id){
 $post_id=absint($post_id);
 if(!function_exists('pll_get_post')||!function_exists('pll_get_post_language'))return 0;
 $language=pll_get_post_language($post_id);
 return in_array($language,['en','ar'],true)?(int)pll_get_post($post_id,$language==='en'?'ar':'en'):0;
}
add_filter('acf/location/rule_types',function($types){$types['Page']['bj_edit_language']='Editing language';return $types;});
add_filter('acf/location/rule_values/bj_edit_language',fn($values)=>['en'=>'English']);
add_filter('acf/location/rule_match/bj_edit_language',function($match,$rule,$screen){$id=absint($screen['post_id']??($_GET['post']??0));$language=function_exists('pll_get_post_language')?pll_get_post_language($id):'';return $rule['operator']==='=='?$language===$rule['value']:$language!==$rule['value'];},10,3);
add_action('acf/init',function(){
 if(!function_exists('acf_add_local_field_group'))return;
 acf_add_local_field_group(['key'=>'group_bj_arabic_translation','title'=>'Arabic translation','position'=>'normal','menu_order'=>20,'fields'=>[
 ['key'=>'field_bj_ar_help','name'=>'bj_ar_help','label'=>'Linked Arabic page','type'=>'message','message'=>'These fields load from and save to the Arabic page linked through Polylang.'],
 ['key'=>'field_bj_linked_ar_title','name'=>'bj_linked_ar_title','label'=>'Arabic page title','type'=>'text'],
 ['key'=>'field_bj_linked_ar_fields','name'=>'bj_linked_ar_fields','label'=>'Arabic content','type'=>'repeater','layout'=>'block','sub_fields'=>[
 ['key'=>'field_bj_ar_content_label','name'=>'bj_ar_content_label','label'=>'Reference','type'=>'text','readonly'=>1,'wrapper'=>['class'=>'bj-content-reference']],
 ['key'=>'field_bj_ar_content_value','name'=>'bj_ar_content_value','label'=>'Arabic content','type'=>'textarea','new_lines'=>''],
 ]],
 ['key'=>'field_bj_linked_ar_media','name'=>'bj_linked_ar_media','label'=>'Arabic images and video','type'=>'repeater','layout'=>'block','sub_fields'=>[
 ['key'=>'field_bj_ar_media_key','name'=>'bj_ar_media_key','label'=>'Reference','type'=>'text','readonly'=>1,'wrapper'=>['class'=>'bj-content-reference']],
 ['key'=>'field_bj_ar_media_file','name'=>'bj_ar_media_file','label'=>'Arabic media','type'=>'file','return_format'=>'id'],
 ]],
 ],'location'=>[[['param'=>'post_type','operator'=>'==','value'=>'page'],['param'=>'bj_edit_language','operator'=>'==','value'=>'en']]]]);
});
// Keep both languages together in the main page-content box rather than below all media.
add_action('acf/init',function(){
 if(!function_exists('acf_get_local_field_group'))return;
 $id=absint($_GET['post']??$_POST['post_ID']??0);
 if(!$id||!bj_editor_translation_id($id))return;
 $group=acf_get_local_field_group('group_bj_page');
 if(!$group)return;
 $english=array_values(acf_get_local_fields('group_bj_page'));
 $arabic=array_values(acf_get_local_fields('group_bj_arabic_translation'));
 $group['fields']=[$arabic[0],$arabic[1],$english[0],$arabic[2],$english[1],$arabic[3]];
 $is_ar=function_exists('pll_get_post_language')&&pll_get_post_language($id)==='ar';
 $group['fields'][0]['label']='Linked translation';
 $group['fields'][0]['message']='Edit both languages here. Each language saves to its own linked Polylang page.';
 $group['fields'][1]['label']=$is_ar?'English page title':'Arabic page title';
 $group['fields'][2]['label']=$is_ar?'Arabic content':'English content';
 $group['fields'][3]['label']=$is_ar?'English content':'Arabic content';
 $group['fields'][4]['label']=$is_ar?'Arabic images and video':'English images and video';
 $group['fields'][5]['label']=$is_ar?'English images and video':'Arabic images and video';
 $group['fields'][2]['wrapper']['width']='50';
 $group['fields'][3]['wrapper']['width']='50';
 foreach($group['fields'] as $order=>$field){
  acf_remove_local_field($field['key']);
  $field['parent']='group_bj_page';$field['menu_order']=$order;
  acf_add_local_field($field,true);
 }
 acf_remove_local_field_group('group_bj_arabic_translation');
},30);
add_filter('acf/load_value',function($value,$post_id,$field){
 $name=$field['name']??'';
 if(!in_array($name,['bj_linked_ar_title','bj_linked_ar_fields','bj_linked_ar_media'],true))return $value;
 $arabic_id=bj_editor_translation_id($post_id);if(!$arabic_id)return $name==='bj_linked_ar_title'?'':[];
 if($name==='bj_linked_ar_title')return get_the_title($arabic_id);
 if($name==='bj_linked_ar_fields')return array_map(fn($r)=>['field_bj_ar_content_label'=>$r['content_label'],'field_bj_ar_content_value'=>$r['content_value']],get_field('content_fields',$arabic_id)?:[]);
 return array_map(fn($r)=>['field_bj_ar_media_key'=>$r['media_key'],'field_bj_ar_media_file'=>$r['media_file']],get_field('media_fields',$arabic_id)?:[]);
},20,3);
// Stop ACF before it writes repeater child fields onto the English page.
add_filter('acf/pre_update_value',function($check,$value,$post_id,$field){
 $name=$field['name']??'';
 if(!in_array($name,['bj_linked_ar_title','bj_linked_ar_fields','bj_linked_ar_media'],true))return $check;
 $arabic_id=bj_editor_translation_id($post_id);
 if(!$arabic_id||!current_user_can('edit_post',$arabic_id))return true;
 if($name==='bj_linked_ar_title'){wp_update_post(['ID'=>$arabic_id,'post_title'=>sanitize_text_field($value)]);return true;}
 $content=$name==='bj_linked_ar_fields';$source=$content?'content_fields':'media_fields';$rows=get_field($source,$arabic_id)?:[];$incoming=[];
 foreach((array)$value as $key=>$row)if($key!=='acfcloneindex'&&is_array($row))$incoming[]=$row;
 // Layout references and positions remain owned by the translated page.
 foreach($rows as $i=>&$row){if(!isset($incoming[$i]))continue;$input=$incoming[$i];if($content)$row['content_value']=(string)($input['field_bj_ar_content_value']??$input['bj_ar_content_value']??$row['content_value']);else $row['media_file']=absint($input['field_bj_ar_media_file']??$input['bj_ar_media_file']??$row['media_file']);}unset($row);
 update_field($content?'field_bj_content_fields':'field_bj_media_fields',$rows,$arabic_id);
 return true;
},10,4);
add_filter('acf/prepare_field',function($field){
 $name=$field['_name']??$field['name']??'';
 if(!str_starts_with($name,'bj_linked_ar_'))return $field;
 $id=absint($_GET['post']??$_POST['post_ID']??0);
 if(!$id||!bj_editor_translation_id($id))return false;
 if(in_array($name,['bj_linked_ar_fields','bj_linked_ar_media'],true)&&!empty($field['value'])){$field['min']=count($field['value']);$field['max']=count($field['value']);}
 return $field;
});

add_action('acf/input/admin_enqueue_scripts',function(){
 $id=absint($_GET['post']??$_POST['post_ID']??0);
 if(!function_exists('pll_get_post_language')||pll_get_post_language($id)!=='ar')return;
 wp_add_inline_style('acf-input','[data-name="bj_linked_ar_fields"] input[type="text"],[data-name="bj_linked_ar_fields"] textarea{direction:ltr;text-align:left}[data-name="content_fields"] input[type="text"],[data-name="content_fields"] textarea{direction:rtl;text-align:right}');
},20);
