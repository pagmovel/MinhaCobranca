<script setup>
import { computed, nextTick, ref } from 'vue';
import BuscaAutosugestao from './BuscaAutosugestao.vue';
import CadastroRapidoProduto from './CadastroRapidoProduto.vue';
import CatalogoItens from './CatalogoItens.vue';
import { centavos, formatarCentavos, totalDoItem } from '../itens.js';

const props = defineProps({ form: { type: Object, required: true } });

const total = computed(() => props.form.itens.reduce((soma, item) => soma + totalDoItem(item.quantidade, item.preco), 0));
const erro = (indice, campo) => props.form.errors[`itens.${indice}.${campo}`];
const semPreco = (item) => !centavos(item.preco);
const incluidos = computed(() => props.form.itens.map((item) => item.produto_servico_id));

const catalogoAberto = ref(false);
const cadastroAberto = ref(false);
const nomeNovo = ref('');

/** Inclui o item e leva o foco ao preço: o valor é combinado com o cliente, o do catálogo é só sugestão. */
async function adicionar(opcao) {
    if (incluidos.value.includes(opcao.id)) {
        return;
    }
    props.form.itens.push({
        produto_servico_id: opcao.id,
        nome: opcao.nome,
        unidade: opcao.unidade,
        quantidade: '1',
        preco: opcao.preco,
        principal: props.form.itens.length === 0,
    });
    if (catalogoAberto.value) {
        return; // no catálogo a pessoa ainda está escolhendo; o foco volta à lista quando ela concluir
    }
    await nextTick();
    const campo = document.getElementById(`preco_item_${opcao.id}`);
    campo?.focus();
    campo?.select();
}

function remover(indice) {
    const saiu = props.form.itens.splice(indice, 1)[0];
    if (saiu?.principal && props.form.itens.length) {
        props.form.itens[0].principal = true;
    }
}

function marcarPrincipal(indice) {
    props.form.itens.forEach((item, posicao) => {
        item.principal = posicao === indice;
    });
}

function abrirCadastro(texto) {
    nomeNovo.value = texto;
    catalogoAberto.value = false;
    cadastroAberto.value = true;
}
</script>

<template>
    <fieldset class="flex min-w-0 flex-col gap-3 sm:col-span-6">
        <legend class="mb-2 text-sm font-semibold">Produtos e serviços</legend>
        <Transition name="surgir">
            <p v-if="form.errors.itens" class="text-xs text-tinta-vermelha">{{ form.errors.itens }}</p>
        </Transition>

        <Transition name="surgir">
            <div v-if="form.itens.length" class="relative overflow-x-auto border border-linha bg-white">
                <table class="w-full text-sm">
                    <thead class="font-estreita text-xs text-grafite/75">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left font-medium">Item</th>
                            <th scope="col" class="px-3 py-2 text-left font-medium">Quantidade</th>
                            <th scope="col" class="px-3 py-2 text-left font-medium">Preço combinado (R$)</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Total (R$)</th>
                            <th scope="col" class="px-3 py-2 text-left font-medium">Principal</th>
                            <th scope="col" class="px-3 py-2"><span class="sr-only">Remover</span></th>
                        </tr>
                    </thead>
                    <TransitionGroup tag="tbody" name="lista" class="divide-y divide-linha">
                        <tr v-for="(item, indice) in form.itens" :key="item.produto_servico_id">
                            <td class="px-3 py-2">
                                {{ item.nome }} <span class="font-estreita text-xs text-grafite/70">({{ item.unidade }})</span>
                                <p v-if="erro(indice, 'produto_servico_id')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'produto_servico_id') }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="item.quantidade" inputmode="decimal" class="entrada w-24" :aria-label="`Quantidade de ${item.nome}`" required>
                                <p v-if="erro(indice, 'quantidade')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'quantidade') }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input
                                    :id="`preco_item_${item.produto_servico_id}`"
                                    v-model="item.preco"
                                    v-mascara="'valor'"
                                    inputmode="decimal"
                                    class="entrada w-32"
                                    :class="semPreco(item) ? 'border-tinta-laranja' : ''"
                                    :aria-label="`Preço combinado de ${item.nome}`"
                                    :aria-describedby="semPreco(item) ? `preco_aviso_${item.produto_servico_id}` : undefined"
                                    required
                                >
                                <p v-if="erro(indice, 'preco')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'preco') }}</p>
                                <p v-else-if="semPreco(item)" :id="`preco_aviso_${item.produto_servico_id}`" class="text-xs text-tinta-laranja">Informe o preço combinado</p>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ formatarCentavos(totalDoItem(item.quantidade, item.preco)) }}</td>
                            <td class="px-3 py-2">
                                <input type="radio" name="item_principal" :checked="item.principal" :aria-label="`${item.nome} é o item principal`" @change="marcarPrincipal(indice)">
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="botao-secundario px-2 py-1" :aria-label="`Remover ${item.nome}`" @click="remover(indice)">Remover</button>
                            </td>
                        </tr>
                    </TransitionGroup>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="3" class="px-3 py-2 text-right font-medium">Total</th>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">
                                <span :key="total" class="destacar inline-block px-1">{{ formatarCentavos(total) }}</span>
                            </td>
                            <td colspan="2" />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Transition>

        <div>
            <label for="novo_item" class="font-estreita text-xs font-medium text-grafite/75">
                {{ form.itens.length ? 'Incluir outro produto ou serviço' : 'Incluir produto ou serviço' }}
            </label>
            <div class="flex flex-wrap items-start gap-2">
                <div class="min-w-0 flex-1 basis-60">
                    <BuscaAutosugestao
                        id="novo_item"
                        url="/produtos-servicos/busca"
                        vazio="Clique para ver o catálogo ou digite o nome"
                        nenhum="Nenhum produto ou serviço ativo com esse nome."
                        cadastrar="Cadastrar produto ou serviço"
                        limpar-ao-escolher
                        @escolhido="adicionar"
                        @cadastrar="abrirCadastro"
                    >
                        <template #opcao="{ opcao }">
                            <span class="font-semibold">{{ opcao.nome }}</span>
                            <span class="ml-2 font-estreita text-xs text-grafite/70">R$ {{ opcao.preco }} por {{ opcao.unidade }}</span>
                        </template>
                    </BuscaAutosugestao>
                </div>
                <button type="button" class="botao-secundario" @click="catalogoAberto = true">Ver catálogo</button>
            </div>
            <p class="mt-1 text-xs text-grafite/70">
                O preço vem do catálogo como sugestão; troque pelo valor combinado com o cliente. O item principal define os
                códigos fiscais do recibo (sem marcação, vale o de maior total). O valor é a soma dos itens.
            </p>
        </div>

        <CatalogoItens
            :aberto="catalogoAberto"
            :incluidos="incluidos"
            @fechar="catalogoAberto = false"
            @incluir="adicionar"
            @cadastrar="abrirCadastro"
        />
        <CadastroRapidoProduto
            :aberto="cadastroAberto"
            :nome-inicial="nomeNovo"
            @fechar="cadastroAberto = false"
            @criado="adicionar"
        />
    </fieldset>
</template>
