<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class WooCommerce_evfwr_Customizer {	
	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;
	
	/**
	 * Initialize the main plugin function
	*/
	public function __construct() {
			
	}	
	
	/**
	 * Register the Customizer sections
	 */
	public function evfwr_add_customizer_sections( $wp_customize ) {	
		
		$wp_customize->add_section( 'evfwr_verification_widget_messages',
			array(
				'title' => __( 'Verification widget', 'customer-email-verification-for-woocommerce' ),
				'description' => '',
				'panel' => ''
			)
		);
		
		$wp_customize->add_section( 'evfwr_controls_section',
			array(
					'title' => __( 'Verification email', 'customer-email-verification-for-woocommerce' ),
					'description' => '',
					'panel' => ''
			)
		);
		
		if ( 1 == get_option('evfwr_email_for_verification') ) {
			$wp_customize->add_section( 'evfwr_new_account_email_section',
				array(
					'title' => __( 'Verification in new account email', 'customer-email-verification-for-woocommerce' ),
					'description' => '',
					'panel' => ''
				)
			);
		
		}
	}
	
	
	/**
	* Remove unrelated components
	*	
	* @param array $components
	* @param object $wp_customize
	* @return array
	*/
	public function remove_unrelated_components( $components, $wp_customize ) {
		// Iterate over components
		foreach ($components as $component_key => $component) {
	
			// Check if current component is own component
			if ( ! $this->is_own_component( $component ) ) {
				unset($components[$component_key]);
			}
		}
		// Return remaining components
		return $components;
	}
	
	/**
	* Remove unrelated sections
	*	
	* @param bool $active
	* @param object $section
	* @return bool
	*/
	public function remove_unrelated_sections( $active, $section ) {
		// Check if current section is own section
		if ( ! $this->is_own_section( $section->id ) ) {
			return false;
		}
	
		// We can override $active completely since this runs only on own Customizer requests
		return true;
	}
	
	/**
	* Check if current section is own section
	*	
	* @param string $key
	* @return bool
	*/
	public static function is_own_section( $key ) {		
		if ( 'evfwr_verification_widget_messages' === $key || 'evfwr_new_account_email_section' === $key || 'evfwr_controls_section' === $key ) {
			return true;
		}

		// Section not found
		return false;
	}
	
	/*
	 * Unhook Divi front end.
	 */
	public function unhook_divi() {
		// Divi Theme issue.
		remove_action( 'wp_footer', 'et_builder_get_modules_js_data' );
		remove_action( 'et_customizer_footer_preview', 'et_load_social_icons' );
	}
	
	/*
	 * Unhook flatsome front end.
	 */
	public function unhook_flatsome() {
		// Unhook flatsome issue.
		wp_dequeue_style( 'flatsome-customizer-preview' );
		wp_dequeue_script( 'flatsome-customizer-frontend-js' );
	}
	
	/**
	* Check if current component is own component
	*	
	* @param string $component
	* @return bool
	*/
	public static function is_own_component( $component ) {
		return false;
	}

	
	/**
	 * Add css and js for customizer
	*/
	public function enqueue_customizer_scripts() {
  		if ( isset( $_REQUEST['evfwr-customizer'] ) && '1' === $_REQUEST['evfwr-customizer'] && wp_verify_nonce( $_REQUEST['_wpnonce'], 'evfwr_customizer_nonce' ) ) {

    			// Enqueue scripts and styles as usual
    			wp_enqueue_style( 'wp-color-picker' );
    			wp_enqueue_style('evfwr-customizer-styles', woo_customer_email_verification()->plugin_dir_url() . 'assets/css/customizer-styles.css', array(), woo_customer_email_verification()->version );
    			wp_enqueue_script('evfwr-customizer-scripts', woo_customer_email_verification()->plugin_dir_url() . 'assets/js/customizer-scripts.js', array('jquery', 'customize-controls'), woo_customer_email_verification()->version, true);

    			$section = isset( $_REQUEST['section'] ) ? woocommerce_clean( $_REQUEST['section'] ) : '';

    			// Send variables to Javascript
    			wp_localize_script('evfwr-customizer-scripts', 'evfwr_customizer', array(
      				'ajax_url'       => admin_url('admin-ajax.php'),
      				'trigger_click'    => '#accordion-section-' . $section . ' h3',
      				'seperate_email_preview_url'  => $this->seperate_email_preview_url(),
      				'my_account_email_preview_url' => $this->my_account_email_preview_url(),
      				'verification_widget_preview_url'  => $this->verification_widget_preview_url(),
      				'verification_widget_message_preview_url'  => $this->verification_widget_message_preview_url(),
    			));

    			wp_localize_script('wp-color-picker', 'wpColorPickerL10n', array(
      				'clear'      => __( 'Clear' ),
      				'clearAriaLabel'  => __( 'Clear color' ),
      				'defaultString'  => __( 'Default' ),
      				'defaultAriaLabel' => __( 'Select default color' ),
      				'pick'       => __( 'Select Color' ),
      				'defaultLabel'   => __( 'Color value' ),
    			));
  		}
	}

	
	/**
	 * Get Customizer URL
	 *
	 */
	public function seperate_email_preview_url() {		
		$seperate_email_preview_url = add_query_arg( array(
			'evfwr-email-preview' => '1',
		), home_url( '' ) );		
		return $seperate_email_preview_url;
	}
	
	/**
	 * Get Customizer URL
	 *
	 */
	public function my_account_email_preview_url() {		
		$my_account_email_preview_url = add_query_arg( array(
			'evfwr-new-account-email-preview' => '1',
		), home_url( '' ) );		
		return $my_account_email_preview_url;
	}
	
	/**
	 * Get Customizer preview URL
	 *
	 */
	public function verification_widget_preview_url() {		
		$verification_widget_preview_url = add_query_arg( array(
			'action' => 'preview_evfwr_verification_lightbox',
		), home_url( '' ) );		
		return $verification_widget_preview_url;
	}
	
	/**
	 * Get Customizer URL
	 */
	public function verification_widget_message_preview_url() {
		$action = apply_filters( 'verification_widget_message_preview_action', 'preview_evfwr_verification_lightbox' );	
		$verification_widget_message_preview_url = add_query_arg( array( 'action' => $action, ), home_url( '' ) );		
		return $verification_widget_message_preview_url;		
	}
	
}
/**
 * Returns an instance of redvilla_woocommerce_evfwr.
 *
 * @since 1.6.5
 * @version 1.6.5
 *
 * @return customer-email-verification-for-woocommerce
*/
function woocommerce_evfwr_customizer() {
	static $instance;

	if ( ! isset( $instance ) ) {		
		$instance = new woocommerce_evfwr_customizer();
	}

	return $instance;
}

/**
 * Register this class globally.
 *
 * Backward compatibility.
*/
woocommerce_evfwr_customizer();
