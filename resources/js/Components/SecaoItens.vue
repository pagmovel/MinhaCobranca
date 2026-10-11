<script setup>
import { computed } from 'vue';
import BuscaAutosugestao from './BuscaAutosugestao.vue';
import { formatarCentavos, totalDoItem } from '../itens.js';

const props = defineProps({ form: { type: Object, required: true } });

const total = computed(() => props.form.itens.reduce((soma, item) => soma + totalDoItem(item.quantidade, item.preco), 0));
const erro = (indice, campo) => props.form.errors[`itens.${indice}.${campo}`];

function adicionar(opcao) {
    if (props.form.itens.some((item) => item.produto_servico_id === opcao.id)) {
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
}

function marcarPrincipal(indice) {
    props.form.itens.forEach((item, posicao) => {
        item.principal = posicao === indice;
    });
}
</script>

<template>
    <fieldset class="flex flex-col gap-3 sm:col-span-6">
        <legend class="mb-2 text-sm font-semibold">Produtos e serviços</legend>
        <p v-if="form.errors.itens" class="text-xs text-tinta-vermelha">{{ form.errors.itens }}</p>

        <div v-if="form.itens.length" class="overflow-x-auto border border-linha bg-white">
            <table class="w-full text-sm">
                <thead class="font-estreita text-xs text-grafite/75">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Item</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Quantidade</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Preço unitário (R$)</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Total (R$)</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Principal</th>
                        <th scope="col" class="px-3 py-2"><span class="sr-only">Remover</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linha">
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
                            <input v-model="item.preco" v-mascara="'valor'" inputmode="decimal" class="entrada w-32" :aria-label="`Preço unitário de ${item.nome}`" required>
                            <p v-if="erro(indice, 'preco')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'preco') }}</p>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatarCentavos(totalDoItem(item.quantidade, item.preco)) }}</td>
                        <td class="px-3 py-2">
                            <input type="radio" name="item_principal" :checked="item.principal" :aria-label="`${item.nome} é o item principal`" @change="marcarPrincipal(indice)">
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" class="botao-secundario px-2 py-1" @click="form.itens.splice(indice, 1)">Remover</button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3" class="px-3 py-2 text-right font-medium">Total</th>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ formatarCentavos(total) }}</td>
                        <td colspan="2" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <div>
            <label for="novo_item" class="font-estreita text-xs font-medium text-grafite/75">Incluir produto ou serviço</label>
            <BuscaAutosugestao
                id="novo_item"
                url="/produtos-servicos/busca"
                vazio="Digite parte do nome do produto ou serviço"
                nenhum="Nenhum produto ou serviço ativo encontrado."
                limpar-ao-escolher
                @escolhido="adicionar"
            >
                <template #opcao="{ opcao }">
                    <span class="font-semibold">{{ opcao.nome }}</span>
                    <span class="ml-2 font-estreita text-xs text-grafite/70">R$ {{ opcao.preco }} por {{ opcao.unidade }}</span>
                </template>
            </BuscaAutosugestao>
            <p class="mt-1 text-xs text-grafite/70">
                O item principal define os códigos fiscais do recibo; sem marcação, vale o de maior total.
                O valor é a soma dos itens, recalculada ao salvar.
            </p>
        </div>
    </fieldset>
</template>
