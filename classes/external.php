<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External API for enhanced course overview
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/blocks/enhanced_course_overview/lib.php');

/**
 * External API functions
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enhanced_course_overview_external extends external_api {

    /**
     * Returns description of get_filter_patterns parameters
     *
     * @return external_function_parameters
     */
    public static function get_filter_patterns_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get filter patterns from configuration
     *
     * @return array Filter patterns
     */
    public static function get_filter_patterns() {
        $config = block_enhanced_course_overview_get_filter_config();
        return $config;
    }

    /**
     * Returns description of get_filter_patterns return value
     *
     * @return external_description
     */
    public static function get_filter_patterns_returns() {
        return new external_single_structure([
            'timeframe' => new external_multiple_structure(
                new external_single_structure([
                    'year' => new external_value(PARAM_TEXT, 'Academic year'),
                    'terms' => new external_multiple_structure(
                        new external_single_structure([
                            'label' => new external_value(PARAM_TEXT, 'Term label'),
                            'pattern' => new external_value(PARAM_TEXT, 'Pattern to match in course name'),
                            'value' => new external_value(PARAM_TEXT, 'Unique filter value')
                        ])
                    )
                ])
            ),
            'campus' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Campus label'),
                    'pattern' => new external_value(PARAM_TEXT, 'Pattern to match in course name'),
                    'value' => new external_value(PARAM_TEXT, 'Unique filter value')
                ])
            ),
            'role' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Role label'),
                    'pattern' => new external_value(PARAM_TEXT, 'Role pattern'),
                    'value' => new external_value(PARAM_TEXT, 'Unique filter value')
                ])
            )
        ]);
    }

    /**
     * Returns description of save_filters parameters
     *
     * @return external_function_parameters
     */
    public static function save_filters_parameters() {
        return new external_function_parameters([
            'filters' => new external_value(PARAM_RAW, 'JSON encoded filter preferences')
        ]);
    }

    /**
     * Save user filter preferences
     *
     * @param string $filters JSON encoded filter preferences
     * @return array Result status
     */
    public static function save_filters($filters) {
        global $USER;
        
        $params = self::validate_parameters(self::save_filters_parameters(), ['filters' => $filters]);
        
        // Store filter preferences in user preferences
        set_user_preference('block_enhanced_course_overview_filters', $params['filters']);
        
        return ['status' => true];
    }

    /**
     * Returns description of save_filters return value
     *
     * @return external_description
     */
    public static function save_filters_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if filters were saved successfully')
        ]);
    }
}