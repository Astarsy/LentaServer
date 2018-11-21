var main_page=(function(){
    var navs,lents;
    return {
        diactivate:function(navs){
            [].map.call(navs,function (i) {
                i.classList.remove('active');
            });
        },
        init:function() {
            navs=document.getElementsByClassName('_nav');
            lents=document.getElementsByClassName('_lent');

            for(var i=0;i<navs.length;i++){
                navs[i].diactivate=this.diactivate;
                navs[i].addEventListener('click',function (e) {
                    e.target.diactivate(navs);
                    e.target.diactivate(lents);
                    e.target.classList.add('active');
                    var alcn=e.target.getAttribute('value');
                    var ale=document.getElementsByClassName(alcn)[0];
                    ale.classList.add('active');
                    // console.dir(e.target.getAttribute('value'));
                });
            }
        }
    }
})();
main_page.init();