<?php

namespace App\Modules\Auditoria\Application;

use App\Modules\Auditoria\Domain\EventoAuditoria;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class Auditoria
{
    /**
     * @param  list<string>  $campos
     * @param  array<string, bool|int|float|string|null|list<bool|int|float|string|null>>  $metadados
     */
    public function registrar(
        EventoAuditoria $evento,
        int $organizacaoId,
        string $alvoTipo,
        int|string $alvoId,
        ?int $acessoId = null,
        ?int $contaId = null,
        ?int $operadorId = null,
        ?int $autorizacaoSuporteId = null,
        ?int $emNomeDePessoaId = null,
        ?int $igrejaId = null,
        array $campos = [],
        array $metadados = [],
        string $canal = 'painel',
        ?string $requestId = null,
    ): void {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('auditoria.exige_transacao');
        }

        $this->validarCampos($campos);
        $this->validarMetadados($evento, $metadados);

        DB::table('auditoria')->insert([
            'organizacao_id' => $organizacaoId,
            'ocorrido_em' => now(),
            'evento' => $evento->value,
            'acesso_id' => $acessoId,
            'conta_id' => $contaId,
            'operador_id' => $operadorId,
            'autorizacao_suporte_id' => $autorizacaoSuporteId,
            'em_nome_de_pessoa_id' => $emNomeDePessoaId,
            'alvo_tipo' => $alvoTipo,
            'alvo_id' => (string) $alvoId,
            'igreja_id' => $igrejaId,
            'campos' => json_encode($campos, JSON_THROW_ON_ERROR),
            'metadados' => json_encode((object) $metadados, JSON_THROW_ON_ERROR),
            'canal' => $canal,
            'request_id' => $requestId ?? Context::get('request_id'),
        ]);
    }

    /** @param list<string> $campos */
    private function validarCampos(array $campos): void
    {
        foreach ($campos as $campo) {
            if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $campo) !== 1) {
                throw new InvalidArgumentException('auditoria.campo_invalido');
            }
        }
    }

    /** @param array<string, mixed> $metadados */
    private function validarMetadados(EventoAuditoria $evento, array $metadados): void
    {
        $naoPermitidos = array_diff(array_keys($metadados), $evento->metadadosPermitidos());
        if ($naoPermitidos !== []) {
            throw new InvalidArgumentException('auditoria.metadado_fora_do_catalogo');
        }

        array_walk_recursive($metadados, static function (mixed $valor): void {
            if (is_string($valor) && preg_match('/^[a-zA-Z0-9_.:-]{1,120}$/', $valor) !== 1) {
                throw new InvalidArgumentException('auditoria.texto_livre_proibido');
            }

            if (! is_string($valor) && ! is_int($valor) && ! is_float($valor) && ! is_bool($valor) && $valor !== null) {
                throw new InvalidArgumentException('auditoria.metadado_invalido');
            }
        });
    }
}
