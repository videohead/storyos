# World Graph Studio Testing Guide

This guide covers testing practices for the World Graph Studio project, including PHPUnit for unit tests, WordPress CLI usage via Lando, and Playwright for end-to-end testing.

## PHPUnit

### Test Location
World Graph Studio unit tests are located in:
```
wordpress/wp-content/plugins/worldgraph/tests/
```

### PHPUnit Configuration
The PHPUnit configuration is at:
```
wordpress/wp-content/plugins/worldgraph/tests/phpunit.xml
```

### Running Tests via Lando

#### Run All Tests
```bash
lando phpunit
```

#### Run Tests with Coverage
```bash
lando phpunit --coverage-html ./coverage
```

#### Run Specific Test File
```bash
lando phpunit wordpress/wp-content/plugins/worldgraph/tests/test-cpt.php
```

#### Run Tests Matching a Pattern
```bash
lando phpunit --filter testMethodName
```

#### Run with Verbose Output
```bash
lando phpunit --verbose
```

### Direct PHPUnit Access

If you need to run phpunit directly without Lando tooling:
```bash
lando exec appserver -- ./vendor/bin/phpunit --help
```

### Test Files

Current test files in the World Graph Studio plugin:
- `test-admin-metabox-assets.php` - Admin metabox functionality
- `test-cpt.php` - Custom Post Type tests
- `test-exporter.php` - Export functionality tests
- `test-generation-modality.php` - Generation modality tests
- `test-helpers.php` - Utility helper functions tests
- `test-import.php` - Import functionality tests
- `test-relationships.php` - Relationship management tests
- `test-schema-alignment.php` - Schema alignment validation tests

### Bootstrap and Setup

The PHPUnit bootstrap file is at:
```
wordpress/wp-content/plugins/worldgraph/tests/bootstrap.php
```

This file sets up the WordPress testing environment before tests run.

## WordPress CLI via Lando

### Using the `wp` Command

Lando exposes the WordPress CLI as a tooling command that runs in the `appserver` service:

#### Basic Syntax
```bash
lando wp [command] [args]
```

### Common WordPress CLI Commands

#### Post Management
```bash
# List all posts
lando wp post list

# List custom post types
lando wp post list --post_type=worldgraph_project

# Create a new post
lando wp post create --post_type=worldgraph_character --post_title="Character Name"

# Get post information
lando wp post get <post_id>

# Update a post
lando wp post update <post_id> --post_content="Updated content"

# Delete a post
lando wp post delete <post_id>
```

#### Plugin Management
```bash
# List installed plugins
lando wp plugin list

# Activate a plugin
lando wp plugin activate worldgraph

# Deactivate a plugin
lando wp plugin deactivate worldgraph

# Update plugins
lando wp plugin update --all
```

#### WordPress Information
```bash
# Get WordPress version
lando wp core version

# Check WordPress installation status
lando wp core is-installed

# Get site information
lando wp option get siteurl
lando wp option get home
```

#### User Management
```bash
# List users
lando wp user list

# Create a user
lando wp user create testuser test@example.com --user_pass=password

# Update user meta
lando wp user meta update <user_id> <meta_key> <meta_value>
```

#### Database Commands
```bash
# Export database
lando wp db export database.sql

# Import database
lando wp db import database.sql

# Run a database query
lando wp db query "SELECT * FROM wp_posts LIMIT 5;"
```

### Direct WordPress CLI Access

For commands that need special handling:
```bash
lando exec appserver -- wp [command]
```

### Using wp-cli.yml Configuration

If your project has a `wp-cli.yml` file, WordPress CLI will automatically use it. Place it in the WordPress root to configure default arguments.

## Playwright E2E Testing

### Playwright Setup

Playwright dependencies are installed in the `cli` service during Lando startup:
- `@playwright/test` - Testing framework
- `@wordpress/e2e-test-utils-playwright` - WordPress-specific utilities
- Chromium browser for E2E tests

