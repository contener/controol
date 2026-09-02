import { createI18n } from 'vue-i18n';
import fr from './lang/fr.json';
import en from './lang/en.json';

export const i18n = createI18n({
    legacy: false,
    locale: 'fr',
    fallbackLocale: 'fr',
    messages: { fr, en },
});

export function syncLocale(locale) {
    if (locale && (locale === 'fr' || locale === 'en') && i18n.global.locale.value !== locale) {
        i18n.global.locale.value = locale;
    }
}
