<?php
if(!defined('ABSPATH'))exit;
// A release carries media once for older installations. Keep editable uploads intact.
add_action('init',function(){
 $archive=get_template_directory().'/babyjoy-media.zip';
 if(is_file($archive)){
  $uploads=wp_upload_dir();$ok=true;$entries=[];
  if(class_exists('ZipArchive')){
   $zip=new ZipArchive();
   if($zip->open($archive)===true){for($i=0;$i<$zip->numFiles;$i++){$name=$zip->getNameIndex($i);if(!str_ends_with($name,'/'))$entries[]=['filename'=>$name,'content'=>$zip->getFromIndex($i)];}$zip->close();}else $ok=false;
  }else{
   require_once ABSPATH.'wp-admin/includes/class-pclzip.php';
   $zip=new PclZip($archive);$entries=$zip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);if(!is_array($entries)){$entries=[];$ok=false;}
  }
  foreach($entries as $entry){
   $name=$entry['filename'];if(!str_starts_with($name,'uploads/babyjoy/')||str_contains($name,'..')||str_ends_with($name,'/'))continue;
   $target=$uploads['basedir'].'/'.substr($name,8);
   if(is_file($target))continue;
   if($entry['content']===false||!wp_mkdir_p(dirname($target))||file_put_contents($target,$entry['content'])===false)$ok=false;
  }
  if($ok&&$entries)wp_delete_file($archive);
 }
 // Older sites store the Saudi retailers in options. Migrate without overwriting edits.
 if(function_exists('update_field')){
  $home=(int)(get_option('bj_page_ids',[])['index']['en']??0);
  if($home&&!metadata_exists('post',$home,'aggregators_enabled')){
   update_field('field_bj_aggregators_enabled',get_option('options_aggregators_enabled',1),$home);
   update_field('field_bj_retailers',get_field('retailers','option')?:[],$home);
  }
 }
},50);
