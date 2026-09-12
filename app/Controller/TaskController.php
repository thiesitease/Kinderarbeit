<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class TaskController
{
    /** Beliebte Symbole fuer neue Aufgaben. */
    public const EMOJIS = ['🌱','🐕','🍽️','🧹','🧺','🛏️','🗑️','🪟','🚗','🧽','📚','🛒','🌻','🧊','🚲','🪴','👕','🧼','❄️','⭐'];

    public static function index(): void
    {
        Auth::requireParent();

        View::page('parent/tasks', [
            'title'    => 'Aufgaben',
            'tasks'    => Tasks::all(),
            'children' => Users::children(),
        ]);
    }

    /** Formular zum Anlegen oder Bearbeiten. */
    public static function form(): void
    {
        Auth::requireParent();

        $id   = param_int('id');
        $task = $id > 0 ? Tasks::find($id) : null;

        if ($id > 0 && !$task) {
            Flash::error('Diese Aufgabe gibt es nicht.');
            redirect('aufgaben');
        }

        View::page('parent/task-form', [
            'title'    => $task ? 'Aufgabe bearbeiten' : 'Neue Aufgabe',
            'task'     => $task,
            'children' => Users::children(),
            'emojis'   => self::EMOJIS,
        ]);
    }

    public static function save(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('aufgaben');
        }
        Csrf::check();

        $id          = param_int('id');
        $title       = param('title');
        $amountInput = param('amount');
        $amount      = Money::parse($amountInput);
        $assignedTo  = param_int('assigned_to');
        $kind        = param('kind') === 'once' ? 'once' : 'repeatable';

        if ($title === '') {
            Flash::error('Bitte einen Namen für die Aufgabe eingeben.');
            redirect('aufgabe-form', $id > 0 ? ['id' => $id] : []);
        }
        if ($amount === null || $amount <= 0) {
            Flash::error('Bitte einen Betrag größer als 0 € eingeben, zum Beispiel 2,50.');
            redirect('aufgabe-form', $id > 0 ? ['id' => $id] : []);
        }
        if ($amount > 100000) {
            Flash::error('Der Betrag darf höchstens 1.000,00 € betragen.');
            redirect('aufgabe-form', $id > 0 ? ['id' => $id] : []);
        }

        $data = [
            'title'        => mb_substr($title, 0, 80),
            'description'  => mb_substr(param('description'), 0, 400),
            'emoji'        => self::sanitizeEmoji(param('emoji')),
            'amount_cents' => $amount,
            'kind'         => $kind,
            'assigned_to'  => $assignedTo > 0 ? $assignedTo : null,
            'created_by'   => (int)$me['id'],
        ];

        if ($id > 0) {
            Tasks::update($id, $data);
            Flash::success('Die Aufgabe wurde gespeichert.');
        } else {
            Tasks::create($data);
            Flash::success('Die Aufgabe „' . $data['title'] . '“ ist jetzt für die Kinder sichtbar.');
        }

        redirect('aufgaben');
    }

    /** Aktivieren, pausieren, loeschen oder sortieren. */
    public static function action(): void
    {
        Auth::requireParent();
        if (!is_post()) {
            redirect('aufgaben');
        }
        Csrf::check();

        $id   = param_int('id');
        $task = Tasks::find($id);
        if (!$task) {
            Flash::error('Diese Aufgabe gibt es nicht.');
            redirect('aufgaben');
        }

        switch (param('action')) {
            case 'pause':
                Tasks::setActive($id, false);
                Flash::info('„' . $task['title'] . '“ ist pausiert und für die Kinder nicht mehr sichtbar.');
                break;

            case 'activate':
                Tasks::setActive($id, true);
                Flash::success('„' . $task['title'] . '“ ist wieder sichtbar.');
                break;

            case 'delete':
                if (Tasks::delete($id)) {
                    Flash::info('Die Aufgabe wurde gelöscht.');
                } else {
                    Flash::info('Zu dieser Aufgabe gibt es schon Buchungen – sie wurde deshalb nur pausiert, damit die Historie erhalten bleibt.');
                }
                break;

            case 'up':
                Tasks::move($id, -1);
                break;

            case 'down':
                Tasks::move($id, 1);
                break;
        }

        redirect('aufgaben');
    }

    /** Nur ein einzelnes Symbol uebernehmen. */
    public static function sanitizeEmoji(string $input, string $fallback = '⭐'): string
    {
        $input = trim($input);
        if ($input === '') {
            return $fallback;
        }
        $chars = preg_split('//u', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $emoji = implode('', array_slice($chars, 0, 3));
        return mb_strlen($emoji) > 0 ? $emoji : $fallback;
    }
}
