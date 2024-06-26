<?php
if (!defined('ABSPATH')) {
	exit;
}

class WooCommerce_Customer_Email_Verification_Admin
{

	public $my_account_id;

	/**
	 * Initialize the main plugin function
	 */
	public function __construct()
	{
		$this->my_account_id = get_option('woocommerce_myaccount_page_id');
	}

	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;

	/**
	 * Get the class instance
	 *
	 * @return woo_customer_email_verification_Admin
	 */
	public static function get_instance()
	{

		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/*
	* Init from parent mail class
	*/
	public function init()
	{
		add_action('wp_ajax_evfwr_settings_form_update', array($this, 'evfwr_settings_form_update_fun'));
		add_filter('manage_users_columns', array($this, 'add_column_users_list'), 10, 1);
		add_filter('manage_users_custom_column', array($this, 'add_details_in_custom_users_list'), 10, 3);
		add_action('show_user_profile', array($this, 'show_evfwr_fields_in_single_user'));
		add_action('edit_user_profile', array($this, 'show_evfwr_fields_in_single_user'));
		add_action('admin_head', array($this, 'evfwr_manual_verify_user'));

		/*** Sort and Filter Users ***/
		add_action('restrict_manage_users', array($this, 'filter_user_by_verified'));
		add_filter('pre_get_users', array($this, 'filter_users_by_user_by_verified_section'));

		/*** Bulk actions for Users ***/
		add_filter('bulk_actions-users', array($this, 'add_custom_bulk_actions_for_user'));
		add_filter('handle_bulk_actions-users', array($this, 'users_bulk_action_handler'), 10, 3);
		add_action('admin_notices', array($this, 'user_bulk_action_notices'));

		$evfwr_settings_nonce = wp_create_nonce('evfwr_settings_update');
    		$settings_page_url = add_query_arg(array('page' => 'email-verification-for-wc-registration', 'nonce' => $evfwr_settings_nonce));
		
		if (isset($_GET['page']) && 'email-verification-for-wc-registration' == $_GET['page'] && wp_verify_nonce($_GET['nonce'], 'evfwr_settings_update')) {
			// Hook for add admin body class in settings page
			add_filter('admin_body_class', array($this, 'evfwr_post_admin_body_class'), 100);
		}

		add_action('wp_ajax_evfwr_manualy_user_verify_in_user_menu', array($this, 'evfwr_manualy_user_verify_in_user_menu'));
	}
	/*
	* Admin Menu add function
	* WC sub menu
	*/
	public function register_woocommerce_menu()
	{
		add_submenu_page('woocommerce', 'Customer Verification', 'Email Verification', 'manage_woocommerce', 'email-verification-for-wc-registration', array($this, 'wc_customer_email_verification_page_callback'));
	}

	/*
	* Add class in body tag
	*/
	public function evfwr_post_admin_body_class($body_class)
	{
		$body_class .= ' email-verification-for-wc-registration';
		return $body_class;
	}

	/**
	 * Load admin styles.
	 */
	public function admin_styles($hook)
	{
    		// Verify the nonce
    		if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'evfwr_nonce_creation')) {
        		return;
    	}

    		if (!isset($_GET['page'])) {
        		return;
    	}

    		if ('email-verification-for-wc-registration' != $_GET['page']) {
        		return;
    	}

    		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

    		wp_register_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.full' . $suffix . '.js', array('jquery'), '4.0.3', true);
    		wp_enqueue_script('select2');
    		wp_enqueue_style('wp-color-picker');
    		wp_enqueue_style('customer_email_verification_styles', woo_customer_email_verification()->plugin_dir_url() . 'assets/css/admin.css', array(), woo_customer_email_verification()->version);

    		wp_enqueue_script('customer_email_verification_script', woo_customer_email_verification()->plugin_dir_url() . 'assets/js/admin.js', array('jquery', 'wp-util'), woo_customer_email_verification()->version, true);

    		wp_localize_script('customer_email_verification_script', 'customer_email_verification_script', array());

    		wp_register_script('selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js', array('jquery'), '1.0.4', true);
    		wp_register_script('wc-enhanced-select', WC()->plugin_url() . '/assets/js/admin/wc-enhanced-select' . $suffix . '.js', array('jquery', 'selectWoo'), WC_VERSION, true);
    		wp_register_script('jquery-blockui', WC()->plugin_url() . '/assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array('jquery'), '2.70', true);

    		wp_enqueue_script('selectWoo');
    		wp_enqueue_script('wc-enhanced-select');

    		wp_register_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION);
    		wp_enqueue_style('woocommerce_admin_styles');

