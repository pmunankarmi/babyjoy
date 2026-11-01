<?php
if(!defined('WP_CLI'))exit;
$base=get_option('bj_page_ids')['index'];$pairs=get_option('bj_country_pages');
foreach(bj_countries() as $country){
 foreach(['en','ar'] as $lang){$id=$pairs[$country][$lang]??0;if(!$id||pll_get_post_language($id)!==$lang||pll_get_post($id,$lang==='en'?'ar':'en')!==$pairs[$country][$lang==='en'?'ar':'en'])WP_CLI::error('Missing linked translation: '.$country);
 if(get_post_meta($id,'bj_layout',true)!==get_post_meta($base[$lang],'bj_layout',true))WP_CLI::error('Homepage layout differs: '.$country);
 }
 $expected=['kuwait'=>4,'uae'=>6,'oman'=>1][$country]??0;
 $rows=get_field('retailers',$pairs[$country]['en'])?:[];
 if(count($rows)!==$expected||(bool)get_field('aggregators_enabled',$pairs[$country]['en'])!==($expected>0))WP_CLI::error('Wrong retailers: '.$country);
}
WP_CLI::success('Seven country translation pairs, copied layouts, retailer counts and visibility verified.');
