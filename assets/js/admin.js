jQuery(document).ready(function($) {
    'use strict';
    
    // Wait for WooCommerce scripts to load
    if (typeof $.fn.selectWoo === 'undefined') {
        // Fallback to select2 if selectWoo is not available
        if (typeof $.fn.select2 !== 'undefined') {
            $.fn.selectWoo = $.fn.select2;
        } else {
            console.error('Neither selectWoo nor select2 is available');
            return;
        }
    }
    
    // Initialize product search select
    function initProductSearch($element) {
        // Skip if already initialized
        if ($element.hasClass('select2-hidden-accessible') || $element.hasClass('enhanced')) {
            return;
        }
        
        // Check if WooCommerce enhanced select params are available
        if (typeof wc_enhanced_select_params === 'undefined') {
            console.error('WooCommerce enhanced select params not available');
            // Try to get basic AJAX URL from localized script
            if (typeof wbc_admin !== 'undefined' && wbc_admin.ajax_url) {
                // Use basic select2 without AJAX
                $element.selectWoo({
                    placeholder: $element.data('placeholder') || 'Type to search...',
                    allowClear: true,
                    width: '100%'
                });
            }
            return;
        }
        
        try {
            $element.selectWoo({
                ajax: {
                    url: wc_enhanced_select_params.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            term: params.term || '',
                            action: $element.data('action') || 'woocommerce_json_search_products_and_variations',
                            security: wc_enhanced_select_params.search_products_nonce || '',
                            exclude: $element.data('exclude') || '',
                            include: $element.data('include') || '',
                            limit: $element.data('limit') || -1
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
                escapeMarkup: function(m) {
                    return m;
                },
                minimumInputLength: 3,
                placeholder: $element.data('placeholder') || 'Search for a product…',
                allowClear: true,
                width: '100%'
            }).addClass('enhanced');
        } catch (e) {
            console.error('Error initializing product search:', e);
            // Fallback to basic select2
            $element.selectWoo({
                placeholder: $element.data('placeholder') || 'Type to search...',
                allowClear: true,
                width: '100%'
            });
        }
    }

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
    
    // Ensure button exists
    if ($('#add-bogo-rule').length === 0) {
        console.error('Add BOGO Rule button not found!');
    } else {
        console.log('Add BOGO Rule button found and ready.');
    }

    // Rule counter
    var ruleIndex = $('#bogo-rules-list tr').length;

    // Add new rule - using event delegation
    $(document).on('click', '#add-bogo-rule', function(e) {
        e.preventDefault();
        
        console.log('Add Rule button clicked!');
        console.log('Current rule index: ' + ruleIndex);
        
        // Try to get template from hidden div first
        var template = $('#bogo-rule-template').html();
        
        if (!template) {
            console.log('Template not found in hidden div, creating manually...');
            // Create template manually as fallback
            template = createRuleRowTemplate(ruleIndex);
        } else {
            console.log('Template found, replacing index...');
            template = template.replace(/{{index}}/g, ruleIndex);
        }
        
        // Verify we have a rules list
        if ($('#bogo-rules-list').length === 0) {
            console.error('Rules list table body not found!');
            return;
        }
        
        $('#bogo-rules-list').append(template);
        console.log('Rule added to table');
        
        // Initialize select2 for new row
        $('#bogo-rules-list tr:last').find('.wc-product-search').each(function() {
            console.log('Initializing product search for new row');
            initProductSearch($(this));
        });
        
        ruleIndex++;
        
        // Log current rule count
        console.log('Total rules now: ' + $('#bogo-rules-list tr').length);
    });
    
    // Fallback template function
    function createRuleRowTemplate(index) {
        var buySearchId = 'bogo_rules_' + index + '_buy_product_id';
        var getSearchId = 'bogo_rules_' + index + '_get_product_id';
        
        return '<tr class="bogo-rule-row">' +
            '<td>' +
                '<select name="bogo_rules[' + index + '][buy_product_id]" ' +
                        'id="' + buySearchId + '" ' +
                        'class="wc-product-search" ' +
                        'style="width: 100%;" ' +
                        'data-placeholder="' + (wbc_admin.i18n.search_product || 'Search for a product…') + '" ' +
                        'data-action="woocommerce_json_search_products_and_variations">' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="number" ' +
                       'name="bogo_rules[' + index + '][buy_quantity]" ' +
                       'value="1" ' +
                       'min="1" ' +
                       'style="width: 60px;">' +
            '</td>' +
            '<td>' +
                '<select name="bogo_rules[' + index + '][get_product_id]" ' +
                        'id="' + getSearchId + '" ' +
                        'class="wc-product-search" ' +
                        'style="width: 100%;" ' +
                        'data-placeholder="' + (wbc_admin.i18n.search_product || 'Search for a product…') + '" ' +
                        'data-action="woocommerce_json_search_products_and_variations">' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="number" ' +
                       'name="bogo_rules[' + index + '][get_quantity]" ' +
                       'value="1" ' +
                       'min="1" ' +
                       'style="width: 60px;">' +
            '</td>' +
            '<td>' +
                '<input type="number" ' +
                       'name="bogo_rules[' + index + '][discount]" ' +
                       'value="100" ' +
                       'min="0" ' +
                       'max="100" ' +
                       'step="0.01" ' +
                       'style="width: 80px;">' +
            '</td>' +
            '<td>' +
                '<input type="number" ' +
                       'name="bogo_rules[' + index + '][max_free_quantity]" ' +
                       'value="" ' +
                       'min="0" ' +
                       'placeholder="' + (wbc_admin.i18n.unlimited || 'Unlimited') + '" ' +
                       'style="width: 80px;">' +
            '</td>' +
            '<td>' +
                '<button type="button" class="button remove-bogo-rule">' +
                    (wbc_admin.i18n.remove || 'Remove') +
                '</button>' +
            '</td>' +
        '</tr>';
    }

    // Remove rule
    $(document).on('click', '.remove-bogo-rule', function(e) {
        e.preventDefault();
        
        if (!confirm(wbc_admin.i18n.confirm_remove)) {
            return;
        }
        
        $(this).closest('tr').remove();
        
        // Ensure at least one rule exists
        if ($('#bogo-rules-list tr').length === 0) {
            $('#add-bogo-rule').trigger('click');
        }
    });

    // Initialize Select2 for existing rows
    setTimeout(function() {
        $('.wc-product-search').each(function() {
            initProductSearch($(this));
        });
        
        // Trigger WooCommerce enhanced select init
        $(document.body).trigger('wc-enhanced-select-init');
    }, 100);
    
    // Also initialize on WooCommerce's enhanced select init event
    $(document.body).on('wc-enhanced-select-init', function() {
        $('.wc-product-search:not(.enhanced)').each(function() {
            initProductSearch($(this));
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