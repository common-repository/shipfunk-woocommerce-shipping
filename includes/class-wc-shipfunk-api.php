<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Communicates with Shipfunk API.
 */
class WC_Shipfunk_API {

    /**
     * Shipfunk API base endpoint
     */
    const BASE_URL = 'https://shipfunkservices.com/api/1.2/';

    /**
     * Secret API key
     *
     * @var string
     */
    private static $secret_key;

    /**
     * Get secret key
     *
     * @return string
     */
    public static function get_base_url() {
        return self::BASE_URL;
    }

    /**
     * Set secret key
     *
     * @param string $secret_key
     */
    public static function set_secret_key( $secret_key ) {
        self::$secret_key = $secret_key;
    }

    /**
     * Get secret key
     *
     * @return string
     */
    public static function get_secret_key() {
        return self::$secret_key;
    }

    /**
     * Send the request to Shipfunk
     *
     * @param string    $url
     * @param string    $method
     * @param array     $data
     *
     * @return array
     */
    public static function request( $endpoint, $method = 'GET', $data = array() ) {
        $url = self::BASE_URL . $endpoint;

        $response = wp_remote_post( $url, array(
                'method' => $method,
                'timeout' => 45,
                'headers' => array(
                    'Authorization'  => self::get_secret_key()
                ),
                'body' => $data // Must be wrapped in API endpoint specific variable
            )
        );

        if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
            return new WP_Error( 'shipfunk_error', __( 'There was a problem connecting to the shipping gateway. Refill your address.', 'shipfunk-woocommerce-shipping' ) );
        }

        $response = json_decode( $response['body'] );

        if ( isset( $response->Error ) ) {
            if ( ! is_admin() ) {
                switch ( $response->Error->Code ) {
                    case '00027':
                        $message = __( 'Phonenumber is invalid.', 'shipfunk-woocommerce-shipping' );
                        break;
                    case '00085':
                        $message = __( 'Email is invalid.', 'shipfunk-woocommerce-shipping' );
                        break;
                    case '00109':
                        $message = __( 'Postal code is invalid.', 'shipfunk-woocommerce-shipping' );
                        break;
                    default:
                        $message = __( 'There was a problem connecting to the shipping gateway. Refill your address.', 'shipfunk-woocommerce-shipping' );
                        break;
                }
            } else {
                $message = $response->Error->Message;
            }

            error_log( 'API error response: ' . $response->Error->Message );
            return new WP_Error( 'shipfunk_error', $message );
        }

        if ( isset( $response->Info ) ) {
            $message = $response->Info->Message;
            error_log( 'API info response: ' . $message );
            return new WP_Error( 'shipfunk_info', $message );
        }

        return $response;
    }
}
