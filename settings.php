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
 * Settings for the enhanced course overview block
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Default filter configuration
    $defaultconfig = <<<EOT
# Type: timeframe
# Display: Year/Term
2022-23
Term 1|_1_202223
Term 2|_2_202223
Term 3|_3_202223
# Type: timeframe
2023-24
Term 1|_1_202324
Term 2|_2_202324
Term 3|_3_202324
# Type: timeframe
2024-25
Term 1|_1_202425
[current] Term 2|_2_202425
Term 3|_3_202425

# Type: campus
# Display: Campus
Main|_A_
Secondary|_B_

# Type: role
# Display: Role
Module Lead|{role:editingteacher}
Assistant|{role:teacher}
Student|{role:student}
EOT;

    // Filter configuration setting
    $settings->add(new admin_setting_configtextarea(
        'block_enhanced_course_overview/filterconfig',
        get_string('filterconfig', 'block_enhanced_course_overview'),
        get_string('filterconfigdesc', 'block_enhanced_course_overview'),
        $defaultconfig,
        PARAM_RAW
    ));
    
    // Display settings
    $settings->add(new admin_setting_heading(
        'block_enhanced_course_overview/displayheading',
        get_string('displayheading', 'block_enhanced_course_overview'),
        ''
    ));
    
    // Integration with original course overview
    $settings->add(new admin_setting_configcheckbox(
        'block_enhanced_course_overview/replacecourseoverview',
        get_string('replacecourseoverview', 'block_enhanced_course_overview'),
        get_string('replacecourseover_desc', 'block_enhanced_course_overview'),
        0
    ));
    
    // Whether to show course counts next to filter options
    $settings->add(new admin_setting_configcheckbox(
        'block_enhanced_course_overview/showcounts',
        get_string('showcounts', 'block_enhanced_course_overview'),
        get_string('showcounts_desc', 'block_enhanced_course_overview'),
        1
    ));
    
    // Position of filters (above or below courses)
    $options = [
        'above' => get_string('above', 'block_enhanced_course_overview'),
        'below' => get_string('below', 'block_enhanced_course_overview')
    ];
    $settings->add(new admin_setting_configselect(
        'block_enhanced_course_overview/filterposition',
        get_string('filterposition', 'block_enhanced_course_overview'),
        get_string('filterposition_desc', 'block_enhanced_course_overview'),
        'above',
        $options
    ));
}