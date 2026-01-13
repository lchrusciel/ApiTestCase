# Trait-Oriented Architecture Proposal

## Executive Summary

This document proposes refactoring the ApiTestCase library from an inheritance-based architecture to a **trait-oriented** design inspired by Symfony's testing components and Mathias Noback's approach to composable test helpers.

---

## Current Architecture: Coupling Analysis

### Current Class Hierarchy

```
PHPUnit\Framework\TestCase
          │
          ▼
Symfony\Bundle\FrameworkBundle\Test\WebTestCase
          │
          ▼
    ApiTestCase (abstract)
     ┌────┴────┐
     ▼         ▼
JsonApiTestCase  XmlApiTestCase
```

### Current Functional Areas (Tightly Coupled)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            ApiTestCase                                       │
│                                                                              │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐ │
│  │    Kernel    │   │   Database   │   │   Fixture    │   │   Response   │ │
│  │   Lifecycle  │◄──│    Setup     │◄──│   Loading    │   │   Matching   │ │
│  │              │   │              │   │              │   │              │ │
│  │ $sharedKernel│   │$entityManager│   │$fixtureLoader│   │$matcherFactory│
│  │ createKernel │   │ setUpDatabase│   │loadFixtures* │   │ buildMatcher │ │
│  │ shutdown     │   │ purgeDatabase│   │getFixtureLoad│   │assertResponse│ │
│  └──────┬───────┘   └──────┬───────┘   └──────┬───────┘   └──────┬───────┘ │
│         │                  │                  │                  │         │
│         ▼                  ▼                  ▼                  ▼         │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐ │
│  │   Container  │   │     Path     │   │    HTTP      │   │   Content    │ │
│  │    Access    │◄──│  Resolution  │◄──│   Client     │   │  Formatting  │ │
│  │              │   │              │   │              │   │              │ │
│  │   $client    │   │ getFixtures  │   │  setUpClient │   │ prettifyJson │ │
│  │    get()     │   │ Folder()     │   │  $client     │   │ prettifyXml  │ │
│  └──────────────┘   └──────────────┘   └──────────────┘   └──────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Dependency Graph (Current State)

```
                    ┌─────────────────┐
                    │   Test Method   │
                    └────────┬────────┘
                             │ uses
          ┌──────────────────┼──────────────────┐
          ▼                  ▼                  ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  assertResponse │ │loadFixturesFrom*│ │      get()      │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         │ requires          │ requires          │ requires
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  buildMatcher() │ │getFixtureLoader │ │    $client      │
│  (abstract)     │ │                 │ │                 │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         │ requires          │ requires          │ set by
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ createMatcher() │ │ setUpDatabase() │ │  setUpClient()  │
│    #[Before]    │ │    #[Before]    │ │    #[Before]    │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         │                   │ requires          │ requires
         │                   ▼                   ▼
         │          ┌─────────────────┐ ┌─────────────────┐
         │          │  $sharedKernel  │ │ createSharedKern│
         │          │  ->getContainer │ │ #[BeforeClass]  │
         │          └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         │                   └─────────┬─────────┘
         │                             │
         ▼                             ▼
┌────────────────────────────────────────────────┐
│          Path Resolution Functions             │
│  getFixturesFolder() / getResponsesFolder()   │
│         getCalledClassFolder()                 │
│            getProjectDir()                     │
└────────────────────────────────────────────────┘
                        │
                        │ requires
                        ▼
              ┌─────────────────┐
              │   get('kernel') │
              │   via $client   │
              └─────────────────┘
```

### Coupling Problems Identified

| Problem | Severity | Description |
|---------|----------|-------------|
| **Implicit Lifecycle Dependencies** | CRITICAL | `#[Before]` methods have hidden execution order requirements |
| **All-or-Nothing Inheritance** | HIGH | Can't use response matching without database setup |
| **Database Always Configured** | HIGH | Even for simple JSON response tests, database code runs |
| **No Trait Composition** | HIGH | Zero reusability of individual concerns |
| **Path Resolution Coupled to Container** | MEDIUM | Path helpers require kernel access via HTTP client |
| **Environment Variable Configuration** | MEDIUM | Magic strings, no type safety |
| **JSON/XML Code Duplication** | MEDIUM | ~30 lines duplicated between subclasses |

---

## Proposed Architecture: Trait-Oriented Design

### Design Principles (Inspired by Symfony & Mathias Noback)

1. **Single Responsibility Traits** - Each trait handles exactly one concern
2. **Explicit Dependencies** - Traits declare what they need via abstract methods
3. **No Hidden State** - Properties are local to each trait when possible
4. **Composability** - Traits can be mixed in any combination
5. **Optional Features** - Use only what you need, pay only for what you use
6. **Backward Compatible** - Existing classes remain functional using trait composition

