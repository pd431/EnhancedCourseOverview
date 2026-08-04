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

    /** @var int Safety cap on the number of years a single generator line may produce. */
    const MAX_GENERATED_YEARS = 50;

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

        $manualgroups = $this->parse_filter_definitions(
            get_config('block_enhancedcourseoverview', 'filterdefinitions')
        );
        $generatedgroups = $this->generate_year_groups(
            get_config('block_enhancedcourseoverview', 'yeargenerator')
        );
        $filtergroups = array_merge($manualgroups, $generatedgroups);

        if (empty($filtergroups)) {
            return $this->content;
        }

        $filtergroups = $this->mark_default_filters($filtergroups);

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
     * Parse the manually-defined filter definitions from the settings.
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
        if (empty($filterdefs)) {
            return [];
        }

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

    /**
     * Generate year-based filter groups from template definitions, so admins
     * don't need to hand-write a block of filters for every new academic year.
     *
     * Each line has the format:
     *   startyear|endyear|termcount|title template|pattern template
     *
     * Title and pattern templates may use the placeholders:
     *   {n}  - the term number (1-based)
     *   {ay} - the full academic year, e.g. 202324
     *   {y1} - the start year, e.g. 2023
     *   {y2} - the two-digit end year, e.g. 24
     *
     * Example: 2023|2026|3|Term {n}|_A_{n}_{ay}
     * generates groups "2023-24".."2026-27", each with Term 1..3 filters
     * matching patterns like "_A_1_202324".
     *
     * @param string $generatordefs The raw generator definitions from the settings.
     * @return array The generated filter groups, each with a 'name' and a list of 'filters'.
     */
    protected function generate_year_groups($generatordefs) {
        if (empty($generatordefs)) {
            return [];
        }

        $generatordefs = str_replace(["\r\n", "\r"], "\n", $generatordefs);
        $lines = explode("\n", $generatordefs);

        $groups = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }

            $parts = array_pad(explode('|', $line, 5), 5, '');
            [$startyear, $endyear, $termcount, $titletemplate, $patterntemplate] = $parts;

            $startyear = (int) trim($startyear);
            $endyear = (int) trim($endyear);
            $termcount = (int) trim($termcount);
            $titletemplate = trim($titletemplate);
            $patterntemplate = trim($patterntemplate);

            if ($startyear <= 0 || $endyear < $startyear || $termcount <= 0
                    || $titletemplate === '' || $patterntemplate === '') {
                continue;
            }

            if (($endyear - $startyear + 1) > self::MAX_GENERATED_YEARS) {
                $endyear = $startyear + self::MAX_GENERATED_YEARS - 1;
            }

            for ($year = $startyear; $year <= $endyear; $year++) {
                $y1 = (string) $year;
                $y2 = str_pad((string) (($year + 1) % 100), 2, '0', STR_PAD_LEFT);
                $ay = $y1 . $y2;
                $groupname = $y1 . '-' . $y2;

                $filters = [];
                for ($term = 1; $term <= $termcount; $term++) {
                    $replacements = [
                        '{n}' => $term,
                        '{ay}' => $ay,
                        '{y1}' => $y1,
                        '{y2}' => $y2,
                    ];
                    $filters[] = [
                        'title' => strtr($titletemplate, $replacements),
                        'pattern' => strtr($patterntemplate, $replacements),
                    ];
                }

                $groups[] = [
                    'name' => $groupname,
                    'filters' => $filters,
                ];
            }
        }

        return $groups;
    }

    /**
     * Mark which filters should be active by default, based on the
     * defaultpatterns setting (a comma/newline separated list of exact
     * pattern strings).
     *
     * @param array $filtergroups The filter groups produced by parse_filter_definitions()
     *                            and/or generate_year_groups().
     * @return array The same groups, with each filter tagged with 'isdefault'.
     */
    protected function mark_default_filters(array $filtergroups) {
        $raw = get_config('block_enhancedcourseoverview', 'defaultpatterns');
        $defaults = [];

        if (!empty($raw)) {
            $raw = str_replace(["\r\n", "\r", ','], "\n", $raw);
            foreach (explode("\n", $raw) as $pattern) {
                $pattern = trim($pattern);
                if ($pattern !== '') {
                    $defaults[$pattern] = true;
                }
            }
        }

        foreach ($filtergroups as &$group) {
            foreach ($group['filters'] as &$filter) {
                $filter['isdefault'] = isset($defaults[$filter['pattern']]);
            }
            unset($filter);
        }
        unset($group);

        return $filtergroups;
    }
}
