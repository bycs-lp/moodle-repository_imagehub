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
 * Upgrade steps for Imagehub
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    repository_imagehub
 * @category   upgrade
 * @copyright  2025 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_repository_imagehub_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025021300) {
        // Define field description to be added to repository_imagehub.
        $table = new xmldb_table('repository_imagehub');
        $field = new xmldb_field('description', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'title');

        // Conditionally launch add field description.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Imagehub savepoint reached.
        upgrade_plugin_savepoint(true, 2025021300, 'repository', 'imagehub');
    }

    if ($oldversion < 2026041701) {
        core_tag_area::reset_definitions_for_component('repository_imagehub');

        // Imagehub savepoint reached.
        upgrade_plugin_savepoint(true, 2026041701, 'repository', 'imagehub');
    }

    return true;
}