### Proposed Trait Structure

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        INDEPENDENT TRAITS (Layer 1)                          │
│              No dependencies on other traits - pure functionality            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐          │
│  │ PathBuilderTrait │  │ContentFormatTrait│  │MatcherAwareTrait │          │
│  │                  │  │                  │  │                  │          │
│  │ + buildPath()    │  │ + prettifyJson() │  │ + getMatcher()   │          │
│  │                  │  │ + prettifyXml()  │  │ + matchContent() │          │
│  │ No dependencies  │  │ No dependencies  │  │ No dependencies  │          │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘          │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                     INFRASTRUCTURE TRAITS (Layer 2)                          │
│             Provide access to external systems (Symfony, Doctrine)           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐          │
│  │KernelAwareTrait  │  │ContainerAwareTrait│  │ ClientAwareTrait │          │
│  │                  │  │                  │  │                  │          │
│  │ + getKernel()    │  │ + get(id)        │  │ + getClient()    │          │
│  │ + bootKernel()   │  │ + has(id)        │  │ + createClient() │          │
│  │                  │  │                  │  │                  │          │
│  │ abstract:        │  │ Requires:        │  │ Requires:        │          │
│  │  getKernelClass()│  │  KernelAware     │  │  KernelAware     │          │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘          │
│                                                                              │
│  ┌──────────────────┐                                                        │
│  │EntityManagerTrait│                                                        │
│  │                  │                                                        │
│  │ +getEntityMgr()  │                                                        │
│  │                  │                                                        │
│  │ Requires:        │                                                        │
│  │  ContainerAware  │                                                        │
│  └──────────────────┘                                                        │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       FEATURE TRAITS (Layer 3)                               │
│             High-level testing functionality combining lower layers          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐          │
│  │DatabasePurgeTrait│  │ FixtureLoadTrait │  │ResponseMatchTrait│          │
│  │                  │  │                  │  │                  │          │
│  │ + purgeDatabase()│  │ + loadFixtures() │  │+ assertResponse()│          │
│  │                  │  │ + loadFromDir()  │  │+ assertContent() │          │
│  │ Requires:        │  │ + loadFromFile() │  │                  │          │
│  │  EntityManager   │  │                  │  │ Requires:        │          │
│  │                  │  │ Requires:        │  │  MatcherAware    │          │
│  │                  │  │  ContainerAware  │  │  PathBuilder     │          │
│  │                  │  │  DatabasePurge   │  │                  │          │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘          │
│                                                                              │
│  ┌──────────────────┐  ┌──────────────────┐                                 │
│  │JsonAssertionTrait│  │XmlAssertionTrait │                                 │
│  │                  │  │                  │                                 │
│  │+assertJsonResp() │  │ +assertXmlResp() │                                 │
│  │+assertJsonHeader │  │ +assertXmlHeader │                                 │
│  │                  │  │                  │                                 │
│  │ Requires:        │  │ Requires:        │                                 │
│  │  ResponseMatch   │  │  ResponseMatch   │                                 │
│  │  ContentFormat   │  │  ContentFormat   │                                 │
│  └──────────────────┘  └──────────────────┘                                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    BACKWARD COMPATIBLE LAYER (Layer 4)                       │
│          Existing classes reimplemented using trait composition              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ApiTestCase (Backward Compatible - uses all traits internally)      │   │
│  │                                                                     │   │
│  │   use KernelAwareTrait;                                             │   │
│  │   use ContainerAwareTrait;                                          │   │
│  │   use ClientAwareTrait;                                             │   │
│  │   use EntityManagerAwareTrait;                                      │   │
│  │   use MatcherAwareTrait;                                            │   │
│  │   use PathBuilderTrait;                                             │   │
│  │   use ContentFormatterTrait;                                        │   │
│  │   use DatabasePurgeTrait;                                           │   │
│  │   use FixtureLoadTrait;                                             │   │
│  │   use ResponseMatchTrait;                                           │   │
│  │                                                                     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌───────────────────────────┐  ┌───────────────────────────────────────┐  │
│  │ JsonApiTestCase           │  │ XmlApiTestCase                        │  │
│  │                           │  │                                       │  │
│  │   extends ApiTestCase     │  │   extends ApiTestCase                 │  │
│  │   use JsonAssertionTrait; │  │   use XmlAssertionTrait;              │  │
│  └───────────────────────────┘  └───────────────────────────────────────┘  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Trait Dependency Graph

### Explicit Trait Requirements

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          TRAIT DEPENDENCY GRAPH                              │
└─────────────────────────────────────────────────────────────────────────────┘

 PathBuilderTrait          ContentFormatterTrait       MatcherAwareTrait
       │                          │                          │
       │ (standalone)             │ (standalone)             │ (standalone)
       ▼                          ▼                          ▼

 KernelAwareTrait ◄─────── ContainerAwareTrait ◄────── EntityManagerAwareTrait
       │                          │                          │
       │                          │                          │
       ▼                          ▼                          ▼
 ClientAwareTrait          FixtureLoadTrait            DatabasePurgeTrait
       │                    │         │                      │
       │                    │         │                      │
       └────────────────────┴─────────┴──────────────────────┘
                                 │
                                 ▼
                        ResponseMatchTrait
                          │         │
                          ▼         ▼
                JsonAssertionTrait  XmlAssertionTrait
```

### Trait Composition Rules

```
LEGEND:
  ──────►  "requires" (abstract methods that must be satisfied)
  ------►  "optionally uses" (can work without)

JsonAssertionTrait ──────► ResponseMatchTrait ──────► MatcherAwareTrait
                   ──────► ContentFormatterTrait
                   ------► ClientAwareTrait (for response access)

FixtureLoadTrait ──────► ContainerAwareTrait (for fidry_alice service)
                 ──────► PathBuilderTrait (for path resolution)
                 ------► DatabasePurgeTrait (optional auto-purge)

DatabasePurgeTrait ──────► EntityManagerAwareTrait

EntityManagerAwareTrait ──────► ContainerAwareTrait

ContainerAwareTrait ──────► KernelAwareTrait

ClientAwareTrait ──────► KernelAwareTrait
```

---

## Proposed Trait Interfaces

### Layer 1: Independent Traits

```php
trait PathBuilderTrait
{
    private ?string $fixturesPath = null;
    private ?string $responsesPath = null;

