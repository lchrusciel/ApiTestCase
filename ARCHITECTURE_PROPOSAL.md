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

## Next Steps

1. **Review this proposal** - Confirm the trait structure and naming
2. **Implement Layer 1 traits** - Start with independent traits
3. **Implement Layer 2 traits** - Infrastructure traits with dependencies
4. **Implement Layer 3 traits** - Feature traits
5. **Refactor existing classes** - Use traits internally
6. **Add tests for each trait** - Ensure independent testability
7. **Update documentation** - Show both approaches
8. **Release as minor version** - Non-breaking addition
