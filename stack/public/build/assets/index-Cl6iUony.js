import{B as w,ay as z,H as h,ao as v,aG as O,aH as $,L as j,c as y,o as b,D as L,m as x,i as T,A as _,t as k,n as C,G as H}from"./app-DjFlEUH4.js";import{B as A,c as B,f as D}from"./index-DL9KtRp0.js";var I=w.extend({name:"focustrap-directive"}),K=A.extend({style:I});function m(e){"@babel/helpers - typeof";return m=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},m(e)}function F(e,t){var a=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(n){return Object.getOwnPropertyDescriptor(e,n).enumerable})),a.push.apply(a,r)}return a}function E(e){for(var t=1;t<arguments.length;t++){var a=arguments[t]!=null?arguments[t]:{};t%2?F(Object(a),!0).forEach(function(r){N(e,r,a[r])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(a)):F(Object(a)).forEach(function(r){Object.defineProperty(e,r,Object.getOwnPropertyDescriptor(a,r))})}return e}function N(e,t,a){return(t=G(t))in e?Object.defineProperty(e,t,{value:a,enumerable:!0,configurable:!0,writable:!0}):e[t]=a,e}function G(e){var t=q(e,"string");return m(t)=="symbol"?t:t+""}function q(e,t){if(m(e)!="object"||!e)return e;var a=e[Symbol.toPrimitive];if(a!==void 0){var r=a.call(e,t);if(m(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}var ot=K.extend("focustrap",{mounted:function(t,a){var r=a.value||{},n=r.disabled;n||(this.createHiddenFocusableElements(t,a),this.bind(t,a),this.autoElementFocus(t,a)),t.setAttribute("data-pd-focustrap",!0),this.$el=t},updated:function(t,a){var r=a.value||{},n=r.disabled;n&&this.unbind(t)},unmounted:function(t){this.unbind(t)},methods:{getComputedSelector:function(t){return':not(.p-hidden-focusable):not([data-p-hidden-focusable="true"])'.concat(t??"")},bind:function(t,a){var r=this,n=a.value||{},o=n.onFocusIn,l=n.onFocusOut;t.$_pfocustrap_mutationobserver=new MutationObserver(function(i){i.forEach(function(c){if(c.type==="childList"&&!t.contains(document.activeElement)){var p=function(s){var u=$(s)?$(s,r.getComputedSelector(t.$_pfocustrap_focusableselector))?s:v(t,r.getComputedSelector(t.$_pfocustrap_focusableselector)):v(s);return j(u)?u:s.nextSibling&&p(s.nextSibling)};h(p(c.nextSibling))}})}),t.$_pfocustrap_mutationobserver.disconnect(),t.$_pfocustrap_mutationobserver.observe(t,{childList:!0}),t.$_pfocustrap_focusinlistener=function(i){return o&&o(i)},t.$_pfocustrap_focusoutlistener=function(i){return l&&l(i)},t.addEventListener("focusin",t.$_pfocustrap_focusinlistener),t.addEventListener("focusout",t.$_pfocustrap_focusoutlistener)},unbind:function(t){t.$_pfocustrap_mutationobserver&&t.$_pfocustrap_mutationobserver.disconnect(),t.$_pfocustrap_focusinlistener&&t.removeEventListener("focusin",t.$_pfocustrap_focusinlistener)&&(t.$_pfocustrap_focusinlistener=null),t.$_pfocustrap_focusoutlistener&&t.removeEventListener("focusout",t.$_pfocustrap_focusoutlistener)&&(t.$_pfocustrap_focusoutlistener=null)},autoFocus:function(t){this.autoElementFocus(this.$el,{value:E(E({},t),{},{autoFocus:!0})})},autoElementFocus:function(t,a){var r=a.value||{},n=r.autoFocusSelector,o=n===void 0?"":n,l=r.firstFocusableSelector,i=l===void 0?"":l,c=r.autoFocus,p=c===void 0?!1:c,d=v(t,"[autofocus]".concat(this.getComputedSelector(o)));p&&!d&&(d=v(t,this.getComputedSelector(i))),h(d)},onFirstHiddenElementFocus:function(t){var a,r=t.currentTarget,n=t.relatedTarget,o=n===r.$_pfocustrap_lasthiddenfocusableelement||!((a=this.$el)!==null&&a!==void 0&&a.contains(n))?v(r.parentElement,this.getComputedSelector(r.$_pfocustrap_focusableselector)):r.$_pfocustrap_lasthiddenfocusableelement;h(o)},onLastHiddenElementFocus:function(t){var a,r=t.currentTarget,n=t.relatedTarget,o=n===r.$_pfocustrap_firsthiddenfocusableelement||!((a=this.$el)!==null&&a!==void 0&&a.contains(n))?z(r.parentElement,this.getComputedSelector(r.$_pfocustrap_focusableselector)):r.$_pfocustrap_firsthiddenfocusableelement;h(o)},createHiddenFocusableElements:function(t,a){var r=this,n=a.value||{},o=n.tabIndex,l=o===void 0?0:o,i=n.firstFocusableSelector,c=i===void 0?"":i,p=n.lastFocusableSelector,d=p===void 0?"":p,s=function(S){return O("span",{class:"p-hidden-accessible p-hidden-focusable",tabIndex:l,role:"presentation","aria-hidden":!0,"data-p-hidden-accessible":!0,"data-p-hidden-focusable":!0,onFocus:S?.bind(r)})},u=s(this.onFirstHiddenElementFocus),f=s(this.onLastHiddenElementFocus);u.$_pfocustrap_lasthiddenfocusableelement=f,u.$_pfocustrap_focusableselector=c,u.setAttribute("data-pc-section","firstfocusableelement"),f.$_pfocustrap_firsthiddenfocusableelement=u,f.$_pfocustrap_focusableselector=d,f.setAttribute("data-pc-section","lastfocusableelement"),t.prepend(u),t.append(f)}}}),M=`
    .p-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: dt('avatar.width');
        height: dt('avatar.height');
        font-size: dt('avatar.font.size');
        background: dt('avatar.background');
        color: dt('avatar.color');
        border-radius: dt('avatar.border.radius');
    }

    .p-avatar-image {
        background: transparent;
    }

    .p-avatar-circle {
        border-radius: 50%;
    }

    .p-avatar-circle img {
        border-radius: 50%;
    }

    .p-avatar-icon {
        font-size: dt('avatar.icon.size');
        width: dt('avatar.icon.size');
        height: dt('avatar.icon.size');
    }

    .p-avatar img {
        width: 100%;
        height: 100%;
    }

    .p-avatar-lg {
        width: dt('avatar.lg.width');
        height: dt('avatar.lg.width');
        font-size: dt('avatar.lg.font.size');
    }

    .p-avatar-lg .p-avatar-icon {
        font-size: dt('avatar.lg.icon.size');
        width: dt('avatar.lg.icon.size');
        height: dt('avatar.lg.icon.size');
    }

    .p-avatar-xl {
        width: dt('avatar.xl.width');
        height: dt('avatar.xl.width');
        font-size: dt('avatar.xl.font.size');
    }

    .p-avatar-xl .p-avatar-icon {
        font-size: dt('avatar.xl.icon.size');
        width: dt('avatar.xl.icon.size');
        height: dt('avatar.xl.icon.size');
    }

    .p-avatar-group {
        display: flex;
        align-items: center;
    }

    .p-avatar-group .p-avatar + .p-avatar {
        margin-inline-start: dt('avatar.group.offset');
    }

    .p-avatar-group .p-avatar {
        border: 2px solid dt('avatar.group.border.color');
    }

    .p-avatar-group .p-avatar-lg + .p-avatar-lg {
        margin-inline-start: dt('avatar.lg.group.offset');
    }

    .p-avatar-group .p-avatar-xl + .p-avatar-xl {
        margin-inline-start: dt('avatar.xl.group.offset');
    }
`,U={root:function(t){var a=t.props;return["p-avatar p-component",{"p-avatar-image":a.image!=null,"p-avatar-circle":a.shape==="circle","p-avatar-lg":a.size==="large","p-avatar-xl":a.size==="xlarge"}]},label:"p-avatar-label",icon:"p-avatar-icon"},V=w.extend({name:"avatar",style:M,classes:U}),J={name:"BaseAvatar",extends:B,props:{label:{type:String,default:null},icon:{type:String,default:null},image:{type:String,default:null},size:{type:String,default:"normal"},shape:{type:String,default:"square"},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:V,provide:function(){return{$pcAvatar:this,$parentInstance:this}}};function g(e){"@babel/helpers - typeof";return g=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(t){return typeof t}:function(t){return t&&typeof Symbol=="function"&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},g(e)}function P(e,t,a){return(t=Q(t))in e?Object.defineProperty(e,t,{value:a,enumerable:!0,configurable:!0,writable:!0}):e[t]=a,e}function Q(e){var t=R(e,"string");return g(t)=="symbol"?t:t+""}function R(e,t){if(g(e)!="object"||!e)return e;var a=e[Symbol.toPrimitive];if(a!==void 0){var r=a.call(e,t);if(g(r)!="object")return r;throw new TypeError("@@toPrimitive must return a primitive value.")}return(t==="string"?String:Number)(e)}var W={name:"Avatar",extends:J,inheritAttrs:!1,emits:["error"],methods:{onError:function(t){this.$emit("error",t)}},computed:{dataP:function(){return D(P(P({},this.shape,this.shape),this.size,this.size))}}},X=["aria-labelledby","aria-label","data-p"],Y=["data-p"],Z=["data-p"],tt=["src","alt","data-p"];function et(e,t,a,r,n,o){return b(),y("div",_({class:e.cx("root"),"aria-labelledby":e.ariaLabelledby,"aria-label":e.ariaLabel},e.ptmi("root"),{"data-p":o.dataP}),[L(e.$slots,"default",{},function(){return[e.label?(b(),y("span",_({key:0,class:e.cx("label")},e.ptm("label"),{"data-p":o.dataP}),k(e.label),17,Y)):e.$slots.icon?(b(),x(H(e.$slots.icon),{key:1,class:C(e.cx("icon"))},null,8,["class"])):e.icon?(b(),y("span",_({key:2,class:[e.cx("icon"),e.icon]},e.ptm("icon"),{"data-p":o.dataP}),null,16,Z)):e.image?(b(),y("img",_({key:3,src:e.image,alt:e.ariaLabel,onError:t[0]||(t[0]=function(){return o.onError&&o.onError.apply(o,arguments)})},e.ptm("image"),{"data-p":o.dataP}),null,16,tt)):T("",!0)]})],16,X)}W.render=et;export{ot as F,W as s};
