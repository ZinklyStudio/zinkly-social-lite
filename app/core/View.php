<?php
/** Zinkly Social Lite - plain PHP templates in app/views. Variables are escaped with e() in the templates. */
final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $content = self::fetch($view, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::fetch($layout, $data + ['content' => $content]);
    }

    public static function fetch(string $view, array $data = []): string
    {
        $file = ZL_ROOT . '/app/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function partial(string $view, array $data = []): void
    {
        echo self::fetch('partials/' . $view, $data);
    }
}
