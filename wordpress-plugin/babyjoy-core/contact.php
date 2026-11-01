<?php
if(!defined('ABSPATH'))exit;
add_action('wp_ajax_bj_contact','bj_contact_submit');
add_action('wp_ajax_nopriv_bj_contact','bj_contact_submit');
function bj_contact_submit(){
 if(!check_ajax_referer('bj_contact','nonce',false))wp_send_json_error(['message'=>__('Please refresh and try again.','babyjoy')],403);
 if(!empty($_POST['website']))wp_send_json_error([],400);
 $ip=hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'',wp_salt());$key='bj_rate_'.substr($ip,0,32);
 if(get_transient($key))wp_send_json_error(['message'=>__('Please wait before submitting again.','babyjoy')],429);
 $name=sanitize_text_field(wp_unslash($_POST['name']??''));$email=sanitize_email(wp_unslash($_POST['email']??''));$message=sanitize_textarea_field(wp_unslash($_POST['message']??''));
 if(!$name||mb_strlen($name)>150||!is_email($email)||!$message||mb_strlen($message)>10000||empty($_POST['consent']))wp_send_json_error(['message'=>__('Complete all required fields.','babyjoy')],422);
 $id=wp_insert_post(['post_type'=>'bj_submission','post_status'=>'private','post_title'=>$name.' — '.current_time('mysql'),'post_content'=>$message],true);
 if(is_wp_error($id))wp_send_json_error([],500);
 foreach(['email'=>$email,'name'=>$name,'message'=>$message,'phone'=>sanitize_text_field(wp_unslash($_POST['phone']??'')),'language'=>in_array($_POST['language']??'',['en','ar'],true)?$_POST['language']:'en','consent'=>'yes'] as $k=>$v)update_post_meta($id,'bj_'.$k,$v);
 set_transient($key,1,MINUTE_IN_SECONDS);
 $recipient=function_exists('get_field')?get_field('notification_email','option'):'';
 if(is_email($recipient))wp_mail($recipient,sprintf(__('New contact submission #%d','babyjoy'),$id),$name."\n".$email."\n\n".$message,['Reply-To: '.$email]);
 wp_send_json_success(['id'=>$id]);
}
add_action('add_meta_boxes',function(){add_meta_box('bj_contact_details',__('Submission details','babyjoy'),function($post){foreach(['name','email','phone','language','consent','message'] as $key)echo '<p><strong>'.esc_html(ucfirst($key)).'</strong><br>'.nl2br(esc_html(get_post_meta($post->ID,'bj_'.$key,true))).'</p>';},'bj_submission','normal');});
add_filter('manage_bj_submission_posts_columns',function($cols){$cols['bj_email']=__('Email','babyjoy');$cols['bj_language']=__('Language','babyjoy');return $cols;});
add_action('manage_bj_submission_posts_custom_column',function($column,$id){if(in_array($column,['bj_email','bj_language'],true))echo esc_html(get_post_meta($id,$column,true));},10,2);
add_action('restrict_manage_posts',function($type){if($type!=='bj_submission'||!current_user_can('manage_options'))return;echo '<a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bj_export'),'bj_export')).'">'.esc_html__('Export CSV','babyjoy').'</a>';});
function bj_csv_cell($value){$value=(string)$value;return preg_match('/^[\s]*[=+@\-\t\r]/u',$value)?"'".$value:$value;}
add_action('admin_post_bj_export',function(){
 if(!current_user_can('manage_options'))wp_die(esc_html__('Access denied.','babyjoy'),'',403);check_admin_referer('bj_export');
 nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="babyjoy-submissions-'.gmdate('Y-m-d').'.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['ID','Date','Name','Email','Phone','Language','Consent','Message'],',','"','');
 $page=1;do{$posts=get_posts(['post_type'=>'bj_submission','post_status'=>'private','posts_per_page'=>200,'paged'=>$page++,'orderby'=>'ID','order'=>'ASC']);foreach($posts as $p){$row=[$p->ID,$p->post_date];foreach(['name','email','phone','language','consent','message'] as $k)$row[]=bj_csv_cell(get_post_meta($p->ID,'bj_'.$k,true));fputcsv($out,$row,',','"','');}}while(count($posts)===200);fclose($out);exit;
});
// Native WordPress privacy exports and erasure include stored contact messages.
add_filter('wp_privacy_personal_data_exporters',function($list){$list['babyjoy']=['exporter_friendly_name'=>'BabyJoy contact submissions','callback'=>function($email,$page=1){$posts=get_posts(['post_type'=>'bj_submission','post_status'=>'private','meta_key'=>'bj_email','meta_value'=>$email,'numberposts'=>100,'paged'=>$page]);$data=[];foreach($posts as $p){$items=[];foreach(['name','email','phone','message','language','consent'] as $k)$items[]=['name'=>ucfirst($k),'value'=>get_post_meta($p->ID,'bj_'.$k,true)];$data[]=['group_id'=>'babyjoy-contact','group_label'=>'Contact submissions','item_id'=>'contact-'.$p->ID,'data'=>$items];}return ['data'=>$data,'done'=>count($posts)<100];}];return $list;});
add_filter('wp_privacy_personal_data_erasers',function($list){$list['babyjoy']=['eraser_friendly_name'=>'BabyJoy contact submissions','callback'=>function($email){$posts=get_posts(['post_type'=>'bj_submission','post_status'=>'private','meta_key'=>'bj_email','meta_value'=>$email,'numberposts'=>100]);foreach($posts as $p)wp_delete_post($p->ID,true);return ['items_removed'=>!empty($posts),'items_retained'=>false,'messages'=>[],'done'=>count($posts)<100];}];return $list;});
