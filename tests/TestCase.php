<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava: a suíte apaga e recria as tabelas (`migrate:fresh` do LazilyRefreshDatabase). Antes de qualquer acesso ao
     * banco, recusa tudo que não seja o banco de teste — um `phpunit.xml` errado não pode apagar o `recibos`.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app['config'];
        $nome = $config->get('database.default');

        if (! self::bancoDeTeste($config->get("database.connections.{$nome}", []))) {
            throw new RuntimeException("Suíte recusada: a conexão [{$nome}] não aponta para o banco de teste (recibos_teste).");
        }

        return $app;
    }

    /** @param  array{driver?: string, database?: string}  $conexao */
    public static function bancoDeTeste(array $conexao): bool
    {
        return match ($conexao['driver'] ?? null) {
            'pgsql' => ($conexao['database'] ?? null) === 'recibos_teste',
            'sqlite' => ($conexao['database'] ?? null) === ':memory:',
            default => false,
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Sem manifest do Vite nos testes; o fake também cobre a diretiva @fonts.
        $this->withoutVite();
    }
}
