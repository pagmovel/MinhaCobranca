import { onScopeDispose, ref } from 'vue';

/**
 * Aviso que some sozinho (ex.: "Máximo: 100%"): `aviso` é o texto atual, `mostrar` troca o texto e recomeça a
 * contagem, `limpar` apaga na hora. Sair da tela (fim do escopo do componente) cancela a contagem.
 * Quem usa mantém a região `aria-live` sempre presente e só troca o texto dela: aviso que entra por `v-if` não é anunciado.
 */
export function criarAvisoTemporario(duracaoMs = 2000) {
    const aviso = ref('');
    let temporizador;

    const cancelar = () => clearTimeout(temporizador);
    const limpar = () => {
        cancelar();
        aviso.value = '';
    };
    const mostrar = (texto) => {
        cancelar();
        aviso.value = texto;
        temporizador = setTimeout(() => { aviso.value = ''; }, duracaoMs);
    };

    onScopeDispose(cancelar, true); // `true`: fora de um componente (nos testes) não avisa

    return { aviso, mostrar, limpar };
}
