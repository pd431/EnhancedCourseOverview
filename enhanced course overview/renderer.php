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
 * Renderer for block_enhancedcourseoverview
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Renderer for block_enhancedcourseoverview
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_enhancedcourseoverview_renderer extends plugin_renderer_base {
    
    /**
     * Renders the filter buttons.
     *
     * @param \block_enhancedcourseoverview\output\filter_buttons $filterbuttons The filter buttons renderable.
     * @return string HTML string.
     */
    public function render_filter_buttons(\block_enhancedcourseoverview\output\filter_buttons $filterbuttons) {
        return $this->render_from_template('block_enhancedcourseoverview/filter_buttons', $filterbuttons->export_for_template($this));
    }
}
