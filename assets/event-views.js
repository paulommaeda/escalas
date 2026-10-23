/* Event-centered views. Mutations remain authorized by the WordPress API. */
window.CCS_EVENT_VIEWS = function (ctx) {
  'use strict';
  const S=ctx.getState, e=ctx.e, num=ctx.num, same=ctx.same, button=ctx.button, badge=ctx.badge;
  const children=o=>(o.requirements||[]).flatMap(r=>r.events||[]);
  const slots=o=>children(o).flatMap(ev=>S().slots.filter(s=>same(s.event_id,ev.id)));
  const mine=o=>slots(o).filter(s=>same(s.assigned_id,S().me.id)||same(s.original_id,S().me.id));
  const all=()=>S().occurrences||[];
  const current=o=>o.status==='published'&&o.ends>=S().now;
  const sorted=list=>list.slice().sort((a,b)=>ctx.filter.period==='past'?b.starts.localeCompare(a.starts):a.starts.localeCompare(b.starts));
  function openEvents() { return all().filter(o=>current(o)&&o.starts>S().now&&children(o).some(ev=>ev.can_claim&&S().slots.some(s=>same(s.event_id,ev.id)&&s.status==='available'&&s.can_claim))); }
  function ownEvents() { return all().filter(o=>current(o)&&mine(o).length); }
  function program(o) {
    return (o.requirements||[]).map(r=>'<section class="ccs-program-department"><h4>'+e(r.department_name)+'</h4>'+
      (r.events||[]).map(ev=>'<p class="ccs-program-team">'+e(ev.team_name)+' · '+e(ctx.modeLabel(ev.mode))+'</p><ul>'+
        S().slots.filter(s=>same(s.event_id,ev.id)).map(s=>'<li><span>'+e(s.function_name||(ev.mode==='roster'?'Integrante da equipe':'Participação'))+'</span><strong>'+e(s.status==='available'?'Vaga disponível':ctx.user(s.assigned_id))+'</strong>'+badge(s.status)+'</li>').join('')+'</ul>').join('')+
      (r.missing_team?'<p class="ccs-hint">'+e(r.remaining_teams)+' participação(ões) de equipe aguardando liderança.</p>':'')+'</section>').join('');
  }
  function card(o) {
    const ds=ctx.day(o.starts),my=mine(o),allSlots=slots(o),missing=(o.requirements||[]).filter(r=>r.required!==false&&r.missing_team).length;
    const pending=my.filter(s=>same(s.assigned_id,S().me.id)&&s.status==='pending').length;
    const ready=(o.requirements||[]).some(r=>(r.claimable_teams||[]).length||r.can_assume_department);
    return '<article class="ccs-event ccs-unified-event" data-calendar-event="'+num(o.id)+'"><div class="ccs-date"><strong>'+ds.d+'</strong><span>'+ds.m+'</span></div><div class="ccs-event-body"><span class="ccs-eyebrow">EVENTO · '+(o.requirements||[]).length+' DEPARTAMENTOS</span><h3>'+e(o.title)+'</h3><p>'+e(o.starts.slice(11,16))+'–'+e(o.ends.slice(11,16))+' · Chegada '+e(o.arrival.slice(11,16))+'</p>'+
      (o.location?'<p>'+e(o.location)+'</p>':'')+'<div class="ccs-badges">'+(o.status==='cancelled'?badge('cancelled'):pending?badge('pending',pending+' resposta(s) sua(s) pendente(s)'):my.some(s=>same(s.assigned_id,S().me.id)&&s.status==='confirmed')?badge('confirmed','Sua participação confirmada'):badge('neutral',allSlots.filter(s=>s.status==='confirmed').length+' voluntário(s) confirmado(s)'))+
      (missing&&o.status==='published'?badge('available',missing+' departamento(s) aguardando equipe'):'')+(ready?badge('available','Sua equipe pode assumir esta data'):'')+'</div></div><details class="ccs-event-overview"><summary>Departamentos e designações</summary>'+program(o)+'</details><button class="ccs-card-link" type="button" data-action="occurrence" data-id="'+num(o.id)+'" aria-label="Ver evento '+e(o.title)+' de '+e(ctx.stamp(o.starts))+'">Ver evento completo <span aria-hidden="true">→</span></button></article>';
  }
  function home() {
    const list=all().filter(current).sort((a,b)=>a.starts.localeCompare(b.starts)),own=ownEvents().sort((a,b)=>a.starts.localeCompare(b.starts));
    const pending=own.reduce((n,o)=>n+mine(o).filter(s=>same(s.assigned_id,S().me.id)&&s.status==='pending').length,0);
    const invitations=(S().notifications||[]).length, next=own[0]||list[0];
    ctx.content.innerHTML=ctx.heading('JUNTOS PARA SERVIR','Olá, '+S().me.name.split(' ')[0]+'.','Um encontro, todos os departamentos, o mesmo propósito.')+
      '<section class="ccs-hero"><div><span class="ccs-eyebrow">NOSSO PRÓXIMO EVENTO</span><h2>'+e(next?next.title:'Prontos para o próximo encontro')+'</h2><p>'+e(next?ctx.stamp(next.starts):'Os eventos publicados aparecerão aqui.')+'</p></div>'+(next?button('Ver evento','occurrence',next.id,'light'):'')+'</section>'+
      '<div class="ccs-stats"><div><strong>'+own.length+'</strong><span>Eventos em que você serve</span></div><div><strong>'+pending+'</strong><span>Suas respostas pendentes</span></div><div><strong>'+invitations+'</strong><span>Pedidos de troca</span></div></div>'+
      (S().me.can_manage?'<section class="ccs-card ccs-open-callout"><h2>Gestão de evento</h2><p>Selecione uma data e acompanhe equipes, confirmações e tarefas.</p>'+button('Gerenciar um evento','manage_dashboard','','')+'</section>':'')+
      ((S().tasks||[]).some(t=>same(t.assigned_id,S().me.id)&&t.status!=='done'&&t.event_status==='published')?'<section class="ccs-card ccs-open-callout"><h2>Suas tarefas nos eventos</h2><p>Confira os prazos e atualize o andamento.</p>'+button('Minhas tarefas','mine_tasks','','')+'</section>':'')+
      (openEvents().length?'<section class="ccs-card ccs-open-callout"><h2>Há um lugar para você</h2><p>'+openEvents().length+' evento(s) com vagas nas suas equipes.</p>'+button('Escolher uma vaga','open_list','','')+'</section>':'')+
      '<div class="ccs-title-row"><h2>Próximos eventos</h2>'+button('Atualizar','refresh','','text')+'</div><div class="ccs-event-grid">'+(list.map(card).join('')||ctx.empty('Nenhum evento publicado neste período.'))+'</div>';
  }
  const dashboardFilter=ctx.dashboardFilter||{period:'future',month:'',department:'',status:'published'};
  function dashboardData() {
    const allowed=S().me.can_manage,globalView=S().me.pastor||S().me.secretary;
    const rows=allowed?all().map(o=>{
      const requirements=(o.requirements||[]).filter(r=>(globalView||S().departments.some(d=>same(d.id,r.department_id)&&d.can_manage))&&(!dashboardFilter.department||same(r.department_id,dashboardFilter.department)));
      if(!requirements.length)return null;
      const live=o.status==='published',eventIds=new Set(requirements.flatMap(r=>(r.events||[]).filter(ev=>ev.status!=='cancelled'&&ev.status!=='deleted').map(ev=>num(ev.id))));
      const assigned=S().slots.filter(s=>eventIds.has(num(s.event_id))&&s.status!=='withdrawn');
      const missing=live?requirements.reduce((n,r)=>n+(r.required===false?0:Math.max(0,num(r.team_count||1)-(r.events||[]).filter(ev=>ev.status!=='cancelled'&&ev.status!=='deleted').length)),0):0;
      const vacancies=live?assigned.filter(s=>['available','declined'].includes(s.status)).length:0,pending=live?assigned.filter(s=>s.status==='pending').length:0,confirmed=live?assigned.filter(s=>s.status==='confirmed').length:0;
      return {event:o,requirements,missing,vacancies,pending,confirmed,total:live?assigned.length:0,needsAttention:!!(missing||vacancies||pending)};
    }).filter(Boolean).filter(r=>dashboardFilter.period==='all'||(dashboardFilter.period==='past'?r.event.ends<S().now:r.event.ends>=S().now)).filter(r=>!dashboardFilter.month||r.event.starts.slice(0,7)===dashboardFilter.month).filter(r=>dashboardFilter.status==='all'||(dashboardFilter.status==='cancelled'?r.event.status==='cancelled':r.event.status==='published'&&(dashboardFilter.status==='pending'?r.needsAttention:dashboardFilter.status==='ready'?!r.needsAttention:true))).sort((a,b)=>dashboardFilter.period==='past'?b.event.starts.localeCompare(a.event.starts):a.event.starts.localeCompare(b.event.starts)):[];
    const live=rows.filter(r=>r.event.status==='published');
    return {rows,metrics:{events:rows.length,departments:new Set(live.flatMap(r=>r.requirements.map(d=>num(d.department_id)))).size,teams:live.reduce((n,r)=>n+r.missing,0),vacancies:live.reduce((n,r)=>n+r.vacancies,0),pending:live.reduce((n,r)=>n+r.pending,0),confirmed:live.reduce((n,r)=>n+r.confirmed,0)}};
  }
  function eventDashboardData(id) {
    const o=all().find(o=>same(o.id,id));
    const allowed=o&&S().me.can_manage&&(o.can_dashboard??(S().me.pastor||S().me.secretary||(o.requirements||[]).some(r=>S().departments.some(d=>same(d.id,r.department_id)&&d.can_manage))));
    if(!allowed)return null;
    const requirements=o.requirements||[],live=o.status==='published',events=requirements.flatMap(r=>r.events||[]).filter(ev=>ev.status==='published'),ids=new Set(events.map(ev=>num(ev.id)));
    const list=S().slots.filter(s=>ids.has(num(s.event_id))&&s.status!=='withdrawn');
    return {event:o,requirements,slots:list,metrics:{departments:requirements.length,teams:live?events.length:0,missing:live?requirements.reduce((n,r)=>n+(r.required===false?0:Math.max(0,num(r.team_count||1)-(r.events||[]).filter(ev=>ev.status==='published').length)),0):0,vacancies:live?list.filter(s=>['available','declined'].includes(s.status)).length:0,pending:live?list.filter(s=>s.status==='pending').length:0,confirmed:live?list.filter(s=>s.status==='confirmed').length:0}};
  }
  function dashboardDepartments(data) {
    const o=data.event;
    return '<section aria-labelledby="ccs-event-teams-title"><h2 id="ccs-event-teams-title">Departamentos e equipes</h2><p class="ccs-hint">Uma equipe com data assumida ainda pode ter voluntários aguardando resposta. As confirmações individuais aparecem em cada equipe.</p>'+data.requirements.map(r=>'<article class="ccs-card ccs-dashboard-department"><div class="ccs-title-row"><h3>'+e(r.department_name)+'</h3><span class="ccs-count-pill">'+(r.events||[]).filter(ev=>ev.status==='published').length+'/'+num(r.team_count||1)+' equipes</span></div>'+((r.events||[]).map(ev=>{
      const t=S().teams.find(t=>same(t.id,ev.team_id)),list=S().slots.filter(s=>same(s.event_id,ev.id)&&s.status!=='withdrawn'),confirmed=list.filter(s=>s.status==='confirmed').length;
      return '<section class="ccs-dashboard-team"><div class="ccs-title-row"><h4>'+e(ev.team_name)+'</h4>'+badge(ev.status==='published'?'confirmed':'cancelled',ev.status==='published'?'Data assumida':'Participação cancelada')+'</div><p>Responsável: '+e(t?.leader_id?ctx.user(t.leader_id):'Não definido')+'</p><p class="ccs-hint">'+e(ctx.modeLabel(ev.mode))+' · '+confirmed+'/'+list.length+' participações confirmadas</p>'+((list.length)?'<details class="ccs-team-assignments"><summary>Ver designações ('+list.length+')</summary><ul>'+list.map(slot=>'<li><div><strong>'+e(slot.function_name||(ev.mode==='roster'?'Integrante':'Participação'))+'</strong><span>'+e(slot.assigned_id?ctx.user(slot.assigned_id):'Vaga disponível')+'</span></div>'+badge(slot.status)+'</li>').join('')+'</ul></details>':'<p class="ccs-hint">Nenhuma vaga publicada para esta equipe.</p>')+'</section>';
    }).join('')||'<p>Nenhuma equipe assumiu esta data.</p>')+(r.required!==false&&r.missing_team&&o.status==='published'?'<p class="ccs-task-warning">Falta definir '+num(r.remaining_teams)+' equipe(s).</p>':'')+(r.can_assign?'<div class="ccs-actions">'+button('Definir equipes','assign_occurrence',o.id+':'+r.department_id)+'</div>':'')+'</article>').join('')+'</section>';
  }
  function dashboard() {
    if(!S().me.can_manage)return;
    const data=dashboardData(),selected=eventDashboardData(dashboardFilter.event),expanded=ctx.content.querySelector('[data-dashboard-filters]')?.open;
    const opts=(choices,value)=>choices.map(([id,label])=>'<option value="'+e(id)+'" '+(String(value)===String(id)?'selected':'')+'>'+e(label)+'</option>').join('');
    const departments=S().departments.filter(d=>S().me.pastor||S().me.secretary||d.can_manage);
    const filters='<details class="ccs-dashboard-filters" data-dashboard-filters '+(expanded?'open':'')+'><summary>Encontrar evento ou celebração</summary><div class="ccs-filters">'+ctx.select('Período','dashboard-period',opts([['future','Próximas datas'],['past','Histórico'],['all','Todos os períodos']],dashboardFilter.period))+ctx.input('Mês (opcional)','dashboard-month','month',dashboardFilter.month,false)+ctx.select('Departamento','dashboard-department',opts([['','Todos os departamentos'],...departments.map(d=>[d.id,d.name])],dashboardFilter.department))+ctx.select('Situação','dashboard-status',opts([['published','Publicados'],['pending','Com pendências nas escalas'],['ready','Sem pendências nas escalas'],['cancelled','Cancelados'],['all','Todas as situações']],dashboardFilter.status))+'</div>'+button('Limpar filtros','dashboard_reset','','text')+'</details>';
    const groups=[['Eventos',data.rows.filter(r=>!num(r.event.celebration_id))],['Celebrações',data.rows.filter(r=>num(r.event.celebration_id))]];
    const selector=ctx.select('Selecione o evento ou a data da celebração','dashboard-event','<option value="">Escolha uma data para gerenciar</option>'+groups.filter(([,rows])=>rows.length).map(([label,rows])=>'<optgroup label="'+e(label)+'">'+opts(rows.map(r=>[r.event.id,r.event.title+' · '+ctx.stamp(r.event.starts)+(r.event.event_kind==='special'?' · Especial':'')]),dashboardFilter.event)+'</optgroup>').join(''));
    const intro=(ctx.managementTabs?.()||'')+ctx.heading('PLANEJAMENTO E PARTICIPAÇÃO','Gestão de evento','Escolha uma data para acompanhar sua preparação, equipes e designações.')+'<div class="ccs-actions ccs-management-actions">'+(S().me.can_publish_event?button('＋ Publicar evento','publish_event','',''):'')+button('Equipes e cadastros','manage_schedules')+'</div>'+filters+'<section class="ccs-card ccs-event-selector">'+selector+'</section>';
    if(!selected){ctx.content.innerHTML=intro+'<h2>Escolha um evento</h2><div class="ccs-event-grid">'+(data.rows.map(({event:o})=>'<article class="ccs-card" data-dashboard-event="'+num(o.id)+'"><span class="ccs-eyebrow">'+e(ctx.stamp(o.starts))+' · '+(num(o.celebration_id)?'CELEBRAÇÃO':'EVENTO')+'</span><h3>'+e(o.title)+'</h3><p>'+e(o.location||'')+'</p>'+button('Gerenciar evento','event_dashboard',o.id)+'</article>').join('')||ctx.empty('Nenhum evento encontrado. Ajuste os filtros ou publique uma data.'))+'</div>';return;}
    const o=selected.event,m=selected.metrics,metrics=[['departments','Departamentos'],['teams','Equipes com data assumida'],['missing','Equipes a definir'],['vacancies','Vagas a preencher'],['pending','Respostas pendentes'],['confirmed','Participações confirmadas']];
    const percent=selected.slots.length?Math.round(100*m.confirmed/selected.slots.length):0;
    ctx.content.innerHTML=intro+'<section class="ccs-event-management" data-selected-event="'+num(o.id)+'"><div class="ccs-hero"><div><span class="ccs-eyebrow">'+(o.event_kind==='special'?'EVENTO ESPECIAL':num(o.celebration_id)?'CELEBRAÇÃO':'EVENTO')+'</span><h2>'+e(o.title)+'</h2><p>'+e(ctx.stamp(o.starts))+' até '+e(ctx.stamp(o.ends))+'</p><p>Chegada: '+e(ctx.stamp(o.arrival))+(o.location?' · '+e(o.location):'')+'</p></div></div><div class="ccs-actions ccs-management-actions">'+button('Ver programação e escalas','occurrence',o.id)+(o.can_edit?button('Editar evento','edit_occurrence',o.id):'')+(o.can_plan?button('Configurar tarefas','event_planning',o.id):'')+button('Atualizar','refresh','','text')+'</div>'+(o.status!=='published'?'<p class="ccs-task-warning">Evento cancelado. Dados e tarefas disponíveis somente para consulta.</p>':'')+(o.notes?'<p class="ccs-notes">'+e(o.notes)+'</p>':'')+'<div class="ccs-dashboard-stats" aria-label="Indicadores deste evento">'+metrics.map(([key,label])=>'<div><strong data-dashboard-metric="'+key+'">'+m[key]+'</strong><span>'+e(label)+'</span></div>').join('')+'</div><p class="ccs-hint">'+m.confirmed+' de '+selected.slots.length+' participações confirmadas nas vagas publicadas. Vagas a preencher incluem recusas aguardando substituição.</p><progress class="ccs-task-progress" max="100" value="'+percent+'" aria-label="Participações confirmadas deste evento"></progress>'+(ctx.taskViews?.board(o)||'')+dashboardDepartments(selected)+'</section>';
  }
  function mineList() {
    const source=ctx.getMineSection()==='open'?openEvents():all().filter(o=>mine(o).length);
    const q=ctx.filter.q.toLocaleLowerCase('pt-BR');
    const list=sorted(source.filter(o=>ctx.filter.period==='future'?o.ends>=S().now:o.ends<S().now).filter(o=>(o.title+' '+(o.requirements||[]).map(r=>r.department_name+' '+(r.events||[]).map(ev=>ev.team_name).join(' ')).join(' ')).toLocaleLowerCase('pt-BR').includes(q)));
    ctx.content.querySelector('#ccs-events').innerHTML=list.map(card).join('')||ctx.empty('Nenhum evento encontrado neste período.');
  }
  function managed() {
    const list=sorted(all().filter(o=>S().me.secretary||o.can_manage||(o.requirements||[]).some(r=>r.can_assign||(r.events||[]).some(ev=>ev.can_manage))).filter(o=>ctx.filter.period==='future'?o.ends>=S().now:o.ends<S().now).filter(o=>!ctx.filter.team||(o.requirements||[]).some(r=>(r.events||[]).some(ev=>same(ev.team_id,ctx.filter.team))||(r.claimable_teams||[]).some(t=>same(t.id,ctx.filter.team)))));
    ctx.content.querySelector('#ccs-managed').innerHTML=list.map(card).join('')||ctx.empty('Nenhum evento encontrado.');
  }
  function teamOpportunities() {
    const list=all().filter(o=>current(o)&&(o.requirements||[]).some(r=>r.can_assume_department||(r.claimable_teams||[]).length));
    return list.length?'<section class="ccs-team-opportunities"><h2>Datas disponíveis para assumir</h2><p>Escolha um evento e confirme a participação da sua equipe.</p><div class="ccs-event-grid">'+list.map(card).join('')+'</div></section>':'';
  }
  function participation(ev,o) {
    const t=S().teams.find(t=>same(t.id,ev.team_id)),canAct=o.status==='published'&&ev.status==='published'&&ev.starts>S().now&&!S().me.secretary;
    const list=S().slots.filter(s=>same(s.event_id,ev.id));
    const rows=list.map(s=>{
      const own=same(s.assigned_id,S().me.id),invitation=S().invites.find(i=>same(i.slot_id,s.id)&&i.status==='pending');
      let actions='';
      if(canAct&&s.status==='available'&&s.can_claim) actions+=button('Assumir esta vaga','claim',s.id,'');
      if(canAct&&s.status==='available'&&ev.can_delegate) actions+=button('Designar voluntário','delegate',s.id);
      if(canAct&&own) {
        if(s.status!=='confirmed') actions+=button('Confirmar participação','confirm',s.id,'');
        actions+=s.status!=='declined'?button('Não posso participar','decline',s.id):button(invitation?'Escolher outro substituto':'Solicitar troca','invite',s.id);
        if(['open','functions'].includes(ev.mode)) actions+=button('Liberar minha vaga','release_slot',s.id,'text');
      }
      return '<article class="ccs-slot"><h4>'+e(s.function_name||(ev.mode==='roster'?'Integrante da equipe':'Participação'))+'</h4><div class="ccs-slot-head"><strong>'+e(s.status==='available'?'Vaga disponível':ctx.user(s.assigned_id))+(own?' · você':'')+'</strong>'+badge(s.status)+'</div>'+
        (num(s.original_id)&&!same(s.original_id,s.assigned_id)?'<p>Substituindo '+e(ctx.user(s.original_id))+'</p>':'')+
        (invitation?'<p>Convite aguardando '+e(ctx.user(invitation.to_id))+'</p>':'')+
        (actions?'<div class="ccs-actions">'+actions+'</div>':'')+'</article>';
    }).join('');
    const history=(S().audit||[]).filter(a=>same(a.event_id,ev.id));
    return '<section class="ccs-team-participation"><div class="ccs-team-participation-heading"><h4>'+e(ev.team_name)+'</h4><span>'+e(ctx.modeLabel(ev.mode))+'</span></div>'+
      (ev.notes?'<p class="ccs-notes">'+e(ev.notes)+'</p>':'')+rows+
      (canAct&&ev.can_delegate?'<div class="ccs-actions">'+button('Ajustar vagas por função','vacancies',ev.id)+'</div>':'')+
      (canAct&&ev.can_manage?'<div class="ccs-actions ccs-team-tools">'+button('Orientações desta equipe','edit_schedule',ev.id)+button('Liberar participação da equipe','release_event_team',ev.id,'text')+(t?.is_department&&t.can_manage?button('Organizar participantes','team',t.id,'text'):'')+'</div>':'')+
      (history.length?'<details class="ccs-history"><summary>Histórico desta equipe</summary>'+history.map(a=>'<p><strong>'+e(a.actor_name)+'</strong> · '+e(a.action)+'<br>'+e(ctx.stamp(a.created_at))+'</p>').join('')+'</details>':'')+'</section>';
  }
  function details(id) {
    const o=all().find(o=>same(o.id,id));if(!o){ctx.notify('Este evento não está mais disponível.',true);return;}
    ctx.setOpened(num(id));
    const sections=(o.requirements||[]).map(r=>'<section class="ccs-requirement"><div class="ccs-title-row"><h3>'+e(r.department_name)+'</h3>'+badge(r.missing_team?'available':'confirmed',(r.events||[]).length+'/'+r.team_count+' equipes')+'</div>'+
      (r.events||[]).map(ev=>participation(ev,o)).join('')+
      (r.missing_team&&o.status==='published'?'<p class="ccs-hint">Aguardando '+num(r.remaining_teams)+' participação(ões) de equipe.</p>':'')+
      '<div class="ccs-actions">'+(r.claimable_teams||[]).map(t=>button(t.is_department?'Assumir pelo departamento':'Assumir com '+t.name,'claim_event_team',o.id+':'+t.id,'')).join('')+
      (r.can_assume_department?button('Assumir pelo departamento','claim_event_department',o.id+':'+r.department_id,''):'')+
      (r.can_assign?button('Definir equipes deste departamento','assign_occurrence',o.id+':'+r.department_id,'text'):'')+'</div></section>').join('');
    ctx.open(o.title,'<div class="ccs-info"><strong>'+e(ctx.stamp(o.starts))+'</strong><span>Chegada: '+e(ctx.stamp(o.arrival))+'</span><span>Término: '+e(ctx.stamp(o.ends))+'</span>'+(o.location?'<span>'+e(o.location)+'</span>':'')+'</div>'+
      (o.notes?'<p class="ccs-notes">'+e(o.notes)+'</p>':'')+(o.status==='cancelled'?badge('cancelled'):'')+
      '<p class="ccs-hint">Todos podem acompanhar o evento completo. As ações disponíveis correspondem à sua participação ou liderança.</p>'+sections+
      (o.can_dashboard?'<div class="ccs-actions">'+button('Painel de gestão deste evento','event_dashboard',o.id)+'</div>':'')+
      (o.can_edit?'<div class="ccs-actions">'+button('Editar evento','edit_occurrence',o.id)+button('Cancelar evento','cancel_occurrence',o.id,'danger')+'</div>':'')+
      (o.can_manage?'<div class="ccs-delete-zone">'+button('Excluir evento','delete_item','occurrence:'+o.id,'danger')+'</div>':''));
    ctx.dialog.classList.add('ccs-event-dialog');
  }
  function departmentChoices(o) {
    return '<fieldset><legend>Departamentos necessários</legend><p>Selecione os departamentos. Informe quantas equipes precisam participar de cada um.</p>'+S().departments.filter(d=>d.can_manage).map(d=>{
      const r=o?.requirements?.find(r=>same(r.department_id,d.id));
      return '<div class="ccs-event-department-choice" data-event-department="'+num(d.id)+'"><label class="ccs-check"><input type="checkbox" data-event-enabled '+(r?'checked':'')+'>'+e(d.name)+'</label><label class="ccs-field">Equipes<input type="number" data-event-count min="1" max="30" value="'+num(r?.team_count||1)+'" aria-label="Equipes necessárias de '+e(d.name)+'"></label></div>';
    }).join('')+'</fieldset>';
  }
  function collectRequirements(f) { return Array.from(f.querySelectorAll('[data-event-department]')).filter(row=>row.querySelector('[data-event-enabled]').checked).map(row=>({department_id:num(row.dataset.eventDepartment),team_count:num(row.querySelector('[data-event-count]').value)})); }
  function publishForm() {
    if(!S().me.can_publish_event)return;
    if(!S().departments.some(d=>d.can_manage)){ctx.notify('Cadastre os departamentos antes de publicar o evento.',true);return;}
    ctx.open('Publicar evento',ctx.form('publish_event',ctx.input('Nome do evento','title','text','',true,'maxlength="150"')+ctx.input('Local','location','text','',false,'maxlength="200"')+
      ctx.input('Chegada das equipes','arrival','datetime-local')+ctx.input('Início','starts','datetime-local')+ctx.input('Término','ends','datetime-local')+departmentChoices(null)+
      ctx.select('Formato de gestão','event_kind','<option value="regular">Evento ou celebração regular</option><option value="special">Evento especial · tarefas e Kanban</option>')+
      ctx.input('Quantidade de datas','repeat','number',1,true,'min="1" max="12"')+ctx.select('Intervalo','interval','<option value="7">Semanal</option><option value="14">Quinzenal</option><option value="28">A cada quatro semanas</option>')+
      '<label class="ccs-field">Orientações do evento<textarea name="notes" rows="3" maxlength="3000"></textarea></label><p class="ccs-hint">Cada data aparece uma única vez. As lideranças assumem a participação e a convocação usa o formato cadastrado da equipe.</p>',0,'Publicar evento'));
  }
  function editForm(id) {
    const o=all().find(o=>same(o.id,id));if(!o?.can_edit)return;
    const dt=v=>v.replace(' ','T').slice(0,16);
    ctx.open('Editar evento',ctx.form('edit_occurrence','<input type="hidden" name="fingerprint" value="'+e(o.fingerprint)+'">'+ctx.input('Nome','title','text',o.title,true,'maxlength="150"')+ctx.input('Local','location','text',o.location,false,'maxlength="200"')+
      ctx.input('Chegada','arrival','datetime-local',dt(o.arrival))+ctx.input('Início','starts','datetime-local',dt(o.starts))+ctx.input('Término','ends','datetime-local',dt(o.ends))+departmentChoices(o)+
      '<label class="ccs-field">Orientações do evento<textarea name="notes" rows="3" maxlength="3000">'+e(o.notes)+'</textarea></label><p class="ccs-hint">Mudanças de horário são aplicadas a todas as equipes e pedem nova confirmação dos participantes. Libere as equipes de um departamento antes de removê-lo.</p>',id,'Salvar evento'));
  }
  function claimForm(raw,department) {
    const [id,target]=String(raw).split(':').map(num),o=all().find(o=>same(o.id,id));if(!o)return;
    const t=department?null:S().teams.find(t=>same(t.id,target));
    ctx.open('Assumir esta data',ctx.form(department?'claim_event_department':'claim_event_team','<input type="hidden" name="'+(department?'department_id':'team_id')+'" value="'+target+'"><p><strong>'+e(o.title)+'</strong><br>'+e(ctx.stamp(o.starts))+'</p>'+
      (t?'<p>'+e(t.name)+' · '+e(ctx.modeLabel(t.mode))+'</p><p>A participação seguirá o formato cadastrado desta equipe.</p>':'<p>O departamento terá uma participação direta, inicialmente com uma vaga aberta. Você poderá organizar os participantes e o formato em Equipes.</p>'),id,'Assumir participação'));
  }
  function releaseForm(id) { ctx.open('Liberar participação desta equipe?',ctx.form('release_event_team','<p>As convocações e trocas pendentes desta equipe serão canceladas. O evento e as participações dos outros departamentos continuam.</p>',id,'Liberar participação')); }
  function teamEditForm(id) {
    const ev=S().events.find(ev=>same(ev.id,id));if(!ev?.can_manage)return;
    const dt=v=>v.replace(' ','T').slice(0,16);
    ctx.open('Orientações · '+ev.team_name,ctx.form('edit_schedule','<p>'+e(ev.title)+' · '+e(ctx.stamp(ev.starts))+'</p>'+
      ['title','location'].map(k=>'<input type="hidden" name="'+k+'" value="'+e(ev[k])+'">').join('')+
      ['arrival','starts','ends'].map(k=>'<input type="hidden" name="'+k+'" value="'+e(dt(ev[k]))+'">').join('')+
      '<label class="ccs-field">Orientações da equipe<textarea name="notes" rows="3" maxlength="3000">'+e(ev.notes)+'</textarea></label><p class="ccs-hint">O nome, o local e os horários são definidos na edição do evento.</p>',id,'Salvar orientações'));
  }
  function setFeed(feed) { S().inbox=Array.isArray(feed)?{requests:feed,items:[],unread:0}:feed;S().notifications=S().inbox.requests||[]; }
  function updateBell() {
    const bell=ctx.root.querySelector('.ccs-bell');if(!bell)return;
    const count=(S()?.notifications?.length||0)+(S()?.inbox?.unread||0);
    bell.setAttribute('aria-label','Notificações: '+count+' aviso(s) ou pedido(s) pendente(s)');
    const tag=bell.querySelector('.ccs-bell-count');tag.textContent=count>99?'99+':String(count);tag.hidden=!count;
  }
  async function notificationModal() {
    try {
      setFeed(await ctx.api('notifications'));updateBell();ctx.setOpened(0);
      const f=S().inbox,requests=f.requests||[],items=(f.items||[]).filter(n=>n.type!=='replacement_requested'||!requests.some(i=>same(i.id,n.invite_id)));
      const html=requests.map(i=>'<article class="ccs-notification"><span class="ccs-eyebrow">PEDIDO DE TROCA</span><h3>'+e(i.from_name)+' precisa de você</h3><p><strong>'+e(i.function_name||'Substituição')+'</strong> · '+e(i.team_name)+'</p><p>'+e(i.title)+'<br>'+e(ctx.stamp(i.starts))+'</p><div class="ccs-actions">'+button('Aceitar troca','accept',i.id,'')+button('Não posso','reject',i.id)+'</div></article>').join('')+
        (items.length?'<div class="ccs-title-row"><h3>Seus avisos</h3>'+button('Marcar como lidos','notices_read','','text')+'</div>':'')+
        items.map(n=>'<article class="ccs-notification '+(n.read_at?'':'ccs-notification-unread')+'"><h3>'+e(n.title)+'</h3><p>'+e(n.body)+'</p><p class="ccs-hint">'+e(ctx.stamp(n.created_at))+'</p>'+button(n.type.startsWith('task_')&&new URL(n.url,location.href).searchParams.get('ccs_task')?'Ver tarefa':'Ver evento','notice_open',n.id,'text')+'</article>').join('');
      ctx.open('Notificações',html||'<p>Você não tem avisos ou pedidos de troca.</p>');
    } catch(error){ctx.notify(error.message,true);}
  }
  async function openNotice(id) {
    const n=S().inbox?.items?.find(n=>same(n.id,id));if(!n)return;
    try { if(!n.read_at)await ctx.api('action',{action:'notice_read',ids:[num(n.id)]});await ctx.load();const task=n.type.startsWith('task_')?num(new URL(n.url,location.href).searchParams.get('ccs_task')):0;if(task){ctx.taskViews?.openTask(task);}else details(n.occurrence_id); } catch(error){ctx.notify(error.message,true);}
  }
  async function readNotices() {
    const ids=(S().inbox?.items||[]).filter(n=>!n.read_at).map(n=>num(n.id));
    if(!ids.length&&!S().inbox?.unread)return;
    try{await ctx.api('action',{action:'notice_read',all:true});await notificationModal();}catch(error){ctx.notify(error.message,true);}
  }
  function parkPush() {
    const panel=document.getElementById('ccs-push-panel'),parking=document.getElementById('ccs-push-parking');
    if(panel&&parking){parking.append(panel);parking.hidden=true;}
  }
  function profilePush() {
    const target=ctx.content.querySelector('.ccs-profile-layout')?.lastElementChild,panel=document.getElementById('ccs-push-panel');
    if(target&&panel)target.prepend(panel);
    else if(target)target.insertAdjacentHTML('afterbegin','<div class="ccs-card"><h2>Avisos do aplicativo</h2><p>Você pode acompanhar seus avisos pelo sino no canto superior da tela.</p></div>');
  }
  return {all,card,details,home,dashboard,dashboardData,eventDashboardData,mineList,managed,teamOpportunities,openEvents,ownEvents,publishForm,editForm,claimForm,releaseForm,teamEditForm,collectRequirements,setFeed,updateBell,notificationModal,openNotice,readNotices,parkPush,profilePush};
};
