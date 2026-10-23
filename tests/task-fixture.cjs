const fixture=require('./event-fixture.cjs');
module.exports=function taskFixture(role='admin') {
  const s=fixture(role),root=s.occurrences[0];root.event_kind='special';
  s.occurrences.push({...root,id:51,title:'Outro evento',requirements:[{...root.requirements[0],events:[],missing_team:true,remaining_teams:1}]});
  s.occurrences.push({...root,id:55,celebration_id:8,event_kind:'regular',title:'Celebração semanal',requirements:[{...root.requirements[0],events:[],missing_team:true,remaining_teams:1}]});
  s.tasks=[
    {id:201,occurrence_id:50,department_id:20,department_name:'Artes',team_id:10,team_name:'Música 1',title:'Montar palco <seguro>',description:'Preparar cabos e instrumentos.',assigned_id:1,assigned_name:'Ana <Batera>',due_at:'2026-10-20 18:00:00',status:'todo'},
    {id:202,occurrence_id:50,department_id:21,department_name:'Comunicação',team_id:11,team_name:'Transmissão',title:'Testar transmissão',description:'Conferir sinal.',assigned_id:2,assigned_name:'Bruno Transmissão',due_at:'2030-09-26 18:00:00',status:'doing'},
    {id:203,occurrence_id:50,department_id:0,department_name:'Organização geral',team_id:0,team_name:'',title:'Reservar espaço',description:'',assigned_id:3,assigned_name:'Líder Música',due_at:'2026-10-19 18:00:00',status:'done'},
    {id:204,occurrence_id:51,department_id:20,department_name:'Artes',team_id:10,team_name:'Música 1',title:'Outra data não mistura',description:'',assigned_id:1,assigned_name:'Ana <Batera>',due_at:'2030-09-25 18:00:00',status:'todo'}
  ].map(t=>{const manager=role==='admin'||(role==='department'&&t.department_id===20);return {...t,event_title:t.occurrence_id===50?root.title:'Outro evento',event_starts:root.starts,event_status:'published',version:1,created_by:6,updated_by:6,created_name:'Administrador',updated_name:'Administrador',created_at:'2026-10-22 09:00:00',updated_at:'2026-10-22 09:00:00',can_edit:manager,can_delete:manager,can_move:manager||(!s.me.secretary&&t.assigned_id===s.me.id)};});
  if(!s.me.can_manage)s.tasks=s.tasks.filter(t=>t.assigned_id===s.me.id);
  return s;
};