    protected function buildPath(string ...$segments): string;
    protected function getFixturesPath(): string;
    protected function getExpectedResponsesPath(): string;

    // Configuration hook - can be overridden
    protected function getDefaultFixturesDirectory(): string;
    protected function getDefaultResponsesDirectory(): string;
}

trait ContentFormatterTrait
{
    protected function prettifyJson(string $content, int $flags = 0): string;
    protected function prettifyXml(string $content): string;
}

trait MatcherAwareTrait
{
    private ?Matcher $matcher = null;

    protected function getMatcher(): Matcher;
    protected function match(string $actual, string $expected): bool;
    protected function getMatcherError(): string;

    // Configuration hook
    protected function createMatcher(): Matcher;
}
```

### Layer 2: Infrastructure Traits

```php
trait KernelAwareTrait
{
    private static ?KernelInterface $kernel = null;

    protected static function bootKernel(array $options = []): KernelInterface;
    protected static function getKernel(): KernelInterface;
    protected static function ensureKernelShutdown(): void;

    // Abstract - must be implemented
    abstract protected static function getKernelClass(): string;
}

trait ContainerAwareTrait
{
    // Requires: KernelAwareTrait (via abstract)
    abstract protected static function getKernel(): KernelInterface;

    protected function getContainer(): ContainerInterface;
    protected function get(string $id): ?object;
    protected function has(string $id): bool;
}

trait ClientAwareTrait
{
    private ?KernelBrowser $client = null;

    // Requires: KernelAwareTrait
    abstract protected static function getKernel(): KernelInterface;

    protected function getClient(): KernelBrowser;
    protected function createClient(array $options = [], array $server = []): KernelBrowser;

    // Configuration hook
    protected function getDefaultClientOptions(): array;
    protected function getDefaultServerParameters(): array;
}

trait EntityManagerAwareTrait
{
    private ?EntityManagerInterface $entityManager = null;

    // Requires: ContainerAwareTrait
    abstract protected function get(string $id): ?object;

    protected function getEntityManager(): EntityManagerInterface;

    // Configuration hook
    protected function getEntityManagerServiceId(): string;
}
```

### Layer 3: Feature Traits

```php
trait DatabasePurgeTrait
{
    // Requires: EntityManagerAwareTrait
    abstract protected function getEntityManager(): EntityManagerInterface;

    protected function purgeDatabase(): void;
    protected function truncateDatabase(): void;

    // Configuration hook
    protected function createPurger(): ORMPurger;
}

trait FixtureLoadTrait
{
    private ?LoaderInterface $fixtureLoader = null;

    // Requires: ContainerAwareTrait, PathBuilderTrait
    abstract protected function get(string $id): ?object;
    abstract protected function getFixturesPath(): string;

    protected function loadFixturesFromDirectory(string $subPath = ''): array;
    protected function loadFixturesFromFile(string $filename): array;
    protected function loadFixturesFromFiles(array $filenames): array;

    // Configuration hooks
    protected function getFixtureLoaderServiceId(): string;
    protected function shouldPurgeBeforeLoad(): bool;
}

trait ResponseMatchTrait
{
    // Requires: MatcherAwareTrait, PathBuilderTrait
    abstract protected function getMatcher(): Matcher;
    abstract protected function getExpectedResponsesPath(): string;
    abstract protected function buildPath(string ...$segments): string;

    protected function assertResponseCode(Response $response, int $expected): void;
    protected function assertHeader(Response $response, string $contains): void;
    protected function assertResponseContent(string $actual, string $filename, string $ext): void;
}

trait JsonAssertionTrait
{
    // Requires: ResponseMatchTrait, ContentFormatterTrait
    abstract protected function assertResponseCode(Response $response, int $expected): void;
    abstract protected function assertHeader(Response $response, string $contains): void;
    abstract protected function assertResponseContent(string $actual, string $filename, string $ext): void;
    abstract protected function prettifyJson(string $content, int $flags = 0): string;

    protected function assertJsonResponse(Response $response, string $filename, int $code = 200): void;
    protected function assertJsonHeader(Response $response): void;
    protected function assertJsonContent(Response $response, string $filename): void;
}

trait XmlAssertionTrait
{
    // Requires: ResponseMatchTrait, ContentFormatterTrait
    abstract protected function assertResponseCode(Response $response, int $expected): void;
    abstract protected function assertHeader(Response $response, string $contains): void;
    abstract protected function assertResponseContent(string $actual, string $filename, string $ext): void;
    abstract protected function prettifyXml(string $content): string;

    protected function assertXmlResponse(Response $response, string $filename, int $code = 200): void;
    protected function assertXmlHeader(Response $response): void;
    protected function assertXmlContent(Response $response, string $filename): void;
}
```

---

## Use Cases: Composable Testing

### Use Case 1: JSON Response Testing Only (No Database)

```php
use PHPUnit\Framework\TestCase;

class MyApiTest extends TestCase
{
    use KernelAwareTrait;
    use ClientAwareTrait;
    use MatcherAwareTrait;
    use PathBuilderTrait;
    use ContentFormatterTrait;
    use ResponseMatchTrait;
    use JsonAssertionTrait;

    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    public function testEndpoint(): void
    {
        $client = $this->getClient();
        $client->request('GET', '/api/products');

        $this->assertJsonResponse($client->getResponse(), 'products_list');
    }
}
```

### Use Case 2: Database Testing Only (No Response Matching)

```php
use PHPUnit\Framework\TestCase;

