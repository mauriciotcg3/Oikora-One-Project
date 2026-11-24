<?php

namespace App\Modules\Auditoria\Domain;

/** Catálogo estável e sem texto livre das classes de falha auditáveis (OIK-ADR-0015 §6). */
enum CodigoFalhaAuditoria: string
{
    case RegraNegocio = 'regra_negocio';
    case ConflitoConcorrencia = 'conflito_concorrencia';
    case IntegracaoIndisponivel = 'integracao_indisponivel';
    case PersistenciaIndisponivel = 'persistencia_indisponivel';
    case ExecucaoInesperada = 'execucao_inesperada';
}
