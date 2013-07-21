jQuery(document).ready(function($) {
	$('.edd-variable-pricing-switcher').change(function() {
		$(this).closest('form').submit();
	})
});