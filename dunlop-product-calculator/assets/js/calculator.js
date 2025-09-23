/**
 * Dunlop Calculator Frontend JavaScript
 * Version 1.1.0 - With attribute support
 */
jQuery(document).ready(function($) {
    console.log('Dunlop Plugin Calculator: Initializing v1.1.0...');
    
    // Block old calculator
    window.ajax_function = function() {
        console.log('Old ajax_function blocked by plugin');
        return false;
    };
    
    // Remove duplicate calculators
    setTimeout(function() {
        $('.calculator_container').each(function() {
            if (!$(this).hasClass('dunlop-plugin-calculator')) {
                console.log('Removing theme calculator:', this);
                $(this).remove();
            }
        });
        
        $('.calculator_container.wp_content').addClass('dunlop-plugin-calculator');
        
        const calculator = $('.calculator_container.dunlop-plugin-calculator');
        const targetHr = $('.product_description hr');
        
        if (calculator.length && targetHr.length) {
            calculator.insertAfter(targetHr);
            console.log('Dunlop Calculator: Positioned correctly');
        }
    }, 100);
    
    // Check if config exists
    if (typeof dunlopCalc === 'undefined') {
        console.log('Dunlop Calculator: Config not found, exiting');
        return;
    }
    
    const config = dunlopCalc.config;
    const calculatorType = config.type;
    const productId = parseInt(dunlopCalc.product_id);
    
    // Get form elements
    const form = $('#dunlop-calc-form');
    const resultsContainer = $('.results_container');
    const addToJobListBtn = $('#add-to-job-list');
    const calculationDiv = $('.calculation');
    const resultText = $('#calc-result');
    
    // Store calculated results
    let currentCalculation = null;
    
    // Initialize with "Enter values" state
    resultText.addClass('initial-state').text('Enter values');
    
    // Set depth validation and hints
    if (calculatorType === 'leveller') {
        const depthInput = form.find('input[name="depth"]');
        if (depthInput.length) {
            const minDepth = config.leveller ? config.leveller.min_depth : 1;
            const maxDepth = config.leveller ? config.leveller.max_depth : 20;
            
            // Set HTML5 validation attributes
            depthInput.attr('min', minDepth);
            depthInput.attr('max', maxDepth);
            depthInput.attr('placeholder', minDepth + '-' + maxDepth);
            depthInput.attr('title', `Depth must be between ${minDepth}mm and ${maxDepth}mm`);
            
            // Add validation on input
            depthInput.on('input blur', function() {
                const val = parseFloat($(this).val());
                if (val > maxDepth) {
                    $(this).val(maxDepth);
                } else if (val < minDepth && val !== 0 && !isNaN(val)) {
                    $(this).val(minDepth);
                }
            });
        }
    }
    
    // Calculate on input change
    form.find('input, select').on('change keyup', function() {
        performCalculation();
    });
    
    // Perform calculation
    function performCalculation() {
        const formData = {};
        let isValid = true;
        
        // Collect form data
        form.find('input:not([type="hidden"]), select').each(function() {
            const val = $(this).val();
            const name = $(this).attr('name');
            
            if ($(this).prop('required') !== false && !val) {
                isValid = false;
            }
            
            formData[name] = val;
        });
        
        // Check if we have minimum required data
        switch (calculatorType) {
            case 'leveller':
                if (!formData.area || !formData.depth) isValid = false;
                break;
            case 'adhesive_powder':
            case 'adhesive_ready':
                if (!formData.area) isValid = false;
                break;
            case 'grout':
                if (!formData.area || !formData.tile_length || !formData.tile_width || 
                    !formData.joint_width || !formData.joint_depth) isValid = false;
                break;
            case 'silicone':
                if (!formData.length) isValid = false;
                break;
            case 'waterproofing':
                if (!formData.area) isValid = false;
                break;
        }
        
        if (!isValid) {
            // Reset to initial state
            resultText.removeClass('calculated').addClass('initial-state').text('Enter values');
            calculationDiv.hide();
            addToJobListBtn.addClass('disabled');
            currentCalculation = null;
            return;
        }
        
        // Send AJAX request
        $.ajax({
            url: dunlopCalc.ajax_url,
            type: 'POST',
            data: {
                action: 'dunlop_calculate',
                nonce: dunlopCalc.nonce,
                product_id: dunlopCalc.product_id,
                calc_type: calculatorType,
                input_data: formData
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response);
                    currentCalculation = response;
                    // Enable button after successful calculation
                    addToJobListBtn.removeClass('disabled');
                } else {
                    console.error('Calculation failed:', response);
                    resultText.removeClass('calculated').addClass('initial-state').text('Calculation error');
                    calculationDiv.hide();
                    addToJobListBtn.addClass('disabled');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                resultText.removeClass('calculated').addClass('initial-state').text('Calculation error');
                calculationDiv.hide();
                addToJobListBtn.addClass('disabled');
            }
        });
    }
    
    // Display results
    function displayResults(data) {
        // Update result text with quantity and unit
        const unitText = data.unit + (data.quantity > 1 ? 's' : '');
        resultText.removeClass('initial-state').addClass('calculated').html(`${data.quantity} ${unitText}`);
        
        // Show details in calculation div
        if (data.details) {
            $('#calc-details').html(data.details);
        }
        
        // Show pack breakdown if available (for optimized pack sizes)
        if (data.pack_breakdown && data.pack_breakdown.length > 0) {
            let breakdownHtml = '<strong>Pack breakdown:</strong><br>';
            data.pack_breakdown.forEach(function(item) {
                breakdownHtml += item.quantity + ' × ' + item.size + 'kg ' + 
                                (item.quantity > 1 ? 'packs' : 'pack') + '<br>';
            });
            $('#pack-breakdown').html(breakdownHtml).show();
        } else {
            $('#pack-breakdown').hide();
        }
        
        // Show the calculation div
        calculationDiv.fadeIn();
    }
    
    // Add to job list functionality
    addToJobListBtn.on('click', function(e) {
        e.preventDefault();
        
        // Check if button is disabled
        if ($(this).hasClass('disabled')) {
            alert('Please complete the calculation first');
            return;
        }
        
        if (!currentCalculation) {
            alert('Please complete the calculation first');
            return;
        }
        
        // Get product title
        const productTitle = $('.product_description h4').first().text();
        
        // Build job list data
        let jobData = {
            product: productTitle,
            product_id: dunlopCalc.product_id,
            quantity: currentCalculation.quantity,
            unit: currentCalculation.unit,
            details: {}
        };
        
        // Add calculation details
        const formData = {};
        form.find('input:not([type="hidden"]), select').each(function() {
            const name = $(this).attr('name');
            const val = $(this).val();
            if (val) {
                formData[name] = val;
            }
        });
        
        // Add specific details based on type
        switch (calculatorType) {
            case 'leveller':
                jobData.details = {
                    area: formData.area + 'm²',
                    depth: formData.depth + 'mm',
                    total: currentCalculation.total_kg + 'kg'
                };
                break;
                
            case 'adhesive_ready':
            case 'adhesive_powder':
                jobData.details = {
                    area: formData.area + 'm²',
                    application: formData.application,
                    total: currentCalculation.total_kg + 'kg'
                };
                if (formData.colour) {
                    jobData.colour = form.find('select[name="colour"] option:selected').text();
                }
                if (currentCalculation.pack_breakdown) {
                    jobData.pack_breakdown = currentCalculation.pack_breakdown;
                }
                break;
                
            case 'grout':
                jobData.details = {
                    area: formData.area + 'm²',
                    tile_size: formData.tile_length + '×' + formData.tile_width + 'mm',
                    joint: formData.joint_width + '×' + formData.joint_depth + 'mm',
                    total: currentCalculation.total_kg + 'kg'
                };
                if (formData.colour) {
                    jobData.colour = form.find('select[name="colour"] option:selected').text();
                }
                break;
                
            case 'silicone':
                jobData.details = {
                    length: formData.length + 'm'
                };
                if (formData.colour) {
                    jobData.colour = form.find('select[name="colour"] option:selected').text();
                }
                break;
                
            case 'waterproofing':
                jobData.details = {
                    area: formData.area + 'm²',
                    coats: formData.coats
                };
                break;
        }
        
        // Get existing job list from localStorage
        let jobList = [];
        try {
            const stored = localStorage.getItem('dunlop_job_list');
            if (stored) {
                jobList = JSON.parse(stored);
            }
        } catch (e) {
            console.error('Error reading job list:', e);
        }
        
        // Add to job list
        jobList.push(jobData);
        
        // Save back to localStorage
        try {
            localStorage.setItem('dunlop_job_list', JSON.stringify(jobList));
            
            // Show success message
            showNotification('Product added to job list!', 'success');
            
            // Update job list counter if exists
            updateJobListCounter(jobList.length);
            
        } catch (e) {
            console.error('Error saving job list:', e);
            showNotification('Error adding to job list', 'error');
        }
    });
    
    // Show notification
    function showNotification(message, type) {
        const notification = $('<div class="dunlop-notification ' + type + '">' + message + '</div>');
        $('body').append(notification);
        
        notification.fadeIn(300).delay(2000).fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    // Update job list counter
    function updateJobListCounter(count) {
        const counter = $('.job-list-counter');
        if (counter.length) {
            counter.text(count);
            if (count > 0) {
                counter.addClass('has-items');
            } else {
                counter.removeClass('has-items');
            }
        }
    }
    
    // Initialize job list counter on page load
    try {
        const stored = localStorage.getItem('dunlop_job_list');
        if (stored) {
            const jobList = JSON.parse(stored);
            updateJobListCounter(jobList.length);
        }
    } catch (e) {
        console.error('Error initializing job list counter:', e);
    }
});