# ApiTestCase upgrade instructions

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
