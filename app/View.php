<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/** Minimale Template-Schicht: Views sind reine PHP-Dateien. */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** View rendern und als String zurueckgeben. */
    public static function render(string $template, array $data = []): string
    {
        $file = APP_DIR . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View nicht gefunden: ' . $template);
        }

        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }

    /** View in das Layout einbetten und ausgeben. */
    public static function page(string $template, array $data = []): void
    {
        $content = self::render($template, $data);
        echo self::render('layout', array_merge(self::$shared, $data, ['content' => $content]));
    }
}
