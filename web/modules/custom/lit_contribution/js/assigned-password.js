(function (Drupal, once, settings) {
  'use strict';
  Drupal.behaviors.litAssignedPassword = {attach(context) {
    once('lit-assigned-password','[data-generate-password]',context).forEach(button=>{
      const form=button.closest('form'),input=form.querySelector('[data-assigned-password]'),show=form.querySelector('[data-show-password]');
      button.disabled=!(window.isSecureContext && window.crypto?.getRandomValues && settings.litPasswordWords?.length>=128);
      const random=n=>{const limit=Math.floor(4294967296/n)*n,bytes=new Uint32Array(1);do{crypto.getRandomValues(bytes);}while(bytes[0]>=limit);return bytes[0]%n;};
      show.addEventListener('change',()=>{input.type=show.checked?'text':'password';});
      button.addEventListener('click',()=>{
        const words=[...settings.litPasswordWords],chosen=[];
        if(!window.isSecureContext || !crypto?.getRandomValues || words.length<128)return;
        for(let i=0;i<10;i++)chosen.push(words.splice(random(words.length),1)[0]);
        chosen[0]=chosen[0][0].toUpperCase()+chosen[0].slice(1);
        input.value=chosen.join('-')+'-'+String(100000+random(900000));
        show.checked=true;input.type='text';input.focus();input.select();
      });
      // No local/session storage, clipboard scraping, network request or server display.
      form.addEventListener('submit',()=>{input.type='password';});
    });
  }};
})(Drupal,once,drupalSettings);