class MyRepositoryTest extends TestCase
{
    use KernelAwareTrait;
    use ContainerAwareTrait;
    use EntityManagerAwareTrait;
    use DatabasePurgeTrait;
    use PathBuilderTrait;
    use FixtureLoadTrait;

    protected function setUp(): void
    {
        $this->purgeDatabase();
    }

    public function testRepository(): void
    {
        $fixtures = $this->loadFixturesFromFile('products.yaml');

        $repo = $this->getEntityManager()->getRepository(Product::class);
        $this->assertCount(3, $repo->findAll());
    }
}
```

### Use Case 3: Full API Testing (Current Behavior)

```php
// Backward compatible - works exactly as before
class MyTest extends JsonApiTestCase
{
    public function testProductApi(): void
    {
        $this->loadFixturesFromDirectory();
        $this->client->request('GET', '/api/products');
        $this->assertResponse($this->client->getResponse(), 'products');
    }
}
```

### Use Case 4: Custom Composition

```php
use PHPUnit\Framework\TestCase;

class GraphQLApiTest extends TestCase
{
    use KernelAwareTrait;
    use ClientAwareTrait;
    use MatcherAwareTrait;
    use PathBuilderTrait;
    use ContentFormatterTrait;
    use ResponseMatchTrait;
    use JsonAssertionTrait;
    use EntityManagerAwareTrait;
    use DatabasePurgeTrait;
    use FixtureLoadTrait;

    // Custom: GraphQL-specific client setup
    protected function getDefaultServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    protected function query(string $query, array $variables = []): Response
    {
        $this->getClient()->request('POST', '/graphql', [], [], [], json_encode([
            'query' => $query,
            'variables' => $variables,
        ]));

        return $this->getClient()->getResponse();
    }
}
```

---

## Migration Path

### Phase 1: Extract Traits (Non-Breaking)

1. Create all traits in `src/Trait/` directory
2. Existing classes remain unchanged
3. New traits are optional additions

```
src/
├── ApiTestCase.php           (unchanged)
├── JsonApiTestCase.php       (unchanged)
├── XmlApiTestCase.php        (unchanged)
├── PathBuilder.php           (unchanged)
└── Trait/
    ├── PathBuilderTrait.php
    ├── ContentFormatterTrait.php
    ├── MatcherAwareTrait.php
    ├── KernelAwareTrait.php
    ├── ContainerAwareTrait.php
    ├── ClientAwareTrait.php
    ├── EntityManagerAwareTrait.php
    ├── DatabasePurgeTrait.php
    ├── FixtureLoadTrait.php
    ├── ResponseMatchTrait.php
    ├── JsonAssertionTrait.php
    └── XmlAssertionTrait.php
```

### Phase 2: Refactor Base Classes to Use Traits (Non-Breaking)

```php
// ApiTestCase.php - refactored internally but API unchanged
abstract class ApiTestCase extends WebTestCase
{
    use KernelAwareTrait;
    use ContainerAwareTrait;
    use ClientAwareTrait;
    use EntityManagerAwareTrait;
    use MatcherAwareTrait;
    use PathBuilderTrait;
    use ContentFormatterTrait;
    use DatabasePurgeTrait;
    use FixtureLoadTrait;
    use ResponseMatchTrait;

    // Deprecated methods point to trait methods
    // All existing public/protected API preserved
}
```

### Phase 3: Documentation & Promotion

1. Document trait-based approach as recommended
2. Provide migration examples
3. Mark inheritance-based approach as legacy (but supported)

### Phase 4: Next Major Version (Breaking)

1. Remove deprecated methods from base classes
2. Base classes become thin wrappers around traits
3. Encourage direct trait usage

---

## Backward Compatibility Strategy

### Preserved Public API

All existing methods remain available:

| Method | Status | Implementation |
|--------|--------|----------------|
| `setUpClient()` | Preserved | Delegates to `ClientAwareTrait` |
| `setUpDatabase()` | Preserved | Delegates to `EntityManagerAwareTrait` + `DatabasePurgeTrait` |
| `createMatcher()` | Preserved | Delegates to `MatcherAwareTrait` |
| `loadFixturesFromDirectory()` | Preserved | Delegates to `FixtureLoadTrait` |
| `loadFixturesFromFile()` | Preserved | Delegates to `FixtureLoadTrait` |
| `loadFixturesFromFiles()` | Preserved | Delegates to `FixtureLoadTrait` |
| `assertResponse()` | Preserved | Delegates to `JsonAssertionTrait` / `XmlAssertionTrait` |
| `assertResponseCode()` | Preserved | Delegates to `ResponseMatchTrait` |
| `assertHeader()` | Preserved | Delegates to `ResponseMatchTrait` |
| `get()` | Preserved | Delegates to `ContainerAwareTrait` |
| `purgeDatabase()` | Preserved | Delegates to `DatabasePurgeTrait` |
| `getEntityManager()` | Preserved | Delegates to `EntityManagerAwareTrait` |

### Property Compatibility

| Property | Strategy |
|----------|----------|
| `$client` | Preserved, populated by trait |
| `$sharedKernel` | Preserved as static, managed by trait |
| `$matcherFactory` | Internal to trait, not exposed |
| `$entityManager` | Internal to trait, accessor preserved |
| `$fixtureLoader` | Internal to trait, accessor preserved |

---

## Summary: Before vs After

### Before (Current)

```
┌──────────────────────────────────────────┐
│           Inheritance-Based              │
│                                          │
│  • Must extend ApiTestCase               │
│  • Get ALL features or NOTHING           │
│  • Database setup always runs            │
│  • Can't customize lifecycle             │
│  • Implicit method dependencies          │
│  • Hard to test specific concerns        │
└──────────────────────────────────────────┘
```

### After (Proposed)

```
┌──────────────────────────────────────────┐
│            Trait-Based                   │
│                                          │
│  • Compose only what you need            │
│  • Mix traits in any TestCase            │
│  • Explicit dependencies via abstract    │
│  • Each trait is independently testable  │
│  • Clear lifecycle hooks                 │
│  • Backward compatible with old style    │
└──────────────────────────────────────────┘
```

---

## Files to Create

```
src/Trait/
├── PathBuilderTrait.php           (~40 lines)
├── ContentFormatterTrait.php      (~35 lines)
├── MatcherAwareTrait.php          (~45 lines)
├── KernelAwareTrait.php           (~50 lines)
├── ContainerAwareTrait.php        (~30 lines)
├── ClientAwareTrait.php           (~45 lines)
├── EntityManagerAwareTrait.php    (~35 lines)
├── DatabasePurgeTrait.php         (~30 lines)
├── FixtureLoadTrait.php           (~70 lines)
├── ResponseMatchTrait.php         (~60 lines)
├── JsonAssertionTrait.php         (~50 lines)
└── XmlAssertionTrait.php          (~50 lines)

