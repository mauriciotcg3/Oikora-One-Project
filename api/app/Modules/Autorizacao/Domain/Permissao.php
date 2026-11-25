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

    /**
     * Pode ser pedida por concessão temporária (OIK-ADR-0012 §6): governança e exportação, não. A
     * própria aprovação de solicitações também não, para ninguém decidir pedidos por concessão.
     */
    public function solicitavel(): bool
    {
        return ! in_array($this, [
            self::PapeisGerir,
            self::AcessosGerir,
            self::PessoasExportar,
            self::SolicitacoesPermissaoAprovar,
        ], true);
    }

    /** Rótulo estável para clientes que exibem o catálogo de permissões. */
    public function rotulo(): string
    {
        return match ($this) {
            self::OrganizacaoConfigurar => 'Configurar organização',
            self::IgrejasGerir => 'Gerir igrejas',
            self::AcessosGerir => 'Gerir acessos',
            self::PapeisGerir => 'Gerir papéis',
            self::AuditoriaVer => 'Ver auditoria',
            self::AvisosWhatsappGerir => 'Gerir avisos por WhatsApp',
            self::PessoasConfigurar => 'Configurar pessoas',
            self::SolicitacoesPermissaoAprovar => 'Aprovar solicitações temporárias',
            self::PessoasVer => 'Ver pessoas',
            self::PessoasCriar => 'Cadastrar pessoas',
            self::PessoasEditar => 'Editar pessoas',
            self::PessoasExcluir => 'Excluir pessoas',
            self::PessoasExportar => 'Exportar pessoas',
            self::PessoasMesclar => 'Mesclar pessoas',
            self::TransferenciasSolicitar => 'Solicitar transferências',
            self::TransferenciasAprovar => 'Aprovar transferências',
            self::SaudeVer => 'Ver dados de saúde',
            self::SaudeEditar => 'Editar dados de saúde',
            self::DocumentosVer => 'Ver documentos',
            self::DocumentosEditar => 'Editar documentos',
            self::ContatosVer => 'Ver contatos',
            self::ContatosEditar => 'Editar contatos',
            self::FeVer => 'Ver dados de fé',
            self::FeEditar => 'Editar dados de fé',
        };
    }

    public function grupo(): string
    {
        return match (true) {
            str_starts_with($this->value, 'pessoas.sensiveis.') => 'Dados sensíveis',
            str_starts_with($this->value, 'pessoas.') => 'Pessoas',
            default => 'Administração',
        };
    }

    /** Categoria sensível (`saude`, `documentos`, `contatos`, `fe`), ou null (OIK-ADR-0012 §5). */
    public function categoriaSensivel(): ?string
    {
        return preg_match('/^pessoas\.sensiveis\.([a-z]+)\./', $this->value, $partes) === 1 ? $partes[1] : null;
    }
}
