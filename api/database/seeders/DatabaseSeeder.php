<?php

namespace Database\Seeders;

use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Só dados fictícios e só no dev (OIK-ADR-0011 §4): uma conta sem 2FA e uma com, para testar o login. */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        // Organização fictícia pelo mesmo comando do operador (T-23); repetir o seed não duplica.
        Artisan::call('oikora:organizacao:provisionar', [
            'identificador' => 'demonstracao',
            '--nome' => 'Igreja Demonstração',
            '--igreja' => 'Sede',
            '--admin-nome' => 'Administração Demo',
            '--admin-email' => 'admin@oikora.test',
            '--operador' => 'seeder',
            '--motivo' => 'dados_ficticios',
        ]);

        $db = DB::connection('pgsql_dono');

        $db->table('contas')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'email' => 'demo@oikora.test',
            'celular' => '+5511999990000',
            'password' => Hash::make('oikora123'),
            'estado' => 'ativa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $db->table('contas')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'email' => 'demo2fa@oikora.test',
            'email_verificado_em' => now(),
            'celular' => '+5511999990001',
            'celular_verificado_em' => now(),
            'password' => Hash::make('oikora123'),
            'estado' => 'ativa',
            'dois_fatores_canal' => 'email',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->criarAcessoTotalDePrevia();
    }

    /**
     * A conta de prévia precisa alcançar todas as telas sem fingir ser o papel Administrador, que
     * corretamente exige 2FA. O papel customizado conserva as mesmas permissões sem o marcador de
     * sistema usado pela regra de segundo fator.
     */
    private function criarAcessoTotalDePrevia(): void
    {
        $db = DB::connection('pgsql_dono');

        $db->transaction(function () use ($db): void {
            $organizacaoId = (int) $db->table('organizacoes')
                ->where('identificador', 'demonstracao')
                ->value('id');
            $igrejaId = (int) $db->table('igrejas')
                ->where('organizacao_id', $organizacaoId)
                ->orderBy('id')
                ->value('id');
            $contaId = (int) $db->table('contas')->where('email', 'demo@oikora.test')->value('id');

            $papelId = $db->table('papeis')
                ->where('organizacao_id', $organizacaoId)
                ->where('nome', 'Acesso total de prévia')
                ->value('id');

            if ($papelId === null) {
                $papelId = $db->table('papeis')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'organizacao_id' => $organizacaoId,
                    'nome' => 'Acesso total de prévia',
                    'sistema' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $permissoes = array_map(static fn (Permissao $permissao): array => [
                'organizacao_id' => $organizacaoId,
                'papel_id' => (int) $papelId,
                'permissao' => $permissao->value,
            ], Permissao::cases());
            $db->table('papel_permissoes')->insertOrIgnore($permissoes);

            $acessoId = $db->table('acessos')
                ->where('organizacao_id', $organizacaoId)
                ->where('conta_id', $contaId)
                ->where('estado', 'ativo')
                ->value('id');

            if ($acessoId === null) {
                $pessoaId = $db->table('pessoas')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'organizacao_id' => $organizacaoId,
                    'igreja_id' => $igrejaId,
                    'nome' => 'Usuário de Prévia',
                    'nome_busca' => 'usuario de previa',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $acessoId = $db->table('acessos')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'organizacao_id' => $organizacaoId,
                    'conta_id' => $contaId,
                    'pessoa_id' => $pessoaId,
                    'estado' => 'ativo',
                    'origem' => 'convite',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $atribuida = $db->table('atribuicoes_papel')
                ->where('organizacao_id', $organizacaoId)
                ->where('acesso_id', $acessoId)
                ->where('papel_id', $papelId)
                ->where('escopo_tipo', 'organizacao')
                ->whereNull('escopo_id')
                ->exists();

            if (! $atribuida) {
                $db->table('atribuicoes_papel')->insert([
                    'uuid' => (string) Str::uuid(),
                    'organizacao_id' => $organizacaoId,
                    'acesso_id' => $acessoId,
                    'papel_id' => $papelId,
                    'escopo_tipo' => 'organizacao',
                    'escopo_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
