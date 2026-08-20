<script setup lang="ts">
/**
 * RelatedActions -- the ruled strip of what else you might need.
 *
 * The mockup's action strip, given a job. It sits beneath the work on a page
 * and offers the two or three adjacent things a person plausibly needs from
 * that screen: on an invoice you are writing, a customer or a product; on one
 * you are looking at, a payment against it or a credit note reducing it.
 *
 * Which actions those are is not decided here. `lib/relatedActions.ts` holds
 * one definition per screen, so the same strip appears everywhere with the same
 * appearance and different contents, and a page never invents its own row of
 * buttons. This component decides only how the strip looks and what happens
 * when something in it is chosen.
 *
 * It renders nothing when there is nothing to offer -- no empty heading, no
 * lone rule. A strip is only worth the space when it has something in it.
 *
 * Actions that navigate are real anchors, so middle-click and "open in new tab"
 * work the way a link should. Actions the host page handles itself are buttons,
 * because they are not going anywhere.
 */
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { resolveRelatedActions, type RelatedAction } from '@/lib/relatedActions'

const props = withDefaults(
    defineProps<{
        /** Which screen this is, e.g. `invoice.create`. Keys `relatedActionDefinitions`. */
        screen: string
        /** The record in view, if the definition reads it. */
        subject?: Record<string, any>
        /** Company slug. Falls back to the one in shared props. */
        slug?: string
        /** Heading above the strip. */
        label?: string
    }>(),
    { label: 'Related' },
)

const emit = defineEmits<{
    /**
     * An action the page owns was chosen. The key is the action's key, so a
     * host wires `@select="key => key === 'customer.create' && open()"` rather
     * than one handler per action.
     */
    select: [key: string, action: RelatedAction]
}>()

const page = usePage()

const slug = computed(
    () => props.slug ?? (page.props.auth as any)?.currentCompany?.slug ?? '',
)
const role = computed(() => (page.props.auth as any)?.currentCompanyRole ?? null)

const actions = computed(() =>
    slug.value
        ? resolveRelatedActions(props.screen, {
              slug: slug.value,
              role: role.value,
              subject: props.subject,
          })
        : [],
)
</script>

<template>
    <section v-if="actions.length" class="strip" :aria-label="label">
        <h2 class="strip__label">{{ label }}</h2>

        <ul class="strip__list">
            <li v-for="action in actions" :key="action.key">
                <Link v-if="action.href" :href="action.href" class="strip__action">
                    <component :is="action.icon" v-if="action.icon" class="strip__icon" aria-hidden="true" />
                    <span class="strip__text">
                        <span class="strip__title">{{ action.title }}</span>
                        <span v-if="action.hint" class="strip__hint">{{ action.hint }}</span>
                    </span>
                </Link>

                <button
                    v-else
                    type="button"
                    class="strip__action"
                    @click="emit('select', action.key, action)"
                >
                    <component :is="action.icon" v-if="action.icon" class="strip__icon" aria-hidden="true" />
                    <span class="strip__text">
                        <span class="strip__title">{{ action.title }}</span>
                        <span v-if="action.hint" class="strip__hint">{{ action.hint }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </section>
</template>

<style scoped>
/* Ruled, not boxed. The strip is a continuation of the page it sits under,
   marked off by a line the way a ledger marks a section, so it never reads as
   a second card competing with the work above it. */
.strip {
    margin-top: 24px;
    padding-top: 12px;
    border-top: var(--rule-w-base) solid var(--rule-default);
}

.strip__label {
    margin-bottom: 8px;
    font-family: var(--mono-family);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.strip__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.strip__action {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px 12px;
    text-align: start;
    background: var(--surface-raised);
    border: var(--rule-w-base) solid var(--rule-default);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    cursor: pointer;
    transition: border-color 120ms ease, background-color 120ms ease;
}

/* Hover is an outline taking weight, not a fill changing colour -- the same
   move the register uses on a row, for the same reason. */
.strip__action:hover {
    border-color: var(--rule-emphasis);
    background: var(--surface-sunken);
}

.strip__action:focus-visible {
    outline: 2px solid var(--rule-emphasis);
    outline-offset: 2px;
}

.strip__icon {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    color: var(--text-secondary);
}

.strip__text {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}

.strip__title {
    font-size: 13px;
    font-weight: 500;
}

.strip__hint {
    font-size: 11px;
    color: var(--text-secondary);
}

@media (min-width: 640px) {
    .strip__list > li {
        flex: 1 1 200px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .strip__action {
        transition: none;
    }
}
</style>
