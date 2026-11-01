<?php
if(!defined('WP_CLI'))exit;
$ids=get_option('bj_page_ids')['index'];$en=$ids['en'];$ar=$ids['ar'];
$admins=get_users(['role'=>'administrator','number'=>1]);wp_set_current_user($admins[0]->ID);
if(bj_arabic_page_id($en)!==$ar)WP_CLI::error('Polylang translation lookup failed.');
$english=get_field('content_fields',$en);$arabic=get_field('content_fields',$ar);$proxy=get_field('bj_linked_ar_fields',$en);
if(count($proxy)!==count($arabic))WP_CLI::error('Arabic rows did not load.');
foreach($arabic as $i=>$row)if(($proxy[$i]['bj_ar_content_value']??$proxy[$i]['field_bj_ar_content_value'])!==$row['content_value'])WP_CLI::error('Arabic value mismatch.');
$probe=$proxy;$probe[0]['field_bj_ar_content_value']=$arabic[0]['content_value'].' [local verification]';
try{
 update_field('field_bj_linked_ar_fields',$probe,$en);acf_get_store('values')->reset();
 if(get_field('content_fields',$ar)[0]['content_value']!==$probe[0]['field_bj_ar_content_value'])throw new RuntimeException('Save did not reach the Arabic page.');
 if(get_field('content_fields',$en)!==$english)throw new RuntimeException('English content changed.');
 if(metadata_exists('post',$en,'bj_linked_ar_fields'))throw new RuntimeException('Arabic values were duplicated on the English page.');
 WP_CLI::log('PASS Arabic values load through Polylang and save to the linked Arabic record only.');
}finally{update_field('field_bj_content_fields',$arabic,$ar);acf_get_store('values')->reset();}
WP_CLI::success('Bilingual editor verified; original content restored.');

$proxy=get_field('bj_linked_ar_fields',$ar);
if(count($proxy)!==count($english))WP_CLI::error('English rows did not load in Arabic editor.');
$probe=$proxy;$probe[0]['field_bj_ar_content_value']=$english[0]['content_value'].' [reverse verification]';
try{
 update_field('field_bj_linked_ar_fields',$probe,$ar);acf_get_store('values')->reset();
 if(get_field('content_fields',$en)[0]['content_value']!==$probe[0]['field_bj_ar_content_value'])throw new RuntimeException('Save did not reach English page.');
 if(get_field('content_fields',$ar)!==$arabic)throw new RuntimeException('Arabic content changed unexpectedly.');
 if(metadata_exists('post',$ar,'bj_linked_ar_fields'))throw new RuntimeException('Proxy data duplicated on Arabic page.');
 WP_CLI::log('PASS English values load and save through the Arabic editor.');
}finally{update_field('field_bj_content_fields',$english,$en);acf_get_store('values')->reset();}
WP_CLI::success('Both directions verified; original content restored.');
