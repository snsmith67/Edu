<?php

class LP_Pmpro_Order {

	// Change status all the LP Orders has status "Complete"
	public static $change_status_orders = true;

	public function __construct () {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'pmpro_checkout_before_change_membership_level', array(
			$this,
			'learn_press_pmpro_checkout_before_change_membership_level'
		), 10, 2 );
		add_action( 'pmpro_added_order', array( $this, 'learn_press_pmpro_added_order' ) );
		add_action( 'pmpro_updated_order', array( $this, 'learn_press_pmpro_updated_order' ) );
		add_action( 'learn_press_update_order_status', array( $this, 'learn_press_pmpro_update_order_status' ), 10, 2 );
		add_action( 'pmpro_cron_expire_memberships', array(
			$this,
			'learn_press_pmpro_cron_expire_memberships'
		), - 99999 );
		do_action( 'pmpro_cron_expire_memberships' );
		add_action( 'profile_update', array( $this, 'learn_press_pmpro_profile_update' ), 10, 2 );

		self::$change_status_orders = apply_filters( 'learn_press_is_change_status_orders', self::$change_status_orders );
	}
	
	public function init() {
		if ( !is_admin() ) {
			$user = learn_press_get_current_user();
			if($user){
				$this->auto_update_lp_orders();
			}
		}
	}

	public function learn_press_pmpro_checkout_before_change_membership_level ( $user_id, $morder ) {
		if ( ! empty( $morder ) ) {

			// Auto change status of all other orders to "cancelled"
			if ( self::$change_status_orders ) {
				$this->auto_change_status_orders();
			}
		}
	}

	public function learn_press_pmpro_added_order ( $pmpro_order ) {
		$user_id = $pmpro_order->user_id;
		if ( empty( $pmpro_order->user_id ) ) {
			$this->create_order( $pmpro_order, $user_id, true );
		} else {
			$this->create_order( $pmpro_order, $user_id );
		}


	}

	public function auto_change_status_orders ( $action = 'cancelled' ) {

		global $wpdb;

		do_action( 'learn_press_pmpro_before_change_status_orders' );

		$user_id               = learn_press_get_current_user_id();
		$pmpro_other_order_ids = $wpdb->get_col( "SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $user_id . "' AND status = 'success' ORDER BY id DESC" );

		if ( ! empty( $pmpro_other_order_ids ) ) {
			foreach ( $pmpro_other_order_ids as $pmpro_order_id ) {

				$lp_order_id = get_option( 'learn_press_pmpro_order_' . $pmpro_order_id, true );


				if ( $action == 'cancelled' ) {

					if ( ! empty( $lp_order_id ) ) {
						learn_press_update_order_status( $lp_order_id, 'cancelled' );
					}
				} else if ( $action == 'update_level' ) {
					$this->learn_press_pmpro_change_user_membership_level( $lp_order_id );
				}

			}
		}

		do_action( 'learn_press_pmpro_after_change_status_orders' );
	}

	public function learn_press_pmpro_change_user_membership_level ( $lp_order_id ) {
		$user_id         = get_current_user_id();
		$user_membership = learn_press_get_membership_level_for_user( $user_id );
		$order           = new LP_Order_Post_Type( 'lp_order' );

		// Remove all items
		$order->delete_order_items( $lp_order_id );
		update_post_meta( $lp_order_id, '_order_subtotal', 0 );
		update_post_meta( $lp_order_id, '_order_total', 0 );

		// Add new items
		$pmpro_order_id = get_post_meta( $lp_order_id, '_pmpro_membership_order_id', true );
		$pmpro_order    = new MemberOrder( $pmpro_order_id );
		$this->learn_press_add_sub_order( $user_membership->ID, $lp_order_id );
		update_post_meta( $lp_order_id, '_order_subtotal', $pmpro_order->subtotal );
		update_post_meta( $lp_order_id, '_order_total', $pmpro_order->subtotal );
	}

	public function learn_press_pmpro_updated_order ( $pmpro_order ) {
		$lp_order_id     = get_option( 'learn_press_pmpro_order_' . $pmpro_order->id );
		$lp_order_status = $this->learn_press_status_from_paid_membership_to_learn_press( $pmpro_order->status );

		$update_from = get_post_meta( $lp_order_id, 'learn_presslast_update_status_from', true );
		$update_from = absint( $update_from );

		if ( $update_from == 2 ) {
			update_post_meta( $lp_order_id, 'learn_presslast_update_status_from', 0 );

			return;
		} else {
			update_post_meta( $lp_order_id, 'learn_presslast_update_status_from', ++ $update_from );
		}

		if ( ! empty( $lp_order_id ) ) {

			learn_press_update_order_status( $lp_order_id, $lp_order_status );

			// Update user id if this order isn't setup UserID
			$is_create_new_user = get_option( 'learn_press_pmpro_create_new_user_' . $lp_order_id, true );

			if ( ! empty( $is_create_new_user ) ) {

				$user_id = get_current_user_id();

				update_post_meta( $lp_order_id, '_user_id', $user_id > 0 ? $user_id : 0 );
				delete_option( 'learn_press_pmpro_create_new_user_' . $lp_order_id );
			}
		}

	}

	private function create_order ( $pmpro_order, $_user_id, $create_new_user = false ) {

		$method = apply_filters( 'learn_press_pmpro_order_method', array(
			'id'   => 'membership',
			'name' => __( 'Membership', 'learnpress-paid-membership-pro' ) . ' (<a href="admin.php?page=pmpro-orders&order=' . $pmpro_order->id . '"><strong>' . $pmpro_order->code . '</strong></a>)'
		), $pmpro_order, $_user_id );

		$pmpro_order_status = $pmpro_order->status;
		if( 'free' === $pmpro_order->gateway && ''===$pmpro_order->status && $pmpro_order->subtotal == 0 ){
			$pmpro_order_status = 'success';
		}
		$status = $this->learn_press_status_from_paid_membership_to_learn_press( $pmpro_order_status );
		$lp_order_data = array(
			'status'      => apply_filters( 'learn_press_default_order_status', $status ),
			'user_id'     => $_user_id,
			'user_note'   => '',
			'created_via' => 'membership'
		);
// 		LP()->set_object( 'cart', LP_Cart::instance() );
		$lp_order = learn_press_create_order( $lp_order_data );

		update_option( 'learn_press_pmpro_order_' . $pmpro_order->id, $lp_order->id );
		if ( $create_new_user ) {
			update_option( 'learn_press_pmpro_create_new_user_' . $lp_order->id, true );
		}
		update_post_meta( $lp_order->id, '_payment_method', $method['id'] ); // any string but should be the same with 'created_via'
		update_post_meta( $lp_order->id, '_payment_method_title', $method['name'] ); // any string
		update_post_meta( $lp_order->id, '_order_subtotal', $pmpro_order->subtotal );
		update_post_meta( $lp_order->id, '_order_total', $pmpro_order->subtotal );
		update_post_meta( $lp_order->id, '_pmpro_membership_order_id', $pmpro_order->id );

		// Add Sub Order
		$this->learn_press_add_sub_order( $pmpro_order->membership_id, $lp_order->id );

		// Auto enroll for all course in this order
		if ( $lp_order->get_status() == 'completed' ) {
			learn_press_auto_enroll_user_to_courses( $lp_order->id );
		}

	}

	/**
	 * @param $membership_id
	 * @param $lp_order_id
	 */
	public function learn_press_add_sub_order ( $membership_id, $lp_order_id ) {

		$courses_query = lp_pmpro_query_course_by_level( $membership_id );
		$user_id       = $user_id = get_current_user_id();

		foreach ( $courses_query->posts as $post ) {
		    $course = learn_press_get_course( $post->ID );
			$item   = array(
				'course_id' => $course->get_id(),
				'name'      => $course->get_title(),
				'quantity'  => 1,
				'subtotal'  => $course->get_price(),
				'total'     => $course->get_price()
			);

			// Check if buy renew this course
			$this->learn_press_renew_course( $user_id, $post->ID );

			// Add item
			$item_id    = learn_press_add_order_item( $lp_order_id, array(
			    'item_id' => $post->ID, 
				'order_item_name' => $item['name']
			) );
			$item['id'] = $item_id;
			$item['item_id'] = $item_id;

			// Add item meta
			if ( $item_id ) {
				learn_press_add_order_item_meta( $item_id, '_course_id', $item['course_id'] );
				learn_press_add_order_item_meta( $item_id, '_quantity', $item['quantity'] );
				learn_press_add_order_item_meta( $item_id, '_subtotal', $item['subtotal'] );
				learn_press_add_order_item_meta( $item_id, '_total', $item['total'] );
			}
		}
	}

	public function learn_press_status_from_paid_membership_to_learn_press ( $pmpro_status ) {

		switch ( $pmpro_status ) {

			case 'pending':
			case 'error':
			case 'token':
				$lp_order_status = 'pending';
				break;

			case 'review':
				$lp_order_status = 'processing';
				break;

			case 'success':
				$lp_order_status = 'completed';
				break;

			case 'cancelled':
			case 'refunded':
				$lp_order_status = 'cancelled';
				break;

			default:
				$lp_order_status = 'pending';
				break;
		}

		return $lp_order_status;

	}

	public function learn_press_status_from_learn_press_to_paid_membership ( $lp_order_status ) {

		switch ( $lp_order_status ) {

			case 'pending':
				$pmpro_status = 'pending';
				break;

			case 'processing':
				$pmpro_status = 'review';
				break;

			case 'completed':
				$pmpro_status = 'success';
				break;

			case 'cancelled':
				$pmpro_status = 'cancelled';
				break;

			default:
				$pmpro_status = 'pending';
				break;
		}

		return $pmpro_status;
	}

	public function learn_press_pmpro_update_order_status ( $new_status, $lp_order_id ) {

		$update_from = get_post_meta( $lp_order_id, 'learn_presslast_update_status_from', true );
		$update_from = absint( $update_from );

		if ( $update_from == 2 ) {
			update_post_meta( $lp_order_id, 'learn_presslast_update_status_from', 0 );

			return;
		} else {
			update_post_meta( $lp_order_id, 'learn_presslast_update_status_from', ++ $update_from );
		}

		$pmpro_order_id = get_post_meta( $lp_order_id, '_pmpro_membership_order_id', true );

		if ( ! empty( $pmpro_order_id ) ) {
			$pmpro_order     = new MemberOrder( $pmpro_order_id );
			$pmpro_order->id = $pmpro_order_id;

			// Sync status between Learnpress Status Order & Paid Memberships Pro Status Order
			$pmpro_order->status = $this->learn_press_status_from_learn_press_to_paid_membership( $new_status );
			$pmpro_order->saveOrder();
		}
		update_post_meta( $lp_order_id, 'learn_presslast_update_status_from', 0 );
	}

	/**
	 * Lock courses when membership expire
	 */
	public function learn_press_pmpro_cron_expire_memberships () {

		global $wpdb;

		//make sure we only run once a day
		$today = date_i18n( "Y-m-d", current_time( "timestamp" ) );

		//look for memberships that expired before today
		$sqlQuery = "SELECT mu.id, mu.user_id, mu.membership_id, mu.startdate, mu.enddate FROM " . $wpdb->pmpro_memberships_users . " mu WHERE mu.status = 'active' AND mu.enddate IS NOT NULL AND mu.enddate <> '' AND mu.enddate <> '0000-00-00 00:00:00' AND DATE(mu.enddate) <= '" . $today . "' ORDER BY mu.enddate";
		if ( defined( 'PMPRO_CRON_LIMIT' ) ) {
			$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;
		}

		$expired = $wpdb->get_results( $sqlQuery );
		foreach ( $expired as $e ) {

			do_action( 'learn_press_pmpro_membership_pre_membership_expiry', $e->user_id, $e->membership_id );
			$other_order_ids = $wpdb->get_col( "SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $e->user_id . "' AND status = 'success' AND membership_id = '" . $e->membership_id . "' ORDER BY id DESC" );

			if ( ! empty( $other_order_ids ) ) {
				foreach ( $other_order_ids as $order_id ) {
					$lp_order_id = get_option( 'learn_press_pmpro_order_' . $order_id, true );
					$order       = new LP_Order( $lp_order_id );

					// Get list courses in this order
					$items = $order->get_items();
					if ( ! empty( $items ) ) {
						foreach ( $items as $item ) {
							$this->learn_press_lock_course_after_expired( $item['id'], $item['course_id'] );
						}
					}
					$order->update_status( 'cancelled' );
				}
			}

			do_action( 'learn_press_pmpro_membership_post_membership_expiry', $e->user_id, $e->membership_id );
		}
	}

	/**
	 * Lock course after the membership is expired
	 *
	 * @param string $order_id
	 * @param string $course_id
	 *
	 * @return bool
	 */
	public function learn_press_lock_course_after_expired ( $order_id = '', $course_id = '' ) {

		if ( empty( $order_id ) || empty( $course_id ) ) {
			return false;
		}

		// MINH CHINH CAN HOP VA XU LY TIEP

		// Save duration for this course
		$user_id      = get_current_user_id();
		$user         = learn_press_get_user( $user_id );
		$course       = learn_press_get_course( $course_id );
		$user_item_id = learn_press_get_user_item_id( $user_id, $course->id );
		$duration     = $course->get_duration();
		$course_info  = $user->get_course_info( $course->id );
		if ( $course_info ) {
			$now        = current_time( 'timestamp' );
			$start_time = intval( strtotime( $course_info['start'] ) );
			if ( $start_time + $duration > $now ) {
				$remain = $start_time + $duration - $now;

				if ( $remain > 0 ) {
					learn_press_update_user_item_meta( $user_item_id, 'lp_pmpro_remain_course_duration', $remain );
				}
			}

			// Get all quiz is started
			$curriculum = $course->get_curriculum();

			if ( ! empty( $curriculum ) ) {
				foreach ( $curriculum as $section ) {
					if ( ! empty( $section->items ) ) {
						foreach ( $section->items as $item ) {

							// Process if this item is quiz
							if ( $item->item_type === 'lp_quiz' ) {
								$user_quiz_id   = learn_press_get_user_item_id( $user_id, $item->item_id );
								$user_item_quiz = learn_press_get_user_item( array(
									'user_id' => $user_id,
									'item_id' => $item->item_id
								), true );

								// Check if exist item
								if ( ! empty( $user_item_quiz ) && $user_item_quiz->status === 'started' ) {
									$quiz        = LP_Quiz::get_quiz( $item->item_id );
									$start_time  = intval( strtotime( $user_item_quiz->start_time ) );
									$duration    = $quiz->duration;
									$remain_quiz = $start_time + $duration - $now;

									if ( $remain_quiz > 0 ) {
										learn_press_update_user_item_meta( $user_quiz_id, 'lp_pmpro_remain_quiz_duration', $remain_quiz );
									}
								}
							}
						}


					}
				}
			}
		}


		return true;
	}

	public function learn_press_renew_course ( $user_id, $course_id ) {

		if ( empty( $course_id ) ) {
			return;
		}
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$course       = learn_press_get_course( $course_id );
		$user_item_id = learn_press_get_user_item_id( $user_id, $course_id );
		$duration     = $course->get_duration();
		$now          = current_time( 'timestamp' );

		// Restore duration for this course
		$remain_course_duration = learn_press_get_user_item_meta( $user_item_id, 'lp_pmpro_remain_course_duration', true );
		if ( ! empty( $remain_course_duration ) ) {
			$remain_course_duration = abs( $remain_course_duration );
			if ( $remain_course_duration > $duration ) {
				$remain_course_duration = $duration;
			}
			$start_time = $now - ( $duration - $remain_course_duration );
			$start_time = date( "Y-m-d H:i:s", $start_time );
			learn_press_update_user_item_field( array(
				'start_time' => $start_time
			), array(
				'user_item_id' => $user_item_id,
				'user_id'      => $user_id,
			    'item_id'      => $course_id,
			) );
			delete_metadata( 'learnpress_user_item', $user_item_id, 'lp_pmpro_remain_course_duration' );

			// Restore duration for lesson and quiz
			// Get all quiz is started
			$curriculum = $course->get_curriculum();

			if ( ! empty( $curriculum ) ) {
				foreach ( $curriculum as $section ) {
					if ( ! empty( $section->items ) ) {
						foreach ( $section->items as $item ) {

							// Process if this item is quiz
							if ( $item->item_type === 'lp_quiz' ) {
								$user_quiz_id         = learn_press_get_user_item_id( $user_id, $item->item_id );
								$remain_quiz_duration = learn_press_get_user_item_meta( $user_quiz_id, 'lp_pmpro_remain_quiz_duration', true );
								$quiz                 = LP_Quiz::get_quiz( $item->item_id );
								$quiz_duration        = $quiz->duration;

								if ( ! empty( $remain_quiz_duration ) ) {
									$remain_quiz_duration = abs( $remain_quiz_duration );
									if ( $remain_quiz_duration > $quiz_duration ) {
										$remain_quiz_duration = $quiz_duration;
									}
									$quiz_start_time = $now - ( $quiz_duration - $remain_quiz_duration );
									$quiz_start_time = date( "Y-m-d H:i:s", $quiz_start_time );
									learn_press_update_user_item_field( array(
										'start_time' => $quiz_start_time,
										'status'     => 'started'
									), array(
										'user_item_id' => $user_quiz_id,
										'user_id'      => $user_id,
										'item_id'      => $item->item_id,
									) );
									delete_metadata( 'learnpress_user_item', $user_quiz_id, 'lp_pmpro_remain_quiz_duration' );
								}
							}
						}


					}
				}
			}
		}


	}

	/**
	 * @param $user_id
	 * @param $old_user_data
	 */
	public function learn_press_pmpro_profile_update ( $user_id, $old_user_data ) {

		if ( isset( $_POST['membership_level'] ) && ! empty( $_POST['membership_level'] ) ) {

			$user_membership = learn_press_get_membership_level_for_user( $user_id );

			if ( $user_membership->ID !== $_POST['membership_level'] ) {
				$this->auto_change_status_orders( 'update_level' );
			}
		}
	}

	
	public function auto_update_lp_orders( ) {
		$user = learn_press_get_current_user();
		if( !$user || !$user->get_id() ) {
			return;
		}
		$user_level = learn_press_get_membership_level_for_user( $user->get_id() );
		$user_order = $this->get_user_order($user->get_id());
		if( !$user_level ) {
			if( $user_order ) {
				$this->lp_order_update_by_level($user_order->ID);
			}
			return;
		}
		$level_courses = $this->get_level_course( $user_level->id );
		if( empty($level_courses) ) {
			return;
		}
		$level_course_ids = array_keys( $level_courses );
		if ( !$user_order || empty( $user_order ) ) {
			# cretate order
			$order_id = $this->create_new_lp_order( $user_level->id, $level_course_ids );
			if($order_id){
				learn_press_auto_enroll_user_to_courses( $order_id );
				wp_redirect(learn_press_get_current_url());
			}
		} else {
			# update order
			$row = $user_order;
			$updated = $this->lp_order_update_by_level( $row->ID, $user_level->id );
			if( $updated ){
				$this->lp_order_update_memberships_level( $row->ID, $user_level->id );// update level for order
				learn_press_auto_enroll_user_to_courses( $row->ID );
				wp_redirect(learn_press_get_current_url());
			}
		}
	}
	
	
	public function get_user_order( $user_id = null ) {
		global $wpdb;
		if ( !$user_id ) {
			$user_id = learn_press_get_current_user_id();
		}
		$sql = "SELECT 
					p.ID, pm.meta_value as user_id, pm2.meta_value as `level`,  pmpro_mo.membership_id ,pm3.meta_value as `pmpro_order_id`
				FROM
					{$wpdb->prefix}posts AS p
						INNER JOIN
					{$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id AND pm.meta_key = '_user_id'
						LEFT JOIN
					{$wpdb->prefix}postmeta AS pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_lp_pmpro_level'
						LEFT JOIN
					{$wpdb->prefix}postmeta AS pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_pmpro_membership_order_id'
						LEFT JOIN
					{$wpdb->prefix}pmpro_membership_orders AS pmpro_mo ON pm.meta_value = pmpro_mo.user_id AND pm3.meta_value=pmpro_mo.id
				WHERE
					post_type = 'lp_order'
					AND pm.meta_value = %d
 					AND (
						(pm2.meta_value IS NOT NULL)
							OR 
						(pm3.meta_value IS NOT NULL)
							OR 
						( pmpro_mo.membership_id IS NOT NULL)
					)
					";
		$query = $wpdb->prepare( $sql, $user_id );
		$row = $wpdb->get_row( $query );
		return $row;
	}
	
	/**
	 * Get Courses in Level
	 */
	public function get_level_course( $level_id ){
		return lp_pmpro_get_course_by_level_id( $level_id );
	}

	/**
	 * Get course_ids in an lp_order
	 * @param type $order_id
	 */
	public function get_lp_order_courses( $order_id ) {
		$order = learn_press_get_order( $order_id );
		$course_ids = array();
		if(!$order || !method_exists($order, 'get_items') ) {
			return array();;
		}

		$items = $order->get_items();
		foreach ( $items as $item ) {
			if(!in_array( $item['course_id'], $course_ids )){
				$course_ids[]=intval($item['course_id']);
			}
		}

		return $course_ids;
	}

	/**
	 * Add course into lp_order
	 * @param type $order_id
	 * @param type $course_ids
	 */
	public function lp_order_add_courses( $order_id, $course_ids = array() ) {
		if ( empty( $course_ids ) ) {
			return;
		}
		$lp_order = learn_press_get_order($order_id);
		if( !$lp_order ) {
			return;
		}
		foreach ($course_ids as $course_id){
			$course = learn_press_get_course( $course_id );
			if(!$course) {
				continue;
			}
			$item = array(
				'item_id'			=> $course->get_id(),
				'order_item_name'	=> $course->get_title(),
			    'course_id'			=> $course->get_id(),
				'name'				=> $course->get_title(),
				'quantity'			=> 1,
				'subtotal'			=> $course->get_price(),
				'total'				=> $course->get_price(),
				'data'				=> array()
			);
			$lp_order->add_item($item);
			$this->user_enroll_course($course_id, $order_id);
		}
	}

	/**
	 * Remove course from lp_order
	 * @param type $order_id
	 * @param type $course_ids
	 */
	public function lp_order_rem_courses($order_id, $course_ids = array() ) {
		if(empty($course_ids)){
			return;
		}
		global $wpdb;
		# get order item ids
		$course_ids_placeholder = implode( ', ', array_fill( 0, count( $course_ids ), '%d' ) );
		$prepare_values           = array_merge( array( $order_id ), $course_ids );
		$sql	= $wpdb->prepare( "
				SELECT 
					i.order_item_id
				FROM
					{$wpdb->learnpress_order_items} AS i
						INNER JOIN
					{$wpdb->learnpress_order_itemmeta} AS im 
							ON i.order_item_id = im.learnpress_order_item_id
								AND im.meta_key = '_course_id'
				WHERE 
					i.order_id=%d
					and meta_value IN({$course_ids_placeholder})",
				$prepare_values
			);
		$item_ids = $wpdb->get_col( $sql );
		
		# remove 
		if( empty($item_ids) ) {
			return;
		}
		$item_ids_placeholder = implode( ', ', array_fill( 0, count( $item_ids ), '%d' ) );
		$prepare_values           = array_merge( array( $order_id ), $item_ids );
		$wpdb->query(
			$wpdb->prepare( "
				DELETE FROM itemmeta
					USING {$wpdb->learnpress_order_itemmeta} itemmeta
					INNER JOIN {$wpdb->learnpress_order_items} items
				WHERE itemmeta.learnpress_order_item_id = items.order_item_id
					AND items.order_id = %d 
					AND itemmeta.learnpress_order_item_id IN({$item_ids_placeholder})",
				$prepare_values
			)
		);
		$wpdb->query(
			$wpdb->prepare( "
				DELETE FROM {$wpdb->learnpress_order_items}
				WHERE order_id = %d		
					AND order_item_id IN({$item_ids_placeholder})",
				$prepare_values
			)
		);
		$wpdb->query( $wpdb->prepare( "ALTER TABLE {$wpdb->learnpress_order_itemmeta} AUTO_INCREMENT = %d", 1 ) );
		$wpdb->query( $wpdb->prepare( "ALTER TABLE {$wpdb->learnpress_order_items} AUTO_INCREMENT = %d", 1 ) );
	}
	
	public function create_new_lp_order( $level_id, $course_ids = array() ) {
		# cretate order
		$user_id = learn_press_get_current_user_id();
		$order_data = array(
			'post_author' => $user_id,
			'post_parent' => '0',
			'post_type' => LP_ORDER_CPT,
			'post_status' => 'lp-completed',
			'ping_status' => 'closed',
			'post_title' => __( 'Order on', 'learnpress-pmpro' ) . ' ' . current_time( "l jS F Y h:i:s A" ),
			'meta_input' => array(
				'_user_id' => $user_id,
				'_created_via' => 'membership_auto',
				'_payment_method' => 'memberships_level',
				'_payment_method_title' => __( 'Memberships Level', 'learnpress-pmpro' ),
				'_lp_pmpro_level' => $level_id,
			)
		);
		$order_id = wp_insert_post( $order_data );
		if ( empty( $course_ids ) ) {
			return $order_id;
		}
		$this->lp_order_add_courses($order_id, $course_ids);
		return $order_id;
	}

	public function lp_order_update_memberships_level( $lp_order_id, $level_id ) {
		$lp_order = learn_press_get_order( $lp_order_id );
		if( $lp_order && isset($lp_order->id) && $lp_order->id ) {
			update_post_meta($lp_order_id, '_lp_pmpro_level', $level_id);
		}
	}
	
	/**
	 * Update total price for order
	 * @param type $lp_order_id
	 */
	public function lp_order_update_total_price( $lp_order_id ) {
		$lp_order = learn_press_get_order( $lp_order_id );
		if( !$lp_order ) {
			return;
		}
		$user_id = $lp_order->get_user('id');
		$membership_values = learn_press_get_membership_level_for_user( $user_id );
		//we tweak the initial payment here so the text here effectively shows the recurring amount
		if ( !empty( $membership_values ) ) {
			$membership_values->original_initial_payment = $membership_values->initial_payment;
			$membership_values->initial_payment = $membership_values->billing_amount;
		}
		$order_price = 0;
		if ( empty( $membership_values ) || pmpro_isLevelFree( $membership_values ) ) {
			if ( !empty( $membership_values->original_initial_payment ) && $membership_values->original_initial_payment > 0 ) {
				$order_price = $membership_values->original_initial_payment;
				//echo "Paid " . pmpro_formatPrice($membership_values->original_initial_payment) . ".";
			}
		} else {
			$order_price = pmpro_getLevelCost( $membership_values, true, true );
		}
		update_post_meta( $lp_order_id, '_order_subtotal', floatval( $order_price ) );
		update_post_meta( $lp_order_id, '_order_total', floatval( $order_price ) );
	}

	public function lp_order_update_by_level( $lp_order_id, $level_id = null ) {
		if ( !$level_id ) {
			$level_id = get_post_meta( $lp_order_id, '_lp_pmpro_level', true );
		}
		$level_courses = $this->get_level_course( $level_id );
		$level_course_ids = array_keys( $level_courses );
		$order_courses = $this->get_lp_order_courses( $lp_order_id );
		$course_add = array_diff( $level_course_ids, $order_courses ); //var_dump($course_add);// get course need to remove from order
		$course_rem = array_diff( $order_courses, $level_course_ids ); //var_dump($course_rem);// get course need to add in to order
		if( empty($course_add) && empty($course_rem) ) {
			return false;
		}
		$change = false;
		
		if ( !empty( $course_add ) ) {
			if($this->lp_order_add_courses( $lp_order_id, $course_add )){
				$change = true;
			}
		}
		if ( !empty( $course_rem ) ) {
			if($this->lp_order_rem_courses( $lp_order_id, $course_rem ) && !$change){
				$change = true;
			}
		}
		if($change){
			$this->lp_order_update_total_price( $lp_order_id );
		}
		
		return $change;
	}


	public function user_enroll_course( $course_id, $order_id, $user_id=null ) {
		global $wpdb;
		if( !$user_id ) {
			$user_id = learn_press_get_current_user_id();
		}
		# 4 enroll course
		$res_insert = $wpdb->insert(
					$wpdb->prefix . 'learnpress_user_items',
					array(
							'user_id'    => $user_id,
							'item_id'    => $course_id,
							'start_time' => current_time( 'mysql' ),
							'status'     => 'enrolled',
							'end_time'   => '0000-00-00 00:00:00',
							'ref_id'     => $order_id,
							'item_type'  => 'lp_course',
							'ref_type'   => 'lp_order'
					),
					array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
				);
		$inserted = 0;
		if ( $res_insert ) {
			$inserted = $wpdb->insert_id;
			do_action( 'learn_press_user_enrolled_course', $course_id, $user_id, $inserted );					
		} else {
			learn_press_debug( $wpdb );
			$user = learn_press_get_user($user_id);
			do_action( 'learn_press_user_enroll_course_failed', $user, $course_id, $inserted );
		}
		return $inserted;
	}
}

$x = new LP_Pmpro_Order;
//$x->learn_press_lock_course_after_expired( 889, 731 );
