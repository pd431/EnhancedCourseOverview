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
 * Enhanced course overview block renderer.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_enhanced_course_overview\output;

defined('MOODLE_INTERNAL') || die;

use plugin_renderer_base;

/**
 * Enhanced course overview block renderer class.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the enhanced course overview block.
     *
     * @param main $main The main renderable
     * @return string HTML
     */
    public function render_main(main $main) {
        return $this->render_from_template('block_enhanced_course_overview/main', $main->export_for_template($this));
    }

    /**
     * Render a course item with enhanced badges.
     *
     * @param \stdClass $course The course data for rendering
     * @return string HTML
     */
    public function render_course_item($course) {
        return $this->render_from_template('block_enhanced_course_overview/course-item', $course);
    }
}
