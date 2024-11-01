jQuery(document).ready( function($){
    $(".woocommerce-checkout").on('click', '#sf_reload_pickups', (function(){
        $("#sf_pickups_reloaded").hide();

        var postcode = $("#sf_pickup_location_postcode").val();
        var country = $('#ship_to_different_address input:checked') ? $('#shipping_country').val() : $('#billing_country').val();
        var rate_id = $('#shipping_method input:checked').val();
        var rate_id_split = rate_id.split('_');
        var carriercode = rate_id_split[1];

        var request_data = {
            'action': 'get_pickups',
            'security': get_pickups.security,
            'rate_id': rate_id,
            'carriercode': carriercode,
            'country': country,
            'postcode': postcode
        }

        $.post(get_pickups.ajaxurl, request_data, function(data, status) {
            var data = JSON.parse(data);
            if (data.response != undefined){
                $("#sf_pickup_select").empty();
                for(var i = 0; i < data.response.length; i++){
                    var pickup_value = data.response[i]["pickup_id"] + '|'
                        + data.response[i]["pickup_name"] + '|'
                        + data.response[i]["pickup_addr"] + '|'
                        + data.response[i]["pickup_postal"] + '|'
                        + data.response[i]["pickup_city"] + '|'
                        + data.response[i]["pickup_country"];

                    var pickup_text = data.response[i]["pickup_name"] + " - "
                        + data.response[i]["pickup_addr"] + ", "
                        + data.response[i]["pickup_postal"] + ", "
                        + data.response[i]["pickup_city"];

                    var option = $('<option></option>').attr("value", pickup_value).text(pickup_text);

                    $("#sf_pickup_select").append(option);

                    if (data.previous_selection == pickup_value) {
                        $("#sf_pickup_select").val(pickup_value);
                    }

                    $("#sf_no_pickups").hide();
                    $("#sf_pickups_reloaded").show();
                }
                $.post(get_pickups.ajaxurl, {
                    'action': 'save_selected_pickup',
                    'option_value': $('#sf_pickup_select').val()
                });
            } else {
                $("#sf_no_pickups").show();
            }
        });

        return false; // Prevents click event happening twice
    }));

    $(".woocommerce-checkout").on("change", "#sf_pickup_select", function() {
        var rate_id = $('#shipping_method input:checked').val();
        var data = {
            'action': 'save_selected_pickup',
            'option_value': $(this).val(),
            'rate_id': rate_id
        };
        $.post(get_pickups.ajaxurl, data);
    });
});
