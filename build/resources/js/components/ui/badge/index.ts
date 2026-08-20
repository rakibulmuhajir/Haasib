import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

/**
 * The chip is a rule and a weight before it is a colour.
 *
 * shadcn's default fills the whole chip with the variant colour, which is why
 * a page of records used to read as a row of coloured pills competing with the
 * figures beside them. This matches StatusBadge instead: a hairline box with a
 * heavy left edge carrying the meaning, so the chip annotates a row rather
 * than interrupting it, and so the same status looks the same whichever of the
 * two components a page happens to import.
 *
 * The left edge is also the non-colour indicator — its weight survives
 * greyscale and the text still says what it says.
 */
export const badgeVariants = cva(
  [
    "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap",
    "rounded-[var(--radius)] border border-l-[3px] border-[var(--rule-default)]",
    "px-2 py-0.5 text-xs font-semibold text-[var(--text-primary)]",
    "[&>svg]:size-3 [&>svg]:pointer-events-none",
    "transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]",
    "aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
  ],
  {
    variants: {
      variant: {
        default: "border-l-[var(--rule-emphasis)]",
        secondary: "border-l-[var(--rule-default)] text-[var(--text-secondary)]",
        success: "border-l-[var(--status-success)]",
        destructive: "border-l-[var(--status-critical)]",
        outline: "border-l-[var(--rule-default)]",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type BadgeVariants = VariantProps<typeof badgeVariants>
