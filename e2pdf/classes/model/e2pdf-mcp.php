<?php

/**
 * File: /model/e2pdf-mcp.php
 *
 * @package  E2Pdf
 * @license  GPLv3
 * @link     https://e2pdf.com
 */
if (!defined('ABSPATH')) {
    die('Access denied.');
}

class Model_E2pdf_Mcp extends Model_E2pdf_Model {

    // supported protocol versions, latest first
    protected $protocol_versions = [
        '2025-11-25',
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
    ];
    // template settings available for create_template / edit_template
    protected $template_settings = [
        'title',
        'name',
        'savename',
        'password',
        'owner_password',
        'format',
        'flatten',
        'inline',
        'auto',
        'rtl',
        'dpdf',
        'dataset_title',
        'dataset_title1',
        'dataset_title2',
        'button_title',
        'font',
        'font_size',
        'font_color',
        'line_height',
        'text_align',
        'lang_code',
        'meta_title',
        'meta_subject',
        'meta_author',
        'meta_keywords',
        'compression',
        'optimization',
        'resample',
        'appearance',
        'tab_order',
        'width',
        'height',
        'access_check',
    ];
    // shortcodes processed during pdf rendering besides registered wordpress shortcodes
    protected $shortcode_pattern = '/^e2pdf-(?:dataset2?|userid|usercurrentid|url|uid|lang|num|arg[0-9]*|if(?:-condition|-do|-else)?(?:-[0-9]+)?|for(?:-data|-do|-else|-value|-key|-index|-counter|-evenodd|-count)?(?:-[0-9]+)?|foreach(?:-value|-key|-index|-counter|-evenodd|-x)?(?:-[0-9]+)?|acf-repeater(?:-index|-counter|-evenodd|-count)?(?:-[0-9]+)?)$/';
    // shortcodes which must be closed: [tag]...[/tag]
    protected $shortcode_enclosing = [
        'e2pdf-format-date',
        'e2pdf-format-number',
        'e2pdf-format-output',
        'e2pdf-math',
        'e2pdf-if',
        'e2pdf-for',
        'e2pdf-translate',
    ];
    // shortcodes reference for get_shortcodes (https://e2pdf.com/docs/shortcodes/helper, https://e2pdf.com/docs/shortcodes/logic)
    protected $shortcodes_reference = [
        'rules' => [
            'Field values ({form:...}, [e2pdf-wp ...], etc.) differ per extension: take them from get_fields, never construct them.',
            'Only use the shortcodes listed here. Shortcodes like [e2pdf-date] or [e2pdf-field] do not exist.',
            'Wrapper shortcodes (e2pdf-format-date, e2pdf-format-number, e2pdf-format-output, e2pdf-math, e2pdf-translate) take the value between opening and closing tag: [tag attributes]VALUE[/tag]. VALUE may contain field values and other shortcodes. They must always be closed, also when VALUE is a single field value.',
            'Attribute values are written in double quotes.',
            'Use shortcodes only when the output really needs them. A field value is written as is: an empty value prints nothing, so it needs no [e2pdf-if]. Field elements (input, textarea, select, checkbox, radio) take the plain field value from get_fields. To hide an e2pdf-html element or its page when its value is empty use its properties hide_if_empty / hide_page_if_empty, not [e2pdf-if].',
            'If a shortcode is used once with a closing tag (e.g. [e2pdf-user id="dynamic"]...[/e2pdf-user]), every other use of the same shortcode in the same element value must be closed too: [e2pdf-user key="ID"][/e2pdf-user]. Otherwise WordPress pairs the opening tag with the wrong closing tag.',
            'Processing order inside a value: [e2pdf-for] loops first, then [e2pdf-if] conditions, then the other shortcodes. So loop placeholders like [e2pdf-for-value] can be used inside [e2pdf-if-condition], and formatting shortcodes can wrap field values inside loops and conditions.',
        ],
        'placeholders' => [
            '[e2pdf-dataset]' => 'Dataset (entry) ID the PDF is generated for.',
            '[e2pdf-dataset2]' => 'Second dataset ID (templates with merged items only).',
            '[e2pdf-userid]' => 'ID of the user the PDF is generated for.',
            '[e2pdf-usercurrentid]' => 'ID of the currently logged in user.',
            '[e2pdf-url]' => 'Download URL of this PDF.',
            '[e2pdf-uid]' => 'Unique ID of this PDF download.',
            '[e2pdf-num]' => 'Counter: how many times the PDF was generated for this entry (+1).',
            '[e2pdf-lang]' => 'Current language code.',
            '[e2pdf-arg key="arg1"]' => 'Argument passed on generation (generate_pdf arguments or shortcode attribute arg1="..."). Short form: [e2pdf-arg1].',
            '[e2pdf-page-number]' => 'Current page number. Only works in the value of an e2pdf-page-number element (not in e2pdf-html).',
            '[e2pdf-page-total]' => 'Total number of pages. Only works in the value of an e2pdf-page-number element (not in e2pdf-html).',
        ],
        'shortcodes' => [
            [
                'shortcode' => 'e2pdf-format-date',
                'syntax' => '[e2pdf-format-date format="F j, Y"]VALUE[/e2pdf-format-date]',
                'description' => 'Formats a date. VALUE: a date field value or any date text readable by PHP strtotime, a Unix timestamp with timestamp="true", or the keywords "now" (current server datetime), "time" (current UTC datetime), "current_time" (current WordPress datetime, site timezone).',
                'attributes' => [
                    'format' => 'PHP date() format, e.g. "F j, Y", "d/m/Y", "Y-m-d H:i:s". Default: WordPress date format.',
                    'offset' => 'Add or remove time before formatting, e.g. "+30 days", "-1 month".',
                    'function' => '"date" (default, PHP date(), not translated) or "wp_date" (WordPress wp_date(): required for translated month/day names, locale and timezone). "date_i18n" is also accepted.',
                    'locale' => 'Language for translated names with function="wp_date", e.g. "it_IT". The language must be installed in WordPress.',
                    'timezone' => 'Timezone with function="wp_date", e.g. "America/Los_Angeles".',
                    'timestamp' => '"true" when VALUE is a Unix timestamp (e.g. 1681984741).',
                    'gmt' => '"true" with VALUE "current_time" to output the WordPress UTC datetime.',
                ],
                'examples' => [
                    '[e2pdf-format-date format="F j, Y"]current_time[/e2pdf-format-date]',
                    '[e2pdf-format-date format="d.m.Y"]{form:date}[/e2pdf-format-date]',
                    '[e2pdf-format-date format="F j, Y" offset="+30 days"]current_time[/e2pdf-format-date]',
                    '[e2pdf-format-date function="wp_date" locale="it_IT" format="j F Y"]current_time[/e2pdf-format-date]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-format-number',
                'syntax' => '[e2pdf-format-number decimal="2" dec_point="." thousands_sep=","]VALUE[/e2pdf-format-number]',
                'description' => 'Formats a number.',
                'attributes' => [
                    'decimal' => 'Number of decimal digits.',
                    'dec_point' => 'Decimal point separator. Default: "."',
                    'thousands_sep' => 'Thousands separator. Default: none.',
                    'explode' => 'Split VALUE by this separator and format each number.',
                    'implode' => 'Join the formatted numbers with this separator.',
                ],
                'examples' => [
                    '[e2pdf-format-number decimal="2" thousands_sep=","]{form:amount}[/e2pdf-format-number]',
                    '[e2pdf-format-number explode="," implode="," decimal="2"]{form:amounts}[/e2pdf-format-number]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-math',
                'syntax' => '[e2pdf-math decimal="2"]EXPRESSION[/e2pdf-math]',
                'description' => 'Calculates an arithmetic expression (+ - * / and parentheses) and formats the result like e2pdf-format-number.',
                'attributes' => [
                    'decimal' => 'Number of decimals of the result.',
                    'dec_point' => 'Decimal separator of the result.',
                    'thousands_sep' => 'Thousands separator of the result.',
                    'default' => 'Result when the expression is empty. Default: 0.',
                    'thousands_sep_split' => 'Thousands separator used in the input values. Default: ","',
                    'dec_point_split' => 'Decimal separator used in the input values. Default: "."',
                ],
                'examples' => [
                    '[e2pdf-math decimal="2"]{form:price} * {form:quantity}[/e2pdf-math]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-format-output',
                'syntax' => '[e2pdf-format-output filter="FILTERS"]VALUE[/e2pdf-format-output]',
                'description' => 'Modifies the output of a field value or shortcode. search/replace, substr and sprintf are applied to the whole VALUE before explode; filters are applied in the listed order to each exploded part.',
                'attributes' => [
                    'filter' => 'Comma separated, applied in this order: trim, ltrim, rtrim, ucfirst, ucwords, strtoupper, strtolower, lines (removes empty lines), nl2br, wpautop, normalize_whitespace, strip_tags, sanitize_title, htmlentities, html_entity_decode, urlencode, urldecode. The names replace, ireplace, substr, sprintf, truncate in filter set the position of that operation in the filter order (its attribute is still required), e.g. filter="trim,substr" substr="0,10".',
                    'search' => 'Text to search, several separated by "|||". Used with replace or ireplace.',
                    'replace' => 'Case-sensitive replacement for search, several separated by "|||".',
                    'ireplace' => 'Case-insensitive replacement for search, several separated by "|||".',
                    'substr' => 'Part of the string: "start" or "start,length", e.g. "2,1".',
                    'sprintf' => 'PHP sprintf format, e.g. "%01.2f", "%05d".',
                    'truncate' => 'Max number of characters, "..." is appended.',
                    'explode' => 'Split VALUE by this separator; filters are applied to each part.',
                    'implode' => 'Join the parts with this separator.',
                    'output' => 'With explode: output only selected parts by index, e.g. "{0}", "{1}" or "{0} / {2}"; "{count}" outputs the number of parts.',
                    'pre' => 'Text added before each non-empty part (not with output).',
                    'after' => 'Text added after each non-empty part (not with output).',
                ],
                'examples' => [
                    '[e2pdf-format-output filter="strtoupper"]{form:name}[/e2pdf-format-output]',
                    '[e2pdf-format-output filter="trim,ucfirst"]{form:name}[/e2pdf-format-output]',
                    '[e2pdf-format-output explode="," output="{0}"]{form:list}[/e2pdf-format-output]',
                    '[e2pdf-format-output explode="," implode="-"]{form:list}[/e2pdf-format-output]',
                    '[e2pdf-format-output search="opt1|||opt2" replace="val1|||val2"]{form:choice}[/e2pdf-format-output]',
                    '[e2pdf-format-output pre="Phone: "]{form:phone}[/e2pdf-format-output]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-if',
                'syntax' => '[e2pdf-if][e2pdf-if-condition]VALUE[==]TEXT[/e2pdf-if-condition][e2pdf-if-do]OUTPUT IF TRUE[/e2pdf-if-do][e2pdf-if-else]OUTPUT IF FALSE[/e2pdf-if-else][/e2pdf-if]',
                'description' => 'Conditional output, only for content that depends on a value (different text per value, or a label + value inside one e2pdf-html element that must disappear together). Not needed to print a field value or to hide an empty element (see rules). [e2pdf-if-else] is optional. Write comparators without spaces around them (spaces become part of the compared text). A condition without comparator ([e2pdf-if-condition]VALUE[/e2pdf-if-condition]) is true when VALUE exists / is not empty. Conditions joined with [&&] / [||] are evaluated left to right without precedence: group with [(] [)]. Nested conditions inside do/else use the suffix -1 on all their tags: [e2pdf-if-1][e2pdf-if-condition-1]...[/e2pdf-if-condition-1][e2pdf-if-do-1]...[/e2pdf-if-do-1][e2pdf-if-else-1]...[/e2pdf-if-else-1][/e2pdf-if-1] (next level -2).',
                'attributes' => [
                    'comparators' => 'VALUE[==]TEXT equal, [===] identical, [!=] not equal, [!==] not identical, [>] greater, [>=] greater or equal, [<] less, [<=] less or equal, VALUE[contains]TEXT, VALUE[not_contains]TEXT, LIST[in_list]LIST / LIST[not_in_list]LIST (all comma separated items of the left list are / are not in the right list).',
                    'array comparators' => 'The array (serialized array value) is always on the RIGHT side: [is_array]ARRAY (nothing on the left), TEXT[in_array]ARRAY, TEXT[not_in_array]ARRAY, KEY[array_key_exists]ARRAY, KEY[array_key_not_exists]ARRAY, KEY[isset]ARRAY, KEY[not_isset]ARRAY.',
                    'logic' => '[&&] (and), [||] (or), [(] and [)] for grouping.',
                ],
                'examples' => [
                    '[e2pdf-if][e2pdf-if-condition]{form:country}[==]Germany[/e2pdf-if-condition][e2pdf-if-do]EU customer[/e2pdf-if-do][e2pdf-if-else]Non-EU customer[/e2pdf-if-else][/e2pdf-if]',
                    '[e2pdf-if][e2pdf-if-condition]{form:total}[>]100[&&]{form:vip}[==]yes[/e2pdf-if-condition][e2pdf-if-do]Free shipping[/e2pdf-if-do][/e2pdf-if]',
                    '[e2pdf-if][e2pdf-if-condition][(]{form:a}[==]1[||]{form:b}[==]2[)][&&]{form:c}[==]3[/e2pdf-if-condition][e2pdf-if-do]Ok[/e2pdf-if-do][/e2pdf-if]',
                    '[e2pdf-if][e2pdf-if-condition]{form:phone}[/e2pdf-if-condition][e2pdf-if-do]<br />Phone: {form:phone}[/e2pdf-if-do][/e2pdf-if] (label + value inside a longer e2pdf-html text)',
                    '[e2pdf-if][e2pdf-if-condition]Option 1[in_array]{form:checkboxes}[/e2pdf-if-condition][e2pdf-if-do]Option 1 selected[/e2pdf-if-do][/e2pdf-if]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-for',
                'syntax' => '[e2pdf-for implode=", "][e2pdf-for-data]ARRAY VALUE[/e2pdf-for-data][e2pdf-for-do]...[e2pdf-for-value]...[/e2pdf-for-do][e2pdf-for-else]OUTPUT IF EMPTY[/e2pdf-for-else][/e2pdf-for]',
                'description' => 'Loops over an array value (e.g. a repeater / multi value field returning a serialized array). [e2pdf-for-else] is optional (output when the data is empty). Inside [e2pdf-for-do]: [e2pdf-for-value] (item value; attribute path="0.name" for a key of a nested array, implode=", " to join an array item), [e2pdf-for-key], [e2pdf-for-index] (0-based), [e2pdf-for-counter] (1-based), [e2pdf-for-count] (total items), [e2pdf-for-evenodd] (0 / 1). Nested loops inside [e2pdf-for-do] use the suffix -1 on all their tags: [e2pdf-for-1][e2pdf-for-data-1][e2pdf-for-value path="items"][/e2pdf-for-data-1][e2pdf-for-do-1][e2pdf-for-value-1][/e2pdf-for-do-1][/e2pdf-for-1]. Loop around table rows (<table>[e2pdf-for]...<tr>...</tr>...[/e2pdf-for]</table>): set the e2pdf-html property wysiwyg_disable "1".',
                'attributes' => [
                    'implode' => 'Separator to merge the loop outputs.',
                ],
                'examples' => [
                    '[e2pdf-for implode="<br>"][e2pdf-for-data]{form:items}[/e2pdf-for-data][e2pdf-for-do][e2pdf-for-counter]. [e2pdf-for-value][/e2pdf-for-do][/e2pdf-for]',
                    '[e2pdf-for implode=", "][e2pdf-for-data]{form:repeater}[/e2pdf-for-data][e2pdf-for-do][e2pdf-for-value path="name"][/e2pdf-for-do][e2pdf-for-else]No items[/e2pdf-for-else][/e2pdf-for]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-user',
                'syntax' => '[e2pdf-user key="user_email"]',
                'description' => 'WordPress user data / user meta. Without id: the user at the moment the PDF URL was generated.',
                'attributes' => [
                    'key' => 'User data: ID (default), user_login, user_email, user_nicename, user_url, user_registered, display_name, user_firstname, user_lastname, user_description, roles. With meta="true": a user meta key.',
                    'meta' => '"true" to read the user meta key.',
                    'id' => 'User ID, "current" (always the user viewing the PDF) or "dynamic" (user ID taken from VALUE: [e2pdf-user id="dynamic" key="..."]VALUE[/e2pdf-user]; then every other [e2pdf-user] in the same element must be closed too).',
                    'implode' => 'Join array values (e.g. roles, array meta) with this separator.',
                    'path' => 'Key of an array meta value, e.g. "0" or "0.name".',
                    'attachment_url' => '"true": convert an attachment ID meta value to the attachment URL.',
                    'attachment_image_url' => '"true": convert an attachment ID meta value to the image URL; size="thumbnail|medium|medium_large|large|full" (default thumbnail).',
                ],
                'examples' => [
                    '[e2pdf-user key="display_name"]',
                    '[e2pdf-user key="roles" implode=", "]',
                    '[e2pdf-user key="billing_phone" meta="true"]',
                    '[e2pdf-user id="current" key="user_email"]',
                    '[e2pdf-user key="META_KEY" meta="true" attachment_image_url="true" size="full"]',
                ],
            ],
            [
                'shortcode' => 'e2pdf-translate',
                'syntax' => '[e2pdf-translate]TEXT[/e2pdf-translate]',
                'description' => 'Translates TEXT with the active translation plugin.',
                'attributes' => [
                    'domain' => 'Text domain for WordPress translations.',
                    'context' => 'Translation context.',
                ],
                'examples' => [],
            ],
        ],
    ];
    // html / css reference for get_html_reference, as supported by the e2pdf-html renderer (iText HTMLWorker v2 / v3)
    protected $html_reference = [
        'rules' => [
            'e2pdf-html is rendered by a simple HTML-to-PDF parser, not a browser. Only the tags and CSS properties listed here have an effect; everything else is silently ignored (text inside unknown tags is still printed, but without styling and layout).',
            'The page layout is built from separate positioned elements (fields, labels, headings, images, rectangles), not inside one e2pdf-html element. Inside an e2pdf-html element, columns and label/value rows are <table> with <td>. There is no flexbox, grid, float layout, absolute / relative positioning or inline-block for text.',
            'Write well-formed HTML: close every tag, quote attribute values, use <br /> / <hr /> / <img ... /> for void tags. Do not nest blocks (<div>, <table>, lists) inside <p>.',
            'Units: pt (recommended), px (treated as pt, 1px = 1pt), mm, cm, in, em / % (relative to the parent font size). Colors: #rrggbb (recommended), #rgb, rgb(r,g,b) or named colors; no alpha / rgba / hsl.',
            'Styles: CSS rules in the css property of the element or the style attribute. Selectors are applied to the HTML before rendering: tag, .class, multiple classes, descendant (".card td"), child (">"), attribute, grouped (".a, .b"), :first-child / :nth-child(). Table rows are inside an implicit <tbody>, so "table > tr" does not match: use "table tr" / "table td". Pseudo-elements (::before / ::after, content), :hover, @media, CSS variables and calc() have no effect. Text properties (font, color, size) are inherited by child elements.',
            'Write structured, readable HTML (indented, one block / table row per line) with classes, and put the styles into the css property of the element as readable CSS (one rule per block, one declaration per line), e.g. ".label {\n    color: #333333;\n    font-size: 7.5pt;\n}" with <td class="label">. Use the style attribute only for single exceptions.',
            'Relative (flowing) content, only when the user asks for it (content that flows and continues on following pages): put all content and CSS into one main e2pdf-html element; empty e2pdf-html elements with property parent = the main element_id continue its overflowing content (on the same or following pages, in element_id order); property multipage "1" duplicates the page while the content overflows (paid license only).',
            'Property wysiwyg_disable "1": set it only when the value, before shortcodes are processed, is not valid well-nested HTML, because the template editor WYSIWYG would rewrite it on save and break the template: text, field values or shortcodes directly inside <table>, <thead>, <tbody>, <tfoot> or <tr> outside of <td>/<th> (e.g. [e2pdf-for] / [e2pdf-if] around table rows or cells); a tag opened and closed in different parts of a shortcode (e.g. <div> opened inside [e2pdf-if-do] and closed after [/e2pdf-if]); a shortcode with double-quoted attributes inside a double-quoted HTML attribute; block elements (<div>, <table>, lists) inside <p>. Leave it unset otherwise: normal HTML, shortcodes inside cells / <li> / tags and comparison operators like [>] [<] [&&] are kept correctly.',
            'html_worker property of the element selects the renderer: "1" = v2 (default for new elements), "3" = v3. In v2 background, border and padding only work on <table>/<td>/<th>; <div>/<p> only take margins and text styles. v3 renders <div>, <p>, <h1>-<h6>, <blockquote>, <figure>, <figcaption>, <details>, <summary> as boxes (background, border, padding, margin, width) and adds the v3-only properties marked below. Set html_worker "3" only when box styling of these block tags is needed; tables work the same in both.',
        ],
        'tags' => [
            'div, p, blockquote, figure, figcaption, details, summary' => 'Block containers (line break before and after). v2: margins and text styles only. v3: also background, border, padding, width.',
            'h1, h2, h3, h4, h5, h6' => 'Headings. Always set font-size and font-weight explicitly in css (the built-in default sizes are not the browser ones).',
            'span, font' => 'Inline text styles: font-family, font-size, color, font-weight, font-style, text-decoration underline, letter-spacing, text-transform. Background (text highlight): v3 only.',
            'b, strong / i, em / u / s, strike, del / sub / sup' => 'Bold / italic / underline / strikethrough / subscript / superscript. Strikethrough only works with these tags (text-decoration: line-through is ignored).',
            'br' => 'Line break.',
            'hr' => 'Horizontal line: width (pt or %), height or size attribute (thickness), color via background-color / bgcolor, align (left, center, right), margin-top / margin-bottom.',
            'a' => 'Link: href, text styles.',
            'img' => 'Image: src (https URL, Media Library URL or data:image/...;base64,...), width, height (pt or %), align / float (left, center, right: text wraps around unless nowrap "1"), display: block (own line), margins. v3: max-width, max-height. Images wider than the element are scaled down.',
            'ul, ol, li' => 'Lists, nesting supported. list-style-type: disc (default), none, decimal (numbered); circle / square use glyphs missing in most fonts (marker is not shown), other types (lower-alpha, roman, ...) fall back to a bullet. E2Pdf extras: list-style-image: url(...) with list-style-image-size / -width / -height, list-style-type-color, list-style-type-size. li: margin-top / margin-bottom (spacing between items), padding-left (text indent after the marker).',
            'table, tr, td, th' => 'Tables (main layout tool). thead / tbody / tfoot / caption / col / colgroup have no effect (header rows: header-rows on <table>). th has no default bold / centering: style it. colspan and rowspan are supported. Text directly inside <table> / <tr> outside <td> / <th> is dropped.',
            'Not supported (ignored)' => 'section, article, header, footer, main, nav, aside, label, button, input, select, textarea, form, iframe, canvas, video, audio, script, style, link, object, dl / dt / dd, abbr, code, mark, small, big, ins, q, cite, pre (whitespace is collapsed: use <br />). Use div, span, table instead. <style> tags are ignored: use the css property.',
        ],
        'css' => [
            'font-family' => 'Name of an installed E2Pdf font (E2Pdf > Settings > Fonts), e.g. "Noto Sans". Unknown fonts fall back to a standard font.',
            'font-size' => 'pt, px, em, %.',
            'font-weight' => 'bold, 700, 800, 900 = bold; everything else (incl. 500, 600) = normal.',
            'font-style' => 'italic / oblique.',
            'text-decoration' => 'underline only.',
            'color' => 'Text color.',
            'line-height' => 'Always with a unit: pt (absolute, e.g. 14pt) or % (e.g. 140%). A unitless number (1.4) is read as 1.4pt and squeezes the lines. normal = 150%.',
            'text-align' => 'left, center, right, justify, justify-all.',
            'text-transform' => 'uppercase, lowercase, capitalize.',
            'letter-spacing' => 'pt.',
            'margin, margin-top / -right / -bottom / -left' => '1-4 values in pt. auto is not supported (center tables with align="center" or a wrapping table).',
            'padding, padding-top / -right / -bottom / -left' => 'td / th (and v3 block tags). Default cell padding comes from the table cellpadding (default 1).',
            'background, background-color' => 'Color only (no images / gradients). td / th / table, span (v3), block tags (v3).',
            'border, border-top / -right / -bottom / -left' => 'Width alone ("1pt") or the full form "1pt solid #cccccc" (3 parts: width, style, color); in a 2-part value ("1pt #ccc") the color is ignored. Style is always solid. Also border-width, border-color and per-side -width / -color. Default: no border. v3: an all-sides border / border-width on td / th is not drawn, use per-side borders (border-top / -right / -bottom / -left) or the table border / bordercolor attributes (work in v2 and v3). No border-radius, box-shadow, outline.',
            'width, height' => 'table, td, th, img, hr (pt or %); v3 also block tags. Set column widths on the td / th of the first row.',
            'vertical-align' => 'td / th: top, middle (default), bottom.',
            'display' => 'none hides the element. block, inline, table, table-row, table-cell, list, list-item change how a tag is handled (e.g. a div with display: table-cell acts as a td). flex, grid, inline-block are ignored. To show / hide content depending on field values use [e2pdf-if].',
            'float, align' => 'Images only (left, center, right).',
            'page-break-before' => 'always: start a new page (relative / multipage elements).',
            'Table extras (E2Pdf)' => 'cellpadding (on table), header-rows (number of rows repeated on each page), keep-together, split-rows, split-late.',
            'E2Pdf extras' => 'char-fill / line-fill: character filling the rest of the line (dot leaders, e.g. "."), with char-fill-color, char-fill-size; dir / direction: rtl; rotate (degrees).',
            'v3 only' => 'text-indent, max-width, max-height (images), split-border, split-padding.',
            'Not supported (ignored)' => 'position, top / left / right / bottom, z-index, flex*, grid*, gap, float (except images), min-width / min-height, max-width (except images in v3), overflow, white-space, word-break, opacity, transform, transition, box-sizing, border-radius, box-shadow, text-shadow, background-image / gradients, text-decoration other than underline, font-variant.',
        ],
    ];
    // element types with default size and properties as used by template editor, "size" is line height + 4
    protected $element_types = [
        'e2pdf-html' => [
            'width' => '200',
            'height' => 'size',
            'properties' => [
                'html_worker' => '1',
                'css_priority' => '1',
            ],
        ],
        'e2pdf-input' => [
            'width' => '200',
            'height' => 'size',
            'properties' => [
                'border' => '1',
                'border_color' => '#000000',
            ],
        ],
        'e2pdf-textarea' => [
            'width' => '200',
            'height' => '100',
            'properties' => [
                'border' => '1',
                'border_color' => '#000000',
            ],
        ],
        'e2pdf-checkbox' => [
            'width' => 'size',
            'height' => 'size',
            'properties' => [
                'option' => 'option',
                'border' => '1',
                'border_color' => '#000000',
            ],
        ],
        'e2pdf-radio' => [
            'width' => 'size',
            'height' => 'size',
            'properties' => [
                'group' => 'group',
                'option' => 'option',
                'border' => '1',
                'border_color' => '#000000',
                'text_type' => 'circle',
            ],
        ],
        'e2pdf-select' => [
            'width' => '200',
            'height' => 'size',
            'properties' => [
                'options' => '',
                'border' => '1',
                'border_color' => '#000000',
            ],
        ],
        'e2pdf-signature' => [
            'width' => '200',
            'height' => '75',
            'properties' => [
                'vertical' => 'bottom',
                'block_dimension' => '1',
                'dimension' => '1',
                'opacity' => '1',
            ],
        ],
        'e2pdf-image' => [
            'width' => '100',
            'height' => '100',
            'properties' => [
                'dimension' => '1',
                'opacity' => '1',
                'block_dimension' => '1',
                'vertical' => 'bottom',
                'pdf_resample' => '-1',
            ],
        ],
        'e2pdf-rectangle' => [
            'width' => '200',
            'height' => '5',
            'properties' => [
                'background' => '#000000',
            ],
        ],
        'e2pdf-link' => [
            'width' => '200',
            'height' => 'size',
            'properties' => [],
        ],
        'e2pdf-qrcode' => [
            'width' => '100',
            'height' => '100',
            'properties' => [
                'color' => '#000000',
                'background' => '#ffffff',
                'wq' => '1',
            ],
        ],
        'e2pdf-barcode' => [
            'width' => '200',
            'height' => '75',
            'properties' => [
                'color' => '#000000',
                'text_color' => '#000000',
                'vertical' => 'middle',
                'horizontal' => 'center',
                'wq' => '1',
                'dimension' => '1',
                'background' => '#ffffff',
                'margin_top' => '10',
                'margin_left' => '10',
                'margin_right' => '10',
                'margin_bottom' => '10',
            ],
        ],
        'e2pdf-page-number' => [
            'width' => '100',
            'height' => 'size',
            'value' => '[e2pdf-page-number] / [e2pdf-page-total]',
            'properties' => [
                'text_align' => 'center',
                'html_worker' => '1',
            ],
        ],
    ];

    // process json-rpc message, returns null for notifications and responses
    public function request($message) {
        if (!is_array($message) || !isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            return $this->error(null, -32600, 'Invalid Request');
        }

        if (!isset($message['method'])) {
            if (array_key_exists('result', $message) || array_key_exists('error', $message)) {
                return null;
            }
            return $this->error(isset($message['id']) ? $message['id'] : null, -32600, 'Invalid Request');
        }

        if (!array_key_exists('id', $message)) {
            return null;
        }

        $id = $message['id'];
        if (!is_string($id) && !is_int($id) || !is_string($message['method'])) {
            return $this->error(null, -32600, 'Invalid Request');
        }

        $params = isset($message['params']) && is_array($message['params']) ? $message['params'] : [];

        switch ($message['method']) {
            case 'initialize':
                $protocol_version = isset($params['protocolVersion']) && in_array($params['protocolVersion'], $this->protocol_versions, true) ? $params['protocolVersion'] : $this->protocol_versions[0];
                $site = $this->get_site();
                return $this->result(
                                $id,
                                [
                                    'protocolVersion' => $protocol_version,
                                    'capabilities' => [
                                        'tools' => [
                                            'listChanged' => false,
                                        ],
                                    ],
                                    'serverInfo' => [
                                        'name' => 'e2pdf',
                                        'title' => sprintf('E2Pdf (%s)', $site['host']),
                                        'version' => $this->helper->get('version'),
                                        'websiteUrl' => $site['url'],
                                    ],
                                    'instructions' => sprintf('Connected site: %s. Template ids, datasets, media and preview_id belong to this site only, another site is a separate MCP server with its own ids. ', $site['url']) . 'E2Pdf generates PDFs from templates filled with WordPress, WooCommerce and form plugin data. Build a template: list_extensions, get_fields, create_template, then one edit_template call with all elements (absolute positions in points, top-left origin, A4 is 595x842). Build the page like the E2Pdf template editor does, from separate positioned elements: each form field as the element type get_fields suggests (input, textarea, select, one checkbox / radio per option with its option / group), each label, heading and text block as its own small e2pdf-html element, images as e2pdf-image, lines / boxes as e2pdf-rectangle. Never put the whole page or form into one e2pdf-html element; one main e2pdf-html element (with parent elements) is only for relative / flowing content when the user asks for it. Before writing element values call get_shortcodes (use only its shortcodes and the get_fields values, never invent shortcodes) and, for e2pdf-html, get_html_reference (not a browser: use only the listed tags and CSS, styles in the css property). Images: URLs from list_media or import_image, public https URLs (icons: https://api.iconify.design/<set>/<icon>.svg?color=%23hex), <svg> markup or base64 data. Check changes with edit_template mode "preview" (show it to the user when confirmation is needed), then save once with mode "save" and the preview_id; store the returned backup .e2pdf file for the user. After building, call get_usage_instructions and tell the user where to paste the shortcodes (E2Pdf cannot edit forms). Generate PDFs: list_templates, list_datasets, generate_pdf.',
                                ]
                        );
            case 'ping':
                return $this->result($id, new stdClass());
            case 'tools/list':
                return $this->result(
                                $id,
                                [
                                    'tools' => $this->tools(),
                                ]
                        );
            case 'tools/call':
                $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
                $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
                $method = 'tool_' . $name;
                if (!$name || !in_array($name, wp_list_pluck($this->tools(), 'name'), true) || !method_exists($this, $method)) {
                    return $this->error($id, -32602, 'Unknown tool: ' . $name);
                }
                try {
                    $this->check_user();
                    $content = $this->$method($arguments);
                } catch (Exception $e) {
                    return $this->result(
                                    $id,
                                    [
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => $e->getMessage(),
                                            ],
                                        ],
                                        'isError' => true,
                                    ]
                            );
                }
                return $this->result(
                                $id,
                                [
                                    'content' => $content,
                                    'isError' => false,
                                ]
                        );
            default:
                return $this->error($id, -32601, 'Method not found');
        }
    }

    // tools definitions
    public function tools() {
        return [
            [
                'name' => 'list_templates',
                'title' => 'List Templates',
                'description' => 'List E2Pdf templates (not in trash). Each template is bound to an extension (wordpress, woocommerce, gravity, wpforms, etc.) and an item (form ID, post type, etc.).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Filter by template title.',
                        ],
                        'include_inactive' => [
                            'type' => 'boolean',
                            'description' => 'Include not activated templates. Default: false.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Max templates to return. Default: all.',
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Offset for pagination. Default: 0.',
                        ],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'get_template',
                'title' => 'Get Template',
                'description' => 'Get E2Pdf template details: extension, item, settings and the elements of each page with their values. Empty settings and empty element name / value / properties are omitted; actions is the number of element actions (omitted if none).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'list_datasets',
                'title' => 'List Datasets',
                'description' => 'List datasets (entries: form submissions, posts, orders, users) available for a template. Use the dataset id with generate_pdf.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                        'merged_item' => [
                            'type' => 'string',
                            'enum' => [
                                'dataset',
                                'dataset2',
                            ],
                            'description' => 'For templates with merged items only: list datasets of the first (dataset) or second (dataset2) item. Default: dataset.',
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Filter by dataset id or title.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Max datasets to return. Default: all.',
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Offset for pagination. Default: 0.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'generate_pdf',
                'title' => 'Generate PDF',
                'description' => 'Generate a PDF from a template and dataset. Returns the generated file.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                        'dataset' => [
                            'type' => 'string',
                            'description' => 'Dataset ID (from list_datasets).',
                        ],
                        'dataset2' => [
                            'type' => 'string',
                            'description' => 'Second dataset ID, for templates with merged items only.',
                        ],
                        'wc_order_id' => [
                            'type' => 'string',
                            'description' => 'WooCommerce order ID, for WooCommerce product templates.',
                        ],
                        'wc_product_item_id' => [
                            'type' => 'string',
                            'description' => 'WooCommerce order item ID, for WooCommerce product templates.',
                        ],
                        'user_id' => [
                            'type' => 'integer',
                            'description' => 'WordPress user ID used for user related values. Default: MCP user.',
                        ],
                        'arguments' => [
                            'type' => 'object',
                            'description' => 'Template arguments, e.g. {"arg1": "value"}, available in the template as [e2pdf-arg key="arg1"].',
                            'additionalProperties' => [
                                'type' => 'string',
                            ],
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'File name without extension. Default: template setting.',
                        ],
                        'password' => [
                            'type' => 'string',
                            'description' => 'PDF password. Default: template setting.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                ],
            ],
            [
                'name' => 'list_extensions',
                'title' => 'List Extensions',
                'description' => 'List active E2Pdf extensions (WordPress, WooCommerce, form plugins) and their items (forms, post types, etc.) that a template can be connected to, items as {"item id": "name"}.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'get_shortcodes',
                'title' => 'Get Shortcodes',
                'description' => 'Reference of E2Pdf shortcodes and placeholders (syntax, attributes, examples) to use in element values, e.g. date/number formatting, math, conditions, loops. Call before writing element values with shortcodes.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'get_html_reference',
                'title' => 'Get HTML Reference',
                'description' => 'HTML tags and CSS properties supported by the e2pdf-html renderer (a simple HTML-to-PDF parser, not a browser: unsupported tags and CSS are silently ignored). Call before writing e2pdf-html values and css, and use only what it lists.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'get_usage_instructions',
                'title' => 'Get Usage Instructions',
                'description' => 'After creating a template: where the user must paste which E2Pdf shortcodes (form confirmation message, email notifications, ...) to offer the PDF download / view / email attachment, and which WP admin hooks (download links in entries) can be enabled with edit_template hooks. E2Pdf cannot change form settings itself, give these instructions to the user.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'get_fields',
                'title' => 'Get Fields',
                'description' => 'Get the fields of an extension item (or of an existing template connection) with the values (shortcodes / field tags) to use as element values, their labels, the element type to use for each value (input, textarea, select, checkbox, radio, html, ...) and option / options / group for select, checkbox and radio elements.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Use the connection of this template. Alternatively pass extension and item.',
                        ],
                        'extension' => [
                            'type' => 'string',
                            'description' => 'Extension key from list_extensions.',
                        ],
                        'item' => [
                            'type' => 'string',
                            'description' => 'Item ID from list_extensions.',
                        ],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'list_media',
                'title' => 'List Media',
                'description' => 'Search images in the WordPress Media Library. Use the returned url as e2pdf-image element value. New images: import_image (remote URL) or ask the user to upload them to the Media Library.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Filter by title or file name.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Max images to return (1-100). Default: 20.',
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Offset for pagination. Default: 0.',
                        ],
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                ],
            ],
            [
                'name' => 'import_image',
                'title' => 'Import Image',
                'description' => 'Download a remote image by URL on the server (nothing to transfer through the chat) and save it to the WordPress Media Library, following the WordPress upload permissions and allowed file types (e.g. SVG only if the site allows SVG uploads). Use the returned url as e2pdf-image element value.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'Public http(s) image URL.',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Media title. Default: file name.',
                        ],
                    ],
                    'required' => [
                        'url',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => false,
                    'openWorldHint' => true,
                ],
            ],
            [
                'name' => 'create_template',
                'title' => 'Create Template',
                'description' => 'Create a new empty template with one page connected to an extension item, with defaults: A4 (595x842 points), font "Noto Sans Regular", font_size 12, line_height 12. Add content and change settings with edit_template.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Template title.',
                        ],
                        'extension' => [
                            'type' => 'string',
                            'description' => 'Extension key from list_extensions.',
                        ],
                        'item' => [
                            'type' => 'string',
                            'description' => 'Item ID from list_extensions.',
                        ],
                        'item1' => [
                            'type' => 'string',
                            'description' => 'Merged items only: first item ID (use together with item2 instead of item).',
                        ],
                        'item2' => [
                            'type' => 'string',
                            'description' => 'Merged items only: second item ID.',
                        ],
                        'activate' => [
                            'type' => 'boolean',
                            'description' => 'Activate the template (required to generate PDFs, uses a license slot). Default: true.',
                        ],
                        'access_check' => [
                            'type' => 'boolean',
                            'description' => 'Access Check of the template. Default: the E2Pdf setting.',
                        ],
                    ],
                    'required' => [
                        'title',
                        'extension',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => false,
                ],
            ],
            [
                'name' => 'edit_template',
                'title' => 'Edit Template',
                'description' => 'Apply all changes to a template in one call, in this order: settings, hooks, delete_elements, delete_pages, add_pages, update_elements, add_elements. mode "preview": renders the changes in memory (nothing is saved) and returns a preview_id. mode "save": saves one revision, either the preview (preview_id only, do not resend the changes) or the passed changes; for templates with elements it returns a backup .e2pdf file of the template before saving (restore: E2Pdf > Templates > Import), store it for the user.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                        'mode' => [
                            'type' => 'string',
                            'enum' => [
                                'preview',
                                'save',
                            ],
                            'description' => 'preview: render without saving. save: back up and save.',
                        ],
                        'settings' => [
                            'type' => 'object',
                            'description' => 'Settings to change: size, ' . implode(', ', $this->template_settings) . '. size: page size preset (' . implode(', ', array_keys((new Controller_E2pdf_Templates())->get_sizes_list())) . '), sets width/height. Changing width/height resizes all pages. font: one of the installed E2Pdf fonts (E2Pdf > Settings > Fonts). font_size / line_height: 1-512. name/savename/password support shortcodes, e.g. "[e2pdf-dataset]". format: pdf or jpg. flatten: 0 (editable fields), 1 (flatten), 2 (flatten to image). text_align: left, center, right, justify.',
                        ],
                        'hooks' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'description' => 'WP admin hooks to enable (replaces the enabled list), e.g. download link in the admin entry view. Available hooks: get_usage_instructions.',
                        ],
                        'delete_elements' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ],
                            'description' => 'Element IDs (from get_template) to delete.',
                        ],
                        'delete_pages' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ],
                            'description' => 'Page numbers (current numbering) to delete with their elements, following pages are shifted.',
                        ],
                        'add_pages' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ],
                            'description' => 'Positions (page numbers after delete_pages) of new empty pages, applied in the given order, following pages are shifted; a position after the last page appends. Not available for templates based on an uploaded PDF or with the FREE license.',
                        ],
                        'update_elements' => [
                            'type' => 'array',
                            'description' => 'Element changes by element_id. Only passed fields change; properties are merged, a property set to null is removed. page_id (numbering after page changes) moves the element to another page.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'element_id' => [
                                        'type' => 'integer',
                                    ],
                                    'page_id' => [
                                        'type' => 'integer',
                                    ],
                                    'top' => [
                                        'type' => 'number',
                                    ],
                                    'left' => [
                                        'type' => 'number',
                                    ],
                                    'width' => [
                                        'type' => 'number',
                                    ],
                                    'height' => [
                                        'type' => 'number',
                                    ],
                                    'value' => [
                                        'type' => 'string',
                                    ],
                                    'name' => [
                                        'type' => 'string',
                                    ],
                                    'properties' => [
                                        'type' => 'object',
                                    ],
                                ],
                                'required' => [
                                    'element_id',
                                ],
                            ],
                        ],
                        'add_elements' => [
                            'type' => 'array',
                            'description' => 'Elements to add, one element per form field, label, heading, text block, image and line (no whole-page e2pdf-html element unless relative / flowing content is requested). Positions and sizes are in points from the top-left corner of the page. Values may contain field values from get_fields, E2Pdf shortcodes and (for e2pdf-html) HTML. Omitted width/height use the template editor defaults for the type.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'page_id' => [
                                        'type' => 'integer',
                                        'description' => 'Page number (numbering after page changes). Default: 1.',
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => array_keys($this->element_types),
                                        'description' => 'e2pdf-input, e2pdf-textarea, e2pdf-select, e2pdf-checkbox, e2pdf-radio: PDF form fields for form field values, use the type, option / options / group from get_fields (checkbox / radio: one element per option, value = the field value, checked when option is one of the selected values). e2pdf-html: one label, heading, text block, combined value, table or loop each (not the whole page). e2pdf-image: image / logo. e2pdf-rectangle: line / box.',
                                    ],
                                    'top' => [
                                        'type' => 'number',
                                    ],
                                    'left' => [
                                        'type' => 'number',
                                    ],
                                    'width' => [
                                        'type' => 'number',
                                    ],
                                    'height' => [
                                        'type' => 'number',
                                    ],
                                    'value' => [
                                        'type' => 'string',
                                        'description' => 'Text/HTML with field values from get_fields and shortcodes from get_shortcodes, e.g. "<b>Email:</b> {form:email}". Unknown [e2pdf-...] shortcodes are rejected. e2pdf-image: image URL from list_media / import_image, a public https URL, <svg> markup or base64 data. e2pdf-html: see get_html_reference.',
                                    ],
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'PDF form field name (optional).',
                                    ],
                                    'properties' => [
                                        'type' => 'object',
                                        'description' => 'Other element properties, e.g. text_font_size, text_line_height, text_color, text_font, text_align, border, border_color, background, padding_top/left/right/bottom, option (checkbox/radio checked value), group (radio group), options (select options, one per line), multiline, readonly, hide_if_empty "1", hide_page_if_empty "1" (e2pdf-html only: hide the element / its page when the value is empty). e2pdf-html: css, html_worker, parent, multipage, wysiwyg_disable, see get_html_reference. New element_ids are assigned in add_elements order starting at the highest element_id of the template + 1, so parent can refer to an element added in the same call.',
                                    ],
                                ],
                                'required' => [
                                    'type',
                                    'top',
                                    'left',
                                ],
                            ],
                        ],
                        'dataset' => [
                            'type' => 'string',
                            'description' => 'Preview only: dataset ID (from list_datasets) to fill the preview with real data, requires an activated template. Default: no data, field values are shown as placeholders like in the template editor Preview.',
                        ],
                        'dataset2' => [
                            'type' => 'string',
                            'description' => 'Preview only: second dataset ID, for templates with merged items only.',
                        ],
                        'preview_format' => [
                            'type' => 'string',
                            'enum' => [
                                'jpg',
                                'pdf',
                            ],
                            'description' => 'Preview only: jpg returns one image with all pages one below the other to check the layout visually, pdf returns the PDF file. Default: jpg.',
                        ],
                        'preview_id' => [
                            'type' => 'string',
                            'description' => 'Save only: preview_id returned by a preview (valid 1 hour), saves exactly the previewed changes; do not pass changes together with it.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                        'mode',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'idempotentHint' => false,
                ],
            ],
            [
                'name' => 'set_template_activation',
                'title' => 'Activate / Deactivate Template',
                'description' => 'Activate or deactivate a template. Only activated templates generate PDFs; activation uses a slot of the E2Pdf license.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                        'activated' => [
                            'type' => 'boolean',
                        ],
                    ],
                    'required' => [
                        'template_id',
                        'activated',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                ],
            ],
            [
                'name' => 'trash_template',
                'title' => 'Move Template to Trash',
                'description' => 'Move a template to trash and deactivate it (PDFs of this template stop working). Requires confirmation: the first call returns a confirmation_code; ask the user to explicitly confirm, then call again with the code.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                        'confirmation_code' => [
                            'type' => 'string',
                            'description' => 'Code returned by the first call, only after the user confirmed.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'idempotentHint' => false,
                ],
            ],
            [
                'name' => 'restore_template',
                'title' => 'Restore Template',
                'description' => 'Restore a template from trash. It stays deactivated until set_template_activation.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                ],
            ],
            [
                'name' => 'delete_template',
                'title' => 'Delete Template',
                'description' => 'Permanently delete a template that is in trash, including its revisions. Cannot be undone. Requires confirmation: the first call returns a confirmation_code; ask the user to explicitly confirm, then call again with the code.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => [
                            'type' => 'integer',
                            'description' => 'Template ID.',
                        ],
                        'confirmation_code' => [
                            'type' => 'string',
                            'description' => 'Code returned by the first call, only after the user confirmed.',
                        ],
                    ],
                    'required' => [
                        'template_id',
                    ],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'idempotentHint' => false,
                ],
            ],
        ];
    }

    // tool: list_templates
    public function tool_list_templates($arguments) {
        global $wpdb;

        $this->check_caps(['e2pdf_templates', 'e2pdf']);

        $site = $this->get_site();
        $model_e2pdf_template = new Model_E2pdf_Template();
        $limit = $this->get_int($arguments, 'limit', 0, 0);
        $offset = $this->get_int($arguments, 'offset', 0, 0);

        $condition = [
            'trash' => [
                'condition' => '<>',
                'value' => '1',
                'type' => '%d',
            ],
        ];
        if (empty($arguments['include_inactive'])) {
            $condition['activated'] = [
                'condition' => '=',
                'value' => '1',
                'type' => '%d',
            ];
        }
        if (isset($arguments['search']) && is_string($arguments['search']) && trim($arguments['search']) !== '') {
            $condition['title'] = [
                'condition' => 'LIKE',
                'value' => '%' . $wpdb->esc_like(trim($arguments['search'])) . '%',
                'type' => '%s',
            ];
        }
        $where = $this->helper->load('db')->prepare_where($condition);
        $orderby = $this->helper->load('db')->prepare_orderby(
                [
                    'orderby' => 'id',
                    'order' => 'desc',
                ]
        );
        $limit_sql = $limit ? $this->helper->load('db')->prepare_limit(
                        [
                            'limit' => $limit,
                            'offset' => $offset,
                        ]
                ) : '';

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . $model_e2pdf_template->get_table() . '`' . $where['sql'], $where['filter']));
        $ids = $wpdb->get_col($wpdb->prepare('SELECT `ID` FROM `' . $model_e2pdf_template->get_table() . '`' . $where['sql'] . $orderby . $limit_sql, $where['filter']));
        // phpcs:enable

        $templates = [];
        foreach ((array) $ids as $id) {
            $model_e2pdf_template = new Model_E2pdf_Template();
            if (!$model_e2pdf_template->load($id, false)) {
                continue;
            }
            $extension = $model_e2pdf_template->extension() && $model_e2pdf_template->extension()->extension() ? $model_e2pdf_template->extension() : null;
            $template = [
                'id' => (int) $model_e2pdf_template->get('ID'),
                'title' => $model_e2pdf_template->get('title'),
                'extension' => $model_e2pdf_template->get('extension'),
                'extension_active' => $extension ? true : false,
                'format' => $model_e2pdf_template->get('format'),
                'activated' => $model_e2pdf_template->get('activated') == '1',
                'updated_at' => $model_e2pdf_template->get('updated_at'),
            ];
            if ($model_e2pdf_template->get('item') == '-2') {
                $template['merged_items'] = [
                    $this->get_item($extension, $model_e2pdf_template->get('item1')),
                    $this->get_item($extension, $model_e2pdf_template->get('item2')),
                ];
            } else {
                $template['item'] = $this->get_item($extension, $model_e2pdf_template->get('item'));
            }
            $templates[] = $template;
        }

        return $this->json_content(
                        [
                            'site' => $site['url'],
                            'total' => $total,
                            'offset' => $offset,
                            'templates' => $templates,
                        ]
                );
    }

    // tool: get_template
    public function tool_get_template($arguments) {

        $this->check_caps(['e2pdf_templates', 'e2pdf']);

        $site = $this->get_site();
        $template = $this->load_template($arguments, false);
        $extension = $template->extension() && $template->extension()->extension() ? $template->extension() : null;

        $data = [
            'site' => $site['url'],
            'id' => (int) $template->get('ID'),
            'title' => $template->get('title'),
            'extension' => $template->get('extension'),
            'extension_active' => $extension ? true : false,
            'activated' => $template->get('activated') == '1',
            'uploaded_pdf' => $template->get('pdf') ? true : false,
        ];
        if ($template->get('item') == '-2') {
            $data['merged_items'] = [
                $this->get_item($extension, $template->get('item1')),
                $this->get_item($extension, $template->get('item2')),
            ];
        } else {
            $data['item'] = $this->get_item($extension, $template->get('item'));
        }
        $data['settings'] = [];
        foreach ($this->template_settings as $key) {
            if ((string) $template->get($key) !== '') {
                $data['settings'][$key] = $template->get($key);
            }
        }
        $data['hooks'] = $template->get('hooks') ? explode(',', $template->get('hooks')) : [];
        $data['revisions'] = count((array) $template->get('revisions')) - 1;

        $data['pages'] = [];
        foreach ((array) $template->get('pages') as $page_id => $page) {
            $elements = [];
            if (!empty($page['elements']) && is_array($page['elements'])) {
                foreach ($page['elements'] as $element) {
                    $elements[] = array_filter(
                            [
                                'element_id' => isset($element['element_id']) ? (int) $element['element_id'] : 0,
                                'type' => isset($element['type']) ? $element['type'] : '',
                                'name' => isset($element['name']) ? (string) $element['name'] : '',
                                'top' => isset($element['top']) ? (float) $element['top'] : 0,
                                'left' => isset($element['left']) ? (float) $element['left'] : 0,
                                'width' => isset($element['width']) ? (float) $element['width'] : 0,
                                'height' => isset($element['height']) ? (float) $element['height'] : 0,
                                'value' => isset($element['value']) ? (string) $element['value'] : '',
                                'properties' => !empty($element['properties']) && is_array($element['properties']) ? $element['properties'] : [],
                                'actions' => !empty($element['actions']) && is_array($element['actions']) ? count($element['actions']) : 0,
                            ],
                            function ($value, $key) {
                                return !in_array($key, ['name', 'value', 'properties', 'actions'], true) || ($value !== '' && $value !== [] && $value !== 0);
                            },
                            ARRAY_FILTER_USE_BOTH
                    );
                }
            }
            $data['pages'][] = [
                'page_id' => (int) $page_id,
                'width' => isset($page['properties']['width']) ? (float) $page['properties']['width'] : (float) $template->get('width'),
                'height' => isset($page['properties']['height']) ? (float) $page['properties']['height'] : (float) $template->get('height'),
                'elements' => $elements,
            ];
        }

        return $this->json_content($data);
    }

    // tool: list_datasets
    public function tool_list_datasets($arguments) {

        $this->check_caps(['e2pdf']);

        $template = $this->load_template($arguments);
        $template_id = $template->get('ID');
        $limit = $this->get_int($arguments, 'limit', 0, 0);
        $offset = $this->get_int($arguments, 'offset', 0, 0);

        if ($template->get('item') == '-2') {
            $key = isset($arguments['merged_item']) && $arguments['merged_item'] === 'dataset2' ? '2' : '1';
            $datasets = (new Controller_E2pdf())->get_datasets($template_id, $template->get('item' . $key), $this->helper->load('translator')->pre_translate($template->get('dataset_title' . $key), $template_id, 'dataset_title' . $key, 'template'));
        } else {
            $datasets = (new Controller_E2pdf())->get_datasets($template_id, false, $this->helper->load('translator')->pre_translate($template->get('dataset_title'), $template_id, 'dataset_title', 'template'));
        }

        $list = [];
        if (is_array($datasets)) {
            $search = isset($arguments['search']) && is_string($arguments['search']) ? trim($arguments['search']) : '';
            foreach ($datasets as $dataset) {
                if (!isset($dataset['key']) || $dataset['key'] === '') {
                    continue;
                }
                $title = isset($dataset['value']) ? wp_strip_all_tags((string) $dataset['value']) : '';
                if ($search !== '' && stripos((string) $dataset['key'], $search) === false && stripos($title, $search) === false) {
                    continue;
                }
                $list[] = [
                    'id' => (string) $dataset['key'],
                    'title' => $title,
                ];
            }
        }

        return $this->json_content(
                        [
                            'template_id' => (int) $template_id,
                            'total' => count($list),
                            'offset' => $offset,
                            'datasets' => array_slice($list, $offset, $limit ? $limit : null),
                        ]
                );
    }

    // tool: generate_pdf
    public function tool_generate_pdf($arguments) {

        $this->check_caps(['e2pdf']);

        $site = $this->get_site();
        $template = $this->load_template($arguments);
        $template_id = (int) $template->get('ID');
        if ($template->get('activated') != '1') {
            throw new Exception(sprintf('Template %d is not activated', $template_id));
        }

        // render in the current WPML language, MCP requests carry no language url
        if ($this->helper->load('translator')->isWPML()) {
            do_action('wpml_switch_language', apply_filters('wpml_current_language', null));
        }

        $request = (new Controller_E2pdf())->create_pdf($this->pdf_data($arguments), $template);
        if (isset($request['error'])) {
            throw new Exception(isset($request['error_code']) && $request['error_code'] === 'incorrect_dataset' ? 'Incorrect Dataset: the dataset does not belong to the template item, see list_datasets' : wp_strip_all_tags((string) $request['error']));
        }

        $filename = $this->helper->load('convert')->to_file_name($request['name'] . '.' . $request['format']);
        $mime_type = $request['format'] === 'jpg' ? 'image/jpeg' : 'application/pdf';

        return array_merge(
                $this->json_content(
                        [
                            'site' => $site['url'],
                            'template_id' => $template_id,
                            'filename' => $filename,
                            'mime_type' => $mime_type,
                            'size' => strlen($request['file']),
                        ]
                ),
                [
                    [
                        'type' => 'resource',
                        'resource' => [
                            'uri' => 'e2pdf://' . $site['host'] . '/template/' . $template_id . '/' . rawurlencode($filename),
                            'mimeType' => $mime_type,
                            'blob' => base64_encode($request['file']), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                        ],
                    ],
                ]
        );
    }

    // request data for Controller_E2pdf::create_pdf
    protected function pdf_data($arguments) {

        $template_id = isset($arguments['template_id']) && is_numeric($arguments['template_id']) ? (int) $arguments['template_id'] : 0;
        $dataset = isset($arguments['dataset']) && is_scalar($arguments['dataset']) && (string) $arguments['dataset'] !== '' ? sanitize_text_field((string) $arguments['dataset']) : false;
        $dataset2 = isset($arguments['dataset2']) && is_scalar($arguments['dataset2']) && (string) $arguments['dataset2'] !== '' ? sanitize_text_field((string) $arguments['dataset2']) : false;
        $wc_order_id = isset($arguments['wc_order_id']) && is_scalar($arguments['wc_order_id']) && (string) $arguments['wc_order_id'] !== '' ? sanitize_text_field((string) $arguments['wc_order_id']) : false;
        $wc_product_item_id = isset($arguments['wc_product_item_id']) && is_scalar($arguments['wc_product_item_id']) && (string) $arguments['wc_product_item_id'] !== '' ? sanitize_text_field((string) $arguments['wc_product_item_id']) : false;

        if (!$template_id || (!$dataset && !$dataset2)) {
            throw new Exception('Template ID and dataset are required');
        }

        $data = [
            'template_id' => $template_id,
            'dataset' => $dataset,
            'dataset2' => $dataset2,
            'wc_order_id' => $wc_order_id,
            'wc_product_item_id' => $wc_product_item_id,
            'user_id' => isset($arguments['user_id']) && is_numeric($arguments['user_id']) ? absint($arguments['user_id']) : get_current_user_id(),
            'inline' => '1',
            'args' => [],
        ];

        foreach (['name', 'password'] as $key) {
            if (isset($arguments[$key]) && is_scalar($arguments[$key]) && (string) $arguments[$key] !== '') {
                $data[$key] = sanitize_text_field((string) $arguments[$key]);
            }
        }

        if (isset($arguments['arguments']) && is_array($arguments['arguments'])) {
            foreach ($arguments['arguments'] as $key => $value) {
                if (preg_match('/^arg[0-9]+$/', (string) $key) && is_scalar($value) && (string) $value !== '') {
                    $data['args'][(string) $key] = (string) $value;
                }
            }
        }

        return $data;
    }

    // tool: list_extensions
    public function tool_list_extensions($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $extensions = [];
        foreach ((new Model_E2pdf_Extension())->extensions() as $extension_key => $extension_title) {
            $model_e2pdf_extension = new Model_E2pdf_Extension();
            if (!$model_e2pdf_extension->load($extension_key)) {
                continue;
            }
            $items = [];
            foreach ((array) $model_e2pdf_extension->items() as $item) {
                if (is_object($item) && isset($item->id) && (string) $item->id !== '') {
                    $items[(string) $item->id] = isset($item->name) ? $item->name : '';
                }
            }
            $extensions[] = [
                'extension' => $extension_key,
                'title' => $extension_title,
                'items' => (object) $items,
            ];
        }

        return $this->json_content(
                        [
                            'extensions' => $extensions,
                        ]
                );
    }

    // tool: get_shortcodes
    public function tool_get_shortcodes($arguments) {

        $this->check_caps(['e2pdf_templates']);

        return $this->json_content($this->shortcodes_reference);
    }

    // tool: get_html_reference
    public function tool_get_html_reference($arguments) {

        $this->check_caps(['e2pdf_templates']);

        return $this->json_content($this->html_reference);
    }

    // tool: get_usage_instructions
    public function tool_get_usage_instructions($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $template = $this->load_template($arguments);
        $template_id = (int) $template->get('ID');
        $extension = $template->get('extension');

        $data = [
            'template_id' => $template_id,
            'extension' => $extension,
            'item' => $this->get_item($template->extension(), $template->get('item')),
            'activated' => $template->get('activated') == '1',
            'notes' => [
                'E2Pdf cannot change form, page or email settings: tell the user exactly where to paste the shortcodes below.',
                'In the listed places the submitted entry is added automatically as dataset, do not add a dataset attribute.',
                'The template must be activated to generate PDFs.',
            ],
            'shortcodes' => [
                'download' => [
                    'shortcode' => '[e2pdf-download id="' . $template_id . '"]',
                    'description' => 'Download link / button. Attributes: button_title="Download PDF", inline="true" (open in browser), auto="true" (start download automatically), output="url" (URL only, e.g. for redirects or links).',
                ],
                'view' => [
                    'shortcode' => '[e2pdf-view id="' . $template_id . '"]',
                    'description' => 'Embedded PDF viewer. Attributes: width="100%" height="500".',
                ],
                'attachment' => [
                    'shortcode' => '[e2pdf-attachment id="' . $template_id . '"]',
                    'description' => 'Attaches the PDF to the email. Only works in email messages; it is removed from the email text.',
                ],
            ],
            'places' => [],
        ];

        $extension_instructions = apply_filters('e2pdf_extension_mcp_instructions', []);
        if (isset($extension_instructions[$extension]['places'])) {
            foreach ($extension_instructions[$extension]['places'] as $place) {
                // add the template id to the e2pdf shortcodes of the extension
                $place['shortcodes'] = preg_replace('/^\[(e2pdf-[a-z]+)/', '[$1 id="' . $template_id . '"', $place['shortcodes']);
                $data['places'][] = $place;
            }
        }

        $hooks_key = $extension === 'woocommerce' || ($extension === 'wordpress' && $template->get('item') == '-3') ? $extension . '_' . $template->get('item') : $extension;
        $extension_hooks = apply_filters('e2pdf_extension_hooks', []);
        $data['admin_hooks'] = [
            'description' => 'Download links in WP admin (no form changes needed). Enable with edit_template hooks.',
            'available' => isset($extension_hooks[$hooks_key]) ? $extension_hooks[$hooks_key] : new stdClass(),
            'enabled' => $template->get('hooks') ? explode(',', $template->get('hooks')) : [],
        ];

        return $this->json_content($data);
    }

    // tool: get_fields
    public function tool_get_fields($arguments) {

        $this->check_caps(['e2pdf_templates']);

        if (!empty($arguments['template_id'])) {
            $template = $this->load_template($arguments);
            $extension_key = $template->get('extension');
            $item = $template->get('item');
            $item1 = $template->get('item1');
            $item2 = $template->get('item2');
            $font_size = $template->get('font_size');
            $line_height = $template->get('line_height');
        } else {
            $extension_key = isset($arguments['extension']) && is_string($arguments['extension']) ? sanitize_key($arguments['extension']) : '';
            $item = isset($arguments['item']) && is_scalar($arguments['item']) ? sanitize_text_field((string) $arguments['item']) : '';
            $item1 = '';
            $item2 = '';
            $font_size = '12';
            $line_height = '12';
        }

        $model_e2pdf_extension = new Model_E2pdf_Extension();
        if (!$extension_key || !$model_e2pdf_extension->load($extension_key)) {
            throw new Exception(sprintf('Extension "%s" is not active', $extension_key));
        }
        if ($item === '') {
            throw new Exception('Item is required');
        }
        $model_e2pdf_extension->set('item', $item);
        if ($item == '-2') {
            $model_e2pdf_extension->set('item1', $item1);
            $model_e2pdf_extension->set('item2', $item2);
        }
        $model_e2pdf_extension->set('font_size', $font_size);
        $model_e2pdf_extension->set('line_height', $line_height);

        $fields = [];
        $values = [];
        $label = '';
        $auto = method_exists($model_e2pdf_extension->extension(), 'auto') ? $model_e2pdf_extension->auto() : [];
        if (!empty($auto['elements']) && is_array($auto['elements'])) {
            foreach ($auto['elements'] as $element) {
                $type = isset($element['type']) ? $element['type'] : '';
                $value = isset($element['properties']['value']) ? (string) $element['properties']['value'] : '';
                if ($type === 'e2pdf-html' && false === strpos($value, '{') && false === strpos($value, '[')) {
                    if (!empty($element['block'])) {
                        $label = wp_strip_all_tags($value);
                    }
                    continue;
                }
                if ($value === '') {
                    continue;
                }
                $field = [
                    'value' => $value,
                    'label' => $label,
                    'type' => $type,
                ];
                foreach (['option', 'options', 'group'] as $key) {
                    if (isset($element['properties'][$key]) && $element['properties'][$key] !== '') {
                        $field[$key] = (string) $element['properties'][$key];
                    }
                }
                $fields[] = $field;
                $values[$value] = true;
            }
        }

        $visual_mapper = method_exists($model_e2pdf_extension->extension(), 'visual_mapper') ? $model_e2pdf_extension->visual_mapper() : '';
        if (is_string($visual_mapper) && preg_match_all('/\sname=(["\'])(.*?)\1/', $visual_mapper, $matches)) {
            foreach ($matches[2] as $value) {
                $value = html_entity_decode($value, ENT_QUOTES);
                if ($value !== '' && !isset($values[$value]) && (false !== strpos($value, '{') || false !== strpos($value, '['))) {
                    $fields[] = [
                        'value' => $value,
                    ];
                    $values[$value] = true;
                }
            }
        }

        $data = [
            'extension' => $extension_key,
            'item' => $item,
            'fields' => $fields,
        ];
        $extension_instructions = apply_filters('e2pdf_extension_mcp_instructions', []);
        if (!empty($extension_instructions[$extension_key]['values'])) {
            $data['instructions'] = $extension_instructions[$extension_key]['values'];
        }

        return $this->json_content($data);
    }

    // tool: list_media
    public function tool_list_media($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $offset = $this->get_int($arguments, 'offset', 0, 0);
        add_filter('wp_allow_query_attachment_by_filename', '__return_true');
        $query = new WP_Query(
                [
                    'post_type' => 'attachment',
                    'post_status' => 'inherit',
                    'post_mime_type' => 'image',
                    's' => isset($arguments['search']) && is_string($arguments['search']) ? sanitize_text_field($arguments['search']) : '',
                    'posts_per_page' => $this->get_int($arguments, 'limit', 20, 1, 100),
                    'offset' => $offset,
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]
        );
        remove_filter('wp_allow_query_attachment_by_filename', '__return_true');

        $media = [];
        foreach ($query->posts as $post) {
            $meta = wp_get_attachment_metadata($post->ID);
            $media[] = [
                'id' => (int) $post->ID,
                'title' => $post->post_title,
                'filename' => wp_basename((string) get_attached_file($post->ID)),
                'url' => wp_get_attachment_url($post->ID),
                'mime_type' => $post->post_mime_type,
                'width' => isset($meta['width']) ? (int) $meta['width'] : 0,
                'height' => isset($meta['height']) ? (int) $meta['height'] : 0,
            ];
        }

        return $this->json_content(
                        [
                            'total' => (int) $query->found_posts,
                            'offset' => $offset,
                            'media' => $media,
                        ]
        );
    }

    // tool: import_image
    public function tool_import_image($arguments) {

        $this->check_caps(['e2pdf_templates']);
        if (!current_user_can('upload_files')) {
            throw new Exception('Permission denied: the MCP user cannot upload files');
        }

        $url = isset($arguments['url']) && is_string($arguments['url']) ? esc_url_raw(trim($arguments['url']), ['http', 'https']) : '';
        if (!$url || !wp_http_validate_url($url)) {
            throw new Exception('A public http(s) URL is required');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            throw new Exception(sprintf('Image could not be downloaded: %s', $tmp->get_error_message()));
        }

        $name = sanitize_file_name(wp_basename((string) wp_parse_url($url, PHP_URL_PATH)));
        $mime_type = wp_get_image_mime($tmp);
        if ($mime_type && !wp_check_filetype($name)['type']) {
            $name = ($name ? $name : 'image') . '.' . wp_get_default_extension_for_mime_type($mime_type);
        }
        $attachment_id = media_handle_sideload(
                [
                    'name' => $name,
                    'tmp_name' => $tmp,
                ],
                0,
                isset($arguments['title']) && is_string($arguments['title']) ? sanitize_text_field($arguments['title']) : null
        );
        if (is_wp_error($attachment_id)) {
            wp_delete_file($tmp);
            throw new Exception(sprintf('Image could not be saved to the Media Library: %s', $attachment_id->get_error_message()));
        }
        if (0 !== strpos((string) get_post_mime_type($attachment_id), 'image/')) {
            wp_delete_attachment($attachment_id, true);
            throw new Exception('The URL is not an image');
        }

        return $this->json_content(
                        [
                            'id' => (int) $attachment_id,
                            'url' => wp_get_attachment_url($attachment_id),
                        ]
        );
    }

    // tool: create_template
    public function tool_create_template($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $site = $this->get_site();
        $title = isset($arguments['title']) && is_scalar($arguments['title']) ? sanitize_text_field((string) $arguments['title']) : '';
        if ($title === '') {
            throw new Exception('Title is required');
        }

        $extension_key = isset($arguments['extension']) && is_string($arguments['extension']) ? sanitize_key($arguments['extension']) : '';
        $model_e2pdf_extension = new Model_E2pdf_Extension();
        if (!$extension_key || !$model_e2pdf_extension->load($extension_key)) {
            throw new Exception(sprintf('Extension "%s" is not active', $extension_key));
        }

        $items = [];
        foreach ((array) $model_e2pdf_extension->items() as $extension_item) {
            if (is_object($extension_item) && isset($extension_item->id)) {
                $items[] = (string) $extension_item->id;
            }
        }
        $connection = [
            'item' => '',
            'item1' => '',
            'item2' => '',
        ];
        foreach ($connection as $key => $value) {
            if (isset($arguments[$key]) && is_scalar($arguments[$key]) && (string) $arguments[$key] !== '') {
                $connection[$key] = sanitize_text_field((string) $arguments[$key]);
                if (!in_array($connection[$key], $items, true)) {
                    throw new Exception(sprintf('Item "%s" not found in extension "%s", see list_extensions', $connection[$key], $extension_key));
                }
            }
        }
        if ($connection['item1'] !== '' || $connection['item2'] !== '') {
            if ($connection['item1'] === '' || $connection['item2'] === '') {
                throw new Exception('Both item1 and item2 are required for merged items');
            }
            $connection['item'] = '-2';
        } elseif ($connection['item'] === '') {
            throw new Exception('Item is required');
        }

        $template = new Model_E2pdf_Template();
        $template->set('title', $title);
        $template->set('extension', $extension_key);
        foreach ($connection as $key => $value) {
            $template->set($key, $value);
        }
        $template->set('width', '595');
        $template->set('height', '842');
        $template->set('font', 'Noto Sans Regular');
        $template->set('font_size', '12');
        $template->set('line_height', '12');
        if (isset($arguments['access_check']) && current_user_can('manage_options')) {
            $template->set('access_check', $arguments['access_check'] ? '1' : '0');
        } else {
            $template->set('access_check', get_option('e2pdf_access_check', '0') ? '1' : '0');
        }
        $template->set(
                'pages',
                [
                    1 => [
                        'properties' => [
                            'width' => $template->get('width'),
                            'height' => $template->get('height'),
                        ],
                        'actions' => [],
                        'elements' => [],
                    ],
                ]
        );

        $template_id = $template->save(true);
        if (!$template_id) {
            throw new Exception('Template could not be saved');
        }

        $data = [
            'site' => $site['url'],
            'template_id' => (int) $template_id,
            'activated' => false,
            'next' => 'Add elements and change settings with edit_template, then call get_usage_instructions to tell the user where to paste the PDF shortcodes.',
        ];
        if (!isset($arguments['activate']) || $arguments['activate']) {
            $request = $template->activate();
            if (isset($request['error'])) {
                $data['activation_error'] = wp_strip_all_tags((string) $request['error']);
            } else {
                $data['activated'] = true;
            }
        }

        return $this->json_content($data);
    }

    // tool: edit_template
    public function tool_edit_template($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $site = $this->get_site();
        $template = $this->load_template($arguments);
        $mode = isset($arguments['mode']) && in_array($arguments['mode'], ['preview', 'save'], true) ? $arguments['mode'] : '';
        if (!$mode) {
            throw new Exception('Mode is required: preview or save');
        }

        $changes = array_intersect(['settings', 'hooks', 'delete_elements', 'delete_pages', 'add_pages', 'update_elements', 'add_elements'], array_keys($arguments));

        // save previewed changes
        $preview_transient = '';
        if (!empty($arguments['preview_id'])) {
            $preview_transient = 'e2pdf_mcp_preview_' . md5(get_current_user_id() . '|' . (is_scalar($arguments['preview_id']) ? (string) $arguments['preview_id'] : ''));
            $preview = get_transient($preview_transient);
            if ($mode !== 'save' || !is_array($preview) || (int) $preview['template_id'] !== (int) $template->get('ID') || $changes) {
                throw new Exception('preview_id not found or expired, or passed together with changes: run the preview again or pass the changes');
            }
            $arguments = array_merge($preview, ['mode' => 'save']);
        } elseif ($mode === 'save' && !$changes) {
            throw new Exception('No changes to save');
        }

        // templates with elements are backed up before save
        $backup = $mode === 'save' && array_filter(wp_list_pluck((array) $template->get('pages'), 'elements'));

        // settings
        $arguments['settings'] = isset($arguments['settings']) && is_array($arguments['settings']) ? $arguments['settings'] : [];
        $controller = new Controller_E2pdf_Templates();
        foreach ($arguments['settings'] as $key => $value) {
            if ($key === 'size') {
                $sizes = $controller->get_sizes_list();
                if (!is_string($value) || !isset($sizes[$value])) {
                    throw new Exception(sprintf('Invalid size, allowed: %s', implode(', ', array_keys($sizes))));
                }
                $template->set('width', $sizes[$value]['width']);
                $template->set('height', $sizes[$value]['height']);
                continue;
            }
            if (!in_array($key, $this->template_settings, true) || !is_scalar($value) || ($key === 'access_check' && !current_user_can('manage_options'))) {
                throw new Exception(sprintf('Unknown or invalid setting "%s"', $key));
            }
            if (($key === 'width' || $key === 'height') && (!is_numeric($value) || $value <= 0)) {
                throw new Exception(sprintf('Invalid %s', $key));
            }
            if ($key === 'font' && !in_array((string) $value, $controller->get_fonts(), true)) {
                throw new Exception(sprintf('Invalid font, allowed: %s', implode(', ', $controller->get_fonts())));
            }
            if (($key === 'font_size' && !isset($controller->get_font_sizes()[(string) $value])) || ($key === 'line_height' && !isset($controller->get_line_heights()[(string) $value]))) {
                throw new Exception(sprintf('Invalid %s, allowed: 1-%d', $key, count($key === 'font_size' ? $controller->get_font_sizes() : $controller->get_line_heights())));
            }
            if ($key === 'lang_code' && !isset($controller->get_lang_codes()[(string) $value])) {
                throw new Exception('Invalid lang_code, use an ISO 639-1 code (e.g. "en") or language-region code (e.g. "en-US")');
            }
            if (!$template->set($key, sanitize_text_field((string) $value))) {
                throw new Exception(sprintf('Invalid value for setting "%s"', $key));
            }
        }

        // hooks
        if (isset($arguments['hooks'])) {
            if (!is_array($arguments['hooks'])) {
                throw new Exception('Hooks must be an array');
            }
            $extension_hooks = apply_filters('e2pdf_extension_hooks', []);
            $hooks_key = $template->get('extension') === 'woocommerce' || ($template->get('extension') === 'wordpress' && $template->get('item') == '-3') ? $template->get('extension') . '_' . $template->get('item') : $template->get('extension');
            $extension_hooks = isset($extension_hooks[$hooks_key]) && is_array($extension_hooks[$hooks_key]) ? $extension_hooks[$hooks_key] : [];
            foreach ($arguments['hooks'] as $hook) {
                if (!is_string($hook) || !isset($extension_hooks[$hook])) {
                    throw new Exception(sprintf('Unknown hook "%s", available: %s', is_scalar($hook) ? $hook : '', implode(', ', array_keys($extension_hooks))));
                }
            }
            $template->set('hooks', implode(',', array_unique($arguments['hooks'])));
        }

        $pages = (array) $template->get('pages');

        // delete elements
        $deleted = [];
        if (!empty($arguments['delete_elements'])) {
            if (!is_array($arguments['delete_elements'])) {
                throw new Exception('delete_elements must be an array');
            }
            $element_ids = array_map('intval', $arguments['delete_elements']);
            foreach ($pages as $page_id => $page) {
                foreach ((array) (isset($page['elements']) ? $page['elements'] : []) as $element_key => $element) {
                    if (in_array((int) $element['element_id'], $element_ids, true)) {
                        unset($pages[$page_id]['elements'][$element_key]);
                        $deleted[] = (int) $element['element_id'];
                    }
                }
                if (isset($pages[$page_id]['elements'])) {
                    $pages[$page_id]['elements'] = array_values($pages[$page_id]['elements']);
                }
            }
            $not_found = array_values(array_diff($element_ids, $deleted));
            if (!empty($not_found)) {
                throw new Exception(sprintf('Elements not found: %s', implode(', ', $not_found)));
            }
        }

        // delete pages
        if (!empty($arguments['delete_pages'])) {
            if (!is_array($arguments['delete_pages'])) {
                throw new Exception('delete_pages must be an array');
            }
            if ($template->get('pdf')) {
                throw new Exception('Deleting pages is not available for templates based on an uploaded PDF');
            }
            foreach ($arguments['delete_pages'] as $page_id) {
                if (!is_numeric($page_id) || !isset($pages[(int) $page_id])) {
                    throw new Exception(sprintf('Page %s not found', is_scalar($page_id) ? $page_id : ''));
                }
                unset($pages[(int) $page_id]);
            }
            if (empty($pages)) {
                throw new Exception('The last page of a template cannot be deleted');
            }
            $pages = array_combine(range(1, count($pages)), array_values($pages));
        }

        // add pages
        if (!empty($arguments['add_pages'])) {
            if (!is_array($arguments['add_pages'])) {
                throw new Exception('add_pages must be an array');
            }
            if ($template->get('pdf')) {
                throw new Exception('Adding new pages is not available for templates based on an uploaded PDF');
            }
            if (!$this->helper->get('license')) {
                $this->helper->load('license')->load();
            }
            if ($this->helper->get('license') && $this->helper->get('license')->get('type') == 'FREE') {
                throw new Exception('Only single-page PDFs are allowed with the "FREE" license type');
            }
            $pages = array_values($pages);
            foreach ($arguments['add_pages'] as $position) {
                $position = is_numeric($position) ? min(max((int) $position, 1), count($pages) + 1) : count($pages) + 1;
                array_splice(
                        $pages,
                        $position - 1,
                        0,
                        [
                            [
                                'properties' => [
                                    'width' => $template->get('width'),
                                    'height' => $template->get('height'),
                                ],
                                'actions' => [],
                                'elements' => [],
                            ],
                        ]
                );
            }
            $pages = array_combine(range(1, count($pages)), $pages);
        }

        if (isset($arguments['settings']['width']) || isset($arguments['settings']['height']) || isset($arguments['settings']['size'])) {
            foreach ($pages as $page_id => $page) {
                $pages[$page_id]['properties']['width'] = $template->get('width');
                $pages[$page_id]['properties']['height'] = $template->get('height');
            }
        }

        // update and add elements, new elements start with the template editor defaults
        $updated = [];
        $added = [];
        $size = (string) ((float) ($template->get('line_height') ? $template->get('line_height') : '12') + 4);
        $element_id = 0;
        foreach ((array) $template->get('pages') as $page) {
            foreach ((array) (isset($page['elements']) ? $page['elements'] : []) as $element) {
                $element_id = max($element_id, (int) $element['element_id']);
            }
        }
        foreach (['update_elements', 'add_elements'] as $list) {
            if (empty($arguments[$list])) {
                continue;
            }
            if (!is_array($arguments[$list])) {
                throw new Exception(sprintf('%s must be an array', $list));
            }
            foreach (array_values($arguments[$list]) as $index => $element_changes) {
                $element_changes = is_array($element_changes) ? $element_changes : [];
                $location = false;
                if ($list === 'update_elements') {
                    $label = sprintf('Element %d', isset($element_changes['element_id']) && is_numeric($element_changes['element_id']) ? (int) $element_changes['element_id'] : 0);
                    foreach ($pages as $page_id => $page) {
                        foreach ((array) (isset($page['elements']) ? $page['elements'] : []) as $element_key => $element) {
                            if (isset($element_changes['element_id']) && (int) $element['element_id'] === (int) $element_changes['element_id']) {
                                $location = [$page_id, $element_key];
                                break 2;
                            }
                        }
                    }
                    if (!$location) {
                        throw new Exception(sprintf('%s not found', $label));
                    }
                    $element = $pages[$location[0]]['elements'][$location[1]];
                } else {
                    $label = sprintf('New element %d', $index);
                    $type = isset($element_changes['type']) && is_string($element_changes['type']) ? $element_changes['type'] : '';
                    if (!isset($this->element_types[$type])) {
                        throw new Exception(sprintf('%s: unknown type "%s"', $label, $type));
                    }
                    $element = [
                        'element_id' => ++$element_id,
                        'type' => $type,
                        'name' => '',
                        'top' => '0',
                        'left' => '0',
                        'width' => $this->element_types[$type]['width'] === 'size' ? $size : $this->element_types[$type]['width'],
                        'height' => $this->element_types[$type]['height'] === 'size' ? $size : $this->element_types[$type]['height'],
                        'value' => isset($this->element_types[$type]['value']) ? $this->element_types[$type]['value'] : '',
                        'properties' => $this->element_types[$type]['properties'],
                        'actions' => [],
                    ];
                }
                foreach (['top', 'left', 'width', 'height'] as $key) {
                    if (isset($element_changes[$key])) {
                        if (!is_numeric($element_changes[$key]) || $element_changes[$key] < 0 || (($key === 'width' || $key === 'height') && $element_changes[$key] <= 0)) {
                            throw new Exception(sprintf('%s: invalid %s', $label, $key));
                        }
                        $element[$key] = (string) round((float) $element_changes[$key], 2);
                    }
                }
                if (isset($element_changes['value']) && is_scalar($element_changes['value'])) {
                    $element['value'] = (string) $element_changes['value'];
                    if (preg_match_all('/\[\/?(e2pdf-[a-z0-9_-]+)/i', $element['value'], $tags)) {
                        foreach (array_unique($tags[1]) as $tag) {
                            if (!shortcode_exists($tag) && !preg_match($this->shortcode_pattern, $tag)) {
                                throw new Exception(sprintf('%s: unknown shortcode [%s], see get_shortcodes for supported shortcodes and syntax', $label, $tag));
                            }
                        }
                        foreach ($this->shortcode_enclosing as $tag) {
                            if (preg_match_all('/\[' . $tag . '[\s\]]/', $element['value']) !== substr_count($element['value'], '[/' . $tag . ']')) {
                                throw new Exception(sprintf('%s: [%s] must be closed with [/%s], see get_shortcodes for the syntax', $label, $tag, $tag));
                            }
                        }
                    }
                }
                if (isset($element_changes['name']) && is_scalar($element_changes['name'])) {
                    $element['name'] = sanitize_text_field((string) $element_changes['name']);
                }
                if (isset($element_changes['properties']) && is_array($element_changes['properties'])) {
                    $element['properties'] = isset($element['properties']) && is_array($element['properties']) ? $element['properties'] : [];
                    foreach ($element_changes['properties'] as $key => $value) {
                        $key = sanitize_key($key);
                        if ($key === '' || in_array($key, ['top', 'left', 'width', 'height', 'value'], true)) {
                            continue;
                        }
                        if ($value === null) {
                            unset($element['properties'][$key]);
                        } elseif (is_scalar($value)) {
                            $element['properties'][$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                        } else {
                            throw new Exception(sprintf('%s: invalid property "%s"', $label, $key));
                        }
                    }
                }
                $page_id = isset($element_changes['page_id']) && is_numeric($element_changes['page_id']) ? (int) $element_changes['page_id'] : ($location ? $location[0] : 1);
                if (!isset($pages[$page_id])) {
                    throw new Exception(sprintf('%s: page %d not found', $label, $page_id));
                }
                if ($location && $page_id === $location[0]) {
                    $pages[$page_id]['elements'][$location[1]] = $element;
                } else {
                    if ($location) {
                        unset($pages[$location[0]]['elements'][$location[1]]);
                        $pages[$location[0]]['elements'] = array_values($pages[$location[0]]['elements']);
                    }
                    $pages[$page_id]['elements'][] = $element;
                }
                if ($location) {
                    $updated[] = (int) $element['element_id'];
                } else {
                    $added[] = [
                        'element_id' => $element['element_id'],
                        'page_id' => $page_id,
                        'type' => $element['type'],
                        'top' => (float) $element['top'],
                        'left' => (float) $element['left'],
                        'width' => (float) $element['width'],
                        'height' => (float) $element['height'],
                    ];
                }
            }
        }

        // page and template ids for rendering, save uses the keys
        foreach ($pages as $page_id => $page) {
            $pages[$page_id]['page_id'] = $page_id;
            $pages[$page_id]['template_id'] = $template->get('ID');
            foreach ((array) (isset($page['elements']) ? $page['elements'] : []) as $element_key => $element) {
                $pages[$page_id]['elements'][$element_key]['page_id'] = $page_id;
                $pages[$page_id]['elements'][$element_key]['template_id'] = $template->get('ID');
            }
        }
        $template->set('pages', $pages);

        $result = [
            'site' => $site['url'],
            'template_id' => (int) $template->get('ID'),
            'mode' => $mode,
            'saved' => false,
            'deleted_elements' => $deleted,
            'pages' => count($pages),
            'updated_elements' => $updated,
            'added_elements' => $added,
        ];

        if ($mode === 'preview') {
            $format = isset($arguments['preview_format']) && $arguments['preview_format'] === 'pdf' ? 'pdf' : 'jpg';
            $template->set('format', $format);
            // both branches render the not saved template: with dataset via Controller_E2pdf::create_pdf(),
            // without dataset via Controller_E2pdf_Templates::preview_template() as template editor Preview
            if (!empty($arguments['dataset']) || !empty($arguments['dataset2'])) {
                $this->check_caps(['e2pdf']);
                if ($template->get('activated') != '1') {
                    throw new Exception(sprintf('Template %d is not activated', $template->get('ID')));
                }
                // render in the current WPML language, MCP requests carry no language url
                if ($this->helper->load('translator')->isWPML()) {
                    do_action('wpml_switch_language', apply_filters('wpml_current_language', null));
                }
                $request = (new Controller_E2pdf())->create_pdf($this->pdf_data($arguments), $template);
            } else {
                // field values are shown as placeholders
                $request = (new Controller_E2pdf_Templates())->preview_template(
                        array_merge(
                                $template->pre_save(),
                                [
                                    'ID' => $template->get('ID'),
                                    'permissions' => $template->get('permissions'),
                                    'fonts' => $template->get('fonts'),
                                    'properties' => $template->get('properties'),
                                    'actions' => $template->get('actions'),
                                    'pages' => $template->get('pages'),
                                ]
                        )
                );
            }
            if (isset($request['error'])) {
                throw new Exception(isset($request['error_code']) && $request['error_code'] === 'incorrect_dataset' ? 'Incorrect Dataset: the dataset does not belong to the template item, see list_datasets' : wp_strip_all_tags((string) $request['error']));
            }
            $blob = base64_encode($request['file']); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            $result['preview_id'] = wp_generate_password(12, false);
            set_transient('e2pdf_mcp_preview_' . md5(get_current_user_id() . '|' . $result['preview_id']), $arguments, HOUR_IN_SECONDS);
            $result['message'] = sprintf('Nothing was saved. To save exactly these changes call edit_template with mode "save" and preview_id "%s" (valid 1 hour), do not resend the changes.', $result['preview_id']);
            return array_merge(
                    $this->json_content($result),
                    [
                        $format === 'jpg' ? [
                            'type' => 'image',
                            'data' => $blob,
                            'mimeType' => 'image/jpeg',
                        ] : [
                            'type' => 'resource',
                            'resource' => [
                                'uri' => 'e2pdf://' . $site['host'] . '/template/' . $template->get('ID') . '/preview.pdf',
                                'mimeType' => 'application/pdf',
                                'blob' => $blob,
                            ],
                        ],
                    ]
            );
        }

        // backup as Templates > Backup, before save
        if ($backup) {
            $backup = $controller->backup_template(
                    $template->get('ID'),
                    [
                        'images' => '0',
                        'fonts' => '0',
                        'item' => '0',
                    ]
            );
            if (isset($backup['error'])) {
                throw new Exception('Backup could not be created, nothing was saved');
            }
            $backup['name'] = $this->helper->load('convert')->to_file_name($backup['name']) . '.xml';
        }

        if (!$template->save(true)) {
            throw new Exception('Template could not be saved');
        }
        if ($preview_transient) {
            delete_transient($preview_transient);
        }

        $result['saved'] = true;
        $result['hooks'] = $template->get('hooks') ? explode(',', $template->get('hooks')) : [];
        if (!$backup) {
            return $this->json_content($result);
        }
        $result['backup'] = $backup['name'];
        $result['message'] = sprintf('The attached resource is the backup of the template before saving. Save its text unchanged as file "%s" now (use your file tools; if you cannot write files, give it to the user as a downloadable file) and tell the user where it is. Restore: E2Pdf > Templates > Import.', $backup['name']);
        return array_merge(
                $this->json_content($result),
                [
                    [
                        'type' => 'resource',
                        'resource' => [
                            'uri' => 'e2pdf://' . $site['host'] . '/backup/' . rawurlencode($backup['name']),
                            'mimeType' => 'application/xml',
                            'text' => $backup['file'],
                        ],
                    ],
                ]
        );
    }

    // tool: set_template_activation
    public function tool_set_template_activation($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $template = $this->load_template($arguments, false);
        if (!isset($arguments['activated'])) {
            throw new Exception('Activated is required');
        }
        $request = (new Controller_E2pdf_Templates())->{$arguments['activated'] ? 'activate_template' : 'deactivate_template'}($template->get('ID'));
        if (isset($request['error'])) {
            throw new Exception(wp_strip_all_tags((string) $request['error']));
        }

        return $this->json_content(
                        [
                            'template_id' => (int) $template->get('ID'),
                            'activated' => $template->get('activated', true) == '1',
                        ]
                );
    }

    // tool: trash_template
    public function tool_trash_template($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $template = $this->load_template($arguments, false);
        $transient = 'e2pdf_mcp_confirm_' . md5('trash_template|' . $template->get('ID') . '|' . get_current_user_id());
        $confirmation_code = isset($arguments['confirmation_code']) && is_scalar($arguments['confirmation_code']) ? strtoupper(trim((string) $arguments['confirmation_code'])) : '';
        $code = get_transient($transient);
        if (!$confirmation_code || !$code || !hash_equals((string) $code, $confirmation_code)) {
            $code = strtoupper(wp_generate_password(6, false));
            set_transient($transient, $code, 600);
            return $this->json_content(
                            [
                                'confirmation_required' => true,
                                'confirmation_code' => $code,
                                'message' => sprintf('Template %d "%s" will be moved to trash and deactivated, PDFs of this template will stop working. Ask the user to explicitly confirm this action. Only after the user confirmed, call trash_template again with confirmation_code "%s". The code expires in 10 minutes.', $template->get('ID'), $template->get('title'), $code),
                            ]
                    );
        }
        delete_transient($transient);

        (new Controller_E2pdf_Templates())->trash_template($template->get('ID'));

        return $this->json_content(
                        [
                            'template_id' => (int) $template->get('ID'),
                            'trashed' => true,
                        ]
                );
    }

    // tool: restore_template
    public function tool_restore_template($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $template = $this->load_template($arguments, false, true);
        (new Controller_E2pdf_Templates())->restore_template($template->get('ID'));

        return $this->json_content(
                        [
                            'template_id' => (int) $template->get('ID'),
                            'restored' => true,
                            'activated' => false,
                        ]
                );
    }

    // tool: delete_template
    public function tool_delete_template($arguments) {

        $this->check_caps(['e2pdf_templates']);

        $template = $this->load_template($arguments, false, true);
        $transient = 'e2pdf_mcp_confirm_' . md5('delete_template|' . $template->get('ID') . '|' . get_current_user_id());
        $confirmation_code = isset($arguments['confirmation_code']) && is_scalar($arguments['confirmation_code']) ? strtoupper(trim((string) $arguments['confirmation_code'])) : '';
        $code = get_transient($transient);
        if (!$confirmation_code || !$code || !hash_equals((string) $code, $confirmation_code)) {
            $code = strtoupper(wp_generate_password(6, false));
            set_transient($transient, $code, 600);
            return $this->json_content(
                            [
                                'confirmation_required' => true,
                                'confirmation_code' => $code,
                                'message' => sprintf('Template %d "%s" and all its revisions will be permanently deleted. This cannot be undone. Ask the user to explicitly confirm this action. Only after the user confirmed, call delete_template again with confirmation_code "%s". The code expires in 10 minutes.', $template->get('ID'), $template->get('title'), $code),
                            ]
                    );
        }
        delete_transient($transient);

        (new Controller_E2pdf_Templates())->delete_template($template->get('ID'));

        return $this->json_content(
                        [
                            'template_id' => (int) $template->get('ID'),
                            'deleted' => true,
                        ]
                );
    }

    // load template from tool arguments
    protected function load_template($arguments, $extension = true, $trash = false) {
        $template_id = isset($arguments['template_id']) && is_numeric($arguments['template_id']) ? (int) $arguments['template_id'] : 0;
        $template = new Model_E2pdf_Template();
        if (!$template_id || !$template->load($template_id) || ($template->get('trash') == '1') !== $trash) {
            throw new Exception(sprintf($trash ? 'Template %d not found in trash' : 'Template %d not found', $template_id));
        }
        if ($extension && (!$template->extension() || !$template->extension()->extension())) {
            throw new Exception(sprintf('Extension "%s" of template %d is not active', $template->get('extension'), $template_id));
        }
        return $template;
    }

    // item info
    protected function get_item($extension, $item_id) {
        $item = [
            'id' => (string) $item_id,
            'name' => '',
        ];
        if ($extension && $item_id !== '' && $item_id !== null) {
            $info = $extension->item($item_id);
            if ($info && isset($info->name)) {
                $item['name'] = $info->name;
            }
        }
        return $item;
    }

    // check if user is set (mcp request runs as Run As User)
    protected function check_user() {
        if (!is_user_logged_in() || !get_current_user_id()) {
            throw new Exception('User is not set');
        }
        return true;
    }

    // check current user caps: manage_options or one of the required caps
    protected function check_caps($caps = []) {
        $this->check_user();
        if (current_user_can('manage_options')) {
            return true;
        }
        foreach ($caps as $cap) {
            if (current_user_can($cap)) {
                return true;
            }
        }
        throw new Exception($caps ? sprintf('Permission denied: missing capability "%s"', implode('" or "', $caps)) : 'Permission denied');
    }

    // integer argument
    protected function get_int($arguments, $key, $default, $min = null, $max = null) {
        $value = isset($arguments[$key]) && is_numeric($arguments[$key]) ? (int) $arguments[$key] : $default;
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        if ($max !== null && $value > $max) {
            $value = $max;
        }
        return $value;
    }

    // text content with json
    protected function json_content($data) {
        return [
            [
                'type' => 'text',
                'text' => wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    // site identity, sites connected to the same agent are separate mcp servers with own template and dataset ids
    protected function get_site() {

        $url = (string) $this->helper->get_site_url();
        if (!$url) {
            $url = home_url();
        }
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        $port = wp_parse_url($url, PHP_URL_PORT);

        return [
            'host' => $host ? $host . ($port ? ':' . $port : '') : $url,
            'url' => $url,
        ];
    }

    // json-rpc result
    public function result($id, $result) {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    // json-rpc error
    public function error($id, $code, $message) {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
