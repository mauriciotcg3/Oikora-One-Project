<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Avisos\Domain\JanelaDiurna;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Organizacao\Contracts\PerfilDeEnvio;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

/**
 * Uma tentativa de envio (OIK-ADR-0008 §3, §4, §12, §14). Roda dentro da transação do job (contexto
 * RLS): trava o envio com `FOR UPDATE SKIP LOCKED`, confere o estado e a janela diurna e só então chama
 * o provedor. Falha definitiva de aviso de serviço passa para o próximo canal. Devolve a espera em
 * segundos para a próxima tentativa, ou null quando não há o que tentar de novo.
 */
final readonly class EntregarAviso
{
    /** Espera depois da 1ª, 2ª, 3ª e 4ª falha temporária; a 5ª vira definitiva. */
    public const ESPERAS = [60, 300, 1800, 7200];

    public const MAXIMO_DE_TENTATIVAS = 5;

    public function __construct(
        private CanaisDeAviso $canais,
        private DestinosDeAviso $destinos,
        private ConsultaOrganizacoes $organizacoes,
        private NumerosWhatsApp $numeros,
        private ReenviarPeloProximoCanal $reenvio,
        private LinksDeDescadastro $links,
        private ContatosDaPessoa $contatos,
    ) {}

    public function executar(int $envioId): ?int
    {
        $envio = DB::selectOne(
            'select e.id, e.aviso_id, e.canal, e.estado, e.tentativas, a.organizacao_id, a.tipo, a.conta_id, a.pessoa_id,
                    a.destino, a.uuid as aviso_uuid
               from envios e join avisos a on a.id = e.aviso_id
              where e.id = ?
              for update of e skip locked',
            [$envioId],
        );

        // Outro worker está com ele, ou já não existe: nada a fazer.
        if (! $envio instanceof stdClass) {
            return null;
        }

        if (! EstadoEnvio::from($envio->estado)->podeEnviar()) {
            return null;
        }

        $tipo = TipoAviso::from($envio->tipo);
        $canal = (string) $envio->canal;
        $organizacaoId = (int) $envio->organizacao_id;
        $perfil = $this->organizacoes->perfilDeEnvio($organizacaoId);

        // A janela vale também para nova tentativa e reenvio: fora dela, volta a esperar as 8h.
        $proximo = $tipo->esperaJanelaDiurna() ? JanelaDiurna::proximoEnvio(now()->toImmutable(), $perfil->fuso) : null;
        if ($proximo !== null) {
            $this->atualizar($envioId, ['estado' => EstadoEnvio::Agendado->value, 'agendado_para' => $proximo, 'enfileirado_em' => null]);

            return null;
        }

        if ($canal === 'whatsapp' && $this->numeros->daOrganizacao($organizacaoId) === null) {
            $this->falharDefinitivo($envio, 'sem_numero_whatsapp');

            return null;
        }

        $contaId = $envio->conta_id === null ? null : (int) $envio->conta_id;
        $pessoaId = $envio->pessoa_id === null ? null : (int) $envio->pessoa_id;
        $endereco = $this->destinos->endereco($contaId, $pessoaId, $canal, ! $tipo->obrigatorio());

        if ($endereco === null) {
            $this->falharDefinitivo($envio, 'sem_destino');

            return null;
        }

        $tentativa = (int) $envio->tentativas + 1;
        $adapter = $this->canais->doCanal($canal);
        $this->atualizar($envioId, [
            'estado' => EstadoEnvio::Enviando->value,
            'tentativas' => $tentativa,
            'enviando_em' => now(),
            'provedor' => $adapter->provedor(),
            'destino_mascarado' => $this->mascarar($endereco),
            'destino_hash' => hash_hmac('sha256', strtolower($endereco), (string) config('oikora.chave_resumo')),
        ]);

        try {
            $idExterno = $adapter->enviarAviso(new MensagemDeAviso(
                tipo: $tipo,
                canal: $canal,
                destino: $endereco,
                link: $envio->destino === null ? null : (string) $envio->destino,
                chaveDeIdempotencia: $envio->aviso_uuid.':'.$canal,
                organizacaoId: $organizacaoId,
                nomeOrganizacao: $perfil->nome,
                responderPara: $perfil->responderPara,
                linkDescadastro: $this->linkDescadastro($tipo, $canal, $contaId, $pessoaId, $perfil),
            ));
        } catch (FalhaDefinitivaDeEnvio $falha) {
            $this->falharDefinitivo($envio, $falha->getMessage());

            return null;
        } catch (Throwable $falha) {
            // Temporária, ou erro não classificado: nunca grava a mensagem crua, que pode ter dado pessoal.
            return $this->falharTemporario($envio, $tentativa, $falha instanceof FalhaTemporariaDeEnvio ? $falha->getMessage() : 'erro_inesperado');
        }

        $this->atualizar($envioId, [
            'estado' => EstadoEnvio::Enviado->value,
            'id_externo' => $idExterno,
            'enviado_em' => now(),
            'erro_codigo' => null,
        ]);
        Log::info('envio.enviado', ['canal' => $canal, 'tentativa' => $tentativa]);

        return null;
    }

    /** E-mail não obrigatório para pessoa sem conta leva o descadastro em um clique (RFC 8058). */
    private function linkDescadastro(TipoAviso $tipo, string $canal, ?int $contaId, ?int $pessoaId, PerfilDeEnvio $perfil): ?string
    {
        if ($tipo->obrigatorio() || $canal !== 'email' || $contaId !== null || $pessoaId === null) {
            return null;
        }

        $pessoaUuid = $this->contatos->uuid($pessoaId);

        return $pessoaUuid === null ? null : $this->links->paraPessoa($perfil->uuid, $pessoaUuid, 'email');
    }

    private function falharTemporario(stdClass $envio, int $tentativa, string $motivo): ?int
    {
        if ($tentativa >= self::MAXIMO_DE_TENTATIVAS) {
            $this->falharDefinitivo($envio, 'tentativas_esgotadas');

            return null;
        }

        $this->atualizar((int) $envio->id, ['estado' => EstadoEnvio::FalhouTemporario->value, 'erro_codigo' => $motivo]);
        Log::warning('envio.falhou_temporario', ['motivo' => $motivo, 'tentativa' => $tentativa]);

        return self::ESPERAS[$tentativa - 1];
    }

    private function falharDefinitivo(stdClass $envio, string $motivo): void
    {
        $this->atualizar((int) $envio->id, [
            'estado' => EstadoEnvio::FalhouDefinitivo->value,
            'erro_codigo' => $motivo,
            'falhou_em' => now(),
        ]);
        Log::warning('envio.falhou_definitivo', ['motivo' => $motivo]);

        $this->reenvio->reenviar((int) $envio->aviso_id, $motivo);
    }

    /** @param  array<string, mixed>  $campos */
    private function atualizar(int $envioId, array $campos): void
    {
        DB::table('envios')->where('id', $envioId)->update([...$campos, 'updated_at' => now()]);
    }

    private function mascarar(string $endereco): string
    {
        if (str_contains($endereco, '@')) {
            [$usuario, $dominio] = explode('@', $endereco, 2);

            return mb_substr($usuario, 0, 1).'***@'.$dominio;
        }

        return '***'.substr(preg_replace('/\D+/', '', $endereco) ?? '', -4);
    }
}
