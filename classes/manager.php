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

use core_tag_tag;
use moodle_exception;
use stored_file;

/**
 * Class manager
 *
 * @package    repository_imagehub
 * @copyright  2024 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var array $filereport Data for the file report after uploading a zip. */
    protected static $filereport;

    /**
     * manager constructor.
     */
    public function __construct() {
        self::$filereport = [
            'has_data' => false,
            'files_imported' => [],
            'files_error' => [],
            'files_replaced' => [],
            'stats' => [
                'total' => 0,
                'imported' => 0,
                'error' => 0,
                'replaced' => 0,
            ],
        ];
    }


    /**
     * Get the file report.
     * @return array The file report.
     */
    public static function get_file_report() {
        return self::$filereport;
    }


    /**
     * Add a file to the repository.
     * @param stored_file $file
     * @param string $filepath The path of the file.
     * @param int $sourceid The id of the source.
     * @param array $metadata
     */
    public static function add_item(stored_file $file, string $filepath, int $sourceid, array $metadata = []) {
        global $DB;
        $source = $DB->get_record('repository_imagehub_sources', ['id' => $sourceid], '*', MUST_EXIST);
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => context_system::instance()->id,
            'component' => 'repository_imagehub',
            'filearea' => 'images',
            'itemid' => $sourceid,
            'filepath' => $filepath,
            'filename' => $file->get_filename(),
        ];

        $filerecord = array_merge($metadata, $filerecord);

        $newfile = $fs->create_file_from_storedfile($filerecord, $file);
        $recordid = $DB->insert_record('repository_imagehub', [
            'fileid' => $newfile->get_id(),
            'source' => $sourceid,
            'description' => join(',', $metadata['tags']),
        ]);

        foreach ($metadata['tags'] as $tag) {
            core_tag_tag::add_item_tag(
                'repository_imagehub',
                'repository_imagehub',
                $recordid,
                \core\context\system::instance(),
                $tag
            );
        }
    }

    /**
     * Remove a file from the repository.
     * @param int $itemid The id of the item.
     */
    public static function remove_item(int $itemid) {
        global $DB;
        $record = $DB->get_record('repository_imagehub', ['id' => $itemid], '*', MUST_EXIST);
        $fs = get_file_storage();
        $fs->delete_area_files(
            $record->contextid,
            'repository_imagehub',
            'images',
            $record->fileid
        );
        $DB->delete_records('repository_imagehub', ['id' => $itemid]);
    }

    /**
     * Update a file in the repository.
     * @param int $itemid The id of the item.
     * @param array $metadata The metadata to update.
     */
    public static function update_item(int $itemid, array $metadata) {
        global $DB;
        $record = $DB->get_record('repository_imagehub', ['id' => $itemid], '*', MUST_EXIST);
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($record->fileid);
        if (isset($metadata['title'])) {
            $DB->update_record('repository_imagehub', [
                'id' => $itemid,
                'title' => $metadata['title'],
                'description' => join(',', $metadata['tags']),
            ]);
        }
        if (isset($metadata['tags'])) {
            core_tag_tag::set_item_tags(
                'repository_imagehub',
                'repository_imagehub',
                $itemid,
                \context_system::instance(),
                $metadata['tags']
            );
        }
        if (isset($metadata['author'])) {
            $file->set_author($metadata['author']);
        }
        if (isset($metadata['license'])) {
            $file->set_license($metadata['license']);
        }
    }

    /**
     * Process metadata for a source.
     * @param int $sourceid The id of the source.
     */
    public static function process_metadata(int $sourceid) {
        global $DB;
        $source = $DB->get_record('repository_imagehub_sources', ['id' => $sourceid], '*', MUST_EXIST);
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'repository_imagehub', 'images', $sourceid);
        foreach ($files as $file) {
            if ($file->get_filename() == 'metadata.json') {
                $metadata = json_decode($file->get_content(), true);
                // Handle metadata files that refer to only one image.
                if (!array_key_exists('images', $metadata)) {
                    $metadata = ['images' => [$metadata]];
                }

                foreach ($metadata['images'] as $metadataitem) {
                    $imagefile = $fs->get_file(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $metadataitem['filename']
                    );
                    if ($imagefile) {
                        $item = self::get_item_from_fileid($imagefile->get_id());
                        self::update_item($item->id, $metadataitem);
                    }
                }
                $file->delete();
            }
        }
    }

    /**
     * Get an item from a file id.
     * @param int $fileid The id of the file.
     * @return object The item.
     */
    public static function get_item_from_fileid(int $fileid): object {
        global $DB;
        return $DB->get_record('repository_imagehub', ['fileid' => $fileid], '*', MUST_EXIST);
    }

    /**
     * Import files from a zip file.
     * @param stored_file $zip The zip file.
     * @param int $sourceid The id of the source.
     * @param bool $deleteold Whether to delete old files. Default is false.
     */
    public static function import_files_from_zip(stored_file $zip, int $sourceid, bool $deleteold = false) {
        global $DB;
        $source = $DB->get_record('repository_imagehub_sources', ['id' => $sourceid], '*', MUST_EXIST);

        $fp = get_file_packer('application/zip');
        $zip->extract_to_storage($fp, \context_system::instance()->id, 'repository_imagehub', 'temp', $sourceid, '/');

        $fs = get_file_storage();
        $directory = $fs->get_file(\context_system::instance()->id, 'repository_imagehub', 'temp', $sourceid, '/', '.');

        self::import_files_from_directory($directory, $sourceid, $deleteold);

        self::process_metadata($sourceid);

        $fs->delete_area_files(\context_system::instance()->id, 'repository_imagehub', 'temp', $sourceid, '/');
    }

    /**
     * Import files from a directory and create a detailed report.
     * @param stored_file $directory The directory.
     * @param int $sourceid The id of the source.
     * @param bool $deleteold Whether to delete old files. Default is false.
     */
    public static function import_files_from_directory(stored_file $directory, int $sourceid, bool $deleteold = false) {
        global $DB;
        $source = $DB->get_record('repository_imagehub_sources', ['id' => $sourceid], '*', MUST_EXIST);

        $fs = get_file_storage();
        if (!$directory->is_directory()) {
            throw new moodle_exception('not_a_directory', 'repository_imagehub');
        }

        $files = $fs->get_directory_files(
            $directory->get_contextid(),
            $directory->get_component(),
            $directory->get_filearea(),
            $directory->get_itemid(),
            $directory->get_filepath(),
            true,
            false
        );

        foreach ($files as $file) {
            if (
                $file->get_filename() !== 'metadata.json'
                && !file_extension_in_typegroup($file->get_filename(), 'web_image', true)
            ) {
                self::$filereport['files_error'][] = $file->get_filepath() . $file->get_filename();
                continue;
            }
            try {
                $targetfile = $fs->get_file(
                    \context_system::instance()->id,
                    'repository_imagehub',
                    'images',
                    $sourceid,
                    $file->get_filepath(),
                    $file->get_filename()
                );
                if (!$targetfile) {
                    $targetfile = $fs->create_file_from_storedfile([
                        'contextid' => \context_system::instance()->id,
                        'component' => 'repository_imagehub',
                        'filearea' => 'images',
                        'itemid' => $file->get_itemid(),
                        'filepath' => $file->get_filepath(),
                        'filename' => $file->get_filename(),
                    ], $file);
                    $DB->insert_record('repository_imagehub', [
                        'fileid' => $targetfile->get_id(),
                        'source' => $sourceid,
                    ]);

                    self::$filereport['files_imported'][] = $file->get_filepath() . $file->get_filename();
                } else {
                    if ($targetfile->get_contenthash() != $file->get_contenthash()) {
                        $targetfile->replace_file_with($file);
                        $DB->update_record('repository_imagehub', [
                            'fileid' => $targetfile->get_id(),
                            'source' => $sourceid,
                        ]);
                        self::$filereport['files_replaced'][] = $file->get_filepath() . $file->get_filename();
                    }
                }
            } catch (\Exception $e) {
                // Skip file if an error occurs.
                self::$filereport['files_error'][] = $file->get_filepath() . $file->get_filename();
            }
        }

        // Calculate the stats.
        self::$filereport['stats']['error'] = count(self::$filereport['files_error']);
        self::$filereport['stats']['imported'] = count(self::$filereport['files_imported']);
        self::$filereport['stats']['replaced'] = count(self::$filereport['files_replaced']);
        // Set has_data to true if anything has been imported in order to show filereport in filereport.mustache-template.
        self::$filereport['has_data'] = self::$filereport['stats']['imported'] > 0 || self::$filereport['stats']['replaced'] > 0;
    }

    /**
     * Import files from a git repository. Not implemented yet.
     * @param \moodle_url $gitrepository The git repository.
     */
    public static function import_files_from_git(\moodle_url $gitrepository) {
    }

    /**
     * Import files from a web url. Not implemented yet.
     * @param \moodle_url $weburl The web url.
     */
    public static function import_files_from_web(\moodle_url $weburl) {
    }

    /**
     * Delete a source.
     * @param int $sourceid The id of the source.
     */
    public static function delete_source($sourceid) {
        global $DB;
        $fs = get_file_storage();
        $fs->delete_area_files(\context_system::instance()->id, 'repository_imagehub', 'images', $sourceid);
        $DB->delete_records('repository_imagehub', ['source' => $sourceid]);
        $DB->delete_records('repository_imagehub_sources', ['id' => $sourceid]);
    }
}
