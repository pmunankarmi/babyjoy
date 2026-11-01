<?php
if(!defined('ABSPATH'))exit;
// Imported labels identify content, but are not editable website content.
add_filter('acf/prepare_field',function($field){
 $names=['content_label','header_en_label','header_ar_label','footer_en_label','footer_ar_label'];
 if(in_array($field['_name']??$field['name']??'',$names,true)){
  $field['readonly']=1;
  $field['wrapper']['class']=trim(($field['wrapper']['class']??'').' bj-content-reference');
 }
 $name=$field['_name']??$field['name']??'';
 if(in_array($name,['content_value','header_en_value','header_ar_value','footer_en_value','footer_ar_value','string_en','string_ar','bj_ar_content_value'],true)){
  $value=(string)($field['value']??'');
  $short=!preg_match('/[\r\n]/',$value)&&(mb_strlen($value)<=120||preg_match('#^(?:https?://|mailto:|tel:)#',$value));
  $field['type']=$short?'text':'textarea';
  // ACF validated the original type before this filter; apply the new type's defaults.
  $field_type=acf_get_field_type($field['type']);
  if($field_type){$field=array_merge($field_type->defaults,$field);}
  if(!$short){$field['rows']=max(3,min(8,(int)ceil(mb_strlen($value)/90)));$field['new_lines']='';}
 }
 return $field;
});
add_action('acf/input/admin_enqueue_scripts',function(){
 wp_add_inline_style('acf-input','.acf-field.bj-content-reference{display:none!important}[data-name=bj_linked_ar_fields] textarea,[data-name=bj_linked_ar_fields] input[type=text]{direction:rtl;text-align:right}[data-name=bj_linked_ar_fields] .acf-row-handle.order,[data-name=bj_linked_ar_media] .acf-row-handle.order{cursor:default;pointer-events:none}');
 wp_add_inline_script('acf-input', <<<'JS'
(function(){
 function labelContent(){
  document.querySelectorAll('.acf-row:not(.acf-clone)').forEach(function(row){
   var reference=row.querySelector('.bj-content-reference input');
   if(!reference||!reference.value.trim())return;
   var value=row.querySelector('[data-name="content_value"], [data-name="header_en_value"], [data-name="header_ar_value"], [data-name="footer_en_value"], [data-name="footer_ar_value"], [data-name="bj_ar_content_value"], [data-name="bj_ar_media_file"]');
   var label=value&&value.querySelector('.acf-label label');
   if(label)label.textContent=reference.value;
  });
 }
 if(window.acf){acf.addAction('ready',labelContent);acf.addAction('append',labelContent);}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',labelContent);else labelContent();
})();
JS
 );
});
