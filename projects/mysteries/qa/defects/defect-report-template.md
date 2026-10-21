# Defect report template

Copy this block into [defect-log.md](defect-log.md), or into a GitHub issue with the label `defect`.
Contents follow CTFL 5.5 (defect management).

```markdown
### DEF-nnn: <short title: what is wrong, where>

| Field | Value |
|---|---|
| Date / reporter | YYYY-MM-DD / name |
| Test object | file or page, commit id |
| Environment | Docker / live, browser + version, OS |
| Found by | TC id, or exploratory session / review |
| Test level | component / integration / system / acceptance |
| Severity | critical / major / minor / trivial (impact on the system) |
| Priority | high / medium / low (urgency to fix) |
| Status | New / Open / Fixed / Retest / Closed / Rejected / Deferred |
| Requirement | FR-/NFR-/REG- ids |

**Steps to reproduce**
1. ...

**Expected result:** ...
**Actual result:** ...
**Evidence:** screenshot, log excerpt, response body
**Root cause (when known):** ...
**Fix + confirmation test:** commit id, TC id that now passes
```

**Severity guide:** *critical* = personal data exposed or not deleted, site down · *major* = a requirement
not met, no workaround · *minor* = a workaround exists or the effect is small · *trivial* = cosmetic.
