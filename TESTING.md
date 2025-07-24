# AI-Book Testing Guide

This document describes the comprehensive testing setup for the AI-Book social networking platform.

## Testing Frameworks

The project uses three main testing frameworks:

- **PHPUnit** - Backend unit and feature tests for Laravel
- **Vitest** - Frontend unit and integration tests for Vue 3
- **Cypress** - End-to-end testing for full application workflows

## Getting Started

### Prerequisites

Ensure the development environment is running:

```bash
./docker/scripts/start-dev.sh
```

### Running All Tests

Use the comprehensive test script:

```bash
./docker/scripts/test.sh
```

This script will:
1. Run PHPUnit tests with coverage
2. Run Vitest tests with coverage
3. Run TypeScript type checking
4. Optionally run Cypress E2E tests

## Backend Testing (PHPUnit)

### Configuration

PHPUnit is configured via `phpunit.xml`:
- Uses SQLite in-memory database for tests
- Runs with array cache/session drivers
- Includes code coverage analysis

### Running Tests

```bash
# Run all Laravel tests
docker-compose exec app php artisan test

# Run with coverage
docker-compose exec app php artisan test --coverage

# Run specific test files
docker-compose exec app php artisan test --filter=ApiHealthTest

# Run only feature tests
docker-compose exec app php artisan test tests/Feature

# Run only unit tests
docker-compose exec app php artisan test tests/Unit
```

### Test Types

#### Feature Tests (`tests/Feature/`)
Test full application workflows including:
- API endpoints
- HTTP responses
- Database interactions
- Authentication flows

Example:
```php
public function test_api_health_endpoint(): void
{
    $response = $this->get('/api/health');
    
    $response->assertStatus(200)
        ->assertJson(['status' => 'ok']);
}
```

#### Unit Tests (`tests/Unit/`)
Test individual classes and methods:
- Business logic
- Helper functions
- Model methods
- Service classes

Example:
```php
public function test_user_can_create_post(): void
{
    $user = User::factory()->create();
    $post = $user->posts()->create(['content' => 'Test post']);
    
    $this->assertEquals('Test post', $post->content);
}
```

### Database Testing

Tests use an in-memory SQLite database that's reset for each test:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_example()
    {
        // Database is automatically migrated and reset
    }
}
```

## Frontend Testing (Vitest)

### Configuration

Vitest is configured in `vite.config.ts`:
- Uses jsdom environment for DOM testing
- Includes Vue Test Utils for component testing
- Configured for TypeScript support

### Running Tests

```bash
# Run all frontend tests
docker-compose exec node npm run test

# Run tests in watch mode
docker-compose exec node npm run test:ui

# Run tests once with coverage
docker-compose exec node npm run test:coverage

# Run specific test files
docker-compose exec node npm run test -- Example.test.ts
```

### Test Types

#### Component Tests
Test Vue 3 components in isolation:

```typescript
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import MyComponent from '@/components/MyComponent.vue'

describe('MyComponent', () => {
  it('renders properly', () => {
    const wrapper = mount(MyComponent, {
      props: { message: 'Hello' }
    })
    
    expect(wrapper.text()).toContain('Hello')
  })
})
```

#### Composable Tests
Test Vue 3 composition API functions:

```typescript
import { describe, it, expect } from 'vitest'
import { useCounter } from '@/composables/useCounter'

describe('useCounter', () => {
  it('increments count', () => {
    const { count, increment } = useCounter()
    
    increment()
    expect(count.value).toBe(1)
  })
})
```

### Mocking

Vitest includes automatic mocking capabilities:

```typescript
import { vi } from 'vitest'

// Mock API calls
vi.mock('@/services/api', () => ({
  fetchUser: vi.fn(() => Promise.resolve({ id: 1, name: 'John' }))
}))

// Mock Vue Router
const mockPush = vi.fn()
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush })
}))
```

## End-to-End Testing (Cypress)

### Configuration

Cypress is configured in `cypress.config.ts`:
- E2E tests run against `http://localhost:5173`
- Component tests use Vite dev server
- Custom commands for common workflows

### Running Tests

```bash
# Open Cypress interactive mode
docker-compose exec node npm run test:e2e

# Run E2E tests headlessly
docker-compose exec node npm run test:e2e:run

# Run specific test files
docker-compose exec node npx cypress run --spec "cypress/e2e/auth.cy.ts"
```

### Test Types

#### E2E Tests (`cypress/e2e/`)
Test complete user workflows:

```typescript
describe('User Authentication', () => {
  it('should login successfully', () => {
    cy.visit('/login')
    cy.get('[data-cy="email"]').type('user@example.com')
    cy.get('[data-cy="password"]').type('password')
    cy.get('[data-cy="login-btn"]').click()
    
    cy.url().should('include', '/dashboard')
    cy.contains('Welcome')
  })
})
```

