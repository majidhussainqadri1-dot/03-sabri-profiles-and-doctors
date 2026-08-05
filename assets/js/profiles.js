(function(){
  'use strict';
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.spd-form').forEach(function(form){
      form.addEventListener('submit',function(){
        var button=form.querySelector('button[type="submit"]');
        if(button){button.disabled=true;button.setAttribute('aria-busy','true');}
      });
    });
  });
}());