### Running Playwright Tests

#### Run All Playwright Tests
```bash
lando playwright test
```

#### Run Tests in Debug Mode
```bash
lando playwright test --debug
```

#### Run Tests with UI Mode (Interactive)
```bash
lando playwright test --ui
```

#### Run Specific Test File
```bash
lando playwright test tests/path/to/test.spec.js
```

#### Run Tests Matching a Pattern
```bash
lando playwright test -g "test pattern"
```

#### Generate Playwright Report
```bash
lando playwright test --reporter=html
```

### Browser Configuration

Playwright is configured to use Chromium, with the following setup in `.lando.yml`:
- Chromium browser is pre-installed
- FFmpeg is available for video recording
- Tests run in the `cli` service with Node.js 20

### Test Environment Variables

Set the WordPress URL for Playwright tests:
```bash
export WORDPRESS_URL=https://worldgraph.lndo.site
lando playwright test
```

### Playwright Configuration

Create a `playwright.config.ts` or `playwright.config.js` file in the project root to configure:
- Base URL (WordPress site URL)
- Viewport sizes
- Timeout settings
- Browser configurations
- Screenshot and trace capture

Example configuration:
```javascript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: process.env.WORDPRESS_URL || 'https://worldgraph.lndo.site',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'lando start',
    url: 'https://worldgraph.lndo.site',
    reuseExistingServer: !process.env.CI,
  },
});
```

### Using WordPress E2E Test Utils

Playwright tests can use WordPress-specific utilities from `@wordpress/e2e-test-utils-playwright`:

```javascript
import { test, expect } from '@playwright/test';
import { admin, loginUser } from '@wordpress/e2e-test-utils-playwright';

test('Login to WordPress', async ({ page }) => {
  const adminPage = new admin({ page });
  await adminPage.login();
  await expect(page).toHaveURL('/wp-admin/');
});
```

### Recording Tests

To record tests for debugging:
```bash
lando playwright codegen https://worldgraph.lndo.site
```

This opens a browser and generates Playwright code as you interact with the site.

### Debugging Playwright Tests

#### Using Playwright Inspector
```bash
lando playwright test --debug
```

#### Generate Trace Files
```bash
lando playwright test --trace on
```

Then view the trace:
```bash
lando playwright show-trace trace.zip
```

## Local Entry Points for Testing

When Lando is running, use these URLs for validation:
- WordPress app: https://worldgraph.lndo.site/
- Playwright tests should target: https://worldgraph.lndo.site
- phpMyAdmin: http://localhost:port (use `lando info` to find the exact port)

## Checking Lando Status

Get information about running services and ports:
```bash
lando info
```

This shows all service URLs and ports, including the WordPress site, database, and phpMyAdmin.

## Best Practices

1. **Run tests before committing** - Always run the full test suite locally before pushing changes
2. **Test isolation** - Ensure tests can run in any order and are independent
3. **Use descriptive test names** - Test method names should clearly describe what is being tested
4. **Keep tests focused** - Each test should verify one specific behavior
5. **Mock external services** - ComfyUI and LLM calls should be mocked in unit tests
6. **Use fixtures in Playwright** - Share common setup/teardown code across E2E tests
7. **Clear cache between tests** - Use `opcache_reset()` if old code is still served during PHP testing

## Troubleshooting

### Tests Still Using Old Code
If you modify PHP files but tests still run with old code, clear OPcache:
```bash
lando exec appserver -- php -r "opcache_reset();"
```

### Database State Issues
Reset the test database by running the bootstrap script:
```bash
lando wp db query < wordpress/wp-content/plugins/worldgraph/tests/bootstrap.php
```

### Node Modules Not Installed
Reinstall Playwright dependencies:
```bash
lando npm install
lando playwright install --with-deps chromium
```

### Playwright Port Conflicts
Ensure Playwright browser is not already running from a previous test:
```bash
lando exec cli -- pkill -f chromium
```
