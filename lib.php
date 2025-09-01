<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve cached rendered images.
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
