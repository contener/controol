<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import FlashMessages from '@/Components/FlashMessages.vue';

defineProps({
    title: String,
});

const { t } = useI18n();
const page = usePage();
const showingNavigationDropdown = ref(false);

const mesBoutiques = computed(() => page.props.mesBoutiques ?? []);
const boutiqueCouranteId = computed(() => page.props.auth.user?.current_boutique_id);
const boutiqueCourante = computed(() => mesBoutiques.value.find((b) => b.id === boutiqueCouranteId.value));

// N'affiche/masque des liens que par confort — le backend (EnsureAdminAccess /
// EnsureAdminPermission / super_admin) fait toujours foi, jamais cette visibilité seule.
const estSuperAdmin = computed(() => page.props.auth.user?.role === 'super_admin');
const estAdminOuPlus = computed(() => estSuperAdmin.value || page.props.auth.user?.role === 'admin');
const aPermissionAdmin = (permission) => estSuperAdmin.value || (page.props.auth.user?.admin_permissions_liste ?? []).includes(permission);

const switchBoutique = (boutique) => {
    router.post(route('boutiques.switch', boutique.id), {}, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('compte.dashboard')" :active="route().current('compte.dashboard')">
                                    {{ t('nav.global_view') }}
                                </NavLink>
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    {{ t('nav.dashboard') }}
                                </NavLink>
                                <NavLink :href="route('marketplace.index')" :active="route().current('marketplace.*')" class="font-semibold">
                                    🛍️ {{ t('nav.marketplace') }}
                                </NavLink>
                                <NavLink :href="route('clients.index')" :active="route().current('clients.*')">
                                    {{ t('nav.clients') }}
                                </NavLink>
                                <NavLink :href="route('produits.index')" :active="route().current('produits.*')">
                                    {{ t('nav.products') }}
                                </NavLink>
                                <NavLink :href="route('stock.index')" :active="route().current('stock.*')">
                                    {{ t('nav.stock') }}
                                </NavLink>
                                <NavLink :href="route('factures.index')" :active="route().current('factures.*')">
                                    {{ t('nav.invoices') }}
                                </NavLink>
                                <NavLink :href="route('depenses.index')" :active="route().current('depenses.*')">
                                    {{ t('nav.expenses') }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- Boutique Switcher -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="64">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-100 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                🏪 {{ boutiqueCourante?.nom ?? t('nav.no_shop') }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-64">
                                            <div class="block px-4 py-2 text-xs text-gray-400 dark:text-gray-500">
                                                {{ t('nav.my_shops') }}
                                            </div>

                                            <template v-for="boutique in mesBoutiques" :key="boutique.id">
                                                <form @submit.prevent="switchBoutique(boutique)">
                                                    <DropdownLink as="button">
                                                        <div class="flex items-center">
                                                            <svg v-if="boutique.id === boutiqueCouranteId" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <div>{{ boutique.nom }}</div>
                                                        </div>
                                                    </DropdownLink>
                                                </form>
                                            </template>

                                            <div class="border-t border-gray-200 dark:border-gray-700" />

                                            <DropdownLink :href="route('boutiques.index')">
                                                {{ t('nav.manage_shops') }}
                                            </DropdownLink>
                                            <DropdownLink :href="route('boutiques.create')">
                                                {{ t('nav.create_shop') }}
                                            </DropdownLink>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-100 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400 dark:text-gray-500">
                                            {{ t('nav.manage_account') }}
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            {{ t('nav.profile') }}
                                        </DropdownLink>

                                        <DropdownLink :href="route('abonnement.index')">
                                            {{ t('nav.subscription') }}
                                        </DropdownLink>

                                        <DropdownLink v-if="boutiqueCourante" :href="route('public.boutique', boutiqueCourante.slug)" target="_blank">
                                            {{ t('nav.view_public_shop') }}
                                        </DropdownLink>

                                        <DropdownLink v-if="estAdminOuPlus" :href="route('admin.dashboard')">
                                            {{ t('nav.admin_dashboard') }}
                                        </DropdownLink>

                                        <DropdownLink v-if="aPermissionAdmin('paiements.voir')" :href="route('admin.paiements.index')">
                                            {{ t('nav.admin_payments') }}
                                        </DropdownLink>

                                        <DropdownLink v-if="aPermissionAdmin('marketplace.voir')" :href="route('admin.marketplace.index')">
                                            {{ t('nav.admin_marketplace') }}
                                        </DropdownLink>

                                        <DropdownLink v-if="estSuperAdmin" :href="route('admin.administrateurs.index')">
                                            {{ t('nav.admin_administrators') }}
                                        </DropdownLink>

                                        <div class="border-t border-gray-200 dark:border-gray-700" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                {{ t('nav.logout') }}
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 focus:text-gray-500 dark:focus:text-gray-300 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="size-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('compte.dashboard')" :active="route().current('compte.dashboard')">
                            {{ t('nav.global_view') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            {{ t('nav.dashboard') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('marketplace.index')" :active="route().current('marketplace.*')">
                            🛍️ {{ t('nav.marketplace') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('clients.index')" :active="route().current('clients.*')">
                            {{ t('nav.clients') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('produits.index')" :active="route().current('produits.*')">
                            {{ t('nav.products') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('stock.index')" :active="route().current('stock.*')">
                            {{ t('nav.stock') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('factures.index')" :active="route().current('factures.*')">
                            {{ t('nav.invoices') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('depenses.index')" :active="route().current('depenses.*')">
                            {{ t('nav.expenses') }}
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800 dark:text-gray-100">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                {{ t('nav.profile') }}
                            </ResponsiveNavLink>

                            <ResponsiveNavLink :href="route('abonnement.index')" :active="route().current('abonnement.*')">
                                {{ t('nav.subscription') }}
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    {{ t('nav.logout') }}
                                </ResponsiveNavLink>
                            </form>

                            <!-- Boutique Switcher -->
                            <div class="border-t border-gray-200 dark:border-gray-700" />

                            <div class="block px-4 py-2 text-xs text-gray-400 dark:text-gray-500">
                                {{ t('nav.my_shops') }}
                            </div>

                            <template v-for="boutique in mesBoutiques" :key="boutique.id">
                                <form @submit.prevent="switchBoutique(boutique)">
                                    <ResponsiveNavLink as="button">
                                        <div class="flex items-center">
                                            <svg v-if="boutique.id === boutiqueCouranteId" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div>{{ boutique.nom }}</div>
                                        </div>
                                    </ResponsiveNavLink>
                                </form>
                            </template>

                            <ResponsiveNavLink :href="route('boutiques.index')" :active="route().current('boutiques.*')">
                                {{ t('nav.manage_shops') }}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <FlashMessages />

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
