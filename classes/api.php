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
 * API for enhanced course overview.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_enhanced_course_overview;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/enhanced_course_overview/lib.php');

/**
 * API class for enhanced course overview functionality.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    
    /**
     * Get the parsed academic years configuration.
     *
     * @return array The academic years and terms structure
     */
    public static function get_academic_years_config() {
        $config = get_config('block_enhanced_course_overview', 'academicyears');
        if (empty($config)) {
            // Return default structure if no configuration is available
            return self::get_default_academic_years_config();
        }
        
        return self::parse_config($config);
    }
    
    /**
     * Get the default academic years configuration.
     *
     * @return array The default academic years and terms structure
     */
    public static function get_default_academic_years_config() {
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
    
    /**
     * Parse the academic year and term configuration.
     *
     * @param string $config The configuration string
     * @return array The parsed configuration structure
     */
    public static function parse_config($config) {
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
     * Match a course to academic years and terms based on pattern matching.
     *
     * @param \stdClass $course The course object
     * @return array Associative array of matching years and terms
     */
    public static function match_course_to_terms($course) {
        $yearTermStructure = self::get_academic_years_config();
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
     * Get the user's primary role in a course.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return string Role identifier (student, teacher, admin)
     */
    public static function get_user_role_in_course($userid, $courseid) {
        global $DB;
        
        // Get course context
        $coursecontext = \context_course::instance($courseid);
        
        // Check for admin role first
        if (has_capability('moodle/course:update', $coursecontext, $userid)) {
            return \BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ADMIN;
        }
        
        // Check for teacher role
        if (has_capability('moodle/course:manageactivities', $coursecontext, $userid)) {
            return \BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_TEACHER;
        }
        
        // Default to student role
        return \BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_STUDENT;
    }
    
    /**
     * Get courses filtered by academic year, term, and role.
     *
     * @param array $courses Array of course objects
     * @param string $academicyear Academic year filter
     * @param string $term Term filter
     * @param string $role Role filter
     * @return array Filtered array of courses
     */
    public static function filter_courses($courses, $academicyear, $term, $role) {
        global $USER;
        
        $filteredcourses = [];
        
        // If all filters are set to "all", return all courses
        if ($academicyear === \BLOCK_ENHANCED_COURSE_OVERVIEW_ACADEMIC_YEAR_ALL &&
            $term === \BLOCK_ENHANCED_COURSE_OVERVIEW_TERM_ALL &&
            $role === \BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ALL) {
            return $courses;
        }
        
        $yearTermStructure = self::get_academic_years_config();
        
        // Resolve "current" year and term to actual values
        if ($academicyear === \BLOCK_ENHANCED_COURSE_OVERVIEW_ACADEMIC_YEAR_CURRENT) {
            $academicyear = $yearTermStructure['current_year'];
        }
        
        if ($term === \BLOCK_ENHANCED_COURSE_OVERVIEW_TERM_CURRENT) {
            $term = $yearTermStructure['current_term'];
        }
        
        foreach ($courses as $course) {
            $termMatches = self::match_course_to_terms($course);
            $userRole = self::get_user_role_in_course($USER->id, $course->id);
            
            $includeByYear = true;
            $includeByTerm = true;
            $includeByRole = true;
            
            // Filter by academic year
            if ($academicyear !== \BLOCK_ENHANCED_COURSE_OVERVIEW_ACADEMIC_YEAR_ALL) {
                $includeByYear = isset($termMatches[$academicyear]);
            }
            
            // Filter by term
            if ($term !== \BLOCK_ENHANCED_COURSE_OVERVIEW_TERM_ALL && $includeByYear) {
                $includeByTerm = false;
                if (isset($termMatches[$academicyear]) && in_array($term, $termMatches[$academicyear])) {
                    $includeByTerm = true;
                }
            }
            
            // Filter by role
            if ($role !== \BLOCK_ENHANCED_COURSE_OVERVIEW_ROLE_ALL) {
                $includeByRole = ($userRole === $role);
            }
            
            // Include course if it matches all filters
            if ($includeByYear && $includeByTerm && $includeByRole) {
                $filteredcourses[$course->id] = $course;
            }
        }
        
        return $filteredcourses;
    }
}