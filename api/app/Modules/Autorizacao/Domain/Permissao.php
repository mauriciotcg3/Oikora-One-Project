<?php

namespace App\Modules\Autorizacao\Domain;

enum Permissao: string
{
    case OrganizacaoConfigurar = 'organizacao.configurar';
    case IgrejasGerir = 'igrejas.gerir';
    case AcessosGerir = 'acessos.gerir';
    case PapeisGerir = 'papeis.gerir';
    case AuditoriaVer = 'auditoria.ver';
    case AvisosWhatsappGerir = 'avisos.whatsapp.gerir';
    case PessoasConfigurar = 'pessoas.configurar';
    case SolicitacoesPermissaoAprovar = 'permissoes.solicitacoes.aprovar';
    case PessoasVer = 'pessoas.ver';
    case PessoasCriar = 'pessoas.criar';
    case PessoasEditar = 'pessoas.editar';
    case PessoasExcluir = 'pessoas.excluir';
    case PessoasExportar = 'pessoas.exportar';
    case PessoasMesclar = 'pessoas.mesclar';
    case TransferenciasSolicitar = 'pessoas.transferencias.solicitar';
    case TransferenciasAprovar = 'pessoas.transferencias.aprovar';
    case SaudeVer = 'pessoas.sensiveis.saude.ver';
    case SaudeEditar = 'pessoas.sensiveis.saude.editar';
    case DocumentosVer = 'pessoas.sensiveis.documentos.ver';
    case DocumentosEditar = 'pessoas.sensiveis.documentos.editar';
    case ContatosVer = 'pessoas.sensiveis.contatos.ver';
    case ContatosEditar = 'pessoas.sensiveis.contatos.editar';
    case FeVer = 'pessoas.sensiveis.fe.ver';
    case FeEditar = 'pessoas.sensiveis.fe.editar';
}
