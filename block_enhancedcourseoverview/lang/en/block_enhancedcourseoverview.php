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
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Enhanced Course Overview';
$string['enhancedcourseoverview:addinstance'] = 'Add a new Enhanced Course Overview block';
$string['enhancedcourseoverview:myaddinstance'] = 'Add a new Enhanced Course Overview block to Dashboard';

// Settings
$string['settings:heading'] = 'Filter Configuration';
$string['settings:heading_desc'] = 'Configure the filters that will be displayed above the courses.';
$string['settings:filterdefinitions'] = 'Filter Definitions';
$string['settings:filterdefinitions_desc'] = 'Define filters using the following format:<br>
<pre>
Group Name
Filter Title|Pattern to Match

Group Name 2
Filter Title|Pattern to Match
Filter Title 2|Pattern to Match
</pre>
Each line without a pipe (|) character starts a new group. Lines with pipes define a filter button, where the text before the pipe is the button label and the text after is the pattern to match in course titles.<br><br>
<strong>Pattern Matching:</strong> The pattern will match if it appears anywhere in the course title or code. For example, to match Term 1 courses with codes like "WIN1001_A_1_202425", you could use "_A_1_" as the pattern.';