    		wp_register_script('jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array('jquery'), WC_VERSION, true);

    		wp_enqueue_script('jquery-tiptip');
    		wp_enqueue_script('jquery-blockui');
    		wp_enqueue_script('wp-color-picker');
    		wp_enqueue_script('jquery-ui-sortable');
    		wp_enqueue_script('media-upload');
    		wp_enqueue_script('thickbox');
    		wp_enqueue_style('thickbox');
	}

// You need to generate a nonce when creating the URL with the 'page' parameter.
$admin_url = add_query_arg(
    array(
        'page' => 'email-verification-for-wc-registration',
        '_wpnonce' => wp_create_nonce('evfwr_nonce_creation')
    ),
    admin_url('admin.php')
);

	/*
	* Callback for Customer Email Verification page
	*/
	public function wc_customer_email_verification_page_callback()
	{
		wp_enqueue_script('customer_email_verification_table_rows');
?>
		<div class="redvilla-layout-evfwr__header">
			<h1 class="page_heading">
				<a href="javascript:void(0)"><?php esc_html('Customer Email Verification', 'customer-email-verification'); ?></a> <span class="dashicons dashicons-arrow-right-alt2"></span> <span class="breadcums_page_heading"><?php esc_html('Settings', 'customer-email-verification'); ?></span>
			</h1>
		</div>
		<div class="woocommerce evfwr_admin_layout">
			<div class="evfwr_admin_content">
				<?php include 'views/activity_panel.php'; ?>
				<div class="evfwr_nav_div">
					<?php $this->get_html_menu_tab($this->get_evfwr_tab_settings_data()); ?>
					<div class="menu_devider"></div>
					<?php
					require_once('views/admin_options_settings.php');

					?>
				</div>
			</div>
		</div>
		<?php
	}

	public function get_html_menu_tab($arrays)
	{
  	// Check for a valid nonce with specific action name 
  	if (!wp_verify_nonce($_GET['_wpnonce'], 'evfwr_menu_tab_nonce')) {
    		wp_die('Invalid nonce'); // Die if nonce verification fails
  	}

  	$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'email-verification';
  		foreach ((array) $arrays as $id => $array) {
    			if (isset($array['type']) && 'link' == $array['type']) {
  				?>
      				<a class="menu_evfwr_link" href="<?php echo esc_url($array['link']); ?>"><?php esc_html($array['title']); ?></a>
    				<?php
    				} else {
    				?>
      				<input class="evfwr_tab_input" id="<?php echo esc_html($id); ?>" name="<?php echo esc_html($array['name']); ?>" type="radio" data-tab="<?php echo esc_html($array['data-tab']); ?>" data-label="<?php echo esc_html($array['data-label']); ?>" <?php echo ($tab == $array['data-tab'] ? 'checked' : ''); ?> />
      				<label class="<?php echo esc_html($array['class']); ?>" for="<?php echo esc_html($id); ?>"><?php echo esc_html($array['title']); ?></label>
    				<?php
    			}
  		}
	}

	public function get_evfwr_tab_settings_data()
	{

		$evfwr_customizer_settings = new evfwr_initialise_customizer_settings();

		$setting_data = array(
			'setting_tab' => array(
				'title'		=> __('Settings', 'email-verification-for-wc-registration'),
				'show'      => true,
				'class'     => 'evfwr_tab_label first_label',
				'data-tab'  => 'email-verification',
				'data-label' => __('Settings', 'email-verification-for-wc-registration'),
				'name'  => 'tabs',
			),

			'customize' => array(
				'title'		=> __('Customize', 'customer-email-verification-pro'),
				'type'		=> 'link',
				'link'		=> $evfwr_customizer_settings->get_customizer_url('evfwr_main_controls_section', 'settings'),
				'show'      => true,
				'class'     => 'tab_label',
				'data-tab'  => 'trackship',
				'data-label' => __('Customize', 'customer-email-verification-pro'),
				'name'  => 'tabs',
			),
		);

		return $setting_data;
	}

