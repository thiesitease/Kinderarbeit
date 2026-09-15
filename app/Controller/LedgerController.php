<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class LedgerController
{
    /** Formular fuer eine Auszahlung, einen Bonus oder eine Korrektur. */
    public static function form(): void
    {
        Auth::requireParent();

        View::page('parent/booking-form', [
            'title'       => 'Buchung erfassen',
            'children'    => Users::children(),
            'balances'    => Ledger::balances(),
            'presetChild' => param_int('kind'),
            'presetKind'  => param('art', 'payout'),
        ]);
    }

    public static function save(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('buchung');
        }
        Csrf::check();

        $childId = param_int('child_id');
        $kind    = param('kind');
        $amount  = Money::parse(param('amount'));
        $note    = trim(param('description'));

        $child = Users::find($childId);
        if (!$child || $child['role'] !== 'child') {
            Flash::error('Bitte ein Kind auswählen.');
            redirect('buchung');
        }
        if ($amount === null || $amount <= 0) {
            Flash::error('Bitte einen Betrag größer als 0 € eingeben.');
            redirect('buchung', ['kind' => $childId, 'art' => $kind]);
        }

        [$category, $signedAmount, $defaultText] = match ($kind) {
            'bonus'      => ['bonus', $amount, 'Bonus'],
            'charge'     => ['correction', -$amount, 'Abzug'],
            'correction' => ['correction', $amount, 'Korrektur'],
            default      => ['payout', -$amount, 'Auszahlung'],
        };

        $balance = Ledger::balance($childId);
        if ($signedAmount < 0 && $balance + $signedAmount < 0 && param('confirm_negative') !== '1') {
            Flash::error(
                'Das Konto von ' . $child['name'] . ' würde dadurch ins Minus rutschen ('
                . Money::format($balance + $signedAmount) . '). Bitte den Haken zum Bestätigen setzen.'
            );
            redirect('buchung', ['kind' => $childId, 'art' => $kind]);
        }

        Ledger::book(
            $childId,
            $signedAmount,
            $note !== '' ? mb_substr($note, 0, 120) : $defaultText,
            $category,
            'manual',
            null,
            (int)$me['id']
        );

        $neuerStand = Ledger::balance($childId);

        Flash::success(
            Money::format($signedAmount, true) . ' für ' . $child['name'] . ' gebucht. '
            . 'Neuer Kontostand: ' . Money::format($neuerStand) . '.'
        );

        Push::toUser($childId, [
            'title' => ($signedAmount >= 0 ? '💰 ' : '💸 ')
                     . ($note !== '' ? mb_substr($note, 0, 60) : $defaultText),
            'body'  => Money::format($signedAmount, true)
                     . ' · neuer Kontostand: ' . Money::format($neuerStand) . '.',
            'url'   => url('kind-konto'),
            'tag'   => 'buchung',
        ]);

        redirect('kind-detail', ['id' => $childId]);
    }

    /** Gesamter Verlauf mit Filter nach Kind und Monat. */
    public static function history(): void
    {
        Auth::requireParent();

        $childId = param_int('kind');
        $month   = param('monat', current_month());
        if ($month !== 'alle' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = current_month();
        }

        View::page('parent/history', [
            'title'     => 'Verlauf',
            'entries'   => Ledger::recent($childId ?: null, $month === 'alle' ? null : $month, 300),
            'children'  => Users::children(),
            'childId'   => $childId,
            'month'     => $month,
            'months'    => Ledger::availableMonths(),
            'decisions' => Completions::decided(40),
        ]);
    }
}
