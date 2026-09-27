---
paths:
    - 'resources/js/components/ui/**'
---

# Ui

## Keep KORA UI shadcn-compatible

KORA OPS Web UI primitives must remain shadcn/ui-compatible: Radix where appropriate, CVA variants, cn()/tailwind-merge, and semantic CSS variables. Do not build a parallel Tailwind-only component library. All UI primitives and Storybook examples must support both light and dark mode.
