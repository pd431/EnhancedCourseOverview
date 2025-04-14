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
 * Settings for the Enhanced Course Overview block.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Settings header
    $settings->add(new admin_setting_heading(
        'block_enhancedcourseoverview/heading',
        get_string('settings:heading', 'block_enhancedcourseoverview'),
        get_string('settings:heading_desc', 'block_enhancedcourseoverview')
    ));
    
    // Filter definitions
    $defaultfilters = "2023-24\nTerm 1|_A_1_202324\nTerm 2|_A_2_202324\nTerm 3|_A_3_202324\n\n2024-25\nTerm 1|_A_1_202425\nTerm 2|_A_2_202425\nTerm 3|_A_3_202425\n\n2025-26\nTerm 1|_A_1_202526\nTerm 2|_A_2_202526\nTerm 3|_A_3_202526";

    $description = get_string('settings:filterdefinitions_desc', 'block_enhancedcourseoverview') . 
                  '<br><br><strong>Format:</strong><pre>' . 
                  htmlspecialchars($defaultfilters) . '</pre>' .
                  '<br><strong>Note:</strong> Make sure each group name (like "2023-24") appears on its own line, followed by filter definitions in the format "Term X|_A_X_YYYY". There should be an empty line between groups.' .
                  '<br><strong>Pattern Explanation:</strong> The pattern should match your institution\'s course code format, where "_A_1_202324" matches courses from Term 1 in 2023-24, etc.';


    
    $settings->add(new admin_setting_configtextarea(
        'block_enhancedcourseoverview/filterdefinitions',
        get_string('settings:filterdefinitions', 'block_enhancedcourseoverview'),
        $description,
        $defaultfilters,
        PARAM_RAW
    ));
    
    // Add a static text with debugging info about the current settings value
    $currentvalue = get_config('block_enhancedcourseoverview', 'filterdefinitions');
    if (!empty($currentvalue)) {
        $lines = explode("\n", $currentvalue);
        $linecount = count($lines);
        $debuginfo = '<strong>Current filter definitions have ' . $linecount . ' lines</strong><br>' .
                    'Raw value (for debugging):<pre>' . htmlspecialchars($currentvalue) . '</pre>';
        
        $settings->add(new admin_setting_heading(
            'block_enhancedcourseoverview/debuginfo',
            'Debug Information',
            $debuginfo
        ));
    }
}