	/*
	* get html get_html of fields
	*/
	public function get_html($arrays)
	{

		$checked = '';
		?>
		<ul class="settings_ul">
			<?php
			foreach ((array) $arrays as $id => $array) {
				if ($array['show']) {
					$class = isset($array['class']) ? $array['class'] : '';
			?>
					<li class="<?php esc_html($class); ?>">
						<?php
						if ('desc' != $array['type'] && 'checkbox' != $array['type'] && 'checkbox_select' != $array['type']) {
						?>
							<label class="settings_label">
								<?php
								esc_html($array['title']);
								if (isset($array['tooltip'])) {
								?>
									<span class="woocommerce-help-tip tipTip" title="<?php esc_html($array['tooltip']); ?>"></span>
								<?php } ?>
							</label>
						<?php
						}
						if (isset($array['type']) && 'dropdown' == $array['type']) {
							$multiple = isset($array['multiple']) ? 'multiple' : '';
							$field_id = isset($array['multiple']) ? $array['multiple'] : $id;
						?>
							<fieldset>
								<select class="select select2" id="<?php esc_html($field_id); ?>" name="<?php esc_html($id); ?>" <?php esc_html($multiple); ?>>
									<?php
									foreach ((array) $array['options'] as $key => $val) {
										$selected = (get_option($id, $array['Default']) == (string) $key) ? 'selected' : '';
									?>
										<option value="<?php esc_html($key); ?>" <?php esc_html($selected); ?>><?php esc_html($val); ?></option>
									<?php
									}
									?>
								</select>
							</fieldset>
						<?php
						} elseif (isset($array['type']) && 'multiple_select' == $array['type']) {
						?>
							<div class="multiple_select_container">
								<select multiple class="wc-enhanced-select" name="<?php esc_html($id); ?>[]" id="<?php esc_html($id); ?>">
									<?php
									foreach ((array) $array['options'] as $key => $val) :
										$multi_checkbox_data = get_option($id);
										$checked = isset($multi_checkbox_data[$key]) && 1 == $multi_checkbox_data[$key] ? 'selected' : '';
									?>
										<option value="<?php esc_html($key); ?>" <?php esc_html($checked); ?>>
											<?php esc_html($val); ?>
										</option>
									<?php
									endforeach;
									?>
								</select>
							</div>
						<?php
						} elseif ('checkbox' == $array['type']) {
							$checked = (get_option($id, $array['Default'])) ? 'checked' : '';
						?>
							<label class="" for="<?php esc_html($id); ?>">
								<input type="hidden" name="<?php esc_html($id); ?>" value="0" />
								<input type="checkbox" id="<?php esc_html($id); ?>" name="<?php esc_html($id); ?>" class="" <?php esc_html($checked); ?> value="1" />
								<span class="label">
									<?php
									esc_html($array['title']);
									if (isset($array['tooltip'])) {
									?>
										<span class="woocommerce-help-tip tipTip" title="<?php esc_html($array['tooltip']); ?>"></span>
									<?php } ?>
								</span>
							</label>
						<?php
						} elseif ('checkbox_select' == $array['type']) {
							$checked = (get_option($id, $array['Default'])) ? 'checked' : '';
						?>
							<label class="" for="<?php esc_html($id); ?>">
								<input type="hidden" name="<?php esc_html($id); ?>" value="0" />
								<input type="checkbox" id="<?php esc_html($id); ?>" name="<?php esc_html($id); ?>" class="" <?php esc_html($checked); ?> value="1" />
								<span class="label">
									<?php
									$sanitized_url = esc_url($array['link']);
									/* translators: %s is the URL to be used for email verification */
									esc_html(sprintf(__('Click here to verify your email: %s', 'email-verification-for-wc-registration'), $sanitized_url));

									if (!empty($array['select'])) {
									?>
										<select name="<?php esc_html($array['select']['id']); ?>" style="width: auto;">
											<?php
											foreach ($array['select']['options'] as $key => $val) {
												$selected = (get_option($array['select']['id'], '') == $key) ? 'selected' : '';
											?>
												<option value="<?php esc_html($key); ?>" <?php esc_html($selected); ?>><?php esc_html($val); ?></option>
											<?php
											}
											?>
										</select>
									<?php
									}
									if (isset($array['tooltip'])) {
									?>
										<span class="woocommerce-help-tip tipTip" title="<?php esc_html($array['tooltip']); ?>"></span>
									<?php } ?>
								</span>
							</label>
							<?php
						} elseif ('multiple_checkbox' == $array['type']) {
							$op = 1;
							foreach ((array) $array['options'] as $key => $val) {

								$multi_checkbox_data = get_option($id);
								if (isset($multi_checkbox_data[$key]) && 1 == $multi_checkbox_data[$key]) {
									$checked = 'checked';
								} else {
									$checked = '';
								}
							?>

								<span class="multiple_checkbox">
									<label class="" for="<?php esc_html($key); ?>">
										<input type="hidden" name="<?php esc_html($id); ?>[<?php esc_html($key); ?>]" value="0" />
										<input type="checkbox" id="<?php esc_html($key); ?>" name="<?php esc_html($id); ?>[<?php esc_html($key); ?>]" class="" <?php esc_html($checked); ?> value="1" />
										<span class="multiple_label"><?php esc_html($val); ?></span>
										</br>
									</label>
								</span>
							<?php
							}
						} elseif ('textarea' == $array['type']) {
							$placeholder = (!empty($array['placeholder'])) ? $array['placeholder'] : '';
							?>

							<fieldset>
								<textarea placeholder="<?php esc_html($placeholder); ?>" class="input-text regular-input" name="<?php esc_html($id); ?>" id="<?php esc_html($id); ?>"><?php esc_html(get_option($id, $array['Default'])); ?></textarea>
							</fieldset>
							<span class="" style="font-size: 12px;"><?php esc_html($array['desc_tip']); ?></span>
						<?php
						} elseif ('tag_block' == $array['type']) {
						?>
							<fieldset class="tag_block">
								<code>{customer_email_verification_code}</code><code>{evfwr_user_verification_link}</code><code>{evfwr_resend_email_link}</code><code>{evfwr_display_name}</code><code>{evfwr_user_login}</code><code>{evfwr_user_email}</code>
							</fieldset>
						<?php
						} elseif ('desc' == $array['type']) {
						?>
							<p class="section_desc"><?php esc_html($array['title']); ?></p>
						<?php
						} else {
							$placeholder = (!empty($array['placeholder'])) ? $array['placeholder'] : '';
						?>
							<fieldset>
								<input class="input-text regular-input " type="text" name="<?php esc_html($id); ?>" id="<?php esc_html($id); ?>" style="" value="<?php esc_html(get_option($id, $array['Default'])); ?>" placeholder="<?php esc_html($placeholder); ?>">
							</fieldset>
						<?php } ?>
					</li>
			<?php
				}
			}
			?>
		</ul>
	<?php
	}

