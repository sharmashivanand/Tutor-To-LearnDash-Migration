<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class T_LD_Mig {

		private $debug = false;
		function __construct() {

		}

		function export_all() {
			// Tutor 		=> Course > Topic > Lesson
			// LearnDash	=> Course > Lesson > Topic
			$this->export_courses_to_courses(); // Courses are mapped to courses
			$this->export_topics_to_lesson(); // Tutor Topics are mapped to Lessons
			$this->export_lessons_to_topics(); // Tutor Lessons are mapped to topics
			$this->export_user_activity();
			$this->export_users();
			$this->export_options();
		}

		function export_courses_to_courses() {
			$export_type = 'post_type_course';
			$courses     = new WP_Query(
				array(
					'post_type'      => 'courses',
					'posts_per_page' => -1,
				)
			);

			if ( $courses && $courses->post_count ) {
				$this->cleanup_old_exports( $export_type );
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

					$product_id    = get_post_meta( $course->ID, '_tutor_course_product_id', 1 );
					$product_price = get_post_meta( $product_id, '_price', true );

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
							'sfwd-courses_course_price_type_' . $price_type . '_enrollment_url' => '',
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
					if ( $this->debug ) {
						$this->print( wp_json_encode( $post, JSON_PRETTY_PRINT ) );
					} else {
						$this->jlog( wp_json_encode( $post ), $export_type );
					}
				}
				if ( file_exists( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' ) ) {
					WP_CLI::success( 'Successfully exported ' . trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' );
				} else {
					WP_CLI::warning( 'Nothing exported.' );
				}
			}
		}

		function export_courses_to_users() {
			$export_type = 'post_type_course';
			$courses     = new WP_Query(
				array(
					'post_type'      => 'courses',
					'posts_per_page' => -1,
				)
			);

			if ( $courses && $courses->post_count ) {
				$this->cleanup_old_exports( $export_type );
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

					$product_id    = get_post_meta( $course->ID, '_tutor_course_product_id', 1 );
					$product_price = get_post_meta( $product_id, '_price', true );

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
							'sfwd-courses_course_price_type_' . $price_type . '_enrollment_url' => '',
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
						'wp_post'      => array(
							'url'        => 'https://ashwinflute.com/wp-admin/post.php?p=' . $course->ID,
							'post_title' => $course->post_title,
						),
						'wp_post_meta' => array(
							'_ld_price_type' => array( $price_type ),
							// '_sfwd-courses'  => array( (object) $_sfwd_courses ),
						),

					);
					$children = get_children(
						array(
							'post_parent' => $course->ID,
							'numberposts' => -1,
							'post_type'   => 'any',
						)
					);
					$this->print( $children );
					return;
					if ( $this->debug ) {
						$this->print( wp_json_encode( $post, JSON_PRETTY_PRINT ) );
					} else {
						$this->jlog( wp_json_encode( $post ), $export_type );
					}
				}
				if ( file_exists( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' ) ) {
					WP_CLI::success( 'Successfully exported ' . trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' );
				} else {
					WP_CLI::warning( 'Nothing exported.' );
				}
			}
		}

		function export_topics_to_lesson() {
			$export_type = 'post_type_lesson';
			$topics      = new WP_Query(
				array(
					'post_type'      => 'topics',
					'posts_per_page' => -1,
				)
			);
			if ( $topics && $topics->post_count ) {
				$this->cleanup_old_exports( $export_type );
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
					if ( $this->debug ) {
						$this->print( wp_json_encode( $post, JSON_PRETTY_PRINT ) );
					} else {
						$this->jlog( wp_json_encode( $post ), $export_type );
					}
				}
				if ( file_exists( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' ) ) {
					WP_CLI::success( 'Successfully exported ' . trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' );
				} else {
					WP_CLI::warning( 'Nothing exported.' );
				}
			}
		}

		function export_lessons_to_topics() {
			$export_type = 'post_type_topic';
			$lessons     = new WP_Query(
				array(
					'post_type'      => 'lesson',
					'posts_per_page' => -1,
				)
			);
			if ( $lessons && $lessons->post_count ) {
				$this->cleanup_old_exports( $export_type );
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
					if ( $this->debug ) {
						$this->print( wp_json_encode( $post, JSON_PRETTY_PRINT ) );
					} else {
						$this->jlog( wp_json_encode( $post ), $export_type );
					}
				}
				if ( file_exists( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' ) ) {
					WP_CLI::success( 'Successfully exported ' . trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' );
				} else {
					WP_CLI::warning( 'Nothing exported.' );
				}
			}
		}

		function export_user_activity() {

			$export_type = 'user_activity';
			$timestamp   = time();
			$enrollments = new WP_Query(
				array(
					'post_type'              => 'tutor_enrolled',
					'post_status'            => 'completed',
					'orderby'                => 'date',
					'order'                  => 'ASC',
					'posts_per_page'         => -1,
					'cache_results'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'cache'                  => time(),
				)
			);

			if ( $enrollments && $enrollments->post_count ) {
				$this->cleanup_old_exports( $export_type );
				$enrollments = $enrollments->posts;
				foreach ( $enrollments as $enrollment ) {
					if ( $enrollment->post_status != 'completed' ) { // Somehow WP_Query returns all post_statuses when passed 'completed
						// $this->print( $enrollment->post_status );
						continue;
					}
					if ( $enrollment->ID == 9577 ) {
						// $this->print( $enrollment );
					}
					$post = array(
						'wp_post' => array(
							'user_id'            => $enrollment->post_author,
							'post_id'            => $enrollment->ID,
							'course_id'          => wp_get_post_parent_id( $enrollment->ID ),
							'activity_type'      => 'access',
							'activity_status'    => '0',
							'activity_started'   => strtotime( $enrollment->post_date ), // post_date_gmt was sometimes empty in the database
							'activity_completed' => null,
							'activity_updated'   => $timestamp,
							'activity_meta'      => array(),
						),
					);
					if ( $this->debug ) {
						$this->print( wp_json_encode( $post, JSON_PRETTY_PRINT ) );
					} else {
						$this->jlog( wp_json_encode( $post ), $export_type );
					}
				}
				if ( file_exists( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' ) ) {
					WP_CLI::success( 'Successfully exported ' . trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $export_type . '.ld' );
				} else {
					WP_CLI::warning( 'Nothing exported.' );
				}
			}

		}

		function export_users() {
			$users = get_users();
			$this->cleanup_old_exports( 'user' );
			foreach ( $users as $user ) {
				$user_enrollments = new WP_Query(
					array(
						'post_type'      => 'tutor_enrolled',
						'post_status'    => 'any',
						'author'         => $user->data->ID, // $user->data->ID,
						'posts_per_page' => -1,
					)
				);

				if ( $user_enrollments && $user_enrollments->post_count ) {
					$user_meta = get_user_meta( $user->data->ID );

					$user_details = array(
						'wp_user'      => array(
							'ID'              => $user->data->ID,
							'user_login'      => $user->data->user_login,
							'user_pass'       => $user->data->user_pass,
							'user_nicename'   => $user->data->user_nicename,
							'user_email'      => $user->data->user_email,
							'user_registered' => $user->data->user_registered,
							'user_status'     => $user->data->user_status,
							'display_name'    => $user->data->display_name,
							'role'            => 'subscriber',
						),
						'wp_user_meta' => array(
							'nickname'             => array( $user_meta['nickname'][0] ),
							'first_name'           => array( $user_meta['first_name'][0] ),
							'last_name'            => array( $user_meta['last_name'][0] ),
							'description'          => array( $user_meta['description'][0] ),
							'rich_editing'         => array( $user_meta['rich_editing'][0] ),
							'syntax_highlighting'  => array( $user_meta['syntax_highlighting'][0] ),
							'comment_shortcuts'    => array( $user_meta['comment_shortcuts'][0] ),
							'admin_color'          => array( $user_meta['admin_color'][0] ),
							'use_ssl'              => array( $user_meta['use_ssl'][0] ),
							'show_admin_bar_front' => array( $user_meta['show_admin_bar_front'][0] ),
							'locale'               => array( $user_meta['locale'][0] ),
							'wp_capabilities'      => array( array( 'subscriber' => true ) ),
							'wp_user_level'        => $user_meta['wp_user_level'][0],
							'twitter'              => '',
						),
					);
					$enrollments  = $user_enrollments->posts;

					foreach ( $enrollments as $enrollment ) {
						if ( $enrollment->post_status != 'completed' ) {
							continue;
						}
						// $this->print( wp_get_post_parent_id( $enrollment->ID ) );
						$user_details['wp_user_meta'][ 'course_' . wp_get_post_parent_id( $enrollment->ID ) . '_access_from' ] = array( strtotime( $enrollment->post_date ) );
					}
					$this->jlog( wp_json_encode( $user_details ), 'user' );
				}
			}
		}

		function export_options() {
			// {"post_types":["sfwd-courses","sfwd-lessons","sfwd-topic","sfwd-quiz","sfwd-question","sfwd-transactions","groups","sfwd-assignment","sfwd-essays","sfwd-certificates","ld-exam","ld-coupon"],"post_type_settings":["sfwd-courses","sfwd-lessons","sfwd-topic","sfwd-quiz","sfwd-question","sfwd-transactions","groups","sfwd-assignment","sfwd-essays","sfwd-certificates","ld-exam","ld-coupon"],"users":["profiles","progress"],"other":["settings"],"info":{"ld_version":"4.5.3","wp_version":"6.2","db_prefix":"wp_","is_multisite":false,"blog_id":1,"home_url":"https:\/\/dev.converticacommerce.com\/ldsb"}}
			$configuration = array(
				'post_types'         => array(
					'sfwd-courses',
					'sfwd-lessons',
					'sfwd-topic',
					'sfwd-quiz',
					'sfwd-question',
				),
				'post_type_settings' => array(),
				'users'              => array(
					'profiles',
					'progress',
				),
				'other'              => array(),
				'info'               => array(
					'ld_version'   => '4.5.3',
					'wp_version'   => '6.2',
					'db_prefix'    => 'wp_',
					'is_multisite' => false,
					'blog_id'      => 1,
					'home_url'     => home_url(),
				),
			);

			$taxonomies =
				array(
					array(
						'wp_taxonomy_terms' => array(),
					),
					array(
						'wp_taxonomy_terms' => array(),
					),
					array(
						'wp_taxonomy_terms' => array(),
					),
					array(
						'wp_taxonomy_terms' => array(),
					),
					array(
						'wp_taxonomy_terms' => array(),
					),
					array(
						'wp_taxonomy_terms' => array(),
					),
				);
			$this->cleanup_old_exports( 'taxonomies' );
			$this->jlog( wp_json_encode( $taxonomies ), 'taxonomies' );
			$this->cleanup_old_exports( 'configuration' );
			$this->jlog( wp_json_encode( $configuration ), 'configuration' );
			$this->cleanup_old_exports( 'proquiz' );
			$this->jlog( '', 'proquiz' );
			$this->cleanup_old_exports( 'post_type_quiz' );
			$this->jlog( '', 'post_type_quiz' );
			$this->cleanup_old_exports( 'post_type_question' );
			$this->jlog( '', 'post_type_question' );
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

		function cleanup_old_exports( $table ) {
			if ( is_file( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld' ) ) {
				// WP_CLI::log( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld' );
				wp_delete_file( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld' );
			}
		}

		function jlog( $str, $table ) {
			if ( ! is_dir( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv' ) ) {
				wp_mkdir_p( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv' );
			}
			if ( empty( $str ) ) {
				wp_delete_file( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld' );
				touch( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld' );
				WP_CLI::warning( 'Created new file ' . trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld' );
				return;
			}
			file_put_contents( trailingslashit( __DIR__ ) . '/learndash-export-ldsb-20230415-shiv/' . $table . '.ld', $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}

		function flog( $str ) {
			file_put_contents( trailingslashit( __DIR__ ) . 'flog.log', $str . PHP_EOL, FILE_APPEND | LOCK_EX );
		}

	}

	WP_CLI::add_command( 'afc', 'T_LD_Mig' );
}
