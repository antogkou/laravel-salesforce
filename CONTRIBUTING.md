# Contributing to Laravel Salesforce Integration

First off, thank you for considering contributing to Laravel Salesforce Integration! It's people like you that make this
package better for everyone.

## Code of Conduct

This project and everyone participating in it is governed by our [Code of Conduct](CODE_OF_CONDUCT.md). By
participating, you are expected to uphold this code.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer 2.0 or higher
- Laravel 10.0 or higher
- Git
- A Salesforce developer account for testing

### Development Setup

1. Fork the repository
2. Clone your fork:

```bash
git clone https://github.com/YOUR_USERNAME/laravel-salesforce.git
```

3. Add the original repository as upstream:

```bash
git remote add upstream https://github.com/antogkou/laravel-salesforce.git
```

4. Install dependencies:

```bash
composer install
```

5. Create your feature branch:

```bash
git checkout -b feature/my-new-feature
```

### Development Process

1. Update your local main branch:

```bash
git checkout main
git pull upstream main
```

2. Create your feature branch from main:

```bash
git checkout -b feature/my-new-feature
```

3. Write your code and tests
4. Run the test suite:

```bash
composer test
```

5. Run static analysis:

```bash
composer analyse
```

6. Fix code style:

```bash
composer format
```

## Coding Standards

We follow the PSR-12 coding standard and the PSR-4 autoloading standard.

### Key Points:

- Use strict typing
- Add proper PHPDoc blocks
- Use type hints wherever possible
- Follow Laravel's coding style
- Write descriptive commit messages
- Keep methods focused and small
- Add tests for new features

### Example Class Structure:

```php
declare(strict_types=1);

namespace Antogkou\LaravelSalesforce;

use Exception;
use Illuminate\Support\Collection;

final class MyClass
{
    public function __construct(
        private readonly Service $service
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return Collection<int, Item>
     *
     * @throws Exception
     */
    public function process(array $data): Collection
    {
        // Implementation
    }
}
```

## Testing

- Write tests for all new features
- Maintain or improve test coverage
- Use real-world examples in tests
- Mock external services

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/Feature/MyTest.php

# Run with coverage report
composer test-coverage
```

## Documentation

- Update README.md with new features
- Add PHPDoc blocks to all public methods
- Include examples for new functionality
- Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/)

## Pull Request Process

1. Update CHANGELOG.md with your changes
2. Update README.md if needed
3. Run all checks:

```bash
composer check-all
```

4. Push to your fork and create a Pull Request
5. Fill in the PR template
6. Wait for review

### PR Title Format

Use semantic commit messages for PR titles:

- `feat: Add new feature`
- `fix: Fix some bug`
- `docs: Update documentation`
- `test: Add tests`
- `refactor: Refactor code`
- `chore: Update dependencies`

### PR Description Template

```markdown
## Description

Clear description of what this PR does.

## Type of Change

- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## How Has This Been Tested?

Describe your test process.

## Checklist

- [ ] My code follows the project's standards
- [ ] I have added tests
- [ ] I have updated the documentation
- [ ] I have updated CHANGELOG.md
```

## Release Process

1. Update version numbers
2. Update CHANGELOG.md
3. Create a new release in GitHub
4. Tag the release following semantic versioning

## Reporting Bugs

Open a new issue following the bug report template. Include:

- Description of the bug
- Steps to reproduce
- Expected behavior
- Actual behavior
- Environment details (PHP version, Laravel version, etc.)

## Suggesting Enhancements

Open a new issue following the feature request template. Include:

- Clear description of the feature
- Motivation and use case
- Potential implementation details
- Breaking changes if any

## Questions or Problems?

- Open a [GitHub Discussion](https://github.com/antogkou/laravel-salesforce/discussions)
- Check existing issues and discussions
- Read the [documentation](README.md)

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
