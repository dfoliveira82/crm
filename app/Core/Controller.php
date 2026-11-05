<?php
declare(strict_types=1);
namespace App\Core;

use App\Models\RmUserProfile;
use Throwable;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): Response
    {
        if ($layout === 'main') {
            $this->ensureGlobalUserContext();
        }

        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === '') {
            return Response::html($content);
        }

        ob_start();
        require dirname(__DIR__) . "/Views/layouts/{$layout}.php";
        return Response::html((string) ob_get_clean());
    }

    /**
     * Garante que o menu global tenha os dados oficiais do usuário e os
     * contextos autorizados, inclusive em sessões criadas antes desta versão.
     */
    private function ensureGlobalUserContext(): void
    {
        $user = Session::get('user', []);
        if (!is_array($user)) {
            return;
        }

        $login = trim((string) ($user['code'] ?? $user['codusuario'] ?? $user['username'] ?? ''));
        if ($login === '') {
            return;
        }

        $contexts = Session::get('user_contexts');
        $activeContext = Session::get('active_context', []);
        $needsBootstrap = !is_array($contexts) || !is_array($activeContext) || !$activeContext;
        if (!$needsBootstrap) {
            return;
        }

        try {
            $model = new RmUserProfile();
            $profile = null;
            if (!is_array($contexts)) {
                $profile = $model->findByLogin($login);
                $contexts = is_array($profile['contexts'] ?? null) ? $profile['contexts'] : [];
                Session::put('user_contexts', $contexts);
            }

            if ($profile === null && (!isset($user['name']) || !isset($user['email']) || !isset($user['avatar_data_uri']))) {
                $profile = $model->findByLogin($login);
            }
            if (is_array($profile)) {
                $user['name'] = $profile['name'] ?: ($user['name'] ?? $login);
                $user['email'] = $profile['email'] ?? ($user['email'] ?? '');
                $user['avatar_data_uri'] = $profile['avatar_data_uri'] ?? ($user['avatar_data_uri'] ?? '');
            }

            if (!is_array($activeContext) || !$activeContext) {
                $activeContext = $model->findDefaultContextByActiveEmployment($login) ?? [];
                if ($activeContext) {
                    Session::put('active_context', $activeContext);
                }
            }

            if (is_array($activeContext) && $activeContext) {
                $allowed = false;
                foreach ((array) $contexts as $context) {
                    if ((int) ($context['CODCOLIGADA'] ?? -1) === (int) ($activeContext['CODCOLIGADA'] ?? -2)
                        && (int) ($context['CODFILIAL'] ?? -1) === (int) ($activeContext['CODFILIAL'] ?? -2)
                        && (int) ($context['CODTIPOCURSO'] ?? -1) === (int) ($activeContext['CODTIPOCURSO'] ?? -2)) {
                        $activeContext = $context;
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    $activeContext = $model->findDefaultContextByActiveEmployment($login) ?? [];
                    Session::put('active_context', $activeContext);
                }
                if ($activeContext) {
                    $profiles = $model->findProfiles($login, (int) ($activeContext['CODCOLIGADA'] ?? 0));
                    $names = array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['name'] ?? ''), $profiles)));
                    $user['role'] = $names ? implode(' / ', $names) : ($user['role'] ?? '');
                }
            }

            Session::put('user', $user);
        } catch (Throwable $e) {
            error_log('[Controller] Não foi possível preparar o contexto global do usuário: ' . $e->getMessage());
        }
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}
