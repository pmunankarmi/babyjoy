<?php
if(!defined('ABSPATH'))exit;
add_action('acf/init',function(){
 if(!function_exists('acf_add_local_field_group'))return;
 $field=function($name,$label,$type='text',$extra=[]){return array_merge(['key'=>'field_bj_'.$name,'name'=>$name,'label'=>$label,'type'=>$type],$extra);};
 $repeat=function($name,$label,$sub)use($field){return $field($name,$label,'repeater',['layout'=>'block','button_label'=>__('Add item','babyjoy'),'sub_fields'=>$sub]);};
 acf_add_options_page(['page_title'=>__('BabyJoy Theme Options','babyjoy'),'menu_title'=>__('BabyJoy Options','babyjoy'),'menu_slug'=>'babyjoy-options','capability'=>'manage_options','redirect'=>false]);
 acf_add_local_field_group(['key'=>'group_bj_options','title'=>__('Site settings','babyjoy'),'fields'=>[
 $field('gtm_container_id','Google Tag Manager container ID','text',['instructions'=>'Enter the GTM container ID, for example GTM-XXXXXXX. Leave blank to disable.']),$field('site_logo','Site logo','image',['return_format'=>'id']),$field('saudi_made_logo_ar','Saudi Made logo — Arabic','image',['return_format'=>'id']),$field('contact_email','Contact email','email'),$field('contact_phone','Phone'),$field('contact_address','Address','textarea'),$field('contact_address_ar','Address — Arabic','textarea'),$field('notification_email','Submission notification recipient','email'),$field('aggregators_enabled','Show aggregators','true_false',['default_value'=>1,'ui'=>1]),
 $repeat('retailers','Aggregators',[$field('retailer_name','Name'),$field('retailer_name_ar','Arabic name'),$field('retailer_url','Website','url'),$field('retailer_logo','Logo','image',['return_format'=>'id']),$field('retailer_enabled','Visible','true_false',['default_value'=>1,'ui'=>1])]),
 $repeat('socials','Social media',[$field('social_name','Name'),$field('social_url','URL','url'),$field('social_icon','Icon','image',['return_format'=>'id'])]),
 ...array_map(function($part)use($repeat,$field){return $repeat($part,$part,[$field($part.'_label','Label'),$field($part.'_value','Content','textarea',['new_lines'=>''])]);},['header_en','header_ar','footer_en','footer_ar']),
 $repeat('ui_strings','Interface translations',[$field('string_key','Key'),$field('string_en','English'),$field('string_ar','Arabic')]),
 ],'location'=>[[['param'=>'options_page','operator'=>'==','value'=>'babyjoy-options']]]]);
 acf_add_local_field_group(['key'=>'group_bj_page','title'=>__('Page content and media','babyjoy'),'fields'=>[
 $repeat('content_fields','Content fields',[$field('content_label','Field label'),$field('content_value','Content','textarea',['new_lines'=>''])]),
 $repeat('media_fields','Images and video',[$field('media_key','Reference'),$field('media_file','Media file','file',['return_format'=>'id'])])
 ],'location'=>[[['param'=>'post_type','operator'=>'==','value'=>'page']]]]);
 acf_add_local_field_group(['key'=>'group_bj_product','title'=>__('BabyJoy product content','babyjoy'),'fields'=>[
 $field('title_ar','Arabic product title'),$field('description_ar','Arabic description','wysiwyg'),$field('collection','Collection','select',['choices'=>['olive'=>'Premium Olive','comp'=>'Compressed','s8'=>'Super Big','wipes'=>'Wipes']]),$field('diaper_type','Product type','select',['choices'=>['tape'=>'Tape','pants'=>'Pants','wipes'=>'Wipes']]),
 $repeat('benefits','Benefits',[$field('benefit_en','English'),$field('benefit_ar','Arabic')]),
 $repeat('attribute_labels_ar','Arabic attribute labels',[$field('attribute_name','Native attribute name'),$field('attribute_label_ar','Arabic label'),$field('attribute_value_ar','Arabic value')]),
 $field('product_aggregators','Product aggregators','checkbox',['choices'=>[],'instructions'=>'Leave empty to use all enabled site aggregators.']),
 ],'location'=>[[['param'=>'post_type','operator'=>'==','value'=>'product']]]]);
 // Native WooCommerce variation editing owns sizes and images; ACF stores extra specifications as variation meta.
});
add_filter('acf/load_field/name=product_aggregators',function($field){if(function_exists('get_field'))foreach((array)get_field('retailers','option') as $row)if(!empty($row['retailer_name']))$field['choices'][sanitize_title($row['retailer_name'])]=$row['retailer_name'];return $field;});
add_action('woocommerce_product_after_variable_attributes',function($loop,$data,$variation){foreach(['min_weight'=>'Minimum weight (kg)','max_weight'=>'Maximum weight (kg; blank = open ended)','pack_counts'=>'Pack counts','size_label_ar'=>'Arabic size label'] as $key=>$label){woocommerce_wp_text_input(['id'=>'bj_'.$key.'['.$variation->ID.']','label'=>__($label,'babyjoy'),'value'=>get_post_meta($variation->ID,'bj_'.$key,true),'wrapper_class'=>'form-row form-row-full','type'=>str_contains($key,'weight')?'number':'text','custom_attributes'=>str_contains($key,'weight')?['step'=>'0.5','min'=>'0']:[]]);}} ,10,3);
add_action('woocommerce_save_product_variation',function($id){foreach(['min_weight','max_weight','pack_counts','size_label_ar'] as $key){$value=isset($_POST['bj_'.$key][$id])?sanitize_text_field(wp_unslash($_POST['bj_'.$key][$id])):'';update_post_meta($id,'bj_'.$key,$value);}},10);

// Only a container identifier is editable; executable markup remains theme-controlled.
add_action('wp_head',function(){
 if(!function_exists('get_field'))return;
 $id=strtoupper(trim((string)get_field('gtm_container_id','option')));
 if(!preg_match('/^GTM-[A-Z0-9]+$/',$id))return;
 echo '<script async src="'.esc_url('https://www.googletagmanager.com/gtm.js?id='.$id).'"></script>' . "\n";
 echo "<script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date());</script>\n";
},1);
add_filter('acf/validate_value/name=gtm_container_id',function($valid,$value){
 if($valid!==true||trim((string)$value)==='')return $valid;
 return preg_match('/^GTM-[A-Z0-9]+$/',strtoupper(trim((string)$value)))?true:__('Enter a valid GTM container ID.','babyjoy');
},10,2);
