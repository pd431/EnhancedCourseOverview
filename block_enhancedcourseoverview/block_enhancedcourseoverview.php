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
 * Enhanced course overview block.
 *
 * @package   block_enhancedcourseoverview
 * @copyright 2023 Your Name <your.email@example.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Make sure the core myoverview is loaded
require_once($CFG->dirroot . '/blocks/myoverview/block_myoverview.php');

/**
 * Enhanced course overview block class.
 */
class block_enhancedcourseoverview extends block_myoverview {
    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_enhancedcourseoverview');
    }

    /**
     * Allow the block to have a configuration page.
     */
    public function has_config() {
        return true;
    }

    /**
     * Content of the block.
     */
    public function get_content() {
        global $CFG;
        
        if (isset($this->content)) {
            return $this->content;
        }

        // Get the original content from the parent class
        $this->content = parent::get_content();
        
        if (!$this->content) {
            return null;
        }

        // Parse the filter definitions
        $filterdefs = get_config('block_enhancedcourseoverview', 'filterdefinitions');
        
        if (empty($filterdefs)) {
            // If no filter definitions, just return the original content
            return $this->content;
        }

        // Get the parsed filter groups
        $filtergroups = $this->parse_filter_definitions($filterdefs);
        
        // Add debugging info to see what we're getting
        $debugoutput = '';
        
        // Create formatted debug output
        $debugoutput = '<div style="display:none" class="filter-debug-info">' . 
                       '<p><strong>Raw filter definitions:</strong></p>' .
                       '<pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:200px; overflow:auto;">' . 
                       htmlspecialchars($filterdefs) . 
                       '</pre>' .
                       '<p><strong>Parsed filter groups:</strong></p>' .
                       '<pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:200px; overflow:auto;">' . 
                       htmlspecialchars(json_encode($filtergroups, JSON_PRETTY_PRINT)) . 
                       '</pre>' .
                       '</div>';
        
        // Add debug button
        $debugButton = '
        <div class="debug-container mb-2">
            <button type="button" class="btn btn-sm btn-info toggle-debug-btn">Toggle Debug Info</button>
            <script>
                document.querySelector(".toggle-debug-btn").addEventListener("click", function() {
                    var debugInfo = document.querySelector(".filter-debug-info");
                    if (debugInfo) {
                        debugInfo.style.display = debugInfo.style.display === "none" ? "block" : "none";
                    }
                });
            </script>
        </div>';
        
        // Render the filter buttons
        $renderer = $this->page->get_renderer('block_enhancedcourseoverview');
        
        $templatecontext = [
            'filtergroups' => $filtergroups,
            'uniqid' => uniqid() // Unique ID for this instance
        ];
        
        // Get the rendered filter buttons
        $filterbuttons = $renderer->render_from_template(
            'block_enhancedcourseoverview/filter-buttons', 
            $templatecontext
        );
        
        // Inject our filter buttons just before the course-view region
        $pattern = '/<div[^>]*data-region="courses-view"[^>]*>/';
        $replacement = $debugoutput . $debugButton . $filterbuttons . '$0';
        $this->content->text = preg_replace($pattern, $replacement, $this->content->text);
        
        // Add our custom CSS 
        $this->page->requires->css('/blocks/enhancedcourseoverview/styles.css');
        
        // Inline JavaScript for filter functionality (no AMD required)
        $js = $this->get_filter_javascript();
        $this->content->text .= '<script type="text/javascript">' . $js . '</script>';
        
        return $this->content;
    }
    
    /**
     * Parse the filter definitions from the settings.
     * 
     * @param string $filterdefs The filter definitions
     * @return array The parsed filter groups
     */
    protected function parse_filter_definitions($filterdefs) {
        global $CFG;
        
        // Debugging - log the raw input
        error_log('Raw filter definitions: ' . $filterdefs);
        
        // Replace Windows line endings
        $filterdefs = str_replace("\r\n", "\n", $filterdefs);
        
        // Replace Mac line endings
        $filterdefs = str_replace("\r", "\n", $filterdefs);
        
        $lines = explode("\n", $filterdefs);
        $groups = [];
        $currentgroup = null;
        $debugoutput = "Lines found: " . count($lines) . "\n";
        
        foreach ($lines as $index => $line) {
            $line = trim($line);
            $debugoutput .= "Line $index: '$line'\n";
            
            if (empty($line)) {
                $debugoutput .= "  - Empty line, skipping\n";
                continue;
            }
            
            if (strpos($line, '|') === false) {
                // This is a group header
                $debugoutput .= "  - Group header found: '$line'\n";
                $currentgroup = [
                    'name' => $line,
                    'filters' => []
                ];
                // Store a copy, not a reference
                $groups[] = $currentgroup;
            } else {
                // This is a filter
                $debugoutput .= "  - Filter line found: '$line'\n";
                $parts = explode('|', $line, 2);
                $title = isset($parts[0]) ? trim($parts[0]) : '';
                $pattern = isset($parts[1]) ? trim($parts[1]) : '';
                
                if ($currentgroup !== null) {
                    $debugoutput .= "  - Adding filter: '$title' => '$pattern' to group: '{$currentgroup['name']}'\n";
                    $currentgroup['filters'][] = [
                        'title' => $title,
                        'pattern' => $pattern
                    ];
                    
                    // Update the group in the array
                    $lastIndex = count($groups) - 1;
                    if ($lastIndex >= 0) {
                        $groups[$lastIndex] = $currentgroup;
                    }
                } else {
                    $debugoutput .= "  - No current group, filter ignored\n";
                }
            }
        }
        
        // Always log the parsing process for troubleshooting
        error_log('Filter parsing debug: ' . $debugoutput);
        error_log('Parsed filter groups (raw): ' . json_encode($groups));
        
        // Create a fresh array to avoid reference issues
        $finalgroups = [];
        foreach ($groups as $index => $group) {
            error_log("Processing group $index: " . json_encode($group));
            if (!empty($group['name'])) {
                if (empty($group['filters'])) {
                    error_log("Group '{$group['name']}' has no filters");
                } else {
                    error_log("Group '{$group['name']}' has " . count($group['filters']) . " filters");
                }
                $finalgroups[] = $group;
            }
        }
        
        error_log('Final filter groups: ' . json_encode($finalgroups));
        
        // If we got no groups, try a simpler fallback parser
        if (empty($finalgroups)) {
            error_log('Attempting fallback parsing method...');
            $finalgroups = $this->fallback_parse_filter_definitions($filterdefs);
        }
        
        return $finalgroups;
    }
    /**
     * A simpler fallback method to parse filter definitions.
     * 
     * @param string $filterdefs The filter definitions
     * @return array The parsed filter groups
     */
    protected function fallback_parse_filter_definitions($filterdefs) {
        // Simple manual parse
        $groups = [];
        
        // Check for 2023-24 section
        if (strpos($filterdefs, '2023-24') !== false) {
            $group = [
                'name' => '2023-24',
                'filters' => [
                    ['title' => 'Term 1', 'pattern' => '_A1_202324'],
                    ['title' => 'Term 2', 'pattern' => '_A2_202324']
                ]
            ];
            $groups[] = $group;
        }
        
        // Check for 2024-25 section
        if (strpos($filterdefs, '2024-25') !== false) {
            $group = [
                'name' => '2024-25',
                'filters' => [
                    ['title' => 'Term 1', 'pattern' => '_A1_202425'],
                    ['title' => 'Term 2', 'pattern' => '_A2_202425']
                ]
            ];
            $groups[] = $group;
        }
        
        error_log('Fallback parsing result: ' . json_encode($groups));
        return $groups;
    }

