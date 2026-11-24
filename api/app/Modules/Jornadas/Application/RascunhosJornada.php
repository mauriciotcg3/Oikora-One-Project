<?php

namespace App\Modules\Jornadas\Application;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Jornadas\Domain\TipoJornada;
use App\Shared\Cifra\CampoSensivelEncrypter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** @phpstan-type Linha object{tipo: string, etapa: string, dados: string, dados_sensiveis: string|null, versao: int, updated_at: mixed, expira_em: mixed} */
final class RascunhosJornada
{
    public function __construct(
        private readonly AlcanceAtual $alcance,
        private readonly CampoSensivelEncrypter $cifra,
    ) {}

    /** @return array<string, mixed>|null */
    public function ler(TipoJornada $tipo): ?array
    {
        [$organizacao, $conta] = $this->identidade();
        /** @var Linha|null $linha */
        $linha = DB::table('rascunhos_jornada')->where([
            'organizacao_id' => $organizacao, 'conta_id' => $conta, 'tipo' => $tipo->value,
        ])->first();

        return $linha === null ? null : $this->serializar($linha);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function salvar(TipoJornada $tipo, string $etapa, array $dados, int $versao): array
    {
        [$organizacao, $conta] = $this->identidade();
        $publicos = array_intersect_key($dados, array_flip($tipo->camposPublicos()));
        $sensiveis = array_intersect_key($dados, array_flip($tipo->camposSensiveis()));

        $resultado = DB::transaction(function () use ($tipo, $etapa, $publicos, $sensiveis, $versao, $organizacao, $conta): object {
            $consulta = DB::table('rascunhos_jornada')->where([
                'organizacao_id' => $organizacao, 'conta_id' => $conta, 'tipo' => $tipo->value,
            ]);
            /** @var Linha|null $atual */
            $atual = $consulta->lockForUpdate()->first();
            if (($atual === null && $versao !== 0) || ($atual !== null && (int) $atual->versao !== $versao)) {
                throw new RascunhoDesatualizado($atual === null ? null : $this->serializar($atual));
            }

            $novaVersao = $versao + 1;
            $valores = [
                'etapa' => $etapa,
                'dados' => json_encode($publicos, JSON_THROW_ON_ERROR),
                'dados_sensiveis' => $sensiveis === [] ? null : $this->cifra->encryptString(json_encode($sensiveis, JSON_THROW_ON_ERROR)),
                'versao' => $novaVersao,
                'expira_em' => now()->addDays(30),
                'updated_at' => now(),
            ];
            if ($atual === null) {
                DB::table('rascunhos_jornada')->insert([
                    'organizacao_id' => $organizacao, 'conta_id' => $conta, 'tipo' => $tipo->value,
                    'created_at' => now(), ...$valores,
                ]);
            } else {
                $consulta->update($valores);
            }

            /** @var Linha $salvo */
            $salvo = $consulta->firstOrFail();

            return $salvo;
        });

        Log::info('jornada.rascunho_salvo', ['tipo' => $tipo->value, 'etapa' => $etapa, 'resultado' => 'sucesso']);

        return $this->serializar($resultado);
    }

    public function descartar(TipoJornada $tipo): void
    {
        [$organizacao, $conta] = $this->identidade();
        DB::table('rascunhos_jornada')->where([
            'organizacao_id' => $organizacao, 'conta_id' => $conta, 'tipo' => $tipo->value,
        ])->delete();
        Log::info('jornada.rascunho_descartado', ['tipo' => $tipo->value, 'resultado' => 'sucesso']);
    }

    /** @return array{int, int} */
    private function identidade(): array
    {
        if (! $this->alcance->temNaOrganizacao(Permissao::AvisosWhatsappGerir)) {
            throw new AuthorizationException;
        }
        $ator = $this->alcance->ator();

        return [$ator->organizacaoId, $ator->contaId];
    }

    /**
     * @param  Linha  $linha
     * @return array<string, mixed>
     */
    private function serializar(object $linha): array
    {
        $publicos = json_decode((string) $linha->dados, true, flags: JSON_THROW_ON_ERROR);
        $sensiveis = $linha->dados_sensiveis === null ? [] : json_decode($this->cifra->decryptString($linha->dados_sensiveis), true, flags: JSON_THROW_ON_ERROR);

        return [
            'tipo' => $linha->tipo,
            'etapa' => $linha->etapa,
            'dados' => [...$publicos, ...$sensiveis],
            'versao' => (int) $linha->versao,
            'atualizado_em' => $linha->updated_at,
            'expira_em' => $linha->expira_em,
        ];
    }
}
