jQuery(document).ready(function($) {
	$('#expire_passwords_form').submit(function(){
		data = {
			action: 'ep_expire_users'
		};

		$.post(ajaxurl, data, function(response){
			$('#EP_response').html(response);
		});
		return false;
	});
});