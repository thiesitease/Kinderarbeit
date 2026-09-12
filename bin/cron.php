<?php
declare(strict_types=1);

/**
 * Bucht faellige feste Ausgaben ab.
 *
 * Die Anwendung erledigt das ohnehin beim ersten Seitenaufruf des Tages.
 * Wer einen Cronjob hat, kann das zusaetzlich absichern – zum Beispiel taeglich
 * um 6 Uhr:
 *
 *     0 6 * * * /usr/bin/php /pfad/zur/anwendung/bin/cron.php >/dev/null 2>&1
 */

require dirname(__DIR__) . '/app/cli.php';

$booked = Billing::run(true);

printf("[%s] %d Abbuchung%s angelegt.\n", date('Y-m-d H:i:s'), $booked, $booked === 1 ? '' : 'en');
