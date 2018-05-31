<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class LP_Settings_PMPro_Membership extends LP_Abstract_Settings_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id   = 'membership';
		$this->text = __( 'Memberships', 'learnpress-pmpro' );

		parent::__construct();
	}

	public function output_section_general() {
		include LP_ADDON_PMPRO_PATH . '/inc/views/membership.php';
	}

	public function get_settings( $section = '', $tab = '' ) {
		return array(
			array(
				'title' => __( 'Paid Memberships Pro add-on for LearnPress', 'learnpress-paid-membership-pro' ),
				'type'  => 'title'
			),
			array(
				'title'   => __( 'Always buy the course through membership', 'learnpress-pmpro' ),
				'id'      => 'buy_through_membership',
				'default' => 'no',
				'type'    => 'yes-no',
			),
			array(
				'title'      => __( 'Button Buy Course', 'learnpress-pmpro' ),
				'id'         => 'button_buy_course',
				'default'    => 'Buy Now',
				'type'       => 'text',
				'visibility' => array(
					'state'       => 'show',
					'conditional' => array(
						array(
							'field'   => 'buy_through_membership',
							'compare' => '!=',
							'value'   => 'yes'
						)
					)
				)
			),
			array(
				'title'      => __( 'Button Buy Membership', 'learnpress-pmpro' ),
				'id'         => 'button_buy_membership',
				'default'    => 'Buy Membership',
				'type'       => 'text'
			)
		);
	}
}

return new LP_Settings_PMPro_Membership();