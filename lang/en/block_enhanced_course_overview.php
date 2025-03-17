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
 * Language strings for enhanced course overview
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Enhanced Course Overview';
$string['enhanced_course_overview:addinstance'] = 'Add a new enhanced course overview block';
$string['enhanced_course_overview:myaddinstance'] = 'Add a new enhanced course overview block to Dashboard';

// Settings strings
$string['filterconfig'] = 'Filter configuration';
$string['filterconfigdesc'] = 'Configure the filters that will be available to users. The format is as follows:
<pre>
# Type: timeframe
2024-25
Term 1|_1_202425
Term 2|_2_202425
Term 3|_3_202425

# Type: campus
Main|_A_
Secondary|_B_

# Type: role
Module Lead|{role:editingteacher}
Assistant|{role:teacher}
</pre>
Each filter group starts with a "# Type:" line followed by filter items.<br>
For timeframe filters, put the year on a separate line, then list terms with "Label|Pattern" format.<br>
Add [current] before a term to make it active by default.<br>
For role filters, use special {role:rolename} syntax to match user roles.';

$string['displayheading'] = 'Display settings';
$string['replacecourseoverview'] = 'Replace standard course overview block';
$string['replacecourseover_desc'] = 'If enabled, this block will replace the standard course overview block on the dashboard.';
$string['showcounts'] = 'Show course counts';
$string['showcounts_desc'] = 'Show the number of courses matching each filter option.';
$string['filterposition'] = 'Filter position';
$string['filterposition_desc'] = 'Position of the filter controls relative to the course list.';
$string['above'] = 'Above courses';
$string['below'] = 'Below courses';

// Interface strings
$string['allcourses'] = 'All courses';
$string['nocourses'] = 'No courses match the selected filters';
$string['currentterm'] = 'Current term';
$string['timeframe'] = 'Year/Term';
$string['campus'] = 'Campus';
$string['role'] = 'Role';
$string['clearfilters'] = 'Clear filters';
$string['filtersmultiple'] = '{$a} filters active';
$string['filtersingle'] = '1 filter active';