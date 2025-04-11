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
 * Form for editing Enhanced Course Overview block instances.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing Enhanced Course Overview block instances.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enhancedcourseoverview_edit_form extends block_edit_form {

    /**
     * Adds form fields specific to this block.
     *
     * @param moodleform $mform The form being built.
     */
    protected function specific_definition($mform) {
        global $CFG;
        
        // Fields for editing block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_enhancedcourseoverview'));
        $mform->setType('config_title', PARAM_TEXT);
        
        // Default filter configuration
        $defaultfilters = "2023-24\nTerm 1|_A1_202324\nTerm 2|_A2_202324\n\n2024-25\nTerm 1|_A1_202425\nTerm 2|_A2_202425";
        
        // Add separator and heading for filter configuration
        $mform->addElement('static', 'filtersheader', '<h4>' . get_string('configfilters', 'block_enhancedcourseoverview') . '</h4>',
            '<p>' . get_string('configfilters_desc', 'block_enhancedcourseoverview') . '</p>');
        
        // Explicitly add the filter configuration field
        $mform->addElement('textarea', 'config_filters', '', 
            array('rows' => 12, 'cols' => 60, 'class' => 'form-control', 'style' => 'font-family: monospace;'));
        $mform->setType('config_filters', PARAM_RAW);
        $mform->setDefault('config_filters', $defaultfilters);
        $mform->addHelpButton('config_filters', 'configfilters', 'block_enhancedcourseoverview');
        
        // Add a static element with example
        $mform->addElement('static', 'filtershelp', '', 
            '<div class="alert alert-info">
                <strong>Example:</strong><br>
                <pre style="background-color: #f8f9fa; padding: 10px; border-radius: 4px;">2023-24
Term 1|_A1_202324
Term 2|_A2_202324

2024-25
Term 1|_A1_202425
Term 2|_A2_202425</pre>
             </div>');
    }
}