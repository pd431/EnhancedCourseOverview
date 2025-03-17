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
 * Enhanced Course Overview block
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/enhanced_course_overview/lib.php');

/**
 * Enhanced Course Overview block class
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enhanced_course_overview extends block_base {

    /**
     * Initialize the block
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_enhanced_course_overview');
    }

    /**
     * Return the content of this block
     *
     * @return stdClass Contents and footer
     */
    public function get_content() {
        global $USER, $OUTPUT;

        if (isset($this->content)) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get user courses
        $courses = enrol_get_my_courses(null, 'fullname ASC');
        if (empty($courses)) {
            return $this->content;
        }

        // Get filter configuration
        $filterConfig = block_enhanced_course_overview_get_filter_config();
        
        // Get available filters based on the user's courses
        $availableFilters = block_enhanced_course_overview_get_available_filters($courses, $filterConfig);
        
        // Setup default active filters (if any)
        $activeFilters = $this->get_active_filters($availableFilters);
        
        // Filter courses based on active filters
        if (!empty($activeFilters)) {
            $filteredCourses = block_enhanced_course_overview_filter_courses($courses, $activeFilters);
        } else {
            $filteredCourses = $courses;
        }
        
        // Store full courses list and filtered courses list in JS for dynamic filtering
        $this->page->requires->data_for_js('enhancedCourseOverviewCourses', $courses);
        $this->page->requires->data_for_js('enhancedCourseOverviewFilteredCourses', $filteredCourses);
        
        // Render filter buttons
        $filterButtonsHtml = $OUTPUT->render_from_template(
            'block_enhanced_course_overview/filter_groups', 
            $availableFilters
        );
        
        // Create a div for the filtered courses - will be updated via JS
        $coursesContainerHtml = html_writer::start_div('enhanced-overview-courses', ['id' => 'enhanced-overview-courses-container']);
        $coursesContainerHtml .= html_writer::end_div();
        
        // Combine filter buttons and courses container
        $this->content->text = html_writer::div(
            $filterButtonsHtml . $coursesContainerHtml,
            'enhanced-course-overview-container'
        );
        
        // Initialize JavaScript
        $this->page->requires->js_call_amd('block_enhanced_course_overview/main', 'init', [['blockid' => $this->instance->id]]);
        
        return $this->content;
    }

    /**
     * Get active filters from the available filters
     *
     * @param array $availableFilters Available filters
     * @return array Active filters
     */
    protected function get_active_filters($availableFilters) {
        $activeFilters = [];
        
        // Check for active timeframe filters
        if (!empty($availableFilters['timeframeFilters']['years'])) {
            foreach ($availableFilters['timeframeFilters']['years'] as $year) {
                foreach ($year['terms'] as $term) {
                    if (!empty($term['active'])) {
                        if (!isset($activeFilters['timeframe'])) {
                            $activeFilters['timeframe'] = [];
                        }
                        $activeFilters['timeframe'][] = $term['value'];
                    }
                }
            }
        }
        
        // Check for active campus filters
        if (!empty($availableFilters['campusFilters']['items'])) {
            foreach ($availableFilters['campusFilters']['items'] as $campus) {
                if (!empty($campus['active'])) {
                    if (!isset($activeFilters['campus'])) {
                        $activeFilters['campus'] = [];
                    }
                    $activeFilters['campus'][] = $campus['value'];
                }
            }
        }
        
        // Check for active role filters
        if (!empty($availableFilters['roleFilters']['items'])) {
            foreach ($availableFilters['roleFilters']['items'] as $role) {
                if (!empty($role['active'])) {
                    if (!isset($activeFilters['role'])) {
                        $activeFilters['role'] = [];
                    }
                    $activeFilters['role'][] = $role['value'];
                }
            }
        }
        
        return $activeFilters;
    }

    /**
     * This block has global configuration
     *
     * @return bool True
     */
    public function has_config() {
        return true;
    }

    /**
     * Locations where block can be displayed
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true];
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function instance_allow_config() {
        return true;
    }
}