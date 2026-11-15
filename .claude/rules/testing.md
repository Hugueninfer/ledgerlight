---
paths: ["api/tests/**", "web/tests/**", "web/**/*.test.ts", "tests/**"]
---

# Testing rules

- Tests never call real services. No live `Http` requests to Groq or Tallyline: use `Http::fake()` / `Http::preventStrayRequests()` so a missed fake fails loudly.
- **Groq and Tallyline are always faked**, in every test, including end-to-end of the pipeline. Fixtures for model output live next to the tests and mirror real response shapes, including malformed and low-confidence outputs.
- Tests do not depend on wall-clock time, randomness or the network: freeze time, seed randomness, fake queues and storage.
- A bug fix starts with a test that reproduces it.
- The route isolation harness (`api/tests/Feature/Tenancy/RouteIsolationTest.php`) must keep iterating every parameterised API route as a user of tenant B against tenant A resources. Register every new route parameter in `Tests\Support\TenantIsolation::ROUTE_PARAMETERS` (`api/tests/Support/TenantIsolation.php`) as a static closure creating the model in the given tenant.
- Shared test helpers live in namespaced classes under `api/tests/Support/`, not as global functions in test files: every test file loads into one Pest process, so two files declaring the same function fail with "cannot redeclare".
- Shell hook tests (`tests/hooks/`) print `SKIP` when a precondition cannot hold yet, never a silent pass.
