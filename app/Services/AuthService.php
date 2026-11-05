<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\RmUserProfile;
use App\Services\Rm\{RmAuthClient, RmException};

final class AuthService
{
    public function __construct(private ?RmAuthClient $client = null)
    {
        $this->client ??= new RmAuthClient();
    }

    public function attempt(string $username, string $password): bool
    {
        $result = $this->client->authenticate($username, $password);
        $user = $result['user'] ?? [];

        if (empty($user['code'])) {
            return false;
        }

        Session::regenerate();
        Session::put('user', $user);
        // Um novo login sempre inicia um novo contexto; nunca reaproveita o
        // contexto escolhido por outra conta que tenha usado o mesmo navegador.
        Session::forget('active_context');
        try {
            $profileModel = new RmUserProfile();
            $profile = $profileModel->findByLogin((string) $user['code']);
            if (is_array($profile)) {
                $user['name'] = $profile['name'] ?: ($user['name'] ?? $user['code']);
                $user['email'] = $profile['email'] ?? '';
                $user['avatar_data_uri'] = $profile['avatar_data_uri'] ?? '';
                Session::put('user_contexts', $profile['contexts'] ?? []);
            } else {
                Session::put('user_contexts', []);
            }

            $defaultContext = $profileModel->findDefaultContextByActiveEmployment((string) $user['code']);
            if ($defaultContext !== null) {
                Session::put('active_context', $defaultContext);
                $profiles = $profileModel->findProfiles((string) $user['code'], (int) $defaultContext['CODCOLIGADA']);
                $roleNames = array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['name'] ?? ''), $profiles)));
                $user['role'] = $roleNames ? implode(' / ', $roleNames) : '';
            }
            Session::put('user', $user);
        } catch (\Throwable $e) {
            // A autenticação RM já foi validada. Falha na consulta de perfil/contexto
            // não deve impedir o acesso; a tela continuará disponível sem esses dados.
            error_log('[AuthService] Não foi possível carregar perfil/contexto do RM: ' . $e->getMessage());
        }

        if (!empty($result['token'])) {
            Session::put('rm_token', $result['token']);
            Session::put('rm_token_expires', time() + max(0, (int) ($result['expires'] ?? 0)));
        }

        if (!empty($result['refresh_token'])) {
            Session::put('rm_refresh_token', $result['refresh_token']);
        }

        return true;
    }
}
