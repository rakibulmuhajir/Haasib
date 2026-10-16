/**
 * The dashboard widget registry — key → component.
 *
 * Starts empty: each module registers its own widgets from its extensions file
 * (see resources/js/extensions). DashboardGrid reads `widgetMap` at render time,
 * so registration only has to happen before the first render.
 *
 * An unknown key is a DashboardGrid concern, not this file's — this map
 * simply may not have an entry for it, and the grid skips it silently.
 */
import type { Component } from 'vue';

export const widgetMap: Record<string, Component> = {};

export function registerWidget(key: string, component: Component): void {
    widgetMap[key] = component;
}
