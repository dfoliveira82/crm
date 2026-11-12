<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Env, Request, Response, Session};
use App\Models\{RmAcademicCatalog, RmClassParticipants, RmUserProfile};
use Throwable;

final class AdIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $context = $this->activeContext();
        $search = trim((string) $request->query('q', ''));
        $periodId = (int) $request->query('period', 0);
        $periodId = $periodId > 0 ? $periodId : null;
        $courseId = trim((string) $request->query('course', ''));
        $groups = [];
        $periods = [];
        $courses = [];
        $loadError = '';

        try {
            if (!$context) {
                throw new \RuntimeException('Nenhum contexto acadêmico ativo foi encontrado.');
            }

            $catalog = new RmAcademicCatalog();
            $periods = $catalog->listPeriods($context);
            $courses = $catalog->listCourses($context, $periodId);
            $allowedCourses = array_map(static fn(array $course): string => trim((string) $course['CODCURSO']), $courses);
            if ($courseId !== '' && !in_array($courseId, $allowedCourses, true)) {
                $courseId = '';
            }

            $rows = $catalog->listClasses($context, $search, $periodId, $courseId, true);
            $groups = $this->groupClasses($rows);
            $participantModel = new RmClassParticipants();
            foreach ($groups as &$group) {
                $codTurma = (string) ($group['cod_turma'] ?? '');
                $idPerlet = (int) ($group['period_id'] ?? 0);
                $students = [];
                if ($codTurma !== '' && $idPerlet > 0) {
                    $students = $participantModel->classStudents((int) $context['CODCOLIGADA'], $idPerlet, $codTurma);
                }

                $summary = $this->summarizeStudents($students);
                $group['students'] = array_map(static function (array $student): array {
                    $emailAd = trim((string) ($student['EMAILAD'] ?? ''));
                    return [
                        'RA' => (string) ($student['RA'] ?? ''),
                        'NOME' => (string) ($student['NOME'] ?? ''),
                        'EMAIL' => (string) ($student['EMAIL'] ?? ''),
                        'EMAILAD' => $emailAd,
                        'AD_STATUS' => $emailAd !== '' ? 'INTEGRADO' : 'PENDENTE',
                    ];
                }, $students);
                $group['students_total'] = (int) $summary['total'];
                $group['students_integrated'] = (int) $summary['integrated'];
                $group['students_pending'] = (int) $summary['pending'];
                $group['ad_status'] = $summary['integrated'] >= $summary['total'] ? 'INTEGRADO' : ($summary['integrated'] > 0 ? 'PARCIAL' : 'PENDENTE');
                $group['disciplines'] = [];
            }
            unset($group);
        } catch (Throwable $exception) {
            error_log('[AdIntegrationController] Falha ao carregar alunos do RM: ' . $exception->getMessage());
            $loadError = 'Não foi possível carregar a integração do RM com o Active Directory.';
        }

        return $this->render('educacional/integracao-ad', [
            'context' => $context,
            'groups' => $groups,
            'periods' => $periods,
            'periodId' => $periodId,
            'courses' => $courses,
            'courseId' => $courseId,
            'search' => $search,
            'loadError' => $loadError,
        ]);
    }

    public function participants(Request $request): Response
    {
        try {
            $context = $this->activeContext();
            $selected = array_values(array_unique(array_filter(array_map('intval', (array) ($request->json()['disciplines'] ?? $request->json()['ids'] ?? [])))));
            if (!$context || $selected === []) {
                return $this->json(['error' => 'invalid_selection', 'message' => 'Selecione ao menos uma disciplina.'], 422);
            }

            $allowed = $this->allowedDisciplines($context);
            $result = [];
            $model = new RmClassParticipants();
            foreach ($selected as $idTurmaDisc) {
                if (!isset($allowed[$idTurmaDisc])) {
                    return $this->json(['error' => 'discipline_not_allowed', 'message' => 'Disciplina fora do contexto acadêmico.'], 403);
                }
                $discipline = $allowed[$idTurmaDisc];
                $students = $model->adStudents((int) $context['CODCOLIGADA'], $idTurmaDisc);
                $summary = $this->summarizeStudents($students);
                $result[] = [
                    'id_turma_disc' => $idTurmaDisc,
                    'discipline' => $discipline['name'],
                    'total' => $summary['total'],
                    'integrated' => $summary['integrated'],
                    'pending' => $summary['pending'],
                    'students' => $students,
                ];
            }

            return $this->json(['status' => 'ok', 'disciplines' => $result]);
        } catch (Throwable $exception) {
            error_log('[AdIntegrationController] Falha ao carregar alunos para AD: ' . $exception->getMessage());
            return $this->json(['error' => 'ad_students_failed', 'message' => $exception->getMessage()], 500);
        }
    }

    public function validateSelection(Request $request): Response
    {
        try {
            $context = $this->activeContext();
            if (!$context) {
                return $this->json(['error' => 'academic_context_missing', 'message' => 'Nenhum contexto acadêmico ativo foi encontrado.'], 422);
            }

            $payload = $request->json();
            $selected = (array) ($payload['students'] ?? []);
            if ($selected === []) {
                return $this->json(['error' => 'select_at_least_one_student', 'message' => 'Selecione ao menos um aluno para validar.'], 422);
            }

            $seen = [];
            $valid = [];
            $duplicate = [];
            $alreadyIntegrated = [];
            $invalid = [];

            foreach ($selected as $student) {
                if (!is_array($student)) {
                    $invalid[] = 'Registro inválido';
                    continue;
                }

                $ra = trim((string) ($student['RA'] ?? ''));
                $name = trim((string) ($student['NOME'] ?? ''));
                $emailAd = trim((string) ($student['EMAILAD'] ?? ''));
                $codTurma = trim((string) ($student['CODTURMA'] ?? ''));
                $periodId = (int) ($student['IDPERLET'] ?? 0);

                if ($ra === '' || $name === '' || $codTurma === '' || $periodId <= 0) {
                    $invalid[] = ['RA' => $ra, 'NOME' => $name, 'status' => 'dados_incompletos'];
                    continue;
                }

                if (isset($seen[$ra])) {
                    $duplicate[] = ['RA' => $ra, 'NOME' => $name];
                    continue;
                }
                $seen[$ra] = true;

                if ($emailAd !== '') {
                    $alreadyIntegrated[] = ['RA' => $ra, 'NOME' => $name, 'EMAILAD' => $emailAd];
                    continue;
                }

                $valid[] = ['RA' => $ra, 'NOME' => $name, 'CODTURMA' => $codTurma, 'IDPERLET' => $periodId];
            }

            $safeToProceed = $valid !== [] && $duplicate === [] && $invalid === [] && $alreadyIntegrated === [];

            return $this->json([
                'status' => 'ok',
                'safe_to_proceed' => $safeToProceed,
                'summary' => [
                    'selected' => count($selected),
                    'valid' => count($valid),
                    'duplicate' => count($duplicate),
                    'already_integrated' => count($alreadyIntegrated),
                    'invalid' => count($invalid),
                ],
                'valid' => $valid,
                'duplicate' => $duplicate,
                'already_integrated' => $alreadyIntegrated,
                'invalid' => $invalid,
                'message' => $safeToProceed
                    ? 'Validação concluída. Os alunos selecionados estão elegíveis para a próxima etapa do processo AD.'
                    : 'A seleção contém itens que exigem revisão antes de seguir para a criação no Active Directory.',
            ]);
        } catch (Throwable $exception) {
            error_log('[AdIntegrationController] Falha ao validar seleção AD: ' . $exception->getMessage());
            return $this->json(['error' => 'ad_validation_failed', 'message' => $exception->getMessage()], 500);
        }
    }

    private const AD_STUDENT_OU = 'OU=FADIPA,OU=ALUNOS,OU=ACADEMICO,OU=FADIPA,DC=fadipa,DC=local';

    public function confirmEnvironment(Request $request): Response
    {
        try {
            $payload = $request->json();
            $environment = strtolower(trim((string) ($payload['environment'] ?? Env::get('AD_ENVIRONMENT', 'producao'))));
            $targetOu = self::AD_STUDENT_OU;
            $bindDn = trim((string) Env::get('AD_BIND_DN', ''));
            $serviceAccountLabel = trim((string) Env::get('AD_SERVICE_ACCOUNT', ''));

            if (!in_array($environment, ['homologacao', 'producao'], true)) {
                return $this->json(['error' => 'invalid_environment', 'message' => 'Ambiente inválido. Use homologação ou produção.'], 422);
            }
            if ($bindDn === '' || trim((string) Env::get('AD_BIND_PASSWORD', '')) === '') {
                return $this->json(['error' => 'missing_bind_credentials', 'message' => 'Configure AD_BIND_DN e AD_BIND_PASSWORD no ambiente do servidor.'], 500);
            }

            // A OU é fixa no backend; valores de ambiente ou navegador não alteram o destino.

            $productionAllowed = Env::bool('AD_ALLOW_PRODUCTION', false);

            Session::put('ad_confirmation', [
                'environment' => $environment,
                'ou' => self::AD_STUDENT_OU,
                'service_account' => $serviceAccountLabel !== '' ? $serviceAccountLabel : $bindDn,
                'created_at' => time(),
            ]);

            return $this->json([
                'status' => 'ok',
                'safe_to_proceed' => true,
                'environment' => $environment,
                'ou' => self::AD_STUDENT_OU,
                'service_account' => $serviceAccountLabel !== '' ? $serviceAccountLabel : $bindDn,
                'write_allowed' => $environment !== 'producao' || $productionAllowed,
                'message' => 'Configuração registrada. Testes de conexão são somente leitura; gravações em produção continuam bloqueadas até AD_ALLOW_PRODUCTION=true.',
            ]);
        } catch (Throwable $exception) {
            error_log('[AdIntegrationController] Falha ao confirmar ambiente AD: ' . $exception->getMessage());
            return $this->json(['error' => 'ad_confirm_failed', 'message' => 'Não foi possível confirmar a configuração do Active Directory.'], 500);
        }
    }

    /** Teste somente leitura: autentica no domínio e confirma que a OU pode ser consultada. */
    public function testConnection(Request $request): Response
    {
        $connection = null;
        try {
            if (!function_exists('ldap_connect')) {
                return $this->json(['status' => 'error', 'code' => 'ldap_missing', 'message' => 'Extensão php-ldap não está habilitada.'], 500);
            }
            $host = trim((string) Env::get('AD_LDAP_HOST', ''));
            $port = (int) Env::get('AD_LDAP_PORT', 636);
            $bindDn = trim((string) Env::get('AD_BIND_DN', ''));
            $password = (string) Env::get('AD_BIND_PASSWORD', '');
            if ($host === '' || $bindDn === '' || $password === '') {
                return $this->json(['status' => 'error', 'code' => 'ad_config_missing', 'message' => 'Configure host, AD_BIND_DN e AD_BIND_PASSWORD no servidor.'], 500);
            }
            if (!str_starts_with(strtolower($host), 'ldaps://') || $port !== 636) {
                return $this->json(['status' => 'error', 'code' => 'ldaps_required', 'message' => 'O teste exige LDAPS na porta 636.'], 400);
            }
            $connection = @ldap_connect($host, $port);
            if (!$connection) {
                return $this->json(['status' => 'error', 'code' => 'connect_failed', 'message' => 'Não foi possível abrir a conexão LDAP.'], 502);
            }
            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 8);
            if (!@ldap_bind($connection, $bindDn, $password)) {
                return $this->json([
                    'status' => 'error', 'code' => 'bind_failed',
                    'message' => 'BIND_FAILED: ' . ldap_error($connection),
                    'ldap_code' => ldap_errno($connection),
                    'diagnostic' => $this->ldapDiagnostic($connection),
                ], 401);
            }
            $ouResult = @ldap_read($connection, self::AD_STUDENT_OU, '(objectClass=organizationalUnit)', ['distinguishedName']);
            if ($ouResult === false || ldap_count_entries($connection, $ouResult) !== 1) {
                return $this->json([
                    'status' => 'warning', 'code' => 'bind_ok_ou_read_failed',
                    'message' => 'BIND_OK, mas a consulta da OU falhou. Verifique o DN e as permissões de leitura.',
                    'ldap_code' => ldap_errno($connection),
                    'diagnostic' => $this->ldapDiagnostic($connection),
                    'ou' => self::AD_STUDENT_OU,
                ], 409);
            }
            return $this->json([
                'status' => 'ok',
                'code' => 'BIND_OK_OU_READ_OK',
                'message' => 'BIND_OK e OU_READ_OK. Este teste não tentou criar, alterar ou excluir objetos no AD.',
                'host' => $host,
                'port' => $port,
                'ou' => self::AD_STUDENT_OU,
                'write_enabled' => Env::bool('AD_ALLOW_PRODUCTION', false),
            ]);
        } catch (Throwable $exception) {
            error_log('[AdIntegrationController] Teste de conexão AD: ' . $exception->getMessage());
            return $this->json(['status' => 'error', 'code' => 'test_failed', 'message' => 'Falha inesperada no teste LDAP. Consulte o log técnico.'], 500);
        } finally {
            if ($connection instanceof \LDAP\Connection) {
                @ldap_unbind($connection);
            }
        }
    }

    /**
     * Etapa 4: valida conexão e gravação controlada do objeto desabilitado.
     * Não define senha nem habilita a conta. Isso fica para a etapa 5, após definir
     * o procedimento institucional seguro de entrega da senha inicial.
     */
    public function integrateStudents(Request $request): Response
    {
        $connection = null;
        try {
            if (!function_exists('ldap_connect')) {
                return $this->json([
                    'error' => 'ldap_missing',
                    'message' => 'A extensão php-ldap não está habilitada no PHP deste servidor.',
                    'safe_to_proceed' => false,
                ], 500);
            }

            $confirmation = Session::get('ad_confirmation', []);
            if (!is_array($confirmation) || empty($confirmation['environment']) || empty($confirmation['created_at'])) {
                return $this->json(['error' => 'environment_not_confirmed', 'message' => 'Confirme primeiro a configuração do ambiente AD.'], 403);
            }
            if (time() - (int) $confirmation['created_at'] > 1800) {
                Session::forget('ad_confirmation');
                return $this->json(['error' => 'confirmation_expired', 'message' => 'A confirmação expirou. Confirme novamente o ambiente.'], 403);
            }

            $environment = (string) $confirmation['environment'];
            if ($environment === 'producao' && !Env::bool('AD_ALLOW_PRODUCTION', false)) {
                return $this->json(['error' => 'production_blocked', 'message' => 'Criação em produção bloqueada pela configuração do servidor.'], 403);
            }

            $payload = $request->json();
            $selected = (array) ($payload['students'] ?? []);
            if ($selected === []) {
                return $this->json(['error' => 'select_at_least_one_student', 'message' => 'Selecione ao menos um aluno.'], 422);
            }
            if (count($selected) > 25) {
                return $this->json(['error' => 'batch_limit', 'message' => 'Limite de 25 alunos por operação nesta etapa.'], 422);
            }

            $validated = $this->validateIntegrationPayload($selected);
            if ($validated['safe_to_proceed'] !== true) {
                return $this->json([
                    'error' => 'ad_validation_failed',
                    'message' => $validated['message'],
                    'safe_to_proceed' => false,
                    'summary' => $validated['summary'],
                ], 422);
            }

            $ldapHost = trim((string) Env::get('AD_LDAP_HOST', ''));
            $ldapPort = (int) Env::get('AD_LDAP_PORT', 636);
            $baseDn = trim((string) Env::get('AD_BASE_DN', 'DC=fadipa,DC=local'));
            $bindDn = trim((string) Env::get('AD_BIND_DN', ''));
            $bindPassword = (string) Env::get('AD_BIND_PASSWORD', '');
            $defaultDomain = trim((string) Env::get('AD_DEFAULT_DOMAIN', 'fadipa.local'));
            $adUserOu = self::AD_STUDENT_OU;

            if ($ldapHost === '' || $bindDn === '' || $bindPassword === '' || $baseDn === '') {
                return $this->json(['error' => 'ad_connection_missing', 'message' => 'Configure AD_LDAP_HOST, AD_BASE_DN, AD_BIND_DN e AD_BIND_PASSWORD no servidor.'], 500);
            }
            if (!str_starts_with(strtolower($ldapHost), 'ldaps://') && $ldapPort !== 636) {
                return $this->json(['error' => 'ad_tls_required', 'message' => 'A criação de contas exige LDAPS na porta 636 ou LDAP com StartTLS validado.'], 400);
            }

            $connection = @ldap_connect($ldapHost, $ldapPort);
            if (!$connection) {
                return $this->json(['error' => 'ad_connection_failed', 'message' => 'Não foi possível abrir a conexão LDAP.'], 502);
            }
            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 8);
            // Não desabilitar validação de certificado TLS. O certificado do DC deve ser confiável pelo servidor PHP.

            if (!str_starts_with(strtolower($ldapHost), 'ldaps://')) {
                if (!@ldap_start_tls($connection)) {
                    $diag = $this->ldapDiagnostic($connection);
                    return $this->json([
                        'error' => 'ad_starttls_failed',
                        'message' => 'Falha ao iniciar StartTLS: ' . ldap_error($connection),
                        'ldap_code' => ldap_errno($connection),
                        'diagnostic' => $diag,
                    ], 502);
                }
            }

            if (!@ldap_bind($connection, $bindDn, $bindPassword)) {
                return $this->json([
                    'error' => 'ad_bind_failed',
                    'message' => 'Falha na autenticação LDAP: ' . ldap_error($connection),
                    'ldap_code' => ldap_errno($connection),
                    'diagnostic' => $this->ldapDiagnostic($connection),
                ], 401);
            }

            // Confirma que a OU existe e é pesquisável pela identidade autenticada.
            $ouCheck = @ldap_read($connection, $adUserOu, '(objectClass=organizationalUnit)', ['distinguishedName']);
            if ($ouCheck === false || ldap_count_entries($connection, $ouCheck) !== 1) {
                return $this->json([
                    'error' => 'ad_ou_not_found_or_unreadable',
                    'message' => 'A OU de destino não foi localizada ou não pode ser consultada pela conta configurada.',
                    'ldap_code' => ldap_errno($connection),
                    'diagnostic' => $this->ldapDiagnostic($connection),
                    'ou' => $adUserOu,
                ], 409);
            }

            $results = [];
            foreach ($validated['students'] as $student) {
                $ra = (string) $student['RA'];
                $sam = $this->safeSamAccountName($ra);
                if ($sam === '') {
                    $results[] = ['RA' => $ra, 'status' => 'erro', 'message' => 'RA inválido para sAMAccountName. Use de 1 a 20 caracteres alfanuméricos.'];
                    continue;
                }

                $fullName = trim((string) $student['NOME']);
                $parts = preg_split('/\s+/', $fullName, 2, PREG_SPLIT_NO_EMPTY) ?: [$fullName];
                $firstName = $parts[0];
                $lastName = $parts[1] ?? $parts[0];
                $mail = $sam . '@fadipa.br';
                $upn = $sam . '@' . ($defaultDomain !== '' ? $defaultDomain : 'fadipa.local');

                $filter = '(sAMAccountName=' . ldap_escape($sam, '', LDAP_ESCAPE_FILTER) . ')';
                $search = @ldap_search($connection, $baseDn, $filter, ['sAMAccountName', 'distinguishedName']);
                if ($search === false) {
                    $results[] = [
                        'RA' => $ra, 'status' => 'erro',
                        'message' => 'Falha ao verificar duplicidade: ' . ldap_error($connection),
                        'ldap_code' => ldap_errno($connection),
                        'diagnostic' => $this->ldapDiagnostic($connection),
                    ];
                    continue;
                }
                if (ldap_count_entries($connection, $search) > 0) {
                    $results[] = ['RA' => $ra, 'status' => 'ja_existe', 'message' => 'O sAMAccountName já existe no domínio. Nenhuma alteração foi feita.'];
                    continue;
                }

                // Criar desabilitado, sem unicodePwd. A senha e habilitação ficam para a etapa 5.
                $entry = [
                    'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
                    'cn' => $fullName,
                    'sn' => $lastName,
                    'givenName' => $firstName,
                    'displayName' => $fullName,
                    'sAMAccountName' => $sam,
                    'userPrincipalName' => $upn,
                    'mail' => $mail,
                    'department' => 'Alunos',
                    'description' => 'Criado pela integração RM -> AD; aguardando ativação de credencial',
                    'userAccountControl' => '514',
                ];

                $dn = 'CN=' . ldap_escape($fullName, '', LDAP_ESCAPE_DN) . ',' . $adUserOu;
                if (!@ldap_add($connection, $dn, $entry)) {
                    $results[] = [
                        'RA' => $ra,
                        'status' => 'erro',
                        'message' => 'O AD recusou a criação do objeto: ' . ldap_error($connection),
                        'ldap_code' => ldap_errno($connection),
                        'diagnostic' => $this->ldapDiagnostic($connection),
                    ];
                    continue;
                }

                $verify = @ldap_read($connection, $dn, '(objectClass=user)', ['sAMAccountName', 'mail', 'userAccountControl']);
                if ($verify === false || ldap_count_entries($connection, $verify) !== 1) {
                    $results[] = [
                        'RA' => $ra,
                        'status' => 'criado_sem_confirmacao',
                        'message' => 'O objeto foi adicionado, mas a leitura de confirmação falhou. Não atualize EMAILAD no RM.',
                        'ldap_code' => ldap_errno($connection),
                        'diagnostic' => $this->ldapDiagnostic($connection),
                    ];
                    continue;
                }

                $results[] = [
                    'RA' => $ra,
                    'status' => 'criado_desabilitado',
                    'message' => 'Objeto criado e confirmado no AD, ainda desabilitado e sem senha. A ativação depende da etapa 5.',
                    'email' => $mail,
                    'upn' => $upn,
                ];
            }

            $created = count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'criado_desabilitado'));
            $errors = count(array_filter($results, static fn(array $row): bool => in_array(($row['status'] ?? ''), ['erro', 'criado_sem_confirmacao'], true)));
            $already = count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'ja_existe'));

            return $this->json([
                'status' => 'ok',
                'safe_to_proceed' => false,
                'environment' => $environment,
                'ou' => $adUserOu,
                'summary' => [
                    'selected' => count($validated['students']),
                    'created_disabled' => $created,
                    'already_exists' => $already,
                    'errors' => $errors,
                ],
                'results' => $results,
                'message' => 'Etapa 4 concluída: objetos criados, quando autorizado, permanecem desabilitados. Nenhum EMAILAD foi atualizado no RM.',
            ]);
        } catch (Throwable $exception) {
            error_log('[AdIntegrationController] Falha na integração AD: ' . $exception->getMessage());
            return $this->json(['error' => 'ad_integration_failed', 'message' => 'Falha inesperada na integração AD. Consulte o log técnico do servidor.'], 500);
        } finally {
            if ($connection instanceof \LDAP\Connection) {
                @ldap_unbind($connection);
            }
        }
    }

    private function ldapDiagnostic($connection): string
    {
        $diagnostic = '';
        if (defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
            @ldap_get_option($connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic);
        }
        return trim((string) $diagnostic);
    }

    private function validateIntegrationPayload(array $selected): array
    {
        $students = [];
        $message = '';
        $invalid = 0;
        foreach ($selected as $student) {
            if (!is_array($student)) {
                $invalid++;
                continue;
            }
            $ra = trim((string) ($student['RA'] ?? ''));
            $name = trim((string) ($student['NOME'] ?? ''));
            $email = trim((string) ($student['EMAIL'] ?? ''));
            if ($ra === '' || $name === '') {
                $invalid++;
                continue;
            }
            $students[] = ['RA' => $ra, 'NOME' => $name, 'EMAIL' => $email];
        }

        $safeToProceed = $students !== [] && $invalid === 0;
        if (!$safeToProceed) {
            $message = 'A seleção contém elementos inválidos ou incompletos para criação no AD.';
        }

        return [
            'safe_to_proceed' => $safeToProceed,
            'message' => $message,
            'summary' => ['selected' => count($selected), 'valid' => count($students), 'invalid' => $invalid],
            'students' => $students,
        ];
    }

    private function safeSamAccountName(string $value): string
    {
        $value = trim($value);
        // Preserva o RA literalmente, inclusive zeros à esquerda.
        return preg_match('/^[A-Za-z0-9]{1,20}$/D', $value) === 1 ? $value : '';
    }

    private function summarizeStudents(array $students): array
    {
        $total = count($students);
        $integrated = 0;
        foreach ($students as $student) {
            $emailAd = trim((string) ($student['EMAILAD'] ?? ''));
            if ($emailAd !== '') {
                $integrated++;
            }
        }
        return [
            'total' => $total,
            'integrated' => $integrated,
            'pending' => max(0, $total - $integrated),
        ];
    }

    private function activeContext(): array
    {
        $context = Session::get('active_context', []);
        if (is_array($context) && isset($context['CODCOLIGADA'], $context['CODFILIAL'], $context['CODTIPOCURSO'])) {
            return $context;
        }

        $user = Session::get('user', []);
        $login = is_array($user) ? trim((string) ($user['code'] ?? $user['username'] ?? '')) : '';
        if ($login === '') {
            return [];
        }

        return (new RmUserProfile())->findDefaultContextByActiveEmployment($login) ?? [];
    }

    private function allowedDisciplines(array $context): array
    {
        $rows = (new RmAcademicCatalog())->listClasses($context, null, null, null, true);
        $allowed = [];
        foreach ($rows as $row) {
            $id = (int) ($row['IDTURMADISC'] ?? 0);
            if ($id <= 0) continue;
            $allowed[$id] = [
                'id_turma_disc' => $id,
                'name' => (string) ($row['DISCIPLINA_NOME'] ?? $row['CODDISC'] ?? 'Disciplina'),
            ];
        }
        return $allowed;
    }

    private function groupClasses(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $classId = implode(':', [(int) $row['CODCOLIGADA'], (int) $row['IDPERLET'], (string) $row['CODTURMA'], (string) ($row['CODCURSO'] ?? 'SEM-CURSO')]);
            $turmaName = trim((string) (($row['TURMA_NOME'] ?? $row['CODTURMA']) ?: 'Turma'));
            $turnoValue = $turmaName . ' ' . trim((string) ($row['DISCIPLINA_NOME'] ?? ''));
            $turno = $this->classifyTurno($turnoValue);

            if (!isset($groups[$classId])) {
                $groups[$classId] = [
                    'idnumber' => 'rm:turma:' . $classId,
                    'turma' => $turmaName,
                    'cod_turma' => (string) ($row['CODTURMA'] ?? ''),
                    'curso' => (string) ($row['CURSO_NOME'] ?? 'Curso não informado'),
                    'periodo' => (string) ($row['PERIODO'] ?? $row['CODPERLET'] ?? 'Periodo'),
                    'period_id' => (int) $row['IDPERLET'],
                    'course_id' => (string) ($row['CODCURSO'] ?? ''),
                    'turno_group' => $turno['key'],
                    'turno_label' => $turno['label'],
                    'disciplines' => [],
                ];
            }
        }

        uasort($groups, static function (array $left, array $right): int {
            return strcmp((string) ($left['turma'] ?? ''), (string) ($right['turma'] ?? ''));
        });

        return array_values($groups);
    }

    private function classifyTurno(string $turno): array
    {
        $normalized = strtoupper(trim($turno));
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) : $normalized;
        $value = preg_replace('/[^A-Z0-9]+/', '', (string) ($ascii ?: $normalized)) ?: '';

        if (str_contains($value, 'ESPECIAL')) {
            return ['key' => 'especial', 'label' => 'Disciplinas especiais'];
        }
        if (str_contains($value, 'MANHA') || str_contains($value, 'MATUTINO')) {
            return ['key' => 'manha', 'label' => 'Manhã'];
        }
        if (str_contains($value, 'TARDE') || str_contains($value, 'VESPERTINO')) {
            return ['key' => 'tarde', 'label' => 'Tarde'];
        }
        if (str_contains($value, 'NOITE') || str_contains($value, 'NOTURNO')) {
            return ['key' => 'noite', 'label' => 'Noite'];
        }

        return ['key' => 'outros', 'label' => 'Outros turnos'];
    }
}
