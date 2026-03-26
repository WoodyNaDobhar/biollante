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

- PHP 8.2+
- Laravel 10+
- MySQL or MariaDB (Biollante queries `information_schema` directly)
- [Spatie Laravel Permission](https://github.com/spatie/laravel-permission) (for policy and permission test generation)

## Installation

```bash
composer require woodynadobhar/biollante
```

Laravel auto-discovers the service provider. Then publish the config:

```bash
php artisan vendor:publish --tag=biollante
```

This publishes `config/biollante.php`, which contains all package configuration: output paths, namespaces, generation options, and scoped access settings.

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
| `excluded_tables` | `[]` | Tables to skip during generation |
| `hidden_fields` | `[...]` | Fields hidden in API resources |

### Scoped Access

Two keys control the permission architecture:

**`organizer_roles`** — base names of entity types that have scoped organizer authority (e.g. `['Practice']` or `['Chapter', 'Collective', 'World']`). These drive permission path resolution and Swagger documentation. Set to `[]` if your app has no scoped roles.

**`scope_resolver`** — fully qualified class name implementing `Biollante\Contracts\ScopeResolver`. This tells Biollante how your application links users to scoped entities at runtime. Set to `null` if your app doesn't use scoped access.

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

### Polymorphic Relations

The `_type` column must be a MySQL ENUM. Values must be singular StudlyCase model names matching your application's model classes (e.g. `'User'`, `'Guide'`, `'Practice'`).

If a column comment contains `morphOne`, a singular relation is generated. Otherwise it defaults to `morphMany`.

### Pivot Tables

Many-to-many relationships are inferred when a table contains exactly two foreign keys pointing at different tables. Standard Laravel pivot naming conventions apply.

### Enums

Native MySQL ENUMs generate select inputs in frontend scaffolding and typed constants in TypeScript. For polymorphic `_type` columns, enum values must align with model naming. For regular enums, Biollante generates a TypeScript constants file.

## Scoped Access

Biollante generates policies that support three levels of access for each action (display, update, remove):

- **Full** — the user has the unrestricted permission (e.g. `display events`)
- **Own** — the user owns the resource (traced through `user_id` or polymorphic owner paths)
- **Related** — the user has scoped authority over the entity the resource belongs to

The first two are handled generically. The third requires your application to implement `Biollante\Contracts\ScopeResolver`, which answers the question: "does this user have authority over entity X?" Different apps answer this differently — some use polymorphic organizer chains, others use direct pivot tables. The interface abstracts the mechanism.

See `src/Contracts/ScopeResolver.php` for the full interface documentation.

## Model Architecture

Generated models use a three-layer pattern:

```
app/Models/Event.php              ← Wrapper (uses the Extension trait)
app/Models/Core/Event.php         ← Core (generated, overwritten on re-scaffold)
app/Models/Extensions/EventExtension.php  ← Extension (generated once, never overwritten)
```

Custom relationships, accessors, mutators, and business logic go in the Extension. The Core model is regenerated freely. The Wrapper ties them together.

## Template Customization

Biollante uses Blade templates for all code generation. To customize output:

```bash
php artisan vendor:publish --tag=biollante-views
```

This publishes templates to `resources/views/vendor/biollante/`. Modifications take precedence over the package defaults.

## Post-Generation

After scaffolding, Biollante automatically:

- Clears and rebuilds the config, route, and view caches
- Dumps the optimized autoloader
- Regenerates Swagger documentation (if `l5-swagger` is installed)
- Regenerates IDE helper files (if `barryvdh/laravel-ide-helper` is installed)

## Contributing

Contributions are welcome.

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push and open a pull request

Include a clear description of the problem being solved.

## License

MIT. See [LICENSE](LICENSE) for details.