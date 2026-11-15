---
paths: ["web/**"]
---

# Frontend rules (Nuxt)

- Design tokens (colors, type scale, spacing, radii) come from page 1 of `docs/design/ledgerlight-ui.pdf` and nowhere else. Expose them once as CSS variables / Tailwind theme values.
- No hard-coded colors, font sizes or spacing in components: use tokens. Dark mode works by swapping token values, not by per-component overrides.
- Reusable building blocks live in `components/ui/` (button, input, badge, confidence meter, ...). Feature components compose them; they do not restyle them.
- Amounts use tabular numerals and are right-aligned. Money arrives as integer cents and is formatted only at the display edge.
- All API calls go through one typed client that understands the error contract (`message`, `code`, `request_id`). Show the `request_id` in unexpected-error states.
- Components with states (loading, empty, error, uncertain) get a Vitest test for each state. Run `eslint` and `nuxi typecheck` before committing.
