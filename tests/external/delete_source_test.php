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

namespace repository_imagehub\external;

use core_external\external_api;
use core_external\restricted_context_exception;

/**
 * Tests for the delete_source external function.
 *
 * @package    repository_imagehub
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \repository_imagehub\external\delete_source
 */
final class delete_source_test extends \advanced_testcase {
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * The deletion honours the context restriction of the calling session.
     *
     * @covers \repository_imagehub\external\delete_source::execute
     */
    public function test_execute_validates_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $sourceid = $DB->insert_record('repository_imagehub_sources', [
            'title' => 'Source',
            'type' => 'manual',
            'timemodified' => time(),
            'lastupdate' => time(),
        ]);
        $course = $this->getDataGenerator()->create_course();

        external_api::set_context_restriction(\context_course::instance($course->id));
        try {
            delete_source::execute($sourceid);
            $this->fail('Expected restricted_context_exception');
        } catch (restricted_context_exception $e) {
            $this->assertTrue($DB->record_exists('repository_imagehub_sources', ['id' => $sourceid]));
        }

        external_api::set_context_restriction(null);
        $this->assertSame(['result' => true], delete_source::execute($sourceid));
        $this->assertFalse($DB->record_exists('repository_imagehub_sources', ['id' => $sourceid]));
    }
}
