<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'filter_mermaidsvg/krokiurl',
        get_string('krokiurl', 'filter_mermaidsvg'),
        get_string('krokiurl_desc', 'filter_mermaidsvg'),
        'https://kroki.io',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configselect(
        'filter_mermaidsvg/format',
        get_string('format', 'filter_mermaidsvg'),
        get_string('format_desc', 'filter_mermaidsvg'),
        'svg',
        ['svg' => 'SVG', 'png' => 'PNG']
    ));

    $settings->add(new admin_setting_configtext(
        'filter_mermaidsvg/timeout',
        get_string('timeout', 'filter_mermaidsvg'),
        get_string('timeout_desc', 'filter_mermaidsvg'),
        5,
        PARAM_INT
    ));
}
