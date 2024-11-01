<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper functions
 */
class WC_Shipfunk_API_Helper {

    /**
     * @var array
     */
    private static $boxes;

    /**
     * @var float
     */
    private static $default_weight;

    /**
     * @var string
     */
    private static $default_dimensions;

    /**
     * Set box
     *
     * @param array $boxes
     */
    public static function set_boxes( $boxes ) {
        self::$boxes = $boxes;
    }

    /**
     * Get box
     *
     * @param array $boxes
     */
    public static function get_boxes( ) {
        return self::$boxes;
    }

    /**
     * Set temporary order id
     *
     * @param mixed $temp_order_id
     */
    public static function set_temp_order_id( $temp_order_id ) {
        WC()->session->set( 'sf_temp_order_id', $temp_order_id );
    }

    /**
     * Generate unigue shipfunk order id for every order.
     * We would have liked to use the real order number, but it is not yet generated when we need it in the api calls.
     *
     * @return string
     */
    public static function generate_temp_order_id() {
        return uniqid() . substr( md5( microtime() ), rand( 0, 26 ), 2 );
    }

    /**
     * Get temporary order id or create it if not exist
     *
     * @return string
     */
    public static function get_temp_order_id() {
        $temp_order_id = WC()->session->get( 'sf_temp_order_id' );
        if ( empty( $temp_order_id ) ){
            $temp_order_id = self::generate_temp_order_id();
            WC()->session->set( 'sf_temp_order_id', $temp_order_id );
        }

        return $temp_order_id;
    }

    /**
     * Delete temporary order id
     */
    public static function delete_temp_order_id() {
        WC()->session->set( 'sf_temp_order_id', null );
    }

    /**
     * Set default weight
     *
     * @param float $default_weight
     */
    public static function set_default_weight( $default_weight ) {
        self::$default_weight = $default_weight;
    }

    /**
     * Get default weight
     *
     * @return float
     */
    public static function get_default_weight() {
        return self::$default_weight;
    }

    /**
     * Set default dimensions
     *
     * @param string $default_dimensions
     */
    public static function set_default_dimensions( $default_dimensions ) {
        self::$default_dimensions = $default_dimensions;
    }

    /**
     * Get default dimensions
     *
     * @return string
     */
    public static function get_default_dimensions() {
        return self::$default_dimensions;
    }

    /**
     * Parse discount amount from cart
     *
     * @param  $cart The cart
     * @param  string $discount_enabled_deliveries
     */
    public static function parse_discounts_from_cart( $cart, $discount_enabled_deliveries ) {
        $coupons = $cart->get_applied_coupons();
        $discountAmount = self::parse_discount_amount_from_coupons( $coupons );
        $discounts = self::format_discounts_for_API( $discountAmount, $discount_enabled_deliveries );

        return $discounts;
    }

    /**
     * Parse discounts from coupons
     *
     * @param array[string]
     *
     * @return discount double 100.00 or 0.00 free/not free for now.
     */
    public static function parse_discount_amount_from_coupons( $coupons ) {

        $discountAmount = 0.00;
        foreach ( $coupons as $coupon ) {
            $WC_coupon = new WC_Coupon( $coupon );
            if ( $WC_coupon->get_free_shipping() ) {
                $discountAmount = 100.00;
            }
        }

        return $discountAmount;
    }

    /**
     * Format discount amount for API call
     *
     * @param decimal $discountAmount The discount amount
     *
     * @return array discounts for different shipping types
     */
    public static function format_discounts_for_API( $discountAmount, $discount_enabled_deliveries ) {
        if ( $discountAmount === 0.00 ) {
            return array();
        }
        $discounts = array('type' => 0,
            'all'       => $discount_enabled_deliveries === 'all' ? $discountAmount : 0,
            'home'      => $discount_enabled_deliveries === 'home' ? $discountAmount : 0,
            'express'   => $discount_enabled_deliveries === 'express' ? $discountAmount : 0,
            'normal'    => $discount_enabled_deliveries === 'normal' ? $discountAmount : 0
        );

        return $discounts;
    }

    /**
     * Parse products from cart
     *
     * @param array $cart
     *
     * @return array
     */
    public static function parse_products_from_items( $cart ) {
        $products = array();
        foreach( $cart as $key => $item ) {
            $quantity = $item['quantity'];
            if ( is_array( $item ) ) {
                $cart_product = $item['data'];
            } else {
                $cart_product = $item->get_product();
            }
            // Check that product is not virtual
            if ( ! $cart_product->is_virtual() ) {
                $products[] = self::format_product_for_api($cart_product, $quantity);
            }
        }

        return $products;
    }

