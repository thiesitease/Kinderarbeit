<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class ParentController
{
    /** Elternstart: Wiedervorlage der gemeldeten Aufgaben plus Kontouebersicht. */
    public static function dashboard(): void
    {
        Auth::requireParent();
        if (Auth::mustChangePin()) {
            redirect('pin');
        }

        $month     = current_month();
        $children  = Users::children();
        $balances  = Ledger::balances();
        $expenses  = Expenses::monthlyTotals();

        $overview = [];
        foreach ($children as $child) {
            $childId = (int)$child['id'];
            $summary = Ledger::monthSummary($childId, $month);
            $overview[] = [
                'child'          => $child,
                'balance'        => $balances[$childId] ?? 0,
                'pendingAmount'  => Completions::pendingAmount($childId),
                'pendingCount'   => Completions::pendingCount($childId),
                'monthlyExpense' => $expenses[$childId] ?? 0,
                'summary'        => $summary,
            ];
        }

        View::page('parent/dashboard', [
            'title'          => 'Übersicht',
            'pending'        => Completions::pending(),
            'overview'       => $overview,
            'month'          => $month,
            'daysLeft'       => days_left_in_month(),
            'defaultPinUsers'=> Users::withDefaultPin(),
            'recent'         => Completions::decided(8),
        ]);
    }

    /** Bestaetigen, ablehnen oder eine Bestaetigung zuruecknehmen. */
    public static function decide(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('eltern');
        }
        Csrf::check();

        $id     = param_int('id');
        $action = param('action');
        $note   = param('note');
        $back   = param('back', 'eltern');
        $backId = param_int('back_id');
        $params = $backId > 0 ? ['id' => $backId] : [];

        $completion = Completions::find($id);
        if (!$completion) {
            Flash::error('Diese Meldung gibt es nicht mehr.');
            redirect($back, $params);
        }

        switch ($action) {
            case 'approve':
                if (Completions::approve($id, (int)$me['id'], $note)) {
                    Flash::success(
                        'Bestätigt: ' . Money::format((int)$completion['amount_cents'])
                        . ' für ' . $completion['child_name'] . ' gutgeschrieben.'
                    );
                } else {
                    Flash::info('Diese Meldung wurde bereits bearbeitet.');
                }
                break;

            case 'reject':
                if (Completions::reject($id, (int)$me['id'], $note)) {
                    Flash::info('Abgelehnt – es wurde nichts gutgeschrieben.');
                } else {
                    Flash::info('Diese Meldung wurde bereits bearbeitet.');
                }
                break;

            case 'revoke':
                if (Completions::revoke($id, (int)$me['id'], $note)) {
                    Flash::info(
                        'Bestätigung zurückgenommen – '
                        . Money::format((int)$completion['amount_cents']) . ' wurden wieder abgezogen.'
                    );
                } else {
                    Flash::error('Diese Bestätigung lässt sich nicht mehr zurücknehmen.');
                }
                break;

            default:
                Flash::error('Unbekannte Aktion.');
        }

        redirect($back, $params);
    }

    /** Alle Kinder im Vergleich. */
    public static function childrenOverview(): void
    {
        Auth::requireParent();

        $month    = self::selectedMonth();
        $balances = Ledger::balances();
        $expenses = Expenses::monthlyTotals();

        $rows = [];
        foreach (Users::children() as $child) {
            $childId = (int)$child['id'];
            $rows[] = [
                'child'          => $child,
                'balance'        => $balances[$childId] ?? 0,
                'summary'        => Ledger::monthSummary($childId, $month),
                'monthlyExpense' => $expenses[$childId] ?? 0,
                'pendingCount'   => Completions::pendingCount($childId),
                'pendingAmount'  => Completions::pendingAmount($childId),
                'approvedCount'  => Completions::approvedCountForMonth($childId, $month),
            ];
        }

        View::page('parent/children', [
            'title'  => 'Kinder',
            'rows'   => $rows,
            'month'  => $month,
            'months' => Ledger::availableMonths(),
        ]);
    }

    /** Detailseite eines Kindes mit Konto, Aufgaben und festen Ausgaben. */
    public static function childDetail(): void
    {
        Auth::requireParent();

        $childId = param_int('id');
        $child   = Users::find($childId);
        if (!$child || $child['role'] !== 'child') {
            Flash::error('Dieses Kind gibt es nicht.');
            redirect('kinder');
        }

        $month = self::selectedMonth();

        View::page('parent/child-detail', [
            'title'         => $child['name'],
            'child'         => $child,
            'balance'       => Ledger::balance($childId),
            'pendingAmount' => Completions::pendingAmount($childId),
            'pending'       => Completions::pending($childId),
            'summary'       => Ledger::monthSummary($childId, $month),
            'entries'       => Ledger::forChild($childId, $month, 200),
            'expenses'      => Expenses::all($childId),
            'month'         => $month,
            'months'        => Ledger::availableMonths(),
            'daysLeft'      => days_left_in_month(),
        ]);
    }

    private static function selectedMonth(): string
    {
        $month = param('monat', current_month());
        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : current_month();
    }
}
