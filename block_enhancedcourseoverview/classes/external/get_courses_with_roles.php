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

use context_user;
use core_course_external;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 * Fetch the current user's courses, matching the same timeline
 * classification block_myoverview itself uses, with their own role(s) in
 * each course attached.
 *
 * For the common classifications (all/inprogress/past/future), this avoids
 * core_course_external::get_enrolled_courses_by_timeline_classification()
 * entirely: that function is built for *rendering* the dashboard, so it
 * decorates every single returned course with a completion progress
 * percentage, card image, and other display data - each requiring its own
 * per-course queries. That's a well-documented Moodle performance issue
 * (see MDL-72246) that gets worse the more courses a user is enrolled in,
 * and none of it is data this plugin actually needs; it only ever wants a
 * course's id, fullname, and the user's role(s) in it, to decide which
 * filter buttons should be visible. The fast path here (get_courses_fast())
 * uses enrol_get_all_users_courses() instead - the same plain, single-query
 * enrolment lookup many other core areas use - and classifies courses into
 * buckets itself from their start/end dates, which needs no per-course
 * queries at all. Classifications outside that set (favourites, hidden,
 * customfield) still delegate entirely to core - see get_courses_via_core()
 * - since replicating the favourites service or per-user hidden-course
 * preferences ourselves isn't worth the risk of drifting from core's own
 * behaviour for something so rarely used.
 *
 * Roles are fetched for every course in one batched pair of queries (see
 * get_roles_by_course()) rather than one query per course, regardless of
 * which path above was used - the per-course version this replaced was a
 * second, self-inflicted N+1 on top of whatever core's own function cost.
 *
 * @package    block_enhancedcourseoverview
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courses_with_roles extends external_api {

    /**
     * Classifications handled by the fast path (see class docblock).
     */
    const FAST_PATH_CLASSIFICATIONS = ['all', 'inprogress', 'past', 'future'];

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

        if (in_array($params['classification'], self::FAST_PATH_CLASSIFICATIONS, true)) {
            $courses = self::get_courses_fast(
                (int) $USER->id,
                $params['classification'],
                $params['sort'],
                $params['customfieldname'],
                $params['customfieldvalue']
            );
        } else {
            $courses = self::get_courses_via_core(
                $params['classification'],
                $params['sort'],
                $params['customfieldname'],
                $params['customfieldvalue']
            );
        }

        $rolesbycourse = self::get_roles_by_course((int) $USER->id, $courses);

        $result = [];
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            $result[] = [
                'id' => $courseid,
                'fullname' => $course->fullname,
                'roles' => $rolesbycourse[$courseid] ?? [],
            ];
        }

        return ['courses' => $result];
    }

    /**
     * Fetch the user's enrolled courses for a date-based classification
     * (all/inprogress/past/future) without going through core's expensive
     * per-course exporter - see the class docblock for why.
     *
     * @param int $userid
     * @param string $classification 'all', 'inprogress', 'past', or 'future'.
     * @param string|null $sort Ignored deliberately: this data is only ever
     *                          used for filter matching, which doesn't care
     *                          about order, so there's nothing to gain (and
     *                          a real risk of a sort string that doesn't
     *                          translate cleanly) from applying it here.
     * @param string|null $customfieldname Only used for get_hidden_course_ids()'s core call.
     * @param string|null $customfieldvalue Only used for get_hidden_course_ids()'s core call.
     * @return stdClass[] Course records with at least ->id, ->fullname, ->category.
     */
    protected static function get_courses_fast(
        int $userid,
        string $classification,
        ?string $sort,
        ?string $customfieldname,
        ?string $customfieldvalue
    ): array {
        // id, category, and startdate are already part of this function's
        // own base field set - only enddate needs to be requested.
        $courses = enrol_get_all_users_courses($userid, true, ['enddate']);

        $hiddenids = self::get_hidden_course_ids($sort, $customfieldname, $customfieldvalue);
        if (!empty($hiddenids)) {
            $courses = array_filter($courses, function($course) use ($hiddenids) {
                return !isset($hiddenids[(int) $course->id]);
            });
        }

        if ($classification === 'all') {
            return array_values($courses);
        }

        $now = time();
        return array_values(array_filter($courses, function($course) use ($classification, $now) {
            if ($course->startdate > $now) {
                return $classification === 'future';
            }
            if (!empty($course->enddate) && $course->enddate < $now) {
                return $classification === 'past';
            }
            return $classification === 'inprogress';
        }));
    }

    /**
     * The set of course ids the user has individually hidden from their own
     * dashboard, as an id => true map, so get_courses_fast() can exclude
     * them the same way core's own buckets would.
     *
     * Deliberately asks core for just the 'hidden' classification rather
     * than re-deriving Moodle's hidden-course preference storage directly:
     * that storage is an internal implementation detail, not a public API,
     * so reading it ourselves would be fragile across Moodle versions. This
     * is cheap in practice even though it goes through the same expensive
     * per-course exporter as everything else in
     * get_courses_via_core() - almost no one hides more than a handful of
     * courses from their own dashboard, unlike 'all', which would decorate
     * every single enrolled course.
     *
     * @param string|null $sort
     * @param string|null $customfieldname
     * @param string|null $customfieldvalue
     * @return array
     */
    protected static function get_hidden_course_ids(?string $sort, ?string $customfieldname, ?string $customfieldvalue): array {
        // No $requiredfields override here either - see get_courses_via_core().
        $hidden = core_course_external::get_enrolled_courses_by_timeline_classification(
            'hidden',
            0,
            0,
            $sort,
            $customfieldname,
            $customfieldvalue
        );

        $ids = [];
        foreach ($hidden['courses'] as $course) {
            // See get_courses_via_core() - calling core directly like this
            // bypasses Moodle's usual clean_returnvalue() step.
            $course = (array) $course;
            $ids[(int) $course['id']] = true;
        }
        return $ids;
    }

    /**
     * Fetch the user's courses for a classification that still needs core's
     * own logic (favourites, hidden, customfield): delegates entirely,
     * rather than reimplementing the favourites service or per-user
     * hidden-course preferences ourselves.
     *
     * @param string $classification
     * @param string|null $sort
     * @param string|null $customfieldname
     * @param string|null $customfieldvalue
     * @return stdClass[]
     */
    protected static function get_courses_via_core(
        string $classification,
        ?string $sort,
        ?string $customfieldname,
        ?string $customfieldvalue
    ): array {
        // Deliberately does not restrict $requiredfields to ['id',
        // 'fullname']: core needs fields like enddate internally to compute
        // the classification itself, and on some Moodle versions
        // restricting requiredfields makes its own return-value validation
        // throw a coding_exception ("Unexpected property enddate") because
        // the field it still needs internally isn't declared in the
        // narrowed structure. Taking the default (full) field set and
        // picking out just id/fullname above avoids that entirely.
        $result = core_course_external::get_enrolled_courses_by_timeline_classification(
            $classification,
            0,
            0,
            $sort,
            $customfieldname,
            $customfieldvalue
        );

        $courses = [];
        foreach ($result['courses'] as $course) {
            // Calling get_enrolled_courses_by_timeline_classification()
            // directly like this bypasses Moodle's normal external-API
            // dispatch step (external_api::clean_returnvalue()), which is
            // what usually flattens each course's internal exporter data
            // into a plain array before a webservice response goes out.
            // Cast defensively so this works whether an individual course
            // comes back as a stdClass (the raw, un-flattened shape) or
            // already an array.
            $courses[] = (object) (array) $course;
        }
        return $courses;
    }

    /**
     * Get the distinct roles the user holds in each of the given courses,
     * including roles inherited from a parent context (course category or
     * system), in a small constant number of queries regardless of how many
     * courses there are.
     *
     * This replaces what used to be a call to get_user_roles() once per
     * course - correct, but a second, self-inflicted N+1 query pattern on
     * top of whatever the course list itself cost. Two queries fetch every
     * relevant role assignment for the user at once (one for direct
     * course-level assignments across all the given courses, one for
     * category- and system-level assignments that might apply to any of
     * them), then a category's ancestor path (already fetched, one more
     * query only if there are any category-level assignments to resolve)
     * is used to replicate get_user_roles()'s own default
     * $checkparentcontexts = true behaviour: a role assigned at a course's
     * category (or an ancestor category) or at the system level applies to
     * that course too, not just a direct course-level assignment.
     *
     * @param int $userid
     * @param stdClass[] $courses Must have ->id and ->category on each.
     * @return array courseid => [{shortname, name}, ...]
     */
    protected static function get_roles_by_course(int $userid, array $courses): array {
        global $DB;

        if (empty($courses)) {
            return [];
        }

        $courseids = array_map(function($course) {
            return (int) $course->id;
        }, $courses);

        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
        $sql = "SELECT ra.id, ra.roleid, ctx.instanceid AS courseid, r.name, r.shortname
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :courselevel
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :rauserid AND ctx.instanceid $courseinsql";
        $courserolerecords = $DB->get_records_sql($sql, array_merge($courseparams, [
            'courselevel' => CONTEXT_COURSE,
            'rauserid' => $userid,
        ]));

        $courserolesbycourseid = [];
        foreach ($courserolerecords as $record) {
            $courserolesbycourseid[(int) $record->courseid][(int) $record->roleid] = [
                'shortname' => $record->shortname,
                'name' => role_get_name((object) [
                    'id' => $record->roleid,
                    'name' => $record->name,
                    'shortname' => $record->shortname,
                ]),
            ];
        }

        $catsql = "SELECT ra.id, ra.roleid, ctx.contextlevel, ctx.instanceid, r.name, r.shortname
                     FROM {role_assignments} ra
                     JOIN {context} ctx ON ctx.id = ra.contextid
                     JOIN {role} r ON r.id = ra.roleid
                    WHERE ra.userid = :rauserid AND ctx.contextlevel IN (:catlevel, :syslevel)";
        $inheritedrolerecords = $DB->get_records_sql($catsql, [
            'rauserid' => $userid,
            'catlevel' => CONTEXT_COURSECAT,
            'syslevel' => CONTEXT_SYSTEM,
        ]);

        $systemroles = [];
        $categoryrolesbycategoryid = [];
        foreach ($inheritedrolerecords as $record) {
            $roleinfo = [
                'shortname' => $record->shortname,
                'name' => role_get_name((object) [
                    'id' => $record->roleid,
                    'name' => $record->name,
                    'shortname' => $record->shortname,
                ]),
            ];
            if ((int) $record->contextlevel === CONTEXT_SYSTEM) {
                $systemroles[(int) $record->roleid] = $roleinfo;
            } else {
                $categoryrolesbycategoryid[(int) $record->instanceid][(int) $record->roleid] = $roleinfo;
            }
        }

        $categorypaths = [];
        if (!empty($categoryrolesbycategoryid)) {
            $categoryids = array_unique(array_filter(array_merge(
                array_keys($categoryrolesbycategoryid),
                array_map(function($course) {
                    return (int) ($course->category ?? 0);
                }, $courses)
            )));
            if (!empty($categoryids)) {
                $categoryrecords = $DB->get_records_list('course_categories', 'id', $categoryids, '', 'id, path');
                foreach ($categoryrecords as $category) {
                    $categorypaths[(int) $category->id] = $category->path;
                }
            }
        }

        $rolesbycourse = [];
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            $roles = $courserolesbycourseid[$courseid] ?? [];
            $roles += $systemroles;

            $categoryid = (int) ($course->category ?? 0);
            if (!empty($categoryrolesbycategoryid) && isset($categorypaths[$categoryid])) {
                foreach (array_filter(explode('/', $categorypaths[$categoryid])) as $ancestorid) {
                    if (isset($categoryrolesbycategoryid[(int) $ancestorid])) {
                        $roles += $categoryrolesbycategoryid[(int) $ancestorid];
                    }
                }
            }

            $rolesbycourse[$courseid] = array_values($roles);
        }

        return $rolesbycourse;
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
