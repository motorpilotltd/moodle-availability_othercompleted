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
 * Other course completion condition.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_othercompleted;

use base_logger;
use base_task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/completionlib.php');

/**
 * Condition that requires completion of another course.
 *
 * @package availability_othercompleted
 * @copyright MU DOT MY PLT <support@mu.my>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int ID of the course that this depends on */
    protected $courseid;

    /** @var int Expected completion type (one of the COMPLETE_xx constants) */
    protected $expectedcompletion;

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        // Get course ID.
        if (isset($structure->course) && is_number($structure->course)) {
            $this->courseid = (int)$structure->course;
        } else {
            throw new \coding_exception('Missing or invalid ->course for completion condition');
        }

        // Get expected completion.
        if (
            isset($structure->e) && in_array(
                $structure->e,
                [COMPLETION_COMPLETE, COMPLETION_INCOMPLETE]
            )
        ) {
            $this->expectedcompletion = $structure->e;
        } else {
            throw new \coding_exception('Missing or invalid ->e for completion condition');
        }
    }

    /**
     * Saves the condition data.
     *
     * @return \stdClass The condition data.
     */
    public function save() {
        return (object)[
            'type' => 'othercompleted',
            'course' => $this->courseid,
            'e' => $this->expectedcompletion,
        ];
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * Intended for unit testing, as normally the JSON values are constructed
     * by JavaScript code.
     *
     * @param int $courseid Course id of other course
     * @param int $expectedcompletion Expected completion value (COMPLETION_xx)
     * @return \stdClass The JSON structure.
     */
    public static function get_json($courseid, $expectedcompletion) {
        return (object)[
            'type' => 'othercompleted',
            'course' => (int)$courseid,
            'e' => (int)$expectedcompletion,
        ];
    }

    /**
     * Determines whether the condition is met.
     *
     * @param bool $not Whether the condition is negated.
     * @param \core_availability\info $info The availability info.
     * @param bool $grabthelot Whether to grab all data at once.
     * @param int $userid The user ID.
     * @return bool Whether the condition is met.
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        global $DB;

        $course = $this->courseid;
        $sqlcoursecomplete = "SELECT * FROM {course_completions} as a WHERE a.course = $course AND a.userid = $userid";
        $datacompletes = $DB->get_records_sql($sqlcoursecomplete);
        $completed = false;
        foreach ($datacompletes as $datacomplete) {
            if ($datacomplete->timecompleted > 0) {
                $completed = true;
            }
        }

        if ($this->expectedcompletion == COMPLETION_COMPLETE) {
            $allow = $completed;
        } else {
            $allow = !$completed;
        }

        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    /**
     * Returns a more readable keyword corresponding to a completion state.
     *
     * Used to make lang strings easier to read.
     *
     * @param int $completionstate COMPLETION_xx constant
     * @return string Readable keyword
     */
    protected static function get_lang_string_keyword($completionstate) {
        switch ($completionstate) {
            case COMPLETION_INCOMPLETE:
                return 'incomplete';
            case COMPLETION_COMPLETE:
                return 'complete';
            default:
                throw new \coding_exception('Unexpected completion state: ' . $completionstate);
        }
    }

    /**
     * Returns a human-readable description of the condition.
     *
     * @param bool $full Whether to show the full description.
     * @param bool $not Whether the condition is negated.
     * @param \core_availability\info $info The availability info.
     * @return string The description.
     */
    public function get_description($full, $not, \core_availability\info $info) {
        global $DB;
        // Get name for the course.
        $course = $DB->get_record('course', ['id' => $this->courseid], 'id, fullname');
        $modname = $course ? format_string($course->fullname) : get_string('missing', 'availability_othercompleted');

        // Work out which lang string to use.
        if ($not) {
            // Convert NOT strings to use the equivalent where possible.
            switch ($this->expectedcompletion) {
                case COMPLETION_INCOMPLETE:
                    $str = 'requires_' . self::get_lang_string_keyword(COMPLETION_COMPLETE);
                    break;
                case COMPLETION_COMPLETE:
                    $str = 'requires_' . self::get_lang_string_keyword(COMPLETION_INCOMPLETE);
                    break;
                default:
                    // The other two cases do not have direct opposites.
                    $str = 'requires_not_' . self::get_lang_string_keyword($this->expectedcompletion);
                    break;
            }
        } else {
            $str = 'requires_' . self::get_lang_string_keyword($this->expectedcompletion);
        }

        return get_string($str, 'availability_othercompleted', $modname);
    }

    /**
     * Returns a debug string for this condition.
     *
     * @return string Debug string.
     */
    protected function get_debug_string() {
        switch ($this->expectedcompletion) {
            case COMPLETION_COMPLETE:
                $type = 'COMPLETE';
                break;
            case COMPLETION_INCOMPLETE:
                $type = 'INCOMPLETE';
                break;
            default:
                throw new \coding_exception('Unexpected expected completion');
        }
        return 'course' . $this->courseid . ' ' . $type;
    }

    /**
     * Determines whether this condition should be included after a restore.
     *
     * @param string $restoreid The restore ID.
     * @param int $courseid The course ID.
     * @param base_logger $logger The logger.
     * @param string $name The name.
     * @param base_task $task The task.
     * @return bool Whether the condition should be included.
     */
    public function include_after_restore($restoreid, $courseid, base_logger $logger, $name, base_task $task) {
        global $DB;
        return $DB->record_exists('course', ['id' => $this->courseid]);
    }
}
