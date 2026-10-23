/* Event preparation board. Every mutation is checked again by WordPress. */
window.CCS_TASK_VIEWS = function(ctx) {
  'use strict';
  const S=ctx.getState,e=ctx.e,num=ctx.num,same=ctx.same,button=ctx.button;
  const stages=[['todo','A fazer'],['doing','Em andamento'],['done','Concluído']];
  const filters={assignee:'',department:''};
  const tasks=id=>(S().tasks||[]).filter(t=>same(t.occurrence_id,id));
  const overdue=t=>t.event_status==='published'&&t.status!=='done'&&t.due_at<S().now;
  const options=(items,value)=>items.map(([id,label])=>'<option value="'+e(id)+'" '+(String(value)===String(id)?'selected':'')+'>'+e(label)+'</option>').join('');
  const metrics=id=>{const list=tasks(id);return {total:list.length,done:list.filter(t=>t.status==='done').length,doing:list.filter(t=>t.status==='doing').length,overdue:list.filter(overdue).length};};
  function moveButtons(t) {
    if(!t.can_move)return '';
    return '<div class="ccs-actions ccs-task-moves">'+stages.filter(([key])=>key!==t.status).map(([key,label])=>button(key==='done'?'Concluir':key==='doing'?'Iniciar':'Voltar a fazer','task_move',t.id+':'+key,'text')).join('')+'</div>';
  }
  function card(t,personal=false) {
    return '<article class="ccs-task-card '+(overdue(t)?'ccs-task-overdue':'')+'" data-task-card="'+num(t.id)+'" '+(t.can_move?'draggable="true"':'')+'><span class="ccs-eyebrow">'+e(personal?t.event_title:t.department_name)+'</span><h4>'+e(t.title)+'</h4><p>'+e(t.assigned_name)+(same(t.assigned_id,S().me.id)?' · você':'')+'</p>'+(t.team_name?'<p>'+e(t.team_name)+'</p>':'')+'<p class="ccs-task-deadline">Prazo: '+e(ctx.stamp(t.due_at))+(overdue(t)?' <strong>Atrasada</strong>':'')+'</p>'+(personal?'<p class="ccs-hint">'+e(stages.find(([key])=>key===t.status)?.[1])+(t.event_status==='cancelled'?' · Evento cancelado':'')+'</p>':'')+'<div class="ccs-actions">'+button('Ver tarefa','task_details',t.id,'text')+'</div>'+moveButtons(t)+'</article>';
  }
  function board(o) {
    const list=tasks(o.id),m=metrics(o.id),canCreate=o.status==='published'&&o.event_kind==='special'&&!S().me.secretary&&(o.can_plan||(o.task_departments||[]).length);
    if(filters.assignee&&!list.some(t=>same(t.assigned_id,filters.assignee)))filters.assignee='';
    if(filters.department&&!list.some(t=>same(t.department_id,filters.department)))filters.department='';
    if(o.event_kind!=='special'&&!list.length)return o.can_plan?'<section class="ccs-card"><h2>Preparação do evento</h2><p>Eventos especiais podem ter tarefas com responsáveis, prazos e quadro Kanban.</p>'+button('Ativar tarefas e Kanban','event_planning',o.id)+'</section>':'';
    const selected=list.filter(t=>(!filters.assignee||same(t.assigned_id,filters.assignee))&&(!filters.department||same(t.department_id,filters.department)));
    return '<section class="ccs-event-tasks" aria-labelledby="ccs-tasks-title"><div class="ccs-title-row"><h2 id="ccs-tasks-title">Tarefas do evento · Kanban</h2>'+(canCreate?button('＋ Nova tarefa','task_new',o.id,''):'')+'</div><p class="ccs-hint">'+(o.status!=='published'||o.event_kind!=='special'?'Quadro somente para consulta.':'Arraste os cartões no computador ou use os botões para alterar a etapa. Prazos no horário da igreja.')+'</p><div class="ccs-task-stats">'+[['total','Tarefas'],['doing','Em andamento'],['done','Concluídas'],['overdue','Atrasadas']].map(([key,label])=>'<div><strong data-task-metric="'+key+'">'+m[key]+'</strong><span>'+e(label)+'</span></div>').join('')+'</div><p class="ccs-hint">'+m.done+' de '+m.total+' tarefas concluídas</p><progress class="ccs-task-progress" max="'+Math.max(1,m.total)+'" value="'+m.done+'" aria-label="Conclusão de tarefas"></progress><div class="ccs-filters ccs-task-filters">'+ctx.select('Responsável','task-filter-assignee',options([['','Todos os responsáveis'],...Array.from(new Map(list.map(t=>[num(t.assigned_id),t.assigned_name]))).sort((a,b)=>a[1].localeCompare(b[1],'pt-BR'))],filters.assignee))+ctx.select('Departamento da tarefa','task-filter-department',options([['','Todos os departamentos'],...Array.from(new Map(list.map(t=>[num(t.department_id),t.department_name])))],filters.department))+'</div><div class="ccs-kanban" aria-label="Quadro de tarefas">'+stages.map(([key,label])=>{const column=selected.filter(t=>t.status===key);return '<section class="ccs-kanban-column" data-task-column="'+key+'" aria-label="'+e(label)+'"><h3>'+e(label)+' <span>'+column.length+'</span></h3>'+column.map(t=>card(t)).join('')+(!column.length?'<p class="ccs-hint ccs-kanban-empty">Nenhuma tarefa nesta etapa.</p>':'')+'</section>';}).join('')+'</div></section>';
  }
  function openTask(id) {
    const t=(S().tasks||[]).find(t=>same(t.id,id));if(!t){ctx.notify('Esta tarefa foi retirada ou não está disponível para sua conta.',true);return;}
    ctx.setOpened(0);
    ctx.open(t.title,'<p class="ccs-eyebrow">'+e(t.event_title)+'</p><p>'+e(t.department_name)+(t.team_name?' · '+e(t.team_name):'')+'</p><div class="ccs-info"><strong>'+e(stages.find(([key])=>key===t.status)?.[1])+'</strong><span>Responsável: '+e(t.assigned_name)+'</span><span>Prazo: '+e(ctx.stamp(t.due_at))+'</span></div>'+(overdue(t)?'<p class="ccs-task-warning">Esta tarefa está atrasada.</p>':'')+(t.description?'<p class="ccs-notes">'+e(t.description)+'</p>':'')+'<p class="ccs-hint">Última atualização: '+e(t.updated_name)+' · '+e(ctx.stamp(t.updated_at))+'</p>'+moveButtons(t)+(t.can_edit?'<div class="ccs-actions">'+button('Editar tarefa','task_edit',t.id)+button('Excluir tarefa','task_delete',t.id,'danger')+'</div>':'')+'<div class="ccs-actions">'+button('Ver programação do evento','occurrence',t.occurrence_id,'text')+'</div>');
  }
  function editor(occurrence,id=0) {
    const o=(S().occurrences||[]).find(o=>same(o.id,occurrence)),t=(S().tasks||[]).find(t=>same(t.id,id));
    if(!o||o.status!=='published'||o.event_kind!=='special'||S().me.secretary||(id&&!t?.can_edit))return;
    ctx.setOpened(0);
    const deps=(o.requirements||[]).filter(r=>(o.task_departments||[]).some(id=>same(id,r.department_id)));
    const department=t?.department_id??(o.can_plan?0:deps[0]?.department_id??'');
    ctx.open(t?'Editar tarefa':'Nova tarefa',ctx.form('task_save','<input type="hidden" name="occurrence_id" value="'+num(o.id)+'"><input type="hidden" name="version" value="'+num(t?.version||0)+'"><p><strong>'+e(o.title)+'</strong> · '+e(ctx.stamp(o.starts))+'</p>'+ctx.input('Título','title','text',t?.title||'',true,'maxlength="150"')+'<label class="ccs-field">Descrição<textarea name="description" rows="4" maxlength="5000">'+e(t?.description||'')+'</textarea></label>'+ctx.select('Departamento','department_id',options([...(o.can_plan?[[0,'Organização geral']]:[]),...deps.map(r=>[r.department_id,r.department_name])],department))+'<div data-task-team-field></div>'+ctx.select('Responsável','assigned_id','<option value="">Selecione uma pessoa</option>'+options(S().users.filter(u=>u.active&&u.can_serve!==false).map(u=>[u.id,u.name]),t?.assigned_id||''))+ctx.input('Prazo','due_at','datetime-local',t?.due_at.replace(' ','T').slice(0,16)||'',true)+ctx.select('Etapa','status',options(stages,t?.status||'todo')),id,'Salvar tarefa'));
    updateTeams(t?.team_id||0);
  }
  function updateTeams(selected=0) {
    const field=ctx.dialog.querySelector('[data-task-team-field]'),dep=ctx.dialog.querySelector('[name="department_id"]')?.value;
    if(field)field.innerHTML=ctx.select('Equipe (opcional)','team_id',options([[0,'Sem equipe específica'],...S().teams.filter(t=>same(t.department_id,dep)).map(t=>[t.id,t.name])],selected));
  }
  function planning(id) {
    const o=(S().occurrences||[]).find(o=>same(o.id,id));if(!o?.can_plan)return;ctx.setOpened(0);
    ctx.open('Preparação deste evento',ctx.form('event_planning','<input type="hidden" name="previous_kind" value="'+e(o.event_kind||'regular')+'"><p>'+e(o.title)+' · '+e(ctx.stamp(o.starts))+'</p>'+ctx.select('Formato de gestão','event_kind',options([['regular','Evento ou celebração regular'],['special','Evento especial · tarefas e Kanban']],o.event_kind||'regular'))+'<p class="ccs-hint">A configuração vale somente para esta data. Não altera escalas, confirmações ou outras datas da celebração. Tarefas pendentes precisam ser concluídas ou excluídas antes de desativar o quadro.</p>',id,'Salvar configuração'));
  }
  function deletion(id) {
    const t=(S().tasks||[]).find(t=>same(t.id,id));if(!t?.can_delete)return;ctx.setOpened(0);
    ctx.open('Excluir tarefa?',ctx.form('task_delete','<input type="hidden" name="version" value="'+num(t.version)+'"><p>'+e(t.title)+'</p><p>A tarefa será retirada do quadro. As escalas e demais tarefas permanecem.</p><label class="ccs-check"><input type="checkbox" name="confirm" required>Confirmo a exclusão desta tarefa</label>',id,'Excluir tarefa'));
  }
  function personal() {
    const list=(S().tasks||[]).filter(t=>same(t.assigned_id,S().me.id)).sort((a,b)=>(a.status==='done')-(b.status==='done')||a.due_at.localeCompare(b.due_at));
    return '<h2>Minhas tarefas</h2><p>Preparação dos eventos em que você é responsável. Atualize a etapa pelos botões.</p><div class="ccs-event-grid">'+(list.map(t=>card(t,true)).join('')||ctx.empty('Nenhuma tarefa foi atribuída a você.'))+'</div>';
  }
  function filterChange(name,value) { const key=name==='task-filter-assignee'?'assignee':'department';filters[key]=value;ctx.refresh(); }
  return {tasks,metrics,board,openTask,editor,updateTeams,planning,deletion,personal,filterChange};
};
