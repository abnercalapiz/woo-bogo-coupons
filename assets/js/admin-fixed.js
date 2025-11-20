jQuery(document).ready(function($) {
    'use strict';

    // Show/hide BOGO fields based on coupon type
    function toggleBogoFields() {
        var discountType = $('#discount_type').val();
        if (discountType === 'bogo_coupon') {
            $('.bogo-coupon-fields').show();
            // Hide regular amount field
            $('.coupon_amount_field').hide();
            // Hide other irrelevant fields
            $('#general_coupon_data .form-field:not(.discount_type_field)').hide();
            $('.discount_type_field').show();
            $('.expiry_date_field').show();
            $('.usage_limit_field').show();
            $('.usage_limit_per_user_field').show();
        } else {
            $('.bogo-coupon-fields').hide();
            $('.coupon_amount_field').show();
            $('#general_coupon_data .form-field').show();
        }
    }

    // Initialize on page load
    toggleBogoFields();
    $('#discount_type').on('change', toggleBogoFields);

    // Rule counter
    var ruleIndex = $('#bogo-rules-list tr').length;

    // Function to initialize WooCommerce product search
    function initWCProductSearch($element) {
        try {
            if (typeof wc_enhanced_select_params !== 'undefined' && $.fn.selectWoo) {
                $element.selectWoo({
                    minimumInputLength: 3,
                    ajax: {
                        url: wc_enhanced_select_params.ajax_url,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                term: params.term,
                                action: 'woocommerce_json_search_products_and_variations',
                                security: wc_enhanced_select_params.search_products_nonce
                            };
                        },
                        processResults: function(data) {
                            var terms = [];
                            if (data) {
                                $.each(data, function(id, text) {
                                    terms.push({
                                        id: id,
                                        text: text
                                    });
                                });
                            }
                            return {
                                results: terms
                            };
                        },
                        cache: true
                    },
                    placeholder: 'Search for a product…',
                    allowClear: true
                });
            } else {
                console.log('Select2 not available, using fallback');
                // Fallback: Convert to regular select and populate with products via AJAX
                loadProductOptions($element);
            }
        } catch (e) {
            console.error('Error initializing product search:', e);
            loadProductOptions($element);
        }
    }
    
    // Fallback function to load products into regular select
    function loadProductOptions($select) {
        if (!$select.data('loaded')) {
            $select.data('loaded', true);
            
            // Add loading message
            $select.html('<option value="">Loading products...</option>');
            
            // Make AJAX call to get products
            $.ajax({
                url: wbc_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'woocommerce_json_search_products_and_variations',
                    security: wc_enhanced_select_params ? wc_enhanced_select_params.search_products_nonce : '',
                    term: '',
                    limit: 100
                },
                success: function(data) {
                    var options = '<option value="">Select a product...</option>';
                    if (data) {
                        $.each(data, function(id, text) {
                            options += '<option value="' + id + '">' + text + '</option>';
                        });
                    }
                    $select.html(options);
                },
                error: function() {
                    $select.html('<option value="">Error loading products. Enter product ID manually.</option>');
                }
            });
        }
    }

    // Create rule row HTML
    function createRuleRow(index) {
        var html = '<tr class="bogo-rule-row">';
        
        // Buy Product
        html += '<td>';
        html += '<select name="bogo_rules[' + index + '][buy_product_id]" class="wc-product-search" style="width: 100%;" data-placeholder="Search for a product…" data-action="woocommerce_json_search_products_and_variations">';
        html += '</select>';
        html += '</td>';
        
        // Buy Quantity
        html += '<td>';
        html += '<input type="number" name="bogo_rules[' + index + '][buy_quantity]" value="1" min="1" style="width: 60px;">';
        html += '</td>';
        
        // Get Product
        html += '<td>';
        html += '<select name="bogo_rules[' + index + '][get_product_id]" class="wc-product-search" style="width: 100%;" data-placeholder="Search for a product…" data-action="woocommerce_json_search_products_and_variations">';
        html += '</select>';
        html += '</td>';
        
        // Get Quantity
        html += '<td>';
        html += '<input type="number" name="bogo_rules[' + index + '][get_quantity]" value="1" min="1" style="width: 60px;">';
        html += '</td>';
        
        // Discount %
        html += '<td>';
        html += '<input type="number" name="bogo_rules[' + index + '][discount]" value="100" min="0" max="100" step="0.01" style="width: 80px;">';
        html += '</td>';
        
        // Max Free Qty
        html += '<td>';
        html += '<input type="number" name="bogo_rules[' + index + '][max_free_quantity]" value="" min="0" placeholder="Unlimited" style="width: 80px;">';
        html += '</td>';
        
        // Remove button
        html += '<td>';
        html += '<button type="button" class="button remove-bogo-rule">Remove</button>';
        html += '</td>';
        
        html += '</tr>';
        
        return html;
    }

    // Add new rule
    $(document).on('click', '#add-bogo-rule', function(e) {
        e.preventDefault();
        
        var newRow = createRuleRow(ruleIndex);
        $('#bogo-rules-list').append(newRow);
        
        // Initialize select2 on new dropdowns
        $('#bogo-rules-list tr:last').find('.wc-product-search').each(function() {
            initWCProductSearch($(this));
        });
        
        ruleIndex++;
    });

    // Remove rule
    $(document).on('click', '.remove-bogo-rule', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to remove this rule?')) {
            $(this).closest('tr').remove();
            
            // Ensure at least one rule exists
            if ($('#bogo-rules-list tr').length === 0) {
                $('#add-bogo-rule').trigger('click');
            }
        }
    });

    // Initialize existing select2 fields
    $('.wc-product-search').each(function() {
        initWCProductSearch($(this));
    });

    // Reinitialize on WC enhanced select init
    $(document.body).on('wc-enhanced-select-init', function() {
        $('.wc-product-search:not(.select2-hidden-accessible)').each(function() {
            initWCProductSearch($(this));
        });
    });

    // Validate rules before save
    $('#post').on('submit', function(e) {
        if ($('#discount_type').val() !== 'bogo_coupon') {
            return true;
        }

        var isValid = true;
        var hasRules = false;

        $('#bogo-rules-list tr').each(function() {
            var $row = $(this);
            var buyProduct = $row.find('select[name*="[buy_product_id]"]').val();
            var getProduct = $row.find('select[name*="[get_product_id]"]').val();
            
            if (buyProduct || getProduct) {
                hasRules = true;
                
                if (!buyProduct || !getProduct) {
                    isValid = false;
                    alert('Please select both buy and get products for all rules.');
                    return false;
                }
            }
        });

        if (!hasRules) {
            alert('Please add at least one BOGO rule.');
            return false;
        }

        return isValid;
    });
});