<?php
if(!defined('WP_CLI'))exit;
foreach((get_option('bj_page_ids',[])['index']??[]) as $language=>$id){
 $layout=get_post_meta($id,'bj_layout',true);$fields=get_field('content_fields',$id);
 if(!preg_match('/(<output\b[^>]*>)\{\{text:(\d+)\}\}<small>\{\{text:(\d+)\}\}<\/small>/',$layout,$match))continue;
 $number=(int)$match[2];$unit=(int)$match[3];if($unit!==$number+1||!isset($fields[$unit]))WP_CLI::error('Unexpected weight field structure.');
 $fields[$number]['content_label']='Weight';$fields[$number]['content_value']=trim($fields[$number]['content_value']).' '.trim($fields[$unit]['content_value']);
 $layout=str_replace($match[0],$match[1].'{{measure:'.$number.'}}',$layout);array_splice($fields,$unit,1);
 $layout=preg_replace_callback('/\{\{(text|measure):(\d+)\}\}/',fn($m)=>'{{'.$m[1].':'.((int)$m[2]>$unit?(int)$m[2]-1:(int)$m[2]).'}}',$layout);
 update_field('field_bj_content_fields',$fields,$id);update_post_meta($id,'bj_layout',wp_slash($layout));WP_CLI::log('Combined Weight field: '.$language);
}
