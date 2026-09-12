<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

/** HTML-sicher ausgeben. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Interne URL bauen: url('eltern', ['id' => 3]) => index.php?p=eltern&id=3 */
function url(string $page = 'start', array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    return './?' . http_build_query($params);
}

/** Redirect und Skript beenden. */
function redirect(string $page = 'start', array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function redirect_url(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/** Trimmter String aus dem Request. */
function param(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function param_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($value) ? (int)$value : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Aktueller Zeitstempel im SQLite-Format. */
function now(): string
{
    return date('Y-m-d H:i:s');
}

function current_month(): string
{
    return date('Y-m');
}

/** 'YYYY-MM' => 'September 2026' */
function month_label(string $month): string
{
    static $names = [
        1 => 'Januar', 2 => 'Februar', 3 => 'Maerz', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
        7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];
    [$year, $m] = array_pad(explode('-', $month), 2, '1');
    $name = $names[(int)$m] ?? $m;
    return str_replace('Maerz', 'März', $name) . ' ' . $year;
}

/** Monat verschieben: month_shift('2026-01', -1) => '2025-12' */
function month_shift(string $month, int $delta): string
{
    $ts = strtotime($month . '-01 12:00:00');
    return date('Y-m', (int)strtotime(($delta >= 0 ? '+' : '-') . abs($delta) . ' month', (int)$ts));
}

/** Datum freundlich: "heute, 14:20" / "gestern, 09:05" / "12.09.2026, 14:20" */
function format_datetime(?string $sqlDatetime): string
{
    if (!$sqlDatetime) {
        return '–';
    }
    $ts = strtotime($sqlDatetime);
    if ($ts === false) {
        return '–';
    }
    $day = date('Y-m-d', $ts);
    if ($day === date('Y-m-d')) {
        return 'heute, ' . date('H:i', $ts) . ' Uhr';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'gestern, ' . date('H:i', $ts) . ' Uhr';
    }
    return date('d.m.Y, H:i', $ts) . ' Uhr';
}

function format_date(?string $sqlDatetime): string
{
    if (!$sqlDatetime) {
        return '–';
    }
    $ts = strtotime($sqlDatetime);
    return $ts === false ? '–' : date('d.m.Y', $ts);
}

/** Anzahl Tage bis zum Monatsende. */
function days_left_in_month(): int
{
    return (int)date('t') - (int)date('j');
}

/** Kontrastfarbe (schwarz/weiss) zu einem Hex-Farbwert. */
function contrast_color(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '#ffffff';
    }
    $r = (int)hexdec(substr($hex, 0, 2));
    $g = (int)hexdec(substr($hex, 2, 2));
    $b = (int)hexdec(substr($hex, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.62 ? '#1c1917' : '#ffffff';
}

/** Kurzer Zufallsstring (z. B. fuer Tokens). */
function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Regeln fuer eine gueltige PIN. Wird sowohl von der Anwendung als auch von
 * den Werkzeugen in bin/ genutzt.
 */
function validate_pin(string $pin, ?string &$error = null, int $min = 4, int $max = 10): bool
{
    if (!preg_match('/^\d+$/', $pin)) {
        $error = 'Die PIN darf nur aus Ziffern bestehen.';
        return false;
    }
    $length = strlen($pin);
    if ($length < $min || $length > $max) {
        $error = 'Die PIN muss zwischen ' . $min . ' und ' . $max . ' Ziffern lang sein.';
        return false;
    }
    if (preg_match('/^(\d)\1*$/', $pin)) {
        $error = 'Bitte keine PIN aus lauter gleichen Ziffern wählen.';
        return false;
    }
    if (in_array($pin, ['1234', '0123', '12345', '123456', '4321'], true)) {
        $error = 'Diese PIN ist zu leicht zu erraten.';
        return false;
    }
    return true;
}
