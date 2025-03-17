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
 * Library functions for enhanced course overview.
 *
 * @package   block_enhanced_course_overview
 * @copyright 2025 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Import constants from the core myoverview block
if (file_exists($CFG->dirroot . '/blocks/myoverview/lib.php')) {
    require_once($CFG->dirroot . '/blocks/myoverview/lib.php');
} else {
    // Fallback definitions if myoverview block is not available
    // Constants for the user preferences grouping options
    define('BLOCK_MYOVERVIEW_GROUPING_ALLINCLUDINGHIDDEN', 'allincludinghidden');
    define('BLOCK_MYOVERVIEW_GROUPING_ALL', 'all');
    define('BLOCK_MYOVERVIEW_GROUPING_INPROGRESS', 'inprogress');
    define('BLOCK_MYOVERVIEW_GROUPING_FUTURE', 'future');
    define('BLOCK_MYOVERVIEW_GROUPING_PAST', 'past');
    define('BLOCK_MYOVERVIEW_GROUPING_FAVOURITES', 'favourites');
    define('BLOCK_MYOVERVIEW_GROUPING_HIDDEN', 'hidden');
    define('BLOCK_MYOVERVIEW_GROUPING_CUSTOMFIELD', 'customfield');
    
    // Sorting options
    define('BLOCK_MYOVERVIEW_SORTING_TITLE', 'title');
    define('BLOCK_MYOVERVIEW_SORTING_LASTACCESSED', 'lastaccessed');
    define('BLOCK_MYOVERVIEW_SORTING_SHORTNAME', 'shortname');
    
    // View options
    define('BLOCK_MYOVERVIEW_VIEW_CARD', 'card');
    define('BLOCK_MYOVERVIEW_VIEW_LIST', 'list');
    define('BLOCK_MYOVERVIEW_VIEW_SUMMARY', 'summary');
    
    // Paging options
    define('BLOCK_MYOVERVIEW_PAGING_12', 12);
    define('BLOCK_MYOVERVIEW_PAGING_24', 24);
    define('BLOCK_MYOVERVIEW_PAGING_48', 48);
    define('BLOCK_MYOVERVIEW_PAGING_96', 96);
    define('BLOCK_MYOVERVIEW_PAGING_ALL', 0);
}

/**
 * Constants for academic year and term filters
 */
define('BLOCK_ENHANCED_COURSE_OVERVIEW_ACADEMIC_YEAR_ALL', 'all');
define('BLOCK_ENHANCED_COURSE_OVERVIEW_ACADEMIC_YEAR_CURRENT', 'current');
define('BLOCK_ENHANCED_COURSE_OVERVIEW_TERM_ALL', 'all');
define('BLOCK_ENHANCED_COURSE_OVERVIEW_TERM_CURRENT', 'current');

/**
 * Constants for role filters
 */
define('BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ALL', 'all');
define('BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_STUDENT', 'student');
define('BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_TEACHER', 'teacher');
define('BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ADMIN', 'admin');

/**
 * Get the current user preferences that are available
 *
 * @return array[] Array representing current options along with defaults
 */
function block_enhanced_course_overview_user_preferences(): array {
    // Include all the standard myoverview preferences as a base
    $preferences = block_myoverview_user_preferences();
    
    // Add our custom preferences
    $preferences['block_enhanced_course_overview_user_academicyear_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_ENHANCED_COURSE_OVERVIEW_ACADEMIC_YEAR_CURRENT,
        'type' => PARAM_ALPHANUMEXT, // Allow for specific year codes like "2024-25"
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );
    
    $preferences['block_enhanced_course_overview_user_term_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_ENHANCED_COURSE_OVERVIEW_TERM_CURRENT,
        'type' => PARAM_ALPHANUMEXT, // Allow for specific term codes
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );
    
    $preferences['block_enhanced_course_overview_user_role_preference'] = array(
        'null' => NULL_NOT_ALLOWED,
        'default' => BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ALL,
        'type' => PARAM_ALPHA,
        'choices' => array(
            BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ALL,
            BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_STUDENT,
            BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_TEACHER,
            BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ADMIN,
        ),
        'permissioncallback' => [core_user::class, 'is_current_user'],
    );

    return $preferences;
}

