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
 * Front-end class.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_othercompleted;

/**
 * Front-end class for the other course completion condition.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * @var array Cached init parameters
     */
    protected $cacheparams = [];

    /**
     * @var string IDs of course and section for cache (if any)
     */
    protected $cachekey = '';

    /**
     * Returns the language strings needed by the JS.
     *
     * @return array Array of string keys.
     */
    protected function get_javascript_strings() {
        return ['option_complete', 'option_incomplete', 'label_course', 'label_completion'];
    }

    /**
     * Returns the init parameters for the JS.
     *
     * @param \stdClass $course The course.
     * @param \cm_info|null $cm The course module, if applicable.
     * @param \section_info|null $section The section, if applicable.
     * @return array The init parameters.
     */
    protected function get_javascript_init_params(
        $course,
        \cm_info $cm = null,
        \section_info $section = null
    ) {
        // Use cached result if available. The cache is just because we call it
        // twice (once from allow_add) so it's nice to avoid doing all the
        // print_string calls twice.
        $cachekey = $course->id . ',' . ($cm ? $cm->id : '') . ($section ? $section->id : '');
        if ($cachekey !== $this->cachekey) {
            $context = \context_course::instance($course->id);
            // Get all courses to fill the dropdown.
            $courses = [];
            global $DB;
            $sql = "SELECT * FROM {course} ORDER BY fullname ASC";
            $allcourses = $DB->get_records_sql($sql);
            foreach ($allcourses as $othercourse) {
                // Exclude the site course and the current course.
                if (($othercourse->category > 0) && ($othercourse->id != $course->id)) {
                    $courses[] = (object)[
                        'id' => $othercourse->id,
                        'name' => format_string($othercourse->fullname, true, ['context' => $context]),
                    ];
                }
            }
            $this->cachekey = $cachekey;
            $this->cacheparams = [$courses];
        }
        return $this->cacheparams;
    }

    /**
     * Checks whether this condition can be added.
     *
     * @param \stdClass $course The course.
     * @param \cm_info|null $cm The course module, if applicable.
     * @param \section_info|null $section The section, if applicable.
     * @return bool Whether the condition can be added.
     */
    protected function allow_add(
        $course,
        \cm_info $cm = null,
        \section_info $section = null
    ) {
        global $CFG;

        // Check if completion is enabled for the course.
        require_once($CFG->libdir . '/completionlib.php');
        $info = new \completion_info($course);
        if (!$info->is_enabled()) {
            return false;
        }

        // Check if there's at least one other course available.
        $params = $this->get_javascript_init_params($course, $cm, $section);
        return ((array)$params[0]) != false;
    }
}