    /**
     * Format product for API
     *
     * @param WC_Product $product
     * @param int        $quantity
     *
     * @return array
     */
    public static function format_product_for_api( $product, $quantity ) {
        $formatted_product = array();
        $formatted_product['name'] = htmlspecialchars( $product->get_name(), ENT_XML1);
        $formatted_product['code'] = htmlspecialchars( $product->get_sku(), ENT_XML1);
        $weight = $product->get_weight();

        if ( empty( $weight ) ) {
            $weight = self::get_default_weight();
        }

        $formatted_product['weight']['amount'] = $weight;
        $formatted_product['weight']['unit'] = get_option( 'woocommerce_weight_unit' );

        $default_dimensions = self::get_default_dimensions();
        $dimensions = explode('x', $default_dimensions );
        $default_length = round( floatval( trim( $dimensions[0] ) ), 2);
        $default_height = round( floatval( trim( $dimensions[1] ) ), 2);
        $default_width = round( floatval( trim( $dimensions[2] ) ), 2);

        $formatted_product['dimensions']['unit'] = get_option( 'woocommerce_dimension_unit' );
        $formatted_product['dimensions']['depth'] = ! empty( $product->get_length() ) ? $product->get_length() : $default_length;
        $formatted_product['dimensions']['height'] = ! empty( $product->get_height() ) ? $product->get_height() : $default_height;
        $formatted_product['dimensions']['width'] = ! empty( $product->get_width() ) ? $product->get_width() : $default_width;
        $formatted_product['monetary_value'] = wc_get_price_including_tax($product);
        $formatted_product['amount'] = $quantity;

        return $formatted_product;
    }


    /**
     * Parse parcels from products and boxes
     *
     * Calculates cart items to boxes (parcels)
     * We save the calculation to cache, and we return that if basket is the same as previous call
     *
     * @param array $products
     */
    public static function parse_parcels_from_products_and_boxes( $products ) {
        // Check if products are not changed, return cached parcels if found from session

        if ( ! class_exists( 'WC_Boxpack' ) ) {
            include_once 'box-packer/class-wc-boxpack.php';
        }

        $boxpack = new WC_Boxpack();

        // Let's define configured boxes
        foreach (self::get_boxes() as $box) {
            $newbox = $boxpack->add_box( $box['length'], $box['width'], $box['height'], $box['box_weight'] );
            $newbox->set_id( $box['name'] );
            $newbox->set_inner_dimensions( $box['inner_length'], $box['inner_width'], $box['inner_height'] );
            $newbox->set_max_weight( $box['max_weight'] );

        }

        // Let's add items
        foreach($products as $product) {
            $boxpack->add_item(
                $product['dimensions']['depth'],
                $product['dimensions']['width'],
                $product['dimensions']['height'],
                $product['weight']['amount'],
                $product['monetary_value']
            );
        }

        $boxpack->pack();
        $box_packages = $boxpack->get_packages();
        $parcels = array();

        $i = 1;
        foreach ( $box_packages as $box_package ) {
            $parcel['code'] = strval($i);
            $parcel['weight']['unit'] = get_option( 'woocommerce_weight_unit' );
            $parcel['weight']['amount'] = $box_package->weight;
            $parcel['monetary_value'] = $box_package->value;
            $parcel['dimensions']['unit'] = get_option( 'woocommerce_dimension_unit' );
            $parcel['dimensions']['width'] = $box_package->width;
            $parcel['dimensions']['depth'] = $box_package->length;
            $parcel['dimensions']['height'] = $box_package->height;

            $parcels[] = $parcel;
            $i++;
        }

        // Save box_packages to session

        return $parcels;

    }

