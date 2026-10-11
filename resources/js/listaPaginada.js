import { computed, ref } from 'vue';
import { chaveDoFiltro, fatiarBloco } from './paginacao.js';

/**
 * Lista paginada por ajax (padrão da Fase 19, skill recibos): busca blocos de páginas em `url`, guarda as páginas por
 * filtro e expõe só a atual. `buscar(url)` devolve { dados } | { erros } | { mensagem } (buscarJson);
 * `aoMostrar(pagina, focar)` deixa a tela atualizar URL e foco.
 */
export function criarListaPaginada({ url, filtro, buscar, aoMostrar = () => {} }) {
    const linhas = ref([]);
    const resumo = ref(null);
    const carregando = ref(false);
    const carregou = ref(false);
    const falha = ref('');
    const erros = ref({});
    let cache = { chave: null, paginas: {} };
    let pedido = 0; // só a resposta do pedido mais recente vale (cliques rápidos)

    // A regra "boolean" do Laravel não aceita "true"/"false" na URL: verdadeiro vai 1, falso e vazios ficam de fora.
    const parametros = (pagina) => new URLSearchParams(
        Object.entries({ ...filtro, pagina })
            .map(([chave, valor]) => [chave, typeof valor === 'boolean' ? (valor ? 1 : '') : valor])
            .filter(([, valor]) => valor !== '' && valor !== null && valor !== undefined),
    ).toString();

    function mostrar(pagina, focar) {
        filtro.pagina = pagina;
        linhas.value = cache.paginas[pagina] ?? [];
        aoMostrar(pagina, focar);
    }

    async function irPara(pagina, { focar = false } = {}) {
        const chave = chaveDoFiltro(filtro);
        if (cache.chave !== chave) {
            cache = { chave, paginas: {} };
        }
        if (cache.paginas[pagina]) {
            pedido++; // um pedido em andamento fica velho: a escolha mais recente é esta página
            carregando.value = false;
            mostrar(pagina, focar);
            return;
        }

        const meu = ++pedido;
        carregando.value = true;
        const { dados, erros: recusados, mensagem } = await buscar(`${url}?${parametros(pagina)}`);
        if (meu !== pedido) {
            return; // chegou depois de uma escolha mais nova: descarta
        }
        carregando.value = false;
        erros.value = recusados ?? {};
        falha.value = recusados ? '' : (mensagem ?? '');
        if (!dados) {
            return;
        }
        cache.paginas = { ...cache.paginas, ...fatiarBloco(dados.linhas, dados.pagina.inicio, dados.pagina.por_pagina) };
        resumo.value = dados;
        carregou.value = true;
        mostrar(dados.pagina.inicio, focar);
    }

    const filtrar = () => irPara(1);
    function recarregar() {
        cache = { chave: null, paginas: {} };
        irPara(filtro.pagina);
    }
    // Uma ação terminou (evento "success" do Inertia): recarrega, salvo na chegada à tela, quando a primeira carga já
    // está saindo. Com outra página carregando, recarrega mesmo assim: o contador descarta a resposta velha.
    function aoTerminarAcao() {
        if (carregando.value && !carregou.value) {
            return;
        }
        recarregar();
    }
    function escolheuPorPagina(quantidade) {
        filtro.por_pagina = quantidade;
        filtrar();
    }

    /** carregando (1ª carga) | erro-filtro (422 na 1ª carga) | falha | vazio | lista */
    const estado = computed(() => {
        if (falha.value) {
            return 'falha';
        }
        if (!carregou.value) {
            return Object.keys(erros.value).length ? 'erro-filtro' : 'carregando';
        }

        return resumo.value.pagina.total ? 'lista' : 'vazio';
    });

    return { linhas, resumo, carregando, carregou, falha, erros, estado, parametros, irPara, filtrar, recarregar, aoTerminarAcao, escolheuPorPagina };
}
