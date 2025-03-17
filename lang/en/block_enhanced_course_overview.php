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
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Enhanced Course Overview';
$string['enhancedcourseoverview:addinstance'] = 'Add a new Enhanced Course Overview block';
$string['enhancedcourseoverview:myaddinstance'] = 'Add a new Enhanced Course Overview block to Dashboard';

// Academic year and term configuration.
$string['academicyearconfiguration'] = 'Academic Year and Term Configuration';
$string['academicyearconfiguration_desc'] = 'Configure academic years and terms for course filtering.';
$string['academicyears'] = 'Academic Years';
$string['academicyears_help'] = 'Configure academic years and terms in the format shown below. Each academic year is on a separate line, followed by terms. Use [current] to mark the current year or term. For each term, you can specify a pattern to match course names or codes.';
$string['currentyear'] = 'Current Year';
$string['currentterm'] = 'Current Term';
$string['allyears'] = 'All Years';
$string['allterms'] = 'All Terms';

// Role configuration.
$string['roleconfiguration'] = 'Role Configuration';
$string['roleconfiguration_desc'] = 'Configure role filtering options.';
$string['enablerolefilter'] = 'Enable Role Filter';
$string['enablerolefilter_help'] = 'Enable filtering of courses based on the user\'s role in each course.';
$string['allroles'] = 'All Roles';
$string['role_student'] = 'Student';
$string['role_teacher'] = 'Teacher';
$string['role_admin'] = 'Course Admin';

// Display options.
$string['displaycategories'] = 'Display Categories';
$string['displaycategories_help'] = 'Display the course category on course cards.';
$string['displaytermbadges'] = 'Display Term Badges';
$string['displaytermbadges_help'] = 'Display badges showing which term(s) each course belongs to.';
$string['displayrolebadges'] = 'Display Role Badges';
$string['displayrolebadges_help'] = 'Display badges showing the user\'s role in each course.';

// Other strings.
$string['nocoursesfound'] = 'No courses found matching the selected filters (Year: {$a->year}, Term: {$a->term}, Role: {$a->role}).';
