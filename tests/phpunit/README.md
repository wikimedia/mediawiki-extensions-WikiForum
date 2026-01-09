# WikiForum Test Suite

PHPUnit tests for the WikiForum extension.

## Quick Start

```bash
# Run all tests
php tests/phpunit/phpunit.php --group WikiForum

# Run specific test file
php tests/phpunit/phpunit.php extensions/WikiForum/tests/phpunit/integration/WFCategoryTest.php

# Run specific test method
php tests/phpunit/phpunit.php --filter testNewFromID extensions/WikiForum/tests/phpunit/integration/WFCategoryTest.php
```

## Structure

- `unit/` - Unit tests (MediaWikiUnitTestCase)
- `integration/` - Integration tests (MediaWikiIntegrationTestCase)

## Test Coverage

- Model classes: WFCategory, WFForum, WFThread, WFReply
- Utilities: WikiForum
- GUI: WikiForumGui (unit and integration tests)
- Hooks: WikiForumHooks
- API modules: ApiWikiForumAdminDelete, ApiWikiForumSetThreadStickiness, ApiWikiForumSort, ApiWikiForumDeleteThread
- Special pages: SpecialWikiForum
- Jobs: LockInactiveThreadJob
