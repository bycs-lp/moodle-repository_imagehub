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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/repository/imagehub/lib.php');

/**
 * Tests for the imagehub manager.
 *
 * @package    repository_imagehub
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends \advanced_testcase {
    /**
     * Imports a zip archive with the given entries into a new zip source.
     *
     * @param array $entries filename => content
     * @return int the source id
     */
    private function import_zip(array $entries): int {
        global $DB;
        $sourceid = $DB->insert_record('repository_imagehub_sources', [
            'title' => 'Zip source',
            'type' => \repository_imagehub::SOURCE_TYPE_ZIP_VALUE,
            'timemodified' => time(),
            'lastupdate' => 0,
        ]);
        $files = [];
        foreach ($entries as $filename => $content) {
            $files[$filename] = [$content];
        }
        $zip = get_file_packer('application/zip')->archive_to_storage(
            $files,
            \context_system::instance()->id,
            'repository_imagehub',
            'upload',
            $sourceid,
            '/',
            'import.zip'
        );
        new manager();
        manager::import_files_from_zip($zip, $sourceid);
        return $sourceid;
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Non-image files from a zip archive must not be stored in the images area.
     *
     * @covers \repository_imagehub\manager::import_files_from_directory
     */
    public function test_import_skips_non_image_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sourceid = $this->import_zip(['payload.html' => '<script>alert(1)</script>']);

        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'repository_imagehub',
            'images',
            $sourceid,
            '/',
            'payload.html'
        );
        $this->assertFalse($file);
        $this->assertContains('/payload.html', manager::get_file_report()['files_error']);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Image files from a zip archive are still imported.
     *
     * @covers \repository_imagehub\manager::import_files_from_directory
     */
    public function test_import_keeps_image_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sourceid = $this->import_zip(['picture.png' => 'png content']);

        $file = get_file_storage()->get_file(
            \context_system::instance()->id,
            'repository_imagehub',
            'images',
            $sourceid,
            '/',
            'picture.png'
        );
        $this->assertNotFalse($file);
        $this->assertContains('/picture.png', manager::get_file_report()['files_imported']);
    }
}
