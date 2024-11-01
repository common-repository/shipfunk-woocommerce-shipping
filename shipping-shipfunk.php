<?php
/*
 * Plugin Name: Shipfunk WooCommerce Shipping
 * Plugin URI: http://docs.shipfunk.com
 * Description: Obtain delivery options, real time shipping rates and package cards via Shipfunk API.
 * Version: 1.3.5
 * Author: Shipfunk
 * Author URI: http://www.shipfunk.com
 * Text Domain: shipfunk-woocommerce-shipping
 * Domain Path: /languages
 * WC tested up to: 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    /**
     * WC_Shipfunk class
     */
    class WC_Shipfunk {

        /** Gateway class name */
        const GATEWAY_CLASS_NAME = 'WC_Shipping_Shipfunk';

        /** Gateway class */
        private $gateway_class;

        /** Constructor */
        public function __construct() {

            $this->gateway_class = self::GATEWAY_CLASS_NAME;

            add_action( 'plugins_loaded', array( $this, 'sf_shipping_init') );
            add_action( 'init', array( $this, 'sf_ajax_update_shipping_method_load_and_checkout' ), 10 );
            add_action( 'wc_shipping_shipfunk_init', array( $this, 'sf_store_gateway' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'sf_frontend_scripts' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'sf_admin_scripts' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'sf_enque_admin_styles' ) );

            add_filter( 'woocommerce_checkout_fields', array( $this, 'sf_checkout_fields' ) );
            add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'sf_admin_shipping_fields') );

            add_action( 'add_meta_boxes_shop_order', array( $this, 'sf_add_meta_boxes' ) );
            add_action( 'save_post_shop_order', array( $this, 'sf_save_meta_box_data' ) );

            add_filter( 'woocommerce_locate_template', array( $this, 'sf_woocommerce_locate_template' ), 10, 3 );
            add_action( 'woocommerce_thankyou', array( $this, 'sf_order_info' ), 20 );
            add_action( 'woocommerce_view_order', array( $this, 'sf_order_info' ), 20 );
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_action_links' ) );

            add_action( 'woocommerce_email_order_meta', array( $this, 'sf_custom_email_order_meta' ), 10, 3 );

            add_action( 'woocommerce_order_actions', array( $this, 'sf_add_order_meta_box_action' ) );
            add_action( 'woocommerce_order_action_sf_create_order', array( $this,'sf_process_create_order_meta_box_action' ) );

            add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'sf_hidden_order_itemmeta' ), 10, 1  );

            // This add_action was moved here from WC_Shipping_Shipfunk, because the action did not fire there on WC 4.0.0
            add_action( 'woocommerce_order_status_processing', array( $this, 'create_order' ), 15, 1 );

            if ( is_admin() ) {
                add_action( 'wp_ajax_get_pickups', array( $this, 'get_pickups_callback' ) );
                add_action( 'wp_ajax_nopriv_get_pickups', array( $this, 'get_pickups_callback' ) );
                add_action( 'wp_ajax_save_selected_pickup', array( $this, 'save_selected_pickup_callback' ) );
                add_action( 'wp_ajax_nopriv_save_selected_pickup', array( $this, 'save_selected_pickup_callback' ) );
            }

            if( $this->klarna_allow_separate_shipping() ) {
                add_action( 'admin_notices', array($this, 'sf_admin_notice' ) );
            }

        }

        /**
        * Load admin stylesheet
        */
        public function sf_enque_admin_styles() {
            wp_register_style( 'shipfunk_wp_admin_css', plugins_url('/shipfunk-woocommerce-shipping/admin-style.css'), false, '1.0.0' );
            wp_enqueue_style( 'shipfunk_wp_admin_css' );
        }

        /**
         * Load classes
         */
        public function sf_shipping_init() {
            load_plugin_textdomain( 'shipfunk-woocommerce-shipping', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );

            include_once( dirname( __FILE__ ) . '/includes/class-wc-shipfunk-api.php' );
            include_once( dirname( __FILE__ ) . '/includes/class-wc-shipfunk-api-helper.php' );
            include_once( dirname( __FILE__ ) . '/includes/class-wc-shipfunk-order.php' );
            require_once( 'includes/class-wc-shipping-shipfunk.php' );

            // Register the shipping method
            add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
        }

        /**
         * Declare the class and tell WooCommerce it exists
         *
         * @param array $methods
         * @return array
         */
        public function add_shipping_method( $methods ) {
            $methods[] = $this->gateway_class;
            return $methods;
        }

        /**
         * If shipping methods are updated by ajax call, load gateway, otherwise pickups and info fields will be gone on order review.
         * Load gateway after ajax checkout, so selected delivery api and orderpaid api are called
         */
        public function sf_ajax_update_shipping_method_load_and_checkout(){
            if ( defined('DOING_AJAX') && DOING_AJAX && isset( $_REQUEST['wc-ajax'] ) && ( $_REQUEST['wc-ajax'] == 'update_order_review' || $_REQUEST['wc-ajax'] == 'checkout' || $_REQUEST['wc-ajax'] === 'kco_wc_iframe_shipping_address_change' || $_REQUEST['wc-ajax'] === 'kco_wc_update_klarna_order' ) ){
                $this->sf_init_gateway_class();
            }
        }

        /**
         * Add action links
         *
         * @param array $links
         */
        public function add_action_links( $links ) {
            $translation = __( 'Docs', 'shipfunk-woocommerce-shipping' );
            $pluginLinks = array(
                '<a target="_blank" href="https://tuki.shipfunk.com">' . $translation  . '</a>'
            );

            return array_merge( $pluginLinks, $links );
        }

        /**
         * Override cart-shipping.php template
         *
         * @param string $template
         * @param string $template_name
         * @param string $template_path
         */
        public function sf_woocommerce_locate_template( $template, $template_name, $template_path ) {
            if ( $template_name == 'cart/cart-shipping.php' ) {
                $options = get_option( 'woocommerce_shipfunk_settings' );

                if( $options['use_categories'] === 'yes' ){
                    global $woocommerce;
                    $_template = $template;

                    if ( ! $template_path ) $template_path = $woocommerce->template_url;

                    $plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/woocommerce/';
                    // Get the template from this plugin, if it exists
                    $template = file_exists( $plugin_path . $template_name ) ? $plugin_path . $template_name : false;
                    // Use theme
                    if ( ! $template ) $template = locate_template( array( $template_path . $template_name, $template_name ) );
                    // Use default template
                    if ( ! $template ) $template = $_template;
                }
            }

            return $template;
        }

        /**
         * Thank you- and View Order -page additions
         *
         * @param integer $order_id
         */
        public function sf_order_info( $order_id ) {
            $chosen_delivery = get_post_meta( $order_id, '_sf_chosen_delivery_option', true );

            if ( $chosen_delivery ) {
                $carriercode = $chosen_delivery['carriercode'];

                echo '<br>';
                echo '<div>';
                echo    '<header class="title"><h3>' . __( 'Shipping Method Info', 'shipfunk-woocommerce-shipping' ) . '</h3></header>';

                if ( substr( $carriercode, 0, 1 ) == '1' ) {
                    echo '<p><b>'. __('Store pickup', 'shipfunk-woocommerce-shipping') . '</b><br/>';
                    echo $chosen_delivery['productname'] . '<br/>';
                }

                echo    $chosen_delivery['info'];
                echo '</div>';
                echo '<br>';

                $chosen_pickup = get_post_meta( $order_id, '_sf_chosen_pickup', true );

                if ( !empty( $chosen_pickup ) ) {
                    echo '<div>';
                    echo    '<header class="title"><h3>' . __( 'Shipping Pickup Location', 'shipfunk-woocommerce-shipping' ) . '</h3></header>';
                    echo    '<address>';
                    echo        $chosen_pickup['pickup_name'] . '<br/>';
                    echo        $chosen_pickup['pickup_addr'] . '<br/>';
                    echo        $chosen_pickup['pickup_postal'] . ', ' . $chosen_pickup['pickup_city'] . ', ' . $chosen_pickup['pickup_country'] . '</br>';
                    echo    '</address>';
                    echo '</div>';
                }
            }
        }

        /**
         * Store shipping shipfunk gateway
         */
        public function sf_store_gateway( $shipping_shipfunk_gateway ) {
            $this->gateway_class = $shipping_shipfunk_gateway;
        }

        /**
         * Return initiated or new shipping shipfunk class
         */
        private function sf_init_gateway_class() {
            if ( ! is_object( $this->gateway_class ) ) {
                $this->gateway_class = new WC_Shipping_Shipfunk();
            }

            return $this->gateway_class;
        }

        /**
         * Adds phonenumber and email as required fields to shipping details on the checkout page
         *
         * @param  array $fields
         * @return  array
         */
        public function sf_checkout_fields( $fields ) {
            $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
            foreach ($payment_gateways as $gateway) {
                if ($gateway->id === 'kco' && $gateway->enabled) {
                    // Do not add email and phone when Klarna is active.
                    return $fields;
                }
            }

            $fields['shipping']['shipping_email'] = array(
                'label' => __( 'Email address', 'woocommerce' ),
                'required' => true,
                'class' => array('form-row-first'),
                'validate' => array('email'),
                'type' => 'email'
            );

            $fields['shipping']['shipping_phone'] = array(
                'label' => __( 'Phone', 'woocommerce' ),
                'required' => true,
                'class' => array('form-row-last'),
                'validate' => array('phone'),
                'clear' => true,
                'type' => 'tel'
            );

            // Re-order fields
            $order = array(
                "shipping_first_name",
                "shipping_last_name",
                "shipping_company",
                "shipping_email",
                "shipping_phone",
                "shipping_country",
                "shipping_address_1",
                "shipping_address_2",
                "shipping_city",
                "shipping_state",
                "shipping_postcode"
            );

            foreach ($order as $field) {
                $ordered_fields[$field] = $fields['shipping'][$field];
            }

            $fields['shipping'] = $ordered_fields;

            return $fields;
        }

        /**
         * Add phonenumber and email to Admin Order overview
         *
         * @param array $fields
         * @return array
         */
        public function sf_admin_shipping_fields( $fields ) {
            $fields['email'] = array(
                'label' => __('Email address', 'woocommerce'),
                'required' => true,
                'validate' => array('email'),
                'type' => 'email'
            );
            $fields['phone'] = array(
                'label' => __('Phone', 'woocommerce'),
                'required' => true,
                'validate' => array('phone'),
                'type' => 'phone'
            );
            return $fields;
        }

        /**
         * Load frontend scripts
         */
        public function sf_frontend_scripts() {
            if ( is_checkout() ) {
                wp_register_script( 'sf-frontend', plugins_url( 'js/frontend.js', __FILE__ ), array( 'jquery' ) );
                wp_enqueue_script( 'sf-frontend' );

                $temp_order_id =  WC_Shipfunk_API_Helper::get_temp_order_id();

                wp_localize_script('sf-frontend', 'get_pickups', array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'security' => wp_create_nonce( 'my-special-string' )
                ));
            }
        }

        /**
         * Get pickups ajax callback
         */
        public function get_pickups_callback() {
            check_ajax_referer( 'my-special-string', 'security' );
            $this->sf_init_gateway_class();

            $temp_order_id = WC_Shipfunk_API_Helper::get_temp_order_id();
            $order = new WC_Shipfunk_Order( $temp_order_id );

            $carriercode = $_POST['carriercode'];
            $language = strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) );
            $postcode = trim($_POST['postcode']);

            $country = null;
            if ( isset( $_POST['country'] ) ) {
                $country = $_POST['country'];
            }
            if( !$country ) {
                $country = WC()->customer->get_shipping_country() ?
                    WC()->customer->get_shipping_country() :
                    WC()->customer->get_billing_country();
                if( !$country ) {
                    $country = 'FI';
                }
            }

            $pickups = $order->get_pickups( $carriercode, $language, $postcode, $country );
            $rate_id = $_POST['rate_id'];
            $pickups->previous_selection = WC()->session->get( $rate_id . '_selected_pickup_point' );

            ob_clean();
            echo json_encode( $pickups );
            wp_die();
        }

        /**
          * Saves a selected pickup point.
          */
         public function save_selected_pickup_callback() {
            $key = $_POST['rate_id'] . '_selected_pickup_point';
            $selected_pickup_point = $_POST['option_value'];
            WC()->session->set( $key, $_POST['option_value'] );
            wp_die();
         }

        /**
         * Load admin side scripts
         */
        public function sf_admin_scripts() {
            wp_register_script( 'sf-admin', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ) );
            wp_enqueue_script( 'sf-admin' );

            wp_localize_script( 'sf-admin', 'sfa', array(
                'new_parcel' => __( "New parcel", 'shipfunk-woocommerce-shipping' ),
                'parcel' => __( "Parcel", 'shipfunk-woocommerce-shipping' ),
                'parcel_name' => __( "Parcel Name", 'shipfunk-woocommerce-shipping' ),
                'parcel_unique' => __( "Must be unique to this order. Can not be changed afterwards.", 'shipfunk-woocommerce-shipping' ),
                'content' => __( 'Content', 'shipfunk-woocommerce-shipping' ),
                'value' => __( 'Value', 'shipfunk-woocommerce-shipping' ),
                'optional' => __( '( Optional )', 'shipfunk-woocommerce-shipping' ),
                'dimensions' => __( 'Dimensions', 'shipfunk-woocommerce-shipping' ),
                'dimensions_unit' => get_option( 'woocommerce_dimension_unit' ),
                'dimensions_description' => __( 'depth x width x height', 'shipfunk-woocommerce-shipping' ),
                'weight' => __( 'Weight', 'shipfunk-woocommerce-shipping' ),
                'weight_unit' => get_option('woocommerce_weight_unit')
            ));

        }

        /**
         * Create shipfunk meta box
         */
        public function sf_add_meta_boxes( $post ) {
            add_meta_box(
                'sf_shipping_data',
                __( 'Shipping Information - Shipfunk', 'shipfunk-woocommerce-shipping' ),
                array( $this, 'sf_render_meta_boxes' ),
                'shop_order',
                'normal',
                'low'
            );
        }

        /**
         * Render meta boxes and retry failed api calls if any
         *
         * @param $post
         */
        public function sf_render_meta_boxes( $post ) {
            $order_id = $post->ID;

            // Check if order is made by plugin version < 1.3
            $chosen_delivery = get_post_meta( $order_id, '_sf_chosen_delivery_option', true );
            if ( $chosen_delivery ) {
                $carrier_code = $chosen_delivery['carriercode'];
            } else {
                $carrier_code = get_post_meta( $order_id, '_sf_carrier_code', true );
            }

            if ( ! $carrier_code ) {
                echo '<p>' . __( "The chosen shipping method is not Shipfunk's. No parcel settings, package cards or tracking codes available.", 'shipfunk-woocommerce-shipping') . '</p>';

                return;
            }

            if ( get_post_meta( $order_id, '_sf_create_order_failed' ) ) {
                $order = new WC_Shipfunk_Order( $order_id );
                $wc_shipping_shipfunk = $this->sf_init_gateway_class();
                $wc_shipping_shipfunk->create_order( $order_id );
                if ( get_post_meta( $order_id, '_sf_create_order_failed', true ) ) {
                    echo '<p>' . __( 'There was an error creating the shipment on Shipfunk. Refresh this page to make the API call again.', 'shipfunk-woocommerce-shipping' ) . '</p>';

                    return;
                }
            }

            // Orders made with plugin version < 1.3 needs to check
            // '_sf_set_order_status_called' and not '_sf_create_order_called'
            if ( ! get_post_meta( $order_id, '_sf_set_order_status_called', true ) ) {
                if ( ! get_post_meta( $order_id, '_sf_create_order_called', true ) ) {
                    echo '<p>' . __( 'Waiting for the order status to be set to "Processing". The shipment is not created on Shipfunk and the package cards are not yet available.', 'shipfunk-woocommerce-shipping' ) . '</p>';

                    return;
                }
            }

            if ( substr( $carrier_code, 0, 1 ) === '1' ) {
                echo '<p>' . __( 'Selected shipping method is a store pickup - shipping does not require package cards or tracking codes.', 'shipfunk-woocommerce-shipping' ) . '</p>';

                return;
            }

            $create_new_package_cards_error = get_transient( 'create_new_package_cards_error' );
            if ( $create_new_package_cards_error ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __( 'There was an error updating parcels. API responded an error: ', 'shipfunk-woocommerce-shipping' ) . ' "' . $create_new_package_cards_error . '"</p></div>';
                delete_transient( 'create_new_package_cards_error' );
            }

            $delete_parcels_error = get_transient( 'delete_parcels_error' );
            if ( $delete_parcels_error ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __( 'There was an error deleting parcels. API responded an error: ', 'shipfunk-woocommerce-shipping' ) . ' "' . $delete_parcels_error . '"</p></div>';
                delete_transient( 'delete_parcels_error' );
            }

            $order = new WC_Shipfunk_Order( $order_id );
            $parcels = $order->get_parcels();
            $package_cards_found =  false;

            if ( !isset( $parcels->response ) ) {
                echo '<p>' . __( 'No package cards or tracking codes - add parcel data.', 'shipfunk-woocommerce-shipping' ) . '</p>';
                return;
            } else {
                echo '<h2 style="padding-left:0px;"><b>' . __( 'Package Cards and Tracking Codes', 'shipfunk-woocommerce-shipping' ) . '</b></h2>';
                $shipping_company = $chosen_delivery['company_name'];
                $i = 1;
                foreach ( $parcels->response->parcels as $parcel ) {
                    if ( $parcel->package_cards->send || $parcel->package_cards->return ) {
                        echo '<div class="sf-cnt-box">';
                        echo '<div class="sf-cnt-box-header">' . __( 'Parcel', 'shipfunk-woocommerce-shipping' ) . ' ' . $i . '</div>';
                        echo '<div clasS="sf-cnt-box-wrap">';
                        $package_cards_found = true;
                    }
                    if ( $parcel->package_cards->send ) {
                        echo '<div class="sf-cnt-box-subheader">' . __( 'For sending', 'shipfunk-woocommerce-shipping' ) . '</div>';
                        $filename_send = "send_card_" . (string)$order_id . "_" . $parcel->code . ".pdf";
                        $tracking_url = 'https://shipfunkservices.com/tracking/tracked?tracking_code=' . $parcel->tracking_codes->send . '&company=' . $shipping_company;
                        echo '<div class="sf-cnt-box-ct"><a download="' . $filename_send . '" href="data:application/pdf;base64,' . (string)$parcel->package_cards->send . '">' . __( 'Package card', 'shipfunk-woocommerce-shipping' ) . '</a></div>';
                        echo '<div class="sf-cnt-box-ct"><a href="' . $tracking_url . '" target="_blank">' . $parcel->tracking_codes->send . '</a></div>';
                    } elseif ($package_cards_found) {
                        echo '<div class="sf-cnt-box-subheader">' . __( 'For sending', 'shipfunk-woocommerce-shipping' ) . '</div>';
                        echo '<div class="sf-cnt-box-ct">' . __( 'No package cards.', 'shipfunk-woocommerce-shipping' ) . '</a></div>';
                    }
                    if ( $parcel->package_cards->return ) {
                        echo '<div class="sf-cnt-box-subheader">' . __( 'For returning', 'shipfunk-woocommerce-shipping' ) . '</div>';
                        $filename_return = "return_card" . (string)$order_id . "_" . $parcel->code . ".pdf";
                        $tracking_url = 'https://shipfunkservices.com/tracking/tracked?tracking_code=' . $parcel->tracking_codes->return . '&company=' . $shipping_company;
                        echo '<div class="sf-cnt-box-ct"><a download="' . $filename_send . '" href="data:application/pdf;base64,' . (string)$parcel->package_cards->return . '">' . __( 'Package card', 'shipfunk-woocommerce-shipping' ) . '</a></div>';
                        echo '<div class="sf-cnt-box-ct"><a href="' . $tracking_url . '" target="_blank">' . $parcel->tracking_codes->return . '</a></div>';
                    } elseif ($package_cards_found) {
                        echo '<div class="sf-cnt-box-subheader">' . __( 'For returning', 'shipfunk-woocommerce-shipping' ) . '</div>';
                        echo '<div class="sf-cnt-box-ct">' . __( 'No package cards.', 'shipfunk-woocommerce-shipping' ) . '</a></div>';
                    }
                    if ( $package_cards_found ) {
                        echo '</div>';
                        echo '</div>';
                    }
                    $i++;
                }

                if ( ! $package_cards_found ) {
                    echo '<p>' . __( 'Package cards and tracking codes not retrieved.', 'shipfunk-woocommerce-shipping' ) . '</p>';
                }
            }

            if ( ! isset( $parcels->response->parcels ) ) {
                echo '<h2 style="padding-left:0px;"><b>' . __( 'Parcel Data', 'shipfunk-woocommerce-shipping' ) . '</b></h2>';
                echo '<div class="sf-parcels-wrap">';
                    echo '<input type="hidden" name="parcel_fields_enabled" id="parcel_fields_enabled" value="0"/>';
                    echo '<div id="sf-parcels">';
                    echo '</div>';
                    echo '<button type="button" class="button" id="sf_add_parcel">' . __('Add parcel', 'shipfunk-woocommerce-shipping') . '</button>';
                    echo '<button type="button" class="button" id="sf_remove_parcels">' . __('Remove selected parcels', 'shipfunk-woocommerce-shipping') . '</button>';
                echo '</div>';

            } else {

                echo '<h2 style="padding-left:0px;"><b>' . __( 'Parcel Data', 'shipfunk-woocommerce-shipping' ) . '</b></h2>';
                echo '<div class="sf-parcels-wrap">';
                echo    '<button type="button" class="button" id="sf_edit_parcels">' . __( 'Edit parcels', 'shipfunk-woocommerce-shipping' ) . '</button>';
                echo    '<input type="hidden" name="parcel_fields_enabled" id="parcel_fields_enabled" value="0"/>';
                echo    '<div id="sf-parcels">';

                $i = 0;

                // Render parcel form and data
                foreach ( $parcels->response->parcels as $parcel ) {
                    echo '<div class="sf-parcel">';
                    echo    '<h2><input type="checkbox" name="remove" disabled/><b>' . __( 'Parcel', 'shipfunk-woocommerce-shipping' ) . ' ' . ( $i + 1 ) . '</b></h2>';
                    echo    '<input type="hidden" name="parcels[' . $i . '][id]" id="parcels_id_' . $i . '" value="' . $parcel->id . '" disabled/>';
                    echo    '<input type="hidden" name="parcels[' . $i . '][code]" id="parcels_code_' . $i . '" value="' . $parcel->code . '" disabled/>';
                    echo    '<div class="sf-parcel-wrap">';
                    echo        '<table>';
                    echo            '<tr>';
                    echo                '<td><label for="parcels['.$i.'][contents]">' . __( 'Content', 'shipfunk-woocommerce-shipping' ) . '</label></td>';
                    echo                '<td>';
                    echo                    '<input type="text" name="parcels['.$i.'][contents]" id="parcels_contents_'.$i.'" value="' . $parcel->contents . '" disabled>';
                    echo                    '<span class="description"> ' . __( '( Optional )', 'shipfunk-woocommerce-shipping' ) . '</span>';
                    echo                '</td>';
                    echo            '</tr>';
                    echo            '<tr>';
                    echo                '<td><label for="parcels['.$i.'][monetary_value]">' . __( 'Value', 'shipfunk-woocommerce-shipping' ) . '</label></td>';
                    echo                '<td>';
                    echo                    '<input type="text" name="parcels['.$i.'][monetary_value]" id="parcels_monetary_value_'.$i.'" value="' . $parcel->value . '" disabled>';
                    echo                    '<span class="description"> ' . __( '( Optional )', 'shipfunk-woocommerce-shipping' ) . '</span>';
                    echo                '</td>';
                    echo            '</tr>';
                    echo            '<tr>';
                    echo                '<td><label for="parcels['.$i.'][dimensions][depth]">' . __( 'Dimensions', 'shipfunk-woocommerce-shipping' ) . '</label></td>';
                    echo                '<td>';

                    // Dimensions element can be missing...
                    if (isset($parcel->dimensions)) {
                        $depth = $parcel->dimensions->depth;
                        $width = $parcel->dimensions->width;
                        $height = $parcel->dimensions->height;
                        $unit = $parcel->dimensions->unit;
                    } else {
                        $depth = null;
                        $width = null;
                        $height = null;
                        $unit = 'cm';
                    }
                    echo                    '<input type="number" step="0.01" name="parcels['.$i.'][dimensions][depth]" id="parcels_dimensions_depth_'.$i.'" class="sf-dimension" value="' . $depth . '" disabled required> x ';
                    echo                    '<input type="number" step="0.01" name="parcels['.$i.'][dimensions][width]" id="parcels_dimensions__width_'.$i.'" class="sf-dimension" value="' . $width . '" disabled required> x ';
                    echo                    '<input type="number" step="0.01" name="parcels['.$i.'][dimensions][height]" id="parcels_dimensions__height_'.$i.'" class="sf-dimension" value="' . $height . '" disabled required> ';
                    echo                    '<input type="hidden" name="parcels['.$i.'][dimensions][unit]" id="parcels_dimensions_unit_' . $i . '" value="'.$unit.'" disabled/>';
                    echo                    '<span class="description"> ' . __( 'depth x width x height', 'shipfunk-woocommerce-shipping' ) . ' (' . $unit . ')</span>';
                    echo                '</td>';
                    echo            '</tr>';
                    echo            '<tr>';
                    echo                '<td><label for="parcels['.$i.'][weight][amount]">' . __( 'Weight', 'shipfunk-woocommerce-shipping' ) . '</label></td>';
                    echo                '<td>';
                    echo                    '<input type="number" step="0.01" name="parcels['.$i.'][weight][amount]" id="parcels_weight_amount_'.$i.'" class="sf-weight" value="' . $parcel->weight->amount . '" disabled required>';
                    echo                    $parcel->weight->unit;
                    echo                    '<input type="hidden" name="parcels['.$i.'][weight][unit]" id="parcels_weight_unit_'.$i.'" value="'.$parcel->weight->unit.'" disabled/>';
                    echo                '</td>';
                    echo            '</tr>';
                    echo        '</table>';
                    echo    '</div>';
                    echo '</div>';
                    $i++;
                }
                echo    '</div>';
                echo    '<button type="button" class="button" id="sf_add_parcel" disabled>' . __( 'Add parcel', 'shipfunk-woocommerce-shipping' ) . '</button>';
                echo    '<button type="button" class="button" id="sf_remove_parcels" disabled>' . __( 'Remove selected parcels', 'shipfunk-woocommerce-shipping' ) . '</button>';
                echo '</div>';
            }

            echo '<div class="wc-order-data-row wc-order-bulk-actions wc-order-data-row-toggle">';
            echo    '<p class="add-items">';
            $button_label = $package_cards_found ? __( 'Save order and update package cards and tracking codes', 'shipfunk-woocommerce-shipping' ) : __( 'Save order and retrieve package cards and tracking codes', 'shipfunk-woocommerce-shipping' );
            echo    '<button type="button" class="button button-primary" id="generate_packing_cards" style="margin:10px 15px;float:right">' . $button_label . '</button>';
            echo    '</p>';
            echo '</div>';
            echo '<div class="clear"></div>';

            ?>
                <style type="text/css">
                    .sf-parcels-wrap {
                        background-color: #f8f8f8;
                        border: 1px solid #DFDFDF;
                        padding: 10px;

                    }
                    .sf-parcel {
                        border: 1px solid #DFDFDF;
                        margin-bottom: 10px;
                    }
                    .sf-parcel-wrap {
                        padding: 10px;
                    }
                    .sf-parcel h2{
                        background-color: white;
                    }
                    .sf-dimension {
                        width: 80px;
                    }
                    .sf-weight {
                        width: 80px;
                    }
                    #sf_edit_parcels, #sf_add_parcel, #sf_remove_parcels {
                        margin-right: 10px !important;
                    }
                    #sf_edit_parcels {
                        margin-bottom: 10px !important;
                    }
                    .sf-cnt-box {
                        background-color: #f8f8f8;
                        border: 1px solid #DFDFDF;
                        display: inline-block;
                        margin-bottom: 10px;
                        margin-right: 10px;
                    }
                    .sf-cnt-box-wrap {
                        padding: 10px;
                    }
                    .sf-cnt-box-header {
                        padding: 10px;
                        background-color: white;
                        font-size: 14px;
                        font-weight: bold;
                    }
                    .sf-cnt-box-subheader {
                        font-weight: bold;
                        margin-top: 10px;
                        margin-bottom: 6px;
                    }
                    .sf-cnt-box-subheader:first-child {
                        margin-top: 0px;
                    }
                </style>

            <?php

        }


        /**
         * Save meta box data
         *
         * @param integer $order_id
         */
        public function sf_save_meta_box_data( $order_id ) {
            // Second conditions is for orders made with plugin version < 1.3
            if ( ! get_post_meta( $order_id, '_sf_carrier_code', true ) && ! get_post_meta( $order_id, '_sf_chosen_delivery_option', true ) ) {
                return;
            }
            if ( isset( $_POST['parcel_fields_enabled'] ) && $_POST['parcel_fields_enabled'] == true ){
                // Init gateway class
                $this->sf_init_gateway_class();

                $order = new WC_Shipfunk_Order( $order_id );
                $response = $order->get_parcels();
                if ( is_wp_error( $response ) && is_admin() ) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __( 'There was an error getting parcels.', 'shipfunk-woocommerce-shipping' ) . ' ' . $response->get_error_message() . '</p></div>';
                }
                $original_parcels = isset( $response->response->parcels ) ? $response->response->parcels : array();
                $parcels = isset( $_POST['parcels'] ) ? $_POST['parcels'] : array();

                $delete_parcel_ids = array();
                foreach ( $original_parcels as $original_parcel ) {
                    $found = false;
                    foreach ( $parcels as $parcel ) {
                        if ( $parcel['id'] == $original_parcel->id ) {
                            $found = true;
                            break;
                        }
                    }
                    if ( !$found ) {
                        $delete_parcel_ids[] = $original_parcel->id;
                    }
                }

                if ( count( $delete_parcel_ids ) > 0 ) {
                    $response = $order->delete_parcels( $delete_parcel_ids );
                    if ( is_wp_error( $response ) && is_admin() ) {
                        set_transient( 'delete_parcels_error', $response->get_error_message(), MINUTE_IN_SECONDS * 30 );
                    }
                }
                if ( count( $parcels ) > 0 ) {
                    $options = get_option( 'woocommerce_shipfunk_settings' );
                    $package_card_size = $options['package_card_size'];
                    $customer = WC_Shipfunk_API_Helper::parse_customer_details_from_order( $order_id );
                    $response = $order->create_new_package_cards( $parcels, $customer, $package_card_size );
                    if ( is_wp_error( $response ) && is_admin() ) {
                        set_transient( 'create_new_package_cards_error', $response->get_error_message(), MINUTE_IN_SECONDS * 30 );
                    }

                    $wc_order = new WC_Order( $order_id );
                    $wc_order->add_order_note( __( 'Package cards and tracking codes retrieved from Shipfunk.', 'shipfunk-woocommerce-shipping' ) );
                }
            }
        }

        /**
         * Adds tracking code to order emails
         */
        public function sf_custom_email_order_meta( $order, $sent_to_admin, $plain_text ){
            $tracking_code = get_post_meta( $order->get_id(), '_sf_tracking_code', true );
            if ( ! $tracking_code ) {
                return;
            }
            if ( $plain_text ) {
                echo __( 'Tracking code' ) . ': ' . $tracking_code;
            } else {
                echo '<h2>' . __( 'Tracking code' ) . '</h2>';
                echo '<p>' . $tracking_code . '</p>';
            }
        }

        /**
         * Adds custom order meta box action
         */
        public function sf_add_order_meta_box_action( $actions ) {
            global $theorder;

            $order_id = $theorder->get_id();
            $wc_shipping_shipfunk = $this->sf_init_gateway_class();

            if ( ! $wc_shipping_shipfunk->selected_shipping_method_is_shipfunk( $order_id ) ) {
                return $actions;
            }

            if ( get_post_meta( $order_id, '_sf_create_order_called', true ) ) {
                return $actions;
            }

            // Support for orders made with plugin version < 1.3
            if ( get_post_meta( $order_id, '_sf_set_order_status_called', true ) ) {
                return $actions;
            }

            $actions['sf_create_order'] = __( 'Create shipment on Shipfunk', 'shipfunk-woocommerce-shipping' );

            return $actions;
        }

        /**
         * Processes create order meta box action
         */
        public function sf_process_create_order_meta_box_action( $order ) {
            $order_id = $order->get_id();
            $wc_shipping_shipfunk = $this->sf_init_gateway_class();
            $wc_shipping_shipfunk->create_order( $order_id );
        }

        /**
         * Is Klarna allow separate shipping setting on
         *
         * @return boolean
         */
        public function klarna_allow_separate_shipping () {
            if ( ! class_exists( 'Klarna_Checkout_For_WooCommerce' ) ) {
                return false;
            }
            if ( ! $klarna_settings = get_option( 'woocommerce_kco_settings' ) ) {
                return false;
            }
            if ( ! isset( $klarna_settings['allow_separate_shipping'] ) ) {
                return false;
            }
            if ( $klarna_settings['allow_separate_shipping'] !== "yes" ) {
                return false;
            }

            return true;
        }

        /**
         * Show alert-message if klarna checkout is activated
         */
        public function sf_admin_notice() {
            $class = 'notice notice-error is-dismissible';
            $message = __( 'Klarna Checkout plugin conflicts with Shipfunk plugin, if different shipping addres option is enabled. Please disable this option from Klarna Checkout settings.', 'shipfunk-woocommerce-shipping' );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        }

        /**
         * Hide shipfunk shipping meta data (e.g. "calculated_price" value).
         */
        public function sf_hidden_order_itemmeta( $array ) {
            $array[] = '_sf_calculated_delivery_price';

            return $array;
        }

        /**
         * Create order.
         *
         * @param mixed $order_id
         */
        public function create_order( $order_id ) {
            $wc_shipping_shipfunk = $this->sf_init_gateway_class();
            $wc_shipping_shipfunk->create_order( $order_id );
        }
    }

    new WC_Shipfunk();
}
