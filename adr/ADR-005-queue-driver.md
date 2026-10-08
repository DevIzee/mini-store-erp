# ADR-005 — Queue Driver

**Status :** Accepted
**Date :** Session 4
**Décideurs :** Équipe projet

---

## Contexte

Plusieurs opérations dans `mini-store-erp` sont candidates à la mise en queue :

- Import CSV (potentiellement milliers de lignes)
- Export CSV de grandes listes
- Vérification des seuils de stock bas après une Sale
- Envoi de notifications (email/SMS en v2)

Il faut choisir un driver de queue qui soit simple à déployer en v1 pour des PME avec des contraintes d'hébergement modestes, tout en permettant une montée en charge future.

## Décision

**Database queue driver** pour la v1, avec une **migration vers Redis documentée** pour la v2.

```env
# .env
QUEUE_CONNECTION=database
```

La table `jobs` est déjà créée par la migration Laravel par défaut (`0001_01_01_000002_create_jobs_table.php` — présente dans le repo). Le worker est lancé dans le script `composer run dev` :

```json
"dev": "npx concurrently ... \"php artisan queue:listen --tries=1 --timeout=0\""
```

### Jobs planifiés

| Job | Déclencheur | Priorité queue |
|---|---|---|
| `ProcessImportJob` | Upload CSV → `ImportExportController` | `imports` |
| `CheckLowStockJob` | Après création d'une `Sale` | `default` |
| `ExportJob` | Demande d'export → `ImportExportController` | `exports` |

### Retry & timeout

```php
// ProcessImportJob
public int $tries = 1;        // pas de retry (idempotence complexe sur import)
public int $timeout = 300;    // 5 min max pour un CSV de 10k lignes

// CheckLowStockJob
public int $tries = 3;
public int $timeout = 30;
```

### Chemin de migration Redis

Quand le volume de transactions dépasse la capacité de la database queue (typiquement >500 Sales/jour avec import simultané) :

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
```

Aucun changement de code applicatif requis — uniquement config `.env` + déploiement d'un Redis.

## Alternatives écartées

**Redis dès la v1 :** Redis est indisponible sur les hébergements partagés bas de gamme ciblés (Togo, UEMOA). La database queue fonctionne avec n'importe quel hébergement MySQL/SQLite. L'ajout de Redis en v2 est trivial.

**Sync driver :** Exécuter les jobs synchroniquement bloquerait la réponse HTTP lors d'un import de 5000 lignes. Inacceptable UX.

**Beanstalkd / SQS :** Complexité de déploiement injustifiée pour la cible v1. SQS implique un coût AWS et une dépendance réseau.

## Conséquences

**Positives :**
- Zéro infrastructure additionnelle en v1 — fonctionne avec la même DB que l'application
- `failed_jobs` table incluse → les imports échoués sont tracés, relançables via `artisan queue:retry`
- Migration Redis transparente si besoin

**Négatives / risques :**
- La database queue dégrade légèrement les performances DB sous forte charge (polling toutes les secondes)
- Long-polling bloquant si `php artisan queue:work` et non `queue:listen` — utiliser `queue:listen` en dev, `queue:work --daemon` en prod avec Supervisor
- Attention aux imports volumineux (>50k lignes) : chunker par lots de 500 dans `ProcessImportJob`
