        </main>
        <footer class="footer">
            <span><?= h(QA_APP_NAME) ?> — Development Version 1.4.8.1</span>
            <span>ระบบประกันคุณภาพครบวงจร + Project Correction/Cancellation + Coverage Matrix สมศ. เชื่อมฐานข้อมูลจริง</span>
        </footer>
    </div>
</div>
<script src="<?= h(qa_url('assets/js/app.js')) ?>"></script>

<script>
(function(){
    var nav=document.getElementById('qaSidebarNav');
    if(!nav)return;
    var groups=nav.querySelectorAll('.nav-group[data-nav-group]');
    var storageKey='qa_sidebar_open_groups_v122';

    function getSaved(){
        try{var raw=localStorage.getItem(storageKey);return raw?JSON.parse(raw):{};}catch(e){return {};}
    }
    function setOpen(group,isOpen){
        var btn=group.querySelector('.nav-group-toggle');
        if(isOpen){group.classList.add('open');if(btn)btn.setAttribute('aria-expanded','true');}
        else{group.classList.remove('open');if(btn)btn.setAttribute('aria-expanded','false');}
    }
    function saveState(){
        var state={};
        for(var i=0;i<groups.length;i++)state[groups[i].getAttribute('data-nav-group')]=groups[i].classList.contains('open');
        try{localStorage.setItem(storageKey,JSON.stringify(state));}catch(e){}
    }

    var saved=getSaved();
    for(var i=0;i<groups.length;i++){
        var group=groups[i];
        var key=group.getAttribute('data-nav-group');
        var hasActive=!!group.querySelector('.nav-item.active');
        if(hasActive)setOpen(group,true);
        else if(Object.prototype.hasOwnProperty.call(saved,key))setOpen(group,!!saved[key]);
        else setOpen(group,false);

        var btn=group.querySelector('.nav-group-toggle');
        if(btn){
            btn.addEventListener('click',(function(g){return function(){setOpen(g,!g.classList.contains('open'));saveState();};})(group));
        }
    }
    saveState();
})();
</script>

</body>
</html>
