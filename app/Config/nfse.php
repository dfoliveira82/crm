<?php
declare(strict_types=1);

/**
 * Configuração e mapeamento do módulo NFS-e - Integração TOTVS RM
 * Etapa 3: Conexão Real (Leitura)
 */
return [
    'rm' => [
        'enabled'              => true,
        'read_only'            => true,
        // Tabela de lançamentos financeiros
        'launch_table'         => 'dbo.FLAN',
        'launch_id'            => 'IDLAN',
        'company_id'           => 'CODCOLIGADA',
        'branch_id'            => 'CODFILIAL',
        'receivable_flag'      => 'PAGREC',          // 1 = Receber
        'launch_status'        => 'STATUSLAN',
        'document_type'        => 'CODTDO',
        'document_number'      => 'NUMERODOCUMENTO',
        'description'          => 'HISTORICO',
        'created_date'         => 'DATACRIACAO',
        'issue_date'           => 'DATAEMISSAO',
        'due_date'             => 'DATAVENCIMENTO',
        'cancellation_date'    => 'DATACANCELAMENTO',
        'reverse_date'         => 'DATACANCELBAIXA',
        'original_amount'      => 'VALORORIGINAL',
        'settled_amount'       => 'VALORBAIXADO',
        'discount_amount'      => 'VALORDESCONTO',
        'discount_custom_fields' => ['VALOROP3', 'VALOROP5', 'VALOROP6'], // Bolsas e descontos opcionais da instituição
        'interest_amount'      => 'VALORJUROS',
        'fine_amount'          => 'VALORMULTA',
        'customer_company_id'  => 'CODCOLCFO',
        'customer_id'          => 'CODCFO',

        // Tabela de baixas
        'settlement_table'     => 'dbo.FLANBAIXA',
        'settlement_id'        => 'IDBAIXA',
        'settlement_launch_id' => 'IDLAN',
        'settlement_date'      => 'DATABAIXA',
        'settlement_cancel'    => 'DATACANCELBAIXA', // Nulo = ativa; Preenchido = cancelada/estorno
        'settlement_amount'    => 'VALORBAIXA',      // Valor efetivamente recebido
        'settlement_original'  => 'VALORORIGINAL',   // Principal amortizado
        'settlement_discount'  => 'VALORDESCONTO',
        'settlement_interest'  => 'VALORJUROS',
        'settlement_fine'      => 'VALORMULTA',
        'settlement_status'    => 'STATUS',
        'settlement_type'      => 'TIPOBAIXA',

        // Tabela de tomadores (clientes)
        'customer_table'       => 'dbo.FCFO',
        'customer_company_id'  => 'CODCOLIGADA',
        'customer_code'        => 'CODCFO',
        'customer_name'        => 'NOME',
        'customer_fantasy'     => 'NOMEFANTASIA',
        'customer_document'    => 'CGCCFO',          // CPF ou CNPJ
        'customer_email'       => 'EMAIL',
        'customer_street'      => 'RUA',
        'customer_number'      => 'NUMERO',
        'customer_complement'  => 'COMPLEMENTO',
        'customer_district'    => 'BAIRRO',
        'customer_city'        => 'CIDADE',
        'customer_state'       => 'CODETD',
        'customer_zipcode'     => 'CEP',
    ],
    'business_rules' => [
        'issue_only_when_fully_paid'       => true,
        'allow_partial_payment'            => false,
        'rounding_tolerance'               => 0.01,
        'one_invoice_per_financial_launch' => true,
        'operator_authorization_required'  => true,
    ],
    // TRAVA DE SEGURANÇA FISCAL: NENHUMA NOTA É TRANSMITIDA NESTA ETAPA
    'prefeitura_ipatinga' => [
        'transmissao_ativa' => false,       // TRAVA ATIVA: emissão estritamente desabilitada
        'ambiente'          => 'BLOQUEADO',  // Não envia nem para homologação nem para produção
        'comunicacao_ativa' => false,
    ],
];
