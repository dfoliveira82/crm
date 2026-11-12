<?php
declare(strict_types=1);

namespace App\Services\Nfse;

use App\Core\Env;
use RuntimeException;

final class IpatingaNfseProvider
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/Config/nfse.php';
    }

    public function getEnvironment(): string
    {
        return strtoupper((string) ($this->config['provider']['ambiente'] ?? Env::get('NFSE_AMBIENTE', 'BLOQUEADO')));
    }

    public function isHomologacao(): bool
    {
        return $this->getEnvironment() === 'HOMOLOGACAO';
    }

    public function isProductionBlocked(): bool
    {
        return strtoupper((string) ($this->config['provider']['ambiente'] ?? 'BLOQUEADO')) === 'PRODUCAO';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['prefeitura_ipatinga']['transmissao_ativa'] ?? false);
    }

    public function getEndpoint(): string
    {
        $environment = $this->getEnvironment();

        if (! $this->isEnabled()) {
            throw new RuntimeException('A comunicação com a Prefeitura de Ipatinga está bloqueada pela configuração de ambiente.');
        }

        if ($environment === 'PRODUCAO') {
            throw new RuntimeException('Transmissão de NFS-e para produção está bloqueada por segurança.');
        }

        $endpoint = $this->config['prefeitura_ipatinga']['endpoint_homologacao'] ?? Env::get('NFSE_IPATINGA_URL_HOMOLOGACAO', '');
        if ($endpoint === '') {
            throw new RuntimeException('Endpoint de homologação da Prefeitura de Ipatinga não configurado.');
        }

        return (string) $endpoint;
    }

    public function assertAllowed(): void
    {
        if ($this->isProductionBlocked()) {
            throw new RuntimeException('Nenhuma nota fiscal pode ser transmitida para produção nesta etapa.');
        }

        if (! $this->isEnabled()) {
            throw new RuntimeException('A transmissão de NFS-e está desabilitada no ambiente atual.');
        }
    }

    public function buildXml(array $payload): string
    {
        $this->assertAllowed();

        $numero = (string) ($payload['numero_nfse'] ?? '000000');
        $valor = (string) ($payload['valor_servico'] ?? '0.00');
        $tomador = (string) ($payload['tomador_nome'] ?? 'Tomador');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ConsultarNfseEnvio xmlns="http://www.abrasf.org.br/nfse">
  <Prestador>
    <Numero>{$numero}</Numero>
    <ValorServico>{$valor}</ValorServico>
    <Tomador>{$tomador}</Tomador>
  </Prestador>
</ConsultarNfseEnvio>
XML;
    }

    public function send(array $payload): array
    {
        $this->assertAllowed();

        $endpoint = $this->getEndpoint();

        return [
            'status' => 'BLOQUEADO',
            'ambiente' => $this->getEnvironment(),
            'url' => $endpoint,
            'mensagem' => 'Transmissão de NFS-e protegida e bloqueada por configuração do ambiente. Apenas homologação controlada é permitida.',
        ];
    }
}
