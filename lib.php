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
 * repository_imagehub plugin implementation
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/repository}
 *
 * @package    repository_imagehub
 * @copyright  2024 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Repository repository_imagehub implementation
 *
 * @package    repository_imagehub
 * @copyright  2024 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_imagehub extends repository {
    /** @var string The manual source type. */
    public const SOURCE_TYPE_MANUAL = 'manual';
    /** @var string The value of the manual source type. */
    public const SOURCE_TYPE_MANUAL_VALUE = '0';
    /** @var string The zip source type. */
    public const SOURCE_TYPE_ZIP = 'zip';
    /** @var string The value of the zip source type. */
    public const SOURCE_TYPE_ZIP_VALUE = '1';

    /**
     * Given a path, and perhaps a search, get a list of files.
     *
     * See details on {@link http://docs.moodle.org/dev/Repository_plugins}
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return array the list of files, including meta infomation, containing the following keys
     *           manage, url to manage url
     *           client_id
     *           login, login form
     *           repo_id, active repository id
     *           login_btn_action, the login button action
     *           login_btn_label, the login button label
     *           total, number of results
     *           perpage, items per page
     *           page
     *           pages, total pages
     *           issearchresult, is it a search result?
     *           list, file list
     *           path, current path and parent path
     */
    public function get_listing($path = '', $page = '') {
        $ret = [
            'list' => self::get_file_list($path, $page),
            'norefresh' => true,
            'nologin' => true,
            'dynload' => true,
            'nosearch' => false,
        ];
        return $ret;
    }

    /**
     * Search for files.
     *
     * @param string $search
     * @param int $page
     * @return void
     */
    public function search($search, $page = 0) {
        return [
            'list' => $this->get_file_list('', $page, $search),
            'norefresh' => true,
            'nologin' => true,
            'dynload' => true,
            'nosearch' => false,
            'issearchresult' => true,
        ];
    }

    /**
     * Get a list of files.
     * @param string $path
     * @param string $page
     * @param string $search
     * @return array
     */
    public function get_file_list($path = '', $page = '', $search = ''): array {
        global $DB, $OUTPUT;
        $sourceids = $DB->get_fieldset('repository_imagehub_sources', 'id');
        $filelist = [];

        $fs = get_file_storage();
        $files = [];
        foreach ($sourceids as $sourceid) {
            $files = array_merge(
                $files,
                $fs->get_directory_files(
                    \context_system::instance()->id,
                    'repository_imagehub',
                    'images',
                    $sourceid,
                    '/',
                    true,
                    false
                )
            );
        }
        $where = '';
        $params = [];
        if (!empty($search)) {
            $where = $DB->sql_like('title', ':search', false, false);
            $where .= ' OR ' . $DB->sql_like('description', ':search2', false, false);
            $params = [
                'search' => '%' . $DB->sql_like_escape($search) . '%',
                'search2' => '%' . $DB->sql_like_escape($search) . '%',
            ];
        }
        $results = $DB->get_records_select('repository_imagehub', $where, $params, '', 'fileid, title');
        foreach ($files as $file) {
            if (!str_starts_with($file->get_mimetype(), 'image') || !array_key_exists($file->get_id(), $results)) {
                continue;
            }
            $node['thumbnail'] = $OUTPUT->image_url(file_extension_icon($file->get_filename()))->out(false);
            $filelistentry = [
                'title' => $file->get_filename() ?? $results[$file->get_id()]->description,
                'shorttitle' => $results[$file->get_id()]->title ?? $file->get_filename(),
                'size' => $file->get_filesize(),
                'filename' => $file->get_filename(),
                'thumbnail' => $OUTPUT->image_url(file_extension_icon($file->get_filename()))->out(false),
                'icon' => $OUTPUT->image_url(file_extension_icon($file->get_filename()))->out(false),
                'source' => $file->get_id(),
                'author' => $file->get_author(),
                'license' => $file->get_license() ?? 'U',
            ];

            if ($file->get_mimetype() == 'image/svg+xml') {
                $filelistentry['realthumbnail'] = $this->get_thumbnail_url($file, 'thumb')->out(false);
                $filelistentry['realicon'] = $this->get_thumbnail_url($file, 'icon')->out(false);
                $filelistentry['image_width'] = 100;
                $filelistentry['image_height'] = 100;
            } else if ($imageinfo = @getimagesizefromstring($file->get_content())) {
                $filelistentry['realthumbnail'] = $this->get_thumbnail_url($file, 'thumb')->out(false);
                $filelistentry['realicon'] = $this->get_thumbnail_url($file, 'icon')->out(false);
                $filelistentry['image_width'] = $imageinfo[0];
                $filelistentry['image_height'] = $imageinfo[1];
            }
            $filelist[] = $filelistentry;
        }
        return $filelist;
    }

    /**
     * This plugin supports only web images.
     */
    public function supported_filetypes() {
        return ['web_image'];
    }

    /**
     * Repository supports only internal files.
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }

    /**
     * Create an instance for this plug-in
     *
     * @param string $type the type of the repository
     * @param int $userid the user id
     * @param stdClass $context the context
     * @param array $params the options for this instance
     * @param int $readonly whether to create it readonly or not (defaults to not)
     * @return mixed
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public static function create($type, $userid, $context, $params, $readonly = 0) {
        require_capability('moodle/site:config', context_system::instance());
        return parent::create($type, $userid, $context, $params, $readonly);
    }

    /**
     * Get the configuration form for this repository type.
     * @param moodleform $mform
     * @param string $classname
     */
    public static function type_config_form($mform, $classname = 'repository_imagehub') {
        $type = repository::get_type_by_typename('imagehub');

        // Link to managesources.
        if ($type !== null) {
            $url = new moodle_url('/repository/imagehub/managesources.php');
            $mform->addElement(
                'static',
                null,
                get_string('linktomanagesources', 'repository_imagehub', $url),
                get_string('linktomanagesources_description', 'repository_imagehub')
            );
        } else {
            $mform->addElement(
                'static',
                null,
                get_string('repositorynotenabled', 'repository_imagehub'),
                ''
            );
        }
    }

    /**
     * Return names of the general options.
     * By default: no general option name
     *
     * @return array
     */
    public static function get_type_option_names() {
        return ['pluginname'];
    }

    /**
     * Is this repository used to browse moodle files?
     *
     * @return boolean
     */
    public function has_moodle_files() {
        return true;
    }

    /**
     * Returns url of thumbnail file.
     *
     * @param stored_file $file current path in repository (dir and filename)
     * @param string $thumbsize 'thumb' or 'icon'
     * @return moodle_url
     */
    protected static function get_thumbnail_url($file, $thumbsize) {
        return moodle_url::make_pluginfile_url(
            context_system::instance()->id,
            'repository_imagehub',
            $thumbsize,
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * Returns thumbnail file.
     *
     * @param stored_file $file
     * @param string $thumbsize 'thumb' or 'icon'
     * @return stored_file|null
     */
    public static function get_thumbnail($file, $thumbsize) {
        global $CFG;
        $filecontents = $file->get_content();

        $fs = get_file_storage();
        if (
            !($thumbfile = $fs->get_file(
                context_system::instance()->id,
                'repository_imagehub',
                $thumbsize,
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            ))
        ) {
            require_once($CFG->libdir . '/gdlib.php');
            if ($thumbsize === 'thumb') {
                $size = 90;
            } else {
                $size = 24;
            }
            if (!$data = generate_image_thumbnail_from_string($filecontents, $size, $size)) {
                return null;
            }
            $record = [
                'contextid' => context_system::instance()->id,
                'component' => 'repository_imagehub',
                'filearea' => $thumbsize,
                'itemid' => $file->get_itemid(),
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ];
            $thumbfile = $fs->create_file_from_string($record, $data);
        }
        return $thumbfile;
    }

    /**
     * Get the file reference.
     *
     * @param int $fileid
     * @return string
     */
    public function get_file_reference($fileid) {
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($fileid);
        $filerecord = [
            'component' => $file->get_component(),
            'filearea'  => $file->get_filearea(),
            'itemid'    => $file->get_itemid(),
            'author'    => $file->get_author(),
            'filepath'  => $file->get_filepath(),
            'filename'  => $file->get_filename(),
            'contextid' => $file->get_contextid(),
        ];
        return file_storage::pack_reference($filerecord);
    }

    /**
     * Return whether the file is accessible.
     *
     * @param string $fileid
     * @return bool
     */
    public function file_is_accessible($fileid) {
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($fileid);
        return $file->get_component() == 'repository_imagehub';
    }
}

/**
 * Deliver a file from the repository.
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool|null
 */
function repository_imagehub_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): ?bool {
    global $OUTPUT;
    require_login();
    require_capability('repository/imagehub:view', context_system::instance());
    $fullpath = "/1/repository_imagehub/images/" . implode('/', $args);
    $fs = get_file_storage();
    if ((!$file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    if ($filearea === 'thumb' || $filearea === 'icon') {
        if (str_ends_with($fullpath, '.svg')) {
            // SVG files are not resized.
            $file = $fs->get_file_by_hash(sha1($fullpath));
        } else {
            if (!($file = repository_imagehub::get_thumbnail($file, $filearea))) {
                // Generation failed, redirect to default icon for file extension.
                // Do not use redirect() here because is not compatible with webservice/pluginfile.php.
                header('Location: ' . $OUTPUT->image_url(file_extension_icon($file)));
            }
        }
    }

    send_stored_file($file, 0, 0, false, $options);
}
