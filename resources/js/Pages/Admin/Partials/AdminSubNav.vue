<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const estSuperAdmin = computed(() => user.value.role === 'super_admin');
const permissions = computed(() => user.value.admin_permissions_liste ?? []);
const a = (permission) => estSuperAdmin.value || permissions.value.includes(permission);

// Backend fait toujours foi (EnsureAdminAccess/EnsureAdminPermission) — cette
// visibilité n'est qu'un confort d'affichage pour ne pas montrer d'onglets menant
// systématiquement à un 403.
const liens = computed(() => [
    { route: 'admin.dashboard', pattern: 'admin.dashboard', label: 'Dashboard', visible: true },
    { route: 'admin.utilisateurs.index', pattern: 'admin.utilisateurs.*', label: 'Utilisateurs', visible: a('utilisateurs.voir') },
    { route: 'admin.paiements.index', pattern: 'admin.paiements.*', label: 'Paiements', visible: a('paiements.voir') },
    { route: 'admin.marketplace.index', pattern: 'admin.marketplace.*', label: 'Marketplace', visible: a('marketplace.voir') },
    { route: 'admin.administrateurs.index', pattern: 'admin.administrateurs.*', label: 'Administrateurs', visible: estSuperAdmin.value },
    { route: 'admin.contacts.index', pattern: 'admin.contacts.*', label: 'Contacts', visible: a('contacts.voir') },
].filter((lien) => lien.visible));
</script>

<template>
    <nav class="flex gap-1 mb-6 border-b border-slate-200 dark:border-slate-700">
        <Link
            v-for="lien in liens"
            :key="lien.route"
            :href="route(lien.route)"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
            :class="route().current(lien.pattern)
                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
        >
            {{ lien.label }}
        </Link>
    </nav>
</template>
