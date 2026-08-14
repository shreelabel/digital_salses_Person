<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Auth;
use SLC\Core\Response;
use SLC\Core\Validator;

class AuthController extends Controller
{
    public function login(): void
    {
        $input = array_merge($_POST, $this->input());
        $v = new Validator($input);
        $v->required('email')->email('email')->required('password');
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        $result = Auth::attempt($input['email'], $input['password']);
        if (!$result['ok']) {
            Response::error($result['error'], 401);
            return;
        }
        Response::success(['user' => $result['user']]);
    }

    public function logout(): void
    {
        Auth::logout();

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJson = str_contains($accept, 'application/json')
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

        if (!$isJson && !empty($_SERVER['REQUEST_METHOD'])) {
            $base = \SLC\Core\Config::basePath() ?: str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));
            header('Location: ' . $base . '/login.php?logged_out=1');
            exit;
        }

        Response::success(['logged_out' => true]);
    }

    public function me(): void
    {
        $user = Auth::current();
        if (!$user) {
            Response::error('Unauthenticated.', 401);
            return;
        }
        Response::success(['user' => $user]);
    }

    public function changePassword(): void
    {
        $input = $this->input();
        $v = new Validator($input);
        $v->required('current_password')->required('new_password');
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        $uid = $this->userId();
        $result = Auth::changePassword($uid, $input['current_password'], $input['new_password']);
        if (!$result['ok']) {
            Response::error($result['error'], 422);
            return;
        }
        $this->activity('security', 'Changed password');
        Response::success(['changed' => true]);
    }
}
