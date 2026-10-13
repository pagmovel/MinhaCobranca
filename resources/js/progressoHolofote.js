/** Re-filtra contra o form reativo: o que a pessoa já digitou (ainda não salvo) sai da lista do holofote. */
export function camposFaltandoNoForm(camposFaltando, form) {
    return camposFaltando.filter((campo) => !String(form[campo.campo] ?? '').trim());
}
