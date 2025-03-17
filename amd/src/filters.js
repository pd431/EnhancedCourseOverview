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
 * JavaScript for the enhanced course overview block.
 *
 * @module     block_enhanced_course_overview/filters
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery'],
    function($) {
        "use strict";

        /**
         * Initialize the academic year and term filters.
         *
         * @param {HTMLElement} root The root element for the block
         */
        function init(root) {
            var $root = $(root);
            
            console.log('Enhanced Course Overview filters initialized');
            
            // Track active filters
            var activeTerms = [];
            var activeRoles = [];
            
            // Set up click handlers for term buttons
            $root.on('click', '.term-btn', function(e) {
                e.preventDefault();
                console.log('Term button clicked:', $(this).data('value'));
                $(this).toggleClass('active');
                applyFilters();
            });
            
            // Set up click handlers for role buttons
            $root.on('click', '.role-btn', function(e) {
                e.preventDefault();
                console.log('Role button clicked:', $(this).data('value'));
                $(this).toggleClass('active');
                applyFilters();
            });
            
            // Set up click handlers for remove filter buttons
            $root.on('click', '.remove-filter', function(e) {
                e.preventDefault();
                console.log('Remove filter clicked:', $(this).data('value'));
                var filterValue = $(this).data('value');
                var filterType = $(this).data('type');
                
                // Find and deactivate the corresponding filter button
                $root.find('.filter-btn[data-type="' + filterType + '"][data-value="' + filterValue + '"]').removeClass('active');
                applyFilters();
            });
            
            /**
             * Apply filters to the course list
             */
            function applyFilters() {
                // Update active filters
                activeTerms = [];
                $root.find('.term-btn.active').each(function() {
                    activeTerms.push($(this).data('value'));
                });
                
                activeRoles = [];
                $root.find('.role-btn.active').each(function() {
                    activeRoles.push($(this).data('value'));
                });
                
                console.log('Active terms:', activeTerms);
                console.log('Active roles:', activeRoles);
                
                // Show all courses if no filters are active
                if (activeTerms.length === 0 && activeRoles.length === 0) {
                    $root.find('.course-listitem').show();
                    return;
                }
                
                // Filter courses
                $root.find('.course-listitem').each(function() {
                    var courseItem = $(this);
                    var showCourse = true;
                    
                    // Apply term filters
                    if (activeTerms.length > 0) {
                        var courseTerms = courseItem.data('terms') ? courseItem.data('terms').split(' ') : [];
                        var termMatch = false;
                        
                        for (var i = 0; i < activeTerms.length; i++) {
                            if (courseTerms.indexOf(activeTerms[i]) !== -1) {
                                termMatch = true;
                                break;
                            }
                        }
                        
                        if (!termMatch) {
                            showCourse = false;
                        }
                    }
                    
                    // Apply role filters
                    if (activeRoles.length > 0 && showCourse) {
                        var courseRole = courseItem.data('role');
                        var roleMatch = false;
                        
                        for (var j = 0; j < activeRoles.length; j++) {
                            if (courseRole === activeRoles[j]) {
                                roleMatch = true;
                                break;
                            }
                        }
                        
                        if (!roleMatch) {
                            showCourse = false;
                        }
                    }
                    
                    // Show or hide the course
                    if (showCourse) {
                        courseItem.show();
                    } else {
                        courseItem.hide();
                    }
                });
                
                // Update active filters display
                updateActiveFiltersDisplay();
            }
            
            /**
             * Update the active filters display
             */
            function updateActiveFiltersDisplay() {
                // Clear current filter tags
                $root.find('.active-filters .filter-tag').remove();
                
                // Add filter tags for terms
                for (var i = 0; i < activeTerms.length; i++) {
                    var termId = activeTerms[i];
                    var termButton = $root.find('.term-btn[data-value="' + termId + '"]');
                    var termName = termButton.text().trim();
                    var yearGroup = termButton.closest('.year-term-group');
                    var yearName = yearGroup.find('.year-btn').text().trim();
                    
                    var filterTag = 
                        '<div class="filter-tag">' +
                            '<span>' + yearName + ' ' + termName + '</span>' +
                            '<button class="remove-filter" data-type="timeframe" data-value="' + termId + '">×</button>' +
                        '</div>';
                    
                    $root.find('.active-filters > span').after(filterTag);
                }
                
                // Add filter tags for roles
                for (var j = 0; j < activeRoles.length; j++) {
                    var roleId = activeRoles[j];
                    if (roleId !== 'all') { // Don't show "All" as a filter tag
                        var roleName = $root.find('.role-btn[data-value="' + roleId + '"]').text().trim();
                        
                        var roleFilterTag = 
                            '<div class="filter-tag">' +
                                '<span>' + roleName + '</span>' +
                                '<button class="remove-filter" data-type="role" data-value="' + roleId + '">×</button>' +
                            '</div>';
                        
                        $root.find('.active-filters > span').after(roleFilterTag);
                    }
                }
                
                // Show/hide the active filters section based on whether there are any filters
                if (activeTerms.length > 0 || (activeRoles.length > 0 && activeRoles.indexOf('all') === -1)) {
                    $root.find('.active-filters').show();
                } else {
                    $root.find('.active-filters').hide();
                }
            }
        }

        return {
            init: init
        };
    }
);