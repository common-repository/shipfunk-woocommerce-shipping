<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$url = "https://shipfunkservices.com/extranet/login";

return array(
    'enabled'      => array(
        'title'           => __( 'Enable/Disable', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Enable this shipping method', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'checkbox',
        'default'         => 'no'
    ),
    'live_secret_key' => array(
        'title'           => __( 'API Live Key', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'text',
        'description'     => sprintf(wp_kses(__( 'Shipfunk API live secret key found from <a target=%2$s href=%1$s>Extranet</a> -> Account -> API keys. Note: Live key is not shown if you have not activated your account.', 'shipfunk-woocommerce-shipping' ),
                             array(  'a' => array( 'href' => array(), 'target' =>array() ) )), esc_url($url), "_blank"),
        'default'         => '',
        'custom_attributes' => array(
            'autocomplete' => 'off'
        )
    ),
    'test_secret_key' => array(
        'title'           => __( 'API Test Key', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'text',
        'description'     => sprintf(
                                wp_kses(
                                        __(
                                            'Shipfunk API test secret key found from <a target=%2$s href=%1$s>Extranet</a> -> Account -> API keys.',
                                            'shipfunk-woocommerce-shipping'
                                        ),
                                    array(
                                        'a' => array( 'href' => array(), 'target' =>array() )
                                        )
                                ),
                                esc_url($url),
                                '_blank'
                            ),
        'default'         => '',
        'custom_attributes' => array(
            'autocomplete' => 'off'
        )
    ),
    'test_mode'      => array(
        'title'           => __( 'Use Test Mode', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Use test mode', 'shipfunk-woocommerce-shipping' ),
        'description'     => __( 'All data goes to your test environment and no data is sent to logistic companies.', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'checkbox',
        'default'         => 'yes'
    ),
    'default_weight'           => array(
        'title'           => __( 'Default Product Weight', 'shipfunk-woocommerce-shipping' ) . ' (' . get_option( 'woocommerce_weight_unit' ) . ')',
        'type'            => 'decimal',
        'default'         => '2.00',
        'description'     => __( 'Weight parameter is a requisite to be able to calculate shipping rates. If product does not have weight set then the default weight is used. Note: This can lead to unsuitable shipping option results and cause distortion on shipping prices. Thus it is highly recommended that every product has the correct weight set.', 'shipfunk-woocommerce-shipping' )
    ),
    'default_dimensions'  => array(
        'title'           => __( 'Default Product Dimensions', 'shipfunk-woocommerce-shipping' ) . ' (' . get_option( 'woocommerce_dimension_unit' ) . ')',
        'label'           => 'length x height x width (' . get_option( 'woocommerce_dimension_unit' ) . ')',
        'type'            => 'text',
        'default'         => '10x10x2',
        'placeholder'     => __( 'e.g. ', 'shipfunk-woocommerce-shipping' ) . ' 10x10x2',
        'description'     => __( 'Format: length x height x width, e.g. 10x10x2, you can use decimals also.', 'shipfunk-woocommerce-shipping' ) . ' '. __( 'If product does not have dimensions set then the default dimensions is used. Note: This can lead to unsuitable shipping option results and cause distortion on shipping prices. Thus it is highly recommended that every product has the correct dimensions set.', 'shipfunk-woocommerce-shipping' )
    ),
    'sort_by_price'       => array(
        'title'           => __( 'Sort by Price', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Sort shipping methods by price', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'checkbox',
        'default'         => 'yes',
        'description'     => __( 'Shipping methods will be shown from lowest to highest price.', 'shipfunk-woocommerce-shipping' )
    ),
    'use_categories'      => array(
        'title'           => __( 'Use Categories', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Override Woocommerce shipping template and use shipping method categories', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'checkbox',
        'default'         => 'no',
        'description'     => __( 'You can configure shipping method categories on Shipfunk Extranet settings.', 'shipfunk-woocommerce-shipping' )
    ),
    'discount_enabled_deliveries'      => array(
        'title'           => __( 'Coupons', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Apply coupon discounts to selected shipping method type only', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'select',
        'options'         => array(
                                'all'       => 'All shipping methods',
                                'normal'    => 'Normal shipping methods',
                                'home'      => 'Home shipping methods',
                                'express'   => 'Express shipping methods'
                            ),
        'description'     => __( 'You can use coupons to present free delivery for customers. The coupon must have "Allow free delivery" option enabled. Select which type of shipping methods should be shown as free.', 'shipfunk-woocommerce-shipping' )
    ),
    'show_shipping_time_estimate' => array(
        'title'           => __( 'Show shipping time estimate', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Show shipping time estimate', 'shipfunk-woocommerce-shipping' ),
        'description'     => __( 'Show shipping time estimate on shipping label, e.g. Postipaketti (Posti) - 1-3 days. It is possible to increase shipping time estimate by increasing your warehouse lead time on Shipfunk Extranet.', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'checkbox',
        'default'         => 'yes'
    ),
    'manual_parcel_dimens' => array(
        'title'           => __( 'Manual Parcel Data', 'shipfunk-woocommerce-shipping' ),
        'label'           => __( 'Manually enter parcel dimensions, weight etc to get package cards', 'shipfunk-woocommerce-shipping' ),
        'type'            => 'checkbox',
        'default'         => 'yes',
        'description'     => sprintf(
                                    wp_kses(
                                        __( 'Enabling this you need to enter parcel information manually to get package cards and tracking codes for your orders.<br><strong>Keeping this unchecked enables automatic parcel calculation</strong> and thus makes shipment option results more refined and package card and tracking code generation easier. <p class=%s><strong>Note! If you are using automatic parcel calculation remember to configure the following box settings.</strong></p>', 'shipfunk-woocommerce-shipping' ),
                                        array( 'strong'=>array(),
                                            'p' => array('class' => array()),
                                            'br' => array(),)
                                    ),
                                    'strong-note'
                            )
    )
);
