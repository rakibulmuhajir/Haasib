import{B as ee,_ as pe,c as h,o as l,a,A as x,L as B,I as X,M as ne,Y as ge,N as H,H as V,O as ve,P as le,S as Q,y as ye,F as D,J as R,i as L,m as F,h as Ie,n as j,G as W,t as g,T as xe,D as $,d as v,U as ke,V as we,j as ue,q as de,r as K,k as O,s as ce,w,e as m,b as E}from"./app-DjFlEUH4.js";import{c as te,R as Pe,x as Z,d as _e,f as Le,s as M,b as ie,a as A}from"./index-DL9KtRp0.js";import{s as Se}from"./index-sAMPWKi9.js";import{s as Ce,a as Me}from"./index-Dp6GxYCd.js";import{s as Ke}from"./index-CyCOy2Ll.js";import{C as Ae}from"./CompanySwitcher-DfMLVd5D.js";import{s as Fe}from"./index-jrbmM0En.js";import{s as Oe}from"./index-CLnL3lam.js";import{s as Ee}from"./index-di-bTznt.js";import{_ as De}from"./_plugin-vue_export-helper-DlAUqK2U.js";import"./index-Cl6iUony.js";var je={root:{position:"relative"}},Te={root:"p-chart"},ze=ee.extend({name:"chart",classes:Te,inlineStyles:je}),Ve={name:"BaseChart",extends:te,props:{type:String,data:null,options:null,plugins:null,width:{type:Number,default:300},height:{type:Number,default:150},canvasProps:{type:null,default:null}},style:ze,provide:function(){return{$pcChart:this,$parentInstance:this}}},me={name:"Chart",extends:Ve,inheritAttrs:!1,emits:["select","loaded"],chart:null,watch:{data:{handler:function(){this.reinit()},deep:!0},type:function(){this.reinit()},options:function(){this.reinit()}},mounted:function(){this.initChart()},beforeUnmount:function(){this.chart&&(this.chart.destroy(),this.chart=null)},methods:{initChart:function(){var e=this;pe(()=>import("./auto-Cj0TocxD.js"),[]).then(function(n){e.chart&&(e.chart.destroy(),e.chart=null),n&&n.default&&(e.chart=new n.default(e.$refs.canvas,{type:e.type,data:e.data,options:e.options,plugins:e.plugins})),e.$emit("loaded",e.chart)})},getCanvas:function(){return this.$canvas},getChart:function(){return this.chart},getBase64Image:function(){return this.chart.toBase64Image()},refresh:function(){this.chart&&this.chart.update()},reinit:function(){this.initChart()},onCanvasClick:function(e){if(this.chart){var n=this.chart.getElementsAtEventForMode(e,"nearest",{intersect:!0},!1),i=this.chart.getElementsAtEventForMode(e,"dataset",{intersect:!0},!1);n&&n[0]&&i&&this.$emit("select",{originalEvent:e,element:n[0],dataset:i})}},generateLegend:function(){if(this.chart)return this.chart.generateLegend()}}};function N(t){"@babel/helpers - typeof";return N=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},N(t)}function se(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var i=Object.getOwnPropertySymbols(t);e&&(i=i.filter(function(o){return Object.getOwnPropertyDescriptor(t,o).enumerable})),n.push.apply(n,i)}return n}function ae(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?se(Object(n),!0).forEach(function(i){Be(t,i,n[i])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):se(Object(n)).forEach(function(i){Object.defineProperty(t,i,Object.getOwnPropertyDescriptor(n,i))})}return t}function Be(t,e,n){return(e=$e(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function $e(t){var e=Re(t,"string");return N(e)=="symbol"?e:e+""}function Re(t,e){if(N(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var i=n.call(t,e);if(N(i)!="object")return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var Ne=["width","height"];function qe(t,e,n,i,o,s){return l(),h("div",x({class:t.cx("root"),style:t.sx("root")},t.ptmi("root")),[a("canvas",x({ref:"canvas",width:t.width,height:t.height,onClick:e[0]||(e[0]=function(u){return s.onCanvasClick(u)})},ae(ae({},t.canvasProps),t.ptm("canvas"))),null,16,Ne)],16)}me.render=qe;var Ge=`
    .p-menubar {
        display: flex;
        align-items: center;
        background: dt('menubar.background');
        border: 1px solid dt('menubar.border.color');
        border-radius: dt('menubar.border.radius');
        color: dt('menubar.color');
        padding: dt('menubar.padding');
        gap: dt('menubar.gap');
    }

    .p-menubar-start,
    .p-megamenu-end {
        display: flex;
        align-items: center;
    }

    .p-menubar-root-list,
    .p-menubar-submenu {
        display: flex;
        margin: 0;
        padding: 0;
        list-style: none;
        outline: 0 none;
    }

    .p-menubar-root-list {
        align-items: center;
        flex-wrap: wrap;
        gap: dt('menubar.gap');
    }

    .p-menubar-root-list > .p-menubar-item > .p-menubar-item-content {
        border-radius: dt('menubar.base.item.border.radius');
    }

    .p-menubar-root-list > .p-menubar-item > .p-menubar-item-content > .p-menubar-item-link {
        padding: dt('menubar.base.item.padding');
    }

    .p-menubar-item-content {
        transition:
            background dt('menubar.transition.duration'),
            color dt('menubar.transition.duration');
        border-radius: dt('menubar.item.border.radius');
        color: dt('menubar.item.color');
    }

    .p-menubar-item-link {
        cursor: pointer;
        display: flex;
        align-items: center;
        text-decoration: none;
        overflow: hidden;
        position: relative;
        color: inherit;
        padding: dt('menubar.item.padding');
        gap: dt('menubar.item.gap');
        user-select: none;
        outline: 0 none;
    }

    .p-menubar-item-label {
        line-height: 1;
    }

    .p-menubar-item-icon {
        color: dt('menubar.item.icon.color');
    }

    .p-menubar-submenu-icon {
        color: dt('menubar.submenu.icon.color');
        margin-left: auto;
        font-size: dt('menubar.submenu.icon.size');
        width: dt('menubar.submenu.icon.size');
        height: dt('menubar.submenu.icon.size');
    }

    .p-menubar-submenu .p-menubar-submenu-icon:dir(rtl) {
        margin-left: 0;
        margin-right: auto;
    }

    .p-menubar-item.p-focus > .p-menubar-item-content {
        color: dt('menubar.item.focus.color');
        background: dt('menubar.item.focus.background');
    }

    .p-menubar-item.p-focus > .p-menubar-item-content .p-menubar-item-icon {
        color: dt('menubar.item.icon.focus.color');
    }

    .p-menubar-item.p-focus > .p-menubar-item-content .p-menubar-submenu-icon {
        color: dt('menubar.submenu.icon.focus.color');
    }

    .p-menubar-item:not(.p-disabled) > .p-menubar-item-content:hover {
        color: dt('menubar.item.focus.color');
        background: dt('menubar.item.focus.background');
    }

    .p-menubar-item:not(.p-disabled) > .p-menubar-item-content:hover .p-menubar-item-icon {
        color: dt('menubar.item.icon.focus.color');
    }

    .p-menubar-item:not(.p-disabled) > .p-menubar-item-content:hover .p-menubar-submenu-icon {
        color: dt('menubar.submenu.icon.focus.color');
    }

    .p-menubar-item-active > .p-menubar-item-content {
        color: dt('menubar.item.active.color');
        background: dt('menubar.item.active.background');
    }

    .p-menubar-item-active > .p-menubar-item-content .p-menubar-item-icon {
        color: dt('menubar.item.icon.active.color');
    }

    .p-menubar-item-active > .p-menubar-item-content .p-menubar-submenu-icon {
        color: dt('menubar.submenu.icon.active.color');
    }

    .p-menubar-submenu {
        display: none;
        position: absolute;
        min-width: 12.5rem;
        z-index: 1;
        background: dt('menubar.submenu.background');
        border: 1px solid dt('menubar.submenu.border.color');
        border-radius: dt('menubar.submenu.border.radius');
        box-shadow: dt('menubar.submenu.shadow');
        color: dt('menubar.submenu.color');
        flex-direction: column;
        padding: dt('menubar.submenu.padding');
        gap: dt('menubar.submenu.gap');
    }

    .p-menubar-submenu .p-menubar-separator {
        border-block-start: 1px solid dt('menubar.separator.border.color');
    }

    .p-menubar-submenu .p-menubar-item {
        position: relative;
    }

    .p-menubar-submenu > .p-menubar-item-active > .p-menubar-submenu {
        display: block;
        left: 100%;
        top: 0;
    }

    .p-menubar-end {
        margin-left: auto;
        align-self: center;
    }

    .p-menubar-end:dir(rtl) {
        margin-left: 0;
        margin-right: auto;
    }

    .p-menubar-button {
        display: none;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        width: dt('menubar.mobile.button.size');
        height: dt('menubar.mobile.button.size');
        position: relative;
        color: dt('menubar.mobile.button.color');
        border: 0 none;
        background: transparent;
        border-radius: dt('menubar.mobile.button.border.radius');
        transition:
            background dt('menubar.transition.duration'),
            color dt('menubar.transition.duration'),
            outline-color dt('menubar.transition.duration');
        outline-color: transparent;
    }

    .p-menubar-button:hover {
        color: dt('menubar.mobile.button.hover.color');
        background: dt('menubar.mobile.button.hover.background');
    }

    .p-menubar-button:focus-visible {
        box-shadow: dt('menubar.mobile.button.focus.ring.shadow');
        outline: dt('menubar.mobile.button.focus.ring.width') dt('menubar.mobile.button.focus.ring.style') dt('menubar.mobile.button.focus.ring.color');
        outline-offset: dt('menubar.mobile.button.focus.ring.offset');
    }

    .p-menubar-mobile {
        position: relative;
    }

    .p-menubar-mobile .p-menubar-button {
        display: flex;
    }

    .p-menubar-mobile .p-menubar-root-list {
        position: absolute;
        display: none;
        width: 100%;
        flex-direction: column;
        top: 100%;
        left: 0;
        z-index: 1;
        padding: dt('menubar.submenu.padding');
        background: dt('menubar.submenu.background');
        border: 1px solid dt('menubar.submenu.border.color');
        box-shadow: dt('menubar.submenu.shadow');
        border-radius: dt('menubar.submenu.border.radius');
        gap: dt('menubar.submenu.gap');
    }

    .p-menubar-mobile .p-menubar-root-list:dir(rtl) {
        left: auto;
        right: 0;
    }

    .p-menubar-mobile .p-menubar-root-list > .p-menubar-item > .p-menubar-item-content > .p-menubar-item-link {
        padding: dt('menubar.item.padding');
    }

    .p-menubar-mobile-active .p-menubar-root-list {
        display: flex;
    }

    .p-menubar-mobile .p-menubar-root-list .p-menubar-item {
        width: 100%;
        position: static;
    }

    .p-menubar-mobile .p-menubar-root-list .p-menubar-separator {
        border-block-start: 1px solid dt('menubar.separator.border.color');
    }

    .p-menubar-mobile .p-menubar-root-list > .p-menubar-item > .p-menubar-item-content .p-menubar-submenu-icon {
        margin-left: auto;
        transition: transform 0.2s;
    }

    .p-menubar-mobile .p-menubar-root-list > .p-menubar-item > .p-menubar-item-content .p-menubar-submenu-icon:dir(rtl),
    .p-menubar-mobile .p-menubar-submenu-icon:dir(rtl) {
        margin-left: 0;
        margin-right: auto;
    }

    .p-menubar-mobile .p-menubar-root-list > .p-menubar-item-active > .p-menubar-item-content .p-menubar-submenu-icon {
        transform: rotate(-180deg);
    }

    .p-menubar-mobile .p-menubar-submenu .p-menubar-submenu-icon {
        transition: transform 0.2s;
        transform: rotate(90deg);
    }

    .p-menubar-mobile .p-menubar-item-active > .p-menubar-item-content .p-menubar-submenu-icon {
        transform: rotate(-90deg);
    }

    .p-menubar-mobile .p-menubar-submenu {
        width: 100%;
        position: static;
        box-shadow: none;
        border: 0 none;
        padding-inline-start: dt('menubar.submenu.mobile.indent');
        padding-inline-end: 0;
    }
`,Ue={submenu:function(e){var n=e.instance,i=e.processedItem;return{display:n.isItemActive(i)?"flex":"none"}}},He={root:function(e){var n=e.instance;return["p-menubar p-component",{"p-menubar-mobile":n.queryMatches,"p-menubar-mobile-active":n.mobileActive}]},start:"p-menubar-start",button:"p-menubar-button",rootList:"p-menubar-root-list",item:function(e){var n=e.instance,i=e.processedItem;return["p-menubar-item",{"p-menubar-item-active":n.isItemActive(i),"p-focus":n.isItemFocused(i),"p-disabled":n.isItemDisabled(i)}]},itemContent:"p-menubar-item-content",itemLink:"p-menubar-item-link",itemIcon:"p-menubar-item-icon",itemLabel:"p-menubar-item-label",submenuIcon:"p-menubar-submenu-icon",submenu:"p-menubar-submenu",separator:"p-menubar-separator",end:"p-menubar-end"},We=ee.extend({name:"menubar",style:Ge,classes:He,inlineStyles:Ue}),Ye={name:"BaseMenubar",extends:te,props:{model:{type:Array,default:null},buttonProps:{type:null,default:null},breakpoint:{type:String,default:"960px"},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:We,provide:function(){return{$pcMenubar:this,$parentInstance:this}}},he={name:"MenubarSub",hostName:"Menubar",extends:te,emits:["item-mouseenter","item-click","item-mousemove"],props:{items:{type:Array,default:null},root:{type:Boolean,default:!1},popup:{type:Boolean,default:!1},mobileActive:{type:Boolean,default:!1},templates:{type:Object,default:null},level:{type:Number,default:0},menuId:{type:String,default:null},focusedItemId:{type:String,default:null},activeItemPath:{type:Object,default:null}},list:null,methods:{getItemId:function(e){return"".concat(this.menuId,"_").concat(e.key)},getItemKey:function(e){return this.getItemId(e)},getItemProp:function(e,n,i){return e&&e.item?le(e.item[n],i):void 0},getItemLabel:function(e){return this.getItemProp(e,"label")},getItemLabelId:function(e){return"".concat(this.menuId,"_").concat(e.key,"_label")},getPTOptions:function(e,n,i){return this.ptm(i,{context:{item:e.item,index:n,active:this.isItemActive(e),focused:this.isItemFocused(e),disabled:this.isItemDisabled(e),level:this.level}})},isItemActive:function(e){return this.activeItemPath.some(function(n){return n.key===e.key})},isItemVisible:function(e){return this.getItemProp(e,"visible")!==!1},isItemDisabled:function(e){return this.getItemProp(e,"disabled")},isItemFocused:function(e){return this.focusedItemId===this.getItemId(e)},isItemGroup:function(e){return B(e.items)},onItemClick:function(e,n){this.getItemProp(n,"command",{originalEvent:e,item:n.item}),this.$emit("item-click",{originalEvent:e,processedItem:n,isFocus:!0})},onItemMouseEnter:function(e,n){this.$emit("item-mouseenter",{originalEvent:e,processedItem:n})},onItemMouseMove:function(e,n){this.$emit("item-mousemove",{originalEvent:e,processedItem:n})},getAriaPosInset:function(e){return e-this.calculateAriaSetSize.slice(0,e).length+1},getMenuItemProps:function(e,n){return{action:x({class:this.cx("itemLink"),tabindex:-1},this.getPTOptions(e,n,"itemLink")),icon:x({class:[this.cx("itemIcon"),this.getItemProp(e,"icon")]},this.getPTOptions(e,n,"itemIcon")),label:x({class:this.cx("itemLabel")},this.getPTOptions(e,n,"itemLabel")),submenuicon:x({class:this.cx("submenuIcon")},this.getPTOptions(e,n,"submenuIcon"))}}},computed:{calculateAriaSetSize:function(){var e=this;return this.items.filter(function(n){return e.isItemVisible(n)&&e.getItemProp(n,"separator")})},getAriaSetSize:function(){var e=this;return this.items.filter(function(n){return e.isItemVisible(n)&&!e.getItemProp(n,"separator")}).length}},components:{AngleRightIcon:Me,AngleDownIcon:Ke},directives:{ripple:Pe}},Je=["id","aria-label","aria-disabled","aria-expanded","aria-haspopup","aria-setsize","aria-posinset","data-p-active","data-p-focused","data-p-disabled"],Xe=["onClick","onMouseenter","onMousemove"],Ze=["href","target"],Qe=["id"],et=["id"];function tt(t,e,n,i,o,s){var u=Q("MenubarSub",!0),y=ye("ripple");return l(),h("ul",x({class:n.level===0?t.cx("rootList"):t.cx("submenu")},n.level===0?t.ptm("rootList"):t.ptm("submenu")),[(l(!0),h(D,null,R(n.items,function(r,p){return l(),h(D,{key:s.getItemKey(r)},[s.isItemVisible(r)&&!s.getItemProp(r,"separator")?(l(),h("li",x({key:0,id:s.getItemId(r),style:s.getItemProp(r,"style"),class:[t.cx("item",{processedItem:r}),s.getItemProp(r,"class")],role:"menuitem","aria-label":s.getItemLabel(r),"aria-disabled":s.isItemDisabled(r)||void 0,"aria-expanded":s.isItemGroup(r)?s.isItemActive(r):void 0,"aria-haspopup":s.isItemGroup(r)&&!s.getItemProp(r,"to")?"menu":void 0,"aria-setsize":s.getAriaSetSize,"aria-posinset":s.getAriaPosInset(p)},{ref_for:!0},s.getPTOptions(r,p,"item"),{"data-p-active":s.isItemActive(r),"data-p-focused":s.isItemFocused(r),"data-p-disabled":s.isItemDisabled(r)}),[a("div",x({class:t.cx("itemContent"),onClick:function(k){return s.onItemClick(k,r)},onMouseenter:function(k){return s.onItemMouseEnter(k,r)},onMousemove:function(k){return s.onItemMouseMove(k,r)}},{ref_for:!0},s.getPTOptions(r,p,"itemContent")),[n.templates.item?(l(),F(W(n.templates.item),{key:1,item:r.item,root:n.root,hasSubmenu:s.getItemProp(r,"items"),label:s.getItemLabel(r),props:s.getMenuItemProps(r,p)},null,8,["item","root","hasSubmenu","label","props"])):Ie((l(),h("a",x({key:0,href:s.getItemProp(r,"url"),class:t.cx("itemLink"),target:s.getItemProp(r,"target"),tabindex:"-1"},{ref_for:!0},s.getPTOptions(r,p,"itemLink")),[n.templates.itemicon?(l(),F(W(n.templates.itemicon),{key:0,item:r.item,class:j(t.cx("itemIcon"))},null,8,["item","class"])):s.getItemProp(r,"icon")?(l(),h("span",x({key:1,class:[t.cx("itemIcon"),s.getItemProp(r,"icon")]},{ref_for:!0},s.getPTOptions(r,p,"itemIcon")),null,16)):L("",!0),a("span",x({id:s.getItemLabelId(r),class:t.cx("itemLabel")},{ref_for:!0},s.getPTOptions(r,p,"itemLabel")),g(s.getItemLabel(r)),17,Qe),s.getItemProp(r,"items")?(l(),h(D,{key:2},[n.templates.submenuicon?(l(),F(W(n.templates.submenuicon),{key:0,root:n.root,active:s.isItemActive(r),class:j(t.cx("submenuIcon"))},null,8,["root","active","class"])):(l(),F(W(n.root?"AngleDownIcon":"AngleRightIcon"),x({key:1,class:t.cx("submenuIcon")},{ref_for:!0},s.getPTOptions(r,p,"submenuIcon")),null,16,["class"]))],64)):L("",!0)],16,Ze)),[[y]])],16,Xe),s.isItemVisible(r)&&s.isItemGroup(r)?(l(),F(u,{key:0,id:s.getItemId(r)+"_list",menuId:n.menuId,role:"menu",style:xe(t.sx("submenu",!0,{processedItem:r})),focusedItemId:n.focusedItemId,items:r.items,mobileActive:n.mobileActive,activeItemPath:n.activeItemPath,templates:n.templates,level:n.level+1,"aria-labelledby":s.getItemLabelId(r),pt:t.pt,unstyled:t.unstyled,onItemClick:e[0]||(e[0]=function(I){return t.$emit("item-click",I)}),onItemMouseenter:e[1]||(e[1]=function(I){return t.$emit("item-mouseenter",I)}),onItemMousemove:e[2]||(e[2]=function(I){return t.$emit("item-mousemove",I)})},null,8,["id","menuId","style","focusedItemId","items","mobileActive","activeItemPath","templates","level","aria-labelledby","pt","unstyled"])):L("",!0)],16,Je)):L("",!0),s.isItemVisible(r)&&s.getItemProp(r,"separator")?(l(),h("li",x({key:1,id:s.getItemId(r),class:[t.cx("separator"),s.getItemProp(r,"class")],style:s.getItemProp(r,"style"),role:"separator"},{ref_for:!0},t.ptm("separator")),null,16,et)):L("",!0)],64)}),128))],16)}he.render=tt;var be={name:"Menubar",extends:Ye,inheritAttrs:!1,emits:["focus","blur"],matchMediaListener:null,data:function(){return{mobileActive:!1,focused:!1,focusedItemInfo:{index:-1,level:0,parentKey:""},activeItemPath:[],dirty:!1,query:null,queryMatches:!1}},watch:{activeItemPath:function(e){B(e)?(this.bindOutsideClickListener(),this.bindResizeListener()):(this.unbindOutsideClickListener(),this.unbindResizeListener())}},outsideClickListener:null,container:null,menubar:null,mounted:function(){this.bindMatchMediaListener()},beforeUnmount:function(){this.mobileActive=!1,this.unbindOutsideClickListener(),this.unbindResizeListener(),this.unbindMatchMediaListener(),this.container&&Z.clear(this.container),this.container=null},methods:{getItemProp:function(e,n){return e?le(e[n]):void 0},getItemLabel:function(e){return this.getItemProp(e,"label")},isItemDisabled:function(e){return this.getItemProp(e,"disabled")},isItemVisible:function(e){return this.getItemProp(e,"visible")!==!1},isItemGroup:function(e){return B(this.getItemProp(e,"items"))},isItemSeparator:function(e){return this.getItemProp(e,"separator")},getProccessedItemLabel:function(e){return e?this.getItemLabel(e.item):void 0},isProccessedItemGroup:function(e){return e&&B(e.items)},toggle:function(e){var n=this;this.mobileActive?(this.mobileActive=!1,Z.clear(this.menubar),this.hide()):(this.mobileActive=!0,Z.set("menu",this.menubar,this.$primevue.config.zIndex.menu),setTimeout(function(){n.show()},1)),this.bindOutsideClickListener(),e.preventDefault()},show:function(){V(this.menubar)},hide:function(e,n){var i=this;this.mobileActive&&(this.mobileActive=!1,setTimeout(function(){V(i.$refs.menubutton)},0)),this.activeItemPath=[],this.focusedItemInfo={index:-1,level:0,parentKey:""},n&&V(this.menubar),this.dirty=!1},onFocus:function(e){this.focused=!0,this.focusedItemInfo=this.focusedItemInfo.index!==-1?this.focusedItemInfo:{index:this.findFirstFocusedItemIndex(),level:0,parentKey:""},this.$emit("focus",e)},onBlur:function(e){this.focused=!1,this.focusedItemInfo={index:-1,level:0,parentKey:""},this.searchValue="",this.dirty=!1,this.$emit("blur",e)},onKeyDown:function(e){var n=e.metaKey||e.ctrlKey;switch(e.code){case"ArrowDown":this.onArrowDownKey(e);break;case"ArrowUp":this.onArrowUpKey(e);break;case"ArrowLeft":this.onArrowLeftKey(e);break;case"ArrowRight":this.onArrowRightKey(e);break;case"Home":this.onHomeKey(e);break;case"End":this.onEndKey(e);break;case"Space":this.onSpaceKey(e);break;case"Enter":case"NumpadEnter":this.onEnterKey(e);break;case"Escape":this.onEscapeKey(e);break;case"Tab":this.onTabKey(e);break;case"PageDown":case"PageUp":case"Backspace":case"ShiftLeft":case"ShiftRight":break;default:!n&&ve(e.key)&&this.searchItems(e,e.key);break}},onItemChange:function(e,n){var i=e.processedItem,o=e.isFocus;if(!H(i)){var s=i.index,u=i.key,y=i.level,r=i.parentKey,p=i.items,I=B(p),k=this.activeItemPath.filter(function(C){return C.parentKey!==r&&C.parentKey!==u});I&&k.push(i),this.focusedItemInfo={index:s,level:y,parentKey:r},I&&(this.dirty=!0),o&&V(this.menubar),!(n==="hover"&&this.queryMatches)&&(this.activeItemPath=k)}},onItemClick:function(e){var n=e.originalEvent,i=e.processedItem,o=this.isProccessedItemGroup(i),s=H(i.parent),u=this.isSelected(i);if(u){var y=i.index,r=i.key,p=i.level,I=i.parentKey;this.activeItemPath=this.activeItemPath.filter(function(C){return r!==C.key&&r.startsWith(C.key)}),this.focusedItemInfo={index:y,level:p,parentKey:I},this.dirty=!s,V(this.menubar)}else if(o)this.onItemChange(e);else{var k=s?i:this.activeItemPath.find(function(C){return C.parentKey===""});this.hide(n),this.changeFocusedItemIndex(n,k?k.index:-1),this.mobileActive=!1,V(this.menubar)}},onItemMouseEnter:function(e){this.dirty&&this.onItemChange(e,"hover")},onItemMouseMove:function(e){this.focused&&this.changeFocusedItemIndex(e,e.processedItem.index)},menuButtonClick:function(e){this.toggle(e)},menuButtonKeydown:function(e){(e.code==="Enter"||e.code==="NumpadEnter"||e.code==="Space")&&this.menuButtonClick(e)},onArrowDownKey:function(e){var n=this.visibleItems[this.focusedItemInfo.index],i=n?H(n.parent):null;if(i){var o=this.isProccessedItemGroup(n);o&&(this.onItemChange({originalEvent:e,processedItem:n}),this.focusedItemInfo={index:-1,parentKey:n.key},this.onArrowRightKey(e))}else{var s=this.focusedItemInfo.index!==-1?this.findNextItemIndex(this.focusedItemInfo.index):this.findFirstFocusedItemIndex();this.changeFocusedItemIndex(e,s)}e.preventDefault()},onArrowUpKey:function(e){var n=this,i=this.visibleItems[this.focusedItemInfo.index],o=H(i.parent);if(o){var s=this.isProccessedItemGroup(i);if(s){this.onItemChange({originalEvent:e,processedItem:i}),this.focusedItemInfo={index:-1,parentKey:i.key};var u=this.findLastItemIndex();this.changeFocusedItemIndex(e,u)}}else{var y=this.activeItemPath.find(function(p){return p.key===i.parentKey});if(this.focusedItemInfo.index===0)this.focusedItemInfo={index:-1,parentKey:y?y.parentKey:""},this.searchValue="",this.onArrowLeftKey(e),this.activeItemPath=this.activeItemPath.filter(function(p){return p.parentKey!==n.focusedItemInfo.parentKey});else{var r=this.focusedItemInfo.index!==-1?this.findPrevItemIndex(this.focusedItemInfo.index):this.findLastFocusedItemIndex();this.changeFocusedItemIndex(e,r)}}e.preventDefault()},onArrowLeftKey:function(e){var n=this,i=this.visibleItems[this.focusedItemInfo.index],o=i?this.activeItemPath.find(function(u){return u.key===i.parentKey}):null;if(o)this.onItemChange({originalEvent:e,processedItem:o}),this.activeItemPath=this.activeItemPath.filter(function(u){return u.parentKey!==n.focusedItemInfo.parentKey}),e.preventDefault();else{var s=this.focusedItemInfo.index!==-1?this.findPrevItemIndex(this.focusedItemInfo.index):this.findLastFocusedItemIndex();this.changeFocusedItemIndex(e,s),e.preventDefault()}},onArrowRightKey:function(e){var n=this.visibleItems[this.focusedItemInfo.index],i=n?this.activeItemPath.find(function(u){return u.key===n.parentKey}):null;if(i){var o=this.isProccessedItemGroup(n);o&&(this.onItemChange({originalEvent:e,processedItem:n}),this.focusedItemInfo={index:-1,parentKey:n.key},this.onArrowDownKey(e))}else{var s=this.focusedItemInfo.index!==-1?this.findNextItemIndex(this.focusedItemInfo.index):this.findFirstFocusedItemIndex();this.changeFocusedItemIndex(e,s),e.preventDefault()}},onHomeKey:function(e){this.changeFocusedItemIndex(e,this.findFirstItemIndex()),e.preventDefault()},onEndKey:function(e){this.changeFocusedItemIndex(e,this.findLastItemIndex()),e.preventDefault()},onEnterKey:function(e){if(this.focusedItemInfo.index!==-1){var n=X(this.menubar,'li[id="'.concat("".concat(this.focusedItemId),'"]')),i=n&&X(n,'a[data-pc-section="itemlink"]');i?i.click():n&&n.click();var o=this.visibleItems[this.focusedItemInfo.index],s=this.isProccessedItemGroup(o);!s&&(this.focusedItemInfo.index=this.findFirstFocusedItemIndex())}e.preventDefault()},onSpaceKey:function(e){this.onEnterKey(e)},onEscapeKey:function(e){if(this.focusedItemInfo.level!==0){var n=this.focusedItemInfo;this.hide(e,!1),this.focusedItemInfo={index:Number(n.parentKey.split("_")[0]),level:0,parentKey:""}}e.preventDefault()},onTabKey:function(e){if(this.focusedItemInfo.index!==-1){var n=this.visibleItems[this.focusedItemInfo.index],i=this.isProccessedItemGroup(n);!i&&this.onItemChange({originalEvent:e,processedItem:n})}this.hide()},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(n){var i=e.container&&!e.container.contains(n.target),o=!(e.target&&(e.target===n.target||e.target.contains(n.target)));i&&o&&e.hide()},document.addEventListener("click",this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&(document.removeEventListener("click",this.outsideClickListener,!0),this.outsideClickListener=null)},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(n){ge()||e.hide(n,!0),e.mobileActive=!1},window.addEventListener("resize",this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&(window.removeEventListener("resize",this.resizeListener),this.resizeListener=null)},bindMatchMediaListener:function(){var e=this;if(!this.matchMediaListener){var n=matchMedia("(max-width: ".concat(this.breakpoint,")"));this.query=n,this.queryMatches=n.matches,this.matchMediaListener=function(){e.queryMatches=n.matches,e.mobileActive=!1},this.query.addEventListener("change",this.matchMediaListener)}},unbindMatchMediaListener:function(){this.matchMediaListener&&(this.query.removeEventListener("change",this.matchMediaListener),this.matchMediaListener=null)},isItemMatched:function(e){var n;return this.isValidItem(e)&&((n=this.getProccessedItemLabel(e))===null||n===void 0?void 0:n.toLocaleLowerCase().startsWith(this.searchValue.toLocaleLowerCase()))},isValidItem:function(e){return!!e&&!this.isItemDisabled(e.item)&&!this.isItemSeparator(e.item)&&this.isItemVisible(e.item)},isValidSelectedItem:function(e){return this.isValidItem(e)&&this.isSelected(e)},isSelected:function(e){return this.activeItemPath.some(function(n){return n.key===e.key})},findFirstItemIndex:function(){var e=this;return this.visibleItems.findIndex(function(n){return e.isValidItem(n)})},findLastItemIndex:function(){var e=this;return ne(this.visibleItems,function(n){return e.isValidItem(n)})},findNextItemIndex:function(e){var n=this,i=e<this.visibleItems.length-1?this.visibleItems.slice(e+1).findIndex(function(o){return n.isValidItem(o)}):-1;return i>-1?i+e+1:e},findPrevItemIndex:function(e){var n=this,i=e>0?ne(this.visibleItems.slice(0,e),function(o){return n.isValidItem(o)}):-1;return i>-1?i:e},findSelectedItemIndex:function(){var e=this;return this.visibleItems.findIndex(function(n){return e.isValidSelectedItem(n)})},findFirstFocusedItemIndex:function(){var e=this.findSelectedItemIndex();return e<0?this.findFirstItemIndex():e},findLastFocusedItemIndex:function(){var e=this.findSelectedItemIndex();return e<0?this.findLastItemIndex():e},searchItems:function(e,n){var i=this;this.searchValue=(this.searchValue||"")+n;var o=-1,s=!1;return this.focusedItemInfo.index!==-1?(o=this.visibleItems.slice(this.focusedItemInfo.index).findIndex(function(u){return i.isItemMatched(u)}),o=o===-1?this.visibleItems.slice(0,this.focusedItemInfo.index).findIndex(function(u){return i.isItemMatched(u)}):o+this.focusedItemInfo.index):o=this.visibleItems.findIndex(function(u){return i.isItemMatched(u)}),o!==-1&&(s=!0),o===-1&&this.focusedItemInfo.index===-1&&(o=this.findFirstFocusedItemIndex()),o!==-1&&this.changeFocusedItemIndex(e,o),this.searchTimeout&&clearTimeout(this.searchTimeout),this.searchTimeout=setTimeout(function(){i.searchValue="",i.searchTimeout=null},500),s},changeFocusedItemIndex:function(e,n){this.focusedItemInfo.index!==n&&(this.focusedItemInfo.index=n,this.scrollInView())},scrollInView:function(){var e=arguments.length>0&&arguments[0]!==void 0?arguments[0]:-1,n=e!==-1?"".concat(this.$id,"_").concat(e):this.focusedItemId,i=X(this.menubar,'li[id="'.concat(n,'"]'));i&&i.scrollIntoView&&i.scrollIntoView({block:"nearest",inline:"start"})},createProcessedItems:function(e){var n=this,i=arguments.length>1&&arguments[1]!==void 0?arguments[1]:0,o=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{},s=arguments.length>3&&arguments[3]!==void 0?arguments[3]:"",u=[];return e&&e.forEach(function(y,r){var p=(s!==""?s+"_":"")+r,I={item:y,index:r,level:i,key:p,parent:o,parentKey:s};I.items=n.createProcessedItems(y.items,i+1,I,p),u.push(I)}),u},containerRef:function(e){this.container=e},menubarRef:function(e){this.menubar=e?e.$el:void 0}},computed:{processedItems:function(){return this.createProcessedItems(this.model||[])},visibleItems:function(){var e=this,n=this.activeItemPath.find(function(i){return i.key===e.focusedItemInfo.parentKey});return n?n.items:this.processedItems},focusedItemId:function(){return this.focusedItemInfo.index!==-1?"".concat(this.$id).concat(B(this.focusedItemInfo.parentKey)?"_"+this.focusedItemInfo.parentKey:"","_").concat(this.focusedItemInfo.index):null}},components:{MenubarSub:he,BarsIcon:Ce}};function q(t){"@babel/helpers - typeof";return q=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},q(t)}function re(t,e){var n=Object.keys(t);if(Object.getOwnPropertySymbols){var i=Object.getOwnPropertySymbols(t);e&&(i=i.filter(function(o){return Object.getOwnPropertyDescriptor(t,o).enumerable})),n.push.apply(n,i)}return n}function oe(t){for(var e=1;e<arguments.length;e++){var n=arguments[e]!=null?arguments[e]:{};e%2?re(Object(n),!0).forEach(function(i){nt(t,i,n[i])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(n)):re(Object(n)).forEach(function(i){Object.defineProperty(t,i,Object.getOwnPropertyDescriptor(n,i))})}return t}function nt(t,e,n){return(e=it(e))in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}function it(t){var e=st(t,"string");return q(e)=="symbol"?e:e+""}function st(t,e){if(q(t)!="object"||!t)return t;var n=t[Symbol.toPrimitive];if(n!==void 0){var i=n.call(t,e);if(q(i)!="object")return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var at=["aria-haspopup","aria-expanded","aria-controls","aria-label"];function rt(t,e,n,i,o,s){var u=Q("BarsIcon"),y=Q("MenubarSub");return l(),h("div",x({ref:s.containerRef,class:t.cx("root")},t.ptmi("root")),[t.$slots.start?(l(),h("div",x({key:0,class:t.cx("start")},t.ptm("start")),[$(t.$slots,"start")],16)):L("",!0),$(t.$slots,t.$slots.button?"button":"menubutton",{id:t.$id,class:j(t.cx("button")),toggleCallback:function(p){return s.menuButtonClick(p)}},function(){var r;return[t.model&&t.model.length>0?(l(),h("a",x({key:0,ref:"menubutton",role:"button",tabindex:"0",class:t.cx("button"),"aria-haspopup":!!(t.model.length&&t.model.length>0),"aria-expanded":o.mobileActive,"aria-controls":t.$id,"aria-label":(r=t.$primevue.config.locale.aria)===null||r===void 0?void 0:r.navigation,onClick:e[0]||(e[0]=function(p){return s.menuButtonClick(p)}),onKeydown:e[1]||(e[1]=function(p){return s.menuButtonKeydown(p)})},oe(oe({},t.buttonProps),t.ptm("button"))),[$(t.$slots,t.$slots.buttonicon?"buttonicon":"menubuttonicon",{},function(){return[v(u,ke(we(t.ptm("buttonicon"))),null,16)]})],16,at)):L("",!0)]}),v(y,{ref:s.menubarRef,id:t.$id+"_list",role:"menubar",items:s.processedItems,templates:t.$slots,root:!0,mobileActive:o.mobileActive,tabindex:"0","aria-activedescendant":o.focused?s.focusedItemId:void 0,menuId:t.$id,focusedItemId:o.focused?s.focusedItemId:void 0,activeItemPath:o.activeItemPath,level:0,"aria-labelledby":t.ariaLabelledby,"aria-label":t.ariaLabel,pt:t.pt,unstyled:t.unstyled,onFocus:s.onFocus,onBlur:s.onBlur,onKeydown:s.onKeyDown,onItemClick:s.onItemClick,onItemMouseenter:s.onItemMouseEnter,onItemMousemove:s.onItemMouseMove},null,8,["id","items","templates","mobileActive","aria-activedescendant","menuId","focusedItemId","activeItemPath","aria-labelledby","aria-label","pt","unstyled","onFocus","onBlur","onKeydown","onItemClick","onItemMouseenter","onItemMousemove"]),t.$slots.end?(l(),h("div",x({key:1,class:t.cx("end")},t.ptm("end")),[$(t.$slots,"end")],16)):L("",!0)],16)}be.render=rt;var ot=`
    .p-toggleswitch {
        display: inline-block;
        width: dt('toggleswitch.width');
        height: dt('toggleswitch.height');
    }

    .p-toggleswitch-input {
        cursor: pointer;
        appearance: none;
        position: absolute;
        top: 0;
        inset-inline-start: 0;
        width: 100%;
        height: 100%;
        padding: 0;
        margin: 0;
        opacity: 0;
        z-index: 1;
        outline: 0 none;
        border-radius: dt('toggleswitch.border.radius');
    }

    .p-toggleswitch-slider {
        cursor: pointer;
        width: 100%;
        height: 100%;
        border-width: dt('toggleswitch.border.width');
        border-style: solid;
        border-color: dt('toggleswitch.border.color');
        background: dt('toggleswitch.background');
        transition:
            background dt('toggleswitch.transition.duration'),
            color dt('toggleswitch.transition.duration'),
            border-color dt('toggleswitch.transition.duration'),
            outline-color dt('toggleswitch.transition.duration'),
            box-shadow dt('toggleswitch.transition.duration');
        border-radius: dt('toggleswitch.border.radius');
        outline-color: transparent;
        box-shadow: dt('toggleswitch.shadow');
    }

    .p-toggleswitch-handle {
        position: absolute;
        top: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        background: dt('toggleswitch.handle.background');
        color: dt('toggleswitch.handle.color');
        width: dt('toggleswitch.handle.size');
        height: dt('toggleswitch.handle.size');
        inset-inline-start: dt('toggleswitch.gap');
        margin-block-start: calc(-1 * calc(dt('toggleswitch.handle.size') / 2));
        border-radius: dt('toggleswitch.handle.border.radius');
        transition:
            background dt('toggleswitch.transition.duration'),
            color dt('toggleswitch.transition.duration'),
            inset-inline-start dt('toggleswitch.slide.duration'),
            box-shadow dt('toggleswitch.slide.duration');
    }

    .p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider {
        background: dt('toggleswitch.checked.background');
        border-color: dt('toggleswitch.checked.border.color');
    }

    .p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-handle {
        background: dt('toggleswitch.handle.checked.background');
        color: dt('toggleswitch.handle.checked.color');
        inset-inline-start: calc(dt('toggleswitch.width') - calc(dt('toggleswitch.handle.size') + dt('toggleswitch.gap')));
    }

    .p-toggleswitch:not(.p-disabled):has(.p-toggleswitch-input:hover) .p-toggleswitch-slider {
        background: dt('toggleswitch.hover.background');
        border-color: dt('toggleswitch.hover.border.color');
    }

    .p-toggleswitch:not(.p-disabled):has(.p-toggleswitch-input:hover) .p-toggleswitch-handle {
        background: dt('toggleswitch.handle.hover.background');
        color: dt('toggleswitch.handle.hover.color');
    }

    .p-toggleswitch:not(.p-disabled):has(.p-toggleswitch-input:hover).p-toggleswitch-checked .p-toggleswitch-slider {
        background: dt('toggleswitch.checked.hover.background');
        border-color: dt('toggleswitch.checked.hover.border.color');
    }

    .p-toggleswitch:not(.p-disabled):has(.p-toggleswitch-input:hover).p-toggleswitch-checked .p-toggleswitch-handle {
        background: dt('toggleswitch.handle.checked.hover.background');
        color: dt('toggleswitch.handle.checked.hover.color');
    }

    .p-toggleswitch:not(.p-disabled):has(.p-toggleswitch-input:focus-visible) .p-toggleswitch-slider {
        box-shadow: dt('toggleswitch.focus.ring.shadow');
        outline: dt('toggleswitch.focus.ring.width') dt('toggleswitch.focus.ring.style') dt('toggleswitch.focus.ring.color');
        outline-offset: dt('toggleswitch.focus.ring.offset');
    }

    .p-toggleswitch.p-invalid > .p-toggleswitch-slider {
        border-color: dt('toggleswitch.invalid.border.color');
    }

    .p-toggleswitch.p-disabled {
        opacity: 1;
    }

    .p-toggleswitch.p-disabled .p-toggleswitch-slider {
        background: dt('toggleswitch.disabled.background');
    }

    .p-toggleswitch.p-disabled .p-toggleswitch-handle {
        background: dt('toggleswitch.handle.disabled.background');
    }
`,lt={root:{position:"relative"}},ut={root:function(e){var n=e.instance,i=e.props;return["p-toggleswitch p-component",{"p-toggleswitch-checked":n.checked,"p-disabled":i.disabled,"p-invalid":n.$invalid}]},input:"p-toggleswitch-input",slider:"p-toggleswitch-slider",handle:"p-toggleswitch-handle"},dt=ee.extend({name:"toggleswitch",style:ot,classes:ut,inlineStyles:lt}),ct={name:"BaseToggleSwitch",extends:_e,props:{trueValue:{type:null,default:!0},falseValue:{type:null,default:!1},readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},inputId:{type:String,default:null},inputClass:{type:[String,Object],default:null},inputStyle:{type:Object,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:dt,provide:function(){return{$pcToggleSwitch:this,$parentInstance:this}}},fe={name:"ToggleSwitch",extends:ct,inheritAttrs:!1,emits:["change","focus","blur"],methods:{getPTOptions:function(e){var n=e==="root"?this.ptmi:this.ptm;return n(e,{context:{checked:this.checked,disabled:this.disabled}})},onChange:function(e){if(!this.disabled&&!this.readonly){var n=this.checked?this.falseValue:this.trueValue;this.writeValue(n,e),this.$emit("change",e)}},onFocus:function(e){this.$emit("focus",e)},onBlur:function(e){var n,i;this.$emit("blur",e),(n=(i=this.formField).onBlur)===null||n===void 0||n.call(i,e)}},computed:{checked:function(){return this.d_value===this.trueValue},dataP:function(){return Le({checked:this.checked,disabled:this.disabled,invalid:this.$invalid})}}},mt=["data-p-checked","data-p-disabled","data-p"],ht=["id","checked","tabindex","disabled","readonly","aria-checked","aria-labelledby","aria-label","aria-invalid"],bt=["data-p"],ft=["data-p"];function pt(t,e,n,i,o,s){return l(),h("div",x({class:t.cx("root"),style:t.sx("root")},s.getPTOptions("root"),{"data-p-checked":s.checked,"data-p-disabled":t.disabled,"data-p":s.dataP}),[a("input",x({id:t.inputId,type:"checkbox",role:"switch",class:[t.cx("input"),t.inputClass],style:t.inputStyle,checked:s.checked,tabindex:t.tabindex,disabled:t.disabled,readonly:t.readonly,"aria-checked":s.checked,"aria-labelledby":t.ariaLabelledby,"aria-label":t.ariaLabel,"aria-invalid":t.invalid||void 0,onFocus:e[0]||(e[0]=function(){return s.onFocus&&s.onFocus.apply(s,arguments)}),onBlur:e[1]||(e[1]=function(){return s.onBlur&&s.onBlur.apply(s,arguments)}),onChange:e[2]||(e[2]=function(){return s.onChange&&s.onChange.apply(s,arguments)})},s.getPTOptions("input")),null,16,ht),a("div",x({class:t.cx("slider")},s.getPTOptions("slider"),{"data-p":s.dataP}),[a("div",x({class:t.cx("handle")},s.getPTOptions("handle"),{"data-p":s.dataP}),[$(t.$slots,"handle",{checked:s.checked})],16,ft)],16,bt)],16,mt)}fe.render=pt;const gt={class:"relative"},vt={class:"flex items-center space-x-2"},yt={class:"text-sm text-gray-700 dark:text-gray-300"},It={class:"flex items-center justify-between"},xt={class:"flex items-center space-x-3"},kt={class:"text-lg font-semibold text-gray-900 dark:text-white"},wt={key:1,class:"flex justify-center py-12"},Pt={key:2,class:"space-y-4"},_t={class:"flex items-start justify-between"},Lt={class:"flex-1"},St={class:"flex items-center space-x-3 mb-2"},Ct={class:"text-lg font-semibold text-gray-900 dark:text-white"},Mt={class:"text-gray-600 dark:text-gray-400 mb-3"},Kt={key:0,class:"mb-3"},At={class:"flex flex-wrap gap-2"},Ft={key:1,class:"text-xs text-amber-600 dark:text-amber-400 mb-2"},Ot={key:2,class:"text-xs text-red-600 dark:text-red-400"},Et={class:"ml-4"},Dt={key:3,class:"text-center py-12"},jt={__name:"ModuleToggle",emits:["moduleToggled"],setup(t,{emit:e}){const{t:n}=ue(),i=de(),o=e,s=K(!1),u=K(!1),y=K(""),r=K([]),p=K([{id:"invoicing",name:"Invoicing",description:"Create and manage invoices, track payments, and generate reports",icon:"fas fa-file-invoice",category:"billing",required_permissions:["invoices.create","invoices.view"],dependencies:[],features:["Invoice creation","Payment tracking","Auto-numbering","PDF generation"]},{id:"payments",name:"Payment Processing",description:"Process payments, manage payment methods, and reconcile transactions",icon:"fas fa-credit-card",category:"billing",required_permissions:["payments.create","payments.view"],dependencies:["invoicing"],features:["Payment recording","Multiple payment methods","Reconciliation","Refunds"]},{id:"reporting",name:"Reporting & Analytics",description:"Generate financial reports, analytics, and business insights",icon:"fas fa-chart-bar",category:"analytics",required_permissions:["reports.view","reports.export"],dependencies:[],features:["Financial reports","Custom dashboards","Data export","Scheduled reports"]},{id:"inventory",name:"Inventory Management",description:"Track inventory, manage stock levels, and handle product catalog",icon:"fas fa-boxes",category:"operations",required_permissions:["inventory.view","inventory.manage"],dependencies:[],features:["Stock tracking","Product catalog","Low stock alerts","Barcoding"]},{id:"time_tracking",name:"Time Tracking",description:"Track employee time, manage projects, and generate timesheets",icon:"fas fa-clock",category:"operations",required_permissions:["time.view","time.manage"],dependencies:[],features:["Time entry","Project tracking","Timesheet approval","Billing integration"]},{id:"hr",name:"Human Resources",description:"Manage employees, payroll, and HR operations",icon:"fas fa-users",category:"management",required_permissions:["hr.view","hr.manage"],dependencies:[],features:["Employee management","Leave tracking","Performance reviews","Document storage"]}]);O(()=>i.props.current_company);const I=O(()=>i.props.auth?.user),k=O(()=>r.value.filter(c=>c.enabled).length),C=O(()=>p.value.length),G=async()=>{u.value=!0,y.value="";try{const c=await fetch("/api/v1/modules"),b=await c.json();c.ok?(r.value=b.data||[],r.value=p.map(f=>{const S=r.value.find(_=>_.id===f.id);return{...f,enabled:S?.enabled||!1,status:S?.status||"available",last_enabled_at:S?.last_enabled_at,disabled_reason:S?.disabled_reason}})):y.value=b.message||"Failed to load modules"}catch(c){console.error("Failed to load modules:",c),c.value="Network error. Please try again."}finally{u.value=!1}},U=async c=>{const b=r.value.find(_=>_.id===c);if(!b)return;const f=!b.enabled,S=f?"enable":"disable";if(f&&b.dependencies.length>0){const _=b.dependencies.filter(z=>!r.value.find(J=>J.id===z)?.enabled);if(_.length>0){y.value=`This module requires: ${_.join(", ")}`;return}}if(f&&b.required_permissions.length>0){const _=I.value?.permissions||[],z=b.required_permissions.filter(J=>!_.includes(J));if(z.length>0){y.value=`You need these permissions: ${z.join(", ")}`;return}}u.value=!0,y.value="";try{const _=await fetch(`/api/v1/modules/${c}/${S}`,{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")}}),z=await _.json();_.ok?(b.enabled=f,b.status=f?"enabled":"disabled",b.last_enabled_at=f?new Date().toISOString():null,o("moduleToggled",{moduleId:c,enabled:f,module:b}),setTimeout(()=>{y.value=""},3e3)):y.value=z.message||`Failed to ${S} module`}catch(_){console.error(`Failed to ${S} module:`,_),_.value=`Network error. Could not ${S} module.`}finally{u.value=!1}},Y=c=>c.icon||"fas fa-cube",T=c=>c.enabled?{label:"Enabled",severity:"success"}:c.status==="error"?{label:"Error",severity:"danger"}:c.status==="maintenance"?{label:"Maintenance",severity:"warning"}:{label:"Disabled",severity:"secondary"},P=c=>{if(c.enabled)return!0;if(c.required_permissions.length>0){const b=I.value?.permissions||[];return c.required_permissions.every(f=>b.includes(f))}return!0},d=()=>{s.value=!0,G()};return ce(()=>{G()}),(c,b)=>(l(),h("div",gt,[v(m(M),{onClick:d,loading:u.value,text:"",size:"small",class:"flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"},{default:w(()=>[a("div",vt,[b[2]||(b[2]=a("i",{class:"fas fa-puzzle-piece text-gray-600 dark:text-gray-400"},null,-1)),a("span",yt,g(k.value)+"/"+g(C.value),1)]),b[3]||(b[3]=a("i",{class:"fas fa-chevron-down text-xs text-gray-500 ml-1"},null,-1))]),_:1},8,["loading"]),v(m(Fe),{visible:s.value,"onUpdate:visible":b[1]||(b[1]=f=>s.value=f),modal:"",header:m(n)("modules.title"),style:{width:"800px",maxHeight:"80vh"}},{header:w(()=>[a("div",It,[a("div",xt,[b[4]||(b[4]=a("i",{class:"fas fa-puzzle-piece text-blue-600 dark:text-blue-400"},null,-1)),a("span",kt,g(m(n)("modules.title")),1),v(m(ie),{value:`${k.value}/${C.value}`},null,8,["value"])])])]),content:w(()=>[y.value?(l(),F(m(Ee),{key:0,severity:"error",closable:!1,class:"mb-4"},{default:w(()=>[E(g(y.value),1)]),_:1})):L("",!0),u.value&&r.value.length===0?(l(),h("div",wt,[v(m(Oe))])):(l(),h("div",Pt,[(l(!0),h(D,null,R(r.value,f=>(l(),h("div",{key:f.id,class:"border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"},[a("div",_t,[a("div",Lt,[a("div",St,[a("i",{class:j([Y(f),"text-lg text-blue-600 dark:text-blue-400"])},null,2),a("h3",Ct,g(f.name),1),v(m(ie),{value:T(f).label,severity:T(f).severity},null,8,["value","severity"])]),a("p",Mt,g(f.description),1),f.features.length>0?(l(),h("div",Kt,[a("div",At,[(l(!0),h(D,null,R(f.features,S=>(l(),h("span",{key:S,class:"text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded"},g(S),1))),128))])])):L("",!0),f.dependencies.length>0?(l(),h("div",Ft,[b[5]||(b[5]=a("i",{class:"fas fa-link mr-1"},null,-1)),E(" Requires: "+g(f.dependencies.join(", ")),1)])):L("",!0),f.required_permissions.length>0&&!P(f)?(l(),h("div",Ot,[b[6]||(b[6]=a("i",{class:"fas fa-lock mr-1"},null,-1)),E(" Missing permissions: "+g(f.required_permissions.join(", ")),1)])):L("",!0)]),a("div",Et,[v(m(fe),{"model-value":f.enabled,"onUpdate:modelValue":S=>U(f.id),disabled:u.value||!P(f)},null,8,["model-value","onUpdate:modelValue","disabled"])])])]))),128))])),r.value.length===0&&!u.value?(l(),h("div",Dt,[...b[7]||(b[7]=[a("i",{class:"fas fa-puzzle-piece text-3xl text-gray-400 mb-4"},null,-1),a("p",{class:"text-gray-600 dark:text-gray-400"}," No modules available ",-1)])])):L("",!0)]),footer:w(()=>[v(m(M),{onClick:b[0]||(b[0]=f=>s.value=!1),label:c.$t("common.close")},null,8,["label"])]),_:1},8,["visible","header"])]))}},Tt=De(jt,[["__scopeId","data-v-2dcc6b3c"]]),zt={class:"min-h-screen bg-gray-50 dark:bg-gray-900"},Vt={class:"flex items-center space-x-4"},Bt={class:"font-semibold text-gray-900 dark:text-white"},$t={class:"flex items-center space-x-4"},Rt={class:"bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700"},Nt={class:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"},qt={class:"flex justify-between items-center"},Gt={class:"text-2xl font-bold text-gray-900 dark:text-white"},Ut={class:"text-gray-600 dark:text-gray-400 mt-1"},Ht={class:"flex space-x-3"},Wt={class:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"},Yt={key:0,class:"flex justify-center py-12"},Jt={class:"text-center"},Xt={class:"text-gray-600 dark:text-gray-400"},Zt={key:1,class:"space-y-8"},Qt={class:"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"},en={class:"flex items-center justify-between"},tn={class:"text-sm font-medium text-gray-600 dark:text-gray-400"},nn={class:"text-2xl font-bold text-green-600"},sn={class:"flex items-center justify-between"},an={class:"text-sm font-medium text-gray-600 dark:text-gray-400"},rn={class:"text-2xl font-bold text-red-600"},on={class:"flex items-center justify-between"},ln={class:"flex items-center justify-between"},un={class:"flex justify-between items-center"},dn={class:"h-64"},cn={class:"grid grid-cols-1 lg:grid-cols-2 gap-8"},mn={class:"flex justify-between items-center"},hn={key:0,class:"text-center py-8"},bn={key:1,class:"space-y-3"},fn={class:"font-medium text-gray-900 dark:text-white"},pn={class:"text-sm text-gray-600 dark:text-gray-400"},gn={class:"text-right"},vn={class:"font-medium text-gray-900 dark:text-white"},yn={key:0,class:"text-center py-8"},In={key:1,class:"space-y-3"},xn={class:"p-2 bg-blue-100 dark:bg-blue-900 rounded-full"},kn={class:"flex-1"},wn={class:"text-sm font-medium text-gray-900 dark:text-white"},Pn={class:"text-xs text-gray-600 dark:text-gray-400"},_n={class:"grid grid-cols-2 md:grid-cols-4 gap-4"},Tn={__name:"Index",setup(t){const{t:e}=ue(),n=de(),i=K(!1),o=K({financialSummary:{totalRevenue:0,totalExpenses:0,netIncome:0,cashFlow:0},recentInvoices:[],recentPayments:[],activityLog:[],companyStats:{totalCompanies:0,activeModules:0,totalUsers:0}}),s=K({responsive:!0,maintainAspectRatio:!1,plugins:{legend:{position:"top"}},scales:{y:{beginAtZero:!0}}}),u=K({labels:[],datasets:[{label:"Revenue",data:[],backgroundColor:"#10B981",borderColor:"#10B981",borderWidth:1},{label:"Expenses",data:[],backgroundColor:"#EF4444",borderColor:"#EF4444",borderWidth:1}]}),y=O(()=>n.props.auth?.user),r=O(()=>n.props.current_company),p=O(()=>r.value!==null),I=async()=>{i.value=!0;try{const P=await fetch("/api/v1/dashboard/data"),d=await P.json();P.ok&&(o.value=d,u.value.labels=d.monthlyData?.labels||[],u.value.datasets[0].data=d.monthlyData?.revenue||[],u.value.datasets[1].data=d.monthlyData?.expenses||[])}catch(P){console.error("Failed to load dashboard data:",P)}finally{i.value=!1}},k=(P,d="USD")=>new Intl.NumberFormat("en-US",{style:"currency",currency:d}).format(P),C=P=>({paid:"success",pending:"warning",overdue:"danger",draft:"info"})[P]||"info",G=P=>new Date(P).toLocaleDateString(),U=()=>{window.location.href="/invoicing/create"},Y=()=>{window.location.href="/invoicing"},T=P=>{window.location.href=`/modules/${P.toLowerCase()}`};return ce(()=>{I()}),(P,d)=>(l(),h("div",zt,[v(m(be),{class:"border-b border-gray-200 dark:border-gray-700"},{start:w(()=>[a("div",Vt,[d[3]||(d[3]=a("i",{class:"fas fa-chart-line text-blue-600 dark:text-blue-400"},null,-1)),a("span",Bt,g(m(e)("dashboard.title")),1)])]),end:w(()=>[a("div",$t,[p.value?(l(),F(Ae,{key:0})):L("",!0),v(Tt),v(m(M),{icon:"fas fa-user",text:"",rounded:""})])]),_:1}),a("div",Rt,[a("div",Nt,[a("div",qt,[a("div",null,[a("h1",Gt,g(m(e)("setup.welcome_back",{name:y.value?.name})),1),a("p",Ut,g(r.value?.name||"No company selected"),1)]),a("div",Ht,[v(m(M),{onClick:U,icon:"fas fa-plus",label:m(e)("invoicing.create_invoice")},null,8,["label"])])])])]),a("div",Wt,[i.value?(l(),h("div",Yt,[a("div",Jt,[d[4]||(d[4]=a("i",{class:"fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"},null,-1)),a("p",Xt,g(m(e)("common.loading")),1)])])):(l(),h("div",Zt,[a("div",Qt,[v(m(A),{class:"shadow-md"},{content:w(()=>[a("div",en,[a("div",null,[a("p",tn,g(m(e)("dashboard.financial_summary"))+" - Revenue ",1),a("p",nn,g(k(o.value.financialSummary.totalRevenue)),1)]),d[5]||(d[5]=a("div",{class:"p-3 bg-green-100 dark:bg-green-900 rounded-full"},[a("i",{class:"fas fa-arrow-trend-up text-green-600 dark:text-green-400"})],-1))])]),_:1}),v(m(A),{class:"shadow-md"},{content:w(()=>[a("div",sn,[a("div",null,[a("p",an,g(m(e)("dashboard.financial_summary"))+" - Expenses ",1),a("p",rn,g(k(o.value.financialSummary.totalExpenses)),1)]),d[6]||(d[6]=a("div",{class:"p-3 bg-red-100 dark:bg-red-900 rounded-full"},[a("i",{class:"fas fa-arrow-trend-down text-red-600 dark:text-red-400"})],-1))])]),_:1}),v(m(A),{class:"shadow-md"},{content:w(()=>[a("div",on,[a("div",null,[d[7]||(d[7]=a("p",{class:"text-sm font-medium text-gray-600 dark:text-gray-400"}," Net Income ",-1)),a("p",{class:j(["text-2xl font-bold",o.value.financialSummary.netIncome>=0?"text-green-600":"text-red-600"])},g(k(o.value.financialSummary.netIncome)),3)]),d[8]||(d[8]=a("div",{class:"p-3 bg-blue-100 dark:bg-blue-900 rounded-full"},[a("i",{class:"fas fa-chart-pie text-blue-600 dark:text-blue-400"})],-1))])]),_:1}),v(m(A),{class:"shadow-md"},{content:w(()=>[a("div",ln,[a("div",null,[d[9]||(d[9]=a("p",{class:"text-sm font-medium text-gray-600 dark:text-gray-400"}," Cash Flow ",-1)),a("p",{class:j(["text-2xl font-bold",o.value.financialSummary.cashFlow>=0?"text-green-600":"text-red-600"])},g(k(o.value.financialSummary.cashFlow)),3)]),d[10]||(d[10]=a("div",{class:"p-3 bg-purple-100 dark:bg-purple-900 rounded-full"},[a("i",{class:"fas fa-money-bill-wave text-purple-600 dark:text-purple-400"})],-1))])]),_:1})]),v(m(A),{class:"shadow-md"},{title:w(()=>[a("div",un,[a("span",null,g(m(e)("dashboard.financial_summary"))+" - Trend",1),d[11]||(d[11]=a("div",{class:"flex space-x-2"},[a("span",{class:"flex items-center text-sm"},[a("i",{class:"fas fa-circle text-green-500 mr-1",style:{"font-size":"8px"}}),E(" Revenue ")]),a("span",{class:"flex items-center text-sm"},[a("i",{class:"fas fa-circle text-red-500 mr-1",style:{"font-size":"8px"}}),E(" Expenses ")])],-1))])]),content:w(()=>[a("div",dn,[v(m(me),{type:"line",data:u.value,options:s.value},null,8,["data","options"])])]),_:1}),a("div",cn,[v(m(A),{class:"shadow-md"},{title:w(()=>[a("div",mn,[a("span",null,g(m(e)("invoicing.invoices")),1),v(m(M),{onClick:Y,icon:"fas fa-arrow-right",text:"",size:"small"})])]),content:w(()=>[o.value.recentInvoices.length===0?(l(),h("div",hn,[...d[12]||(d[12]=[a("i",{class:"fas fa-file-invoice text-3xl text-gray-400 mb-4"},null,-1),a("p",{class:"text-gray-600 dark:text-gray-400"},"No recent invoices",-1)])])):(l(),h("div",bn,[(l(!0),h(D,null,R(o.value.recentInvoices,c=>(l(),h("div",{key:c.id,class:"flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"},[a("div",null,[a("p",fn,g(c.invoice_number),1),a("p",pn,g(c.customer?.name),1)]),a("div",gn,[a("p",vn,g(k(c.amount)),1),v(m(Se),{value:c.status,severity:C(c.status)},null,8,["value","severity"])])]))),128))]))]),_:1}),v(m(A),{class:"shadow-md"},{title:w(()=>[E(g(m(e)("dashboard.recent_activity")),1)]),content:w(()=>[o.value.activityLog.length===0?(l(),h("div",yn,[...d[13]||(d[13]=[a("i",{class:"fas fa-history text-3xl text-gray-400 mb-4"},null,-1),a("p",{class:"text-gray-600 dark:text-gray-400"},"No recent activity",-1)])])):(l(),h("div",In,[(l(!0),h(D,null,R(o.value.activityLog,c=>(l(),h("div",{key:c.id,class:"flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"},[a("div",xn,[a("i",{class:j([c.icon,"text-blue-600 dark:text-blue-400 text-sm"])},null,2)]),a("div",kn,[a("p",wn,g(c.description),1),a("p",Pn,g(G(c.created_at)),1)])]))),128))]))]),_:1})]),v(m(A),{class:"shadow-md"},{title:w(()=>[E(g(m(e)("dashboard.quick_actions")),1)]),content:w(()=>[a("div",_n,[v(m(M),{onClick:U,icon:"fas fa-plus",label:"New Invoice",class:"p-4",severity:"success"}),v(m(M),{onClick:d[0]||(d[0]=c=>T("Invoicing")),icon:"fas fa-file-invoice",label:"Invoicing",class:"p-4"}),v(m(M),{onClick:d[1]||(d[1]=c=>T("Payments")),icon:"fas fa-credit-card",label:"Payments",class:"p-4"}),v(m(M),{onClick:d[2]||(d[2]=c=>T("Reports")),icon:"fas fa-chart-bar",label:"Reports",class:"p-4"})])]),_:1})]))])]))}};export{Tn as default};
