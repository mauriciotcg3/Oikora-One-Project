<?php

namespace App\Modules\Autorizacao\Application;

use App\Modules\Autorizacao\Contracts\PapeisProntos;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Cria (ou reaproveita, pelo campo `sistema`) os papéis prontos da organização em contexto. */
final readonly class CriarPapeisProntos implements PapeisProntos
{
    public function criar(int $organizacaoId): array
    {
        return [
            'administrador' => $this->papel($organizacaoId, 'administrador', 'Administrador', self::doAdministrador()),
            'secretaria' => $this->papel($organizacaoId, 'secretaria', 'Secretaria', self::daSecretaria()),
        ];
    }

    /** @return list<Permissao> */
    public static function doAdministrador(): array
    {
        return Permissao::cases();
    }

    /**
     * Toda a operação de Pessoas, sem `pessoas.configurar`, e as sensíveis documentos, contatos e fé.
     * Saúde fica de fora até o administrador marcar (OIK-ADR-0004 §3).
     *
     * @return list<Permissao>
     */
    public static function daSecretaria(): array
    {
        return array_values(array_filter(Permissao::cases(), static function (Permissao $permissao): bool {
            $valor = $permissao->value;
            if ($permissao === Permissao::PessoasConfigurar || str_starts_with($valor, 'pessoas.sensiveis.saude.')) {
                return false;
            }

            return str_starts_with($valor, 'pessoas.');
        }));
    }

    /** @param  list<Permissao>  $permissoes */
    private function papel(int $organizacaoId, string $sistema, string $nome, array $permissoes): int
    {
        $existente = DB::table('papeis')->where('organizacao_id', $organizacaoId)->where('sistema', $sistema)->value('id');
        if ($existente !== null) {
            return (int) $existente;
        }

        $papelId = (int) DB::table('papeis')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'nome' => $nome,
            'sistema' => $sistema,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('papel_permissoes')->insert(array_map(static fn (Permissao $permissao): array => [
            'organizacao_id' => $organizacaoId,
            'papel_id' => $papelId,
            'permissao' => $permissao->value,
        ], $permissoes));

        return $papelId;
    }
}
