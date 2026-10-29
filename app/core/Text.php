<?php
/** Zinkly Social Lite - safe rich text: escapes everything, then links URLs, #hashtags and @mentions. */
final class Text
{
    public static function render(?string $s): string
    {
        return nl2br(self::linkify(e((string) $s)), false);
    }

    /** Link URLs, #hashtags and @mentions in text that is already escaped with e(). */
    private static function linkify(string $s): string
    {
        // URLs (escaped text: & is &amp;, quotes are entities, so they cannot break the attribute)
        $s = preg_replace_callback('~\bhttps?://[^\s<]+~iu', function ($m) {
            $url = $m[0];
            $trail = '';
            while ($url !== '' && preg_match('/[.,!?;:)\]]$/', $url)) {
                $trail = substr($url, -1) . $trail;
                $url = substr($url, 0, -1);
            }
            $shown = str_limit(preg_replace('~^https?://(www\.)?~i', '', html_entity_decode($url, ENT_QUOTES, 'UTF-8')), 48);
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow ugc" class="zl-link">' . e($shown) . '</a>' . $trail;
        }, $s) ?? $s;
        $s = preg_replace_callback('/(^|[^\p{L}\p{N}_&\/])#([\p{L}\p{N}_]{1,64})/u', fn ($m) => $m[1] . '<a href="' . e(url('hashtag/' . rawurlencode(mb_strtolower($m[2])))) . '" class="zl-tag">#' . $m[2] . '</a>', $s) ?? $s;
        $s = preg_replace_callback('/(^|[^\p{L}\p{N}_\/.])@([A-Za-z0-9_]{3,32})/u', fn ($m) => $m[1] . '<a href="' . e(url('u/' . $m[2])) . '" class="zl-tag">@' . $m[2] . '</a>', $s) ?? $s;
        return $s;
    }

    /* ---------- rich text (HTML posts and site pages) ---------- */

