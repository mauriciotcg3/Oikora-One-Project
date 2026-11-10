<?php

namespace App\Modules\Avisos\Domain;

/**
 * Catálogo de tipos de aviso desta Spec (OIK-ADR-0008 §1, §12; plan.md §3.6). Códigos não passam por
 * aqui: têm a via estreita da fila `codigos`. Regras por categoria, janela diurna e preferências: T-18.
 */
enum TipoAviso: string
{
    case ConvitePainel = 'convite.painel';
    case TransferenciaSolicitada = 'servico.transferencia_solicitada';
    case TransferenciaDecidida = 'servico.transferencia_decidida';
    case ExportacaoPronta = 'servico.exportacao_pronta';

    public function categoria(): string
    {
        return match ($this) {
            self::ConvitePainel => 'convite',
            default => 'servico',
        };
    }

    /** Convites são obrigatórios: não respeitam preferência de desligar (RN-21). */
    public function obrigatorio(): bool
    {
        return $this === self::ConvitePainel;
    }

    /**
     * Canais em que nasce um envio, na ordem padrão. Convite sai nos dois; aviso de serviço sai por um
     * canal externo (mais a caixa de avisos), com o próximo da ordem só na falha definitiva (T-18).
     *
     * @return list<string>
     */
    public function canais(): array
    {
        return match ($this) {
            self::ConvitePainel => ['email', 'whatsapp'],
            default => ['email'],
        };
    }

    /** Categoria de template do WhatsApp (Meta). */
    public function categoriaTemplateWhatsapp(): string
    {
        return 'utility';
    }

    public function esperaJanelaDiurna(): bool
    {
        return false;
    }
}
