<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HA_Powerflow_Modules {

    private static $modules = null;

    public static function get_all() {
        if ( self::$modules === null ) {
            self::$modules = [
                'solar' => [
                    'label'       => 'Solar',
                    'icon'        => '☀️',
                    'id_prefix'   => 'pv',
                    'has_energy'  => true,
                    'has_soc'     => false,
                    'has_eff'     => false,
                    'default_capacity' => 6000,
                    'default_pos' => [ 'x' => 500, 'y' => 150 ],
                    'default_path'=> 'M 500,150 L 500,350'
                ],
                'battery' => [
                    'label'       => 'Battery',
                    'icon'        => '🔋',
                    'id_prefix'   => 'battery',
                    'has_energy'  => true,
                    'has_soc'     => true,
                    'has_eff'     => false,
                    'default_capacity' => 5000,
                    'default_pos' => [ 'x' => 500, 'y' => 550 ],
                    'default_path'=> 'M 500,350 L 500,550'
                ],
                'ev' => [
                    'label'       => 'EV',
                    'icon'        => '🚗',
                    'id_prefix'   => 'ev',
                    'has_energy'  => false,
                    'has_soc'     => true,
                    'has_eff'     => false,
                    'default_capacity' => 7000,
                    'default_pos' => [ 'x' => 750, 'y' => 550 ],
                    'default_path'=> 'M 750,350 L 750,550'
                ],
                'heatpump' => [
                    'label'       => 'Heat Pump',
                    'icon'        => '♨️',
                    'id_prefix'   => 'heatpump',
                    'has_energy'  => true,
                    'has_soc'     => false,
                    'has_eff'     => true,
                    'default_capacity' => 5000,
                    'default_pos' => [ 'x' => 250, 'y' => 550 ],
                    'default_path'=> 'M 250,350 L 250,550'
                ],
                'weather' => [
                    'label'       => 'Weather',
                    'icon'        => '☁️',
                    'id_prefix'   => 'weather',
                    'is_weather'  => true,
                    'default_pos' => [ 'x' => 500, 'y' => 80 ]
                ]
            ];
            
            // Allow other plugins or themes to add/modify modules
            self::$modules = apply_filters( 'ha_powerflow_registered_modules', self::$modules );
        }
        return self::$modules;
    }

    public static function get( $id ) {
        $all = self::get_all();
        return $all[$id] ?? null;
    }
}
