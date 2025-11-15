# Améliorations possibles pour le Health Check Bundle

Analyse de bundles concurrents et fonctionnalités intéressantes à implémenter.

## Bundles analysés

1. **MacPaw/symfony-health-check-bundle** - Bundle mature avec de bonnes pratiques
2. **ekreative/health-check-bundle** - Approche simple, gestion de multiple connexions
3. **silpo-tech/HealthCheckBundle** - Groupes de checks par contexte (web/command)

## Fonctionnalités prioritaires à ajouter

### 1. Endpoint /ping séparé ⭐⭐⭐

**Source**: MacPaw

**Intérêt**: Endpoint ultra-léger pour les load balancers et Kubernetes liveness probes.

```yaml
# Deux endpoints distincts :
GET /ping      # Simple "up" check, pas de connexions DB/Redis
GET /health    # Checks complets de tous les services
```

**Implémentation**:

- Controller séparé `PingController`
- Check simple `StatusUpCheck` qui retourne toujours "up"
- Utile pour les health checks fréquents sans surcharger la DB

**Priorité**: HAUTE

---

### 2. Support de connexions Doctrine multiples ⭐⭐⭐

**Source**: ekreative

**Intérêt**: Applications avec plusieurs bases de données (read/write replicas, microservices)

```yaml
health_check:
    doctrine_connections:
        - default
        - analytics
        - logs
```

**Implémentation**:

- Modifier `DatabaseHealthCheck` pour accepter un nom de connexion
- Créer un service par connexion via configuration
- Chaque connexion = un check séparé dans la réponse

**Priorité**: MOYENNE

---

### 3. Doctrine MongoDB (ODM) Support ⭐⭐

**Source**: MacPaw

**Intérêt**: Support MongoDB pour les applications utilisant Doctrine ODM

**Implémentation**:

- Nouveau check `MongoDBHealthCheck`
- Utilise `DocumentManager` au lieu de `Connection`
- Query simple sur une collection ou commande `ping`

**Priorité**: BASSE (dépend de la demande)

---

### 4. Optional Checks (non-critical par défaut) ⭐⭐⭐

**Source**: ekreative

**Intérêt**: Checks qui informent mais ne causent pas de failure global

```yaml
health_check:
    health_checks:
        - id: database  # critical: true (default)
    optional_checks:
        - id: redis     # critical: false, n'affecte pas le status global
        - id: s3_cache
```

**Implémentation**:

- Déjà partiellement implémenté avec `$critical` parameter
- Améliorer la configuration pour distinguer visuellement les optional checks
- Section séparée dans la réponse JSON ?

**Priorité**: MOYENNE

---

### 5. Codes de réponse HTTP personnalisables ⭐⭐

**Source**: MacPaw

**Intérêt**: Personnaliser les codes HTTP selon les besoins de l'infrastructure

```yaml
health_check:
    success_status_code: 200   # default
    failure_status_code: 503   # default, mais pourrait être 500, 404, etc.
```

**Priorité**: BASSE

---

### 6. Environment Check ⭐

**Source**: MacPaw

**Intérêt**: Vérifier que l'application tourne dans le bon environnement

```php
class EnvironmentCheck extends AbstractHealthCheck
{
    public function __construct(private string $expectedEnv) {}

    protected function doCheck(): HealthCheckResult
    {
        $currentEnv = $_ENV['APP_ENV'];
        $isHealthy = $currentEnv === $this->expectedEnv;

        return new HealthCheckResult(
            name: 'environment',
            status: $isHealthy ? HealthCheckStatus::HEALTHY : HealthCheckStatus::UNHEALTHY,
            message: $isHealthy ? "Environment is $currentEnv" : "Expected $expectedEnv, got $currentEnv"
        );
    }
}
```

**Priorité**: BASSE

---

### 7. Groupes de checks par contexte ⭐⭐

**Source**: silpo-tech

**Intérêt**: Différents checks pour web vs workers/console

```yaml
health_check:
    groups:
        web:
            - database
            - redis
            - s3
        worker:
            - database
            - redis
            - message_queue

# Routes différentes
GET /health/web     # Checks pour l'application web
GET /health/worker  # Checks pour les workers
```

**Implémentation**:

- Système de groupes/tags pour les checks
- Routes dynamiques ou paramètres de query (`?group=web`)
- Utile pour monitoring granulaire

**Priorité**: MOYENNE

---

## Améliorations de documentation et communauté

### 8. Fichiers communauté ⭐⭐⭐

**Source**: MacPaw

**À ajouter**:

- `CONTRIBUTING.md` - Guide de contribution
- `SECURITY.md` - Politique de sécurité
- `CODE_OF_CONDUCT.md` - Code de conduite

**Priorité**: HAUTE (pour projet open source)

---

### 9. CI/CD avec GitHub Actions ⭐⭐⭐

**Source**: MacPaw, silpo-tech

**À implémenter**:

- Tests automatiques sur PR
- Code coverage avec codecov.io
- PHPStan et PHP-CS-Fixer checks
- Matrix tests (multiple PHP/Symfony versions)

**Priorité**: HAUTE

---

### 10. Docker pour les tests ⭐⭐

**Source**: silpo-tech

**Intérêt**: Tests d'intégration avec vraies bases de données

```yaml
# docker-compose.test.yml
services:
  mysql:
    image: mysql:8.0
  redis:
    image: redis:7
  mongodb:
    image: mongo:7
```

**Priorité**: MOYENNE

---

## Fonctionnalités à NE PAS implémenter

### ❌ RedisFactory avec catch silencieux

**Pourquoi**: Masque les erreurs de configuration, anti-pattern

### ❌ Timeout dans configuration bundle

**Pourquoi**: Mieux géré par le timeout de chaque check individuellement

### ❌ Validation de schéma de réponse externe

**Pourquoi**: Trop spécifique, mieux fait dans custom checks

---

## Plan d'implémentation proposé

### Phase 1 - Quick Wins (v1.1.0)

1. ✅ Ajouter CONTRIBUTING.md, SECURITY.md, CODE_OF_CONDUCT.md
2. ✅ Setup GitHub Actions (tests, coverage)
3. ✅ Créer endpoint /ping séparé
4. ✅ Améliorer documentation des optional checks

### Phase 2 - Features (v1.2.0)

1. Support connexions Doctrine multiples
2. Groupes de checks (web/worker)
3. Docker compose pour tests d'intégration

### Phase 3 - Extensions (v1.3.0+)

1. MongoDB ODM support
2. Codes HTTP personnalisables
3. Environment check

---

## Feedback communauté

À collecter via GitHub Issues pour prioriser les features réellement demandées.
