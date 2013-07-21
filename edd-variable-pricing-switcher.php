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

require_once( EDD_VPS_PLUGIN_DIR . '/includes/metabox.php' );

class EDD_Variable_Pricing_Switcher {

	public function __construct() {
		add_filter( 'edd_settings_extensions', array( $this, 'settings' ), 1 );
		add_filter( 'edd_get_template_part', array( $this, 'filter_checkout_cart' ) );
		add_action( 'init', array( $this, 'catch_post' ), 11 );
		add_action( 'init', array( $this, 'force_single_variable_price' ), 10 );
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
				'id' => 'vps_force_single_variable_price',
				'name' => __('Force single variable price', 'edd'),
				'desc' => __('Check this to only allow 1 variable price per product to be bought at once.', 'edd-vps'),
				'type' => 'checkbox'
			),
		);

  	return array_merge( $settings, $vps_settings );
	}

	public function filter_checkout_cart( $templates, $slug, $name ) {
		global $edd_options;

		if( isset( $edd_options[ 'vps_disable_cart' ] ) && $edd_options[ 'vps_disable_cart' ] == '1' ) {
			if( in_array(  'checkout_cart.php', $templates ) )
				return array();
		}

		return $templates;
	}

	public function force_single_variable_price() {
		global $edd_options;

		if( isset( $edd_options[ 'vps_force_single_variable_price' ] ) && $edd_options[ 'vps_force_single_variable_price' ] == '1' ) {
			$cart = edd_get_cart_contents();
			if( count( $cart ) > 1 ) {

				$temp_cart = array();
				foreach( $cart as $cart_item ) {
					$temp_cart[ $cart_item[ 'id' ] ] = $cart_item;
				}
				$cart = array_values( $temp_cart );

				// Use the one that's added last
				EDD()->session->set( 'edd_cart', $cart );
			}
		}
	}

	public function catch_post() {
		// If Variable pricing switch post is set, switch to post option of first (should be only) product.
		if( isset( $_POST[ 'edd-variable-pricing-switcher' ] ) ) {

			$cart = edd_get_cart_contents();

			foreach( $cart as $item_key => $cart_item ) {
				if( isset( $_POST[ 'edd-variable-pricing-switcher' ][ $cart_item[ 'id' ] ] ) ) {
					$cart[ $item_key ][ 'options' ][ 'price_id' ] = $_POST[ 'edd-variable-pricing-switcher' ][ $cart_item[ 'id' ] ];
				}
			}

			EDD()->session->set( 'edd_cart', $cart );
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

		$cart = edd_get_cart_contents();

		$pricing_switchers = '';
		foreach( $cart as $cart_item ) {

			// Check if the product has variable prices
			if( !edd_has_variable_prices( $cart_item[ 'id' ] ) ) {
				continue;
			}

			// Get pricing options
			$pricing_options = edd_get_variable_prices( $cart_item[ 'id' ] );

			// We need more than one pricing option
			if( count( $pricing_options ) < 2 ) {
				return;
			}

			$item_title = get_the_title( $cart_item[ 'id' ] );

			// Add select box
			$pricing_switchers .= "<select name='edd-variable-pricing-switcher[{$cart_item[ 'id' ]}]' class='edd-variable-pricing-switcher'>\n";
				foreach( $pricing_options as $pricing_id => $pricing_option ) {
					$pricing_switchers .= "<option value='{$pricing_id}'" . ( ( $pricing_id == $cart_item[ 'options' ][ 'price_id' ] ) ? " selected='selected'" : "" ) . ">{$item_title} | {$pricing_option[ 'name' ]} - " . edd_currency_filter( edd_format_amount( $pricing_option[ 'amount' ] ) ) . "</option>\n";
				}
			$pricing_switchers .= "</select>\n";

		}

		if( $pricing_switchers == '' ) {
			return;
		}

		// Get label
		$vps_label = ( ( isset( $edd_options[ 'vps_label' ] ) ) ? $edd_options[ 'vps_label' ] : 'License' );

	?>
	<form name="edd_variable_pricing_switcher" action="<?php echo edd_get_checkout_uri(); ?>" method="post">
		<fieldset id="edd_variable_pricing_switcher-fieldset">
			<legend><?php echo $vps_label; ?></legend>
			<?php echo $pricing_switchers; ?>
		</fieldset>
	</form>
	<?php

	}
}

add_action( 'plugins_loaded', function () {
	new EDD_Variable_Pricing_Switcher();
} );