#### Component Tests (`cypress/component/`)
Test Vue components in a real browser:

```typescript
import { mount } from 'cypress/vue'
import Button from '@/components/Button.vue'

describe('Button Component', () => {
  it('emits click event', () => {
    const onClickSpy = cy.spy().as('onClickSpy')
    
    mount(Button, {
      props: { onClick: onClickSpy }
    })
    
    cy.get('button').click()
    cy.get('@onClickSpy').should('have.been.called')
  })
})
```

### Custom Commands

Custom Cypress commands are defined in `cypress/support/commands.ts`:

```typescript
// Login via API
cy.login('user@example.com', 'password')

// Seed test data
cy.seedDatabase()

// Clear test data
cy.clearDatabase()
```

## Test Data Management

### Backend Test Data

Use Laravel factories for consistent test data:

```php
// Create test users
$user = User::factory()->create([
    'email' => 'test@example.com'
]);

// Create test posts
$posts = Post::factory()->count(5)->create([
    'user_id' => $user->id
]);
```

### Frontend Test Data

Mock API responses for consistent testing:

```typescript
// Mock successful API response
vi.mocked(api.get).mockResolvedValue({
  data: { id: 1, name: 'Test User' }
})

// Mock API error
vi.mocked(api.post).mockRejectedValue({
  response: { status: 422, data: { errors: {} } }
})
```

## Continuous Integration

### Test Scripts

The project includes several npm/artisan scripts:

```json
{
  "scripts": {
    "test": "vitest",
    "test:run": "vitest run",
    "test:coverage": "vitest run --coverage",
    "test:e2e": "cypress open",
    "test:e2e:run": "cypress run"
  }
}
```

### Coverage Reports

Both PHPUnit and Vitest generate coverage reports:

- **PHPUnit**: Coverage displayed in terminal
- **Vitest**: HTML coverage reports in `coverage/` directory
- **Cypress**: Test videos and screenshots in `cypress/` directories

### GitHub Actions (Future)

Example CI workflow:

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Environment
        run: ./docker/scripts/start-dev.sh
      - name: Run Tests
        run: ./docker/scripts/test.sh
```

## Best Practices

### Writing Tests

1. **Follow AAA Pattern**: Arrange, Act, Assert
2. **Use Descriptive Names**: Test names should describe what they test
3. **Keep Tests Independent**: Each test should run in isolation
4. **Mock External Dependencies**: Don't rely on external APIs in tests
5. **Test Edge Cases**: Include boundary conditions and error scenarios

### Test Organization

1. **Group Related Tests**: Use `describe` blocks to organize tests
2. **Use Setup/Teardown**: Utilize `beforeEach`/`afterEach` for common setup
3. **Share Test Utilities**: Create helper functions for common operations
4. **Separate Test Types**: Keep unit, integration, and E2E tests separate

### Performance

1. **Run Fast Tests First**: Unit tests should run quickly
2. **Parallel Execution**: Leverage parallel test execution
3. **Optimize Database Operations**: Use factories and transactions
4. **Mock Heavy Operations**: Mock file uploads, external APIs

## Debugging Tests

### PHPUnit Debugging

```bash
# Run with verbose output
docker-compose exec app php artisan test --verbose

# Debug specific test
docker-compose exec app php artisan test --filter=test_method_name

# Use Xdebug (if configured)
docker-compose exec app php -dxdebug.mode=debug artisan test
```

### Vitest Debugging

```bash
# Run with UI for interactive debugging
docker-compose exec node npm run test:ui

# Debug specific test
docker-compose exec node npm run test -- --reporter=verbose
```

### Cypress Debugging

```bash
# Run in headed mode to see browser
docker-compose exec node npx cypress run --headed

# Open interactive mode
docker-compose exec node npm run test:e2e
```

## Troubleshooting

### Common Issues

**PHPUnit tests fail with database errors**:
- Ensure SQLite is available in the container
- Check that migrations run properly in test environment

**Vitest tests can't find modules**:
- Verify path aliases in `vite.config.ts`
- Check that `jsdom` is installed for DOM testing

**Cypress tests are flaky**:
- Add explicit waits: `cy.wait()`
- Use data attributes instead of CSS selectors
- Ensure test data is properly seeded

### Getting Help

- Check test output logs for specific error messages
- Use debugging tools to step through failing tests
- Verify that all dependencies are properly installed
- Ensure development environment is running correctly

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Vitest Documentation](https://vitest.dev/)
- [Cypress Documentation](https://docs.cypress.io/)
- [Vue Test Utils Documentation](https://test-utils.vuejs.org/)
- [Laravel Testing Documentation](https://laravel.com/docs/testing) 