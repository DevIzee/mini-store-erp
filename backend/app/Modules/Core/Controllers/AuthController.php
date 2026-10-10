<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\LoginRequest;
use App\Modules\Core\Models\User;
use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authentifie l'utilisateur par email ou téléphone.
     *
     * Flux :
     * 1. Trouver l'utilisateur sans BelongsToTenant (tenant_id inconnu)
     * 2. Vérifier le mot de passe + is_active
     * 3. Stocker tenant_id + permissions en session
     * 4. Retourner User + permissions
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Déterminer le champ de login : email ou téléphone
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // withoutGlobalScopes() par sécurité — le trait gère déjà le cas null,
        // mais on l'explicite pour clarifier l'intention.
        $user = User::withoutGlobalScopes()
            ->where($field, $request->login)
            ->where('is_active', true)
            ->with('role.permissions')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Aplatir les permissions en tableau de noms — cache session, O(1) par requête
        $permissions = $user->role->permissions->pluck('name')->toArray();

        session([
            'tenant_id'   => $user->organisation_id,
            'permissions' => $permissions,
        ]);

        // Binder immédiatement pour la suite de cette requête
        app()->instance('tenant_id', $user->organisation_id);

        // updateQuietly évite de déclencher les observers Eloquent sur last_login_at
        $user->updateQuietly(['last_login_at' => now()]);

        $user->loadMissing('warehouse');

        return response()->json([
            'user'        => new UserResource($user),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Déconnecte l'utilisateur et invalide la session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    /**
     * Retourne l'utilisateur authentifié avec son rôle, warehouse et permissions.
     * Utilisé par le frontend au démarrage pour restaurer l'état de la session.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('role', 'warehouse');

        return response()->json([
            'user'        => new UserResource($user),
            'permissions' => session('permissions', []),
        ]);
    }
}