	/*
	* Get settings tab array data
	* return array
	*/
	public function get_evfwr_settings_data()
	{

		$page_list = wp_list_pluck(get_pages(), 'post_title', 'ID');

		$form_data = array(
			'evfwr_enter_account_after_registration' => array(
				'type'		=> 'checkbox',
				'show' => true,
				'tooltip' 		=> __('Allow your customers to access their account for the first time after registration before they verify the email address', 'customer-emial-verification-for-woocommerce'),
				'title' => __('Allow first login after registration without email verification', 'customer-emial-verification-for-woocommerce'),
				'Default'   => '',
				'class'     => '',
			),
			'evfwr_email_for_verification' => array(
				'type'		=> 'checkbox',
				'show' => true,
				'tooltip' 		=> __('if you select this option, the verification message, code and link will be added in New Account Emails. The separate email verification will be sent only when the customer (or admin) resend verification', 'customer-emial-verification-for-woocommerce'),
				'title' => __('Verification code in new account email', 'customer-emial-verification-for-woocommerce'),
				'Default'   => '',
				'class'     => '',
			),
			'evfwr_redirect_page_after_varification' => array(
				'type'		=> 'dropdown',
				'title'		=> __('Page to redirect after successful verification', 'email-verification-for-wc-registration'),
				'class'		=> 'redirect_page border_class',
				'show' => true,
				'tooltip'	=> __('select a page to redirect users after successful verification. In case the email verification was during checkout, the user will be directed to checkout', 'email-verification-for-wc-registration'),
				'Default'   => get_option('woocommerce_myaccount_page_id'),
				'options'   => $page_list,
			),
		);
		$form_data = apply_filters('evfwr_general_settings_options', $form_data);
		return $form_data;
	}

	/*
	* Get settings tab array data
	* return array
	*/
	public function get_evfwr_settings_data_new()
	{

		global $wp_roles;
		$all_roles = $wp_roles->roles;
		$all_roles_array = array();

		foreach ($all_roles as $key => $role) {
			if ('administrator' != $key) {
				$role = array($key => $role['name']);
				$all_roles_array = array_merge($all_roles_array, $role);
			}
		}

		$form_data_2 = array(
			'evfwr_verification_success_message' => array(
				'type'		=> 'textarea',
				'title'		=> __('Email verification success message', 'email-verification-for-wc-registration'),
				'show'		=> true,
				'tooltip'	=> __('the message that will appear on the top of the my-account or checkout page after successful email verification', 'email-verification-for-wc-registration'),
				'Default'   => __('Your email is verified!', 'email-verification-for-wc-registration'),
				'id'        => '',
				'placeholder' => __('Your email is verified!', 'email-verification-for-wc-registration'),
				'desc_tip'      => '',
				'class'     => 'top',
			),
			'evfwr_skip_verification_for_selected_roles' => array(
				'type'		=> 'multiple_select',
				'title'		=> __('Skip email verification for the selected user roles:', 'email-verification-for-wc-registration'),
				'options'   => $all_roles_array,
				'show' => true,
				'Default'   => '',
				'class'     => 'top',
			),
		);

		return $form_data_2;
	}