Total: ~540 lines of new code (traits)
Refactored: ~520 lines (existing classes use traits)
```

---

---

## Backward Compatibility Verification Strategy

### Overview

Ensuring backward compatibility (BC) is critical for this refactoring. The goal is that **any existing test extending `ApiTestCase`, `JsonApiTestCase`, or `XmlApiTestCase` must work without modification** after the refactoring.

### BC Verification Layers

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    BACKWARD COMPATIBILITY VERIFICATION                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Layer 1: Static Analysis                                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Roave BC Check  │  PHPStan  │  Psalm  │  Rector (dry-run)         │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  Layer 2: Unit & Integration Tests                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Existing Tests  │  New Trait Tests  │  BC Contract Tests          │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  Layer 3: Real-World Project Validation                                      │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Sylius  │  Sylius Plugins  │  Community Projects  │  Internal     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  Layer 4: Release Validation                                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Alpha Release  │  Beta with Adopters  │  RC  │  Stable            │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### Layer 1: Static Analysis Tools

#### 1.1 Roave Backward Compatibility Check

**Tool**: `roave/backward-compatibility-check`

**Purpose**: Automatically detect BC breaks in public/protected API

**Setup**:
```bash
composer require --dev roave/backward-compatibility-check
```

**CI Integration** (GitHub Actions):
```yaml
# .github/workflows/bc-check.yml
name: BC Check

on:
  pull_request:
    branches: [main, master]

jobs:
  bc-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-progress

      - name: Roave BC Check
        run: vendor/bin/roave-backward-compatibility-check --from=origin/main
```

**What it checks**:
- Removed public/protected methods
- Changed method signatures
- Removed public/protected properties
- Changed property visibility
- Removed classes/interfaces/traits
- Changed class hierarchy

**Expected violations to address**:
| Change | BC Impact | Mitigation |
|--------|-----------|------------|
| New abstract method in trait | BREAK | Use default implementation |
| New required parameter | BREAK | Use optional with default |
| Return type narrowing | BREAK | Keep original return type |
| Property type change | BREAK | Keep original type or mixed |

#### 1.2 PHPStan Analysis

**Configuration** (`phpstan.neon`):
```neon
parameters:
    level: 8
    paths:
        - src
    checkGenericClassInNonGenericObjectType: false

    # BC-specific rules
    reportUnmatchedIgnoredErrors: false

includes:
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon
```

**Custom BC Rule** (optional):
```php
// Create custom rule to ensure trait methods don't change signatures
namespace ApiTestCase\PHPStan;

class TraitMethodSignatureRule implements Rule
{
    // Verify trait methods match expected signatures from base classes
}
```

#### 1.3 Psalm for Type Safety

**Configuration** (`psalm.xml`):
```xml
<?xml version="1.0"?>
<psalm errorLevel="3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <projectFiles>
        <directory name="src" />
    </projectFiles>

    <plugins>
        <pluginClass class="Psalm\SymfonyPsalmPlugin\Plugin" />
    </plugins>

    <!-- Ensure no BC breaks in return types -->
    <issueHandlers>
        <MoreSpecificReturnType errorLevel="error" />
        <LessSpecificReturnStatement errorLevel="error" />
    </issueHandlers>
</psalm>
```

#### 1.4 Rector Dry-Run

**Purpose**: Identify potential upgrade paths and incompatibilities

```bash
# Check what Rector would change (without actually changing)
vendor/bin/rector process src --dry-run
```

---

### Layer 2: Testing Strategy

#### 2.1 Existing Test Suite

**Requirement**: All existing tests MUST pass without modification

```yaml
# CI must run existing tests before and after refactoring
jobs:
  test-existing:
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
        symfony: ['6.4', '7.0', '7.1', '7.2']
    steps:
      - run: vendor/bin/phpunit
