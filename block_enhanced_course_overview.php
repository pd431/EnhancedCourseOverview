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
 * Contains the class for the Enhanced Course Overview block.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Include required constants
require_once($CFG->dirroot . '/blocks/myoverview/lib.php');
require_once($CFG->dirroot . '/blocks/enhanced_course_overview/lib.php');

/**
 * Enhanced Course Overview block class.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enhanced_course_overview extends block_base {

    /**
     * Init.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_enhanced_course_overview');
    }

    /**
     * Returns the contents.
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        if (isset($this->content)) {
            return $this->content;
        }
        
        // Get user preferences
        $group = get_user_preferences('block_enhanced_course_overview_user_grouping_preference');
        $sort = get_user_preferences('block_enhanced_course_overview_user_sort_preference');
        $view = get_user_preferences('block_enhanced_course_overview_user_view_preference');
        $paging = get_user_preferences('block_enhanced_course_overview_user_paging_preference');
        
        // Academic year and term filters
        $academicyear = get_user_preferences('block_enhanced_course_overview_user_academicyear_preference');
        $term = get_user_preferences('block_enhanced_course_overview_user_term_preference');
        $role = get_user_preferences('block_enhanced_course_overview_user_role_preference');

        $renderable = new \block_enhanced_course_overview\output\main(
            $group, $sort, $view, $paging, $academicyear, $term, $role
        );
        $renderer = $this->page->get_renderer('block_enhanced_course_overview');

        $this->content = new stdClass();
        $this->content->text = $renderer->render($renderable);
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my' => true);
    }

    /**
     * Allow the block to have a configuration page.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     */
    public function get_config_for_external() {
        // Return all settings for all users since it is safe (no private keys, etc..).
        $configs = get_config('block_enhanced_course_overview');

        // Process academic year and term configurations
        $configs->academicyears = \block_enhanced_course_overview\api::get_academic_years_config();
        
        // Get user preferences for filters
        $group = get_user_preferences('block_enhanced_course_overview_user_grouping_preference');
        $sort = get_user_preferences('block_enhanced_course_overview_user_sort_preference');
        $view = get_user_preferences('block_enhanced_course_overview_user_view_preference');
        $paging = get_user_preferences('block_enhanced_course_overview_user_paging_preference');
        $academicyear = get_user_preferences('block_enhanced_course_overview_user_academicyear_preference');
        $term = get_user_preferences('block_enhanced_course_overview_user_term_preference');
        $role = get_user_preferences('block_enhanced_course_overview_user_role_preference');

        return (object) [
            'instance' => new stdClass(),
            'plugin' => $configs,
        ];
    }

    /**
     * Disable block editing on the my courses page.
     *
     * @return boolean
     */
    public function instance_can_be_edited() {
        if ($this->page->blocks->is_known_region(BLOCK_POS_LEFT) || $this->page->blocks->is_known_region(BLOCK_POS_RIGHT)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Hide the block header on the my courses page.
     *
     * @return boolean
     */
    public function hide_header() {
        if ($this->page->blocks->is_known_region(BLOCK_POS_LEFT) || $this->page->blocks->is_known_region(BLOCK_POS_RIGHT)) {
            return false;
        } else {
            return true;
        }
    }
}