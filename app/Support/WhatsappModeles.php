<?php

namespace App\Support;

/**
 * Modèles de messages de relance WhatsApp — catalogue en code, pas en base (textes gérés par
 * les développeurs, pas du contenu éditable par les admins dans cette première passe). Les
 * placeholders ({{nom}}, {{boutique}}, {{plan}}, {{date_expiration}}) sont substitués côté
 * Vue au moment où l'admin choisit un modèle, avec les données déjà présentes sur la page —
 * pas d'aller-retour serveur nécessaire pour prévisualiser, et le texte reste éditable avant
 * l'ouverture de WhatsApp.
 */
class WhatsappModeles
{
    public const RELANCE_ABONNEMENT = 'relance_abonnement';

    public const PAIEMENT_EN_ATTENTE = 'paiement_en_attente';

    public const ASSISTANCE = 'assistance';

    public const OFFRE_PROMOTIONNELLE = 'offre_promotionnelle';

    public const PROSPECTION_INVITATION = 'prospection_invitation';

    public const PROSPECTION_MARKETPLACE = 'prospection_marketplace';

    public const PROSPECTION_RELANCE_INTERET = 'prospection_relance_interet';

    private const TEXTES = [
        self::RELANCE_ABONNEMENT => [
            'libelle' => 'Relance abonnement',
            'texte' => "Bonjour {{nom}}, votre abonnement CONTROOL arrive bientôt à expiration. Souhaitez-vous le renouveler afin de continuer à utiliser toutes vos fonctionnalités ?",
        ],
        self::PAIEMENT_EN_ATTENTE => [
            'libelle' => 'Paiement en attente',
            'texte' => "Bonjour {{nom}}, nous vous contactons concernant votre paiement CONTROOL. Votre paiement est actuellement en attente de validation. N'hésitez pas à nous contacter si vous avez besoin d'aide.",
        ],
        self::ASSISTANCE => [
            'libelle' => 'Assistance',
            'texte' => "Bonjour {{nom}}, nous avons remarqué que vous pourriez avoir besoin d'aide avec CONTROOL. Notre équipe reste disponible pour vous accompagner.",
        ],
        self::OFFRE_PROMOTIONNELLE => [
            'libelle' => 'Offre promotionnelle',
            'texte' => "Bonjour {{nom}}, une offre spéciale CONTROOL est actuellement disponible. Contactez-nous pour découvrir les conditions et les avantages.",
        ],
        self::PROSPECTION_INVITATION => [
            'libelle' => 'Prospection — Invitation à créer un compte',
            'texte' => "Bonjour {{nom}},\n\nDécouvrez CONTROOL, une plateforme tout-en-un qui vous permet de gérer vos produits, vos stocks, vos clients, vos factures et votre activité depuis un seul espace.\n\nCréez gratuitement votre compte et commencez à organiser votre activité plus simplement.\n\nLien d'inscription :\n{{lien_inscription}}",
        ],
        self::PROSPECTION_MARKETPLACE => [
            'libelle' => 'Prospection — Présentation de la Marketplace',
            'texte' => "Bonjour {{nom}},\n\nCONTROOL vous permet également de créer votre boutique publique et de présenter vos produits à de nouveaux clients grâce à la Marketplace.\n\nVous pouvez commencer gratuitement.\n\nDécouvrez la plateforme :\n{{lien_inscription}}",
        ],
        self::PROSPECTION_RELANCE_INTERET => [
            'libelle' => 'Prospection — Relance après intérêt',
            'texte' => "Bonjour {{nom}},\n\nNous revenons vers vous concernant CONTROOL.\n\nSi vous souhaitez gérer plus facilement vos ventes, vos stocks, vos clients et vos factures, nous pouvons vous accompagner dans la création de votre compte.\n\nVoici le lien :\n{{lien_inscription}}",
        ],
    ];

    public static function liste(): array
    {
        return collect(self::TEXTES)
            ->map(fn (array $modele, string $cle) => ['cle' => $cle, ...$modele])
            ->values()
            ->all();
    }

    public static function cles(): array
    {
        return array_keys(self::TEXTES);
    }
}
