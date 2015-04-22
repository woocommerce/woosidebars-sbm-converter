<?php
/**
 * WooSidebars SBM Converter Class
 *
 * @package WordPress
 * @subpackage WooSidebars SBM Converter
 * @category Core
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * - __construct()
 * - load_localisation()
 * - load_plugin_textdomain()
 * - register_admin_screen()
 * - process_request()
 * - admin_notices()
 * - admin_screen()
 * - has_sidebars()
 * - convert_sidebars()
 * - prepare_sidebars_data()
 * - store_unconverted_sidebars()
 * - parse_single_sidebar()
 * - determine_conditions()
 * - add_sidebar()
 * - prepare_internal_comparison_data()
 * - get_hierarchy_options()
 * - delete_sidebars()
 * - toggle_sidebar_manager_status()
 * - disable_sidebar_manager()
 * - remove_sbm_registration()
 * - remove_sbm_filter()
 */

class Woosidebars_SBM_Converter {
	private $file = '';
	private $token = '';
	private $title = '';
	private $hook = '';
	private $sbm_data = array();
	private $not_converted = array();
	private $converted = array();
	private $dependencies = array();
	private $comparison_data = array();

	public $version;
	
	/**
	 * Constructor function.
	 * @since  1.0.0
	 * @return void
	 */
	public function __construct ( $file ) {
		$this->file = $file;
		$this->token = 'woosidebars-sbm-converter';
		$this->title = __( 'Sidebar Manager Converter', 'woosidebars-sbm-converter' );

		if ( is_admin() ) {
			// Get existing converted sidebar id's
			$this->converted = get_option( $this->token . '-converted', array() );

			add_action( 'admin_menu', array( &$this, 'register_admin_screen' ) );
			
			// Localisation
			$this->load_plugin_textdomain();
			add_action( 'init', array( &$this, 'load_localisation' ), 0 );
		}

		// 1 = enabled Sidebar Manager, 0 = disabled Sidebar Manager
		if ( '1' != get_option( $this->token . '-sbm-status', '1' ) ) {
			$this->disable_sidebar_manager();
		}
	} // End __construct()
	
	/**
	 * Load the plugin's localisation file.
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'woosidebars-sbm-converter', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation()

	/**
	 * Load the plugin textdomain from the main WordPress "languages" folder.
	 * @since  1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'woosidebars-sbm-converter';
	    // The "plugin_locale" filter is also used in load_plugin_textdomain()
	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	 
	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain()
	
	/**
	 * Register the admin screen within WordPress.
	 * @since  1.0.0
	 * @return void
	 */
	public function register_admin_screen () {
		$hook = add_submenu_page( 'tools.php', $this->title, __( 'Convert Sidebars', 'woosidebars-sbm-converter' ), 'edit_theme_options', 'woosidebars-sbm-converter', array( &$this, 'admin_screen' ) );

		$this->hook = $hook;

		add_action( 'load-' . $hook, array( &$this, 'process_request' ) );
	} // End register_admin_screen()

