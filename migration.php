<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class T_LD_Mig {
		function __construct() {
		}

		function list_materials() {
			$courses = new WP_Query(
				array(
					'post_type'      => 'courses',
					'posts_per_page' => -1,
				)
			);

			if ( $courses && $courses->post_count ) {

				$courses = $courses->posts;
				foreach ( $courses as $course ) {
					$post_meta              = get_post_meta( $course->ID );
					$_tutor_course_settings = unserialize( $post_meta['_tutor_course_settings'][0] );
					$days                   = $_tutor_course_settings['enrollment_expiry'];
					$price_type             = 0;
					$billing_cycle          = 0;
					if ( $days == 0 ) {
						$price_type = 'paynow';
					} else {
						$price_type = 'subscribe';
					}
					if ( $days ) {
						$billing_cycle = $this->convertDaysToWeeksMonthsAndYearsRounded( $days );
					}

					$product_id    = $post_meta['_tutor_course_product_id'];
					$product       = get_post_meta( $product_id, '_price', true );
					$product_price = $product;// ->get_price();
					$_sfwd_courses = array(
						'0'                                => '',
						'sfwd-courses_certificate'         => '',
						'sfwd-courses_course_disable_content_table' => '',
						'sfwd-courses_course_disable_lesson_progression' => 'on',
						'sfwd-courses_course_lesson_order_enabled' => '',
						'sfwd-courses_course_lesson_order' => 'ASC',
						'sfwd-courses_course_lesson_orderby' => 'menu_order',
						'sfwd-courses_course_lesson_per_page_custom' => '',
						'sfwd-courses_course_lesson_per_page' => '',
						'sfwd-courses_course_materials_enabled' => '',
						'sfwd-courses_course_materials'    => '',
						'sfwd-courses_course_points_access' => '',
						'sfwd-courses_course_points_enabled' => '',
						'sfwd-courses_course_points'       => '',
						'sfwd-courses_course_prerequisite_compare' => 'ANY',
						'sfwd-courses_course_prerequisite_enabled' => '',
						'sfwd-courses_course_prerequisite' => '',
						'sfwd-courses_course_price_billing_p3' => '',
						'sfwd-courses_course_price_billing_t3' => '',
						'sfwd-courses_course_price_type_paynow_enrollment_url' => '',
						'sfwd-courses_course_price_type'   => $price_type,
						'sfwd-courses_course_price'        => $product_price,
						'sfwd-courses_course_topic_per_page_custom' => '',
						'sfwd-courses_course_trial_duration_p1' => '',
						'sfwd-courses_course_trial_duration_t1' => '',
						'sfwd-courses_course_trial_price'  => '',
						'sfwd-courses_custom_button_url'   => '',
						'sfwd-courses_exam_challenge'      => 0,
						'sfwd-courses_expire_access_days'  => 0,
						'sfwd-courses_expire_access_delete_progress' => '',
						'sfwd-courses_expire_access'       => '',
					);

					if ( $price_type == 'subscribe' ) {
						$_sfwd_courses['sfwd-courses_course_price_billing_cycle'] = '';
						$_sfwd_courses['sfwd-courses_course_price_billing_p3']    = $billing_cycle['count'];
						$_sfwd_courses['sfwd-courses_course_price_billing_t3']    = $billing_cycle['unit'];
						$_sfwd_courses['sfwd-courses_course_trial_duration_p1']   = 0;
					} else {
					}

					$post = array(
						'wp_post'           => array(
							'ID'                    => $course->ID,
							'post_author'           => $course->post_author,
							'post_date'             => $course->post_date,
							'post_content'          => $course->post_content,
							'post_title'            => $course->post_title,
							'post_excerpt'          => $course->post_excerpt,
							'post_excerpt'          => $course->post_excerpt,
							'post_status'           => 'publish',
							'comment_status'        => 'closed',
							'ping_status'           => 'closed',
							'post_password'         => '',
							'post_name'             => $course->post_name,
							'to_ping'               => '',
							'pinged'                => '',
							'post_modified'         => $course->post_modified,
							'post_modified_gmt'     => $course->post_modified_gmt,
							'post_content_filtered' => '',
							'post_parent'           => 0,
							'menu_order'            => 0,
							'post_type'             => 'sfwd-courses',
							'post_mime_type'        => '',
							'comment_count'         => '0',
							'filter'                => 'raw',
						),
						'wp_post_permalink' => get_permalink( $course->ID ),
						'wp_post_meta'      => array(
							'_ld_price_type' => $price_type,
							'_sfwd-courses'  => $_sfwd_courses,
						),
						'wp_post_terms'     => array(
							'ld_course_category' => array(),
							'ld_course_tag'      => array(),
						),
					);
					$this->jlog( wp_json_encode( $post ), 'post_type_course' );
				}
			}
		}

		function convertDaysToWeeksMonthsAndYearsRounded( $days ) {
			$daysPerYear  = 365;
			$daysPerMonth = 30;
			$daysPerWeek  = 7;

			$years = floor( $days / $daysPerYear );
			$days -= $years * $daysPerYear;

			$months = round( $days / $daysPerMonth );
			$days  -= $months * $daysPerMonth;

			$weeks = round( $days / $daysPerWeek );
			$days -= $weeks * $daysPerWeek;
			if ( $years ) {
				return array(
					'count' => $years,
					'unit'  => 'Y',
				);
			}
			if ( $months ) {
				return array(
					'count' => $months,
					'unit'  => 'M',
				);
			}
			if ( $weeks ) {
				  return array(
					  'count' => $weeks,
					  'unit'  => 'W',
				  );
			}
			if ( $days ) {
				return array(
					'count' => $days,
					'unit'  => 'D',
				);
			}
		}

		function print( $str ) {
			echo print_r( $str, 1 ) . PHP_EOL;
		}

		function jlog( $str, $table ) {
			file_put_contents( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld', $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}

		function flog( $str ) {
			file_put_contents( trailingslashit( __DIR__ ) . 'flog.log', $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}

	}


	WP_CLI::add_command( 'afc', 'T_LD_Mig' );
}
