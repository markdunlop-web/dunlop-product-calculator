<?php
/**
 * Calculator Display Template
 * 
 * @var array $config Calculator configuration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="calculator_container wp_content dunlop-plugin-calculator">
    <div class="calculator <?php echo esc_attr($config['type']); ?>-calc">
        <h6>CALCULATE HOW MUCH YOU'LL NEED</h6>
        
        <?php switch($config['type']): 
            case 'leveller': ?>
                <form method="post" id="dunlop-calc-form">
                    <div class="area">
                        <label>Area</label>
                        <div>
                            <input type="number" placeholder="10" name="area" step="0.1"/>
                            <span class="append">m²</span>
                        </div>
                    </div>
                    <div class="depth">
                        <label>Depth</label>
                        <div>
                            <input type="number" 
                                   placeholder="<?php echo esc_attr($config['leveller']['min_depth'] . '-' . $config['leveller']['max_depth']); ?>" 
                                   name="depth" 
                                   min="<?php echo esc_attr($config['leveller']['min_depth']); ?>" 
                                   max="<?php echo esc_attr($config['leveller']['max_depth']); ?>"
                                   step="0.5"/>
                            <span class="append">mm</span>
                        </div>
                    </div>
                </form>
                <?php break;
            
            case 'adhesive_powder':
            case 'adhesive_ready': ?>
                <form method="post" id="dunlop-calc-form">
                    <div class="area">
                        <label>Area</label>
                        <div>
                            <input type="number" placeholder="10" name="area" step="0.1"/>
                            <span class="append">m²</span>
                        </div>
                    </div>
                    <div class="application">
                        <label>Application</label>
                        <div>
                            <select name="application">
                                <option value="walls">Walls</option>
                                <option value="floors">Floors</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if ($config['use_colour_attributes'] && !empty($config['available_colours'])): ?>
                    <div class="colour">
                        <label>Colour</label>
                        <div>
                            <select name="colour">
                                <option value="">Select colour...</option>
                                <?php foreach ($config['available_colours'] as $colour): ?>
                                    <option value="<?php echo esc_attr(strtolower(str_replace(' ', '_', $colour))); ?>">
                                        <?php echo esc_html($colour); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
                <?php break;
            
            case 'grout': ?>
                <form method="post" id="dunlop-calc-form" class="grout-form">
                    <div class="area">
                        <label>Area</label>
                        <div>
                            <input type="number" placeholder="10" name="area" step="0.1"/>
                            <span class="append">m²</span>
                        </div>
                    </div>
                    <div class="tile-length">
                        <label>Tile Length</label>
                        <div>
                            <input type="number" placeholder="300" name="tile_length"/>
                            <span class="append">mm</span>
                        </div>
                    </div>
                    <div class="tile-width">
                        <label>Tile Width</label>
                        <div>
                            <input type="number" placeholder="300" name="tile_width"/>
                            <span class="append">mm</span>
                        </div>
                    </div>
                    <div class="joint-width">
                        <label>Joint Width</label>
                        <div>
                            <input type="number" placeholder="3" name="joint_width" step="0.5" min="1" max="15"/>
                            <span class="append">mm</span>
                        </div>
                    </div>
                    <div class="joint-depth">
                        <label>Joint Depth</label>
                        <div>
                            <input type="number" placeholder="10" name="joint_depth"/>
                            <span class="append">mm</span>
                        </div>
                    </div>
                    
                    <?php if ($config['use_colour_attributes'] && !empty($config['available_colours'])): ?>
                    <div class="colour">
                        <label>Colour</label>
                        <div>
                            <select name="colour">
                                <option value="">Select colour...</option>
                                <?php foreach ($config['available_colours'] as $colour): ?>
                                    <option value="<?php echo esc_attr(strtolower(str_replace(' ', '_', $colour))); ?>">
                                        <?php echo esc_html($colour); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
                <?php break;
            
            case 'silicone': ?>
                <form method="post" id="dunlop-calc-form">
                    <input type="hidden" name="coverage" value="<?php echo esc_attr($config['silicone']['linear_coverage']); ?>"/>
                    <div class="length">
                        <label>Total Length</label>
                        <div>
                            <input type="number" placeholder="10" name="length" step="0.1"/>
                            <span class="append">m</span>
                        </div>
                    </div>
                    
                    <?php if ($config['use_colour_attributes'] && !empty($config['available_colours'])): ?>
                    <div class="colour">
                        <label>Colour</label>
                        <div>
                            <select name="colour">
                                <option value="">Select colour...</option>
                                <?php foreach ($config['available_colours'] as $colour): ?>
                                    <option value="<?php echo esc_attr(strtolower(str_replace(' ', '_', $colour))); ?>">
                                        <?php echo esc_html($colour); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
                <div class="bead-info">
                    <small>Based on a 6mm triangular bead. Coverage: <?php echo esc_html($config['silicone']['linear_coverage']); ?>m per 310ml tube.</small>
                </div>
                <?php break;
            
            case 'waterproofing': ?>
                <form method="post" id="dunlop-calc-form">
                    <div class="area">
                        <label>Area</label>
                        <div>
                            <input type="number" placeholder="10" name="area" step="0.1"/>
                            <span class="append">m²</span>
                        </div>
                    </div>
                    <div class="coats">
                        <label>Number of Coats</label>
                        <div>
                            <select name="coats">
                                <option value="1">1 Coat</option>
                                <option value="2" selected>2 Coats (Recommended)</option>
                                <option value="3">3 Coats</option>
                            </select>
                        </div>
                    </div>
                </form>
                <div class="coverage-info">
                    <small>Coverage based on 1.4kg/m² per coat as recommended.</small>
                </div>
                <?php break;
                
            default: ?>
                <p>Calculator type not configured.</p>
                <?php break;
        endswitch; ?>
        
        <!-- Results Container -->
        <div class="results_container">
            <p class="number_of_packs">
                <span id="calc-result" class="initial-state">Enter values</span>
            </p>
            <div class="calculation" style="display:none;">
                <div class="calc-info">
                    <span id="calc-details"></span>
                    <span id="pack-breakdown" class="pack-breakdown"></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add to Job List Button -->
    <a class="button disabled" id="add-to-job-list">ADD TO JOB LIST</a>
</div>