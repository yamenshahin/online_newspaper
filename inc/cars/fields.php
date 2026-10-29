<?php
/**
 * Car_Spec_Renderer: an ACF field group (group_car_details) as spec sections - one per tab, with a
 * sticky anchor nav. Plain fields pool into spec tables (the single-car box / zebra pattern), groups
 * become captioned tables, repeaters grid tables (transposed when wider than 6 columns), colour
 * repeaters swatches, wysiwyg a rich-text block, oembed a responsive video.
 *
 * Fields are skipped BY NAME (HIDDEN_CAR_FIELDS = the import's التعريف / الصور / إضافي tabs), so a
 * relabelled tab stays hidden. Empty values are never printed; a tab with nothing left is dropped.
 *
 *   $renderer = new Car_Spec_Renderer(get_the_ID());
 *   $renderer->render('group_car_details');                 // nav + sections
 *   $out = $renderer->group('group_car_details', []);       // ['nav' => links, 'html' => sections, 'sections' => [...]]
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Car_Spec_Renderer
{
    private const LAYOUT = ['tab', 'message', 'accordion'];

    /** Top-level fields of the hidden tabs (see acf-json/group_car_details.json) plus the importer's price_source. */
    public const HIDDEN_CAR_FIELDS = [
        // التعريف: source ids / urls / dates, the parent relationship and the names shown in the page header
        'source_url', 'source_category_id', 'trim_name', 'model_year', 'full_name', 'spec_region', 'published_at', 'parent_model_year', 'scraped_at', 'content_hash',
        // الصور: the gallery is the hero, the source image URLs are import data
        'gallery', 'source_image_urls',
        // إضافي: highlights are shown at the top of the page, the rest is parser output
        'highlights', 'specs_extra', 'conflicts',
        // how the importer found the price
        'price_source',
    ];

    private const BOX = 'overflow-hidden rounded-xl border border-surface-container-high bg-surface-container-lowest shadow-xs';
    private const ROW = 'odd:bg-surface-container-low';
    private const TH = 'car-spec-th px-4 py-3 text-start align-top font-semibold text-on-surface-variant';
    private const TD = 'px-4 py-3 text-on-surface';
    private const TH_COL = 'whitespace-nowrap px-4 py-3 text-start font-semibold text-on-surface-variant';
    private const NONE = '<span class="text-on-surface-variant opacity-70">—</span>';
    private const MAX_LINKS = 60;

    /** @var int|string post id, or "term_{id}" for a term */
    private $object_id;
    private string $section_label = '';
    private int $thumb_index = 0;

    /** @param int|string $object_id post id or "term_{id}" */
    public function __construct($object_id)
    {
        $this->object_id = $object_id;
    }

    /**
     * Render a field group as tab sections.
     *
     * @param string[]|null $skip_fields field names not to render (null = HIDDEN_CAR_FIELDS, [] = everything)
     * @return array{nav: string, html: string, sections: array<int, array{id: string, label: string}>}
     */
    public function group(string $group_key, ?array $skip_fields = null): array
    {
        // ACF formats oembed values with a live wp_oembed_get() request on every call: skip it, the
        // renderer embeds the video from the raw URL itself (see video()).
        add_filter('pre_oembed_result', [self::class, 'skip_oembed'], 10, 2);
        try {
            return $this->sections($group_key, $skip_fields ?? self::HIDDEN_CAR_FIELDS);
        } finally {
            remove_filter('pre_oembed_result', [self::class, 'skip_oembed'], 10);
        }
    }

    /** pre_oembed_result: hand the URL back so no request is made. */
    public static function skip_oembed($pre, $url)
    {
        return is_string($url) ? $url : '';
    }

    /** @return array{nav: string, html: string, sections: array<int, array{id: string, label: string}>} */
    private function sections(string $group_key, array $skip): array
    {
        $fields = function_exists('acf_get_fields') ? (acf_get_fields($group_key) ?: []) : [];
        $tabs = [];
        $current = ['label' => 'عام', 'fields' => []];
        foreach ($fields as $f) {
            if ($f['type'] === 'tab') {
                $tabs[] = $current;
                $current = ['label' => (string) $f['label'], 'fields' => []];
                continue;
            }
            if (in_array($f['type'], self::LAYOUT, true) || in_array($f['name'], $skip, true)) {
                continue;
            }
            $current['fields'][] = $f;
        }
        $tabs[] = $current;

        $nav = '';
        $html = '';
        $sections = [];
        foreach ($tabs as $tab) {
            if (!$tab['fields']) {
                continue;
            }
            $this->section_label = $tab['label'];
            $body = $this->fields($tab['fields'], '', null);
            if ($body === '') {
                continue;
            }
            $id = 'spec-' . (count($sections) + 1);
            $sections[] = ['id' => $id, 'label' => $tab['label']];
            $nav .= '<a class="car-spec-tab" href="#' . esc_attr($id) . '">' . esc_html($tab['label']) . '</a>';
            $html .= '<section class="car-spec-section" id="' . esc_attr($id) . '" aria-labelledby="' . esc_attr($id) . '-title">'
                . '<h2 class="car-spec-heading mb-4 font-headline-sm text-headline-sm text-on-background" id="' . esc_attr($id) . '-title">' . esc_html($tab['label']) . '</h2>'
                . $body . '</section>';
        }
        return ['nav' => $nav, 'html' => $html, 'sections' => $sections];
    }

    /** Sticky anchor nav followed by the sections (nothing when every tab is empty). */
    public function render(string $group_key, ?array $skip_fields = null): void
    {
        $out = $this->group($group_key, $skip_fields);
        if ($out['html'] === '') {
            return;
        }
        echo self::nav($out['nav']) . $out['html']; // escaped above
    }

    /** Wrap the anchor links of group() in the sticky tab bar. */
    public static function nav(string $links): string
    {
        return $links === ''
            ? ''
            : '<nav class="car-spec-tabs" data-car-spec-tabs aria-label="أقسام المواصفات">' . $links . '</nav>';
    }

    // ----------------------------------------------------------------- fields

    /**
     * Plain fields pool into one spec table; groups, repeaters and wysiwyg get their own block.
     * $values = the formatted group value for sub-fields, null = read each field from the object.
     */
    private function fields(array $fields, string $meta_prefix, ?array $values, string $caption = ''): string
    {
        $body = '';
        $rows = '';
        foreach ($fields as $f) {
            if (in_array($f['type'], self::LAYOUT, true)) {
                continue;
            }
            $meta = $meta_prefix . $f['name'];
            $value = $values === null ? get_field($f['name'], $this->object_id) : ($values[$f['name']] ?? null);
            if (in_array($f['type'], ['group', 'repeater', 'wysiwyg'], true)) {
                $body .= $this->table($rows, $caption);
                $rows = '';
                $body .= $this->block($f, $value, $meta);
            } else {
                $rows .= $this->row($f, $value, $meta);
            }
        }
        return $body . $this->table($rows, $caption);
    }

    private function table(string $rows, string $caption = ''): string
    {
        if ($rows === '') {
            return '';
        }
        return '<div class="car-spec-table mb-4 ' . self::BOX . '">'
            . ($caption !== '' ? '<div class="border-b border-surface-container-high bg-surface-container-low px-4 py-3 text-sm font-semibold text-on-surface">' . esc_html($caption) . '</div>' : '')
            . '<table class="w-full text-start"><tbody>' . $rows . '</tbody></table></div>';
    }

    private function row(array $f, $value, string $meta): string
    {
        $out = $this->value($f, $value, $meta);
        if ($out === '') {
            return '';
        }
        return '<tr class="' . self::ROW . '"><th class="' . self::TH . '" scope="row">' . esc_html($f['label']) . '</th><td class="' . self::TD . '">' . $out . '</td></tr>';
    }

    /** group / repeater / wysiwyg: their own block. */
    private function block(array $f, $value, string $meta): string
    {
        if ($f['type'] === 'wysiwyg') {
            if (trim(wp_strip_all_tags((string) $value)) === '') {
                return '';
            }
            return '<div class="car-review mb-4 rounded-2xl border border-surface-container-high bg-surface-container-lowest p-6 text-body-base text-on-surface ' . esc_attr(car_rich_text_classes()) . '">' . wp_kses_post($value) . '</div>';
        }
        if ($f['type'] === 'group') {
            $caption = (string) $f['label'] === $this->section_label ? '' : (string) $f['label'];
            return $this->fields($f['sub_fields'] ?? [], $meta . '_', is_array($value) ? $value : [], $caption);
        }
        return $this->repeater($f, $value, $meta);
    }

    private function repeater(array $f, $value, string $meta): string
    {
        $subs = array_values(array_filter($f['sub_fields'] ?? [], static fn($sf) => !in_array($sf['type'], self::LAYOUT, true)));
        $rows = is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
        if (!$rows || !$subs) {
            return '';
        }
        // sub-fields with a value in at least one row
        $used = [];
        foreach ($subs as $sf) {
            foreach ($rows as $r) {
                if ($this->value($sf, $r[$sf['name']] ?? null, '') !== '') {
                    $used[] = $sf;
                    break;
                }
            }
        }
        if (!$used) {
            return '';
        }
        $title = '<h3 class="mb-3 mt-6 font-title-editorial text-title-editorial text-on-background">' . esc_html($f['label'])
            . ' <span class="text-body-sm font-normal text-on-surface-variant">(' . count($rows) . ')</span></h3>';
        if ($this->is_colors($subs)) {
            return $title . $this->colors($rows);
        }
        if (count($used) > 6) {
            return $title . $this->transposed($used, $rows);
        }
        $head = '';
        foreach ($used as $sf) {
            $head .= '<th class="' . self::TH_COL . '" scope="col">' . esc_html($sf['label']) . '</th>';
        }
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr class="' . self::ROW . '">';
            foreach ($used as $sf) {
                $cell = $this->value($sf, $r[$sf['name']] ?? null, '');
                $body .= '<td class="' . self::TD . ($sf['type'] === 'number' ? ' text-center' : '') . '">' . ($cell !== '' ? $cell : self::NONE) . '</td>';
            }
            $body .= '</tr>';
        }
        return $title . '<div class="car-table-scroll mb-4 ' . self::BOX . '"><table class="car-grid-table w-full text-start"><thead><tr class="border-b border-surface-container-high bg-surface-container-low">'
            . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    /** Wide repeaters (powertrains): one column per row, one line per sub-field. */
    private function transposed(array $used, array $rows): string
    {
        $head = '';
        foreach ($rows as $i => $r) {
            $head .= '<th class="' . self::TH_COL . '" scope="col">' . esc_html((string) ($r['name'] ?? ('#' . ($i + 1)))) . '</th>';
        }
        $body = '';
        foreach ($used as $sf) {
            if ($sf['name'] === 'name') {
                continue;
            }
            $body .= '<tr class="' . self::ROW . '"><th class="' . self::TH . ' whitespace-nowrap" scope="row">' . esc_html($sf['label']) . '</th>';
            foreach ($rows as $r) {
                $cell = $this->value($sf, $r[$sf['name']] ?? null, '');
                $body .= '<td class="' . self::TD . ' whitespace-nowrap">' . ($cell !== '' ? $cell : self::NONE) . '</td>';
            }
            $body .= '</tr>';
        }
        return '<div class="car-table-scroll mb-4 ' . self::BOX . '"><table class="car-grid-table w-full text-start"><thead><tr class="border-b border-surface-container-high bg-surface-container-low"><th class="' . self::TH_COL . '"></th>'
            . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    private function is_colors(array $subs): bool
    {
        foreach ($subs as $sf) {
            if ($sf['type'] === 'color_picker') {
                return true;
            }
        }
        return false;
    }

    /** Colour repeater (name, hex, hex_2): round swatches, two-tone when a second colour is set. */
    private function colors(array $rows): string
    {
        $out = '';
        foreach ($rows as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            $hex = $this->hex((string) ($r['hex'] ?? ''));
            $hex2 = $this->hex((string) ($r['hex_2'] ?? ''));
            if ($name === '' && $hex === '') {
                continue;
            }
            $bg = $hex2 !== '' ? "linear-gradient(135deg, {$hex} 50%, {$hex2} 50%)" : ($hex !== '' ? $hex : 'transparent');
            $out .= '<span class="car-color inline-flex items-center gap-2 rounded-full border border-surface-container-high bg-surface-container-lowest px-3 py-1 text-sm text-on-surface" title="' . esc_attr(trim($hex . ($hex2 !== '' ? ' / ' . $hex2 : ''))) . '">'
                . '<span class="car-swatch" style="background:' . esc_attr($bg) . '"></span>' . esc_html($name) . '</span>';
        }
        return $out === '' ? '' : '<div class="car-colors mb-4 flex flex-wrap gap-2">' . $out . '</div>';
    }

    private function hex(string $v): string
    {
        $v = strtolower(trim($v));
        return preg_match('/^#[0-9a-f]{3,8}$/', $v) ? $v : '';
    }

    // ----------------------------------------------------------------- value formatting ('' = empty)

    private function value(array $f, $v, string $meta): string
    {
        switch ($f['type']) {
            case 'true_false':
                // ACF formats an unset flag as false: hide it unless the import wrote an explicit value
                if ($v === null || $v === '' || (!$v && $meta !== '' && $this->raw_meta($meta) === '')) {
                    return '';
                }
                return $v
                    ? '<span class="font-semibold text-primary"><i class="fas fa-check" aria-hidden="true"></i> نعم</span>'
                    : '<span class="text-on-surface-variant"><i class="fas fa-xmark" aria-hidden="true"></i> لا</span>';
            case 'number':
                if (!is_numeric($v)) {
                    return '';
                }
                return '<span class="car-ltr">' . esc_html(format_number($v)) . '</span>'
                    . (!empty($f['append']) ? ' <span class="text-body-sm text-on-surface-variant">' . esc_html($f['append']) . '</span>' : '');
            case 'select':
            case 'radio':
            case 'checkbox':
                $keys = array_filter(is_array($v) ? $v : [$v], static fn($k) => $k !== null && $k !== '');
                if (!$keys) {
                    return '';
                }
                return esc_html(implode('، ', array_map(static fn($k) => (string) ($f['choices'][$k] ?? $k), $keys)));
            case 'url':
                return is_string($v) && $v !== ''
                    ? '<a class="car-ltr text-primary underline" href="' . esc_url($v) . '" target="_blank" rel="noopener">' . esc_html(rawurldecode($v)) . '</a>'
                    : '';
            case 'oembed':
                $raw = $this->raw_meta($meta);
                return $this->video($raw !== '' ? $raw : (is_string($v) ? $v : ''));
            case 'color_picker':
                $hex = $this->hex((string) $v);
                return $hex === '' ? '' : '<span class="car-swatch" style="background:' . esc_attr($hex) . '"></span> <span class="car-ltr text-body-sm text-on-surface-variant">' . esc_html($hex) . '</span>';
            case 'relationship':
            case 'post_object':
                $items = array_filter((array) $v);
                if (!$items) {
                    return '';
                }
                $links = [];
                foreach (array_slice($items, 0, self::MAX_LINKS) as $p) {
                    $id = $p instanceof WP_Post ? $p->ID : (int) $p;
                    if ($id) {
                        $links[] = '<a class="text-primary hover:underline" href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a>';
                    }
                }
                return implode('، ', $links) . (count($items) > self::MAX_LINKS ? ' … (+' . (count($items) - self::MAX_LINKS) . ')' : '');
            case 'image':
                $id = is_array($v) ? (int) ($v['ID'] ?? $v['id'] ?? 0) : (int) $v;
                return $id ? (string) wp_get_attachment_image($id, 'medium', false, ['class' => 'rounded-xl', 'loading' => 'lazy']) : '';
            case 'gallery':
                $ids = array_filter(array_map(static fn($i) => is_array($i) ? (int) ($i['ID'] ?? $i['id'] ?? 0) : (int) $i, is_array($v) ? $v : []));
                return $ids ? $this->thumbs(array_values($ids), (string) $f['label']) : '';
            case 'wysiwyg':
                return trim(wp_strip_all_tags((string) $v)) === '' ? '' : '<div class="' . esc_attr(car_rich_text_classes()) . '">' . wp_kses_post($v) . '</div>';
            case 'textarea':
                return trim((string) $v) === '' ? '' : nl2br(esc_html($v));
            default:
                if (is_array($v)) {
                    $v = implode(' | ', array_map('strval', array_filter($v, 'is_scalar')));
                }
                return trim((string) $v) === '' ? '' : esc_html((string) $v);
        }
    }

    /** Thumbnails of a gallery field (wheel designs), opened full screen by the lightbox. */
    private function thumbs(array $ids, string $label): string
    {
        $out = '';
        foreach ($ids as $id) {
            $thumb = (string) wp_get_attachment_image_url($id, 'thumbnail');
            $large = (string) wp_get_attachment_image_url($id, 'large');
            $full = (string) wp_get_attachment_url($id);
            if ($large === '' && $full === '') {
                continue;
            }
            $i = $this->thumb_index++;
            $out .= '<button type="button" class="car-thumb" data-full="' . esc_url($large ?: $full) . '" data-orig="' . esc_url($full ?: $large) . '" data-index="' . $i . '" aria-label="' . esc_attr($label . ' ' . ($i + 1)) . '">'
                . '<img class="car-thumb-pic" src="' . esc_url($thumb ?: $large ?: $full) . '" alt="' . esc_attr($label) . '" loading="lazy" decoding="async"></button>';
        }
        return $out === '' ? '' : '<div class="car-thumbs car-thumbs-inline" data-car-lightbox>' . $out . '</div>';
    }

    /** YouTube URLs (the source's videos) become a privacy-friendly iframe, anything else a link: no live oEmbed requests. */
    private function video(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?(?:.*&)?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return '<div class="car-video aspect-video overflow-hidden rounded-xl bg-surface-container"><iframe class="h-full w-full" src="' . esc_url('https://www.youtube-nocookie.com/embed/' . $m[1]) . '" title="فيديو الاستعراض" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        }
        return '<a class="car-ltr text-primary underline" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>';
    }

    /** Raw meta value of the object (ACF stores group sub-fields as group_sub). */
    private function raw_meta(string $meta): string
    {
        if (is_numeric($this->object_id)) {
            return (string) get_post_meta((int) $this->object_id, $meta, true);
        }
        if (preg_match('/_(\d+)$/', (string) $this->object_id, $m)) {
            return (string) get_term_meta((int) $m[1], $meta, true);
        }
        return '';
    }
}
