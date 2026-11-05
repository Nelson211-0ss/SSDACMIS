<?php
namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;

/**
 * JSON endpoints behind the session-timeout dialog (assets/js/session-timeout.js).
 *
 *   GET /session/status  how long the session has left, WITHOUT extending it
 *                        (App::enforceIdleTimeout() skips this path).
 *   POST /session/ping   "Stay signed in": any normal request already
 *                        refreshes the idle timer, so this just reports back.
 */
class SessionController extends Controller
{
    public function status(): string
    {
        return $this->report();
    }

    public function ping(): string
    {
        return $this->report();
    }

    private function report(): string
    {
        $lifetime = (int) App::config('session.lifetime');
        $signedIn = !empty($_SESSION['users']) || !empty($_SESSION['user']);
        $last     = (int) ($_SESSION['_last_activity'] ?? time());
        $remaining = $signedIn ? max(0, $lifetime - (time() - $last)) : 0;

        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        return json_encode([
            'authenticated' => $signedIn,
            'remaining'     => $remaining,
            'lifetime'      => $lifetime,
        ]);
    }
}
