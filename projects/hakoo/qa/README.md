# QA: testing hakoo.eu

The quality assurance work for hakoo.eu, structured according to the
**ISTQB Certified Tester Foundation Level (CTFL) v4.0** syllabus, with test
documentation modelled on **ISO/IEC/IEEE 29119-3** and quality characteristics
from **ISO/IEC 25010**.

## Where everything is

| ISTQB activity (CTFL 1.4) | Work products | Folder / file |
|---|---|---|
| Test planning | Test plan, risk register | [test-plan.md](test-plan.md), [risk-register.md](risk-register.md) |
| Test analysis (*what* to test) | **Test basis:** requirements, user stories; test conditions | [requirements/](requirements/) |
| Test design (*how* to test) | Test cases designed with test techniques | [test-design/](test-design/) |
| Test implementation | Test scripts, test data, test environment | [automation/](automation/), [../docker/](../docker/) |
| Test execution | Test results, defect reports | `test-results/` (generated), [defects/](defects/) |
| Test monitoring and control | Traceability, progress | [traceability-matrix.md](traceability-matrix.md) |
| Test completion | Test completion report | [templates/test-completion-report.md](templates/test-completion-report.md) |

## Test basis

| Document | Contents | ID format |
|---|---|---|
| [requirements/user-stories.md](requirements/user-stories.md) | User stories with acceptance criteria (Given/When/Then) | `US-nn` |
| [requirements/functional-requirements.md](requirements/functional-requirements.md) | What the system does | `FR-AREA-nn` |
| [requirements/non-functional-requirements.md](requirements/non-functional-requirements.md) | How well it does it (ISO/IEC 25010) | `NFR-CHAR-nn` |
| [requirements/regulatory-requirements.md](requirements/regulatory-requirements.md) | What the law requires (GDPR, ePrivacy, DSA, ...) | `REG-LAW-nn` |

The requirements were **reverse-engineered** from the existing code and the decisions
in this project (there was no written specification before). Reviewing them is itself
static testing (CTFL chapter 3): if a requirement is wrong, the code or the requirement
must change.

## Test levels and where they run

| Level (CTFL 2.2.1) | What | How | Run |
|---|---|---|---|
| Component | Single JS/PHP functions | Not yet automated (backlog) | — |
| Component integration | `hakoo-users.inc.php` against a real MariaDB | PHPUnit | `docker compose --profile test run --rm tests-php` |
| System | HTTP endpoints; pages in real browsers | PHPUnit (API), Playwright (E2E) | `... tests-php` and `... tests-e2e` |
| System integration | Discord, Wargaming, Steam | Manual, on the live site ([manual test cases](test-cases/manual-test-cases.md)) | manual |
| Acceptance | User stories and regulatory requirements | Owner/clan UAT + regulatory checklist | manual |

## ID conventions

`TC-INT-nnn` integration test · `TC-API-nnn` API/system test · `TC-E2E-nnn` browser test ·
`TC-MAN-nnn` manual test · `DEF-nnn` defect · `R-nn` risk. Every automated test has its
TC id and the requirements it covers in a comment, so the traceability matrix can be checked.

## Language

All files are in English so that contributors across Europe can take part.
