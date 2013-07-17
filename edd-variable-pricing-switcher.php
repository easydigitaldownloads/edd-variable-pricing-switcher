<?php
/*
Plugin Name: Easy Digital Downloads - Variable Pricing Switcher
Plugin URI: http://www.barrykooij.com/edd-checkout-variable-pricing-switcher
Description: Easy Digital Downloads - Variable Pricing Switcher
Version: 1.0.0
Author: Barry Kooij
Author URI: http://www.barrykooij.com/
*/

if ( ! defined( 'EDD_VPS_PLUGIN_DIR' ) ) {
	define( 'EDD_VPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EDD_VPS_PLUGIN_FILE' ) ) {
	define( 'EDD_VPS_PLUGIN_FILE', __FILE__ );
}

class EDD_Variable_Pricing_Switcher {
	public function __construct() {
		add_action( 'edd_checkout_form_top', array( $this, 'pricing_switcher' ) );
	}

	public function pricing_switcher() {
		global $edd_options, $user_ID, $post;

		// Get first item of cart - this plugin only works for 1 product webshops
		$item = array_shift( edd_get_cart_contents() );

		// This plugin only work with variable pricing enabled
		if( !edd_has_variable_prices( $item[ 'id' ] ) ) {
			return;
		}

		// Get pricing options
		$pricing_options = edd_get_variable_prices( $item[ 'id' ] );

		// Only show the select box if we have more than 1 pricing option
		if( count( $pricing_options ) < 2 ) {
			return;
		}

	?>
	<form name="edd_variable_pricing_switcher" action="<?php echo edd_get_checkout_uri(); ?>" method="post">
		<select name="edd-variable-pricing-switcher">
		<?php
			foreach( $pricing_options as $pricing_id => $pricing_option ) {
				echo "<option value='{$pricing_id}'" . ( ( $pricing_id == $item[ 'options' ][ 'price_id' ] ) ? " selected='selected'" : "" ) . ">{$pricing_option[ 'name' ]} - " . edd_currency_filter( edd_format_amount( $pricing_option[ 'amount' ] ) ) . "</option>\n";
			}
		?>
		</select>
	</form>
	<?php

	}
}

add_action( 'plugins_loaded', function () {
	new EDD_Variable_Pricing_Switcher();
} );