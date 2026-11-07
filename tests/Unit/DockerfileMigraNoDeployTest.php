<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Decisão do Marcos (2026-10-12, reafirmada em 2026-11-07): o deploy aplica sozinho as migrações pendentes, sem
 * variável nem passo manual no painel. Este teste impede que o comando saia do Dockerfile sem ninguém perceber.
 */
class DockerfileMigraNoDeployTest extends TestCase
{
    private function comandoDeSubida(): string
    {
        $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile');
        $this->assertNotFalse($dockerfile);
        $this->assertSame(1, preg_match('/^CMD .*$/m', $dockerfile, $achado), 'O Dockerfile precisa ter um CMD.');

        return $achado[0];
    }

    public function test_a_subida_do_container_aplica_as_migracoes_pendentes(): void
    {
        $this->assertStringContainsString('php artisan migrate --force', $this->comandoDeSubida());
    }

    public function test_a_migracao_roda_antes_de_o_apache_comecar_a_atender(): void
    {
        $comando = $this->comandoDeSubida();

        $this->assertLessThan(strpos($comando, 'apache2-foreground'), strpos($comando, 'php artisan migrate --force'));
    }

    public function test_a_migracao_nao_depende_de_variavel_de_ambiente(): void
    {
        $this->assertStringNotContainsString('RODAR_MIGRACOES', $this->comandoDeSubida());
    }
}
