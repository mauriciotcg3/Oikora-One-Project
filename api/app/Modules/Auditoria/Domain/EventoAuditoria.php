<?php

namespace App\Modules\Auditoria\Domain;

enum EventoAuditoria: string
{
    case PessoaCriada = 'pessoa.criada';
    case PessoaAtualizada = 'pessoa.atualizada';
    case PessoaExcluida = 'pessoa.excluida';
    case PessoaRecuperada = 'pessoa.recuperada';
    case PessoaPurgada = 'pessoa.purgada';
    case PessoaMesclada = 'pessoa.mesclada';
    case PessoaMesclagemDesfeita = 'pessoa.mesclagem_desfeita';
    case TransferenciaSolicitada = 'pessoa.transferencia_solicitada';
    case TransferenciaAprovada = 'pessoa.transferencia_aprovada';
    case TransferenciaRecusada = 'pessoa.transferencia_recusada';
    case ImportacaoConcluida = 'pessoa.importacao_concluida';
    case ImportacaoDesfeita = 'pessoa.importacao_desfeita';
    case ExportacaoPedida = 'exportacao.pedida';
    case ExportacaoConcluida = 'exportacao.concluida';
    case ExportacaoNegada = 'exportacao.negada';
    case ConviteEmitido = 'acesso.convite_emitido';
    case AcessoConcedido = 'acesso.concedido';
    case AcessoRevogado = 'acesso.revogado';
    case PapelAtualizado = 'autorizacao.papel_atualizado';
    case AtribuicaoConcedida = 'autorizacao.atribuicao_concedida';
    case AtribuicaoRevogada = 'autorizacao.atribuicao_revogada';
    case EscaladaBloqueada = 'authz.escalation_blocked';
    case UltimoAdministradorBloqueado = 'authz.last_admin_blocked';
    case AutorizacaoSuporteCriada = 'suporte.autorizacao_criada';
    case AutorizacaoSuporteRevogada = 'suporte.autorizacao_revogada';
    case AutorizacaoSuporteExpirada = 'suporte.autorizacao_expirada';

    /** @return list<string> */
    public function metadadosPermitidos(): array
    {
        return match ($this) {
            self::ExportacaoPedida, self::ExportacaoConcluida => ['filtro', 'categorias', 'contagem'],
            self::ExportacaoNegada => ['filtro', 'categorias', 'motivo_codigo'],
            self::PessoaMesclada, self::PessoaMesclagemDesfeita => ['pessoa_origem_id', 'pessoa_destino_id'],
            self::ImportacaoConcluida, self::ImportacaoDesfeita => ['importacao_id', 'contagem'],
            self::TransferenciaRecusada, self::EscaladaBloqueada, self::UltimoAdministradorBloqueado => ['motivo_codigo'],
            default => [],
        };
    }
}
