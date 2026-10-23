const fixture=require('./event-fixture.cjs');
module.exports=function convocationFixture(role='volunteer'){
  const s=fixture(role),t=s.teams[0],ev=s.events[0];
  t.name='Equipe pastoral';t.mode='assigned';t.functions[0].name='Pregação';t.functions[1].name='Direção do culto';
  t.members=[1,2,4];s.functions[0].name='Pregação';s.functions[1].name='Direção do culto';
  s.users[0].name='Pastor João';s.users[0].function_ids=[30];s.users[1].name='Pastor André';s.users[1].function_ids=[30,31];s.users[3].name='Pastor sem função';s.users[3].function_ids=[];
  s.departments[0].name='Pastoral';s.occurrences[0].requirements[0].department_name='Pastoral';
  ev.team_name='Equipe pastoral';ev.mode='assigned';ev.can_claim=false;ev.can_delegate=role==='admin'||role==='department'||role==='leader';
  s.slots.filter(slot=>slot.event_id===40).forEach(slot=>{slot.original_id=0;slot.assigned_id=0;slot.status='available';slot.can_claim=false;slot.function_name=slot.function_id===30?'Pregação':'Direção do culto';if(ev.can_delegate)slot.convocation_fingerprint='convocation-'+slot.id+'-0';});
  return s;
};
