const fs=require('fs'),assert=require('assert'),fixture=require('./event-fixture.cjs');
const {chromium}=require(process.env.CCS_PLAYWRIGHT_MODULE||'playwright');
(async()=>{
  const browser=await chromium.launch({headless:true,...(process.env.CCS_BROWSER_EXECUTABLE?{executablePath:process.env.CCS_BROWSER_EXECUTABLE}:{})});
  const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));let state=fixture(),actions=[];
  const html='<!doctype html><html class="ccs-app-document"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>'+fs.readFileSync('assets/app.css','utf8')+fs.readFileSync('assets/account.css','utf8')+'</style></head><body class="ccs-standalone"><div id="ccs-app"><header class="ccs-header"><div class="ccs-top"><span class="ccs-wordmark">CCShalom</span><button class="ccs-bell" data-action="notifications"><span class="ccs-bell-count"></span></button></div></header><div id="ccs-message"></div><main id="ccs-content" class="ccs-main"></main><nav class="ccs-nav"></nav><dialog id="ccs-dialog"></dialog></div><div id="ccs-push-parking" hidden><div id="ccs-push-panel"><section data-ppush><h2>Notificações no dispositivo</h2><button id="provider-button" type="button">Configurar avisos</button></section></div></div><script>window.CCS_CONFIG={api:"http://fixture.test/api/",nonce:"test",login:"/login",lost:"/forgot",logout:"/logout"};document.getElementById("provider-button").addEventListener("click",()=>document.body.dataset.providerClicks=Number(document.body.dataset.providerClicks||0)+1);</script><script>'+fs.readFileSync('assets/event-views.js','utf8')+'</script><script>'+fs.readFileSync('assets/app.js','utf8')+'</script></body></html>';
  await page.route('http://fixture.test/**',async route=>{
    const url=new URL(route.request().url());
    if(url.pathname==='/api/state')return route.fulfill({json:state});
    if(url.pathname==='/api/notifications')return route.fulfill({json:state.inbox});
    if(url.pathname==='/api/action'){
      const data=route.request().postDataJSON();actions.push(data);
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
  assert(actions.some(a=>a.action==='respond'&&a.id===60));await page.locator('#ccs-dialog [data-action="close"]').click();
  await page.locator('[data-view="profile"]').click();await page.locator('#provider-button').click();
  await page.locator('[data-view="home"]').click();assert.equal(await page.locator('#ccs-push-parking #ccs-push-panel').count(),1);
  await page.locator('[data-view="profile"]').click();await page.locator('#provider-button').click();
  assert.equal(await page.evaluate(()=>document.body.dataset.providerClicks),'2');
  await page.locator('[data-action="notifications"]').click();await page.waitForSelector('[data-action="notice_open"]');
  await page.locator('[data-action="notice_open"]').click();await page.waitForSelector('#ccs-dialog.ccs-event-dialog[open]');
  assert(actions.some(a=>a.action==='notice_read'));await page.locator('#ccs-dialog [data-action="close"]').click();
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
  await page.locator('#ccs-dialog [data-action="close"]').click();await page.locator('[data-view="manage"]').click();await page.locator('[data-action="publish_event"]').click();
  assert.equal(await page.locator('[data-event-department]').count(),3);await page.locator('[data-event-enabled]').first().check();
  await page.locator('#ccs-dialog [data-action="close"]').click();
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
  await page.locator('form[data-form="team"] [type="submit"]').click();await page.waitForSelector('form[data-form="team"]',{state:'detached'});
  assert.deepEqual(actions.filter(a=>a.action==='team').at(-1).members.slice().sort((a,b)=>a-b),[1,2]);
  await page.waitForSelector('.ccs-management-actions [data-action="team"]:not([disabled])');
  await page.locator('[data-view="teams"]').click();await page.locator('[data-action="team"][data-id="10"]').click();
  assert.equal(await page.locator('[data-member-selected] [name="members"][value="1"]').count(),1);
  assert.equal(await page.locator('[data-member-add][value="1"]').count(),0);
  await page.locator('[data-action="team_member_remove"][data-id="1"]').click();
  await page.locator('form[data-form="team"] [type="submit"]').click();await page.waitForSelector('form[data-form="team"]',{state:'detached'});
  assert.deepEqual(actions.filter(a=>a.action==='team').at(-1).members,[]);

  assert.equal(errors.length,0,errors.join('\n'));await browser.close();
  console.log('PASS navegador: evento único, edição própria, perfil push persistente, sino, link direto, busca/seleção de integrantes e mobile 360/390/768');
})().catch(e=>{console.error(e);process.exit(1)});
