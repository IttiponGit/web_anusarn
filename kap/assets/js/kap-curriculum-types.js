(()=>{
  'use strict';
  const curriculum=document.getElementById('sectionCurriculum');
  const name=document.getElementById('sectionName');
  if(!curriculum||!name||name.tagName!=='SELECT')return;
  const filter=()=>{
    for(const node of name.querySelectorAll('optgroup[data-type-curriculum]')){
      node.disabled=String(node.dataset.typeCurriculum)!==String(curriculum.value);
      node.hidden=node.disabled;
    }
    for(const option of name.querySelectorAll('option[data-type-curriculum]')){
      option.disabled=String(option.dataset.typeCurriculum)!==String(curriculum.value);
      option.hidden=option.disabled;
    }
    if(name.selectedOptions[0]?.disabled)name.value='';
  };
  curriculum.addEventListener('change',filter);
  filter();
})();
