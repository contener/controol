<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { syncLocale } from '@/i18n';
import { applyTheme } from '@/theme';

const props = defineProps({
    user: Object,
});

const { t } = useI18n();

const form = useForm({
    locale: props.user.locale ?? 'fr',
    theme: props.user.theme ?? 'light',
});

const updatePreferences = () => {
    // Application immédiate côté client, sans attendre l'aller-retour serveur.
    syncLocale(form.locale);
    applyTheme(form.theme);

    form.patch(route('preferences.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection @submitted="updatePreferences">
        <template #title>
            {{ t('preferences.title') }}
        </template>

        <template #description>
            {{ t('preferences.description') }}
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="locale" :value="t('preferences.language_label')" />
                <SelectInput id="locale" v-model="form.locale" class="mt-1 block w-full sm:w-64">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </SelectInput>
                <InputError :message="form.errors.locale" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="theme" :value="t('preferences.theme_label')" />
                <SelectInput id="theme" v-model="form.theme" class="mt-1 block w-full sm:w-64">
                    <option value="light">{{ t('preferences.theme_light') }}</option>
                    <option value="dark">{{ t('preferences.theme_dark') }}</option>
                    <option value="system">{{ t('preferences.theme_system') }}</option>
                </SelectInput>
                <InputError :message="form.errors.theme" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                {{ t('common.saved') }}
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ t('common.save') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
