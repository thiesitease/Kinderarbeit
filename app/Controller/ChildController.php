<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class ChildController
{
    /** Startseite eines Kindes: Kontostand und offene Aufgaben. */
    public static function dashboard(): void
    {
        $me = Auth::requireChild();
        if (Auth::mustChangePin()) {
            redirect('pin');
        }

        $childId = (int)$me['id'];
        $month   = current_month();

        View::page('child/dashboard', [
            'title'          => 'Meine Aufgaben',
            'tasks'          => Tasks::forChild($childId),
            'balance'        => Ledger::balance($childId),
            'pendingAmount'  => Completions::pendingAmount($childId),
            'pending'        => Completions::forChild($childId, 'pending', 20),
            'summary'        => Ledger::monthSummary($childId, $month),
            'expenses'       => Expenses::all($childId, true),
            'month'          => $month,
            'daysLeft'       => days_left_in_month(),
            'reachableParents' => Users::parentsWithPhone(),
        ]);
    }

    /** Kind meldet eine Aufgabe als erledigt. */
    public static function complete(): void
    {
        $me = Auth::requireChild();
        if (!is_post()) {
            redirect('kind');
        }
        Csrf::check();

        $taskId = param_int('task');
        $task   = Tasks::find($taskId);

        if (!$task || (int)$task['is_active'] !== 1) {
            Flash::error('Diese Aufgabe gibt es nicht mehr.');
            redirect('kind');
        }
        if ($task['assigned_to'] !== null && (int)$task['assigned_to'] !== (int)$me['id']) {
            Flash::error('Diese Aufgabe ist für jemand anderen gedacht.');
            redirect('kind');
        }

        // Dieselbe Aufgabe nicht zweimal gleichzeitig einreichen.
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM completions WHERE task_id = :task AND child_id = :child AND status = 'pending'"
        );
        $stmt->execute(['task' => $taskId, 'child' => (int)$me['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            Flash::info('Diese Aufgabe wartet schon auf die Bestätigung.');
            redirect('kind');
        }

        $sterne = max(0, min(3, param_int('sterne')));
        Completions::submit($task, (int)$me['id'], param('note'), $sterne);

        Push::toParents([
            'title' => '⏳ ' . $me['name'] . ' hat etwas erledigt',
            'body'  => $task['title'] . ' · ' . Money::format((int)$task['amount_cents'])
                     . ($sterne > 0 ? ' · ' . str_repeat('⭐', $sterne) . ' war schwer' : '')
                     . ' – bitte bestätigen.',
            'url'   => url('eltern'),
            'tag'   => 'wiedervorlage',
        ]);

        Flash::success(
            'Super! „' . $task['title'] . '“ wartet jetzt auf die Bestätigung von Mama oder Papa.'
            . ($sterne > 0 ? ' Dass es schwer war, steht dabei.' : '')
        );
        redirect('kind');
    }

    /** Kontoansicht mit Monatsuebersicht. */
    public static function account(): void
    {
        $me = Auth::requireChild();
        if (Auth::mustChangePin()) {
            redirect('pin');
        }

        $childId = (int)$me['id'];
        $month   = self::selectedMonth();

        View::page('child/account', [
            'title'         => 'Mein Konto',
            'balance'       => Ledger::balance($childId),
            'pendingAmount' => Completions::pendingAmount($childId),
            'summary'       => Ledger::monthSummary($childId, $month),
            'entries'       => Ledger::forChild($childId, $month, 200),
            'expenses'      => Expenses::all($childId, true),
            'month'         => $month,
            'months'        => Ledger::availableMonths(),
            'daysLeft'      => days_left_in_month(),
        ]);
    }

    /** Verlauf aller gemeldeten Aufgaben. */
    public static function history(): void
    {
        $me = Auth::requireChild();
        if (Auth::mustChangePin()) {
            redirect('pin');
        }

        View::page('child/history', [
            'title'       => 'Mein Verlauf',
            'completions' => Completions::forChild((int)$me['id'], null, 150),
        ]);
    }

    /** Monat aus der URL, sonst der laufende Monat. */
    private static function selectedMonth(): string
    {
        $month = param('monat', current_month());
        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : current_month();
    }
}