	public function evfwr_settings_form_update_fun()
	{
		if (!empty($_POST) && check_admin_referer('evfwr_settings_form_nonce', 'evfwr_settings_form_nonce')) {

			$data = $this->get_evfwr_settings_data();
			$data_2 = $this->get_evfwr_settings_data_new();

			if (isset($_POST['evfwr_enable_email_verification'])) {
				update_option('evfwr_enable_email_verification', wc_clean($_POST['evfwr_enable_email_verification']));
			}

			foreach ($data as $key => $val) {
				if (isset($_POST[$key])) {
					update_option($key, wc_clean($_POST[$key]));
				}

				if (isset($val['type']) && 'inline_checkbox' == $val['type']) {
					foreach ((array) $val['checkbox_array'] as $key1 => $val1) {
						if (isset($_POST[$key1])) {
							update_option($key1, wc_clean($_POST[$key1]));
						}
					}
				}
			}

			foreach ($data_2 as $key => $val) {

				if (isset($_POST[$key])) {
					update_option($key, wc_clean($_POST[$key]));
				}

				if (isset($val['type']) && 'multiple_select' == $val['type']) {

					if (isset($_POST[$key])) {
						$roles = array();
						foreach ($val['options'] as $op_status => $op_data) {
							$roles[$key][$op_status] = 0;
						}

						foreach (wc_clean($_POST[$key]) as $key1 => $val) {
							$roles[$key][$val] = 1;
						}
						update_option($key, wc_clean($roles[$key]));
					} else {
						update_option($key, '');
					}
				}
			}
		}
	}

	/**
	 * This function adds custom columns in user listing screen in wp-admin area.
	 */
	public function add_column_users_list($column)
	{
		$column['evfwr_verified'] = __('Email verification', 'email-verification-for-wc-registration');
		$column['evfwr_action'] = __('Actions', 'email-verification-for-wc-registration');
		return $column;
	}

	/**
	 * This function adds custom values to custom columns in user listing screen in wp-admin area.
	 */
	public function add_details_in_custom_users_list($val, $column_name, $user_id)
	{

		wp_enqueue_script('jquery-blockui');

		wp_enqueue_style('customer_email_verification_user_admin_styles', woo_customer_email_verification()->plugin_dir_url() . 'assets/css/user-admin.css', array(), woo_customer_email_verification()->version);

		wp_enqueue_script('customer_email_verification_user_admin_script', woo_customer_email_verification()->plugin_dir_url() . 'assets/js/user-admin.js', array('jquery', 'wp-util'), woo_customer_email_verification()->version, true);

		$user_role = get_userdata($user_id);
		$verified  = get_user_meta($user_id, 'customer_email_verified', true);

		if ('evfwr_verified' === $column_name) {
			if (!woo_customer_email_verification()->is_admin_user($user_id)) {
				if (!woo_customer_email_verification()->is_verification_skip_for_user($user_id)) {

					$verified_btn_css   = ('true' == $verified) ? 'display:none' : '';
					$unverified_btn_css = ('true' != $verified) ? 'display:none' : '';

					$html = '<span style="' . $unverified_btn_css . '" class="dashicons dashicons-yes evfwr_5 evfwr_verified_admin_user_action" title="Verified"></span>';
					$html .= '<span style="' . $verified_btn_css . '" class="dashicons dashicons-no no-border evfwr_unverified_admin_user_action evfwr_5" title="Unverify"></span>';
					return $html;
				}
			}
			return '-';
		}
		if ('evfwr_action' === $column_name) {
			if (!woo_customer_email_verification()->is_admin_user($user_id)) {
				if (!woo_customer_email_verification()->is_verification_skip_for_user($user_id)) {

					$verify_btn_css   = ('true' == $verified) ? 'display:none' : '';
					$unverify_btn_css = ('true' != $verified) ? 'display:none' : '';

					$html = '<span style="' . $unverify_btn_css . '" class="dashicons dashicons-no evfwr_dashicons_icon_unverify_user" id="' . $user_id . '" wp_nonce="' . wp_create_nonce('wc_evfwr_email') . ' "></span>';
					$html .= '<span style="' . $verify_btn_css . '" class="dashicons dashicons-yes small-yes evfwr_dashicons_icon_verify_user evfwr_10" id="' . $user_id . '" wp_nonce="' . wp_create_nonce('wc_evfwr_email') . ' "></span>';
					$html .= '<span style="' . $verify_btn_css . '" class="dashicons dashicons-image-rotate evfwr_dashicons_icon_resend_email" id="' . $user_id . '" wp_nonce="' . wp_create_nonce('wc_evfwr_email') . ' "></span></span>';
					return $html;
				}
			}
		}
		return $val;
	}

