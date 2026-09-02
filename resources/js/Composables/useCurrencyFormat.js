export function useCurrencyFormat() {
    const formatMontant = (montant, devise = '') => {
        const formatte = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(montant) || 0);
        return devise ? `${formatte} ${devise}` : formatte;
    };

    return { formatMontant };
}
