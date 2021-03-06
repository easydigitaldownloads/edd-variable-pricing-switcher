<?php
/*
Plugin Name: Easy Digital Downloads - Variable Pricing Switcher
Plugin URI: http://www.barrykooij.com/edd-checkout-variable-pricing-switcher
Description: Easy Digital Downloads - Variable Pricing Switcher
Version: 1.0.5
Author: Easy Digital Downloads
Author URI: https://easydigitaldownloads.com
*/

if ( ! defined( 'EDD_VPS_PLUGIN_DIR' ) ) {
	define( 'EDD_VPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EDD_VPS_PLUGIN_FILE' ) ) {
	define( 'EDD_VPS_PLUGIN_FILE', __FILE__ );
}

require_once( EDD_VPS_PLUGIN_DIR . '/includes/metabox.php' );

class EDD_Variable_Pricing_Switcher {

	const PLUGIN_NAME         = 'Variable Pricing Switcher';
	const PLUGIN_VERSION_NAME = '1.0.5';
	const PLUGIN_VERSION_CODE = '2';
	const PLUGIN_AUTHOR       = 'Easy Digital Downloads';

	public function __construct() {
		// Load plugin textdomain
		load_plugin_textdomain( 'edd-vps', false, dirname( plugin_basename( EDD_VPS_PLUGIN_FILE ) ) . '/languages/' );

		// Instantiate the licensing / updater.
		$license = new EDD_License( __FILE__, self::PLUGIN_NAME, self::PLUGIN_VERSION_NAME, self::PLUGIN_AUTHOR );

		// Filters & Hooks
		add_filter( 'edd_settings_sections_extensions', array( $this, 'register_settings_section' ), 10 );
		add_filter( 'edd_settings_extensions', array( $this, 'settings' ), 1 );
		add_filter( 'edd_get_template_part', array( $this, 'filter_checkout_cart' ) );
		add_action( 'init', array( $this, 'catch_post' ), 11 );
		add_action( 'init', array( $this, 'force_single_variable_price' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'checkout_style' ) );
		add_action( 'edd_before_purchase_form', array( $this, 'checkout_addition' ), 10 );
	}

	public function register_settings_section( $sections ) {
		$sections['vps'] = __( 'Variable Pricing Switcher', 'edd-vps' );

		return $sections;
	}

	public function settings( $settings ) {
		$vps_settings = array(
			array(
				'id' 		=> 'vps_settings',
				'name' 	=> '<strong>' . __('Variable Pricing Switcher Settings', 'edd-vps') . '</strong>',
				'desc' 	=> '',
				'type' 	=> 'header'
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
				'id' 		=> 'vps_disable_cart',
				'name' 	=> __('Disable cart on checkout page', 'edd-vps'),
				'desc' 	=> __('Check this to disable the cart on the checkout page.', 'edd-vps'),
				'type' 	=> 'checkbox'
			),
			array(
				'id' 		=> 'vps_force_single_variable_price',
				'name' 	=> __('Force single variable price', 'edd-vps'),
				'desc' 	=> __('Check this to only allow 1 variable price per product to be bought at once.', 'edd-vps'),
				'type' 	=> 'checkbox'
			),
		);

		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$vps_settings = array( 'vps' => $vps_settings );
		}

  		return array_merge( $settings, $vps_settings );
	}

	public function filter_checkout_cart( $templates, $slug = "", $name = "" ) {

		if( edd_get_option( 'vps_disable_cart' ) ) {

			if( in_array(  'checkout_cart.php', $templates ) ) {
				return array();
			}

		}

		return $templates;
	}

	public function force_single_variable_price() {

		if( edd_get_option( 'vps_force_single_variable_price' ) ) {
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

				$cart_key = edd_get_item_position_in_cart( $cart_item['id'], $cart_item['options'] );
				edd_remove_from_cart( $cart_key );

				$options = array();
				$options['quantity'] = $cart_item['quantity'];
				$options['price_id'] = absint( $_POST[ 'edd-variable-pricing-switcher' ][ $cart_item[ 'id' ] ] );

				edd_add_to_cart( $cart_item['id'], $options );

			}

			wp_redirect( edd_get_checkout_uri() ); exit;

		}
	}

	public function enqueue_scripts() {
		global $post;

		if( ! is_object( $post ) || $post->ID != edd_get_option( 'purchase_page' ) ) {
			return;
		}

		wp_enqueue_script( 'edd-variable-pricing-switcher-js', plugins_url( '/js/edd-variable-pricing-switcher.js' , __FILE__ ) );
	}

	public function checkout_style() {
		global $post;

		if( ! is_object( $post ) || $post->ID != edd_get_option( 'purchase_page' ) ) {
			return;
		}

		echo "<style type='text/css'>
			.edd-variable-pricing-switcher{width:100%}
			#edd_variable_pricing_switcher_discounts span.edd_discount{display:block;width:100%}
		</style>\n";
	}

	public function checkout_addition() {
		global $user_ID, $post;

		$cart = edd_get_cart_contents();

		$pricing_switchers = '';
		foreach( $cart as $cart_item ) {

			// Check if variable pricing switcher is enabled for this download
			$enabled = get_post_meta( $cart_item[ 'id' ], '_edd_vps_enabled', true ) ? true : false;
			if( ! $enabled ) {
				continue;
			}

			// Check if the product has variable prices
			if( !edd_has_variable_prices( $cart_item[ 'id' ] ) ) {
				continue;
			}

			// Fix the price_id option if it doesn't exists
			if( ! isset( $cart_item[ 'options' ][ 'price_id' ] ) ) {
				$cart_item[ 'options' ][ 'price_id' ] = 0;
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
		$vps_label = edd_get_option( 'vps_label', 'License' );

	?>
	<form name="edd_variable_pricing_switcher" action="<?php echo edd_get_checkout_uri(); ?>" method="post">
		<fieldset id="edd_variable_pricing_switcher-fieldset">
			<span><legend><?php echo $vps_label; ?></legend></span>
			<?php echo $pricing_switchers; ?>
		</fieldset>
	</form>

	<?php
		// Only show discount fieldset if the normal cart is disabled
		if( edd_get_option( 'vps_disable_cart' ) ) {
	?>
		<fieldset id="edd_variable_pricing_switcher_discounts"<?php if( ! edd_cart_has_discounts() )  echo ' style="display:none;"'; ?>>
			<span><legend><?php _e( 'DISCOUNT', 'edd' ); ?></legend></span>
			<div>
			<?php
			if( edd_cart_has_discounts() ) {
				echo edd_get_cart_discounts_html();
			}
			?>
			</div>
		</fieldset>
	<?php
		}
	?>

	<?php
	}
}

function edd_vps_load() {

	if( ! function_exists( 'EDD' ) ) {
		return;
	}

	new EDD_Variable_Pricing_Switcher();
}
add_action( 'plugins_loaded', 'edd_vps_load' );
