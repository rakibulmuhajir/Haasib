import{B as pe,c as h,i as M,o as f,D as L,m as F,A as m,G as re,t as V,L as _,M as oe,I as $e,H as $,C as ze,Y as Ae,W as Fe,X as Ee,Z as Re,$ as je,N as Be,a0 as G,S as H,y as Ue,d as u,T as Ne,n as q,a as l,F as ee,J as se,w as O,a1 as Ge,a2 as He,h as qe,b as E,j as Xe,q as Je,r as K,k as z,s as We,e as c,p as Ye}from"./app-DjFlEUH4.js";import{c as Qe,f as W,R as Ze,e as _e,g as et,C as tt,x as ae,O as nt,s as D,a as X}from"./index-DL9KtRp0.js";import{a as Oe,s as J}from"./index-CXOcYUhh.js";import{s as le}from"./index-CyPBiJ0T.js";import{s as fe}from"./index-424M1LzA.js";import{s as me}from"./index-CEuCntvK.js";import{d as it,e as ot,f as at,s as lt}from"./index-zYy9pXTp.js";import{s as he}from"./index-jrbmM0En.js";import{s as rt}from"./index-CLnL3lam.js";import{s as st}from"./index-CwhCYogN.js";import{C as dt}from"./CompanySwitcher-DfMLVd5D.js";import{C as ut}from"./CommandPalette-BP-Quzwc.js";import{_ as pt}from"./_plugin-vue_export-helper-DlAUqK2U.js";import"./index-CyCOy2Ll.js";import"./index-CKALDcO3.js";import"./index-Cl6iUony.js";import"./index-di-bTznt.js";var ct=`
    .p-chip {
        display: inline-flex;
        align-items: center;
        background: dt('chip.background');
        color: dt('chip.color');
        border-radius: dt('chip.border.radius');
        padding-block: dt('chip.padding.y');
        padding-inline: dt('chip.padding.x');
        gap: dt('chip.gap');
    }

    .p-chip-icon {
        color: dt('chip.icon.color');
        font-size: dt('chip.icon.font.size');
        width: dt('chip.icon.size');
        height: dt('chip.icon.size');
    }

    .p-chip-image {
        border-radius: 50%;
        width: dt('chip.image.width');
        height: dt('chip.image.height');
        margin-inline-start: calc(-1 * dt('chip.padding.y'));
    }

    .p-chip:has(.p-chip-remove-icon) {
        padding-inline-end: dt('chip.padding.y');
    }

    .p-chip:has(.p-chip-image) {
        padding-block-start: calc(dt('chip.padding.y') / 2);
        padding-block-end: calc(dt('chip.padding.y') / 2);
    }

    .p-chip-remove-icon {
        cursor: pointer;
        font-size: dt('chip.remove.icon.size');
        width: dt('chip.remove.icon.size');
        height: dt('chip.remove.icon.size');
        color: dt('chip.remove.icon.color');
        border-radius: 50%;
        transition:
            outline-color dt('chip.transition.duration'),
            box-shadow dt('chip.transition.duration');
        outline-color: transparent;
    }

    .p-chip-remove-icon:focus-visible {
        box-shadow: dt('chip.remove.icon.focus.ring.shadow');
        outline: dt('chip.remove.icon.focus.ring.width') dt('chip.remove.icon.focus.ring.style') dt('chip.remove.icon.focus.ring.color');
        outline-offset: dt('chip.remove.icon.focus.ring.offset');
    }
`,ft={root:"p-chip p-component",image:"p-chip-image",icon:"p-chip-icon",label:"p-chip-label",removeIcon:"p-chip-remove-icon"},mt=pe.extend({name:"chip",style:ct,classes:ft}),ht={name:"BaseChip",extends:Qe,props:{label:{type:[String,Number],default:null},icon:{type:String,default:null},image:{type:String,default:null},removable:{type:Boolean,default:!1},removeIcon:{type:String,default:void 0}},style:mt,provide:function(){return{$pcChip:this,$parentInstance:this}}},we={name:"Chip",extends:ht,inheritAttrs:!1,emits:["remove"],data:function(){return{visible:!0}},methods:{onKeydown:function(e){(e.key==="Enter"||e.key==="Backspace")&&this.close(e)},close:function(e){this.visible=!1,this.$emit("remove",e)}},computed:{dataP:function(){return W({removable:this.removable})}},components:{TimesCircleIcon:it}},vt=["aria-label","data-p"],yt=["src"];function bt(t,e,n,a,r,i){return r.visible?(f(),h("div",m({key:0,class:t.cx("root"),"aria-label":t.label},t.ptmi("root"),{"data-p":i.dataP}),[L(t.$slots,"default",{},function(){return[t.image?(f(),h("img",m({key:0,src:t.image},t.ptm("image"),{class:t.cx("image")}),null,16,yt)):t.$slots.icon?(f(),F(re(t.$slots.icon),m({key:1,class:t.cx("icon")},t.ptm("icon")),null,16,["class"])):t.icon?(f(),h("span",m({key:2,class:[t.cx("icon"),t.icon]},t.ptm("icon")),null,16)):M("",!0),t.label!==null?(f(),h("div",m({key:3,class:t.cx("label")},t.ptm("label")),V(t.label),17)):M("",!0)]}),t.removable?L(t.$slots,"removeicon",{key:0,removeCallback:i.close,keydownCallback:i.onKeydown},function(){return[(f(),F(re(t.removeIcon?"span":"TimesCircleIcon"),m({class:[t.cx("removeIcon"),t.removeIcon],onClick:i.close,onKeydown:i.onKeydown},t.ptm("removeIcon")),null,16,["class","onClick","onKeydown"]))]}):M("",!0)],16,vt)):M("",!0)}we.render=bt;var gt=`
    .p-textarea {
        font-family: inherit;
        font-feature-settings: inherit;
        font-size: 1rem;
        color: dt('textarea.color');
        background: dt('textarea.background');
        padding-block: dt('textarea.padding.y');
        padding-inline: dt('textarea.padding.x');
        border: 1px solid dt('textarea.border.color');
        transition:
            background dt('textarea.transition.duration'),
            color dt('textarea.transition.duration'),
            border-color dt('textarea.transition.duration'),
            outline-color dt('textarea.transition.duration'),
            box-shadow dt('textarea.transition.duration');
        appearance: none;
        border-radius: dt('textarea.border.radius');
        outline-color: transparent;
        box-shadow: dt('textarea.shadow');
    }

    .p-textarea:enabled:hover {
        border-color: dt('textarea.hover.border.color');
    }

    .p-textarea:enabled:focus {
        border-color: dt('textarea.focus.border.color');
        box-shadow: dt('textarea.focus.ring.shadow');
        outline: dt('textarea.focus.ring.width') dt('textarea.focus.ring.style') dt('textarea.focus.ring.color');
        outline-offset: dt('textarea.focus.ring.offset');
    }

    .p-textarea.p-invalid {
        border-color: dt('textarea.invalid.border.color');
    }

    .p-textarea.p-variant-filled {
        background: dt('textarea.filled.background');
    }

    .p-textarea.p-variant-filled:enabled:hover {
        background: dt('textarea.filled.hover.background');
    }

    .p-textarea.p-variant-filled:enabled:focus {
        background: dt('textarea.filled.focus.background');
    }

    .p-textarea:disabled {
        opacity: 1;
        background: dt('textarea.disabled.background');
        color: dt('textarea.disabled.color');
    }

    .p-textarea::placeholder {
        color: dt('textarea.placeholder.color');
    }

    .p-textarea.p-invalid::placeholder {
        color: dt('textarea.invalid.placeholder.color');
    }

    .p-textarea-fluid {
        width: 100%;
    }

    .p-textarea-resizable {
        overflow: hidden;
        resize: none;
    }

    .p-textarea-sm {
        font-size: dt('textarea.sm.font.size');
        padding-block: dt('textarea.sm.padding.y');
        padding-inline: dt('textarea.sm.padding.x');
    }

    .p-textarea-lg {
        font-size: dt('textarea.lg.font.size');
        padding-block: dt('textarea.lg.padding.y');
        padding-inline: dt('textarea.lg.padding.x');
    }
`,Ot={root:function(e){var n=e.instance,a=e.props;return["p-textarea p-component",{"p-filled":n.$filled,"p-textarea-resizable ":a.autoResize,"p-textarea-sm p-inputfield-sm":a.size==="small","p-textarea-lg p-inputfield-lg":a.size==="large","p-invalid":n.$invalid,"p-variant-filled":n.$variant==="filled","p-textarea-fluid":n.$fluid}]}},wt=pe.extend({name:"textarea",style:gt,classes:Ot}),xt={name:"BaseTextarea",extends:Oe,props:{autoResize:Boolean},style:wt,provide:function(){return{$pcTextarea:this,$parentInstance:this}}};function Y(t){"@babel/helpers - typeof";return Y=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},Y(t)}function It(t,e,n){return(e=kt(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function kt(t){var e=St(t,"string");return Y(e)=="symbol"?e:e+""}function St(t,e){if(Y(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var a=n.call(t,e);if(Y(a)!="object")return a;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var te={name:"Textarea",extends:xt,inheritAttrs:!1,observer:null,mounted:function(){var e=this;this.autoResize&&(this.observer=new ResizeObserver(function(){requestAnimationFrame(function(){e.resize()})}),this.observer.observe(this.$el))},updated:function(){this.autoResize&&this.resize()},beforeUnmount:function(){this.observer&&this.observer.disconnect()},methods:{resize:function(){if(this.$el.offsetParent){var e=this.$el.style.height,n=parseInt(e)||0,a=this.$el.scrollHeight,r=!n||a>n,i=n&&a<n;i?(this.$el.style.height="auto",this.$el.style.height="".concat(this.$el.scrollHeight,"px")):r&&(this.$el.style.height="".concat(a,"px"))}},onInput:function(e){this.autoResize&&this.resize(),this.writeValue(e.target.value,e)}},computed:{attrs:function(){return m(this.ptmi("root",{context:{filled:this.$filled,disabled:this.disabled}}),this.formField)},dataP:function(){return W(It({invalid:this.$invalid,fluid:this.$fluid,filled:this.$variant==="filled"},this.size,this.size))}}},Ct=["value","name","disabled","aria-invalid","data-p"];function Vt(t,e,n,a,r,i){return f(),h("textarea",m({class:t.cx("root"),value:t.d_value,name:t.name,disabled:t.disabled,"aria-invalid":t.invalid||void 0,"data-p":i.dataP,onInput:e[0]||(e[0]=function(){return i.onInput&&i.onInput.apply(i,arguments)})},i.attrs),null,16,Ct)}te.render=Vt;var Lt=`
    .p-autocomplete {
        display: inline-flex;
    }

    .p-autocomplete-loader {
        position: absolute;
        top: 50%;
        margin-top: -0.5rem;
        inset-inline-end: dt('autocomplete.padding.x');
    }

    .p-autocomplete:has(.p-autocomplete-dropdown) .p-autocomplete-loader {
        inset-inline-end: calc(dt('autocomplete.dropdown.width') + dt('autocomplete.padding.x'));
    }

    .p-autocomplete:has(.p-autocomplete-dropdown) .p-autocomplete-input {
        flex: 1 1 auto;
        width: 1%;
    }

    .p-autocomplete:has(.p-autocomplete-dropdown) .p-autocomplete-input,
    .p-autocomplete:has(.p-autocomplete-dropdown) .p-autocomplete-input-multiple {
        border-start-end-radius: 0;
        border-end-end-radius: 0;
    }

    .p-autocomplete-dropdown {
        cursor: pointer;
        display: inline-flex;
        user-select: none;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        width: dt('autocomplete.dropdown.width');
        border-start-end-radius: dt('autocomplete.dropdown.border.radius');
        border-end-end-radius: dt('autocomplete.dropdown.border.radius');
        background: dt('autocomplete.dropdown.background');
        border: 1px solid dt('autocomplete.dropdown.border.color');
        border-inline-start: 0 none;
        color: dt('autocomplete.dropdown.color');
        transition:
            background dt('autocomplete.transition.duration'),
            color dt('autocomplete.transition.duration'),
            border-color dt('autocomplete.transition.duration'),
            outline-color dt('autocomplete.transition.duration'),
            box-shadow dt('autocomplete.transition.duration');
        outline-color: transparent;
    }

    .p-autocomplete-dropdown:not(:disabled):hover {
        background: dt('autocomplete.dropdown.hover.background');
        border-color: dt('autocomplete.dropdown.hover.border.color');
        color: dt('autocomplete.dropdown.hover.color');
    }

    .p-autocomplete-dropdown:not(:disabled):active {
        background: dt('autocomplete.dropdown.active.background');
        border-color: dt('autocomplete.dropdown.active.border.color');
        color: dt('autocomplete.dropdown.active.color');
    }

    .p-autocomplete-dropdown:focus-visible {
        box-shadow: dt('autocomplete.dropdown.focus.ring.shadow');
        outline: dt('autocomplete.dropdown.focus.ring.width') dt('autocomplete.dropdown.focus.ring.style') dt('autocomplete.dropdown.focus.ring.color');
        outline-offset: dt('autocomplete.dropdown.focus.ring.offset');
    }

    .p-autocomplete-overlay {
        position: absolute;
        top: 0;
        left: 0;
        background: dt('autocomplete.overlay.background');
        color: dt('autocomplete.overlay.color');
        border: 1px solid dt('autocomplete.overlay.border.color');
        border-radius: dt('autocomplete.overlay.border.radius');
        box-shadow: dt('autocomplete.overlay.shadow');
        min-width: 100%;
    }

    .p-autocomplete-list-container {
        overflow: auto;
    }

    .p-autocomplete-list {
        margin: 0;
        list-style-type: none;
        display: flex;
        flex-direction: column;
        gap: dt('autocomplete.list.gap');
        padding: dt('autocomplete.list.padding');
    }

    .p-autocomplete-option {
        cursor: pointer;
        white-space: nowrap;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        padding: dt('autocomplete.option.padding');
        border: 0 none;
        color: dt('autocomplete.option.color');
        background: transparent;
        transition:
            background dt('autocomplete.transition.duration'),
            color dt('autocomplete.transition.duration'),
            border-color dt('autocomplete.transition.duration');
        border-radius: dt('autocomplete.option.border.radius');
    }

    .p-autocomplete-option:not(.p-autocomplete-option-selected):not(.p-disabled).p-focus {
        background: dt('autocomplete.option.focus.background');
        color: dt('autocomplete.option.focus.color');
    }

    .p-autocomplete-option-selected {
        background: dt('autocomplete.option.selected.background');
        color: dt('autocomplete.option.selected.color');
    }

    .p-autocomplete-option-selected.p-focus {
        background: dt('autocomplete.option.selected.focus.background');
        color: dt('autocomplete.option.selected.focus.color');
    }

    .p-autocomplete-option-group {
        margin: 0;
        padding: dt('autocomplete.option.group.padding');
        color: dt('autocomplete.option.group.color');
        background: dt('autocomplete.option.group.background');
        font-weight: dt('autocomplete.option.group.font.weight');
    }

    .p-autocomplete-input-multiple {
        margin: 0;
        list-style-type: none;
        cursor: text;
        overflow: hidden;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        padding: calc(dt('autocomplete.padding.y') / 2) dt('autocomplete.padding.x');
        gap: calc(dt('autocomplete.padding.y') / 2);
        color: dt('autocomplete.color');
        background: dt('autocomplete.background');
        border: 1px solid dt('autocomplete.border.color');
        border-radius: dt('autocomplete.border.radius');
        width: 100%;
        transition:
            background dt('autocomplete.transition.duration'),
            color dt('autocomplete.transition.duration'),
            border-color dt('autocomplete.transition.duration'),
            outline-color dt('autocomplete.transition.duration'),
            box-shadow dt('autocomplete.transition.duration');
        outline-color: transparent;
        box-shadow: dt('autocomplete.shadow');
    }

    .p-autocomplete-input-multiple.p-disabled {
        opacity: 1;
        background: dt('inputtext.disabled.background');
        color: dt('inputtext.disabled.color');
    }

    .p-autocomplete:not(.p-disabled):hover .p-autocomplete-input-multiple {
        border-color: dt('autocomplete.hover.border.color');
    }

    .p-autocomplete:not(.p-disabled).p-focus .p-autocomplete-input-multiple {
        border-color: dt('autocomplete.focus.border.color');
        box-shadow: dt('autocomplete.focus.ring.shadow');
        outline: dt('autocomplete.focus.ring.width') dt('autocomplete.focus.ring.style') dt('autocomplete.focus.ring.color');
        outline-offset: dt('autocomplete.focus.ring.offset');
    }

    .p-autocomplete.p-invalid .p-autocomplete-input-multiple {
        border-color: dt('autocomplete.invalid.border.color');
    }

    .p-variant-filled.p-autocomplete-input-multiple {
        background: dt('autocomplete.filled.background');
    }

    .p-autocomplete:not(.p-disabled):hover .p-variant-filled.p-autocomplete-input-multiple {
        background: dt('autocomplete.filled.hover.background');
    }

    .p-autocomplete:not(.p-disabled).p-focus .p-variant-filled.p-autocomplete-input-multiple {
        background: dt('autocomplete.filled.focus.background');
    }

    .p-autocomplete.p-disabled .p-autocomplete-input-multiple {
        opacity: 1;
        background: dt('autocomplete.disabled.background');
        color: dt('autocomplete.disabled.color');
    }

    .p-autocomplete-chip.p-chip {
        padding-block-start: calc(dt('autocomplete.padding.y') / 2);
        padding-block-end: calc(dt('autocomplete.padding.y') / 2);
        border-radius: dt('autocomplete.chip.border.radius');
    }

    .p-autocomplete-input-multiple:has(.p-autocomplete-chip) {
        padding-inline-start: calc(dt('autocomplete.padding.y') / 2);
        padding-inline-end: calc(dt('autocomplete.padding.y') / 2);
    }

    .p-autocomplete-chip-item.p-focus .p-autocomplete-chip {
        background: dt('autocomplete.chip.focus.background');
        color: dt('autocomplete.chip.focus.color');
    }

    .p-autocomplete-input-chip {
        flex: 1 1 auto;
        display: inline-flex;
        padding-block-start: calc(dt('autocomplete.padding.y') / 2);
        padding-block-end: calc(dt('autocomplete.padding.y') / 2);
    }

    .p-autocomplete-input-chip input {
        border: 0 none;
        outline: 0 none;
        background: transparent;
        margin: 0;
        padding: 0;
        box-shadow: none;
        border-radius: 0;
        width: 100%;
        font-family: inherit;
        font-feature-settings: inherit;
        font-size: 1rem;
        color: inherit;
    }

    .p-autocomplete-input-chip input::placeholder {
        color: dt('autocomplete.placeholder.color');
    }

    .p-autocomplete.p-invalid .p-autocomplete-input-chip input::placeholder {
        color: dt('autocomplete.invalid.placeholder.color');
    }

    .p-autocomplete-empty-message {
        padding: dt('autocomplete.empty.message.padding');
    }

    .p-autocomplete-fluid {
        display: flex;
    }

    .p-autocomplete-fluid:has(.p-autocomplete-dropdown) .p-autocomplete-input {
        width: 1%;
    }

    .p-autocomplete:has(.p-inputtext-sm) .p-autocomplete-dropdown {
        width: dt('autocomplete.dropdown.sm.width');
    }

    .p-autocomplete:has(.p-inputtext-sm) .p-autocomplete-dropdown .p-icon {
        font-size: dt('form.field.sm.font.size');
        width: dt('form.field.sm.font.size');
        height: dt('form.field.sm.font.size');
    }

    .p-autocomplete:has(.p-inputtext-lg) .p-autocomplete-dropdown {
        width: dt('autocomplete.dropdown.lg.width');
    }

    .p-autocomplete:has(.p-inputtext-lg) .p-autocomplete-dropdown .p-icon {
        font-size: dt('form.field.lg.font.size');
        width: dt('form.field.lg.font.size');
        height: dt('form.field.lg.font.size');
    }

    .p-autocomplete-clear-icon {
        position: absolute;
        top: 50%;
        margin-top: -0.5rem;
        cursor: pointer;
        color: dt('autocomplete.dropdown.color');
        inset-inline-end: dt('autocomplete.padding.x');
    }

    .p-autocomplete:has(.p-autocomplete-dropdown) .p-autocomplete-clear-icon {
        inset-inline-end: calc(dt('autocomplete.padding.x') + dt('autocomplete.dropdown.width'));
    }
`,Mt={root:{position:"relative"}},Kt={root:function(e){var n=e.instance;return["p-autocomplete p-component p-inputwrapper",{"p-invalid":n.$invalid,"p-focus":n.focused,"p-inputwrapper-filled":n.$filled||_(n.inputValue),"p-inputwrapper-focus":n.focused,"p-autocomplete-open":n.overlayVisible,"p-autocomplete-fluid":n.$fluid}]},pcInputText:"p-autocomplete-input",inputMultiple:function(e){var n=e.instance,a=e.props;return["p-autocomplete-input-multiple",{"p-variant-filled":n.$variant==="filled","p-disabled":a.disabled}]},chipItem:function(e){var n=e.instance,a=e.i;return["p-autocomplete-chip-item",{"p-focus":n.focusedMultipleOptionIndex===a}]},pcChip:"p-autocomplete-chip",chipIcon:"p-autocomplete-chip-icon",inputChip:"p-autocomplete-input-chip",loader:"p-autocomplete-loader",dropdown:"p-autocomplete-dropdown",overlay:"p-autocomplete-overlay p-component",listContainer:"p-autocomplete-list-container",list:"p-autocomplete-list",optionGroup:"p-autocomplete-option-group",option:function(e){var n=e.instance,a=e.option,r=e.i,i=e.getItemOptions;return["p-autocomplete-option",{"p-autocomplete-option-selected":n.isSelected(a),"p-focus":n.focusedOptionIndex===n.getOptionIndex(r,i),"p-disabled":n.isOptionDisabled(a)}]},emptyMessage:"p-autocomplete-empty-message"},Dt=pe.extend({name:"autocomplete",style:Lt,classes:Kt,inlineStyles:Mt}),Tt={name:"BaseAutoComplete",extends:Oe,props:{suggestions:{type:Array,default:null},optionLabel:null,optionDisabled:null,optionGroupLabel:null,optionGroupChildren:null,scrollHeight:{type:String,default:"14rem"},dropdown:{type:Boolean,default:!1},dropdownMode:{type:String,default:"blank"},multiple:{type:Boolean,default:!1},loading:{type:Boolean,default:!1},placeholder:{type:String,default:null},dataKey:{type:String,default:null},minLength:{type:Number,default:1},delay:{type:Number,default:300},appendTo:{type:[String,Object],default:"body"},forceSelection:{type:Boolean,default:!1},completeOnFocus:{type:Boolean,default:!1},inputId:{type:String,default:null},inputStyle:{type:Object,default:null},inputClass:{type:[String,Object],default:null},panelStyle:{type:Object,default:null},panelClass:{type:[String,Object],default:null},overlayStyle:{type:Object,default:null},overlayClass:{type:[String,Object],default:null},dropdownIcon:{type:String,default:null},dropdownClass:{type:[String,Object],default:null},loader:{type:String,default:null},loadingIcon:{type:String,default:null},removeTokenIcon:{type:String,default:null},chipIcon:{type:String,default:null},virtualScrollerOptions:{type:Object,default:null},autoOptionFocus:{type:Boolean,default:!1},selectOnFocus:{type:Boolean,default:!1},focusOnHover:{type:Boolean,default:!0},searchLocale:{type:String,default:void 0},searchMessage:{type:String,default:null},selectionMessage:{type:String,default:null},emptySelectionMessage:{type:String,default:null},emptySearchMessage:{type:String,default:null},showEmptyMessage:{type:Boolean,default:!0},tabindex:{type:Number,default:0},typeahead:{type:Boolean,default:!0},ariaLabel:{type:String,default:null},ariaLabelledby:{type:String,default:null}},style:Dt,provide:function(){return{$pcAutoComplete:this,$parentInstance:this}}};function ve(t,e,n){return(e=Pt(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function Pt(t){var e=$t(t,"string");return R(e)=="symbol"?e:e+""}function $t(t,e){if(R(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var a=n.call(t,e);if(R(a)!="object")return a;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}function R(t){"@babel/helpers - typeof";return R=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},R(t)}function ye(t){return Et(t)||Ft(t)||At(t)||zt()}function zt(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function At(t,e){if(t){if(typeof t=="string")return de(t,e);var n={}.toString.call(t).slice(8,-1);return n==="Object"&&t.constructor&&(n=t.constructor.name),n==="Map"||n==="Set"?Array.from(t):n==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?de(t,e):void 0}}function Ft(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function Et(t){if(Array.isArray(t))return de(t)}function de(t,e){(e==null||e>t.length)&&(e=t.length);for(var n=0,a=Array(e);n<e;n++)a[n]=t[n];return a}var ue={name:"AutoComplete",extends:Tt,inheritAttrs:!1,emits:["change","focus","blur","item-select","item-unselect","option-select","option-unselect","dropdown-click","clear","complete","before-show","before-hide","show","hide"],inject:{$pcFluid:{default:null}},outsideClickListener:null,resizeListener:null,scrollHandler:null,overlay:null,virtualScroller:null,searchTimeout:null,dirty:!1,startRangeIndex:-1,data:function(){return{clicked:!1,focused:!1,focusedOptionIndex:-1,focusedMultipleOptionIndex:-1,overlayVisible:!1,searching:!1}},watch:{suggestions:function(){this.searching&&(this.show(),this.focusedOptionIndex=this.overlayVisible&&this.autoOptionFocus?this.findFirstFocusedOptionIndex():-1,this.searching=!1,!this.showEmptyMessage&&this.visibleOptions.length===0&&this.hide()),this.autoUpdateModel()}},mounted:function(){this.autoUpdateModel()},updated:function(){this.overlayVisible&&this.alignOverlay()},beforeUnmount:function(){this.unbindOutsideClickListener(),this.unbindResizeListener(),this.scrollHandler&&(this.scrollHandler.destroy(),this.scrollHandler=null),this.overlay&&(ae.clear(this.overlay),this.overlay=null)},methods:{getOptionIndex:function(e,n){return this.virtualScrollerDisabled?e:n&&n(e).index},getOptionLabel:function(e){return this.optionLabel?G(e,this.optionLabel):e},getOptionValue:function(e){return e},getOptionRenderKey:function(e,n){return(this.dataKey?G(e,this.dataKey):this.getOptionLabel(e))+"_"+n},getPTOptions:function(e,n,a,r){return this.ptm(r,{context:{option:e,index:a,selected:this.isSelected(e),focused:this.focusedOptionIndex===this.getOptionIndex(a,n),disabled:this.isOptionDisabled(e)}})},isOptionDisabled:function(e){return this.optionDisabled?G(e,this.optionDisabled):!1},isOptionGroup:function(e){return this.optionGroupLabel&&e.optionGroup&&e.group},getOptionGroupLabel:function(e){return G(e,this.optionGroupLabel)},getOptionGroupChildren:function(e){return G(e,this.optionGroupChildren)},getAriaPosInset:function(e){var n=this;return(this.optionGroupLabel?e-this.visibleOptions.slice(0,e).filter(function(a){return n.isOptionGroup(a)}).length:e)+1},show:function(e){this.$emit("before-show"),this.dirty=!0,this.overlayVisible=!0,this.focusedOptionIndex=this.focusedOptionIndex!==-1?this.focusedOptionIndex:this.autoOptionFocus?this.findFirstFocusedOptionIndex():-1,e&&$(this.multiple?this.$refs.focusInput:this.$refs.focusInput.$el)},hide:function(e){var n=this,a=function(){var i;n.$emit("before-hide"),n.dirty=e,n.overlayVisible=!1,n.clicked=!1,n.focusedOptionIndex=-1,e&&$(n.multiple?n.$refs.focusInput:(i=n.$refs.focusInput)===null||i===void 0?void 0:i.$el)};setTimeout(function(){a()},0)},onFocus:function(e){this.disabled||(!this.dirty&&this.completeOnFocus&&this.search(e,e.target.value,"focus"),this.dirty=!0,this.focused=!0,this.overlayVisible&&(this.focusedOptionIndex=this.focusedOptionIndex!==-1?this.focusedOptionIndex:this.overlayVisible&&this.autoOptionFocus?this.findFirstFocusedOptionIndex():-1,this.scrollInView(this.focusedOptionIndex)),this.$emit("focus",e))},onBlur:function(e){var n,a;this.dirty=!1,this.focused=!1,this.focusedOptionIndex=-1,this.$emit("blur",e),(n=(a=this.formField).onBlur)===null||n===void 0||n.call(a)},onKeyDown:function(e){if(this.disabled){e.preventDefault();return}switch(e.code){case"ArrowDown":this.onArrowDownKey(e);break;case"ArrowUp":this.onArrowUpKey(e);break;case"ArrowLeft":this.onArrowLeftKey(e);break;case"ArrowRight":this.onArrowRightKey(e);break;case"Home":this.onHomeKey(e);break;case"End":this.onEndKey(e);break;case"PageDown":this.onPageDownKey(e);break;case"PageUp":this.onPageUpKey(e);break;case"Enter":case"NumpadEnter":this.onEnterKey(e);break;case"Space":this.onSpaceKey(e);break;case"Escape":this.onEscapeKey(e);break;case"Tab":this.onTabKey(e);break;case"ShiftLeft":case"ShiftRight":this.onShiftKey(e);break;case"Backspace":this.onBackspaceKey(e);break}this.clicked=!1},onInput:function(e){var n=this;if(this.typeahead){this.searchTimeout&&clearTimeout(this.searchTimeout);var a=e.target.value;this.multiple||this.updateModel(e,a),a.length===0?(this.hide(),this.$emit("clear")):a.length>=this.minLength?(this.focusedOptionIndex=-1,this.searchTimeout=setTimeout(function(){n.search(e,a,"input")},this.delay)):this.hide()}},onChange:function(e){var n=this;if(this.forceSelection){var a=!1;if(this.visibleOptions&&!this.multiple){var r,i=this.multiple?this.$refs.focusInput.value:(r=this.$refs.focusInput)===null||r===void 0||(r=r.$el)===null||r===void 0?void 0:r.value,x=this.visibleOptions.find(function(T){return n.isOptionMatched(T,i||"")});x!==void 0&&(a=!0,!this.isSelected(x)&&this.onOptionSelect(e,x))}if(!a){if(this.multiple)this.$refs.focusInput.value="";else{var I,S=(I=this.$refs.focusInput)===null||I===void 0?void 0:I.$el;S&&(S.value="")}this.$emit("clear"),!this.multiple&&this.updateModel(e,null)}}},onMultipleContainerFocus:function(){this.disabled||(this.focused=!0)},onMultipleContainerBlur:function(){this.focusedMultipleOptionIndex=-1,this.focused=!1},onMultipleContainerKeyDown:function(e){if(this.disabled){e.preventDefault();return}switch(e.code){case"ArrowLeft":this.onArrowLeftKeyOnMultiple(e);break;case"ArrowRight":this.onArrowRightKeyOnMultiple(e);break;case"Backspace":this.onBackspaceKeyOnMultiple(e);break}},onContainerClick:function(e){this.clicked=!0,!(this.disabled||this.searching||this.loading||this.isDropdownClicked(e))&&(!this.overlay||!this.overlay.contains(e.target))&&$(this.multiple?this.$refs.focusInput:this.$refs.focusInput.$el)},onDropdownClick:function(e){var n=void 0;if(this.overlayVisible)this.hide(!0);else{var a=this.multiple?this.$refs.focusInput:this.$refs.focusInput.$el;$(a),n=a.value,this.dropdownMode==="blank"?this.search(e,"","dropdown"):this.dropdownMode==="current"&&this.search(e,n,"dropdown")}this.$emit("dropdown-click",{originalEvent:e,query:n})},onOptionSelect:function(e,n){var a=arguments.length>2&&arguments[2]!==void 0?arguments[2]:!0,r=this.getOptionValue(n);this.multiple?(this.$refs.focusInput.value="",this.isSelected(n)||this.updateModel(e,[].concat(ye(this.d_value||[]),[r]))):this.updateModel(e,r),this.$emit("item-select",{originalEvent:e,value:n}),this.$emit("option-select",{originalEvent:e,value:n}),a&&this.hide(!0)},onOptionMouseMove:function(e,n){this.focusOnHover&&this.changeFocusedOptionIndex(e,n)},onOptionSelectRange:function(e){var n=this,a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:-1,r=arguments.length>2&&arguments[2]!==void 0?arguments[2]:-1;if(a===-1&&(a=this.findNearestSelectedOptionIndex(r,!0)),r===-1&&(r=this.findNearestSelectedOptionIndex(a)),a!==-1&&r!==-1){var i=Math.min(a,r),x=Math.max(a,r),I=this.visibleOptions.slice(i,x+1).filter(function(S){return n.isValidOption(S)}).map(function(S){return n.getOptionValue(S)});this.updateModel(e,I)}},onOverlayClick:function(e){nt.emit("overlay-click",{originalEvent:e,target:this.$el})},onOverlayKeyDown:function(e){switch(e.code){case"Escape":this.onEscapeKey(e);break}},onArrowDownKey:function(e){if(this.overlayVisible){var n=this.focusedOptionIndex!==-1?this.findNextOptionIndex(this.focusedOptionIndex):this.clicked?this.findFirstOptionIndex():this.findFirstFocusedOptionIndex();this.multiple&&e.shiftKey&&this.onOptionSelectRange(e,this.startRangeIndex,n),this.changeFocusedOptionIndex(e,n),e.preventDefault()}},onArrowUpKey:function(e){if(this.overlayVisible)if(e.altKey)this.focusedOptionIndex!==-1&&this.onOptionSelect(e,this.visibleOptions[this.focusedOptionIndex]),this.overlayVisible&&this.hide(),e.preventDefault();else{var n=this.focusedOptionIndex!==-1?this.findPrevOptionIndex(this.focusedOptionIndex):this.clicked?this.findLastOptionIndex():this.findLastFocusedOptionIndex();this.multiple&&e.shiftKey&&this.onOptionSelectRange(e,n,this.startRangeIndex),this.changeFocusedOptionIndex(e,n),e.preventDefault()}},onArrowLeftKey:function(e){var n=e.currentTarget;this.focusedOptionIndex=-1,this.multiple&&(Be(n.value)&&this.$filled?($(this.$refs.multiContainer),this.focusedMultipleOptionIndex=this.d_value.length):e.stopPropagation())},onArrowRightKey:function(e){this.focusedOptionIndex=-1,this.multiple&&e.stopPropagation()},onHomeKey:function(e){var n=e.currentTarget,a=n.value.length,r=e.metaKey||e.ctrlKey,i=this.findFirstOptionIndex();this.multiple&&e.shiftKey&&r&&this.onOptionSelectRange(e,i,this.startRangeIndex),n.setSelectionRange(0,e.shiftKey?a:0),this.focusedOptionIndex=-1,e.preventDefault()},onEndKey:function(e){var n=e.currentTarget,a=n.value.length,r=e.metaKey||e.ctrlKey,i=this.findLastOptionIndex();this.multiple&&e.shiftKey&&r&&this.onOptionSelectRange(e,this.startRangeIndex,i),n.setSelectionRange(e.shiftKey?0:a,a),this.focusedOptionIndex=-1,e.preventDefault()},onPageUpKey:function(e){this.scrollInView(0),e.preventDefault()},onPageDownKey:function(e){this.scrollInView(this.visibleOptions.length-1),e.preventDefault()},onEnterKey:function(e){this.typeahead?this.overlayVisible?(this.focusedOptionIndex!==-1&&(this.multiple&&e.shiftKey?(this.onOptionSelectRange(e,this.focusedOptionIndex),e.preventDefault()):this.onOptionSelect(e,this.visibleOptions[this.focusedOptionIndex])),this.hide()):(this.focusedOptionIndex=-1,this.onArrowDownKey(e)):this.multiple&&(e.target.value.trim()&&(this.updateModel(e,[].concat(ye(this.d_value||[]),[e.target.value.trim()])),this.$refs.focusInput.value=""),e.preventDefault())},onSpaceKey:function(e){!this.autoOptionFocus&&this.focusedOptionIndex!==-1&&this.onEnterKey(e)},onEscapeKey:function(e){this.overlayVisible&&this.hide(!0),e.preventDefault()},onTabKey:function(e){this.focusedOptionIndex!==-1&&this.onOptionSelect(e,this.visibleOptions[this.focusedOptionIndex]),this.overlayVisible&&this.hide()},onShiftKey:function(){this.startRangeIndex=this.focusedOptionIndex},onBackspaceKey:function(e){if(this.multiple){if(_(this.d_value)&&!this.$refs.focusInput.value){var n=this.d_value[this.d_value.length-1],a=this.d_value.slice(0,-1);this.writeValue(a,e),this.$emit("item-unselect",{originalEvent:e,value:n}),this.$emit("option-unselect",{originalEvent:e,value:n})}e.stopPropagation()}},onArrowLeftKeyOnMultiple:function(){this.focusedMultipleOptionIndex=this.focusedMultipleOptionIndex<1?0:this.focusedMultipleOptionIndex-1},onArrowRightKeyOnMultiple:function(){this.focusedMultipleOptionIndex++,this.focusedMultipleOptionIndex>this.d_value.length-1&&(this.focusedMultipleOptionIndex=-1,$(this.$refs.focusInput))},onBackspaceKeyOnMultiple:function(e){this.focusedMultipleOptionIndex!==-1&&this.removeOption(e,this.focusedMultipleOptionIndex)},onOverlayEnter:function(e){ae.set("overlay",e,this.$primevue.config.zIndex.overlay),je(e,{position:"absolute",top:"0"}),this.alignOverlay(),this.$attrSelector&&e.setAttribute(this.$attrSelector,"")},onOverlayAfterEnter:function(){this.bindOutsideClickListener(),this.bindScrollListener(),this.bindResizeListener(),this.$emit("show")},onOverlayLeave:function(){this.unbindOutsideClickListener(),this.unbindScrollListener(),this.unbindResizeListener(),this.$emit("hide"),this.overlay=null},onOverlayAfterLeave:function(e){ae.clear(e)},alignOverlay:function(){var e=this.multiple?this.$refs.multiContainer:this.$refs.focusInput.$el;this.appendTo==="self"?Fe(this.overlay,e):(this.overlay.style.minWidth=Ee(e)+"px",Re(this.overlay,e))},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(n){e.overlayVisible&&e.overlay&&e.isOutsideClicked(n)&&e.hide()},document.addEventListener("click",this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&(document.removeEventListener("click",this.outsideClickListener,!0),this.outsideClickListener=null)},bindScrollListener:function(){var e=this;this.scrollHandler||(this.scrollHandler=new tt(this.$refs.container,function(){e.overlayVisible&&e.hide()})),this.scrollHandler.bindScrollListener()},unbindScrollListener:function(){this.scrollHandler&&this.scrollHandler.unbindScrollListener()},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(){e.overlayVisible&&!Ae()&&e.hide()},window.addEventListener("resize",this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&(window.removeEventListener("resize",this.resizeListener),this.resizeListener=null)},isOutsideClicked:function(e){return!this.overlay.contains(e.target)&&!this.isInputClicked(e)&&!this.isDropdownClicked(e)},isInputClicked:function(e){return this.multiple?e.target===this.$refs.multiContainer||this.$refs.multiContainer.contains(e.target):e.target===this.$refs.focusInput.$el},isDropdownClicked:function(e){return this.$refs.dropdownButton?e.target===this.$refs.dropdownButton||this.$refs.dropdownButton.contains(e.target):!1},isOptionMatched:function(e,n){var a;return this.isValidOption(e)&&((a=this.getOptionLabel(e))===null||a===void 0?void 0:a.toLocaleLowerCase(this.searchLocale))===n.toLocaleLowerCase(this.searchLocale)},isValidOption:function(e){return _(e)&&!(this.isOptionDisabled(e)||this.isOptionGroup(e))},isValidSelectedOption:function(e){return this.isValidOption(e)&&this.isSelected(e)},isEquals:function(e,n){return ze(e,n,this.equalityKey)},isSelected:function(e){var n=this,a=this.getOptionValue(e);return this.multiple?(this.d_value||[]).some(function(r){return n.isEquals(r,a)}):this.isEquals(this.d_value,this.getOptionValue(e))},findFirstOptionIndex:function(){var e=this;return this.visibleOptions.findIndex(function(n){return e.isValidOption(n)})},findLastOptionIndex:function(){var e=this;return oe(this.visibleOptions,function(n){return e.isValidOption(n)})},findNextOptionIndex:function(e){var n=this,a=e<this.visibleOptions.length-1?this.visibleOptions.slice(e+1).findIndex(function(r){return n.isValidOption(r)}):-1;return a>-1?a+e+1:e},findPrevOptionIndex:function(e){var n=this,a=e>0?oe(this.visibleOptions.slice(0,e),function(r){return n.isValidOption(r)}):-1;return a>-1?a:e},findSelectedOptionIndex:function(){var e=this;return this.$filled?this.visibleOptions.findIndex(function(n){return e.isValidSelectedOption(n)}):-1},findFirstFocusedOptionIndex:function(){var e=this.findSelectedOptionIndex();return e<0?this.findFirstOptionIndex():e},findLastFocusedOptionIndex:function(){var e=this.findSelectedOptionIndex();return e<0?this.findLastOptionIndex():e},search:function(e,n,a){n!=null&&(a==="input"&&n.trim().length===0||(this.searching=!0,this.$emit("complete",{originalEvent:e,query:n})))},removeOption:function(e,n){var a=this,r=this.d_value[n],i=this.d_value.filter(function(x,I){return I!==n}).map(function(x){return a.getOptionValue(x)});this.updateModel(e,i),this.$emit("item-unselect",{originalEvent:e,value:r}),this.$emit("option-unselect",{originalEvent:e,value:r}),this.dirty=!0,$(this.multiple?this.$refs.focusInput:this.$refs.focusInput.$el)},changeFocusedOptionIndex:function(e,n){this.focusedOptionIndex!==n&&(this.focusedOptionIndex=n,this.scrollInView(),this.selectOnFocus&&this.onOptionSelect(e,this.visibleOptions[n],!1))},scrollInView:function(){var e=this,n=arguments.length>0&&arguments[0]!==void 0?arguments[0]:-1;this.$nextTick(function(){var a=n!==-1?"".concat(e.$id,"_").concat(n):e.focusedOptionId,r=$e(e.list,'li[id="'.concat(a,'"]'));r?r.scrollIntoView&&r.scrollIntoView({block:"nearest",inline:"start"}):e.virtualScrollerDisabled||e.virtualScroller&&e.virtualScroller.scrollToIndex(n!==-1?n:e.focusedOptionIndex)})},autoUpdateModel:function(){this.selectOnFocus&&this.autoOptionFocus&&!this.$filled&&(this.focusedOptionIndex=this.findFirstFocusedOptionIndex(),this.onOptionSelect(null,this.visibleOptions[this.focusedOptionIndex],!1))},updateModel:function(e,n){this.writeValue(n,e),this.$emit("change",{originalEvent:e,value:n})},flatOptions:function(e){var n=this;return(e||[]).reduce(function(a,r,i){a.push({optionGroup:r,group:!0,index:i});var x=n.getOptionGroupChildren(r);return x&&x.forEach(function(I){return a.push(I)}),a},[])},overlayRef:function(e){this.overlay=e},listRef:function(e,n){this.list=e,n&&n(e)},virtualScrollerRef:function(e){this.virtualScroller=e},findNextSelectedOptionIndex:function(e){var n=this,a=this.$filled&&e<this.visibleOptions.length-1?this.visibleOptions.slice(e+1).findIndex(function(r){return n.isValidSelectedOption(r)}):-1;return a>-1?a+e+1:-1},findPrevSelectedOptionIndex:function(e){var n=this,a=this.$filled&&e>0?oe(this.visibleOptions.slice(0,e),function(r){return n.isValidSelectedOption(r)}):-1;return a>-1?a:-1},findNearestSelectedOptionIndex:function(e){var n=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,a=-1;return this.$filled&&(n?(a=this.findPrevSelectedOptionIndex(e),a=a===-1?this.findNextSelectedOptionIndex(e):a):(a=this.findNextSelectedOptionIndex(e),a=a===-1?this.findPrevSelectedOptionIndex(e):a)),a>-1?a:e}},computed:{visibleOptions:function(){return this.optionGroupLabel?this.flatOptions(this.suggestions):this.suggestions||[]},inputValue:function(){if(this.$filled)if(R(this.d_value)==="object"){var e=this.getOptionLabel(this.d_value);return e??this.d_value}else return this.d_value;else return""},hasSelectedOption:function(){return this.$filled},equalityKey:function(){return this.dataKey},searchResultMessageText:function(){return _(this.visibleOptions)&&this.overlayVisible?this.searchMessageText.replaceAll("{0}",this.visibleOptions.length):this.emptySearchMessageText},searchMessageText:function(){return this.searchMessage||this.$primevue.config.locale.searchMessage||""},emptySearchMessageText:function(){return this.emptySearchMessage||this.$primevue.config.locale.emptySearchMessage||""},selectionMessageText:function(){return this.selectionMessage||this.$primevue.config.locale.selectionMessage||""},emptySelectionMessageText:function(){return this.emptySelectionMessage||this.$primevue.config.locale.emptySelectionMessage||""},selectedMessageText:function(){return this.$filled?this.selectionMessageText.replaceAll("{0}",this.multiple?this.d_value.length:"1"):this.emptySelectionMessageText},listAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.listLabel:void 0},focusedOptionId:function(){return this.focusedOptionIndex!==-1?"".concat(this.$id,"_").concat(this.focusedOptionIndex):null},focusedMultipleOptionId:function(){return this.focusedMultipleOptionIndex!==-1?"".concat(this.$id,"_multiple_option_").concat(this.focusedMultipleOptionIndex):null},ariaSetSize:function(){var e=this;return this.visibleOptions.filter(function(n){return!e.isOptionGroup(n)}).length},virtualScrollerDisabled:function(){return!this.virtualScrollerOptions},panelId:function(){return this.$id+"_panel"},containerDataP:function(){return W({fluid:this.$fluid})},overlayDataP:function(){return W(ve({},"portal-"+this.appendTo,"portal-"+this.appendTo))},inputMultipleDataP:function(){return W(ve({invalid:this.$invalid,disabled:this.disabled,focus:this.focused,fluid:this.$fluid,filled:this.$variant==="filled",empty:!this.$filled},this.size,this.size))}},components:{InputText:J,VirtualScroller:at,Portal:et,ChevronDownIcon:ot,SpinnerIcon:_e,Chip:we},directives:{ripple:Ze}};function Q(t){"@babel/helpers - typeof";return Q=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},Q(t)}function be(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(t);e&&(a=a.filter(function(r){return Object.getOwnPropertyDescriptor(t,r).enumerable})),n.push.apply(n,a)}return n}function ge(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?be(Object(n),!0).forEach(function(a){Rt(t,a,n[a])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):be(Object(n)).forEach(function(a){Object.defineProperty(t,a,Object.getOwnPropertyDescriptor(n,a))})}return t}function Rt(t,e,n){return(e=jt(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function jt(t){var e=Bt(t,"string");return Q(e)=="symbol"?e:e+""}function Bt(t,e){if(Q(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var a=n.call(t,e);if(Q(a)!="object")return a;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var Ut=["data-p"],Nt=["aria-activedescendant","data-p-has-dropdown","data-p"],Gt=["id","aria-label","aria-setsize","aria-posinset"],Ht=["id","placeholder","tabindex","disabled","aria-label","aria-labelledby","aria-expanded","aria-controls","aria-activedescendant","aria-invalid"],qt=["data-p-has-dropdown"],Xt=["disabled","aria-expanded","aria-controls"],Jt=["id","data-p"],Wt=["id","aria-label"],Yt=["id"],Qt=["id","aria-label","aria-selected","aria-disabled","aria-setsize","aria-posinset","onClick","onMousemove","data-p-selected","data-p-focused","data-p-disabled"];function Zt(t,e,n,a,r,i){var x=H("InputText"),I=H("Chip"),S=H("SpinnerIcon"),T=H("VirtualScroller"),j=H("Portal"),B=Ue("ripple");return f(),h("div",m({ref:"container",class:t.cx("root"),style:t.sx("root"),onClick:e[11]||(e[11]=function(){return i.onContainerClick&&i.onContainerClick.apply(i,arguments)}),"data-p":i.containerDataP},t.ptmi("root")),[t.multiple?M("",!0):(f(),F(x,{key:0,ref:"focusInput",id:t.inputId,type:"text",name:t.$formName,class:q([t.cx("pcInputText"),t.inputClass]),style:Ne(t.inputStyle),defaultValue:i.inputValue,placeholder:t.placeholder,tabindex:t.disabled?-1:t.tabindex,fluid:t.$fluid,disabled:t.disabled,size:t.size,invalid:t.invalid,variant:t.variant,autocomplete:"off",role:"combobox","aria-label":t.ariaLabel,"aria-labelledby":t.ariaLabelledby,"aria-haspopup":"listbox","aria-autocomplete":"list","aria-expanded":r.overlayVisible,"aria-controls":i.panelId,"aria-activedescendant":r.focused?i.focusedOptionId:void 0,onFocus:i.onFocus,onBlur:i.onBlur,onKeydown:i.onKeyDown,onInput:i.onInput,onChange:i.onChange,unstyled:t.unstyled,"data-p-has-dropdown":t.dropdown,pt:t.ptm("pcInputText")},null,8,["id","name","class","style","defaultValue","placeholder","tabindex","fluid","disabled","size","invalid","variant","aria-label","aria-labelledby","aria-expanded","aria-controls","aria-activedescendant","onFocus","onBlur","onKeydown","onInput","onChange","unstyled","data-p-has-dropdown","pt"])),t.multiple?(f(),h("ul",m({key:1,ref:"multiContainer",class:t.cx("inputMultiple"),tabindex:"-1",role:"listbox","aria-orientation":"horizontal","aria-activedescendant":r.focused?i.focusedMultipleOptionId:void 0,onFocus:e[5]||(e[5]=function(){return i.onMultipleContainerFocus&&i.onMultipleContainerFocus.apply(i,arguments)}),onBlur:e[6]||(e[6]=function(){return i.onMultipleContainerBlur&&i.onMultipleContainerBlur.apply(i,arguments)}),onKeydown:e[7]||(e[7]=function(){return i.onMultipleContainerKeyDown&&i.onMultipleContainerKeyDown.apply(i,arguments)}),"data-p-has-dropdown":t.dropdown,"data-p":i.inputMultipleDataP},t.ptm("inputMultiple")),[(f(!0),h(ee,null,se(t.d_value,function(b,v){return f(),h("li",m({key:"".concat(v,"_").concat(i.getOptionLabel(b)),id:t.$id+"_multiple_option_"+v,class:t.cx("chipItem",{i:v}),role:"option","aria-label":i.getOptionLabel(b),"aria-selected":!0,"aria-setsize":t.d_value.length,"aria-posinset":v+1},{ref_for:!0},t.ptm("chipItem")),[L(t.$slots,"chip",m({class:t.cx("pcChip"),value:b,index:v,removeCallback:function(y){return i.removeOption(y,v)}},{ref_for:!0},t.ptm("pcChip")),function(){return[u(I,{class:q(t.cx("pcChip")),label:i.getOptionLabel(b),removeIcon:t.chipIcon||t.removeTokenIcon,removable:"",unstyled:t.unstyled,onRemove:function(y){return i.removeOption(y,v)},"data-p-focused":r.focusedMultipleOptionIndex===v,pt:t.ptm("pcChip")},{removeicon:O(function(){return[L(t.$slots,t.$slots.chipicon?"chipicon":"removetokenicon",{class:q(t.cx("chipIcon")),index:v,removeCallback:function(y){return i.removeOption(y,v)}})]}),_:2},1032,["class","label","removeIcon","unstyled","onRemove","data-p-focused","pt"])]})],16,Gt)}),128)),l("li",m({class:t.cx("inputChip"),role:"option"},t.ptm("inputChip")),[l("input",m({ref:"focusInput",id:t.inputId,type:"text",style:t.inputStyle,class:t.inputClass,placeholder:t.placeholder,tabindex:t.disabled?-1:t.tabindex,disabled:t.disabled,autocomplete:"off",role:"combobox","aria-label":t.ariaLabel,"aria-labelledby":t.ariaLabelledby,"aria-haspopup":"listbox","aria-autocomplete":"list","aria-expanded":r.overlayVisible,"aria-controls":t.$id+"_list","aria-activedescendant":r.focused?i.focusedOptionId:void 0,"aria-invalid":t.invalid||void 0,onFocus:e[0]||(e[0]=function(){return i.onFocus&&i.onFocus.apply(i,arguments)}),onBlur:e[1]||(e[1]=function(){return i.onBlur&&i.onBlur.apply(i,arguments)}),onKeydown:e[2]||(e[2]=function(){return i.onKeyDown&&i.onKeyDown.apply(i,arguments)}),onInput:e[3]||(e[3]=function(){return i.onInput&&i.onInput.apply(i,arguments)}),onChange:e[4]||(e[4]=function(){return i.onChange&&i.onChange.apply(i,arguments)})},t.ptm("input")),null,16,Ht)],16)],16,Nt)):M("",!0),r.searching||t.loading?L(t.$slots,t.$slots.loader?"loader":"loadingicon",{key:2,class:q(t.cx("loader"))},function(){return[t.loader||t.loadingIcon?(f(),h("i",m({key:0,class:["pi-spin",t.cx("loader"),t.loader,t.loadingIcon],"aria-hidden":"true","data-p-has-dropdown":t.dropdown},t.ptm("loader")),null,16,qt)):(f(),F(S,m({key:1,class:t.cx("loader"),spin:"","aria-hidden":"true","data-p-has-dropdown":t.dropdown},t.ptm("loader")),null,16,["class","data-p-has-dropdown"]))]}):M("",!0),L(t.$slots,t.$slots.dropdown?"dropdown":"dropdownbutton",{toggleCallback:function(v){return i.onDropdownClick(v)}},function(){return[t.dropdown?(f(),h("button",m({key:0,ref:"dropdownButton",type:"button",class:[t.cx("dropdown"),t.dropdownClass],disabled:t.disabled,"aria-haspopup":"listbox","aria-expanded":r.overlayVisible,"aria-controls":i.panelId,onClick:e[8]||(e[8]=function(){return i.onDropdownClick&&i.onDropdownClick.apply(i,arguments)})},t.ptm("dropdown")),[L(t.$slots,"dropdownicon",{class:q(t.dropdownIcon)},function(){return[(f(),F(re(t.dropdownIcon?"span":"ChevronDownIcon"),m({class:t.dropdownIcon},t.ptm("dropdownIcon")),null,16,["class"]))]})],16,Xt)):M("",!0)]}),t.typeahead?(f(),h("span",m({key:3,role:"status","aria-live":"polite",class:"p-hidden-accessible"},t.ptm("hiddenSearchResult"),{"data-p-hidden-accessible":!0}),V(i.searchResultMessageText),17)):M("",!0),u(j,{appendTo:t.appendTo},{default:O(function(){return[u(Ge,m({name:"p-connected-overlay",onEnter:i.onOverlayEnter,onAfterEnter:i.onOverlayAfterEnter,onLeave:i.onOverlayLeave,onAfterLeave:i.onOverlayAfterLeave},t.ptm("transition")),{default:O(function(){return[r.overlayVisible?(f(),h("div",m({key:0,ref:i.overlayRef,id:i.panelId,class:[t.cx("overlay"),t.panelClass,t.overlayClass],style:ge(ge({},t.panelStyle),t.overlayStyle),onClick:e[9]||(e[9]=function(){return i.onOverlayClick&&i.onOverlayClick.apply(i,arguments)}),onKeydown:e[10]||(e[10]=function(){return i.onOverlayKeyDown&&i.onOverlayKeyDown.apply(i,arguments)}),"data-p":i.overlayDataP},t.ptm("overlay")),[L(t.$slots,"header",{value:t.d_value,suggestions:i.visibleOptions}),l("div",m({class:t.cx("listContainer"),style:{"max-height":i.virtualScrollerDisabled?t.scrollHeight:""}},t.ptm("listContainer")),[u(T,m({ref:i.virtualScrollerRef},t.virtualScrollerOptions,{style:{height:t.scrollHeight},items:i.visibleOptions,tabindex:-1,disabled:i.virtualScrollerDisabled,pt:t.ptm("virtualScroller")}),He({content:O(function(b){var v=b.styleClass,p=b.contentRef,y=b.items,C=b.getItemOptions,U=b.contentStyle,P=b.itemSize;return[l("ul",m({ref:function(k){return i.listRef(k,p)},id:t.$id+"_list",class:[t.cx("list"),v],style:U,role:"listbox","aria-label":i.listAriaLabel},t.ptm("list")),[(f(!0),h(ee,null,se(y,function(g,k){return f(),h(ee,{key:i.getOptionRenderKey(g,i.getOptionIndex(k,C))},[i.isOptionGroup(g)?(f(),h("li",m({key:0,id:t.$id+"_"+i.getOptionIndex(k,C),style:{height:P?P+"px":void 0},class:t.cx("optionGroup"),role:"option"},{ref_for:!0},t.ptm("optionGroup")),[L(t.$slots,"optiongroup",{option:g.optionGroup,index:i.getOptionIndex(k,C)},function(){return[E(V(i.getOptionGroupLabel(g.optionGroup)),1)]})],16,Yt)):qe((f(),h("li",m({key:1,id:t.$id+"_"+i.getOptionIndex(k,C),style:{height:P?P+"px":void 0},class:t.cx("option",{option:g,i:k,getItemOptions:C}),role:"option","aria-label":i.getOptionLabel(g),"aria-selected":i.isSelected(g),"aria-disabled":i.isOptionDisabled(g),"aria-setsize":i.ariaSetSize,"aria-posinset":i.getAriaPosInset(i.getOptionIndex(k,C)),onClick:function(N){return i.onOptionSelect(N,g)},onMousemove:function(N){return i.onOptionMouseMove(N,i.getOptionIndex(k,C))},"data-p-selected":i.isSelected(g),"data-p-focused":r.focusedOptionIndex===i.getOptionIndex(k,C),"data-p-disabled":i.isOptionDisabled(g)},{ref_for:!0},i.getPTOptions(g,C,k,"option")),[L(t.$slots,"option",{option:g,index:i.getOptionIndex(k,C)},function(){return[E(V(i.getOptionLabel(g)),1)]})],16,Qt)),[[B]])],64)}),128)),t.showEmptyMessage&&(!y||y&&y.length===0)?(f(),h("li",m({key:0,class:t.cx("emptyMessage"),role:"option"},t.ptm("emptyMessage")),[L(t.$slots,"empty",{},function(){return[E(V(i.searchResultMessageText),1)]})],16)):M("",!0)],16,Wt)]}),_:2},[t.$slots.loader?{name:"loader",fn:O(function(b){var v=b.options;return[L(t.$slots,"loader",{options:v})]}),key:"0"}:void 0]),1040,["style","items","disabled","pt"])],16),L(t.$slots,"footer",{value:t.d_value,suggestions:i.visibleOptions}),l("span",m({role:"status","aria-live":"polite",class:"p-hidden-accessible"},t.ptm("hiddenSelectedMessage"),{"data-p-hidden-accessible":!0}),V(i.selectedMessageText),17)],16,Jt)):M("",!0)]}),_:3},16,["onEnter","onAfterEnter","onLeave","onAfterLeave"])]}),_:3},8,["appendTo"])],16,Ut)}ue.render=Zt;const _t={class:"min-h-screen bg-gray-50 dark:bg-gray-900"},en={class:"bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700"},tn={class:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"},nn={class:"flex justify-between items-center h-16"},on={class:"flex items-center space-x-4"},an={class:"flex items-center space-x-4"},ln={class:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"},rn={key:0,class:"flex justify-center py-12"},sn={key:1,class:"grid grid-cols-1 lg:grid-cols-3 gap-8"},dn={class:"lg:col-span-2 space-y-6"},un={class:"grid grid-cols-1 md:grid-cols-2 gap-4"},pn={class:"md:col-span-2"},cn={class:"flex space-x-2"},fn={class:"grid grid-cols-1 md:grid-cols-2 gap-4"},mn={class:"mt-4"},hn={class:"mt-4"},vn={class:"flex justify-between items-center"},yn={class:"space-y-4"},bn={class:"grid grid-cols-1 md:grid-cols-6 gap-4"},gn={class:"md:col-span-2"},On={class:"space-y-2"},wn={class:"mt-4 flex justify-between items-center"},xn={class:"text-sm text-gray-600 dark:text-gray-400"},In={class:"space-y-6"},kn={class:"space-y-3"},Sn={class:"flex justify-between text-sm"},Cn={class:"font-medium text-gray-900 dark:text-white"},Vn={class:"flex justify-between text-sm"},Ln={class:"font-medium text-gray-900 dark:text-white"},Mn={class:"flex justify-between text-sm"},Kn={class:"font-medium text-gray-900 dark:text-white"},Dn={class:"flex justify-between"},Tn={class:"text-lg font-bold text-blue-600 dark:text-blue-400"},Pn={class:"space-y-3"},$n={class:"space-y-4"},zn={class:"bg-white dark:bg-gray-800 rounded-lg p-6"},An={class:"text-center text-gray-600 dark:text-gray-400"},Fn={class:"mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded text-left"},En={class:"font-semibold mb-2"},Rn={class:"text-sm"},jn={class:"text-sm"},Bn={__name:"InvoiceCreate",emits:["invoice-created"],setup(t,{emit:e}){const{t:n}=Xe(),a=Je(),r=e,i=K(),x=K(),I=K(!1),S=K(!1),T=K([]),j=K([]),B=K([]),b=K(!1),v=K(!1),p=K({customer_id:null,invoice_number:"",invoice_date:new Date,due_date:new Date(new Date().setDate(new Date().getDate()+30)),status:"draft",currency:"USD",notes:"",terms:"",items:[{id:Date.now(),description:"",quantity:1,unit_price:0,tax_rate_id:null,discount:0,total:0}]}),y=K({name:"",email:"",phone:"",address:"",city:"",country:"",tax_number:""});z(()=>a.props.current_company),z(()=>a.props.auth?.user);const C=z(()=>p.value.items.reduce((d,o)=>d+o.quantity*o.unit_price,0)),U=z(()=>p.value.items.reduce((d,o)=>{const s=o.quantity*o.unit_price,ie=B.value.find(w=>w.id===o.tax_rate_id)?.rate||0;return d+s*(ie/100)},0)),P=z(()=>p.value.items.reduce((d,o)=>{const s=o.quantity*o.unit_price;return d+s*(o.discount/100)},0)),g=z(()=>C.value+U.value-P.value),k=z(()=>p.value.items.some(d=>d.description&&d.unit_price>0)),ne=async()=>{try{const d=await fetch("/api/v1/customers"),o=await d.json();d.ok&&(T.value=o.data||[])}catch(d){console.error("Failed to load customers:",d)}},N=async()=>{try{const d=await fetch("/api/v1/products"),o=await d.json();d.ok&&(j.value=o.data||[])}catch(d){console.error("Failed to load products:",d)}},xe=async()=>{try{const d=await fetch("/api/v1/tax-rates"),o=await d.json();d.ok&&(B.value=o.data||[])}catch(d){console.error("Failed to load tax rates:",d)}},Ie=async()=>{try{const d=await fetch("/api/v1/invoices/generate-number",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")}});if(d.ok){const o=await d.json();p.value.invoice_number=o.invoice_number}}catch(d){console.error("Failed to generate invoice number:",d);const o=Date.now();p.value.invoice_number=`INV-${o}`}},ke=()=>{p.value.items.push({id:Date.now(),description:"",quantity:1,unit_price:0,tax_rate_id:null,discount:0,total:0})},Se=d=>{p.value.items.length>1&&(p.value.items.splice(d,1),Ce())},Z=d=>{d.total=d.quantity*d.unit_price},Ce=()=>{p.value.items.forEach(d=>{Z(d)})},Ve=async d=>{const o=d.query.toLowerCase();return o?T.value.filter(s=>s.name.toLowerCase().includes(o)||s.email.toLowerCase().includes(o)).slice(0,10):T.value.slice(0,10)},Le=(d,o)=>{d.description=o.name,d.unit_price=o.price,Z(d)},Me=async d=>{const o=d.query.toLowerCase();return o?j.value.filter(s=>s.name.toLowerCase().includes(o)||s.description.toLowerCase().includes(o)).slice(0,10):j.value.slice(0,10)},Ke=async()=>{p.value.status="draft",await ce()},De=async()=>{p.value.status="sent",await ce(!0)},ce=async(d=!1)=>{if(!k.value){i.value.add({severity:"warn",summary:"Validation Error",detail:"Please add at least one item to the invoice",life:3e3});return}S.value=!0;try{const o=await fetch("/api/v1/invoices",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")},body:JSON.stringify({...p.value,subtotal:C.value,total_tax:U.value,total_discount:P.value,grand_total:g.value})}),s=await o.json();o.ok?(i.value.add({severity:"success",summary:"Success",detail:d?"Invoice created and sent successfully":"Invoice saved successfully",life:3e3}),r("invoice-created",s.data),setTimeout(()=>{Ye.visit("/invoicing")},1500)):i.value.add({severity:"error",summary:"Error",detail:s.message||"Failed to save invoice",life:3e3})}catch(o){console.error("Failed to save invoice:",o),i.value.add({severity:"error",summary:"Network Error",detail:"Failed to save invoice",life:3e3})}finally{S.value=!1}},Te=async()=>{if(!y.value.name){i.value.add({severity:"warn",summary:"Validation Error",detail:"Customer name is required",life:3e3});return}try{const d=await fetch("/api/v1/customers",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")},body:JSON.stringify(y.value)}),o=await d.json();d.ok?(T.value.push(o.data),p.value.customer_id=o.data.id,v.value=!1,y.value={name:"",email:"",phone:"",address:"",city:"",country:"",tax_number:""},i.value.add({severity:"success",summary:"Success",detail:"Customer created successfully",life:3e3})):i.value.add({severity:"error",summary:"Error",detail:o.message||"Failed to create customer",life:3e3})}catch(d){console.error("Failed to create customer:",d),i.value.add({severity:"error",summary:"Network Error",detail:"Failed to create customer",life:3e3})}},Pe=()=>{if(!k.value){i.value.add({severity:"warn",summary:"Validation Error",detail:"Please add at least one item to preview",life:3e3});return}b.value=!0},A=(d,o="USD")=>new Intl.NumberFormat("en-US",{style:"currency",currency:o}).format(d);return We(async()=>{I.value=!0,await Promise.all([ne(),N(),xe(),Ie()]),I.value=!1}),(d,o)=>(f(),h("div",_t,[l("div",en,[l("div",tn,[l("div",nn,[l("div",on,[u(c(D),{onClick:o[0]||(o[0]=s=>d.$inertia.visit("/invoicing")),icon:"fas fa-arrow-left",text:""}),o[17]||(o[17]=l("i",{class:"fas fa-file-invoice text-2xl text-blue-600 dark:text-blue-400"},null,-1)),o[18]||(o[18]=l("h1",{class:"text-xl font-semibold text-gray-900 dark:text-white"}," Create Invoice ",-1))]),l("div",an,[u(dt),u(ut,{ref_key:"commandPalette",ref:x},null,512)])])])]),l("div",ln,[I.value?(f(),h("div",rn,[u(c(rt))])):(f(),h("div",sn,[l("div",dn,[u(c(X),{class:"shadow-md"},{title:O(()=>[...o[19]||(o[19]=[E(" Customer Information ",-1)])]),content:O(()=>[l("div",un,[l("div",pn,[o[20]||(o[20]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Customer * ",-1)),l("div",cn,[u(c(ue),{modelValue:p.value.customer_id,"onUpdate:modelValue":o[1]||(o[1]=s=>p.value.customer_id=s),suggestions:Ve,optionLabel:"name",optionValue:"id",placeholder:"Search or select customer...",class:"flex-1",dropdown:!0,forceSelection:""},null,8,["modelValue"]),u(c(D),{onClick:o[2]||(o[2]=s=>v.value=!0),icon:"fas fa-plus",label:"New Customer",text:""})])])])]),_:1}),u(c(X),{class:"shadow-md"},{title:O(()=>[...o[21]||(o[21]=[E(" Invoice Details ",-1)])]),content:O(()=>[l("div",fn,[l("div",null,[o[22]||(o[22]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Invoice Number ",-1)),u(c(J),{modelValue:p.value.invoice_number,"onUpdate:modelValue":o[3]||(o[3]=s=>p.value.invoice_number=s),class:"w-full"},null,8,["modelValue"])]),l("div",null,[o[23]||(o[23]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Currency ",-1)),u(c(fe),{modelValue:p.value.currency,"onUpdate:modelValue":o[4]||(o[4]=s=>p.value.currency=s),options:[{label:"USD",value:"USD"},{label:"EUR",value:"EUR"},{label:"GBP",value:"GBP"}],optionLabel:"label",optionValue:"value",class:"w-full"},null,8,["modelValue"])]),l("div",null,[o[24]||(o[24]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Invoice Date ",-1)),u(c(me),{modelValue:p.value.invoice_date,"onUpdate:modelValue":o[5]||(o[5]=s=>p.value.invoice_date=s),dateFormat:"yy-mm-dd",class:"w-full",showButtonBar:""},null,8,["modelValue"])]),l("div",null,[o[25]||(o[25]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Due Date ",-1)),u(c(me),{modelValue:p.value.due_date,"onUpdate:modelValue":o[6]||(o[6]=s=>p.value.due_date=s),dateFormat:"yy-mm-dd",class:"w-full",showButtonBar:""},null,8,["modelValue"])])]),l("div",mn,[o[26]||(o[26]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Notes ",-1)),u(c(te),{modelValue:p.value.notes,"onUpdate:modelValue":o[7]||(o[7]=s=>p.value.notes=s),placeholder:"Additional notes for the customer...",class:"w-full",rows:"3"},null,8,["modelValue"])]),l("div",hn,[o[27]||(o[27]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Terms & Conditions ",-1)),u(c(te),{modelValue:p.value.terms,"onUpdate:modelValue":o[8]||(o[8]=s=>p.value.terms=s),placeholder:"Payment terms and conditions...",class:"w-full",rows:"3"},null,8,["modelValue"])])]),_:1}),u(c(X),{class:"shadow-md"},{title:O(()=>[l("div",vn,[o[28]||(o[28]=l("span",null,"Line Items",-1)),u(c(D),{onClick:ke,icon:"fas fa-plus",label:"Add Item",text:"",size:"small"})])]),content:O(()=>[l("div",yn,[(f(!0),h(ee,null,se(p.value.items,(s,ie)=>(f(),h("div",{key:s.id,class:"border border-gray-200 dark:border-gray-700 rounded-lg p-4"},[l("div",bn,[l("div",gn,[o[29]||(o[29]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Description * ",-1)),l("div",On,[u(c(ue),{modelValue:s.description,"onUpdate:modelValue":w=>s.description=w,suggestions:Me,optionLabel:"name",placeholder:"Search products or enter description...",class:"w-full",dropdown:!0,onOptionSelect:w=>Le(s,w.value)},null,8,["modelValue","onUpdate:modelValue","onOptionSelect"])])]),l("div",null,[o[30]||(o[30]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Quantity ",-1)),u(c(le),{modelValue:s.quantity,"onUpdate:modelValue":w=>s.quantity=w,min:1,class:"w-full",onInput:w=>Z(s)},null,8,["modelValue","onUpdate:modelValue","onInput"])]),l("div",null,[o[31]||(o[31]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Unit Price * ",-1)),u(c(le),{modelValue:s.unit_price,"onUpdate:modelValue":w=>s.unit_price=w,min:0,mode:"currency",currency:"USD",class:"w-full",onInput:w=>Z(s)},null,8,["modelValue","onUpdate:modelValue","onInput"])]),l("div",null,[o[32]||(o[32]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Tax Rate ",-1)),u(c(fe),{modelValue:s.tax_rate_id,"onUpdate:modelValue":w=>s.tax_rate_id=w,options:B.value,optionLabel:"label",optionValue:"id",placeholder:"No Tax",class:"w-full"},null,8,["modelValue","onUpdate:modelValue","options"])]),l("div",null,[o[33]||(o[33]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Discount % ",-1)),u(c(le),{modelValue:s.discount,"onUpdate:modelValue":w=>s.discount=w,min:0,max:100,class:"w-full"},null,8,["modelValue","onUpdate:modelValue"])])]),l("div",wn,[l("div",xn," Line Total: "+V(A(s.total)),1),p.value.items.length>1?(f(),F(c(D),{key:0,onClick:w=>Se(ie),icon:"fas fa-trash",text:"",size:"small",severity:"danger"},null,8,["onClick"])):M("",!0)])]))),128))])]),_:1})]),l("div",In,[u(c(X),{class:"shadow-md"},{title:O(()=>[...o[34]||(o[34]=[E(" Invoice Summary ",-1)])]),content:O(()=>[l("div",kn,[l("div",Sn,[o[35]||(o[35]=l("span",{class:"text-gray-600 dark:text-gray-400"},"Subtotal:",-1)),l("span",Cn,V(A(C.value)),1)]),l("div",Vn,[o[36]||(o[36]=l("span",{class:"text-gray-600 dark:text-gray-400"},"Tax:",-1)),l("span",Ln,V(A(U.value)),1)]),l("div",Mn,[o[37]||(o[37]=l("span",{class:"text-gray-600 dark:text-gray-400"},"Discount:",-1)),l("span",Kn," -"+V(A(P.value)),1)]),u(c(st)),l("div",Dn,[o[38]||(o[38]=l("span",{class:"text-lg font-semibold text-gray-900 dark:text-white"}," Total: ",-1)),l("span",Tn,V(A(g.value)),1)])])]),_:1}),u(c(X),{class:"shadow-md"},{content:O(()=>[l("div",Pn,[u(c(D),{onClick:Pe,icon:"fas fa-eye",label:"Preview Invoice",class:"w-full",outlined:""}),u(c(D),{onClick:Ke,icon:"fas fa-save",label:"Save as Draft",class:"w-full",loading:S.value},null,8,["loading"]),u(c(D),{onClick:De,icon:"fas fa-paper-plane",label:"Save & Send",class:"w-full",severity:"success",loading:S.value},null,8,["loading"])])]),_:1})])]))]),u(c(he),{visible:v.value,"onUpdate:visible":o[14]||(o[14]=s=>v.value=s),modal:"",header:"Create New Customer",style:{width:"500px"}},{footer:O(()=>[u(c(D),{onClick:o[13]||(o[13]=s=>v.value=!1),label:d.$t("common.cancel"),text:""},null,8,["label"]),u(c(D),{onClick:Te,label:"Create Customer",loading:S.value},null,8,["loading"])]),default:O(()=>[l("div",$n,[l("div",null,[o[39]||(o[39]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Customer Name * ",-1)),u(c(J),{modelValue:y.value.name,"onUpdate:modelValue":o[9]||(o[9]=s=>y.value.name=s),class:"w-full",placeholder:"Enter customer name"},null,8,["modelValue"])]),l("div",null,[o[40]||(o[40]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Email ",-1)),u(c(J),{modelValue:y.value.email,"onUpdate:modelValue":o[10]||(o[10]=s=>y.value.email=s),type:"email",class:"w-full",placeholder:"customer@example.com"},null,8,["modelValue"])]),l("div",null,[o[41]||(o[41]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Phone ",-1)),u(c(J),{modelValue:y.value.phone,"onUpdate:modelValue":o[11]||(o[11]=s=>y.value.phone=s),class:"w-full",placeholder:"+1 (555) 123-4567"},null,8,["modelValue"])]),l("div",null,[o[42]||(o[42]=l("label",{class:"block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"}," Address ",-1)),u(c(te),{modelValue:y.value.address,"onUpdate:modelValue":o[12]||(o[12]=s=>y.value.address=s),class:"w-full",placeholder:"123 Main St, City, State 12345",rows:"2"},null,8,["modelValue"])])])]),_:1},8,["visible"]),u(c(he),{visible:b.value,"onUpdate:visible":o[16]||(o[16]=s=>b.value=s),modal:"",header:"Invoice Preview",style:{width:"800px"},maximizable:""},{footer:O(()=>[u(c(D),{onClick:o[15]||(o[15]=s=>b.value=!1),label:d.$t("common.close")},null,8,["label"])]),default:O(()=>[l("div",zn,[l("div",An,[o[43]||(o[43]=l("i",{class:"fas fa-file-invoice text-4xl mb-4"},null,-1)),o[44]||(o[44]=l("p",null,"Invoice preview will be rendered here",-1)),l("div",Fn,[l("h3",En,V(p.value.invoice_number),1),l("p",Rn,"Total: "+V(A(g.value)),1),l("p",jn,"Items: "+V(p.value.items.length),1)])])])]),_:1},8,["visible"]),u(c(lt),{ref_key:"toast",ref:i},null,512)]))}},ai=pt(Bn,[["__scopeId","data-v-9ee43d84"]]);export{ai as default};
