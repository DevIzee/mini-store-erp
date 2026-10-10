<?php

namespace Database\Seeders;

use App\Modules\Core\Models\FiscalYear;
use App\Modules\Core\Models\Organisation;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Subscription;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganisationSeeder extends Seeder
{
    // BelongsToTenant n'applique pas le scope quand tenant_id n'est pas bindé —
    // le seeder tourne hors contexte HTTP, donc pas de filtrage parasite.
    // L'organisation_id est fourni explicitement sur chaque create().

    public function run(): void
    {
        // ── Organisation ──────────────────────────────────────────────────────
        $org = Organisation::create([
            'name'                    => 'Boutique Test',
            'currency_code'           => 'XOF',
            'timezone'                => 'Africa/Abidjan',
            'language'                => 'fr',
            'fiscal_year_start'       => now()->startOfYear()->toDateString(),
            'fiscal_year_mode'        => 'simple',
            'numbering_strategy'      => 'yearly_reset',
            'doc_prefix_sale'         => 'FAC',
            'doc_prefix_proforma'     => 'PRO',
            'doc_prefix_delivery'     => 'BL',
            'doc_prefix_purchase'     => 'BC',
            'sequence_padding'        => 6,
            'low_stock_threshold_mode'=> 'global',
            'low_stock_threshold'     => 5,
            'barcode_scanner_enabled' => true,
            'thermal_print_logo'      => true,
            'import_export_enabled'   => true,
            'template_name'           => 'default',
        ]);

        // ── Abonnement ────────────────────────────────────────────────────────
        Subscription::create([
            'organisation_id' => $org->id,
            'plan'            => 'pro',
            'status'          => 'active',
            'expires_at'      => now()->addYear(),
        ]);

        // ── Warehouse principal ───────────────────────────────────────────────
        $warehouse = Warehouse::create([
            'organisation_id' => $org->id,
            'name'            => 'Dépôt Principal',
            'is_active'       => true,
        ]);

        // ── Exercice fiscal courant ───────────────────────────────────────────
        FiscalYear::create([
            'organisation_id' => $org->id,
            'name'            => 'Exercice '.now()->year,
            'starts_at'       => now()->startOfYear()->toDateString(),
            'ends_at'         => now()->endOfYear()->toDateString(),
            'status'          => 'open',
        ]);

        // ── Rôles + permissions + utilisateurs de test ────────────────────────
        $this->seedRolesAndUsers($org, $warehouse);
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function seedRolesAndUsers(Organisation $org, Warehouse $warehouse): void
    {
        // Charger toutes les permissions en mémoire — plus efficace que N requêtes
        $allPermissions = Permission::all()->keyBy('name');

        foreach ($this->roleDefinitions() as $definition) {
            $role = Role::create([
                'organisation_id' => $org->id,
                'name'            => $definition['name'],
                'is_system'       => true,
                'hierarchy_level' => $definition['level'],
            ]);

            // Assigner les permissions via la table pivot role_permissions
            $permissionIds = collect($definition['permissions'])
                ->map(fn (string $name) => $allPermissions->get($name)?->id)
                ->filter()
                ->values()
                ->toArray();

            $role->permissions()->attach($permissionIds);

            // Utilisateur de test pour ce rôle
            // Operator et Lead_Operator sont scopés au warehouse — les autres non
            User::create([
                'organisation_id' => $org->id,
                'role_id'         => $role->id,
                'warehouse_id'    => $definition['level'] <= 2 ? $warehouse->id : null,
                'name'            => $definition['user_name'],
                'email'           => $definition['user_email'],
                'password'        => Hash::make('password'),
                'language'        => 'fr',
                'timezone'        => 'Africa/Abidjan',
                'is_active'       => true,
            ]);
        }
    }

    private function roleDefinitions(): array
    {
        $operatorPerms = [
            'items.read',
            'customers.read', 'customers.create',
            'sales.create', 'sales.read',
            'proformas.create', 'proformas.read', 'proformas.delete', 'proformas.convert',
            'stock.read',
            'warehouses.read',
        ];

        $leadOperatorPerms = array_merge($operatorPerms, [
            'items.create', 'items.update', 'items.import', 'items.export',
            'customers.update',
            'suppliers.read', 'suppliers.create', 'suppliers.update',
            'sales.export',
            'stock.material_receipt', 'stock.material_issue',
            'alerts.read', 'alerts.dismiss',
        ]);

        $managerPerms = array_merge($leadOperatorPerms, [
            'items.delete',
            'customers.delete',
            'suppliers.delete',
            'purchase_orders.create', 'purchase_orders.read',
            'purchase_orders.update', 'purchase_orders.delete',
            'stock.material_transfer', 'stock.reconciliation',
            'analytics.read',
            'users.read',
            'roles.read',
        ]);

        $adminPerms = array_merge($managerPerms, [
            'users.create', 'users.update', 'users.delete',
            'roles.update',
            'warehouses.create', 'warehouses.update',
            'organisation.settings',
            'fiscal_years.manage',
        ]);

        return [
            [
                'name'        => 'Operator',
                'level'       => 1,
                'permissions' => $operatorPerms,
                'user_name'   => 'Caissier Test',
                'user_email'  => 'operator@test.com',
            ],
            [
                'name'        => 'Lead_Operator',
                'level'       => 2,
                'permissions' => $leadOperatorPerms,
                'user_name'   => 'Responsable Test',
                'user_email'  => 'lead@test.com',
            ],
            [
                'name'        => 'Manager',
                'level'       => 3,
                'permissions' => $managerPerms,
                'user_name'   => 'Manager Test',
                'user_email'  => 'manager@test.com',
            ],
            [
                'name'        => 'Admin',
                'level'       => 4,
                'permissions' => $adminPerms,
                'user_name'   => 'Administrateur Test',
                'user_email'  => 'admin@test.com',
            ],
        ];
    }
}
