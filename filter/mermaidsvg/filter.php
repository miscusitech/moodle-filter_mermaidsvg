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
 * Text filter for rendering Mermaid diagrams via Kroki and serving static images.
 *
 * @package    filter_mermaidsvg
 * @copyright  2025 Miscusi Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No direct access guard required: no side effects in this file.

/**
 * Filter implementation.
 */
class filter_mermaidsvg extends moodle_text_filter {

    /**
     * Filter callback to replace Mermaid code blocks with rendered images.
     *
     * @param string $text Input text potentially containing Mermaid code.
     * @param array $options Options from core.
     * @return string Filtered text.
     */
    public function filter($text, array $options = []) {
        if (!is_string($text) || $text === '') {
            return $text;
        }
        if (stripos($text, 'mermaid') === false) {
            return $text;
        }

        $bt = "\x60"; // Backtick character to avoid literal usage in strings.
        $patterns = [
            '/'.$bt.$bt.$bt.'(?:mermaid|mmd)\s+([\s\S]*?)'.$bt.$bt.$bt.'/i',
            '/\[mermaid\]([\s\S]*?)\[\/mermaid\]/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace_callback($pattern, function ($m) {
                $code = trim($m[1]);

                $kroki   = rtrim(get_config('filter_mermaidsvg', 'krokiurl') ?? 'https://kroki.io', '/');
                $format  = get_config('filter_mermaidsvg', 'format') ?? 'svg';
                $timeout = (int)(get_config('filter_mermaidsvg', 'timeout') ?? 5);

                $hash = sha1("v1|{$format}|".$code);
                $filename = $hash.'.'.$format;

                $fs = get_file_storage();
                $context = context_system::instance();
                $existing = $fs->get_file($context->id, 'filter_mermaidsvg', 'rendered', 0, '/', $filename);

                if (!$existing) {
                    $rendered = $this->render_via_kroki($kroki, $format, $code, $timeout);
                    if ($rendered !== null) {
                        $fileinfo = [
                            'contextid' => $context->id,
                            'component' => 'filter_mermaidsvg',
                            'filearea'  => 'rendered',
                            'itemid'    => 0,
                            'filepath'  => '/',
                            'filename'  => $filename,
                        ];
                        $existing = $fs->create_file_from_string($fileinfo, $rendered);
                    }
                }

                if ($existing) {
                    $url = moodle_url::make_pluginfile_url(
                        $existing->get_contextid(),
                        $existing->get_component(),
                        $existing->get_filearea(),
                        $existing->get_itemid(),
                        $existing->get_filepath(),
                        $existing->get_filename()
                    )->out(false);

                    $first = strtok($code, "\n");
                    $alt = 'Mermaid diagram';
                    if ($first) {
                        $alt .= ' - '.strip_tags(substr($first, 0, 120));
                    }

                    return '<img class="mermaid-svg" src="'.s($url).'" alt="'.s($alt).'" />';
                }
                return '<pre class="mermaid-code">'.s($code).'</pre>';
            }, $text);
        }

        return $text;
    }

    /**
     * Render Mermaid code via Kroki.
     *
     * @param string $base Kroki base URL.
     * @param string $format Output format (svg|png).
     * @param string $code Mermaid code.
     * @param int $timeout HTTP timeout.
     * @return string|null Binary or SVG text on success, null otherwise.
     */
    private function render_via_kroki(string $base, string $format, string $code, int $timeout): ?string {
        $url = $base.'/mermaid/'.($format === 'png' ? 'png' : 'svg');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
            CURLOPT_POSTFIELDS     => $code,
            CURLOPT_TIMEOUT        => $timeout,
        ]);

        $data = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300 && $data !== false && $data !== '') {
            return $data;
    }

    debugging('Mermaid Kroki render failed: HTTP '.$status.' '.$err, DEBUG_DEVELOPER);
    return null;
    }
}
