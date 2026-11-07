<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A suíte roda com o papel `oikora_app` (OIK-ADR-0002 §12), então a RLS vale de verdade.
 * Por isso não há transação envolvendo o teste: o estado é limpo pelo `oikora_dono`.
 */
abstract class TestCase extends BaseTestCase
{
    private static bool $esquemaPronto = false;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->prepararBanco();
        $this->beforeApplicationDestroyed(function (): void {
            DB::disconnect('pgsql');
            DB::disconnect('pgsql_dono');
        });
    }

    private function prepararBanco(): void
    {
        $dono = DB::connection('pgsql_dono');

        if ($dono->getDatabaseName() !== 'oikora_teste') {
            throw new RuntimeException('A suíte só limpa o banco oikora_teste.');
        }

        if (! self::$esquemaPronto) {
            Artisan::call('migrate:fresh', ['--database' => 'pgsql_dono', '--force' => true]);
            self::$esquemaPronto = true;
        }

        $tabelas = collect($dono->select("select tablename from pg_tables where schemaname = 'public' and tablename <> 'migrations'"))
            ->map(fn (object $linha): string => '"'.$linha->tablename.'"')
            ->implode(', ');

        if ($tabelas !== '') {
            $dono->statement("truncate table {$tabelas} restart identity cascade");
        }
    }
}
