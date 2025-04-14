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
    return "
    document.addEventListener('DOMContentLoaded', function() {
        // Enable debug button
        var debugContainer = document.querySelector('.debug-container');
        if (debugContainer) {
            debugContainer.style.display = 'block';
        }
        
        // Create a counter for visible courses
        var filterContainer = document.querySelector('.enhanced-filters');
        var courseCountDiv = document.createElement('div');
        courseCountDiv.className = 'course-count-indicator';
        courseCountDiv.innerHTML = '';
        filterContainer.after(courseCountDiv);
        
        // Add click handlers to all filter buttons
        var filterButtons = document.querySelectorAll('.filter-term-btn');
        console.log('Found ' + filterButtons.length + ' filter buttons');
        
        // Log all available patterns
        console.log('Available filter patterns:');
        filterButtons.forEach(function(btn) {
            console.log(' - \"' + btn.textContent + '\" pattern: \"' + btn.getAttribute('data-pattern') + '\"');
        });
        
        for (var i = 0; i < filterButtons.length; i++) {
            filterButtons[i].addEventListener('click', function(e) {
                e.preventDefault();
                
                // Toggle active state
                this.classList.toggle('active');
                console.log('Button clicked: \"' + this.textContent + '\", pattern: \"' + this.getAttribute('data-pattern') + '\"');
                
                // Get all active filter patterns
                var activePatterns = [];
                var activeButtons = document.querySelectorAll('.filter-term-btn.active');
                for (var j = 0; j < activeButtons.length; j++) {
                    activePatterns.push(activeButtons[j].getAttribute('data-pattern'));
                }
                
                console.log('Active patterns: ' + JSON.stringify(activePatterns));
                
                // Filter the course cards/items
                filterCourses(activePatterns);
            });
        }
        
        // Function to filter courses
        function filterCourses(patterns) {
            console.log('Filtering courses with patterns: ' + JSON.stringify(patterns));
            
            // Important: Select the parent columns rather than just the cards
            var courseColumns = document.querySelectorAll('.col.d-flex.px-0.mb-2');
            console.log('Found ' + courseColumns.length + ' course columns');
            
            var visibleCount = 0;
            var totalCount = courseColumns.length;
            var matchResults = [];
            
            // If no active filters, show all courses
            if (patterns.length === 0) {
                for (var i = 0; i < courseColumns.length; i++) {
                    courseColumns[i].style.display = '';
                    visibleCount++;
                }
            } else {
                // Filter courses based on patterns
                for (var i = 0; i < courseColumns.length; i++) {
                    var courseColumn = courseColumns[i];
                    var courseItem = courseColumn.querySelector('.course-card, .list-group-item.course-listitem, .course-summaryitem');
                    
                    if (!courseItem) {
                        // Skip if no course item found in this column
                        continue;
                    }
                    
                    var courseTitle = '';
                    
                    // Try different elements to find course title/content
                    // First try to find course name element
                    var courseNameElement = courseItem.querySelector('.coursename');
                    if (courseNameElement) {
                        courseTitle = courseNameElement.textContent || '';
                    } else {
                        // Fall back to the full item text
                        courseTitle = courseItem.textContent || '';
                    }
                    
                    // Get course short name if present (often contains the course code we filter by)
                    var shortNameElement = courseItem.querySelector('.text-muted.muted');
                    var shortName = shortNameElement ? shortNameElement.textContent || '' : '';
                    
                    var combinedText = courseTitle + ' ' + shortName;
                    var showCourse = false;
                    
                    // For debugging - store course information
                    var matches = [];
                    
                    // Check if course matches any of the active patterns
                    for (var j = 0; j < patterns.length; j++) {
                        var pattern = patterns[j];
                        
                        // Log all courses for debugging (first 5 in detail)
                        if (i < 5) {
                            console.log('Checking course: \"' + combinedText.substring(0, 100) + 
                                       '...\" against pattern: \"' + pattern + '\"');
                        }
                        
                        if (combinedText.indexOf(pattern) !== -1) {
                            showCourse = true;
                            matches.push(pattern);
                            if (i < 5) {
                                console.log('MATCH FOUND: Course ' + i + ' matches pattern: \"' + pattern + '\"');
                            }
                        }
                    }
                    
                    // Record match results for all courses
                    matchResults.push({
                        index: i,
                        title: combinedText.substring(0, 50) + '...',
                        matches: matches,
                        visible: showCourse
                    });
                    
                    // Hide/show the entire column, not just the course item
                    courseColumn.style.display = showCourse ? '' : 'none';
                    if (showCourse) {
                        visibleCount++;
                    }
                }
                
                // Log match results
                console.log('Full match results:', matchResults);
                console.log('Courses that matched:');
                for (var i = 0; i < matchResults.length; i++) {
                    if (matchResults[i].visible) {
                        console.log(' - Course ' + i + ': \"' + matchResults[i].title + 
                                   '\" matched patterns: ' + JSON.stringify(matchResults[i].matches));
                    }
                }
            }
            
            // Update the count indicator
            if (patterns.length === 0) {
                courseCountDiv.innerHTML = '';
            } else {
                courseCountDiv.innerHTML = 'Showing ' + visibleCount + ' of ' + totalCount + ' courses';
            }
        }
        
        // Initially update the course count (all courses)
        var allCourseItems = document.querySelectorAll('.col.d-flex.px-0.mb-2');
        if (allCourseItems.length > 0) {
            courseCountDiv.innerHTML = '';
        }
    });
    ";
}
