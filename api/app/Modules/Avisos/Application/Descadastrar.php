<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Descadastro de pessoa sem conta, gravado no contato da ficha (OIK-ADR-0008 §13): pelo link assinado do
 * e-mail (RFC 8058) ou pela palavra "SAIR" no WhatsApp da organização. Vale para os tipos não obrigatórios.
 */
final readonly class Descadastrar
{
    public const PALAVRA_WHATSAPP = 'SAIR';

    public function __construct(
        private ConsultaOrganizacoes $organizacoes,
        private ContatosDaPessoa $contatos,
    ) {}

    /** Sem sessão: abre o contexto da organização do link. Devolve se algum contato mudou. */
    public function porLink(string $organizacaoUuid, string $pessoaUuid, string $canal): bool
    {
        $organizacaoId = $this->organizacoes->idPorUuid($organizacaoUuid);
        if ($organizacaoId === null) {
            return false;
        }

        return DB::transaction(function () use ($organizacaoId, $pessoaUuid, $canal): bool {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);
            $mudou = $this->contatos->descadastrar($pessoaUuid, $canal);
            Log::info('avisos.descadastro', [
                'module' => 'Avisos', 'origem' => 'link', 'canal' => $canal, 'status' => $mudou ? 'descadastrado' : 'sem_mudanca',
            ]);

            return $mudou;
        });
    }

    /** Chamado pelo webhook de mensagens recebidas (T-19), já no contexto da organização do número. */
    public function porMensagemWhatsApp(string $celular, string $texto): int
    {
        if (mb_strtoupper(trim($texto)) !== self::PALAVRA_WHATSAPP) {
            return 0;
        }

        $quantidade = $this->contatos->descadastrarWhatsApp($celular);
        Log::info('avisos.descadastro', [
            'module' => 'Avisos', 'origem' => 'whatsapp', 'canal' => 'whatsapp', 'status' => $quantidade > 0 ? 'descadastrado' : 'sem_mudanca',
        ]);

        return $quantidade;
    }
}
