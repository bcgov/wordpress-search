# Automated testing
All tests require `wp-env` to be running. `wp-env` makes it easy to provide a basic containerized WordPress environment is running for PHP unit tests and E2E tests which is also used in the GitHub workflows.
To start `wp-env`:
```bash
npm run wp-env start
```

If you get an error like `Bind for 0.0.0.0:8888 failed: port is already allocated`, it's likely that another instance of wp-env is running somewhere else. Shut that instance down in order to start it in this project.

## WordPress PHP unit tests
This project uses [PHPUnit](https://phpunit.de/index.html) and [WordPress' recommended testing setup.](https://make.wordpress.org/cli/handbook/how-to/plugin-unit-tests/).

### Running locally
```bash
npm run test:unit:php
```

### With coverage report
```bash
npm run test:unit:php:coverage
```
The report will be generated in `coverage/php/`.

## WordPress E2E tests
This project uses [Playwright](https://playwright.dev/) for end-to-end (E2E) testing of WordPress blocks.

### Running locally
```bash
npm run test:e2e
```

### Running in debug mode
```bash
npx playwright test --ui
# or
npm run test:e2e:debug
```
These are very useful when writing tests. See [Playwright debugging documentation](https://playwright.dev/docs/running-tests#debugging-tests) for usage.

## Screenshot testing
Playwright is also used for screenshot testing to check for visual regressions.

### Running locally
```bash
npm run test:screenshot
```

### Updating screenshots
Playwright compares screenshots against stored baselines.
If you've intentionally made changes to a block and need to update snapshots:
```bash
npm run test:screenshot:update
```