<?php
declare(strict_types=1);

namespace App\Services\Rm;

use App\Core\{Env, HttpClient};

interface RmAuthenticator
{
    public function authenticate(string $username, string $password): array;
}

final class RmAuthClient implements RmAuthenticator
{
    public function __construct(private ?HttpClient $http = null)
    {
        $this->http ??= new HttpClient();
    }

    public function authenticate(string $username, string $password): array
    {
        // Fake authentication is intentionally restricted to explicit local development.
        if (Env::get('APP_ENV') === 'local' && Env::bool('RM_FAKE')) {
            return [
                'user' => ['code' => $username, 'name' => $username, 'email' => '', 'role' => 'Usuário'],
                'token' => null,
                'refresh_token' => null,
                'expires' => null,
            ];
        }

        $base = rtrim((string) Env::get('RM_BASE_URL', ''), '/');
        if ($base === '') {
            throw new RmException('RM não configurado.', true);
        }

        $method = strtolower((string) Env::get('RM_AUTH_METHOD', 'token'));

        try {
            if ($method === 'basic') {
                $endpoint = $base . '/' . ltrim((string) Env::get('RM_AUTH_ENDPOINT', ''), '/');
                $result = $this->http->request('GET', $endpoint, [
                    'Authorization: Basic ' . base64_encode($username . ':' . $password),
                    'Accept: application/json',
                ]);

                if ($result['status'] < 200 || $result['status'] >= 300) {
                    $this->throwForHttpStatus($result['status']);
                }

                $data = json_decode($result['body'], true);
                if (!is_array($data)) {
                    throw new RmException('O RM retornou uma resposta inválida.');
                }

                return $this->normalize($data, null, null, $username);
            }

            if ($method === 'token') {
                $endpoint = $base . '/' . ltrim((string) Env::get('RM_TOKEN_ENDPOINT', '/api/connect/token/'), '/');

                // A API de autenticação da Linha RM espera JSON, não form-urlencoded.
                $payload = json_encode([
                    'grant_type' => 'password',
                    'username' => $username,
                    'password' => $password,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if ($payload === false) {
                    throw new RmException('Não foi possível preparar a solicitação de autenticação.');
                }

                $result = $this->http->request('POST', $endpoint, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ], $payload);

                if ($result['status'] < 200 || $result['status'] >= 300) {
                    $this->throwForHttpStatus($result['status']);
                }

                $data = json_decode($result['body'], true);
                if (!is_array($data) || empty($data['access_token'])) {
                    throw new RmException('O RM respondeu, mas não retornou um access_token válido.');
                }

                return $this->normalize(
                    $data,
                    (string) $data['access_token'],
                    isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
                    $username
                );
            }

            throw new RmException('RM_AUTH_METHOD deve ser basic ou token.');
        } catch (RmException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Do not log credentials, request bodies, or token values.
            error_log('[RM AUTH] ' . get_class($e) . ': ' . $e->getMessage());
            throw new RmException('Não foi possível comunicar com o serviço de autenticação do RM.', true);
        }
    }

    private function throwForHttpStatus(int $status): never
    {
        if (in_array($status, [400, 401, 403], true)) {
            throw new RmException('Usuário ou senha inválidos.', false, $status);
        }

        throw new RmException('O serviço de autenticação do RM está indisponível.', true, $status);
    }

    private function normalize(array $data, ?string $token, ?string $refreshToken, string $username): array
    {
        // O endpoint /api/connect/token normalmente retorna os tokens, não o cadastro do usuário.
        // Por isso, quando não houver objeto de usuário na resposta, usamos o login informado
        // apenas como identificação de sessão, sem presumir nome, e-mail ou perfil no RM.
        $u = $data['user'] ?? $data['usuario'] ?? [];
        if (!is_array($u)) {
            $u = [];
        }

        return [
            'user' => [
                'code' => (string) ($u['code'] ?? $u['codusuario'] ?? $u['CODUSUARIO'] ?? $u['username'] ?? $username),
                'name' => (string) ($u['name'] ?? $u['nome'] ?? $u['NOME'] ?? $u['username'] ?? $username),
                'email' => (string) ($u['email'] ?? $u['EMAIL'] ?? ''),
                'role' => (string) ($u['role'] ?? $u['perfil'] ?? ''),
            ],
            'token' => $token,
            'refresh_token' => $refreshToken,
            'expires' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        ];
    }
}
