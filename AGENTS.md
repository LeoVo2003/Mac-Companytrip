# Project instructions

## UI design system

- Use the repository-local **Premium** skill at `.agents/skills/premium/SKILL.md` as the primary visual direction for UI work in this repository. It is sourced from `bergside/awesome-design-skills` under the MIT license.
- Preserve the MAC Marketing identity instead of using Premium's default blue and violet tokens:
  - primary red: `#E31E24`
  - accent orange: `#FF6A2C`
  - primary action gradient: red to orange
- Use refined neutral surfaces: page `#F5F5F7`, panel `#FFFFFF`, subtle panel `#FAFAFC`, text `#111827`, and muted text `#667085`.
- Keep the public voting flow polished, calm, mobile-first, and easy to use during a live event. Use 18-24px feature-card radii, 12-14px control radii, and 44px minimum touch targets.
- Keep the WordPress admin interface compact and precise: follow the Premium 4/8/12/16/24/32 spacing scale and use 10-14px panel radii while retaining explicit interaction states and accessibility rules.
- Use Inter with the system sans-serif stack for interface text. Use gradients only for primary actions, progress, and selected score states; prefer neutral surfaces and hairline borders elsewhere.
- Use soft elevation sparingly. Floating overlays may have a stronger shadow, while resting cards should rely on subtle borders and a restrained shadow.
- Support visible keyboard focus, semantic HTML, reduced motion, long labels, empty states, loading states, errors, and overflow.
- Reserve success, warning, and danger colors for actual status communication.
- Do not introduce a second design system unless the user explicitly asks to change direction.

## Release workflow

- After each completed code update passes its relevant checks, automatically bump the patch version, build the plugin ZIP, commit, push `main`, create and push the matching `v*` tag, and verify the GitHub Release asset. Do not wait for a separate push request.
