jQuery(document).ready( function($){

    /*** Settings page scripts **************************/

    $("#sf_boxes").on('click', '.sf-add', function(e){
        e.preventDefault();

        var lastId = $("#sf_boxes table tbody tr:last-child td:first-child input[type=hidden]").attr("value");
        if( lastId != null ){
            console.log(lastId);
            var newId = parseInt( lastId ) + 1;
        }else{
            var newId = 0;
        }

        $(
            '<tr class="alternate">\
                <td class="check-column">\
                    <input type="checkbox" name="select"  style="margin-left:10px; margin-top:10px;" />\
                    <input type="hidden" name="woocommerce_sf_box[' + newId + '][id]" id="woocommerce_sf_box_id_' + newId + '" value="' + newId + '" required/>\
                </td>\
                <td>\
                    <input type="text" name="woocommerce_sf_box[' + newId + '][name]" id="woocommerce_sf_box_name_' + newId + '" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][length]" id="woocommerce_sf_box_length_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][width]" id="woocommerce_sf_box_width_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][height]" id="woocommerce_sf_box_height_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][inner_length]" id="woocommerce_sf_box_inner_length_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][inner_width]" id="woocommerce_sf_box_inner_width_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][inner_height]" id="woocommerce_sf_box_inner_height_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][box_weight]" id="woocommerce_sf_box_box_weight_' + newId + '" class="sf-input-width-short" required></input>\
                </td>\
                <td>\
                    <input type="number" step="0.01" min="0" name="woocommerce_sf_box[' + newId + '][max_weight]" id="woocommerce_sf_box_max_weight_' + newId + '" class="sf-input-width-short" equired></input>\
                </td>\
            </tr>'
        ).appendTo($("#sf_boxes > table > tbody"));

    });

    $("#sf_boxes").on('click', '.sf-remove', function(e){
        e.preventDefault();
        $("#sf_boxes table tbody tr td:first-child input:checked").each(function(){
            $(this).parent().parent().remove();
        });
    });

    /*** Order details page scripts *****************************/

    function create_id( array, allow_zero ) {
        var new_id = allow_zero ? '0' : '1';
        while( jQuery.inArray( new_id, array ) !== -1 ){
            new_id = (parseInt( new_id ) + 1 ).toString();
        }

        return new_id;
    }

    $('#sf_add_parcel').click(function(){
        var ids = [];
        var parcelcodes = [];

        $('.sf-parcel').each(function(){
            ids.push( $(this).find('input[id^=parcels_id_]').val() );
            parcelcodes.push( $(this).find('input[id^=parcels_code_]').val() );
        });

        var new_id = create_id( ids, true );
        var new_parcelcode = create_id( parcelcodes, false );

        var fields = '<div class="sf-parcel">';
        fields +=       '<h2><input type="checkbox" name="remove" /><b>' + sfa.new_parcel + '</b></h2>';
        //fields +=         '<input type="hidden" name="parcels[' + new_id + '][id]" id="parcels_id_' + new_id + '" value="' + new_id + '" />';
        fields +=       '<input type="hidden" name="parcels[' + new_id + '][code]" id="parcels_code_' + new_id + '" value="' + new_parcelcode + '" />';
        fields +=   '<div class="sf-parcel-wrap">';
        fields +=       '<table>';
        fields +=           '<tr>';
        fields +=               '<td><label for="parcels['+ new_id +'][contents]">' + sfa.content + ' </label></td>';
        fields +=               '<td>';
        fields +=                   '<input type="text" name="parcels['+ new_id +'][contents]" id="parcels_content_'+ new_id +'">';
        fields +=                   '<span class="description"> ' + sfa.optional + ' </span>';
        fields +=               '</td>';
        fields +=           '</tr>';
        fields +=           '<tr>';
        fields +=               '<td><label for="parcels['+ new_id +'][monetary_value]">' + sfa.value + ' </label></td>';
        fields +=               '<td>';
        fields +=                   '<input type="text" name="parcels['+ new_id +'][monetary_value]" id="parcels_monetary_value_'+ new_id +'">';
        fields +=                   '<span class="description"> ' + sfa.optional + ' </span>';
        fields +=               '</td>';
        fields +=           '</tr>';
        fields +=           '<tr>';
        fields +=               '<td><label for="parcels['+ new_id +'][dimensions][depth]">' + sfa.dimensions + '</label></td>';
        fields +=               '<td>';
        fields +=                   '<input type="number" step="0.01" name="parcels['+ new_id +'][dimensions][depth]" id="parcels_dimensions_depth_'+ new_id +'" class="sf-dimension" required> x ';
        fields +=                   '<input type="number" step="0.01" name="parcels['+ new_id +'][dimensions][width]" id="parcels_dimensions_width_'+ new_id +'" class="sf-dimension" required> x ';
        fields +=                   '<input type="number" step="0.01" name="parcels['+ new_id +'][dimensions][height]" id="parcels_dimensions_height_'+ new_id +'" class="sf-dimension" required> ';
        fields +=                   '<input type="hidden" name="parcels['+ new_id +'][dimensions][unit]" id="parcels_dimenions_unit_'+ new_id +'" value="' + sfa.dimensions_unit + '"/>';
        fields +=                   '<span class="description"> ' + sfa.dimensions_description + ' (' + sfa.dimensions_unit + ')</span>';
        fields +=               '</td>';
        fields +=           '</tr>';
        fields +=           '<tr>';
        fields +=               '<td><label for="parcels['+ new_id +'][weight][amount]">' + sfa.weight + '</label></td>';
        fields +=               '<td>';
        fields +=                   '<input type="number" step="0.01" name="parcels['+ new_id +'][weight][amount]" id="parcels_weight_amount_'+ new_id +'" class="sf-weight" required> ' + sfa.weight_unit;
        fields +=                   '<input type="hidden" name="parcels[' + new_id + '][weight][unit]" id="parcels_weight_unit_' + new_id + '" value="' + sfa.weight_unit + '" disabled/>';
        fields +=               '</td>';
        fields +=           '</tr>';
        fields +=       '</table>';
        fields +=   '</div>';
        fields += '</div>';

        $('#sf-parcels').append(fields);

    });

    $('#sf_remove_parcels').click(function(){
        $(".sf-parcel").each(function(){
            if( $(this).find('input[name="remove"]').attr('checked') ){
                $(this).remove();
            }

        });
    });

    $('#sf_edit_parcels').click(function(){
        $('.sf-parcel').css('color', 'black');
        $('.sf-parcel h2').css('color', 'black');
        $('.sf-parcel .sf-tcode-header').css('color', 'grey');

        $('#sf_add_parcel').prop('disabled', false);
        $('#sf_remove_parcels').prop('disabled', false);

        $('#parcel_fields_enabled').val(1);

        $('#sf_shipping_data input').prop('disabled', false);
        $('#sf_shipping_data select').prop('disabled', false);

    });

    if( $('#sf_add_parcel').prop('disabled') === true ){
        $('.sf-parcel').css('color', 'lightgrey');
        $('.sf-parcel h2').css('color', 'lightgrey');
    }

    $('#generate_packing_cards').click(function(){
        $('#parcel_fields_enabled').val(1);

        $('#sf_shipping_data input').prop('disabled', false);
        $('#sf_shipping_data select').prop('disabled', false);

        $("form#post").submit();
    });

});
