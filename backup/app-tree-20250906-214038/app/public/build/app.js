import { mergeProps, useSSRContext, onMounted, onUnmounted, computed, ref, unref, withCtx, renderSlot, defineComponent, watch, nextTick, createVNode, toDisplayString, createBlock, openBlock, createCommentVNode, Fragment, createTextVNode, renderList, withDirectives, isRef, vModelText, useModel, withModifiers, withKeys, createApp, h as h$1 } from "vue";
import { ssrRenderAttrs, ssrRenderSlot, ssrRenderStyle, ssrRenderClass, ssrRenderComponent, ssrRenderList, ssrRenderAttr, ssrInterpolate, ssrIncludeBooleanAttr, ssrGetDynamicModelProps, ssrLooseContain } from "vue/server-renderer";
import { Link, usePage, Head, useForm, createInertiaApp } from "@inertiajs/vue3";
import axios from "axios";
import { Dialog, DialogPanel } from "@headlessui/vue";
import Fuse from "fuse.js";
const _export_sfc = (sfc, props) => {
  const target = sfc.__vccOpts || sfc;
  for (const [key, val] of props) {
    target[key] = val;
  }
  return target;
};
const _sfc_main$v = {};
function _sfc_ssrRender$2(_ctx, _push, _parent, _attrs) {
  _push(`<svg${ssrRenderAttrs(mergeProps({
    viewBox: "0 0 316 316",
    xmlns: "http://www.w3.org/2000/svg"
  }, _attrs))}><path d="M305.8 81.125C305.77 80.995 305.69 80.885 305.65 80.755C305.56 80.525 305.49 80.285 305.37 80.075C305.29 79.935 305.17 79.815 305.07 79.685C304.94 79.515 304.83 79.325 304.68 79.175C304.55 79.045 304.39 78.955 304.25 78.845C304.09 78.715 303.95 78.575 303.77 78.475L251.32 48.275C249.97 47.495 248.31 47.495 246.96 48.275L194.51 78.475C194.33 78.575 194.19 78.725 194.03 78.845C193.89 78.955 193.73 79.045 193.6 79.175C193.45 79.325 193.34 79.515 193.21 79.685C193.11 79.815 192.99 79.935 192.91 80.075C192.79 80.285 192.71 80.525 192.63 80.755C192.58 80.875 192.51 80.995 192.48 81.125C192.38 81.495 192.33 81.875 192.33 82.265V139.625L148.62 164.795V52.575C148.62 52.185 148.57 51.805 148.47 51.435C148.44 51.305 148.36 51.195 148.32 51.065C148.23 50.835 148.16 50.595 148.04 50.385C147.96 50.245 147.84 50.125 147.74 49.995C147.61 49.825 147.5 49.635 147.35 49.485C147.22 49.355 147.06 49.265 146.92 49.155C146.76 49.025 146.62 48.885 146.44 48.785L93.99 18.585C92.64 17.805 90.98 17.805 89.63 18.585L37.18 48.785C37 48.885 36.86 49.035 36.7 49.155C36.56 49.265 36.4 49.355 36.27 49.485C36.12 49.635 36.01 49.825 35.88 49.995C35.78 50.125 35.66 50.245 35.58 50.385C35.46 50.595 35.38 50.835 35.3 51.065C35.25 51.185 35.18 51.305 35.15 51.435C35.05 51.805 35 52.185 35 52.575V232.235C35 233.795 35.84 235.245 37.19 236.025L142.1 296.425C142.33 296.555 142.58 296.635 142.82 296.725C142.93 296.765 143.04 296.835 143.16 296.865C143.53 296.965 143.9 297.015 144.28 297.015C144.66 297.015 145.03 296.965 145.4 296.865C145.5 296.835 145.59 296.775 145.69 296.745C145.95 296.655 146.21 296.565 146.45 296.435L251.36 236.035C252.72 235.255 253.55 233.815 253.55 232.245V174.885L303.81 145.945C305.17 145.165 306 143.725 306 142.155V82.265C305.95 81.875 305.89 81.495 305.8 81.125ZM144.2 227.205L100.57 202.515L146.39 176.135L196.66 147.195L240.33 172.335L208.29 190.625L144.2 227.205ZM244.75 114.995V164.795L226.39 154.225L201.03 139.625V89.825L219.39 100.395L244.75 114.995ZM249.12 57.105L292.81 82.265L249.12 107.425L205.43 82.265L249.12 57.105ZM114.49 184.425L96.13 194.995V85.305L121.49 70.705L139.85 60.135V169.815L114.49 184.425ZM91.76 27.425L135.45 52.585L91.76 77.745L48.07 52.585L91.76 27.425ZM43.67 60.135L62.03 70.705L87.39 85.305V202.545V202.555V202.565C87.39 202.735 87.44 202.895 87.46 203.055C87.49 203.265 87.49 203.485 87.55 203.695V203.705C87.6 203.875 87.69 204.035 87.76 204.195C87.84 204.375 87.89 204.575 87.99 204.745C87.99 204.745 87.99 204.755 88 204.755C88.09 204.905 88.22 205.035 88.33 205.175C88.45 205.335 88.55 205.495 88.69 205.635L88.7 205.645C88.82 205.765 88.98 205.855 89.12 205.965C89.28 206.085 89.42 206.225 89.59 206.325C89.6 206.325 89.6 206.325 89.61 206.335C89.62 206.335 89.62 206.345 89.63 206.345L139.87 234.775V285.065L43.67 229.705V60.135ZM244.75 229.705L148.58 285.075V234.775L219.8 194.115L244.75 179.875V229.705ZM297.2 139.625L253.49 164.795V114.995L278.85 100.395L297.21 89.825V139.625H297.2Z"></path></svg>`);
}
const _sfc_setup$v = _sfc_main$v.setup;
_sfc_main$v.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ApplicationLogo.vue");
  return _sfc_setup$v ? _sfc_setup$v(props, ctx) : void 0;
};
const ApplicationLogo = /* @__PURE__ */ _export_sfc(_sfc_main$v, [["ssrRender", _sfc_ssrRender$2]]);
const _sfc_main$u = {
  __name: "Dropdown",
  __ssrInlineRender: true,
  props: {
    align: {
      type: String,
      default: "right"
    },
    width: {
      type: String,
      default: "48"
    },
    contentClasses: {
      type: String,
      default: "py-1 bg-white"
    }
  },
  setup(__props) {
    const props = __props;
    const closeOnEscape = (e2) => {
      if (open.value && e2.key === "Escape") {
        open.value = false;
      }
    };
    onMounted(() => document.addEventListener("keydown", closeOnEscape));
    onUnmounted(() => document.removeEventListener("keydown", closeOnEscape));
    const widthClass = computed(() => {
      return {
        48: "w-48"
      }[props.width.toString()];
    });
    const alignmentClasses = computed(() => {
      if (props.align === "left") {
        return "ltr:origin-top-left rtl:origin-top-right start-0";
      } else if (props.align === "right") {
        return "ltr:origin-top-right rtl:origin-top-left end-0";
      } else {
        return "origin-top";
      }
    });
    const open = ref(false);
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "relative" }, _attrs))}><div>`);
      ssrRenderSlot(_ctx.$slots, "trigger", {}, null, _push, _parent);
      _push(`</div><div style="${ssrRenderStyle(open.value ? null : { display: "none" })}" class="fixed inset-0 z-40"></div><div style="${ssrRenderStyle([
        open.value ? null : { display: "none" },
        { "display": "none" }
      ])}" class="${ssrRenderClass([[widthClass.value, alignmentClasses.value], "absolute z-50 mt-2 rounded-md shadow-lg"])}"><div class="${ssrRenderClass([__props.contentClasses, "rounded-md ring-1 ring-black ring-opacity-5"])}">`);
      ssrRenderSlot(_ctx.$slots, "content", {}, null, _push, _parent);
      _push(`</div></div></div>`);
    };
  }
};
const _sfc_setup$u = _sfc_main$u.setup;
_sfc_main$u.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Dropdown.vue");
  return _sfc_setup$u ? _sfc_setup$u(props, ctx) : void 0;
};
const _sfc_main$t = {
  __name: "DropdownLink",
  __ssrInlineRender: true,
  props: {
    href: {
      type: String,
      required: true
    }
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(unref(Link), mergeProps({
        href: __props.href,
        class: "block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
      }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            ssrRenderSlot(_ctx.$slots, "default", {}, null, _push2, _parent2, _scopeId);
          } else {
            return [
              renderSlot(_ctx.$slots, "default")
            ];
          }
        }),
        _: 3
      }, _parent));
    };
  }
};
const _sfc_setup$t = _sfc_main$t.setup;
_sfc_main$t.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/DropdownLink.vue");
  return _sfc_setup$t ? _sfc_setup$t(props, ctx) : void 0;
};
const _sfc_main$s = {
  __name: "NavLink",
  __ssrInlineRender: true,
  props: {
    href: {
      type: String,
      required: true
    },
    active: {
      type: Boolean
    }
  },
  setup(__props) {
    const props = __props;
    const classes = computed(
      () => props.active ? "inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out" : "inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out"
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(unref(Link), mergeProps({
        href: __props.href,
        class: classes.value
      }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            ssrRenderSlot(_ctx.$slots, "default", {}, null, _push2, _parent2, _scopeId);
          } else {
            return [
              renderSlot(_ctx.$slots, "default")
            ];
          }
        }),
        _: 3
      }, _parent));
    };
  }
};
const _sfc_setup$s = _sfc_main$s.setup;
_sfc_main$s.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/NavLink.vue");
  return _sfc_setup$s ? _sfc_setup$s(props, ctx) : void 0;
};
const _sfc_main$r = {
  __name: "ResponsiveNavLink",
  __ssrInlineRender: true,
  props: {
    href: {
      type: String,
      required: true
    },
    active: {
      type: Boolean
    }
  },
  setup(__props) {
    const props = __props;
    const classes = computed(
      () => props.active ? "block w-full ps-3 pe-4 py-2 border-l-4 border-indigo-400 text-start text-base font-medium text-indigo-700 bg-indigo-50 focus:outline-none focus:text-indigo-800 focus:bg-indigo-100 focus:border-indigo-700 transition duration-150 ease-in-out" : "block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out"
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(unref(Link), mergeProps({
        href: __props.href,
        class: classes.value
      }, _attrs), {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            ssrRenderSlot(_ctx.$slots, "default", {}, null, _push2, _parent2, _scopeId);
          } else {
            return [
              renderSlot(_ctx.$slots, "default")
            ];
          }
        }),
        _: 3
      }, _parent));
    };
  }
};
const _sfc_setup$r = _sfc_main$r.setup;
_sfc_main$r.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/ResponsiveNavLink.vue");
  return _sfc_setup$r ? _sfc_setup$r(props, ctx) : void 0;
};
const _sfc_main$q = {
  __name: "CompanySwitcher",
  __ssrInlineRender: true,
  setup(__props) {
    const companies = ref([]);
    const currentId = ref(localStorage.getItem("currentCompanyId") || null);
    onMounted(async () => {
      try {
        const { data } = await axios.get("/api/v1/me/companies");
        companies.value = data.data;
        if (!currentId.value && companies.value.length === 1) {
          currentId.value = companies.value[0].id;
          localStorage.setItem("currentCompanyId", currentId.value);
        }
      } catch (e2) {
        companies.value = [];
      }
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<select${ssrRenderAttrs(mergeProps({
        value: currentId.value,
        class: "border rounded px-2 py-1"
      }, _attrs))}><option disabled value="">Select company…</option><!--[-->`);
      ssrRenderList(companies.value, (c2) => {
        _push(`<option${ssrRenderAttr("value", c2.id)}>${ssrInterpolate(c2.name)}</option>`);
      });
      _push(`<!--]--></select>`);
    };
  }
};
const _sfc_setup$q = _sfc_main$q.setup;
_sfc_main$q.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/CompanySwitcher.vue");
  return _sfc_setup$q ? _sfc_setup$q(props, ctx) : void 0;
};
const _sfc_main$p = /* @__PURE__ */ defineComponent({
  __name: "SuggestList",
  __ssrInlineRender: true,
  props: {
    items: {},
    selectedIndex: {},
    loading: { type: Boolean },
    error: {}
  },
  emits: ["select"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const highlighted = computed(() => {
      if (!props.items || props.items.length === 0) return null;
      return props.items[Math.min(props.selectedIndex, props.items.length - 1)];
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "border border-gray-700/50 rounded-xl bg-gray-900/50 backdrop-blur-sm overflow-hidden" }, _attrs))}><div class="text-gray-500 text-xs px-4 py-2.5 mb-1 bg-gray-800/30 flex items-center gap-2">`);
      ssrRenderSlot(_ctx.$slots, "header", {}, null, _push, _parent);
      _push(`</div>`);
      if (_ctx.loading) {
        _push(`<div class="px-4 py-3 text-gray-500 text-xs">Loading…</div>`);
      } else if (_ctx.error) {
        _push(`<div class="px-4 py-3 text-red-300 text-xs">${ssrInterpolate(_ctx.error)}</div>`);
      } else {
        _push(`<div class="max-h-40 overflow-auto"><!--[-->`);
        ssrRenderList(_ctx.items, (it, index) => {
          _push(`<button type="button" class="${ssrRenderClass([index === _ctx.selectedIndex ? "bg-gray-800/50" : "", "w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50"])}"><div class="font-medium">${ssrInterpolate(it.label)}</div>`);
          if (it.meta && (it.meta.description || it.meta.sub)) {
            _push(`<div class="text-xs text-gray-500">${ssrInterpolate(it.meta.description || it.meta.sub)}</div>`);
          } else {
            _push(`<!---->`);
          }
          _push(`</button>`);
        });
        _push(`<!--]--></div>`);
      }
      if (highlighted.value) {
        _push(`<div class="px-4 py-3 text-xs text-gray-400 border-t border-gray-800/50 bg-gray-800/20">`);
        ssrRenderSlot(_ctx.$slots, "preview", { item: highlighted.value }, null, _push, _parent);
        _push(`</div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div>`);
    };
  }
});
const _sfc_setup$p = _sfc_main$p.setup;
_sfc_main$p.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/SuggestList.vue");
  return _sfc_setup$p ? _sfc_setup$p(props, ctx) : void 0;
};
let csrfReady = false;
let csrfPromise = null;
async function ensureCsrf() {
  if (csrfReady) return;
  if (!csrfPromise) {
    csrfPromise = axios.get("/sanctum/csrf-cookie").catch(() => {
    }).then(() => {
      csrfReady = true;
    });
  }
  return csrfPromise;
}
const http = axios.create();
http.interceptors.request.use(async (config) => {
  const method = (config.method || "get").toLowerCase();
  const needsCsrf = method === "post" || method === "put" || method === "patch" || method === "delete";
  if (needsCsrf && !csrfReady) {
    await ensureCsrf();
  }
  return config;
});
function withIdempotency(headers = {}) {
  return {
    "X-Idempotency-Key": globalThis.crypto && "randomUUID" in globalThis.crypto ? globalThis.crypto.randomUUID() : Math.random().toString(36).slice(2),
    ...headers
  };
}
const entities = [
  {
    id: "company",
    label: "company",
    aliases: ["company", "comp", "co", "cmp"],
    verbs: [
      {
        id: "list",
        label: "list",
        action: "ui.list.companies",
        fields: [
          { id: "company", label: "Company", placeholder: "-company", required: false, type: "text" }
        ]
      },
      {
        id: "create",
        label: "create",
        action: "company.create",
        fields: [
          { id: "name", label: "Name", placeholder: "-name", required: true, type: "text" },
          {
            id: "base_currency",
            label: "Base currency",
            placeholder: "-base_currency",
            required: false,
            type: "remote",
            picker: "inline",
            default: "USD",
            source: { kind: "remote", endpoint: "/web/currencies/suggest", queryKey: "q", limit: 12, valueKey: "code", labelTemplate: "{code} — {name}" }
          },
          {
            id: "language",
            label: "Language",
            placeholder: "-language",
            required: false,
            type: "remote",
            picker: "inline",
            default: "en",
            source: { kind: "remote", endpoint: "/web/languages/suggest", queryKey: "q", limit: 12, valueKey: "code", labelTemplate: "{code} — {name}" }
          },
          {
            id: "locale",
            label: "Locale",
            placeholder: "-locale",
            required: false,
            type: "remote",
            picker: "inline",
            default: "en-US",
            source: { kind: "remote", endpoint: "/web/locales/suggest", queryKey: "q", limit: 12, valueKey: "tag", labelTemplate: "{tag}", dependsOn: ["language", "country"] }
          }
        ]
      },
      {
        id: "delete",
        label: "delete",
        action: "company.delete",
        fields: [
          {
            id: "company",
            label: "Company",
            placeholder: "-company",
            required: true,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/companies", valueKey: "id", labelKey: "name" }
          }
        ]
      },
      {
        id: "assign",
        label: "assign",
        action: "company.assign",
        fields: [
          {
            id: "email",
            label: "User email",
            placeholder: "-email",
            required: true,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/users/suggest", queryKey: "q", valueKey: "email", labelTemplate: "{name} — {email}" }
          },
          {
            id: "company",
            label: "Company",
            placeholder: "-company",
            required: true,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/companies", valueKey: "id", labelKey: "name" }
          },
          { id: "role", label: "Role", placeholder: "-role", required: true, type: "select", options: ["owner", "admin", "accountant", "viewer"] }
        ]
      },
      {
        id: "unassign",
        label: "unassign",
        action: "company.unassign",
        fields: [
          {
            id: "email",
            label: "User email",
            placeholder: "-email",
            required: true,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/users/suggest", queryKey: "q", valueKey: "email", labelTemplate: "{name} — {email}" }
          },
          {
            id: "company",
            label: "Company",
            placeholder: "-company",
            required: true,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/companies", valueKey: "id", labelKey: "name" }
          }
        ]
      }
    ]
  },
  {
    id: "user",
    label: "user",
    aliases: ["user", "usr", "users"],
    verbs: [
      {
        id: "list",
        label: "list",
        action: "ui.list.users",
        fields: [
          {
            id: "email",
            label: "Email",
            placeholder: "-email",
            required: false,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/users/suggest", queryKey: "q", valueKey: "email", labelTemplate: "{name} — {email}" }
          }
        ]
      },
      {
        id: "create",
        label: "create",
        action: "user.create",
        fields: [
          { id: "name", label: "Name", placeholder: "-name", required: true, type: "text" },
          { id: "email", label: "Email", placeholder: "-email", required: true, type: "email" },
          { id: "password", label: "Password", placeholder: "-password", required: false, type: "password" },
          { id: "system_role", label: "System role", placeholder: "-system_role", required: false, type: "select", options: ["superadmin"] }
        ]
      },
      {
        id: "delete",
        label: "delete",
        action: "user.delete",
        fields: [
          {
            id: "email",
            label: "User to delete",
            placeholder: "-email",
            required: true,
            type: "remote",
            picker: "panel",
            source: { kind: "remote", endpoint: "/web/users/suggest", queryKey: "q", valueKey: "email", labelTemplate: "{name} — {email}" }
          }
        ]
      }
    ]
  }
];
function useSuggestions(ctx) {
  const cache = /* @__PURE__ */ new Map();
  const TTL_MS = 60 * 1e3;
  const templ = (tpl, row) => tpl.replace(/\{(\w+)\}/g, (_, k2) => row?.[k2] ?? "");
  async function fromField(field, qstr, params) {
    const src = field.source || null;
    if (!src || src.kind !== "remote" || !src.endpoint) return [];
    const depends = (src.dependsOn || []).reduce((acc, key) => {
      acc[key] = params?.[key];
      return acc;
    }, {});
    const qkey = src.queryKey || "q";
    const limit = typeof src.limit === "number" ? src.limit : 12;
    const cacheKey = JSON.stringify({ ep: src.endpoint, q: qstr || "", depends });
    const now = Date.now();
    const hit = cache.get(cacheKey);
    if (hit && now - hit.ts < TTL_MS) return hit.items;
    const reqParams = { [qkey]: qstr, limit, ...depends };
    await ensureCsrf();
    const { data } = await http.get(src.endpoint, { params: reqParams });
    const list = data?.data || [];
    const items = list.map((row) => {
      const value = row?.[src.valueKey];
      const label = src.labelTemplate ? templ(src.labelTemplate, row) : src.labelKey ? row?.[src.labelKey] : String(value);
      return { value, label, meta: row };
    });
    cache.set(cacheKey, { ts: now, items });
    return items;
  }
  async function users(qstr) {
    const paramsAny = { q: qstr };
    if (!ctx.isSuperAdmin.value || ctx.userSource.value === "company") {
      paramsAny.company_id = ctx.currentCompanyId.value;
    }
    await ensureCsrf();
    const { data } = await http.get("/web/users/suggest", { params: paramsAny });
    const list = data?.data || [];
    return list.map((u2) => ({ value: u2.email, label: u2.name, meta: { email: u2.email, id: u2.id, name: u2.name } }));
  }
  async function companies() {
    const qobj = {};
    if (ctx.companySource.value === "byUser" && ctx.isSuperAdmin.value && ctx.params.value.email) {
      qobj.user_email = ctx.params.value.email;
    }
    if (ctx.q.value && ctx.q.value.length > 0) {
      qobj.q = ctx.q.value;
    }
    await ensureCsrf();
    const { data } = await http.get("/web/companies", { params: qobj });
    const list = data?.data || [];
    return list.map((c2) => ({ value: c2.id, label: c2.name, meta: { id: c2.id } }));
  }
  async function currencies(qstr) {
    await ensureCsrf();
    const { data } = await http.get("/web/currencies/suggest", { params: { q: qstr, limit: 12 } });
    const list = data?.data || [];
    return list.map((c2) => ({ value: c2.code, label: `${c2.code} — ${c2.name}${c2.symbol ? ` (${c2.symbol})` : ""}`, meta: c2 }));
  }
  async function languages(qstr) {
    await ensureCsrf();
    const { data } = await http.get("/web/languages/suggest", { params: { q: qstr, limit: 12 } });
    const list = data?.data || [];
    return list.map((l2) => ({ value: l2.code, label: `${l2.code} — ${l2.native_name || l2.name}${l2.rtl ? " (RTL)" : ""}`, meta: l2 }));
  }
  async function locales(qstr) {
    await ensureCsrf();
    const paramsAny = { q: qstr, limit: 12 };
    if (ctx.params.value?.language) paramsAny.language = ctx.params.value.language;
    if (ctx.params.value?.country) paramsAny.country = ctx.params.value.country;
    const { data } = await http.get("/web/locales/suggest", { params: paramsAny });
    const list = data?.data || [];
    return list.map((l2) => ({ value: l2.tag, label: `${l2.tag} — ${l2.native_name || l2.name || ""}`.trim(), meta: l2 }));
  }
  async function countries(qstr) {
    await ensureCsrf();
    const { data } = await http.get("/web/countries/suggest", { params: { q: qstr, limit: 12 } });
    const list = data?.data || [];
    return list.map((c2) => ({ value: c2.code, label: `${c2.emoji ? c2.emoji + " " : ""}${c2.name} — ${c2.code}`, meta: c2 }));
  }
  return { users, companies, currencies, languages, locales, countries, fromField };
}
function usePalette() {
  const open = ref(false);
  const q = ref("");
  const step = ref("entity");
  const selectedEntity = ref(null);
  const selectedVerb = ref(null);
  const params = ref({});
  const inputEl = ref(null);
  const selectedIndex = ref(0);
  const executing = ref(false);
  const results = ref([]);
  const showResults = ref(false);
  const stashParams = ref({});
  const activeFlagId = ref(null);
  const flagAnimating = ref(null);
  const editingFlagId = ref(null);
  const animatingToCompleted = ref(null);
  const page = usePage();
  const isSuperAdmin = computed(() => !!page.props?.auth?.isSuperAdmin);
  const currentCompanyId = computed(() => page.props?.auth?.companyId || null);
  const userSource = ref(isSuperAdmin.value ? "all" : "company");
  const companySource = ref(isSuperAdmin.value ? "all" : "me");
  const panelItems = ref([]);
  const inlineItems = ref([]);
  const companyDetails = ref({});
  const companyMembers = ref({});
  const companyMembersLoading = ref({});
  const userDetails = ref({});
  const deleteConfirmText = ref("");
  const deleteConfirmRequired = ref("");
  const provider = useSuggestions({ isSuperAdmin, currentCompanyId, userSource, companySource, q, params });
  const entFuse = new Fuse(entities, { keys: ["label", "aliases"], includeScore: true, threshold: 0.3 });
  const entitySuggestions = computed(() => {
    if (q.value.length < 2) return entities.slice(0, 6);
    const results2 = entFuse.search(q.value);
    return results2.map((r2) => r2.item).slice(0, 6);
  });
  const verbSuggestions = computed(() => {
    if (!selectedEntity.value) return [];
    const verbs = selectedEntity.value.verbs;
    const needle = q.value.trim().toLowerCase();
    if (!needle) return verbs;
    return verbs.filter((v2) => v2.label.toLowerCase().includes(needle) || v2.id.toLowerCase().includes(needle));
  });
  const availableFlags = computed(() => {
    if (!selectedVerb.value) return [];
    return selectedVerb.value.fields.filter((f2) => !params.value[f2.id] && f2.id !== activeFlagId.value && f2.id !== animatingToCompleted.value);
  });
  const filledFlags = computed(() => {
    if (!selectedVerb.value) return [];
    return selectedVerb.value.fields.filter((f2) => params.value[f2.id] && f2.id !== activeFlagId.value && f2.id !== animatingToCompleted.value);
  });
  const currentField = computed(() => {
    if (activeFlagId.value && selectedVerb.value) {
      return selectedVerb.value.fields.find((f2) => f2.id === activeFlagId.value);
    }
    return void 0;
  });
  const dashParameterMatch = computed(() => {
    if (step.value !== "fields" || !selectedVerb.value || activeFlagId.value) return null;
    if (!q.value.startsWith("-")) return null;
    const paramName = q.value.slice(1).toLowerCase();
    return selectedVerb.value.fields.find((f2) => f2.id.toLowerCase().startsWith(paramName) || f2.placeholder.toLowerCase().startsWith(paramName));
  });
  const allRequiredFilled = computed(() => {
    if (!selectedVerb.value) return false;
    if (selectedVerb.value.action.startsWith("ui.")) return false;
    return selectedVerb.value.fields.filter((f2) => f2.required).every((f2) => params.value[f2.id]);
  });
  const currentChoices = computed(() => {
    const f2 = currentField.value;
    if (!f2) return [];
    if (f2.type === "select") return f2.options || [];
    return [];
  });
  const isUIList = computed(() => !!selectedVerb.value && selectedVerb.value.action.startsWith("ui.list."));
  const showUserPicker = computed(() => {
    if (!selectedVerb.value) return false;
    if (isUIList.value && selectedVerb.value.action === "ui.list.users") return true;
    const f2 = currentField.value;
    return f2?.type === "remote" && f2?.picker === "panel" && f2?.source?.endpoint?.includes("/users/");
  });
  const showCompanyPicker = computed(() => {
    if (!selectedVerb.value) return false;
    if (isUIList.value && selectedVerb.value.action === "ui.list.companies") return true;
    const f2 = currentField.value;
    return f2?.type === "remote" && f2?.picker === "panel" && f2?.source?.endpoint?.includes("/companies");
  });
  const showGenericPanelPicker = computed(() => {
    const f2 = currentField.value;
    if (!f2 || f2.type !== "remote" || f2.picker !== "panel") return false;
    return !showUserPicker.value && !showCompanyPicker.value;
  });
  const inlineSuggestions = computed(() => {
    const f2 = currentField.value;
    if (step.value !== "fields" || !f2 || f2.type !== "remote" || f2.picker !== "inline") return [];
    const term = (q.value || "").toString().trim();
    const list = inlineItems.value;
    if (list.length === 0) return [];
    const lower = term.toLowerCase();
    let idx = list.findIndex((i2) => i2.value.toLowerCase() === lower);
    if (idx === -1 && lower) idx = list.findIndex((i2) => i2.value.toLowerCase().startsWith(lower));
    if (idx === -1 && lower) idx = list.findIndex((i2) => i2.label.toLowerCase().includes(lower));
    if (idx === -1) idx = 0;
    const start = Math.max(0, idx - 3);
    return list.slice(start, Math.min(list.length, start + 7));
  });
  const highlightedItem = computed(() => {
    const isPanelActive = showUserPicker.value || showCompanyPicker.value || showGenericPanelPicker.value;
    if (isPanelActive && panelItems.value.length > 0) {
      return panelItems.value[Math.min(selectedIndex.value, panelItems.value.length - 1)];
    }
    return null;
  });
  const highlightedUser = computed(() => showUserPicker.value ? highlightedItem.value : null);
  const highlightedCompany = computed(() => showCompanyPicker.value ? highlightedItem.value : null);
  const statusText = computed(() => {
    if (step.value === "entity") return "SELECT_ENTITY";
    if (step.value === "verb") return "SELECT_ACTION";
    if (step.value === "fields") return activeFlagId.value ? "INPUT_VALUE" : "SELECT_PARAM";
    return "READY";
  });
  const getTabCompletion = computed(() => {
    if (step.value === "entity" && q.value.length > 0) {
      const matches = entitySuggestions.value.filter((e2) => e2.label.startsWith(q.value.toLowerCase()) || e2.aliases.some((a2) => a2.startsWith(q.value.toLowerCase())));
      if (matches.length === 1) return matches[0].label;
      if (matches.length > 1) {
        const labels = matches.map((m2) => m2.label);
        let commonPrefix = labels[0];
        for (let i2 = 1; i2 < labels.length; i2++) {
          while (!labels[i2].startsWith(commonPrefix) && commonPrefix.length > 0) {
            commonPrefix = commonPrefix.slice(0, -1);
          }
        }
        if (commonPrefix.length > q.value.length) return commonPrefix;
      }
    }
    return q.value;
  });
  function animateFlag(flagId) {
    flagAnimating.value = flagId;
    setTimeout(() => {
      flagAnimating.value = null;
    }, 300);
  }
  function selectFlag(flagId) {
    if (activeFlagId.value === flagId) return;
    animateFlag(flagId);
    setTimeout(() => {
      activeFlagId.value = flagId;
      q.value = "";
      selectedIndex.value = 0;
      nextTick(() => inputEl.value?.focus());
      const field = selectedVerb.value?.fields.find((f2) => f2.id === flagId);
      let defVal;
      if (field && typeof field.default !== "undefined") {
        defVal = typeof field.default === "function" ? field.default(params.value) : field.default;
      }
      if (!params.value[flagId] && defVal) {
        q.value = defVal;
        nextTick(() => {
          inputEl.value?.focus();
          inputEl.value?.select();
        });
      }
    }, 200);
  }
  function editFilledFlag(flagId) {
    const currentValue = params.value[flagId];
    delete params.value[flagId];
    activeFlagId.value = flagId;
    q.value = currentValue || "";
    editingFlagId.value = flagId;
    nextTick(() => {
      if (inputEl.value) {
        inputEl.value.focus();
        inputEl.value.select();
      }
    });
  }
  function completeCurrentFlag() {
    if (!activeFlagId.value || !currentField.value) return;
    const val = q.value.trim();
    if (val || !currentField.value.required) {
      const completingFlagId = activeFlagId.value;
      if (val) params.value[completingFlagId] = val;
      let nextField;
      if (selectedVerb.value) {
        const nextRequired = selectedVerb.value.fields.find((f2) => f2.required && !params.value[f2.id]);
        const nextAvailable = selectedVerb.value.fields.find((f2) => !params.value[f2.id]);
        nextField = nextRequired || nextAvailable;
      }
      animatingToCompleted.value = completingFlagId;
      activeFlagId.value = null;
      editingFlagId.value = null;
      q.value = "";
      selectedIndex.value = 0;
      if (nextField) setTimeout(() => selectFlag(nextField.id), 200);
      setTimeout(() => {
        animatingToCompleted.value = null;
      }, 450);
    }
  }
  function cycleToLastFilledFlag() {
    const filled = filledFlags.value;
    if (filled.length === 0) return;
    editFilledFlag(filled[filled.length - 1].id);
  }
  function handleDashParameter() {
    const match = dashParameterMatch.value;
    if (match) {
      selectFlag(match.id);
      return true;
    }
    return false;
  }
  async function loadCompanyMembers(companyId) {
    if (companyMembersLoading.value[companyId]) return;
    companyMembersLoading.value[companyId] = true;
    try {
      await ensureCsrf();
      const { data } = await http.get(`/web/companies/${encodeURIComponent(companyId)}/users`);
      companyMembers.value[companyId] = data?.data || [];
    } catch (e2) {
      companyMembers.value[companyId] = [];
    } finally {
      companyMembersLoading.value[companyId] = false;
    }
  }
  function quickAssignToCompany(companyId) {
    const coEntity = entities.find((e2) => e2.id === "company") || null;
    if (!coEntity) return;
    selectedEntity.value = coEntity;
    const assignVerb = coEntity.verbs.find((v2) => v2.id === "assign") || null;
    selectedVerb.value = assignVerb || null;
    step.value = "fields";
    params.value["company"] = companyId;
    const emailField = assignVerb?.fields.find((f2) => f2.id === "email");
    if (emailField) selectFlag("email");
    selectedIndex.value = 0;
    q.value = "";
    nextTick(() => inputEl.value?.focus());
  }
  async function setActiveCompany(companyId) {
    try {
      await ensureCsrf();
      await http.post("/web/companies/switch", { company_id: companyId });
      window.location.reload();
    } catch (e2) {
    }
  }
  function quickAssignUserToCompany(userIdOrEmail) {
    const coEntity = entities.find((e2) => e2.id === "company") || null;
    if (!coEntity) return;
    selectedEntity.value = coEntity;
    const assignVerb = coEntity.verbs.find((v2) => v2.id === "assign") || null;
    selectedVerb.value = assignVerb || null;
    step.value = "fields";
    params.value["email"] = userIdOrEmail;
    const companyField = assignVerb?.fields.find((f2) => f2.id === "company");
    if (companyField) selectFlag("company");
    selectedIndex.value = 0;
    q.value = "";
    nextTick(() => inputEl.value?.focus());
  }
  function quickUnassignUserFromCompany(userEmail, companyId) {
    const coEntity = entities.find((e2) => e2.id === "company") || null;
    if (!coEntity) return;
    selectedEntity.value = coEntity;
    const unassignVerb = coEntity.verbs.find((v2) => v2.id === "unassign") || null;
    selectedVerb.value = unassignVerb || null;
    step.value = "fields";
    params.value["email"] = userEmail;
    params.value["company"] = companyId;
    selectedIndex.value = 0;
    q.value = "";
    nextTick(() => inputEl.value?.focus());
  }
  function resetAll() {
    step.value = "entity";
    q.value = "";
    selectedEntity.value = null;
    selectedVerb.value = null;
    params.value = {};
    selectedIndex.value = 0;
    executing.value = false;
    activeFlagId.value = null;
    editingFlagId.value = null;
    animatingToCompleted.value = null;
  }
  function goHome() {
    step.value = "entity";
    q.value = "";
    selectedVerb.value = null;
    selectedEntity.value = null;
    selectedIndex.value = 0;
    activeFlagId.value = null;
    editingFlagId.value = null;
    animatingToCompleted.value = null;
  }
  function goBack() {
    if (step.value === "fields" && activeFlagId.value) {
      activeFlagId.value = null;
      editingFlagId.value = null;
      q.value = "";
      return;
    }
    if (step.value === "fields" && selectedVerb.value) {
      if (q.value) {
        q.value = "";
        return;
      }
      selectedVerb.value = null;
      step.value = "verb";
      q.value = "";
      selectedIndex.value = 0;
      return;
    }
    if (step.value === "verb" && selectedEntity.value) {
      if (q.value) {
        q.value = "";
        return;
      }
      selectedEntity.value = null;
      step.value = "entity";
      q.value = "";
      selectedIndex.value = 0;
      return;
    }
    if (step.value === "entity" && q.value) {
      q.value = "";
      return;
    }
    open.value = false;
  }
  function selectEntity(entity) {
    selectedEntity.value = entity;
    step.value = "verb";
    q.value = "";
    selectedIndex.value = 0;
    nextTick(() => inputEl.value?.focus());
  }
  function selectVerb(verb) {
    selectedVerb.value = verb;
    step.value = "fields";
    q.value = "";
    selectedIndex.value = 0;
    activeFlagId.value = null;
    nextTick(() => inputEl.value?.focus());
    if (Object.keys(stashParams.value).length > 0 && selectedVerb.value) {
      for (const f2 of selectedVerb.value.fields) {
        const v2 = stashParams.value[f2.id];
        if (v2 && !params.value[f2.id]) params.value[f2.id] = v2;
      }
      stashParams.value = {};
    }
    setTimeout(() => {
      const firstRequired = verb.fields.find((f2) => f2.required);
      if (firstRequired) selectFlag(firstRequired.id);
    }, 100);
  }
  function selectChoice(choice) {
    if (!currentField.value) return;
    q.value = choice;
    setTimeout(completeCurrentFlag, 50);
  }
  async function execute() {
    if (!selectedVerb.value) return;
    if (selectedVerb.value.action.startsWith("ui.")) return;
    if (selectedVerb.value.id === "delete" && params.value["company"]) {
      const coId = params.value["company"];
      const details = companyDetails.value[coId];
      if (details) {
        if (!deleteConfirmRequired.value) deleteConfirmRequired.value = details.slug || details.name;
        if (!deleteConfirmText.value || deleteConfirmText.value !== deleteConfirmRequired.value) return;
      }
    }
    if (!allRequiredFilled.value) return;
    executing.value = true;
    try {
      const response = await http.post("/commands", params.value, { headers: withIdempotency({ "X-Action": selectedVerb.value.action }) });
      results.value = [{ success: true, action: selectedVerb.value.action, params: params.value, timestamp: (/* @__PURE__ */ new Date()).toISOString(), message: `Successfully executed ${selectedEntity.value?.label} ${selectedVerb.value.label}`, data: response.data }, ...results.value.slice(0, 4)];
      showResults.value = true;
      setTimeout(() => {
        resetAll();
        open.value = false;
      }, 2e3);
    } catch (error) {
      results.value = [{ success: false, action: selectedVerb.value.action, params: params.value, timestamp: (/* @__PURE__ */ new Date()).toISOString(), message: `Failed to execute ${selectedEntity.value?.label} ${selectedVerb.value.label}`, error: error.response?.data || error.message }, ...results.value.slice(0, 4)];
      showResults.value = true;
    } finally {
      executing.value = false;
    }
  }
  function pickUserEmail(email) {
    if (currentField.value?.id === "email") {
      q.value = email;
      setTimeout(completeCurrentFlag, 10);
    } else {
      const userEntity = entities.find((e2) => e2.id === "user") || null;
      if (userEntity) {
        selectedEntity.value = userEntity;
        step.value = "verb";
        q.value = "";
        selectedIndex.value = 0;
        stashParams.value = { email };
      } else {
        q.value = email;
      }
    }
  }
  function pickCompanyName(idOrName) {
    if (currentField.value?.id === "company") {
      q.value = idOrName;
      setTimeout(completeCurrentFlag, 10);
    } else {
      const coEntity = entities.find((e2) => e2.id === "company") || null;
      if (coEntity) {
        selectedEntity.value = coEntity;
        step.value = "verb";
        q.value = "";
        selectedIndex.value = 0;
        stashParams.value = { company: idOrName };
      } else {
        q.value = idOrName;
      }
    }
  }
  function pickGeneric(value) {
    if (currentField.value) {
      q.value = value;
      setTimeout(completeCurrentFlag, 10);
    } else {
      q.value = value;
    }
  }
  watch(highlightedItem, async (item) => {
    if (!item || !item.meta) return;
    if (showUserPicker.value) {
      const userId = item.meta.id;
      if (!userId || userDetails.value[userId]) return;
      try {
        await ensureCsrf();
        const { data } = await http.get(`/web/users/${encodeURIComponent(userId)}`);
        userDetails.value[userId] = data?.data || {};
      } catch (e2) {
      }
    } else if (showCompanyPicker.value) {
      const companyId = item.meta.id;
      if (!companyId || companyDetails.value[companyId]) return;
      try {
        await ensureCsrf();
        const { data } = await http.get(`/web/companies/${encodeURIComponent(companyId)}`);
        companyDetails.value[companyId] = data?.data || {};
      } catch (e2) {
      }
    }
  });
  watch([q, step], ([newQ, newStep]) => {
    if (newStep === "entity" && newQ.length >= 2) {
      const exact = entitySuggestions.value.find((e2) => e2.label.toLowerCase() === newQ.toLowerCase() || e2.aliases.some((a2) => a2.toLowerCase() === newQ.toLowerCase()));
      if (exact) setTimeout(() => selectEntity(exact), 100);
    }
  });
  watch([verbSuggestions, step], () => {
    if (step.value === "verb") {
      if (selectedIndex.value >= verbSuggestions.value.length) {
        selectedIndex.value = 0;
      }
    }
  });
  const lookupTimers = {};
  watch([q, currentField, step, companySource, userSource, () => params.value.email], async ([qv, cf, st]) => {
    const schedule = (key, ms, fn) => {
      clearTimeout(lookupTimers[key]);
      lookupTimers[key] = setTimeout(fn, ms);
    };
    if (st !== "fields" || !cf || cf.type !== "remote") {
      panelItems.value = [];
      inlineItems.value = [];
      return;
    }
    const qstr = qv || "";
    const run = async () => {
      const items = await provider.fromField(cf, qstr, params.value);
      if (cf.picker === "panel") {
        panelItems.value = items;
      } else {
        inlineItems.value = items;
      }
    };
    schedule("remote-lookup:" + cf.id, 160, run);
  });
  watch([isUIList, step, q, companySource, userSource, () => params.value.email, selectedVerb], async ([ui, st]) => {
    if (!ui || st !== "fields") return;
    const action = selectedVerb.value?.action || "";
    try {
      if (action === "ui.list.companies") {
        const items = await provider.companies();
        panelItems.value = items;
      } else if (action === "ui.list.users") {
        const items = await provider.users(q.value);
        panelItems.value = items;
      }
    } catch (e2) {
      panelItems.value = [];
    }
  });
  return {
    open,
    q,
    step,
    selectedEntity,
    selectedVerb,
    params,
    inputEl,
    selectedIndex,
    executing,
    results,
    showResults,
    stashParams,
    activeFlagId,
    flagAnimating,
    editingFlagId,
    animatingToCompleted,
    isSuperAdmin,
    currentCompanyId,
    userSource,
    companySource,
    panelItems,
    inlineItems,
    // Replaces userOptions, companyOptions, etc.
    companyDetails,
    companyMembers,
    companyMembersLoading,
    userDetails,
    deleteConfirmText,
    deleteConfirmRequired,
    entitySuggestions,
    verbSuggestions,
    availableFlags,
    filledFlags,
    currentField,
    dashParameterMatch,
    allRequiredFilled,
    currentChoices,
    isUIList,
    showUserPicker,
    showCompanyPicker,
    showGenericPanelPicker,
    inlineSuggestions,
    highlightedUser,
    highlightedCompany,
    highlightedItem,
    statusText,
    getTabCompletion,
    animateFlag,
    selectFlag,
    editFilledFlag,
    completeCurrentFlag,
    cycleToLastFilledFlag,
    handleDashParameter,
    loadCompanyMembers,
    quickAssignToCompany,
    setActiveCompany,
    quickAssignUserToCompany,
    quickUnassignUserFromCompany,
    resetAll,
    goHome,
    goBack,
    selectEntity,
    selectVerb,
    selectChoice,
    execute,
    pickUserEmail,
    pickCompanyName,
    pickGeneric
  };
}
function usePaletteKeybindings(palette) {
  const {
    open,
    step,
    q,
    isUIList,
    inputEl,
    selectedIndex,
    entitySuggestions,
    verbSuggestions,
    currentChoices,
    inlineSuggestions,
    panelItems,
    showUserPicker,
    showCompanyPicker,
    showGenericPanelPicker,
    activeFlagId,
    dashParameterMatch,
    allRequiredFilled,
    filledFlags,
    getTabCompletion,
    selectEntity,
    selectVerb,
    selectChoice,
    pickUserEmail,
    pickCompanyName,
    pickGeneric,
    completeCurrentFlag,
    execute,
    cycleToLastFilledFlag,
    handleDashParameter,
    goBack,
    resetAll
  } = palette;
  function handleKeydown(e2) {
    if (step.value === "fields" && !activeFlagId.value && e2.key === "Enter" && dashParameterMatch.value) {
      e2.preventDefault();
      handleDashParameter();
      return;
    }
    const suggestionSources = [
      { condition: step.value === "entity", list: entitySuggestions },
      { condition: step.value === "verb", list: verbSuggestions },
      { condition: currentChoices.value.length > 0, list: currentChoices },
      { condition: showUserPicker.value || showCompanyPicker.value || showGenericPanelPicker.value, list: panelItems },
      { condition: inlineSuggestions.value.length > 0, list: inlineSuggestions }
    ];
    const activeSource = suggestionSources.find((s2) => s2.condition);
    if (e2.key === "ArrowDown") {
      e2.preventDefault();
      if (activeSource) {
        selectedIndex.value = Math.min(selectedIndex.value + 1, activeSource.list.value.length - 1);
      }
    } else if (e2.key === "ArrowUp") {
      e2.preventDefault();
      if (activeSource) {
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
      }
    } else if (e2.key === "Enter") {
      e2.preventDefault();
      if (step.value === "entity" && entitySuggestions.value[selectedIndex.value]) selectEntity(entitySuggestions.value[selectedIndex.value]);
      else if (step.value === "verb" && verbSuggestions.value[selectedIndex.value]) selectVerb(verbSuggestions.value[selectedIndex.value]);
      else if (step.value === "fields") {
        if (isUIList.value) {
          return;
        }
        if (currentChoices.value.length > 0 && selectedIndex.value < currentChoices.value.length) {
          selectChoice(currentChoices.value[selectedIndex.value]);
        } else if ((showUserPicker.value || showCompanyPicker.value || showGenericPanelPicker.value) && panelItems.value[selectedIndex.value]) {
          const item = panelItems.value[selectedIndex.value];
          if (showUserPicker.value) pickUserEmail(item.value);
          else if (showCompanyPicker.value) pickCompanyName(item.value);
          else pickGeneric(item.value);
        } else if (inlineSuggestions.value.length > 0) {
          selectChoice(inlineSuggestions.value[Math.min(selectedIndex.value, inlineSuggestions.value.length - 1)].value);
        } else if (activeFlagId.value) completeCurrentFlag();
        else if (allRequiredFilled.value) execute();
      }
    } else if (e2.key === "Tab") {
      e2.preventDefault();
      if (e2.shiftKey && step.value === "fields" && filledFlags.value.length > 0) cycleToLastFilledFlag();
      else if (step.value === "entity") q.value = getTabCompletion.value;
      else if (step.value === "fields" && activeFlagId.value) completeCurrentFlag();
      else if (activeSource && activeSource.list.value.length > 0) selectedIndex.value = (selectedIndex.value + 1) % activeSource.list.value.length;
    } else if (e2.key === "Escape") {
      e2.preventDefault();
      e2.stopPropagation();
      goBack();
    }
  }
  return { handleKeydown };
}
const _sfc_main$o = /* @__PURE__ */ defineComponent({
  __name: "CommandPalette",
  __ssrInlineRender: true,
  setup(__props) {
    const palette = usePalette();
    const {
      open,
      q,
      step,
      selectedEntity,
      selectedVerb,
      params,
      inputEl,
      selectedIndex,
      executing,
      results,
      showResults,
      activeFlagId,
      animatingToCompleted,
      isSuperAdmin,
      userSource,
      companySource,
      panelItems,
      companyDetails,
      companyMembers,
      companyMembersLoading,
      userDetails,
      deleteConfirmText,
      deleteConfirmRequired,
      entitySuggestions,
      verbSuggestions,
      availableFlags,
      filledFlags,
      currentField,
      dashParameterMatch,
      allRequiredFilled,
      currentChoices,
      showUserPicker,
      showCompanyPicker,
      showGenericPanelPicker,
      inlineSuggestions,
      highlightedItem,
      statusText,
      selectFlag,
      editFilledFlag,
      completeCurrentFlag,
      handleDashParameter,
      loadCompanyMembers,
      quickAssignToCompany,
      setActiveCompany,
      quickAssignUserToCompany,
      quickUnassignUserFromCompany,
      resetAll,
      goHome,
      goBack,
      selectEntity,
      selectVerb,
      selectChoice,
      execute,
      pickUserEmail,
      pickCompanyName,
      pickGeneric
    } = palette;
    const { handleKeydown } = usePaletteKeybindings(palette);
    function escapeHtml(s2) {
      return s2.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;");
    }
    function highlight(text, needle) {
      const n2 = (needle || "").trim();
      if (!n2) return escapeHtml(text);
      const lower = text.toLowerCase();
      const idx = lower.indexOf(n2.toLowerCase());
      if (idx === -1) return escapeHtml(text);
      const before = escapeHtml(text.slice(0, idx));
      const match = escapeHtml(text.slice(idx, idx + n2.length));
      const after = escapeHtml(text.slice(idx + n2.length));
      return `${before}<span class="underline decoration-dotted">${match}</span>${after}`;
    }
    function handleGlobalKeydown(e2) {
      const key = (e2.key || "").toLowerCase();
      const isCmdK = e2.metaKey && !e2.ctrlKey && !e2.altKey && !e2.shiftKey && key === "k";
      e2.ctrlKey && !e2.metaKey && !e2.altKey && !e2.shiftKey && key === "k";
      const isCtrlShiftK = e2.ctrlKey && e2.shiftKey && !e2.altKey && key === "k";
      const isCtrlSlash = e2.ctrlKey && !e2.altKey && (key === "/" || e2.code.toLowerCase() === "slash");
      const isCtrlSpace = e2.ctrlKey && !e2.shiftKey && !e2.altKey && (key === " " || e2.code.toLowerCase() === "space");
      const isAltK = e2.altKey && !e2.ctrlKey && !e2.metaKey && key === "k";
      if (isCmdK || isCtrlShiftK || isCtrlSlash || isCtrlSpace || isAltK) {
        e2.preventDefault();
        e2.stopPropagation();
        open.value = true;
        resetAll();
        nextTick(() => inputEl.value?.focus());
      }
    }
    onMounted(() => {
      window.addEventListener("keydown", handleGlobalKeydown, { passive: false });
    });
    onUnmounted(() => {
      window.removeEventListener("keydown", handleGlobalKeydown);
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[--><div class="fixed bottom-4 right-4 z-40" data-v-5548a2bb><button type="button" class="px-4 py-3 bg-gradient-to-br from-gray-800 to-gray-900 text-green-400 font-mono text-sm rounded-lg border border-green-600/30 hover:border-green-400 shadow-xl flex items-center gap-2 group" data-v-5548a2bb><span class="text-green-300" data-v-5548a2bb>$</span><span data-v-5548a2bb>command</span><kbd class="px-2 py-1 bg-gray-700/50 rounded text-xs border border-gray-600 group-hover:border-green-400/50" data-v-5548a2bb>⌘K</kbd></button></div>`);
      _push(ssrRenderComponent(unref(Dialog), {
        open: unref(open),
        onClose: ($event) => open.value = false,
        class: "relative z-50"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="fixed inset-0 bg-black/70 backdrop-blur-sm" aria-hidden="true" data-v-5548a2bb${_scopeId}></div><div class="fixed inset-0 flex items-start justify-center pt-4 sm:pt-20 px-4" data-v-5548a2bb${_scopeId}><div class="${ssrRenderClass([unref(showResults) ? "lg:max-w-5xl" : "", "w-full max-w-4xl flex flex-col lg:flex-row gap-4"])}" data-v-5548a2bb${_scopeId}>`);
            _push2(ssrRenderComponent(unref(DialogPanel), {
              class: ["flex-1 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden", unref(open) ? "scale-100 opacity-100" : "scale-105 opacity-0"],
              onKeydown: (e2) => {
                if (e2.key === "Escape") {
                  e2.preventDefault();
                  e2.stopPropagation();
                  unref(goBack)();
                }
              }
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`<div class="flex flex-col h-96 sm:h-[500px]" data-v-5548a2bb${_scopeId2}><div class="px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" data-v-5548a2bb${_scopeId2}><div class="flex items-center justify-between" data-v-5548a2bb${_scopeId2}><div class="flex items-center gap-3" data-v-5548a2bb${_scopeId2}><div class="flex gap-1.5" data-v-5548a2bb${_scopeId2}><div class="w-3 h-3 rounded-full bg-red-500 shadow-sm" data-v-5548a2bb${_scopeId2}></div><div class="w-3 h-3 rounded-full bg-yellow-500 shadow-sm" data-v-5548a2bb${_scopeId2}></div><div class="w-3 h-3 rounded-full bg-green-500 shadow-sm" data-v-5548a2bb${_scopeId2}></div></div><span class="text-gray-400 text-xs hidden sm:inline tracking-wide" data-v-5548a2bb${_scopeId2}>accounting-cli v1.0</span></div><div class="flex items-center gap-3" data-v-5548a2bb${_scopeId2}><button type="button" class="text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Back </button><button type="button" class="text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" data-v-5548a2bb${_scopeId2}></path></svg> Home </button><div class="text-gray-500 text-xs px-2 py-1 bg-gray-800/50 rounded-md border border-gray-700/50" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(unref(statusText))}</div></div></div></div>`);
                  if (unref(step) === "fields" && unref(selectedVerb)) {
                    _push3(`<div class="px-4 py-3 border-b border-gray-700/30 bg-gray-800/40" data-v-5548a2bb${_scopeId2}><div class="text-gray-500 text-xs mb-2 font-medium tracking-wide" data-v-5548a2bb${_scopeId2}>AVAILABLE PARAMETERS:</div><div class="flex flex-wrap gap-2" data-v-5548a2bb${_scopeId2}><!--[-->`);
                    ssrRenderList(unref(availableFlags), (flag) => {
                      _push3(`<button type="button" class="${ssrRenderClass([[
                        "border-gray-600/50 text-gray-300 bg-gray-800/40 hover:border-orange-500/70 hover:text-orange-300 hover:bg-orange-900/20",
                        ""
                      ], "px-3 py-1.5 text-xs rounded-lg border backdrop-blur-sm"])}" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(flag.placeholder)} `);
                      if (flag.required) {
                        _push3(`<span class="ml-1 text-red-400" data-v-5548a2bb${_scopeId2}>*</span>`);
                      } else {
                        _push3(`<!---->`);
                      }
                      _push3(`</button>`);
                    });
                    _push3(`<!--]--></div>`);
                    if (!unref(activeFlagId) && !unref(dashParameterMatch)) {
                      _push3(`<div class="text-gray-600 text-xs mt-2 flex items-center gap-1" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Click a parameter above or type -paramName to start entering values </div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(dashParameterMatch)) {
                      _push3(`<div class="text-orange-400 text-xs mt-2 flex items-center gap-1" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Press Enter to select: ${ssrInterpolate(unref(dashParameterMatch).placeholder)}</div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    _push3(`</div>`);
                  } else {
                    _push3(`<!---->`);
                  }
                  _push3(`<div class="px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/50 to-gray-900/10" data-v-5548a2bb${_scopeId2}><div class="flex items-center gap-2" data-v-5548a2bb${_scopeId2}><span class="text-green-400 text-lg" data-v-5548a2bb${_scopeId2}>❯</span><div class="starship-bc" data-v-5548a2bb${_scopeId2}>`);
                  if (unref(selectedEntity)) {
                    _push3(`<!--[--><div class="seg seg-entity seg-first" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(unref(selectedEntity).label)}</div>`);
                    if (unref(selectedVerb)) {
                      _push3(`<div class="seg seg-verb seg-mid" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(unref(selectedVerb).label)}</div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(step) === "fields" && unref(currentField)) {
                      _push3(`<div class="seg seg-active seg-last" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(unref(currentField).placeholder)}`);
                      if (unref(currentField).required) {
                        _push3(`<span class="ml-0.5 text-red-300" data-v-5548a2bb${_scopeId2}>*</span>`);
                      } else {
                        _push3(`<!---->`);
                      }
                      _push3(`</div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    _push3(`<!--]-->`);
                  } else {
                    _push3(`<!---->`);
                  }
                  _push3(`</div><div class="flex-1 ml-3" data-v-5548a2bb${_scopeId2}><div class="flex items-center gap-2 relative w-full" data-v-5548a2bb${_scopeId2}><input${ssrRenderAttr("value", unref(q))}${ssrRenderAttr("placeholder", unref(step) === "entity" ? "Search entities..." : unref(step) === "verb" ? "Search actions..." : !unref(activeFlagId) ? "Select parameter or type -param..." : "Enter value...")} class="${ssrRenderClass([[
                    unref(step) === "fields" && unref(currentField) ? "text-orange-300 placeholder-orange-300/50" : "",
                    unref(dashParameterMatch) ? "text-yellow-300" : unref(step) !== "fields" ? "text-green-400 placeholder-gray-600" : ""
                  ], "flex-1 bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus-visible:ring-0 appearance-none py-2 no-focus-ring rounded-lg px-3 border-0 focus:border-0"])}" style="${ssrRenderStyle({})}"${ssrIncludeBooleanAttr(unref(executing)) ? " disabled" : ""} data-v-5548a2bb${_scopeId2}>`);
                  if (unref(step) === "fields" && unref(activeFlagId) && unref(q).trim()) {
                    _push3(`<button type="button" class="px-3 py-1.5 bg-orange-700/50 text-orange-100 rounded-lg border border-orange-600/50 text-xs flex items-center gap-1 backdrop-blur-sm" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Set </button>`);
                  } else {
                    _push3(`<!---->`);
                  }
                  if (unref(step) === "fields" && !unref(activeFlagId) && unref(dashParameterMatch)) {
                    _push3(`<button type="button" class="px-3 py-1.5 bg-yellow-700/50 text-yellow-100 rounded-lg border border-yellow-600/50 text-xs flex items-center gap-1 backdrop-blur-sm" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Select </button>`);
                  } else {
                    _push3(`<!---->`);
                  }
                  if (unref(step) === "fields" && unref(allRequiredFilled) && !unref(activeFlagId)) {
                    _push3(`<button type="button"${ssrIncludeBooleanAttr(unref(executing)) ? " disabled" : ""} class="px-4 py-1.5 bg-green-700/50 text-green-100 rounded-lg border border-green-600/50 text-xs disabled:opacity-50 flex items-center gap-1 backdrop-blur-sm" data-v-5548a2bb${_scopeId2}>`);
                    if (!unref(executing)) {
                      _push3(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg>`);
                    } else {
                      _push3(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg>`);
                    }
                    _push3(` ${ssrInterpolate(unref(executing) ? "Executing..." : "Execute")}</button>`);
                  } else {
                    _push3(`<!---->`);
                  }
                  _push3(`</div></div></div></div>`);
                  if (unref(step) === "fields" && (unref(filledFlags).length > 0 || unref(animatingToCompleted))) {
                    _push3(`<div class="px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/20 to-gray-900/5" data-v-5548a2bb${_scopeId2}><div class="text-gray-500 text-xs mb-2 font-medium tracking-wide" data-v-5548a2bb${_scopeId2}>COMPLETED PARAMETERS:</div><div class="flex flex-wrap gap-2" data-v-5548a2bb${_scopeId2}><!--[-->`);
                    ssrRenderList(unref(filledFlags), (flag) => {
                      _push3(`<button type="button" class="px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 backdrop-blur-sm flex items-center gap-1" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> ${ssrInterpolate(flag.placeholder)}=&quot;${ssrInterpolate(unref(params)[flag.id])}&quot; </button>`);
                    });
                    _push3(`<!--]-->`);
                    if (unref(animatingToCompleted) && unref(selectedVerb)) {
                      _push3(`<div class="px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 flex items-center gap-1 backdrop-blur-sm animate-slideToInput" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> ${ssrInterpolate(unref(selectedVerb).fields.find((f2) => f2.id === unref(animatingToCompleted))?.placeholder)}=&quot;${ssrInterpolate(unref(params)[unref(animatingToCompleted)])}&quot; </div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    _push3(`</div></div>`);
                  } else {
                    _push3(`<!---->`);
                  }
                  _push3(`<div class="flex-1 overflow-auto p-3 bg-gradient-to-b from-gray-900/10 to-gray-900/5" data-v-5548a2bb${_scopeId2}>`);
                  if (unref(step) === "entity") {
                    _push3(`<div class="space-y-2" data-v-5548a2bb${_scopeId2}><div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block" data-v-5548a2bb${_scopeId2}>Available entities</div><!--[-->`);
                    ssrRenderList(unref(entitySuggestions), (entity, index) => {
                      _push3(`<div class="${ssrRenderClass([index === unref(selectedIndex) ? "bg-blue-900/30 text-blue-200 border-blue-700/50 scale-[1.02] shadow-lg shadow-blue-500/10" : "hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent", "px-4 py-3 rounded-xl cursor-pointer border"])}" data-v-5548a2bb${_scopeId2}><div class="flex items-center justify-between" data-v-5548a2bb${_scopeId2}><span class="text-green-400 font-medium" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(entity.label)}</span><span class="text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(entity.verbs.length)} actions</span></div><div class="text-xs text-gray-500 mt-1" data-v-5548a2bb${_scopeId2}> aliases: ${ssrInterpolate(entity.aliases.join(", "))}</div></div>`);
                    });
                    _push3(`<!--]--></div>`);
                  } else if (unref(step) === "verb") {
                    _push3(`<div class="space-y-2" data-v-5548a2bb${_scopeId2}><div class="text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block" data-v-5548a2bb${_scopeId2}>Available actions</div><!--[-->`);
                    ssrRenderList(unref(verbSuggestions), (verb, index) => {
                      _push3(`<div class="${ssrRenderClass([index === unref(selectedIndex) ? "bg-yellow-900/30 text-yellow-200 border-yellow-700/50 scale-[1.02] shadow-lg shadow-yellow-500/10" : "hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent", "px-4 py-3 rounded-xl cursor-pointer border"])}" data-v-5548a2bb${_scopeId2}><div class="flex items-center justify-between" data-v-5548a2bb${_scopeId2}><span class="text-yellow-400 font-medium" data-v-5548a2bb${_scopeId2}>${highlight(verb.label, unref(q)) ?? ""}</span><span class="text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(verb.fields.filter((f2) => f2.required).length)}/${ssrInterpolate(verb.fields.length)} required </span></div><div class="text-xs text-gray-500 mt-1" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(verb.fields.map((f2) => f2.placeholder).join(" "))}</div></div>`);
                    });
                    _push3(`<!--]--></div>`);
                  } else if (unref(step) === "fields") {
                    _push3(`<div class="space-y-4" data-v-5548a2bb${_scopeId2}>`);
                    if (unref(currentChoices).length > 0) {
                      _push3(`<div data-v-5548a2bb${_scopeId2}><div class="text-gray-500 text-xs px-2 py-1.5 mb-3 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-flex items-center gap-2" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Select option </div><div class="grid grid-cols-1 sm:grid-cols-2 gap-2" data-v-5548a2bb${_scopeId2}><!--[-->`);
                      ssrRenderList(unref(currentChoices), (choice, index) => {
                        _push3(`<button class="${ssrRenderClass([index === unref(selectedIndex) ? "bg-orange-900/30 text-orange-200 border-orange-700/50 scale-[1.02] shadow-lg shadow-orange-500/10" : "bg-gray-800/30 hover:bg-gray-700/30 text-gray-300 hover:scale-[1.01] border-transparent", "px-4 py-2.5 text-left rounded-xl border"])}" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(choice)}</button>`);
                      });
                      _push3(`<!--]--></div></div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(showGenericPanelPicker)) {
                      _push3(ssrRenderComponent(_sfc_main$p, {
                        items: unref(panelItems),
                        "selected-index": unref(selectedIndex),
                        onSelect: (it) => unref(pickGeneric)(it.value)
                      }, {
                        header: withCtx((_3, _push4, _parent4, _scopeId3) => {
                          if (_push4) {
                            _push4(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId3}><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" data-v-5548a2bb${_scopeId3}></path></svg><span data-v-5548a2bb${_scopeId3}>Suggestions</span>`);
                          } else {
                            return [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("span", null, "Suggestions")
                            ];
                          }
                        }),
                        preview: withCtx(({ item }, _push4, _parent4, _scopeId3) => {
                          if (_push4) {
                            _push4(`<div class="text-gray-300" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(item.label)}</div>`);
                          } else {
                            return [
                              createVNode("div", { class: "text-gray-300" }, toDisplayString(item.label), 1)
                            ];
                          }
                        }),
                        _: 1
                      }, _parent3, _scopeId2));
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(showUserPicker)) {
                      _push3(ssrRenderComponent(_sfc_main$p, {
                        items: unref(panelItems),
                        "selected-index": unref(selectedIndex),
                        onSelect: (it) => unref(pickUserEmail)(it.value)
                      }, {
                        header: withCtx((_3, _push4, _parent4, _scopeId3) => {
                          if (_push4) {
                            _push4(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId3}><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" data-v-5548a2bb${_scopeId3}></path></svg><span data-v-5548a2bb${_scopeId3}>Users</span><div class="flex gap-1 ml-auto" data-v-5548a2bb${_scopeId3}>`);
                            if (unref(isSuperAdmin)) {
                              _push4(`<!--[--><button type="button" class="${ssrRenderClass([unref(userSource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"])}" data-v-5548a2bb${_scopeId3}>All</button><button type="button" class="${ssrRenderClass([unref(userSource) === "company" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"])}" data-v-5548a2bb${_scopeId3}>Company</button><!--]-->`);
                            } else {
                              _push4(`<!---->`);
                            }
                            _push4(`</div>`);
                          } else {
                            return [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("span", null, "Users"),
                              createVNode("div", { class: "flex gap-1 ml-auto" }, [
                                unref(isSuperAdmin) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => userSource.value = "all",
                                    class: [unref(userSource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "All", 10, ["onClick"]),
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => userSource.value = "company",
                                    class: [unref(userSource) === "company" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "Company", 10, ["onClick"])
                                ], 64)) : createCommentVNode("", true)
                              ])
                            ];
                          }
                        }),
                        _: 1
                      }, _parent3, _scopeId2));
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(showCompanyPicker)) {
                      _push3(ssrRenderComponent(_sfc_main$p, {
                        items: unref(panelItems),
                        "selected-index": unref(selectedIndex),
                        onSelect: (it) => unref(pickCompanyName)(it.value)
                      }, {
                        header: withCtx((_3, _push4, _parent4, _scopeId3) => {
                          if (_push4) {
                            _push4(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId3}><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" data-v-5548a2bb${_scopeId3}></path></svg><span data-v-5548a2bb${_scopeId3}>Companies</span><div class="flex gap-1 ml-auto" data-v-5548a2bb${_scopeId3}>`);
                            if (unref(isSuperAdmin)) {
                              _push4(`<!--[--><button class="${ssrRenderClass([unref(companySource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"])}" data-v-5548a2bb${_scopeId3}>All</button><button class="${ssrRenderClass([unref(companySource) === "me" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"])}" data-v-5548a2bb${_scopeId3}>Mine</button>`);
                              if (unref(params).email) {
                                _push4(`<button class="${ssrRenderClass([unref(companySource) === "byUser" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"])}" data-v-5548a2bb${_scopeId3}>User</button>`);
                              } else {
                                _push4(`<!---->`);
                              }
                              _push4(`<!--]-->`);
                            } else {
                              _push4(`<!---->`);
                            }
                            _push4(`</div>`);
                          } else {
                            return [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("span", null, "Companies"),
                              createVNode("div", { class: "flex gap-1 ml-auto" }, [
                                unref(isSuperAdmin) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                  createVNode("button", {
                                    onClick: ($event) => companySource.value = "all",
                                    class: [unref(companySource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "All", 10, ["onClick"]),
                                  createVNode("button", {
                                    onClick: ($event) => companySource.value = "me",
                                    class: [unref(companySource) === "me" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "Mine", 10, ["onClick"]),
                                  unref(params).email ? (openBlock(), createBlock("button", {
                                    key: 0,
                                    onClick: ($event) => companySource.value = "byUser",
                                    class: [unref(companySource) === "byUser" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "User", 10, ["onClick"])) : createCommentVNode("", true)
                                ], 64)) : createCommentVNode("", true)
                              ])
                            ];
                          }
                        }),
                        preview: withCtx(({ item }, _push4, _parent4, _scopeId3) => {
                          if (_push4) {
                            _push4(`<div class="font-medium text-gray-200 mb-2" data-v-5548a2bb${_scopeId3}>Selected Company</div><div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-v-5548a2bb${_scopeId3}><div data-v-5548a2bb${_scopeId3}><div class="text-gray-500" data-v-5548a2bb${_scopeId3}>Name:</div><div class="text-gray-200" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(item.label)}</div></div>`);
                            if (unref(companyDetails)[item.meta?.id]) {
                              _push4(`<div data-v-5548a2bb${_scopeId3}><div class="text-gray-500" data-v-5548a2bb${_scopeId3}>Slug:</div><div class="text-gray-200" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(unref(companyDetails)[item.meta?.id].slug)}</div></div>`);
                            } else {
                              _push4(`<!---->`);
                            }
                            _push4(`</div>`);
                            if (unref(companyDetails)[item.meta?.id]) {
                              _push4(`<div class="space-y-2 pt-2 border-t border-gray-800/30" data-v-5548a2bb${_scopeId3}><div class="grid grid-cols-2 gap-3" data-v-5548a2bb${_scopeId3}><div data-v-5548a2bb${_scopeId3}><div class="text-gray-500" data-v-5548a2bb${_scopeId3}>Currency:</div><div class="text-gray-200" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(unref(companyDetails)[item.meta?.id].base_currency)}</div></div><div data-v-5548a2bb${_scopeId3}><div class="text-gray-500" data-v-5548a2bb${_scopeId3}>Language:</div><div class="text-gray-200" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(unref(companyDetails)[item.meta?.id].language)}</div></div></div><div data-v-5548a2bb${_scopeId3}><div class="text-gray-500" data-v-5548a2bb${_scopeId3}>Members:</div><div class="text-gray-200" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(unref(companyDetails)[item.meta?.id].members_count)}</div></div><div data-v-5548a2bb${_scopeId3}><div class="text-gray-500" data-v-5548a2bb${_scopeId3}>Roles:</div><div class="text-gray-300 text-xs mt-0.5 grid grid-cols-2 gap-1" data-v-5548a2bb${_scopeId3}><span data-v-5548a2bb${_scopeId3}>owner: ${ssrInterpolate((unref(companyDetails)[item.meta?.id].role_counts || {}).owner || 0)}</span><span data-v-5548a2bb${_scopeId3}>admin: ${ssrInterpolate((unref(companyDetails)[item.meta?.id].role_counts || {}).admin || 0)}</span><span data-v-5548a2bb${_scopeId3}>accountant: ${ssrInterpolate((unref(companyDetails)[item.meta?.id].role_counts || {}).accountant || 0)}</span><span data-v-5548a2bb${_scopeId3}>viewer: ${ssrInterpolate((unref(companyDetails)[item.meta?.id].role_counts || {}).viewer || 0)}</span></div></div>`);
                              if (unref(companyDetails)[item.meta?.id].owners && unref(companyDetails)[item.meta?.id].owners.length) {
                                _push4(`<div class="text-gray-500" data-v-5548a2bb${_scopeId3}> Owners: <span class="text-gray-300" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(unref(companyDetails)[item.meta?.id].owners.map((o2) => o2.name).join(", "))}</span></div>`);
                              } else {
                                _push4(`<!---->`);
                              }
                              if (unref(companyDetails)[item.meta?.id].last_activity) {
                                _push4(`<div class="text-gray-500" data-v-5548a2bb${_scopeId3}> Last activity: <span class="text-gray-300" data-v-5548a2bb${_scopeId3}>${ssrInterpolate(unref(companyDetails)[item.meta?.id].last_activity.action)}</span><span class="text-gray-400 block text-xs mt-0.5" data-v-5548a2bb${_scopeId3}>@ ${ssrInterpolate(unref(companyDetails)[item.meta?.id].last_activity.created_at)}</span></div>`);
                              } else {
                                _push4(`<!---->`);
                              }
                              _push4(`<div class="flex flex-wrap gap-2 pt-2 border-t border-gray-800/30" data-v-5548a2bb${_scopeId3}><button type="button" class="px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId3}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId3}><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" data-v-5548a2bb${_scopeId3}></path></svg> View members </button><button type="button" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId3}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId3}><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" data-v-5548a2bb${_scopeId3}></path></svg> Assign user </button><button type="button" class="px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId3}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId3}><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId3}></path></svg> Set active </button></div></div>`);
                            } else {
                              _push4(`<!---->`);
                            }
                          } else {
                            return [
                              createVNode("div", { class: "font-medium text-gray-200 mb-2" }, "Selected Company"),
                              createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 gap-3" }, [
                                createVNode("div", null, [
                                  createVNode("div", { class: "text-gray-500" }, "Name:"),
                                  createVNode("div", { class: "text-gray-200" }, toDisplayString(item.label), 1)
                                ]),
                                unref(companyDetails)[item.meta?.id] ? (openBlock(), createBlock("div", { key: 0 }, [
                                  createVNode("div", { class: "text-gray-500" }, "Slug:"),
                                  createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].slug), 1)
                                ])) : createCommentVNode("", true)
                              ]),
                              unref(companyDetails)[item.meta?.id] ? (openBlock(), createBlock("div", {
                                key: 0,
                                class: "space-y-2 pt-2 border-t border-gray-800/30"
                              }, [
                                createVNode("div", { class: "grid grid-cols-2 gap-3" }, [
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Currency:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].base_currency), 1)
                                  ]),
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Language:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].language), 1)
                                  ])
                                ]),
                                createVNode("div", null, [
                                  createVNode("div", { class: "text-gray-500" }, "Members:"),
                                  createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].members_count), 1)
                                ]),
                                createVNode("div", null, [
                                  createVNode("div", { class: "text-gray-500" }, "Roles:"),
                                  createVNode("div", { class: "text-gray-300 text-xs mt-0.5 grid grid-cols-2 gap-1" }, [
                                    createVNode("span", null, "owner: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).owner || 0), 1),
                                    createVNode("span", null, "admin: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).admin || 0), 1),
                                    createVNode("span", null, "accountant: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).accountant || 0), 1),
                                    createVNode("span", null, "viewer: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).viewer || 0), 1)
                                  ])
                                ]),
                                unref(companyDetails)[item.meta?.id].owners && unref(companyDetails)[item.meta?.id].owners.length ? (openBlock(), createBlock("div", {
                                  key: 0,
                                  class: "text-gray-500"
                                }, [
                                  createTextVNode(" Owners: "),
                                  createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[item.meta?.id].owners.map((o2) => o2.name).join(", ")), 1)
                                ])) : createCommentVNode("", true),
                                unref(companyDetails)[item.meta?.id].last_activity ? (openBlock(), createBlock("div", {
                                  key: 1,
                                  class: "text-gray-500"
                                }, [
                                  createTextVNode(" Last activity: "),
                                  createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[item.meta?.id].last_activity.action), 1),
                                  createVNode("span", { class: "text-gray-400 block text-xs mt-0.5" }, "@ " + toDisplayString(unref(companyDetails)[item.meta?.id].last_activity.created_at), 1)
                                ])) : createCommentVNode("", true),
                                createVNode("div", { class: "flex flex-wrap gap-2 pt-2 border-t border-gray-800/30" }, [
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => unref(loadCompanyMembers)(item.meta?.id),
                                    class: "px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1"
                                  }, [
                                    (openBlock(), createBlock("svg", {
                                      xmlns: "http://www.w3.org/2000/svg",
                                      class: "h-3.5 w-3.5",
                                      viewBox: "0 0 20 20",
                                      fill: "currentColor"
                                    }, [
                                      createVNode("path", { d: "M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" })
                                    ])),
                                    createTextVNode(" View members ")
                                  ], 8, ["onClick"]),
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => unref(quickAssignToCompany)(item.meta?.id),
                                    class: "px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1"
                                  }, [
                                    (openBlock(), createBlock("svg", {
                                      xmlns: "http://www.w3.org/2000/svg",
                                      class: "h-3.5 w-3.5",
                                      viewBox: "0 0 20 20",
                                      fill: "currentColor"
                                    }, [
                                      createVNode("path", { d: "M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" })
                                    ])),
                                    createTextVNode(" Assign user ")
                                  ], 8, ["onClick"]),
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => unref(setActiveCompany)(item.meta?.id),
                                    class: "px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1"
                                  }, [
                                    (openBlock(), createBlock("svg", {
                                      xmlns: "http://www.w3.org/2000/svg",
                                      class: "h-3.5 w-3.5",
                                      viewBox: "0 0 20 20",
                                      fill: "currentColor"
                                    }, [
                                      createVNode("path", {
                                        "fill-rule": "evenodd",
                                        d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                        "clip-rule": "evenodd"
                                      })
                                    ])),
                                    createTextVNode(" Set active ")
                                  ], 8, ["onClick"])
                                ])
                              ])) : createCommentVNode("", true)
                            ];
                          }
                        }),
                        _: 1
                      }, _parent3, _scopeId2));
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(inlineSuggestions).length > 0) {
                      _push3(`<div class="rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden" data-v-5548a2bb${_scopeId2}><div class="max-h-40 overflow-auto" data-v-5548a2bb${_scopeId2}><!--[-->`);
                      ssrRenderList(unref(inlineSuggestions), (it, index) => {
                        _push3(`<button class="${ssrRenderClass([index === unref(selectedIndex) ? "bg-gray-800/50" : "", "w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50"])}" data-v-5548a2bb${_scopeId2}><div class="font-medium" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(it.value)}</div><div class="text-xs text-gray-500" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(it.label)}</div></button>`);
                      });
                      _push3(`<!--]--></div></div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    if (unref(selectedVerb) && unref(selectedVerb).id === "delete" && unref(params).company && unref(companyDetails)[unref(params).company]) {
                      _push3(`<div class="mt-4 bg-red-900/20 border border-red-700/50 rounded-xl p-4 text-red-200 backdrop-blur-sm" data-v-5548a2bb${_scopeId2}><div class="flex items-center gap-2 mb-3" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg><div class="text-sm font-medium" data-v-5548a2bb${_scopeId2}>Confirm Deletion</div></div><div class="text-xs mb-3" data-v-5548a2bb${_scopeId2}>Type <strong class="text-red-100" data-v-5548a2bb${_scopeId2}>${ssrInterpolate(unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name)}</strong> to confirm deletion of this company with ${ssrInterpolate(unref(companyDetails)[unref(params).company].members_count)} members.</div><input${ssrRenderAttr("value", unref(deleteConfirmText))} class="w-full bg-red-900/30 border border-red-700/50 rounded-lg p-2.5 text-red-100 placeholder-red-300/70 focus:outline-none"${ssrRenderAttr("placeholder", unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name)} data-v-5548a2bb${_scopeId2}><div class="mt-3" data-v-5548a2bb${_scopeId2}><button type="button"${ssrIncludeBooleanAttr(unref(deleteConfirmText) !== (unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name)) ? " disabled" : ""} class="${ssrRenderClass([unref(deleteConfirmText) === (unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name) ? "border-red-500 bg-red-700/50 text-white hover:bg-red-600/50" : "border-red-800/50 bg-red-950/30 text-red-400 cursor-not-allowed", "px-4 py-2 rounded-lg border transition-all duration-300 flex items-center gap-1.5 text-xs"])}" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Delete company </button></div></div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    if (!unref(activeFlagId) && !unref(showUserPicker) && !unref(showCompanyPicker) && unref(currentChoices).length === 0) {
                      _push3(`<div class="text-center py-10" data-v-5548a2bb${_scopeId2}><div class="text-gray-500 text-sm mb-4 flex flex-col items-center" data-v-5548a2bb${_scopeId2}><svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mb-2 opacity-50" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId2}><path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" data-v-5548a2bb${_scopeId2}></path></svg> Click a parameter above to start entering values </div><div class="text-gray-600 text-xs" data-v-5548a2bb${_scopeId2}> Required parameters: ${ssrInterpolate(unref(selectedVerb)?.fields.filter((f2) => f2.required).length || 0)}/${ssrInterpolate(unref(selectedVerb)?.fields.length || 0)}</div></div>`);
                    } else {
                      _push3(`<!---->`);
                    }
                    _push3(`</div>`);
                  } else {
                    _push3(`<!---->`);
                  }
                  _push3(`</div><div class="px-4 py-3 border-t border-gray-700/30 bg-gradient-to-r from-gray-800 to-gray-900" data-v-5548a2bb${_scopeId2}><div class="flex justify-between items-center text-xs text-gray-500" data-v-5548a2bb${_scopeId2}><div class="flex gap-3 sm:gap-4 flex-wrap" data-v-5548a2bb${_scopeId2}><span class="flex items-center gap-1" data-v-5548a2bb${_scopeId2}><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" data-v-5548a2bb${_scopeId2}>↑↓</kbd> navigate</span><span class="flex items-center gap-1" data-v-5548a2bb${_scopeId2}><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" data-v-5548a2bb${_scopeId2}>↵</kbd> select</span><span class="flex items-center gap-1" data-v-5548a2bb${_scopeId2}><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" data-v-5548a2bb${_scopeId2}>⇥</kbd> complete</span><span class="hidden sm:flex items-center gap-1" data-v-5548a2bb${_scopeId2}><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" data-v-5548a2bb${_scopeId2}>⇧⇥</kbd> edit last</span><span class="flex items-center gap-1" data-v-5548a2bb${_scopeId2}><kbd class="px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" data-v-5548a2bb${_scopeId2}>⎋</kbd> back</span></div><div class="text-green-400 flex items-center gap-1.5" data-v-5548a2bb${_scopeId2}><span class="h-2 w-2 rounded-full bg-green-400" data-v-5548a2bb${_scopeId2}></span> ${ssrInterpolate(unref(executing) ? "EXECUTING..." : "READY")}</div></div></div></div>`);
                } else {
                  return [
                    createVNode("div", {
                      class: "flex flex-col h-96 sm:h-[500px]",
                      onKeydown: unref(handleKeydown)
                    }, [
                      createVNode("div", { class: "px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" }, [
                        createVNode("div", { class: "flex items-center justify-between" }, [
                          createVNode("div", { class: "flex items-center gap-3" }, [
                            createVNode("div", { class: "flex gap-1.5" }, [
                              createVNode("div", { class: "w-3 h-3 rounded-full bg-red-500 shadow-sm" }),
                              createVNode("div", { class: "w-3 h-3 rounded-full bg-yellow-500 shadow-sm" }),
                              createVNode("div", { class: "w-3 h-3 rounded-full bg-green-500 shadow-sm" })
                            ]),
                            createVNode("span", { class: "text-gray-400 text-xs hidden sm:inline tracking-wide" }, "accounting-cli v1.0")
                          ]),
                          createVNode("div", { class: "flex items-center gap-3" }, [
                            createVNode("button", {
                              type: "button",
                              class: "text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1",
                              onClick: unref(goBack)
                            }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3 w-3",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createTextVNode(" Back ")
                            ], 8, ["onClick"]),
                            createVNode("button", {
                              type: "button",
                              class: "text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1",
                              onClick: unref(goHome)
                            }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3 w-3",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", { d: "M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" })
                              ])),
                              createTextVNode(" Home ")
                            ], 8, ["onClick"]),
                            createVNode("div", { class: "text-gray-500 text-xs px-2 py-1 bg-gray-800/50 rounded-md border border-gray-700/50" }, toDisplayString(unref(statusText)), 1)
                          ])
                        ])
                      ]),
                      unref(step) === "fields" && unref(selectedVerb) ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "px-4 py-3 border-b border-gray-700/30 bg-gray-800/40"
                      }, [
                        createVNode("div", { class: "text-gray-500 text-xs mb-2 font-medium tracking-wide" }, "AVAILABLE PARAMETERS:"),
                        createVNode("div", { class: "flex flex-wrap gap-2" }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(unref(availableFlags), (flag) => {
                            return openBlock(), createBlock("button", {
                              type: "button",
                              key: flag.id,
                              onClick: ($event) => unref(selectFlag)(flag.id),
                              class: ["px-3 py-1.5 text-xs rounded-lg border backdrop-blur-sm", [
                                "border-gray-600/50 text-gray-300 bg-gray-800/40 hover:border-orange-500/70 hover:text-orange-300 hover:bg-orange-900/20",
                                ""
                              ]]
                            }, [
                              createTextVNode(toDisplayString(flag.placeholder) + " ", 1),
                              flag.required ? (openBlock(), createBlock("span", {
                                key: 0,
                                class: "ml-1 text-red-400"
                              }, "*")) : createCommentVNode("", true)
                            ], 8, ["onClick"]);
                          }), 128))
                        ]),
                        !unref(activeFlagId) && !unref(dashParameterMatch) ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "text-gray-600 text-xs mt-2 flex items-center gap-1"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "h-3 w-3",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", {
                              "fill-rule": "evenodd",
                              d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z",
                              "clip-rule": "evenodd"
                            })
                          ])),
                          createTextVNode(" Click a parameter above or type -paramName to start entering values ")
                        ])) : createCommentVNode("", true),
                        unref(dashParameterMatch) ? (openBlock(), createBlock("div", {
                          key: 1,
                          class: "text-orange-400 text-xs mt-2 flex items-center gap-1"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "h-3 w-3",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", {
                              "fill-rule": "evenodd",
                              d: "M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z",
                              "clip-rule": "evenodd"
                            })
                          ])),
                          createTextVNode(" Press Enter to select: " + toDisplayString(unref(dashParameterMatch).placeholder), 1)
                        ])) : createCommentVNode("", true)
                      ])) : createCommentVNode("", true),
                      createVNode("div", { class: "px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/50 to-gray-900/10" }, [
                        createVNode("div", { class: "flex items-center gap-2" }, [
                          createVNode("span", { class: "text-green-400 text-lg" }, "❯"),
                          createVNode("div", { class: "starship-bc" }, [
                            unref(selectedEntity) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                              createVNode("div", { class: "seg seg-entity seg-first" }, toDisplayString(unref(selectedEntity).label), 1),
                              unref(selectedVerb) ? (openBlock(), createBlock("div", {
                                key: 0,
                                class: "seg seg-verb seg-mid"
                              }, toDisplayString(unref(selectedVerb).label), 1)) : createCommentVNode("", true),
                              unref(step) === "fields" && unref(currentField) ? (openBlock(), createBlock("div", {
                                key: 1,
                                class: "seg seg-active seg-last"
                              }, [
                                createTextVNode(toDisplayString(unref(currentField).placeholder), 1),
                                unref(currentField).required ? (openBlock(), createBlock("span", {
                                  key: 0,
                                  class: "ml-0.5 text-red-300"
                                }, "*")) : createCommentVNode("", true)
                              ])) : createCommentVNode("", true)
                            ], 64)) : createCommentVNode("", true)
                          ]),
                          createVNode("div", { class: "flex-1 ml-3" }, [
                            createVNode("div", { class: "flex items-center gap-2 relative w-full" }, [
                              withDirectives(createVNode("input", {
                                ref_key: "inputEl",
                                ref: inputEl,
                                "onUpdate:modelValue": ($event) => isRef(q) ? q.value = $event : null,
                                placeholder: unref(step) === "entity" ? "Search entities..." : unref(step) === "verb" ? "Search actions..." : !unref(activeFlagId) ? "Select parameter or type -param..." : "Enter value...",
                                class: ["flex-1 bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus-visible:ring-0 appearance-none py-2 no-focus-ring rounded-lg px-3 border-0 focus:border-0", [
                                  unref(step) === "fields" && unref(currentField) ? "text-orange-300 placeholder-orange-300/50" : "",
                                  unref(dashParameterMatch) ? "text-yellow-300" : unref(step) !== "fields" ? "text-green-400 placeholder-gray-600" : ""
                                ]],
                                style: {},
                                disabled: unref(executing)
                              }, null, 10, ["onUpdate:modelValue", "placeholder", "disabled"]), [
                                [vModelText, unref(q)]
                              ]),
                              unref(step) === "fields" && unref(activeFlagId) && unref(q).trim() ? (openBlock(), createBlock("button", {
                                key: 0,
                                type: "button",
                                onClick: unref(completeCurrentFlag),
                                class: "px-3 py-1.5 bg-orange-700/50 text-orange-100 rounded-lg border border-orange-600/50 text-xs flex items-center gap-1 backdrop-blur-sm"
                              }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" Set ")
                              ], 8, ["onClick"])) : createCommentVNode("", true),
                              unref(step) === "fields" && !unref(activeFlagId) && unref(dashParameterMatch) ? (openBlock(), createBlock("button", {
                                key: 1,
                                type: "button",
                                onClick: unref(handleDashParameter),
                                class: "px-3 py-1.5 bg-yellow-700/50 text-yellow-100 rounded-lg border border-yellow-600/50 text-xs flex items-center gap-1 backdrop-blur-sm"
                              }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" Select ")
                              ], 8, ["onClick"])) : createCommentVNode("", true),
                              unref(step) === "fields" && unref(allRequiredFilled) && !unref(activeFlagId) ? (openBlock(), createBlock("button", {
                                key: 2,
                                type: "button",
                                onClick: unref(execute),
                                disabled: unref(executing),
                                class: "px-4 py-1.5 bg-green-700/50 text-green-100 rounded-lg border border-green-600/50 text-xs disabled:opacity-50 flex items-center gap-1 backdrop-blur-sm"
                              }, [
                                !unref(executing) ? (openBlock(), createBlock("svg", {
                                  key: 0,
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z",
                                    "clip-rule": "evenodd"
                                  })
                                ])) : (openBlock(), createBlock("svg", {
                                  key: 1,
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" " + toDisplayString(unref(executing) ? "Executing..." : "Execute"), 1)
                              ], 8, ["onClick", "disabled"])) : createCommentVNode("", true)
                            ])
                          ])
                        ])
                      ]),
                      unref(step) === "fields" && (unref(filledFlags).length > 0 || unref(animatingToCompleted)) ? (openBlock(), createBlock("div", {
                        key: 1,
                        class: "px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/20 to-gray-900/5"
                      }, [
                        createVNode("div", { class: "text-gray-500 text-xs mb-2 font-medium tracking-wide" }, "COMPLETED PARAMETERS:"),
                        createVNode("div", { class: "flex flex-wrap gap-2" }, [
                          (openBlock(true), createBlock(Fragment, null, renderList(unref(filledFlags), (flag) => {
                            return openBlock(), createBlock("button", {
                              type: "button",
                              key: flag.id,
                              onClick: ($event) => unref(editFilledFlag)(flag.id),
                              class: "px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 backdrop-blur-sm flex items-center gap-1"
                            }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createTextVNode(" " + toDisplayString(flag.placeholder) + '="' + toDisplayString(unref(params)[flag.id]) + '" ', 1)
                            ], 8, ["onClick"]);
                          }), 128)),
                          unref(animatingToCompleted) && unref(selectedVerb) ? (openBlock(), createBlock("div", {
                            key: `animating-${unref(animatingToCompleted)}`,
                            class: "px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 flex items-center gap-1 backdrop-blur-sm animate-slideToInput"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-3.5 w-3.5",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                "clip-rule": "evenodd"
                              })
                            ])),
                            createTextVNode(" " + toDisplayString(unref(selectedVerb).fields.find((f2) => f2.id === unref(animatingToCompleted))?.placeholder) + '="' + toDisplayString(unref(params)[unref(animatingToCompleted)]) + '" ', 1)
                          ])) : createCommentVNode("", true)
                        ])
                      ])) : createCommentVNode("", true),
                      createVNode("div", { class: "flex-1 overflow-auto p-3 bg-gradient-to-b from-gray-900/10 to-gray-900/5" }, [
                        unref(step) === "entity" ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "space-y-2"
                        }, [
                          createVNode("div", { class: "text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block" }, "Available entities"),
                          (openBlock(true), createBlock(Fragment, null, renderList(unref(entitySuggestions), (entity, index) => {
                            return openBlock(), createBlock("div", {
                              key: entity.id,
                              onClick: ($event) => unref(selectEntity)(entity),
                              class: ["px-4 py-3 rounded-xl cursor-pointer border", index === unref(selectedIndex) ? "bg-blue-900/30 text-blue-200 border-blue-700/50 scale-[1.02] shadow-lg shadow-blue-500/10" : "hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent"]
                            }, [
                              createVNode("div", { class: "flex items-center justify-between" }, [
                                createVNode("span", { class: "text-green-400 font-medium" }, toDisplayString(entity.label), 1),
                                createVNode("span", { class: "text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full" }, toDisplayString(entity.verbs.length) + " actions", 1)
                              ]),
                              createVNode("div", { class: "text-xs text-gray-500 mt-1" }, " aliases: " + toDisplayString(entity.aliases.join(", ")), 1)
                            ], 10, ["onClick"]);
                          }), 128))
                        ])) : unref(step) === "verb" ? (openBlock(), createBlock("div", {
                          key: 1,
                          class: "space-y-2"
                        }, [
                          createVNode("div", { class: "text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block" }, "Available actions"),
                          (openBlock(true), createBlock(Fragment, null, renderList(unref(verbSuggestions), (verb, index) => {
                            return openBlock(), createBlock("div", {
                              key: verb.id,
                              onClick: ($event) => unref(selectVerb)(verb),
                              class: ["px-4 py-3 rounded-xl cursor-pointer border", index === unref(selectedIndex) ? "bg-yellow-900/30 text-yellow-200 border-yellow-700/50 scale-[1.02] shadow-lg shadow-yellow-500/10" : "hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent"]
                            }, [
                              createVNode("div", { class: "flex items-center justify-between" }, [
                                createVNode("span", {
                                  class: "text-yellow-400 font-medium",
                                  innerHTML: highlight(verb.label, unref(q))
                                }, null, 8, ["innerHTML"]),
                                createVNode("span", { class: "text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full" }, toDisplayString(verb.fields.filter((f2) => f2.required).length) + "/" + toDisplayString(verb.fields.length) + " required ", 1)
                              ]),
                              createVNode("div", { class: "text-xs text-gray-500 mt-1" }, toDisplayString(verb.fields.map((f2) => f2.placeholder).join(" ")), 1)
                            ], 10, ["onClick"]);
                          }), 128))
                        ])) : unref(step) === "fields" ? (openBlock(), createBlock("div", {
                          key: 2,
                          class: "space-y-4"
                        }, [
                          unref(currentChoices).length > 0 ? (openBlock(), createBlock("div", { key: 0 }, [
                            createVNode("div", { class: "text-gray-500 text-xs px-2 py-1.5 mb-3 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-flex items-center gap-2" }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createTextVNode(" Select option ")
                            ]),
                            createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 gap-2" }, [
                              (openBlock(true), createBlock(Fragment, null, renderList(unref(currentChoices), (choice, index) => {
                                return openBlock(), createBlock("button", {
                                  key: choice,
                                  onClick: ($event) => unref(selectChoice)(choice),
                                  class: ["px-4 py-2.5 text-left rounded-xl border", index === unref(selectedIndex) ? "bg-orange-900/30 text-orange-200 border-orange-700/50 scale-[1.02] shadow-lg shadow-orange-500/10" : "bg-gray-800/30 hover:bg-gray-700/30 text-gray-300 hover:scale-[1.01] border-transparent"]
                                }, toDisplayString(choice), 11, ["onClick"]);
                              }), 128))
                            ])
                          ])) : createCommentVNode("", true),
                          unref(showGenericPanelPicker) ? (openBlock(), createBlock(_sfc_main$p, {
                            key: 1,
                            items: unref(panelItems),
                            "selected-index": unref(selectedIndex),
                            onSelect: (it) => unref(pickGeneric)(it.value)
                          }, {
                            header: withCtx(() => [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("span", null, "Suggestions")
                            ]),
                            preview: withCtx(({ item }) => [
                              createVNode("div", { class: "text-gray-300" }, toDisplayString(item.label), 1)
                            ]),
                            _: 1
                          }, 8, ["items", "selected-index", "onSelect"])) : createCommentVNode("", true),
                          unref(showUserPicker) ? (openBlock(), createBlock(_sfc_main$p, {
                            key: 2,
                            items: unref(panelItems),
                            "selected-index": unref(selectedIndex),
                            onSelect: (it) => unref(pickUserEmail)(it.value)
                          }, {
                            header: withCtx(() => [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("span", null, "Users"),
                              createVNode("div", { class: "flex gap-1 ml-auto" }, [
                                unref(isSuperAdmin) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => userSource.value = "all",
                                    class: [unref(userSource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "All", 10, ["onClick"]),
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => userSource.value = "company",
                                    class: [unref(userSource) === "company" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "Company", 10, ["onClick"])
                                ], 64)) : createCommentVNode("", true)
                              ])
                            ]),
                            _: 1
                          }, 8, ["items", "selected-index", "onSelect"])) : createCommentVNode("", true),
                          unref(showCompanyPicker) ? (openBlock(), createBlock(_sfc_main$p, {
                            key: 3,
                            items: unref(panelItems),
                            "selected-index": unref(selectedIndex),
                            onSelect: (it) => unref(pickCompanyName)(it.value)
                          }, {
                            header: withCtx(() => [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("span", null, "Companies"),
                              createVNode("div", { class: "flex gap-1 ml-auto" }, [
                                unref(isSuperAdmin) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                  createVNode("button", {
                                    onClick: ($event) => companySource.value = "all",
                                    class: [unref(companySource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "All", 10, ["onClick"]),
                                  createVNode("button", {
                                    onClick: ($event) => companySource.value = "me",
                                    class: [unref(companySource) === "me" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "Mine", 10, ["onClick"]),
                                  unref(params).email ? (openBlock(), createBlock("button", {
                                    key: 0,
                                    onClick: ($event) => companySource.value = "byUser",
                                    class: [unref(companySource) === "byUser" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                  }, "User", 10, ["onClick"])) : createCommentVNode("", true)
                                ], 64)) : createCommentVNode("", true)
                              ])
                            ]),
                            preview: withCtx(({ item }) => [
                              createVNode("div", { class: "font-medium text-gray-200 mb-2" }, "Selected Company"),
                              createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 gap-3" }, [
                                createVNode("div", null, [
                                  createVNode("div", { class: "text-gray-500" }, "Name:"),
                                  createVNode("div", { class: "text-gray-200" }, toDisplayString(item.label), 1)
                                ]),
                                unref(companyDetails)[item.meta?.id] ? (openBlock(), createBlock("div", { key: 0 }, [
                                  createVNode("div", { class: "text-gray-500" }, "Slug:"),
                                  createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].slug), 1)
                                ])) : createCommentVNode("", true)
                              ]),
                              unref(companyDetails)[item.meta?.id] ? (openBlock(), createBlock("div", {
                                key: 0,
                                class: "space-y-2 pt-2 border-t border-gray-800/30"
                              }, [
                                createVNode("div", { class: "grid grid-cols-2 gap-3" }, [
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Currency:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].base_currency), 1)
                                  ]),
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Language:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].language), 1)
                                  ])
                                ]),
                                createVNode("div", null, [
                                  createVNode("div", { class: "text-gray-500" }, "Members:"),
                                  createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].members_count), 1)
                                ]),
                                createVNode("div", null, [
                                  createVNode("div", { class: "text-gray-500" }, "Roles:"),
                                  createVNode("div", { class: "text-gray-300 text-xs mt-0.5 grid grid-cols-2 gap-1" }, [
                                    createVNode("span", null, "owner: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).owner || 0), 1),
                                    createVNode("span", null, "admin: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).admin || 0), 1),
                                    createVNode("span", null, "accountant: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).accountant || 0), 1),
                                    createVNode("span", null, "viewer: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).viewer || 0), 1)
                                  ])
                                ]),
                                unref(companyDetails)[item.meta?.id].owners && unref(companyDetails)[item.meta?.id].owners.length ? (openBlock(), createBlock("div", {
                                  key: 0,
                                  class: "text-gray-500"
                                }, [
                                  createTextVNode(" Owners: "),
                                  createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[item.meta?.id].owners.map((o2) => o2.name).join(", ")), 1)
                                ])) : createCommentVNode("", true),
                                unref(companyDetails)[item.meta?.id].last_activity ? (openBlock(), createBlock("div", {
                                  key: 1,
                                  class: "text-gray-500"
                                }, [
                                  createTextVNode(" Last activity: "),
                                  createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[item.meta?.id].last_activity.action), 1),
                                  createVNode("span", { class: "text-gray-400 block text-xs mt-0.5" }, "@ " + toDisplayString(unref(companyDetails)[item.meta?.id].last_activity.created_at), 1)
                                ])) : createCommentVNode("", true),
                                createVNode("div", { class: "flex flex-wrap gap-2 pt-2 border-t border-gray-800/30" }, [
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => unref(loadCompanyMembers)(item.meta?.id),
                                    class: "px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1"
                                  }, [
                                    (openBlock(), createBlock("svg", {
                                      xmlns: "http://www.w3.org/2000/svg",
                                      class: "h-3.5 w-3.5",
                                      viewBox: "0 0 20 20",
                                      fill: "currentColor"
                                    }, [
                                      createVNode("path", { d: "M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" })
                                    ])),
                                    createTextVNode(" View members ")
                                  ], 8, ["onClick"]),
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => unref(quickAssignToCompany)(item.meta?.id),
                                    class: "px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1"
                                  }, [
                                    (openBlock(), createBlock("svg", {
                                      xmlns: "http://www.w3.org/2000/svg",
                                      class: "h-3.5 w-3.5",
                                      viewBox: "0 0 20 20",
                                      fill: "currentColor"
                                    }, [
                                      createVNode("path", { d: "M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" })
                                    ])),
                                    createTextVNode(" Assign user ")
                                  ], 8, ["onClick"]),
                                  createVNode("button", {
                                    type: "button",
                                    onClick: ($event) => unref(setActiveCompany)(item.meta?.id),
                                    class: "px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1"
                                  }, [
                                    (openBlock(), createBlock("svg", {
                                      xmlns: "http://www.w3.org/2000/svg",
                                      class: "h-3.5 w-3.5",
                                      viewBox: "0 0 20 20",
                                      fill: "currentColor"
                                    }, [
                                      createVNode("path", {
                                        "fill-rule": "evenodd",
                                        d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                        "clip-rule": "evenodd"
                                      })
                                    ])),
                                    createTextVNode(" Set active ")
                                  ], 8, ["onClick"])
                                ])
                              ])) : createCommentVNode("", true)
                            ]),
                            _: 1
                          }, 8, ["items", "selected-index", "onSelect"])) : createCommentVNode("", true),
                          unref(inlineSuggestions).length > 0 ? (openBlock(), createBlock("div", {
                            key: 4,
                            class: "rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden"
                          }, [
                            createVNode("div", { class: "max-h-40 overflow-auto" }, [
                              (openBlock(true), createBlock(Fragment, null, renderList(unref(inlineSuggestions), (it, index) => {
                                return openBlock(), createBlock("button", {
                                  key: it.value,
                                  onClick: ($event) => unref(selectChoice)(it.value),
                                  class: ["w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50", index === unref(selectedIndex) ? "bg-gray-800/50" : ""]
                                }, [
                                  createVNode("div", { class: "font-medium" }, toDisplayString(it.value), 1),
                                  createVNode("div", { class: "text-xs text-gray-500" }, toDisplayString(it.label), 1)
                                ], 10, ["onClick"]);
                              }), 128))
                            ])
                          ])) : createCommentVNode("", true),
                          unref(selectedVerb) && unref(selectedVerb).id === "delete" && unref(params).company && unref(companyDetails)[unref(params).company] ? (openBlock(), createBlock("div", {
                            key: 5,
                            class: "mt-4 bg-red-900/20 border border-red-700/50 rounded-xl p-4 text-red-200 backdrop-blur-sm"
                          }, [
                            createVNode("div", { class: "flex items-center gap-2 mb-3" }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-4 w-4",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createVNode("div", { class: "text-sm font-medium" }, "Confirm Deletion")
                            ]),
                            createVNode("div", { class: "text-xs mb-3" }, [
                              createTextVNode("Type "),
                              createVNode("strong", { class: "text-red-100" }, toDisplayString(unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name), 1),
                              createTextVNode(" to confirm deletion of this company with " + toDisplayString(unref(companyDetails)[unref(params).company].members_count) + " members.", 1)
                            ]),
                            withDirectives(createVNode("input", {
                              "onUpdate:modelValue": ($event) => isRef(deleteConfirmText) ? deleteConfirmText.value = $event : null,
                              class: "w-full bg-red-900/30 border border-red-700/50 rounded-lg p-2.5 text-red-100 placeholder-red-300/70 focus:outline-none",
                              placeholder: unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name
                            }, null, 8, ["onUpdate:modelValue", "placeholder"]), [
                              [vModelText, unref(deleteConfirmText)]
                            ]),
                            createVNode("div", { class: "mt-3" }, [
                              createVNode("button", {
                                type: "button",
                                disabled: unref(deleteConfirmText) !== (unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name),
                                onClick: unref(execute),
                                class: ["px-4 py-2 rounded-lg border transition-all duration-300 flex items-center gap-1.5 text-xs", unref(deleteConfirmText) === (unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name) ? "border-red-500 bg-red-700/50 text-white hover:bg-red-600/50" : "border-red-800/50 bg-red-950/30 text-red-400 cursor-not-allowed"]
                              }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" Delete company ")
                              ], 10, ["disabled", "onClick"])
                            ])
                          ])) : createCommentVNode("", true),
                          !unref(activeFlagId) && !unref(showUserPicker) && !unref(showCompanyPicker) && unref(currentChoices).length === 0 ? (openBlock(), createBlock("div", {
                            key: 6,
                            class: "text-center py-10"
                          }, [
                            createVNode("div", { class: "text-gray-500 text-sm mb-4 flex flex-col items-center" }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-8 w-8 mb-2 opacity-50",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createTextVNode(" Click a parameter above to start entering values ")
                            ]),
                            createVNode("div", { class: "text-gray-600 text-xs" }, " Required parameters: " + toDisplayString(unref(selectedVerb)?.fields.filter((f2) => f2.required).length || 0) + "/" + toDisplayString(unref(selectedVerb)?.fields.length || 0), 1)
                          ])) : createCommentVNode("", true)
                        ])) : createCommentVNode("", true)
                      ]),
                      createVNode("div", { class: "px-4 py-3 border-t border-gray-700/30 bg-gradient-to-r from-gray-800 to-gray-900" }, [
                        createVNode("div", { class: "flex justify-between items-center text-xs text-gray-500" }, [
                          createVNode("div", { class: "flex gap-3 sm:gap-4 flex-wrap" }, [
                            createVNode("span", { class: "flex items-center gap-1" }, [
                              createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "↑↓"),
                              createTextVNode(" navigate")
                            ]),
                            createVNode("span", { class: "flex items-center gap-1" }, [
                              createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "↵"),
                              createTextVNode(" select")
                            ]),
                            createVNode("span", { class: "flex items-center gap-1" }, [
                              createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "⇥"),
                              createTextVNode(" complete")
                            ]),
                            createVNode("span", { class: "hidden sm:flex items-center gap-1" }, [
                              createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "⇧⇥"),
                              createTextVNode(" edit last")
                            ]),
                            createVNode("span", { class: "flex items-center gap-1" }, [
                              createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "⎋"),
                              createTextVNode(" back")
                            ])
                          ]),
                          createVNode("div", { class: "text-green-400 flex items-center gap-1.5" }, [
                            createVNode("span", { class: "h-2 w-2 rounded-full bg-green-400" }),
                            createTextVNode(" " + toDisplayString(unref(executing) ? "EXECUTING..." : "READY"), 1)
                          ])
                        ])
                      ])
                    ], 40, ["onKeydown"])
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            if ((unref(showCompanyPicker) || unref(showUserPicker)) && unref(highlightedItem)) {
              _push2(`<div class="w-full lg:w-80 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden" data-v-5548a2bb${_scopeId}><div class="px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" data-v-5548a2bb${_scopeId}><div class="flex items-center gap-2" data-v-5548a2bb${_scopeId}><span class="text-green-400" data-v-5548a2bb${_scopeId}>●</span><span class="text-gray-400 text-xs tracking-wide" data-v-5548a2bb${_scopeId}>DETAILS</span></div></div>`);
              if (unref(showCompanyPicker)) {
                _push2(`<div class="p-4 space-y-3 max-h-96 overflow-auto" data-v-5548a2bb${_scopeId}><div class="text-gray-200 text-sm font-medium" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(highlightedItem).label)}</div>`);
                if (unref(companyDetails)[unref(highlightedItem).meta?.id]) {
                  _push2(`<div class="grid grid-cols-2 gap-3 text-xs" data-v-5548a2bb${_scopeId}><div data-v-5548a2bb${_scopeId}><div class="text-gray-500" data-v-5548a2bb${_scopeId}>Slug</div><div class="text-gray-200" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].slug)}</div></div><div data-v-5548a2bb${_scopeId}><div class="text-gray-500" data-v-5548a2bb${_scopeId}>Currency</div><div class="text-gray-200" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].base_currency)}</div></div><div data-v-5548a2bb${_scopeId}><div class="text-gray-500" data-v-5548a2bb${_scopeId}>Language</div><div class="text-gray-200" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].language)}</div></div><div data-v-5548a2bb${_scopeId}><div class="text-gray-500" data-v-5548a2bb${_scopeId}>Members</div><div class="text-gray-200" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].members_count)}</div></div></div>`);
                } else {
                  _push2(`<!---->`);
                }
                if (unref(companyDetails)[unref(highlightedItem).meta?.id]) {
                  _push2(`<div class="text-xs text-gray-300" data-v-5548a2bb${_scopeId}><div class="text-gray-500" data-v-5548a2bb${_scopeId}>Roles</div><div class="grid grid-cols-2 gap-1" data-v-5548a2bb${_scopeId}><span data-v-5548a2bb${_scopeId}>owner: ${ssrInterpolate((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).owner || 0)}</span><span data-v-5548a2bb${_scopeId}>admin: ${ssrInterpolate((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).admin || 0)}</span><span data-v-5548a2bb${_scopeId}>accountant: ${ssrInterpolate((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).accountant || 0)}</span><span data-v-5548a2bb${_scopeId}>viewer: ${ssrInterpolate((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).viewer || 0)}</span></div></div>`);
                } else {
                  _push2(`<!---->`);
                }
                if (unref(companyDetails)[unref(highlightedItem).meta?.id]?.owners?.length) {
                  _push2(`<div class="text-xs text-gray-400" data-v-5548a2bb${_scopeId}> Owners: <span class="text-gray-300" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].owners.map((o2) => o2.name).join(", "))}</span></div>`);
                } else {
                  _push2(`<!---->`);
                }
                if (unref(companyDetails)[unref(highlightedItem).meta?.id]?.last_activity) {
                  _push2(`<div class="text-xs text-gray-400" data-v-5548a2bb${_scopeId}> Last: <span class="text-gray-300" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].last_activity.action)}</span><span class="text-gray-500" data-v-5548a2bb${_scopeId}>@ ${ssrInterpolate(unref(companyDetails)[unref(highlightedItem).meta?.id].last_activity.created_at)}</span></div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`<div class="flex flex-wrap gap-2 pt-2 border-t border-gray-800/30" data-v-5548a2bb${_scopeId}><button type="button" class="px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" data-v-5548a2bb${_scopeId}></path></svg> View members </button><button type="button" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" data-v-5548a2bb${_scopeId}></path></svg> Assign user </button><button type="button" class="px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId}></path></svg> Set active </button></div>`);
                if (unref(companyMembersLoading)[unref(highlightedItem).meta?.id]) {
                  _push2(`<div class="text-gray-500 text-xs" data-v-5548a2bb${_scopeId}>Loading members…</div>`);
                } else if (unref(companyMembers)[unref(highlightedItem).meta?.id] && unref(companyMembers)[unref(highlightedItem).meta?.id].length) {
                  _push2(`<div class="mt-2 max-h-32 overflow-auto border-t border-gray-800/30 pt-2" data-v-5548a2bb${_scopeId}><!--[-->`);
                  ssrRenderList(unref(companyMembers)[unref(highlightedItem).meta?.id], (m2) => {
                    _push2(`<div class="py-1.5 text-gray-300 text-xs border-b border-gray-800/30 last:border-b-0" data-v-5548a2bb${_scopeId}><div class="font-medium" data-v-5548a2bb${_scopeId}>${ssrInterpolate(m2.name)}</div><div class="text-gray-500" data-v-5548a2bb${_scopeId}>${ssrInterpolate(m2.email)} — <span class="text-gray-400" data-v-5548a2bb${_scopeId}>${ssrInterpolate(m2.role)}</span></div></div>`);
                  });
                  _push2(`<!--]--></div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
              } else if (unref(showUserPicker)) {
                _push2(`<div class="p-4 space-y-3 max-h-96 overflow-auto" data-v-5548a2bb${_scopeId}><div class="text-gray-200 text-sm font-medium" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(highlightedItem).meta?.name)}</div><div class="grid grid-cols-2 gap-3 text-xs" data-v-5548a2bb${_scopeId}><div data-v-5548a2bb${_scopeId}><div class="text-gray-500" data-v-5548a2bb${_scopeId}>Email</div><div class="text-gray-200" data-v-5548a2bb${_scopeId}>${ssrInterpolate(unref(highlightedItem).meta?.email)}</div></div></div>`);
                if (unref(userDetails)[unref(highlightedItem).meta?.id]) {
                  _push2(`<div class="mt-2" data-v-5548a2bb${_scopeId}><div class="text-gray-500 font-medium mb-1 text-xs" data-v-5548a2bb${_scopeId}>Memberships</div><!--[-->`);
                  ssrRenderList(unref(userDetails)[unref(highlightedItem).meta?.id].memberships, (m2) => {
                    _push2(`<div class="text-gray-300 text-xs py-1.5 border-t border-gray-800/30 flex justify-between items-center" data-v-5548a2bb${_scopeId}><div data-v-5548a2bb${_scopeId}><span class="font-medium" data-v-5548a2bb${_scopeId}>${ssrInterpolate(m2.name)}</span><span class="text-gray-500 ml-2" data-v-5548a2bb${_scopeId}>— ${ssrInterpolate(m2.role)}</span></div><button type="button" class="text-red-300 hover:text-red-200 transition-colors px-2 py-0.5 rounded border border-red-800/30 hover:border-red-600/50" data-v-5548a2bb${_scopeId}>Unassign</button></div>`);
                  });
                  _push2(`<!--]--><div class="flex gap-2 mt-3" data-v-5548a2bb${_scopeId}><button type="button" class="px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 transition-all duration-200 text-xs flex items-center gap-1" data-v-5548a2bb${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" data-v-5548a2bb${_scopeId}></path></svg> Assign to company… </button></div></div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
              } else {
                _push2(`<!---->`);
              }
              _push2(`</div>`);
            } else {
              _push2(`<!---->`);
            }
            if (unref(showResults) && unref(results).length > 0) {
              _push2(`<div class="${ssrRenderClass([unref(showResults) ? "opacity-100 translate-x-0" : "opacity-0 translate-x-4", "w-full lg:w-80 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden"])}" data-v-5548a2bb${_scopeId}><div class="px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" data-v-5548a2bb${_scopeId}><div class="flex items-center gap-2" data-v-5548a2bb${_scopeId}><span class="text-green-400" data-v-5548a2bb${_scopeId}>●</span><span class="text-gray-400 text-xs tracking-wide" data-v-5548a2bb${_scopeId}>EXECUTION LOG</span><button class="ml-auto text-gray-500 hover:text-gray-300" data-v-5548a2bb${_scopeId}><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" data-v-5548a2bb${_scopeId}></path></svg></button></div></div><div class="p-4 space-y-3 max-h-96 overflow-auto" data-v-5548a2bb${_scopeId}><!--[-->`);
              ssrRenderList(unref(results), (result, index) => {
                _push2(`<div class="${ssrRenderClass([result.success ? "border-green-700/30" : "border-red-700/30", "bg-gray-800/30 p-3 rounded-xl border backdrop-blur-sm"])}" data-v-5548a2bb${_scopeId}><div class="flex items-center gap-2 mb-2" data-v-5548a2bb${_scopeId}><span class="${ssrRenderClass([result.success ? "text-green-400" : "text-red-400", "flex items-center gap-1.5 text-xs"])}" data-v-5548a2bb${_scopeId}>`);
                if (result.success) {
                  _push2(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" data-v-5548a2bb${_scopeId}></path></svg>`);
                } else {
                  _push2(`<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" data-v-5548a2bb${_scopeId}><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" data-v-5548a2bb${_scopeId}></path></svg>`);
                }
                _push2(` ${ssrInterpolate(result.action)}</span></div><div class="${ssrRenderClass([result.success ? "text-green-200" : "text-red-200", "text-xs mb-2"])}" data-v-5548a2bb${_scopeId}>${ssrInterpolate(result.message)}</div><div class="text-gray-500 text-xs" data-v-5548a2bb${_scopeId}>${ssrInterpolate(new Date(result.timestamp).toLocaleTimeString())}</div></div>`);
              });
              _push2(`<!--]--></div></div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div></div>`);
          } else {
            return [
              createVNode("div", {
                class: "fixed inset-0 bg-black/70 backdrop-blur-sm",
                "aria-hidden": "true"
              }),
              createVNode("div", { class: "fixed inset-0 flex items-start justify-center pt-4 sm:pt-20 px-4" }, [
                createVNode("div", {
                  class: ["w-full max-w-4xl flex flex-col lg:flex-row gap-4", unref(showResults) ? "lg:max-w-5xl" : ""]
                }, [
                  createVNode(unref(DialogPanel), {
                    class: ["flex-1 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden", unref(open) ? "scale-100 opacity-100" : "scale-105 opacity-0"],
                    onKeydown: (e2) => {
                      if (e2.key === "Escape") {
                        e2.preventDefault();
                        e2.stopPropagation();
                        unref(goBack)();
                      }
                    }
                  }, {
                    default: withCtx(() => [
                      createVNode("div", {
                        class: "flex flex-col h-96 sm:h-[500px]",
                        onKeydown: unref(handleKeydown)
                      }, [
                        createVNode("div", { class: "px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" }, [
                          createVNode("div", { class: "flex items-center justify-between" }, [
                            createVNode("div", { class: "flex items-center gap-3" }, [
                              createVNode("div", { class: "flex gap-1.5" }, [
                                createVNode("div", { class: "w-3 h-3 rounded-full bg-red-500 shadow-sm" }),
                                createVNode("div", { class: "w-3 h-3 rounded-full bg-yellow-500 shadow-sm" }),
                                createVNode("div", { class: "w-3 h-3 rounded-full bg-green-500 shadow-sm" })
                              ]),
                              createVNode("span", { class: "text-gray-400 text-xs hidden sm:inline tracking-wide" }, "accounting-cli v1.0")
                            ]),
                            createVNode("div", { class: "flex items-center gap-3" }, [
                              createVNode("button", {
                                type: "button",
                                class: "text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1",
                                onClick: unref(goBack)
                              }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3 w-3",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" Back ")
                              ], 8, ["onClick"]),
                              createVNode("button", {
                                type: "button",
                                class: "text-gray-400 hover:text-gray-200 text-xs flex items-center gap-1",
                                onClick: unref(goHome)
                              }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3 w-3",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", { d: "M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" })
                                ])),
                                createTextVNode(" Home ")
                              ], 8, ["onClick"]),
                              createVNode("div", { class: "text-gray-500 text-xs px-2 py-1 bg-gray-800/50 rounded-md border border-gray-700/50" }, toDisplayString(unref(statusText)), 1)
                            ])
                          ])
                        ]),
                        unref(step) === "fields" && unref(selectedVerb) ? (openBlock(), createBlock("div", {
                          key: 0,
                          class: "px-4 py-3 border-b border-gray-700/30 bg-gray-800/40"
                        }, [
                          createVNode("div", { class: "text-gray-500 text-xs mb-2 font-medium tracking-wide" }, "AVAILABLE PARAMETERS:"),
                          createVNode("div", { class: "flex flex-wrap gap-2" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(unref(availableFlags), (flag) => {
                              return openBlock(), createBlock("button", {
                                type: "button",
                                key: flag.id,
                                onClick: ($event) => unref(selectFlag)(flag.id),
                                class: ["px-3 py-1.5 text-xs rounded-lg border backdrop-blur-sm", [
                                  "border-gray-600/50 text-gray-300 bg-gray-800/40 hover:border-orange-500/70 hover:text-orange-300 hover:bg-orange-900/20",
                                  ""
                                ]]
                              }, [
                                createTextVNode(toDisplayString(flag.placeholder) + " ", 1),
                                flag.required ? (openBlock(), createBlock("span", {
                                  key: 0,
                                  class: "ml-1 text-red-400"
                                }, "*")) : createCommentVNode("", true)
                              ], 8, ["onClick"]);
                            }), 128))
                          ]),
                          !unref(activeFlagId) && !unref(dashParameterMatch) ? (openBlock(), createBlock("div", {
                            key: 0,
                            class: "text-gray-600 text-xs mt-2 flex items-center gap-1"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-3 w-3",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z",
                                "clip-rule": "evenodd"
                              })
                            ])),
                            createTextVNode(" Click a parameter above or type -paramName to start entering values ")
                          ])) : createCommentVNode("", true),
                          unref(dashParameterMatch) ? (openBlock(), createBlock("div", {
                            key: 1,
                            class: "text-orange-400 text-xs mt-2 flex items-center gap-1"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-3 w-3",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", {
                                "fill-rule": "evenodd",
                                d: "M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z",
                                "clip-rule": "evenodd"
                              })
                            ])),
                            createTextVNode(" Press Enter to select: " + toDisplayString(unref(dashParameterMatch).placeholder), 1)
                          ])) : createCommentVNode("", true)
                        ])) : createCommentVNode("", true),
                        createVNode("div", { class: "px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/50 to-gray-900/10" }, [
                          createVNode("div", { class: "flex items-center gap-2" }, [
                            createVNode("span", { class: "text-green-400 text-lg" }, "❯"),
                            createVNode("div", { class: "starship-bc" }, [
                              unref(selectedEntity) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                createVNode("div", { class: "seg seg-entity seg-first" }, toDisplayString(unref(selectedEntity).label), 1),
                                unref(selectedVerb) ? (openBlock(), createBlock("div", {
                                  key: 0,
                                  class: "seg seg-verb seg-mid"
                                }, toDisplayString(unref(selectedVerb).label), 1)) : createCommentVNode("", true),
                                unref(step) === "fields" && unref(currentField) ? (openBlock(), createBlock("div", {
                                  key: 1,
                                  class: "seg seg-active seg-last"
                                }, [
                                  createTextVNode(toDisplayString(unref(currentField).placeholder), 1),
                                  unref(currentField).required ? (openBlock(), createBlock("span", {
                                    key: 0,
                                    class: "ml-0.5 text-red-300"
                                  }, "*")) : createCommentVNode("", true)
                                ])) : createCommentVNode("", true)
                              ], 64)) : createCommentVNode("", true)
                            ]),
                            createVNode("div", { class: "flex-1 ml-3" }, [
                              createVNode("div", { class: "flex items-center gap-2 relative w-full" }, [
                                withDirectives(createVNode("input", {
                                  ref_key: "inputEl",
                                  ref: inputEl,
                                  "onUpdate:modelValue": ($event) => isRef(q) ? q.value = $event : null,
                                  placeholder: unref(step) === "entity" ? "Search entities..." : unref(step) === "verb" ? "Search actions..." : !unref(activeFlagId) ? "Select parameter or type -param..." : "Enter value...",
                                  class: ["flex-1 bg-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus-visible:ring-0 appearance-none py-2 no-focus-ring rounded-lg px-3 border-0 focus:border-0", [
                                    unref(step) === "fields" && unref(currentField) ? "text-orange-300 placeholder-orange-300/50" : "",
                                    unref(dashParameterMatch) ? "text-yellow-300" : unref(step) !== "fields" ? "text-green-400 placeholder-gray-600" : ""
                                  ]],
                                  style: {},
                                  disabled: unref(executing)
                                }, null, 10, ["onUpdate:modelValue", "placeholder", "disabled"]), [
                                  [vModelText, unref(q)]
                                ]),
                                unref(step) === "fields" && unref(activeFlagId) && unref(q).trim() ? (openBlock(), createBlock("button", {
                                  key: 0,
                                  type: "button",
                                  onClick: unref(completeCurrentFlag),
                                  class: "px-3 py-1.5 bg-orange-700/50 text-orange-100 rounded-lg border border-orange-600/50 text-xs flex items-center gap-1 backdrop-blur-sm"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    xmlns: "http://www.w3.org/2000/svg",
                                    class: "h-3.5 w-3.5",
                                    viewBox: "0 0 20 20",
                                    fill: "currentColor"
                                  }, [
                                    createVNode("path", {
                                      "fill-rule": "evenodd",
                                      d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z",
                                      "clip-rule": "evenodd"
                                    })
                                  ])),
                                  createTextVNode(" Set ")
                                ], 8, ["onClick"])) : createCommentVNode("", true),
                                unref(step) === "fields" && !unref(activeFlagId) && unref(dashParameterMatch) ? (openBlock(), createBlock("button", {
                                  key: 1,
                                  type: "button",
                                  onClick: unref(handleDashParameter),
                                  class: "px-3 py-1.5 bg-yellow-700/50 text-yellow-100 rounded-lg border border-yellow-600/50 text-xs flex items-center gap-1 backdrop-blur-sm"
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    xmlns: "http://www.w3.org/2000/svg",
                                    class: "h-3.5 w-3.5",
                                    viewBox: "0 0 20 20",
                                    fill: "currentColor"
                                  }, [
                                    createVNode("path", {
                                      "fill-rule": "evenodd",
                                      d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                      "clip-rule": "evenodd"
                                    })
                                  ])),
                                  createTextVNode(" Select ")
                                ], 8, ["onClick"])) : createCommentVNode("", true),
                                unref(step) === "fields" && unref(allRequiredFilled) && !unref(activeFlagId) ? (openBlock(), createBlock("button", {
                                  key: 2,
                                  type: "button",
                                  onClick: unref(execute),
                                  disabled: unref(executing),
                                  class: "px-4 py-1.5 bg-green-700/50 text-green-100 rounded-lg border border-green-600/50 text-xs disabled:opacity-50 flex items-center gap-1 backdrop-blur-sm"
                                }, [
                                  !unref(executing) ? (openBlock(), createBlock("svg", {
                                    key: 0,
                                    xmlns: "http://www.w3.org/2000/svg",
                                    class: "h-3.5 w-3.5",
                                    viewBox: "0 0 20 20",
                                    fill: "currentColor"
                                  }, [
                                    createVNode("path", {
                                      "fill-rule": "evenodd",
                                      d: "M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z",
                                      "clip-rule": "evenodd"
                                    })
                                  ])) : (openBlock(), createBlock("svg", {
                                    key: 1,
                                    xmlns: "http://www.w3.org/2000/svg",
                                    class: "h-3.5 w-3.5",
                                    viewBox: "0 0 20 20",
                                    fill: "currentColor"
                                  }, [
                                    createVNode("path", {
                                      "fill-rule": "evenodd",
                                      d: "M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z",
                                      "clip-rule": "evenodd"
                                    })
                                  ])),
                                  createTextVNode(" " + toDisplayString(unref(executing) ? "Executing..." : "Execute"), 1)
                                ], 8, ["onClick", "disabled"])) : createCommentVNode("", true)
                              ])
                            ])
                          ])
                        ]),
                        unref(step) === "fields" && (unref(filledFlags).length > 0 || unref(animatingToCompleted)) ? (openBlock(), createBlock("div", {
                          key: 1,
                          class: "px-4 py-3 border-b border-gray-700/30 bg-gradient-to-b from-gray-900/20 to-gray-900/5"
                        }, [
                          createVNode("div", { class: "text-gray-500 text-xs mb-2 font-medium tracking-wide" }, "COMPLETED PARAMETERS:"),
                          createVNode("div", { class: "flex flex-wrap gap-2" }, [
                            (openBlock(true), createBlock(Fragment, null, renderList(unref(filledFlags), (flag) => {
                              return openBlock(), createBlock("button", {
                                type: "button",
                                key: flag.id,
                                onClick: ($event) => unref(editFilledFlag)(flag.id),
                                class: "px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 backdrop-blur-sm flex items-center gap-1"
                              }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" " + toDisplayString(flag.placeholder) + '="' + toDisplayString(unref(params)[flag.id]) + '" ', 1)
                              ], 8, ["onClick"]);
                            }), 128)),
                            unref(animatingToCompleted) && unref(selectedVerb) ? (openBlock(), createBlock("div", {
                              key: `animating-${unref(animatingToCompleted)}`,
                              class: "px-3 py-1.5 text-xs rounded-lg border border-green-700/50 bg-green-900/20 text-green-200 flex items-center gap-1 backdrop-blur-sm animate-slideToInput"
                            }, [
                              (openBlock(), createBlock("svg", {
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createTextVNode(" " + toDisplayString(unref(selectedVerb).fields.find((f2) => f2.id === unref(animatingToCompleted))?.placeholder) + '="' + toDisplayString(unref(params)[unref(animatingToCompleted)]) + '" ', 1)
                            ])) : createCommentVNode("", true)
                          ])
                        ])) : createCommentVNode("", true),
                        createVNode("div", { class: "flex-1 overflow-auto p-3 bg-gradient-to-b from-gray-900/10 to-gray-900/5" }, [
                          unref(step) === "entity" ? (openBlock(), createBlock("div", {
                            key: 0,
                            class: "space-y-2"
                          }, [
                            createVNode("div", { class: "text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block" }, "Available entities"),
                            (openBlock(true), createBlock(Fragment, null, renderList(unref(entitySuggestions), (entity, index) => {
                              return openBlock(), createBlock("div", {
                                key: entity.id,
                                onClick: ($event) => unref(selectEntity)(entity),
                                class: ["px-4 py-3 rounded-xl cursor-pointer border", index === unref(selectedIndex) ? "bg-blue-900/30 text-blue-200 border-blue-700/50 scale-[1.02] shadow-lg shadow-blue-500/10" : "hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent"]
                              }, [
                                createVNode("div", { class: "flex items-center justify-between" }, [
                                  createVNode("span", { class: "text-green-400 font-medium" }, toDisplayString(entity.label), 1),
                                  createVNode("span", { class: "text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full" }, toDisplayString(entity.verbs.length) + " actions", 1)
                                ]),
                                createVNode("div", { class: "text-xs text-gray-500 mt-1" }, " aliases: " + toDisplayString(entity.aliases.join(", ")), 1)
                              ], 10, ["onClick"]);
                            }), 128))
                          ])) : unref(step) === "verb" ? (openBlock(), createBlock("div", {
                            key: 1,
                            class: "space-y-2"
                          }, [
                            createVNode("div", { class: "text-gray-500 text-xs px-2 py-1.5 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-block" }, "Available actions"),
                            (openBlock(true), createBlock(Fragment, null, renderList(unref(verbSuggestions), (verb, index) => {
                              return openBlock(), createBlock("div", {
                                key: verb.id,
                                onClick: ($event) => unref(selectVerb)(verb),
                                class: ["px-4 py-3 rounded-xl cursor-pointer border", index === unref(selectedIndex) ? "bg-yellow-900/30 text-yellow-200 border-yellow-700/50 scale-[1.02] shadow-lg shadow-yellow-500/10" : "hover:bg-gray-800/30 text-gray-300 hover:scale-[1.01] border-transparent"]
                              }, [
                                createVNode("div", { class: "flex items-center justify-between" }, [
                                  createVNode("span", {
                                    class: "text-yellow-400 font-medium",
                                    innerHTML: highlight(verb.label, unref(q))
                                  }, null, 8, ["innerHTML"]),
                                  createVNode("span", { class: "text-gray-500 text-xs bg-gray-800/50 px-2 py-1 rounded-full" }, toDisplayString(verb.fields.filter((f2) => f2.required).length) + "/" + toDisplayString(verb.fields.length) + " required ", 1)
                                ]),
                                createVNode("div", { class: "text-xs text-gray-500 mt-1" }, toDisplayString(verb.fields.map((f2) => f2.placeholder).join(" ")), 1)
                              ], 10, ["onClick"]);
                            }), 128))
                          ])) : unref(step) === "fields" ? (openBlock(), createBlock("div", {
                            key: 2,
                            class: "space-y-4"
                          }, [
                            unref(currentChoices).length > 0 ? (openBlock(), createBlock("div", { key: 0 }, [
                              createVNode("div", { class: "text-gray-500 text-xs px-2 py-1.5 mb-3 bg-gray-800/30 rounded-lg backdrop-blur-sm inline-flex items-center gap-2" }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" Select option ")
                              ]),
                              createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 gap-2" }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(unref(currentChoices), (choice, index) => {
                                  return openBlock(), createBlock("button", {
                                    key: choice,
                                    onClick: ($event) => unref(selectChoice)(choice),
                                    class: ["px-4 py-2.5 text-left rounded-xl border", index === unref(selectedIndex) ? "bg-orange-900/30 text-orange-200 border-orange-700/50 scale-[1.02] shadow-lg shadow-orange-500/10" : "bg-gray-800/30 hover:bg-gray-700/30 text-gray-300 hover:scale-[1.01] border-transparent"]
                                  }, toDisplayString(choice), 11, ["onClick"]);
                                }), 128))
                              ])
                            ])) : createCommentVNode("", true),
                            unref(showGenericPanelPicker) ? (openBlock(), createBlock(_sfc_main$p, {
                              key: 1,
                              items: unref(panelItems),
                              "selected-index": unref(selectedIndex),
                              onSelect: (it) => unref(pickGeneric)(it.value)
                            }, {
                              header: withCtx(() => [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createVNode("span", null, "Suggestions")
                              ]),
                              preview: withCtx(({ item }) => [
                                createVNode("div", { class: "text-gray-300" }, toDisplayString(item.label), 1)
                              ]),
                              _: 1
                            }, 8, ["items", "selected-index", "onSelect"])) : createCommentVNode("", true),
                            unref(showUserPicker) ? (openBlock(), createBlock(_sfc_main$p, {
                              key: 2,
                              items: unref(panelItems),
                              "selected-index": unref(selectedIndex),
                              onSelect: (it) => unref(pickUserEmail)(it.value)
                            }, {
                              header: withCtx(() => [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createVNode("span", null, "Users"),
                                createVNode("div", { class: "flex gap-1 ml-auto" }, [
                                  unref(isSuperAdmin) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                    createVNode("button", {
                                      type: "button",
                                      onClick: ($event) => userSource.value = "all",
                                      class: [unref(userSource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                    }, "All", 10, ["onClick"]),
                                    createVNode("button", {
                                      type: "button",
                                      onClick: ($event) => userSource.value = "company",
                                      class: [unref(userSource) === "company" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                    }, "Company", 10, ["onClick"])
                                  ], 64)) : createCommentVNode("", true)
                                ])
                              ]),
                              _: 1
                            }, 8, ["items", "selected-index", "onSelect"])) : createCommentVNode("", true),
                            unref(showCompanyPicker) ? (openBlock(), createBlock(_sfc_main$p, {
                              key: 3,
                              items: unref(panelItems),
                              "selected-index": unref(selectedIndex),
                              onSelect: (it) => unref(pickCompanyName)(it.value)
                            }, {
                              header: withCtx(() => [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-3.5 w-3.5",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createVNode("span", null, "Companies"),
                                createVNode("div", { class: "flex gap-1 ml-auto" }, [
                                  unref(isSuperAdmin) ? (openBlock(), createBlock(Fragment, { key: 0 }, [
                                    createVNode("button", {
                                      onClick: ($event) => companySource.value = "all",
                                      class: [unref(companySource) === "all" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                    }, "All", 10, ["onClick"]),
                                    createVNode("button", {
                                      onClick: ($event) => companySource.value = "me",
                                      class: [unref(companySource) === "me" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                    }, "Mine", 10, ["onClick"]),
                                    unref(params).email ? (openBlock(), createBlock("button", {
                                      key: 0,
                                      onClick: ($event) => companySource.value = "byUser",
                                      class: [unref(companySource) === "byUser" ? "bg-gray-700/50 text-gray-200 border-gray-600/50" : "bg-gray-800/30 text-gray-400 border-gray-700/30", "px-2 py-0.5 rounded-lg border text-xs"]
                                    }, "User", 10, ["onClick"])) : createCommentVNode("", true)
                                  ], 64)) : createCommentVNode("", true)
                                ])
                              ]),
                              preview: withCtx(({ item }) => [
                                createVNode("div", { class: "font-medium text-gray-200 mb-2" }, "Selected Company"),
                                createVNode("div", { class: "grid grid-cols-1 sm:grid-cols-2 gap-3" }, [
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Name:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(item.label), 1)
                                  ]),
                                  unref(companyDetails)[item.meta?.id] ? (openBlock(), createBlock("div", { key: 0 }, [
                                    createVNode("div", { class: "text-gray-500" }, "Slug:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].slug), 1)
                                  ])) : createCommentVNode("", true)
                                ]),
                                unref(companyDetails)[item.meta?.id] ? (openBlock(), createBlock("div", {
                                  key: 0,
                                  class: "space-y-2 pt-2 border-t border-gray-800/30"
                                }, [
                                  createVNode("div", { class: "grid grid-cols-2 gap-3" }, [
                                    createVNode("div", null, [
                                      createVNode("div", { class: "text-gray-500" }, "Currency:"),
                                      createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].base_currency), 1)
                                    ]),
                                    createVNode("div", null, [
                                      createVNode("div", { class: "text-gray-500" }, "Language:"),
                                      createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].language), 1)
                                    ])
                                  ]),
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Members:"),
                                    createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[item.meta?.id].members_count), 1)
                                  ]),
                                  createVNode("div", null, [
                                    createVNode("div", { class: "text-gray-500" }, "Roles:"),
                                    createVNode("div", { class: "text-gray-300 text-xs mt-0.5 grid grid-cols-2 gap-1" }, [
                                      createVNode("span", null, "owner: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).owner || 0), 1),
                                      createVNode("span", null, "admin: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).admin || 0), 1),
                                      createVNode("span", null, "accountant: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).accountant || 0), 1),
                                      createVNode("span", null, "viewer: " + toDisplayString((unref(companyDetails)[item.meta?.id].role_counts || {}).viewer || 0), 1)
                                    ])
                                  ]),
                                  unref(companyDetails)[item.meta?.id].owners && unref(companyDetails)[item.meta?.id].owners.length ? (openBlock(), createBlock("div", {
                                    key: 0,
                                    class: "text-gray-500"
                                  }, [
                                    createTextVNode(" Owners: "),
                                    createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[item.meta?.id].owners.map((o2) => o2.name).join(", ")), 1)
                                  ])) : createCommentVNode("", true),
                                  unref(companyDetails)[item.meta?.id].last_activity ? (openBlock(), createBlock("div", {
                                    key: 1,
                                    class: "text-gray-500"
                                  }, [
                                    createTextVNode(" Last activity: "),
                                    createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[item.meta?.id].last_activity.action), 1),
                                    createVNode("span", { class: "text-gray-400 block text-xs mt-0.5" }, "@ " + toDisplayString(unref(companyDetails)[item.meta?.id].last_activity.created_at), 1)
                                  ])) : createCommentVNode("", true),
                                  createVNode("div", { class: "flex flex-wrap gap-2 pt-2 border-t border-gray-800/30" }, [
                                    createVNode("button", {
                                      type: "button",
                                      onClick: ($event) => unref(loadCompanyMembers)(item.meta?.id),
                                      class: "px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1"
                                    }, [
                                      (openBlock(), createBlock("svg", {
                                        xmlns: "http://www.w3.org/2000/svg",
                                        class: "h-3.5 w-3.5",
                                        viewBox: "0 0 20 20",
                                        fill: "currentColor"
                                      }, [
                                        createVNode("path", { d: "M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" })
                                      ])),
                                      createTextVNode(" View members ")
                                    ], 8, ["onClick"]),
                                    createVNode("button", {
                                      type: "button",
                                      onClick: ($event) => unref(quickAssignToCompany)(item.meta?.id),
                                      class: "px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1"
                                    }, [
                                      (openBlock(), createBlock("svg", {
                                        xmlns: "http://www.w3.org/2000/svg",
                                        class: "h-3.5 w-3.5",
                                        viewBox: "0 0 20 20",
                                        fill: "currentColor"
                                      }, [
                                        createVNode("path", { d: "M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" })
                                      ])),
                                      createTextVNode(" Assign user ")
                                    ], 8, ["onClick"]),
                                    createVNode("button", {
                                      type: "button",
                                      onClick: ($event) => unref(setActiveCompany)(item.meta?.id),
                                      class: "px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1"
                                    }, [
                                      (openBlock(), createBlock("svg", {
                                        xmlns: "http://www.w3.org/2000/svg",
                                        class: "h-3.5 w-3.5",
                                        viewBox: "0 0 20 20",
                                        fill: "currentColor"
                                      }, [
                                        createVNode("path", {
                                          "fill-rule": "evenodd",
                                          d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                          "clip-rule": "evenodd"
                                        })
                                      ])),
                                      createTextVNode(" Set active ")
                                    ], 8, ["onClick"])
                                  ])
                                ])) : createCommentVNode("", true)
                              ]),
                              _: 1
                            }, 8, ["items", "selected-index", "onSelect"])) : createCommentVNode("", true),
                            unref(inlineSuggestions).length > 0 ? (openBlock(), createBlock("div", {
                              key: 4,
                              class: "rounded-xl bg-gray-900/40 border border-gray-800/50 overflow-hidden"
                            }, [
                              createVNode("div", { class: "max-h-40 overflow-auto" }, [
                                (openBlock(true), createBlock(Fragment, null, renderList(unref(inlineSuggestions), (it, index) => {
                                  return openBlock(), createBlock("button", {
                                    key: it.value,
                                    onClick: ($event) => unref(selectChoice)(it.value),
                                    class: ["w-full text-left px-4 py-2.5 hover:bg-gray-800/30 text-gray-300 border-t border-gray-800/50", index === unref(selectedIndex) ? "bg-gray-800/50" : ""]
                                  }, [
                                    createVNode("div", { class: "font-medium" }, toDisplayString(it.value), 1),
                                    createVNode("div", { class: "text-xs text-gray-500" }, toDisplayString(it.label), 1)
                                  ], 10, ["onClick"]);
                                }), 128))
                              ])
                            ])) : createCommentVNode("", true),
                            unref(selectedVerb) && unref(selectedVerb).id === "delete" && unref(params).company && unref(companyDetails)[unref(params).company] ? (openBlock(), createBlock("div", {
                              key: 5,
                              class: "mt-4 bg-red-900/20 border border-red-700/50 rounded-xl p-4 text-red-200 backdrop-blur-sm"
                            }, [
                              createVNode("div", { class: "flex items-center gap-2 mb-3" }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-4 w-4",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createVNode("div", { class: "text-sm font-medium" }, "Confirm Deletion")
                              ]),
                              createVNode("div", { class: "text-xs mb-3" }, [
                                createTextVNode("Type "),
                                createVNode("strong", { class: "text-red-100" }, toDisplayString(unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name), 1),
                                createTextVNode(" to confirm deletion of this company with " + toDisplayString(unref(companyDetails)[unref(params).company].members_count) + " members.", 1)
                              ]),
                              withDirectives(createVNode("input", {
                                "onUpdate:modelValue": ($event) => isRef(deleteConfirmText) ? deleteConfirmText.value = $event : null,
                                class: "w-full bg-red-900/30 border border-red-700/50 rounded-lg p-2.5 text-red-100 placeholder-red-300/70 focus:outline-none",
                                placeholder: unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name
                              }, null, 8, ["onUpdate:modelValue", "placeholder"]), [
                                [vModelText, unref(deleteConfirmText)]
                              ]),
                              createVNode("div", { class: "mt-3" }, [
                                createVNode("button", {
                                  type: "button",
                                  disabled: unref(deleteConfirmText) !== (unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name),
                                  onClick: unref(execute),
                                  class: ["px-4 py-2 rounded-lg border transition-all duration-300 flex items-center gap-1.5 text-xs", unref(deleteConfirmText) === (unref(companyDetails)[unref(params).company].slug || unref(companyDetails)[unref(params).company].name) ? "border-red-500 bg-red-700/50 text-white hover:bg-red-600/50" : "border-red-800/50 bg-red-950/30 text-red-400 cursor-not-allowed"]
                                }, [
                                  (openBlock(), createBlock("svg", {
                                    xmlns: "http://www.w3.org/2000/svg",
                                    class: "h-3.5 w-3.5",
                                    viewBox: "0 0 20 20",
                                    fill: "currentColor"
                                  }, [
                                    createVNode("path", {
                                      "fill-rule": "evenodd",
                                      d: "M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z",
                                      "clip-rule": "evenodd"
                                    })
                                  ])),
                                  createTextVNode(" Delete company ")
                                ], 10, ["disabled", "onClick"])
                              ])
                            ])) : createCommentVNode("", true),
                            !unref(activeFlagId) && !unref(showUserPicker) && !unref(showCompanyPicker) && unref(currentChoices).length === 0 ? (openBlock(), createBlock("div", {
                              key: 6,
                              class: "text-center py-10"
                            }, [
                              createVNode("div", { class: "text-gray-500 text-sm mb-4 flex flex-col items-center" }, [
                                (openBlock(), createBlock("svg", {
                                  xmlns: "http://www.w3.org/2000/svg",
                                  class: "h-8 w-8 mb-2 opacity-50",
                                  viewBox: "0 0 20 20",
                                  fill: "currentColor"
                                }, [
                                  createVNode("path", {
                                    "fill-rule": "evenodd",
                                    d: "M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z",
                                    "clip-rule": "evenodd"
                                  })
                                ])),
                                createTextVNode(" Click a parameter above to start entering values ")
                              ]),
                              createVNode("div", { class: "text-gray-600 text-xs" }, " Required parameters: " + toDisplayString(unref(selectedVerb)?.fields.filter((f2) => f2.required).length || 0) + "/" + toDisplayString(unref(selectedVerb)?.fields.length || 0), 1)
                            ])) : createCommentVNode("", true)
                          ])) : createCommentVNode("", true)
                        ]),
                        createVNode("div", { class: "px-4 py-3 border-t border-gray-700/30 bg-gradient-to-r from-gray-800 to-gray-900" }, [
                          createVNode("div", { class: "flex justify-between items-center text-xs text-gray-500" }, [
                            createVNode("div", { class: "flex gap-3 sm:gap-4 flex-wrap" }, [
                              createVNode("span", { class: "flex items-center gap-1" }, [
                                createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "↑↓"),
                                createTextVNode(" navigate")
                              ]),
                              createVNode("span", { class: "flex items-center gap-1" }, [
                                createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "↵"),
                                createTextVNode(" select")
                              ]),
                              createVNode("span", { class: "flex items-center gap-1" }, [
                                createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "⇥"),
                                createTextVNode(" complete")
                              ]),
                              createVNode("span", { class: "hidden sm:flex items-center gap-1" }, [
                                createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "⇧⇥"),
                                createTextVNode(" edit last")
                              ]),
                              createVNode("span", { class: "flex items-center gap-1" }, [
                                createVNode("kbd", { class: "px-1.5 py-0.5 bg-gray-700/50 rounded border border-gray-600/50" }, "⎋"),
                                createTextVNode(" back")
                              ])
                            ]),
                            createVNode("div", { class: "text-green-400 flex items-center gap-1.5" }, [
                              createVNode("span", { class: "h-2 w-2 rounded-full bg-green-400" }),
                              createTextVNode(" " + toDisplayString(unref(executing) ? "EXECUTING..." : "READY"), 1)
                            ])
                          ])
                        ])
                      ], 40, ["onKeydown"])
                    ]),
                    _: 1
                  }, 8, ["class", "onKeydown"]),
                  (unref(showCompanyPicker) || unref(showUserPicker)) && unref(highlightedItem) ? (openBlock(), createBlock("div", {
                    key: 0,
                    class: "w-full lg:w-80 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden"
                  }, [
                    createVNode("div", { class: "px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" }, [
                      createVNode("div", { class: "flex items-center gap-2" }, [
                        createVNode("span", { class: "text-green-400" }, "●"),
                        createVNode("span", { class: "text-gray-400 text-xs tracking-wide" }, "DETAILS")
                      ])
                    ]),
                    unref(showCompanyPicker) ? (openBlock(), createBlock("div", {
                      key: 0,
                      class: "p-4 space-y-3 max-h-96 overflow-auto"
                    }, [
                      createVNode("div", { class: "text-gray-200 text-sm font-medium" }, toDisplayString(unref(highlightedItem).label), 1),
                      unref(companyDetails)[unref(highlightedItem).meta?.id] ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "grid grid-cols-2 gap-3 text-xs"
                      }, [
                        createVNode("div", null, [
                          createVNode("div", { class: "text-gray-500" }, "Slug"),
                          createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].slug), 1)
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "text-gray-500" }, "Currency"),
                          createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].base_currency), 1)
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "text-gray-500" }, "Language"),
                          createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].language), 1)
                        ]),
                        createVNode("div", null, [
                          createVNode("div", { class: "text-gray-500" }, "Members"),
                          createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].members_count), 1)
                        ])
                      ])) : createCommentVNode("", true),
                      unref(companyDetails)[unref(highlightedItem).meta?.id] ? (openBlock(), createBlock("div", {
                        key: 1,
                        class: "text-xs text-gray-300"
                      }, [
                        createVNode("div", { class: "text-gray-500" }, "Roles"),
                        createVNode("div", { class: "grid grid-cols-2 gap-1" }, [
                          createVNode("span", null, "owner: " + toDisplayString((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).owner || 0), 1),
                          createVNode("span", null, "admin: " + toDisplayString((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).admin || 0), 1),
                          createVNode("span", null, "accountant: " + toDisplayString((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).accountant || 0), 1),
                          createVNode("span", null, "viewer: " + toDisplayString((unref(companyDetails)[unref(highlightedItem).meta?.id].role_counts || {}).viewer || 0), 1)
                        ])
                      ])) : createCommentVNode("", true),
                      unref(companyDetails)[unref(highlightedItem).meta?.id]?.owners?.length ? (openBlock(), createBlock("div", {
                        key: 2,
                        class: "text-xs text-gray-400"
                      }, [
                        createTextVNode(" Owners: "),
                        createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].owners.map((o2) => o2.name).join(", ")), 1)
                      ])) : createCommentVNode("", true),
                      unref(companyDetails)[unref(highlightedItem).meta?.id]?.last_activity ? (openBlock(), createBlock("div", {
                        key: 3,
                        class: "text-xs text-gray-400"
                      }, [
                        createTextVNode(" Last: "),
                        createVNode("span", { class: "text-gray-300" }, toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].last_activity.action), 1),
                        createVNode("span", { class: "text-gray-500" }, "@ " + toDisplayString(unref(companyDetails)[unref(highlightedItem).meta?.id].last_activity.created_at), 1)
                      ])) : createCommentVNode("", true),
                      createVNode("div", { class: "flex flex-wrap gap-2 pt-2 border-t border-gray-800/30" }, [
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => unref(loadCompanyMembers)(unref(highlightedItem).meta?.id),
                          class: "px-3 py-1.5 rounded-lg border border-gray-700/50 bg-gray-800/30 text-gray-300 hover:bg-gray-700/50 text-xs flex items-center gap-1"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "h-3.5 w-3.5",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", { d: "M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" })
                          ])),
                          createTextVNode(" View members ")
                        ], 8, ["onClick"]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => unref(quickAssignToCompany)(unref(highlightedItem).meta?.id),
                          class: "px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 text-xs flex items-center gap-1"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "h-3.5 w-3.5",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", { d: "M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" })
                          ])),
                          createTextVNode(" Assign user ")
                        ], 8, ["onClick"]),
                        createVNode("button", {
                          type: "button",
                          onClick: ($event) => unref(setActiveCompany)(unref(highlightedItem).meta?.id),
                          class: "px-3 py-1.5 rounded-lg border border-blue-700/50 bg-blue-900/20 text-blue-200 hover:bg-blue-800/30 text-xs flex items-center gap-1"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "h-3.5 w-3.5",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", {
                              "fill-rule": "evenodd",
                              d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                              "clip-rule": "evenodd"
                            })
                          ])),
                          createTextVNode(" Set active ")
                        ], 8, ["onClick"])
                      ]),
                      unref(companyMembersLoading)[unref(highlightedItem).meta?.id] ? (openBlock(), createBlock("div", {
                        key: 4,
                        class: "text-gray-500 text-xs"
                      }, "Loading members…")) : unref(companyMembers)[unref(highlightedItem).meta?.id] && unref(companyMembers)[unref(highlightedItem).meta?.id].length ? (openBlock(), createBlock("div", {
                        key: 5,
                        class: "mt-2 max-h-32 overflow-auto border-t border-gray-800/30 pt-2"
                      }, [
                        (openBlock(true), createBlock(Fragment, null, renderList(unref(companyMembers)[unref(highlightedItem).meta?.id], (m2) => {
                          return openBlock(), createBlock("div", {
                            key: m2.id,
                            class: "py-1.5 text-gray-300 text-xs border-b border-gray-800/30 last:border-b-0"
                          }, [
                            createVNode("div", { class: "font-medium" }, toDisplayString(m2.name), 1),
                            createVNode("div", { class: "text-gray-500" }, [
                              createTextVNode(toDisplayString(m2.email) + " — ", 1),
                              createVNode("span", { class: "text-gray-400" }, toDisplayString(m2.role), 1)
                            ])
                          ]);
                        }), 128))
                      ])) : createCommentVNode("", true)
                    ])) : unref(showUserPicker) ? (openBlock(), createBlock("div", {
                      key: 1,
                      class: "p-4 space-y-3 max-h-96 overflow-auto"
                    }, [
                      createVNode("div", { class: "text-gray-200 text-sm font-medium" }, toDisplayString(unref(highlightedItem).meta?.name), 1),
                      createVNode("div", { class: "grid grid-cols-2 gap-3 text-xs" }, [
                        createVNode("div", null, [
                          createVNode("div", { class: "text-gray-500" }, "Email"),
                          createVNode("div", { class: "text-gray-200" }, toDisplayString(unref(highlightedItem).meta?.email), 1)
                        ])
                      ]),
                      unref(userDetails)[unref(highlightedItem).meta?.id] ? (openBlock(), createBlock("div", {
                        key: 0,
                        class: "mt-2"
                      }, [
                        createVNode("div", { class: "text-gray-500 font-medium mb-1 text-xs" }, "Memberships"),
                        (openBlock(true), createBlock(Fragment, null, renderList(unref(userDetails)[unref(highlightedItem).meta?.id].memberships, (m2) => {
                          return openBlock(), createBlock("div", {
                            key: m2.id,
                            class: "text-gray-300 text-xs py-1.5 border-t border-gray-800/30 flex justify-between items-center"
                          }, [
                            createVNode("div", null, [
                              createVNode("span", { class: "font-medium" }, toDisplayString(m2.name), 1),
                              createVNode("span", { class: "text-gray-500 ml-2" }, "— " + toDisplayString(m2.role), 1)
                            ]),
                            createVNode("button", {
                              type: "button",
                              onClick: ($event) => unref(quickUnassignUserFromCompany)(unref(highlightedItem).meta?.email, m2.id),
                              class: "text-red-300 hover:text-red-200 transition-colors px-2 py-0.5 rounded border border-red-800/30 hover:border-red-600/50"
                            }, "Unassign", 8, ["onClick"])
                          ]);
                        }), 128)),
                        createVNode("div", { class: "flex gap-2 mt-3" }, [
                          createVNode("button", {
                            type: "button",
                            onClick: ($event) => unref(quickAssignUserToCompany)(unref(highlightedItem).meta?.email),
                            class: "px-3 py-1.5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 text-emerald-200 hover:bg-emerald-800/30 transition-all duration-200 text-xs flex items-center gap-1"
                          }, [
                            (openBlock(), createBlock("svg", {
                              xmlns: "http://www.w3.org/2000/svg",
                              class: "h-3.5 w-3.5",
                              viewBox: "0 0 20 20",
                              fill: "currentColor"
                            }, [
                              createVNode("path", { d: "M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" })
                            ])),
                            createTextVNode(" Assign to company… ")
                          ], 8, ["onClick"])
                        ])
                      ])) : createCommentVNode("", true)
                    ])) : createCommentVNode("", true)
                  ])) : createCommentVNode("", true),
                  unref(showResults) && unref(results).length > 0 ? (openBlock(), createBlock("div", {
                    key: 1,
                    class: ["w-full lg:w-80 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-2xl font-mono text-sm overflow-hidden", unref(showResults) ? "opacity-100 translate-x-0" : "opacity-0 translate-x-4"]
                  }, [
                    createVNode("div", { class: "px-4 py-3 border-b border-gray-700/50 bg-gradient-to-r from-gray-800 to-gray-900" }, [
                      createVNode("div", { class: "flex items-center gap-2" }, [
                        createVNode("span", { class: "text-green-400" }, "●"),
                        createVNode("span", { class: "text-gray-400 text-xs tracking-wide" }, "EXECUTION LOG"),
                        createVNode("button", {
                          onClick: ($event) => showResults.value = false,
                          class: "ml-auto text-gray-500 hover:text-gray-300"
                        }, [
                          (openBlock(), createBlock("svg", {
                            xmlns: "http://www.w3.org/2000/svg",
                            class: "h-3.5 w-3.5",
                            viewBox: "0 0 20 20",
                            fill: "currentColor"
                          }, [
                            createVNode("path", {
                              "fill-rule": "evenodd",
                              d: "M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z",
                              "clip-rule": "evenodd"
                            })
                          ]))
                        ], 8, ["onClick"])
                      ])
                    ]),
                    createVNode("div", { class: "p-4 space-y-3 max-h-96 overflow-auto" }, [
                      (openBlock(true), createBlock(Fragment, null, renderList(unref(results), (result, index) => {
                        return openBlock(), createBlock("div", {
                          key: index,
                          class: ["bg-gray-800/30 p-3 rounded-xl border backdrop-blur-sm", result.success ? "border-green-700/30" : "border-red-700/30"]
                        }, [
                          createVNode("div", { class: "flex items-center gap-2 mb-2" }, [
                            createVNode("span", {
                              class: [result.success ? "text-green-400" : "text-red-400", "flex items-center gap-1.5 text-xs"]
                            }, [
                              result.success ? (openBlock(), createBlock("svg", {
                                key: 0,
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z",
                                  "clip-rule": "evenodd"
                                })
                              ])) : (openBlock(), createBlock("svg", {
                                key: 1,
                                xmlns: "http://www.w3.org/2000/svg",
                                class: "h-3.5 w-3.5",
                                viewBox: "0 0 20 20",
                                fill: "currentColor"
                              }, [
                                createVNode("path", {
                                  "fill-rule": "evenodd",
                                  d: "M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z",
                                  "clip-rule": "evenodd"
                                })
                              ])),
                              createTextVNode(" " + toDisplayString(result.action), 1)
                            ], 2)
                          ]),
                          createVNode("div", {
                            class: [result.success ? "text-green-200" : "text-red-200", "text-xs mb-2"]
                          }, toDisplayString(result.message), 3),
                          createVNode("div", { class: "text-gray-500 text-xs" }, toDisplayString(new Date(result.timestamp).toLocaleTimeString()), 1)
                        ], 2);
                      }), 128))
                    ])
                  ], 2)) : createCommentVNode("", true)
                ], 2)
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<!--]-->`);
    };
  }
});
const _sfc_setup$o = _sfc_main$o.setup;
_sfc_main$o.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/CommandPalette.vue");
  return _sfc_setup$o ? _sfc_setup$o(props, ctx) : void 0;
};
const CommandPalette = /* @__PURE__ */ _export_sfc(_sfc_main$o, [["__scopeId", "data-v-5548a2bb"]]);
const _sfc_main$n = {
  __name: "AuthenticatedLayout",
  __ssrInlineRender: true,
  setup(__props) {
    const showingNavigationDropdown = ref(false);
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(_attrs)}><div class="min-h-screen bg-gray-100"><nav class="border-b border-gray-100 bg-white"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><div class="flex h-16 justify-between"><div class="flex"><div class="flex shrink-0 items-center">`);
      _push(ssrRenderComponent(unref(Link), {
        href: _ctx.route("dashboard")
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(ApplicationLogo, { class: "block h-9 w-auto fill-current text-gray-800" }, null, _parent2, _scopeId));
          } else {
            return [
              createVNode(ApplicationLogo, { class: "block h-9 w-auto fill-current text-gray-800" })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div><div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">`);
      _push(ssrRenderComponent(_sfc_main$s, {
        href: _ctx.route("dashboard"),
        active: _ctx.route().current("dashboard")
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(` Dashboard `);
          } else {
            return [
              createTextVNode(" Dashboard ")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div></div><div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">`);
      _push(ssrRenderComponent(_sfc_main$q, null, null, _parent));
      _push(`<div class="relative ms-1">`);
      _push(ssrRenderComponent(_sfc_main$u, {
        align: "right",
        width: "48"
      }, {
        trigger: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<span class="inline-flex rounded-md"${_scopeId}><button type="button" class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"${_scopeId}>${ssrInterpolate(_ctx.$page.props.auth.user.name)} `);
            if (_ctx.$page.props.auth.isSuperAdmin) {
              _push2(`<span class="ms-2 rounded bg-red-100 px-1 text-xs text-red-600"${_scopeId}>Superadmin</span>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"${_scopeId}><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"${_scopeId}></path></svg></button></span>`);
          } else {
            return [
              createVNode("span", { class: "inline-flex rounded-md" }, [
                createVNode("button", {
                  type: "button",
                  class: "inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                }, [
                  createTextVNode(toDisplayString(_ctx.$page.props.auth.user.name) + " ", 1),
                  _ctx.$page.props.auth.isSuperAdmin ? (openBlock(), createBlock("span", {
                    key: 0,
                    class: "ms-2 rounded bg-red-100 px-1 text-xs text-red-600"
                  }, "Superadmin")) : createCommentVNode("", true),
                  (openBlock(), createBlock("svg", {
                    class: "-me-0.5 ms-2 h-4 w-4",
                    xmlns: "http://www.w3.org/2000/svg",
                    viewBox: "0 0 20 20",
                    fill: "currentColor"
                  }, [
                    createVNode("path", {
                      "fill-rule": "evenodd",
                      d: "M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z",
                      "clip-rule": "evenodd"
                    })
                  ]))
                ])
              ])
            ];
          }
        }),
        content: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(_sfc_main$t, {
              href: _ctx.route("profile.edit")
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`Profile`);
                } else {
                  return [
                    createTextVNode("Profile")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$t, {
              href: _ctx.route("logout"),
              method: "post",
              as: "button"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`Log Out`);
                } else {
                  return [
                    createTextVNode("Log Out")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
          } else {
            return [
              createVNode(_sfc_main$t, {
                href: _ctx.route("profile.edit")
              }, {
                default: withCtx(() => [
                  createTextVNode("Profile")
                ]),
                _: 1
              }, 8, ["href"]),
              createVNode(_sfc_main$t, {
                href: _ctx.route("logout"),
                method: "post",
                as: "button"
              }, {
                default: withCtx(() => [
                  createTextVNode("Log Out")
                ]),
                _: 1
              }, 8, ["href"])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div></div><div class="-me-2 flex items-center sm:hidden"><button class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"><svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path class="${ssrRenderClass({ hidden: showingNavigationDropdown.value, "inline-flex": !showingNavigationDropdown.value })}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path><path class="${ssrRenderClass({ hidden: !showingNavigationDropdown.value, "inline-flex": showingNavigationDropdown.value })}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></div></div></div><div class="${ssrRenderClass([{ block: showingNavigationDropdown.value, hidden: !showingNavigationDropdown.value }, "sm:hidden"])}"><div class="space-y-1 pb-3 pt-2">`);
      _push(ssrRenderComponent(_sfc_main$r, {
        href: _ctx.route("dashboard"),
        active: _ctx.route().current("dashboard")
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(` Dashboard `);
          } else {
            return [
              createTextVNode(" Dashboard ")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div><div class="border-t border-gray-200 pb-1 pt-4"><div class="px-4"><div class="text-base font-medium text-gray-800 flex items-center gap-1"><span>${ssrInterpolate(_ctx.$page.props.auth.user.name)}</span>`);
      if (_ctx.$page.props.auth.isSuperAdmin) {
        _push(`<span class="rounded bg-red-100 px-1 text-xs text-red-600">Superadmin</span>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="text-sm font-medium text-gray-500">${ssrInterpolate(_ctx.$page.props.auth.user.email)}</div></div><div class="mt-3 px-4">`);
      _push(ssrRenderComponent(_sfc_main$q, null, null, _parent));
      _push(`</div><div class="mt-3 space-y-1">`);
      _push(ssrRenderComponent(_sfc_main$r, {
        href: _ctx.route("profile.edit")
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`Profile`);
          } else {
            return [
              createTextVNode("Profile")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$r, {
        href: _ctx.route("logout"),
        method: "post",
        as: "button"
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`Log Out`);
          } else {
            return [
              createTextVNode("Log Out")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div></div></div></nav>`);
      if (_ctx.$slots.header) {
        _push(`<header class="bg-white shadow"><div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">`);
        ssrRenderSlot(_ctx.$slots, "header", {}, null, _push, _parent);
        _push(`</div></header>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<main>`);
      ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
      _push(`</main></div>`);
      _push(ssrRenderComponent(CommandPalette, null, null, _parent));
      _push(`</div>`);
    };
  }
};
const _sfc_setup$n = _sfc_main$n.setup;
_sfc_main$n.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/AuthenticatedLayout.vue");
  return _sfc_setup$n ? _sfc_setup$n(props, ctx) : void 0;
};
const _sfc_main$m = {
  __name: "Dashboard",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Admin Dashboard" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$n, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<h2 class="text-xl font-semibold leading-tight text-gray-800"${_scopeId}> Admin Dashboard </h2>`);
          } else {
            return [
              createVNode("h2", { class: "text-xl font-semibold leading-tight text-gray-800" }, " Admin Dashboard ")
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="py-12"${_scopeId}><div class="mx-auto max-w-7xl sm:px-6 lg:px-8"${_scopeId}><div class="overflow-hidden bg-white shadow-sm sm:rounded-lg"${_scopeId}><div class="p-6 text-gray-900"${_scopeId}> Welcome, superadmin! </div></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "mx-auto max-w-7xl sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "overflow-hidden bg-white shadow-sm sm:rounded-lg" }, [
                    createVNode("div", { class: "p-6 text-gray-900" }, " Welcome, superadmin! ")
                  ])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup$m = _sfc_main$m.setup;
_sfc_main$m.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Admin/Dashboard.vue");
  return _sfc_setup$m ? _sfc_setup$m(props, ctx) : void 0;
};
const __vite_glob_0_0 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$m
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$l = {
  __name: "GuestLayout",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0" }, _attrs))}><div>`);
      _push(ssrRenderComponent(unref(Link), { href: "/" }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(ApplicationLogo, { class: "h-20 w-20 fill-current text-gray-500" }, null, _parent2, _scopeId));
          } else {
            return [
              createVNode(ApplicationLogo, { class: "h-20 w-20 fill-current text-gray-500" })
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div><div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">`);
      ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
      _push(`</div></div>`);
    };
  }
};
const _sfc_setup$l = _sfc_main$l.setup;
_sfc_main$l.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/GuestLayout.vue");
  return _sfc_setup$l ? _sfc_setup$l(props, ctx) : void 0;
};
const _sfc_main$k = {
  __name: "InputError",
  __ssrInlineRender: true,
  props: {
    message: {
      type: String
    }
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({
        style: __props.message ? null : { display: "none" }
      }, _attrs))}><p class="text-sm text-red-600">${ssrInterpolate(__props.message)}</p></div>`);
    };
  }
};
const _sfc_setup$k = _sfc_main$k.setup;
_sfc_main$k.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/InputError.vue");
  return _sfc_setup$k ? _sfc_setup$k(props, ctx) : void 0;
};
const _sfc_main$j = {
  __name: "InputLabel",
  __ssrInlineRender: true,
  props: {
    value: {
      type: String
    }
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<label${ssrRenderAttrs(mergeProps({ class: "block text-sm font-medium text-gray-700" }, _attrs))}>`);
      if (__props.value) {
        _push(`<span>${ssrInterpolate(__props.value)}</span>`);
      } else {
        _push(`<span>`);
        ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
        _push(`</span>`);
      }
      _push(`</label>`);
    };
  }
};
const _sfc_setup$j = _sfc_main$j.setup;
_sfc_main$j.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/InputLabel.vue");
  return _sfc_setup$j ? _sfc_setup$j(props, ctx) : void 0;
};
const _sfc_main$i = {};
function _sfc_ssrRender$1(_ctx, _push, _parent, _attrs) {
  _push(`<button${ssrRenderAttrs(mergeProps({ class: "inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900" }, _attrs))}>`);
  ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
  _push(`</button>`);
}
const _sfc_setup$i = _sfc_main$i.setup;
_sfc_main$i.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/PrimaryButton.vue");
  return _sfc_setup$i ? _sfc_setup$i(props, ctx) : void 0;
};
const PrimaryButton = /* @__PURE__ */ _export_sfc(_sfc_main$i, [["ssrRender", _sfc_ssrRender$1]]);
const _sfc_main$h = {
  __name: "TextInput",
  __ssrInlineRender: true,
  props: {
    "modelValue": {
      type: String,
      required: true
    },
    "modelModifiers": {}
  },
  emits: ["update:modelValue"],
  setup(__props, { expose: __expose }) {
    const model = useModel(__props, "modelValue");
    const input = ref(null);
    onMounted(() => {
      if (input.value.hasAttribute("autofocus")) {
        input.value.focus();
      }
    });
    __expose({ focus: () => input.value.focus() });
    return (_ctx, _push, _parent, _attrs) => {
      let _temp0;
      _push(`<input${ssrRenderAttrs((_temp0 = mergeProps({
        class: "rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500",
        ref_key: "input",
        ref: input
      }, _attrs), mergeProps(_temp0, ssrGetDynamicModelProps(_temp0, model.value))))}>`);
    };
  }
};
const _sfc_setup$h = _sfc_main$h.setup;
_sfc_main$h.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/TextInput.vue");
  return _sfc_setup$h ? _sfc_setup$h(props, ctx) : void 0;
};
const _sfc_main$g = {
  __name: "ConfirmPassword",
  __ssrInlineRender: true,
  setup(__props) {
    const form = useForm({
      password: ""
    });
    const submit = () => {
      form.post(route("password.confirm"), {
        onFinish: () => form.reset()
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$l, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Confirm Password" }, null, _parent2, _scopeId));
            _push2(`<div class="mb-4 text-sm text-gray-600"${_scopeId}> This is a secure area of the application. Please confirm your password before continuing. </div><form${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password",
              value: "Password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password",
              type: "password",
              class: "mt-1 block w-full",
              modelValue: unref(form).password,
              "onUpdate:modelValue": ($event) => unref(form).password = $event,
              required: "",
              autocomplete: "current-password",
              autofocus: ""
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.password
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4 flex justify-end"${_scopeId}>`);
            _push2(ssrRenderComponent(PrimaryButton, {
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Confirm `);
                } else {
                  return [
                    createTextVNode(" Confirm ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Confirm Password" }),
              createVNode("div", { class: "mb-4 text-sm text-gray-600" }, " This is a secure area of the application. Please confirm your password before continuing. "),
              createVNode("form", {
                onSubmit: withModifiers(submit, ["prevent"])
              }, [
                createVNode("div", null, [
                  createVNode(_sfc_main$j, {
                    for: "password",
                    value: "Password"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password",
                    type: "password",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).password,
                    "onUpdate:modelValue": ($event) => unref(form).password = $event,
                    required: "",
                    autocomplete: "current-password",
                    autofocus: ""
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.password
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4 flex justify-end" }, [
                  createVNode(PrimaryButton, {
                    class: ["ms-4", { "opacity-25": unref(form).processing }],
                    disabled: unref(form).processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Confirm ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"])
                ])
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$g = _sfc_main$g.setup;
_sfc_main$g.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/ConfirmPassword.vue");
  return _sfc_setup$g ? _sfc_setup$g(props, ctx) : void 0;
};
const __vite_glob_0_1 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$g
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$f = {
  __name: "ForgotPassword",
  __ssrInlineRender: true,
  props: {
    status: {
      type: String
    }
  },
  setup(__props) {
    const form = useForm({
      email: ""
    });
    const submit = () => {
      form.post(route("password.email"));
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$l, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Forgot Password" }, null, _parent2, _scopeId));
            _push2(`<div class="mb-4 text-sm text-gray-600"${_scopeId}> Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one. </div>`);
            if (__props.status) {
              _push2(`<div class="mb-4 text-sm font-medium text-green-600"${_scopeId}>${ssrInterpolate(__props.status)}</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<form${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "email",
              value: "Email"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "email",
              type: "email",
              class: "mt-1 block w-full",
              modelValue: unref(form).email,
              "onUpdate:modelValue": ($event) => unref(form).email = $event,
              required: "",
              autofocus: "",
              autocomplete: "username"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.email
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4 flex items-center justify-end"${_scopeId}>`);
            _push2(ssrRenderComponent(PrimaryButton, {
              class: { "opacity-25": unref(form).processing },
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Email Password Reset Link `);
                } else {
                  return [
                    createTextVNode(" Email Password Reset Link ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Forgot Password" }),
              createVNode("div", { class: "mb-4 text-sm text-gray-600" }, " Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one. "),
              __props.status ? (openBlock(), createBlock("div", {
                key: 0,
                class: "mb-4 text-sm font-medium text-green-600"
              }, toDisplayString(__props.status), 1)) : createCommentVNode("", true),
              createVNode("form", {
                onSubmit: withModifiers(submit, ["prevent"])
              }, [
                createVNode("div", null, [
                  createVNode(_sfc_main$j, {
                    for: "email",
                    value: "Email"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "email",
                    type: "email",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).email,
                    "onUpdate:modelValue": ($event) => unref(form).email = $event,
                    required: "",
                    autofocus: "",
                    autocomplete: "username"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.email
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4 flex items-center justify-end" }, [
                  createVNode(PrimaryButton, {
                    class: { "opacity-25": unref(form).processing },
                    disabled: unref(form).processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Email Password Reset Link ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"])
                ])
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$f = _sfc_main$f.setup;
_sfc_main$f.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/ForgotPassword.vue");
  return _sfc_setup$f ? _sfc_setup$f(props, ctx) : void 0;
};
const __vite_glob_0_2 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$f
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$e = {
  __name: "Checkbox",
  __ssrInlineRender: true,
  props: {
    checked: {
      type: [Array, Boolean],
      required: true
    },
    value: {
      default: null
    }
  },
  emits: ["update:checked"],
  setup(__props, { emit: __emit }) {
    const emit = __emit;
    const props = __props;
    const proxyChecked = computed({
      get() {
        return props.checked;
      },
      set(val) {
        emit("update:checked", val);
      }
    });
    return (_ctx, _push, _parent, _attrs) => {
      let _temp0;
      _push(`<input${ssrRenderAttrs((_temp0 = mergeProps({
        type: "checkbox",
        value: __props.value,
        checked: Array.isArray(proxyChecked.value) ? ssrLooseContain(proxyChecked.value, __props.value) : proxyChecked.value,
        class: "rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
      }, _attrs), mergeProps(_temp0, ssrGetDynamicModelProps(_temp0, proxyChecked.value))))}>`);
    };
  }
};
const _sfc_setup$e = _sfc_main$e.setup;
_sfc_main$e.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Checkbox.vue");
  return _sfc_setup$e ? _sfc_setup$e(props, ctx) : void 0;
};
const _sfc_main$d = {
  __name: "Login",
  __ssrInlineRender: true,
  props: {
    canResetPassword: {
      type: Boolean
    },
    status: {
      type: String
    }
  },
  setup(__props) {
    const form = useForm({
      email: "",
      password: "",
      remember: false
    });
    const submit = () => {
      form.post(route("login"), {
        onFinish: () => form.reset("password")
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$l, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Log in" }, null, _parent2, _scopeId));
            if (__props.status) {
              _push2(`<div class="mb-4 text-sm font-medium text-green-600"${_scopeId}>${ssrInterpolate(__props.status)}</div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<form${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "email",
              value: "Email"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "email",
              type: "email",
              class: "mt-1 block w-full",
              modelValue: unref(form).email,
              "onUpdate:modelValue": ($event) => unref(form).email = $event,
              required: "",
              autofocus: "",
              autocomplete: "username"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.email
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password",
              value: "Password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password",
              type: "password",
              class: "mt-1 block w-full",
              modelValue: unref(form).password,
              "onUpdate:modelValue": ($event) => unref(form).password = $event,
              required: "",
              autocomplete: "current-password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.password
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4 block"${_scopeId}><label class="flex items-center"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$e, {
              name: "remember",
              checked: unref(form).remember,
              "onUpdate:checked": ($event) => unref(form).remember = $event
            }, null, _parent2, _scopeId));
            _push2(`<span class="ms-2 text-sm text-gray-600"${_scopeId}>Remember me</span></label></div><div class="mt-4 flex items-center justify-end"${_scopeId}>`);
            if (__props.canResetPassword) {
              _push2(ssrRenderComponent(unref(Link), {
                href: _ctx.route("password.request"),
                class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              }, {
                default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                  if (_push3) {
                    _push3(` Forgot your password? `);
                  } else {
                    return [
                      createTextVNode(" Forgot your password? ")
                    ];
                  }
                }),
                _: 1
              }, _parent2, _scopeId));
            } else {
              _push2(`<!---->`);
            }
            _push2(ssrRenderComponent(PrimaryButton, {
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Log in `);
                } else {
                  return [
                    createTextVNode(" Log in ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Log in" }),
              __props.status ? (openBlock(), createBlock("div", {
                key: 0,
                class: "mb-4 text-sm font-medium text-green-600"
              }, toDisplayString(__props.status), 1)) : createCommentVNode("", true),
              createVNode("form", {
                onSubmit: withModifiers(submit, ["prevent"])
              }, [
                createVNode("div", null, [
                  createVNode(_sfc_main$j, {
                    for: "email",
                    value: "Email"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "email",
                    type: "email",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).email,
                    "onUpdate:modelValue": ($event) => unref(form).email = $event,
                    required: "",
                    autofocus: "",
                    autocomplete: "username"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.email
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$j, {
                    for: "password",
                    value: "Password"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password",
                    type: "password",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).password,
                    "onUpdate:modelValue": ($event) => unref(form).password = $event,
                    required: "",
                    autocomplete: "current-password"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.password
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4 block" }, [
                  createVNode("label", { class: "flex items-center" }, [
                    createVNode(_sfc_main$e, {
                      name: "remember",
                      checked: unref(form).remember,
                      "onUpdate:checked": ($event) => unref(form).remember = $event
                    }, null, 8, ["checked", "onUpdate:checked"]),
                    createVNode("span", { class: "ms-2 text-sm text-gray-600" }, "Remember me")
                  ])
                ]),
                createVNode("div", { class: "mt-4 flex items-center justify-end" }, [
                  __props.canResetPassword ? (openBlock(), createBlock(unref(Link), {
                    key: 0,
                    href: _ctx.route("password.request"),
                    class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Forgot your password? ")
                    ]),
                    _: 1
                  }, 8, ["href"])) : createCommentVNode("", true),
                  createVNode(PrimaryButton, {
                    class: ["ms-4", { "opacity-25": unref(form).processing }],
                    disabled: unref(form).processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Log in ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"])
                ])
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$d = _sfc_main$d.setup;
_sfc_main$d.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/Login.vue");
  return _sfc_setup$d ? _sfc_setup$d(props, ctx) : void 0;
};
const __vite_glob_0_3 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$d
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$c = {
  __name: "Register",
  __ssrInlineRender: true,
  setup(__props) {
    const form = useForm({
      name: "",
      email: "",
      password: "",
      password_confirmation: ""
    });
    const submit = () => {
      form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation")
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$l, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Register" }, null, _parent2, _scopeId));
            _push2(`<form${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "name",
              value: "Name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "name",
              type: "text",
              class: "mt-1 block w-full",
              modelValue: unref(form).name,
              "onUpdate:modelValue": ($event) => unref(form).name = $event,
              required: "",
              autofocus: "",
              autocomplete: "name"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.name
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "email",
              value: "Email"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "email",
              type: "email",
              class: "mt-1 block w-full",
              modelValue: unref(form).email,
              "onUpdate:modelValue": ($event) => unref(form).email = $event,
              required: "",
              autocomplete: "username"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.email
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password",
              value: "Password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password",
              type: "password",
              class: "mt-1 block w-full",
              modelValue: unref(form).password,
              "onUpdate:modelValue": ($event) => unref(form).password = $event,
              required: "",
              autocomplete: "new-password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.password
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password_confirmation",
              value: "Confirm Password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password_confirmation",
              type: "password",
              class: "mt-1 block w-full",
              modelValue: unref(form).password_confirmation,
              "onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
              required: "",
              autocomplete: "new-password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.password_confirmation
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4 flex items-center justify-end"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(Link), {
              href: _ctx.route("login"),
              class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Already registered? `);
                } else {
                  return [
                    createTextVNode(" Already registered? ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(PrimaryButton, {
              class: ["ms-4", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Register `);
                } else {
                  return [
                    createTextVNode(" Register ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Register" }),
              createVNode("form", {
                onSubmit: withModifiers(submit, ["prevent"])
              }, [
                createVNode("div", null, [
                  createVNode(_sfc_main$j, {
                    for: "name",
                    value: "Name"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "name",
                    type: "text",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).name,
                    "onUpdate:modelValue": ($event) => unref(form).name = $event,
                    required: "",
                    autofocus: "",
                    autocomplete: "name"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.name
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$j, {
                    for: "email",
                    value: "Email"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "email",
                    type: "email",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).email,
                    "onUpdate:modelValue": ($event) => unref(form).email = $event,
                    required: "",
                    autocomplete: "username"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.email
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$j, {
                    for: "password",
                    value: "Password"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password",
                    type: "password",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).password,
                    "onUpdate:modelValue": ($event) => unref(form).password = $event,
                    required: "",
                    autocomplete: "new-password"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.password
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$j, {
                    for: "password_confirmation",
                    value: "Confirm Password"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password_confirmation",
                    type: "password",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).password_confirmation,
                    "onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
                    required: "",
                    autocomplete: "new-password"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.password_confirmation
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4 flex items-center justify-end" }, [
                  createVNode(unref(Link), {
                    href: _ctx.route("login"),
                    class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Already registered? ")
                    ]),
                    _: 1
                  }, 8, ["href"]),
                  createVNode(PrimaryButton, {
                    class: ["ms-4", { "opacity-25": unref(form).processing }],
                    disabled: unref(form).processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Register ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"])
                ])
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$c = _sfc_main$c.setup;
_sfc_main$c.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/Register.vue");
  return _sfc_setup$c ? _sfc_setup$c(props, ctx) : void 0;
};
const __vite_glob_0_4 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$c
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$b = {
  __name: "ResetPassword",
  __ssrInlineRender: true,
  props: {
    email: {
      type: String,
      required: true
    },
    token: {
      type: String,
      required: true
    }
  },
  setup(__props) {
    const props = __props;
    const form = useForm({
      token: props.token,
      email: props.email,
      password: "",
      password_confirmation: ""
    });
    const submit = () => {
      form.post(route("password.store"), {
        onFinish: () => form.reset("password", "password_confirmation")
      });
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$l, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Reset Password" }, null, _parent2, _scopeId));
            _push2(`<form${_scopeId}><div${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "email",
              value: "Email"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "email",
              type: "email",
              class: "mt-1 block w-full",
              modelValue: unref(form).email,
              "onUpdate:modelValue": ($event) => unref(form).email = $event,
              required: "",
              autofocus: "",
              autocomplete: "username"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.email
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password",
              value: "Password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password",
              type: "password",
              class: "mt-1 block w-full",
              modelValue: unref(form).password,
              "onUpdate:modelValue": ($event) => unref(form).password = $event,
              required: "",
              autocomplete: "new-password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.password
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password_confirmation",
              value: "Confirm Password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password_confirmation",
              type: "password",
              class: "mt-1 block w-full",
              modelValue: unref(form).password_confirmation,
              "onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
              required: "",
              autocomplete: "new-password"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              class: "mt-2",
              message: unref(form).errors.password_confirmation
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-4 flex items-center justify-end"${_scopeId}>`);
            _push2(ssrRenderComponent(PrimaryButton, {
              class: { "opacity-25": unref(form).processing },
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Reset Password `);
                } else {
                  return [
                    createTextVNode(" Reset Password ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Reset Password" }),
              createVNode("form", {
                onSubmit: withModifiers(submit, ["prevent"])
              }, [
                createVNode("div", null, [
                  createVNode(_sfc_main$j, {
                    for: "email",
                    value: "Email"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "email",
                    type: "email",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).email,
                    "onUpdate:modelValue": ($event) => unref(form).email = $event,
                    required: "",
                    autofocus: "",
                    autocomplete: "username"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.email
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$j, {
                    for: "password",
                    value: "Password"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password",
                    type: "password",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).password,
                    "onUpdate:modelValue": ($event) => unref(form).password = $event,
                    required: "",
                    autocomplete: "new-password"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.password
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4" }, [
                  createVNode(_sfc_main$j, {
                    for: "password_confirmation",
                    value: "Confirm Password"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password_confirmation",
                    type: "password",
                    class: "mt-1 block w-full",
                    modelValue: unref(form).password_confirmation,
                    "onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
                    required: "",
                    autocomplete: "new-password"
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    class: "mt-2",
                    message: unref(form).errors.password_confirmation
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-4 flex items-center justify-end" }, [
                  createVNode(PrimaryButton, {
                    class: { "opacity-25": unref(form).processing },
                    disabled: unref(form).processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Reset Password ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"])
                ])
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$b = _sfc_main$b.setup;
_sfc_main$b.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/ResetPassword.vue");
  return _sfc_setup$b ? _sfc_setup$b(props, ctx) : void 0;
};
const __vite_glob_0_5 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$b
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$a = {
  __name: "VerifyEmail",
  __ssrInlineRender: true,
  props: {
    status: {
      type: String
    }
  },
  setup(__props) {
    const props = __props;
    const form = useForm({});
    const submit = () => {
      form.post(route("verification.send"));
    };
    const verificationLinkSent = computed(
      () => props.status === "verification-link-sent"
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(ssrRenderComponent(_sfc_main$l, _attrs, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(ssrRenderComponent(unref(Head), { title: "Email Verification" }, null, _parent2, _scopeId));
            _push2(`<div class="mb-4 text-sm text-gray-600"${_scopeId}> Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn&#39;t receive the email, we will gladly send you another. </div>`);
            if (verificationLinkSent.value) {
              _push2(`<div class="mb-4 text-sm font-medium text-green-600"${_scopeId}> A new verification link has been sent to the email address you provided during registration. </div>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<form${_scopeId}><div class="mt-4 flex items-center justify-between"${_scopeId}>`);
            _push2(ssrRenderComponent(PrimaryButton, {
              class: { "opacity-25": unref(form).processing },
              disabled: unref(form).processing
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Resend Verification Email `);
                } else {
                  return [
                    createTextVNode(" Resend Verification Email ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(unref(Link), {
              href: _ctx.route("logout"),
              method: "post",
              as: "button",
              class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(`Log Out`);
                } else {
                  return [
                    createTextVNode("Log Out")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></form>`);
          } else {
            return [
              createVNode(unref(Head), { title: "Email Verification" }),
              createVNode("div", { class: "mb-4 text-sm text-gray-600" }, " Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another. "),
              verificationLinkSent.value ? (openBlock(), createBlock("div", {
                key: 0,
                class: "mb-4 text-sm font-medium text-green-600"
              }, " A new verification link has been sent to the email address you provided during registration. ")) : createCommentVNode("", true),
              createVNode("form", {
                onSubmit: withModifiers(submit, ["prevent"])
              }, [
                createVNode("div", { class: "mt-4 flex items-center justify-between" }, [
                  createVNode(PrimaryButton, {
                    class: { "opacity-25": unref(form).processing },
                    disabled: unref(form).processing
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Resend Verification Email ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"]),
                  createVNode(unref(Link), {
                    href: _ctx.route("logout"),
                    method: "post",
                    as: "button",
                    class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  }, {
                    default: withCtx(() => [
                      createTextVNode("Log Out")
                    ]),
                    _: 1
                  }, 8, ["href"])
                ])
              ], 32)
            ];
          }
        }),
        _: 1
      }, _parent));
    };
  }
};
const _sfc_setup$a = _sfc_main$a.setup;
_sfc_main$a.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Auth/VerifyEmail.vue");
  return _sfc_setup$a ? _sfc_setup$a(props, ctx) : void 0;
};
const __vite_glob_0_6 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$a
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$9 = {
  __name: "Dashboard",
  __ssrInlineRender: true,
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Dashboard" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$n, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<h2 class="text-xl font-semibold leading-tight text-gray-800"${_scopeId}> Dashboard </h2>`);
          } else {
            return [
              createVNode("h2", { class: "text-xl font-semibold leading-tight text-gray-800" }, " Dashboard ")
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="py-12"${_scopeId}><div class="mx-auto max-w-7xl sm:px-6 lg:px-8"${_scopeId}><div class="overflow-hidden bg-white shadow-sm sm:rounded-lg"${_scopeId}><div class="p-6 text-gray-900"${_scopeId}> You&#39;re logged in! </div></div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "mx-auto max-w-7xl sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "overflow-hidden bg-white shadow-sm sm:rounded-lg" }, [
                    createVNode("div", { class: "p-6 text-gray-900" }, " You're logged in! ")
                  ])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup$9 = _sfc_main$9.setup;
_sfc_main$9.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Dashboard.vue");
  return _sfc_setup$9 ? _sfc_setup$9(props, ctx) : void 0;
};
const __vite_glob_0_7 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$9
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$8 = {
  __name: "DevCli",
  __ssrInlineRender: true,
  setup(__props) {
    const command = ref("");
    const busy = ref(false);
    const output = ref([]);
    const cursor = ref(-1);
    const ENTITIES = [
      { key: "user", aliases: ["u"] },
      { key: "company", aliases: ["c", "co"] },
      { key: "bootstrap", aliases: [] }
      // special
    ];
    const ACTIONS = [
      { key: "add", aliases: [] },
      { key: "assign", aliases: ["ass"] },
      { key: "unassign", aliases: ["unass"] },
      { key: "delete", aliases: ["del", "rm"] }
    ];
    function tokens(str) {
      return str.trim().split(/\s+/).filter(Boolean);
    }
    function resolveEntity(tok) {
      const t4 = tok?.toLowerCase() || "";
      return ENTITIES.find((e2) => e2.key.startsWith(t4) || e2.aliases.some((a2) => a2.startsWith(t4)));
    }
    function suggestions() {
      const [t1, t22] = tokens(command.value);
      if (!t1) {
        return ENTITIES.map((e2) => e2.key);
      }
      const ent = resolveEntity(t1);
      if (!ent) {
        return ENTITIES.map((e2) => e2.key).filter((k2) => k2.startsWith(t1.toLowerCase()));
      }
      if (!t22 && ent.key !== "bootstrap") {
        return ACTIONS.map((a2) => a2.key);
      }
      if (ent.key === "bootstrap") {
        return [];
      }
      return [];
    }
    const sugg = computed(suggestions);
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "mx-auto max-w-5xl p-6" }, _attrs))}><h1 class="text-2xl font-semibold mb-4">Dev Console (local)</h1><div class="flex gap-2 relative"><input${ssrRenderAttr("value", command.value)} placeholder="c ass --email=jane@example.com --company=Acme --role=admin" class="flex-1 border rounded px-3 py-2" autocomplete="off"><button${ssrIncludeBooleanAttr(busy.value) ? " disabled" : ""} class="border rounded px-4 py-2">Run</button>`);
      if (sugg.value.length) {
        _push(`<div class="absolute top-full mt-1 w-full bg-white border rounded shadow z-10 max-h-56 overflow-auto"><!--[-->`);
        ssrRenderList(sugg.value, (s2, i2) => {
          _push(`<div class="${ssrRenderClass([i2 === cursor.value ? "bg-gray-100" : "", "px-3 py-2 text-sm cursor-pointer"])}">${ssrInterpolate(s2)}</div>`);
        });
        _push(`<!--]--></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div><div class="mt-6 space-y-3"><!--[-->`);
      ssrRenderList(output.value, (row, i2) => {
        _push(`<div class="border rounded"><div class="px-3 py-2 text-sm bg-gray-50 border-b font-mono"><span class="text-gray-500">${ssrInterpolate(row.at)}</span><span class="ml-2">› ${ssrInterpolate(row.cmd)}</span></div><pre class="px-3 py-3 text-sm overflow-auto">${ssrInterpolate(row.res)}</pre></div>`);
      });
      _push(`<!--]--></div></div>`);
    };
  }
};
const _sfc_setup$8 = _sfc_main$8.setup;
_sfc_main$8.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/DevCli.vue");
  return _sfc_setup$8 ? _sfc_setup$8(props, ctx) : void 0;
};
const __vite_glob_0_8 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$8
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$7 = {};
function _sfc_ssrRender(_ctx, _push, _parent, _attrs) {
  _push(`<button${ssrRenderAttrs(mergeProps({ class: "inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:bg-red-700" }, _attrs))}>`);
  ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
  _push(`</button>`);
}
const _sfc_setup$7 = _sfc_main$7.setup;
_sfc_main$7.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/DangerButton.vue");
  return _sfc_setup$7 ? _sfc_setup$7(props, ctx) : void 0;
};
const DangerButton = /* @__PURE__ */ _export_sfc(_sfc_main$7, [["ssrRender", _sfc_ssrRender]]);
const _sfc_main$6 = {
  __name: "Modal",
  __ssrInlineRender: true,
  props: {
    show: {
      type: Boolean,
      default: false
    },
    maxWidth: {
      type: String,
      default: "2xl"
    },
    closeable: {
      type: Boolean,
      default: true
    }
  },
  emits: ["close"],
  setup(__props, { emit: __emit }) {
    const props = __props;
    const emit = __emit;
    const dialog = ref();
    const showSlot = ref(props.show);
    watch(
      () => props.show,
      () => {
        if (props.show) {
          document.body.style.overflow = "hidden";
          showSlot.value = true;
          dialog.value?.showModal();
        } else {
          document.body.style.overflow = "";
          setTimeout(() => {
            dialog.value?.close();
            showSlot.value = false;
          }, 200);
        }
      }
    );
    const close = () => {
      if (props.closeable) {
        emit("close");
      }
    };
    const closeOnEscape = (e2) => {
      if (e2.key === "Escape") {
        e2.preventDefault();
        if (props.show) {
          close();
        }
      }
    };
    onMounted(() => document.addEventListener("keydown", closeOnEscape));
    onUnmounted(() => {
      document.removeEventListener("keydown", closeOnEscape);
      document.body.style.overflow = "";
    });
    const maxWidthClass = computed(() => {
      return {
        sm: "sm:max-w-sm",
        md: "sm:max-w-md",
        lg: "sm:max-w-lg",
        xl: "sm:max-w-xl",
        "2xl": "sm:max-w-2xl"
      }[props.maxWidth];
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<dialog${ssrRenderAttrs(mergeProps({
        class: "z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent",
        ref_key: "dialog",
        ref: dialog
      }, _attrs))}><div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" scroll-region><div style="${ssrRenderStyle(__props.show ? null : { display: "none" })}" class="fixed inset-0 transform transition-all"><div class="absolute inset-0 bg-gray-500 opacity-75"></div></div><div style="${ssrRenderStyle(__props.show ? null : { display: "none" })}" class="${ssrRenderClass([maxWidthClass.value, "mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:mx-auto sm:w-full"])}">`);
      if (showSlot.value) {
        ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></div></dialog>`);
    };
  }
};
const _sfc_setup$6 = _sfc_main$6.setup;
_sfc_main$6.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/Modal.vue");
  return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
const _sfc_main$5 = {
  __name: "SecondaryButton",
  __ssrInlineRender: true,
  props: {
    type: {
      type: String,
      default: "button"
    }
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<button${ssrRenderAttrs(mergeProps({
        type: __props.type,
        class: "inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
      }, _attrs))}>`);
      ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
      _push(`</button>`);
    };
  }
};
const _sfc_setup$5 = _sfc_main$5.setup;
_sfc_main$5.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/SecondaryButton.vue");
  return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
const _sfc_main$4 = {
  __name: "DeleteUserForm",
  __ssrInlineRender: true,
  setup(__props) {
    const confirmingUserDeletion = ref(false);
    const passwordInput = ref(null);
    const form = useForm({
      password: ""
    });
    const confirmUserDeletion = () => {
      confirmingUserDeletion.value = true;
      nextTick(() => passwordInput.value.focus());
    };
    const deleteUser = () => {
      form.delete(route("profile.destroy"), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset()
      });
    };
    const closeModal = () => {
      confirmingUserDeletion.value = false;
      form.clearErrors();
      form.reset();
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<section${ssrRenderAttrs(mergeProps({ class: "space-y-6" }, _attrs))}><header><h2 class="text-lg font-medium text-gray-900"> Delete Account </h2><p class="mt-1 text-sm text-gray-600"> Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain. </p></header>`);
      _push(ssrRenderComponent(DangerButton, { onClick: confirmUserDeletion }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`Delete Account`);
          } else {
            return [
              createTextVNode("Delete Account")
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(ssrRenderComponent(_sfc_main$6, {
        show: confirmingUserDeletion.value,
        onClose: closeModal
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="p-6"${_scopeId}><h2 class="text-lg font-medium text-gray-900"${_scopeId}> Are you sure you want to delete your account? </h2><p class="mt-1 text-sm text-gray-600"${_scopeId}> Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account. </p><div class="mt-6"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$j, {
              for: "password",
              value: "Password",
              class: "sr-only"
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$h, {
              id: "password",
              ref_key: "passwordInput",
              ref: passwordInput,
              modelValue: unref(form).password,
              "onUpdate:modelValue": ($event) => unref(form).password = $event,
              type: "password",
              class: "mt-1 block w-3/4",
              placeholder: "Password",
              onKeyup: deleteUser
            }, null, _parent2, _scopeId));
            _push2(ssrRenderComponent(_sfc_main$k, {
              message: unref(form).errors.password,
              class: "mt-2"
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="mt-6 flex justify-end"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$5, { onClick: closeModal }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Cancel `);
                } else {
                  return [
                    createTextVNode(" Cancel ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(ssrRenderComponent(DangerButton, {
              class: ["ms-3", { "opacity-25": unref(form).processing }],
              disabled: unref(form).processing,
              onClick: deleteUser
            }, {
              default: withCtx((_2, _push3, _parent3, _scopeId2) => {
                if (_push3) {
                  _push3(` Delete Account `);
                } else {
                  return [
                    createTextVNode(" Delete Account ")
                  ];
                }
              }),
              _: 1
            }, _parent2, _scopeId));
            _push2(`</div></div>`);
          } else {
            return [
              createVNode("div", { class: "p-6" }, [
                createVNode("h2", { class: "text-lg font-medium text-gray-900" }, " Are you sure you want to delete your account? "),
                createVNode("p", { class: "mt-1 text-sm text-gray-600" }, " Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account. "),
                createVNode("div", { class: "mt-6" }, [
                  createVNode(_sfc_main$j, {
                    for: "password",
                    value: "Password",
                    class: "sr-only"
                  }),
                  createVNode(_sfc_main$h, {
                    id: "password",
                    ref_key: "passwordInput",
                    ref: passwordInput,
                    modelValue: unref(form).password,
                    "onUpdate:modelValue": ($event) => unref(form).password = $event,
                    type: "password",
                    class: "mt-1 block w-3/4",
                    placeholder: "Password",
                    onKeyup: withKeys(deleteUser, ["enter"])
                  }, null, 8, ["modelValue", "onUpdate:modelValue"]),
                  createVNode(_sfc_main$k, {
                    message: unref(form).errors.password,
                    class: "mt-2"
                  }, null, 8, ["message"])
                ]),
                createVNode("div", { class: "mt-6 flex justify-end" }, [
                  createVNode(_sfc_main$5, { onClick: closeModal }, {
                    default: withCtx(() => [
                      createTextVNode(" Cancel ")
                    ]),
                    _: 1
                  }),
                  createVNode(DangerButton, {
                    class: ["ms-3", { "opacity-25": unref(form).processing }],
                    disabled: unref(form).processing,
                    onClick: deleteUser
                  }, {
                    default: withCtx(() => [
                      createTextVNode(" Delete Account ")
                    ]),
                    _: 1
                  }, 8, ["class", "disabled"])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</section>`);
    };
  }
};
const _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/DeleteUserForm.vue");
  return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
const __vite_glob_0_10 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$4
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$3 = {
  __name: "UpdatePasswordForm",
  __ssrInlineRender: true,
  setup(__props) {
    const passwordInput = ref(null);
    const currentPasswordInput = ref(null);
    const form = useForm({
      current_password: "",
      password: "",
      password_confirmation: ""
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<section${ssrRenderAttrs(_attrs)}><header><h2 class="text-lg font-medium text-gray-900"> Update Password </h2><p class="mt-1 text-sm text-gray-600"> Ensure your account is using a long, random password to stay secure. </p></header><form class="mt-6 space-y-6"><div>`);
      _push(ssrRenderComponent(_sfc_main$j, {
        for: "current_password",
        value: "Current Password"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$h, {
        id: "current_password",
        ref_key: "currentPasswordInput",
        ref: currentPasswordInput,
        modelValue: unref(form).current_password,
        "onUpdate:modelValue": ($event) => unref(form).current_password = $event,
        type: "password",
        class: "mt-1 block w-full",
        autocomplete: "current-password"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$k, {
        message: unref(form).errors.current_password,
        class: "mt-2"
      }, null, _parent));
      _push(`</div><div>`);
      _push(ssrRenderComponent(_sfc_main$j, {
        for: "password",
        value: "New Password"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$h, {
        id: "password",
        ref_key: "passwordInput",
        ref: passwordInput,
        modelValue: unref(form).password,
        "onUpdate:modelValue": ($event) => unref(form).password = $event,
        type: "password",
        class: "mt-1 block w-full",
        autocomplete: "new-password"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$k, {
        message: unref(form).errors.password,
        class: "mt-2"
      }, null, _parent));
      _push(`</div><div>`);
      _push(ssrRenderComponent(_sfc_main$j, {
        for: "password_confirmation",
        value: "Confirm Password"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$h, {
        id: "password_confirmation",
        modelValue: unref(form).password_confirmation,
        "onUpdate:modelValue": ($event) => unref(form).password_confirmation = $event,
        type: "password",
        class: "mt-1 block w-full",
        autocomplete: "new-password"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$k, {
        message: unref(form).errors.password_confirmation,
        class: "mt-2"
      }, null, _parent));
      _push(`</div><div class="flex items-center gap-4">`);
      _push(ssrRenderComponent(PrimaryButton, {
        disabled: unref(form).processing
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`Save`);
          } else {
            return [
              createTextVNode("Save")
            ];
          }
        }),
        _: 1
      }, _parent));
      if (unref(form).recentlySuccessful) {
        _push(`<p class="text-sm text-gray-600"> Saved. </p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></form></section>`);
    };
  }
};
const _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue");
  return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
const __vite_glob_0_11 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$3
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$2 = {
  __name: "UpdateProfileInformationForm",
  __ssrInlineRender: true,
  props: {
    mustVerifyEmail: {
      type: Boolean
    },
    status: {
      type: String
    }
  },
  setup(__props) {
    const user = usePage().props.auth.user;
    const form = useForm({
      name: user.name,
      email: user.email
    });
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<section${ssrRenderAttrs(_attrs)}><header><h2 class="text-lg font-medium text-gray-900"> Profile Information </h2><p class="mt-1 text-sm text-gray-600"> Update your account&#39;s profile information and email address. </p></header><form class="mt-6 space-y-6"><div>`);
      _push(ssrRenderComponent(_sfc_main$j, {
        for: "name",
        value: "Name"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$h, {
        id: "name",
        type: "text",
        class: "mt-1 block w-full",
        modelValue: unref(form).name,
        "onUpdate:modelValue": ($event) => unref(form).name = $event,
        required: "",
        autofocus: "",
        autocomplete: "name"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$k, {
        class: "mt-2",
        message: unref(form).errors.name
      }, null, _parent));
      _push(`</div><div>`);
      _push(ssrRenderComponent(_sfc_main$j, {
        for: "email",
        value: "Email"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$h, {
        id: "email",
        type: "email",
        class: "mt-1 block w-full",
        modelValue: unref(form).email,
        "onUpdate:modelValue": ($event) => unref(form).email = $event,
        required: "",
        autocomplete: "username"
      }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$k, {
        class: "mt-2",
        message: unref(form).errors.email
      }, null, _parent));
      _push(`</div>`);
      if (__props.mustVerifyEmail && unref(user).email_verified_at === null) {
        _push(`<div><p class="mt-2 text-sm text-gray-800"> Your email address is unverified. `);
        _push(ssrRenderComponent(unref(Link), {
          href: _ctx.route("verification.send"),
          method: "post",
          as: "button",
          class: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        }, {
          default: withCtx((_, _push2, _parent2, _scopeId) => {
            if (_push2) {
              _push2(` Click here to re-send the verification email. `);
            } else {
              return [
                createTextVNode(" Click here to re-send the verification email. ")
              ];
            }
          }),
          _: 1
        }, _parent));
        _push(`</p><div style="${ssrRenderStyle(__props.status === "verification-link-sent" ? null : { display: "none" })}" class="mt-2 text-sm font-medium text-green-600"> A new verification link has been sent to your email address. </div></div>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<div class="flex items-center gap-4">`);
      _push(ssrRenderComponent(PrimaryButton, {
        disabled: unref(form).processing
      }, {
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`Save`);
          } else {
            return [
              createTextVNode("Save")
            ];
          }
        }),
        _: 1
      }, _parent));
      if (unref(form).recentlySuccessful) {
        _push(`<p class="text-sm text-gray-600"> Saved. </p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div></form></section>`);
    };
  }
};
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue");
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const __vite_glob_0_12 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$2
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main$1 = {
  __name: "Edit",
  __ssrInlineRender: true,
  props: {
    mustVerifyEmail: {
      type: Boolean
    },
    status: {
      type: String
    }
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Profile" }, null, _parent));
      _push(ssrRenderComponent(_sfc_main$n, null, {
        header: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<h2 class="text-xl font-semibold leading-tight text-gray-800"${_scopeId}> Profile </h2>`);
          } else {
            return [
              createVNode("h2", { class: "text-xl font-semibold leading-tight text-gray-800" }, " Profile ")
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="py-12"${_scopeId}><div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"${_scopeId}><div class="bg-white p-4 shadow sm:rounded-lg sm:p-8"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$2, {
              "must-verify-email": __props.mustVerifyEmail,
              status: __props.status,
              class: "max-w-xl"
            }, null, _parent2, _scopeId));
            _push2(`</div><div class="bg-white p-4 shadow sm:rounded-lg sm:p-8"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$3, { class: "max-w-xl" }, null, _parent2, _scopeId));
            _push2(`</div><div class="bg-white p-4 shadow sm:rounded-lg sm:p-8"${_scopeId}>`);
            _push2(ssrRenderComponent(_sfc_main$4, { class: "max-w-xl" }, null, _parent2, _scopeId));
            _push2(`</div></div></div>`);
          } else {
            return [
              createVNode("div", { class: "py-12" }, [
                createVNode("div", { class: "mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8" }, [
                  createVNode("div", { class: "bg-white p-4 shadow sm:rounded-lg sm:p-8" }, [
                    createVNode(_sfc_main$2, {
                      "must-verify-email": __props.mustVerifyEmail,
                      status: __props.status,
                      class: "max-w-xl"
                    }, null, 8, ["must-verify-email", "status"])
                  ]),
                  createVNode("div", { class: "bg-white p-4 shadow sm:rounded-lg sm:p-8" }, [
                    createVNode(_sfc_main$3, { class: "max-w-xl" })
                  ]),
                  createVNode("div", { class: "bg-white p-4 shadow sm:rounded-lg sm:p-8" }, [
                    createVNode(_sfc_main$4, { class: "max-w-xl" })
                  ])
                ])
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`<!--]-->`);
    };
  }
};
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Profile/Edit.vue");
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const __vite_glob_0_9 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main$1
}, Symbol.toStringTag, { value: "Module" }));
const _sfc_main = {
  __name: "Welcome",
  __ssrInlineRender: true,
  props: {
    canLogin: {
      type: Boolean
    },
    canRegister: {
      type: Boolean
    },
    laravelVersion: {
      type: String,
      required: true
    },
    phpVersion: {
      type: String,
      required: true
    }
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Welcome" }, null, _parent));
      _push(`<div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50"><img id="background" class="absolute -left-20 top-0 max-w-[877px]" src="https://laravel.com/assets/img/welcome/background.svg"><div class="relative flex min-h-screen flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white"><div class="relative w-full max-w-2xl px-6 lg:max-w-7xl"><header class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3"><div class="flex lg:col-start-2 lg:justify-center"><svg class="h-12 w-auto text-white lg:h-16 lg:text-[#FF2D20]" viewBox="0 0 62 65" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M61.8548 14.6253C61.8778 14.7102 61.8895 14.7978 61.8897 14.8858V28.5615C61.8898 28.737 61.8434 28.9095 61.7554 29.0614C61.6675 29.2132 61.5409 29.3392 61.3887 29.4265L49.9104 36.0351V49.1337C49.9104 49.4902 49.7209 49.8192 49.4118 49.9987L25.4519 63.7916C25.3971 63.8227 25.3372 63.8427 25.2774 63.8639C25.255 63.8714 25.2338 63.8851 25.2101 63.8913C25.0426 63.9354 24.8666 63.9354 24.6991 63.8913C24.6716 63.8838 24.6467 63.8689 24.6205 63.8589C24.5657 63.8389 24.5084 63.8215 24.456 63.7916L0.501061 49.9987C0.348882 49.9113 0.222437 49.7853 0.134469 49.6334C0.0465019 49.4816 0.000120578 49.3092 0 49.1337L0 8.10652C0 8.01678 0.0124642 7.92953 0.0348998 7.84477C0.0423783 7.8161 0.0598282 7.78993 0.0697995 7.76126C0.0884958 7.70891 0.105946 7.65531 0.133367 7.6067C0.152063 7.5743 0.179485 7.54812 0.20192 7.51821C0.230588 7.47832 0.256763 7.43719 0.290416 7.40229C0.319084 7.37362 0.356476 7.35243 0.388883 7.32751C0.425029 7.29759 0.457436 7.26518 0.498568 7.2415L12.4779 0.345059C12.6296 0.257786 12.8015 0.211853 12.9765 0.211853C13.1515 0.211853 13.3234 0.257786 13.475 0.345059L25.4531 7.2415H25.4556C25.4955 7.26643 25.5292 7.29759 25.5653 7.32626C25.5977 7.35119 25.6339 7.37362 25.6625 7.40104C25.6974 7.43719 25.7224 7.47832 25.7523 7.51821C25.7735 7.54812 25.8021 7.5743 25.8196 7.6067C25.8483 7.65656 25.8645 7.70891 25.8844 7.76126C25.8944 7.78993 25.9118 7.8161 25.9193 7.84602C25.9423 7.93096 25.954 8.01853 25.9542 8.10652V33.7317L35.9355 27.9844V14.8846C35.9355 14.7973 35.948 14.7088 35.9704 14.6253C35.9792 14.5954 35.9954 14.5692 36.0053 14.5405C36.0253 14.4882 36.0427 14.4346 36.0702 14.386C36.0888 14.3536 36.1163 14.3274 36.1375 14.2975C36.1674 14.2576 36.1923 14.2165 36.2272 14.1816C36.2559 14.1529 36.292 14.1317 36.3244 14.1068C36.3618 14.0769 36.3942 14.0445 36.4341 14.0208L48.4147 7.12434C48.5663 7.03694 48.7383 6.99094 48.9133 6.99094C49.0883 6.99094 49.2602 7.03694 49.4118 7.12434L61.3899 14.0208C61.4323 14.0457 61.4647 14.0769 61.5021 14.1055C61.5333 14.1305 61.5694 14.1529 61.5981 14.1803C61.633 14.2165 61.6579 14.2576 61.6878 14.2975C61.7103 14.3274 61.7377 14.3536 61.7551 14.386C61.7838 14.4346 61.8 14.4882 61.8199 14.5405C61.8312 14.5692 61.8474 14.5954 61.8548 14.6253ZM59.893 27.9844V16.6121L55.7013 19.0252L49.9104 22.3593V33.7317L59.8942 27.9844H59.893ZM47.9149 48.5566V37.1768L42.2187 40.4299L25.953 49.7133V61.2003L47.9149 48.5566ZM1.99677 9.83281V48.5566L23.9562 61.199V49.7145L12.4841 43.2219L12.4804 43.2194L12.4754 43.2169C12.4368 43.1945 12.4044 43.1621 12.3682 43.1347C12.3371 43.1097 12.3009 43.0898 12.2735 43.0624L12.271 43.0586C12.2386 43.0275 12.2162 42.9888 12.1887 42.9539C12.1638 42.9203 12.1339 42.8916 12.114 42.8567L12.1127 42.853C12.0903 42.8156 12.0766 42.7707 12.0604 42.7283C12.0442 42.6909 12.023 42.656 12.013 42.6161C12.0005 42.5688 11.998 42.5177 11.9931 42.4691C11.9881 42.4317 11.9781 42.3943 11.9781 42.3569V15.5801L6.18848 12.2446L1.99677 9.83281ZM12.9777 2.36177L2.99764 8.10652L12.9752 13.8513L22.9541 8.10527L12.9752 2.36177H12.9777ZM18.1678 38.2138L23.9574 34.8809V9.83281L19.7657 12.2459L13.9749 15.5801V40.6281L18.1678 38.2138ZM48.9133 9.14105L38.9344 14.8858L48.9133 20.6305L58.8909 14.8846L48.9133 9.14105ZM47.9149 22.3593L42.124 19.0252L37.9323 16.6121V27.9844L43.7219 31.3174L47.9149 33.7317V22.3593ZM24.9533 47.987L39.59 39.631L46.9065 35.4555L36.9352 29.7145L25.4544 36.3242L14.9907 42.3482L24.9533 47.987Z" fill="currentColor"></path></svg></div>`);
      if (__props.canLogin) {
        _push(`<nav class="-mx-3 flex flex-1 justify-end">`);
        if (_ctx.$page.props.auth.user) {
          _push(ssrRenderComponent(unref(Link), {
            href: _ctx.route("dashboard"),
            class: "rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(` Dashboard `);
              } else {
                return [
                  createTextVNode(" Dashboard ")
                ];
              }
            }),
            _: 1
          }, _parent));
        } else {
          _push(`<!--[-->`);
          _push(ssrRenderComponent(unref(Link), {
            href: _ctx.route("login"),
            class: "rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
          }, {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(` Log in `);
              } else {
                return [
                  createTextVNode(" Log in ")
                ];
              }
            }),
            _: 1
          }, _parent));
          if (__props.canRegister) {
            _push(ssrRenderComponent(unref(Link), {
              href: _ctx.route("register"),
              class: "rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
            }, {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(` Register `);
                } else {
                  return [
                    createTextVNode(" Register ")
                  ];
                }
              }),
              _: 1
            }, _parent));
          } else {
            _push(`<!---->`);
          }
          _push(`<!--]-->`);
        }
        _push(`</nav>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</header><main class="mt-6"><div class="grid gap-6 lg:grid-cols-2 lg:gap-8"><a href="https://laravel.com/docs" id="docs-card" class="flex flex-col items-start gap-6 overflow-hidden rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] md:row-span-3 lg:p-10 lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"><div id="screenshot-container" class="relative flex w-full flex-1 items-stretch"><img src="https://laravel.com/assets/img/welcome/docs-light.svg" alt="Laravel documentation screenshot" class="aspect-video h-full w-full flex-1 rounded-[10px] object-cover object-top drop-shadow-[0px_4px_34px_rgba(0,0,0,0.06)] dark:hidden"><img src="https://laravel.com/assets/img/welcome/docs-dark.svg" alt="Laravel documentation screenshot" class="hidden aspect-video h-full w-full flex-1 rounded-[10px] object-cover object-top drop-shadow-[0px_4px_34px_rgba(0,0,0,0.25)] dark:block"><div class="absolute -bottom-16 -left-16 h-40 w-[calc(100%+8rem)] bg-gradient-to-b from-transparent via-white to-white dark:via-zinc-900 dark:to-zinc-900"></div></div><div class="relative flex items-center gap-6 lg:items-end"><div id="docs-card-content" class="flex items-start gap-6 lg:flex-col"><div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16"><svg class="size-5 sm:size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path fill="#FF2D20" d="M23 4a1 1 0 0 0-1.447-.894L12.224 7.77a.5.5 0 0 1-.448 0L2.447 3.106A1 1 0 0 0 1 4v13.382a1.99 1.99 0 0 0 1.105 1.79l9.448 4.728c.14.065.293.1.447.1.154-.005.306-.04.447-.105l9.453-4.724a1.99 1.99 0 0 0 1.1-1.789V4ZM3 6.023a.25.25 0 0 1 .362-.223l7.5 3.75a.251.251 0 0 1 .138.223v11.2a.25.25 0 0 1-.362.224l-7.5-3.75a.25.25 0 0 1-.138-.22V6.023Zm18 11.2a.25.25 0 0 1-.138.224l-7.5 3.75a.249.249 0 0 1-.329-.099.249.249 0 0 1-.033-.12V9.772a.251.251 0 0 1 .138-.224l7.5-3.75a.25.25 0 0 1 .362.224v11.2Z"></path><path fill="#FF2D20" d="m3.55 1.893 8 4.048a1.008 1.008 0 0 0 .9 0l8-4.048a1 1 0 0 0-.9-1.785l-7.322 3.706a.506.506 0 0 1-.452 0L4.454.108a1 1 0 0 0-.9 1.785H3.55Z"></path></svg></div><div class="pt-3 sm:pt-5 lg:pt-0"><h2 class="text-xl font-semibold text-black dark:text-white"> Documentation </h2><p class="mt-4 text-sm/relaxed"> Laravel has wonderful documentation covering every aspect of the framework. Whether you are a newcomer or have prior experience with Laravel, we recommend reading our documentation from beginning to end. </p></div></div><svg class="size-6 shrink-0 stroke-[#FF2D20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"></path></svg></div></a><a href="https://laracasts.com" class="flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"><div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16"><svg class="size-5 sm:size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><g fill="#FF2D20"><path d="M24 8.25a.5.5 0 0 0-.5-.5H.5a.5.5 0 0 0-.5.5v12a2.5 2.5 0 0 0 2.5 2.5h19a2.5 2.5 0 0 0 2.5-2.5v-12Zm-7.765 5.868a1.221 1.221 0 0 1 0 2.264l-6.626 2.776A1.153 1.153 0 0 1 8 18.123v-5.746a1.151 1.151 0 0 1 1.609-1.035l6.626 2.776ZM19.564 1.677a.25.25 0 0 0-.177-.427H15.6a.106.106 0 0 0-.072.03l-4.54 4.543a.25.25 0 0 0 .177.427h3.783c.027 0 .054-.01.073-.03l4.543-4.543ZM22.071 1.318a.047.047 0 0 0-.045.013l-4.492 4.492a.249.249 0 0 0 .038.385.25.25 0 0 0 .14.042h5.784a.5.5 0 0 0 .5-.5v-2a2.5 2.5 0 0 0-1.925-2.432ZM13.014 1.677a.25.25 0 0 0-.178-.427H9.101a.106.106 0 0 0-.073.03l-4.54 4.543a.25.25 0 0 0 .177.427H8.4a.106.106 0 0 0 .073-.03l4.54-4.543ZM6.513 1.677a.25.25 0 0 0-.177-.427H2.5A2.5 2.5 0 0 0 0 3.75v2a.5.5 0 0 0 .5.5h1.4a.106.106 0 0 0 .073-.03l4.54-4.543Z"></path></g></svg></div><div class="pt-3 sm:pt-5"><h2 class="text-xl font-semibold text-black dark:text-white"> Laracasts </h2><p class="mt-4 text-sm/relaxed"> Laracasts offers thousands of video tutorials on Laravel, PHP, and JavaScript development. Check them out, see for yourself, and massively level up your development skills in the process. </p></div><svg class="size-6 shrink-0 self-center stroke-[#FF2D20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"></path></svg></a><a href="https://laravel-news.com" class="flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]"><div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16"><svg class="size-5 sm:size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><g fill="#FF2D20"><path d="M8.75 4.5H5.5c-.69 0-1.25.56-1.25 1.25v4.75c0 .69.56 1.25 1.25 1.25h3.25c.69 0 1.25-.56 1.25-1.25V5.75c0-.69-.56-1.25-1.25-1.25Z"></path><path d="M24 10a3 3 0 0 0-3-3h-2V2.5a2 2 0 0 0-2-2H2a2 2 0 0 0-2 2V20a3.5 3.5 0 0 0 3.5 3.5h17A3.5 3.5 0 0 0 24 20V10ZM3.5 21.5A1.5 1.5 0 0 1 2 20V3a.5.5 0 0 1 .5-.5h14a.5.5 0 0 1 .5.5v17c0 .295.037.588.11.874a.5.5 0 0 1-.484.625L3.5 21.5ZM22 20a1.5 1.5 0 1 1-3 0V9.5a.5.5 0 0 1 .5-.5H21a1 1 0 0 1 1 1v10Z"></path><path d="M12.751 6.047h2a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-2A.75.75 0 0 1 12 7.3v-.5a.75.75 0 0 1 .751-.753ZM12.751 10.047h2a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-2A.75.75 0 0 1 12 11.3v-.5a.75.75 0 0 1 .751-.753ZM4.751 14.047h10a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-10A.75.75 0 0 1 4 15.3v-.5a.75.75 0 0 1 .751-.753ZM4.75 18.047h7.5a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-7.5A.75.75 0 0 1 4 19.3v-.5a.75.75 0 0 1 .75-.753Z"></path></g></svg></div><div class="pt-3 sm:pt-5"><h2 class="text-xl font-semibold text-black dark:text-white"> Laravel News </h2><p class="mt-4 text-sm/relaxed"> Laravel News is a community driven portal and newsletter aggregating all of the latest and most important news in the Laravel ecosystem, including new package releases and tutorials. </p></div><svg class="size-6 shrink-0 self-center stroke-[#FF2D20]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"></path></svg></a><div class="flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800"><div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16"><svg class="size-5 sm:size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><g fill="#FF2D20"><path d="M16.597 12.635a.247.247 0 0 0-.08-.237 2.234 2.234 0 0 1-.769-1.68c.001-.195.03-.39.084-.578a.25.25 0 0 0-.09-.267 8.8 8.8 0 0 0-4.826-1.66.25.25 0 0 0-.268.181 2.5 2.5 0 0 1-2.4 1.824.045.045 0 0 0-.045.037 12.255 12.255 0 0 0-.093 3.86.251.251 0 0 0 .208.214c2.22.366 4.367 1.08 6.362 2.118a.252.252 0 0 0 .32-.079 10.09 10.09 0 0 0 1.597-3.733ZM13.616 17.968a.25.25 0 0 0-.063-.407A19.697 19.697 0 0 0 8.91 15.98a.25.25 0 0 0-.287.325c.151.455.334.898.548 1.328.437.827.981 1.594 1.619 2.28a.249.249 0 0 0 .32.044 29.13 29.13 0 0 0 2.506-1.99ZM6.303 14.105a.25.25 0 0 0 .265-.274 13.048 13.048 0 0 1 .205-4.045.062.062 0 0 0-.022-.07 2.5 2.5 0 0 1-.777-.982.25.25 0 0 0-.271-.149 11 11 0 0 0-5.6 2.815.255.255 0 0 0-.075.163c-.008.135-.02.27-.02.406.002.8.084 1.598.246 2.381a.25.25 0 0 0 .303.193 19.924 19.924 0 0 1 5.746-.438ZM9.228 20.914a.25.25 0 0 0 .1-.393 11.53 11.53 0 0 1-1.5-2.22 12.238 12.238 0 0 1-.91-2.465.248.248 0 0 0-.22-.187 18.876 18.876 0 0 0-5.69.33.249.249 0 0 0-.179.336c.838 2.142 2.272 4 4.132 5.353a.254.254 0 0 0 .15.048c1.41-.01 2.807-.282 4.117-.802ZM18.93 12.957l-.005-.008a.25.25 0 0 0-.268-.082 2.21 2.21 0 0 1-.41.081.25.25 0 0 0-.217.2c-.582 2.66-2.127 5.35-5.75 7.843a.248.248 0 0 0-.09.299.25.25 0 0 0 .065.091 28.703 28.703 0 0 0 2.662 2.12.246.246 0 0 0 .209.037c2.579-.701 4.85-2.242 6.456-4.378a.25.25 0 0 0 .048-.189 13.51 13.51 0 0 0-2.7-6.014ZM5.702 7.058a.254.254 0 0 0 .2-.165A2.488 2.488 0 0 1 7.98 5.245a.093.093 0 0 0 .078-.062 19.734 19.734 0 0 1 3.055-4.74.25.25 0 0 0-.21-.41 12.009 12.009 0 0 0-10.4 8.558.25.25 0 0 0 .373.281 12.912 12.912 0 0 1 4.826-1.814ZM10.773 22.052a.25.25 0 0 0-.28-.046c-.758.356-1.55.635-2.365.833a.25.25 0 0 0-.022.48c1.252.43 2.568.65 3.893.65.1 0 .2 0 .3-.008a.25.25 0 0 0 .147-.444c-.526-.424-1.1-.917-1.673-1.465ZM18.744 8.436a.249.249 0 0 0 .15.228 2.246 2.246 0 0 1 1.352 2.054c0 .337-.08.67-.23.972a.25.25 0 0 0 .042.28l.007.009a15.016 15.016 0 0 1 2.52 4.6.25.25 0 0 0 .37.132.25.25 0 0 0 .096-.114c.623-1.464.944-3.039.945-4.63a12.005 12.005 0 0 0-5.78-10.258.25.25 0 0 0-.373.274c.547 2.109.85 4.274.901 6.453ZM9.61 5.38a.25.25 0 0 0 .08.31c.34.24.616.561.8.935a.25.25 0 0 0 .3.127.631.631 0 0 1 .206-.034c2.054.078 4.036.772 5.69 1.991a.251.251 0 0 0 .267.024c.046-.024.093-.047.141-.067a.25.25 0 0 0 .151-.23A29.98 29.98 0 0 0 15.957.764a.25.25 0 0 0-.16-.164 11.924 11.924 0 0 0-2.21-.518.252.252 0 0 0-.215.076A22.456 22.456 0 0 0 9.61 5.38Z"></path></g></svg></div><div class="pt-3 sm:pt-5"><h2 class="text-xl font-semibold text-black dark:text-white"> Vibrant Ecosystem </h2><p class="mt-4 text-sm/relaxed"> Laravel&#39;s robust library of first-party tools and libraries, such as <a href="https://forge.laravel.com" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white dark:focus-visible:ring-[#FF2D20]">Forge</a>, <a href="https://vapor.laravel.com" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Vapor</a>, <a href="https://nova.laravel.com" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Nova</a>, <a href="https://envoyer.io" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Envoyer</a>, and <a href="https://herd.laravel.com" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Herd</a> help you take your projects to the next level. Pair them with powerful open source libraries like <a href="https://laravel.com/docs/billing" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Cashier</a>, <a href="https://laravel.com/docs/dusk" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Dusk</a>, <a href="https://laravel.com/docs/broadcasting" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Echo</a>, <a href="https://laravel.com/docs/horizon" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Horizon</a>, <a href="https://laravel.com/docs/sanctum" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Sanctum</a>, <a href="https://laravel.com/docs/telescope" class="rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white">Telescope</a>, and more. </p></div></div></div></main><footer class="py-16 text-center text-sm text-black dark:text-white/70"> Laravel v${ssrInterpolate(__props.laravelVersion)} (PHP v${ssrInterpolate(__props.phpVersion)}) </footer></div></div></div><!--]-->`);
    };
  }
};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Welcome.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const __vite_glob_0_13 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: _sfc_main
}, Symbol.toStringTag, { value: "Module" }));
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
window.axios.defaults.withCredentials = true;
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
  window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
}
function t() {
  return t = Object.assign ? Object.assign.bind() : function(t4) {
    for (var e2 = 1; e2 < arguments.length; e2++) {
      var r2 = arguments[e2];
      for (var n2 in r2) ({}).hasOwnProperty.call(r2, n2) && (t4[n2] = r2[n2]);
    }
    return t4;
  }, t.apply(null, arguments);
}
var e = String.prototype.replace, r = /%20/g, n = "RFC3986", o = { default: n, formatters: { RFC1738: function(t4) {
  return e.call(t4, r, "+");
}, RFC3986: function(t4) {
  return String(t4);
} }, RFC1738: "RFC1738" }, i = Object.prototype.hasOwnProperty, u = Array.isArray, a = (function() {
  for (var t4 = [], e2 = 0; e2 < 256; ++e2) t4.push("%" + ((e2 < 16 ? "0" : "") + e2.toString(16)).toUpperCase());
  return t4;
})(), s = function(t4, e2) {
  for (var r2 = e2 && e2.plainObjects ? /* @__PURE__ */ Object.create(null) : {}, n2 = 0; n2 < t4.length; ++n2) void 0 !== t4[n2] && (r2[n2] = t4[n2]);
  return r2;
}, f = { arrayToObject: s, assign: function(t4, e2) {
  return Object.keys(e2).reduce(function(t5, r2) {
    return t5[r2] = e2[r2], t5;
  }, t4);
}, combine: function(t4, e2) {
  return [].concat(t4, e2);
}, compact: function(t4) {
  for (var e2 = [{ obj: { o: t4 }, prop: "o" }], r2 = [], n2 = 0; n2 < e2.length; ++n2) for (var o2 = e2[n2], i2 = o2.obj[o2.prop], a2 = Object.keys(i2), s2 = 0; s2 < a2.length; ++s2) {
    var f2 = a2[s2], c2 = i2[f2];
    "object" == typeof c2 && null !== c2 && -1 === r2.indexOf(c2) && (e2.push({ obj: i2, prop: f2 }), r2.push(c2));
  }
  return (function(t5) {
    for (; t5.length > 1; ) {
      var e3 = t5.pop(), r3 = e3.obj[e3.prop];
      if (u(r3)) {
        for (var n3 = [], o3 = 0; o3 < r3.length; ++o3) void 0 !== r3[o3] && n3.push(r3[o3]);
        e3.obj[e3.prop] = n3;
      }
    }
  })(e2), t4;
}, decode: function(t4, e2, r2) {
  var n2 = t4.replace(/\+/g, " ");
  if ("iso-8859-1" === r2) return n2.replace(/%[0-9a-f]{2}/gi, unescape);
  try {
    return decodeURIComponent(n2);
  } catch (t5) {
    return n2;
  }
}, encode: function(t4, e2, r2, n2, i2) {
  if (0 === t4.length) return t4;
  var u2 = t4;
  if ("symbol" == typeof t4 ? u2 = Symbol.prototype.toString.call(t4) : "string" != typeof t4 && (u2 = String(t4)), "iso-8859-1" === r2) return escape(u2).replace(/%u[0-9a-f]{4}/gi, function(t5) {
    return "%26%23" + parseInt(t5.slice(2), 16) + "%3B";
  });
  for (var s2 = "", f2 = 0; f2 < u2.length; ++f2) {
    var c2 = u2.charCodeAt(f2);
    45 === c2 || 46 === c2 || 95 === c2 || 126 === c2 || c2 >= 48 && c2 <= 57 || c2 >= 65 && c2 <= 90 || c2 >= 97 && c2 <= 122 || i2 === o.RFC1738 && (40 === c2 || 41 === c2) ? s2 += u2.charAt(f2) : c2 < 128 ? s2 += a[c2] : c2 < 2048 ? s2 += a[192 | c2 >> 6] + a[128 | 63 & c2] : c2 < 55296 || c2 >= 57344 ? s2 += a[224 | c2 >> 12] + a[128 | c2 >> 6 & 63] + a[128 | 63 & c2] : (c2 = 65536 + ((1023 & c2) << 10 | 1023 & u2.charCodeAt(f2 += 1)), s2 += a[240 | c2 >> 18] + a[128 | c2 >> 12 & 63] + a[128 | c2 >> 6 & 63] + a[128 | 63 & c2]);
  }
  return s2;
}, isBuffer: function(t4) {
  return !(!t4 || "object" != typeof t4 || !(t4.constructor && t4.constructor.isBuffer && t4.constructor.isBuffer(t4)));
}, isRegExp: function(t4) {
  return "[object RegExp]" === Object.prototype.toString.call(t4);
}, maybeMap: function(t4, e2) {
  if (u(t4)) {
    for (var r2 = [], n2 = 0; n2 < t4.length; n2 += 1) r2.push(e2(t4[n2]));
    return r2;
  }
  return e2(t4);
}, merge: function t2(e2, r2, n2) {
  if (!r2) return e2;
  if ("object" != typeof r2) {
    if (u(e2)) e2.push(r2);
    else {
      if (!e2 || "object" != typeof e2) return [e2, r2];
      (n2 && (n2.plainObjects || n2.allowPrototypes) || !i.call(Object.prototype, r2)) && (e2[r2] = true);
    }
    return e2;
  }
  if (!e2 || "object" != typeof e2) return [e2].concat(r2);
  var o2 = e2;
  return u(e2) && !u(r2) && (o2 = s(e2, n2)), u(e2) && u(r2) ? (r2.forEach(function(r3, o3) {
    if (i.call(e2, o3)) {
      var u2 = e2[o3];
      u2 && "object" == typeof u2 && r3 && "object" == typeof r3 ? e2[o3] = t2(u2, r3, n2) : e2.push(r3);
    } else e2[o3] = r3;
  }), e2) : Object.keys(r2).reduce(function(e3, o3) {
    var u2 = r2[o3];
    return e3[o3] = i.call(e3, o3) ? t2(e3[o3], u2, n2) : u2, e3;
  }, o2);
} }, c = Object.prototype.hasOwnProperty, l = { brackets: function(t4) {
  return t4 + "[]";
}, comma: "comma", indices: function(t4, e2) {
  return t4 + "[" + e2 + "]";
}, repeat: function(t4) {
  return t4;
} }, p = Array.isArray, h = String.prototype.split, y = Array.prototype.push, d = function(t4, e2) {
  y.apply(t4, p(e2) ? e2 : [e2]);
}, g = Date.prototype.toISOString, b = o.default, v = { addQueryPrefix: false, allowDots: false, charset: "utf-8", charsetSentinel: false, delimiter: "&", encode: true, encoder: f.encode, encodeValuesOnly: false, format: b, formatter: o.formatters[b], indices: false, serializeDate: function(t4) {
  return g.call(t4);
}, skipNulls: false, strictNullHandling: false }, m = function t3(e2, r2, n2, o2, i2, u2, a2, s2, c2, l2, y2, g2, b2, m2) {
  var j2, w2 = e2;
  if ("function" == typeof a2 ? w2 = a2(r2, w2) : w2 instanceof Date ? w2 = l2(w2) : "comma" === n2 && p(w2) && (w2 = f.maybeMap(w2, function(t4) {
    return t4 instanceof Date ? l2(t4) : t4;
  })), null === w2) {
    if (o2) return u2 && !b2 ? u2(r2, v.encoder, m2, "key", y2) : r2;
    w2 = "";
  }
  if ("string" == typeof (j2 = w2) || "number" == typeof j2 || "boolean" == typeof j2 || "symbol" == typeof j2 || "bigint" == typeof j2 || f.isBuffer(w2)) {
    if (u2) {
      var $2 = b2 ? r2 : u2(r2, v.encoder, m2, "key", y2);
      if ("comma" === n2 && b2) {
        for (var O2 = h.call(String(w2), ","), E2 = "", R2 = 0; R2 < O2.length; ++R2) E2 += (0 === R2 ? "" : ",") + g2(u2(O2[R2], v.encoder, m2, "value", y2));
        return [g2($2) + "=" + E2];
      }
      return [g2($2) + "=" + g2(u2(w2, v.encoder, m2, "value", y2))];
    }
    return [g2(r2) + "=" + g2(String(w2))];
  }
  var S2, x2 = [];
  if (void 0 === w2) return x2;
  if ("comma" === n2 && p(w2)) S2 = [{ value: w2.length > 0 ? w2.join(",") || null : void 0 }];
  else if (p(a2)) S2 = a2;
  else {
    var N2 = Object.keys(w2);
    S2 = s2 ? N2.sort(s2) : N2;
  }
  for (var T2 = 0; T2 < S2.length; ++T2) {
    var k2 = S2[T2], C = "object" == typeof k2 && void 0 !== k2.value ? k2.value : w2[k2];
    if (!i2 || null !== C) {
      var _ = p(w2) ? "function" == typeof n2 ? n2(r2, k2) : r2 : r2 + (c2 ? "." + k2 : "[" + k2 + "]");
      d(x2, t3(C, _, n2, o2, i2, u2, a2, s2, c2, l2, y2, g2, b2, m2));
    }
  }
  return x2;
}, j = Object.prototype.hasOwnProperty, w = Array.isArray, $ = { allowDots: false, allowPrototypes: false, arrayLimit: 20, charset: "utf-8", charsetSentinel: false, comma: false, decoder: f.decode, delimiter: "&", depth: 5, ignoreQueryPrefix: false, interpretNumericEntities: false, parameterLimit: 1e3, parseArrays: true, plainObjects: false, strictNullHandling: false }, O = function(t4) {
  return t4.replace(/&#(\d+);/g, function(t5, e2) {
    return String.fromCharCode(parseInt(e2, 10));
  });
}, E = function(t4, e2) {
  return t4 && "string" == typeof t4 && e2.comma && t4.indexOf(",") > -1 ? t4.split(",") : t4;
}, R = function(t4, e2, r2, n2) {
  if (t4) {
    var o2 = r2.allowDots ? t4.replace(/\.([^.[]+)/g, "[$1]") : t4, i2 = /(\[[^[\]]*])/g, u2 = r2.depth > 0 && /(\[[^[\]]*])/.exec(o2), a2 = u2 ? o2.slice(0, u2.index) : o2, s2 = [];
    if (a2) {
      if (!r2.plainObjects && j.call(Object.prototype, a2) && !r2.allowPrototypes) return;
      s2.push(a2);
    }
    for (var f2 = 0; r2.depth > 0 && null !== (u2 = i2.exec(o2)) && f2 < r2.depth; ) {
      if (f2 += 1, !r2.plainObjects && j.call(Object.prototype, u2[1].slice(1, -1)) && !r2.allowPrototypes) return;
      s2.push(u2[1]);
    }
    return u2 && s2.push("[" + o2.slice(u2.index) + "]"), (function(t5, e3, r3, n3) {
      for (var o3 = n3 ? e3 : E(e3, r3), i3 = t5.length - 1; i3 >= 0; --i3) {
        var u3, a3 = t5[i3];
        if ("[]" === a3 && r3.parseArrays) u3 = [].concat(o3);
        else {
          u3 = r3.plainObjects ? /* @__PURE__ */ Object.create(null) : {};
          var s3 = "[" === a3.charAt(0) && "]" === a3.charAt(a3.length - 1) ? a3.slice(1, -1) : a3, f3 = parseInt(s3, 10);
          r3.parseArrays || "" !== s3 ? !isNaN(f3) && a3 !== s3 && String(f3) === s3 && f3 >= 0 && r3.parseArrays && f3 <= r3.arrayLimit ? (u3 = [])[f3] = o3 : "__proto__" !== s3 && (u3[s3] = o3) : u3 = { 0: o3 };
        }
        o3 = u3;
      }
      return o3;
    })(s2, e2, r2, n2);
  }
}, S = function(t4, e2) {
  var r2 = /* @__PURE__ */ (function(t5) {
    return $;
  })();
  if ("" === t4 || null == t4) return r2.plainObjects ? /* @__PURE__ */ Object.create(null) : {};
  for (var n2 = "string" == typeof t4 ? (function(t5, e3) {
    var r3, n3 = {}, o3 = (e3.ignoreQueryPrefix ? t5.replace(/^\?/, "") : t5).split(e3.delimiter, Infinity === e3.parameterLimit ? void 0 : e3.parameterLimit), i3 = -1, u3 = e3.charset;
    if (e3.charsetSentinel) for (r3 = 0; r3 < o3.length; ++r3) 0 === o3[r3].indexOf("utf8=") && ("utf8=%E2%9C%93" === o3[r3] ? u3 = "utf-8" : "utf8=%26%2310003%3B" === o3[r3] && (u3 = "iso-8859-1"), i3 = r3, r3 = o3.length);
    for (r3 = 0; r3 < o3.length; ++r3) if (r3 !== i3) {
      var a3, s3, c2 = o3[r3], l2 = c2.indexOf("]="), p2 = -1 === l2 ? c2.indexOf("=") : l2 + 1;
      -1 === p2 ? (a3 = e3.decoder(c2, $.decoder, u3, "key"), s3 = e3.strictNullHandling ? null : "") : (a3 = e3.decoder(c2.slice(0, p2), $.decoder, u3, "key"), s3 = f.maybeMap(E(c2.slice(p2 + 1), e3), function(t6) {
        return e3.decoder(t6, $.decoder, u3, "value");
      })), s3 && e3.interpretNumericEntities && "iso-8859-1" === u3 && (s3 = O(s3)), c2.indexOf("[]=") > -1 && (s3 = w(s3) ? [s3] : s3), n3[a3] = j.call(n3, a3) ? f.combine(n3[a3], s3) : s3;
    }
    return n3;
  })(t4, r2) : t4, o2 = r2.plainObjects ? /* @__PURE__ */ Object.create(null) : {}, i2 = Object.keys(n2), u2 = 0; u2 < i2.length; ++u2) {
    var a2 = i2[u2], s2 = R(a2, n2[a2], r2, "string" == typeof t4);
    o2 = f.merge(o2, s2, r2);
  }
  return f.compact(o2);
};
class x {
  constructor(t4, e2, r2) {
    var n2, o2;
    this.name = t4, this.definition = e2, this.bindings = null != (n2 = e2.bindings) ? n2 : {}, this.wheres = null != (o2 = e2.wheres) ? o2 : {}, this.config = r2;
  }
  get template() {
    const t4 = `${this.origin}/${this.definition.uri}`.replace(/\/+$/, "");
    return "" === t4 ? "/" : t4;
  }
  get origin() {
    return this.config.absolute ? this.definition.domain ? `${this.config.url.match(/^\w+:\/\//)[0]}${this.definition.domain}${this.config.port ? `:${this.config.port}` : ""}` : this.config.url : "";
  }
  get parameterSegments() {
    var t4, e2;
    return null != (t4 = null == (e2 = this.template.match(/{[^}?]+\??}/g)) ? void 0 : e2.map((t5) => ({ name: t5.replace(/{|\??}/g, ""), required: !/\?}$/.test(t5) }))) ? t4 : [];
  }
  matchesUrl(t4) {
    var e2;
    if (!this.definition.methods.includes("GET")) return false;
    const r2 = this.template.replace(/[.*+$()[\]]/g, "\\$&").replace(/(\/?){([^}?]*)(\??)}/g, (t5, e3, r3, n3) => {
      var o3;
      const i3 = `(?<${r3}>${(null == (o3 = this.wheres[r3]) ? void 0 : o3.replace(/(^\^)|(\$$)/g, "")) || "[^/?]+"})`;
      return n3 ? `(${e3}${i3})?` : `${e3}${i3}`;
    }).replace(/^\w+:\/\//, ""), [n2, o2] = t4.replace(/^\w+:\/\//, "").split("?"), i2 = null != (e2 = new RegExp(`^${r2}/?$`).exec(n2)) ? e2 : new RegExp(`^${r2}/?$`).exec(decodeURI(n2));
    if (i2) {
      for (const t5 in i2.groups) i2.groups[t5] = "string" == typeof i2.groups[t5] ? decodeURIComponent(i2.groups[t5]) : i2.groups[t5];
      return { params: i2.groups, query: S(o2) };
    }
    return false;
  }
  compile(t4) {
    return this.parameterSegments.length ? this.template.replace(/{([^}?]+)(\??)}/g, (e2, r2, n2) => {
      var o2, i2;
      if (!n2 && [null, void 0].includes(t4[r2])) throw new Error(`Ziggy error: '${r2}' parameter is required for route '${this.name}'.`);
      if (this.wheres[r2] && !new RegExp(`^${n2 ? `(${this.wheres[r2]})?` : this.wheres[r2]}$`).test(null != (i2 = t4[r2]) ? i2 : "")) throw new Error(`Ziggy error: '${r2}' parameter '${t4[r2]}' does not match required format '${this.wheres[r2]}' for route '${this.name}'.`);
      return encodeURI(null != (o2 = t4[r2]) ? o2 : "").replace(/%7C/g, "|").replace(/%25/g, "%").replace(/\$/g, "%24");
    }).replace(this.config.absolute ? /(\.[^/]+?)(\/\/)/ : /(^)(\/\/)/, "$1/").replace(/\/+$/, "") : this.template;
  }
}
class N extends String {
  constructor(e2, r2, n2 = true, o2) {
    if (super(), this.t = null != o2 ? o2 : "undefined" != typeof Ziggy ? Ziggy : null == globalThis ? void 0 : globalThis.Ziggy, this.t = t({}, this.t, { absolute: n2 }), e2) {
      if (!this.t.routes[e2]) throw new Error(`Ziggy error: route '${e2}' is not in the route list.`);
      this.i = new x(e2, this.t.routes[e2], this.t), this.u = this.l(r2);
    }
  }
  toString() {
    const e2 = Object.keys(this.u).filter((t4) => !this.i.parameterSegments.some(({ name: e3 }) => e3 === t4)).filter((t4) => "_query" !== t4).reduce((e3, r2) => t({}, e3, { [r2]: this.u[r2] }), {});
    return this.i.compile(this.u) + (function(t4, e3) {
      var r2, n2 = t4, i2 = (function(t5) {
        if (!t5) return v;
        if (null != t5.encoder && "function" != typeof t5.encoder) throw new TypeError("Encoder has to be a function.");
        var e4 = t5.charset || v.charset;
        if (void 0 !== t5.charset && "utf-8" !== t5.charset && "iso-8859-1" !== t5.charset) throw new TypeError("The charset option must be either utf-8, iso-8859-1, or undefined");
        var r3 = o.default;
        if (void 0 !== t5.format) {
          if (!c.call(o.formatters, t5.format)) throw new TypeError("Unknown format option provided.");
          r3 = t5.format;
        }
        var n3 = o.formatters[r3], i3 = v.filter;
        return ("function" == typeof t5.filter || p(t5.filter)) && (i3 = t5.filter), { addQueryPrefix: "boolean" == typeof t5.addQueryPrefix ? t5.addQueryPrefix : v.addQueryPrefix, allowDots: void 0 === t5.allowDots ? v.allowDots : !!t5.allowDots, charset: e4, charsetSentinel: "boolean" == typeof t5.charsetSentinel ? t5.charsetSentinel : v.charsetSentinel, delimiter: void 0 === t5.delimiter ? v.delimiter : t5.delimiter, encode: "boolean" == typeof t5.encode ? t5.encode : v.encode, encoder: "function" == typeof t5.encoder ? t5.encoder : v.encoder, encodeValuesOnly: "boolean" == typeof t5.encodeValuesOnly ? t5.encodeValuesOnly : v.encodeValuesOnly, filter: i3, format: r3, formatter: n3, serializeDate: "function" == typeof t5.serializeDate ? t5.serializeDate : v.serializeDate, skipNulls: "boolean" == typeof t5.skipNulls ? t5.skipNulls : v.skipNulls, sort: "function" == typeof t5.sort ? t5.sort : null, strictNullHandling: "boolean" == typeof t5.strictNullHandling ? t5.strictNullHandling : v.strictNullHandling };
      })(e3);
      "function" == typeof i2.filter ? n2 = (0, i2.filter)("", n2) : p(i2.filter) && (r2 = i2.filter);
      var u2 = [];
      if ("object" != typeof n2 || null === n2) return "";
      var a2 = l[e3 && e3.arrayFormat in l ? e3.arrayFormat : e3 && "indices" in e3 ? e3.indices ? "indices" : "repeat" : "indices"];
      r2 || (r2 = Object.keys(n2)), i2.sort && r2.sort(i2.sort);
      for (var s2 = 0; s2 < r2.length; ++s2) {
        var f2 = r2[s2];
        i2.skipNulls && null === n2[f2] || d(u2, m(n2[f2], f2, a2, i2.strictNullHandling, i2.skipNulls, i2.encode ? i2.encoder : null, i2.filter, i2.sort, i2.allowDots, i2.serializeDate, i2.format, i2.formatter, i2.encodeValuesOnly, i2.charset));
      }
      var h2 = u2.join(i2.delimiter), y2 = true === i2.addQueryPrefix ? "?" : "";
      return i2.charsetSentinel && (y2 += "iso-8859-1" === i2.charset ? "utf8=%26%2310003%3B&" : "utf8=%E2%9C%93&"), h2.length > 0 ? y2 + h2 : "";
    })(t({}, e2, this.u._query), { addQueryPrefix: true, arrayFormat: "indices", encodeValuesOnly: true, skipNulls: true, encoder: (t4, e3) => "boolean" == typeof t4 ? Number(t4) : e3(t4) });
  }
  p(e2) {
    e2 ? this.t.absolute && e2.startsWith("/") && (e2 = this.h().host + e2) : e2 = this.v();
    let r2 = {};
    const [n2, o2] = Object.entries(this.t.routes).find(([t4, n3]) => r2 = new x(t4, n3, this.t).matchesUrl(e2)) || [void 0, void 0];
    return t({ name: n2 }, r2, { route: o2 });
  }
  v() {
    const { host: t4, pathname: e2, search: r2 } = this.h();
    return (this.t.absolute ? t4 + e2 : e2.replace(this.t.url.replace(/^\w*:\/\/[^/]+/, ""), "").replace(/^\/+/, "/")) + r2;
  }
  current(e2, r2) {
    const { name: n2, params: o2, query: i2, route: u2 } = this.p();
    if (!e2) return n2;
    const a2 = new RegExp(`^${e2.replace(/\./g, "\\.").replace(/\*/g, ".*")}$`).test(n2);
    if ([null, void 0].includes(r2) || !a2) return a2;
    const s2 = new x(n2, u2, this.t);
    r2 = this.l(r2, s2);
    const f2 = t({}, o2, i2);
    if (Object.values(r2).every((t4) => !t4) && !Object.values(f2).some((t4) => void 0 !== t4)) return true;
    const c2 = (t4, e3) => Object.entries(t4).every(([t5, r3]) => Array.isArray(r3) && Array.isArray(e3[t5]) ? r3.every((r4) => e3[t5].includes(r4)) : "object" == typeof r3 && "object" == typeof e3[t5] && null !== r3 && null !== e3[t5] ? c2(r3, e3[t5]) : e3[t5] == r3);
    return c2(r2, f2);
  }
  h() {
    var t4, e2, r2, n2, o2, i2;
    const { host: u2 = "", pathname: a2 = "", search: s2 = "" } = "undefined" != typeof window ? window.location : {};
    return { host: null != (t4 = null == (e2 = this.t.location) ? void 0 : e2.host) ? t4 : u2, pathname: null != (r2 = null == (n2 = this.t.location) ? void 0 : n2.pathname) ? r2 : a2, search: null != (o2 = null == (i2 = this.t.location) ? void 0 : i2.search) ? o2 : s2 };
  }
  get params() {
    const { params: e2, query: r2 } = this.p();
    return t({}, e2, r2);
  }
  get routeParams() {
    return this.p().params;
  }
  get queryParams() {
    return this.p().query;
  }
  has(t4) {
    return this.t.routes.hasOwnProperty(t4);
  }
  l(e2 = {}, r2 = this.i) {
    null != e2 || (e2 = {}), e2 = ["string", "number"].includes(typeof e2) ? [e2] : e2;
    const n2 = r2.parameterSegments.filter(({ name: t4 }) => !this.t.defaults[t4]);
    return Array.isArray(e2) ? e2 = e2.reduce((e3, r3, o2) => t({}, e3, n2[o2] ? { [n2[o2].name]: r3 } : "object" == typeof r3 ? r3 : { [r3]: "" }), {}) : 1 !== n2.length || e2[n2[0].name] || !e2.hasOwnProperty(Object.values(r2.bindings)[0]) && !e2.hasOwnProperty("id") || (e2 = { [n2[0].name]: e2 }), t({}, this.m(r2), this.j(e2, r2));
  }
  m(e2) {
    return e2.parameterSegments.filter(({ name: t4 }) => this.t.defaults[t4]).reduce((e3, { name: r2 }, n2) => t({}, e3, { [r2]: this.t.defaults[r2] }), {});
  }
  j(e2, { bindings: r2, parameterSegments: n2 }) {
    return Object.entries(e2).reduce((e3, [o2, i2]) => {
      if (!i2 || "object" != typeof i2 || Array.isArray(i2) || !n2.some(({ name: t4 }) => t4 === o2)) return t({}, e3, { [o2]: i2 });
      if (!i2.hasOwnProperty(r2[o2])) {
        if (!i2.hasOwnProperty("id")) throw new Error(`Ziggy error: object passed as '${o2}' parameter is missing route model binding key '${r2[o2]}'.`);
        r2[o2] = "id";
      }
      return t({}, e3, { [o2]: i2[r2[o2]] });
    }, {});
  }
  valueOf() {
    return this.toString();
  }
}
function T(t4, e2, r2, n2) {
  const o2 = new N(t4, e2, r2, n2);
  return t4 ? o2.toString() : o2;
}
const k = { install(t4, e2) {
  const r2 = (t5, r3, n2, o2 = e2) => T(t5, r3, n2, o2);
  parseInt(t4.version) > 2 ? (t4.config.globalProperties.route = r2, t4.provide("route", r2)) : t4.mixin({ methods: { route: r2 } });
} };
createInertiaApp({
  resolve: (name) => {
    const pages = /* @__PURE__ */ Object.assign({ "./Pages/Admin/Dashboard.vue": __vite_glob_0_0, "./Pages/Auth/ConfirmPassword.vue": __vite_glob_0_1, "./Pages/Auth/ForgotPassword.vue": __vite_glob_0_2, "./Pages/Auth/Login.vue": __vite_glob_0_3, "./Pages/Auth/Register.vue": __vite_glob_0_4, "./Pages/Auth/ResetPassword.vue": __vite_glob_0_5, "./Pages/Auth/VerifyEmail.vue": __vite_glob_0_6, "./Pages/Dashboard.vue": __vite_glob_0_7, "./Pages/DevCli.vue": __vite_glob_0_8, "./Pages/Profile/Edit.vue": __vite_glob_0_9, "./Pages/Profile/Partials/DeleteUserForm.vue": __vite_glob_0_10, "./Pages/Profile/Partials/UpdatePasswordForm.vue": __vite_glob_0_11, "./Pages/Profile/Partials/UpdateProfileInformationForm.vue": __vite_glob_0_12, "./Pages/Welcome.vue": __vite_glob_0_13 });
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    const vue = createApp({ render: () => h$1(App, props) });
    vue.use(plugin);
    vue.use(k, {
      ...props.initialPage.props.ziggy,
      location: new URL(props.initialPage.props.ziggy.location)
    });
    vue.mount(el);
  }
});
axios.defaults.withCredentials = true;
axios.interceptors.request.use((config) => {
  const cid = window.localStorage.getItem("currentCompanyId");
  if (cid) config.headers["X-Company-Id"] = cid;
  return config;
});
