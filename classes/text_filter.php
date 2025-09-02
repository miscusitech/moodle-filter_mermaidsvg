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
 * Namespaced text filter for rendering Mermaid diagrams via Kroki.
 *
 * @package    filter_mermaidsvg
 * @copyright  2025 Miscusi Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_mermaidsvg;

/**
 * Filter implementation.
 */
class text_filter extends \moodle_text_filter {
    /**
     * Replace Mermaid code blocks with rendered images served via pluginfile.
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
            '/\[mermaid(?:[^\]]*)\]([\s\S]*?)\[\/mermaid\]/iu',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace_callback($pattern, function ($m) {
                $code = $this->normalize_mermaid_code($m[1]);

                $kroki   = rtrim(\get_config('filter_mermaidsvg', 'krokiurl') ?? 'https://kroki.io', '/');
                $format  = \get_config('filter_mermaidsvg', 'format') ?? 'svg';
                $timeout = (int)(\get_config('filter_mermaidsvg', 'timeout') ?? 5);

                $hash = sha1("v1|{$format}|".$code);
                $filename = $hash.'.'.$format;

                $fs = \get_file_storage();
                $context = \context_system::instance();
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
                    $url = \moodle_url::make_pluginfile_url(
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

                    return '<img class="mermaid-svg" src="'.\s($url).'" alt="'.\s($alt).'" />';
                }

                return '<pre class="mermaid-code">'.\s($code).'</pre>';
            }, $text);
        }

        return $text;
    }

    /**
     * Normalize Mermaid code possibly wrapped in HTML produced by editors.
     * - Convert common block tags and <br> to newlines
     * - Strip all remaining tags
     * - Decode HTML entities (&gt;, &amp;, &nbsp;)
     * - Normalize whitespace and remove zero-width chars
     *
     * @param string $raw Raw HTML/text between [mermaid] tags or code fences.
     * @return string Clean Mermaid source code.
     */
    private function normalize_mermaid_code(string $raw): string {
        // Defensive: ensure string to avoid deprecation warnings in preg_replace.
        $code = (string)$raw;
        \debugging('normalize_mermaid_code input: "' . $code);

        // Normalize newlines early (use PCRE2-compatible escapes).
        // Matches: CRLF or CR, VT, FF, NEL, LS, PS.
        $code = preg_replace("/\r\n?|\x0B|\x0C|\x{85}|\x{2028}|\x{2029}/u", "\n", $code);
        \debugging('normalize_mermaid_code step 1: "' . $code);

        // Map HTML breaks and common block boundaries to newlines.
        $code = preg_replace('/<br\s*\/?\s*>/i', "\n", $code);
        $blocktags  = '(?:p|div|li|h[1-6]|tr|pre|code|section|article|';
        $blocktags .= 'blockquote|dd|dt|tbody|thead|tfoot)';
        $code = preg_replace('/<\/' . $blocktags . '\s*>/i', "\n", $code);
        $code = preg_replace('/<' . $blocktags . '(?:\s+[^>]*)?>/i', "\n", $code);
        \debugging('normalize_mermaid_code step 2: "' . $code);

        // Strip any remaining tags.
        $code = strip_tags($code);
        \debugging('normalize_mermaid_code step 3: "' . $code);

        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        \debugging('normalize_mermaid_code step 4.1: "'.$code.'"');

        // Sostituisci SOLO gli NBSP veri (U+00A0, e affini) in modo Unicode-safe.
        // NIENTE "\xA0" singolo byte: rompe l'UTF-8.
        $code = preg_replace('/[\x{00A0}\x{202F}\x{2007}]+/u', ' ', $code);
        \debugging('normalize_mermaid_code step 4.2: "'.$code.'"');

        // Rimuovi caratteri zero-width / BOM. Gestisci errori di PCRE su UTF-8 malformato.
        $tmp = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $code);
        if ($tmp === null) {
            // Recupero: ripulisci byte invalidi e riprova.
            $code = iconv('UTF-8', 'UTF-8//IGNORE', $code);
            $tmp = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $code);
        }
        $code = $tmp ?? $code;
        \debugging('normalize_mermaid_code step 5: "'.$code.'"');

        // Collapse excessive blank lines but keep intentional paragraphs.
        $code = preg_replace("/\n{3,}/", "\n\n", $code);
        \debugging('normalize_mermaid_code step 6: "' . $code);

        // Trim trailing spaces on each line and remove leading/trailing blank lines.
        $lines = array_map(function ($l) {
            return rtrim($l);
        }, explode("\n", $code));
        while ($lines && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines && trim(end($lines)) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
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

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
            CURLOPT_POSTFIELDS     => $code,
            CURLOPT_TIMEOUT        => $timeout,
        ]);

        $data = \curl_exec($ch);
        $status = \curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err   = \curl_error($ch);
        \curl_close($ch);

        if ($status >= 200 && $status < 300 && $data !== false && $data !== '') {
            return $data;
        }

        \debugging('Mermaid Kroki render failed: HTTP '.$status.' '.$err, DEBUG_DEVELOPER);
        return null;
    }
}
