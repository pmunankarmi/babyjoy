<?php
if(!defined('ABSPATH'))exit;
// Imported labels identify content, but are not editable website content.
add_filter('acf/prepare_field',function($field){
 $names=['content_label','header_en_label','header_ar_label','footer_en_label','footer_ar_label'];
 if(in_array($field['_name']??$field['name']??'',$names,true)){
  $field['readonly']=1;
  $field['wrapper']['class']=trim(($field['wrapper']['class']??'').' bj-content-reference');
 }
 return $field;
});
add_action('acf/input/admin_enqueue_scripts',function(){
 wp_add_inline_style('acf-input','.acf-field.bj-content-reference{display:none!important}');
 wp_add_inline_script('acf-input', <<<'JS'
(function(){
 function labelContent(){
  document.querySelectorAll('.acf-row:not(.acf-clone)').forEach(function(row){
   var reference=row.querySelector('.bj-content-reference input');
   if(!reference||!reference.value.trim())return;
   var value=row.querySelector('[data-name="content_value"], [data-name="header_en_value"], [data-name="header_ar_value"], [data-name="footer_en_value"], [data-name="footer_ar_value"]');
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
