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
 * Library functions for the Enhanced Course Overview block.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Callback to add CSS to the block.
 *
 * @param block_base $block The block object
 * @return array Array with CSS file path
 */
function block_enhancedcourseoverview_get_styles($block) {
    return array('styles.css');
}

/**
 * Parse filter configuration text into structured groups and filters
 *
 * @param string $filterconfig The filter configuration text
 * @return array The parsed filter groups
 */
function block_enhancedcourseoverview_parse_filters($filterconfig) {
    $lines = explode("\n", $filterconfig);
    $groups = [];
    $currentGroup = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue; // Skip empty lines
        }
        
        if (strpos($line, '|') === false) {
            // This is a group header
            $currentGroup = [
                'name' => $line,
                'filters' => []
            ];
            $groups[] = $currentGroup;
        } else if ($currentGroup !== null) {
            // This is a filter item
            list($title, $match) = array_map('trim', explode('|', $line, 2));
            $currentGroup['filters'][] = [
                'title' => $title,
                'match' => $match,
                'id' => 'filter-' . md5($match) // Generate a unique ID for each filter
            ];
        }
    }
    
    return $groups;
}

/**
 * Serves the block help files.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param context $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function block_enhancedcourseoverview_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // For now, we don't have any file areas. This is a placeholder for future use.
    return false;
}

/**
 * This function extends the settings navigation block for the site.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The context object
 */
function block_enhancedcourseoverview_extend_settings_navigation(settings_navigation $settingsnav, $context) {
    // No additional settings navigation items needed yet.
}
