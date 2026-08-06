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

namespace block_enhancedcourseoverview\external;

use context_course;
use context_user;
use core_course_external;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

require_once($CFG->dirroot . '/course/externallib.php');

/**
 * Fetch the current user's courses, matching the same timeline
 * classification block_myoverview itself uses, with their own role(s) in
 * each course attached.
 *
 * This deliberately does not reimplement course enrolment/classification
 * logic: it calls core_course_external::get_enrolled_courses_by_timeline_classification()
 * directly (the exact same function block_myoverview's own JavaScript
 * calls) and only adds role data to its output, so this plugin keeps
 * riding on core's own logic - resilient to how Moodle's enrolment and
 * classification rules evolve - rather than forking it.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courses_with_roles extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classification' => new external_value(
                PARAM_ALPHA,
                'Timeline classification: all, allincludinghidden, past, inprogress, future, favourites, ' .
                    'hidden, or customfield - see core_course_external::get_enrolled_courses_by_timeline_classification'
            ),
            'sort' => new external_value(PARAM_TEXT, 'Sort string', VALUE_DEFAULT, null),
            'customfieldname' => new external_value(
                PARAM_ALPHANUMEXT, 'Used when classification = customfield', VALUE_DEFAULT, null
            ),
            'customfieldvalue' => new external_value(
                PARAM_RAW, 'Used when classification = customfield', VALUE_DEFAULT, null
            ),
        ]);
    }

    /**
     * Get the current user's courses matching the given classification, each
     * with the roles the current user holds in it.
     *
     * @param string $classification See core_course_external::get_enrolled_courses_by_timeline_classification().
     * @param string|null $sort SQL sort string for results.
     * @param string|null $customfieldname Used when classification = customfield.
     * @param string|null $customfieldvalue Used when classification = customfield.
     * @return array
     */
    public static function execute(
        string $classification,
        ?string $sort = null,
        ?string $customfieldname = null,
        ?string $customfieldvalue = null
    ): array {
        global $USER;

        self::validate_context(context_user::instance($USER->id));

        $params = self::validate_parameters(self::execute_parameters(), [
            'classification' => $classification,
            'sort' => $sort,
            'customfieldname' => $customfieldname,
            'customfieldvalue' => $customfieldvalue,
        ]);

        // Delegate entirely to core for the course list itself: no limit
        // (we need every matching course, not one page of them), and no
        // search value - this function isn't wired up to the dashboard's
        // search box.
        $result = core_course_external::get_enrolled_courses_by_timeline_classification(
            $params['classification'],
            0,
            0,
            $params['sort'],
            $params['customfieldname'],
            $params['customfieldvalue'],
            null,
            ['id', 'fullname']
        );

        $courses = [];
        foreach ($result['courses'] as $course) {
            $courses[] = [
                'id' => (int) $course['id'],
                'fullname' => $course['fullname'],
                'roles' => self::get_user_course_roles((int) $course['id'], (int) $USER->id),
            ];
        }

        return ['courses' => $courses];
    }

    /**
     * Get the distinct roles a user holds in a course (including any
     * inherited from a parent context, e.g. a category-level role), as
     * {shortname, name} pairs.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    protected static function get_user_course_roles(int $courseid, int $userid): array {
        $context = context_course::instance($courseid);
        $roleassignments = get_user_roles($context, $userid);

        $roles = [];
        $seenroleids = [];
        foreach ($roleassignments as $roleassignment) {
            if (isset($seenroleids[$roleassignment->roleid])) {
                continue;
            }
            $seenroleids[$roleassignment->roleid] = true;

            $roles[] = [
                'shortname' => $roleassignment->shortname,
                'name' => role_get_name((object) [
                    'id' => $roleassignment->roleid,
                    'name' => $roleassignment->name,
                    'shortname' => $roleassignment->shortname,
                ]),
            ];
        }

        return $roles;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course id'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                    'roles' => new external_multiple_structure(
                        new external_single_structure([
                            'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Role shortname'),
                            'name' => new external_value(PARAM_TEXT, 'Role display name'),
                        ]),
                        'Distinct roles the current user holds in this course'
                    ),
                ]),
                'Course'
            ),
        ]);
    }
}
