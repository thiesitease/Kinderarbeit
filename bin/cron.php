<?php
declare(strict_types=1);

/**
 * Bucht faellige feste Ausgaben ab.
 *
 * Die Anwendung erledigt das ohnehin beim ersten Seitenaufruf des Tages,
 * dieses Skript wird also nicht gebraucht.
 *
 * Wer es trotzdem taeglich laufen lassen will: bei manitu ueber das Feature
 * "Cronjob" im Kundenbereich, nicht ueber einen eigenen crontab-Eintrag –
 * den untersagen die AGB dort ausdruecklich.
 */

require dirname(__DIR__) . '/app/cli.php';

$booked = Billing::run(true);

printf("[%s] %d Abbuchung%s angelegt.\n", date('Y-m-d H:i:s'), $booked, $booked === 1 ? '' : 'en');
