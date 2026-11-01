<?php
/** One-time migration, preserving other content edits and token references. */
if(!defined('WP_CLI'))exit;
foreach((get_option('bj_page_ids',[])['index']??[]) as $language=>$id){
 $layout=get_post_meta($id,'bj_layout',true);$fields=get_field('content_fields',$id);
 if(!preg_match('/<span class="n">\{\{text:(\d+)\}\}<\/span>\{\{text:(\d+)\}\}/',$layout,$match))continue;
 $number=(int)$match[1];$symbol=(int)$match[2];
 if($symbol!==$number+1||!isset($fields[$symbol]))WP_CLI::error('Unexpected degree field structure.');
 $fields[$number]['content_label']='Degree';$fields[$number]['content_value']=trim($fields[$number]['content_value']).trim($fields[$symbol]['content_value']);
 $layout=str_replace($match[0],'<span class="n">{{text:'.$number.'}}</span>',$layout);
 array_splice($fields,$symbol,1);
 $layout=preg_replace_callback('/\{\{text:(\d+)\}\}/',fn($m)=>'{{text:'.((int)$m[1]>$symbol?(int)$m[1]-1:(int)$m[1]).'}}',$layout);
 update_field('field_bj_content_fields',$fields,$id);update_post_meta($id,'bj_layout',wp_slash($layout));
 WP_CLI::log('Combined Degree field: '.$language);
}
WP_CLI::success('Degree fields combined.');
