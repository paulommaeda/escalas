const fs=require('fs'),assert=require('assert'),fixture=require('./event-fixture.cjs'),taskFixture=require('./task-fixture.cjs'),convocationFixture=require('./convocation-fixture.cjs');
const {chromium}=require(process.env.CCS_PLAYWRIGHT_MODULE||'playwright');
(async()=>{
  const browser=await chromium.launch({headless:true,...(process.env.CCS_BROWSER_EXECUTABLE?{executablePath:process.env.CCS_BROWSER_EXECUTABLE}:{})});
  const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));let state=fixture(),actions=[];
  const html='<!doctype html><html class="ccs-app-document"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'+fs.readFileSync('assets/app.css','utf8')+fs.readFileSync('assets/account.css','utf8')+'</style></head><body class="ccs-standalone"><div id="ccs-app"><header class="ccs-header"><div class="ccs-top"><span class="ccs-wordmark">CCShalom</span><button class="ccs-bell" data-action="notifications"><span class="ccs-bell-count"></span></button></div></header><div id="ccs-message"></div><main id="ccs-content" class="ccs-main"></main><nav class="ccs-nav"></nav><dialog id="ccs-dialog"></dialog></div><div id="ccs-push-parking" hidden><div id="ccs-push-panel"><section data-ppush><h2>Notificações no dispositivo</h2><button id="provider-button" type="button">Configurar avisos</button></section></div></div><script>window.CCS_CONFIG={api:"http://fixture.test/api/",nonce:"test",login:"/login",lost:"/forgot",logout:"/logout"};document.getElementById("provider-button").addEventListener("click",()=>document.body.dataset.providerClicks=Number(document.body.dataset.providerClicks||0)+1);</script><script>'+fs.readFileSync('assets/task-views.js','utf8')+'</script><script>'+fs.readFileSync('assets/event-views.js','utf8')+'</script><script>'+fs.readFileSync('assets/app.js','utf8')+'</script></body></html>';
  await page.route('http://fixture.test/**',async route=>{
    const url=new URL(route.request().url());
    if(url.pathname==='/api/state')return route.fulfill({json:state});
    if(url.pathname==='/api/notifications')return route.fulfill({json:state.inbox});
    if(url.pathname==='/api/action'){
      const data=route.request().postDataJSON();actions.push(data);
      if(data.action==='event_planning')state.occurrences.find(o=>o.id===Number(data.id)).event_kind=data.event_kind;
      if(data.action==='task_move'){
        const t=state.tasks.find(t=>t.id===Number(data.id));if(Number(data.version)!==Number(t.version))return route.fulfill({status:409,json:{message:'Esta tarefa mudou. Atualize o painel antes de salvar novamente.'}});
        t.status=data.status;t.version++;
      }
      if(data.action==='task_save'){
        let t=state.tasks.find(t=>t.id===Number(data.id));if(!t){t={id:Math.max(200,...state.tasks.map(t=>Number(t.id)))+1,version:0,created_name:'Administrador',created_at:state.now};state.tasks.push(t);}
        Object.assign(t,{...data,id:t.id,occurrence_id:Number(data.occurrence_id),department_id:Number(data.department_id),team_id:Number(data.team_id),assigned_id:Number(data.assigned_id),version:Number(t.version)+1,due_at:data.due_at.replace('T',' ')+':00',assigned_name:state.users.find(u=>u.id===Number(data.assigned_id)).name,department_name:state.departments.find(d=>d.id===Number(data.department_id))?.name||'Organização geral',team_name:state.teams.find(team=>team.id===Number(data.team_id))?.name||'',event_status:'published',event_title:state.occurrences.find(o=>o.id===Number(data.occurrence_id)).title,updated_name:'Administrador',updated_at:state.now,can_edit:true,can_delete:true,can_move:true});
      }
      if(data.action==='task_delete')state.tasks=state.tasks.filter(t=>t.id!==Number(data.id));
      if(data.action==='function')state.functions.push({id:Math.max(...state.functions.map(f=>Number(f.id)))+1,name:data.name});
      if(data.action==='team'&&data.mode==='assigned'){
        const team=state.teams.find(t=>t.id===Number(data.id));if(team){team.mode='assigned';team.members=data.members;team.functions=data.functions.map(f=>({...f,name:state.functions.find(fn=>fn.id===Number(f.id)).name,members:state.users.filter(u=>(u.function_ids||[]).includes(Number(f.id))).map(u=>u.id)}));}
      }
      if(data.action==='delegate'||data.action==='clear_convocation'){
        const slot=state.slots.find(s=>s.id===Number(data.id)),ev=state.events.find(e=>e.id===Number(slot.event_id));
        if(ev.mode==='assigned'&&data.fingerprint!==slot.convocation_fingerprint)return route.fulfill({status:409,json:{message:'Esta convocação mudou. Atualize o evento antes de alterar a pessoa designada.'}});
        const to=data.action==='delegate'?Number(data.to_id):0;slot.assigned_id=to;slot.original_id=to;slot.status=to?'confirmed':'available';slot.convocation_fingerprint='updated-'+slot.id+'-'+actions.length;
      }
      if(data.action==='respond')state.slots.find(s=>s.id===Number(data.id)).status=data.status;
      if(data.action==='notice_read'){state.inbox.items.forEach(n=>n.read_at='2026-10-22 11:00:00');state.inbox.unread=0;}
      return route.fulfill({json:{ok:true,message:'Salvo'}});
    }
    return route.fulfill({contentType:'text/html',body:html});
  });
  await page.goto('http://fixture.test/');await page.waitForSelector('[data-calendar-event]');
  assert.equal(await page.locator('[data-calendar-event]').count(),1);
  assert.equal(await page.locator('[data-view="manage"]').count(),0);
  await page.locator('.ccs-event-overview summary').click();assert((await page.locator('.ccs-event-overview').innerText()).includes('Bruno Transmissão'));
  await page.locator('[data-calendar-event] [data-action="occurrence"]').click();
  assert.equal(await page.locator('#ccs-dialog [data-action="confirm"][data-id="60"]').count(),1);
  assert.equal(await page.locator('#ccs-dialog [data-action="confirm"][data-id="62"]').count(),0);
  assert.equal(await page.locator('#ccs-dialog [data-action="edit_occurrence"]').count(),0);
  assert.equal(await page.locator('#ccs-dialog [data-action="claim"][data-id="61"]').count(),1);
  await page.locator('#ccs-dialog [data-action="confirm"]').click();await page.waitForFunction(()=>{const d=document.querySelector('#ccs-dialog[open]');return d?.querySelector('.ccs-slot .ccs-badge.confirmed')&&!d.querySelector('[data-action="confirm"][data-id="60"]');});
  assert(actions.some(a=>a.action==='respond'&&a.id===60));await page.locator('#ccs-dialog .ccs-dialog-head [data-action="close"]').click();
  await page.locator('[data-view="profile"]').click();await page.locator('#provider-button').click();
  await page.locator('[data-view="home"]').click();assert.equal(await page.locator('#ccs-push-parking #ccs-push-panel').count(),1);
  await page.locator('[data-view="profile"]').click();await page.locator('#provider-button').click();
  assert.equal(await page.evaluate(()=>document.body.dataset.providerClicks),'2');
  await page.locator('[data-action="notifications"]').click();await page.waitForSelector('[data-action="notice_open"]');
  await page.locator('[data-action="notice_open"]').click();await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');
  assert(actions.some(a=>a.action==='notice_read'));await page.locator('#ccs-dialog .ccs-dialog-head [data-action="close"]').click();
  for(const width of [360,390,768]){await page.setViewportSize({width,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no horizontal scrolling at '+width);}
  state=fixture('leader');await page.goto('http://fixture.test/?ccs_event=50');await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');
  assert.equal(await page.locator('#ccs-dialog [data-action="claim_event_team"]').count(),1);
  assert.equal(await page.locator('#ccs-dialog [data-action="release_event_team"][data-id="41"]').count(),0);
  await page.locator('[data-action="claim_event_team"]').click();assert.equal(await page.locator('form[data-form="claim_event_team"] [name="team_id"]').inputValue(),'12');
  state=fixture('unqualified');await page.goto('http://fixture.test/?ccs_event=50');await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');
  assert.equal(await page.locator('#ccs-dialog [data-action="claim"]').count(),0);
  state=fixture('admin');await page.goto('http://fixture.test/?ccs_event=50');await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');
  assert.equal(await page.locator('#ccs-dialog [data-action="claim_event_team"]').count(),0);
  assert.equal(await page.locator('#ccs-dialog [data-action="claim_event_department"]').count(),0);
  assert.equal(await page.locator('#ccs-dialog [data-action="assign_occurrence"]').count(),3);
  await page.locator('#ccs-dialog .ccs-dialog-head [data-action="close"]').click();await page.locator('[data-view="manage"]').click();
  assert.equal(await page.locator('h1').innerText(),'Gestão de evento');
  assert.equal(await page.locator('[data-dashboard-metric]').count(),0);assert.equal(await page.locator('[data-dashboard-event]').count(),1);
  await page.locator('[name="dashboard-event"]').selectOption('50');
  for(const [key,value] of Object.entries({departments:3,teams:2,missing:1,vacancies:1,pending:1,confirmed:1}))assert.equal(await page.locator('[data-dashboard-metric="'+key+'"]').innerText(),String(value));
  assert((await page.locator('[data-selected-event="50"]').innerText()).includes('Transmissão'));
  await page.locator('.ccs-team-assignments summary').first().click();assert((await page.locator('.ccs-team-assignments').first().innerText()).includes('Ana <Batera>'));
  await page.locator('[data-dashboard-filters] summary').click();await page.locator('[name="dashboard-department"]').selectOption('21');
  assert.equal(await page.locator('[data-selected-event]').count(),0);assert.equal(await page.locator('[data-dashboard-event]').count(),1);
  await page.locator('[name="dashboard-department"]').selectOption('');await page.locator('[name="dashboard-month"]').fill('2030-10');
  assert.equal(await page.locator('[data-dashboard-event]').count(),0);
  await page.locator('[data-action="dashboard_reset"]').click();assert.equal(await page.locator('[data-dashboard-event]').count(),1);
  await page.locator('#ccs-content [data-action="event_dashboard"]').click();
  for(const width of [360,390,768]){await page.setViewportSize({width,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'event dashboard fits mobile '+width);}
  await page.locator('[data-action="publish_event"]').click();
  assert.equal(await page.locator('[data-event-department]').count(),3);await page.locator('[data-event-enabled]').first().check();
  await page.locator('#ccs-dialog .ccs-dialog-head [data-action="close"]').click();
  await page.locator('.ccs-tabs [data-action="manage_schedules"]').click();
  await page.locator('.ccs-management-actions [data-action="team"]').click();
  const memberSearch=page.locator('[data-member-search]');
  await memberSearch.fill('ana');assert.equal(await page.locator('[data-member-add]').count(),1);
  await page.locator('[data-member-add][value="1"]').click();assert.equal(await page.locator('[data-member-selected] [name="members"][value="1"]').count(),1);
  await memberSearch.fill('BRUNO');assert.equal(await page.locator('[data-member-add]').count(),1);
  assert((await page.locator('[data-member-selected]').innerText()).includes('Ana <Batera>'));
  await page.locator('[data-member-add][value="2"]').click();
  await memberSearch.fill('inexistente');assert.equal(await page.locator('[data-member-add]').count(),0);
  assert.equal(await page.locator('[data-member-selected] [name="members"]').count(),2);
  await memberSearch.press('Enter');assert.equal(actions.filter(a=>a.action==='team').length,0);
  await page.locator('[data-action="team_member_remove"][data-id="1"]').click();
  assert.equal(await page.locator('[data-member-selected] [name="members"]').count(),1);
  await memberSearch.fill('ana');await page.locator('[data-member-add][value="1"]').click();
  await memberSearch.fill('lider');assert.equal(await page.locator('[data-member-add]').count(),2);
  for(const width of [360,390]){await page.setViewportSize({width,height:844});assert(await page.evaluate(()=>{const d=document.querySelector('#ccs-dialog');return d.scrollWidth<=d.clientWidth+1;}),'member picker fits mobile '+width);}
  await page.locator('form[data-form="team"] [name="name"]').fill('Equipe busca');
  await page.locator('form[data-form="team"] [name="mode"]').selectOption('roster');
  await page.locator('form[data-form="team"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});
  assert.deepEqual(actions.filter(a=>a.action==='team').at(-1).members.slice().sort((a,b)=>a-b),[1,2]);
  await page.waitForSelector('.ccs-management-actions [data-action="team"]:not([disabled])');
  await page.locator('[data-view="teams"]').click();
  const summary=page.locator('[data-action="team_details"][data-id="10"]');
  assert((await summary.innerText()).includes('Música 1'));assert((await summary.innerText()).includes('Líder Música'));assert((await summary.innerText()).includes('Artes'));assert((await summary.innerText()).includes('1 integrante'));
  assert(!(await summary.innerText()).includes('Ana <Batera>'));assert(!(await summary.innerText()).includes('Bateria'));
  assert.equal(await page.locator('#ccs-content .ccs-people').count(),0);
  await summary.click();assert((await page.locator('#ccs-dialog .ccs-people').innerText()).includes('Ana <Batera>'));
  await page.locator('#ccs-dialog [data-action="team"][data-id="10"]').click();
  assert.equal(await page.locator('[data-member-selected] [name="members"][value="1"]').count(),1);
  assert.equal(await page.locator('[data-member-add][value="1"]').count(),0);
  await page.locator('[data-action="team_member_remove"][data-id="1"]').click();
  await page.locator('form[data-form="team"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});
  assert.deepEqual(actions.filter(a=>a.action==='team').at(-1).members,[]);

  state=fixture('secretary');await page.goto('http://fixture.test/');await page.waitForSelector('[data-view="manage"]');await page.locator('[data-view="manage"]').click();
  await page.locator('[name="dashboard-event"]').selectOption('50');assert.equal(await page.locator('[data-dashboard-metric="teams"]').innerText(),'2');assert.equal(await page.locator('[data-action="publish_event"]').count(),0);
  await page.locator('[data-selected-event] [data-action="occurrence"]').click();assert.equal(await page.locator('#ccs-dialog [data-action="assign_occurrence"]').count(),0);
  state=fixture('department');await page.goto('http://fixture.test/');await page.waitForSelector('[data-view="manage"]');await page.locator('[data-view="manage"]').click();
  await page.locator('[name="dashboard-event"]').selectOption('50');assert.equal(await page.locator('[data-dashboard-metric="departments"]').innerText(),'3');assert.equal(await page.locator('[data-dashboard-metric="confirmed"]').innerText(),'1');

  // A special event owns its board; another event and a celebration can be selected separately.
  state=taskFixture('admin');await page.goto('http://fixture.test/');await page.waitForSelector('[data-view="manage"]');await page.locator('[data-view="manage"]').click();
  assert.equal(await page.locator('[name="dashboard-event"] optgroup[label="Celebrações"]').count(),1);
  await page.locator('[name="dashboard-event"]').selectOption('50');
  assert.equal(await page.locator('[data-task-column]').count(),3);assert.equal(await page.locator('[data-task-card="204"]').count(),0);
  for(const [key,value] of Object.entries({total:3,doing:1,done:1,overdue:1}))assert.equal(await page.locator('[data-task-metric="'+key+'"]').innerText(),String(value));
  await page.locator('[name="task-filter-assignee"]').selectOption('1');assert.equal(await page.locator('[data-task-card]').count(),1);
  await page.locator('[name="task-filter-assignee"]').selectOption('');
  await page.locator('[data-action="task_new"]').click();
  assert.equal(await page.locator('form[data-form="task_save"] [name="assigned_id"] option[value="7"]').count(),0);
  await page.locator('form[data-form="task_save"] [name="title"]').fill('Conferir som');
  await page.locator('form[data-form="task_save"] [name="department_id"]').selectOption('20');
  assert.equal(await page.locator('form[data-form="task_save"] [name="team_id"] option[value="11"]').count(),0);
  await page.locator('form[data-form="task_save"] [name="team_id"]').selectOption('10');
  await page.locator('form[data-form="task_save"] [name="assigned_id"]').selectOption('1');
  await page.locator('form[data-form="task_save"] [name="due_at"]').fill('2030-09-26T12:00');
  await page.locator('form[data-form="task_save"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});
  await page.waitForSelector('[data-task-card="205"] [data-action="task_details"]:not([disabled])');
  assert.equal(await page.locator('[data-task-metric="total"]').innerText(),'4');
  await page.locator('[data-task-card="205"] [data-action="task_details"]').click();await page.locator('#ccs-dialog [data-action="task_edit"]').click();
  await page.locator('form[data-form="task_save"] [name="title"]').fill('Conferir som e microfones');await page.locator('form[data-form="task_save"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});
  await page.waitForSelector('[data-task-card="205"] [data-action="task_move"]:not([disabled])');
  await page.locator('[data-task-card="205"] [data-action="task_move"][data-id="205:doing"]').click();
  await page.waitForSelector('[data-task-column="doing"] [data-task-card="205"] [data-action="task_details"]:not([disabled])');
  await page.setViewportSize({width:1100,height:900});await page.locator('[data-task-card="205"]').dragTo(page.locator('[data-task-column="done"]'));
  await page.waitForSelector('[data-task-column="done"] [data-task-card="205"] [data-action="task_details"]:not([disabled])');
  for(const width of [360,390,768,1100]){await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Kanban fits '+width);}
  // Optimistic conflict keeps the current board; refreshing recovers the new version.
  state.tasks.find(t=>t.id===205).version++;
  await page.locator('[data-task-card="205"] [data-action="task_move"][data-id="205:todo"]').click();await page.waitForFunction(()=>document.querySelector('#ccs-message').textContent.includes('Esta tarefa mudou'));
  await page.locator('[data-selected-event] [data-action="refresh"]').click();await page.waitForSelector('[data-task-card="205"] [data-action="task_details"]:not([disabled])');
  await page.locator('[data-task-card="205"] [data-action="task_details"]').click();await page.locator('#ccs-dialog [data-action="task_delete"]').click();
  await page.locator('form[data-form="task_delete"] [name="confirm"]').check();await page.locator('form[data-form="task_delete"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});
  await page.waitForSelector('[data-action="task_new"]:not([disabled])');assert.equal(await page.locator('[data-task-card="205"]').count(),0);
  await page.locator('[name="dashboard-event"]').selectOption('55');assert.equal(await page.locator('[data-task-column]').count(),0);assert.equal(await page.locator('[data-selected-event="55"]').count(),1);
  await page.locator('[data-selected-event] [data-action="event_planning"]').first().click();await page.locator('form[data-form="event_planning"] [name="event_kind"]').selectOption('special');await page.locator('form[data-form="event_planning"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});
  await page.waitForSelector('[data-selected-event="55"] [data-task-column]');assert.equal(await page.locator('[data-task-card]').count(),0);

  state=taskFixture('secretary');await page.goto('http://fixture.test/');await page.waitForSelector('[data-view="manage"]');await page.locator('[data-view="manage"]').click();await page.locator('[name="dashboard-event"]').selectOption('50');
  assert.equal(await page.locator('[data-task-column]').count(),3);assert.equal(await page.locator('[data-action="task_new"]').count(),0);assert.equal(await page.locator('[data-task-card][draggable="true"]').count(),0);
  await page.locator('[data-task-card="201"] [data-action="task_details"]').click();assert.equal(await page.locator('#ccs-dialog [data-action="task_move"]').count(),0);assert.equal(await page.locator('#ccs-dialog [data-action="task_edit"]').count(),0);
  state=taskFixture('department');await page.goto('http://fixture.test/');await page.waitForSelector('[data-view="manage"]');await page.locator('[data-view="manage"]').click();await page.locator('[name="dashboard-event"]').selectOption('50');
  assert.equal(await page.locator('[data-task-card="202"] [data-action="task_move"]').count(),0);assert.equal(await page.locator('[data-task-card="201"] [data-action="task_move"]').count(),2);
  await page.locator('[data-action="task_new"]').click();assert.deepEqual(await page.locator('form[data-form="task_save"] [name="department_id"] option').evaluateAll(options=>options.map(o=>o.value)),['20']);
  state=taskFixture('volunteer');await page.goto('http://fixture.test/?ccs_event=50&ccs_task=201');await page.waitForSelector('#ccs-dialog[open]');
  assert((await page.locator('#ccs-dialog').innerText()).includes('Montar palco <seguro>'));assert.equal(await page.locator('[data-view="manage"]').count(),0);assert.equal(await page.locator('#ccs-dialog [data-action="task_edit"]').count(),0);
  await page.locator('#ccs-dialog .ccs-dialog-head [data-action="close"]').click();await page.locator('#ccs-content [data-action="mine_tasks"]').click();
  assert.equal(await page.locator('[data-task-card]').count(),2);assert.equal(await page.locator('[data-task-card="202"]').count(),0);
  await page.locator('[data-task-card="201"] [data-action="task_move"][data-id="201:done"]').click();await page.waitForFunction(()=>Array.from(document.querySelectorAll('[data-task-card="201"] p')).some(p=>p.textContent.includes('Concluído')));
  assert(actions.some(a=>a.action==='task_move'&&Number(a.id)===201&&a.status==='done'));
  // Directed functions: arbitrary catalogue functions, qualified appointments and no self-claim.
  state=convocationFixture('admin');await page.goto('http://fixture.test/');await page.waitForSelector('[data-view="manage"]');await page.locator('[data-view="manage"]').click();
  await page.locator('.ccs-tabs [data-action="manage_schedules"]').click();await page.locator('.ccs-management-actions [data-action="catalog"]').click();await page.locator('#ccs-dialog [data-action="function"][data-id="0"]').click();
  await page.locator('form[data-form="function"] [name="name"]').fill('Leitura bíblica pastoral');await page.locator('form[data-form="function"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});await page.waitForSelector('.ccs-management-actions [data-action="catalog"]:not([disabled])');
  await page.locator('[data-view="teams"]').click();await page.locator('[data-action="team_details"][data-id="10"]').click();await page.locator('#ccs-dialog [data-action="team"][data-id="10"]').click();
  assert.equal(await page.locator('form[data-form="team"] [name="mode"]').inputValue(),'assigned');assert((await page.locator('form[data-form="team"] [name="mode"] option[value="assigned"]').innerText()).includes('Convocação por função'));
  await page.locator('[data-function-row="32"] [name="team_function"]').check();await page.locator('form[data-form="team"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog[open]',{state:'detached'});await page.waitForSelector('[data-action="team_details"]:not([disabled])');
  assert(actions.filter(a=>a.action==='team').at(-1).functions.some(f=>Number(f.id)===32));
  await page.locator('[data-view="home"]').click();await page.locator('[data-calendar-event="50"] [data-action="occurrence"]').click();
  assert.equal(await page.locator('#ccs-dialog [data-action="claim"]').count(),0);assert((await page.locator('#ccs-dialog').innerText()).includes('Aguardando convocação'));
  for(const width of [360,390,768]){await page.setViewportSize({width,height:844});assert(await page.evaluate(()=>{const d=document.querySelector('#ccs-dialog');return d.scrollWidth<=d.clientWidth+1;}),'directed event fits mobile '+width);}
  await page.locator('#ccs-dialog [data-action="delegate"][data-id="60"]').click();
  assert.deepEqual(await page.locator('form[data-form="delegate"] [name="to_id"] option').evaluateAll(options=>options.map(o=>o.value)),['','1','2']);
  assert.equal(await page.locator('form[data-form="delegate"] [name="fingerprint"]').inputValue(),'convocation-60-0');
  await page.locator('form[data-form="delegate"] [name="to_id"]').selectOption('1');await page.locator('form[data-form="delegate"] [type="submit"]').click();
  await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open] [data-action="clear_convocation"][data-id="60"]:not([disabled])');
  assert((await page.locator('#ccs-dialog .ccs-team-participation').first().innerText()).includes('Pastor João'));
  await page.locator('#ccs-dialog [data-action="delegate"][data-id="60"]').click();await page.locator('form[data-form="delegate"] [name="to_id"]').selectOption('2');
  state.slots.find(s=>s.id===60).convocation_fingerprint='changed-on-server';
  await page.locator('form[data-form="delegate"] [type="submit"]').click();await page.waitForFunction(()=>document.querySelector('#ccs-dialog .ccs-form-error')?.textContent.includes('Esta convocação mudou'));
  assert.equal(state.slots.find(s=>s.id===60).assigned_id,1);
  await page.locator('#ccs-dialog .ccs-dialog-head [data-action="close"]').click();await page.locator('#ccs-content [data-action="refresh"]').click();await page.waitForFunction(()=>document.querySelector('#ccs-message').textContent==='Escalas atualizadas.');
  await page.waitForSelector('#ccs-content [data-action="occurrence"]:not([disabled])');await page.locator('[data-calendar-event="50"] [data-action="occurrence"]').click();await page.locator('#ccs-dialog [data-action="delegate"][data-id="60"]').click();
  await page.locator('form[data-form="delegate"] [name="to_id"]').selectOption('2');await page.locator('form[data-form="delegate"] [type="submit"]').click();await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open] [data-action="clear_convocation"][data-id="60"]:not([disabled])');
  assert.equal(state.slots.find(s=>s.id===60).assigned_id,2);
  await page.locator('#ccs-dialog [data-action="clear_convocation"][data-id="60"]').click();await page.locator('form[data-form="clear_convocation"] [name="confirm"]').check();await page.locator('form[data-form="clear_convocation"] [type="submit"]').click();
  await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open] [data-action="delegate"][data-id="60"]:not([disabled])');assert.equal(state.slots.find(s=>s.id===60).assigned_id,0);assert.equal(await page.locator('#ccs-dialog [data-action="claim"]').count(),0);
  state=convocationFixture('leader');await page.goto('http://fixture.test/?ccs_event=50');await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');assert.equal(await page.locator('[data-view="manage"]').count(),0);assert.equal(await page.locator('#ccs-dialog [data-action="delegate"][data-id="60"]').count(),1);
  state=convocationFixture('volunteer');Object.assign(state.slots[0],{assigned_id:1,original_id:1,status:'confirmed'});await page.goto('http://fixture.test/?ccs_event=50');await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');
  assert.equal(await page.locator('#ccs-dialog [data-action="delegate"]').count(),0);assert.equal(await page.locator('#ccs-dialog [data-action="claim"]').count(),0);assert.equal(await page.locator('#ccs-dialog [data-action="release_slot"]').count(),0);
  await page.locator('#ccs-dialog [data-action="decline"][data-id="60"]').click();await page.waitForSelector('form[data-form="invite"]');assert.equal(await page.locator('form[data-form="invite"] [name="to_id"] option[value="2"]').count(),1);assert.equal(await page.locator('form[data-form="invite"] [name="to_id"] option[value="4"]').count(),0);
  state=convocationFixture('secretary');await page.goto('http://fixture.test/?ccs_event=50');await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');assert.equal(await page.locator('#ccs-dialog [data-action="delegate"]').count(),0);assert.equal(await page.locator('#ccs-dialog [data-action="clear_convocation"]').count(),0);
  assert.equal(errors.length,0,errors.join('\n'));await browser.close();
  console.log('PASS navegador: evento único, edição própria, perfil push persistente, sino, link direto, busca/seleção de integrantes, equipes resumidas, gestão por data, tarefas e Kanban (criar/editar/mover/arrastar/concluir/excluir/conflito), perfis, convocação dirigida com funções livres e mobile');
})().catch(e=>{console.error(e);process.exit(1)});
