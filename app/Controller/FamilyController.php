<?php
declare(strict_types=1);

defined('KINDERARBEIT') || exit;

final class FamilyController
{
    public const COLORS = ['#e8590c','#1c7ed6','#2f9e44','#c2255c','#5f3dc4','#0b7285','#e67700','#495057'];
    public const EMOJIS = ['🦊','🦁','🐻','🐼','🐨','🐯','🦄','🐙','🌷','⚓','🌟','🍀','🚀','🎈','🐢','🦉'];

    /** Profile, PINs und Sperren verwalten. */
    public static function index(): void
    {
        Auth::requireParent();

        $highlight = (int)($_SESSION['highlight_link'] ?? 0);
        unset($_SESSION['highlight_link']);

        View::page('parent/family', [
            'title'     => 'Familie',
            'users'     => Users::all(),
            'colors'    => self::COLORS,
            'emojis'    => self::EMOJIS,
            'highlight' => $highlight,
        ]);
    }

    public static function action(): void
    {
        $me = Auth::requireParent();
        if (!is_post()) {
            redirect('familie');
        }
        Csrf::check();

        $id   = param_int('id');
        $user = Users::find($id);
        if (!$user) {
            Flash::error('Dieses Profil gibt es nicht.');
            redirect('familie');
        }

        switch (param('action')) {
            case 'reset-pin':
                // Eltern duerfen die PIN der Kinder neu setzen; untereinander nicht.
                if ($user['role'] === 'parent' && (int)$user['id'] !== (int)$me['id']) {
                    Flash::error('Die PIN des anderen Elternteils kann nur dieses selbst ändern.');
                    break;
                }
                $pin   = preg_replace('/\D/', '', param('new_pin'));
                $error = null;
                if (!Auth::validatePin($pin, $error)) {
                    Flash::error($error);
                    break;
                }
                Users::setPin($id, $pin, (int)$user['id'] !== (int)$me['id']);
                Flash::success(
                    'Neue PIN für ' . $user['name'] . ' gespeichert'
                    . ((int)$user['id'] !== (int)$me['id'] ? ' – sie muss beim nächsten Anmelden geändert werden.' : '.')
                );
                break;

            case 'unlock':
                Users::unlock($id);
                Flash::success('Die Sperre für ' . $user['name'] . ' ist aufgehoben.');
                break;

            case 'create-link':
                $token = Users::createToken($id);
                Flash::success(
                    'Neuer Zugangslink für ' . $user['name'] . ' erzeugt'
                    . (!empty($user['access_token']) ? ' – der bisherige gilt nicht mehr.' : '.')
                );
                $_SESSION['highlight_link'] = $id;
                break;

            case 'clear-link':
                Users::clearToken($id);
                Flash::info('Der Zugangslink von ' . $user['name'] . ' wurde zurückgezogen.');
                break;

            case 'profile':
                Users::updateProfile(
                    $id,
                    TaskController::sanitizeEmoji(param('emoji'), '🙂'),
                    preg_match('/^#[0-9a-fA-F]{6}$/', param('color')) ? param('color') : (string)$user['color']
                );
                Flash::success('Das Profil von ' . $user['name'] . ' wurde aktualisiert.');
                break;
        }

        redirect('familie');
    }
}
