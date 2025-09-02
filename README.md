# filter_mermaidsvg

Server-side Mermaid rendering for Moodle using **Kroki**.  
Replaces ```mermaid fences with static **SVG/PNG** served via `pluginfile.php` so diagrams render in the **Moodle App** (no client JS).

## Settings
- **Kroki base URL**: `https://kroki.io` or your self-hosted instance.
- **Format**: `svg` (recommended) or `png`.
- **Timeout**: HTTP timeout in seconds.

## Development
- `make link MOODLEDIR=/path/to/moodle` → symlink into `filter/mermaidsvg`
- Visit *Site administration → Plugins → Filters → Manage filters*, enable **Mermaid (server-side via Kroki)** and set the base URL.

## Example
\`\`\`mermaid
flowchart TD
  A(["Pasta fresca"]) --> B["Cottura"]
  B --> C(["Salsa"])
  C --> D["Mantecatura"]
  D --> E["Impiattamento piatto caldo"]
  E --> F(["Topping"])
  F --> G["Pass"]
\`\`\`

## License
GPLv3 (compatible with Moodle). See `LICENSE`.