    /**
     * Parse shipping tags from cart
     *
     * @param array $tags
     */
    public static function parse_tags_from_cart( $cart ) {
        $tags = array();
        foreach( $cart as $item => $values ) {
            $product = $values['data'];
            // Check that product is not virtual
            if ( ! $product->is_virtual( ) ) {
                // Get parent product if cart product is variaton
                if ( $product->is_type( 'variation' ) ) {
                    $id = $product->get_parent_id( );
                    $product = wc_get_product( $id );
                }

                $shipfunk_attribute = $product->get_attribute( 'shipfunk-tags' );
                $product_tags = explode( '|', $shipfunk_attribute );
                foreach ( $product_tags as $product_tag ) {
                    $tag = trim( $product_tag );
                    if ( !empty( $tag ) && !in_array( $tag, $tags ) ) {
                        $tags[] = $tag;
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Checks if we all required fields are set for getDeliveryOptions api call
     * Some required fields are replaced with temporary value to get the call made
     * Temporary values will be replaced eventually.
     *
     * @return array
     */
    public static function parse_customer() {
        // Temp customer details
        $customer = array(
            'first_name' => 'wc-temp',
            'last_name' => 'wc-temp',
            'company' => '',
            'street_address' => 'wc-temp',
            'postal_code' => '00100', // Default postcode
            'city' => 'wc-temp',
            'country' => 'FI', // Default country
            'phone' => '040 0000000',
            'email' => 'wc-temp@example.com'
        );

        // Get shipping details from WC_Customer object
        if ( !empty( WC()->customer->get_shipping_country() ) )
            $customer['country'] = htmlspecialchars( WC()->customer->get_shipping_country(), ENT_XML1 );

        if ( !empty( WC()->customer->get_shipping_postcode() ) )
            $customer['postal_code'] = htmlspecialchars(WC()->customer->get_shipping_postcode(), ENT_XML1 );

        if ( !empty( WC()->customer->get_shipping_city() ) )
            $customer['city'] = htmlspecialchars(WC()->customer->get_shipping_city(), ENT_XML1 );

        if ( !empty( WC()->customer->get_shipping_address() ) ){
            $customer['street_address'] = htmlspecialchars(WC()->customer->get_shipping_address(), ENT_XML1 );

            if ( !empty( WC()->customer->get_shipping_address_2() ) ){
                $customer['street_address'] .= ', ' . htmlspecialchars(WC()->customer->get_shipping_address_2(), ENT_XML1 );
            }
        }

        // Get data from post variable
        if ( isset( $_POST['post_data'] ) ){
            $post_data = explode( '&', $_POST['post_data'] );
            $post_data_fields = array();

            foreach ($post_data as $value) {
                $row = explode( '=', $value );
                $post_data_fields[$row[0]] = urldecode( $row[1] );
            }

            // Check is shipping to billing or shipping address
            if ( !empty( $post_data_fields['ship_to_different_address'] ) ){
                // Get shipping fields data
                if ( !empty( $post_data_fields['shipping_first_name'] ) )
                    $customer['first_name'] = htmlspecialchars($post_data_fields['shipping_first_name'], ENT_XML1 );
                if ( !empty( $post_data_fields['shipping_last_name'] ) )
                    $customer['last_name'] = htmlspecialchars($post_data_fields['shipping_last_name'], ENT_XML1 );
                if ( !empty( $post_data_fields['shipping_company'] ) )
                    $customer['company'] = htmlspecialchars($post_data_fields['shipping_company'], ENT_XML1 );
                if ( !empty( $post_data_fields['shipping_phone'] ) )
                    $customer['phone'] = htmlspecialchars($post_data_fields['shipping_phone'], ENT_XML1 );
                if ( !empty( $post_data_fields['shipping_email'] ) )
                    $customer['email'] = htmlspecialchars($post_data_fields['shipping_email'], ENT_XML1 );
            } else {
                // Get billing fields data
                if ( !empty( $post_data_fields['billing_first_name'] ) )
                    $customer['first_name'] = htmlspecialchars($post_data_fields['billing_first_name'], ENT_XML1 );
                if ( !empty( $post_data_fields['billing_last_name'] ) )
                    $customer['last_name'] = htmlspecialchars($post_data_fields['billing_last_name'], ENT_XML1 );
                if ( !empty( $post_data_fields['billing_company'] ) )
                    $customer['company'] = htmlspecialchars($post_data_fields['billing_company'], ENT_XML1 );
                if ( !empty( $post_data_fields['billing_phone'] ) )
                    $customer['phone'] = htmlspecialchars($post_data_fields['billing_phone'], ENT_XML1 );
                if ( !empty( $post_data_fields['billing_email'] ) )
                    $customer['email'] = htmlspecialchars($post_data_fields['billing_email'], ENT_XML1 );
            }
        }

        return $customer;
    }

    /**
     * Returns customer details suitable for API request from order
     *
     * @param mixed $order_id
     * @return array
     */
    public static function parse_customer_details_from_order( $order_id ) {
        $order = new WC_Order( $order_id );

        $customer['first_name'] = $order->get_shipping_first_name();
        $customer['last_name'] = $order->get_shipping_last_name();
        $customer['street_address'] = $order->get_shipping_address_1();
        if ( !empty( $order->get_shipping_address_2() ) ) {
            $customer['street_address'] .= ', ' . $order->get_shipping_address_2();
        }
        $customer['postal_code'] = $order->get_shipping_postcode();
        $customer['city'] = $order->get_shipping_city();
        $customer['country'] = $order->get_shipping_country();
        $customer['company'] = $order->get_shipping_company();
        $customer['phone'] = get_post_meta( $order_id, '_shipping_phone', true );
        $customer['email'] = get_post_meta( $order_id, '_shipping_email', true );

        // Klarna support. Phone and email are not found from shipping
        // fields with Klarna.
        if ( ! $customer['phone'] ) {
            $customer['phone'] = $order->get_billing_phone();
        }
        if ( ! $customer['email'] ) {
            $customer['email'] = $order->get_billing_email();
        }

        return $customer;
    }
}
