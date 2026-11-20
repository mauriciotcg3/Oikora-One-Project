<?php

namespace App\Modules\Avisos\Domain;

/**
 * Catálogo de tipos de aviso desta Spec (OIK-ADR-0008 §1, §12; plan.md §3.6). Códigos não passam por
 * aqui: têm a via estreita da fila `codigos`. Cada tipo define categoria, obrigatoriedade, canais e ordem,
 * janela diurna e reenvio pelo próximo canal (RN-21).
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

    /** Convites e avisos de serviço são obrigatórios: a pessoa não os desliga (RN-21). */
    public function obrigatorio(): bool
    {
        return in_array($this->categoria(), ['convite', 'servico'], true);
    }

    /**
     * Canais externos na ordem de preferência. Convite sai em todos que a pessoa tiver; serviço sai pelo
     * primeiro disponível (WhatsApp da organização, senão e-mail) e usa o seguinte só no reenvio.
     *
     * @return list<string>
     */
    public function canais(): array
    {
        return match ($this->categoria()) {
            'convite' => ['email', 'whatsapp'],
            default => ['whatsapp', 'email'],
        };
    }

    /** Convite: e-mail e WhatsApp juntos, quando houver os dois contatos (OIK-ADR-0008 §12). */
    public function enviaEmTodosOsCanais(): bool
    {
        return $this->categoria() === 'convite';
    }

    /** Serviço: a pessoa troca o canal externo pela preferência, sem poder desligar o aviso. */
    public function permiteEscolherCanal(): bool
    {
        return $this->categoria() === 'servico';
    }

    /** Serviço: falha definitiva, ou WhatsApp sem entrega em 30 min, passa para o próximo canal. */
    public function reenviaPeloProximoCanal(): bool
    {
        return $this->categoria() === 'servico';
    }

    /** Categoria de template do WhatsApp (Meta). */
    public function categoriaTemplateWhatsapp(): string
    {
        return 'utility';
    }

    /** Serviço espera 8h–21h no fuso da organização; códigos e convites saem na hora (§14). */
    public function esperaJanelaDiurna(): bool
    {
        return $this->categoria() === 'servico';
    }

    /** @return list<string> */
    public static function queReenviam(): array
    {
        return array_values(array_map(
            static fn (self $tipo): string => $tipo->value,
            array_filter(self::cases(), static fn (self $tipo): bool => $tipo->reenviaPeloProximoCanal()),
        ));
    }
}
