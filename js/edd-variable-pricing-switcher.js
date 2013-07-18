jQuery(document).ready(function($) {
	$('select[name=edd-variable-pricing-switcher]').change(function() {
		$(this).closest('form').submit();
	})
});