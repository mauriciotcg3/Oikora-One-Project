<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Avisos\Contracts\EnvioCodigo;
use App\Modules\Identidade\Contracts\CodigoParaEnvio;
use App\Modules\Identidade\Contracts\CodigosDeIdentidade;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;

final class EmissaoDeCodigos implements CodigosDeIdentidade
{
    public const DOIS_FATORES = 'dois_fatores';

    public const RECUPERAR_SENHA = 'recuperar_senha';

    public function __construct(
        private readonly Codigos $codigos,
        private readonly Contas $contas,
        private readonly EnvioCodigo $envio,
        private readonly Encrypter $cifra,
    ) {}

    public function emitir(int $contaId, string $finalidade, string $canal, ?string $identificadorResumo = null): int
    {
        $segredo = $this->gerar($finalidade);

        $id = $this->codigos->criar(
            contaId: $contaId,
            finalidade: $finalidade,
            canal: $canal,
            hash: Resumo::de($segredo),
            cifrado: $this->cifra->encryptString($segredo),
            expiraEm: now()->addMinutes($this->validadeEmMinutos($finalidade)),
            identificadorResumo: $identificadorResumo,
        );

        $this->envio->enviar($id);

        return $id;
    }

    public function paraEnvio(int $codigoId): ?CodigoParaEnvio
    {
        $codigo = $this->codigos->aberto($codigoId);

        if ($codigo === null || $codigo->cifrado === null) {
            return null;
        }

        return new CodigoParaEnvio(
            codigo: $this->cifra->decryptString($codigo->cifrado),
            destino: $this->contas->destinoDeEnvio($codigo->contaId, $codigo->canal),
            canal: $codigo->canal,
            finalidade: $codigo->finalidade,
        );
    }

    public function registrarEnvio(int $codigoId, string $estado, string $canal, ?string $idExterno = null, ?string $erro = null): void
    {
        $this->codigos->registrarEnvio($codigoId, $estado, $canal, $idExterno, $erro);
    }

    /** O token da recuperação é longo porque vale 60 min: 6 dígitos dariam tempo para força bruta. */
    private function gerar(string $finalidade): string
    {
        return $finalidade === self::RECUPERAR_SENHA
            ? Str::random(64)
            : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function validadeEmMinutos(string $finalidade): int
    {
        return $finalidade === self::RECUPERAR_SENHA
            ? (int) config('oikora.recuperacao.validade_minutos')
            : (int) config('oikora.codigos.validade_minutos');
    }
}
