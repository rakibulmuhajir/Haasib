import{d as a,f as u}from"./index-DL9KtRp0.js";import{B as l,A as d,c as p,o as s}from"./app-DjFlEUH4.js";var c={name:"BaseInput",extends:a,props:{size:{type:String,default:null},fluid:{type:Boolean,default:null},variant:{type:String,default:null}},inject:{$parentInstance:{default:void 0},$pcFluid:{default:void 0}},computed:{$variant:function(){var t;return(t=this.variant)!==null&&t!==void 0?t:this.$primevue.config.inputStyle||this.$primevue.config.inputVariant},$fluid:function(){var t;return(t=this.fluid)!==null&&t!==void 0?t:!!this.$pcFluid},hasFluid:function(){return this.$fluid}}},f=`
    .p-inputtext {
        font-family: inherit;
        font-feature-settings: inherit;
        font-size: 1rem;
        color: dt('inputtext.color');
        background: dt('inputtext.background');
        padding-block: dt('inputtext.padding.y');
        padding-inline: dt('inputtext.padding.x');
        border: 1px solid dt('inputtext.border.color');
        transition:
            background dt('inputtext.transition.duration'),
            color dt('inputtext.transition.duration'),
            border-color dt('inputtext.transition.duration'),
            outline-color dt('inputtext.transition.duration'),
            box-shadow dt('inputtext.transition.duration');
        appearance: none;
        border-radius: dt('inputtext.border.radius');
        outline-color: transparent;
        box-shadow: dt('inputtext.shadow');
    }

    .p-inputtext:enabled:hover {
        border-color: dt('inputtext.hover.border.color');
    }

    .p-inputtext:enabled:focus {
        border-color: dt('inputtext.focus.border.color');
        box-shadow: dt('inputtext.focus.ring.shadow');
        outline: dt('inputtext.focus.ring.width') dt('inputtext.focus.ring.style') dt('inputtext.focus.ring.color');
        outline-offset: dt('inputtext.focus.ring.offset');
    }

    .p-inputtext.p-invalid {
        border-color: dt('inputtext.invalid.border.color');
    }

    .p-inputtext.p-variant-filled {
        background: dt('inputtext.filled.background');
    }

    .p-inputtext.p-variant-filled:enabled:hover {
        background: dt('inputtext.filled.hover.background');
    }

    .p-inputtext.p-variant-filled:enabled:focus {
        background: dt('inputtext.filled.focus.background');
    }

    .p-inputtext:disabled {
        opacity: 1;
        background: dt('inputtext.disabled.background');
        color: dt('inputtext.disabled.color');
    }

    .p-inputtext::placeholder {
        color: dt('inputtext.placeholder.color');
    }

    .p-inputtext.p-invalid::placeholder {
        color: dt('inputtext.invalid.placeholder.color');
    }

    .p-inputtext-sm {
        font-size: dt('inputtext.sm.font.size');
        padding-block: dt('inputtext.sm.padding.y');
        padding-inline: dt('inputtext.sm.padding.x');
    }

    .p-inputtext-lg {
        font-size: dt('inputtext.lg.font.size');
        padding-block: dt('inputtext.lg.padding.y');
        padding-inline: dt('inputtext.lg.padding.x');
    }

    .p-inputtext-fluid {
        width: 100%;
    }
`,x={root:function(t){var i=t.instance,e=t.props;return["p-inputtext p-component",{"p-filled":i.$filled,"p-inputtext-sm p-inputfield-sm":e.size==="small","p-inputtext-lg p-inputfield-lg":e.size==="large","p-invalid":i.$invalid,"p-variant-filled":i.$variant==="filled","p-inputtext-fluid":i.$fluid}]}},b=l.extend({name:"inputtext",style:f,classes:x}),v={name:"BaseInputText",extends:c,style:b,provide:function(){return{$pcInputText:this,$parentInstance:this}}};function o(n){"@babel/helpers - typeof";return o=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},o(n)}function g(n,t,i){return(t=m(t))in n?Object.defineProperty(n,t,{value:i,enumerable:!0,configurable:!0,writable:!0}):n[t]=i,n}function m(n){var t=h(n,"string");return o(t)=="symbol"?t:t+""}function h(n,t){if(o(n)!="object"||!n)return n;var i=n[Symbol.toPrimitive];if(i!==void 0){var e=i.call(n,t);if(o(e)!="object")return e;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(n)}var y={name:"InputText",extends:v,inheritAttrs:!1,methods:{onInput:function(t){this.writeValue(t.target.value,t)}},computed:{attrs:function(){return d(this.ptmi("root",{context:{filled:this.$filled,disabled:this.disabled}}),this.formField)},dataP:function(){return u(g({invalid:this.$invalid,fluid:this.$fluid,filled:this.$variant==="filled"},this.size,this.size))}}},$=["value","name","disabled","aria-invalid","data-p"];function k(n,t,i,e,I,r){return s(),p("input",d({type:"text",class:n.cx("root"),value:n.d_value,name:n.name,disabled:n.disabled,"aria-invalid":n.$invalid||void 0,"data-p":r.dataP,onInput:t[0]||(t[0]=function(){return r.onInput&&r.onInput.apply(r,arguments)})},r.attrs),null,16,$)}y.render=k;export{c as a,y as s};
