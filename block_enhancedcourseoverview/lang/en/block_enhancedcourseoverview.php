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

// JavaScript strings.
$string['filter:loading'] = 'Loading all courses...';
$string['filter:nomatches'] = 'No courses match the selected filters.';
$string['filter:showing'] = 'Showing {visible} of {total} courses';

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
<strong>Pattern Matching:</strong> The pattern will match if it appears anywhere in the course title or code. For example, to match Term 1 courses with codes like "WIN1001_A_1_202425", you could use "_A_1_" as the pattern.<br><br>
<strong>Digit wildcard:</strong> Use "*" in a pattern to match a run of one or more digits. This is needed for course codes that combine multiple terms into one digit group, which a plain pattern can\'t match at all: a code like "CHE3005_A_23_202425" (spanning Term 2 and Term 3) contains neither "_A_2_202425" nor "_A_3_202425" as a substring. Instead use "_A_*2*_202425" for Term 2 and "_A_*3*_202425" for Term 3 - both will match "_A_23_202425", and each still matches a single-term code like "_A_2_202425" too. "*" only ever matches digits, never letters or other text, so it can\'t accidentally spill past the surrounding underscores.<br><br>
<strong>Regex patterns:</strong> For anything the digit wildcard can\'t express (for example, if the "A" in your codes can vary), wrap a pattern in forward slashes to match it as a full regular expression instead, optionally followed by flags, e.g. "/pattern/i". For example "/_[AB]_[0-9]*2[0-9]*_202425/" matches Term 2 whether the code has "_A_" or "_B_" in that position.';

$string['settings:defaultpatterns'] = 'Default active filters';
$string['settings:defaultpatterns_desc'] = 'Comma or newline separated list of exact patterns (matching the pattern column above, not the button title) that should already be active when a user opens their dashboard, for example the current term. Leave empty for no default. Applies every time the block renders, it is not a per-user preference.';