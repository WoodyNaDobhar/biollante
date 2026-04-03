# Biollante

A database-driven scaffolding generator for Laravel. Biollante reads your MySQL schema and generates a complete application stack: models, policies, API controllers, resources, routes, tests, TypeScript interfaces, validation rules, and Swagger documentation.

> **Beta.** Biollante is under active development. It works and is used in production, but the API is not yet stable and the documentation is catching up with the code. Contributions and feedback are welcome, but expect rough edges.

## What It Does

Point Biollante at a database table (or all of them) and it generates:

**Backend**
- Models with a wrapper/core/extension architecture
- Policies with owner, related, and scoped permission checks
- API controllers (with optional repository pattern)
- Form request classes with validation derived from the schema
- API resource transformers with relationship loading
- API routes (auto-sorted into authenticated and public groups)
- Swagger/OpenAPI documentation annotations
- Factories, seeders, and test scaffolding (API, permission, repository, and unit)

**Frontend**
- TypeScript interfaces (full, simple, and super-simple variants)
- Vuelidate validation rules with extension points
- Field-level tooltip definitions

Everything is derived from the database: column types, nullability, foreign keys, enums, polymorphic patterns, comments, and constraints. The schema is the source of truth.

## Requirements

- PHP 8.3+
- Laravel 12+
- MySQL or MariaDB (Biollante queries `information_schema` directly)
- [Spatie Laravel Permission](https://github.com/spatie/laravel-permission) `^5|^6|^7`
- [Genealabs Laravel Pivot Events](https://github.com/genealabs/laravel-pivot-events)

### Suggested packages

The following packages are not required, but Biollante's generated output is aware of them and will produce richer scaffolding when they are present:

- `owen-it/laravel-auditing` — enables audit trail support in generated models
- `wildside/userstamps` — enables `created_by` / `updated_by` tracking in generated models
- `darkaonline/l5-swagger` — triggers automatic Swagger documentation regeneration after scaffolding
- `barryvdh/laravel-ide-helper` — triggers IDE helper regeneration after scaffolding

## Installation

```bash
composer require woodynadobhar/biollante
```

Laravel auto-discovers the service provider. Then publish assets using the tags below.

### Publish tags

| Tag | Command | What it publishes |
|---|---|---|
| `biollante` | `php artisan vendor:publish --tag=biollante` | `config/biollante.php` |
| `biollante-views` | `php artisan vendor:publish --tag=biollante-views` | Blade generation templates to `resources/views/vendor/biollante/` |
| `biollante-inertia` | `php artisan vendor:publish --tag=biollante-inertia --force` | `HandleInertiaRequests` middleware stub with permission sharing pre-wired |
| `biollante-frontend` | `php artisan vendor:publish --tag=biollante-frontend` | `usePermissions.ts` Vue composable |

The `--force` flag is required for `biollante-inertia` because `HandleInertiaRequests` already exists in any Jetstream/Inertia application and would otherwise be skipped.

A typical first install:

```bash
php artisan vendor:publish --tag=biollante
php artisan vendor:publish --tag=biollante-inertia --force
php artisan vendor:publish --tag=biollante-frontend
```

Publish `biollante-views` only if you intend to customise generated output templates.

## Permissions in the Frontend

Roles and permissions are available to your Vue frontend out of the box.

### Backend trait

The generated User model includes the HasJsPermissions trait, which serialises the user's Spatie roles and permissions into a JSON structure suitable for frontend consumption.

### Inertia middleware

Publishing `biollante-inertia` writes a `HandleInertiaRequests` stub to `app/Http/Middleware/` that shares permissions on every request:

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user'          => $request->user(),
            'jsPermissions' => $request->user()?->jsPermissions(),
        ],
    ]);
}
```

### Frontend composable

Publishing `biollante-frontend` writes `usePermissions.ts` to your frontend resources. It reads from Inertia's shared props and exposes `can()` and `is()` helpers:

```ts
import { usePermissions } from '@/composables/usePermissions'

const { can, is } = usePermissions()