	public function evfwr_manualy_user_verify_in_user_menu()
	{

		if (isset($_POST['wp_nonce']) && wp_verify_nonce(wc_clean($_POST['wp_nonce']), 'wc_evfwr_email')) {

			$user_id = isset($_POST['id']) ? wc_clean($_POST['id']) : '';
			$action_type = isset($_POST['actin_type']) ? wc_clean($_POST['actin_type']) : '';

			if ('unverify_user' == $action_type) {
				delete_user_meta($user_id, 'customer_email_verified');
			}

			if ('verify_user' == $action_type) {
				update_user_meta($user_id, 'customer_email_verified', 'true');
			}

			if ('resend_email' == $action_type) {
				$current_user           = get_user_by('id', $user_id);
				$is_secret_code_present = get_user_meta($user_id, 'customer_email_verification_code', true);

				if ('' === $is_secret_code_present) {
					$secret_code = md5($user_id . time());
					update_user_meta($user_id, 'customer_email_verification_code', $secret_code);
				}

				WC_customer_email_verification_email_Common()->wuev_user_id = $user_id; // WPCS: input var ok, CSRF ok.
				WC_customer_email_verification_email_Common()->wuev_myaccount_page_id = $this->my_account_id;

				WC_customer_email_verification_email_Common()->code_mail_sender($current_user->user_email);
			}
		}
		exit;
	}

	/**
	 * This function manually verifies a user from wp-admin area.
	 */
	public function evfwr_manual_verify_user()
	{

		if (isset($_GET['user_id']) && isset($_GET['wp_nonce']) && wp_verify_nonce(wc_clean($_GET['wp_nonce']), 'wc_evfwr_email')) {

			$user_id = wc_clean($_GET['user_id']);

			if (isset($_GET['wc_evfwr_confirm']) && 'true' === $_GET['wc_evfwr_confirm']) {

				update_user_meta($user_id, 'customer_email_verified', 'true');
				add_action('admin_notices', array($this, 'manual_evfwr_verify_email_success_admin'));
			} else {
				delete_user_meta($user_id, 'customer_email_verified');
				add_action('admin_notices', array($this, 'manual_evfwr_verify_email_unverify_admin'));
			}
		}

		if (isset($user_id) && isset($_GET['wp_nonce']) && wp_verify_nonce(wc_clean($_GET['wp_nonce']), 'wc_evfwr_email_confirmation')) {
			$current_user           = get_user_by('id', $user_id);
			$is_secret_code_present = get_user_meta($user_id, 'customer_email_verification_code', true);

			if ('' === $is_secret_code_present) {
				$secret_code = md5($user_id . time());
				update_user_meta($user_id, 'customer_email_verification_code', $secret_code);
			}

			WC_customer_email_verification_email_Common()->wuev_user_id = $user_id; // WPCS: input var ok, CSRF ok.
			WC_customer_email_verification_email_Common()->wuev_myaccount_page_id = $this->my_account_id;

			WC_customer_email_verification_email_Common()->code_mail_sender($current_user->user_email);
			add_action('admin_notices', array($this, 'manual_confirmation_email_success_admin'));
		}
	}

	public function manual_confirmation_email_success_admin()
	{
		$text = __('Verification email successfully sent.', 'email-verification-for-wc-registration');
	?>
		<div class="updated notice">
			<p><?php echo esc_html($text); ?></p>
		</div>
	<?php
	}

	public function manual_evfwr_verify_email_success_admin()
	{
		$text = __('User verified successfully.', 'email-verification-for-wc-registration');
	?>
		<div class="updated notice">
			<p><?php echo esc_html($text); ?></p>
		</div>
	<?php
	}
	public function manual_evfwr_verify_email_unverify_admin()
	{
		$text = __('User unverified.', 'email-verification-for-wc-registration');
	?>
		<div class="updated notice">
			<p><?php echo esc_html($text); ?></p>
		</div>
	<?php
	}

	// define the woocommerce_login_form_end callback 
	public function action_woocommerce_login_form_end()
	{
	?>
		<p class="woocommerce-LostPassword lost_password">
			<a href="<?php echo esc_url(get_home_url()); ?>?p=reset-verification-email"><?php esc_html('Resend verification email', 'email-verification-for-wc-registration'); ?></a>
		</p>
	<?php
	}

