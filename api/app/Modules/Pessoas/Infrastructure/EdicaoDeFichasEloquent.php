<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Pessoas\Application\Actions\EdicaoDeFichas;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Pela Pessoa já localizada no alcance: as tabelas filhas são alteradas só através dela. A comparação usa
 * o valor decifrado (a cifra muda a cada gravação), para auditar só o que mudou.
 *
 * @phpstan-import-type ContatoEditado from EdicaoDeFichas
 */
final class EdicaoDeFichasEloquent implements EdicaoDeFichas
{
    public function atualizarCampos(int $id, array $campos): array
    {
        $pessoa = Pessoa::query()->whereKey($id)->firstOrFail();

        $alterados = [];
        foreach ($campos as $coluna => $valor) {
            $atual = $pessoa->getAttribute($coluna);
            if ($atual instanceof DateTimeInterface) {
                $atual = $atual->format('Y-m-d');
            }
            if ($atual === $valor) {
                continue;
            }

            $pessoa->setAttribute($coluna, $valor);
            $alterados[] = $coluna;
        }

        if ($alterados !== []) {
            $pessoa->save();
        }

        return $alterados;
    }

    public function substituirContatos(int $organizacaoId, int $id, array $contatos): array
    {
        $atuais = PessoaContato::query()->where('pessoa_id', $id)->get()
            ->keyBy(static fn (PessoaContato $contato): string => $contato->getAttribute('tipo').'|'.$contato->getAttribute('valor'));

        $alterados = [];
        $mantidos = [];
        foreach ($contatos as $novo) {
            $chave = $novo['tipo'].'|'.$novo['valor'];
            $mantidos[] = $chave;
            $existente = $atuais->get($chave);

            if ($existente === null) {
                PessoaContato::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'organizacao_id' => $organizacaoId,
                    'pessoa_id' => $id,
                    ...$novo,
                ]);
                $alterados[] = $novo['tipo'];
                if ($novo['e_whatsapp']) {
                    $alterados[] = 'e_whatsapp';
                }

                continue;
            }

            if ((bool) $existente->getAttribute('e_whatsapp') !== $novo['e_whatsapp']) {
                $alterados[] = 'e_whatsapp';
            }
            if ((bool) $existente->getAttribute('principal') !== $novo['principal']) {
                $alterados[] = $novo['tipo'];
            }
            $existente->forceFill(['e_whatsapp' => $novo['e_whatsapp'], 'principal' => $novo['principal']])->save();
        }

        foreach ($atuais as $chave => $removido) {
            if (! in_array($chave, $mantidos, true)) {
                $alterados[] = (string) $removido->getAttribute('tipo');
                if ((bool) $removido->getAttribute('e_whatsapp')) {
                    $alterados[] = 'e_whatsapp';
                }
                $removido->delete();
            }
        }

        return array_values(array_unique($alterados));
    }

    public function atualizarEndereco(int $organizacaoId, int $id, array $endereco): array
    {
        $atual = PessoaEndereco::query()->where('pessoa_id', $id)->orderBy('id')->first()
            ?? new PessoaEndereco(['uuid' => (string) Str::uuid(), 'organizacao_id' => $organizacaoId, 'pessoa_id' => $id]);

        $alterados = [];
        foreach ($endereco as $campo => $valor) {
            if ($atual->getAttribute($campo) !== $valor) {
                $atual->setAttribute($campo, $valor);
                $alterados[] = $campo;
            }
        }

        if ($alterados !== []) {
            $atual->save();
        }

        return $alterados;
    }
}