```

#### 2.2 New Trait Unit Tests

Each trait should have dedicated tests:

```
test/
├── Unit/
│   └── Trait/
│       ├── PathBuilderTraitTest.php
│       ├── ContentFormatterTraitTest.php
│       ├── MatcherAwareTraitTest.php
│       ├── KernelAwareTraitTest.php
│       ├── ContainerAwareTraitTest.php
│       ├── ClientAwareTraitTest.php
│       ├── EntityManagerAwareTraitTest.php
│       ├── DatabasePurgeTraitTest.php
│       ├── FixtureLoadTraitTest.php
│       ├── ResponseMatchTraitTest.php
│       ├── JsonAssertionTraitTest.php
│       └── XmlAssertionTraitTest.php
```

**Example Trait Test**:
```php
class PathBuilderTraitTest extends TestCase
{
    public function testBuildPathConcatenatesSegments(): void
    {
        $instance = new class {
            use PathBuilderTrait;

            public function exposeBuildPath(string ...$segments): string
            {
                return $this->buildPath(...$segments);
            }
        };

        $this->assertSame(
            'foo' . DIRECTORY_SEPARATOR . 'bar' . DIRECTORY_SEPARATOR . 'baz',
            $instance->exposeBuildPath('foo', 'bar', 'baz')
        );
    }
}
```

#### 2.3 BC Contract Tests

**Purpose**: Explicitly test that the refactored classes maintain the same behavior

```php
namespace ApiTestCase\Test\BackwardCompatibility;

/**
 * These tests verify that the public API of ApiTestCase remains unchanged.
 * Each test corresponds to a documented feature that users depend on.
 */
class ApiTestCaseBCTest extends TestCase
{
    public function testClientPropertyIsAccessible(): void
    {
        $test = new ConcreteApiTestCase();
        $test->setUpClient();

        // Protected property must still exist and be accessible to subclasses
        $this->assertInstanceOf(KernelBrowser::class, $test->getClientForTest());
    }

    public function testSetUpClientIsCallableAsBefore(): void
    {
        $test = new ConcreteApiTestCase();

        // Method must have #[Before] attribute
        $method = new \ReflectionMethod($test, 'setUpClient');
        $attributes = $method->getAttributes(Before::class);

        $this->assertNotEmpty($attributes, 'setUpClient must have #[Before] attribute');
    }

    public function testLoadFixturesReturnsArray(): void
    {
        $test = new ConcreteApiTestCase();
        $test->setUpClient();
        $test->setUpDatabase();

        $result = $test->loadFixturesFromFile('test.yml');

        $this->assertIsArray($result);
    }

    public function testGetMethodReturnsService(): void
    {
        $test = new ConcreteApiTestCase();
        $test->setUpClient();

        $kernel = $test->get('kernel');

        $this->assertInstanceOf(KernelInterface::class, $kernel);
    }

    // ... more BC contract tests for each public/protected method
}
```

#### 2.4 Signature Contract Tests

**Purpose**: Ensure method signatures haven't changed

```php
class MethodSignatureContractTest extends TestCase
{
    /**
     * @dataProvider methodSignatureProvider
     */
    public function testMethodSignatureUnchanged(
        string $class,
        string $method,
        array $expectedParams,
        string $expectedReturnType
    ): void {
        $reflection = new \ReflectionMethod($class, $method);

        // Check parameter count and types
        $params = $reflection->getParameters();
        $this->assertCount(count($expectedParams), $params);

        foreach ($expectedParams as $index => $expected) {
            $this->assertSame($expected['name'], $params[$index]->getName());
            $this->assertSame($expected['type'], (string) $params[$index]->getType());
            $this->assertSame($expected['optional'], $params[$index]->isOptional());
        }

        // Check return type
        $this->assertSame($expectedReturnType, (string) $reflection->getReturnType());
    }

    public static function methodSignatureProvider(): array
    {
        return [
            'ApiTestCase::get' => [
                ApiTestCase::class,
                'get',
                [['name' => 'id', 'type' => 'string', 'optional' => false]],
                '?object',
            ],
            'ApiTestCase::loadFixturesFromFile' => [
                ApiTestCase::class,
                'loadFixturesFromFile',
                [['name' => 'source', 'type' => 'string', 'optional' => false]],
                'array',
            ],
            'ApiTestCase::assertResponseCode' => [
                ApiTestCase::class,
                'assertResponseCode',
                [
                    ['name' => 'response', 'type' => Response::class, 'optional' => false],
                    ['name' => 'statusCode', 'type' => 'int', 'optional' => false],
                ],
                'void',
            ],
            // ... all public/protected methods
        ];
    }
}
```

---

### Layer 3: Real-World Project Validation

#### 3.1 Sylius Validation (Primary)

**Why Sylius**: Largest and most complex user of ApiTestCase

**Validation Process**:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SYLIUS VALIDATION PROCESS                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Step 1: Fork Sylius                                                         │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  git clone https://github.com/Sylius/Sylius.git                     │   │
│  │  cd Sylius                                                          │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  Step 2: Point to development version of ApiTestCase                         │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  composer.json:                                                     │   │
│  │  {                                                                  │   │
│  │    "repositories": [{                                               │   │
│  │      "type": "path",                                                │   │
│  │      "url": "../ApiTestCase"                                        │   │
│  │    }],                                                              │   │
│  │    "require-dev": {                                                 │   │
│  │      "lchrusciel/api-test-case": "@dev"                            │   │
│  │    }                                                                │   │
│  │  }                                                                  │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  Step 3: Run Sylius API test suite                                           │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  vendor/bin/phpunit --testsuite="API Test Suite"                    │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  Step 4: Compare results                                                     │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  • All tests must pass                                              │   │
│  │  • No new deprecation warnings                                      │   │
│  │  • Performance within 5% of baseline                                │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Automated Sylius Testing** (GitHub Actions):

```yaml
# .github/workflows/sylius-validation.yml
name: Sylius Validation

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  sylius-validation:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: sylius_test
        ports:
          - 3306:3306

    steps:
      - name: Checkout ApiTestCase
        uses: actions/checkout@v4
        with:
          path: api-test-case

      - name: Checkout Sylius
        uses: actions/checkout@v4
        with:
          repository: Sylius/Sylius
          path: sylius

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: intl, pdo_mysql

      - name: Configure Sylius to use local ApiTestCase
        run: |
          cd sylius
          composer config repositories.api-test-case '{"type": "path", "url": "../api-test-case"}'
          composer require lchrusciel/api-test-case:@dev --no-update
          composer update lchrusciel/api-test-case --with-dependencies

      - name: Setup Sylius
        run: |
          cd sylius
          bin/console doctrine:database:create --if-not-exists
          bin/console doctrine:migrations:migrate --no-interaction
          bin/console sylius:fixtures:load default --no-interaction

      - name: Run Sylius API Tests
        run: |
          cd sylius
          vendor/bin/phpunit --testsuite="API Test Suite" --log-junit=results.xml

      - name: Upload Results
        uses: actions/upload-artifact@v4
        with:
          name: sylius-test-results
          path: sylius/results.xml