if (can('edit posts')) { ... }
if (is('Admin')) { ... }
```

### Sanctum token flows

For API-authenticated contexts where Inertia is not in the picture, call `$user->jsPermissions()` directly and include it in your login response payload. The frontend stores and re-applies it from `localStorage` as needed.

## Usage

Generate scaffolding for a single model:

```bash
php artisan make:scaffold Event
```

Generate scaffolding for every eligible table in the database:

```bash
php artisan make:scaffold
```

Generate a shell script that runs each model in parallel:

```bash
php artisan make:scaffold --dump-script
```

### Options

| Option | Description |
|---|---|
| `--table=TABLE` | Use a specific table name instead of inferring from the model |
| `--ignoreFields=a,b,c` | Comma-separated list of columns to skip |
| `--skip=STEPS` | Comma-separated steps to skip (see below) |
| `--dump-script` | Output a parallelized shell script instead of generating |

### Skippable Steps

`model`, `policy`, `repository`, `tests`, `api_controller`, `api_requests`, `api_routes`, `dump-autoload`, `interfaces`, `tips`, `rules`

## Configuration

All configuration lives in a single file: `config/biollante.php`.

### Paths

Output directories for generated files. Override any path to match your project's directory structure.

### Namespaces

PHP namespaces for all generated classes. Defaults to standard Laravel conventions (`App\Models`, `App\Http\Controllers\API`, etc.) but can be overridden for any project structure.

### Options

Feature toggles that control what gets generated:

| Option | Default | Description |
|---|---|---|
| `soft_delete` | `true` | Generate soft delete support |
| `repository_pattern` | `true` | Generate repository classes and inject into controllers |
| `resources` | `true` | Generate API resource transformers |
| `swagger` | `true` | Generate Swagger/OpenAPI annotations |
| `tests` | `true` | Generate test scaffolding |
| `factory` | `true` | Generate model factories |
| `seeder` | `true` | Generate test seeders |
| `auditable` | `true` | Generate audit trail support (requires `owen-it/laravel-auditing`) |
| `userstamps` | `true` | Generate userstamp support (requires `wildside/userstamps`) |
| `excluded_tables` | `[]` | Tables to skip during generation |
| `hidden_fields` | `[...]` | Fields hidden in API resources |

### Scoped Access

Three keys control the permission architecture for applications that have entity-scoped roles (e.g. an organizer who has authority over a specific entity but not the whole system):

**`organizer_roles`** — base names of entity types that carry organizer-level authority (e.g. `['Practice']` or `['Chapter', 'Collective', 'World']`). Biollante appends `" Organizer"` when resolving role names, so `'Practice'` becomes `'Practice Organizer'`. These values drive permission path resolution and Swagger documentation. Set to `[]` if your app has no scoped roles.

**`scope_resolver`** — fully qualified class name implementing `Biollante\Contracts\ScopeResolver`. This tells Biollante how your application proves at runtime that a user holds a scoped role over a particular entity. The interface requires two methods:

```php
interface ScopeResolver
{
    public function userHasScope(User $user, string $roleType, Model $entity): bool;
    public function grantScopeForTest(User $user, string $roleType, Model $entity): void;
}
```

Set to `null` if your app does not use scoped access. Biollante will generate policies with only Full and Own permission checks.

**`parent_hierarchy`** — maps child entity types to their parent entity type and the foreign key connecting them. Used in generated policies to cascade organizer authority from a parent to its children.

```php
'parent_hierarchy' => [
    'Chapter' => ['parent_type' => 'Collective', 'parent_field' => 'collective_id'],
],
```

Set to `[]` if your application has no hierarchical entity relationships affecting permission scoping.

### Invitations

```php
'invitations' => true,
```

When enabled, Biollante generates invitation token generation and decoding endpoints in `AppBaseController`. Set to `false` if your application does not use an invitation flow.

## Schema Conventions

Biollante is not schema-agnostic. It relies on consistent database structure to generate accurate code. Poor schema design produces poor output.

### Tables

Tables must be plural snake_case. Models are inferred as singular StudlyCase.

| Table | Model |
|---|---|
| `events` | `Event` |
| `gathering_assignments` | `GatheringAssignment` |

### Columns

| Pattern | Meaning |
|---|---|
| `id` | Primary key (single column, auto-increment) |
| `slug` | Enables route model binding by slug |
| `<model>_id` | Foreign key (must have a real database constraint) |
| `<n>_type` + `<n>_id` | Polymorphic relation (`_type` must be an ENUM) |
| `parent_id` | Self-referencing tree |
| `is_*` | Boolean flag |
| `*_at` | Timestamp |
| `*_on` | Date |
| `*_by` | Audit user reference |
| `password`, `email` | Receive special handling |
| `created_at/by`, `updated_at/by`, `deleted_at/by` | Audit fields (excluded from forms and most output) |

### Nullability

Nullability is treated as a business rule, not just a database concern. Non-null columns generate `required` validation. Nullable columns are optional. Non-nullable foreign keys create mandatory relationship checks in permission resolution.

### Comments

Biollante uses database column and table comments as documentation source material for Swagger descriptions, TypeScript tooltips, and field hints. Without comments, generated documentation is generic.

## Post-Generation

After scaffolding, Biollante automatically:

- Clears and rebuilds the config, route, and view caches
- Dumps the optimized autoloader
- Regenerates Swagger documentation (if `darkaonline/l5-swagger` is installed)
- Regenerates IDE helper files (if `barryvdh/laravel-ide-helper` is installed)

## Template Customization

Biollante uses Blade templates for all code generation. To customise generated output:

```bash
php artisan vendor:publish --tag=biollante-views
```

This publishes templates to `resources/views/vendor/biollante/`. Modifications take precedence over the package defaults. Only publish this tag if you need custom output — most applications should not need it.

## Generated Architecture

### Models

Each model is split into three files:

- **Core** (`EventCore.php`) — generated freely, contains schema-derived casts, fillable, relations, and validation rules. Never edit this file directly.
- **Wrapper** (`Event.php`) — generated once, then yours. Extends Core. The place for custom accessors, mutators, and business logic.
- **Extension** (`EventExtension.php`) — generated once, then yours. Blade-style extension point for appended attributes and retrieved-event customizations.

The Core model is regenerated freely. The Wrapper ties them together.

### Permissions

Generated policies distinguish three levels of access per operation:

- **Full** — the user has unconditional permission (e.g. Admin, or no role holds the permission at all)
- **Own** — the user has permission only over records they own (linked via a `user_id` or equivalent path)
- **Related** — the user has permission over records belonging to an entity they hold a scoped role over

Permission path resolution is driven entirely by the database schema and `organizer_roles` config. Biollante walks foreign key chains to determine how a user's role connects to any given resource.

## Contributing

Contributions are welcome.

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push and open a pull request

Include a clear description of the problem being solved.

## License

MIT. See [LICENSE](LICENSE) for details.