# Test completion report: <release name>

Template based on ISO/IEC/IEEE 29119-3 and CTFL 5.3.2. Fill it in at the end of each release test.

| | |
|---|---|
| Release / commit | |
| Test period | |
| Author | |
| Recommendation | **Go / No-go / Go with conditions** |

## 1. Summary
What was tested, the main result and the recommendation, in 3–5 sentences.

## 2. Scope and deviations
Tested as planned? What was left out, and why (for example, an environment problem or time)?

## 3. Results

| Suite | Planned | Run | Passed | Failed | Blocked |
|---|---|---|---|---|---|
| Integration (PHPUnit) | | | | | |
| E2E (Playwright) × 3 browsers | | | | | |
| Manual (TC-MAN) | | | | | |

Requirements coverage (from the traceability matrix): __ of __ requirements have ≥ 1 passed test.

## 4. Defects

| Severity | Found | Fixed | Open | Deferred |
|---|---|---|---|---|
| critical | | | | |
| major | | | | |
| minor | | | | |

Open defects and their impact on the release:

## 5. Exit criteria (test plan § 6)

| Criterion | Met? |
|---|---|
| All high-risk tests pass | |
| ≥ 95 % of automated tests pass, failures have defect reports | |
| No open critical/major defects without an owner decision | |
| Every requirement has an executed test or a documented reason | |
| TC-MAN-002 done (for personal-data changes) | |

## 6. Residual risks
Which risks from the risk register are still open, and what that means for users.

## 7. Lessons learned
What should we do differently in the next test cycle?
