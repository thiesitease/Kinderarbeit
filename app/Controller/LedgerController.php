<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class LedgerController
{
    /** Formular fuer eine Auszahlung, einen Bonus oder eine Korrektur – neu oder zum Bearbeiten. */
    public static function form(): void
    {
        Auth::requireParent();

        $id    = param_int('id');
        $entry = $id > 0 ? Ledger::find($id) : null;
        if ($id > 0 && !$entry) {
            Flash::error('Diese Buchung gibt es nicht mehr.');
            self::goBack(0);
        }

        $presetChild = $entry ? (int)$entry['child_id'] : param_int('kind');
        [$backPage, $backParams] = self::backTarget($presetChild);

        View::page('parent/booking-form', [
            'title'       => $entry ? 'Buchung bearbeiten' : 'Buchung erfassen',
            'entry'       => $entry,
            'children'    => Users::children(),
            'balances'    => Ledger::balances(),
            'presetChild' => $presetChild,
            'presetKind'  => $entry ? Ledger::kindOf($entry) : param('art', 'payout'),
            'back'        => self::backParams(),
            'cancel'      => url($backPage, $backParams),
        ]);
    }

    /** Eine neue Buchung anlegen oder eine bestehende aendern. */
    public static function save(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('buchung');
        }
        Csrf::check();

        $id    = param_int('id');
        $entry = $id > 0 ? Ledger::find($id) : null;
        if ($id > 0 && !$entry) {
            Flash::error('Diese Buchung gibt es nicht mehr.');
            self::goBack(0);
        }

        // Gutschriften aus Aufgaben und feste Ausgaben haengen an einem anderen
        // Datensatz: Kind und Art bleiben, geaendert werden nur Betrag, Datum und Text.
        $free     = $entry === null || Ledger::isManual($entry);
        $childId  = $free ? param_int('child_id') : (int)$entry['child_id'];
        $kind     = $free ? param('kind') : Ledger::kindOf($entry);
        $amount   = Money::parse(param('amount'));
        $note     = trim(param('description'));
        $bookedAt = self::bookedAt($entry);

        $child = Users::find($childId);
        if (!$child || $child['role'] !== 'child') {
            Flash::error('Bitte ein Kind auswählen.');
            self::backToForm($id, $childId, $kind);
        }
        if ($amount === null || $amount <= 0) {
            Flash::error('Bitte einen Betrag größer als 0 € eingeben.');
            self::backToForm($id, $childId, $kind);
        }

        [$category, $signedAmount, $defaultText] = match ($kind) {
            'bonus'      => ['bonus', $amount, 'Bonus'],
            'charge'     => ['correction', -$amount, 'Abzug'],
            'correction' => ['correction', $amount, 'Korrektur'],
            default      => ['payout', -$amount, 'Auszahlung'],
        };

        if (!$free) {
            $category     = $entry['category'];
            $signedAmount = (int)$entry['amount_cents'] < 0 ? -$amount : $amount;
            $defaultText  = $entry['description'];
        }

        // Kontostand vorher und nachher; beim Bearbeiten zaehlt der bisherige
        // Betrag nicht mehr mit. Nachgefragt wird nur, wenn es schlechter wird.
        $before = Ledger::balance($childId);
        $after  = $before + $signedAmount;
        if ($entry !== null && (int)$entry['child_id'] === $childId) {
            $after -= (int)$entry['amount_cents'];
        }
        if ($after < 0 && $after < $before && param('confirm_negative') !== '1') {
            Flash::error(
                'Das Konto von ' . $child['name'] . ' würde dadurch ins Minus rutschen ('
                . Money::format($after) . '). Bitte den Haken zum Bestätigen setzen.'
            );
            self::backToForm($id, $childId, $kind);
        }

        $description = $note !== '' ? mb_substr($note, 0, 120) : $defaultText;

        if ($entry !== null) {
            if (!Ledger::update($id, [
                'child_id'     => $childId,
                'amount_cents' => $signedAmount,
                'description'  => $description,
                'category'     => $category,
                'booked_at'    => $bookedAt,
            ], (int)$me['id'])) {
                Flash::error('Diese Buchung gibt es nicht mehr.');
                self::goBack($childId);
            }

            Flash::success(
                'Die Buchung wurde geändert: ' . Money::format($signedAmount, true) . ' für ' . $child['name'] . '. '
                . 'Neuer Kontostand: ' . Money::format(Ledger::balance($childId)) . '.'
            );
        } else {
            Ledger::book(
                $childId,
                $signedAmount,
                $description,
                $category,
                'manual',
                null,
                (int)$me['id'],
                $bookedAt
            );

            Flash::success(
                Money::format($signedAmount, true) . ' für ' . $child['name'] . ' gebucht. '
                . 'Neuer Kontostand: ' . Money::format(Ledger::balance($childId)) . '.'
            );
        }

        self::goBack($childId);
    }

    /** Eine Buchung loeschen. */
    public static function action(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('verlauf');
        }
        Csrf::check();

        $entry = Ledger::find(param_int('id'));
        if (!$entry) {
            Flash::error('Diese Buchung gibt es nicht mehr.');
            self::goBack(0);
        }

        $childId = (int)$entry['child_id'];
        if (param('action') !== 'delete') {
            Flash::error('Unbekannte Aktion.');
            self::goBack($childId);
        }
        if (!Ledger::delete((int)$entry['id'], (int)$me['id'])) {
            Flash::error('Diese Buchung lässt sich nicht mehr löschen.');
            self::goBack($childId);
        }

        $hint = match (true) {
            $entry['ref_type'] === 'completion' && $entry['category'] === 'task'
                => ' Die gemeldete Aufgabe gilt jetzt als abgelehnt.',
            $entry['ref_type'] === 'completion'
                => ' Die Bestätigung der Aufgabe gilt wieder.',
            $entry['ref_type'] === 'expense'
                => ' Die feste Ausgabe wird für ' . month_label((string)$entry['booked_month']) . ' nicht erneut abgebucht.',
            default => '',
        };

        Flash::info(
            '„' . $entry['description'] . '“ (' . Money::format((int)$entry['amount_cents'], true) . ') gelöscht.'
            . $hint . ' Kontostand von ' . $entry['child_name'] . ': ' . Money::format(Ledger::balance($childId)) . '.'
        );
        self::goBack($childId);
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

    /**
     * Buchungsdatum aus dem Formular. Die Uhrzeit bleibt erhalten, damit die
     * Reihenfolge mehrerer Buchungen an einem Tag stimmt.
     */
    private static function bookedAt(?array $entry): string
    {
        $previous = (string)($entry['booked_at'] ?? now());
        $date     = param('datum');

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            return $previous;
        }

        $time = substr($previous, 11, 8);
        return $date . ' ' . ($time !== '' ? $time : '12:00:00');
    }

    /** Woher der Aufruf kam – Verlauf mit Filter oder Seite eines Kindes. */
    private static function backParams(): array
    {
        $params = [];

        if (in_array(param('back'), ['verlauf', 'kind-detail'], true)) {
            $params['back'] = param('back');
        }
        if (param_int('back_id') > 0) {
            $params['back_id'] = param_int('back_id');
        }
        if (param_int('back_kind') > 0) {
            $params['back_kind'] = param_int('back_kind');
        }

        $month = param('back_monat');
        if ($month === 'alle' || preg_match('/^\d{4}-\d{2}$/', $month)) {
            $params['back_monat'] = $month;
        }

        return $params;
    }

    /** Zielseite fuer „Abbrechen“ und nach dem Speichern: [Seite, Parameter]. */
    private static function backTarget(int $childId): array
    {
        $back  = self::backParams();
        $page  = $back['back'] ?? ($childId > 0 ? 'kind-detail' : '');
        $month = $back['back_monat'] ?? null;

        if ($page === 'kind-detail') {
            $id = (int)($back['back_id'] ?? $childId);
            if ($id > 0) {
                $params = ['id' => $id];
                if ($month !== null && $month !== 'alle') {
                    $params['monat'] = $month;
                }
                return ['kind-detail', $params];
            }
        }
        if ($page === '') {
            return ['eltern', []];
        }

        $params = [];
        if (isset($back['back_kind'])) {
            $params['kind'] = $back['back_kind'];
        }
        if ($month !== null) {
            $params['monat'] = $month;
        }
        return ['verlauf', $params];
    }

    /** Zurueck zu der Seite, von der aus die Buchung aufgerufen wurde. */
    private static function goBack(int $childId): never
    {
        [$page, $params] = self::backTarget($childId);
        redirect($page, $params);
    }

    /** Zurueck ins Formular, wenn eine Eingabe nicht stimmt. */
    private static function backToForm(int $id, int $childId, string $kind): never
    {
        $params = self::backParams();

        if ($id > 0) {
            $params['id'] = $id;
        } else {
            if ($childId > 0) {
                $params['kind'] = $childId;
            }
            $params['art'] = $kind;
        }

        redirect('buchung', $params);
    }
}
