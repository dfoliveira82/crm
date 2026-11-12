/*
================================================================================
ETAPA 3 - INTEGRAÇÃO TOTVS RM COM NFS-e DE IPATINGA
Script de Atualização e Validação do Banco de Dados
================================================================================
Parte 1: Executar no BANCO DE DADOS DA APLICAÇÃO (CRM / TotvsWEB)
Garante a integridade das tabelas NFS_* e cria índices otimizados para sincronização.
================================================================================
*/
SET NOCOUNT ON;
GO

-- 1. Verificar/Criar índices adicionais para melhor desempenho na sincronização
IF OBJECT_ID('dbo.NFS_COBRANCA', 'U') IS NOT NULL
BEGIN
    -- Garantir tamanho suficiente para CPF/CNPJ com ou sem formatação
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'NFS_COBRANCA' AND COLUMN_NAME = 'DOCUMENTO_TOMADOR' AND CHARACTER_MAXIMUM_LENGTH < 20
    )
    BEGIN
        ALTER TABLE dbo.NFS_COBRANCA ALTER COLUMN DOCUMENTO_TOMADOR VARCHAR(20) NULL;
        PRINT 'Coluna DOCUMENTO_TOMADOR ajustada para VARCHAR(20).';
    END;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_NFS_COB_SINC_STATUS' AND object_id = OBJECT_ID('dbo.NFS_COBRANCA'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_NFS_COB_SINC_STATUS
        ON dbo.NFS_COBRANCA (CODCOLIGADA, IDLAN_RM)
        INCLUDE (QUITADA_INTEGRAL, ELEGIVEL_EMISSAO, VALOR_PAGO, VALOR_ORIGINAL);
        PRINT 'Índice IX_NFS_COB_SINC_STATUS criado com sucesso.';
    END;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_NFS_COB_VENCIMENTO' AND object_id = OBJECT_ID('dbo.NFS_COBRANCA'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_NFS_COB_VENCIMENTO
        ON dbo.NFS_COBRANCA (DATA_VENCIMENTO, CODCOLIGADA)
        INCLUDE (QUITADA_INTEGRAL, ELEGIVEL_EMISSAO);
        PRINT 'Índice IX_NFS_COB_VENCIMENTO criado com sucesso.';
    END;
END;
GO

IF OBJECT_ID('dbo.NFS_BAIXAS', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_NFS_BAIXAS_LOOKUP' AND object_id = OBJECT_ID('dbo.NFS_BAIXAS'))
    BEGIN
        CREATE NONCLUSTERED INDEX IX_NFS_BAIXAS_LOOKUP
        ON dbo.NFS_BAIXAS (CODCOLIGADA, IDLAN_RM, IDBAIXA_RM)
        INCLUDE (VALOR_BAIXA, DATA_BAIXA, ESTORNADA);
        PRINT 'Índice IX_NFS_BAIXAS_LOOKUP criado com sucesso.';
    END;
END;
GO

/*
================================================================================
Parte 2: SCRIPT DE CONCESSÃO DE PERMISSÃO NO BANCO TOTVS RM (CorporeRM)
Executar com o administrador do SQL Server / DBA no banco do TOTVS RM.
Cria ou ajusta o usuário 'rm' ou 'usr_crm_nfse' estritamente para LEITURA.
================================================================================

-- Exemplo para criar usuário específico com acesso SOMENTE LEITURA:
-- USE CorporeRM;
-- CREATE LOGIN [usr_crm_nfse] WITH PASSWORD = 'CrieUmaSenhaForteAqui!';
-- CREATE USER [usr_crm_nfse] FOR LOGIN [usr_crm_nfse];
-- GRANT SELECT ON dbo.FLAN TO [usr_crm_nfse];
-- GRANT SELECT ON dbo.FLANBAIXA TO [usr_crm_nfse];
-- GRANT SELECT ON dbo.FCFO TO [usr_crm_nfse];
-- DENY INSERT, UPDATE, DELETE ON dbo.FLAN TO [usr_crm_nfse];
-- DENY INSERT, UPDATE, DELETE ON dbo.FLANBAIXA TO [usr_crm_nfse];
-- DENY INSERT, UPDATE, DELETE ON dbo.FCFO TO [usr_crm_nfse];
================================================================================
*/
PRINT 'Atualização de índices do módulo NFS-e (Etapa 3) concluída.';
GO
