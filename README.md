# Biollante

A Laravel scaffolding generator for rapidly building application structure.

Biollante provides Artisan commands that generate common Laravel and frontend components, reducing repetitive setup and helping keep projects consistent.

The package focuses on reproducible scaffolding patterns using template-driven generation.

---

## Features

- Artisan-based code generation
- Scaffolding for Laravel models, interfaces, and related components
- Frontend scaffolding for Vue components
- Template-driven generation using customizable stubs
- Designed for reuse across multiple Laravel projects

---

## Requirements

- PHP 8.2+
- Laravel 10 or newer
- Composer

---

## Installation

Install via Composer:

```bash
composer require woodynadobhar/biollante
```

Laravel will automatically discover the package service provider.

---

## Quick Example

Generate scaffolding for a model:

```bash
php artisan make:scaffold Example
```

The command generates the application scaffolding defined by the package templates.

---

## Usage

Run the available Artisan generators:

```bash
php artisan make:scaffold ModelName
```

Additional generator commands may be available depending on the installed version.

---

## Philosophy

Biollante follows a few guiding principles:

**Reduce repetition**  
Eliminate boilerplate setup work.

**Encourage consistency**  
Generated files follow predictable structure and conventions.

**Remain flexible**  
Templates can be modified or extended to suit project needs.

**Stay tool-focused**  
Biollante provides scaffolding tools rather than dictating application architecture.

---

## Contributing

Contributions are welcome.

If you want to contribute:

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push your branch
5. Submit a pull request

Please include a clear description of the problem being solved.

---

## License

MIT
