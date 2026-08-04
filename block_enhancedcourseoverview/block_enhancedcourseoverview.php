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

// Make sure the core myoverview is loaded.
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
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Content of the block.
     *
     * @return stdClass|null
     */
    public function get_content() {
        if (isset($this->content)) {
            return $this->content;
        }

        // Get the original content from the parent class.
        $this->content = parent::get_content();

        if (!$this->content) {
            return null;
        }

        $filterdefs = get_config('block_enhancedcourseoverview', 'filterdefinitions');

        if (empty($filterdefs)) {
            // No filters configured, just return the original content.
            return $this->content;
        }

        $filtergroups = $this->parse_filter_definitions($filterdefs);

        if (empty($filtergroups)) {
            return $this->content;
        }

        $uniqid = 'ceo-' . $this->instance->id;

        $renderer = $this->page->get_renderer('block_enhancedcourseoverview');
        $filterbuttons = $renderer->render_from_template(
            'block_enhancedcourseoverview/filter-buttons',
            [
                'filtergroups' => $filtergroups,
                'uniqid' => $uniqid,
            ]
        );

        // Inject our filter buttons just before the course-view region.
        $pattern = '/<div[^>]*data-region="courses-view"[^>]*>/';
        $this->content->text = preg_replace($pattern, $filterbuttons . '$0', $this->content->text, 1);

        $this->page->requires->css('/blocks/enhancedcourseoverview/styles.css');

        $this->page->requires->strings_for_js(
            ['filter:loading', 'filter:nomatches'],
            'block_enhancedcourseoverview'
        );
        $this->page->requires->js_call_amd('block_enhancedcourseoverview/filter', 'init', [$uniqid]);

        return $this->content;
    }

    /**
     * Parse the filter definitions from the settings.
     *
     * Format:
     *   Group name (line without a pipe character)
     *   Filter title|pattern to match (repeated for each filter in the group)
     *   (blank line separates groups)
     *
     * @param string $filterdefs The raw filter definitions from the settings.
     * @return array The parsed filter groups, each with a 'name' and a list of 'filters'.
     */
    protected function parse_filter_definitions($filterdefs) {
        $filterdefs = str_replace(["\r\n", "\r"], "\n", $filterdefs);
        $lines = explode("\n", $filterdefs);

        $groups = [];
        $currentindex = -1;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (strpos($line, '|') === false) {
                // A line without a pipe starts a new group.
                $groups[] = [
                    'name' => $line,
                    'filters' => [],
                ];
                $currentindex = count($groups) - 1;
                continue;
            }

            if ($currentindex === -1) {
                // A filter line before any group header is invalid, skip it.
                continue;
            }

            [$title, $pattern] = array_pad(explode('|', $line, 2), 2, '');
            $title = trim($title);
            $pattern = trim($pattern);

            if ($title === '' || $pattern === '') {
                continue;
            }

            $groups[$currentindex]['filters'][] = [
                'title' => $title,
                'pattern' => $pattern,
            ];
        }

        // Drop groups that ended up with no valid filters.
        return array_values(array_filter($groups, function($group) {
            return !empty($group['filters']);
        }));
    }
}
