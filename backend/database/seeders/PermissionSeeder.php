<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // insertOrIgnore — idempotent : peut être relancé sans erreur
        DB::table('permissions')->insertOrIgnore($this->permissions());
    }

    private function permissions(): array
    {
        return [
            // ── Articles ──────────────────────────────────────────────────────
            ['name' => 'items.read',   'display_name' => 'Voir les articles',          'permission_group' => 'items'],
            ['name' => 'items.create', 'display_name' => 'Créer un article',           'permission_group' => 'items'],
            ['name' => 'items.update', 'display_name' => 'Modifier un article',        'permission_group' => 'items'],
            ['name' => 'items.delete', 'display_name' => 'Supprimer un article',       'permission_group' => 'items'],
            ['name' => 'items.import', 'display_name' => 'Importer des articles CSV',  'permission_group' => 'items'],
            ['name' => 'items.export', 'display_name' => 'Exporter des articles CSV',  'permission_group' => 'items'],

            // ── Clients ───────────────────────────────────────────────────────
            ['name' => 'customers.read',   'display_name' => 'Voir les clients',    'permission_group' => 'customers'],
            ['name' => 'customers.create', 'display_name' => 'Créer un client',     'permission_group' => 'customers'],
            ['name' => 'customers.update', 'display_name' => 'Modifier un client',  'permission_group' => 'customers'],
            ['name' => 'customers.delete', 'display_name' => 'Supprimer un client', 'permission_group' => 'customers'],

            // ── Fournisseurs ──────────────────────────────────────────────────
            ['name' => 'suppliers.read',   'display_name' => 'Voir les fournisseurs',    'permission_group' => 'suppliers'],
            ['name' => 'suppliers.create', 'display_name' => 'Créer un fournisseur',     'permission_group' => 'suppliers'],
            ['name' => 'suppliers.update', 'display_name' => 'Modifier un fournisseur',  'permission_group' => 'suppliers'],
            ['name' => 'suppliers.delete', 'display_name' => 'Supprimer un fournisseur', 'permission_group' => 'suppliers'],

            // ── Ventes ────────────────────────────────────────────────────────
            ['name' => 'sales.create', 'display_name' => 'Créer une vente',        'permission_group' => 'sales'],
            ['name' => 'sales.read',   'display_name' => 'Voir les ventes',         'permission_group' => 'sales'],
            ['name' => 'sales.export', 'display_name' => 'Exporter les ventes CSV', 'permission_group' => 'sales'],

            // ── Proformas ─────────────────────────────────────────────────────
            ['name' => 'proformas.create',  'display_name' => 'Créer un proforma',              'permission_group' => 'proformas'],
            ['name' => 'proformas.read',    'display_name' => 'Voir les proformas',              'permission_group' => 'proformas'],
            ['name' => 'proformas.delete',  'display_name' => 'Supprimer un proforma',          'permission_group' => 'proformas'],
            ['name' => 'proformas.convert', 'display_name' => 'Convertir un proforma en vente', 'permission_group' => 'proformas'],

            // ── Bons de commande ──────────────────────────────────────────────
            ['name' => 'purchase_orders.create', 'display_name' => 'Créer un bon de commande',    'permission_group' => 'purchase_orders'],
            ['name' => 'purchase_orders.read',   'display_name' => 'Voir les bons de commande',   'permission_group' => 'purchase_orders'],
            ['name' => 'purchase_orders.update', 'display_name' => 'Modifier un bon de commande', 'permission_group' => 'purchase_orders'],
            ['name' => 'purchase_orders.delete', 'display_name' => 'Supprimer un bon de commande','permission_group' => 'purchase_orders'],

            // ── Stock ─────────────────────────────────────────────────────────
            ['name' => 'stock.read',              'display_name' => 'Voir les niveaux de stock',           'permission_group' => 'stock'],
            ['name' => 'stock.material_receipt',  'display_name' => 'Créer une entrée de stock',           'permission_group' => 'stock'],
            ['name' => 'stock.material_issue',    'display_name' => 'Créer une sortie de stock',           'permission_group' => 'stock'],
            ['name' => 'stock.material_transfer', 'display_name' => 'Créer un transfert entre warehouses', 'permission_group' => 'stock'],
            ['name' => 'stock.reconciliation',    'display_name' => "Réconciliation d'inventaire",         'permission_group' => 'stock'],

            // ── Analytiques ───────────────────────────────────────────────────
            ['name' => 'analytics.read', 'display_name' => 'Accéder aux rapports et statistiques', 'permission_group' => 'analytics'],

            // ── Utilisateurs ──────────────────────────────────────────────────
            ['name' => 'users.read',   'display_name' => 'Voir les utilisateurs',     'permission_group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Créer un utilisateur',      'permission_group' => 'users'],
            ['name' => 'users.update', 'display_name' => 'Modifier un utilisateur',   'permission_group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Désactiver un utilisateur', 'permission_group' => 'users'],

            // ── Rôles ─────────────────────────────────────────────────────────
            ['name' => 'roles.read',   'display_name' => 'Voir les rôles',                      'permission_group' => 'roles'],
            ['name' => 'roles.update', 'display_name' => "Modifier les permissions d'un rôle",  'permission_group' => 'roles'],

            // ── Warehouses ────────────────────────────────────────────────────
            ['name' => 'warehouses.read',   'display_name' => 'Voir les warehouses',   'permission_group' => 'warehouses'],
            ['name' => 'warehouses.create', 'display_name' => 'Créer un warehouse',    'permission_group' => 'warehouses'],
            ['name' => 'warehouses.update', 'display_name' => 'Modifier un warehouse', 'permission_group' => 'warehouses'],

            // ── Organisation ──────────────────────────────────────────────────
            ['name' => 'organisation.settings', 'display_name' => "Modifier les paramètres de l'organisation", 'permission_group' => 'organisation'],

            // ── Exercice fiscal ───────────────────────────────────────────────
            ['name' => 'fiscal_years.manage', 'display_name' => 'Ouvrir / fermer un exercice fiscal', 'permission_group' => 'fiscal_years'],

            // ── Alertes ───────────────────────────────────────────────────────
            ['name' => 'alerts.read',    'display_name' => 'Voir les alertes de stock bas',  'permission_group' => 'alerts'],
            ['name' => 'alerts.dismiss', 'display_name' => 'Marquer une alerte comme lue',   'permission_group' => 'alerts'],
        ];
    }
}
