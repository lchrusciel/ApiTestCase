# ApiTestCase upgrade instructions

## Upgrading from v4 to v5

* Path resolution for `FIXTURES_DIR`, `EXPECTED_RESPONSE_DIR` and `MOCKED_RESPONSE_DIR` should be adjusted to reflect a relative position according to project root dir. 
* Support for SymfonyMockerContainer was dropped. If required it should be adjusted manually on clients app according to [SymfonyMockerContainer docs](https://github.com/PolishSymfonyCommunity/SymfonyMockerContainer#installation)
* If one was using default response path resolution(`../Responses/Expected`), then all files should we moved one folder up(`../Responses`).

## Upgrading from v3 to v4

* Change namespace from `Lakion\ApiTestCase` to `ApiTestCase``
* If you are using `polishsymfonycommunity/symfony-mocker-container`, you need to whitelist it as your requirement

## Upgrading from v2 to v3

* JSON output is not escaped any more when comparing it to expected data. Turn on `ESCAPE_JSON` flag in your test configuration retain previous behaviour.

  NOTE: This flag exists for temporary BC between v2 and v3 and will be removed in v4. v4 will not escape JSON output.

* A variable `KERNEL_CLASS_PATH` has been changed to `KERNEL_CLASS`

* Add `Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle()` and `Fidry\AliceDataFixtures\Bridge\Symfony\FidryAliceDataFixturesBundle()` to your bundles list in `AppKernel`, if they're not already added

* Default fixtures loader in Alice has been changed to PurgerLoader. To [not purge database after each fixtures loading](https://github.com/Sylius/Sylius/pull/9266) configure default purge mode.

```yml
fidry_alice_data_fixtures:
    default_purge_mode: no_purge
```
