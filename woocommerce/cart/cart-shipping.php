<?php
/**
 * Shipping Methods Display
 *
 * Overridden template /cart/cart-shipping.php.
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<tr class="shipping">
    <th><?php echo wp_kses_post( $package_name ); ?></th>
    <td data-title="<?php echo esc_attr( $package_name ); ?>">
        <?php if ( 1 < count( $available_methods ) ) : ?>
            <ul id="shipping_method">
                <?php

                    $temp_available_methods = $available_methods;
                    $categories = WC()->session->get( 'sf_categories', array() );

                    $loops_first_step = true;
                    foreach ( $categories as $category => $carriercodes ) {

                        if( $loops_first_step ) echo '<li class="sf-category">' . $category . '</li>';
                        else echo '<li  class="sf-category" style="padding-top:10px;">' . $category . '</li>';

                        foreach ( $temp_available_methods as $key => $method ) {

                            $rate_id = explode( '_', $method->id );
                            if( $rate_id[0] === 'sf' && isset( $rate_id[1]) && in_array( $rate_id[1], $carriercodes ) ){

                                echo '<li>';
                                printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />
                                <label for="shipping_method_%1$d_%2$s">%5$s</label>',
                                $index, sanitize_title( $method->id ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ), wc_cart_totals_shipping_method_label( $method ) );

                                do_action( 'woocommerce_after_shipping_rate', $method, $index );
                                echo '</li>';

                                unset( $temp_available_methods[$key]);
                            }
                        }

                        $loops_first_step = false;
                    }

                    if( count( $temp_available_methods ) > 0 ){

                        // If there are no categories configured, do not display "Other Shipping Methods"
                        if( count( $categories ) > 0 ) echo '<li style="padding-top:10px;">' . __( 'Other Shipping Methods', 'shipfunk-woocommerce-shipping') . '</li>';

                        foreach ($temp_available_methods as $method) {

                            echo '<li>';
                            printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />
                            <label for="shipping_method_%1$d_%2$s">%5$s</label>',
                            $index, sanitize_title( $method->id ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ), wc_cart_totals_shipping_method_label( $method ) );

                            do_action( 'woocommerce_after_shipping_rate', $method, $index );
                            echo '</li>';
                        }

                    }
                ?>
            </ul>
        <?php elseif ( 1 === count( $available_methods ) ) :  ?>
            <?php
                $method = current( $available_methods );
                //$rate_id = explode( '_', $method->id );
                //if( $rate_id[0] == 'sf' ) echo '<div style="padding: 0px 0px 10px 0px;"><b>' . $c[5] . '</b></div>';
                printf( '%3$s <input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d" value="%2$s" class="shipping_method" />', $index, esc_attr( $method->id ), wc_cart_totals_shipping_method_label( $method ) );
                do_action( 'woocommerce_after_shipping_rate', $method, $index );
            ?>
        <?php elseif ( ! WC()->customer->has_calculated_shipping() ) : ?>
            <?php echo wpautop( __( 'Shipping costs will be calculated once you have provided your address.', 'woocommerce' ) ); ?>
        <?php else : ?>
            <?php echo apply_filters( is_cart() ? 'woocommerce_cart_no_shipping_available_html' : 'woocommerce_no_shipping_available_html', wpautop( __( 'There are no shipping methods available. Please double check your address and contact details, or contact us if you need any help.', 'shipfunk-woocommerce-shipping' ) ) ); ?>
        <?php endif; ?>

        <?php if ( $show_package_details ) : ?>
            <?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
        <?php endif; ?>

        <?php if ( is_cart() && ! $index ) : ?>
            <?php woocommerce_shipping_calculator(); ?>
        <?php endif; ?>
    </td>
</tr>