```

#### 3.2 Sylius Plugins Validation

**Sample Plugins to Test**:
- `Sylius/InvoicingPlugin`
- `Sylius/RefundPlugin`
- `BitBag/SyliusCmsPlugin`
- `Setono/SyliusAnalyticsPlugin`

**Validation Script**:
```bash
#!/bin/bash
# validate-plugins.sh

PLUGINS=(
    "Sylius/InvoicingPlugin"
    "Sylius/RefundPlugin"
    "BitBag/SyliusCmsPlugin"
)

for plugin in "${PLUGINS[@]}"; do
    echo "Testing $plugin..."
    git clone "https://github.com/$plugin.git" "plugin-test"
    cd plugin-test

    composer config repositories.api-test-case '{"type": "path", "url": "../../api-test-case"}'
    composer require lchrusciel/api-test-case:@dev --no-update
    composer update

    vendor/bin/phpunit || echo "FAILED: $plugin"

    cd ..
    rm -rf plugin-test
done
```

#### 3.3 Community Project Testing

**Call for Testers** (Release Notes):
```markdown
## Testing the New Trait Architecture

We're introducing a trait-oriented architecture in version X.Y.
We need community help to validate backward compatibility.

### How to Test

1. Add to your `composer.json`:
   ```json
   {
       "require-dev": {
           "lchrusciel/api-test-case": "X.Y.0-beta1"
       }
   }
   ```

2. Run your test suite:
   ```bash
   vendor/bin/phpunit
   ```

3. Report issues at: https://github.com/lchrusciel/ApiTestCase/issues

### What to Report
- Test failures (with full output)
- Deprecation warnings
- Performance regressions
- Any behavioral changes
```

---

### Layer 4: Release Strategy

#### 4.1 Versioning Strategy

```
Current:  1.x (stable)
          │
          ▼
Phase 1:  2.0.0-alpha.1 (traits added, classes unchanged)
          │
          ▼
Phase 2:  2.0.0-beta.1 (classes refactored to use traits)
          │             (BC validation complete)
          ▼
Phase 3:  2.0.0-rc.1   (Sylius validated, community tested)
          │
          ▼
Phase 4:  2.0.0        (stable release)
          │
          ▼
Future:   3.0.0        (deprecated methods removed)
```

#### 4.2 Release Checklist

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          RELEASE CHECKLIST                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  □ Alpha Release (2.0.0-alpha.1)                                            │
│    □ All traits implemented                                                  │
│    □ Trait unit tests passing                                                │
│    □ Existing tests unchanged and passing                                    │
│    □ Roave BC Check: no breaks detected                                      │
│                                                                              │
│  □ Beta Release (2.0.0-beta.1)                                              │
│    □ Classes refactored to use traits internally                             │
│    □ BC contract tests passing                                               │
│    □ Method signature tests passing                                          │
│    □ PHPStan level 8 passing                                                 │
│    □ Psalm passing                                                           │
│    □ Internal Sylius test passing                                            │
│                                                                              │
│  □ Release Candidate (2.0.0-rc.1)                                           │
│    □ Full Sylius test suite passing                                          │
│    □ At least 3 Sylius plugins validated                                     │
│    □ Community beta feedback addressed                                       │
│    □ Documentation updated                                                   │
│    □ UPGRADE.md written                                                      │
│    □ CHANGELOG.md updated                                                    │
│                                                                              │
│  □ Stable Release (2.0.0)                                                   │
│    □ No new issues in RC for 2 weeks                                         │
│    □ Performance benchmarks acceptable                                       │
│    □ All CI checks green                                                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 4.3 CI/CD Pipeline

```yaml
# .github/workflows/release-validation.yml
name: Release Validation

on:
  push:
    tags:
      - 'v*.*.*-*'  # Pre-releases
      - 'v*.*.*'    # Stable releases

