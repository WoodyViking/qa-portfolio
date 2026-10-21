# 05: Accessibility (European Accessibility Act and WCAG)

## What it is

- **European Accessibility Act (EAA)**: Directive (EU) 2019/882. It has applied since
  **28 June 2025** to certain products and services sold to consumers, for example
  e-commerce, banking, e-books, and transport ticketing.
- **Web Accessibility Directive**: Directive (EU) 2016/2102. It applies only to
  **public-sector** websites and apps.
- The technical benchmark for both is **EN 301 549**, which for the web means
  **WCAG 2.1 level AA**.

## Does it apply to hakoo.eu?

**No, not legally.** We sell nothing, so the EAA's service categories do not
cover us. Microenterprises that provide services are exempt anyway. We are not
a public-sector body.

## Why follow it anyway

Some clan members may be colour-blind, use screen readers or navigate by
keyboard. Accessible pages also work better on phones.

## Practical WCAG 2.1 AA checklist for our pages

| Check | Notes for hakoo.eu |
|---|---|
| Text contrast of at least **4.5:1** (3:1 for large text) | The dark theme is mostly fine. Check `--muted` (#8b949e) on `--card` (#1c2128), and small grey labels. |
| Don't use colour alone | Online/idle/DND dots should also have a text or `title` label |
| All images have `alt` | Tank icons, avatars and flags. Decorative images get `alt=""`. |
| Everything works by keyboard | Language flags, switches (`.switch`) and builder slots must be reachable by Tab and have a visible focus style |
| Buttons are `<button>`, links are `<a>` | Avoid `div onclick` |
| Form fields have labels | Roster IGN inputs and search fields |
| `<html lang="...">` matches the language shown | Update `lang` when the language flag is switched |
| Pages work at 200% zoom / 320 px width | Test the wide tables (wot-stats, wot-tracker) |
| Motion can be reduced | Respect `prefers-reduced-motion` for animations |

Now that the CSS is split out, we can add a shared **focus style** and
**contrast-checked colour tokens** in `src/css/base.css`, and every page
inherits them.

## Sources

- EAA: https://eur-lex.europa.eu/eli/dir/2019/882/oj
- WCAG 2.1: https://www.w3.org/TR/WCAG21/
