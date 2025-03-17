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
    require_once($CFG->dirroot . '/blocks/enhanced_course_overview/lib.php');

    // Academic Year and Term Configuration
    $settings->add(new admin_setting_heading('block_enhanced_course_overview/academicyearconfiguration',
            get_string('academicyearconfiguration', 'block_enhanced_course_overview'),
            get_string('academicyearconfiguration_desc', 'block_enhanced_course_overview')));
    
    // Academic year and term configuration
    $settings->add(new admin_setting_configtextarea(
            'block_enhanced_course_overview/academicyears',
            get_string('academicyears', 'block_enhanced_course_overview'),
            get_string('academicyears_help', 'block_enhanced_course_overview'),
            '# Format: Academic years and terms
# Each line represents either a year group or specific term
# Use [current] to mark the current year/term

[current] 2024-25
Term1_2425|_A1_2425
[current] Term2_2425|_A2_2425
Term3_2425|_A3_2425

2023-24
Term1_2324|_A1_2324
Term2_2324|_A2_2324
Term3_2324|_A3_2324

2022-23
Term1_2223|_A1_2223
Term2_2223|_A2_2223
Term3_2223|_A3_2223',
            PARAM_RAW));
    
    // Role configuration
    $settings->add(new admin_setting_heading('block_enhanced_course_overview/roleconfiguration',
            get_string('roleconfiguration', 'block_enhanced_course_overview'),
            get_string('roleconfiguration_desc', 'block_enhanced_course_overview')));
    
    // Enable role filtering
    $settings->add(new admin_setting_configcheckbox(
            'block_enhanced_course_overview/enablerolefilter',
            get_string('enablerolefilter', 'block_enhanced_course_overview'),
            get_string('enablerolefilter_help', 'block_enhanced_course_overview'),
            1));
    
    // Display Options
    $settings->add(new admin_setting_heading('block_enhanced_course_overview/appearance',
            get_string('appearance', 'admin'),
            ''));
    
    // Display Course Categories
    $settings->add(new admin_setting_configcheckbox(
            'block_enhanced_course_overview/displaycategories',
            get_string('displaycategories', 'block_enhanced_course_overview'),
            get_string('displaycategories_help', 'block_enhanced_course_overview'),
            1));
    
    // Course term badges
    $settings->add(new admin_setting_configcheckbox(
            'block_enhanced_course_overview/displaytermbadges',
            get_string('displaytermbadges', 'block_enhanced_course_overview'),
            get_string('displaytermbadges_help', 'block_enhanced_course_overview'),
            1));
    
    // Course role badges
    $settings->add(new admin_setting_configcheckbox(
            'block_enhanced_course_overview/displayrolebadges',
            get_string('displayrolebadges', 'block_enhanced_course_overview'),
            get_string('displayrolebadges_help', 'block_enhanced_course_overview'),
            1));
    
    // Enable / Disable available layouts.
    $choices = array(
        BLOCK_MYOVERVIEW_VIEW_CARD => get_string('card', 'block_myoverview'),
        BLOCK_MYOVERVIEW_VIEW_LIST => get_string('list', 'block_myoverview'),
        BLOCK_MYOVERVIEW_VIEW_SUMMARY => get_string('summary', 'block_myoverview')
    );
    $settings->add(new admin_setting_configmulticheckbox(
            'block_enhanced_course_overview/layouts',
            get_string('layouts', 'block_myoverview'),
            get_string('layouts_help', 'block_myoverview'),
            $choices,
            $choices));
}