	public function show_evfwr_fields_in_single_user($user)
	{

		wp_enqueue_style('customer_email_verification_user_admin_styles', woo_customer_email_verification()->plugin_dir_url() . 'assets/css/user-admin.css', array(), woo_customer_email_verification()->version);

		wp_enqueue_script('customer_email_verification_user_admin_script', woo_customer_email_verification()->plugin_dir_url() . 'assets/js/user-admin.js', array('jquery', 'wp-util'), woo_customer_email_verification()->version, true);

		$user_id = $user->ID;
		$verified  = get_user_meta($user_id, 'customer_email_verified', true);
		$user_role = get_userdata($user_id);
	?>

		<table class="form-table evfwr-admin-menu">
			<th colspan="2">
				<h4 class="evfwr_admin_user"><?php esc_html('Customer verification', 'email-verification-for-wc-registration'); ?></h4>
			</th>
			<tr>
				<th class="evfwr-admin-padding"><label for="year_of_birth"><?php esc_html('Email verification status:', 'email-verification-for-wc-registration'); ?></label></th>
				<td>
					<?php
					if (!woo_customer_email_verification()->is_admin_user($user_id)  && !woo_customer_email_verification()->is_verification_skip_for_user($user_id)) {

						$verified_btn_css   = ('true' == $verified) ? 'display:none' : '';
						$unverified_btn_css = ('true' != $verified) ? 'display:none' : '';

						$html = '<span style="' . $unverified_btn_css . '" class="dashicons dashicons-yes evfwr_5 evfwr_verified_admin_user_action_single" title="Verified"></span>';
						$html .= '<span style="' . $verified_btn_css . '" class="dashicons dashicons-no no-border evfwr_unverified_admin_user_action_single evfwr_5" title="Unverify"></span>';
						echo wp_kses_post($html);
					} else {
						echo 'Admin';
					}
					?>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<?php
					if (!woo_customer_email_verification()->is_admin_user($user_id) && !woo_customer_email_verification()->is_verification_skip_for_user($user_id)) {

						$verify_btn_css   = ('true' == $verified) ? 'display:none' : '';
						$unverify_btn_css = ('true' != $verified) ? 'display:none' : '';

						$text = '<span class="dashicons dashicons-yes evfwr-admin-dashicons" style="color:#ffffff; margin-right: 2px;"></span><span>' . __('Verify email manually', 'email-verification-for-wc-registration') . '</span>';

						echo '<a style="' . esc_html($verify_btn_css) . '" class="button-primary evfwr-admin-verify-button evfwr_dashicons_icon_verify_user" id="' . esc_html($user_id) . '" wp_nonce="' . esc_html(wp_create_nonce('wc_evfwr_email')) . ' "> ' . wp_kses_post($text) . '</a>';

						$text = '<span class="dashicons dashicons-image-rotate evfwr-admin-dashicons evfwr-rotate" ></span><span> ' . __('Resend verification email', 'email-verification-for-wc-registration') . '</span>';

						echo '<a style="' . esc_html($verify_btn_css) . '" class="button-primary evfwr-admin-resend-button evfwr_dashicons_icon_resend_email" id="' . esc_html($user_id) . '" wp_nonce="' . esc_html(wp_create_nonce('wc_evfwr_email')) . ' "> ' . wp_kses_post($text) . '</a>';

						$text = '<span class="dashicons dashicons-no evfwr-admin-dashicons"></span><span>' . __('Un-verify email', 'email-verification-for-wc-registration') . '</span>';

						echo '<a style="' . esc_html($unverify_btn_css) . '" class="button-primary evfwr-admin-unverify-button evfwr_dashicons_icon_unverify_user" id="' . esc_html($user_id) . '" wp_nonce="' . esc_html(wp_create_nonce('wc_evfwr_email')) . '">' . wp_kses_post($text) . '</a>';
					}
					?>
				</td>

			</tr>
		</table>

<?php
	}

	public function filter_user_by_verified($which)
	{
    	// Verify nonce
    	if (isset($_GET['customer_email_verified_nonce']) && !wp_verify_nonce($_GET['customer_email_verified_nonce'], 'customer_email_verified')) {
        	return;
    	}

    	$true_selected = '';
    	$false_selected = '';

    	// figure out which button was clicked. The $which in filter_by_job_role()
    	if (isset($_GET['customer_email_verified_top'])) {
        	$top = wc_clean($_GET['customer_email_verified_top']) ? wc_clean($_GET['customer_email_verified_top']) : null;
    	}

    	if (isset($_GET['customer_email_verified_bottom'])) {
        	$bottom = wc_clean($_GET['customer_email_verified_bottom']) ? wc_clean($_GET['customer_email_verified_bottom']) : null;
    	}

    	if (!empty($top) || !empty($bottom)) {
        	$section = !empty($top) ? $top : $bottom;

        if ('true' == $section) {
            $true_selected = 'selected';
        }

        if ('false' == $section) {
            $false_selected = 'selected';
        }
    	}

    	// template for filtering
    	$st = '<select name="customer_email_verified_%s" style="float:none;margin-left:10px;">
        <option value="">%s</option>%s</select>';

    	// generate options
    	$options = '<option value="true" ' . $true_selected . '>' . __('Verified', 'email-verification-for-wc-registration') . '</option>
        <option value="false" ' . $false_selected . '>' . __('Non verified', 'email-verification-for-wc-registration') . '</option>';

    	// combine template and options
    	$select = sprintf($st, $which, __('User verification', 'email-verification-for-wc-registration'), $options);

    		// output <select> and submit button with nonce field
    		echo '<form method="get">';
    		wp_nonce_field('customer_email_verified', 'customer_email_verified_nonce');
    		echo esc_html($select);
    		submit_button(__('Filter'), null, $which, false);
    		echo '</form>';
	}

