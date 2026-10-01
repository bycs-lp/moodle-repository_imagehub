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
 * Class delete_source
 *
 * @package    repository_imagehub
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_imagehub\external;

use repository_imagehub\manager;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Delete a source.
 */
class delete_source extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Source id.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the service.
     *
     * @param int $id
     * @return array
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public static function execute(int $id): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('repository/imagehub:managerepositories', $context);

        manager::delete_source($params['id']);

        return ['result' => true];
    }

    /**
     * Describes the return structure of the service..
     *
     * @return external_single_structure the return structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'Removed successfully.'),
        ]);
    }
}
