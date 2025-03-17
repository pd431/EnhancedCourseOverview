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
 * Helper functions for enhanced course overview
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Parse the filter configuration from settings
 *
 * @return array Structured filter configuration
 */
function block_enhanced_course_overview_get_filter_config() {
    $config = get_config('block_enhanced_course_overview', 'filterconfig');
    return block_enhanced_course_overview_parse_filter_config($config);
}

/**
 * Parse raw filter configuration text into structured format
 *
 * @param string $config Raw configuration text
 * @return array Structured filter data
 */
function block_enhanced_course_overview_parse_filter_config($config) {
    $lines = explode("\n", $config);
    $filters = [
        'timeframe' => [],
        'campus' => [],
        'role' => []
    ];
    
    $currentType = null;
    $currentYear = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            // Skip comments and empty lines
            continue;
        }
        
        // Check for type header
        if (preg_match('/^# Type: (.+)$/i', $line, $matches)) {
            $currentType = trim(strtolower($matches[1]));
            continue;
        }
        
        if ($currentType === 'timeframe') {
            // Year headers don't have a pipe
            if (strpos($line, '|') === false) {
                $currentYear = $line;
                if (!isset($filters['timeframe'][$currentYear])) {
                    $filters['timeframe'][$currentYear] = [];
                }
                continue;
            }
            
            // Term entries use label|pattern format
            list($label, $pattern) = explode('|', $line, 2);
            $filters['timeframe'][$currentYear][] = [
                'label' => trim($label),
                'pattern' => trim($pattern),
                'value' => block_enhanced_course_overview_generate_filter_value($label, $currentYear)
            ];
        } else if ($currentType && strpos($line, '|') !== false) {
            // Other filter types with label|pattern format
            list($label, $pattern) = explode('|', $line, 2);
            $filters[$currentType][] = [
                'label' => trim($label),
                'pattern' => trim($pattern),
                'value' => block_enhanced_course_overview_generate_filter_value($label, $currentType)
            ];
        }
    }
    
    return $filters;
}

/**
 * Generate a unique filter value from a label and group
 *
 * @param string $label The filter label
 * @param string $group The filter group (year or type)
 * @return string Unique filter value
 */
function block_enhanced_course_overview_generate_filter_value($label, $group) {
    // Replace spaces with underscores and remove any special characters
    $label = preg_replace('/[^a-zA-Z0-9]/', '', $label);
    $group = preg_replace('/[^a-zA-Z0-9]/', '', $group);
    
    // For terms, format like "Term1_2425"
    if (preg_match('/term/i', $label)) {
        return $label . '_' . $group;
    }
    
    // For other filters, format like "Main_campus"
    return $label . '_' . $group;
}

/**
 * Get all available filters based on the user's courses
 *
 * @param array $courses User's courses
 * @param array $config Filter configuration
 * @return array Available filters for the user
 */
function block_enhanced_course_overview_get_available_filters($courses, $config) {
    global $USER;
    
    // Initialize filter counts to track which ones are relevant
    $filterCounts = [
        'timeframe' => [],
        'campus' => [],
        'role' => []
    ];
    
    // Check which patterns match in the courses
    foreach ($courses as $course) {
        // Check timeframe (year/term) filters
        foreach ($config['timeframe'] as $year => $terms) {
            $yearMatched = false;
            
            foreach ($terms as $term) {
                if (preg_match('/' . preg_quote($term['pattern'], '/') . '/i', $course->fullname)) {
                    if (!isset($filterCounts['timeframe'][$year][$term['value']])) {
                        $filterCounts['timeframe'][$year][$term['value']] = 0;
                    }
                    $filterCounts['timeframe'][$year][$term['value']]++;
                    $yearMatched = true;
                }
            }
            
            // Keep track of matched years even if no specific term matched
            if ($yearMatched && !isset($filterCounts['timeframe'][$year]['_year_total'])) {
                $filterCounts['timeframe'][$year]['_year_total'] = 0;
            }
            if ($yearMatched) {
                $filterCounts['timeframe'][$year]['_year_total']++;
            }
        }
        
        // Check campus filters
        foreach ($config['campus'] as $campus) {
            if (preg_match('/' . preg_quote($campus['pattern'], '/') . '/i', $course->fullname)) {
                if (!isset($filterCounts['campus'][$campus['value']])) {
                    $filterCounts['campus'][$campus['value']] = 0;
                }
                $filterCounts['campus'][$campus['value']]++;
            }
        }
        
        // Check role filters
        $context = \context_course::instance($course->id);
        foreach ($config['role'] as $roleFilter) {
            // Check if pattern is a special role pattern
            if (preg_match('/\{role:([a-z]+)\}/', $roleFilter['pattern'], $matches)) {
                $rolename = $matches[1];
                $roleid = get_role_id($rolename);
                
                if ($roleid && user_has_role_assignment($USER->id, $roleid, $context->id)) {
                    if (!isset($filterCounts['role'][$roleFilter['value']])) {
                        $filterCounts['role'][$roleFilter['value']] = 0;
                    }
                    $filterCounts['role'][$roleFilter['value']]++;
                }
            }
        }
    }
    
    // Build available filters based on counts
    $available = [
        'timeframeFilters' => [
            'years' => []
        ],
        'campusFilters' => [
            'isAvailable' => !empty($filterCounts['campus']),
            'items' => []
        ],
        'roleFilters' => [
            'isAvailable' => !empty($filterCounts['role']),
            'items' => []
        ]
    ];
    
    // Process timeframe filters
    foreach ($config['timeframe'] as $year => $terms) {
        // Only include years that have matching courses
        if (isset($filterCounts['timeframe'][$year]) && $filterCounts['timeframe'][$year]['_year_total'] > 0) {
            $yearTerms = [];
            
            foreach ($terms as $term) {
                // Only include terms that have matching courses
                if (isset($filterCounts['timeframe'][$year][$term['value']]) && 
                    $filterCounts['timeframe'][$year][$term['value']] > 0) {
                    
                    // Check if this should be active by default
                    $isActive = false;
                    if (isset($term['pattern']) && strpos($term['pattern'], '[current]') !== false) {
                        $isActive = true;
                    }
                    
                    $yearTerms[] = [
                        'label' => $term['label'],
                        'value' => $term['value'],
                        'active' => $isActive
                    ];
                }
            }
            
            // Only add years that have at least one available term
            if (!empty($yearTerms)) {
                $available['timeframeFilters']['years'][] = [
                    'label' => $year,
                    'terms' => $yearTerms
                ];
            }
        }
    }
    
    // Process campus filters
    foreach ($config['campus'] as $campus) {
        if (isset($filterCounts['campus'][$campus['value']]) && 
            $filterCounts['campus'][$campus['value']] > 0) {
            
            $available['campusFilters']['items'][] = [
                'label' => $campus['label'],
                'value' => $campus['value'],
                'active' => false
            ];
        }
    }
    
    // Process role filters
    foreach ($config['role'] as $role) {
        if (isset($filterCounts['role'][$role['value']]) && 
            $filterCounts['role'][$role['value']] > 0) {
            
            $available['roleFilters']['items'][] = [
                'label' => $role['label'],
                'value' => $role['value'],
                'active' => false
            ];
        }
    }
    
    // If we have only one role, don't show role filters
    if (count($available['roleFilters']['items']) <= 1) {
        $available['roleFilters']['isAvailable'] = false;
    }
    
    return $available;
}

