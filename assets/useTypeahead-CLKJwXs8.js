import{N as i,an as p,af as x}from"./app-Y-ywjGbl.js";/**
 * @license lucide-vue-next v0.468.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const w=i("ChevronDownIcon",[["path",{d:"m6 9 6 6 6-6",key:"qrunsl"}]]);function y(n){const e=p("",1e3);return{search:e,handleTypeaheadSearch:(c,d)=>{e.value=e.value+c;{const r=x(),s=d.map(t=>({...t,textValue:t.value?.textValue??t.ref.textContent?.trim()??""})),u=s.find(t=>t.ref===r),a=s.map(t=>t.textValue),f=v(a,e.value,u?.textValue),h=s.find(t=>t.textValue===f);return h&&h.ref.focus(),h?.ref}},resetTypeahead:()=>{e.value=""}}}function m(n,e){return n.map((o,l)=>n[(e+l)%n.length])}function v(n,e,o){const c=e.length>1&&Array.from(e).every(a=>a===e[0])?e[0]:e,d=o?n.indexOf(o):-1;let r=m(n,Math.max(d,0));c.length===1&&(r=r.filter(a=>a!==o));const u=r.find(a=>a.toLowerCase().startsWith(c.toLowerCase()));return u!==o?u:void 0}export{w as C,y as u};
