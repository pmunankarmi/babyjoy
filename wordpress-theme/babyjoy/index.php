<?php get_header();while(have_posts()):the_post();
if(is_singular('product')){get_template_part('template-parts/product');}
else{$markup=get_post_meta(get_the_ID(),'bj_layout',true);if($markup)echo bj_markup($markup,bj_field('content_fields'),bj_field('media_fields'));else the_content();}
endwhile;get_footer();