/**
 * Get role ID from role short name
 *
 * @param string $rolename Role short name
 * @return int|false Role ID or false if not found
 */
function get_role_id($rolename) {
    global $DB;
    return $DB->get_field('role', 'id', ['shortname' => $rolename]);
}

/**
 * Apply filters to course list
 *
 * @param array $courses List of courses
 * @param array $activeFilters Active filters
 * @return array Filtered courses
 */
function block_enhanced_course_overview_filter_courses($courses, $activeFilters) {
    global $USER;
    
    if (empty($activeFilters)) {
        return $courses;
    }
    
    $result = [];
    $config = block_enhanced_course_overview_get_filter_config();
    
    foreach ($courses as $course) {
        $matchesAllTypes = true;
        
        // Group filters by type
        $filtersByType = [
            'timeframe' => isset($activeFilters['timeframe']) ? $activeFilters['timeframe'] : [],
            'campus' => isset($activeFilters['campus']) ? $activeFilters['campus'] : [],
            'role' => isset($activeFilters['role']) ? $activeFilters['role'] : []
        ];
        
        // Process each filter type (AND between different types)
        foreach ($filtersByType as $type => $filters) {
            // Skip if no filters of this type are active
            if (empty($filters)) {
                continue;
            }
            
            $matchesType = false;
            
            // Process each filter in this type (OR within same type)
            foreach ($filters as $filterValue) {
                if ($type === 'timeframe') {
                    // Find the matching pattern for this filter value
                    $pattern = null;
                    foreach ($config['timeframe'] as $year => $terms) {
                        foreach ($terms as $term) {
                            if ($term['value'] === $filterValue) {
                                $pattern = $term['pattern'];
                                break 2;
                            }
                        }
                    }
                    
                    if ($pattern && preg_match('/' . preg_quote($pattern, '/') . '/i', $course->fullname)) {
                        $matchesType = true;
                        break;
                    }
                } else if ($type === 'campus') {
                    // Find the matching pattern for this campus
                    $pattern = null;
                    foreach ($config['campus'] as $campus) {
                        if ($campus['value'] === $filterValue) {
                            $pattern = $campus['pattern'];
                            break;
                        }
                    }
                    
                    if ($pattern && preg_match('/' . preg_quote($pattern, '/') . '/i', $course->fullname)) {
                        $matchesType = true;
                        break;
                    }
                } else if ($type === 'role') {
                    // Find the matching role for this filter value
                    $rolePattern = null;
                    foreach ($config['role'] as $role) {
                        if ($role['value'] === $filterValue) {
                            $rolePattern = $role['pattern'];
                            break;
                        }
                    }
                    
                    if ($rolePattern && preg_match('/\{role:([a-z]+)\}/', $rolePattern, $matches)) {
                        $rolename = $matches[1];
                        $roleid = get_role_id($rolename);
                        $context = \context_course::instance($course->id);
                        
                        if ($roleid && user_has_role_assignment($USER->id, $roleid, $context->id)) {
                            $matchesType = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$matchesType) {
                $matchesAllTypes = false;
                break;
            }
        }
        
        if ($matchesAllTypes) {
            $result[] = $course;
        }
    }
    
    return $result;
}