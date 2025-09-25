/**
 * Dunlop Calculator Admin JavaScript
 * Version 1.1.0
 */
jQuery(document).ready(function($) {
    console.log('Dunlop Calculator Admin: Initialized');
    
    // Get elements
    const calculatorType = $('#_calculator_type');
    const enableCheckbox = $('#_enable_calculator');
    const fieldGroups = $('.calc-type-fields');
    const attributeSettings = $('.attribute-settings');
    
    // Function to show/hide type-specific fields
    function toggleTypeFields() {
        const selectedType = calculatorType.val();
        
        // Hide all field groups first
        fieldGroups.hide().removeClass('active');
        
        if (!selectedType) {
            return;
        }
        
        // Map calculator types to field groups
        const typeMapping = {
            'leveller': '[data-type="leveller"]',
            'adhesive_powder': '[data-type="adhesive"]',
            'adhesive_ready': '[data-type="adhesive"]',
            'grout': '[data-type="grout"]',
            'silicone': '[data-type="silicone"]',
            'waterproofing': '[data-type="waterproofing"]'
        };
        
        // Show relevant field group
        if (typeMapping[selectedType]) {
            $(typeMapping[selectedType]).show().addClass('active');
        }
        
        // Show/hide attribute options based on type
        toggleAttributeOptions(selectedType);
    }
    
    // Function to toggle attribute options visibility
    function toggleAttributeOptions(type) {
        const colourOption = $('._use_colour_attributes_field');
        const weightOption = $('._use_weight_attributes_field');
        
        // Types that support colour attributes
        const colourTypes = ['grout', 'silicone', 'adhesive_ready'];
        
        // Types that support weight attributes
        const weightTypes = ['adhesive_ready', 'adhesive_powder', 'leveller', 'grout'];
        
        // Show/hide colour option
        if (colourTypes.includes(type)) {
            colourOption.show();
        } else {
            colourOption.hide();
            $('#_use_colour_attributes').prop('checked', false);
        }
        
        // Show/hide weight option
        if (weightTypes.includes(type)) {
            weightOption.show();
        } else {
            weightOption.hide();
            $('#_use_weight_attributes').prop('checked', false);
        }
    }
    
    // Function to toggle calculator fields visibility
    function toggleCalculatorFields() {
        const isEnabled = enableCheckbox.is(':checked');
        
        if (isEnabled) {
            $('.dunlop-calculator-fields').find('.form-field').not('._enable_calculator_field').show();
            attributeSettings.show();
            toggleTypeFields();
        } else {
            $('.dunlop-calculator-fields').find('.form-field').not('._enable_calculator_field').hide();
            fieldGroups.hide();
            attributeSettings.hide();
        }
    }
    
    // Add help text for attribute settings
    function addAttributeHelpText() {
        const colourHelp = $('<p class="description attribute-help" style="margin: 10px 0; padding: 10px; background: #f0f8ff; border-left: 3px solid #2271b1;">' +
            '<strong>Colour Attributes:</strong> If enabled, the calculator will pull available colours from the product\'s colour attributes. ' +
            'Make sure colours are added to the product under Product Data > Attributes > Colour. ' +
            'Multiple colours can be separated with " | " in a single attribute term.' +
            '</p>');
        
        const weightHelp = $('<p class="description attribute-help" style="margin: 10px 0; padding: 10px; background: #f0f8ff; border-left: 3px solid #2271b1;">' +
            '<strong>Weight Attributes:</strong> If enabled, the calculator will pull pack sizes from the product\'s weight attributes. ' +
            'The system will automatically optimize pack combinations to minimize the total number of packs needed. ' +
            'Add weights like "5kg", "15kg", "20kg" to the product under Product Data > Attributes > Weight.' +
            '</p>');
        
        // Add help text only if not already present
        if (!$('.attribute-help').length) {
            $('._use_colour_attributes_field').after(colourHelp);
            $('._use_weight_attributes_field').after(weightHelp);
        }
    }
    
    // Highlight calculator section in admin
    function highlightCalculatorSection() {
        $('.dunlop-calculator-fields').css({
            'background': '#f9f9f9',
            'border': '1px solid #c3c4c7',
            'border-left': '4px solid #2271b1',
            'margin': '15px 0'
        });
    }
    
    // Validate depth fields
    function validateDepthFields() {
        const minDepth = $('#_leveller_min_depth');
        const maxDepth = $('#_leveller_max_depth');
        
        minDepth.on('change', function() {
            const min = parseFloat($(this).val());
            const max = parseFloat(maxDepth.val());
            
            if (min && max && min > max) {
                $(this).css('border-color', '#d63638');
                alert('Minimum depth cannot be greater than maximum depth');
            } else {
                $(this).css('border-color', '');
            }
        });
        
        maxDepth.on('change', function() {
            const max = parseFloat($(this).val());
            const min = parseFloat(minDepth.val());
            
            if (min && max && max < min) {
                $(this).css('border-color', '#d63638');
                alert('Maximum depth cannot be less than minimum depth');
            } else {
                $(this).css('border-color', '');
            }
        });
    }
    
    // Initialize on page load
    toggleCalculatorFields();
    toggleTypeFields();
    addAttributeHelpText();
    highlightCalculatorSection();
    validateDepthFields();
    
    // Event listeners
    enableCheckbox.on('change', toggleCalculatorFields);
    calculatorType.on('change', toggleTypeFields);
    
    // Show a notice if WooCommerce attributes are not configured
    function checkAttributeConfiguration() {
        const useColour = $('#_use_colour_attributes').is(':checked');
        const useWeight = $('#_use_weight_attributes').is(':checked');
        
        if (useColour || useWeight) {
            const notice = $('<div class="notice notice-info" style="margin: 10px 0; padding: 10px;">' +
                '<p><strong>Important:</strong> Make sure to configure product attributes in the "Attributes" tab. ' +
                'Attributes must be added and visible on the product page for the calculator to detect them.</p>' +
                '</div>');
            
            if (!$('.attribute-notice').length) {
                attributeSettings.prepend(notice.addClass('attribute-notice'));
            }
        }
    }
    
    $('#_use_colour_attributes, #_use_weight_attributes').on('change', checkAttributeConfiguration);
    checkAttributeConfiguration();
    
    // Add quick links to attribute tab
    function addQuickLinks() {
        const quickLinks = $('<div class="quick-links" style="margin: 10px 0; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">' +
            '<strong>Quick Actions:</strong> ' +
            '<a href="#woocommerce-product-data" class="button button-small" id="goto-attributes">Go to Attributes Tab</a> ' +
            '<span style="margin: 0 10px;">|</span> ' +
            '<a href="https://docs.woocommerce.com/document/managing-product-taxonomies/#product-attributes" target="_blank" class="button button-small">Attributes Documentation</a>' +
            '</div>');
        
        if (!$('.quick-links').length) {
            attributeSettings.after(quickLinks);
        }
        
        $('#goto-attributes').on('click', function(e) {
            e.preventDefault();
            $('.product_attributes').find('a').click();
            $('html, body').animate({
                scrollTop: $('#product_attributes').offset().top - 100
            }, 500);
        });
    }
    
    addQuickLinks();
    
    // Log current configuration for debugging
    console.log('Calculator Type:', calculatorType.val());
    console.log('Calculator Enabled:', enableCheckbox.is(':checked'));
    console.log('Use Colour Attributes:', $('#_use_colour_attributes').is(':checked'));
    console.log('Use Weight Attributes:', $('#_use_weight_attributes').is(':checked'));
});
