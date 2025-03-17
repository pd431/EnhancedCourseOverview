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
 * Class containing data for enhanced course overview block.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_enhanced_course_overview\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use core_course\external\course_summary_exporter;

/**
 * Class containing data for enhanced course overview block.
 *
 * @package    block_enhanced_course_overview
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /** @var string $grouping Current selected grouping. */
    private $grouping;

    /** @var string $sort Current selected sorting. */
    private $sort;

    /** @var string $view Current selected view. */
    private $view;

    /** @var int $paging Current paging preference. */
    private $paging;

    /** @var string $academicyear Current selected academic year. */
    private $academicyear;

    /** @var string $term Current selected term. */
    private $term;

    /** @var string $role Current selected role filter. */
    private $role;

    /** @var array $coursesview List of courses for rendering. */
    private $coursesview = [];

    /**
     * Constructor.
     *
     * @param string $grouping Grouping user preference.
     * @param string $sort Sort user preference.
     * @param string $view View user preference.
     * @param int $paging Paging user preference.
     * @param string $academicyear Academic year user preference.
     * @param string $term Term user preference.
     * @param string $role Role user preference.
     */
    public function __construct($grouping, $sort, $view, $paging, $academicyear, $term, $role) {
        $this->grouping = $grouping;
        $this->sort = $sort;
        $this->view = $view;
        $this->paging = $paging;
        $this->academicyear = $academicyear;
        $this->term = $term;
        $this->role = $role;

        // Set defaults
        if (!$this->view) {
            $this->view = 'card';
        }
        
        $this->init();
    }

    /**
     * Initialize the course data for rendering.
     */
    private function init() {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        
        // Get user's courses.
        $courses = enrol_get_my_courses('*', 'fullname');
        
        // Prepare course data for export.
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $exporter = new course_summary_exporter($course, [
                'context' => $context
            ]);
            $exportedcourse = $exporter->export($this->get_renderer());
            
            // Add basic badges for demonstration
            $exportedcourse->term_badges = [
                ['name' => 'Term 1', 'year' => '2024-25']
            ];
            $exportedcourse->role_badge = [
                'name' => 'Student', 'role' => 'student'
            ];
            
            $this->coursesview[] = $exportedcourse;
        }
    }

    /**
     * Export this data for use in a template context.
     *
     * @param renderer_base $output Renderer.
     * @return array Data for use in a template.
     */
    public function export_for_template(renderer_base $output) {
        // Example years
        $years = [
            [
                'id' => '2022-23',
                'name' => '22/23',
                'active' => false,
                'terms' => [
                    ['id' => 'term1_2223', 'name' => 'T1', 'active' => false],
                    ['id' => 'term2_2223', 'name' => 'T2', 'active' => false],
                    ['id' => 'term3_2223', 'name' => 'T3', 'active' => false]
                ]
            ],
            [
                'id' => '2023-24',
                'name' => '23/24',
                'active' => false,
                'terms' => [
                    ['id' => 'term1_2324', 'name' => 'T1', 'active' => false],
                    ['id' => 'term2_2324', 'name' => 'T2', 'active' => true],
                    ['id' => 'term3_2324', 'name' => 'T3', 'active' => true]
                ]
            ],
            [
                'id' => '2024-25',
                'name' => '24/25',
                'active' => true,
                'terms' => [
                    ['id' => 'term1_2425', 'name' => 'T1', 'active' => false],
                    ['id' => 'term2_2425', 'name' => 'T2', 'active' => true],
                    ['id' => 'term3_2425', 'name' => 'T3', 'active' => false]
                ]
            ]
        ];
        
        // Example roles
        $roles = [
            ['id' => 'all', 'name' => 'All Roles', 'active' => true],
            ['id' => 'student', 'name' => 'Student', 'active' => false],
            ['id' => 'teacher', 'name' => 'Teacher', 'active' => false],
            ['id' => 'admin', 'name' => 'Admin', 'active' => false]
        ];
        
        // Example active filters
        $activeFilters = [
            ['type' => 'timeframe', 'id' => 'term2_2324', 'name' => '23/24 T2'],
            ['type' => 'timeframe', 'id' => 'term3_2324', 'name' => '23/24 T3'],
            ['type' => 'timeframe', 'id' => 'term2_2425', 'name' => '24/25 T2']
        ];
        
        // Prepare data for template
        $data = [
            'years' => $years,
            'roles' => $roles,
            'activefilters' => $activeFilters,
            'courses' => $this->coursesview,
            'coursesview' => $this->view,
            'paging' => $this->paging,
            'displayrolebadges' => true,
            'displaytermbadges' => true,
        ];
        
        return $data;
    }

    /**
     * Get the renderer for the class.
     *
     * @return renderer_base The renderer.
     */
    private function get_renderer() {
        global $PAGE;
        return $PAGE->get_renderer('block_enhanced_course_overview');
    }
}