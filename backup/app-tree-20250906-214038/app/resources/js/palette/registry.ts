export const registry = [
  { id:'user.create',    label:'user create',    aliases:['user add','add user'] , needs:['name','email','password?','system_role?'] },
  { id:'user.delete',    label:'user delete',    aliases:['user remove','remove user'], needs:['email'] },
  { id:'company.create', label:'company create', aliases:['company add','add company'], needs:['name'] },
  { id:'company.delete', label:'company delete', aliases:['company remove','remove company'], needs:['company'] },
  { id:'company.assign', label:'company assign', aliases:['assign user','assign company'], needs:['email','company','role'] },
  { id:'company.unassign',label:'company unassign',aliases:['unassign user','remove from company'], needs:['email','company'] },
];
