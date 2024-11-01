<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Communicates with Shipfunk API.
 */
class WC_Shipfunk_Order {

    /**
     * @var string
     */
    private $order_number;

    /**
     * Constructor
     *
     * @param string $order_number
     */
    public function __construct( $order_number ) {
        $this->order_number = $order_number;
    }

    /**
     * Set order number
     *
     * @param string $order_number
     */
    public function set_order_number( $order_number ) {
        $this->order_number = $order_number;
    }

    /**
     * Get order number
     *
     * @return string
     */
    public function get_order_number() {
        return $this->order_number;
    }

    /**
     * Get delivery options
     *
     * @param array      $products
     * @param array      $parcels
     * @param array      $customer
     * @param array|null $tags
     *
     * @return stdClass
     */
    public function get_delivery_options( $products, $parcels, $customer, $tags = array(), $discounts = array() ) {
        $endpoint = 'get_delivery_options/true/json/json/' . $this->get_order_number();

        $data['order']['language'] = strtoupper( substr( get_bloginfo( 'language' ), 0, 2) );
        $data['order']['tags'] = $tags;
        $data['order']['products'] = $products;
        $data['order']['parcels'] = $parcels;
        $data['order']['discounts'] = $discounts;
        // Warehouse lead time should have been true on default
        $data['order']['additions']['warehouse_leadtime'] = 1;
        $data['customer'] = $customer;
        // Check request and response cache
        $cached_request_data = WC()->session->get( 'sf_shipping_request_data' );
        $cached_response = WC()->session->get( 'sf_shipping_response' );
        if( $cached_request_data === $data && $cached_response ) {
            return $cached_response;
        }

        $encoded_data = array( 'sf_get_delivery_options' => json_encode( array( 'query' => $data ) ) );

        $response = WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );

        WC()->session->set( 'sf_shipping_request_data', $data );
        WC()->session->set( 'sf_shipping_response', $response );

        return $response;
    }

    /**
     * Get pickup points
     *
     * @param string $carriercode
     * @param string $language
     * @param string $postcode
     * @param string $country
     *
     * @return stdClass
     */
    public function get_pickups( $carriercode, $language, $postcode, $country ) {
        $endpoint = 'get_pickups/true/json/json/' . $this->get_order_number();

        $data['order']['carriercode'] = $carriercode;
        $data['order']['language'] = $language;
        $data['customer']['postal_code'] = $postcode;
        $data['customer']['country'] = $country;

        $encoded_data = array( 'sf_get_pickups' => json_encode( array( 'query' => $data ) ) );

        return WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );
    }

    /**
     * Set customer details
     *
     * @param array $customer
     *
     * @return stdClass
     */
    public function set_customer_details( $customer ) {
        $endpoint = 'set_customer_details/true/json/json/' . $this->get_order_number();
        $encoded_data = array( 'sf_set_customer_details' => json_encode( array( 'query' => array( 'customer' => $customer ) ) ) );

        return WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );
    }

    /**
     * Get parcels
     *
     * @return stdClass
     */
    public function get_parcels() {
        $endpoint = 'get_parcels/true/rest/json/' . $this->get_order_number();

        return WC_Shipfunk_API::request( $endpoint );
    }

    /**
     * Set parcels
     *
     * @param array $parcels
     *
     * @return stdClass
     */
    public function edit_parcels( $parcels ) {
        $endpoint = 'edit_parcels/true/json/json/' . $this->get_order_number();
        $data = array( 'query' => array( 'order' => array( 'parcels' => $parcels ) ) );
        $encoded_data = array( 'sf_edit_parcels' => json_encode( $data ) );

        return WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );
    }

    /**
     * Delete parcels
     *
     * @param array $ids
     *
     * @return stdClass
     */
    public function delete_parcels( $ids ) {
        $endpoint = 'delete_parcels/true/json/json/' . $this->get_order_number();

        foreach ($ids as $id) {
            $data['order']['parcels'][] = array( 'id' => $id );
        }

        $encoded_data = array( 'sf_delete_parcels' => json_encode( array( 'query' => $data ) ) );

        return WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );
    }

    /**
     * Create new package cards
     *
     * @param array  $parcels
     * @param array  $customer
     * @param string $size
     *
     * @return stdClass
     */
    public function create_new_package_cards( $parcels, $customer, $package_card_size ) {
        $endpoint = 'create_new_package_cards/true/json/json/' . $this->get_order_number();

        $data['order']['parcels'] = $parcels;
        $data['customer'] = $customer;
        $data['order']['package_card']['size'] = $package_card_size;

        $encoded_data = array( 'sf_create_new_package_cards' => json_encode( array( 'query' => $data ) ) );

        return WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );
    }

    /**
     * Get package cards
     *
     * @return stdClass
     */
    public function get_package_cards() {
        $endpoint = 'get_package_cards/true/json/json/' . $this->get_order_number();

        return WC_Shipfunk_API::request( $endpoint );
    }

    /**
     * Create an order
     *
     * @param array $customer
     * @param array $products
     * @param array $parcels
     * @param array $shipping_method
     * @param array $discounts
     *
     * @return stdClass
     */
    public function create_order( $products, $parcels, $customer, $shipping_method, $discounts ) {
        $endpoint = 'create_order/true/json/json/' . $this->get_order_number();

        $data['customer'] = $customer;
        $data['order']['products'] = $products;
        $data['order']['parcels'] = $parcels;
        $data['order']['discounts'] = $discounts;
        $data['order']['selected_option'] = $shipping_method;
        $data['order']['additions']['warehouse_leadtime'] = 1;
        $data['customer'] = $customer;

        $encoded_data = array( 'sf_create_order' => json_encode( array( 'query' => $data ) ) );
        $response = WC_Shipfunk_API::request( $endpoint, 'POST', $encoded_data );

        return $response;
    }
}
