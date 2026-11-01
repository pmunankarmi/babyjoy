$=jQuery.noConflict();
new WOW().init();

jQuery(document).ready(function($) {
	
	const isRTL = $('html').attr('lang') === 'ar'; 
	
	
	const herotSlider = $('.mt-hero_slider');
    herotSlider.slick({
				rtl: isRTL,
                slidesToShow: 1,
                slidesToScroll: 1,
				arrows: false,
				dots: true,
			//	adaptiveHeight: true,
                autoplay: 1,
                autoplaySpeed: 2000,
                // Add more settings as needed
            });
	
	const productSlider = $('.mt-product_slider');
    productSlider.slick({
				rtl: isRTL,
                slidesToShow: 4,
                slidesToScroll: 1,
				arrows: false,
				dots: true,
			//	adaptiveHeight: true,
                autoplay: 1,
             //   variableWidth: true,
                autoplaySpeed: 2000,
				responsive: [
					{
					  breakpoint: 768,
					  settings: {
						arrows: false,
					//	centerMode: true,
				//	variableWidth: true,
					//	centerPadding: '40px',
						slidesToShow: 3
					  }
					},
					{
					  breakpoint: 480,
					  settings: {
						arrows: false,
						centerMode: true,
				//	variableWidth: true,
						centerPadding: '80px',
						slidesToShow: 1
					  }
					}
				  ]
                // Add more settings as needed
            });
            
          $('#mt-register').submit(function(e) {
       e.preventDefault();
		$(this).addClass('processing');
		var source			= $('#source').val();
        var full_name 		= $('#full_name').val();
        var email_address 	= $('#email_address').val();
		var mobile_number	= $('#mobile_number').val();
		var message			= $('#message').val();

		
        $.ajax({ 
             data: {action: 'get_registration', source: source, full_name:full_name, email_address: email_address, mobile_number: mobile_number, message: message },
             type: 'POST',
             url: ajaxurl,
             success: function(data) {
              //   alert(data); 
                  console.log(data); //should print out the name since you sent it along
              $('#message-box').html(data['register']);
				 $('#mt-register').removeClass('processing');
				 
            }
        });
	
    })  
	
})