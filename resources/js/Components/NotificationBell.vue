<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';

const page = usePage();
const nonLuesCount = computed(() => page.props.notificationsNonLuesCount ?? 0);

const notifications = ref([]);
const chargement = ref(false);
const dejaCharge = ref(false);

const charger = async () => {
    if (dejaCharge.value) return;
    chargement.value = true;
    try {
        const { data } = await window.axios.get(route('mes-notifications.index'));
        notifications.value = data.notifications;
        dejaCharge.value = true;
    } finally {
        chargement.value = false;
    }
};

const marquerLu = async (notification) => {
    if (notification.lu_a) return;
    notification.lu_a = new Date().toISOString();
    page.props.notificationsNonLuesCount = Math.max(0, nonLuesCount.value - 1);
    await window.axios.patch(route('mes-notifications.lu', notification.id));
};

const toutMarquerLu = async () => {
    notifications.value.forEach((n) => { n.lu_a = n.lu_a ?? new Date().toISOString(); });
    page.props.notificationsNonLuesCount = 0;
    await window.axios.patch(route('mes-notifications.tout-lu'));
};

const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Dropdown align="right" width="80" @click="charger">
        <template #trigger>
            <button type="button" class="relative inline-flex items-center justify-center p-2 rounded-full text-slate-500 dark:text-slate-300 hover:text-slate-700 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <span class="sr-only">Notifications</span>
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                <span
                    v-if="nonLuesCount > 0"
                    class="absolute top-1 right-1 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold"
                >
                    {{ nonLuesCount > 99 ? '99+' : nonLuesCount }}
                </span>
            </button>
        </template>

        <template #content>
            <div class="w-80 max-h-96 overflow-y-auto">
                <div class="flex items-center justify-between px-4 py-2 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Notifications</span>
                    <button v-if="nonLuesCount > 0" type="button" class="text-xs text-blue-600 dark:text-blue-400 hover:underline" @click="toutMarquerLu">
                        Tout marquer comme lu
                    </button>
                </div>

                <div v-if="chargement" class="py-8 text-center text-sm text-slate-400 dark:text-slate-500">Chargement...</div>

                <EmptyState v-else-if="notifications.length === 0" titre="Aucune notification" description="Vous serez averti ici des informations importantes." />

                <div v-else>
                    <component
                        :is="notification.lien ? Link : 'button'"
                        v-for="notification in notifications"
                        :key="notification.id"
                        v-bind="notification.lien ? { href: notification.lien } : { type: 'button' }"
                        class="block w-full text-left px-4 py-3 border-b border-slate-100 dark:border-slate-700 last:border-0 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                        :class="!notification.lu_a ? 'bg-blue-50/60 dark:bg-blue-900/10' : ''"
                        @click="marquerLu(notification)"
                    >
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ notification.titre }}</span>
                            <Badge v-if="notification.est_promotionnelle" couleur="yellow">Offre</Badge>
                            <span v-if="!notification.lu_a" class="size-2 rounded-full bg-blue-600 dark:bg-blue-400 shrink-0"></span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line line-clamp-3">{{ notification.message }}</p>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ formatDate(notification.created_at) }}</p>
                            <span v-if="notification.lien" class="text-xs font-medium text-blue-600 dark:text-blue-400">Voir la boutique →</span>
                        </div>
                    </component>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-700">
                    <DropdownLink :href="route('abonnement.index')">Voir les abonnements</DropdownLink>
                </div>
            </div>
        </template>
    </Dropdown>
</template>
