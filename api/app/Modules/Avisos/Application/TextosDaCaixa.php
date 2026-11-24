<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;

/**
 * Texto de cada tipo, montado na leitura (OIK-ADR-0008 §7): o banco guarda só tipo, referência e
 * destino. Entra apenas o nome da organização; nada da ficha, então o texto não depende do alcance de
 * quem lê e não vaza dado de uma Pessoa fora dele. O e-mail usa os mesmos textos.
 */
final class TextosDaCaixa
{
    /** @return array{titulo: string, texto: string, acao: string} */
    public static function do(TipoAviso $tipo, string $organizacao): array
    {
        [$titulo, $texto, $acao] = match ($tipo) {
            TipoAviso::ConvitePainel => ['Convite para o painel', "Você foi convidado(a) para acessar o painel de {$organizacao} no Oikora.", 'Aceitar convite'],
            TipoAviso::TransferenciaSolicitada => ['Pedido de transferência', "Há um pedido de transferência aguardando sua análise em {$organizacao}.", 'Ver pedido'],
            TipoAviso::TransferenciaDecidida => ['Transferência decidida', "Um pedido de transferência de {$organizacao} foi decidido.", 'Ver transferência'],
            TipoAviso::ExportacaoPronta => ['Exportação pronta', "A exportação que você pediu em {$organizacao} está pronta para baixar.", 'Baixar arquivo'],
            TipoAviso::LimiteWhatsappAlerta => ['Limite de WhatsApp perto do fim', "{$organizacao} já usou 80% do limite mensal de mensagens de WhatsApp.", 'Ver configuração'],
            TipoAviso::LimiteWhatsappAtingido => ['Limite de WhatsApp atingido', "{$organizacao} atingiu o limite mensal de WhatsApp. Até o próximo mês, os avisos de serviço seguem por e-mail e pela caixa de avisos; convites continuam.", 'Ver configuração'],
        };

        return ['titulo' => $titulo, 'texto' => $texto, 'acao' => $acao];
    }
}
