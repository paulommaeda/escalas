const fs=require('fs'),vm=require('vm'),assert=require('assert'),fixture=require('./convocation-fixture.cjs');
const sandbox={window:{}};vm.runInNewContext(fs.readFileSync('assets/event-views.js','utf8'),sandbox);
let state=fixture(),modal='',page='';
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ctx={getState:()=>state,e:esc,num:Number,same:(a,b)=>Number(a)===Number(b),button:(label,action,id)=>'<button data-action="'+action+'" data-id="'+id+'">'+esc(label)+'</button>',badge:(s,label)=>'<span>'+esc(label||s)+'</span>',modeLabel:m=>m,day:()=>({d:27,m:'SET'}),stamp:v=>v,user:id=>state.users.find(u=>u.id===Number(id))?.name||'Removido',content:{set innerHTML(v){page=v}},root:{querySelector:()=>null},dialog:{classList:{add:()=>{}}},heading:()=>'',empty:()=>'',open:(_,html)=>modal=html,setOpened:()=>{},filter:{period:'future'}};
const views=sandbox.window.CCS_EVENT_VIEWS(ctx);
views.details(50);assert(modal.includes('Aguardando convocação'));assert(!modal.includes('data-action="claim"'));assert(!modal.includes('data-action="delegate"'));assert.equal(views.openEvents().length,0);
// UI also respects the mode if a stale client-side eligibility flag is present.
state.events[0].can_claim=true;state.slots[1].can_claim=true;views.details(50);assert(!modal.includes('data-action="claim"'));assert.equal(views.openEvents().length,0);
state=fixture('leader');views.details(50);assert(modal.includes('Convocar integrante'));assert(modal.includes('data-action="delegate" data-id="60"'));assert(!modal.includes('data-action="claim"'));
state.slots[0].assigned_id=1;state.slots[0].original_id=1;state.slots[0].status='confirmed';views.details(50);assert(modal.includes('Alterar convocação'));assert(modal.includes('data-action="clear_convocation" data-id="60"'));
state=fixture();state.slots[0].assigned_id=1;state.slots[0].original_id=1;state.slots[0].status='confirmed';views.details(50);assert(modal.includes('data-action="decline" data-id="60"'));assert(!modal.includes('data-action="release_slot"'));assert(!modal.includes('data-action="delegate"'));assert(!modal.includes('data-action="clear_convocation"'));
state.slots[0].status='declined';views.details(50);assert(modal.includes('data-action="invite" data-id="60"'));
state=fixture('secretary');views.details(50);assert(!modal.includes('data-action="claim"'));assert(!modal.includes('data-action="delegate"'));assert(!modal.includes('data-action="clear_convocation"'));assert(!modal.includes('data-action="decline"'));
state=fixture('leader');state.events[0].status='cancelled';views.details(50);assert(!modal.includes('data-action="delegate"'));assert(!modal.includes('data-action="clear_convocation"'));
console.log('PASS convocações por função: liderança, ausência de voluntariado/liberação pessoal, indisponibilidade, troca, consulta e cancelamento');
