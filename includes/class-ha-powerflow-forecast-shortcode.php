<?php
/**
 * HA Powerflow – Solar Forecast Shortcode
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Forecast_Shortcode {

    public static function init() {
        add_shortcode( 'ha_solar_forecast', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts = [] ) {
        $o = get_option( 'ha_powerflow_options', [] );
        
        // Enqueue base styles (reuse existing ones)
        wp_enqueue_style( 'ha-powerflow-style' );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'ha-powerflow-js' );

        $instance_id = 'ha-pf-forecast-' . wp_generate_password( 4, false );
        
        // Prepare localized data if not already done by main shortcode
        // (Usually handled by the main plugin file or first shortcode)

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $instance_id ); ?>" class="ha-pf-forecast-container ha-pf-standalone-module">
            <div class="ha-pf-module-frame">
                <div class="ha-pf-module-header">
                    <span class="ha-pf-mod-label">Solar</span>
                    <span class="ha-pf-mod-sublabel">SOLAR GENERATION</span>
                </div>
                
                <div class="ha-pf-module-body">
                    <div class="ha-pf-gauge-wrap">
                        <svg viewBox="0 0 240 280" class="ha-pf-solar-svg">
                            <defs>
                                <linearGradient id="hapf-solar-grad" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#f0a500" stop-opacity="0.2" />
                                    <stop offset="100%" stop-color="#f0a500" stop-opacity="0" />
                                </linearGradient>
                                <filter id="hapf-glow-gold">
                                    <feGaussianBlur stdDeviation="3" result="blur" />
                                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                                </filter>
                                <filter id="hapf-glow-green">
                                    <feGaussianBlur stdDeviation="2" result="blur" />
                                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                                </filter>
                            </defs>
                            
                            <!-- Top Bar Telemetry -->
                            <g class="ha-pf-top-telemetry">
                                <rect x="25" y="10" width="40" height="10" rx="2" fill="rgba(255,255,255,0.05)" />
                                <rect x="27" y="12" width="10" height="6" rx="1" fill="#349bef" class="ha-pf-pwr-bar" />
                                <rect x="39" y="12" width="10" height="6" rx="1" fill="#349bef" class="ha-pf-pwr-bar" />
                                <text x="25" y="32" font-size="9" fill="#8899bb" font-family="'Exo 2'">Power</text>
                                
                                <g transform="translate(185, 10)" class="ha-pf-bat-status">
                                    <rect x="0" y="0" width="25" height="12" rx="2" fill="none" stroke="#22c55e" stroke-width="1.5" />
                                    <rect x="26" y="3" width="2" height="6" fill="#22c55e" />
                                    <rect x="2" y="2" width="15" height="8" fill="#22c55e" class="ha-pf-bat-fill" />
                                    <text x="25" y="24" text-anchor="end" font-size="10" fill="#22c55e" font-family="'Orbitron'" class="ha-pf-bat-text">94%</text>
                                </g>
                            </g>

                            <!-- Main Rings -->
                            <circle cx="120" cy="140" r="90" class="ha-pf-ring-bg" />
                            <circle cx="120" cy="140" r="90" class="ha-pf-ring-progress" 
                                    stroke-dasharray="0 566" transform="rotate(-90 120 140)" />                            <!-- Values (Centered) -->
                            <text x="120" y="110" text-anchor="middle" class="ha-pf-val-sub">ACTUAL</text>
                            <text x="120" y="135" text-anchor="middle" class="ha-pf-val-main ha-pf-actual-val">0.0 kWh</text>
                            
                            <text x="120" y="155" text-anchor="middle" class="ha-pf-val-sub">FORECAST</text>
                            <text x="120" y="175" text-anchor="middle" class="ha-pf-val-sec ha-pf-forecast-val">0.0 kWh (Expected)</text>

                            <!-- Bottom Telemetry -->
                            <g transform="translate(150, 255)" class="ha-pf-bottom-telemetry">
                                <text x="80" y="0" text-anchor="end" font-size="11" fill="#22c55e" font-family="'Orbitron'" class="ha-pf-usage-pwr">0.0 W</text>
                                <text x="80" y="14" text-anchor="end" font-size="10" fill="#8899bb" font-family="'Exo 2'" class="ha-pf-usage-energy">0.0 kWh</text>
                                <text x="0" y="0" font-size="10" fill="#8899bb" font-family="'Exo 2'">Power</text>
                                <text x="0" y="14" font-size="10" fill="#8899bb" font-family="'Exo 2'">Usage</text>
                            </g>
                        </svg>
>

                        <!-- Floating Percentage Badge -->
                        <div class="ha-pf-percent-badge">
                            <span class="ha-pf-percentage">0%</span>
                        </div>
                    </div>
                </div>

                <div class="ha-pf-module-footer">
                    <div class="ha-pf-status-line">CURRENTLY ACTIVE - GENERATING</div>
                    <div class="ha-pf-stats-line">Progress: <span class="ha-pf-percentage">0%</span> (<span class="ha-pf-actual-val">0.0</span> / <span class="ha-pf-forecast-val-raw">0.0</span> kWh)</div>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            $(document).ready(function() {
                var $container = $('#<?php echo $instance_id; ?>');
                
                function updateForecast() {
                    if (typeof lastData === 'undefined' || lastData === null) {
                        fetchStandalone();
                        return;
                    }
                    renderData(lastData);
                }

                function fetchStandalone() {
                    if (!window.haPowerflow || !window.haPowerflow.restUrl) return;
                    $.ajax({
                        url: window.haPowerflow.restUrl,
                        method: 'GET',
                        success: function(d) {
                            renderData(d);
                        }
                    });
                }                function formatPower(val) {
                    var n = parseFloat(val);
                    if (isNaN(n)) return '—';
                    return Math.round(n) + ' W';
                }

                function renderData(d) {
                    if (!d) return;
                    var actual = parseFloat(d.pv_energy ? d.pv_energy.state : 0) || 0;
                    var forecast = parseFloat(d.solar_forecast ? d.solar_forecast.state : 0) || 0;
                    var loadPwr  = parseFloat(d.load_power ? d.load_power.state : 0) || 0;
                    var loadEng  = parseFloat(d.load_energy ? d.load_energy.state : 0) || 0;
                    var batSoc   = parseFloat(d.battery_soc ? d.battery_soc.state : 0) || 0;
                    
                    var percent = forecast > 0 ? Math.min(100, (actual / forecast) * 100) : 0;
                    
                    // Update Ring (2 * PI * R where R=90)
                    var circ = 2 * Math.PI * 90;
                    $container.find('.ha-pf-ring-progress').css('stroke-dasharray', (circ * (percent/100)) + ' ' + circ);
                    
                    // Update Main Solar Text
                    $container.find('.ha-pf-actual-val').text(actual.toFixed(1) + ' kWh');
                    $container.find('.ha-pf-forecast-val').text(forecast.toFixed(1) + ' kWh (Expected)');
                    $container.find('.ha-pf-forecast-val-raw').text(forecast.toFixed(1));
                    $container.find('.ha-pf-percentage').text(percent.toFixed(0) + '%');

                    // Update Auxiliary Telemetry
                    $container.find('.ha-pf-bat-text').text(batSoc.toFixed(0) + '%');
                    $container.find('.ha-pf-bat-fill').attr('width', (batSoc / 100) * 21); // max width 21
                    $container.find('.ha-pf-usage-pwr').text(formatPower(loadPwr));
                    $container.find('.ha-pf-usage-energy').text(loadEng.toFixed(1) + ' kWh');
                    
                    // Power bars (visual only, linked to current solar power)
                    var pvPwr = parseFloat(d.pv_power ? d.pv_power.state : 0) || 0;
                    $container.find('.ha-pf-pwr-bar').css('opacity', pvPwr > 50 ? 1 : 0.3);
                    
                    // Conditional Status Line Visibility
                    var $statusLine = $container.find('.ha-pf-status-line');
                    if (pvPwr < 20) {
                        $statusLine.css('visibility', 'hidden');
                    } else {
                        $statusLine.css('visibility', 'visible');
                    }
                }
                
                updateForecast();
                setInterval(updateForecast, 5000);
            });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }
}
HA_Powerflow_Forecast_Shortcode::init();