	public function filter_users_by_user_by_verified_section($query) {
  	global $pagenow;

  	if (is_admin() && 'users.php' == $pagenow) {
    	// Check for nonce presence and validity
    	if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'filter_users_by_verified_section')) {

      	// figure out which button was clicked
      	if (isset($_GET['customer_email_verified_top'])) {
        	$top = wc_clean($_GET['customer_email_verified_top']) ? wc_clean($_GET['customer_email_verified_top']) : null;
      	}

      	if (isset($_GET['customer_email_verified_bottom'])) {
        	$bottom = wc_clean($_GET['customer_email_verified_bottom']) ? wc_clean($_GET['customer_email_verified_bottom']) : null;
      	}

      	if (!empty($top) || !empty($bottom)) {
        $section = !empty($top) ? $top : $bottom;

        if ('true' == $section) {
          	// change the meta query based on which option was chosen
          	$meta_query = array(
            	array(
              		'key' => 'customer_email_verified',
              		'value' => $section,
              		'compare' => 'LIKE'
            	)
          );
        	} else {
          	$meta_query = array(
            		'relation' => 'AND',
            	array(
              		'key' => 'evfwr_email_verification_pin',
              		'compare' => 'EXISTS'
            	),
            	array(
              		'key' => 'customer_email_verified',
              		'value' => $section,
              		'compare' => 'NOT EXISTS'
            		),
          	);
        }
        	$query->set('meta_query', $meta_query);
      			}
    			} else {
      			// Handle invalid nonce (optional: display error message)
      			wp_die('Invalid request.');
    			}
  		}
	}


	public function add_custom_bulk_actions_for_user($bulk_array)
	{

		$bulk_array['verify_users_email'] = __('Verify users email', 'email-verification-for-wc-registration');
		$bulk_array['send_verification_email'] = __('Send verification email', 'email-verification-for-wc-registration');
		return $bulk_array;
	}

	public function users_bulk_action_handler($redirect, $doaction, $object_ids)
	{

		$redirect = remove_query_arg(array('user_id', 'wc_evfwr_confirm', 'wp_nonce', 'wc_evfwr_confirmation', 'verify_users_emails', 'send_verification_emails'), $redirect);

		if ('verify_users_email' == $doaction) {

			foreach ($object_ids as $user_id) {
				update_user_meta($user_id, 'customer_email_verified', 'true');
			}

			$redirect = add_query_arg('verify_users_emails', count($object_ids), $redirect);
		}

		if ('send_verification_email' == $doaction) {
			foreach ($object_ids as $user_id) {

				$current_user = get_user_by('id', $user_id);
				$this->user_id                         = $current_user->ID;
				$this->email_id                        = $current_user->user_email;
				$this->user_login                      = $current_user->user_login;
				$this->user_email                      = $current_user->user_email;
				WC_customer_email_verification_email_Common()->wuev_user_id  = $current_user->ID;
				WC_customer_email_verification_email_Common()->wuev_myaccount_page_id = $this->my_account_id;
				$this->is_user_created                 = true;
				$is_secret_code_present                = get_user_meta($this->user_id, 'customer_email_verification_code', true);

				if ('' === $is_secret_code_present) {
					$secret_code = md5($this->user_id . time());
					update_user_meta($user_id, 'customer_email_verification_code', $secret_code);
				}

				$evfwr_email_for_verification = get_option('evfwr_email_for_verification', 0);
				$verified = get_user_meta($this->user_id, 'customer_email_verified', true);
				$evfwr_email_for_verification_mode = get_option('evfwr_email_for_verification_mode', 1);

				if (0 == $evfwr_email_for_verification && 'true' != $verified) {
					WC_customer_email_verification_email_Common()->code_mail_sender($current_user->user_email);
				}
			}
			$redirect = add_query_arg('send_verification_emails', count($object_ids), $redirect);
		}

		return $redirect;
	}

	public function user_bulk_action_notices() {

  	// Check for valid nonce
  		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'user_bulk_action_notices' ) ) {
    			return;  // Exit if nonce is invalid
  	}

  	if (!empty($_REQUEST['verify_users_emails'])) {
    		printf('<div id="message" class="updated notice is-dismissible"><p>' .
      			/* translators: %s: replace with email */
      			esc_html(_n(
        			'Verification Status updated for  %s user.',
        			'Verification Status updated for  %s users.',
        			intval($_REQUEST['verify_users_emails'])
      				)) . '</p></div>', intval($_REQUEST['verify_users_emails']));
  			}

  	if (!empty($_REQUEST['send_verification_emails'])) {
    		printf('<div id="message" class="updated notice is-dismissible"><p>' .
      			/* translators: %s: replace with email */
      			esc_html(_n(
        			'Verification email sent to %s user.',
        			'Verification email sent to %s users.',
        			intval($_REQUEST['send_verification_emails'])
      				)) . '</p></div>', intval($_REQUEST['send_verification_emails']));
  			}
		}
