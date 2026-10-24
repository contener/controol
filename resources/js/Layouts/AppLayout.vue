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
import NotificationBell from '@/Components/NotificationBell.vue';
import InstallAppBanner from '@/Components/InstallAppBanner.vue';
import BoutonFlottantDeplacable from '@/Components/BoutonFlottantDeplacable.vue';

defineProps({
    title: String,
});

const { t } = useI18n();
const page = usePage();
const showingNavigationDropdown = ref(false);

const mesBoutiques = computed(() => page.props.mesBoutiques ?? []);
const messagesNonLus = computed(() => page.props.messagesNonLus ?? 0);
const mesConversationsNonLues = computed(() => page.props.mesConversationsNonLues ?? 0);
const boutiqueCouranteId = computed(() => page.props.auth.user?.current_boutique_id);
const boutiqueCourante = computed(() => mesBoutiques.value.find((b) => b.id === boutiqueCouranteId.value));

// N'affiche/masque les liens que par confort -- les Policies/contrôleurs (voir
// Boutique::autoriseCollaborateurPour()) font toujours foi, jamais cette visibilité
// seule. Pas de boutique courante résolue (pendant un chargement partiel) : on
// affiche par défaut plutôt que de faire disparaître tout le menu.
const peutAcceder = (permission) => boutiqueCourante.value?.permissions?.[permission] ?? true;

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

        <div class="min-h-screen bg-slate-100 dark:bg-slate-900">
            <nav class="sticky top-0 z-30 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-20">
                        <div class="flex min-w-0">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <!-- overflow-x-auto + min-w-0 (requis pour que le défilement s'applique
                                 au lieu de déborder hors du conteneur, piège classique flexbox) : avec
                                 toutes les fonctionnalités activées (plan Pro + propriétaire), une
                                 douzaine de liens ne tient plus sur un écran d'ordinateur portable
                                 standard. Sans ceci, les liens en trop poussaient le sélecteur de
                                 boutique/menu de compte/cloche de notifications hors de l'écran,
                                 les rendant invisibles -- jamais acceptable pour des contrôles
                                 essentiels, voir le défilement comme solution, pas le masquage.
                                 Dès qu'un axe (ici x) passe à une valeur autre que visible, la spec
                                 CSS force l'AUTRE axe (y) à devenir "auto" aussi -- sans assez de
                                 hauteur dans la barre (voir h-20 plus haut, augmenté depuis h-16
                                 pour cette raison précise), les emoji des liens (🛍️ 👥 🔑,
                                 légèrement plus hauts que le texte) se retrouvaient rognés en
                                 haut/bas au lieu de simplement déborder sans gêne comme avant.
                                 Pas d'items-center ici volontairement : NavLink (inline-flex
                                 items-center) centre déjà son propre contenu, et le wrapper doit
                                 garder le stretch par défaut pour que la bordure active (border-b-2)
                                 reste collée au bas de la barre -- un items-center ici la ferait
                                 flotter au milieu de la barre au lieu de souligner son bord. -->
                            <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex overflow-x-auto min-w-0">
                                <NavLink :href="route('compte.dashboard')" :active="route().current('compte.dashboard')">
                                    {{ t('nav.global_view') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('dashboard')" :href="route('dashboard')" :active="route().current('dashboard')">
                                    {{ t('nav.dashboard') }}
                                </NavLink>
                                <NavLink :href="route('marketplace.index')" :active="route().current('marketplace.*')" class="font-semibold">
                                    🛍️ {{ t('nav.marketplace') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('clients')" :href="route('clients.index')" :active="route().current('clients.*')">
                                    {{ t('nav.clients') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('produits')" :href="route('produits.index')" :active="route().current('produits.*')">
                                    {{ t('nav.products') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('audience')" :href="route('audience.index')" :active="route().current('audience.*')">
                                    👥 {{ t('nav.audience') }}
                                </NavLink>
                                <NavLink v-if="boutiqueCourante?.est_proprietaire" :href="route('boutique.collaborateurs.index')" :active="route().current('boutique.collaborateurs.*')">
                                    🔑 {{ t('nav.team') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('produits')" :href="route('stock.index')" :active="route().current('stock.*')">
                                    {{ t('nav.stock') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('factures')" :href="route('factures.index')" :active="route().current('factures.*')">
                                    {{ t('nav.invoices') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('depenses')" :href="route('depenses.index')" :active="route().current('depenses.*')">
                                    {{ t('nav.expenses') }}
                                </NavLink>
                                <NavLink v-if="peutAcceder('messagerie')" :href="route('messages.index')" :active="route().current('messages.*')" class="relative">
                                    {{ t('nav.messages') }}
                                    <span v-if="messagesNonLus > 0" class="ms-1 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold">
                                        {{ messagesNonLus > 99 ? '99+' : messagesNonLus }}
                                    </span>
                                </NavLink>
                                <NavLink :href="route('mes-conversations.index')" :active="route().current('mes-conversations.*')" class="relative">
                                    {{ t('nav.my_conversations') }}
                                    <span v-if="mesConversationsNonLues > 0" class="ms-1 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold">
                                        {{ mesConversationsNonLues > 99 ? '99+' : mesConversationsNonLues }}
                                    </span>
                                </NavLink>
                            </div>
                        </div>

                        <!-- shrink-0 : ces contrôles (changer de boutique, gérer le compte) ne
                             doivent jamais être compressés ni poussés hors de l'écran par les
                             liens de navigation qui défilent à côté -- toujours entièrement
                             visibles, quel que soit le nombre de liens actifs. -->
                        <div class="hidden sm:flex sm:items-center sm:ms-6 shrink-0">
                            <!-- Boutique Switcher -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="64">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-slate-500 dark:text-slate-300 bg-white dark:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-100 focus:outline-none focus:bg-slate-50 dark:focus:bg-slate-700 active:bg-slate-50 dark:active:bg-slate-700 transition ease-in-out duration-150">
                                                <!-- Nom de boutique borné (max-w + truncate) : un nom très
                                                     long ne doit jamais élargir ce bouton au point de
                                                     pousser le menu de compte/la cloche hors de l'écran. -->
                                                <span class="truncate max-w-[9rem]">🏪 {{ boutiqueCourante?.nom ?? t('nav.no_shop') }}</span>

                                                <svg class="ms-2 -me-0.5 size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-64">
                                            <div class="block px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                                {{ t('nav.my_shops') }}
                                            </div>

                                            <template v-for="boutique in mesBoutiques" :key="boutique.id">
                                                <form @submit.prevent="switchBoutique(boutique)">
                                                    <DropdownLink as="button">
                                                        <div class="flex items-center min-w-0">
                                                            <svg v-if="boutique.id === boutiqueCouranteId" class="me-2 size-5 shrink-0 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <div class="truncate">{{ boutique.nom }}</div>
                                                            <span v-if="!boutique.est_proprietaire" class="ms-2 shrink-0 text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                                                {{ t('nav.collaborator') }}
                                                            </span>
                                                        </div>
                                                    </DropdownLink>
                                                </form>
                                            </template>

                                            <div class="border-t border-slate-200 dark:border-slate-700" />

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
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-slate-300 transition">
                                            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-slate-500 dark:text-slate-300 bg-white dark:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-100 focus:outline-none focus:bg-slate-50 dark:focus:bg-slate-700 active:bg-slate-50 dark:active:bg-slate-700 transition ease-in-out duration-150">
                                                <!-- Même principe que le nom de boutique ci-dessus : un nom
                                                     d'utilisateur très long ne doit jamais pousser la cloche
                                                     de notifications hors de l'écran. -->
                                                <span class="truncate max-w-[9rem]">{{ $page.props.auth.user.name }}</span>

                                                <svg class="ms-2 -me-0.5 size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            {{ t('nav.manage_account') }}
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            {{ t('nav.profile') }}
                                        </DropdownLink>

                                        <DropdownLink :href="route('abonnement.index')">
                                            {{ t('nav.subscription') }}
                                        </DropdownLink>

                                        <DropdownLink :href="route('compte.parrainage')">
                                            {{ t('nav.referral') }}
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

                                        <DropdownLink v-if="aPermissionAdmin('parrainage.voir')" :href="route('admin.parrainage.index')">
                                            {{ t('nav.admin_referral') }}
                                        </DropdownLink>

                                        <DropdownLink v-if="estSuperAdmin" :href="route('admin.administrateurs.index')">
                                            {{ t('nav.admin_administrators') }}
                                        </DropdownLink>

                                        <div class="border-t border-slate-200 dark:border-slate-700" />

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

                        <!-- Notifications (visible sur tous les écrans, pas seulement le menu desktop) -->
                        <div class="flex items-center shrink-0">
                            <NotificationBell />
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 dark:text-slate-500 hover:text-slate-500 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 focus:outline-none focus:bg-slate-100 dark:focus:bg-slate-700 focus:text-slate-500 dark:focus:text-slate-300 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
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
                <!-- Position fixed + défilement propre au panneau (pas à la page) : sans
                     cela, ce panneau grandissait à l'intérieur de la nav sticky et pouvait
                     dépasser la hauteur de l'écran, obligeant à faire défiler toute la page
                     pour atteindre les dernières options (changement de boutique, déconnexion). -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden fixed inset-x-0 top-20 bottom-0 z-40 overflow-y-auto bg-white dark:bg-slate-800 border-t border-slate-100 dark:border-slate-700 shadow-lg">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('compte.dashboard')" :active="route().current('compte.dashboard')">
                            {{ t('nav.global_view') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('dashboard')" :href="route('dashboard')" :active="route().current('dashboard')">
                            {{ t('nav.dashboard') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('marketplace.index')" :active="route().current('marketplace.*')">
                            🛍️ {{ t('nav.marketplace') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('clients')" :href="route('clients.index')" :active="route().current('clients.*')">
                            {{ t('nav.clients') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('produits')" :href="route('produits.index')" :active="route().current('produits.*')">
                            {{ t('nav.products') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('audience')" :href="route('audience.index')" :active="route().current('audience.*')">
                            👥 {{ t('nav.audience') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="boutiqueCourante?.est_proprietaire" :href="route('boutique.collaborateurs.index')" :active="route().current('boutique.collaborateurs.*')">
                            🔑 {{ t('nav.team') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('produits')" :href="route('stock.index')" :active="route().current('stock.*')">
                            {{ t('nav.stock') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('factures')" :href="route('factures.index')" :active="route().current('factures.*')">
                            {{ t('nav.invoices') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('depenses')" :href="route('depenses.index')" :active="route().current('depenses.*')">
                            {{ t('nav.expenses') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="peutAcceder('messagerie')" :href="route('messages.index')" :active="route().current('messages.*')">
                            {{ t('nav.messages') }}
                            <span v-if="messagesNonLus > 0" class="ms-1 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold">
                                {{ messagesNonLus > 99 ? '99+' : messagesNonLus }}
                            </span>
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('mes-conversations.index')" :active="route().current('mes-conversations.*')">
                            {{ t('nav.my_conversations') }}
                            <span v-if="mesConversationsNonLues > 0" class="ms-1 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold">
                                {{ mesConversationsNonLues > 99 ? '99+' : mesConversationsNonLues }}
                            </span>
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div class="min-w-0">
                                <div class="font-medium text-base text-slate-800 dark:text-slate-100 truncate">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-slate-500 dark:text-slate-400 truncate">
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

                            <ResponsiveNavLink :href="route('compte.parrainage')" :active="route().current('compte.parrainage')">
                                {{ t('nav.referral') }}
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    {{ t('nav.logout') }}
                                </ResponsiveNavLink>
                            </form>

                            <!-- Boutique Switcher -->
                            <div class="border-t border-slate-200 dark:border-slate-700" />

                            <div class="block px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ t('nav.my_shops') }}
                            </div>

                            <template v-for="boutique in mesBoutiques" :key="boutique.id">
                                <form @submit.prevent="switchBoutique(boutique)">
                                    <ResponsiveNavLink as="button">
                                        <div class="flex items-center min-w-0">
                                            <svg v-if="boutique.id === boutiqueCouranteId" class="me-2 size-5 shrink-0 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <div class="truncate">{{ boutique.nom }}</div>
                                            <span v-if="!boutique.est_proprietaire" class="ms-2 shrink-0 text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                                {{ t('nav.collaborator') }}
                                            </span>
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

            <InstallAppBanner />

            <FlashMessages />

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white dark:bg-slate-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>

            <!-- Bouton flottant "Voir ma boutique" : ouvre directement la boutique s'il n'y
                 en a qu'une, propose un choix s'il y en a plusieurs. Déplaçable
                 indépendamment du bouton "Ajouter un produit" (souris ou tactile, voir
                 BoutonFlottantDeplacable.vue) -- position par défaut juste au-dessus de
                 celui-ci. Téléporté dans <body>, donc toujours réellement fixe à l'écran
                 (indépendant de tout ancêtre de la page en cours), contrairement à la
                 version précédente positionnée en Tailwind `fixed` à l'intérieur de ce
                 layout, qui pouvait sembler défiler avec la page sur certains mobiles.
                 Masqué tant que le menu mobile est ouvert (showingNavigationDropdown) :
                 en z-50, au-dessus du panneau du menu (z-40), il recouvrait sinon ses
                 dernières entrées (sélecteur de boutique, "Gérer mes boutiques") sans
                 jamais s'effacer -- impossible à distinguer d'un élément "coupé". -->
            <BoutonFlottantDeplacable v-if="mesBoutiques.length > 0 && !showingNavigationDropdown" storage-key="voir_boutique" :default-bottom="96" :default-right="24">
                <template #default="{ dragging }">
                    <a
                        v-if="mesBoutiques.length === 1"
                        :href="route('public.boutique', mesBoutiques[0].slug)"
                        target="_blank"
                        title="Voir ma boutique"
                        aria-label="Voir ma boutique"
                        class="group flex items-center gap-2 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 hover:shadow-xl transition-all h-14 px-4"
                        :class="{ 'animate-balancement': !dragging }"
                    >
                        <span class="text-2xl leading-none">🏪</span>
                        <span class="hidden group-hover:inline text-sm font-medium whitespace-nowrap pr-1">Voir ma boutique</span>
                    </a>

                    <Dropdown v-else align="right" position="top">
                        <template #trigger>
                            <button
                                type="button"
                                title="Voir ma boutique"
                                aria-label="Voir ma boutique"
                                class="group flex items-center gap-2 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 hover:shadow-xl transition-all h-14 px-4"
                                :class="{ 'animate-balancement': !dragging }"
                            >
                                <span class="text-2xl leading-none">🏪</span>
                                <span class="hidden group-hover:inline text-sm font-medium whitespace-nowrap pr-1">Voir ma boutique</span>
                            </button>
                        </template>
                        <template #content>
                            <a
                                v-for="boutique in mesBoutiques"
                                :key="boutique.id"
                                :href="route('public.boutique', boutique.slug)"
                                target="_blank"
                                class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 whitespace-nowrap"
                            >
                                🏪 {{ boutique.nom }}
                            </a>
                        </template>
                    </Dropdown>
                </template>
            </BoutonFlottantDeplacable>

            <!-- Bouton flottant "Ajouter un produit" : visible sur toutes les pages du
                 tableau de bord (pas seulement Produits/Index.vue), avec une animation de
                 balancement récurrente -- des utilisateurs ont du mal à trouver comment
                 ajouter un produit, ce bouton doit être impossible à manquer. Déplaçable
                 indépendamment du bouton "Voir ma boutique" ci-dessus. Masqué tant que le
                 menu mobile est ouvert, même raison que ci-dessus. -->
            <BoutonFlottantDeplacable v-if="boutiqueCouranteId && !showingNavigationDropdown" storage-key="ajouter_produit" :default-bottom="24" :default-right="24">
                <template #default="{ dragging }">
                    <Link
                        :href="route('produits.create')"
                        title="Ajouter un nouveau produit"
                        aria-label="Ajouter un nouveau produit"
                        class="group flex items-center gap-2 rounded-full bg-blue-600 text-white shadow-lg hover:bg-blue-700 hover:shadow-xl transition-all h-14 px-4"
                        :class="{ 'animate-balancement': !dragging }"
                    >
                        <span class="text-2xl leading-none">+</span>
                        <span class="hidden group-hover:inline text-sm font-medium whitespace-nowrap pr-1">Ajouter un nouveau produit</span>
                    </Link>
                </template>
            </BoutonFlottantDeplacable>
        </div>
    </div>
</template>

<style scoped>
@keyframes balancement {
    0%, 100% { transform: rotate(0deg); }
    20% { transform: rotate(-14deg); }
    40% { transform: rotate(10deg); }
    60% { transform: rotate(-6deg); }
    80% { transform: rotate(3deg); }
}

.animate-balancement {
    animation: balancement 1s ease-in-out infinite;
    transform-origin: bottom center;
}

.animate-balancement:hover {
    animation-play-state: paused;
}

@media (prefers-reduced-motion: reduce) {
    .animate-balancement {
        animation: none;
    }
}
</style>