/**
 * Parse the academic year and term configuration
 *
 * @param string $config The configuration string
 * @return array The parsed configuration structure
 */
function block_enhanced_course_overview_parse_config(string $config): array {
    $result = [
        'years' => [],
        'current_year' => null,
        'current_term' => null,
    ];
    
    $lines = explode("\n", $config);
    $currentYear = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || $line[0] == '#') {
            continue;
        }
        
        // Check if this is a year or term line
        if (strpos($line, '|') === false) {
            // This is a year line
            $isCurrent = (strpos($line, '[current]') !== false);
            $year = trim(str_replace('[current]', '', $line));
            
            $currentYear = $year;
            $result['years'][$year] = [
                'name' => $year,
                'is_current' => $isCurrent,
                'terms' => []
            ];
            
            if ($isCurrent) {
                $result['current_year'] = $year;
            }
        } else {
            // This is a term line
            if ($currentYear === null) {
                // Ignore terms without a parent year
                continue;
            }
            
            $parts = explode('|', $line);
            $termName = trim($parts[0]);
            $termPattern = isset($parts[1]) ? trim($parts[1]) : '';
            
            $isCurrent = (strpos($termName, '[current]') !== false);
            $termName = trim(str_replace('[current]', '', $termName));
            
            $result['years'][$currentYear]['terms'][$termName] = [
                'name' => $termName,
                'pattern' => $termPattern,
                'is_current' => $isCurrent
            ];
            
            if ($isCurrent) {
                $result['current_term'] = $termName;
            }
        }
    }
    
    return $result;
}

/**
 * Get available academic years and terms
 * 
 * @return array Academic years and terms structure
 */
function block_enhanced_course_overview_get_available_years_and_terms(): array {
    $config = get_config('block_enhanced_course_overview', 'academicyears');
    if (empty($config)) {
        // Return default structure if no configuration is available
        return [
            'years' => [
                'current' => [
                    'name' => get_string('currentyear', 'block_enhanced_course_overview'),
                    'is_current' => true,
                    'terms' => [
                        'current' => [
                            'name' => get_string('currentterm', 'block_enhanced_course_overview'),
                            'pattern' => '',
                            'is_current' => true
                        ]
                    ]
                ]
            ],
            'current_year' => 'current',
            'current_term' => 'current'
        ];
    }
    
    return block_enhanced_course_overview_parse_config($config);
}

/**
 * Match a course to academic year and term based on its fullname, shortname, or idnumber
 * 
 * @param stdClass $course The course object
 * @param array $yearTermStructure The year-term structure from get_available_years_and_terms
 * @return array Matching years and terms [year => [terms]]
 */
function block_enhanced_course_overview_match_course_to_terms($course, $yearTermStructure): array {
    $matches = [];
    
    foreach ($yearTermStructure['years'] as $yearKey => $year) {
        foreach ($year['terms'] as $termKey => $term) {
            if (empty($term['pattern'])) {
                continue;
            }
            
            // Try to match the pattern against course fields
            $pattern = '/' . $term['pattern'] . '/i';
            if (preg_match($pattern, $course->fullname) || 
                preg_match($pattern, $course->shortname) || 
                (isset($course->idnumber) && preg_match($pattern, $course->idnumber))) {
                
                if (!isset($matches[$yearKey])) {
                    $matches[$yearKey] = [];
                }
                
                $matches[$yearKey][] = $termKey;
            }
        }
    }
    
    return $matches;
}

/**
 * Get user's primary role in a course
 * 
 * @param int $userid The user ID
 * @param int $courseid The course ID
 * @return string The role constant (STUDENT, TEACHER, or ADMIN)
 */
function block_enhanced_course_overview_get_user_role_in_course($userid, $courseid): string {
    global $DB;
    
    // Get course context
    $coursecontext = context_course::instance($courseid);
    
    // Check for admin role first
    if (has_capability('moodle/course:update', $coursecontext, $userid)) {
        return BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ADMIN;
    }
    
    // Check for teacher role
    if (has_capability('moodle/course:manageactivities', $coursecontext, $userid)) {
        return BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_TEACHER;
    }
    
    // Default to student role
    return BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_STUDENT;
}