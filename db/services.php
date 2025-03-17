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
 * External functions and web services for enhanced course overview
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_enhanced_course_overview_get_filter_patterns' => [
        'classname'     => 'block_enhanced_course_overview_external',
        'methodname'    => 'get_filter_patterns',
        'description'   => 'Get filter patterns from configuration',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true
    ],
    'block_enhanced_course_overview_save_filters' => [
        'classname'     => 'block_enhanced_course_overview_external',
        'methodname'    => 'save_filters',
        'description'   => 'Save user filter preferences',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true
    ]
];