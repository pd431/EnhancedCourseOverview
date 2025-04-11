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
 * Language strings for the Enhanced Course Overview block.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Enhanced Course Overview';
$string['enhancedcourseoverview:addinstance'] = 'Add a new Enhanced Course Overview block';
$string['enhancedcourseoverview:myaddinstance'] = 'Add a new Enhanced Course Overview block to Dashboard';
$string['privacy:metadata'] = 'The Enhanced Course Overview block does not store any personal data.';

// Settings
$string['configtitle'] = 'Block title';
$string['configfilters'] = 'Course filters';
$string['configfilters_desc'] = 'Configure filter groups and items. Empty lines are ignored. 
Lines without a pipe character "|" are treated as group headers. Lines with a pipe have format "Filter Title|text_to_match".
Example:
2023-24
Term 1|_A1_202324
Term 2|_A2_202324

2024-25
Term 1|_A1_202425
Term 2|_A2_202425';

// Filter buttons
$string['allcourses'] = 'All courses';
$string['aria:filter'] = 'Filter courses';
$string['filtercourses'] = 'Filter courses';
$string['nocoursesfound'] = 'No courses found matching the filter';
