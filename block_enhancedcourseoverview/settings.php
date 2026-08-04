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
    
    // Manual filter definitions. Kept separate from the year generator below
    // so admins can still hand-define one-off groups that aren't year-based.
    $examplefilters = "Custom group\nOnline only|_ONLINE_\nEvening|_EVE_";

    $description = get_string('settings:filterdefinitions_desc', 'block_enhancedcourseoverview') .
                  '<br><br><strong>Format:</strong><pre>' .
                  htmlspecialchars($examplefilters) . '</pre>' .
                  '<br><strong>Note:</strong> Each line without a pipe (|) starts a new group; lines with a pipe define a filter button within it. Leave an empty line between groups.' .
                  '<br><strong>Tip:</strong> For year-based groups (2023-24, 2024-25, ...), use the year generator below instead of typing them out by hand.';

    $settings->add(new admin_setting_configtextarea(
        'block_enhancedcourseoverview/filterdefinitions',
        get_string('settings:filterdefinitions', 'block_enhancedcourseoverview'),
        $description,
        '',
        PARAM_RAW
    ));

    // Year generator: builds one group per academic year automatically,
    // so a new year doesn't require editing the settings by hand.
    $defaultgenerator = "2023|2026|3|Term {n}|_A_{n}_{ay}";

    $generatordescription = get_string('settings:yeargenerator_desc', 'block_enhancedcourseoverview') .
                  '<br><br><strong>Format (one line per range):</strong><pre>startyear|endyear|termcount|title template|pattern template</pre>' .
                  '<strong>Example:</strong><pre>' . htmlspecialchars($defaultgenerator) . '</pre>' .
                  'This generates groups "2023-24" through "2026-27", each with Term 1, Term 2 and Term 3 buttons matching patterns like "_A_1_202324".' .
                  '<br><br><strong>Placeholders:</strong> <code>{n}</code> term number, <code>{ay}</code> full academic year (e.g. 202324), ' .
                  '<code>{y1}</code> start year (e.g. 2023), <code>{y2}</code> two-digit end year (e.g. 24).';

    $settings->add(new admin_setting_configtextarea(
        'block_enhancedcourseoverview/yeargenerator',
        get_string('settings:yeargenerator', 'block_enhancedcourseoverview'),
        $generatordescription,
        $defaultgenerator,
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