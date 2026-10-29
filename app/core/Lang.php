<?php
/** Zinkly Social Lite - translations. Files: app/lang/<code>.php returning a flat ['key' => 'text'] array. English is the fallback. */
final class Lang
{
    public const LANGS = [
        'en' => 'English', 'el' => 'Ελληνικά', 'de' => 'Deutsch', 'fr' => 'Français',
        'pt_BR' => 'Português (Brasil)', 'es_MX' => 'Español (México)', 'ru' => 'Русский', 'it' => 'Italiano',
    ];

    private static string $locale = 'en';
    private static array $lines = [];
    private static array $fallback = [];

    public static function set(string $code): void
    {
        self::$locale = isset(self::LANGS[$code]) ? $code : 'en';
        self::$fallback = self::load('en');
        self::$lines = self::$locale === 'en' ? self::$fallback : self::load(self::$locale);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function htmlLang(): string
    {
        return str_replace('_', '-', self::$locale);
    }

    private static function load(string $code): array
    {
        $f = ZL_ROOT . '/app/lang/' . $code . '.php';
        return is_file($f) ? (array) include $f : [];
    }

    /** Pick a language: user setting > cookie > browser > site default. */
    public static function detect(?array $user): string
    {
        if ($user && isset(self::LANGS[$user['lang'] ?? ''])) {
            return $user['lang'];
        }
        $c = $_COOKIE['zl_lang'] ?? '';
        if (isset(self::LANGS[$c])) {
            return $c;
        }
        $def = (string) setting('default_lang', '');
        if (isset(self::LANGS[$def]) && setting('force_default_lang') === '1') {
            return $def;
        }
        $byPrimary = ['en' => 'en', 'el' => 'el', 'de' => 'de', 'fr' => 'fr', 'pt' => 'pt_BR', 'es' => 'es_MX', 'ru' => 'ru', 'it' => 'it'];
        foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            if ($tag === '') {
                continue;
            }
            foreach (array_keys(self::LANGS) as $code) {
                if ($tag === strtolower(str_replace('_', '-', $code))) {
                    return $code;
                }
            }
            $primary = explode('-', $tag)[0];
            if (isset($byPrimary[$primary])) {
                return $byPrimary[$primary];
            }
        }
        return isset(self::LANGS[$def]) ? $def : 'en';
    }

    public static function get(string $key, array $r = []): string
    {
        $s = self::$lines[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($r as $k => $v) {
            $s = str_replace(':' . $k, (string) $v, $s);
        }
        return $s;
    }

    /** "one|other" texts: tc('posts.n_likes', 3). */
    public static function choice(string $key, int $n, array $r = []): string
    {
        $parts = explode('|', self::get($key, $r + ['n' => $n]));
        if (count($parts) === 1) {
            return $parts[0];
        }
        if (self::$locale === 'ru') {
            $m10 = $n % 10;
            $m100 = $n % 100;
            $i = ($m10 === 1 && $m100 !== 11) ? 0 : (($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) ? 1 : 2);
            return $parts[min($i, count($parts) - 1)];
        }
        if (in_array(self::$locale, ['fr', 'pt_BR'], true)) {
            return $n <= 1 ? $parts[0] : $parts[1];
        }
        return $n === 1 ? $parts[0] : $parts[1];
    }

    /** Texts the browser script needs (keys starting with "js."). */
    public static function jsTexts(): array
    {
        $out = [];
        foreach (self::$fallback as $k => $v) {
            if (str_starts_with($k, 'js.')) {
                $out[substr($k, 3)] = self::$lines[$k] ?? $v;
            }
        }
        return $out;
    }

    public static function date(int $ts): string
    {
        $months = explode(',', self::get('time.months'));
        $m = $months[(int) date('n', $ts) - 1] ?? date('M', $ts);
        $y = date('Y', $ts) !== date('Y') ? ' ' . date('Y', $ts) : '';
        return in_array(self::$locale, ['en'], true) ? $m . ' ' . date('j', $ts) . $y : date('j', $ts) . ' ' . $m . $y;
    }
}
