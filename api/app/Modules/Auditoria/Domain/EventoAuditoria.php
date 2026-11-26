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
    case ListaValorCriado = 'pessoas.lista_valor_criado';
    case ListaValorAtualizado = 'pessoas.lista_valor_atualizado';
    case ObrigatoriosAtualizados = 'pessoas.obrigatorios_atualizados';
    case ExportacaoPedida = 'exportacao.pedida';
    case ExportacaoConcluida = 'exportacao.concluida';
    case ExportacaoNegada = 'exportacao.negada';
    case WhatsappNumeroLigado = 'avisos.whatsapp_numero_ligado';
    case WhatsappNumeroDesligado = 'avisos.whatsapp_numero_desligado';
    case ConviteEmitido = 'acesso.convite_emitido';
    case ConviteRevogado = 'acesso.convite_revogado';
    case AcessoConcedido = 'acesso.concedido';
    case AcessoRevogado = 'acesso.revogado';
    case PapelCriado = 'autorizacao.papel_criado';
    case PapelAtualizado = 'autorizacao.papel_atualizado';
    case AtribuicaoConcedida = 'autorizacao.atribuicao_concedida';
    case AtribuicaoRevogada = 'autorizacao.atribuicao_revogada';
    case EscaladaBloqueada = 'authz.escalation_blocked';
    case UltimoAdministradorBloqueado = 'authz.last_admin_blocked';
    case AutorizacaoSuporteCriada = 'suporte.autorizacao_criada';
    case AutorizacaoSuporteRevogada = 'suporte.autorizacao_revogada';
    case AutorizacaoSuporteExpirada = 'suporte.autorizacao_expirada';
    case Elevacao = 'auth.elevacao';
    case PermissaoSolicitada = 'authz.permission_requested';
    case SolicitacaoPermissaoCancelada = 'authz.permission_request_cancelled';
    case SolicitacaoPermissaoAprovada = 'authz.permission_request_approved';
    case SolicitacaoPermissaoRecusada = 'authz.permission_request_rejected';
    case ConcessaoPermissaoRevogada = 'authz.permission_grant_revoked';
    case ConcessaoPermissaoExpirada = 'authz.permission_grant_expired';
    case OrganizacaoConfigurada = 'organizacao.configurada';
    case IgrejaCriada = 'organizacao.igreja_criada';
    case IgrejaAtualizada = 'organizacao.igreja_atualizada';

    /** @return list<string> */
    public function metadadosPermitidos(): array
    {
        return match ($this) {
            self::ExportacaoPedida, self::ExportacaoConcluida => ['filtro', 'categorias', 'contagem'],
            self::ExportacaoNegada => ['filtro', 'categorias', 'motivo_codigo'],
            self::PessoaMesclada, self::PessoaMesclagemDesfeita => ['pessoa_origem_id', 'pessoa_destino_id', 'operacao_id', 'acesso_movido'],
            self::ImportacaoConcluida, self::ImportacaoDesfeita => ['importacao_id', 'contagem'],
            self::UltimoAdministradorBloqueado => ['motivo_codigo'],
            // Convite e acesso (OIK-ADR-0003 §5): papel e escopo concedidos; o contato da pessoa fica fora.
            self::ConviteEmitido, self::ConviteRevogado => ['convite_id', 'papel_id', 'escopo_tipo', 'escopo_id'],
            self::AcessoConcedido => ['convite_id', 'papel_id', 'escopo_tipo', 'escopo_id', 'conta_nova'],
            // Transferência (FLOW-009): igrejas de origem e destino; a justificativa é texto livre e fica no pedido.
            self::TransferenciaSolicitada, self::TransferenciaAprovada, self::TransferenciaRecusada => ['transferencia_id', 'igreja_origem_id', 'igreja_destino_id'],
            // Permissões e escopo não são dado pessoal (OIK-ADR-0005 §5): antes e depois ficam na auditoria.
            self::PapelCriado => ['permissoes_depois'],
            self::PapelAtualizado => ['permissoes_antes', 'permissoes_depois', 'nome_alterado'],
            self::AtribuicaoConcedida, self::AtribuicaoRevogada => ['acesso_alvo_id', 'papel_id', 'permissoes', 'escopo_tipo', 'escopo_id'],
            self::EscaladaBloqueada => ['motivo_codigo', 'permissoes', 'escopo_tipo', 'escopo_id'],
            // Configuração da organização. O nome do item é texto livre: fica só `nome_alterado`.
            self::ListaValorCriado => ['lista', 'ordem_depois'],
            self::ListaValorAtualizado => ['lista', 'nome_alterado', 'ordem_antes', 'ordem_depois', 'ativo_antes', 'ativo_depois'],
            self::ObrigatoriosAtualizados => ['vinculo', 'campos_antes', 'campos_depois'],
            // Como a elevação abriu (`login` ou `dialogo`) e o segundo fator usado, sem código.
            self::Elevacao => ['via', 'segundo_fator'],
            // Solicitações temporárias (OIK-ADR-0012 §5, §8): permissões, escopos e categorias sensíveis
            // explícitas. A justificativa é texto livre e fica só na própria solicitação.
            self::PermissaoSolicitada, self::SolicitacaoPermissaoCancelada, self::SolicitacaoPermissaoRecusada,
            self::SolicitacaoPermissaoAprovada => ['solicitante_acesso_id', 'permissoes', 'escopos', 'categorias_sensiveis', 'duracao_horas', 'concessao_id', 'expira_em'],
            self::ConcessaoPermissaoRevogada, self::ConcessaoPermissaoExpirada => ['solicitacao_id', 'acesso_alvo_id', 'permissoes', 'escopos', 'categorias_sensiveis', 'expira_em'],
            // Configuração da organização e igrejas: os campos alterados vão em `campos`; nome e contato
            // são texto livre e ficam fora. O modo de matrícula, codificado, entra antes e depois.
            self::OrganizacaoConfigurada => ['modo_matricula_antes', 'modo_matricula_depois'],
            self::IgrejaCriada, self::IgrejaAtualizada => ['regional_id'],
            default => [],
        };
    }
}
