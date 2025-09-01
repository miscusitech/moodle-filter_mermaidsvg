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
 * Library callbacks for filter_mermaidsvg.
 *
 * @package    filter_mermaidsvg
 * @copyright  2025 Miscusi Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve cached rendered images.
 *
 * @package    filter_mermaidsvg
 * @param stdClass $course Course object.
 * @param stdClass|null $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Remaining file path arguments.
 * @param bool $forcedownload Force download flag.
 * @param array $options Send file options.
 * @return bool|void False if file not found, otherwise outputs file and exits.
 */
function filter_mermaidsvg_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== 'rendered') {
        return false;
    }

    $fs = get_file_storage();
    $itemid = 0;
    $filepath = '/';
    $filename = implode('/', $args);

    if (!$file = $fs->get_file($context->id, 'filter_mermaidsvg', 'rendered', $itemid, $filepath, $filename)) {
        return false;
    }
    send_stored_file($file, 0, 0, false, $options);
}
