<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Limite mensal de WhatsApp da organização (OIK-ADR-0008 §16; U-04, valor em aberto). Sem limite
 * (null), não há corte. Com o limite atingido, serviço e automáticas deixam de usar WhatsApp e seguem
 * pelos outros canais; convite continua. Leitura no contexto RLS da organização.
 */
final readonly class LimiteDeWhatsApp
{
    public function __construct(private ConsultaOrganizacoes $organizacoes) {}

    public function bloqueia(int $organizacaoId, TipoAviso $tipo): bool
    {
        if ($tipo->categoria() === 'convite') {
            return false;
        }

        $limite = $this->organizacoes->limiteWhatsappMensal($organizacaoId);

        return $limite !== null && $this->usadoNoMes($organizacaoId, $this->mes($organizacaoId)) >= $limite;
    }

    public function limite(int $organizacaoId): ?int
    {
        return $this->organizacoes->limiteWhatsappMensal($organizacaoId);
    }

    public function usadoNoMes(int $organizacaoId, string $mes): int
    {
        return (int) DB::table('uso_mensageria')
            ->where('organizacao_id', $organizacaoId)
            ->where('mes', $mes)
            ->where('canal', 'whatsapp')
            ->sum('quantidade');
    }

    /** Primeiro dia do mês corrente no fuso da organização (`Y-m-d`). */
    public function mes(int $organizacaoId): string
    {
        $fuso = $this->organizacoes->perfilDeEnvio($organizacaoId)->fuso;

        return CarbonImmutable::now($fuso)->startOfMonth()->format('Y-m-d');
    }
}
