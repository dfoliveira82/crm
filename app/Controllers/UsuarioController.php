<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller, Request, Response, Session};
use App\Models\RmUserProfile;
use Throwable;

final class UsuarioController extends Controller
{
    public function perfil(Request $request): Response
    {
        $sessionUser = Session::get('user', []);
        $sessionUser = is_array($sessionUser) ? $sessionUser : [];
        $login = trim((string)($sessionUser['code'] ?? $sessionUser['codusuario'] ?? $sessionUser['username'] ?? ''));
        $profile = [];
        $contexts = [];
        $profiles = [];
        $loadError = '';

        try {
            if ($login === '') throw new \RuntimeException('Não foi possível identificar o usuário autenticado.');
            $model = new RmUserProfile();
            $profile = $model->findByLogin($login) ?? [];
            if (!$profile) throw new \RuntimeException('O usuário autenticado não foi localizado no cadastro do RM.');
            $contexts = $profile['contexts'] ?? [];
            Session::put('user_contexts', $contexts);
            $activeContext = Session::get('active_context', []);
            if (!is_array($activeContext) || !$activeContext) {
                // Recuperação para sessões antigas ou quando o contexto inicial
                // não pôde ser definido durante o login.
                $activeContext = $model->findDefaultContextByActiveEmployment($login) ?? [];
                if ($activeContext) Session::put('active_context', $activeContext);
            }
            $contextIsAllowed = false;
            if ($activeContext) {
                foreach ($contexts as $allowedContext) {
                    if ((int)($allowedContext['CODCOLIGADA'] ?? -1) === (int)($activeContext['CODCOLIGADA'] ?? -2)
                        && (int)($allowedContext['CODFILIAL'] ?? -1) === (int)($activeContext['CODFILIAL'] ?? -2)
                        && (int)($allowedContext['CODTIPOCURSO'] ?? -1) === (int)($activeContext['CODTIPOCURSO'] ?? -2)) {
                        $contextIsAllowed = true;
                        $activeContext = $allowedContext;
                        break;
                    }
                }
                if (!$contextIsAllowed) {
                    $activeContext = $model->findDefaultContextByActiveEmployment($login) ?? [];
                }
            }
            if ($activeContext) {
                $profiles = $model->findProfiles($login, (int)($activeContext['CODCOLIGADA'] ?? 0));
            }

            $roleNames = array_values(array_filter(array_map(static fn(array $item): string => $item['name'] ?? '', $profiles)));
            $role = $roleNames ? implode(' / ', $roleNames) : 'Não informado';
            // Atualiza a identidade da sessão com os dados oficiais do RM para o menu superior.
            $sessionUser['name'] = $profile['name'] ?: ($sessionUser['name'] ?? $login);
            $sessionUser['email'] = $profile['email'] ?? '';
            $sessionUser['role'] = $role;
            $sessionUser['avatar_data_uri'] = $profile['avatar_data_uri'] ?? '';
            Session::put('user', $sessionUser);
            Session::put('active_context', $activeContext);
        } catch (Throwable $e) {
            error_log('[UsuarioController] Falha ao carregar perfil RM: ' . $e->getMessage());
            $loadError = 'Não foi possível carregar todos os dados do RM. Verifique a conexão e as permissões de leitura.';
            $activeContext = Session::get('active_context', []);
            if (!is_array($activeContext)) $activeContext = [];
            $profile = [
                'code' => $login,
                'name' => (string)($sessionUser['name'] ?? $login ?: 'Usuário'),
                'email' => (string)($sessionUser['email'] ?? ''),
                'avatar_data_uri' => (string)($sessionUser['avatar_data_uri'] ?? ''),
            ];
            $role = (string)($sessionUser['role'] ?? 'Não informado');
        }

        return $this->render('usuario/perfil', [
            'profile' => $profile,
            'role' => $role ?? 'Não informado',
            'contexts' => $contexts,
            'activeContext' => $activeContext ?? [],
            'loadError' => $loadError,
            'successMessage' => Session::pullFlash('context_success', ''),
            'errorMessage' => Session::pullFlash('context_error', ''),
        ]);
    }

    public function alterarContexto(Request $request): Response
    {
        $user = Session::get('user', []);
        $login = is_array($user) ? trim((string)($user['code'] ?? $user['codusuario'] ?? $user['username'] ?? '')) : '';
        $codColigada = filter_var($request->input('cod_coligada'), FILTER_VALIDATE_INT);
        $codFilial = filter_var($request->input('cod_filial'), FILTER_VALIDATE_INT);
        $codTipoCurso = filter_var($request->input('cod_tipo_curso'), FILTER_VALIDATE_INT);

        $returnTo = $this->safeReturnTo($request);
        if ($login === '' || $codColigada === false || $codFilial === false || $codTipoCurso === false) {
            Session::flash('context_error', 'Os dados do contexto são inválidos.');
            return $this->redirect($returnTo);
        }

        try {
            $context = (new RmUserProfile())->findAllowedContext($login, $codColigada, $codFilial, $codTipoCurso);
            if ($context === null) {
                Session::flash('context_error', 'Você não possui acesso a esse contexto no RM.');
                return $this->redirect('/usuario/perfil');
            }
            Session::put('active_context', $context);
            $profiles = (new RmUserProfile())->findProfiles($login, $codColigada);
            $roleNames = array_values(array_filter(array_map(static fn(array $item): string => (string)($item['name'] ?? ''), $profiles)));
            $user['role'] = $roleNames ? implode(' / ', $roleNames) : 'Não informado';
            Session::put('user', $user);
            Session::flash('context_success', 'Contexto alterado com sucesso.');
        } catch (Throwable $e) {
            error_log('[UsuarioController] Falha ao alterar contexto: ' . $e->getMessage());
            Session::flash('context_error', 'Não foi possível alterar o contexto. Tente novamente.');
        }

        return $this->redirect($returnTo);
    }

    private function safeReturnTo(Request $request): string
    {
        $returnTo = trim((string)$request->input('return_to', '/'));
        $returnPath = parse_url($returnTo, PHP_URL_PATH);
        if (!is_string($returnPath) || $returnPath === '' || $returnPath[0] !== '/' || str_starts_with($returnPath, '//')
            || parse_url($returnTo, PHP_URL_HOST) !== null || str_starts_with($returnPath, '/usuario/contexto')) {
            return '/';
        }
        return $returnTo;
    }
}
