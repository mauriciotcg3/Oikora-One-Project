<?php

namespace App\Modules\Organizacao\Presentation\Console;

use App\Modules\Organizacao\Application\DadosProvisionamento;
use App\Modules\Organizacao\Application\ProvisionarOrganizacao as Provisionar;
use App\Modules\Organizacao\Domain\Fuso;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * `php artisan oikora:organizacao:provisionar` (T-23; plan.md §3.3, §4). Comando de operador: cria a
 * organização com os papéis prontos, a primeira igreja e o convite do primeiro administrador, pela
 * conexão de plataforma, com o registro em `auditoria_plataforma`.
 */
final class ProvisionarOrganizacao extends Command
{
    protected $signature = 'oikora:organizacao:provisionar
        {identificador : Identificador estável (minúsculas, números e hífen); repetir não duplica}
        {--nome= : Nome da organização}
        {--igreja= : Nome da primeira igreja (padrão: o nome da organização)}
        {--admin-nome= : Nome do primeiro administrador}
        {--admin-email= : E-mail do primeiro administrador}
        {--admin-celular= : Celular do primeiro administrador}
        {--fuso='.Fuso::PADRAO.' : Fuso IANA da organização}
        {--em-migracao : Nasce em migração: convites e avisos ficam fechados (OIK-ADR-0009 §5)}
        {--operador= : Quem executa (registrado em auditoria_plataforma)}
        {--motivo=provisionamento : Código do motivo}';

    protected $description = 'Provisiona uma organização com papéis prontos, primeira igreja e convite do administrador';

    public function handle(Provisionar $provisionar): int
    {
        $nome = trim((string) $this->option('nome'));
        $adminNome = trim((string) $this->option('admin-nome'));
        $operador = trim((string) ($this->option('operador') ?: get_current_user()));
        if ($nome === '' || $adminNome === '' || $operador === '') {
            $this->error('Informe --nome, --admin-nome e --operador.');

            return self::INVALID;
        }

        $texto = static fn (mixed $valor): ?string => is_string($valor) && trim($valor) !== '' ? trim($valor) : null;

        try {
            $resultado = $provisionar->executar(new DadosProvisionamento(
                identificador: (string) $this->argument('identificador'),
                nome: $nome,
                igreja: $texto($this->option('igreja')) ?? $nome,
                adminNome: $adminNome,
                adminEmail: $texto($this->option('admin-email')),
                adminCelular: $texto($this->option('admin-celular')),
                operador: $operador,
                motivo: $texto($this->option('motivo')) ?? 'provisionamento',
                emMigracao: (bool) $this->option('em-migracao'),
                fuso: $texto($this->option('fuso')) ?? Fuso::PADRAO,
            ));
        } catch (InvalidArgumentException $erro) {
            $this->error($erro->getMessage());

            return self::INVALID;
        }

        if (! $resultado['criada']) {
            $this->info("Organização já provisionada: {$resultado['organizacao']} ({$resultado['estado']}).");

            return self::SUCCESS;
        }

        $this->info("Organização provisionada: {$resultado['organizacao']} ({$resultado['estado']}); convite {$resultado['convite']}.");

        return self::SUCCESS;
    }
}
