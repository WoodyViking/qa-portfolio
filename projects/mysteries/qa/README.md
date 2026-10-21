# QA: testing Mysteries

Quality assurance for the Mysteries game engine, structured like [hakoo's QA](../../hakoo/qa/README.md):
the **ISTQB Certified Tester Foundation Level (CTFL) v4.0** syllabus, test documentation modelled on
**ISO/IEC/IEEE 29119-3**, quality characteristics from **ISO/IEC 25010**.

## Where everything is

| ISTQB activity (CTFL 1.4) | Work products | Folder / file |
|---|---|---|
| Test planning | Test plan, risk register | [test-plan.md](test-plan.md), [risk-register.md](risk-register.md) |
| Test analysis (*what* to test) | **Test basis:** requirements, user stories, acceptance criteria | [requirements/](requirements/) |
| Test design (*how* to test) | Test cases designed with test techniques | [test-design/](test-design/), [test-cases/](test-cases/) |
| Test implementation | Test scripts, test data, mocks | [automation/](automation/) |
| Test execution | Test results (generated, not committed), defect reports | `test-results/`, [defects/](defects/) |
| Test monitoring and control | Traceability, progress | [traceability-matrix.md](traceability-matrix.md) |
| Test completion | Test completion reports | [test-reports/](test-reports/), [templates/](templates/) |

## Test basis

| Document | Contents | ID format |
|---|---|---|
| [requirements/kravsammanstallning.md](requirements/kravsammanstallning.md) | Goals, user stories, requirements, NFRs, acceptance criteria, scope, assumptions, open questions (Swedish, from the requirements interview 2026-10-11) | `US-nn`, `K-nn`, `NFR-nn`, `AK-nn`, `A-nn` |

## ID conventions

`TC-UNIT-nnn` component test · `TC-INT-nnn` integration test · `TC-API-nnn` API/system test ·
`TC-E2E-nnn` browser test · `TC-MAN-nnn` manual test · `DEF-nnn` defect · `R-nn` risk.
Every automated test has its TC id and the requirement/AK ids it covers in a comment.

## Language

The requirements document is in Swedish (the customer's language in the interview). Like hakoo, all other
files are in English so that contributors across Europe can take part.