    /** The only tags rich text keeps. Everything else is unwrapped (its text stays) or dropped with its content. */
    public const RICH_TAGS = ['p', 'br', 'strong', 'em', 'u', 's', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'a', 'code', 'pre', 'hr'];
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'h1' => 'h2', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3', 'div' => 'p'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'textarea', 'select', 'button', 'input',
        'form', 'head', 'title', 'meta', 'link', 'base', 'frame', 'frameset', 'video', 'audio', 'img', 'picture', 'source', 'canvas', 'applet', 'param', 'option'];

    public static function richAvailable(): bool
    {
        return class_exists('DOMDocument');
    }

    /**
     * Clean HTML from the editor: only RICH_TAGS, no attributes except a safe href on links.
     * The result is stored; links for #tags and @names are added when it is shown (renderHtml).
     */
    public static function sanitize(string $html): string
    {
        if (!self::richAvailable() || trim($html) === '') {
            return '';
        }
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? '';
        $src = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $src->loadHTML('<?xml encoding="utf-8"?><html><body><div>' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $src->getElementsByTagName('body')->item(0)?->firstChild;
        if (!$root) {
            return '';
        }
        $out = new DOMDocument('1.0', 'UTF-8');
        $box = $out->createElement('div');
        $out->appendChild($box);
        self::copy($root, $box, $out, 0, false);
        // no empty paragraphs at the start or the end
        foreach ([true, false] as $fromStart) {
            while ($box->hasChildNodes()) {
                $n = $fromStart ? $box->firstChild : $box->lastChild;
                $empty = ($n instanceof DOMText && trim($n->nodeValue) === '')
                    || ($n instanceof DOMElement && in_array($n->nodeName, ['p', 'br'], true) && trim(str_replace("\xC2\xA0", ' ', $n->textContent)) === '');
                if (!$empty) {
                    break;
                }
                $box->removeChild($n);
            }
        }
        $s = '';
        foreach ($box->childNodes as $n) {
            $s .= $out->saveHTML($n);
        }
        return trim($s);
    }

    private static function copy(DOMNode $from, DOMNode $to, DOMDocument $out, int $depth, bool $inBlock): void
    {
        foreach ($from->childNodes as $n) {
            if ($n instanceof DOMText) {
                $to->appendChild($out->createTextNode($n->nodeValue));
                continue;
            }
            if (!$n instanceof DOMElement) {
                continue; // comments, processing instructions
            }
            $tag = strtolower($n->nodeName);
            $tag = self::RENAME[$tag] ?? $tag;
            if (in_array($tag, self::DROP, true)) {
                continue;
            }
            // the only span kept: a spoiler (text hidden until tapped)
            if ($tag === 'span' && $depth <= 12 && in_array('zl-spoiler', preg_split('/\s+/', $n->getAttribute('class')), true)
                && !($to instanceof DOMElement && $to->getAttribute('class') === 'zl-spoiler')) {
                $sp = $out->createElement('span');
                $sp->setAttribute('class', 'zl-spoiler');
                $to->appendChild($sp);
                self::copy($n, $sp, $out, $depth + 1, true);
                if (trim($sp->textContent) === '') {
                    $to->removeChild($sp);
                }
                continue;
            }
            // a paragraph that holds lists, quotes or headings (browsers' editors make these) is unwrapped: <p><ul> is not valid HTML
            $wrapsBlocks = $tag === 'p' && (new DOMXPath($n->ownerDocument))->query('./ul|./ol|./blockquote|./h1|./h2|./h3|./h4|./pre|./p|./div|./hr', $n)->length > 0;
            if (!in_array($tag, self::RICH_TAGS, true) || $depth > 12 || ($tag === 'p' && $inBlock) || $wrapsBlocks) {
                self::copy($n, $to, $out, $depth, $inBlock); // keep the text, lose the tag
                if ($tag === 'p' && $inBlock && $n->nextSibling) {
                    $to->appendChild($out->createElement('br'));
                }
                continue;
            }
            $el = $out->createElement($tag);
            if ($tag === 'a') {
                $href = self::safeHref($n->getAttribute('href'));
                if ($href === null) {
                    self::copy($n, $to, $out, $depth, $inBlock);
                    continue;
                }
                $el->setAttribute('href', $href);
            }
            $to->appendChild($el);
            if ($tag !== 'br' && $tag !== 'hr') {
                self::copy($n, $el, $out, $depth + 1, $inBlock || in_array($tag, ['p', 'h2', 'h3', 'li', 'pre', 'code'], true));
                // empty paragraphs, quotes, headings and list items are dropped
                if (in_array($tag, ['p', 'blockquote', 'h2', 'h3', 'li', 'ul', 'ol'], true) && trim(str_replace(["\xC2\xA0", "\xE2\x80\x8B"], '', $el->textContent)) === '') {
                    $to->removeChild($el);
                }
            }
        }
    }

    /** http(s), mailto and links inside the site only. */
    private static function safeHref(string $href): ?string
    {
        $href = trim(preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($href === '' || strlen($href) > 1000 || preg_match('/[<>"\'`\\\\]/', $href)) {
            return null;
        }
        if (preg_match('~^https?://~i', $href)) {
            return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
        }
        if (preg_match('~^mailto:[^@\s]+@[^@\s]+$~i', $href)) {
            return $href;
        }
        if (preg_match('~^/(?!/)~', $href)) {
            return $href;
        }
        return null;
    }

    /** True when cleaned HTML has formatting worth keeping (otherwise the post is saved as plain text). */
    public static function hasFormatting(string $clean): bool
    {
        return (bool) preg_match('~<(strong|em|u|s|h2|h3|blockquote|ul|ol|code|pre|hr|a)\b|<span class="zl-spoiler">~', $clean);
    }

    /** Show cleaned HTML: link URLs, #hashtags and @mentions in the text parts (not inside links or code). */
    public static function renderHtml(?string $clean): string
    {
        $parts = preg_split('~(<[^>]*>)~', (string) $clean, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $skip = 0;
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('~^<(/?)(a|code|pre)\b~i', $part, $m)) {
                    $skip = max(0, $skip + ($m[1] === '/' ? -1 : 1));
                }
                // links in posts open in a new tab and are not followed by search engines
                if (preg_match('~^<a href="([^"]*)">$~', $part, $m)) {
                    $part = str_starts_with($m[1], '/') ? '<a href="' . $m[1] . '" class="zl-link">'
                        : '<a href="' . $m[1] . '" class="zl-link" target="_blank" rel="noopener noreferrer nofollow ugc">';
                }
                $out .= $part;
                continue;
            }
            $out .= $skip ? $part : self::linkify(e(html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        return $out;
    }

    /** Plain text of a body (for hashtags, mentions, search, titles and previews). */
    public static function plain(?string $body, string $format = 'text'): string
    {
        $s = (string) $body;
        if ($format !== 'html') {
            return $s;
        }
        $s = preg_replace('~<br\s*/?>|</(p|h2|h3|li|blockquote|pre)>~i', "\n", $s) ?? $s;
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\xC2\xA0", ' ', $s);
        return trim(preg_replace("/\n{3,}/", "\n\n", $s) ?? $s);
    }

    /** A post body as HTML, whatever its format. */
    public static function body(?string $body, string $format = 'text'): string
    {
        return $format === 'html' ? self::renderHtml($body) : self::render($body);
    }

    /** Text for the editor box when a post is edited. */
    public static function editorHtml(?string $body, string $format = 'text'): string
    {
        return $format === 'html' ? (string) $body : nl2br(e((string) $body), false);
    }

    /** Hashtags in a text (lowercase, unique). */
    public static function hashtags(?string $s): array
    {
        preg_match_all('/(?:^|[^\p{L}\p{N}_&\/])#([\p{L}\p{N}_]{1,64})/u', (string) $s, $m);
        return array_values(array_unique(array_map('mb_strtolower', $m[1])));
    }

    /** @usernames in a text (unique). */
    public static function mentions(?string $s): array
    {
        preg_match_all('/(?:^|[^\p{L}\p{N}_\/.])@([A-Za-z0-9_]{3,32})/u', (string) $s, $m);
        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /** Clean user text: no control characters, max length, max 3 empty lines in a row. */
    public static function clean(?string $s, int $max): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", (string) $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = preg_replace("/\n{4,}/", "\n\n\n", $s) ?? $s;
        return mb_substr(trim($s), 0, $max);
    }
}
