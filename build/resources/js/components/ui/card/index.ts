/*
 * CARD_VARIANT is provide()'d by Card.vue and inject()'d by CardTitle so a
 * stat tile's label reads as a small mono caption instead of a full display
 * heading -- the number itself lives in CardFigure, not in CardTitle, under
 * every variant. It's re-exported here (from ./variant, not defined inline)
 * so call sites can import it alongside the components from one place, per
 * the existing convention of this file.
 */
export { CARD_VARIANT, type CardVariant } from './variant'

export { default as Card } from './Card.vue'
export { default as CardAction } from './CardAction.vue'
export { default as CardContent } from './CardContent.vue'
export { default as CardDescription } from './CardDescription.vue'
export { default as CardFigure } from './CardFigure.vue'
export { default as CardFooter } from './CardFooter.vue'
export { default as CardHeader } from './CardHeader.vue'
export { default as CardNote } from './CardNote.vue'
export { default as CardTitle } from './CardTitle.vue'