jobs:
  # Stage 1: Static Analysis
  static-analysis:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Roave BC Check
        run: vendor/bin/roave-backward-compatibility-check --from=$(git describe --tags --abbrev=0 HEAD^)

      - name: PHPStan
        run: vendor/bin/phpstan analyse

      - name: Psalm
        run: vendor/bin/psalm

  # Stage 2: Test Suite
  test-suite:
    needs: static-analysis
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
        symfony: ['6.4', '7.0', '7.1', '7.2']
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}

      - name: Install dependencies
        run: |
          composer require symfony/framework-bundle:^${{ matrix.symfony }} --no-update
          composer install

      - name: Run tests
        run: vendor/bin/phpunit

  # Stage 3: Sylius Validation
  sylius-validation:
    needs: test-suite
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
    steps:
      - name: Checkout and test with Sylius
        run: |
          # Full Sylius validation script
          # (as defined in Layer 3.1)

  # Stage 4: Create Release
  create-release:
    needs: [static-analysis, test-suite, sylius-validation]
    runs-on: ubuntu-latest
    if: startsWith(github.ref, 'refs/tags/v') && !contains(github.ref, '-')
    steps:
      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          generate_release_notes: true
```

---

### Additional BC Safeguards

#### 1. Deprecation Warnings

When methods are moved to traits, add deprecation notices for direct overrides:

```php
abstract class ApiTestCase extends WebTestCase
{
    use KernelAwareTrait;
    use ContainerAwareTrait;
    // ... other traits

    /**
     * @deprecated since 2.0, use ContainerAwareTrait::get() directly
     */
    protected function get(string $id): ?object
    {
        trigger_deprecation(
            'lchrusciel/api-test-case',
            '2.0',
            'Calling %s::get() is deprecated, the method is now provided by ContainerAwareTrait.',
            static::class
        );

        return $this->doGet($id);  // Delegate to trait method
    }
}
```

#### 2. Runtime BC Assertions

```php
trait BackwardCompatibilityAssertionsTrait
{
    protected function assertBackwardCompatibility(): void
    {
        // Verify expected properties exist
        assert(property_exists($this, 'client'), 'BC Break: $client property must exist');
        assert(property_exists($this, 'matcherFactory'), 'BC Break: $matcherFactory property must exist');

        // Verify expected methods exist
        assert(method_exists($this, 'setUpClient'), 'BC Break: setUpClient() must exist');
        assert(method_exists($this, 'loadFixturesFromFile'), 'BC Break: loadFixturesFromFile() must exist');
    }
}
```

#### 3. Mutation Testing (Optional)

Use Infection to ensure tests adequately cover BC-critical code:

```bash
composer require --dev infection/infection
vendor/bin/infection --filter=src/ApiTestCase.php --min-msi=80
```

---

### Performance Validation

#### Benchmark Tests

```php
class PerformanceBenchmarkTest extends TestCase
{
    private const MAX_SETUP_TIME_MS = 100;
    private const MAX_FIXTURE_LOAD_TIME_MS = 500;
    private const MAX_ASSERTION_TIME_MS = 50;

    public function testSetupClientPerformance(): void
    {
        $start = microtime(true);

        $test = new ConcreteJsonApiTestCase();
        $test->setUpClient();

        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(
            self::MAX_SETUP_TIME_MS,
            $elapsed,
            sprintf('setUpClient() took %.2fms, max allowed is %dms', $elapsed, self::MAX_SETUP_TIME_MS)
        );
    }

    public function testFixtureLoadPerformance(): void
    {
        $test = new ConcreteJsonApiTestCase();
        $test->setUpClient();
        $test->setUpDatabase();

        $start = microtime(true);
        $test->loadFixturesFromFile('products.yml');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(
            self::MAX_FIXTURE_LOAD_TIME_MS,
            $elapsed,
            sprintf('loadFixturesFromFile() took %.2fms', $elapsed)
        );
    }
}
```

---

### Documentation Requirements

#### UPGRADE.md

```markdown
# Upgrading from 1.x to 2.0

## Backward Compatible Changes (No Action Required)

The 2.0 release introduces a trait-based architecture while maintaining
full backward compatibility. Your existing tests will continue to work
without modification.

## New Features

You can now compose test functionality using individual traits:

```php
// Before: Must extend full class hierarchy
class MyTest extends JsonApiTestCase { }

// After: Can compose only what you need
class MyTest extends TestCase
{
    use ClientAwareTrait;
    use JsonAssertionTrait;
}
```

## Deprecations

The following patterns are deprecated and will be removed in 3.0:

1. Direct property access to `$matcherFactory` - use `getMatcher()` instead
2. Overriding `buildMatcher()` - implement `createMatcher()` hook instead
3. ...

## Breaking Changes (None in 2.0)

There are no breaking changes in this release.
```

---

## Summary: BC Verification Approach

| Tool/Method | Purpose | When to Run |
|-------------|---------|-------------|
| **Roave BC Check** | Detect API breaks | Every PR, pre-release |
| **PHPStan** | Type safety | Every PR |
| **Psalm** | Deep type analysis | Every PR |
| **BC Contract Tests** | Verify method signatures | Every PR |
| **Sylius Validation** | Real-world testing | Pre-release |
| **Plugin Validation** | Ecosystem testing | Pre-release |
| **Community Beta** | Wide testing | Beta phase |
| **Performance Benchmarks** | No regression | Pre-release |

---

## Next Steps

1. **Review this proposal** - Confirm the trait structure and naming
2. **Implement Layer 1 traits** - Start with independent traits
3. **Implement Layer 2 traits** - Infrastructure traits with dependencies
4. **Implement Layer 3 traits** - Feature traits
5. **Refactor existing classes** - Use traits internally
6. **Add tests for each trait** - Ensure independent testability
7. **Add BC contract tests** - Verify signatures unchanged
8. **Setup CI with Roave BC Check** - Automated BC verification
9. **Validate with Sylius** - Real-world testing
10. **Update documentation** - Show both approaches
11. **Release as alpha** - Early adopter feedback
12. **Release as beta** - Wider testing
13. **Release as stable** - After validation complete
