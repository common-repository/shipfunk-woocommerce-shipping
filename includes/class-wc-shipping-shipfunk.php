<?php

// Prevent data leaks
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WF_Shipping_Shipfunk class.
 *
 * @extends WC_Shipping_Method
 */

class WC_Shipping_Shipfunk extends WC_Shipping_Method {

    /** Option name for the boxes */
    const BOXES_OPTION = 'woocommerce_shipfunk_boxes';

    /** Shipping method id */
     const SHIPPING_METHOD_ID = 'shipfunk';

     /** Shipfunk shipping method rate id prefix */
     const RATE_ID_PREFIX = 'sf_';

    /** array of master carton boxes */
    private $boxes;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = self::SHIPPING_METHOD_ID;
        $this->method_title = "Shipfunk";

        $this->init_form_fields();
        $this->init_settings();
        $this->load_boxes();

        // Define user set variables
        $this->enabled = $this->get_option( 'enabled' );
        $this->test_mode = 'yes' === $this->get_option( 'test_mode' );
        $this->secret_key = $this->test_mode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'live_secret_key' );
        $this->default_weight = ! empty( $this->get_option( 'default_weight' ) ) ? $this->get_option( 'default_weight' ) : 1.00;
        $this->default_dimensions = ! empty( $this->get_option( 'default_dimensions' ) ) ? $this->get_option( 'default_dimensions' ) : '10x10x2';
        $this->sort_by_price = 'yes' === $this->get_option( 'sort_by_price' );
        $this->use_categories = 'yes' === $this->get_option( 'use_categories' );
        $this->package_card_size = $this->get_option( 'package_card_size' );
        $this->manual_parcels = 'yes' === $this->get_option( 'manual_parcel_dimens' );
        $this->show_shipping_time_estimate = 'yes' === $this->get_option( 'show_shipping_time_estimate' );
        $this->discount_enabled_deliveries = $this->get_option( 'discount_enabled_deliveries' );
        $hideElement = $this->secret_key ? " hide" : "";
        $this->method_description = sprintf(
                                        wp_kses(
                                                __(
                                                    '<p class=%5$s><strong>Plugin usage requires Shipfunk account. <a target=%1$s href=%2$s>Register here for free.</a></strong> Visit <a target=%1$s href=%3$s>Shipfunk Docs</a> for getting started with our service.</strong></p><p>The Shipfunk extension obtains delivery options of various carriers dynamically from the Shipfunk API during checkout and also provides package cards and tracking codes from the API. You can manage your delivery options settings on the Shipfunk <a target=%1$s href=%4$s>Extranet</a>.</p>',
                                                    'shipfunk-woocommerce-shipping'
                                                    ),
                                                array( 'strong' => array(),
                                                        'p' => array('class' => array()),
                                                        'a' => array('href' => array(), 'target' => array()),
                                                        'br' => array()
                                                    )
                                        ),
                                        '_blank',
                                        esc_url('https://shipfunkservices.com/extranet/register'),
                                        esc_url('https://shipfunk.com/docs/index.php/ohjeet/'),
                                        esc_url('https://shipfunkservices.com/extranet/login'),
                                        'strong-note' . $hideElement
                                    );

        WC_Shipfunk_API::set_secret_key( $this->secret_key );
        WC_Shipfunk_API_Helper::set_boxes( $this->boxes );
        WC_Shipfunk_API_Helper::set_default_weight( $this->default_weight );
        WC_Shipfunk_API_Helper::set_default_dimensions( $this->default_dimensions );

        add_action( 'woocommerce_after_template_part', array( $this, 'review_order_shipping_pickup_location' ), 10, 4 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ), 10, 1 );

        // Admin actions
        if ( is_admin() ) {
            add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_sf_data_on_admin_order_meta'), 10, 1 );
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        do_action( 'wc_shipping_shipfunk_init', $this );
    }

    /**
     * Initialize the form fields that will be displayed on the Shipfunk admin page
     */
    public function init_form_fields() {
        $this->form_fields = include( untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/settings-shipfunk.php' );
    }

    /**
     * Overrides default admin_options
     */
    public function admin_options() {

        ?>

        <h2><?php echo $this->method_title; ?></h2>
        <p><?php echo $this->method_description; ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <!-- Box settings-->
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <?php _e( 'Box settings', 'shipfunk-woocommerce-shipping' ) ?>
                </th>
                <td class="forminp" id="sf_boxes">
                    <input type="hidden" name="woocommerce_sf_boxes" id="woocommerce_sf_boxes" value="1"/>
                    <table class="shippingrows widefat" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox"/></th>
                                <th><?php _e( 'Name', 'shipfunk-woocommerce-shipping' ) ?></th>
                                <th><?php echo __('Length', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_dimension_unit' ) . ')'; ?></th>
                                <th><?php echo __('Width', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_dimension_unit' ) . ')'; ?></th>
                                <th><?php echo __('Height', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_dimension_unit' ) . ')'; ?></th>
                                <th><?php echo __('Inner Length', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_dimension_unit' ) . ')'; ?></th>
                                <th><?php echo __('Inner Width', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_dimension_unit' ) . ')'; ?></th>
                                <th><?php echo __('Inner Height', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_dimension_unit' ) . ')'; ?></th>
                                <th><?php echo __('Weight of Box', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_weight_unit' ) . ')'; ?></th>
                                <th><?php echo __('Max Weight', 'shipfunk-woocommerce-shipping' ) . ' (' .get_option( 'woocommerce_weight_unit' ) . ')'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                if( !empty( $this->boxes ) ){
                                    foreach ( $this->boxes as $box ){
                                        echo '<tr class="alternate">';
                                        echo '<td class="check-column">
                                                    <input type="checkbox" name="select" style="margin-left:10px; margin-top:10px;"/>
                                                    <input type="hidden" name="woocommerce_sf_box[' . $box['id'] . '][id]" id="woocommerce_sf_box_id_' . $box['id'] . '" value="' . $box['id'] . '" required/>
                                                </td>
                                                <td>
                                                    <input type="text" name="woocommerce_sf_box[' . $box['id'] . '][name]" id="woocommerce_sf_box_name_' . $box['id'] . '" value="' . $box['name'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][length]" id="woocommerce_sf_box_length_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['length'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][width]" id="woocommerce_sf_box_width_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['width'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][height]" id="woocommerce_sf_box_height_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['height'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][inner_length]" id="woocommerce_sf_box_inner_length_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['inner_length'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][inner_width]" id="woocommerce_sf_box_inner_width_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['inner_width'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][inner_height]" id="woocommerce_sf_box_inner_height_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['inner_height'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][box_weight]" id="woocommerce_sf_box_box_weight_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['box_weight'] . '" required></input>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' . $box['id'] . '][max_weight]" id="woocommerce_sf_box_max_weight_' . $box['id'] . '" class="sf-input-width-short" value="' . $box['max_weight'] . '" required></input>
                                                </td>';
                                        echo '</tr>';
                                    }
                                }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">
                                    <!-- buttons -->
                                    <a href="#" class="sf-add button"><?php _e( 'Add box', 'shipfunk-woocommerce-shipping' ) ?></a>
                                    <a href="#" class="sf-remove button"><?php _e( 'Delete selected box(es)', 'shipfunk-woocommerce-shipping' ) ?></a>
                                </th>
                                <th colspan="8" style="color: #666; font-style: italic; font-weight: normal;">
                                    <?php _e( 'These box configurations are used by the Box Packer algorithm, which calculates cart products into the defined boxes ( = parcels). Products not fitting into boxes will be packed individually. This parcel data is used when we retrieve shipment options from Shipfunk. Parcel data ensures more refined delivery option results compared to a list of individual products. Automatic parcel creation also makes getting package cards and tracking codes more efficient.', 'shipfunk-woocommerce-shipping' ) ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        </table>

        <style type="text/css">
            .sf-input-width-short {
                width: 80px;
            }
        </style>

        <?php
    }

    /**
     * Process admin options
     */
    public function process_admin_options() {
        parent::process_admin_options();
        if ( isset( $_POST['woocommerce_sf_boxes'] ) ){
            $boxes = isset( $_POST['woocommerce_sf_box'] ) ? $_POST['woocommerce_sf_box'] : array();
            update_option( self::BOXES_OPTION, $boxes );
        }
        $this->load_boxes();
    }

    /**
     * Load configured boxes from the database and set them to boxes variable
     */
    private function load_boxes() {
        $this->boxes = array();
        $option = get_option( self::BOXES_OPTION );
        if ( $option ) {
            $this->boxes = array_filter( (array) $option );
        }
    }

    /**
     * Display shipping data on admin order
     *
     * @param WC_Order $order
     */
    public function display_sf_data_on_admin_order_meta( $order ){
        $chosen_delivery = get_post_meta( $order->get_id(), '_sf_chosen_delivery_option', true );

        if ( $chosen_delivery ) {
            echo '<div style="clear: both;">';
            echo '<h4>' . __('Shipping Pickup Details', 'shipfunk-woocommerce-shipping') .'</h4>';
            // Check from the carrier code is the chosen delivery a store pickup
            if ( substr( $chosen_delivery['carriercode'], 0, 1 ) === '1' ){
                echo '<p><b>'. __('Store pickup', 'shipfunk-woocommerce-shipping') . '</b><br/>';
                echo $chosen_delivery['productname'] . '<br/>';
                echo $chosen_delivery['info'] . '</p>';

            } else {
                $chosen_pickup = get_post_meta( $order->get_id(), '_sf_chosen_pickup', true );

                if ( ! empty( $chosen_pickup ) ){
                    echo '<p><strong>'. __('Address', 'shipfunk-woocommerce-shipping') . ':</strong><br>';
                    echo $chosen_pickup['pickup_name'] . '<br/>';
                    echo $chosen_pickup['pickup_addr'] . '<br/>';
                    echo $chosen_pickup['pickup_postal'] . ', ' . $chosen_pickup['pickup_city'] . ', ' . $chosen_pickup['pickup_country'] . '</p>';
                } else {
                    echo '<p><strong>'. __('Home delivery', 'shipfunk-woocommerce-shipping') . '</strong></p>';
                }
            }
            echo '</div>';
        }
    }


    /**
     * Calculate shipping
     *
     * @param array $package
     */
    public function calculate_shipping( $package = array() ) {

        global $woocommerce;
        $products = WC_Shipfunk_API_Helper::parse_products_from_items( $woocommerce->cart->get_cart() );
        $parcels = $this->manual_parcels ? array() : WC_Shipfunk_API_Helper::parse_parcels_from_products_and_boxes( $products );
        $customer = WC_Shipfunk_API_Helper::parse_customer();
        $tags = WC_Shipfunk_API_Helper::parse_tags_from_cart( $woocommerce->cart->get_cart() );
        $discounts = WC_Shipfunk_API_Helper::parse_discounts_from_cart( $woocommerce->cart, $this->discount_enabled_deliveries );

        if ( $this->test_mode && ! is_admin() ) {
            wc_add_notice( __( 'Shipfunk test mode is on. All data goes to your test environment and no data is sent to logistic companies.', 'shipfunk-woocommerce-shipping' ), 'notice' );
        }

        $temp_order_id = WC_Shipfunk_API_Helper::get_temp_order_id();
        $order = new WC_Shipfunk_Order( $temp_order_id );

        $response = $order->get_delivery_options( $products, $parcels, $customer, $tags, $discounts );

        if ( is_wp_error( $response ) && ! is_admin() ) {
            if ( $response->get_error_code() !== 'shipfunk_info' ) {
                wc_add_notice( $response->get_error_message(), 'error' );
            }
            WC()->session->set( 'sf_delivery_options', array() );
            WC()->session->set( 'sf_categories', array() );
            return;
        }

        // Add delivery options to one dimensional array with company name
        $delivery_options = array();
        foreach ($response->response as $carrier) {
            foreach ($carrier->Options as $option) {
                $delivery_option = json_decode( json_encode( $option ), true );
                $delivery_option['company_name'] = $carrier->Companyname;
                $delivery_options[$option->carriercode] = $delivery_option;
            }
        }
        WC()->session->set( 'sf_delivery_options', $delivery_options );

        // Sort delivery options by price if sorting is on
        if ( $this->sort_by_price ) {
            usort( $delivery_options, function( $a, $b ){
                if (floatval($a['customer_price']) == floatval($b['customer_price'])) return 0;
                return (floatval($a['customer_price']) < floatval($b['customer_price'])) ? -1 : 1;
            });
        }

        // Save categories to session
        if ( $this->use_categories ) {
            $categories = array();
            foreach ( $delivery_options as $delivery_option) {
                if( ! empty( $delivery_option['category'] ) ) {
                    $categories[$delivery_option['category']][] = $delivery_option['carriercode'];
                }
            }
            WC()->session->set( 'sf_categories', $categories );
        }

        // Loop all the delivery options, parse them and call add_rate
        foreach ($delivery_options as $delivery_option) {
            // Parse label
            if (isset($delivery_option['custom_name']) && $delivery_option['custom_name']) {
                $label = $delivery_option['custom_name'];
            } else {
                $label = $delivery_option['productname'];
                if ( substr( $delivery_option['carriercode'], 0, 1) !== '1' ) {
                    $label .= ' (' . $delivery_option['company_name'] . ')';
                }
            }

            // Parse delivery time
            if ( $this->show_shipping_time_estimate ) {
                // Parse delivery time to label
                if ( $delivery_option['delivtime'] == '0' ) {
                    $days = '';
                } elseif ( $delivery_option['delivtime'] == '1' ) {
                    $days = ' - ' . $delivery_option['delivtime'] . ' ' . __( 'day', 'shipfunk-woocommerce-shipping' );
                } else {
                    $days = ' - ' . $delivery_option['delivtime'] . ' ' . __( 'days', 'shipfunk-woocommerce-shipping' );
                }
                $label .= $days;
            }

            $rate = array(
                'id' => self::RATE_ID_PREFIX . $delivery_option['carriercode'],
                'label' => $label,
                'cost' => $delivery_option['customer_price'],
                'meta_data' => array(
                    '_sf_calculated_delivery_price' => $delivery_option['calculated_price']
                )
            );

            $this->add_rate( $rate );
        }
    }

    /**
     * Update order meta and delete cached data
     *
     * @param integer $order_id
     */
    public function update_order_meta( $order_id ) {
        if ( $this->selected_shipping_method_is_shipfunk() ) {
            $rate_id = $_POST['shipping_method'][0];
            $carrier_code = substr($rate_id, strlen(self::RATE_ID_PREFIX));
            $temp_order_id = WC_Shipfunk_API_Helper::get_temp_order_id();
            $delivery_options = WC()->session->get( 'sf_delivery_options' );
            $selected_delivery = $delivery_options[$carrier_code];

            update_post_meta( $order_id, '_sf_carrier_code', $carrier_code );
            update_post_meta( $order_id, '_sf_temp_order_id', $temp_order_id );
            update_post_meta( $order_id, '_sf_chosen_delivery_option', $selected_delivery );
            WC_Shipfunk_API_Helper::delete_temp_order_id();

            // Get calculated delivery price from rate meta data
            $calculated_delivery_price = 0;
            $rates_in_session = WC()->session->get('shipping_for_package_0')['rates'];
            foreach ( $rates_in_session as $rate ){
                if ( $rate->id === $rate_id ) {
                    $meta_data = $rate->get_meta_data();
                    if (isset($meta_data['_sf_calculated_delivery_price'])) {
                        $calculated_delivery_price = $meta_data['_sf_calculated_delivery_price'];
                    }
                }
            }
            update_post_meta( $order_id, '_sf_calculated_delivery_price', $calculated_delivery_price );

            if ( $selected_delivery['haspickups'] ) {
                $pickup_point = WC()->session->get( $rate_id . '_selected_pickup_point' );
                if ( $pickup_point ) {
                    $temp = explode( '|', $pickup_point );
                    $chosen_pickup['pickup_id'] = $temp[0];
                    $chosen_pickup['pickup_name'] = $temp[1];
                    $chosen_pickup['pickup_addr'] = $temp[2];
                    $chosen_pickup['pickup_postal'] = $temp[3];
                    $chosen_pickup['pickup_city'] = $temp[4];
                    $chosen_pickup['pickup_country'] = $temp[5];
                } else {
                    // If selected pickup is empty, pick the first one.
                    // The session variable is empty if the customer did
                    // not change the dropdown selection.
                    $chosen_pickup = $selected_delivery['haspickups'][0];
                }
                // Save pickup data to order meta
                update_post_meta( $order_id, '_sf_chosen_pickup', $chosen_pickup );
            }

            WC()->session->set( $rate_id . '_selected_pickup_point', false );
        }
    }

    /**
     * Creates an order.
     *
     * @param $order_id string
     */
    public function create_order( $order_id ) {
        if ( ! $this->selected_shipping_method_is_shipfunk( $order_id ) ) {
            return;
        }
        // "woocommerce_order_status_processing" action might happen multiple times.
        // Check if this api call has been already made.
        if ( get_post_meta( $order_id, '_sf_create_order_called' ) ) {
            return;
        }

        $customer = WC_Shipfunk_API_Helper::parse_customer_details_from_order( $order_id );
        $order = wc_get_order( $order_id );

        $coupons = $order->get_coupon_codes();
        $discount_amount = WC_Shipfunk_API_Helper::parse_discount_amount_from_coupons( $coupons );
        $discounts = WC_Shipfunk_API_Helper::format_discounts_for_API( $discount_amount, $this->discount_enabled_deliveries );

        $items = $order->get_items();
        $products = WC_Shipfunk_API_Helper::parse_products_from_items( $items );
        $parcels = $this->manual_parcels ? array() : WC_Shipfunk_API_Helper::parse_parcels_from_products_and_boxes( $products );

        $pickup_id = null;
        $pickup = get_post_meta( $order_id, '_sf_chosen_pickup', true );

        $shipping_method = [];

        if ($pickup) {
            $shipping_method['pickupid'] = $pickup['pickup_id'];
        }

        $carrier_code = get_post_meta( $order_id, '_sf_carrier_code', true );
        $calculated_price = get_post_meta( $order_id, '_sf_calculated_delivery_price', true );
        if ( ! $carrier_code ) {
            // Support for orders made with plugin version < 1.3
            $delivery_option_data = get_post_meta( $order_id, '_sf_chosen_delivery_option', true );
            $carrier_code = $delivery_option_data['carriercode'];
            $calculated_price = $delivery_option_data['calculated_price'];
        }

        $shipping_method['carriercode'] = $carrier_code;
        $shipping_method['customer_price'] = $order->get_shipping_total();
        $shipping_method['calculated_price'] = $calculated_price;

        $order = new WC_Shipfunk_Order( $order_id );
        $response = $order->create_order( $products, $parcels, $customer, $shipping_method, $discounts );

        if ( is_wp_error( $response ) ) {
            update_post_meta( $order_id, '_sf_create_order_failed', 1 );
            return;
        } else {
            update_post_meta( $order_id, '_sf_create_order_failed', 0 );
        }

        update_post_meta( $order_id, '_sf_create_order_called', 1 );

        $wc_order = new WC_Order( $order_id );
        $wc_order->add_order_note( __( 'Informed Shipfunk the order has been placed.', 'shipfunk-woocommerce-shipping' ) );
    }

    /**
     * Checks if selected shipping method is shipfunks
     *
     * @param integer $order_id
     *
     * @return boolean
     */
    public function selected_shipping_method_is_shipfunk( $order_id = null ) {
        if ( $order_id && get_post_meta( $order_id, '_sf_carrier_code', true ) ) {
            // Order has shipfunk delivery option carrier code in metadata
            return true;
        }

        if ( $order_id && get_post_meta( $order_id, '_sf_chosen_delivery_option', true ) ) {
            // Order has shipfunk delivery option data in metadata (version < 1.3)
            return true;
        }

        if ( empty( $_POST['shipping_method'] ) ) {
            return false;
        }

        foreach ( $_POST['shipping_method'] as $shipping_method ) {
            $rate_id = explode( "_", $shipping_method );
            if ( $rate_id[0] === "sf" ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show shipment info and pickups if Shipfunk shipping method is selected
     *
     * @param string $template_name
     * @param string $template_path
     * @param string located
     * @param array  $args
     */
    public function review_order_shipping_pickup_location( $template_name, $template_path, $located, $args ) {
        if ( $template_name !== 'cart/cart-shipping.php') {
            return;
        }
        if ( ! is_checkout() ) {
            return;
        }
        if ( ! isset( $_REQUEST['wc-ajax'] ) ) {
            return;
        }
        $valid_ajax_events = array(
            'update_order_review',
            'kco_wc_update_shipping', // Klarna event
            'kco_wc_iframe_shipping_address_change', // Klarna event
            'kco_wc_update_klarna_order' // Klarna event
        );
        if ( ! in_array( $_REQUEST['wc-ajax'], $valid_ajax_events ) ) {
            return;
        }

        $rate_id = explode( "_", $args['chosen_method'] );
        $delivery_options = WC()->session->get( 'sf_delivery_options' );
        if ( $rate_id[0] !== 'sf'|| ! $delivery_options || empty( $rate_id[1] ) ) {
            return;
        }

        // Get data of chosen delivery
        $chosen_delivery = $delivery_options[$rate_id[1]];

        if ( ! empty( $chosen_delivery['info'] ) ) {
            echo '<tr class="sf-shipping-info">';
                echo '<th colspan="1"> ' . __( 'Shipping Method Info', 'shipfunk-woocommerce-shipping' ) . '</th>';
                echo '<td class="">';
                    echo $chosen_delivery['info'];
                echo '</td>';
            echo '</tr>';
        }

        if ( ! empty( $chosen_delivery['haspickups'] ) ) {
            global $woocommerce;
            $postcode = $woocommerce->customer->get_shipping_postcode();
            echo '<tr class="sf-pickup-location-postcode">';
            echo    '<th colspan="1">'. __( 'Pickup Locations by Postcode', 'shipfunk-woocommerce-shipping' ) . '</th>';
            echo    '<td>';
            echo        '<input type="text" id="sf_pickup_location_postcode" placeholder value=' . $postcode . '></input>';
            echo        '<button type="button" id="sf_reload_pickups">' . __( 'Reload pickups', 'shipfunk-woocommerce-shipping' ) . '</button>';
            echo        '<div id="sf_no_pickups" style="display:none;">' . __( 'No pickups found', 'shipfunk-woocommerce-shipping') . '</div>';
            echo    '</td>';
            echo '</tr>';
            echo '<tr class="sf-pickup-location">';
            echo    '<th colspan="1">' . __( 'Choose Pickup Location', 'shipfunk-woocommerce-shipping' ) . '</th>';
            echo    '<td width="100%">';
            echo        '<select name="sf_pickup" id="sf_pickup_select" style="width:inherit">';

            $previous_selection = WC()->session->get( $args['chosen_method'] . '_selected_pickup_point', '' );
            foreach ( $chosen_delivery['haspickups'] as $pickup ) {
                $option_value = $pickup['pickup_id'] . '|' . $pickup['pickup_name'] . '|' . $pickup['pickup_addr'] . '|' . $pickup['pickup_postal'] . '|' . $pickup['pickup_city'] . '|' . $pickup['pickup_country'];

                $selected = '';
                if ( $previous_selection === $option_value ) {
                    $selected = 'selected';
                }

                echo    '<option value="' . $option_value . '" ' . $selected .'>';
                echo        $pickup['pickup_name'] . ' - ' . $pickup['pickup_addr'] . ', ' . $pickup['pickup_postal'] . ', ' . $pickup['pickup_city'];
                echo    '</option>';
            }

            echo        '</select>';
            echo        '<div id="sf_pickups_reloaded" style="display:none;">' . __( 'Pickups reloaded', 'shipfunk-woocommerce-shipping') . '</div>';
            echo    '</td>';
            echo '</tr>';
        }
    }
}
