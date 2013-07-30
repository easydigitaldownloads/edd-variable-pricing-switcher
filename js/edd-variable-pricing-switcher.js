jQuery(document).ready(function($) {
	$('.edd-variable-pricing-switcher').change(function() {
		$(this).closest('form').submit();
	})

	var $fieldset = $('#edd_variable_pricing_switcher_discounts');
	var $target 	= $fieldset.find('div:first');

	$('body').bind('edd_discount_applied', function(event, discount_response) {
		$fieldset.css('display', 'block');
		$target.html(discount_response.html)
	});

	$('body').bind('edd_discount_removed', function(event, discount_response) {
		if( ! discount_response.discounts ) {
			$fieldset.css('display', 'none');
		}else {
			$target.html(discount_response.html)
		}
	});

});