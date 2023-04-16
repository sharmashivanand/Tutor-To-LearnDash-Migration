<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class T_LD_Mig {
		function __construct() {
		}

		function export_material() {
			// Tutor 		=> Course > Topic > Lesson
			// LearnDash	=> Course > Lesson > Topic
			$this->export_courses_to_courses(); // Courses are mapped to courses
			$this->export_topics_to_lesson(); // Tutor Topics are mapped to Lessons
			$this->export_lessons_to_topics(); // Tutor Lessons are mapped to topics
			$this->export_enrollments();
		}

		function export_courses_to_courses() {
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
					$_sfwd_courses =
						array(
							'0'                           => '',
							'sfwd-courses_certificate'    => '',
							'sfwd-courses_course_disable_content_table' => '',
							'sfwd-courses_course_disable_lesson_progression' => 'on',
							'sfwd-courses_course_lesson_order_enabled' => '',
							'sfwd-courses_course_lesson_order' => 'ASC',
							'sfwd-courses_course_lesson_orderby' => 'menu_order',
							'sfwd-courses_course_lesson_per_page_custom' => '',
							'sfwd-courses_course_lesson_per_page' => '',
							'sfwd-courses_course_materials_enabled' => '',
							'sfwd-courses_course_materials' => '',
							'sfwd-courses_course_points_access' => '',
							'sfwd-courses_course_points_enabled' => '',
							'sfwd-courses_course_points'  => '',
							'sfwd-courses_course_prerequisite_compare' => 'ANY',
							'sfwd-courses_course_prerequisite_enabled' => '',
							'sfwd-courses_course_prerequisite' => '',
							'sfwd-courses_course_price_billing_p3' => '',
							'sfwd-courses_course_price_billing_t3' => '',
							'sfwd-courses_course_price_type_paynow_enrollment_url' => '',
							'sfwd-courses_course_price_type' => $price_type,
							'sfwd-courses_course_price'   => $product_price,
							'sfwd-courses_course_topic_per_page_custom' => '',
							'sfwd-courses_course_trial_duration_p1' => '',
							'sfwd-courses_course_trial_duration_t1' => '',
							'sfwd-courses_course_trial_price' => '',
							'sfwd-courses_custom_button_url' => '',
							'sfwd-courses_exam_challenge' => 0,
							'sfwd-courses_expire_access_days' => 0,
							'sfwd-courses_expire_access_delete_progress' => '',
							'sfwd-courses_expire_access'  => '',
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
							'post_date_gmt'         => $course->post_date_gmt,
							'post_content'          => $course->post_content,
							'post_title'            => $course->post_title,
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
						'wp_post_permalink' => trailingslashit( home_url() . '/courses/' . $course->post_name ), // get_permalink( $course->ID ), // trailingslashit( home_url() . '/topics/' . $lesson->post_name ),
						'wp_post_meta'      => array(
							'_ld_price_type' => array( $price_type ),
							'_sfwd-courses'  => array( (object) $_sfwd_courses ),
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

		function export_topics_to_lesson() {
			$topics = new WP_Query(
				array(
					'post_type'      => 'topics',
					'posts_per_page' => -1,
				)
			);
			if ( $topics && $topics->post_count ) {
				$topics = $topics->posts;
				foreach ( $topics as $topic ) {
					$post = array(
						'wp_post'           => array(
							'ID'                    => $topic->ID,
							'post_author'           => $topic->post_author,
							'post_date'             => $topic->post_date,
							'post_date_gmt'         => $topic->post_date_gmt,
							'post_content'          => $topic->post_content,
							'post_title'            => $topic->post_title,
							'post_excerpt'          => $topic->post_excerpt,
							'post_status'           => 'publish',
							'comment_status'        => 'closed',
							'ping_status'           => 'closed',
							'post_password'         => '',
							'post_name'             => $topic->post_name,
							'to_ping'               => '',
							'pinged'                => '',
							'post_modified'         => $topic->post_modified,
							'post_modified_gmt'     => $topic->post_modified_gmt,
							'post_content_filtered' => '',
							'post_parent'           => 0,
							'menu_order'            => $topic->menu_order,
							'post_type'             => 'sfwd-lessons',
							'post_mime_type'        => '',
							'comment_count'         => '0',
							'filter'                => 'raw',
						),
						'wp_post_permalink' => trailingslashit( home_url() . '/lessons/' . $topic->post_name ),
						'wp_post_meta'      => array(
							'course_id'     => array( wp_get_post_parent_id( $topic->ID ) ),
							'_sfwd-lessons' => array(
								(object) array(
									'0'                   => '',
									'sfwd-lessons_course' => wp_get_post_parent_id( $topic->ID ),
								),
							),
						),
						'wp_post_terms'     => array(
							'ld_lesson_category' => array(),
							'ld_lesson_tag'      => array(),
						),
					);
					$this->jlog( wp_json_encode( $post ), 'post_type_lesson' );
				}
			}
		}

		function export_lessons_to_topics() {
			$lessons = new WP_Query(
				array(
					'post_type'      => 'lesson',
					'posts_per_page' => -1,
				)
			);
			if ( $lessons && $lessons->post_count ) {
				$lessons = $lessons->posts;
				foreach ( $lessons as $lesson ) {
					$post = array(
						'wp_post'           => array(
							'ID'                    => $lesson->ID,
							'post_author'           => $lesson->post_author,
							'post_date'             => $lesson->post_date,
							'post_date_gmt'         => $lesson->post_date_gmt,
							'post_content'          => $lesson->post_content,
							'post_title'            => $lesson->post_title,
							'post_excerpt'          => $lesson->post_excerpt,
							'post_status'           => 'publish',
							'comment_status'        => 'closed',
							'ping_status'           => 'closed',
							'post_password'         => '',
							'post_name'             => $lesson->post_name,
							'to_ping'               => '',
							'pinged'                => '',
							'post_modified'         => $lesson->post_modified,
							'post_modified_gmt'     => $lesson->post_modified_gmt,
							'post_content_filtered' => '',
							'post_parent'           => 0,
							'menu_order'            => $lesson->menu_order,
							'post_type'             => 'sfwd-topic',
							'post_mime_type'        => '',
							'comment_count'         => '0',
							'filter'                => 'raw',
						),
						'wp_post_permalink' => trailingslashit( home_url() . '/topics/' . $lesson->post_name ),
						'wp_post_meta'      => array(
							'course_id'   => array( wp_get_post_parent_id( wp_get_post_parent_id( $lesson->ID ) ) ),
							'lesson_id'   => array( wp_get_post_parent_id( $lesson->ID ) ),
							'_sfwd-topic' => array(
								(object) array(
									'0'                 => '',
									'sfwd-topic_lesson' => wp_get_post_parent_id( $lesson->ID ), // parent (Tutor Topic)
									'sfwd-topic_course' => wp_get_post_parent_id( wp_get_post_parent_id( $lesson->ID ) ), // ancestor (course)
								),
							),
						),
						'wp_post_terms'     => array(
							'ld_topic_category' => array(),
							'ld_topic_tag'      => array(),
						),
					);
					$this->jlog( wp_json_encode( $post ), 'post_type_topic' );
				}
			}
		}

		function export_enrollments() {
			$timestamp   = time();
			$enrollments = new WP_Query(
				array(
					'post_type'      => 'tutor_enrolled',
					'post_status'    => 'completed',
					'posts_per_page' => -1,
				)
			);
			if ( $enrollments && $enrollments->post_count ) {
				$enrollments = $enrollments->posts;
				foreach ( $enrollments as $enrollment ) {
					$post = array(
						'wp_post' => array(
							'user_id'            => $enrollment->post_author,
							'post_id'            => $enrollment->ID,
							'course_id'          => wp_get_post_parent_id( $enrollment->ID ),
							'activity_type'      => 'access',
							'activity_status'    => '0',
							'activity_started'   => strtotime( $enrollment->post_date_gmt ),
							'activity_completed' => null,
							'activity_updated'   => $timestamp,
							'activity_meta'      => array(),
						),
					);
					$this->jlog( wp_json_encode( $post ), 'user_activity' );
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
			if ( ! is_dir( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv' ) ) {

				wp_mkdir_p( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv' );
			}
			file_put_contents( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld', $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}

		function flog( $str ) {
			file_put_contents( trailingslashit( __DIR__ ) . 'flog.log', $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}

	}

	WP_CLI::add_command( 'afc', 'T_LD_Mig' );
}