	/**
	 * Process the desired action, if applicable.
	 * @since  1.0.0
	 * @return void
	 */
	public function process_request () {
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );

		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'convert', 'delete', 'toggle-sbm' ) ) && check_admin_referer( $this->token ) ) {
			$response = false;
			$status = 'false';

			switch ( $_GET['action'] ) {
				case 'convert':
					$response = $this->convert_sidebars();
				break;

				case 'delete':
					$response = $this->delete_sidebars();
				break;

				case 'toggle-sbm':
					$response = $this->toggle_sidebar_manager_status();
				break;

				default:
				break;
			}

			if ( $response == true ) {
				$status = 'true';
			}

			wp_safe_redirect( esc_url( add_query_arg( 'type', urlencode( $_GET['action'] ),  add_query_arg( 'status', urlencode( $status ), add_query_arg( 'page', urlencode( $this->token ),  admin_url( 'tools.php' ) ) ) ) ) );
			exit;
		}
	} // End process_request()

	/**
	 * Display admin notices.
	 * @since  1.0.0
	 * @return void
	 */
	public function admin_notices () {
		$message = '';
		$response = '';
		
		// Display unconverted sidebars
		$has_sidebars = $this->has_sidebars();
		
		if ( isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'true', 'false' ) ) && isset( $_GET['type'] ) && in_array( $_GET['type'], array( 'convert', 'delete', 'toggle-sbm' ) ) ) {
			$terminology = array( 'convert' => __( 'converted', 'woosidebars-sbm-converter' ), 'delete' => __( 'deleted', 'woosidebars-sbm-converter' ) );
			$classes = array( 'true' => 'updated', 'false' => 'error' );

			if ( 'toggle-sbm' == $_GET['type'] ) {
				$sbm_status = get_option( $this->token . '-sbm-status', '1' );
	        	$status_label = __( 'enabled', 'woosidebars-sbm-converter' );
	        	if ( '1' != $sbm_status ) {
	        		$status_label = __( 'disabled', 'woosidebars-sbm-converter' );
	        	}
	        	if ( 'true' == $_GET['status'] ) {
					$message = sprintf( __( 'WooFramework Sidebar Manager %s successfully.', 'woosidebars-sbm-converter' ), $status_label );
				} else {
					$message = sprintf( __( 'There was an error and the WooFramework Sidebar Manager was not %s.', 'woosidebars-sbm-converter' ), $status_label );
				}
			} else {
				if ( 'true' == $_GET['status'] ) {
					$message = sprintf( __( 'Sidebars %s successfully.', 'woosidebars-sbm-converter' ), $terminology[$_GET['type']] );
				} else {
					$message = sprintf( __( 'There was an error and not all sidebars were %s.', 'woosidebars-sbm-converter' ), $terminology[$_GET['type']] );
				}
			}

			$response = '<div class="' . esc_attr( $classes[$_GET['status']] ) . ' fade">' . "\n";
			$response .= wpautop( $message );
			
			if ( 'convert' == $_GET['type'] ) {
				// Show troublesome sidebars.
				$not_converted = get_option( $this->token . '-not-converted', array() );
				if ( is_array( $not_converted ) && ( 0 < count( $not_converted ) ) ) {
					$response .= '<h4>' . __( 'The following sidebars were not converted:', 'woosidebars-sbm-converter' ) . '</h4>' . "\n";
					$response .= '<ul>' . "\n";
					foreach ( $not_converted as $k => $v ) {
						if ( isset( $v['setup']['name'] ) ) {
							$response .= '<li>' . $v['setup']['name'] . ' <small>(' . $v['setup']['id'] . ')</small></li>' . "\n";
						}
					}
					$response .= '</ul>' . "\n";
				}

				$dependencies = get_option( $this->token . '-dependencies', array() );
				if ( is_array( $dependencies ) && ( 0 < count( $dependencies ) ) ) {
					$response .= '<h4>' . __( 'The following sidebar dependencies were not converted:', 'woosidebars-sbm-converter' ) . '</h4>' . "\n";
					$response .= '<ul>' . "\n";
					foreach ( $dependencies as $k => $v ) {
						if ( isset( $v['setup']['name'] ) ) {
							$response .= '<li>' . $v['setup']['name'] . ' <small>(' . $v['setup']['id'] . ')</small></li>' . "\n";
						}
					}
					$response .= '</ul>' . "\n";
				}
			}

			$response .= '</div>' . "\n";

			if ( '' != $response ) {
				echo $response;
			}
		}
	} // End admin_notices()

	/**
	 * The output of the admin screen.
	 * @since  1.0.0
	 * @return void
	 */
	public function admin_screen () {
		echo '<div class="wrap">' . "\n";
		screen_icon();
		echo '<h2>' . esc_html( $this->title ) . '</h2>' . "\n";
		
		$update = false;
		if ( defined( 'THEME_FRAMEWORK' ) && constant( 'THEME_FRAMEWORK' ) == 'woothemes' ) {
			// Check if there is a later version of the Woo Framework
			$localversion = get_option( 'woo_framework_version' );
        	// Test if new version
			$update = version_compare( $localversion, '5.4.0', '<' );
		}
        		
		// HTML
        if ( $update ) {
        	echo '<p>' . __( 'Please update your WooFramework to the latest version before attempting to convert your existing sidebars.', 'woosidebars-sbm-converter' ) . '</p>' . "\n";
        	echo '<p class="submitbox"><br /><a href="' . esc_url( admin_url( 'admin.php?page=woothemes_framework_update' ) ) . '" class="button upgrade">' . __( 'Upgrade your WooFramework', 'woosidebars-sbm-converter' ) . '</a></p>' . "\n";
        } else {
        	// Determine the current status of the Sidebar Manager ("Disable" if 1, "Enable" if 0).
        	$sbm_status = get_option( $this->token . '-sbm-status', '1' );
        	$status_label = __( 'Disable', 'woosidebars-sbm-converter' );
        	if ( '1' != $sbm_status ) {
        		$status_label = __( 'Enable', 'woosidebars-sbm-converter' );
        	}

        	echo '<p>' . __( 'Convert your custom sidebars created using the WooFramework\'s "Sidebar Manager" into Widget Areas for use with the WooSidebars plugin, with the appropriate conditions applied.', 'woosidebars-sbm-converter' ) . '</p>' . "\n";
			if ( ! $this->has_sidebars() ) {
				echo '<div class="updated">' . "\n";
				echo '<p>' . __( 'You\'ve got no outstanding Sidebar Manager sidebars... you\'re good to go!', 'woosidebars-sbm-converter' ) . '</p>' . "\n";
				echo '</div>' . "\n";
			} else {
				echo '<div class="updated fade"><p>' . __( 'We\'ve detected that you\'ve got data outstanding in the "Sidebar Manager". Clicking the button below will attempt to convert that data for use with the WooSidebars plugin.', 'woosidebars-sbm-converter' ) . '</p></div>' . "\n";

				// Convert button.
				echo '<p><br /><a href="' . esc_url( wp_nonce_url( add_query_arg( 'action', 'convert', admin_url( 'tools.php?page=' . urlencode( $this->token ) ) ), $this->token ) ) . '" class="button">' . __( 'Convert Sidebar Manager Data', 'woosidebars-sbm-converter' ) . '</a></p>' . "\n";

				echo '<p><br />' . sprintf( __( 'Once you\'ve converted your Sidebar Manager data for use with WooSidebars, please visit the "%sWidget Areas%s" screen to see if your sidebars converted successfully.', 'woosidebars-sbm-converter' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=sidebar' ) ) . '" target="_blank">', '</a>' ) . '</p>' . "\n";

				echo '<p>' . __( 'To use WooSidebars in place of the WooFramework\'s Sidebar Manager, you need to disable the WooFramework\'s Sidebar Manager. To enable or disable the Sidebar Manager, please use the button below.', 'woosidebars-sbm-converter' ) . '</p>' . "\n";

				// Toggle Sidebar Manager button.
				echo '<p><br /><a href="' . esc_url( wp_nonce_url( add_query_arg( 'action', 'toggle-sbm', admin_url( 'tools.php?page=' . urlencode( $this->token ) ) ), $this->token ) ) . '" class="button">' . sprintf( __( '%s Sidebar Manager', 'woosidebars-sbm-converter' ), $status_label ) . '</a></p>' . "\n";
			
				echo '<p style="display: none;"><br />' . sprintf( __( 'Once you\'re happy that your sidebars have been converted successfully, you can remove the outdated "Sidebar Manager" data using the link below %s(this cannot be undone)%s.', 'woosidebars-sbm-converter' ), '<strong>', '</strong>' ) . '</p>' . "\n";

				// Remove button.
				echo '<p class="submitbox" style="display: none;"><br /><a href="' . esc_url( wp_nonce_url( add_query_arg( 'action', 'delete', admin_url( 'tools.php?page=' . urlencode( $this->token ) ) ), $this->token ) ) . '" class="submitdelete deletion">' . __( 'Remove Sidebar Manager Data', 'woosidebars-sbm-converter' ) . '</a></p>' . "\n";
			} // End If Statement
        
        } // End If Statement
		echo '</div><!--/.wrap-->' . "\n";
	} // End admin_screen()

	/**
	 * Check if we've got existing Sidebar Manager data. Assign the data internally if we do have.
	 * @since  1.0.0
	 * @return boolean
	 */
	private function has_sidebars () {
		$response = false;

		if ( 0 < count( $this->sbm_data ) ) return true; // If we've already checked, we don't need to check again.

		$data = get_option( 'sbm_woo_sbm_options', array() );

		if ( isset( $data['sidebars'] ) && is_array( $data['sidebars'] ) && ( 0 < count( $data['sidebars'] ) ) ) {
			$this->sbm_data = (array)$data['sidebars'];
			$response = true;
		}

		return $response;
	} // End has_sidebars()

	/**
	 * Convert sidebars to work with WooSidebars.
	 * @since  1.0.0
	 * @return boolean
	 */
	private function convert_sidebars () {
		$response = false;
		
		if ( $this->has_sidebars() ) {
			$response = $this->prepare_sidebars_data( $this->sbm_data );

			if ( 0 < count( (array)$response ) ) {
				foreach ( (array)$response as $k => $v ) {
					$this->add_sidebar( $v );
				}
				update_option( $this->token . '-converted', $this->converted );
			}
		}
		
		return $response;
	} // End convert_sidebars()

	/**
	 * Prepare the data for entry as Widget Areas.
	 * @since  1.0.0
	 * @param  array $data Sidebar Manager data.
	 * @return array       Prepared data for entry into WooSidebars.
	 */
	private function prepare_sidebars_data ( $data ) {
		$response = array();

		$this->prepare_internal_comparison_data();

		foreach ( (array)$data as $k => $v ) {
			// Skip over dependencies. We'll do those once all main sidebars have been processed.
			if ( isset( $v['conditionals']['piggy'] ) && ( '' != $v['conditionals']['piggy'] ) ) {
				$this->dependencies[$k] = $v;
				continue;
			}

			$prep = $this->parse_single_sidebar( $k, $v );

			$response[$k] = $prep;
		}
		
		if ( 0 <= count( (array)$this->dependencies ) ) {
			foreach ( $this->dependencies as $k => $v ) {
				if ( in_array( $v['conditionals']['piggy'], array_keys( $response ) ) ) {
					$conditions = $this->determine_conditions( $v );
					if ( 0 <= count( (array)$conditions ) ) {
						$response[$v['conditionals']['piggy']]['post_meta']['_condition'] = array_merge( $response[$v['conditionals']['piggy']]['post_meta']['_condition'], $conditions );
						unset( $this->dependencies[$k] );
					}
				}
			}
		}

		$this->store_unconverted_sidebars();
		
		return $response;
	} // End prepare_sidebars_data()

	/**
	 * Store data that the converter had trouble converting.
	 * @since  1.0.0
	 * @return void
	 */
	private function store_unconverted_sidebars () {
		if ( 0 <= count( (array)$this->not_converted ) ) {
			update_option( $this->token . '-not-converted', $this->not_converted, 120 );
		}

		if ( 0 <= count( (array)$this->dependencies ) ) {
			update_option( $this->token . '-dependencies', $this->dependencies, 120 );
		}
	} // End store_unconverted_sidebars()

	/**
	 * Parse data for a single sidebar.
	 * @since  1.0.0
	 * @param  string $k The ID of the sidebar.
	 * @param  array  $v Data pertaining to the sidebar being parsed.
	 * @return array
	 */
	private function parse_single_sidebar ( $k, $v ) {
		$response = array( 'post_meta' => array( '_condition' => array() ) );
	
		// Check if the sidebar has already been converted
		if ( isset( $v['conditionals']['sidebar_id'] ) && ( in_array( $v['conditionals']['sidebar_id'], $this->converted ) ) ) {
			return array();
		} // End If Statement
		
		// Check for a slug. If we don't have one, skip over this sidebar.
		if ( isset( $v['conditionals']['sidebar_id'] ) && ( '' != $v['conditionals']['sidebar_id'] ) ) {
			$response['post_name'] = esc_attr( $v['conditionals']['sidebar_id'] );
		} else {
			$this->not_converted[$k] = $v; // Keep a log of this item, which wasn't converted.
			continue; // Skip this one, as we don't have a proper slug.
		}

		// Conditions.
		$response['post_meta']['_condition'] = $this->determine_conditions( $v );
		
		if ( 0 >= count( (array)$response['post_meta']['_condition'] ) ) {
			$this->not_converted[$k] = $v; // Keep a log of this item, which wasn't converted.
			return array(); // Skip over this one if we don't have any conditions.
		}

		// Title.
		if ( isset( $v['conditionals']['name'] ) ) {
			$response['post_title'] = esc_attr( $v['conditionals']['name'] );
		}

		// Sidebar to replace.
		if ( isset( $v['conditionals']['sidebar_to_replace'] ) && ( '' != $v['conditionals']['sidebar_to_replace'] ) ) {
			$response['post_meta']['_sidebar_to_replace'] = esc_attr( $v['conditionals']['sidebar_to_replace'] );
		}

		// Description.
		if ( isset( $v['setup']['description'] ) ) {
			$response['post_excerpt'] = esc_attr( $v['setup']['description'] );
		}

		return $response;
	} // End parse_single_sidebar()

	/**
	 * Determine the conditions for this given data.
	 * @since  1.0.0
	 * @param  array $data Sidebar Manager data.
	 * @return array       Conditions.
	 */
	private function determine_conditions ( $data ) {
		$conditions = array();

		if ( ! isset( $data['conditionals']['conditional'] ) && ! isset( $data['conditionals']['id'] ) ) { return $conditions; }

		$condition = $data['conditionals']['conditional'];
	
		if ( 'hierarchy' == $condition ) {
			$condition = $data['conditionals']['id'];
		}

		if ( '' == $condition ) {
			$condition = $data['conditionals']['type'];
		}

		if ( '' == $condition ) { return $conditions; }

		$type = '';
		
		foreach ( $this->comparison_data as $k => $v ) {
			if ( in_array( $condition, $v ) ) {
				$type = $k;
				break;
			}
		}
		
		if ( '' == $type ) { $type = $condition; }
		
		switch ( $type ) {
			case 'post_types':
			if ( 'page' == $data['conditionals']['id'] ) {
				$conditions[] = 'page';
			} else {
				$conditions[] = 'post-' . $data['conditionals']['id'];
			} // End If Statement
			break;

			case 'taxonomies':
			if ( 'category' == $data['conditionals']['id'] ) {
				$conditions[] = 'archive-' . $data['conditionals']['id'];
			} else {
				$conditions[] = 'term-' . $data['conditionals']['id'];
			} // End If Statement
			break;

			case 'custom_post_type':
			$conditions[] = 'post-type-' . $data['conditionals']['id'];
			break;

			case 'post_type_archive':
			$conditions[] = 'post-type-archive-' . $data['conditionals']['id'];
			break;

			case 'page_template':
			$token = str_replace( '.php', '', 'page-template-' . $data['conditionals']['id'] );
			$conditions[] = esc_attr( $token );
			break;

			case 'hierarchy':
			if ( 'tag' == $data['conditionals']['id'] ) {
				$conditions[] = 'archive-post_' . $data['conditionals']['id'];
			} elseif ( 'tax' == $data['conditionals']['id'] ) {
				$conditions[] = 'archive-taxonomy';
			} elseif ( 'attach' == $data['conditionals']['id'] ) {
				$conditions[] = 'attachment';
			} else {
				if ( in_array( $data['conditionals']['id'], $this->comparison_data['hierarchy'] ) ) { $conditions[] = $data['conditionals']['id']; }
			} // End If Statement
			break;

			default:
			break;
		}

		return $conditions;
	} // End determine_conditions()

	/**
	 * Add a widgetized area to WooSidebars.
	 * @since  1.0.0
	 * @param  array $data Data for the widget area.
	 * @return boolean
	 */
	private function add_sidebar ( $data ) {
		$response = false;
		
		// Check if there is data
		if ( isset( $data['post_name'] ) && ( $data['post_name'] != '' ) ) {
			$post_id = wp_insert_post( array( 'post_title' => esc_html( $data['post_title'] ), 'post_name' => urlencode( $data['post_name'] ), 'post_excerpt' => esc_html( $data['post_excerpt'] ), 'post_status' => 'publish', 'post_type' => 'sidebar' ) );
			
			if ( 0 < intval( $post_id ) ) {
				add_post_meta( intval( $post_id ), '_sidebar_to_replace', esc_attr( $data['post_meta']['_sidebar_to_replace'] ), true );
				
				foreach ( $data['post_meta']['_condition'] as $k => $v ) {
					add_post_meta( intval( $post_id ), '_condition', esc_attr( $v ), false );
				}
			
				// Add Sidebar to already converted list in the db - woo_sbm_converted
				if ( ! in_array( $data['post_name'], $this->converted ) ) {
					array_push( $this->converted, $data['post_name'] );
				}
				
				$response = true;
			}	
		}
		
		return $response;
	} // End add_sidebar()

	/**
	 * Prepare the data to be compared against when determining the conditions.
	 * @since  1.0.0
	 * @return void
	 */
	private function prepare_internal_comparison_data () {
		$this->comparison_data['post_types'] = get_post_types();
		$this->comparison_data['taxonomies'] = get_taxonomies();
		$this->comparison_data['hierarchy'] = $this->get_hierarchy_options();
	} // End prepare_internal_comparison_data()

	/**
	 * Return an array of Sidebar Manager hierarchy options.
	 * @since  1.0.0
	 * @return array
	 */
	private function get_hierarchy_options () {
		return array( 'front_page', 'home', 'single', 'page', 'singular', 'archive', 'category', 'tag', 'tax', 'author', 'date', 'search', 'paged', 'attach', '404' );
	} // End get_hierarchy_options()

	/**
	 * Delete the old sidebars data.
	 * @since  1.0.0
	 * @return boolean
	 */
	private function delete_sidebars () {
		$response = false;
		$response = delete_option( 'sbm_woo_sbm_options' );
		return $response;
	} // End delete_sidebars()

	/**
	 * Toggle the status of whether or not the Sidebar Manager is enabled (1 = currently enabled, 0 = currently disabled).
	 * @since  1.1.0
	 * @return boolean Whether or not the update process was successful.
	 */
	private function toggle_sidebar_manager_status () {
		$response = get_option( $this->token . '-sbm-status', '1' );
		if ( '1' != $response ) { $response = '1'; } else { $response = '0'; }
		$status = update_option( $this->token . '-sbm-status', $response );

		return $status;
	} // End toggle_sidebar_manager_status()

	/**
	 * Add actions that disable the filters and actions added by the WooFramework's Sidebar Manager.
	 * @since  1.1.0
	 * @return  void
	 */
	private function disable_sidebar_manager () {
		add_action( 'after_setup_theme', array( &$this, 'remove_sbm_registration' ) );
		add_action( 'get_header', array( &$this, 'remove_sbm_filter' ) );
	} // End disable_sidebar_manager()

	/**
	 * Remove the action that registers the WooFramework's custom sidebars.
	 * @since  1.1.0
	 * @return  void
	 */
	public function remove_sbm_registration () {
    	remove_action( 'init', 'woo_sbm_widgets_init' );
	} // End remove_sbm_registration()

	/**
	 * Remove the frontend filter that detects the WooFramework's custom sidebars.
	 * @since  1.1.0
	 * @return  void
	 */
	public function remove_sbm_filter () {
	    remove_filter( 'woo_inject_sidebar', 'woo_sbm_sidebar' );
	} // End remove_sbm_filter()
} // End Class
?>