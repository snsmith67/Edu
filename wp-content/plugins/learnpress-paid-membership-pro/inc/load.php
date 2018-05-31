<?php
/*
Plugin Name: LearnPress - Paid Membership Pro Integration
Plugin URI: http://thimpress.com/learnpress
Description: Paid Membership Pro add-on for LearnPress.
Author: ThimPress
Version: 3.0.0
Author URI: http://thimpress.com
Tags: learnpress, lms
Text Domain: learnpress-pmpro
Domain Path: /languages/

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class LP_Addon_Paid_Memberships_Pro
 */
class LP_Addon_Paid_Memberships_Pro extends LP_Addon {
	public $settings = array();
	
    /**
     * page id of the Membership Levels page
     * @var unknown
     */
	public $pmpro_levels_page_id = null;

	/**
	 * @var string
	 */
	public $version = LP_ADDON_PMPRO_VER;

	/**
	 * @var string
	 */
	public $require_version = LP_ADDON_PMPRO_REQUIRE_VER;

	/**
	 * @var
	 */
	public $pmpro_levels;

	/**
	 * @var
	 */
	protected $user;

	/**
	 * @var
	 */
	protected $user_level;

	/**
	 * @var bool
	 */
	private $_meta_box = false;

	/**
	 * LP_Addon_Paid_Memberships_Pro constructor.
	 */
	function __construct() {
		if ( ! $this->pmpro_is_active() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );

			return false;
		}

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		parent::__construct();
	}

	public function plugins_loaded() {
		include 'order.php';
		$this->pmpro_levels = pmpro_getAllLevels();
		$this->user         = learn_press_get_current_user();
		if ( $this->user ) {
			$this->user_level = learn_press_get_membership_level_for_user( $this->user->get_id() );
		}
		if ( is_admin() ) {
			$this->admin_require();
		}
	}

	protected function _define_contants() {
		define( 'LP_PMPRO_FILE', __FILE__ );
		define( 'LP_PMPRO_URI', plugins_url( '/', LP_PMPRO_FILE ) );
		define( 'LP_PMPRO_VER', $this->version );
		define( 'LP_PMPRO_REQUIRE_VER', $this->require_version );
	}

	public function admin_require() {
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'pmpro-admin' . DIRECTORY_SEPARATOR . 'pmpro-admin.php';
	}

	protected function _includes() {
		require_once LP_ADDON_PMPRO_PATH . '/inc/functions.php';
	}

	public function admin_notices() {
		?>
        <div class="notice notice-error">
            <p><?php
				echo wp_kses(
					sprintf(
						'<strong>Paid Membership Pro</strong> addon for <strong>LearnPress</strong> requires %s plugin is <strong>installed</strong> and <strong>activated</strong>.',
						sprintf( '<a href="%s" target="_blank">Paid Membership Pro</a>', admin_url( 'plugin-install.php?tab=search&type=term&s=paid memberships pro' ) )
					),
					array(
						'a'      => array(
							'href'   => array(),
							'target' => array(),
						),
						'strong' => array(),
					)
				);
				?></p>
        </div>
		<?php
	}

	function get_pmpro_levels_page_id(){
	    if( null === $this->pmpro_levels_page_id ) {
	        $this->pmpro_levels_page_id = pmpro_getOption( "levels_page_id" );
	    }
	    return $this->pmpro_levels_page_id;
	}

	function pmpro_can_enroll( $course_id ) {
		$course_levels = get_post_meta( $course_id, '_lp_pmpro_levels', false );
		$has_access    = $this->checkUserHasLevel( $course_levels );

		return $has_access;
	}

	/**
	 * @param array $levels array level_id
	 *
	 * @return bool
	 */
	private function checkUserHasLevel( $levels ) {
		$levels = (array) $levels;

		if ( ! $this->user_level ) {
			return false;
		}

		foreach ( $levels as $l ) {
			if ( $l == $this->user_level->ID ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Init hooks
	 */
	protected function _init_hooks() {
	    # Add the Memberships tab in LearnPress Settings page
		add_filter( 'learn-press/admin/settings-tabs-array', array( $this, 'admin_settings' ) );
		
		# Add the Memberships tab to Course settings in edit Course page
		add_filter( 'learn-press/admin-course-tabs', array( $this, 'admin_course_tabs' ) );
		
		# build externaml link buy course
		add_filter( 'learn-press/course-external-link', array( $this, 'external_link_buy_course' ), 10, 2 );

		# Add the "Buy Memberships" button before the "Buy this Course" button
		add_action( 'learn-press/before-enroll-form', array( $this, 'add_buy_membership_button'),10  );
		add_action( 'learn-press/after-enroll-form', array( $this, 'hide_purchase_course_button'),10  );

		add_action( 'learn-press/before-purchase-form', array( $this, 'add_buy_membership_button'),10  );
		add_action( 'learn-press/after-purchase-form', array( $this, 'hide_purchase_course_button'),10  );
		
		
		# add scripts to singler course page
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_script' ) );
		
		# add scripts to admin  
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        
		
		
		/* Custom Templates */
		add_filter( 'pmpro_pages_custom_template_path', array(
			$this,
			'learn_press_pmpro_pages_custom_template_path'
		), 10, 5 );
		add_action( 'pmpro_checkout_after_pricing_fields', array(
			$this,
			'learn_press_pmpro_checkout_after_pricing_fields'
		) );
		add_filter( 'pmpro_email_data', array( $this, 'learn_press_pmpro_email_data' ), 10, 2 );

		add_filter( 'learn-press/purchase-course-button-text', array(
			$this,
			'learn_press_pmpro_purchase_button_text'
		), 10 );
		add_shortcode( 'lp_pmpro_courses', array( $this, 'learn_press_page_levels_short_code' ) );

		add_action( 'pmpro_before_change_membership_level', array(
			$this,
			'learn_press_pmpro_after_change_membership_level'
		), 10, 4 );

// 		add_action( 'load-post.php', array( $this, 'memberships_meta_box' ), 20 );
// 		add_action( 'load-post-new.php', array( $this, 'memberships_meta_box' ), 20 );

		
	}

    /**
     * callback function for hook 'learn-press/admin/settings-tabs-array'.
     * it add new tab in LearnPress Settings page. 
     * @param unknown $tabs
     * @return unknown
     */
	public function admin_settings( $tabs ) {
		$tabs['membership'] = include_once LP_ADDON_PMPRO_PATH . '/inc/classes/setting-membership.php';
		return $tabs;
	}

	/**
	 * add tab Paid Memberships Pro in to edit course page
	 * @return mixed
	 */
	public function admin_course_tabs( $tabs ) {
		$this->_meta_box              = new RW_Meta_Box( $this->meta_box() );
		$tabs['paid-memberships-pro'] = $this->_meta_box;

		return $tabs;
	}

	/**
	 * build content of settings tab for Paid Memberships Pro in edit course page
	 * @return mixed
	 */
	function meta_box() {
		$prefix         = '_lp_pmpro_';
		$options_levels = array();
		foreach ( $this->pmpro_levels as $pmpro_level ) {
			$options_levels[ $pmpro_level->id ] = $pmpro_level->name;
		}

		$meta_box = array(
			'id'     => 'course_pmpro',
			'title'  => __( 'Course Memberships', 'learnpress-pmpro' ),
			'icon'   => 'dashicons-groups',
			'pages'  => array( LP_COURSE_CPT ),
			'fields' => array(
				array(
					'name'        => __( 'Select Membership Levels', 'learnpress-pmpro' ),
					'id'          => "{$prefix}levels",
					'type'        => 'select_advanced',
					'options'     => $options_levels,
					'multiple'    => true,
					'placeholder' => __( 'Select membership levels', 'learnpress-pmpro' ),
				),
			)
		);

		return apply_filters( 'learn_press_pmpro_meta_box_args', $meta_box );
	}
	
	/**
	 * add the "Buy Membership" button in to single course page
	 */
	public function add_buy_membership_button(){
	    global $post;
	    # get course levels
	    $course_id     = learn_press_get_course_id();
	    if(!$course_id){
	        $course_id = $post->ID;
	    }
	    $course_levels = get_post_meta($course_id, '_lp_pmpro_levels');

	    # get user levels
	    $user_id       = get_current_user_id();

	    if( !empty( $course_levels ) && !pmpro_hasMembershipLevel( $course_levels ) ) {
	        global $current_user;
	        $course     = learn_press_get_course($course_id);
	        $pmpro_levels_page_id = $this->get_pmpro_levels_page_id();
	        $redirect   = add_query_arg( 'course_id', $course_id, get_the_permalink( $pmpro_levels_page_id ) );
	        $redirect   = apply_filters( 'learn_press_pmpro_redirect_levels_page', $redirect, $course, $pmpro_levels_page_id, $current_user );
	        $buy_through_membership      = LP()->settings->get( 'buy_through_membership' );

	        $buy_through_membership_text = LP()->settings->get( 'button_buy_membership' );
	        if ( empty( $buy_through_membership_text ) ) {
	            $buy_through_membership_text = __( 'Buy Membership', 'learnpress-paid-membership-pro' );
	        }
	        $buy_through_membership_text = apply_filters( 'learn_press_buy_through_membership_text', $buy_through_membership_text, $redirect, $course, $pmpro_levels_page_id, $current_user );
	        $args = array(
	           'buy_through_membership_text' => $buy_through_membership_text,
	           'redirect' => $redirect,
	           'course' => $course,
	           'levels_page_id' => $pmpro_levels_page_id,
	           'current_user' => $current_user,
	        );
	        learn_press_get_template( 'button-buy-membership.php', $args, learn_press_template_path() . '/addons/paid-membership-pro/', LP_ADDON_PMPRO_TEMP );
	        if( 'yes' === $buy_through_membership ) {
	            ob_start();
	        }
	    }

	}

	public function hide_purchase_course_button(){
	    # only run this function if function add_buy_membership_button is run
	    if(!has_action( 'learn-press/before-purchase-form', array( $this, 'add_buy_membership_button') ) ){
	        return;
	    }
	    # get course levels
	    global $post;
	    # get course levels
	    $course_id     = learn_press_get_course_id();
	    if(!$course_id){
	        $course_id = $post->ID;
	    }
	    $course_levels = get_post_meta($course_id, '_lp_pmpro_levels');
	    # get user levels
	    $user_id           = get_current_user_id();
	    if( !empty($course_levels) && !pmpro_hasMembershipLevel( $course_levels ) ) {
	        $buy_through_membership      = LP()->settings->get('buy_through_membership');
	        if( 'yes' === $buy_through_membership ) {
	            ob_get_clean();
	        }
	    }
	}

	public function wp_enqueue_script() {
		wp_enqueue_style( 'learn-press-pmpro-style', LP_ADDON_PMPRO_URL . 'assets/style.css', array(), $this->version );
	}

	public function admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen ) {
			wp_enqueue_script( 'learn-press-pmpro-script', LP_ADDON_PMPRO_URL . 'assets/admin-script.js', array(), $this->version, true );
		}
	}

	public function learn_press_pmpro_pages_custom_template_path( $default_templates, $page_name, $type, $where, $ext ) {
		$template            = learn_press_pmpro_locate_template( "{$type}/{$page_name}.{$ext}" );
		$default_templates[] = $template;

		return $default_templates;
	}

	public function learn_press_pmpro_email_data( $data, $email ) {

		$path_email = LP_ADDON_PMPRO_PATH . '/templates/email/';
		$path_email = apply_filters( 'learn_press_pmpro_email_custom_template_path', $path_email, $data, $email );

		if ( ! empty( $email->body ) && ! empty( $email->template ) && file_exists( $path_email . $email->template . ".html" ) ) {
			$email->body = file_get_contents( $path_email . $email->template . ".html" );
		}

		return $data;
	}

	public function learn_press_pmpro_checkout_after_pricing_fields() {
		$content = pmpro_loadTemplate( 'checkout-custom-pricing', 'local', 'pages' );
		echo $content;
	}

	/**
	 * @todo: remove this function in next version
	 * @param unknown $course_id
	 */
	public function learn_press_before_course_buttons( $course_id ) {
		return;
	}

	public function external_link_buy_course( $external_link_buy_course, $course = null ) {
	    $buy_through_membership = LP()->settings->get( 'buy_through_membership' );
		$is_required            = learn_press_pmpro_check_require_template();
		if ( ! empty( $buy_through_membership ) && $buy_through_membership === 'yes' && $is_required ) {
			return '';
		}
		return $external_link_buy_course;
	}

	public function learn_press_pmpro_purchase_button_text( $purchase_text ) {
		$is_required            = learn_press_pmpro_check_require_template();
		$buy_through_membership = LP()->settings->get( 'buy_through_membership' );
		$new_text               = LP()->settings->get( 'button_buy_course' );
		if ( ! empty( $buy_through_membership ) && $buy_through_membership == 'no' && ! empty( $new_text ) && $is_required ) {
			return $new_text;
		}
		return $purchase_text;
	}

	public function learn_press_page_levels_short_code() {
		echo do_shortcode( '[pmpro_levels]' );
	}

	public function learn_press_pmpro_after_change_membership_level( $level_id, $user_id, $old_levels, $cancel_level ) {

	}

	/**
	 * Return TRUE if Paid Membership PRO plugin is installed and active
	 *
	 * @return bool
	 */
	static function pmpro_is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return is_plugin_active( 'paid-memberships-pro/paid-memberships-pro.php' );
	}

	/**
	 * Return TRUE if Paid Membership PRO plugin is installed and active
	 *
	 * @return bool
	 */
	static function learnpress_is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return is_plugin_active( 'learnpress/learnpress.php' ) || is_plugin_active( 'LearnPress/learnpress.php' );
	}
}
