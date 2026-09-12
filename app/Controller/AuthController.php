<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class AuthController
{
    /** Profilauswahl und PIN-Eingabe. */
    public static function login(): void
    {
        if (Auth::check() && !is_post()) {
            redirect('start');
        }

        $selectedId = param_int('user');
        $selected   = $selectedId > 0 ? Users::find($selectedId) : null;
        $error      = null;

        if (is_post()) {
            Csrf::check();
            $selectedId = param_int('user');
            $selected   = $selectedId > 0 ? Users::find($selectedId) : null;
            $pin        = preg_replace('/\D/', '', param('pin'));

            if (!$selected) {
                $error = 'Bitte zuerst ein Profil auswählen.';
            } elseif ($pin === '') {
                $error = 'Bitte die PIN eingeben.';
            } elseif (Auth::attempt($selectedId, $pin, $error)) {
                $intended = $_SESSION['intended'] ?? null;
                unset($_SESSION['intended']);

                if (Auth::mustChangePin()) {
                    Flash::info('Willkommen! Bitte wähle zuerst eine eigene PIN.');
                    redirect('pin');
                }
                Flash::success('Hallo ' . $selected['name'] . '!');
                if (is_string($intended) && str_starts_with($intended, '/')) {
                    redirect_url($intended);
                }
                redirect('start');
            }
        }

        View::page('login', [
            'users'    => Users::all(),
            'selected' => $selected,
            'error'    => $error,
            'bare'     => true,
            'title'    => 'Anmelden',
        ]);
    }

    public static function logout(): void
    {
        if (is_post()) {
            Csrf::check();
        }
        Auth::logout();
        app_start_session();
        Flash::info('Du bist abgemeldet. Bis bald!');
        redirect('login');
    }

    /** Eigene PIN aendern (und Pflichtwechsel bei der Start-PIN). */
    public static function changePin(): void
    {
        $me    = Auth::requireLogin();
        $error = null;
        $force = Auth::mustChangePin();

        if (is_post()) {
            Csrf::check();
            $current = preg_replace('/\D/', '', param('current_pin'));
            $new     = preg_replace('/\D/', '', param('new_pin'));
            $repeat  = preg_replace('/\D/', '', param('repeat_pin'));

            if (!password_verify($current, (string)$me['pin_hash'])) {
                $error = 'Die aktuelle PIN stimmt nicht.';
            } elseif ($new !== $repeat) {
                $error = 'Die beiden neuen PINs sind nicht gleich.';
            } elseif (!Auth::validatePin($new, $error)) {
                // $error wurde von validatePin gesetzt
            } elseif ($new === $current) {
                $error = 'Bitte eine andere PIN als bisher wählen.';
            } else {
                Users::setPin((int)$me['id'], $new, false);
                Flash::success('Deine neue PIN ist gespeichert.');
                redirect('start');
            }
        }

        View::page('pin', [
            'error' => $error,
            'force' => $force,
            'title' => 'PIN ändern',
            'bare'  => $force,
        ]);
    }
}