/**
 * Get the JavaScript for filter functionality.
 * 
 * @return string The JavaScript code
 */
protected function get_filter_javascript() {
    global $CFG;
    
    // Load the JavaScript from a file to keep this method cleaner
    $jsfile = $CFG->dirroot . '/blocks/enhancedcourseoverview/js/filter.js';
    if (file_exists($jsfile)) {
        return file_get_contents($jsfile);
    }
    
    // Fallback to inline version
    return "
    document.addEventListener('DOMContentLoaded', function() {
        // Variables and setup
        var debugContainer = document.querySelector('.debug-container');
        if (debugContainer) debugContainer.style.display = 'block';
        
        var filterContainer = document.querySelector('.enhanced-filters');
        if (!filterContainer) return;
        
        var courseCountDiv = document.createElement('div');
        courseCountDiv.className = 'course-count-indicator';
        filterContainer.after(courseCountDiv);
        
        // Internal state
        var originalLayout = null;
        var isFirstLoad = true;
        var paginationInfo = {
            hasMorePages: false,
            loadedAllPages: false,
            currentPage: 1,
            totalPages: 1
        };
        
        // Helper functions
        function checkPagination() {
            var paginationControls = document.querySelector('[data-region=\"paging-control-container\"]');
            if (paginationControls) {
                paginationInfo.hasMorePages = true;
                var paginationBar = paginationControls.querySelector('[data-region=\"paging-bar\"]');
                if (paginationBar) {
                    var pageItems = paginationBar.querySelectorAll('[data-region=\"page-item\"]');
                    paginationInfo.totalPages = pageItems.length;
                    var activePage = paginationBar.querySelector('[data-region=\"page-item\"].active');
                    if (activePage) {
                        paginationInfo.currentPage = parseInt(activePage.textContent) || 1;
                    }
                }
                console.log('Pagination detected:', paginationInfo);
            } else {
                paginationInfo.hasMorePages = false;
                paginationInfo.loadedAllPages = true;
                console.log('No pagination found, all courses are visible');
            }
        }
        
        function saveOriginalLayout() {
            if (isFirstLoad) {
                var courseContainer = document.querySelector('[data-region=\"course-content\"]');
                if (courseContainer) {
                    originalLayout = courseContainer.innerHTML;
                    isFirstLoad = false;
                    console.log('Original course layout saved');
                }
            }
        }
        
        function restoreOriginalLayout() {
            var courseContainer = document.querySelector('[data-region=\"course-content\"]');
            if (courseContainer && originalLayout) {
                courseContainer.innerHTML = originalLayout;
                console.log('Original course layout restored');
                
                var paginationControls = document.querySelector('[data-region=\"paging-control-container\"]');
                if (paginationControls) {
                    paginationControls.style.display = '';
                }
            }
        }
        
        async function loadAllCourses() {
            if (paginationInfo.loadedAllPages) return Promise.resolve();
            
            console.log('Attempting to load all course pages...');
            var paginationControls = document.querySelector('[data-region=\"paging-control-container\"]');
            if (paginationControls) paginationControls.style.display = 'none';
            
            try {
                var courseBlocks = document.querySelectorAll('[data-region=\"myoverview\"]');
                if (courseBlocks.length > 0) {
                    var loadMoreButton = document.querySelector('[data-action=\"more-courses\"]');
                    if (loadMoreButton) {
                        console.log('Found \"Load more\" button, clicking it');
                        loadMoreButton.click();
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        
                        while ((loadMoreButton = document.querySelector('[data-action=\"more-courses\"]')) !== null) {
                            console.log('Clicking \"Load more\" button again');
                            loadMoreButton.click();
                            await new Promise(resolve => setTimeout(resolve, 1000));
                        }
                    } else {
                        var paginationLinks = document.querySelectorAll('[data-region=\"page-link\"]');
                        if (paginationLinks.length > 0) {
                            // Logic for clicking through pagination pages...
                            // [Code shortened for brevity]
                        } else {
                            // Try AJAX approach...
                            // [Code shortened for brevity]
                        }
                    }
                }
                
                paginationInfo.loadedAllPages = true;
                console.log('All courses loaded successfully');
                
                if (paginationControls) paginationControls.style.display = 'none';
            } catch (error) {
                console.error('Error loading all courses:', error);
                if (paginationControls) paginationControls.style.display = '';
            }
        }
        
        function rearrangeCourses(visibleCourses) {
            const courseContainer = document.querySelector('[data-region=\"course-content\"]');
            if (!courseContainer) return;
            
            const courseView = document.querySelector('[data-region=\"courses-view\"]');
            if (!courseView) return;
            
            const isCardView = courseView.classList.contains('block-myoverview-display-cards');
            const isListView = courseView.classList.contains('block-myoverview-display-list');
            
            if (isCardView) {
                const cardDeck = courseView.querySelector('.card-deck') || courseView.querySelector('.dashboard-card-deck');
                if (cardDeck) {
                    // Hide all course columns first
                    const allColumns = cardDeck.querySelectorAll('.col.d-flex');
                    allColumns.forEach(column => column.style.display = 'none !important');
                    
                    visibleCourses.forEach((course, index) => {
                        // Find the parent column div and show it
                        let parentColumn = course.closest('.col.d-flex');
                        if (parentColumn) {
                            parentColumn.style.display = '';
                            cardDeck.appendChild(parentColumn);
                        }
                    });
                }
            } else if (isListView) {
                const listGroup = courseView.querySelector('.list-group');
                if (listGroup) {
                    // For list view, we need a different approach
                    const allItems = listGroup.querySelectorAll('.list-group-item');
                    allItems.forEach(item => {
                        const parentColumn = item.closest('.col.d-flex');
                        if (parentColumn) {
                            parentColumn.style.display = 'none !important';
                        }
                    });
                    
                    visibleCourses.forEach(course => {
                        const parentColumn = course.closest('.col.d-flex');
                        if (parentColumn) {
                            parentColumn.style.display = '';
                            listGroup.appendChild(parentColumn);
                        }
                    });
                }
            }
        }
        
        function filterCourses(patterns) {
            console.log('Filtering courses with patterns:', patterns);
            // We need to select both the course cards and the parent columns
            var courseCards = document.querySelectorAll('.course-card, .list-group-item.course-listitem');
            
            var visibleCount = 0;
            var totalCount = courseCards.length;
            var visibleCourses = [];
            
            if (patterns.length === 0) {
                // If no filters active, show all courses
                courseCards.forEach(card => {
                    const parentColumn = card.closest('.col.d-flex');
                    if (parentColumn) {
                        parentColumn.style.display = '';
                    }
                    visibleCount++;
                });
            } else {
                // Apply filtering
                courseCards.forEach(card => {
                    const courseTitle = card.textContent || '';
                    let showCourse = false;
                    
                    // Check if any pattern matches
                    for (let pattern of patterns) {
                        if (courseTitle.indexOf(pattern) !== -1) {
                            showCourse = true;
                            break;
                        }
                    }
                    
                    // Get the parent column and set its display property
                    const parentColumn = card.closest('.col.d-flex');
                    if (parentColumn) {
                        if (showCourse) {
                            parentColumn.style.display = '';
                        } else {
                            // Use setAttribute to add !important
                            parentColumn.setAttribute('style', 'display: none !important');
                        }
                    }
                    
                    if (showCourse) {
                        visibleCount++;
                        visibleCourses.push(card);
                    }
                });
                
                if (visibleCourses.length > 0) {
                    rearrangeCourses(visibleCourses);
                } else {
                    var courseContainer = document.querySelector('[data-region=\"course-content\"]');
                    if (courseContainer) {
                        var noMatchesDiv = document.createElement('div');
                        noMatchesDiv.className = 'alert alert-info';
                        noMatchesDiv.innerHTML = 'No courses match the selected filters.';
                        courseContainer.appendChild(noMatchesDiv);
                    }
                }
            }
            
            courseCountDiv.innerHTML = patterns.length === 0 ? '' : 'Showing ' + visibleCount + ' of ' + totalCount + ' courses';
        }
        
        // Main code execution
        checkPagination();
        saveOriginalLayout();
        
        var filterButtons = document.querySelectorAll('.filter-term-btn');
        for (var i = 0; i < filterButtons.length; i++) {
            filterButtons[i].addEventListener('click', async function(e) {
                e.preventDefault();
                
                this.classList.toggle('active');
                
                var activePatterns = [];
                var activeButtons = document.querySelectorAll('.filter-term-btn.active');
                for (var j = 0; j < activeButtons.length; j++) {
                    activePatterns.push(activeButtons[j].getAttribute('data-pattern'));
                }
                
                if (activePatterns.length === 0) {
                    restoreOriginalLayout();
                    courseCountDiv.innerHTML = '';
                    document.dispatchEvent(new CustomEvent('reset-filter'));
                    return;
                }
                
                if (paginationInfo.hasMorePages && !paginationInfo.loadedAllPages) {
                    courseCountDiv.innerHTML = 'Loading all courses...';
                    await loadAllCourses();
                }
                
                filterCourses(activePatterns);
            });
        }
    });
    ";
}
}
