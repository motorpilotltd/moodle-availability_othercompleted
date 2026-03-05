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
 * Upgrade script for availability_othercompleted.
 *
 * @package    availability_othercompleted
 * @copyright  MU DOT MY PLT <support@mu.my>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade function for availability_othercompleted.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_availability_othercompleted_upgrade($oldversion) {
    if ($oldversion < 2024100700) {
        // Rename the JSON key 'cm' to 'course' in all othercompleted availability conditions.
        availability_othercompleted_rename_cm_to_course('course_modules');
        availability_othercompleted_rename_cm_to_course('course_sections');

        upgrade_plugin_savepoint(true, 2024100700, 'availability', 'othercompleted');
    }

    return true;
}

/**
 * Renames the 'cm' key to 'course' in othercompleted availability conditions
 * stored in the given table's availability column.
 *
 * @param string $table The table name ('course_modules' or 'course_sections').
 */
function availability_othercompleted_rename_cm_to_course($table) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    $rs = $DB->get_recordset_select($table, 'availability IS NOT NULL', [], '', 'id, availability');
    foreach ($rs as $record) {
        $availability = json_decode($record->availability);
        if (is_null($availability)) {
            continue;
        }

        $changed = availability_othercompleted_transform_structure($availability);
        if ($changed) {
            $record->availability = json_encode($availability);
            $DB->update_record($table, $record);
        }
    }
    $rs->close();

    $transaction->allow_commit();
}

/**
 * Recursively walks the availability tree and renames 'cm' to 'course'
 * on any othercompleted condition nodes.
 *
 * @param mixed $structure The availability structure (object or array), passed by reference.
 * @return bool True if any changes were made.
 */
function availability_othercompleted_transform_structure(&$structure) {
    $changed = false;

    if (is_array($structure)) {
        foreach ($structure as $item) {
            if (availability_othercompleted_transform_structure($item)) {
                $changed = true;
            }
        }
        return $changed;
    }

    if (!is_object($structure)) {
        return false;
    }

    // Recurse into children.
    if (isset($structure->c)) {
        if (availability_othercompleted_transform_structure($structure->c)) {
            $changed = true;
        }
    }

    // Transform this node if it's an othercompleted condition with the old 'cm' key.
    $isothercompleted = isset($structure->type) && $structure->type === 'othercompleted';
    if ($isothercompleted && isset($structure->cm)) {
        $structure->course = $structure->cm;
        unset($structure->cm);
        $changed = true;
    }

    return $changed;
}
