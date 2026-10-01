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

namespace repository_imagehub;

/**
 * Tests for the pluginfile callback of repository_imagehub.
 *
 * @package    repository_imagehub
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Files of the hub are only served to users holding repository/imagehub:view.
     *
     * @covers ::repository_imagehub_pluginfile
     */
    public function test_pluginfile_requires_view_capability(): void {
        global $CFG;
        require_once($CFG->dirroot . '/repository/imagehub/lib.php');
        $this->resetAfterTest();
        $context = \context_system::instance();
        $args = [1, 'missing.png'];

        $this->setGuestUser();
        try {
            repository_imagehub_pluginfile(null, null, $context, 'images', $args, false);
            $this->fail('Expected required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(repository_imagehub_pluginfile(null, null, $context, 'images', $args, false));
    }
}
