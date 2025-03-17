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
 * JavaScript for enhanced course overview
 *
 * @module     block_enhanced_course_overview/main
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery', 'core/templates', 'block_myoverview/repository', 'block_enhanced_course_overview/repository'],
    function($, Templates, OriginalRepository, Repository) {
        
        return {
            /**
             * Initializes the enhanced course overview functionality
             *
             * @param {Object} config Configuration
             */
            init: function(config) {
    const blockId = config.blockid;
    const container = document.querySelector(`#instance-${blockId}-header + .card-body .enhanced-course-overview-container`);
    
    if (!container) {
        return;
    }
    
    // Get course data passed from PHP
    const allCourses = window.enhancedCourseOverviewCourses || [];
    let filteredCourses = window.enhancedCourseOverviewFilteredCourses || [];
    
    // Initial render of courses
    renderCourses(filteredCourses);
    
    // Set up filter button handlers
    const filterButtons = container.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', e => {
            const btn = e.currentTarget;
            const filterType = btn.dataset.type;
            
            // For timeframe filters, we want to allow selecting one term per year
            if (filterType === 'timeframe') {
                // Find all sibling buttons in the same year-term-group
                const yearGroup = btn.closest('.year-term-group');
                if (yearGroup) {
                    const siblingButtons = yearGroup.querySelectorAll('.term-btn');
                    siblingButtons.forEach(sibling => {
                        if (sibling !== btn) {
                            sibling.classList.remove('active');
                        }
                    });
                }
            }
            
            // Toggle active state
            btn.classList.toggle('active');
            
            // Apply filters and update the course display
            applyFilters();
        });
    });
    
    /**
     * Apply all active filters and update the course display
     */
    function applyFilters() {
        // Collect active filters by type
        const activeFilters = {};
        
        container.querySelectorAll('.filter-btn.active').forEach(button => {
            const type = button.dataset.type;
            const value = button.dataset.value;
            
            if (!activeFilters[type]) {
                activeFilters[type] = [];
            }
            
            activeFilters[type].push(value);
        });
        
        // Apply filters to course list
        if (Object.keys(activeFilters).length === 0) {
            // No filters active, show all courses
            filteredCourses = allCourses;
        } else {
            filteredCourses = filterCourses(allCourses, activeFilters);
        }
        
        // Update the courses display
        renderCourses(filteredCourses);
        
        // Save active filters to user preferences for persistence
        saveActiveFilters(activeFilters);
    }
    
    /**
     * Filter courses based on active filters
     * 
     * @param {Array} courses List of courses to filter
     * @param {Object} activeFilters Active filters grouped by type
     * @return {Array} Filtered courses list
     */
    function filterCourses(courses, activeFilters) {
        return courses.filter(course => {
            let matchesAllTypes = true;
            
            // Process each filter type (AND between different types)
            for (const type in activeFilters) {
                if (!activeFilters[type].length) {
                    continue; // Skip if no filters of this type are active
                }
                
                let matchesType = false;
                
                // Process each filter in this type (OR within same type)
                for (const filterValue of activeFilters[type]) {
                    if (courseMatchesFilter(course, type, filterValue)) {
                        matchesType = true;
                        break;
                    }
                }
                
                if (!matchesType) {
                    matchesAllTypes = false;
                    break;
                }
            }
            
            return matchesAllTypes;
        });
    }
    
    /**
     * Check if a course matches a specific filter
     * 
     * @param {Object} course Course object
     * @param {string} type Filter type
     * @param {string} filterValue Filter value
     * @return {boolean} True if course matches filter
     */
    function courseMatchesFilter(course, type, filterValue) {
        // Use the repository function to check filter match
        return Repository.checkCourseFilter(course, type, filterValue);
    }
    
    /**
     * Render courses using Moodle's template
     * 
     * @param {Array} courses Courses to render
     */
    async function renderCourses(courses) {
        const coursesContainer = container.querySelector('#enhanced-overview-courses-container');
        if (!coursesContainer) return;
        
        try {
            // First, check if we have any courses to show
            if (courses.length === 0) {
                const noCoursesHtml = await Templates.render('core_course/no-courses', {
                    nocoursesimg: M.cfg.wwwroot + '/blocks/myoverview/pix/courses.svg'
                });
                coursesContainer.innerHTML = noCoursesHtml;
                return;
            }
            
            // Get current view preference (card, list, summary)
            const view = getUserViewPreference();
            
            // Prepare course data for templates
            const templateData = {
                courses: courses
            };
            
            // Select template based on view
            let template;
            switch (view) {
                case 'list':
                    template = 'block_myoverview/view-list';
                    break;
                case 'summary':
                    template = 'block_myoverview/view-summary';
                    break;
                case 'card':
                default:
                    template = 'block_myoverview/view-cards';
                    break;
            }
            
            // Render the courses
            const html = await Templates.render(template, templateData);
            coursesContainer.innerHTML = html;
            
            // Initialize course action buttons
            Templates.runTemplateJS(html);
        } catch (error) {
            console.error('Error rendering courses:', error);
        }
    }
    
    /**
     * Get the user's view preference (card, list, summary)
     * 
     * @return {string} View preference
     */
    function getUserViewPreference() {
        // Check for user preference in local storage
        const viewPref = localStorage.getItem('block_myoverview_user_view_preference');
        if (viewPref) {
            return viewPref;
        }
        
        // Default to card view
        return 'card';
    }
    
    /**
     * Save active filters to user preferences
     * 
     * @param {Object} activeFilters Active filters
     */
    function saveActiveFilters(activeFilters) {
        // Convert active filters to a JSON string
        const filtersJson = JSON.stringify(activeFilters);
        
        // Save to local storage for now
        localStorage.setItem('block_enhanced_course_overview_filters', filtersJson);
        
        // Optionally, could use AJAX to save to user preferences on server
        // Repository.saveUserFilters(activeFilters);
    }
        }
    }
});