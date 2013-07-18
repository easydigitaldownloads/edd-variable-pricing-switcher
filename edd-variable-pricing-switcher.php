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
		add_filter( 'edd_settings_extensions', array( $this, 'settings' ), 1 );
		add_action( 'init', array( $this, 'catch_post' ), 11 );
		add_action( 'init', array( $this, 'force_single_product' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'edd_checkout_form_top', array( $this, 'pricing_switcher' ) );
	}

	public function settings( $settings ) {

		$vps_settings = array(
			array(
				'id' => 'vps_settings',
				'name' => '<strong>' . __('Variable Pricing Switcher Settings', 'edd-vps') . '</strong>',
				'type' => 'header'
			),
			array(
				'id'    => 'vps_label',
				'name'  => __( 'Switcher Label', 'edd-vps' ),
				'desc'  => __( 'What text should be displayed before the select box', 'edd-vps' ),
				'type'  => 'text',
				'size'  => 'regular',
				'std' 	=> 'License'
			),
			array(
				'id' => 'vps_disable_cart',
				'name' => __('Disable cart on checkout page', 'edd-vps'),
				'desc' => __('Check this to disable the cart on the checkout page.', 'edd-vps'),
				'type' => 'checkbox'
			),
			array(
				'id' => 'vps_force_single_product',
				'name' => __('Force single product', 'edd'),
				'desc' => __('Check this to only allow 1 product and 1 variable pricing to be bought at once.', 'edd-vps'),
				'type' => 'checkbox'
			),
		);

  	return array_merge( $settings, $vps_settings );
	}

	public function force_single_product() {
		global $edd_options;

		if( isset( $edd_options[ 'vps_force_single_product' ] ) && $edd_options[ 'vps_force_single_product' ] == '1' ) {
			$cart = edd_get_cart_contents();
			if( count( $cart ) > 1 ) {
				// Use the one that's added last
				EDD()->session->set( 'edd_cart', array( array_pop( $cart ) ) );
			}
		}

	}

	public function catch_post() {

		// If Variable pricing switch post is set, switch to post option of first (should be only) product.
		if( isset( $_POST[ 'edd-variable-pricing-switcher' ] ) ) {
			$product = array_shift( edd_get_cart_contents() );
			$product[ 'options' ][ 'price_id' ] = $_POST[ 'edd-variable-pricing-switcher' ]; // Make this more secure, can we check if this is a valid pricing option?
			EDD()->session->set( 'edd_cart', array( $product ) );
		}

	}

	public function enqueue_scripts() {
		global $edd_options, $post;

		if( $post->ID != $edd_options[ 'purchase_page' ] )
			return;

		wp_enqueue_script( 'edd-variable-pricing-switcher-js', plugins_url( '/js/edd-variable-pricing-switcher.js' , __FILE__ ) );
		wp_enqueue_style( 'edd-variable-pricing-switcher-css', plugins_url( '/css/edd-variable-pricing-switcher.css' , __FILE__ ) ); // Maybe just print this css, it's only a few lines
	}

	public function pricing_switcher() {
		global $edd_options, $user_ID, $post;

		echo '<pre>';
			print_r( $edd_options );
		echo '</pre>';

		// Get first item of cart - this plugin only works for 1 product webshops
		$product = array_shift( edd_get_cart_contents() );

		// This plugin only work with variable pricing enabled
		if( !edd_has_variable_prices( $product[ 'id' ] ) ) {
			return;
		}

		// Get pricing options
		$pricing_options = edd_get_variable_prices( $product[ 'id' ] );

		// Only show the select box if we have more than 1 pricing option
		if( count( $pricing_options ) < 2 ) {
			return;
		}

		// Get label
		$vps_label = ( ( isset( $edd_options[ 'vps_label' ] ) ) ? $edd_options[ 'vps_label' ] : 'License' );

	?>
	<form name="edd_variable_pricing_switcher" action="<?php echo edd_get_checkout_uri(); ?>" method="post">
		<fieldset id="edd_variable_pricing_switcher-fieldset">
			<legend><?php echo $vps_label; ?></legend>
			<select name="edd-variable-pricing-switcher" id="edd-variable-pricing-switcher">
			<?php
				foreach( $pricing_options as $pricing_id => $pricing_option ) {
					echo "<option value='{$pricing_id}'" . ( ( $pricing_id == $product[ 'options' ][ 'price_id' ] ) ? " selected='selected'" : "" ) . ">{$pricing_option[ 'name' ]} - " . edd_currency_filter( edd_format_amount( $pricing_option[ 'amount' ] ) ) . "</option>\n";
				}
			?>
			</select>
		</fieldset>
	</form>
	<?php

	}
}

add_action( 'plugins_loaded', function () {
	new EDD_Variable_Pricing_Switcher();
} );