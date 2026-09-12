<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class ExpenseController
{
    public const EMOJIS = ['💳','🏋️','📱','🎮','🎵','⚽','🎨','🚌','📖','🍿','🐴','🎹','🏊','🥋','💈','🎬'];

    public static function index(): void
    {
        Auth::requireParent();

        $expenses = Expenses::all();
        $grouped  = [];
        foreach ($expenses as $expense) {
            $grouped[(int)$expense['child_id']][] = $expense;
        }

        View::page('parent/expenses', [
            'title'    => 'Feste Ausgaben',
            'grouped'  => $grouped,
            'children' => Users::children(),
            'totals'   => Expenses::monthlyTotals(),
            'balances' => Ledger::balances(),
        ]);
    }

    public static function form(): void
    {
        Auth::requireParent();

        $id      = param_int('id');
        $expense = $id > 0 ? Expenses::find($id) : null;

        if ($id > 0 && !$expense) {
            Flash::error('Diese Ausgabe gibt es nicht.');
            redirect('ausgaben');
        }

        View::page('parent/expense-form', [
            'title'       => $expense ? 'Ausgabe bearbeiten' : 'Neue feste Ausgabe',
            'expense'     => $expense,
            'children'    => Users::children(),
            'emojis'      => self::EMOJIS,
            'presetChild' => param_int('kind'),
        ]);
    }

    public static function save(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('ausgaben');
        }
        Csrf::check();

        $id      = param_int('id');
        $childId = param_int('child_id');
        $title   = param('title');
        $amount  = Money::parse(param('amount'));
        $day     = max(1, min(28, param_int('day_of_month', 1)));
        $back    = $id > 0 ? ['id' => $id] : [];

        $child = Users::find($childId);
        if (!$child || $child['role'] !== 'child') {
            Flash::error('Bitte ein Kind auswählen.');
            redirect('ausgabe-form', $back);
        }
        if ($title === '') {
            Flash::error('Bitte einen Namen für die Ausgabe eingeben, zum Beispiel „Beitrag Fitnessstudio“.');
            redirect('ausgabe-form', $back);
        }
        if ($amount === null || $amount <= 0) {
            Flash::error('Bitte einen Betrag größer als 0 € eingeben.');
            redirect('ausgabe-form', $back);
        }

        $endMonth = param('end_month');
        $endMonth = preg_match('/^\d{4}-\d{2}$/', $endMonth) ? $endMonth : null;

        $data = [
            'child_id'     => $childId,
            'title'        => mb_substr($title, 0, 80),
            'emoji'        => TaskController::sanitizeEmoji(param('emoji'), '💳'),
            'amount_cents' => $amount,
            'day_of_month' => $day,
            'end_month'    => $endMonth,
            'created_by'   => (int)$me['id'],
        ];

        if ($id > 0) {
            Expenses::update($id, $data);
            Flash::success('Die Ausgabe wurde gespeichert.');
        } else {
            $startMonth = param('start_month');
            $data['start_month'] = preg_match('/^\d{4}-\d{2}$/', $startMonth) ? $startMonth : current_month();
            Expenses::create($data);
            Flash::success(
                '„' . $data['title'] . '“ wird ab ' . month_label($data['start_month'])
                . ' jeden Monat am ' . $day . '. vom Konto abgebucht.'
            );
        }

        // Faellige Abbuchungen sofort nachziehen, damit der Betrag direkt sichtbar ist.
        Billing::run(true);
        redirect('ausgaben');
    }

    public static function action(): void
    {
        Auth::requireParent();
        if (!is_post()) {
            redirect('ausgaben');
        }
        Csrf::check();

        $id      = param_int('id');
        $expense = Expenses::find($id);
        if (!$expense) {
            Flash::error('Diese Ausgabe gibt es nicht.');
            redirect('ausgaben');
        }

        switch (param('action')) {
            case 'pause':
                Expenses::setActive($id, false);
                Flash::info('„' . $expense['title'] . '“ wird ab sofort nicht mehr abgebucht.');
                break;

            case 'activate':
                Expenses::setActive($id, true);
                Billing::run(true);
                Flash::success('„' . $expense['title'] . '“ wird wieder monatlich abgebucht.');
                break;

            case 'delete':
                Expenses::delete($id);
                Flash::info('Die Ausgabe wurde entfernt. Bereits erfolgte Abbuchungen bleiben im Verlauf stehen.');
                break;
        }

        redirect('ausgaben');
    }
}
