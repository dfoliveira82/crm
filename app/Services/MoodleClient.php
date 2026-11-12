<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class MoodleClient
{
    private string $endpoint;
    private string $token;
    private int $timeout;
    private bool $verifySsl;

    public function __construct()
    {
        $baseUrl = rtrim(trim((string) Env::get('MOODLE_BASE_URL', '')), '/');
        $this->token = trim((string) Env::get('MOODLE_TOKEN', ''));
        $this->timeout = max(5, (int) Env::get('MOODLE_TIMEOUT', 30));
        $this->verifySsl = Env::bool('MOODLE_VERIFY_SSL', true);

        if ($baseUrl === '' || $this->token === '') {
            throw new RuntimeException('As credenciais da API do Moodle não estão configuradas.');
        }
        $this->endpoint = $baseUrl . '/webservice/rest/server.php';
    }

    public function call(string $function, array $params = []): mixed
    {
        $fields = array_merge([
            'wstoken' => $this->token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json',
        ], $params);

        $curl = curl_init($this->endpoint);
        if ($curl === false) throw new RuntimeException('Não foi possível inicializar a conexão com o Moodle.');

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('Falha de comunicação com o Moodle: ' . ($error !== '' ? $error : 'erro desconhecido'));
        }

        $rawBody = trim((string) $body);
        if ($rawBody === '') {
            if ($status >= 400) {
                throw new RuntimeException('Moodle retornou HTTP ' . $status . ' para ' . $function . ' sem detalhes na resposta.');
            }
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $detail = trim(preg_replace('/\s+/', ' ', strip_tags($rawBody)) ?? '');
            $detail = $detail !== '' ? ' Detalhes: ' . substr($detail, 0, 300) : '';
            throw new RuntimeException('O Moodle retornou uma resposta inválida para ' . $function . '. HTTP ' . $status . '.' . $detail);
        }
        if (is_array($decoded) && isset($decoded['exception'])) {
            $message = trim((string) ($decoded['message'] ?? $decoded['errorcode'] ?? 'erro não informado'));
            throw new RuntimeException('Moodle [' . $function . ']: ' . $message);
        }
        if ($status >= 400) {
            throw new RuntimeException('Moodle retornou HTTP ' . $status . ' para ' . $function . '.');
        }

        return $decoded;
    }

    public function findCategory(string $idnumber): ?array
    {
        $result = $this->call('core_course_get_categories', [
            'criteria' => [['key' => 'idnumber', 'value' => $idnumber]],
        ]);
        if (!is_array($result)) return null;
        foreach ($result as $category) {
            if (is_array($category) && trim((string) ($category['idnumber'] ?? '')) === trim($idnumber)) {
                return $category;
            }
        }
        return null;
    }

    public function createCategory(string $name, int $parent, string $idnumber): int
    {
        $result = $this->call('core_course_create_categories', [
            'categories' => [[
                'name' => $name,
                'parent' => $parent,
                'idnumber' => $idnumber,
            ]],
        ]);
        $id = (int) ($result[0]['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('O Moodle não retornou o ID da categoria criada.');
        return $id;
    }

    public function updateCategory(int $id, string $name, int $parent): void
    {
        $this->call('core_course_update_categories', [
            'categories' => [[
                'id' => $id,
                'name' => $name,
                'parent' => $parent,
            ]],
        ]);
    }

    public function findCourse(string $idnumber): ?array
    {
        $result = $this->call('core_course_get_courses_by_field', [
            'field' => 'idnumber',
            'value' => $idnumber,
        ]);
        $courses = is_array($result['courses'] ?? null) ? $result['courses'] : [];
        return isset($courses[0]) && is_array($courses[0]) ? $courses[0] : null;
    }

    public function createCourse(array $course): int
    {
        $result = $this->call('core_course_create_courses', ['courses' => [$course]]);
        $id = (int) ($result[0]['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('O Moodle não retornou o ID do curso criado.');
        return $id;
    }

    public function updateCourse(array $course): void
    {
        $this->call('core_course_update_courses', ['courses' => [$course]]);
    }

    public function findUsersByField(string $field, array $values): array
    {
        if ($values === []) return [];
        $result = $this->call('core_user_get_users_by_field', [
            'field' => $field,
            'values' => array_values($values),
        ]);
        return is_array($result) ? $result : [];
    }

    public function createUser(array $user): int
    {
        $result = $this->call('core_user_create_users', ['users' => [$user]]);
        $id = (int) ($result[0]['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('O Moodle não retornou o ID do usuário criado.');
        return $id;
    }

    public function updateUser(array $user): void
    {
        $this->call('core_user_update_users', ['users' => [$user]]);
    }

    public function enrolUsers(array $enrolments): void
    {
        if ($enrolments !== []) {
            $this->call('enrol_manual_enrol_users', ['enrolments' => array_values($enrolments)]);
        }
    }

    /**
     * Retorna os usuários matriculados em um curso Moodle.
     * O idnumber é usado pelo CRM para comparar o vínculo com o RM.
     */
    public function getEnrolledUsers(int $courseId): array
    {
        if ($courseId <= 0) return [];

        $result = $this->call('core_enrol_get_enrolled_users', [
            'courseid' => $courseId,
            'options' => [
                ['name' => 'userfields', 'value' => 'id,idnumber,username'],
                ['name' => 'onlyactive', 'value' => '1'],
            ],
        ]);

        return is_array($result) ? $result : [];
    }
}
