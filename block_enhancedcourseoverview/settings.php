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
    
    // Filter definitions.
    $defaultfilters = "2023-24\nTerm 1|_*1*_202324\nTerm 2|_*2*_202324\nTerm 3|_*3*_202324\n\n2024-25\nTerm 1|_*1*_202425\nTerm 2|_*2*_202425\nTerm 3|_*3*_202425\n\n2025-26\nTerm 1|_*1*_202526\nTerm 2|_*2*_202526\nTerm 3|_*3*_202526\n\n2026-27\nTerm 1|_*1*_202627\nTerm 2|_*2*_202627\nTerm 3|_*3*_202627";

    $description = get_string('settings:filterdefinitions_desc', 'block_enhancedcourseoverview') .
                  '<br><br><strong>Format:</strong><pre>' .
                  htmlspecialchars($defaultfilters) . '</pre>' .
                  '<br><strong>Note:</strong> Make sure each group name (like "2023-24") appears on its own line, followed by filter definitions in the format "Term X|Pattern". There should be an empty line between groups.' .
                  '<br><strong>Pattern Explanation:</strong> A pattern matches if it appears anywhere in the course title or code, so it only needs to describe the part that identifies the term - it does not need to (and usually should not) also describe your department/module/campus code, whatever that looks like. The default "_*1*_202324" matches Term 1 courses in 2023-24 regardless of what comes before it, e.g. both "MTH2030_A_1_202324" and "BEF3104DA_1F6O25_1_202627"-style codes for their respective years. See "Digit wildcard" below for what "*" does.';

    $settings->add(new admin_setting_configtextarea(
        'block_enhancedcourseoverview/filterdefinitions',
        get_string('settings:filterdefinitions', 'block_enhancedcourseoverview'),
        $description,
        $defaultfilters,
        PARAM_RAW
    ));

    // Default active filters: patterns (matching the pattern column, not the
    // title) that should already be selected when a user opens their
    // dashboard, e.g. the current term.
    $settings->add(new admin_setting_configtextarea(
        'block_enhancedcourseoverview/defaultpatterns',
        get_string('settings:defaultpatterns', 'block_enhancedcourseoverview'),
        get_string('settings:defaultpatterns_desc', 'block_enhancedcourseoverview'),
        '',
        PARAM_RAW
    ));
}