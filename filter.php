<?php
defined('MOODLE_INTERNAL') || die();

class filter_mermaidsvg extends moodle_text_filter {

    public function filter($text, array $options = []) {
        if (!is_string($text) || $text === '') {
            return $text;
        }
        if (stripos($text, 'mermaid') === false) {
            return $text;
        }

        $patterns = [
            '/```(?:mermaid|mmd)\s+([\s\S]*?)```/i',
            '/\[mermaid\]([\s\S]*?)\[\/mermaid\]/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace_callback($pattern, function($m) {
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
                    if ($first) { $alt .= ' - '.strip_tags(substr($first, 0, 120)); }

                    return '<img class="mermaid-svg" src="'.s($url).'" alt="'.s($alt).'" />';
                }
                return '<pre class="mermaid-code">'.s($code).'</pre>';
            }, $text);
        }

        return $text;
    }

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
        } else {
            debugging('Mermaid Kroki render failed: HTTP '.$status.' '.$err, DEBUG_DEVELOPER);
            return null;
        }
    }
}
