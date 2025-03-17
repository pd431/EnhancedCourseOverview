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
 * Repository functions for enhanced course overview
 *
 * @module     block_enhanced_course_overview/repository
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

// Cache for filter patterns to avoid repeated requests
let filterPatternsCache = null;

/**
 * Get filter patterns from server
 *
 * @return {Promise} Promise resolved with filter patterns
 */
export const getFilterPatterns = () => {
    if (filterPatternsCache !== null) {
        return Promise.resolve(filterPatternsCache);
    }

    const request = {
        methodname: 'block_enhanced_course_overview_get_filter_patterns',
        args: {}
    };

    return Ajax.call([request])[0]
        .then(patterns => {
            filterPatternsCache = patterns;
            return patterns;
        })
        .catch(error => {
            console.error('Error fetching filter patterns:', error);
            // Return empty patterns on error
            return {
                timeframe: {},
                campus: [],
                role: []
            };
        });
};

/**
 * Check if a course matches a specific filter
 *
 * @param {Object} course Course object
 * @param {string} type Filter type (timeframe, campus, role)
 * @param {string} value Filter value to check against
 * @return {boolean} True if the course matches the filter
 */
export const checkCourseFilter = (course, type, value) => {
    // Since we have the patterns included in the page, we can do client-side filtering
    // For a real implementation, we would fetch these patterns from the server
    
    // Extract year and term from the filter value (e.g., Term1_2425)
    let pattern = null;
    
    // This is a simplified implementation - in a real version this would use the patterns from the server
    if (type === 'timeframe') {
        // Extract term and year from the value (e.g., "Term1_2425")
        const termMatch = value.match(/^Term(\d)_(\d{4})$/);
        if (termMatch) {
            const term = termMatch[1];
            const year = termMatch[2];
            // Create a pattern to match in the course name
            pattern = `_${term}_${year}`;
            return course.fullname.indexOf(pattern) !== -1;
        }
    } else if (type === 'campus') {
        // For campus filters (e.g. "Main_campus" -> "_A_")
        if (value === 'Main_campus') {
            return course.fullname.indexOf('_A_') !== -1;
        } else if (value === 'Secondary_campus') {
            return course.fullname.indexOf('_B_') !== -1;
        }
    } else if (type === 'role') {
        // For role filters, we need server-side help
        // For a simplified client implementation, we'll check if there's role data in the course object
        if (course.roles && Array.isArray(course.roles)) {
            if (value === 'ModuleLead_role') {
                return course.roles.includes('editingteacher');
            } else if (value === 'Assistant_role') {
                return course.roles.includes('teacher');
            } else if (value === 'Student_role') {
                return course.roles.includes('student');
            }
        }
        // If no role data is available, assume it doesn't match
        return false;
    }
    
    // Default to not matching if no pattern was found
    return false;
};

/**
 * Save user filter preferences
 *
 * @param {Object} filters Active filters
 * @return {Promise} Promise resolved when preferences are saved
 */
const saveUserFilters = (filters) => {
    const request = {
        methodname: 'block_enhanced_course_overview_save_filters',
        args: {
            filters: JSON.stringify(filters)
        }
    };

    return Ajax.call([request])[0]
        .catch(error => {
            console.error('Error saving filter preferences:', error);
        });
};

// Return the public methods
return {
    getFilterPatterns: getFilterPatterns,
    checkCourseFilter: checkCourseFilter,
    saveUserFilters: saveUserFilters
};

});